<?php   
// Definir caminho base
$base_path = dirname(__DIR__);

// Forçar charset UTF-8 para evitar problemas de codificação
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

// AJUSTE DASHBOARD INSTRUTOR - Verificar se o usuário está logado e tem permissão de admin ou é instrutor
// hasPermission('instrutor') verifica se tem a permissão 'instrutor', não se É instrutor
// Por isso, verificamos diretamente o tipo do usuário
if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$user = getCurrentUser();
if (!$user || ($user['tipo'] !== 'admin' && $user['tipo'] !== 'instrutor' && $user['tipo'] !== 'secretaria')) {
    header('Location: ../index.php');
    exit;
}

// Obter dados do usuário logado (já obtido acima na verificação de permissão)
$userType = $user['tipo'] ?? 'admin';
$userId = $user['id'] ?? null;
$isAdmin = ($user['tipo'] === 'admin');
$isInstrutor = ($user['tipo'] === 'instrutor');
$db = Database::getInstance();

// AJUSTE IDENTIDADE INSTRUTOR - Detectar se estamos em fluxo de instrutor
$origem = $_GET['origem'] ?? ($_REQUEST['origem'] ?? '');
$isFluxoInstrutor = ($origem === 'instrutor') || ($userType === 'instrutor');

// Variáveis para exibição de identidade no topbar
$userDisplayName = $user['nome'] ?? 'Usuário';
$userDisplayRole = 'Administrador';

// Se for fluxo de instrutor, buscar dados do instrutor
$instrutorId = null;
if ($isFluxoInstrutor) {
    $instrutorId = getCurrentInstrutorId($userId);
    
    // Log de debug para diagnóstico (apenas em desenvolvimento)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("[TOPBAR] Fluxo instrutor detectado - user_id={$userId}, user_type={$userType}, origem={$origem}, instrutor_id=" . ($instrutorId ?? 'null'));
    }
    
    if ($instrutorId) {
        $instrutorData = $db->fetch("SELECT nome FROM instrutores WHERE id = ?", [$instrutorId]);
        if ($instrutorData && !empty($instrutorData['nome'])) {
            $userDisplayName = $instrutorData['nome'];
        } else {
            // Se não encontrou nome do instrutor, usar nome do usuário
            $userDisplayName = $user['nome'] ?? 'Instrutor';
        }
        $userDisplayRole = 'Instrutor';
    } else {
        // CORREÇÃO 2025-01: Se não encontrou instrutor_id mas userType é 'instrutor',
        // ainda assim exibir como Instrutor (pode ser problema de vínculo no banco)
        if ($userType === 'instrutor') {
            $userDisplayName = $user['nome'] ?? 'Instrutor';
            $userDisplayRole = 'Instrutor';
            
            // Log de aviso
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("[TOPBAR WARN] Usuário tipo 'instrutor' (user_id={$userId}) mas getCurrentInstrutorId() retornou null. Verificar vínculo em instrutores.usuario_id");
            }
        } else {
            // Se origem=instrutor mas userType não é 'instrutor', pode ser acesso direto via URL
            $userDisplayName = $user['nome'] ?? 'Usuário';
            $userDisplayRole = 'Instrutor'; // Manter como Instrutor se origem=instrutor
        }
    }
} else {
    // Fluxo admin normal
    $userDisplayName = $user['nome'] ?? 'Administrador';
    $userDisplayRole = ($userType === 'secretaria') ? 'Secretaria' : 'Administrador';
}

// Obter estatísticas para o dashboard
try {
    $stats = [
        'total_alunos' => $db->count('alunos'),
        'total_instrutores' => $db->count('instrutores'),
        'total_aulas' => $db->count('aulas'),
        'total_veiculos' => $db->count('veiculos'),
        'aulas_hoje' => $db->count('aulas', 'data_aula = ?', [date('Y-m-d')]),
        'aulas_semana' => $db->count('aulas', 'data_aula >= ?', [date('Y-m-d', strtotime('monday this week'))])
    ];
} catch (Exception $e) {
    $stats = [
        'total_alunos' => 0,
        'total_instrutores' => 0,
        'total_aulas' => 0,
        'total_veiculos' => 0,
        'aulas_hoje' => 0,
        'aulas_semana' => 0
    ];
}

// Obter últimas atividades
try {
    $ultimas_atividades = $db->fetchAll("
        (SELECT 'aluno' as tipo, nome, 'cadastrado' as acao, criado_em as data
        FROM alunos 
        ORDER BY criado_em DESC 
        LIMIT 5)
        UNION ALL
        (SELECT 'instrutor' as tipo, u.nome, 'cadastrado' as acao, i.criado_em as data
        FROM instrutores i
        JOIN usuarios u ON i.usuario_id = u.id
        ORDER BY i.criado_em DESC 
        LIMIT 5)
        ORDER BY data DESC 
        LIMIT 10
    ");
} catch (Exception $e) {
    $ultimas_atividades = [];
    if (LOG_ENABLED) {
        error_log('Erro ao buscar últimas atividades: ' . $e->getMessage());
    }
}

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';

// BLOQUEIO DE ROTAS ADMINISTRATIVAS PARA INSTRUTOR
// Instrutor não pode acessar rotas de gestão geral de alunos
if ($userType === 'instrutor') {
    $rotasBloqueadas = ['alunos', 'historico-aluno'];
    
    // Verificar se a página está bloqueada
    if (in_array($page, $rotasBloqueadas)) {
        error_log('[BLOQUEIO] Instrutor tentou acessar rota bloqueada: page=' . $page . ', user_id=' . ($userId ?? ''));
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        $_SESSION['flash_message'] = 'Você não tem permissão.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: ' . $basePath . '/instrutor/dashboard.php');
        exit();
    }
    
    // Bloquear também ações específicas de alunos
    if ($page === 'alunos' && isset($_GET['action']) && in_array($_GET['action'], ['view', 'edit', 'create'])) {
        error_log('[BLOQUEIO] Instrutor tentou ação alunos bloqueada: action=' . ($_GET['action'] ?? '') . ', user_id=' . ($userId ?? ''));
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        $_SESSION['flash_message'] = 'Você não tem permissão.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: ' . $basePath . '/instrutor/dashboard.php');
        exit();
    }
}

// BLOQUEIO DE ROTAS PARA SECRETARIA (apenas ADMIN)
// Secretaria não pode acessar: instrutores, veículos, salas, serviços
if ($userType === 'secretaria') {
    $rotasBloqueadasSecretaria = ['instrutores', 'veiculos', 'configuracoes-salas', 'servicos', 'financeiro-relatorios'];
    if (in_array($page, $rotasBloqueadasSecretaria)) {
        error_log('[BLOQUEIO] Secretaria tentou acessar rota bloqueada: page=' . $page . ', user_id=' . ($userId ?? ''));
        $_SESSION['flash_message'] = 'Você não tem permissão.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: index.php');
        exit();
    }
}

// Verificação ANTECIPADA para turma-chamada (ANTES de qualquer output HTML)
// Isso evita o erro "headers already sent" quando a página requer turma_id
if ($page === 'turma-chamada' && !isset($_GET['turma_id'])) {
    header('Location: index.php?page=turmas-teoricas');
    exit();
}

// Definir constante para indicar que o roteamento está ativo
define('ADMIN_ROUTING', true);

// Processamento de formulários POST - DEVE VIR ANTES DE QUALQUER SAÍDA HTML

// Endpoint AJAX para buscar descrição sugerida da fatura
if ($page === 'financeiro-faturas' && isset($_GET['action']) && $_GET['action'] === 'descricao_sugerida_fatura') {
    // Limpar qualquer output anterior
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $alunoId = isset($_POST['aluno_id']) ? (int) $_POST['aluno_id'] : 0;
        $matriculaId = isset($_POST['matricula_id']) ? (int) $_POST['matricula_id'] : null;
        
        if ($alunoId <= 0 && !$matriculaId) {
            echo json_encode([
                'success' => false,
                'message' => 'aluno_id inválido'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        
        // Incluir função compartilhada
        if (!function_exists('buildDescricaoSugestaoFatura')) {
            require_once __DIR__ . '/includes/financeiro-faturas-functions.php';
        }
        
        $descricao = buildDescricaoSugestaoFatura($db, $alunoId, $matriculaId);
        
        echo json_encode([
            'success' => true,
            'descricao_sugerida' => $descricao,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar descrição sugerida: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Processamento de faturas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'financeiro-faturas' && isset($_GET['action']) && $_GET['action'] === 'create') {
    // Limpar qualquer output anterior (se output buffering estiver ativo)
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Definir header JSON primeiro, antes de qualquer output
    header('Content-Type: application/json; charset=utf-8');
    
    // Error handler temporário para converter warnings/notices em ErrorException
    // Isso garante que qualquer erro PHP seja capturado e retornado como JSON
    $previousErrorHandler = set_error_handler(function($severity, $message, $file, $line) {
        // Respeitar @ (error_reporting() == 0) - não converter erros suprimidos
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    
    try {
        // Verificar se usuário está logado e obter dados do usuário
        if (!isset($user) || !$user || !isset($user['id'])) {
            throw new Exception('Usuário não autenticado. Faça login novamente.');
        }
        // Validar dados obrigatórios
        $aluno_id = isset($_POST['aluno_id']) ? trim($_POST['aluno_id']) : null;
        $valor = isset($_POST['valor_total']) ? trim($_POST['valor_total']) : null;
        $data_vencimento = isset($_POST['data_vencimento']) ? trim($_POST['data_vencimento']) : null;
        $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : null;
        
        // Validar campos obrigatórios
        if (empty($aluno_id) || empty($valor) || empty($data_vencimento) || empty($descricao)) {
            throw new Exception('Todos os campos obrigatórios devem ser preenchidos: Aluno, Valor Total, Data de Vencimento e Descrição.');
        }
        
        // Validar formato numérico do valor
        // O frontend já envia o valor convertido (ex: "3500.00" com ponto decimal)
        $valor = floatval($valor);
        if ($valor <= 0) {
            throw new Exception('O valor total deve ser maior que zero.');
        }
        
        // Validar formato da data
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_vencimento)) {
            throw new Exception('Data de vencimento inválida. Use o formato YYYY-MM-DD.');
        }
        
        // Verificar se o aluno existe
        $aluno = $db->fetch("SELECT id, nome FROM alunos WHERE id = ?", [$aluno_id]);
        if (!$aluno) {
            throw new Exception('Aluno não encontrado.');
        }
        
        // Verificar se é parcelamento
        $parcelamento = isset($_POST['parcelamento']) && $_POST['parcelamento'] === 'on';
        
        if ($parcelamento) {
            // Verificar se parcelas foram editadas manualmente
            $parcelas_editadas = null;
            if (isset($_POST['parcelas_editadas']) && !empty($_POST['parcelas_editadas'])) {
                $parcelas_editadas = json_decode($_POST['parcelas_editadas'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Erro ao processar parcelas editadas: ' . json_last_error_msg());
                }
            }
            
            // Se houver parcelas editadas, usar elas diretamente
            if ($parcelas_editadas && is_array($parcelas_editadas) && count($parcelas_editadas) > 0) {
                $faturas_criadas = [];
                $total_parcelas = count($parcelas_editadas); // Total de itens (entrada + parcelas)
                
                foreach ($parcelas_editadas as $parcela) {
                    $titulo_parcela = isset($parcela['tipo']) && $parcela['tipo'] === 'entrada' 
                        ? $descricao . ' - Entrada'
                        : $descricao . " - {$parcela['numero']}ª parcela";
                    
                    $dados_parcela = [
                        'aluno_id' => $aluno_id,
                        'titulo' => $titulo_parcela, // Campo obrigatório
                        'valor' => floatval($parcela['valor']),
                        'valor_total' => floatval($parcela['valor']), // Campo obrigatório
                        'data_vencimento' => $parcela['vencimento'],
                        'observacoes' => isset($_POST['observacoes']) && !empty(trim($_POST['observacoes'])) ? trim($_POST['observacoes']) : null,
                        'status' => $_POST['status'] ?? 'aberta',
                        'forma_pagamento' => $_POST['forma_pagamento'] ?? 'boleto',
                        'parcelas' => $total_parcelas, // Número total de itens (entrada + parcelas)
                        'criado_em' => date('Y-m-d H:i:s'),
                        'criado_por' => $user['id']
                    ];
                    
                    try {
                        $fatura_id = $db->insert('financeiro_faturas', $dados_parcela);
                        if ($fatura_id) {
                            $faturas_criadas[] = $fatura_id;
                        }
                    } catch (PDOException $e) {
                        error_log('[FATURA CREATE PARCELA PDO ERROR] ' . $e->getMessage());
                        error_log('[FATURA CREATE PARCELA PDO ERROR] Dados: ' . json_encode($dados_parcela, JSON_UNESCAPED_UNICODE));
                        throw new Exception('Erro ao criar parcela: ' . $e->getMessage());
                    }
                }
                
                if (count($faturas_criadas) > 0) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Fatura parcelada criada com sucesso! ' . count($faturas_criadas) . ' faturas geradas.',
                        'faturas_criadas' => $faturas_criadas,
                        'parcelamento' => true
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    // Restaurar error handler antes de sair
                    if ($previousErrorHandler !== null) {
                        set_error_handler($previousErrorHandler);
                    } else {
                        restore_error_handler();
                    }
                    exit;
                } else {
                    throw new Exception('Erro ao criar faturas parceladas.');
                }
            } else {
                // Processar parcelamento automático (cálculo original)
                // $valor já foi convertido para float na validação acima
                $valor_total = $valor;
                // Converter entrada: pode vir como "500.00" (já convertido pelo frontend) ou "500,00" (formato brasileiro)
                $entrada_raw = isset($_POST['entrada']) ? trim($_POST['entrada']) : '0';
                // Se contém vírgula, é formato brasileiro; senão, já está convertido
                if (strpos($entrada_raw, ',') !== false) {
                    $entrada = floatval(str_replace(',', '.', str_replace('.', '', $entrada_raw)));
                } else {
                    $entrada = floatval($entrada_raw);
                }
                $num_parcelas = intval($_POST['num_parcelas'] ?? 1);
                $intervalo_dias = intval($_POST['intervalo_parcelas'] ?? 30);
                
                // Validar parcelamento
                if ($entrada > $valor_total) {
                    throw new Exception('O valor da entrada não pode ser maior que o valor total.');
                }
                
                if ($num_parcelas < 1) {
                    throw new Exception('Número de parcelas deve ser maior que zero.');
                }
                
                // Calcular valor das parcelas
                $valor_restante = $valor_total - $entrada;
                $valor_parcela = $valor_restante / $num_parcelas;
                
                // Data base para cálculo
                $data_base = new DateTime($data_vencimento);
                $faturas_criadas = [];
                
                // Criar entrada se houver
                if ($entrada > 0) {
                    $titulo_entrada = $descricao . ' - Entrada';
                    $dados_entrada = [
                        'aluno_id' => $aluno_id,
                        'titulo' => $titulo_entrada, // Campo obrigatório
                        'valor' => $entrada,
                        'valor_total' => $entrada, // Campo obrigatório
                        'data_vencimento' => $data_base->format('Y-m-d'),
                        'observacoes' => isset($_POST['observacoes']) && !empty(trim($_POST['observacoes'])) ? trim($_POST['observacoes']) : null,
                        'status' => $_POST['status'] ?? 'aberta',
                        'forma_pagamento' => $_POST['forma_pagamento'] ?? 'boleto',
                        'parcelas' => $num_parcelas + 1, // Entrada + parcelas
                        'criado_em' => date('Y-m-d H:i:s'),
                        'criado_por' => $user['id']
                    ];
                    
                    try {
                        $fatura_id = $db->insert('financeiro_faturas', $dados_entrada);
                        if ($fatura_id) {
                            $faturas_criadas[] = $fatura_id;
                        }
                    } catch (PDOException $e) {
                        error_log('[FATURA CREATE ENTRADA PDO ERROR] ' . $e->getMessage());
                        throw new Exception('Erro ao criar entrada: ' . $e->getMessage());
                    }
                }
                
                // Criar parcelas
                for ($i = 1; $i <= $num_parcelas; $i++) {
                    $data_parcela = clone $data_base;
                    $data_parcela->add(new DateInterval('P' . ($i * $intervalo_dias) . 'D'));
                    
                    $titulo_parcela = $descricao . " - {$i}ª parcela de {$num_parcelas}";
                    $dados_parcela = [
                        'aluno_id' => $aluno_id,
                        'titulo' => $titulo_parcela, // Campo obrigatório
                        'valor' => $valor_parcela,
                        'valor_total' => $valor_parcela, // Campo obrigatório
                        'data_vencimento' => $data_parcela->format('Y-m-d'),
                        'observacoes' => isset($_POST['observacoes']) && !empty(trim($_POST['observacoes'])) ? trim($_POST['observacoes']) : null,
                        'status' => $_POST['status'] ?? 'aberta',
                        'forma_pagamento' => $_POST['forma_pagamento'] ?? 'boleto',
                        'parcelas' => $num_parcelas, // Número total de parcelas
                        'criado_em' => date('Y-m-d H:i:s'),
                        'criado_por' => $user['id']
                    ];
                    
                    try {
                        $fatura_id = $db->insert('financeiro_faturas', $dados_parcela);
                        if ($fatura_id) {
                            $faturas_criadas[] = $fatura_id;
                        }
                    } catch (PDOException $e) {
                        error_log('[FATURA CREATE PARCELA PDO ERROR] ' . $e->getMessage());
                        throw new Exception('Erro ao criar parcela ' . $i . ': ' . $e->getMessage());
                    }
                }
                
                if (count($faturas_criadas) > 0) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Fatura parcelada criada com sucesso! ' . count($faturas_criadas) . ' faturas geradas.',
                        'faturas_criadas' => $faturas_criadas,
                        'parcelamento' => true
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    // Restaurar error handler antes de sair
                    if ($previousErrorHandler !== null) {
                        set_error_handler($previousErrorHandler);
                    } else {
                        restore_error_handler();
                    }
                    exit;
                } else {
                    throw new Exception('Erro ao criar faturas parceladas.');
                }
            }
            
        } else {
            // Fatura única
            // $valor já foi convertido para float na validação acima
            // A tabela financeiro_faturas exige 'titulo' (NOT NULL) e 'valor_total' (NOT NULL)
            $dados = [
                'aluno_id' => $aluno_id,
                'titulo' => $descricao, // Usar descricao como titulo (campo obrigatório)
                'valor' => $valor,
                'valor_total' => $valor, // Campo obrigatório na tabela
                'data_vencimento' => $data_vencimento,
                'observacoes' => isset($_POST['observacoes']) && !empty(trim($_POST['observacoes'])) ? trim($_POST['observacoes']) : null,
                'status' => $_POST['status'] ?? 'aberta',
                'forma_pagamento' => $_POST['forma_pagamento'] ?? 'boleto',
                'parcelas' => 1, // Fatura única = 1 parcela
                'criado_em' => date('Y-m-d H:i:s'),
                'criado_por' => $user['id']
            ];
            
            // Log dos dados antes do insert (para debug)
            if (defined('LOG_ENABLED') && LOG_ENABLED) {
                error_log('[FATURA CREATE] Dados preparados: ' . json_encode($dados, JSON_UNESCAPED_UNICODE));
            }
            
            // Inserir fatura
            try {
                $fatura_id = $db->insert('financeiro_faturas', $dados);
                
                if ($fatura_id) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Fatura criada com sucesso!',
                        'fatura_id' => $fatura_id,
                        'parcelamento' => false
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    // Restaurar error handler antes de sair
                    if ($previousErrorHandler !== null) {
                        set_error_handler($previousErrorHandler);
                    } else {
                        restore_error_handler();
                    }
                    exit;
                } else {
                    throw new Exception('Erro ao criar fatura no banco de dados. Insert retornou false.');
                }
            } catch (PDOException $e) {
                // Log do erro PDO específico
                error_log('[FATURA CREATE PDO ERROR] ' . $e->getMessage());
                error_log('[FATURA CREATE PDO ERROR] SQL State: ' . $e->getCode());
                error_log('[FATURA CREATE PDO ERROR] Dados tentados: ' . json_encode($dados, JSON_UNESCAPED_UNICODE));
                throw new Exception('Erro ao inserir fatura no banco de dados: ' . $e->getMessage());
            }
        }
        
    } catch (Throwable $e) {
        // Sempre retornar 500 para erros não tratados (pode ser ajustado para 400 em validações específicas)
        http_response_code(500);
        
        // Montar debug com informações completas
        $debug = [
            'type' => get_class($e),
            'msg' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ];
        
        // Log para o servidor
        error_log('[FATURA CREATE ERROR] ' . json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        
        // Garantir header JSON
        header('Content-Type: application/json; charset=utf-8');
        
        // Retornar JSON de erro
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao criar fatura. Detalhes em debug (ambiente de desenvolvimento).',
            'debug' => $debug
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Restaurar error handler antes de sair
        if ($previousErrorHandler !== null) {
            set_error_handler($previousErrorHandler);
        } else {
            restore_error_handler();
        }
        
        exit;
    } finally {
        // Garantir que o error handler seja restaurado mesmo se houver algum problema
        if (isset($previousErrorHandler)) {
            if ($previousErrorHandler !== null) {
                set_error_handler($previousErrorHandler);
            } else {
                restore_error_handler();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'veiculos') {
    // Processar formulário de veículos diretamente
    try {
        $acao = $_POST['acao'] ?? '';
        
        if ($acao === 'criar') {
            // Criar novo veículo
            $dados = [
                'cfc_id' => $_POST['cfc_id'] ?? null,
                'placa' => $_POST['placa'] ?? '',
                'marca' => $_POST['marca'] ?? '',
                'modelo' => $_POST['modelo'] ?? '',
                'ano' => $_POST['ano'] ?? null,
                'categoria_cnh' => $_POST['categoria_cnh'] ?? '',
                'cor' => $_POST['cor'] ?? null,
                'cod_seg_crv' => $_POST['cod_seg_crv'] ?? null,
                'chassi' => $_POST['chassi'] ?? null,
                'renavam' => $_POST['renavam'] ?? null,
                'combustivel' => $_POST['combustivel'] ?? null,
                'quilometragem' => $_POST['quilometragem'] ?? 0,
                'km_manutencao' => $_POST['km_manutencao'] ?? null,
                'data_aquisicao' => $_POST['data_aquisicao'] ?? null,
                'valor_aquisicao' => $_POST['valor_aquisicao'] ? str_replace(',', '.', str_replace('.', '', $_POST['valor_aquisicao'])) : null,
                'proxima_manutencao' => $_POST['proxima_manutencao'] ?? null,
                'disponivel' => $_POST['disponivel'] ?? 1,
                'observacoes' => $_POST['observacoes'] ?? null,
                'status' => $_POST['status'] ?? 'ativo',
                'ativo' => 1,
                'criado_em' => date('Y-m-d H:i:s')
            ];
            
            // Validar campos obrigatórios
            if (empty($dados['placa']) || empty($dados['marca']) || empty($dados['modelo']) || empty($dados['cfc_id'])) {
                throw new Exception('Placa, marca, modelo e CFC são obrigatórios');
            }
            
            // Verificar se a placa já existe
            $placaExistente = $db->fetch("SELECT id FROM veiculos WHERE placa = ?", [$dados['placa']]);
            if ($placaExistente) {
                throw new Exception('Placa já cadastrada no sistema');
            }
            
            $id = $db->insert('veiculos', $dados);
            
            if ($id) {
                header('Location: index.php?page=veiculos&msg=success&msg_text=' . urlencode('Veículo cadastrado com sucesso!'));
                exit;
            } else {
                throw new Exception('Erro ao cadastrar veículo');
            }
            
        } elseif ($acao === 'editar') {
            // Editar veículo existente
            $veiculo_id = $_POST['veiculo_id'] ?? 0;
            
            if (!$veiculo_id) {
                throw new Exception('ID do veículo não informado');
            }
            
            $dados = [
                'cfc_id' => $_POST['cfc_id'] ?? null,
                'placa' => $_POST['placa'] ?? '',
                'marca' => $_POST['marca'] ?? '',
                'modelo' => $_POST['modelo'] ?? '',
                'ano' => $_POST['ano'] ?? null,
                'categoria_cnh' => $_POST['categoria_cnh'] ?? '',
                'cor' => $_POST['cor'] ?? null,
                'cod_seg_crv' => $_POST['cod_seg_crv'] ?? null,
                'chassi' => $_POST['chassi'] ?? null,
                'renavam' => $_POST['renavam'] ?? null,
                'combustivel' => $_POST['combustivel'] ?? null,
                'quilometragem' => $_POST['quilometragem'] ?? 0,
                'km_manutencao' => $_POST['km_manutencao'] ?? null,
                'data_aquisicao' => $_POST['data_aquisicao'] ?? null,
                'valor_aquisicao' => $_POST['valor_aquisicao'] ? str_replace(',', '.', str_replace('.', '', $_POST['valor_aquisicao'])) : null,
                'proxima_manutencao' => $_POST['proxima_manutencao'] ?? null,
                'disponivel' => $_POST['disponivel'] ?? 1,
                'observacoes' => $_POST['observacoes'] ?? null,
                'status' => $_POST['status'] ?? 'ativo',
                'atualizado_em' => date('Y-m-d H:i:s')
            ];
            
            // Validar campos obrigatórios
            if (empty($dados['placa']) || empty($dados['marca']) || empty($dados['modelo']) || empty($dados['cfc_id'])) {
                throw new Exception('Placa, marca, modelo e CFC são obrigatórios');
            }
            
            // Verificar se a placa já existe em outro veículo
            $placaExistente = $db->fetch("SELECT id FROM veiculos WHERE placa = ? AND id != ?", [$dados['placa'], $veiculo_id]);
            if ($placaExistente) {
                throw new Exception('Placa já cadastrada em outro veículo');
            }
            
            $resultado = $db->update('veiculos', $dados, 'id = ?', [$veiculo_id]);
            
            if ($resultado) {
                header('Location: index.php?page=veiculos&msg=success&msg_text=' . urlencode('Veículo atualizado com sucesso!'));
                exit;
            } else {
                throw new Exception('Erro ao atualizar veículo');
            }
        }
        
    } catch (Exception $e) {
        header('Location: index.php?page=veiculos&msg=danger&msg_text=' . urlencode('Erro: ' . $e->getMessage()));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSP configurado para permitir fontes base64 e Font Awesome -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://kit.fontawesome.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://kit.fontawesome.com; font-src 'self' data: blob: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://kit.fontawesome.com https://cdn.jsdelivr.net; img-src 'self' data: https:; connect-src 'self' https://viacep.com.br https://cdn.jsdelivr.net https://unpkg.com; object-src 'none'; base-uri 'self';">
    
    <!-- PWA Meta Tags -->
    <meta name="application-name" content="CFC Bom Conselho">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CFC Admin">
    <meta name="description" content="Sistema administrativo para gerenciamento de CFC">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-config" content="/pwa/browserconfig.xml">
    <meta name="msapplication-TileColor" content="#2c3e50">
    <meta name="msapplication-tap-highlight" content="no">
    <meta name="theme-color" content="#2c3e50">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="../pwa/manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="../pwa/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="../pwa/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../pwa/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="167x167" href="../pwa/icons/icon-192.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="../pwa/icons/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../pwa/icons/icon-16.png">
    <link rel="shortcut icon" href="../pwa/icons/icon-32.png">
    
    <title>Dashboard Administrativo - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Theme Tokens (deve vir antes de outros CSS) -->
    <link href="../assets/css/theme-tokens.css" rel="stylesheet">
    
    <!-- Theme Overrides Global (dark mode fixes) -->
    <link href="../assets/css/theme-overrides.css?v=1.0.10" rel="stylesheet">
    
    <!-- CSS Principal -->
    <link href="assets/css/admin.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="assets/css/modal-veiculos.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <!-- Mobile Fallback CSS -->
    <link href="assets/css/mobile-fallback.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <!-- CSS dos Botões de Ação -->
    <link href="assets/css/action-buttons.css" rel="stylesheet">
    
    <!-- CSS do Menu com Ícones -->
    <link href="assets/css/sidebar-icons.css" rel="stylesheet">
    
    <!-- CSS Final do Menu -->
    <link href="assets/css/menu-flyout.css" rel="stylesheet">
    
    <!-- CSS de Correções para Sidebar -->
    <link href="assets/css/sidebar-fixes.css" rel="stylesheet">
    
    <!-- CSS para Modais Responsivos -->
    <link href="assets/css/modals-responsive.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <!-- CSS para Modais Popup -->
    <link href="assets/css/popup-reference.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <!-- CSS para Modais de Formulário Longo (Padrão) -->
    <link href="assets/css/modal-form.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <!-- CSS para Sistema de Modal Singleton -->
    <style>
        /* Modal Root - Sistema Singleton */
        #modal-root {
            position: relative;
            z-index: 10000;
        }
        
        /* Backdrop único - Sistema Singleton */
        #modal-root .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9999;
            backdrop-filter: blur(2px);
        }
        
        /* Wrapper do modal centralizado - Sistema Singleton */
        #modal-root .modal-wrapper {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        /* Caixa do modal - Sistema Singleton */
        #modal-root .modal {
            width: min(90vw, 1200px);
            max-height: min(85vh, 900px);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            display: grid;
            grid-template-rows: auto 1fr auto;
            overflow: hidden;
            position: relative;
        }
        
        /* Header do modal - Sistema Singleton */
        #modal-root .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        /* ÚNICO scroll fica no corpo do modal - Sistema Singleton */
        #modal-root .modal-content {
            overflow-y: auto !important;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
            padding: 1.5rem;
            flex: 1;
        }
        
        /* Footer do modal - Sistema Singleton */
        #modal-root .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        
        /* Botão de fechar - Sistema Singleton */
        #modal-root .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out;
        }
        
        #modal-root .modal-close:hover {
            color: #000;
        }
        
        /* Mobile: full-screen - Sistema Singleton */
        @media (max-width: 768px) {
            #modal-root .modal-wrapper {
                padding: 0;
            }
            
            #modal-root .modal {
                width: 100vw;
                max-height: 100vh;
                border-radius: 0;
            }
            
            #modal-root .modal-header,
            #modal-root .modal-content,
            #modal-root .modal-footer {
                padding: 1rem;
            }
        }
        
        /* Animação de entrada - Sistema Singleton */
        #modal-root .modal-wrapper {
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Scrollbar personalizada - Sistema Singleton */
        #modal-root .modal-content::-webkit-scrollbar {
            width: 6px;
        }
        
        #modal-root .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        #modal-root .modal-content::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        #modal-root .modal-content::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Remover rolagem interna - APENAS modal-content pode ter scroll */
        #modal-root .disciplinas-grid,
        #modal-root .panel,
        #modal-root .cards-wrapper,
        #modal-root .disciplinas-panel,
        #modal-root .card,
        #modal-root .card-body,
        #modal-root .modal-body {
            overflow: visible !important;
            max-height: none !important;
            height: auto !important;
        }
        
        /* Remover moldura decorativa se existir */
        #modal-root .panel,
        #modal-root .cards-wrapper,
        #modal-root .disciplinas-panel {
            box-shadow: none !important;
            border: 0 !important;
            padding: 0 !important;
        }
        
        /* Neutralizar regras globais do modals-responsive.css */
        #modal-root .modal-body {
            overflow: visible !important;
            max-height: none !important;
            height: auto !important;
        }
        
        /* Forçar que APENAS modal-content tenha scroll */
        #modal-root .modal-content {
            overflow-y: auto !important;
        }
        
        #modal-root .modal-content > *:not(.modal-header):not(.modal-footer) {
            overflow: visible !important;
            max-height: none !important;
            height: auto !important;
        }
        
        /* Estilos para Modais Popup Globais */
        .popup-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1055;
            padding: 2rem;
        }
        
        .popup-modal-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 1200px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .popup-modal-header {
            background: linear-gradient(135deg, #023A8D 0%, #1e5bb8 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 12px 12px 0 0;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .header-text h5 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .header-text small {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .popup-modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .popup-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }
        
        .popup-modal-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        
        .popup-search-container {
            margin-bottom: 1.5rem;
        }
        
        .popup-search-wrapper {
            position: relative;
        }
        
        .popup-search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .popup-search-input:focus {
            outline: none;
            border-color: #023A8D;
            box-shadow: 0 0 0 3px rgba(2, 58, 141, 0.1);
        }
        
        .popup-search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1rem;
        }
        
        .popup-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .popup-section-title h6 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }
        
        .popup-section-title small {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .popup-stats-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }
        
        .popup-stats-icon .icon-circle {
            width: 40px;
            height: 40px;
            background: #023A8D;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        
        .popup-stats-text h6 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }
        
        .popup-primary-button {
            background: #023A8D;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .popup-primary-button:hover {
            background: #1e5bb8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(2, 58, 141, 0.3);
        }
        
        .popup-secondary-button {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .popup-secondary-button:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .popup-save-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .popup-save-button:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .popup-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .popup-item-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .popup-item-card:hover {
            border-color: #023A8D;
            box-shadow: 0 8px 25px rgba(2, 58, 141, 0.15);
            transform: translateY(-2px);
        }
        
        .popup-item-card.active {
            border-left: 4px solid #28a745;
        }
        
        .popup-item-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .popup-item-card-title {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .popup-item-card-description {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .popup-item-card-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .popup-item-card-menu {
            background: none;
            border: none;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #6c757d;
        }
        
        .popup-item-card-menu:hover {
            background: #f8f9fa;
            color: #023A8D;
        }
        
        .popup-modal-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 0 0 12px 12px;
        }
        
        .popup-footer-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .popup-footer-actions {
            display: flex;
            gap: 1rem;
        }
        
        .popup-loading-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .popup-loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e9ecef;
            border-top: 4px solid #023A8D;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .popup-loading-text h6 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .popup-loading-text p {
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* Estados de erro e vazio */
        .popup-empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .popup-empty-state .empty-icon {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: #023A8D;
        }
        
        .popup-empty-state h5 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }
        
        .popup-empty-state p {
            margin: 0 0 1.5rem 0;
            font-size: 1rem;
            color: #6c757d;
        }
        
        .popup-error-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .popup-error-state .error-icon {
            width: 80px;
            height: 80px;
            background: #f8d7da;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: #dc3545;
        }
        
        .popup-error-state h5 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #dc3545;
        }
        
        .popup-error-state p {
            margin: 0 0 1.5rem 0;
            font-size: 1rem;
            color: #6c757d;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .popup-modal {
                padding: 1rem;
            }
            
            .popup-modal-wrapper {
                max-height: 95vh;
            }
            
            .popup-modal-header {
                padding: 1rem 1.5rem;
            }
            
            .popup-modal-content {
                padding: 1.5rem;
            }
            
            .popup-modal-footer {
                padding: 1rem 1.5rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .popup-section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .popup-items-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
    <!-- CSS da Topbar Unificada -->
    <link href="assets/css/topbar-unified.css" rel="stylesheet">
    
    <!-- CSS de Correções Críticas de Layout -->
    <link href="assets/css/layout-fixes.css" rel="stylesheet">
    
    <!-- CSS do Menu Mobile Clean -->
    <link href="assets/css/mobile-menu-clean.css" rel="stylesheet">
    
    <!-- CSS Adicional para Garantir Apenas Ícones -->
    <style>
        /* Garantir que ícones sejam visíveis */
        .admin-sidebar .nav-icon {
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
            color: #ecf0f1 !important;
            font-size: 18px !important;
            width: 24px !important;
            height: 24px !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        /* Ocultar textos mas manter ícones */
        .admin-sidebar .nav-text,
        .admin-sidebar .nav-badge,
        .admin-sidebar .nav-arrow {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
        
        /* Garantir que apenas ícones sejam visíveis */
        .admin-sidebar .nav-link,
        .admin-sidebar .nav-toggle {
            justify-content: center !important;
            align-items: center !important;
            padding: 12px !important;
        }
        
        /* Garantir que elementos de texto não apareçam */
        .admin-sidebar .nav-link > span:not(.nav-icon),
        .admin-sidebar .nav-toggle > span:not(.nav-icon) {
            display: none !important;
        }
        
        /* Garantir que flyouts apareçam ao lado */
        .admin-sidebar .nav-flyout {
            position: fixed !important;
            z-index: 1000 !important;
            background-color: #2c3e50 !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3) !important;
            min-width: 200px !important;
            max-width: 250px !important;
            padding: 0 !important;
        }
        
        /* CORREÇÕES ESPECÍFICAS PARA TOPBAR - REMOVIDAS - AGORA NO CSS UNIFICADO */
        
        /* Garantir que flyouts mostrem apenas texto */
        .admin-sidebar .nav-flyout .flyout-title {
            color: #ecf0f1 !important;
            font-size: 16px !important;
            font-weight: 700 !important;
            padding: 12px 16px !important;
            background-color: rgba(255, 255, 255, 0.05) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 8px 8px 0 0 !important;
        }
        
        
        .admin-sidebar .nav-flyout .flyout-item {
            display: block !important;
            padding: 12px 16px !important;
            color: #ecf0f1 !important;
            text-decoration: none !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        
        .admin-sidebar .nav-flyout .flyout-item:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }
        
        .admin-sidebar .nav-flyout .flyout-item:last-child {
            border-bottom: none !important;
            border-radius: 0 0 8px 8px !important;
        }
        
        /* Ocultar ícones dos flyouts */
        .admin-sidebar .nav-flyout .flyout-icon {
            display: none !important;
        }
        
        /* Garantir que sidebar nunca expanda */
        .admin-sidebar {
            width: 70px !important;
            transition: none !important;
        }
        
        .admin-sidebar:hover {
            width: 70px !important;
        }
        
    </style>
    
    <!-- CSS Inline para Garantir Funcionamento em Produção -->
    <style>
        /* Estilos de expansão interna removidos - usando menu-flyout.css */
    </style>
    
    <!-- Font Awesome para ícones -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/logo.png">
</head>
<body>
    <!-- Container Principal -->
    <div class="admin-container">
        
        <!-- Topbar Completa - STICKY/FIXED -->
        <div class="topbar" id="main-topbar">
            <!-- Logo -->
            <a href="?page=dashboard" class="topbar-logo">
                <div class="topbar-logo-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="topbar-logo-text">CFC Bom Conselho</div>
            </a>
            
            <!-- Busca Global -->
            <div class="topbar-search">
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        class="search-input" 
                        placeholder="Pesquisar por nome, CPF, matrícula, telefone..."
                        autocomplete="off"
                        aria-label="Busca global"
                    >
                    <i class="fas fa-search search-icon"></i>
                    <div class="search-results" id="search-results" role="listbox" aria-label="Resultados da pesquisa"></div>
                </div>
            </div>
            
            <!-- Notificações e Perfil (Direita) -->
            <div class="topbar-right">
                <!-- Botão Hambúrguer Mobile -->
                <button 
                    class="mobile-menu-toggle" 
                    id="mobile-menu-toggle"
                    aria-label="Abrir menu de navegação"
                    aria-expanded="false"
                    aria-controls="mobile-drawer"
                >
                    <span class="hamburger-icon">
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                    </span>
                </button>
                
                <!-- Notificações -->
                <div class="topbar-notifications">
                    <button class="notification-icon" aria-label="Notificações">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge hidden" id="notification-badge">0</span>
                    </button>
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notification-header">
                            <h3 class="notification-title">Notificações</h3>
                        </div>
                        <div class="notification-list" id="notification-list">
                            <div class="search-loading">Carregando notificações...</div>
                        </div>
                        <div class="notification-footer">
                            <div class="notification-actions">
                                <button class="notification-btn" id="mark-all-read">Marcar todas como lidas</button>
                                <a href="?page=notifications" class="notification-btn">Ver todas</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- DEBUG_TOPBAR:
                     origem=<?php echo htmlspecialchars($origem); ?>;
                     user_type=<?php echo htmlspecialchars($userType); ?>;
                     isFluxoInstrutor=<?php echo $isFluxoInstrutor ? '1' : '0'; ?>;
                     user_id=<?php echo (int)$userId; ?>;
                     instrutor_id=<?php echo isset($instrutorId) ? (int)$instrutorId : 0; ?>;
                -->
                <!-- Perfil do Usuário -->
                <div class="topbar-profile">
                    <button class="profile-button" id="profile-button" aria-label="Perfil do usuário">
                        <div class="profile-avatar" id="profile-avatar"><?php echo strtoupper(substr($userDisplayName, 0, 1)); ?></div>
                        <div class="profile-info">
                            <div class="profile-name" id="profile-name"><?php echo htmlspecialchars($userDisplayName); ?></div>
                            <div class="profile-role" id="profile-role"><?php echo htmlspecialchars($userDisplayRole); ?></div>
                        </div>
                        <i class="fas fa-chevron-down profile-dropdown-icon"></i>
                    </button>
                    <div class="profile-dropdown" id="profile-dropdown">
                        <a href="?page=profile" class="profile-dropdown-item">
                            <i class="fas fa-user profile-dropdown-icon-item"></i>
                            Meu Perfil
                        </a>
                        <a href="?page=change-password" class="profile-dropdown-item">
                            <i class="fas fa-key profile-dropdown-icon-item"></i>
                            Trocar senha
                        </a>
                        <a href="logout.php" class="profile-dropdown-item logout">
                            <i class="fas fa-sign-out-alt profile-dropdown-icon-item"></i>
                            Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar de Navegação -->
        <nav class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Navegação</div>
                <div class="sidebar-subtitle">Sistema CFC</div>
            </div>
            
            <div class="nav-menu">
                <!-- Dashboard -->
                <div class="nav-item">
                    <a href="index.php" class="nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>" title="Dashboard">
                        <div class="nav-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="nav-text">Dashboard</div>
                    </a>
                </div>
                
                <!-- Alunos -->
                <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                <div class="nav-item nav-group">
                    <a href="index.php?page=alunos" class="nav-link nav-toggle" data-group="alunos" title="Alunos">
                        <div class="nav-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="nav-text">Alunos</div>
                        <div class="nav-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </a>
                    <div class="nav-submenu" id="alunos">
                        <a href="index.php?page=alunos" class="nav-sublink <?php echo ($page === 'alunos' && !isset($_GET['status'])) ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>Todos os Alunos</span>
                            <div class="nav-badge"><?php echo $stats['total_alunos']; ?></div>
                        </a>
                        <a href="index.php?page=alunos&status=em_formacao" class="nav-sublink <?php echo ($page === 'alunos' && ($_GET['status'] ?? '') === 'em_formacao') ? 'active' : ''; ?>">
                            <i class="fas fa-user-check"></i>
                            <span>Alunos Ativos</span>
                        </a>
                        <a href="index.php?page=alunos&status=em_exame" class="nav-sublink <?php echo ($page === 'alunos' && ($_GET['status'] ?? '') === 'em_exame') ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Alunos em Exame</span>
                        </a>
                        <a href="index.php?page=alunos&status=concluido" class="nav-sublink <?php echo ($page === 'alunos' && ($_GET['status'] ?? '') === 'concluido') ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i>
                            <span>Alunos Concluídos</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Acadêmico -->
                <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                <div class="nav-item nav-group">
                    <a href="index.php?page=turmas-teoricas" class="nav-link nav-toggle" data-group="academico" title="Acadêmico">
                        <div class="nav-icon">
                            <i class="fas fa-book-reader"></i>
                        </div>
                        <div class="nav-text">Acadêmico</div>
                        <div class="nav-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </a>
                    <div class="nav-submenu" id="academico">
                        <a href="index.php?page=turmas-teoricas" class="nav-sublink <?php echo $page === 'turmas-teoricas' ? 'active' : ''; ?>">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Turmas Teóricas</span>
                        </a>
                        <!-- 
                            AJUSTE MENU PRESENCA TEORICA - REMOVIDO ITEM TEMPORÁRIO
                            O fluxo oficial de presença TEÓRICA é via:
                            Acadêmico → Turmas Teóricas → Detalhes da Turma → Seleção da Aula → Chamada/Frequência
                            
                            O antigo menu "Presenças Teóricas (Temporário)" foi removido para evitar duplicidade de caminhos.
                            A página turma-chamada.php continua existindo e funcionando, mas não deve ser acessada diretamente
                            pelo menu - apenas através do fluxo oficial via Turmas Teóricas.
                        -->
                        <!-- TODO: Criar página aulas-praticas.php - por enquanto usar listar-aulas.php -->
                        <a href="pages/listar-aulas.php" class="nav-sublink">
                            <i class="fas fa-car-side"></i>
                            <span>Aulas Práticas</span>
                            <small style="color: #999; font-size: 0.7em;">(Temporário)</small>
                        </a>
                        <a href="index.php?page=agendamento" class="nav-sublink <?php echo $page === 'agendamento' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Agenda Geral</span>
                            <div class="nav-badge"><?php echo $stats['total_aulas']; ?></div>
                        </a>
                        <?php if ($isAdmin): ?>
                        <a href="index.php?page=instrutores" class="nav-sublink <?php echo $page === 'instrutores' ? 'active' : ''; ?>">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Instrutores</span>
                            <div class="nav-badge"><?php echo $stats['total_instrutores']; ?></div>
                        </a>
                        <a href="index.php?page=veiculos" class="nav-sublink <?php echo $page === 'veiculos' ? 'active' : ''; ?>">
                            <i class="fas fa-car"></i>
                            <span>Veículos</span>
                            <div class="nav-badge"><?php echo $stats['total_veiculos']; ?></div>
                        </a>
                        <a href="index.php?page=configuracoes-salas" class="nav-sublink <?php echo $page === 'configuracoes-salas' ? 'active' : ''; ?>">
                            <i class="fas fa-door-open"></i>
                            <span>Salas</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Provas & Exames -->
                <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                <div class="nav-item nav-group">
                    <a href="index.php?page=exames&tipo=teorico" class="nav-link nav-toggle" data-group="provas-exames" title="Provas & Exames">
                        <div class="nav-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="nav-text">Provas & Exames</div>
                        <div class="nav-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </a>
                    <div class="nav-submenu" id="provas-exames">
                        <a href="index.php?page=exames&tipo=medico" class="nav-sublink <?php echo ($page === 'exames' && ($_GET['tipo'] ?? '') === 'medico') ? 'active' : ''; ?>">
                            <i class="fas fa-stethoscope"></i>
                            <span>Exame Médico</span>
                        </a>
                        <a href="index.php?page=exames&tipo=psicotecnico" class="nav-sublink <?php echo ($page === 'exames' && ($_GET['tipo'] ?? '') === 'psicotecnico') ? 'active' : ''; ?>">
                            <i class="fas fa-brain"></i>
                            <span>Exame Psicotécnico</span>
                        </a>
                        <a href="index.php?page=exames&tipo=teorico" class="nav-sublink <?php echo ($page === 'exames' && ($_GET['tipo'] ?? '') === 'teorico') ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt"></i>
                            <span>Prova Teórica</span>
                        </a>
                        <a href="index.php?page=exames&tipo=pratico" class="nav-sublink <?php echo ($page === 'exames' && ($_GET['tipo'] ?? '') === 'pratico') ? 'active' : ''; ?>">
                            <i class="fas fa-car"></i>
                            <span>Prova Prática</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Financeiro -->
                <?php if (defined('FINANCEIRO_ENABLED') && FINANCEIRO_ENABLED && ($isAdmin || $user['tipo'] === 'secretaria')): ?>
                <div class="nav-item nav-group">
                    <a href="index.php?page=financeiro-faturas" class="nav-link nav-toggle" data-group="financeiro" title="Financeiro">
                        <div class="nav-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="nav-text">Financeiro</div>
                        <div class="nav-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </a>
                    <div class="nav-submenu" id="financeiro">
                        <a href="index.php?page=financeiro-faturas" class="nav-sublink <?php echo $page === 'financeiro-faturas' ? 'active' : ''; ?>">
                            <i class="fas fa-file-invoice"></i>
                            <span>Faturas</span>
                        </a>
                        <!-- Corrigido: usar financeiro-despesas (página que existe) ao invés de financeiro-pagamentos -->
                        <a href="index.php?page=financeiro-despesas" class="nav-sublink <?php echo $page === 'financeiro-despesas' ? 'active' : ''; ?>">
                            <i class="fas fa-receipt"></i>
                            <span>Pagamentos</span>
                        </a>
                        <?php if ($isAdmin): ?>
                        <a href="index.php?page=financeiro-relatorios" class="nav-sublink <?php echo $page === 'financeiro-relatorios' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Relatórios Financeiros</span>
                        </a>
                        <?php endif; ?>
                        <!-- TODO: Criar página financeiro-configuracoes.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-cog"></i>
                            <span>Configurações Financeiras</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Relatórios -->
                <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                <div class="nav-item nav-group">
                    <a href="pages/relatorio-frequencia.php" class="nav-link nav-toggle" data-group="relatorios" title="Relatórios">
                        <div class="nav-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="nav-text">Relatórios</div>
                        <div class="nav-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </a>
                    <div class="nav-submenu" id="relatorios">
                        <a href="pages/relatorio-frequencia.php" class="nav-sublink <?php echo ($page === 'relatorio-frequencia' || $page === 'relatorio-presencas') ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i>
                            <span>Frequência Teórica</span>
                        </a>
                        <!-- TODO: Criar página relatorio-conclusao-pratica.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Relatório em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-check-circle"></i>
                            <span>Conclusão Prática</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                        <!-- TODO: Criar página relatorio-provas.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Relatório em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Provas (Taxa de Aprovação)</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                        <?php if ($isAdmin): ?>
                        <a href="index.php?page=financeiro-relatorios&tipo=inadimplencia" class="nav-sublink <?php echo ($page === 'financeiro-relatorios' && ($_GET['tipo'] ?? '') === 'inadimplencia') ? 'active' : ''; ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Inadimplência</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Usuários do Sistema -->
                <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                <div class="nav-item">
                    <a href="index.php?page=usuarios" class="nav-link <?php echo $page === 'usuarios' ? 'active' : ''; ?>" title="Usuários do Sistema">
                        <div class="nav-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="nav-text">Usuários</div>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Configurações -->
                <?php if ($isAdmin): ?>
                <div class="nav-item nav-group">
                    <a href="index.php?page=configuracoes-categorias" class="nav-link nav-toggle" data-group="configuracoes" title="Configurações">
                        <div class="nav-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="nav-text">Configurações</div>
                        <div class="nav-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </a>
                    <div class="nav-submenu" id="configuracoes">
                        <a href="index.php?page=configuracoes&action=dados-cfc" class="nav-sublink <?php echo ($page === 'configuracoes' && ($_GET['action'] ?? '') === 'dados-cfc') ? 'active' : ''; ?>">
                            <i class="fas fa-building"></i>
                            <span>Dados do CFC</span>
                        </a>
                        <a href="index.php?page=configuracoes-categorias" class="nav-sublink <?php echo $page === 'configuracoes-categorias' ? 'active' : ''; ?>">
                            <i class="fas fa-layer-group"></i>
                            <span>Cursos / Categorias</span>
                        </a>
                        <!-- TODO: Criar página configuracoes-horarios.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-clock"></i>
                            <span>Tabela de Horários</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                        <!-- TODO: Criar página configuracoes-bloqueios.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-ban"></i>
                            <span>Regras de Bloqueio</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                        <!-- TODO: Criar página configuracoes-documentos.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-file-pdf"></i>
                            <span>Modelos de Documentos</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                        <a href="index.php?page=configuracoes-disciplinas" class="nav-sublink <?php echo $page === 'configuracoes-disciplinas' ? 'active' : ''; ?>">
                            <i class="fas fa-book"></i>
                            <span>Disciplinas</span>
                        </a>
                        <?php if ($isAdmin): ?>
                        <a href="index.php?page=aplicar-indices" class="nav-sublink <?php echo $page === 'aplicar-indices' ? 'active' : ''; ?>">
                            <i class="fas fa-database"></i>
                            <span>Otimizar Banco (Índices)</span>
                        </a>
                        <a href="index.php?page=diagnostico-queries" class="nav-sublink <?php echo $page === 'diagnostico-queries' ? 'active' : ''; ?>">
                            <i class="fas fa-search"></i>
                            <span>Diagnóstico de Queries</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                        <a href="index.php?page=configuracoes-smtp" class="nav-sublink <?php echo $page === 'configuracoes-smtp' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope"></i>
                            <span>E-mail (SMTP)</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Sistema / Ajuda -->
                <?php if ($isAdmin): ?>
                <div class="nav-item nav-group">
                    <a href="index.php?page=faq" class="nav-link nav-toggle" data-group="sistema-ajuda" title="Sistema / Ajuda">
                        <div class="nav-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="nav-text">Sistema / Ajuda</div>
                        <div class="nav-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </a>
                    <div class="nav-submenu" id="sistema-ajuda">
                        <a href="index.php?page=logs&action=list" class="nav-sublink <?php echo ($page === 'logs' && ($_GET['action'] ?? '') === 'list') ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt"></i>
                            <span>Logs</span>
                        </a>
                        <!-- TODO: Criar página faq.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-question"></i>
                            <span>FAQ</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                        <!-- TODO: Criar página suporte.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-headset"></i>
                            <span>Suporte</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                        <!-- TODO: Criar página backup.php -->
                        <a href="#" class="nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                            <i class="fas fa-download"></i>
                            <span>Backup</span>
                            <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Sair -->
                <div class="nav-item">
                    <!-- logout.php está em admin/logout.php, então usar ./logout.php -->
                    <a href="./logout.php" class="nav-link" title="Sair">
                        <div class="nav-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <div class="nav-text">Sair</div>
                    </a>
                </div>
            </div>
        </nav>
        
        <!-- Mobile Drawer Navigation -->
        <div class="mobile-drawer" id="mobile-drawer" role="navigation" aria-label="Menu de navegação principal">
            <div class="mobile-drawer-overlay" id="mobile-drawer-overlay"></div>
            <div class="mobile-drawer-content">
                <div class="mobile-drawer-header">
                    <div class="mobile-drawer-logo">
                        <i class="fas fa-car"></i>
                        <span>CFC Bom Conselho</span>
                    </div>
                    <button 
                        class="mobile-drawer-close" 
                        id="mobile-drawer-close"
                        aria-label="Fechar menu de navegação"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mobile-drawer-body">
                    <nav class="mobile-nav">
                        <!-- Dashboard -->
                        <div class="mobile-nav-item">
                            <a href="index.php" class="mobile-nav-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-line"></i>
                                <span>Dashboard</span>
                            </a>
                        </div>
                        
                        <!-- Alunos -->
                        <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                        <div class="mobile-nav-group" data-group="alunos">
                            <div class="mobile-nav-group-header">
                                <i class="fas fa-graduation-cap"></i>
                                <span>Alunos</span>
                                <i class="fas fa-chevron-down mobile-nav-arrow"></i>
                            </div>
                            <div class="mobile-nav-submenu">
                                <a href="index.php?page=alunos" class="mobile-nav-sublink <?php echo ($page === 'alunos' && !isset($_GET['status'])) ? 'active' : ''; ?>">
                                    <i class="fas fa-list"></i>
                                    <span>Todos os Alunos</span>
                                    <span class="mobile-nav-badge"><?php echo $stats['total_alunos']; ?></span>
                                </a>
                                <a href="index.php?page=alunos&status=em_formacao" class="mobile-nav-sublink <?php echo ($page === 'alunos' && ($_GET['status'] ?? '') === 'em_formacao') ? 'active' : ''; ?>">
                                    <i class="fas fa-user-check"></i>
                                    <span>Alunos Ativos</span>
                                </a>
                                <a href="index.php?page=alunos&status=em_exame" class="mobile-nav-sublink <?php echo ($page === 'alunos' && ($_GET['status'] ?? '') === 'em_exame') ? 'active' : ''; ?>">
                                    <i class="fas fa-clipboard-check"></i>
                                    <span>Alunos em Exame</span>
                                </a>
                                <a href="index.php?page=alunos&status=concluido" class="mobile-nav-sublink <?php echo ($page === 'alunos' && ($_GET['status'] ?? '') === 'concluido') ? 'active' : ''; ?>">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Alunos Concluídos</span>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Acadêmico -->
                        <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                        <div class="mobile-nav-group" data-group="academico">
                            <div class="mobile-nav-group-header">
                                <i class="fas fa-book-reader"></i>
                                <span>Acadêmico</span>
                                <i class="fas fa-chevron-down mobile-nav-arrow"></i>
                            </div>
                            <div class="mobile-nav-submenu">
                                <a href="index.php?page=turmas-teoricas" class="mobile-nav-sublink <?php echo $page === 'turmas-teoricas' ? 'active' : ''; ?>">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <span>Turmas Teóricas</span>
                                </a>
                                <!-- 
                                    AJUSTE MENU PRESENCA TEORICA - REMOVIDO ITEM TEMPORÁRIO (MOBILE)
                                    O fluxo oficial de presença TEÓRICA é via:
                                    Acadêmico → Turmas Teóricas → Detalhes da Turma → Seleção da Aula → Chamada/Frequência
                                    
                                    O antigo menu "Presenças Teóricas (Temporário)" foi removido para evitar duplicidade de caminhos.
                                -->
                                <!-- TODO: Criar página aulas-praticas.php - por enquanto usar listar-aulas.php -->
                                <a href="pages/listar-aulas.php" class="mobile-nav-sublink">
                                    <i class="fas fa-car-side"></i>
                                    <span>Aulas Práticas</span>
                                    <small style="color: #999; font-size: 0.7em;">(Temporário)</small>
                                </a>
                                <a href="index.php?page=agendamento" class="mobile-nav-sublink <?php echo $page === 'agendamento' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Agenda Geral</span>
                                    <span class="mobile-nav-badge"><?php echo $stats['total_aulas']; ?></span>
                                </a>
                                <?php if ($isAdmin): ?>
                                <a href="index.php?page=instrutores" class="mobile-nav-sublink <?php echo $page === 'instrutores' ? 'active' : ''; ?>">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <span>Instrutores</span>
                                    <span class="mobile-nav-badge"><?php echo $stats['total_instrutores']; ?></span>
                                </a>
                                <a href="index.php?page=veiculos" class="mobile-nav-sublink <?php echo $page === 'veiculos' ? 'active' : ''; ?>">
                                    <i class="fas fa-car"></i>
                                    <span>Veículos</span>
                                    <span class="mobile-nav-badge"><?php echo $stats['total_veiculos']; ?></span>
                                </a>
                                <a href="index.php?page=configuracoes-salas" class="mobile-nav-sublink <?php echo $page === 'configuracoes-salas' ? 'active' : ''; ?>">
                                    <i class="fas fa-door-open"></i>
                                    <span>Salas</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Provas & Exames -->
                        <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                        <div class="mobile-nav-group" data-group="provas-exames">
                            <div class="mobile-nav-group-header">
                                <i class="fas fa-clipboard-check"></i>
                                <span>Provas & Exames</span>
                                <i class="fas fa-chevron-down mobile-nav-arrow"></i>
                            </div>
                            <div class="mobile-nav-submenu">
                                <a href="index.php?page=exames&tipo=medico" class="mobile-nav-sublink <?php echo ($page === 'exames' && ($_GET['tipo'] ?? '') === 'medico') ? 'active' : ''; ?>">
                                    <i class="fas fa-stethoscope"></i>
                                    <span>Exame Médico</span>
                                </a>
                                <a href="index.php?page=exames&tipo=psicotecnico" class="mobile-nav-sublink <?php echo ($page === 'exames' && ($_GET['tipo'] ?? '') === 'psicotecnico') ? 'active' : ''; ?>">
                                    <i class="fas fa-brain"></i>
                                    <span>Exame Psicotécnico</span>
                                </a>
                                <a href="index.php?page=exames&tipo=teorico" class="mobile-nav-sublink <?php echo ($page === 'exames' && ($_GET['tipo'] ?? '') === 'teorico') ? 'active' : ''; ?>">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Prova Teórica</span>
                                </a>
                                <a href="index.php?page=exames&tipo=pratico" class="mobile-nav-sublink <?php echo ($page === 'exames' && ($_GET['tipo'] ?? '') === 'pratico') ? 'active' : ''; ?>">
                                    <i class="fas fa-car"></i>
                                    <span>Prova Prática</span>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Financeiro -->
                        <?php if (defined('FINANCEIRO_ENABLED') && FINANCEIRO_ENABLED && ($isAdmin || $user['tipo'] === 'secretaria')): ?>
                        <div class="mobile-nav-group" data-group="financeiro">
                            <div class="mobile-nav-group-header">
                                <i class="fas fa-dollar-sign"></i>
                                <span>Financeiro</span>
                                <i class="fas fa-chevron-down mobile-nav-arrow"></i>
                            </div>
                            <div class="mobile-nav-submenu">
                                <a href="index.php?page=financeiro-faturas" class="mobile-nav-sublink <?php echo $page === 'financeiro-faturas' ? 'active' : ''; ?>">
                                    <i class="fas fa-file-invoice"></i>
                                    <span>Faturas</span>
                                </a>
                                <!-- Corrigido: usar financeiro-despesas (página que existe) ao invés de financeiro-pagamentos -->
                                <a href="index.php?page=financeiro-despesas" class="mobile-nav-sublink <?php echo $page === 'financeiro-despesas' ? 'active' : ''; ?>">
                                    <i class="fas fa-receipt"></i>
                                    <span>Pagamentos</span>
                                </a>
                                <?php if ($isAdmin): ?>
                                <a href="index.php?page=financeiro-relatorios" class="mobile-nav-sublink <?php echo $page === 'financeiro-relatorios' ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-line"></i>
                                    <span>Relatórios Financeiros</span>
                                </a>
                                <?php endif; ?>
                                <!-- TODO: Criar página financeiro-configuracoes.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-cog"></i>
                                    <span>Configurações Financeiras</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Relatórios -->
                        <?php if ($isAdmin || $user['tipo'] === 'secretaria'): ?>
                        <div class="mobile-nav-group" data-group="relatorios">
                            <div class="mobile-nav-group-header">
                                <i class="fas fa-chart-bar"></i>
                                <span>Relatórios</span>
                                <i class="fas fa-chevron-down mobile-nav-arrow"></i>
                            </div>
                            <div class="mobile-nav-submenu">
                                <a href="pages/relatorio-frequencia.php" class="mobile-nav-sublink <?php echo ($page === 'relatorio-frequencia' || $page === 'relatorio-presencas') ? 'active' : ''; ?>">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>Frequência Teórica</span>
                                </a>
                                <!-- TODO: Criar página relatorio-conclusao-pratica.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Relatório em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Conclusão Prática</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                                <!-- TODO: Criar página relatorio-provas.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Relatório em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-clipboard-check"></i>
                                    <span>Provas (Taxa de Aprovação)</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                                <?php if ($isAdmin): ?>
                                <a href="index.php?page=financeiro-relatorios&tipo=inadimplencia" class="mobile-nav-sublink <?php echo ($page === 'financeiro-relatorios' && ($_GET['tipo'] ?? '') === 'inadimplencia') ? 'active' : ''; ?>">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Inadimplência</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Configurações -->
                        <?php if ($isAdmin): ?>
                        <div class="mobile-nav-group" data-group="configuracoes">
                            <div class="mobile-nav-group-header">
                                <i class="fas fa-cogs"></i>
                                <span>Configurações</span>
                                <i class="fas fa-chevron-down mobile-nav-arrow"></i>
                            </div>
                            <div class="mobile-nav-submenu">
                                <a href="index.php?page=configuracoes&action=dados-cfc" class="mobile-nav-sublink <?php echo ($page === 'configuracoes' && ($_GET['action'] ?? '') === 'dados-cfc') ? 'active' : ''; ?>">
                                    <i class="fas fa-building"></i>
                                    <span>Dados do CFC</span>
                                </a>
                                <a href="index.php?page=configuracoes-categorias" class="mobile-nav-sublink <?php echo $page === 'configuracoes-categorias' ? 'active' : ''; ?>">
                                    <i class="fas fa-layer-group"></i>
                                    <span>Cursos / Categorias</span>
                                </a>
                                <!-- TODO: Criar página configuracoes-horarios.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-clock"></i>
                                    <span>Tabela de Horários</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                                <!-- TODO: Criar página configuracoes-bloqueios.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-ban"></i>
                                    <span>Regras de Bloqueio</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                                <!-- TODO: Criar página configuracoes-documentos.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>Modelos de Documentos</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                                <a href="index.php?page=configuracoes-disciplinas" class="mobile-nav-sublink <?php echo $page === 'configuracoes-disciplinas' ? 'active' : ''; ?>">
                                    <i class="fas fa-book"></i>
                                    <span>Disciplinas</span>
                                </a>
                                <?php if ($isAdmin): ?>
                                <a href="index.php?page=aplicar-indices" class="mobile-nav-sublink <?php echo $page === 'aplicar-indices' ? 'active' : ''; ?>">
                                    <i class="fas fa-database"></i>
                                    <span>Otimizar Banco (Índices)</span>
                                </a>
                                <a href="index.php?page=diagnostico-queries" class="mobile-nav-sublink <?php echo $page === 'diagnostico-queries' ? 'active' : ''; ?>">
                                    <i class="fas fa-search"></i>
                                    <span>Diagnóstico de Queries</span>
                                </a>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                <a href="index.php?page=configuracoes-smtp" class="mobile-nav-sublink <?php echo $page === 'configuracoes-smtp' ? 'active' : ''; ?>">
                                    <i class="fas fa-envelope"></i>
                                    <span>E-mail (SMTP)</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Sistema / Ajuda -->
                        <?php if ($isAdmin): ?>
                        <div class="mobile-nav-group" data-group="sistema-ajuda">
                            <div class="mobile-nav-group-header">
                                <i class="fas fa-question-circle"></i>
                                <span>Sistema / Ajuda</span>
                                <i class="fas fa-chevron-down mobile-nav-arrow"></i>
                            </div>
                            <div class="mobile-nav-submenu">
                                <a href="index.php?page=logs&action=list" class="mobile-nav-sublink <?php echo ($page === 'logs' && ($_GET['action'] ?? '') === 'list') ? 'active' : ''; ?>">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Logs</span>
                                </a>
                                <!-- TODO: Criar página faq.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-question"></i>
                                    <span>FAQ</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                                <!-- TODO: Criar página suporte.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-headset"></i>
                                    <span>Suporte</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                                <!-- TODO: Criar página backup.php -->
                                <a href="#" class="mobile-nav-sublink" onclick="alert('Página em desenvolvimento'); return false;" style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-download"></i>
                                    <span>Backup</span>
                                    <small style="color: #999; font-size: 0.7em;">(Em breve)</small>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Sair -->
                        <!-- logout.php está em admin/logout.php, então usar ./logout.php -->
                        <div class="mobile-nav-item mobile-nav-logout">
                            <a href="./logout.php" class="mobile-nav-link">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Sair</span>
                            </a>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Conteúdo Principal -->
        <main class="admin-main">
            <?php
            // Inicializar variáveis padrão
            $alunos = [];
            $instrutores = [];
            $cfcs = [];
            $usuarios = [];
            $veiculos = [];
            
            // Carregar dados necessários baseado na página
            switch ($page) {
                case 'alunos':
                    // =====================================================
                    // FILTROS DE STATUS PARA GESTÃO DE ALUNOS
                    // =====================================================
                    // Mapeamento de status da URL para critérios de filtro:
                    // - em_formacao → alunos.status = 'ativo'
                    // - em_exame → alunos que têm exames agendados (exames.status = 'agendado')
                    // - concluido → alunos.status = 'concluido'
                    // =====================================================
                    
                    // Ler parâmetro de status da URL
                    $statusFiltro = isset($_GET['status']) ? trim($_GET['status']) : null;
                    
                    // Mapeamento de status válidos
                    $statusPermitidos = [
                        'em_formacao' => 'ativo',     // Alunos ativos em formação
                        'em_exame'    => 'exame',     // Alunos com exames agendados (tratamento especial)
                        'concluido'   => 'concluido'  // Alunos concluídos
                    ];
                    
                    // Validar status
                    $statusValido = null;
                    if ($statusFiltro && isset($statusPermitidos[$statusFiltro])) {
                        $statusValido = $statusFiltro;
                    }
                    
                    try {
                        // Construir query base com JOIN para matrícula ativa (priorizar categoria/tipo da matrícula)
                        // REGRA DE PADRONIZAÇÃO: Sempre priorizar dados da matrícula ativa quando existir
                        // 
                        // IMPORTANTE: O campo a.status é o status da tabela alunos (ENUM: 'ativo', 'inativo', 'concluido')
                        // Este é o campo usado para controlar se o aluno pode agendar aulas e é exibido na listagem
                        // NÃO confundir com matriculas.status que é o status da matrícula (ativa, concluida, etc.)
                        $sql = "
                            SELECT DISTINCT 
                                a.id, a.nome, a.cpf, a.rg, a.data_nascimento, a.endereco, 
                                a.telefone, a.email, a.cfc_id, a.categoria_cnh, a.status, a.criado_em, a.operacoes,
                                m_ativa.categoria_cnh AS categoria_cnh_matricula,
                                m_ativa.tipo_servico AS tipo_servico_matricula
                            FROM alunos a
                            LEFT JOIN (
                                SELECT 
                                    m1.aluno_id,
                                    m1.categoria_cnh,
                                    m1.tipo_servico
                                FROM matriculas m1
                                WHERE m1.status = 'ativa'
                                GROUP BY m1.aluno_id
                            ) AS m_ativa ON m_ativa.aluno_id = a.id
                        ";
                        
                        $params = [];
                        $whereConditions = [];
                        
                        // Aplicar filtro de status se válido
                        if ($statusValido) {
                            if ($statusValido === 'em_exame') {
                                // Alunos que têm exames agendados (usar EXISTS para evitar duplicatas)
                                $whereConditions[] = "EXISTS (
                                    SELECT 1 FROM exames e 
                                    WHERE e.aluno_id = a.id 
                                    AND e.status = 'agendado'
                                )";
                            } else {
                                // Para em_formacao ou concluido, usar o status direto
                                $whereConditions[] = "a.status = ?";
                                $params[] = $statusPermitidos[$statusValido];
                            }
                        }
                        
                        // Adicionar condições WHERE se houver
                        if (!empty($whereConditions)) {
                            $sql .= " WHERE " . implode(' AND ', $whereConditions);
                        }
                        
                        $sql .= " ORDER BY a.nome ASC";
                        
                        // SOLUÇÃO DEFINITIVA v3.0 - Forçar eliminação de duplicatas
                        $alunosRaw = $db->fetchAll($sql, $params);
                        
                        // FORÇAR eliminação de duplicatas por ID
                        $alunos = [];
                        $idsProcessados = [];
                        foreach ($alunosRaw as $aluno) {
                            if (!in_array($aluno['id'], $idsProcessados)) {
                                $alunos[] = $aluno;
                                $idsProcessados[] = $aluno['id'];
                            }
                        }
                        
                        // Adicionar campos necessários e decodificar operações
                        for ($i = 0; $i < count($alunos); $i++) {
                            $alunos[$i]['cfc_nome'] = 'CFC BOM CONSELHO';
                            $alunos[$i]['ultima_aula'] = null;
                            
                            // Decodificar operações
                            if (!empty($alunos[$i]['operacoes'])) {
                                $alunos[$i]['operacoes'] = json_decode($alunos[$i]['operacoes'], true);
                            } else {
                                $alunos[$i]['operacoes'] = [];
                            }
                        }
                    } catch (Exception $e) {
                        // Log do erro para debug
                        error_log("ERRO na query principal de alunos: " . $e->getMessage());
                        
                        // Query mais simples como fallback
                        // IMPORTANTE: Esta query também usa alunos.status (campo status da tabela alunos)
                        try {
                            $alunos = $db->fetchAll("SELECT DISTINCT * FROM alunos ORDER BY nome ASC");
                            // Decodificar operações para cada aluno no fallback também
                            for ($i = 0; $i < count($alunos); $i++) {
                                if (!empty($alunos[$i]['operacoes'])) {
                                    $alunos[$i]['operacoes'] = json_decode($alunos[$i]['operacoes'], true);
                                } else {
                                    $alunos[$i]['operacoes'] = [];
                                }
                            }
                        } catch (Exception $e2) {
                            error_log("ERRO no fallback de alunos: " . $e2->getMessage());
                            $alunos = [];
                        }
                    }
                    try {
                        $cfcs = $db->fetchAll("SELECT id, nome, ativo FROM cfcs WHERE ativo = 1 ORDER BY nome");
                    } catch (Exception $e) {
                        $cfcs = [];
                    }
                    
                    // Calcular contadores globais (independente do filtro atual)
                    try {
                        // Total de todos os alunos
                        $resultTotal = $db->fetch("SELECT COUNT(*) as total FROM alunos");
                        $stats['total_alunos'] = $resultTotal['total'] ?? count($alunos);
                        
                        // Total de alunos ativos (em formação)
                        $resultAtivos = $db->fetch("SELECT COUNT(*) as total FROM alunos WHERE status = 'ativo'");
                        $stats['total_ativos'] = $resultAtivos['total'] ?? 0;
                        
                        // Total de alunos concluídos
                        $resultConcluidos = $db->fetch("SELECT COUNT(*) as total FROM alunos WHERE status = 'concluido'");
                        $stats['total_concluidos'] = $resultConcluidos['total'] ?? 0;
                        
                        // Total de alunos em exame (com exames agendados)
                        $resultEmExame = $db->fetch("
                            SELECT COUNT(DISTINCT aluno_id) as total
                            FROM exames
                            WHERE status = 'agendado'
                        ");
                        $stats['total_em_exame'] = $resultEmExame['total'] ?? 0;
                        
                    } catch (Exception $e) {
                        $stats['total_alunos'] = count($alunos);
                        $stats['total_ativos'] = 0;
                        $stats['total_concluidos'] = 0;
                        $stats['total_em_exame'] = 0;
                    }
                    
                    break;
                    
                case 'instrutores':
                    try {
                        // Query mais simples primeiro para testar
                        $instrutores = $db->fetchAll("
                            SELECT i.id, i.usuario_id, i.cfc_id, i.credencial, i.categoria_habilitacao, i.ativo, i.criado_em,
                                   u.nome, u.email, c.nome as cfc_nome,
                                   0 as total_aulas, 0 as aulas_hoje, 1 as disponivel
                            FROM instrutores i 
                            LEFT JOIN usuarios u ON i.usuario_id = u.id 
                            LEFT JOIN cfcs c ON i.cfc_id = c.id 
                            ORDER BY u.nome ASC
                        ");
                    } catch (Exception $e) {
                        // Se ainda houver erro, usar query básica
                        try {
                            $instrutores = $db->fetchAll("SELECT * FROM instrutores ORDER BY id ASC");
                        } catch (Exception $e2) {
                            $instrutores = [];
                        }
                    }
                    try {
                        $cfcs = $db->fetchAll("SELECT id, nome, ativo FROM cfcs WHERE ativo = 1 ORDER BY nome");
                    } catch (Exception $e) {
                        $cfcs = [];
                    }
                    try {
                        $usuarios = $db->fetchAll("SELECT * FROM usuarios WHERE tipo IN ('instrutor', 'admin') ORDER BY nome");
                    } catch (Exception $e) {
                        $usuarios = [];
                    }
                    break;
                    
                case 'cfcs':
                    try {
                        $cfcs = $db->fetchAll("
                            SELECT c.id, c.nome, c.cnpj, c.endereco, c.bairro, c.cidade, c.uf, c.cep, c.telefone, c.email, c.responsavel_id, c.ativo, c.criado_em,
                                   u.nome as responsavel_nome,
                                   0 as total_alunos
                            FROM cfcs c 
                            LEFT JOIN usuarios u ON c.responsavel_id = u.id 
                            ORDER BY c.nome
                        ");
                    } catch (Exception $e) {
                        // Query mais simples como fallback
                        try {
                            $cfcs = $db->fetchAll("SELECT * FROM cfcs ORDER BY nome");
                        } catch (Exception $e2) {
                            $cfcs = [];
                        }
                    }
                    break;
                    
                case 'usuarios':
                    try {
                        $usuarios = $db->fetchAll("SELECT id, nome, email, tipo, cpf, telefone, ativo, criado_em FROM usuarios ORDER BY nome");
                    } catch (Exception $e) {
                        // Query mais simples como fallback
                        try {
                            $usuarios = $db->fetchAll("SELECT * FROM usuarios ORDER BY nome");
                        } catch (Exception $e2) {
                            $usuarios = [];
                        }
                    }
                    break;
                    
                case 'veiculos':
                    try {
                        $veiculos = $db->fetchAll("
                            SELECT v.id, v.cfc_id, v.placa, v.modelo, v.marca, v.ano, v.categoria_cnh, v.ativo, v.criado_em,
                                   c.nome as cfc_nome 
                            FROM veiculos v 
                            LEFT JOIN cfcs c ON v.cfc_id = c.id 
                            ORDER BY v.placa ASC
                        ");
                    } catch (Exception $e) {
                        // Query mais simples como fallback
                        try {
                            $veiculos = $db->fetchAll("SELECT * FROM veiculos ORDER BY placa ASC");
                        } catch (Exception $e2) {
                            $veiculos = [];
                        }
                    }
                    try {
                        $cfcs = $db->fetchAll("SELECT id, nome, ativo FROM cfcs WHERE ativo = 1 ORDER BY nome");
                    } catch (Exception $e) {
                        $cfcs = [];
                    }
                    break;
                    
                case 'agendamento':
                case 'agendar-aula':
                    // Verificar se é edição de aula
                    if ($action === 'edit') {
                        $content_file = "pages/editar-aula.php";
                        break;
                    }
                    
                    // Verificar se é listagem de aulas
                    if ($action === 'list') {
                        // Buscar todas as aulas para listagem
                        try {
                            $aulas_lista = $db->fetchAll("
                                SELECT a.*, 
                                       al.nome as aluno_nome,
                                       i.nome as instrutor_nome,
                                       v.placa as veiculo_placa,
                                       v.modelo as veiculo_modelo,
                                       c.nome as cfc_nome
                                FROM aulas a
                                LEFT JOIN alunos al ON a.aluno_id = al.id
                                LEFT JOIN instrutores i ON a.instrutor_id = i.id
                                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                                LEFT JOIN cfcs c ON a.cfc_id = c.id
                                WHERE a.data_aula >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                ORDER BY a.data_aula DESC, a.hora_inicio DESC
                                LIMIT 100
                            ");
                        } catch (Exception $e) {
                            $aulas_lista = [];
                        }
                        break;
                    }
                    
                    // Buscar dados necessários para agendamento
                    $aluno_id = $_GET['aluno_id'] ?? null;
                    $aluno = null;
                    $cfc = null;
                    $instrutores = [];
                    $veiculos = [];
                    $aulas_existentes = [];
                    
                    if ($aluno_id) {
                        try {
                            $aluno = $db->findWhere('alunos', 'id = ?', [$aluno_id], '*', null, 1);
                            if ($aluno && is_array($aluno)) {
                                $aluno = $aluno[0];
                                try {
                                    $cfc = $db->findWhere('cfcs', 'id = ?', [$aluno['cfc_id']], '*', null, 1);
                                    $cfc = $cfc && is_array($cfc) ? $cfc[0] : null;
                                } catch (Exception $e) {
                                    $cfc = null;
                                }
                            }
                            try {
                                $instrutores = $db->fetchAll("SELECT id, nome FROM instrutores WHERE ativo = 1 ORDER BY nome");
                            } catch (Exception $e) {
                                $instrutores = [];
                            }
                            try {
                                $veiculos = $db->fetchAll("SELECT id, placa, modelo FROM veiculos WHERE ativo = 1 ORDER BY placa");
                            } catch (Exception $e) {
                                $veiculos = [];
                            }
                            try {
                                $aulas_existentes = $db->fetchAll("
                                    SELECT a.*, i.nome as instrutor_nome, v.placa as veiculo_placa
                                    FROM aulas a
                                    LEFT JOIN instrutores i ON a.instrutor_id = i.id
                                    LEFT JOIN veiculos v ON a.veiculo_id = v.id
                                    WHERE a.aluno_id = ? AND a.data_aula >= ?
                                    ORDER BY a.data_aula ASC, a.hora_inicio ASC
                                ", [$aluno_id, date('Y-m-d')]);
                            } catch (Exception $e) {
                                $aulas_existentes = [];
                            }
                        } catch (Exception $e) {
                            $aluno = null;
                            $cfc = null;
                            $instrutores = [];
                            $veiculos = [];
                            $aulas_existentes = [];
                        }
                    }
                    break;
                    
                case 'agendar-manutencao':
                    // Buscar dados necessários para agendamento de manutenção
                    $veiculo_id = $_GET['veiculo_id'] ?? null;
                    $veiculo = null;
                    $cfcs = [];
                    
                    if ($veiculo_id) {
                        try {
                            $veiculo = $db->fetch("
                                SELECT v.*, c.nome as cfc_nome 
                                FROM veiculos v 
                                LEFT JOIN cfcs c ON v.cfc_id = c.id 
                                WHERE v.id = ?
                            ", [$veiculo_id]);
                            
                            if (!$veiculo) {
                                throw new Exception('Veículo não encontrado');
                            }
                        } catch (Exception $e) {
                            $veiculo = null;
                        }
                    }
                    
                    try {
                        $cfcs = $db->fetchAll("SELECT id, nome, ativo FROM cfcs WHERE ativo = 1 ORDER BY nome");
                    } catch (Exception $e) {
                        $cfcs = [];
                    }
                    break;
                    
                case 'historico-aluno':
                    // Buscar dados do aluno para histórico
                    $aluno_id = $_GET['id'] ?? null;
                    $aluno = null;
                    $cfc = null;
                    
                    if ($aluno_id) {
                        try {
                            $aluno = $db->findWhere('alunos', 'id = ?', [$aluno_id], '*', null, 1);
                            if ($aluno && is_array($aluno)) {
                                $aluno = $aluno[0];
                                try {
                                    $cfc = $db->findWhere('cfcs', 'id = ?', [$aluno['cfc_id']], '*', null, 1);
                                    $cfc = $cfc && is_array($cfc) ? $cfc[0] : null;
                                } catch (Exception $e) {
                                    $cfc = null;
                                }
                            } else {
                                $aluno = null;
                                $cfc = null;
                            }
                        } catch (Exception $e) {
                            $aluno = null;
                            $cfc = null;
                        }
                    }
                    break;
                    
                case 'historico-instrutor':
                    // Buscar dados do instrutor para histórico
                    $instrutor_id = $_GET['id'] ?? null;
                    $instrutor = null;
                    $cfc = null;
                    
                    if ($instrutor_id) {
                        try {
                            $instrutor = $db->findWhere('instrutores', 'id = ?', [$instrutor_id], '*', null, 1);
                            if ($instrutor && is_array($instrutor)) {
                                $instrutor = $instrutor[0];
                                try {
                                    $cfc = $db->findWhere('cfcs', 'id = ?', [$instrutor['cfc_id']], '*', null, 1);
                                    $cfc = $cfc && is_array($cfc) ? $cfc[0] : null;
                                } catch (Exception $e) {
                                    $cfc = null;
                                }
                            } else {
                                $instrutor = null;
                                $cfc = null;
                            }
                        } catch (Exception $e) {
                            $instrutor = null;
                            $cfc = null;
                        }
                    }
                    break;
                    
                // === CASES PARA MÓDULO FINANCEIRO ===
                case 'financeiro-faturas':
                    // Carregar dados para página de faturas
                    try {
                        // Buscar alunos para filtros
                        $alunos = $db->fetchAll("SELECT id, nome, cpf FROM alunos ORDER BY nome");
                    } catch (Exception $e) {
                        $alunos = [];
                    }
                    break;
                    
                case 'financeiro-despesas':
                    // Carregar dados para página de despesas
                    try {
                        // Dados específicos de despesas podem ser carregados aqui
                    } catch (Exception $e) {
                        // Tratar erro se necessário
                    }
                    break;
                    
                case 'financeiro-relatorios':
                    // Carregar dados para página de relatórios
                    try {
                        // Dados específicos de relatórios podem ser carregados aqui
                    } catch (Exception $e) {
                        // Tratar erro se necessário
                    }
                    break;

                // === CASES PARA TURMAS TEÓRICAS ===
                case 'turmas-teoricas':
                    // Carregar dados básicos para todas as páginas de turmas teóricas
                    try {
                        $turmas = $db->fetchAll("
                            SELECT t.*, i.nome as instrutor_nome, c.nome as cfc_nome,
                                   COUNT(ta.id) as total_alunos_matriculados
                            FROM turmas_teoricas t
                            LEFT JOIN instrutores i ON t.criado_por = i.usuario_id
                            LEFT JOIN cfcs c ON t.cfc_id = c.id
                            LEFT JOIN turma_alunos ta ON t.id = ta.turma_id
                            GROUP BY t.id
                            ORDER BY t.data_inicio DESC
                        ");
                    } catch (Exception $e) {
                        $turmas = [];
                    }
                    
                    try {
                        $instrutores = $db->fetchAll("
                            SELECT i.id, i.usuario_id, u.nome, u.email, i.categoria_habilitacao
                            FROM instrutores i
                            JOIN usuarios u ON i.usuario_id = u.id
                            WHERE i.ativo = 1
                            ORDER BY u.nome ASC
                        ");
                    } catch (Exception $e) {
                        $instrutores = [];
                    }
                    
                    try {
                        $alunos = $db->fetchAll("
                            SELECT a.id, a.nome, a.cpf, a.email, a.telefone, a.categoria_cnh
                            FROM alunos a
                            WHERE a.status = 'ativo'
                            ORDER BY a.nome ASC
                        ");
                    } catch (Exception $e) {
                        $alunos = [];
                    }
                    
                    // Dados específicos por página
                    switch ($page) {
                        // Casos específicos para turmas teóricas
                    }
                    break;
                    
                case 'turma-chamada':
                    // Verificar se turma_id foi fornecido ANTES de qualquer output
                    $turmaId = $_GET['turma_id'] ?? null;
                    if (!$turmaId) {
                        // Redirecionar ANTES de incluir qualquer HTML
                        header('Location: index.php?page=turmas-teoricas');
                        exit();
                    }
                    // Se chegou aqui, turma_id existe - a página vai validar o restante
                    break;
                    
                default:
                    // Para o dashboard, não precisamos carregar dados específicos
                    break;
            }
            
            // Carregar conteúdo dinâmico baseado na página e ação
            if ($page === 'agendar-aula' && $action === 'list') {
                // Página específica para listagem de aulas
                $content_file = "pages/listar-aulas.php";
            } elseif ($page === 'agendar-aula' && $action === 'edit') {
                // Debug: Verificar roteamento de edição
                error_log("DEBUG: Roteamento para edição - ID: " . ($_GET['edit'] ?? 'não fornecido'));
                error_log("DEBUG: Parâmetros GET: " . print_r($_GET, true));
                error_log("DEBUG: Arquivo a ser carregado: pages/editar-aula.php");
                
                // Debug: Verificar sessão antes de carregar a página
                error_log("DEBUG: Session ID antes de carregar editar-aula: " . session_id());
                error_log("DEBUG: User ID antes de carregar editar-aula: " . ($_SESSION['user_id'] ?? 'não definido'));
                error_log("DEBUG: User Type antes de carregar editar-aula: " . ($_SESSION['user_type'] ?? 'não definido'));
                
                // Verificar se o arquivo existe
                if (!file_exists("pages/editar-aula.php")) {
                    error_log("ERRO: Arquivo pages/editar-aula.php não encontrado!");
                    echo '<div class="alert alert-danger">Erro: Arquivo de edição não encontrado.</div>';
                    return;
                }
                
                error_log("DEBUG: Arquivo pages/editar-aula.php encontrado, carregando...");
                // Página específica para edição de aulas
                $content_file = "pages/editar-aula.php";
                
                // Debug: Verificar sessão depois de definir o arquivo
                error_log("DEBUG: Session ID depois de definir arquivo: " . session_id());
                error_log("DEBUG: User ID depois de definir arquivo: " . ($_SESSION['user_id'] ?? 'não definido'));
                error_log("DEBUG: User Type depois de definir arquivo: " . ($_SESSION['user_type'] ?? 'não definido'));
            } elseif ($page === 'turmas') {
                // Páginas de turmas teóricas
                $content_file = "pages/{$page}.php";
            } else {
                $content_file = "pages/{$page}.php";
            }
            
            if (file_exists($content_file)) {
                // Incluir arquivo da página
                include $content_file;
            } else {
                // Página padrão - Dashboard
                include 'pages/dashboard.php';
            }
            ?>
        </main>
        
    </div>
    
    <!-- JavaScript -->
    <script>
        // Função global para detectar o path base automaticamente
        function getBasePath() {
            return window.location.pathname.includes('/cfc-bom-conselho/') ? '/cfc-bom-conselho' : '';
        }
        
        // Sistema de navegação responsiva
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar em dispositivos móveis
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const sidebar = document.querySelector('.admin-sidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                });
            }
            
            // Fechar sidebar ao clicar fora em dispositivos móveis
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    if (!sidebar.contains(e.target) && !e.target.closest('.sidebar-toggle')) {
                        sidebar.classList.remove('open');
                    }
                }
            });
            
            // Animações de entrada
            const animateElements = document.querySelectorAll('.stat-card, .card, .chart-section');
            animateElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
                element.classList.add('animate-fade-in');
            });
            
            // Tooltips
            const tooltipElements = document.querySelectorAll('[data-tooltip]');
            tooltipElements.forEach(element => {
                element.classList.add('tooltip');
            });
            
            // Estados de carregamento
            const loadingElements = document.querySelectorAll('.loading');
            loadingElements.forEach(element => {
                element.classList.add('loading-state');
            });
        });
        
        // Função para mostrar notificações
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.innerHTML = `
                <div class="alert-content">
                    <div class="d-flex items-center gap-3">
                        <div class="notification-icon ${type}">
                            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'danger' ? 'times-circle' : 'info-circle'}"></i>
                        </div>
                        <div>${message}</div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Remover após 5 segundos
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
        
        // Função para confirmar ações
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
        
        // Função para formatar números
        function formatNumber(number) {
            return new Intl.NumberFormat('pt-BR').format(number);
        }
        
        // Função para formatar datas
        function formatDate(date) {
            return new Intl.DateTimeFormat('pt-BR').format(new Date(date));
        }
        
        // Função para formatar moeda
        function formatCurrency(amount) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(amount);
        }
        
        // Scripts de expansão interna removidos - usando menu-flyout.js
    </script>
    
    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- IMask para máscaras de input -->
    <script src="https://unpkg.com/imask@6.4.3/dist/imask.min.js"></script>
    
    <!-- Font Awesome já carregado no head -->
    
    <!-- JavaScript Principal do Admin -->
    <script>window.ADMIN_IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;</script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/admin.js"></script>
    <script src="assets/js/menu-flyout.js"></script>
    <script src="assets/js/mobile-menu-clean.js"></script>
    <script src="assets/js/topbar-unified.js"></script>
    
    <script src="assets/js/components.js"></script>
    
    <!-- Debug das funções dos modais -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔍 Verificando funções dos modais...');
            console.log('abrirModalTiposCursoInterno:', typeof window.abrirModalTiposCursoInterno);
            console.log('abrirModalDisciplinasInterno:', typeof window.abrirModalDisciplinasInterno);
            console.log('fecharModalTiposCurso:', typeof window.fecharModalTiposCurso);
            console.log('fecharModalDisciplinas:', typeof window.fecharModalDisciplinas);
            
            // Verificar se os modais existem
            const modalCursos = document.getElementById('modalGerenciarTiposCurso');
            const modalDisciplinas = document.getElementById('modalGerenciarDisciplinas');
            console.log('Modal Cursos encontrado:', !!modalCursos);
            console.log('Modal Disciplinas encontrado:', !!modalDisciplinas);
            
            // Teste simples - adicionar listeners de debug
            setTimeout(() => {
                console.log('🧪 Testando abertura dos modais...');
                
                // Teste manual das funções
                if (typeof window.abrirModalTiposCursoInterno === 'function') {
                    console.log('✅ Função abrirModalTiposCursoInterno está disponível');
                } else {
                    console.error('❌ Função abrirModalTiposCursoInterno NÃO está disponível');
                }
                
                if (typeof window.abrirModalDisciplinasInterno === 'function') {
                    console.log('✅ Função abrirModalDisciplinasInterno está disponível');
                } else {
                    console.error('❌ Função abrirModalDisciplinasInterno NÃO está disponível');
                }
            }, 1000);
        });
    </script>
    
    <!-- PWA Registration -->
    <?php 
    // Desativar scripts pesados na página de detalhes de turmas teóricas para melhorar performance
    $isTurmasTeoricasDetalhes = isset($_GET['page']) && $_GET['page'] === 'turmas-teoricas' && isset($_GET['acao']) && $_GET['acao'] === 'detalhes';
    if (!$isTurmasTeoricasDetalhes): 
    ?>
    <script src="../pwa/pwa-register.js"></script>
    
    <!-- Performance Metrics -->
    <script src="../pwa/performance-metrics.js"></script>
    
    <!-- Automated PWA Tests -->
    <script src="../pwa/automated-test.js"></script>
    <?php else: ?>
    <!-- Scripts PWA desativados nesta página para melhorar performance do modal -->
    <?php endif; ?>
    
    <?php 
    // CORREÇÕES EMERGENCIAS PARA MODAL - Apenas na página de detalhes de turmas teóricas
    $isTurmasTeoricasDetalhes = isset($_GET['page']) && $_GET['page'] === 'turmas-teoricas' && isset($_GET['acao']) && $_GET['acao'] === 'detalhes';
    if ($isTurmasTeoricasDetalhes): 
    ?>
    <!-- Sistema de Correções Emergenciais para Modal Travado -->
    <script src="assets/js/modal-fix-emergency.js"></script>
    <?php endif; ?>
    
    <!-- JavaScript das Funcionalidades Específicas -->
    <?php if ($page === 'cfcs'): ?>
        <script src="assets/js/cfcs.js"></script>
    <?php endif; ?>
    
    <?php if ($page === 'instrutores'): ?>
        <script src="assets/js/instrutores.js"></script>
        <!-- instrutores-page.js é carregado diretamente na página -->
    <?php endif; ?>
    
    <?php if ($page === 'alunos'): ?>
        <!-- <script src="assets/js/alunos.js"></script> -->
        <!-- alunos.js removido para evitar conflito com código inline -->
    <?php endif; ?>
    
    <!-- Mobile Debug Script -->
    <script src="assets/js/mobile-debug.js"></script>
    
    <!-- Sistema de Modal Singleton -->
    <script>
        // Sistema de Modal Singleton - Namespace específico
        window.SingletonModalSystem = {
            // Abrir modal singleton
            open: function(render) {
                // Verificar se já existe modal aberto
                if (document.body.dataset.singletonModalOpen === '1') {
                    console.log('⚠️ Modal já está aberto, apenas atualizando conteúdo');
                    this.update(render);
                    return;
                }
                
                const root = document.getElementById('modal-root');
                if (!root) {
                    console.error('❌ Modal root não encontrado');
                    return;
                }
                
                // Limpar qualquer modal anterior
                root.innerHTML = '';
                
                // Criar backdrop
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop';
                backdrop.onclick = () => this.close();
                
                // Criar wrapper do modal
                const wrapper = document.createElement('div');
                wrapper.className = 'modal-wrapper';
                
                // Renderizar conteúdo do modal
                const modalContent = render();
                if (modalContent) {
                    wrapper.appendChild(modalContent);
                }
                
                // Adicionar ao root
                root.appendChild(backdrop);
                root.appendChild(wrapper);
                
                // Marcar como aberto
                document.body.dataset.singletonModalOpen = '1';
                document.body.style.overflow = 'hidden';
                
                // Adicionar listener para ESC
                document.addEventListener('keydown', this.handleEscape);
                
                // Focus trap
                this.setupFocusTrap(wrapper);
                
                console.log('✅ Modal singleton aberto');
            },
            
            // Atualizar conteúdo do modal existente
            update: function(render) {
                const wrapper = document.querySelector('#modal-root .modal-wrapper');
                if (wrapper) {
                    wrapper.innerHTML = '';
                    const modalContent = render();
                    if (modalContent) {
                        wrapper.appendChild(modalContent);
                        this.setupFocusTrap(wrapper);
                    }
                }
            },
            
            // Fechar modal
            close: function() {
                const root = document.getElementById('modal-root');
                if (root) {
                    root.innerHTML = '';
                }
                
                // Limpar estado
                delete document.body.dataset.singletonModalOpen;
                document.body.style.overflow = '';
                
                // Remover listener ESC
                document.removeEventListener('keydown', this.handleEscape);
                
                console.log('✅ Modal singleton fechado');
            },
            
            // Handler para tecla ESC
            handleEscape: function(event) {
                if (event.key === 'Escape') {
                    window.SingletonModalSystem.close();
                }
            },
            
            // Setup focus trap
            setupFocusTrap: function(wrapper) {
                const focusableElements = wrapper.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                
                if (focusableElements.length === 0) return;
                
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                wrapper.addEventListener('keydown', function(event) {
                    if (event.key === 'Tab') {
                        if (event.shiftKey) {
                            if (document.activeElement === firstElement) {
                                event.preventDefault();
                                lastElement.focus();
                            }
                        } else {
                            if (document.activeElement === lastElement) {
                                event.preventDefault();
                                firstElement.focus();
                            }
                        }
                    }
                });
                
                // Focar no primeiro elemento
                setTimeout(() => firstElement.focus(), 100);
            }
        };
        
        // Função de conveniência global para sistema singleton
        window.openSingletonModal = function(render) {
            window.SingletonModalSystem.open(render);
        };
        
        window.closeSingletonModal = function() {
            window.SingletonModalSystem.close();
        };
        
        // Manter compatibilidade com código existente
        window.openModal = function(render) {
            window.SingletonModalSystem.open(render);
        };
        
        window.closeModal = function() {
            window.SingletonModalSystem.close();
        };
    </script>
    
    <!-- Modal Root para modais singleton -->
    <div id="modal-root"></div>
    
    <!-- Modal Gerenciar Disciplinas - Global -->
    <div class="popup-modal" id="modalGerenciarDisciplinas" style="display: none;">
        <div class="popup-modal-wrapper">
            
            <!-- HEADER -->
            <div class="popup-modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="header-text">
                        <h5>Gerenciar Disciplinas</h5>
                        <small>Configure e organize as disciplinas do curso</small>
                    </div>
                </div>
                <button type="button" class="popup-modal-close" onclick="fecharModalDisciplinas()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- CONTEÚDO -->
            <div class="popup-modal-content">
                
                <!-- Barra de Busca -->
                <div class="popup-search-container">
                    <div class="popup-search-wrapper">
                        <input type="text" class="popup-search-input" id="buscarDisciplinas" placeholder="Buscar disciplinas..." onkeyup="filtrarDisciplinas()">
                        <i class="fas fa-search popup-search-icon"></i>
                    </div>
                </div>
                
                <!-- Seção Otimizada - Título, Estatísticas e Botão na mesma linha -->
                <div class="popup-section-header">
                    <div class="popup-section-title">
                        <h6>Suas Disciplinas</h6>
                        <small>Gerencie e organize as disciplinas do curso</small>
                    </div>
                    <div class="popup-stats-item" style="margin: 0;">
                        <div class="popup-stats-icon">
                            <div class="icon-circle">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                        <div class="popup-stats-text">
                            <h6 style="margin: 0;">Total: <span class="stats-number" id="totalDisciplinas">0</span></h6>
                        </div>
                    </div>
                    <button type="button" class="popup-primary-button" onclick="abrirFormularioNovaDisciplina()">
                        <i class="fas fa-plus"></i>
                        Nova Disciplina
                    </button>
                </div>
                
                <!-- Conteúdo Principal - Lista de Disciplinas -->
                <div id="conteudo-principal-disciplinas">
                    <!-- Grid de Disciplinas -->
                    <div class="popup-items-grid" id="listaDisciplinas">
                        <!-- Lista de disciplinas será carregada aqui -->
                        <div class="popup-loading-state show">
                            <div class="popup-loading-spinner"></div>
                            <div class="popup-loading-text">
                                <h6>Carregando disciplinas...</h6>
                                <p>Aguarde enquanto buscamos as disciplinas cadastradas</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formulário Nova Disciplina (oculto inicialmente) -->
                <div id="formulario-nova-disciplina" style="display: none;">
                    <div class="popup-section-header">
                        <div class="popup-section-title">
                            <h6>Nova Disciplina</h6>
                            <small>Preencha os dados da nova disciplina</small>
                        </div>
                        <button type="button" class="popup-secondary-button" onclick="voltarParaListaDisciplinas()">
                            <i class="fas fa-arrow-left"></i>
                            Voltar
                        </button>
                    </div>
                    
                    <form id="formNovaDisciplinaIntegrado" class="mt-3" onsubmit="salvarNovaDisciplinaIntegrada(event)">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="codigo_disciplina_integrado" class="form-label">Código *</label>
                                    <input type="text" class="form-control" id="codigo_disciplina_integrado" name="codigo" required placeholder="Ex: direcao_defensiva">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nome_disciplina_integrado" class="form-label">Nome *</label>
                                    <input type="text" class="form-control" id="nome_disciplina_integrado" name="nome" required placeholder="Ex: Direção Defensiva">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao_disciplina_integrado" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao_disciplina_integrado" name="descricao" rows="3" placeholder="Descrição detalhada da disciplina"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="carga_horaria_disciplina_integrado" class="form-label">Carga Horária Padrão</label>
                                    <input type="number" class="form-control" id="carga_horaria_disciplina_integrado" name="carga_horaria_padrao" min="1" value="20">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cor_disciplina_integrado" class="form-label">Cor</label>
                                    <input type="color" class="form-control" id="cor_disciplina_integrado" name="cor_hex" value="#023A8D">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="popup-secondary-button" onclick="voltarParaListaDisciplinas()">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="popup-save-button" id="btnSalvarDisciplina">
                                <i class="fas fa-save"></i>
                                Salvar Disciplina
                            </button>
                        </div>
                    </form>
                </div>
                
            </div>
            
            <!-- FOOTER -->
            <div class="popup-modal-footer">
                <div class="popup-footer-info">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        As alterações são salvas automaticamente
                    </small>
                </div>
                <div class="popup-footer-actions">
                    <button type="button" class="popup-secondary-button" onclick="fecharModalDisciplinas()">
                        <i class="fas fa-times"></i>
                        Fechar
                    </button>
                    <button type="button" class="popup-save-button" onclick="salvarAlteracoesDisciplinas()">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Modal Gerenciar Tipos de Curso - Global -->
    <div class="popup-modal" id="modalGerenciarTiposCurso" style="display: none;">
        <div class="popup-modal-wrapper">
            
            <!-- HEADER -->
            <div class="popup-modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="header-text">
                        <h5>Gerenciar Cursos</h5>
                        <small>Configure e organize os cursos disponíveis</small>
                    </div>
                </div>
                <button type="button" class="popup-modal-close" onclick="fecharModalTiposCurso()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- CONTEÚDO -->
            <div class="popup-modal-content">
                
                <!-- Seção Otimizada - Título, Estatísticas e Botão na mesma linha -->
                <div class="popup-section-header">
                    <div class="popup-section-title">
                        <h6>Cursos Cadastrados</h6>
                        <small>Gerencie e organize os cursos do CFC</small>
                    </div>
                    <div class="popup-stats-item" style="margin: 0;">
                        <div class="popup-stats-icon">
                            <div class="icon-circle">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                        </div>
                        <div class="popup-stats-text">
                            <h6 style="margin: 0;">Total: <span class="stats-number" id="total-tipos-curso">0</span></h6>
                        </div>
                    </div>
                    <button type="button" class="popup-primary-button" onclick="abrirFormularioNovoTipoCurso()">
                        <i class="fas fa-plus"></i>
                        Novo Curso
                    </button>
                </div>
                
                <!-- Conteúdo Principal - Lista de Tipos de Curso -->
                <div id="conteudo-principal-tipos">
                    <!-- Grid de Tipos de Curso -->
                    <div class="popup-items-grid" id="lista-tipos-curso-modal">
                        <!-- Lista de tipos de curso será carregada via AJAX -->
                        <div class="popup-loading-state show">
                            <div class="popup-loading-spinner"></div>
                            <div class="popup-loading-text">
                                <h6>Carregando cursos...</h6>
                                <p>Aguarde enquanto buscamos os cursos cadastrados</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formulário Novo Tipo de Curso (oculto inicialmente) -->
                <div id="formulario-novo-tipo-curso" style="display: none;">
                    <div class="popup-section-header">
                    <div class="popup-section-title">
                        <h6>Novo Curso</h6>
                        <small>Preencha os dados do novo curso</small>
                    </div>
                        <button type="button" class="popup-secondary-button" onclick="voltarParaListaTipos()">
                            <i class="fas fa-arrow-left"></i>
                            Voltar
                        </button>
                    </div>
                    
                    <form id="formNovoTipoCursoIntegrado" class="mt-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="codigo_tipo_integrado" class="form-label">Código do Curso *</label>
                                    <input type="text" class="form-control" id="codigo_tipo_integrado" name="codigo" required placeholder="Ex: formacao_45h, reciclagem_infrator">
                                    <small class="text-muted">Use apenas letras, números e underscore</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nome_tipo_integrado" class="form-label">Nome do Curso *</label>
                                    <input type="text" class="form-control" id="nome_tipo_integrado" name="nome" required placeholder="Ex: Formação de Condutores">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao_tipo_integrado" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao_tipo_integrado" name="descricao" rows="3" placeholder="Descrição detalhada do curso"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="carga_horaria_integrado" class="form-label">Carga Horária Total *</label>
                                    <input type="number" class="form-control" id="carga_horaria_integrado" name="carga_horaria_total" min="1" max="200" value="45" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="ativo_tipo_integrado" name="ativo" value="1" checked>
                                        <label class="form-check-label" for="ativo_tipo_integrado">
                                            Tipo de curso ativo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="button" class="popup-secondary-button" onclick="voltarParaListaTipos()">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="popup-save-button">
                                <i class="fas fa-save"></i>
                                Salvar Curso
                            </button>
                        </div>
                    </form>
                </div>
                
            </div>
            
            <!-- FOOTER -->
            <div class="popup-modal-footer">
                <div class="popup-footer-info">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        As alterações são salvas automaticamente
                    </small>
                </div>
                <div class="popup-footer-actions">
                    <button type="button" class="popup-secondary-button" onclick="fecharModalTiposCurso()">
                        <i class="fas fa-times"></i>
                        Fechar
                    </button>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Modal Gerenciar Salas - Global -->
    <div class="popup-modal" id="modalGerenciarSalas" style="display: none;">
        <div class="popup-modal-wrapper">
            
            <!-- HEADER -->
            <div class="popup-modal-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="header-text">
                        <h5>Gerenciar Salas</h5>
                        <small>Configure e organize as salas de aula disponíveis</small>
                    </div>
                </div>
                <button type="button" class="popup-modal-close" onclick="fecharModalSalas()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- CONTEÚDO -->
            <div class="popup-modal-content">
                
                <!-- Seção Otimizada - Título, Estatísticas e Botão na mesma linha -->
                <div class="popup-section-header">
                    <div class="popup-section-title">
                        <h6>Salas Cadastradas</h6>
                        <small>Gerencie e organize as salas de aula do CFC</small>
                    </div>
                    <div class="popup-stats-item" style="margin: 0;">
                        <div class="popup-stats-icon">
                            <div class="icon-circle">
                                <i class="fas fa-door-open"></i>
                            </div>
                        </div>
                        <div class="popup-stats-text">
                            <h6 style="margin: 0;">Total: <span class="stats-number" id="total-salas">0</span></h6>
                        </div>
                    </div>
                    <button type="button" class="popup-primary-button" onclick="abrirModalNovaSalaInterno()">
                        <i class="fas fa-plus"></i>
                        Nova Sala
                    </button>
                </div>
                
                <!-- Container das salas -->
                <div id="lista-salas-modal">
                    <!-- As salas serão carregadas aqui via AJAX -->
                </div>
                
            </div>
            
            <!-- FOOTER -->
            <div class="popup-modal-footer">
                <div class="popup-footer-info">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        As alterações são salvas automaticamente
                    </small>
                </div>
                <div class="popup-footer-actions">
                    <button type="button" class="popup-secondary-button" onclick="fecharModalSalas()">
                        <i class="fas fa-times"></i>
                        Fechar
                    </button>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Scripts Globais -->
    <script>
    // Função global para recarregar lista de salas via AJAX
    function recarregarSalas() {
        console.log('🔄 Iniciando carregamento de salas...');
        
        // Mostrar loading state
        const salasContainer = document.getElementById('lista-salas-modal');
        if (!salasContainer) {
            console.error('❌ Container de salas não encontrado');
            return;
        }
        
        // Mostrar loading
        salasContainer.innerHTML = `
            <div class="popup-loading-state show">
                <div class="popup-loading-spinner"></div>
                <div class="popup-loading-text">
                    <h6>Carregando salas...</h6>
                    <p>Aguarde enquanto buscamos as salas cadastradas</p>
                </div>
            </div>
        `;
        
        // Cache simples no frontend para reduzir requisições (mitigação max_connections_per_hour)
        const cacheKey = 'salas_list_cache';
        const cacheData = sessionStorage.getItem(cacheKey);
        const cacheTime = sessionStorage.getItem(cacheKey + '_time');
        const now = Date.now();
        
        // Usar cache se existir e tiver menos de 60 segundos
        if (cacheData && cacheTime && (now - parseInt(cacheTime)) < 60000) {
            try {
                const data = JSON.parse(cacheData);
                console.log('📦 Usando cache de salas (economia de conexão)');
                renderSalas(data);
                return;
            } catch (e) {
                // Cache inválido, continuar com fetch
            }
        }
        
        // Fazer requisição AJAX
        fetch(getBasePath() + '/admin/api/salas-clean.php?action=listar')
        .then(response => response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Erro ao parsear JSON:', text);
                throw new Error('Resposta inválida do servidor');
            }
        }))
        .then(data => {
            console.log('📊 Dados recebidos:', data);
            
            // Salvar no cache (60s TTL)
            try {
                sessionStorage.setItem(cacheKey, JSON.stringify(data));
                sessionStorage.setItem(cacheKey + '_time', now.toString());
            } catch (e) {
                // Ignorar erros de cache (pode estar desabilitado)
            }
            
            if (data.sucesso && data.salas) {
                // Atualizar contador
                const totalElement = document.getElementById('total-salas');
                if (totalElement) {
                    totalElement.textContent = data.salas.length;
                }
                
                // Renderizar salas
                renderizarSalas(data.salas);
            } else {
                // Mostrar estado de erro
                salasContainer.innerHTML = `
                    <div class="popup-error-state show">
                        <div class="popup-error-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h5>Erro ao carregar salas</h5>
                        <p>${data.mensagem || 'Não foi possível carregar as salas cadastradas'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('❌ Erro ao carregar salas:', error);
            
            // Mostrar estado de erro
            salasContainer.innerHTML = `
                <div class="popup-error-state show">
                    <div class="popup-error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5>Erro de conexão</h5>
                    <p>Não foi possível conectar com o servidor</p>
                </div>
            `;
        });
    }
    
    // Função para renderizar as salas
    function renderizarSalas(salas) {
        const salasContainer = document.getElementById('lista-salas-modal');
        if (!salasContainer) return;
        
        if (salas.length === 0) {
            salasContainer.innerHTML = `
                <div class="popup-empty-state show">
                    <div class="popup-empty-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <h5>Nenhuma sala cadastrada</h5>
                    <p>Comece criando sua primeira sala de aula</p>
                </div>
            `;
            return;
        }
        
        // Renderizar grid de salas
        salasContainer.innerHTML = `
            <div class="popup-items-grid">
                ${salas.map(sala => `
                    <div class="popup-item-card">
                        <div class="popup-item-card-header">
                            <div class="popup-item-card-content">
                                <h6 class="popup-item-card-title">${sala.nome}</h6>
                                <div class="popup-item-card-code" style="background: ${sala.ativa == 1 ? '#28a745' : '#dc3545'}; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">
                                    ${sala.ativa == 1 ? 'ATIVA' : 'INATIVA'}
                                </div>
                                <div class="popup-item-card-description" style="margin-top: 0.5rem;">
                                    <i class="fas fa-users" style="color: #6c757d; margin-right: 0.5rem;"></i>
                                    Capacidade: ${sala.capacidade} alunos
                                </div>
                            </div>
                            <div class="popup-item-card-actions">
                                <button type="button" class="popup-item-card-menu" onclick="editarSala(${sala.id}, '${sala.nome}', ${sala.capacidade}, ${sala.ativa})" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="popup-item-card-menu" onclick="confirmarExclusaoSala(${sala.id}, '${sala.nome}')" title="Excluir" style="color: #dc3545;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    // Função global para editar sala
    function editarSala(id, nome, capacidade, ativa) {
        // Verificar se existe modal de edição na página atual
        const modalEditar = document.getElementById('modalEditarSala');
        if (modalEditar) {
            // Preencher dados do modal
            document.getElementById('editar_sala_id').value = id;
            document.getElementById('editar_nome').value = nome;
            document.getElementById('editar_capacidade').value = capacidade;
            document.getElementById('editar_ativa').checked = ativa == 1;
            
            // Abrir modal
            modalEditar.style.display = 'flex';
            modalEditar.classList.add('show', 'popup-fade-in');
            document.body.style.overflow = 'hidden';
        } else {
            // Se não tiver modal de edição, redirecionar para página de configurações
            window.location.href = `?page=configuracoes-salas&editar=${id}`;
        }
    }
    
    // Função global para confirmar exclusão de sala
    function confirmarExclusaoSala(id, nome) {
        if (confirm(`Tem certeza que deseja excluir a sala "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
            excluirSala(id, nome);
        }
    }
    
    // Função global para excluir sala
    function excluirSala(id, nome) {
        const formData = new FormData();
        formData.append('acao', 'excluir');
        formData.append('id', id);
        
        fetch(getBasePath() + '/admin/api/salas-clean.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Resposta inválida do servidor');
            }
        }))
        .then(data => {
            if (data.sucesso) {
                // Recarregar lista de salas
                recarregarSalas();
                
                // Mostrar mensagem de sucesso
                alert('Sala excluída com sucesso!');
            } else {
                alert('Erro ao excluir sala: ' + data.mensagem);
            }
        })
        .catch(error => {
            console.error('Erro ao excluir sala:', error);
            alert('Erro ao excluir sala: ' + error.message);
        });
    }
    
    // Função global para fechar modal de salas
    function fecharModalSalas() {
        const modal = document.getElementById('modalGerenciarSalas');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
    
    // Função global para abrir modal de nova sala
    function abrirModalNovaSalaInterno() {
        // Verificar se existe modal de nova sala na página atual
        const modalNovaSala = document.getElementById('modalNovaSala');
        if (modalNovaSala) {
            // Limpar formulário
            document.getElementById('formNovaSala').reset();
            document.getElementById('nome').value = '';
            document.getElementById('capacidade').value = '30';
            document.getElementById('ativa').checked = true;
            
            // Abrir modal
            modalNovaSala.style.display = 'flex';
            modalNovaSala.classList.add('show', 'popup-fade-in');
            document.body.style.overflow = 'hidden';
        } else {
            // Se não tiver modal de nova sala, redirecionar para página de configurações
            window.location.href = '?page=configuracoes-salas';
        }
    }
    
    // Função global para abrir modal de gerenciamento de salas
    function abrirModalSalasInterno() {
        console.log('🔧 Tentando abrir modal de salas...');
        
        // Primeiro, tentar encontrar o modal na página atual
        const modal = document.getElementById('modalGerenciarSalas');
        if (modal) {
            console.log('✅ Modal encontrado na página atual, abrindo...');
            modal.style.display = 'flex';
            modal.classList.add('show', 'popup-fade-in');
            document.body.style.overflow = 'hidden';
            
            // Recarregar as salas
            recarregarSalas();
            return;
        }
        
        // Se não encontrar o modal, redirecionar para a página de configurações
        console.log('⚠️ Modal não encontrado, redirecionando para página de configurações...');
        window.location.href = '?page=configuracoes-salas';
    }
    </script>
</body>
</html>
