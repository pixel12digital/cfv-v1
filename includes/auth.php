<?php
// =====================================================
// SISTEMA DE AUTENTICAÇÃO E SESSÕES
// =====================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class Auth {
    private $db;
    private $maxAttempts;
    private $lockoutTime;
    
    public function __construct() {
        $this->db = db();
        $this->maxAttempts = MAX_LOGIN_ATTEMPTS;
        $this->lockoutTime = LOGIN_TIMEOUT;
        
        // Sessão já foi iniciada no config.php
    }
    
    // Método de login principal
    public function login($login, $senha, $remember = false) {
        try {
            // Validar entrada
            if (empty($login) || empty($senha)) {
                return ['success' => false, 'message' => 'Login e senha são obrigatórios'];
            }
            
            // Verificar se está bloqueado
            if ($this->isLocked($this->getClientIP())) {
                return ['success' => false, 'message' => 'Muitas tentativas de login. Tente novamente em ' . $this->getLockoutTimeRemaining() . ' minutos'];
            }
            
            // Buscar usuário (por email ou CPF)
            $usuario = $this->getUserByLogin($login);
            if (!$usuario) {
                $this->incrementAttempts($this->getClientIP());
                return ['success' => false, 'message' => 'Login ou senha inválidos'];
            }
            
            // Verificar senha (campo pode ser 'senha' ou 'password')
            $senhaHash = $usuario['senha'] ?? $usuario['password'] ?? null;
            if (!$senhaHash || !password_verify($senha, $senhaHash)) {
                $this->incrementAttempts($this->getClientIP());
                return ['success' => false, 'message' => 'Login ou senha inválidos'];
            }
            
            // Verificar se usuário está ativo (campo pode ser 'ativo' ou 'status')
            $ativo = $usuario['ativo'] ?? null;
            if ($ativo === null) {
                // Se não tem campo 'ativo', verificar 'status'
                $status = strtolower($usuario['status'] ?? '');
                if ($status !== 'ativo') {
                    return ['success' => false, 'message' => 'Usuário inativo. Entre em contato com o administrador'];
                }
            } elseif (!$ativo) {
                return ['success' => false, 'message' => 'Usuário inativo. Entre em contato com o administrador'];
            }
            
            // Login bem-sucedido
            $this->createSession($usuario, $remember);
            $this->resetAttempts($this->getClientIP());
            $this->updateLastLogin($usuario['id']);
            
            // Log de login
            if (AUDIT_ENABLED) {
                try {
                    // Verificar se a tabela logs existe antes de tentar inserir
                    $this->db->query("SHOW TABLES LIKE 'logs'");
                    dbLog($usuario['id'], 'login', 'usuarios', $usuario['id']);
                } catch (Exception $e) {
                    // Ignorar erros de log por enquanto
                    if (LOG_ENABLED) {
                        error_log('Erro ao registrar log: ' . $e->getMessage());
                    }
                }
            }
            
            return [
                'success' => true, 
                'message' => 'Login realizado com sucesso',
                'user' => $this->getUserData($usuario['id'])
            ];
            
        } catch (Exception $e) {
            if (LOG_ENABLED) {
                error_log('Erro no login: ' . $e->getMessage());
            }
            return ['success' => false, 'message' => 'Erro interno do sistema'];
        }
    }
    
    // Método de logout
    public function logout() {
        // Obter user_id ANTES de limpar a sessão
        $user_id = $_SESSION['user_id'] ?? null;
        
        if ($user_id && defined('AUDIT_ENABLED') && AUDIT_ENABLED) {
            try {
                dbLog($user_id, 'logout', 'usuarios', $user_id);
            } catch (Exception $e) {
                // Ignorar erros de log por enquanto
                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log('Erro ao registrar log de logout: ' . $e->getMessage());
                }
            }
        }
        
        // Remover cookies de "lembrar-me" ANTES de destruir a sessão
        if (isset($_COOKIE['remember_token'])) {
            try {
                $this->removeRememberToken($_COOKIE['remember_token']);
            } catch (Exception $e) {
                // Ignorar erros
            }
            $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            @setcookie('remember_token', '', time() - 3600, '/', '', $is_https, true);
            unset($_COOKIE['remember_token']);
        }
        
        // Remover todos os cookies relacionados à sessão ANTES de destruir
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            @setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $is_https, $params["httponly"]
            );
        }
        
        // Remover cookie CFC_SESSION se existir
        if (isset($_COOKIE['CFC_SESSION'])) {
            $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            // Tentar remover com diferentes combinações de parâmetros
            @setcookie('CFC_SESSION', '', time() - 42000, '/', '', $is_https, true);
            if (strpos($host, 'hostingersite.com') !== false) {
                @setcookie('CFC_SESSION', '', time() - 42000, '/', '.hostingersite.com', $is_https, true);
                @setcookie('CFC_SESSION', '', time() - 42000, '/', $host, $is_https, true);
            }
            unset($_COOKIE['CFC_SESSION']);
        }
        
        // Limpar todas as variáveis de sessão ANTES de destruir
        $_SESSION = array();
        
        // Fechar a sessão antes de destruir (importante para garantir limpeza completa)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // Destruir a sessão completamente
        if (function_exists('session_destroy') && session_status() !== PHP_SESSION_NONE) {
            @session_destroy();
        }
        
        // Garantir que não há sessão ativa após destruição
        // Se ainda houver sessão ativa, tentar limpar novamente
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_start();
            $_SESSION = array();
            @session_destroy();
        }
        
        return ['success' => true, 'message' => 'Logout realizado com sucesso'];
    }
    
    // Verificar se usuário está logado
    public function isLoggedIn() {
        // Verificar se a sessão está realmente ativa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        // Verificar sessão primeiro
        if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
            // Verificar timeout
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
            return true;
        }
        
        // Só verificar cookie "lembrar-me" se não há sessão ativa
        // e se o cookie realmente existe
        if (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
            try {
                return $this->validateRememberToken($_COOKIE['remember_token']);
            } catch (Exception $e) {
                // Se houver erro ao validar token, remover cookie e retornar false
                setcookie('remember_token', '', time() - 3600, '/');
                return false;
            }
        }
        
        return false;
    }
    
    // Obter dados do usuário logado
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }
        
        return $this->getUserData($userId);
    }
    
    // Verificar permissões
    public function hasPermission($permission) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // Admin tem todas as permissões
        if ($user['tipo'] === 'admin') {
            return true;
        }
        
        // Verificar permissões específicas por tipo
        $permissions = $this->getUserPermissions($user['tipo']);
        return in_array($permission, $permissions);
    }
    
    // Verificar se é admin
    public function isAdmin() {
        $user = $this->getCurrentUser();
        return $user && $user['tipo'] === 'admin';
    }
    
    // Verificar se é instrutor
    public function isInstructor() {
        $user = $this->getCurrentUser();
        return $user && $user['tipo'] === 'instrutor';
    }
    
    // Verificar se é secretaria
    public function isSecretary() {
        $user = $this->getCurrentUser();
        return $user && $user['tipo'] === 'secretaria';
    }
    
    // Verificar se é aluno
    public function isStudent() {
        $user = $this->getCurrentUser();
        return $user && $user['tipo'] === 'aluno';
    }
    
    // Verificar se pode adicionar aulas (apenas admin e secretaria)
    public function canAddLessons() {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        return in_array($user['tipo'], ['admin', 'secretaria']);
    }
    
    // Verificar se pode editar aulas (admin, secretaria e instrutor)
    public function canEditLessons() {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        return in_array($user['tipo'], ['admin', 'secretaria', 'instrutor']);
    }
    
    // Verificar se pode cancelar aulas (admin, secretaria e instrutor)
    public function canCancelLessons() {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        return in_array($user['tipo'], ['admin', 'secretaria', 'instrutor']);
    }
    
    /**
     * Redireciona o usuário para o painel apropriado após login
     * Centraliza a lógica de redirecionamento por tipo de usuário
     * Verifica se precisa trocar senha e redireciona adequadamente
     * 
     * @param array|null $user Dados do usuário (opcional, usa getCurrentUser() se não fornecido)
     * @return void (faz redirect e exit)
     */
    public function redirectAfterLogin($user = null) {
        if (!$user) {
            $user = $this->getCurrentUser();
        }
        
        if (!$user) {
            // Se não houver usuário, redirecionar para login
            $basePath = defined('BASE_PATH') ? BASE_PATH : '';
            header('Location: ' . $basePath . '/login.php');
            exit;
        }
        
        // Verificar se precisa trocar senha
        // Se a coluna precisa_trocar_senha existir e estiver = 1, forçar troca de senha
        $precisaTrocarSenha = false;
        try {
            // Verificar se coluna existe e se está ativa
            $db = $this->db;
            $checkColumn = $db->fetch("SHOW COLUMNS FROM usuarios LIKE 'precisa_trocar_senha'");
            if ($checkColumn) {
                // Buscar valor atual do flag
                $usuarioCompleto = $db->fetch("SELECT precisa_trocar_senha FROM usuarios WHERE id = ?", [$user['id']]);
                if ($usuarioCompleto && isset($usuarioCompleto['precisa_trocar_senha']) && $usuarioCompleto['precisa_trocar_senha'] == 1) {
                    $precisaTrocarSenha = true;
                }
            }
        } catch (Exception $e) {
            // Se houver erro ao verificar, continuar normalmente
            if (LOG_ENABLED) {
                error_log('Erro ao verificar precisa_trocar_senha: ' . $e->getMessage());
            }
        }
        
        // CORREÇÃO: Buscar tipo do usuário (compatibilidade com RBAC e sistema antigo)
        $tipo = strtolower($user['tipo'] ?? '');
        
        // Se não encontrou tipo no array, buscar do banco (RBAC)
        if (empty($tipo)) {
            $tipo = $this->getUserType($user['id']);
        }
        
        // Se precisa trocar senha, redirecionar para página de troca
        if ($precisaTrocarSenha) {
                    switch ($tipo) {
                        case 'instrutor':
                            $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                            header('Location: ' . $basePath . '/instrutor/trocar-senha.php?forcado=1');
                            exit;
                case 'admin':
                case 'secretaria':
                    // TODO: Criar página de troca de senha para admin/secretaria se necessário
                    // Por enquanto, permite acesso normal
                    break;
                case 'aluno':
                    // TODO: Criar página de troca de senha para aluno se necessário
                    // Por enquanto, permite acesso normal
                    break;
            }
        }
        
        // Determinar URL de destino baseado no tipo de usuário
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        switch ($tipo) {
            case 'admin':
            case 'secretaria':
                // Admin e Secretaria vão para o painel administrativo
                header('Location: ' . $basePath . '/admin/index.php');
                break;
                
            case 'instrutor':
                // Instrutor vai para o painel do instrutor
                header('Location: ' . $basePath . '/instrutor/dashboard.php');
                break;
                
            case 'aluno':
                // Aluno vai para o painel do aluno
                header('Location: ' . $basePath . '/aluno/dashboard.php');
                break;
                
            default:
                // Tipo desconhecido, redirecionar para login
                header('Location: ' . $basePath . '/login.php');
        }
        
        exit;
    }
    
    // Verificar se pode acessar configurações (apenas admin)
    public function canAccessConfigurations() {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        return $user['tipo'] === 'admin';
    }
    
    // Verificar se pode gerenciar usuários (admin e secretaria)
    public function canManageUsers() {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        return in_array($user['tipo'], ['admin', 'secretaria']);
    }
    
    // Buscar tipo do usuário (compatibilidade com sistema antigo e novo RBAC)
    private function getUserType($userId) {
        try {
            // 1) Priorizar modo ativo na sessão (novo conceito)
            if (!empty($_SESSION['active_role'])) {
                $role = strtoupper($_SESSION['active_role']);
                $roleMap = [
                    'ADMIN'      => 'admin',
                    'SECRETARIA' => 'secretaria',
                    'INSTRUTOR'  => 'instrutor',
                    'ALUNO'      => 'aluno'
                ];
                if (isset($roleMap[$role])) {
                    return $roleMap[$role];
                }
            }

            // 2) Fallback para current_role quando active_role ainda não existe
            if (!empty($_SESSION['current_role'])) {
                $role = strtoupper($_SESSION['current_role']);
                $roleMap = [
                    'ADMIN'      => 'admin',
                    'SECRETARIA' => 'secretaria',
                    'INSTRUTOR'  => 'instrutor',
                    'ALUNO'      => 'aluno'
                ];
                if (isset($roleMap[$role])) {
                    return $roleMap[$role];
                }
            }

            // 3) Compatibilidade: buscar em RBAC/banco quando ainda não há nada na sessão
            // Primeiro, tentar buscar da tabela usuario_roles (sistema novo RBAC)
            $sql = "SELECT ur.role FROM usuario_roles ur WHERE ur.usuario_id = :id ORDER BY ur.id LIMIT 1";
            $role = $this->db->fetch($sql, ['id' => $userId]);
            
            if ($role && !empty($role['role'])) {
                // Mapear role RBAC para tipo legado
                $roleMap = [
                    'ADMIN' => 'admin',
                    'SECRETARIA' => 'secretaria',
                    'INSTRUTOR' => 'instrutor',
                    'ALUNO' => 'aluno'
                ];
                return $roleMap[strtoupper($role['role'])] ?? 'aluno';
            }
            
            // Se não encontrou em usuario_roles, tentar campo 'tipo' (sistema antigo) só se a coluna existir
            try {
                $col = $this->db->fetch(
                    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo' LIMIT 1"
                );
                if ($col) {
                    $sql = "SELECT tipo FROM usuarios WHERE id = :id LIMIT 1";
                    $usuario = $this->db->fetch($sql, ['id' => $userId]);
                    if ($usuario && !empty($usuario['tipo'])) {
                        return strtolower($usuario['tipo']);
                    }
                }
            } catch (Exception $e) {
                // Coluna tipo não existe ou query falhou (ex.: banco só com RBAC)
            }
            
            // Fallback: retornar 'aluno' como padrão
            return 'aluno';
        } catch (Exception $e) {
            if (LOG_ENABLED) {
                error_log('Erro ao buscar tipo do usuário: ' . $e->getMessage());
            }
            return 'aluno';
        }
    }

    /**
     * Obter todos os perfis (roles) disponíveis do usuário a partir do RBAC,
     * com fallback para o tipo legado quando a tabela usuario_roles não estiver populada.
     *
     * Retorna uma lista de strings como ['ADMIN', 'INSTRUTOR', ...]
     */
    private function getUserRolesList($userId) {
        $roles = [];

        try {
            // Tentar buscar todos os roles do RBAC
            $sql = "SELECT ur.role FROM usuario_roles ur WHERE ur.usuario_id = :id ORDER BY ur.id";
            $rows = $this->db->fetchAll($sql, ['id' => $userId]);

            foreach ($rows as $row) {
                if (!empty($row['role'])) {
                    $roles[] = strtoupper($row['role']);
                }
            }
        } catch (Exception $e) {
            if (LOG_ENABLED) {
                error_log('Erro ao buscar roles do usuário: ' . $e->getMessage());
            }
        }

        // Se não encontrou nada em usuario_roles, manter lista vazia e deixar
        // o caller decidir o fallback (ex.: a partir de $tipo legado)
        return array_values(array_unique($roles));
    }
    
    // Criar nova sessão
    private function createSession($usuario, $remember = false) {
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['user_email'] = $usuario['email'];
        $_SESSION['user_name'] = $usuario['nome'];
        
        // CORREÇÃO: Buscar tipo do usuário (compatibilidade com RBAC e sistema antigo)
        $tipo = $this->getUserType($usuario['id']);
        $_SESSION['user_type'] = $tipo;
        
        $_SESSION['user_cfc_id'] = $usuario['cfc_id'] ?? null;
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $this->getClientIP();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Definir perfis disponíveis na sessão (available_roles)
        // 1) Tentar RBAC (usuario_roles)
        $availableRoles = $this->getUserRolesList($usuario['id']);

        // 2) Fallback para tipo legado quando não houver RBAC configurado
        if (empty($availableRoles)) {
            $roleMap = [
                'admin'      => 'ADMIN',
                'secretaria' => 'SECRETARIA',
                'instrutor'  => 'INSTRUTOR',
                'aluno'      => 'ALUNO'
            ];
            $fallbackRole = $roleMap[$tipo] ?? 'ALUNO';
            $availableRoles = [$fallbackRole];
        }

        $_SESSION['available_roles'] = $availableRoles;

        // Definir current_role mantendo compatibilidade com fluxo legado
        $currentRole = $_SESSION['current_role'] ?? null;
        if (!$currentRole) {
            $lastRole = $_SESSION['last_role'] ?? null;
            if ($lastRole && in_array($lastRole, $availableRoles, true)) {
                $currentRole = $lastRole;
            } else {
                $currentRole = $availableRoles[0];
            }
        }

        $_SESSION['current_role'] = $currentRole;

        // Novo: modo ativo (active_role) – por padrão, segue o mesmo role atual
        if (empty($_SESSION['active_role']) || !in_array($_SESSION['active_role'], $availableRoles, true)) {
            $_SESSION['active_role'] = $currentRole;
        }
        
        // Criar token de "lembrar-me" se solicitado
        if ($remember) {
            $token = $this->createRememberToken($usuario['id']);
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        }
        
        // Registrar sessão no banco
        $this->registerSession($usuario['id']);
    }
    
    // Registrar sessão no banco
    private function registerSession($userId) {
        $token = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        
        $sql = "INSERT INTO sessoes (usuario_id, token, ip_address, user_agent, expira_em) 
                VALUES (:usuario_id, :token, :ip_address, :user_agent, :expira_em)";
        
        $this->db->query($sql, [
            'usuario_id' => $userId,
            'token' => $token,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'expira_em' => $expiraEm
        ]);
    }
    
    // Buscar usuário por email ou CPF
    private function getUserByLogin($login) {
        $login = trim($login);
        
        // Se contém apenas números, tratar como CPF
        if (preg_match('/^[0-9]+$/', $login)) {
            $sql = "SELECT u.*, c.id as cfc_id FROM usuarios u 
                    LEFT JOIN cfcs c ON u.id = c.responsavel_id 
                    WHERE u.cpf = :cpf LIMIT 1";
            
            return $this->db->fetch($sql, ['cpf' => $login]);
        }
        
        // Caso contrário, tratar como email
        $sql = "SELECT u.*, c.id as cfc_id FROM usuarios u 
                LEFT JOIN cfcs c ON u.id = c.responsavel_id 
                WHERE u.email = :email LIMIT 1";
        
        return $this->db->fetch($sql, ['email' => strtolower($login)]);
    }
    
    // Buscar usuário por email (método mantido para compatibilidade)
    private function getUserByEmail($email) {
        $sql = "SELECT u.*, c.id as cfc_id FROM usuarios u 
                LEFT JOIN cfcs c ON u.id = c.responsavel_id 
                WHERE u.email = :email LIMIT 1";
        
        return $this->db->fetch($sql, ['email' => strtolower(trim($email))]);
    }
    
    // Obter dados do usuário (sem ultimo_login para compatibilidade com DB sem essa coluna)
    private function getUserData($userId) {
        $sql = "SELECT u.id, u.nome, u.email, u.cpf, u.telefone,
                       c.id as cfc_id, c.nome as cfc_nome, c.cnpj as cfc_cnpj
                FROM usuarios u 
                LEFT JOIN cfcs c ON u.id = c.responsavel_id 
                WHERE u.id = :id LIMIT 1";
        
        $user = $this->db->fetch($sql, ['id' => $userId]);
        
        if ($user) {
            $user['ultimo_login'] = null;
            $user['tipo'] = $this->getUserType($userId);
        }
        
        return $user;
    }
    
    // Atualizar último login (só executa se a coluna ultimo_login existir)
    private function updateLastLogin($userId) {
        try {
            $col = $this->db->fetch("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'ultimo_login' LIMIT 1");
            if (!$col) {
                return;
            }
            $this->db->query("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id", ['id' => $userId]);
        } catch (Exception $e) {
            // Ignorar se coluna não existir ou query falhar
        }
    }
    
    // Verificar se IP está bloqueado
    private function isLocked($ip) {
        // Verificação simplificada - sempre retorna false por enquanto
        // TODO: Implementar verificação de bloqueio quando a tabela logs estiver funcionando
        return false;
    }
    
    // Incrementar tentativas de login
    private function incrementAttempts($ip) {
        // Função simplificada - não faz nada por enquanto
        // TODO: Implementar contagem de tentativas quando a tabela logs estiver funcionando
    }
    
    // Resetar tentativas de login
    private function resetAttempts($ip) {
        // Função simplificada - não faz nada por enquanto
        // TODO: Implementar reset de tentativas quando a tabela logs estiver funcionando
    }
    
    // Obter tempo restante do bloqueio
    private function getLockoutTimeRemaining() {
        // Função simplificada - sempre retorna 0 por enquanto
        // TODO: Implementar cálculo de tempo quando a tabela logs estiver funcionando
        return 0;
    }
    
    // Criar token de "lembrar-me"
    private function createRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 dias
        
        $sql = "INSERT INTO sessoes (usuario_id, token, ip_address, user_agent, expira_em) 
                VALUES (:usuario_id, :token, :ip_address, :user_agent, :expira_em)";
        
        $this->db->query($sql, [
            'usuario_id' => $userId,
            'token' => $token,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'expira_em' => $expiraEm
        ]);
        
        return $token;
    }
    
    // Validar token de "lembrar-me"
    private function validateRememberToken($token) {
        $sql = "SELECT s.*, u.* FROM sessoes s 
                JOIN usuarios u ON s.usuario_id = u.id 
                WHERE s.token = :token AND s.expira_em > NOW() AND u.ativo = 1 
                LIMIT 1";
        
        $result = $this->db->fetch($sql, ['token' => $token]);
        
        if ($result) {
            $this->createSession($result, false);
            return true;
        }
        
        return false;
    }
    
    // Remover token de "lembrar-me"
    private function removeRememberToken($token) {
        $sql = "DELETE FROM sessoes WHERE token = :token";
        $this->db->query($sql, ['token' => $token]);
    }
    
    // Obter permissões do usuário
    private function getUserPermissions($userType) {
        $permissions = [
            'admin' => [
                'dashboard', 'usuarios', 'cfcs', 'alunos', 'instrutores', 'aulas', 
                'veiculos', 'relatorios', 'configuracoes', 'backup', 'logs'
            ],
            'instrutor' => [
                'dashboard', 'alunos', 'aulas_visualizar', 'aulas_editar', 'aulas_cancelar',
                'veiculos', 'relatorios'
                // Removido: 'aulas_adicionar', 'usuarios', 'cfcs', 'instrutores', 'configuracoes'
            ],
            'secretaria' => [
                'dashboard', 'usuarios', 'cfcs', 'alunos', 'instrutores', 'aulas', 
                'veiculos', 'relatorios'
                // Removido: 'configuracoes', 'backup', 'logs'
            ],
            'aluno' => [
                'dashboard', 'aulas_visualizar', 'relatorios_visualizar'
                // Apenas visualização
            ]
        ];
        
        return $permissions[$userType] ?? [];
    }
    
    // Obter IP do cliente
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    // Limpar sessões expiradas
    public function cleanupExpiredSessions() {
        $sql = "DELETE FROM sessoes WHERE expira_em <= NOW()";
        return $this->db->query($sql);
    }
    
    // Forçar logout de todas as sessões do usuário
    public function forceLogoutAllSessions($userId) {
        $sql = "DELETE FROM sessoes WHERE usuario_id = :usuario_id";
        return $this->db->query($sql, ['usuario_id' => $userId]);
    }
    
    // Verificar se sessão é válida
    public function validateSession() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Verificar se IP mudou
        if ($_SESSION['ip_address'] !== $this->getClientIP()) {
            $this->logout();
            return false;
        }
        
        // Verificar se User-Agent mudou
        if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    // Renovar sessão
    public function renewSession() {
        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
            
            // Atualizar expiração no banco
            $sql = "UPDATE sessoes SET expira_em = DATE_ADD(NOW(), INTERVAL :timeout SECOND) 
                    WHERE usuario_id = :usuario_id AND token = :token";
            
            $this->db->query($sql, [
                'timeout' => SESSION_TIMEOUT,
                'usuario_id' => $_SESSION['user_id'],
                'token' => $_COOKIE['remember_token'] ?? ''
            ]);
        }
    }
    
    // Obter estatísticas de sessões
    public function getSessionStats() {
        $stats = [];
        
        // Total de sessões ativas
        $sql = "SELECT COUNT(*) as total FROM sessoes WHERE expira_em > NOW()";
        $result = $this->db->fetch($sql);
        $stats['active_sessions'] = $result['total'];
        
        // Sessões por usuário
        $sql = "SELECT u.nome, COUNT(s.id) as sessions FROM sessoes s 
                JOIN usuarios u ON s.usuario_id = u.id 
                WHERE s.expira_em > NOW() 
                GROUP BY u.id, u.nome 
                ORDER BY sessions DESC";
        $stats['sessions_by_user'] = $this->db->fetchAll($sql);
        
        // Sessões por IP
        $sql = "SELECT ip_address, COUNT(*) as sessions FROM sessoes 
                WHERE expira_em > NOW() 
                GROUP BY ip_address 
                ORDER BY sessions DESC";
        $stats['sessions_by_ip'] = $this->db->fetchAll($sql);
        
        return $stats;
    }
}

// Funções globais de autenticação
function isLoggedIn() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->isLoggedIn();
}

function getCurrentUser() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->getCurrentUser();
}

function hasPermission($permission) {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->hasPermission($permission);
}

function isAdmin() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->isAdmin();
}

function isInstructor() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->isInstructor();
}

function isSecretary() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->isSecretary();
}

function isStudent() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->isStudent();
}

function canAddLessons() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->canAddLessons();
}

function canEditLessons() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->canEditLessons();
}

function canCancelLessons() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->canCancelLessons();
}

function canAccessConfigurations() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->canAccessConfigurations();
}

function canManageUsers() {
    global $auth;
    if (!isset($auth)) {
        $auth = new Auth();
    }
    return $auth->canManageUsers();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }
}

function requirePermission($permission) {
    requireLogin();
    if (!hasPermission($permission)) {
        header('HTTP/1.1 403 Forbidden');
        die('Acesso negado');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('HTTP/1.1 403 Forbidden');
        die('Acesso negado - Administrador requerido');
    }
}

// Middleware de autenticação para APIs
function apiRequireAuth() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }
}

function apiRequirePermission($permission) {
    apiRequireAuth();
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado']);
        exit;
    }
}

function apiRequireAdmin() {
    apiRequireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado - Administrador requerido']);
        exit;
    }
}

/**
 * FASE 2 - Correção: Função centralizada para obter instrutor_id
 * Arquivo: includes/auth.php (linha ~800)
 * 
 * Usa o mesmo padrão da Fase 1 (admin/api/instrutor-aulas.php linha 64)
 * Query: SELECT id FROM instrutores WHERE usuario_id = ?
 * 
 * @param int|null $userId ID do usuário (opcional, usa getCurrentUser() se não fornecido)
 * @return int|null ID do instrutor ou null se não encontrado
 */
function getCurrentInstrutorId($userId = null) {
    if ($userId === null) {
        $user = getCurrentUser();
        if (!$user) {
            return null;
        }
        $userId = $user['id'];
    }
    
    $db = db();
    // Tentativa primária: buscar por usuario_id ativo
    $instrutor = $db->fetch("SELECT id FROM instrutores WHERE usuario_id = ? AND ativo = 1 LIMIT 1", [$userId]);
    
    if (!$instrutor) {
        error_log("[WARN] getCurrentInstrutorId: nenhum instrutor vinculado ao usuario_id={$userId}");
        // Opcional: tentativas adicionais (email/nome) poderiam ser adicionadas aqui; por ora apenas logamos.
        return null;
    }
    
    return $instrutor['id'];
}

/**
 * FASE 1 - PRESENCA TEORICA - Função centralizada para obter aluno_id
 * Arquivo: includes/auth.php (linha ~828)
 * 
 * Obtém o ID do aluno na tabela alunos a partir do usuário logado.
 * A relação é feita pelo CPF (usuarios.cpf = alunos.cpf).
 * 
 * @param int|null $userId ID do usuário (opcional, usa getCurrentUser() se não fornecido)
 * @return int|null ID do aluno ou null se não encontrado
 */
/**
 * FASE 1 - AREA ALUNO PENDENCIAS - Função robusta para obter o ID do aluno associado ao usuário logado
 * 
 * Ordem de tentativa:
 * 1. Buscar por usuario_id (campo direto na tabela alunos)
 * 2. Buscar por email (e atualizar usuario_id se necessário - migração silenciosa)
 * 3. Buscar por CPF (e atualizar usuario_id se necessário - migração silenciosa)
 * 
 * @param int|null $userId ID do usuário (opcional, usa getCurrentUser() se não fornecido)
 * @return int|null ID do aluno ou null se não encontrado
 */
function getCurrentAlunoId($userId = null) {
    if ($userId === null) {
        $user = getCurrentUser();
        if (!$user || $user['tipo'] !== 'aluno') {
            // Log mesmo quando não é aluno para debug
            error_log("[getCurrentAlunoId] Usuário não é aluno ou não logado. Tipo: " . ($user['tipo'] ?? 'N/A'));
            return null;
        }
        $userId = $user['id'];
    }
    
    $db = db();
    
    // Log temporário para debug (sempre ativo para diagnóstico)
    error_log("[getCurrentAlunoId] Buscando aluno para usuario_id: $userId");
    
    // Verificar se a coluna usuario_id existe (usado em todas as tentativas)
    $colunaExiste = false;
    try {
        $colunas = $db->fetchAll("SHOW COLUMNS FROM alunos LIKE 'usuario_id'");
        $colunaExiste = !empty($colunas);
        error_log("[getCurrentAlunoId] Coluna usuario_id existe: " . ($colunaExiste ? 'SIM' : 'NÃO'));
    } catch (Exception $e) {
        error_log("[getCurrentAlunoId] Erro ao verificar coluna usuario_id: " . $e->getMessage());
    }
    
    // TENTATIVA 1: Buscar por usuario_id (campo direto na tabela alunos)
    if ($colunaExiste) {
        try {
            $aluno = $db->fetch("SELECT id, usuario_id FROM alunos WHERE usuario_id = ? LIMIT 1", [$userId]);
            if ($aluno && isset($aluno['id'])) {
                error_log("[getCurrentAlunoId] Aluno encontrado por usuario_id: {$aluno['id']}");
                return (int)$aluno['id'];
            }
        } catch (Exception $e) {
            error_log("[getCurrentAlunoId] Erro ao buscar por usuario_id: " . $e->getMessage());
        }
    } else {
        error_log("[getCurrentAlunoId] Coluna usuario_id não existe na tabela alunos, pulando tentativa 1");
    }
    
    // Buscar dados do usuário atual (usado nas tentativas 2 e 3)
    $usuario = null;
    try {
        // Não filtrar por tipo aqui, pois pode ser que o tipo não esteja correto
        $usuario = $db->fetch("SELECT id, email, cpf, tipo FROM usuarios WHERE id = ?", [$userId]);
        error_log("[getCurrentAlunoId] Busca de usuário - id: $userId");
        if ($usuario) {
            error_log("[getCurrentAlunoId] Usuário encontrado - id: {$usuario['id']}, tipo: " . ($usuario['tipo'] ?? 'N/A') . ", email: " . ($usuario['email'] ?? 'N/A') . ", cpf: " . ($usuario['cpf'] ?? 'N/A'));
        } else {
            error_log("[getCurrentAlunoId] ERRO: Usuário não encontrado na tabela usuarios para id: $userId");
            return null; // Se não encontrou o usuário, não tem como encontrar o aluno
        }
        
        // Se o tipo não for 'aluno', ainda assim tentar buscar (pode ser erro de cadastro)
        if (($usuario['tipo'] ?? '') !== 'aluno') {
            error_log("[getCurrentAlunoId] AVISO: Usuário tipo '{$usuario['tipo']}' não é 'aluno', mas continuando busca...");
        }
    } catch (Exception $e) {
        error_log("[getCurrentAlunoId] ERRO ao buscar usuário: " . $e->getMessage());
        return null;
    }
    
    // TENTATIVA 2: Buscar por email (e atualizar usuario_id se necessário)
    try {
        if ($usuario && !empty($usuario['email'])) {
            // Buscar aluno por email (case-insensitive)
            error_log("[getCurrentAlunoId] Tentativa 2: Buscando aluno por email: " . $usuario['email']);
            $aluno = $db->fetch("SELECT id" . ($colunaExiste ? ", usuario_id" : "") . " FROM alunos WHERE LOWER(email) = LOWER(?) LIMIT 1", [$usuario['email']]);
            
            if ($aluno && isset($aluno['id'])) {
                error_log("[getCurrentAlunoId] Aluno encontrado por email! ID: {$aluno['id']}");
                // Se encontrou por email mas usuario_id está nulo ou diferente, atualizar (migração silenciosa)
                // Só tentar atualizar se a coluna existir
                if ($colunaExiste && (empty($aluno['usuario_id']) || (int)$aluno['usuario_id'] !== (int)$userId)) {
                    try {
                        $db->query("UPDATE alunos SET usuario_id = ? WHERE id = ?", [$userId, $aluno['id']]);
                        if (defined('LOG_ENABLED') && LOG_ENABLED) {
                            error_log("[getCurrentAlunoId] Atualizado usuario_id para aluno_id {$aluno['id']} via email");
                        }
                    } catch (Exception $e) {
                        // Se a coluna usuario_id não existir, ignorar erro e continuar
                        if (defined('LOG_ENABLED') && LOG_ENABLED) {
                            error_log("[getCurrentAlunoId] Não foi possível atualizar usuario_id: " . $e->getMessage());
                        }
                    }
                }
                
                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log("[getCurrentAlunoId] Aluno encontrado por email: {$aluno['id']}");
                }
                return (int)$aluno['id'];
            }
        }
    } catch (Exception $e) {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            error_log("[getCurrentAlunoId] Erro ao buscar por email: " . $e->getMessage());
        }
    }
    
    // TENTATIVA 3: Buscar por CPF (e atualizar usuario_id se necessário)
    try {
        if ($usuario && !empty($usuario['cpf'])) {
            // Limpar CPF (remover formatação) para comparação
            $cpfLimpo = preg_replace('/[^0-9]/', '', $usuario['cpf']);
            
            // Buscar aluno por CPF (pode estar com ou sem formatação no banco)
            error_log("[getCurrentAlunoId] Tentativa 3: Buscando aluno por CPF - Original: " . $usuario['cpf'] . ", Limpo: $cpfLimpo");
            
            // Tentar primeiro com CPF limpo (mais comum no banco)
            $campos = "id, cpf";
            if ($colunaExiste) {
                $campos .= ", usuario_id";
            }
            $aluno = $db->fetch("SELECT $campos FROM alunos WHERE cpf = ? LIMIT 1", [$cpfLimpo]);
            
            // Se não encontrar com CPF limpo, tentar com CPF formatado
            if (!$aluno && $usuario['cpf'] !== $cpfLimpo) {
                error_log("[getCurrentAlunoId] Tentando CPF formatado: " . $usuario['cpf']);
                $aluno = $db->fetch("SELECT $campos FROM alunos WHERE cpf = ? LIMIT 1", [$usuario['cpf']]);
            }
            
            // Se ainda não encontrou, tentar buscar removendo formatação do CPF do banco também
            if (!$aluno) {
                error_log("[getCurrentAlunoId] Buscando todos os alunos e comparando CPFs limpos...");
                // Buscar todos os alunos e comparar CPFs limpos
                $todosAlunos = $db->fetchAll("SELECT $campos FROM alunos WHERE cpf IS NOT NULL AND cpf != ''");
                error_log("[getCurrentAlunoId] Total de alunos com CPF: " . count($todosAlunos));
                foreach ($todosAlunos as $a) {
                    $cpfAlunoLimpo = preg_replace('/[^0-9]/', '', $a['cpf']);
                    if ($cpfAlunoLimpo === $cpfLimpo) {
                        error_log("[getCurrentAlunoId] Match encontrado! Aluno ID: {$a['id']}, CPF no banco: {$a['cpf']}");
                        $aluno = $a;
                        break;
                    }
                }
            }
            
            if ($aluno && isset($aluno['id'])) {
                // Se encontrou por CPF mas usuario_id está nulo ou diferente, atualizar (migração silenciosa)
                // Só tentar atualizar se a coluna existir
                if ($colunaExiste && (empty($aluno['usuario_id']) || (int)$aluno['usuario_id'] !== (int)$userId)) {
                    try {
                        $db->query("UPDATE alunos SET usuario_id = ? WHERE id = ?", [$userId, $aluno['id']]);
                        if (defined('LOG_ENABLED') && LOG_ENABLED) {
                            error_log("[getCurrentAlunoId] Atualizado usuario_id para aluno_id {$aluno['id']} via CPF");
                        }
                    } catch (Exception $e) {
                        // Se a coluna usuario_id não existir, ignorar erro e continuar
                        if (defined('LOG_ENABLED') && LOG_ENABLED) {
                            error_log("[getCurrentAlunoId] Não foi possível atualizar usuario_id: " . $e->getMessage());
                        }
                    }
                }
                
                if (defined('LOG_ENABLED') && LOG_ENABLED) {
                    error_log("[getCurrentAlunoId] Aluno encontrado por CPF: {$aluno['id']}");
                }
                return (int)$aluno['id'];
            }
        }
    } catch (Exception $e) {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            error_log("[getCurrentAlunoId] Erro ao buscar por CPF: " . $e->getMessage());
        }
    }
    
    // Se nenhuma tentativa funcionou, retornar null
    if (defined('LOG_ENABLED') && LOG_ENABLED) {
        error_log("[getCurrentAlunoId] Aluno não encontrado para usuario_id: $userId");
    }
    
    return null;
}

/**
 * Criar registro de instrutor a partir de um usuário
 * 
 * SYNC_INSTRUTORES - Função helper para garantir consistência entre usuarios e instrutores
 * 
 * @param int $usuarioId ID do usuário na tabela usuarios
 * @param int|null $cfcId ID do CFC (se null, busca o primeiro CFC disponível)
 * @return array ['success' => bool, 'instrutor_id' => int|null, 'message' => string, 'created' => bool]
 */
function createInstrutorFromUser($usuarioId, $cfcId = null) {
    $db = db();
    
    // Verificar se usuário existe e é do tipo instrutor
    $usuario = $db->fetch("SELECT id, nome, email, tipo FROM usuarios WHERE id = ?", [$usuarioId]);
    if (!$usuario) {
        if (LOG_ENABLED) {
            error_log('[SYNC_INSTRUTORES] Usuário não encontrado: usuario_id=' . $usuarioId);
        }
        return [
            'success' => false,
            'instrutor_id' => null,
            'message' => 'Usuário não encontrado',
            'created' => false
        ];
    }
    
    if ($usuario['tipo'] !== 'instrutor') {
        if (LOG_ENABLED) {
            error_log('[SYNC_INSTRUTORES] Usuário não é do tipo instrutor: usuario_id=' . $usuarioId . ', tipo=' . $usuario['tipo']);
        }
        return [
            'success' => false,
            'instrutor_id' => null,
            'message' => 'Usuário não é do tipo instrutor',
            'created' => false
        ];
    }
    
    // Verificar se já existe registro em instrutores
    $instrutorExistente = $db->fetch("SELECT id FROM instrutores WHERE usuario_id = ?", [$usuarioId]);
    if ($instrutorExistente) {
        if (LOG_ENABLED) {
            error_log('[SYNC_INSTRUTORES] Instrutor já existe: usuario_id=' . $usuarioId . ', instrutor_id=' . $instrutorExistente['id']);
        }
        return [
            'success' => true,
            'instrutor_id' => $instrutorExistente['id'],
            'message' => 'Instrutor já existe',
            'created' => false
        ];
    }
    
    // Buscar CFC se não foi fornecido
    if ($cfcId === null) {
        $cfc = $db->fetch("SELECT id FROM cfcs ORDER BY id LIMIT 1");
        if (!$cfc) {
            if (LOG_ENABLED) {
                error_log('[SYNC_INSTRUTORES] Nenhum CFC encontrado no banco de dados');
            }
            return [
                'success' => false,
                'instrutor_id' => null,
                'message' => 'Nenhum CFC encontrado no banco de dados',
                'created' => false
            ];
        }
        $cfcId = $cfc['id'];
    }
    
    // Gerar credencial única
    $credencial = 'CRED-' . str_pad($usuarioId, 6, '0', STR_PAD_LEFT);
    
    // Verificar se credencial já existe
    $credencialExistente = $db->fetch("SELECT id FROM instrutores WHERE credencial = ?", [$credencial]);
    if ($credencialExistente) {
        // Se existir, adicionar sufixo com timestamp
        $credencial = 'CRED-' . str_pad($usuarioId, 6, '0', STR_PAD_LEFT) . '-' . time();
    }
    
    // Criar registro de instrutor
    $instrutorData = [
        'nome' => $usuario['nome'] ?? '',
        'usuario_id' => $usuarioId,
        'cfc_id' => $cfcId,
        'credencial' => $credencial,
        'ativo' => 1,
        'criado_em' => date('Y-m-d H:i:s')
    ];
    
    try {
        $instrutorId = $db->insert('instrutores', $instrutorData);
        
        if ($instrutorId) {
            if (LOG_ENABLED) {
                error_log('[SYNC_INSTRUTORES] Instrutor criado com sucesso: usuario_id=' . $usuarioId . ', instrutor_id=' . $instrutorId . ', cfc_id=' . $cfcId . ', credencial=' . $credencial);
            }
            return [
                'success' => true,
                'instrutor_id' => $instrutorId,
                'message' => 'Instrutor criado com sucesso',
                'created' => true
            ];
        } else {
            if (LOG_ENABLED) {
                error_log('[SYNC_INSTRUTORES] Erro ao criar instrutor: usuario_id=' . $usuarioId . ', erro=' . $db->getLastError());
            }
            return [
                'success' => false,
                'instrutor_id' => null,
                'message' => 'Erro ao criar instrutor: ' . $db->getLastError(),
                'created' => false
            ];
        }
    } catch (Exception $e) {
        if (LOG_ENABLED) {
            error_log('[SYNC_INSTRUTORES] Exceção ao criar instrutor: usuario_id=' . $usuarioId . ', erro=' . $e->getMessage());
        }
        return [
            'success' => false,
            'instrutor_id' => null,
            'message' => 'Erro ao criar instrutor: ' . $e->getMessage(),
            'created' => false
        ];
    }
}

// Instância global do sistema de autenticação - MOVIDA PARA O FINAL
$auth = new Auth();

/**
 * Função global para redirecionar após login baseado no tipo de usuário
 * Centraliza a lógica de redirecionamento
 * 
 * @param array|null $user Dados do usuário (opcional, usa getCurrentUser() se não fornecido)
 * @return void (faz redirect e exit)
 */
function redirectAfterLogin($user = null) {
    global $auth;
    $auth->redirectAfterLogin($user);
}

?>
