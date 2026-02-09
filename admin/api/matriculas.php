<?php
/**
 * API para gerenciamento de Matrículas
 * Sistema CFC - Bom Conselho
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Responder a requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir arquivos necessários
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

try {
    $db = Database::getInstance();
    
    // Verificar e criar colunas se não existirem na tabela matriculas
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'renach'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN renach VARCHAR(50) DEFAULT NULL AFTER observacoes");
        }
    } catch (Exception $e) {
        // Ignorar erro se tabela não existir ou coluna já existir
    }
    
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'processo_numero'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN processo_numero VARCHAR(100) DEFAULT NULL AFTER renach");
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'processo_numero_detran'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN processo_numero_detran VARCHAR(100) DEFAULT NULL AFTER processo_numero");
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'processo_situacao'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN processo_situacao VARCHAR(100) DEFAULT NULL AFTER processo_numero_detran");
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'previsao_conclusao'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN previsao_conclusao DATE DEFAULT NULL AFTER data_fim");
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'forma_pagamento'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN forma_pagamento VARCHAR(50) DEFAULT NULL AFTER valor_total");
            error_log('[DEBUG MATRICULA] Coluna forma_pagamento criada como VARCHAR(50)');
        } else {
            // Verificar se a coluna é ENUM e converter para VARCHAR se necessário
            $columnInfo = $rows[0];
            if (isset($columnInfo['Type']) && stripos($columnInfo['Type'], 'enum') !== false) {
                error_log('[DEBUG MATRICULA] Coluna forma_pagamento é ENUM, convertendo para VARCHAR...');
                try {
                    $db->query("ALTER TABLE matriculas MODIFY COLUMN forma_pagamento VARCHAR(50) DEFAULT NULL");
                    error_log('[DEBUG MATRICULA] Coluna forma_pagamento convertida para VARCHAR(50)');
                } catch (Exception $e2) {
                    error_log('[DEBUG MATRICULA] Erro ao converter coluna: ' . $e2->getMessage());
                }
            } else {
                error_log('[DEBUG MATRICULA] Coluna forma_pagamento já é VARCHAR ou outro tipo compatível: ' . ($columnInfo['Type'] ?? 'N/A'));
            }
        }
    } catch (Exception $e) {
        error_log('[DEBUG MATRICULA] Erro ao verificar/criar coluna forma_pagamento: ' . $e->getMessage());
    }
    
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'status_pagamento'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN status_pagamento VARCHAR(50) DEFAULT NULL AFTER forma_pagamento");
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    // Verificar e criar coluna instrutor_principal_id
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'instrutor_principal_id'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN instrutor_principal_id INT DEFAULT NULL AFTER processo_situacao");
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    // Verificar e criar coluna aulas_praticas_contratadas
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'aulas_praticas_contratadas'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN aulas_praticas_contratadas INT DEFAULT NULL AFTER processo_situacao");
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    // Verificar e criar coluna aulas_praticas_extras
    try {
        $result = $db->query("SHOW COLUMNS FROM matriculas LIKE 'aulas_praticas_extras'");
        $rows = $result->fetchAll();
        if (!$result || count($rows) === 0) {
            $db->query("ALTER TABLE matriculas ADD COLUMN aulas_praticas_extras INT DEFAULT NULL AFTER aulas_praticas_contratadas");
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    // Verificar autenticação
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
        exit;
    }
    
    // Verificar permissão
    $currentUser = getCurrentUser();
    if (!$currentUser || !in_array($currentUser['tipo'], ['admin', 'secretaria'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}

/**
 * Processar requisições GET
 */
function handleGet($db) {
    $alunoId = $_GET['aluno_id'] ?? null;
    
    if ($alunoId) {
        // Buscar matrículas de um aluno específico
        // Tentar selecionar explicitamente os campos, mas se falhar, usar SELECT m.*
        try {
            $matriculas = $db->fetchAll("
                SELECT 
                    m.id,
                    m.aluno_id,
                    m.categoria_cnh,
                    m.tipo_servico,
                    m.status,
                    m.data_inicio,
                    m.data_fim,
                    m.previsao_conclusao,
                    m.valor_total,
                    m.forma_pagamento,
                    m.status_pagamento,
                    m.observacoes,
                    m.renach,
                    m.processo_numero,
                    m.processo_numero_detran,
                    m.processo_situacao,
                    m.aulas_praticas_contratadas,
                    m.aulas_praticas_extras,
                    m.criado_em,
                    m.atualizado_em,
                    a.nome as aluno_nome, 
                    a.cpf as aluno_cpf
                FROM matriculas m
                JOIN alunos a ON m.aluno_id = a.id
                WHERE m.aluno_id = ?
                ORDER BY m.data_inicio DESC
            ", [$alunoId]);
        } catch (Exception $e) {
            // Se falhar (coluna não existe), usar SELECT m.* e adicionar campos manualmente
            $matriculas = $db->fetchAll("
                SELECT m.*, a.nome as aluno_nome, a.cpf as aluno_cpf
                FROM matriculas m
                JOIN alunos a ON m.aluno_id = a.id
                WHERE m.aluno_id = ?
                ORDER BY m.data_inicio DESC
            ", [$alunoId]);
            
            // Garantir que os campos existam no array (mesmo que NULL)
            // NÃO converter strings vazias para NULL - manter o valor exato do banco
            foreach ($matriculas as &$matricula) {
                if (!isset($matricula['aulas_praticas_contratadas'])) {
                    $matricula['aulas_praticas_contratadas'] = null;
                }
                if (!isset($matricula['aulas_praticas_extras'])) {
                    $matricula['aulas_praticas_extras'] = null;
                }
                if (!isset($matricula['forma_pagamento'])) {
                    $matricula['forma_pagamento'] = null;
                }
                // NÃO converter string vazia para NULL - manter valor exato do banco para debug
            }
            unset($matricula);
        }
        
        // Garantir que os campos existam no array (mesmo que NULL) - segunda camada de proteção
        // NÃO converter strings vazias - manter valor exato do banco
        foreach ($matriculas as &$matricula) {
            if (!isset($matricula['aulas_praticas_contratadas'])) {
                $matricula['aulas_praticas_contratadas'] = null;
            }
            if (!isset($matricula['aulas_praticas_extras'])) {
                $matricula['aulas_praticas_extras'] = null;
            }
            if (!isset($matricula['forma_pagamento'])) {
                $matricula['forma_pagamento'] = null;
            }
            // Log para debug
            error_log('[DEBUG MATRICULA GET] forma_pagamento retornado: ' . var_export($matricula['forma_pagamento'] ?? 'NULL', true));
        }
        unset($matricula);
        
        // Log para debug (apenas em desenvolvimento)
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            error_log('[API Matriculas] GET - Matrículas retornadas: ' . json_encode(array_map(function($m) {
                return [
                    'id' => $m['id'] ?? null,
                    'aulas_praticas_contratadas' => $m['aulas_praticas_contratadas'] ?? 'NÃO EXISTE',
                    'aulas_praticas_extras' => $m['aulas_praticas_extras'] ?? 'NÃO EXISTE',
                    'forma_pagamento' => $m['forma_pagamento'] ?? 'NÃO EXISTE'
                ];
            }, $matriculas)));
        }
        
        echo json_encode(['success' => true, 'matriculas' => $matriculas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        // Listar todas as matrículas
        try {
            $matriculas = $db->fetchAll("
                SELECT 
                    m.id,
                    m.aluno_id,
                    m.categoria_cnh,
                    m.tipo_servico,
                    m.status,
                    m.data_inicio,
                    m.data_fim,
                    m.previsao_conclusao,
                    m.valor_total,
                    m.forma_pagamento,
                    m.status_pagamento,
                    m.observacoes,
                    m.renach,
                    m.processo_numero,
                    m.processo_numero_detran,
                    m.processo_situacao,
                    m.forma_pagamento,
                    m.aulas_praticas_contratadas,
                    m.aulas_praticas_extras,
                    m.criado_em,
                    m.atualizado_em,
                    a.nome as aluno_nome, 
                    a.cpf as aluno_cpf
                FROM matriculas m
                JOIN alunos a ON m.aluno_id = a.id
                ORDER BY m.data_inicio DESC
                LIMIT 100
            ");
        } catch (Exception $e) {
            // Se falhar, usar SELECT m.*
            $matriculas = $db->fetchAll("
                SELECT m.*, a.nome as aluno_nome, a.cpf as aluno_cpf
                FROM matriculas m
                JOIN alunos a ON m.aluno_id = a.id
                ORDER BY m.data_inicio DESC
                LIMIT 100
            ");
            
            // Garantir que os campos existam
            foreach ($matriculas as &$matricula) {
                if (!isset($matricula['aulas_praticas_contratadas'])) {
                    $matricula['aulas_praticas_contratadas'] = null;
                }
                if (!isset($matricula['aulas_praticas_extras'])) {
                    $matricula['aulas_praticas_extras'] = null;
                }
                if (!isset($matricula['forma_pagamento'])) {
                    $matricula['forma_pagamento'] = null;
                }
            }
            unset($matricula);
        }
        
        // Garantir que os campos existam no array (mesmo que NULL)
        foreach ($matriculas as &$matricula) {
            if (!isset($matricula['aulas_praticas_contratadas'])) {
                $matricula['aulas_praticas_contratadas'] = null;
            }
            if (!isset($matricula['aulas_praticas_extras'])) {
                $matricula['aulas_praticas_extras'] = null;
            }
            if (!isset($matricula['forma_pagamento'])) {
                $matricula['forma_pagamento'] = null;
            }
        }
        unset($matricula);
        
        echo json_encode(['success' => true, 'matriculas' => $matriculas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

/**
 * Processar requisições POST
 */
function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        return;
    }
    
    // Log para debug (apenas em desenvolvimento)
    if (defined('LOG_ENABLED') && LOG_ENABLED) {
        error_log('[API Matriculas] POST - Dados recebidos: ' . json_encode($input));
        error_log('[API Matriculas] POST - forma_pagamento: ' . ($input['forma_pagamento'] ?? 'NÃO ENVIADO'));
    }
    
    // Validar dados obrigatórios
    $required = ['aluno_id', 'categoria_cnh', 'tipo_servico', 'data_inicio'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Campo obrigatório: $field"]);
            return;
        }
    }
    
    // Verificar se aluno existe
    $aluno = $db->fetch("SELECT id FROM alunos WHERE id = ?", [$input['aluno_id']]);
    if (!$aluno) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Aluno não encontrado']);
        return;
    }
    
    // Verificar se já existe matrícula ativa da mesma categoria + tipo_servico
    // (apenas para criação, não para edição)
    $matriculaExistente = $db->fetch("
        SELECT id FROM matriculas 
        WHERE aluno_id = ? AND categoria_cnh = ? AND tipo_servico = ? AND status = 'ativa'
    ", [$input['aluno_id'], $input['categoria_cnh'], $input['tipo_servico']]);
    
    if ($matriculaExistente) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Já existe uma matrícula ativa para esta categoria e tipo de serviço'
        ]);
        return;
    }
    
    // Inserir nova matrícula
    $matriculaId = $db->insert('matriculas', [
        'aluno_id' => $input['aluno_id'],
        'categoria_cnh' => $input['categoria_cnh'],
        'tipo_servico' => $input['tipo_servico'],
        'status' => $input['status'] ?? 'ativa',
        'data_inicio' => $input['data_inicio'],
        'data_fim' => $input['data_fim'] ?? null,
        'previsao_conclusao' => $input['previsao_conclusao'] ?? null,
        'valor_total' => $input['valor_total'] ?? null,
        'forma_pagamento' => (isset($input['forma_pagamento']) && $input['forma_pagamento'] !== '' && $input['forma_pagamento'] !== null && $input['forma_pagamento'] !== 'Selecione...') 
            ? trim((string)$input['forma_pagamento']) 
            : null,
        'status_pagamento' => (isset($input['status_pagamento']) && $input['status_pagamento'] !== '' && $input['status_pagamento'] !== null && $input['status_pagamento'] !== 'Selecione...') 
            ? trim((string)$input['status_pagamento']) 
            : null,
        'observacoes' => $input['observacoes'] ?? null,
        'renach' => $input['renach'] ?? null,
        'processo_numero' => $input['processo_numero'] ?? null,
        'processo_numero_detran' => $input['processo_numero_detran'] ?? null,
        'processo_situacao' => $input['processo_situacao'] ?? null,
        'aulas_praticas_contratadas' => isset($input['aulas_praticas_contratadas']) && $input['aulas_praticas_contratadas'] !== '' && $input['aulas_praticas_contratadas'] !== null && $input['aulas_praticas_contratadas'] !== '0' && is_numeric($input['aulas_praticas_contratadas'])
            ? (int)$input['aulas_praticas_contratadas']
            : null,
        'aulas_praticas_extras' => isset($input['aulas_praticas_extras']) && $input['aulas_praticas_extras'] !== '' && $input['aulas_praticas_extras'] !== null && $input['aulas_praticas_extras'] !== '0' && is_numeric($input['aulas_praticas_extras'])
            ? (int)$input['aulas_praticas_extras']
            : null
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Matrícula criada com sucesso',
        'matricula_id' => $matriculaId
    ]);
}

/**
 * Processar requisições PUT
 */
function handlePut($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID da matrícula não fornecido']);
        return;
    }
    
    $rawInput = file_get_contents('php://input');
    error_log('[DEBUG MATRICULA PUT] Raw input recebido: ' . $rawInput);
    
    $input = json_decode($rawInput, true);
    
    error_log('[DEBUG MATRICULA PUT] Input decodificado: ' . json_encode($input, JSON_UNESCAPED_UNICODE));
    error_log('[DEBUG MATRICULA PUT] forma_pagamento recebida: ' . print_r($input['forma_pagamento'] ?? null, true));
    error_log('[DEBUG MATRICULA PUT] forma_pagamento isset: ' . (isset($input['forma_pagamento']) ? 'SIM' : 'NÃO'));
    error_log('[DEBUG MATRICULA PUT] forma_pagamento tipo: ' . (isset($input['forma_pagamento']) ? gettype($input['forma_pagamento']) : 'N/A'));
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        return;
    }
    
    // Log para debug (apenas em desenvolvimento)
    if (defined('LOG_ENABLED') && LOG_ENABLED) {
        error_log('[API Matriculas] PUT - Dados recebidos: ' . json_encode([
            'id' => $id,
            'forma_pagamento_input' => $input['forma_pagamento'] ?? 'NÃO DEFINIDO',
            'forma_pagamento_isset' => isset($input['forma_pagamento']),
            'forma_pagamento_type' => isset($input['forma_pagamento']) ? gettype($input['forma_pagamento']) : 'N/A',
            'aulas_praticas_contratadas' => $input['aulas_praticas_contratadas'] ?? 'NÃO ENVIADO',
            'aulas_praticas_extras' => $input['aulas_praticas_extras'] ?? 'NÃO ENVIADO',
            'forma_pagamento' => $input['forma_pagamento'] ?? 'NÃO ENVIADO'
        ]));
    }
    
    // Log para debug (apenas em desenvolvimento)
    if (defined('LOG_ENABLED') && LOG_ENABLED) {
        error_log('[API Matriculas] PUT - Dados recebidos: ' . json_encode($input));
        error_log('[API Matriculas] PUT - forma_pagamento: ' . ($input['forma_pagamento'] ?? 'NÃO ENVIADO'));
    }
    
    // Verificar se matrícula existe
    $matricula = $db->fetch("SELECT * FROM matriculas WHERE id = ?", [$id]);
    if (!$matricula) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Matrícula não encontrada']);
        return;
    }
    
    // Verificar unicidade: não pode haver outra matrícula ativa com mesmo aluno + categoria + tipo
    // (ignorando a própria matrícula que está sendo editada)
    $statusNovo = $input['status'] ?? $matricula['status'];
    $categoriaNova = $input['categoria_cnh'] ?? $matricula['categoria_cnh'];
    $tipoServicoNovo = $input['tipo_servico'] ?? $matricula['tipo_servico'];
    
    // Só validar unicidade se o status for 'ativa'
    if ($statusNovo === 'ativa') {
        $matriculaConflitante = $db->fetch("
            SELECT id FROM matriculas 
            WHERE aluno_id = ? 
              AND categoria_cnh = ? 
              AND tipo_servico = ? 
              AND status = 'ativa'
              AND id <> ?
        ", [$matricula['aluno_id'], $categoriaNova, $tipoServicoNovo, $id]);
        
        if ($matriculaConflitante) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Já existe uma matrícula ativa para esta categoria e tipo de serviço'
            ]);
            return;
        }
    }
    
    // Preparar dados para atualização
    $dadosUpdate = [
        'categoria_cnh' => $categoriaNova,
        'tipo_servico' => $tipoServicoNovo,
        'status' => $statusNovo,
        'data_inicio' => $input['data_inicio'] ?? $matricula['data_inicio'],
        'valor_total' => $input['valor_total'] ?? $matricula['valor_total'],
        'forma_pagamento' => (function() use ($input) {
            // Log sempre (sem depender de LOG_ENABLED)
            error_log('[DEBUG MATRICULA PUT] Processando forma_pagamento...');
            
            // Verificar se o campo existe no input
            if (!isset($input['forma_pagamento'])) {
                error_log('[DEBUG MATRICULA PUT] forma_pagamento NÃO está no input, retornando NULL');
                return null;
            }
            
            $valor = $input['forma_pagamento'];
            error_log('[DEBUG MATRICULA PUT] forma_pagamento valor bruto: ' . var_export($valor, true) . ' (tipo: ' . gettype($valor) . ')');
            
            // Se for null, string vazia ou "Selecione...", retornar null
            if ($valor === null || $valor === '' || $valor === 'Selecione...') {
                error_log('[DEBUG MATRICULA PUT] forma_pagamento inválido (null/vazio/Selecione), retornando NULL');
                return null;
            }
            
            // Converter para string e fazer trim
            $valorTrim = trim((string)$valor);
            error_log('[DEBUG MATRICULA PUT] forma_pagamento após trim: ' . var_export($valorTrim, true));
            
            // Se após trim ficar vazio, retornar null
            if ($valorTrim === '') {
                error_log('[DEBUG MATRICULA PUT] forma_pagamento após trim está vazio, retornando NULL');
                return null;
            }
            
            // Valor válido, retornar (garantir que não é NULL)
            error_log('[DEBUG MATRICULA PUT] forma_pagamento será salvo como: ' . $valorTrim . ' (tipo final: ' . gettype($valorTrim) . ')');
            return $valorTrim; // Retornar string, nunca NULL se chegou aqui
        })(),
        'status_pagamento' => (isset($input['status_pagamento']) && $input['status_pagamento'] !== '' && $input['status_pagamento'] !== null && $input['status_pagamento'] !== 'Selecione...')
            ? trim((string)$input['status_pagamento']) 
            : ($matricula['status_pagamento'] ?? null),
        'observacoes' => $input['observacoes'] ?? $matricula['observacoes'],
        'renach' => $input['renach'] ?? ($matricula['renach'] ?? null),
        'processo_numero' => $input['processo_numero'] ?? ($matricula['processo_numero'] ?? null),
        'processo_numero_detran' => $input['processo_numero_detran'] ?? ($matricula['processo_numero_detran'] ?? null),
        'processo_situacao' => $input['processo_situacao'] ?? ($matricula['processo_situacao'] ?? null),
        'previsao_conclusao' => $input['previsao_conclusao'] ?? ($matricula['previsao_conclusao'] ?? null),
        'aulas_praticas_contratadas' => (isset($input['aulas_praticas_contratadas']) && $input['aulas_praticas_contratadas'] !== '' && $input['aulas_praticas_contratadas'] !== null && $input['aulas_praticas_contratadas'] !== '0' && is_numeric($input['aulas_praticas_contratadas']))
            ? (int)$input['aulas_praticas_contratadas']
            : null, // Sempre usar null se vazio, não manter valor anterior
        'aulas_praticas_extras' => (isset($input['aulas_praticas_extras']) && $input['aulas_praticas_extras'] !== '' && $input['aulas_praticas_extras'] !== null && $input['aulas_praticas_extras'] !== '0' && is_numeric($input['aulas_praticas_extras']))
            ? (int)$input['aulas_praticas_extras']
            : null, // Sempre usar null se vazio, não manter valor anterior
        'atualizado_em' => date('Y-m-d H:i:s')
    ];
    
    // Lógica para data_conclusao automática
    $statusAnterior = $matricula['status'];
    $dataFimAnterior = $matricula['data_fim'];
    
    // Se status mudou para 'concluida' e data_fim está vazia, preencher automaticamente
    if ($statusNovo === 'concluida' && $statusAnterior !== 'concluida') {
        if (empty($dataFimAnterior) && empty($input['data_fim'])) {
            // Preencher automaticamente
            $dadosUpdate['data_fim'] = date('Y-m-d');
        } else {
            // Manter data existente ou usar a fornecida
            $dadosUpdate['data_fim'] = $input['data_fim'] ?? $dataFimAnterior;
        }
    } else {
        // Se não mudou para concluida, usar valor fornecido ou manter existente
        $dadosUpdate['data_fim'] = $input['data_fim'] ?? $dataFimAnterior;
    }
    
    // Log SEMPRE (sem depender de LOG_ENABLED) - ANTES DE SALVAR
    error_log('[DEBUG MATRICULA PUT] ========== ANTES DE SALVAR ==========');
    error_log('[DEBUG MATRICULA PUT] forma_pagamento no dadosUpdate: ' . var_export($dadosUpdate['forma_pagamento'] ?? 'NÃO DEFINIDO', true));
    error_log('[DEBUG MATRICULA PUT] Tipo de forma_pagamento: ' . (isset($dadosUpdate['forma_pagamento']) ? gettype($dadosUpdate['forma_pagamento']) : 'NÃO DEFINIDO'));
    error_log('[DEBUG MATRICULA PUT] dadosUpdate completo (JSON): ' . json_encode($dadosUpdate, JSON_UNESCAPED_UNICODE));
    
    // Garantir que forma_pagamento não seja sobrescrito
    $formaPagamentoAntesUpdate = $dadosUpdate['forma_pagamento'] ?? 'NÃO DEFINIDO';
    error_log('[DEBUG MATRICULA PUT] forma_pagamento ANTES do UPDATE: ' . var_export($formaPagamentoAntesUpdate, true));
    
    // Atualizar matrícula
    error_log('[DEBUG MATRICULA PUT] Executando UPDATE...');
    $resultadoUpdate = $db->update('matriculas', $dadosUpdate, 'id = ?', [$id]);
    error_log('[DEBUG MATRICULA PUT] UPDATE executado. Resultado: ' . var_export($resultadoUpdate, true));
    
    // Verificar se forma_pagamento ainda está no array após update (não deveria mudar, mas vamos garantir)
    error_log('[DEBUG MATRICULA PUT] forma_pagamento no dadosUpdate APÓS update: ' . var_export($dadosUpdate['forma_pagamento'] ?? 'NÃO DEFINIDO', true));
    
    // Verificar se foi salvo corretamente - query direta no banco
    error_log('[DEBUG MATRICULA PUT] Consultando banco após UPDATE...');
    $matriculaDebug = $db->fetch("SELECT id, forma_pagamento, aulas_praticas_contratadas, aulas_praticas_extras FROM matriculas WHERE id = ?", [$id]);
    error_log('[DEBUG MATRICULA DB] forma_pagamento após UPDATE: ' . var_export($matriculaDebug['forma_pagamento'] ?? 'NULL', true));
    error_log('[DEBUG MATRICULA DB] Dados completos após UPDATE: ' . json_encode($matriculaDebug, JSON_UNESCAPED_UNICODE));
    
    // Verificar se foi salvo corretamente (para resposta)
    $matriculaAtualizada = $db->fetch("SELECT aulas_praticas_contratadas, aulas_praticas_extras, forma_pagamento FROM matriculas WHERE id = ?", [$id]);
    error_log('[DEBUG MATRICULA PUT] forma_pagamento que tentamos salvar: ' . var_export($dadosUpdate['forma_pagamento'] ?? 'NÃO DEFINIDO', true));
    error_log('[DEBUG MATRICULA PUT] forma_pagamento que foi salvo no banco: ' . var_export($matriculaAtualizada['forma_pagamento'] ?? 'NULL', true));
    
    echo json_encode(['success' => true, 'message' => 'Matrícula atualizada com sucesso']);
}

/**
 * Processar requisições DELETE
 * Apenas ADMIN pode excluir matrículas (alinhado com AlunosController::excluirMatricula)
 */
function handleDelete($db) {
    $currentUser = getCurrentUser();
    if (($currentUser['tipo'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Apenas administradores podem excluir matrículas']);
        return;
    }

    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID da matrícula não fornecido']);
        return;
    }
    
    // Verificar se matrícula existe
    $matricula = $db->fetch("SELECT * FROM matriculas WHERE id = ?", [$id]);
    if (!$matricula) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Matrícula não encontrada']);
        return;
    }
    
    // Verificar se pode ser excluída (apenas se não há aulas vinculadas)
    $aulasVinculadas = $db->fetch("
        SELECT COUNT(*) as total FROM aulas 
        WHERE aluno_id = ? AND data_aula >= ?
    ", [$matricula['aluno_id'], $matricula['data_inicio']]);
    
    if ($aulasVinculadas['total'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Não é possível excluir matrícula com aulas vinculadas'
        ]);
        return;
    }
    
    // Excluir matrícula
    $db->delete('matriculas', 'id = ?', [$id]);
    
    echo json_encode(['success' => true, 'message' => 'Matrícula excluída com sucesso']);
}
