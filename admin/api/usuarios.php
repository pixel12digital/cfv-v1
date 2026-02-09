<?php
// API para gerenciamento de usuários
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Log de início da API
error_log('[USUARIOS API] Iniciando - Método: ' . $_SERVER['REQUEST_METHOD'] . ' - URI: ' . $_SERVER['REQUEST_URI']);

// Usar caminho relativo que sabemos que funciona
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/CredentialManager.php';

// Log das configurações
error_log('[USUARIOS API] Ambiente: ' . (defined('ENVIRONMENT') ? ENVIRONMENT : 'indefinido'));
error_log('[USUARIOS API] Debug Mode: ' . (DEBUG_MODE ? 'true' : 'false'));

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    error_log('[USUARIOS API] Usuário não está logado');
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado', 'code' => 'NOT_LOGGED_IN']);
    exit;
}

// Verificar permissão de admin
$currentUser = getCurrentUser();
if (!$currentUser) {
    error_log('[USUARIOS API] Usuário atual não encontrado');
    http_response_code(401);
    echo json_encode(['error' => 'Sessão inválida', 'code' => 'INVALID_SESSION']);
    exit;
}

// Log do usuário atual
error_log('[USUARIOS API] Usuário logado: ' . $currentUser['email'] . ' (Tipo: ' . $currentUser['tipo'] . ')');

// Verificar se é admin ou secretaria
if (!canManageUsers()) {
    error_log('[BLOQUEIO] Usuarios API: tipo=' . ($currentUser['tipo'] ?? '') . ', user_id=' . ($currentUser['id'] ?? ''));
    http_response_code(403);
    echo json_encode(['error' => 'Você não tem permissão.', 'code' => 'NOT_AUTHORIZED']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// Log do método e parâmetros
error_log('[USUARIOS API] Método: ' . $method);
if (!empty($_GET)) {
    error_log('[USUARIOS API] GET params: ' . json_encode($_GET));
}

try {
    switch ($method) {
        case 'GET':
            // Listar usuários ou buscar usuário específico
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                error_log('[USUARIOS API] Buscando usuário ID: ' . $id);
                
                $usuario = $db->fetch("SELECT id, nome, email, cpf, telefone, tipo, ativo, criado_em FROM usuarios WHERE id = ?", [$id]);
                
                if ($usuario) {
                    error_log('[USUARIOS API] Usuário encontrado: ' . $usuario['email']);
                    echo json_encode(['success' => true, 'data' => $usuario]);
                } else {
                    error_log('[USUARIOS API] Usuário não encontrado - ID: ' . $id);
                    http_response_code(404);
                    echo json_encode(['error' => 'Usuário não encontrado', 'code' => 'USER_NOT_FOUND']);
                }
            } else {
                // Listar todos os usuários
                error_log('[USUARIOS API] Listando todos os usuários');
                $usuarios = $db->fetchAll("SELECT id, nome, email, tipo, ativo, criado_em FROM usuarios ORDER BY nome");
                error_log('[USUARIOS API] Total de usuários encontrados: ' . count($usuarios));
                echo json_encode(['success' => true, 'data' => $usuarios]);
            }
            break;
            
        case 'POST':
            // Criar novo usuário ou redefinir senha
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                $data = $_POST;
            }
            
            error_log('[USUARIOS API] Dados recebidos: ' . json_encode(array_keys($data)));
            
            // Verificar se é redefinição de senha
            if (isset($data['action']) && $data['action'] === 'reset_password') {
                /**
                 * FLUXO COMPLETO DE REDEFINIÇÃO DE SENHA
                 * 
                 * Suporta dois modos:
                 * - 'auto': Gera senha temporária automaticamente (recomendado)
                 * - 'manual': Admin define a nova senha manualmente
                 * 
                 * Segurança:
                 * - Senha sempre gravada como hash (bcrypt)
                 * - Flag precisa_trocar_senha marcado após reset
                 * - Log de auditoria registrado
                 * - Senha temporária retornada apenas uma vez (modo auto)
                 */
                
                // Validações obrigatórias
                if (empty($data['user_id'])) {
                    error_log('[USUARIOS API] ID do usuário ausente para redefinição de senha');
                    http_response_code(400);
                    echo json_encode(['error' => 'ID do usuário é obrigatório', 'code' => 'MISSING_USER_ID']);
                    exit;
                }
                
                // Validar modo (auto ou manual)
                $mode = $data['mode'] ?? 'auto';
                if (!in_array($mode, ['auto', 'manual'])) {
                    error_log('[USUARIOS API] Modo inválido: ' . $mode);
                    http_response_code(400);
                    echo json_encode(['error' => 'Modo inválido. Use "auto" ou "manual"', 'code' => 'INVALID_MODE']);
                    exit;
                }
                
                // Validar senha manual se modo manual
                if ($mode === 'manual') {
                    if (empty($data['nova_senha'])) {
                        error_log('[USUARIOS API] Senha manual não fornecida');
                        http_response_code(400);
                        echo json_encode(['error' => 'Nova senha é obrigatória no modo manual', 'code' => 'MISSING_PASSWORD']);
                        exit;
                    }
                    
                    // Validar tamanho mínimo
                    if (strlen($data['nova_senha']) < 8) {
                        error_log('[USUARIOS API] Senha muito curta (mínimo 8 caracteres)');
                        http_response_code(400);
                        echo json_encode(['error' => 'A senha deve ter no mínimo 8 caracteres', 'code' => 'PASSWORD_TOO_SHORT']);
                        exit;
                    }
                    
                    // Validar confirmação (se fornecida)
                    if (isset($data['nova_senha_confirmacao']) && $data['nova_senha'] !== $data['nova_senha_confirmacao']) {
                        error_log('[USUARIOS API] Senhas não coincidem');
                        http_response_code(400);
                        echo json_encode(['error' => 'As senhas não coincidem', 'code' => 'PASSWORD_MISMATCH']);
                        exit;
                    }
                }
                
                $userId = (int)$data['user_id'];
                $adminId = $currentUser['id'];
                $adminEmail = $currentUser['email'];
                $clientIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                
                error_log('[USUARIOS API] Redefinindo senha para usuário ID: ' . $userId . ' (Modo: ' . $mode . ', Admin: ' . $adminEmail . ')');
                
                // Verificar se usuário existe
                $usuario = $db->fetch("SELECT id, nome, email, cpf, tipo FROM usuarios WHERE id = ?", [$userId]);
                if (!$usuario) {
                    error_log('[USUARIOS API] Usuário não encontrado para redefinição - ID: ' . $userId);
                    http_response_code(404);
                    echo json_encode(['error' => 'Usuário não encontrado', 'code' => 'USER_NOT_FOUND']);
                    exit;
                }

                // SECRETARIA não pode redefinir senha de usuário admin
                if ($currentUser['tipo'] === 'secretaria' && ($usuario['tipo'] ?? '') === 'admin') {
                    error_log('[BLOQUEIO] Usuarios reset_password negado (secretaria→admin): user_id=' . ($currentUser['id'] ?? ''));
                    http_response_code(403);
                    echo json_encode(['error' => 'Você não tem permissão.', 'code' => 'NOT_AUTHORIZED']);
                    exit;
                }
                
                error_log('[USUARIOS API] Usuário encontrado para redefinição: ' . $usuario['email']);
                
                // Determinar senha a ser usada
                $novaSenha = null;
                $senhaTemporaria = null;
                
                if ($mode === 'auto') {
                    // Modo automático: gerar senha temporária
                    $senhaTemporaria = CredentialManager::generateTemporaryPassword(10); // 10 caracteres
                    $novaSenha = $senhaTemporaria;
                } else {
                    // Modo manual: usar senha fornecida pelo admin
                    $novaSenha = $data['nova_senha'];
                }
                
                // Gerar hash da senha
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                
                // Preparar update com flag precisa_trocar_senha
                // Construir query SQL de forma segura
                $updateFields = ['senha = ?'];
                $updateValues = [$senhaHash];
                
                // Tentar adicionar flag precisa_trocar_senha (se coluna existir)
                $hasPrecisaTrocarSenha = false;
                try {
                    // Verificar se coluna existe
                    $columnCheck = $db->fetch("
                        SELECT COLUMN_NAME 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'usuarios' 
                          AND COLUMN_NAME = 'precisa_trocar_senha'
                    ");
                    
                    if ($columnCheck) {
                        $updateFields[] = 'precisa_trocar_senha = 1';
                        $hasPrecisaTrocarSenha = true;
                        error_log('[USUARIOS API] Flag precisa_trocar_senha será marcado');
                    } else {
                        error_log('[USUARIOS API] Coluna precisa_trocar_senha não existe - pulando flag');
                    }
                } catch (Exception $e) {
                    error_log('[USUARIOS API] Erro ao verificar coluna precisa_trocar_senha: ' . $e->getMessage());
                    // Continuar sem o flag se houver erro
                }
                
                // Adicionar atualizado_em (usando NOW() do MySQL)
                $updateFields[] = 'atualizado_em = NOW()';
                
                // Construir query SQL
                $updateQuery = 'UPDATE usuarios SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
                $updateValues[] = $userId;
                
                error_log('[USUARIOS API] Query SQL: ' . $updateQuery);
                error_log('[USUARIOS API] Parâmetros: ' . json_encode($updateValues));
                
                // Atualizar senha no banco
                $updateSuccess = false;
                $updateError = null;
                
                try {
                    $result = $db->query($updateQuery, $updateValues);
                    
                    // Verificar se a atualização foi bem-sucedida
                    // O método query() retorna um statement, precisamos verificar se houve linhas afetadas
                    if ($result) {
                        $rowsAffected = $result->rowCount();
                        error_log('[USUARIOS API] Linhas afetadas: ' . $rowsAffected);
                        
                        if ($rowsAffected > 0) {
                            $updateSuccess = true;
                        } else {
                            error_log('[USUARIOS API] AVISO: Nenhuma linha foi afetada na atualização');
                            $updateSuccess = false;
                            $updateError = 'Nenhuma linha foi afetada na atualização. Verifique se o usuário existe.';
                        }
                    } else {
                        $updateSuccess = false;
                        $updateError = 'Query retornou false';
                    }
                } catch (Exception $e) {
                    error_log('[USUARIOS API] Exceção ao executar UPDATE: ' . $e->getMessage());
                    error_log('[USUARIOS API] Stack trace: ' . $e->getTraceAsString());
                    $updateSuccess = false;
                    $updateError = $e->getMessage();
                }
                
                if ($updateSuccess) {
                    error_log('[USUARIOS API] Senha redefinida com sucesso - ID: ' . $userId);
                    
                    // CORREÇÃO CRÍTICA: Se for aluno, também atualizar senha na tabela alunos
                    // O login de aluno busca primeiro na tabela alunos, então precisa sincronizar
                    if ($usuario['tipo'] === 'aluno' && !empty($usuario['cpf'])) {
                        try {
                            // Buscar aluno na tabela alunos pelo CPF
                            $alunoNaTabelaAlunos = $db->fetch("SELECT id FROM alunos WHERE cpf = ?", [$usuario['cpf']]);
                            
                            if ($alunoNaTabelaAlunos) {
                                // Atualizar senha também na tabela alunos
                                $db->query("UPDATE alunos SET senha = ? WHERE cpf = ?", [$senhaHash, $usuario['cpf']]);
                                error_log('[USUARIOS API] Senha também atualizada na tabela alunos para CPF: ' . $usuario['cpf']);
                            } else {
                                error_log('[USUARIOS API] Aluno não encontrado na tabela alunos para CPF: ' . $usuario['cpf']);
                            }
                        } catch (Exception $e) {
                            error_log('[USUARIOS API] Erro ao atualizar senha na tabela alunos: ' . $e->getMessage());
                            // Não falhar a operação principal se houver erro na sincronização
                        }
                    }
                    
                    // LOG DE AUDITORIA
                    // Formato: [PASSWORD_RESET] admin_id=X, user_id=Y, mode=auto|manual, timestamp=Z, ip=W
                    $auditLog = sprintf(
                        '[PASSWORD_RESET] admin_id=%d, admin_email=%s, user_id=%d, user_email=%s, mode=%s, timestamp=%s, ip=%s',
                        $adminId,
                        $adminEmail,
                        $userId,
                        $usuario['email'],
                        $mode,
                        date('Y-m-d H:i:s'),
                        $clientIP
                    );
                    error_log($auditLog);
                    
                    // Enviar credenciais por email (apenas modo automático e se email válido)
                    if ($mode === 'auto' && !empty($usuario['email']) && filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)) {
                        // TODO: Quando sistema de email estiver configurado, substituir por envio real
                        // Por enquanto, apenas log simulado
                        CredentialManager::sendCredentials(
                            $usuario['email'], 
                            $senhaTemporaria, 
                            $usuario['tipo']
                        );
                    }
                    
                    // Preparar resposta
                    $response = [
                        'success' => true, 
                        'message' => 'Senha redefinida com sucesso',
                        'mode' => $mode
                    ];
                    
                    // Adicionar senha temporária apenas se modo automático
                    if ($mode === 'auto' && $senhaTemporaria) {
                        $response['temp_password'] = $senhaTemporaria;
                        $response['credentials'] = [
                            'email' => $usuario['email'],
                            'cpf' => $usuario['cpf'] ?? '',
                            'tipo' => $usuario['tipo'],
                            'senha_temporaria' => $senhaTemporaria,
                            'message' => 'Nova senha temporária gerada'
                        ];
                    }
                    
                    echo json_encode($response);
                } else {
                    $errorMessage = $updateError ?? 'Erro ao atualizar senha no banco de dados';
                    error_log('[USUARIOS API] Erro ao redefinir senha - ID: ' . $userId . ' - Erro: ' . $errorMessage);
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Erro ao redefinir senha: ' . $errorMessage,
                        'code' => 'RESET_FAILED',
                        'details' => $updateError ?? 'Nenhuma linha foi afetada na atualização'
                    ]);
                }
                break;
            }
            
            // Criar novo usuário (código original)
            // Validações básicas
            if (empty($data['nome']) || empty($data['email']) || empty($data['tipo'])) {
                error_log('[USUARIOS API] Dados obrigatórios ausentes');
                http_response_code(400);
                echo json_encode(['error' => 'Nome, email e tipo são obrigatórios', 'code' => 'MISSING_FIELDS']);
                exit;
            }

            // SECRETARIA não pode criar usuário admin
            if ($currentUser['tipo'] === 'secretaria' && ($data['tipo'] ?? '') === 'admin') {
                error_log('[BLOQUEIO] Usuarios POST (criar admin) negado: user_id=' . ($currentUser['id'] ?? ''));
                http_response_code(403);
                echo json_encode(['error' => 'Você não tem permissão.', 'code' => 'NOT_AUTHORIZED']);
                exit;
            }
            
            // Verificar se email já existe
            $existingUser = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$data['email']]);
            if ($existingUser) {
                error_log('[USUARIOS API] Email já existe: ' . $data['email']);
                http_response_code(400);
                echo json_encode(['error' => 'E-mail já cadastrado', 'code' => 'EMAIL_EXISTS']);
                exit;
            }
            
            // Criar credenciais automáticas
            error_log('[USUARIOS API] Criando credenciais automáticas para: ' . $data['email']);
            $credentials = CredentialManager::createEmployeeCredentials([
                'nome' => $data['nome'],
                'email' => $data['email'],
                'tipo' => $data['tipo']
            ]);
            
            if (!$credentials['success']) {
                error_log('[USUARIOS API] Erro ao criar credenciais: ' . $credentials['message']);
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao criar credenciais do usuário', 'code' => 'CREDENTIAL_ERROR']);
                exit;
            }
            
            // Enviar credenciais por email (simulado)
            CredentialManager::sendCredentials(
                $credentials['email'], 
                $credentials['senha_temporaria'], 
                $data['tipo']
            );
            
            $result = $credentials['usuario_id'];
            
            if ($result) {
                error_log('[USUARIOS API] Usuário criado com sucesso - ID: ' . $result);
                $usuario = $db->fetch("SELECT id, nome, email, tipo, ativo, criado_em FROM usuarios WHERE id = ?", [$result]);
                
                // SYNC_INSTRUTORES: Se o tipo for 'instrutor', criar registro em instrutores automaticamente
                if ($usuario && $usuario['tipo'] === 'instrutor') {
                    if (LOG_ENABLED) {
                        error_log('[USUARIOS API] Usuário criado como instrutor - criando registro em instrutores: usuario_id=' . $result);
                    }
                    
                    $instrutorResult = createInstrutorFromUser($result);
                    
                    if ($instrutorResult['success']) {
                        if (LOG_ENABLED) {
                            error_log('[USUARIOS API] Registro de instrutor criado automaticamente: usuario_id=' . $result . ', instrutor_id=' . $instrutorResult['instrutor_id']);
                        }
                    } else {
                        // Log do erro, mas não falha a criação do usuário
                        error_log('[USUARIOS API] AVISO: Erro ao criar registro de instrutor automaticamente: usuario_id=' . $result . ', erro=' . $instrutorResult['message']);
                    }
                }
                
                $response = [
                    'success' => true, 
                    'message' => 'Usuário criado com sucesso', 
                    'data' => $usuario
                ];
                
                // Adicionar credenciais à resposta
                $response['credentials'] = [
                    'email' => $credentials['email'],
                    'senha_temporaria' => $credentials['senha_temporaria'],
                    'message' => 'Credenciais criadas automaticamente'
                ];
                
                echo json_encode($response);
            } else {
                error_log('[USUARIOS API] Erro ao criar usuário');
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao criar usuário', 'code' => 'CREATE_FAILED']);
            }
            break;
            
        case 'PUT':
            // Atualizar usuário
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                parse_str(file_get_contents('php://input'), $data);
            }
            
            error_log('[USUARIOS API] Dados recebidos para atualização: ' . json_encode(array_keys($data)));
            
            if (empty($data['id'])) {
                error_log('[USUARIOS API] ID do usuário ausente para atualização');
                http_response_code(400);
                echo json_encode(['error' => 'ID do usuário é obrigatório', 'code' => 'MISSING_ID']);
                exit;
            }
            
            $id = (int)$data['id'];
            error_log('[USUARIOS API] Atualizando usuário ID: ' . $id);
            
            // Verificar se usuário existe e buscar tipo atual ANTES da atualização
            $existingUser = $db->fetch("SELECT id, nome, email, tipo FROM usuarios WHERE id = ?", [$id]);
            if (!$existingUser) {
                error_log('[USUARIOS API] Usuário não encontrado para atualização - ID: ' . $id);
                http_response_code(404);
                echo json_encode(['error' => 'Usuário não encontrado', 'code' => 'USER_NOT_FOUND']);
                exit;
            }

            // SECRETARIA não pode editar usuário admin
            if ($currentUser['tipo'] === 'secretaria' && ($existingUser['tipo'] ?? '') === 'admin') {
                error_log('[BLOQUEIO] Usuarios PUT (editar admin) negado: user_id=' . ($currentUser['id'] ?? ''));
                http_response_code(403);
                echo json_encode(['error' => 'Você não tem permissão.', 'code' => 'NOT_AUTHORIZED']);
                exit;
            }
            if ($currentUser['tipo'] === 'secretaria' && ($data['tipo'] ?? '') === 'admin') {
                error_log('[BLOQUEIO] Usuarios PUT (atribuir admin) negado: user_id=' . ($currentUser['id'] ?? ''));
                http_response_code(403);
                echo json_encode(['error' => 'Você não tem permissão.', 'code' => 'NOT_AUTHORIZED']);
                exit;
            }
            
            $tipoAnterior = $existingUser['tipo'] ?? null;
            
            // Preparar dados para atualização
            $updateData = [];
            if (!empty($data['nome'])) $updateData['nome'] = $data['nome'];
            if (!empty($data['email'])) $updateData['email'] = $data['email'];
            if (!empty($data['tipo'])) $updateData['tipo'] = $data['tipo'];
            if (isset($data['ativo'])) $updateData['ativo'] = (bool)$data['ativo'];
            
            // Atualizar senha se fornecida
            if (!empty($data['senha'])) {
                $updateData['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
            }
            
            $updateData['atualizado_em'] = date('Y-m-d H:i:s');
            
            // Atualizar usuário
            $result = $db->update('usuarios', $updateData, 'id = ?', [$id]);
            
            if ($result) {
                error_log('[USUARIOS API] Usuário atualizado com sucesso - ID: ' . $id);
                $usuario = $db->fetch("SELECT id, nome, email, tipo, ativo, criado_em FROM usuarios WHERE id = ?", [$id]);
                
                // SYNC_INSTRUTORES: Se o tipo foi alterado para 'instrutor', criar registro em instrutores automaticamente
                $tipoNovo = $updateData['tipo'] ?? $tipoAnterior;
                if ($tipoNovo === 'instrutor' && $tipoAnterior !== 'instrutor') {
                    // Tipo foi alterado de algo diferente para 'instrutor'
                    if (LOG_ENABLED) {
                        error_log('[USUARIOS API] Tipo alterado para instrutor - criando registro em instrutores: usuario_id=' . $id . ', tipo_anterior=' . $tipoAnterior);
                    }
                    
                    $instrutorResult = createInstrutorFromUser($id);
                    
                    if ($instrutorResult['success']) {
                        if ($instrutorResult['created']) {
                            if (LOG_ENABLED) {
                                error_log('[USUARIOS API] Registro de instrutor criado automaticamente após alteração de tipo: usuario_id=' . $id . ', instrutor_id=' . $instrutorResult['instrutor_id']);
                            }
                        } else {
                            if (LOG_ENABLED) {
                                error_log('[USUARIOS API] Registro de instrutor já existia: usuario_id=' . $id . ', instrutor_id=' . $instrutorResult['instrutor_id']);
                            }
                        }
                    } else {
                        // Log do erro, mas não falha a atualização do usuário
                        error_log('[USUARIOS API] AVISO: Erro ao criar registro de instrutor automaticamente após alteração de tipo: usuario_id=' . $id . ', erro=' . $instrutorResult['message']);
                    }
                }
                // TODO: Futuro - Se tipo foi alterado de 'instrutor' para outro, considerar desativar/arquivar registro em instrutores
                // Por enquanto, apenas garantimos a criação quando tipo = 'instrutor'
                
                echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso', 'data' => $usuario]);
            } else {
                error_log('[USUARIOS API] Erro ao atualizar usuário - ID: ' . $id);
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao atualizar usuário', 'code' => 'UPDATE_FAILED']);
            }
            break;
            
        case 'DELETE':
            // Apenas ADMIN pode excluir usuários
            if ($currentUser['tipo'] !== 'admin') {
                error_log('[BLOQUEIO] Usuarios DELETE negado: tipo=' . ($currentUser['tipo'] ?? '') . ', user_id=' . ($currentUser['id'] ?? ''));
                http_response_code(403);
                echo json_encode(['error' => 'Você não tem permissão.', 'code' => 'NOT_AUTHORIZED']);
                exit;
            }
            // Excluir usuário
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                error_log('[USUARIOS API] Tentando excluir usuário ID: ' . $id);
                
                // Verificar se usuário existe
                $existingUser = $db->fetch("SELECT id, email FROM usuarios WHERE id = ?", [$id]);
                if (!$existingUser) {
                    error_log('[USUARIOS API] Usuário não encontrado para exclusão - ID: ' . $id);
                    http_response_code(404);
                    echo json_encode(['error' => 'Usuário não encontrado', 'code' => 'USER_NOT_FOUND']);
                    exit;
                }
                
                error_log('[USUARIOS API] Usuário encontrado para exclusão: ' . $existingUser['email']);
                
                // Não permitir exclusão do próprio usuário logado
                if ($id == $currentUser['id']) {
                    error_log('[USUARIOS API] Tentativa de auto-exclusão bloqueada');
                    http_response_code(400);
                    echo json_encode(['error' => 'Não é possível excluir o próprio usuário', 'code' => 'SELF_DELETE']);
                    exit;
                }
                
                // Verificar todas as dependências do usuário
                $dependencias = [];
                
                // Verificar CFCs vinculados
                $cfcsVinculados = $db->fetchAll("SELECT id, nome FROM cfcs WHERE responsavel_id = ?", [$id]);
                if (count($cfcsVinculados) > 0) {
                    $dependencias[] = [
                        'tipo' => 'CFCs',
                        'quantidade' => count($cfcsVinculados),
                        'itens' => $cfcsVinculados,
                        'instrucao' => 'Remova ou altere o responsável dos CFCs antes de excluir o usuário.'
                    ];
                }
                
                // Verificar registros de instrutor
                $instrutoresVinculados = $db->fetchAll("SELECT id, cfc_id FROM instrutores WHERE usuario_id = ?", [$id]);
                if (count($instrutoresVinculados) > 0) {
                    $dependencias[] = [
                        'tipo' => 'Registros de Instrutor',
                        'quantidade' => count($instrutoresVinculados),
                        'itens' => $instrutoresVinculados,
                        'instrucao' => 'Remova os registros de instrutor antes de excluir o usuário.'
                    ];
                }
                
                // Verificar aulas como instrutor
                $aulasComoInstrutor = $db->fetchAll("
                    SELECT a.id, a.data_aula, a.tipo_aula FROM aulas a 
                    INNER JOIN instrutores i ON a.instrutor_id = i.id 
                    WHERE i.usuario_id = ?
                ", [$id]);
                if (count($aulasComoInstrutor) > 0) {
                    $dependencias[] = [
                        'tipo' => 'Aulas como Instrutor',
                        'quantidade' => count($aulasComoInstrutor),
                        'itens' => $aulasComoInstrutor,
                        'instrucao' => 'Remova ou altere as aulas onde o usuário é instrutor antes de excluí-lo.'
                    ];
                }
                
                // Verificar sessões e logs (apenas informativo, serão removidos automaticamente)
                $sessoes = $db->fetch("SELECT COUNT(*) as total FROM sessoes WHERE usuario_id = ?", [$id]);
                $logs = $db->fetch("SELECT COUNT(*) as total FROM logs WHERE usuario_id = ?", [$id]);
                
                if ($sessoes['total'] > 0 || $logs['total'] > 0) {
                    $dependencias[] = [
                        'tipo' => 'Dados de Sistema',
                        'quantidade' => $sessoes['total'] + $logs['total'],
                        'itens' => [
                            'sessoes' => $sessoes['total'],
                            'logs' => $logs['total']
                        ],
                        'instrucao' => 'Sessões e logs serão removidos automaticamente durante a exclusão.'
                    ];
                }
                
                // Se há dependências, retornar erro com instruções detalhadas
                if (!empty($dependencias)) {
                    error_log('[USUARIOS API] Usuário tem dependências - não pode ser excluído');
                    
                    $mensagem = "Não é possível excluir o usuário pois ele possui vínculos ativos:\n\n";
                    foreach ($dependencias as $dep) {
                        $mensagem .= "• {$dep['tipo']}: {$dep['quantidade']} registro(s)\n";
                        $mensagem .= "  Instrução: {$dep['instrucao']}\n\n";
                    }
                    $mensagem .= "Resolva todos os vínculos antes de tentar excluir o usuário novamente.";
                    
                    http_response_code(400);
                    echo json_encode([
                        'error' => $mensagem,
                        'code' => 'HAS_DEPENDENCIES',
                        'dependencias' => $dependencias,
                        'instrucoes' => array_map(function($dep) {
                            return $dep['instrucao'];
                        }, $dependencias)
                    ]);
                    exit;
                }
                
                try {
                    // Começar transação
                    $db->beginTransaction();
                    
                    // Excluir logs do usuário
                    error_log('[USUARIOS API] Excluindo logs do usuário');
                    $db->query("DELETE FROM logs WHERE usuario_id = ?", [$id]);
                    
                    // Excluir sessões do usuário
                    error_log('[USUARIOS API] Excluindo sessões do usuário');
                    $db->query("DELETE FROM sessoes WHERE usuario_id = ?", [$id]);
                    
                    // Excluir usuário usando PDO diretamente
                    error_log('[USUARIOS API] Excluindo usuário da tabela usuarios');
                    $pdo = $db->getConnection();
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                    $result = $stmt->execute([$id]);
                    
                    if ($result && $stmt->rowCount() > 0) {
                        $db->commit();
                        error_log('[USUARIOS API] Usuário excluído com sucesso - ID: ' . $id . ' (' . $existingUser['email'] . ')');
                        echo json_encode(['success' => true, 'message' => 'Usuário excluído com sucesso']);
                    } else {
                        $db->rollback();
                        error_log('[USUARIOS API] Falha ao excluir usuário - ID: ' . $id);
                        http_response_code(500);
                        echo json_encode(['error' => 'Erro ao excluir usuário', 'code' => 'DELETE_FAILED']);
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    error_log('[USUARIOS API] Exceção durante exclusão - ID: ' . $id . ' - Erro: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro interno ao excluir usuário: ' . $e->getMessage(), 'code' => 'DELETE_EXCEPTION']);
                }
            } else {
                error_log('[USUARIOS API] ID ausente para exclusão');
                http_response_code(400);
                echo json_encode(['error' => 'ID do usuário é obrigatório', 'code' => 'MISSING_ID']);
            }
            break;
            
        default:
            error_log('[USUARIOS API] Método não permitido: ' . $method);
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido', 'code' => 'METHOD_NOT_ALLOWED']);
            break;
    }
    
} catch (Exception $e) {
    error_log('[USUARIOS API] Exceção geral: ' . $e->getMessage() . ' - Stack: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage(), 'code' => 'INTERNAL_ERROR']);
}

error_log('[USUARIOS API] Finalizando processamento');
?>
