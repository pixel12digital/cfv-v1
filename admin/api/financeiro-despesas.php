<?php
/**
 * API Contas a Pagar (Agenda de Pagamentos)
 * Sistema CFC - Bom Conselho
 * Tabela: financeiro_pagamentos
 * Status armazenado: pendente | pago | cancelado. "Vencido" = pendente + vencimento < hoje (calculado).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!defined('FINANCEIRO_ENABLED') || !FINANCEIRO_ENABLED) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Módulo financeiro desabilitado']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$currentUser = getCurrentUser();
if (empty($currentUser) || !in_array($currentUser['tipo'] ?? '', ['admin', 'secretaria'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão. Apenas Secretaria e Admin.']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $currentUser);
            break;
        case 'POST':
            handlePost($db, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $currentUser);
            break;
        case 'DELETE':
            handleDelete($db, $currentUser);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Retorna status para exibição: pago | vencido | aberto
 */
function statusExibicao($row) {
    if ($row['status'] === 'pago') return 'pago';
    if ($row['status'] === 'pendente') {
        $venc = strtotime($row['vencimento']);
        return $venc < strtotime(date('Y-m-d')) ? 'vencido' : 'aberto';
    }
    return $row['status']; // cancelado
}

function handleGet($db, $user) {
    $id = $_GET['id'] ?? null;
    $categoria = $_GET['categoria'] ?? null;
    $status_filter = $_GET['status'] ?? null; // aberto | pago | vencido
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim = $_GET['data_fim'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    if (isset($_GET['relatorio']) && $_GET['relatorio'] === 'totais') {
        relatorioTotais($db, $data_inicio, $data_fim);
        return;
    }

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        exportCSV($db, $user, $status_filter, $data_inicio, $data_fim);
        return;
    }

    if ($id) {
        $despesa = $db->fetch("
            SELECT p.*, u.nome as criado_por_nome
            FROM financeiro_pagamentos p
            LEFT JOIN usuarios u ON p.criado_por = u.id
            WHERE p.id = ?
        ", [$id]);
        if (!$despesa) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Conta não encontrada']);
            return;
        }
        $despesa['status_exibicao'] = statusExibicao($despesa);
        echo json_encode(['success' => true, 'data' => $despesa]);
        return;
    }

    $where = ["p.status != 'cancelado'"];
    $params = [];

    if ($categoria) {
        $where[] = 'p.categoria = ?';
        $params[] = $categoria;
    }
    if ($data_inicio) {
        $where[] = 'p.vencimento >= ?';
        $params[] = $data_inicio;
    }
    if ($data_fim) {
        $where[] = 'p.vencimento <= ?';
        $params[] = $data_fim;
    }
    if ($status_filter === 'aberto') {
        $where[] = "p.status = 'pendente'";
        $where[] = 'p.vencimento >= CURDATE()';
    } elseif ($status_filter === 'vencido') {
        $where[] = "p.status = 'pendente'";
        $where[] = 'p.vencimento < CURDATE()';
    } elseif ($status_filter === 'pago') {
        $where[] = "p.status = 'pago'";
    }

    $whereClause = implode(' AND ', $where);

    $total = $db->fetchColumn("
        SELECT COUNT(*) FROM financeiro_pagamentos p WHERE $whereClause
    ", $params);

    $despesas = $db->fetchAll("
        SELECT p.*, u.nome as criado_por_nome
        FROM financeiro_pagamentos p
        LEFT JOIN usuarios u ON p.criado_por = u.id
        WHERE $whereClause
        ORDER BY p.vencimento ASC, p.criado_em DESC
        LIMIT $limit OFFSET $offset
    ", $params);

    foreach ($despesas as &$d) {
        $d['status_exibicao'] = statusExibicao($d);
    }

    echo json_encode([
        'success' => true,
        'data' => $despesas,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
        ]
    ]);
}

function relatorioTotais($db, $data_inicio, $data_fim) {
    $where_periodo = '1=1';
    $params = [];
    if ($data_inicio) {
        $where_periodo .= ' AND vencimento >= ?';
        $params[] = $data_inicio;
    }
    if ($data_fim) {
        $where_periodo .= ' AND vencimento <= ?';
        $params[] = $data_fim;
    }

    $aberto_valor = $db->fetchColumn("
        SELECT COALESCE(SUM(valor), 0) FROM financeiro_pagamentos
        WHERE status = 'pendente' AND vencimento >= CURDATE() AND $where_periodo
    ", $params);
    $aberto_qtd = $db->fetchColumn("
        SELECT COUNT(*) FROM financeiro_pagamentos
        WHERE status = 'pendente' AND vencimento >= CURDATE() AND $where_periodo
    ", $params);

    $vencido_valor = $db->fetchColumn("
        SELECT COALESCE(SUM(valor), 0) FROM financeiro_pagamentos
        WHERE status = 'pendente' AND vencimento < CURDATE() AND $where_periodo
    ", $params);
    $vencido_qtd = $db->fetchColumn("
        SELECT COUNT(*) FROM financeiro_pagamentos
        WHERE status = 'pendente' AND vencimento < CURDATE() AND $where_periodo
    ", $params);

    $pago_valor = $db->fetchColumn("
        SELECT COALESCE(SUM(valor), 0) FROM financeiro_pagamentos
        WHERE status = 'pago' AND $where_periodo
    ", $params);
    $pago_qtd = $db->fetchColumn("
        SELECT COUNT(*) FROM financeiro_pagamentos
        WHERE status = 'pago' AND $where_periodo
    ", $params);

    echo json_encode([
        'success' => true,
        'data' => [
            'aberto' => ['valor' => (float)$aberto_valor, 'quantidade' => (int)$aberto_qtd],
            'vencido' => ['valor' => (float)$vencido_valor, 'quantidade' => (int)$vencido_qtd],
            'pago' => ['valor' => (float)$pago_valor, 'quantidade' => (int)$pago_qtd],
            'periodo' => ['data_inicio' => $data_inicio, 'data_fim' => $data_fim]
        ]
    ]);
}

function handlePost($db, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        return;
    }

    $descricao = isset($input['descricao']) ? trim($input['descricao']) : '';
    $valor = isset($input['valor']) ? $input['valor'] : null;
    $vencimento = isset($input['vencimento']) ? trim($input['vencimento']) : '';

    if ($descricao === '' || $valor === null || $vencimento === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Campos obrigatórios: descricao, valor, vencimento']);
        return;
    }

    $valor = (float) $valor;
    if ($valor <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valor deve ser maior que zero']);
        return;
    }

    $fornecedor = isset($input['fornecedor']) ? trim($input['fornecedor']) : $descricao;
    if ($fornecedor === '') $fornecedor = $descricao;

    $despesaId = $db->insert('financeiro_pagamentos', [
        'fornecedor' => $fornecedor,
        'descricao' => $descricao,
        'categoria' => isset($input['categoria']) && $input['categoria'] !== '' ? $input['categoria'] : 'outros',
        'valor' => $valor,
        'status' => 'pendente',
        'vencimento' => $vencimento,
        'forma_pagamento' => $input['forma_pagamento'] ?? 'pix',
        'data_pagamento' => null,
        'observacoes' => $input['observacoes'] ?? null,
        'comprovante_url' => $input['comprovante_url'] ?? null,
        'criado_por' => $user['id']
    ]);

    echo json_encode([
        'success' => true,
        'despesa_id' => $despesaId,
        'message' => 'Conta cadastrada com sucesso'
    ]);
}

function handlePut($db, $user) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID obrigatório']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        return;
    }

    $despesa = $db->fetch('SELECT * FROM financeiro_pagamentos WHERE id = ?', [$id]);
    if (!$despesa) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conta não encontrada']);
        return;
    }

    $action = $input['action'] ?? null;

    if ($action === 'baixar') {
        if ($despesa['status'] === 'pago') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Conta já está paga']);
            return;
        }
        $data_pagamento = !empty($input['data_pagamento']) ? $input['data_pagamento'] : date('Y-m-d');
        $db->update('financeiro_pagamentos', [
            'status' => 'pago',
            'data_pagamento' => $data_pagamento,
            'atualizado_em' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
        echo json_encode(['success' => true, 'message' => 'Pagamento registrado']);
        return;
    }

    if ($action === 'estornar') {
        if ($despesa['status'] !== 'pago') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Apenas contas pagas podem ser estornadas']);
            return;
        }
        $db->update('financeiro_pagamentos', [
            'status' => 'pendente',
            'data_pagamento' => null,
            'atualizado_em' => date('Y-m-d H:i:s')
        ], 'id = ?', [$id]);
        echo json_encode(['success' => true, 'message' => 'Pagamento estornado']);
        return;
    }

    if ($despesa['status'] === 'pago') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Conta paga não pode ser editada. Use "estornar" para reabrir.']);
        return;
    }

    $allowedFields = ['fornecedor', 'descricao', 'categoria', 'valor', 'vencimento', 'forma_pagamento', 'observacoes', 'comprovante_url'];
    $updateData = [];
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $input)) {
            $updateData[$field] = $input[$field];
        }
    }
    if (empty($updateData)) {
        echo json_encode(['success' => true, 'message' => 'Nenhuma alteração']);
        return;
    }
    $updateData['atualizado_em'] = date('Y-m-d H:i:s');
    $db->update('financeiro_pagamentos', $updateData, 'id = ?', [$id]);
    echo json_encode(['success' => true, 'message' => 'Conta atualizada']);
}

function handleDelete($db, $user) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID obrigatório']);
        return;
    }

    $despesa = $db->fetch('SELECT * FROM financeiro_pagamentos WHERE id = ?', [$id]);
    if (!$despesa) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conta não encontrada']);
        return;
    }

    if ($despesa['status'] !== 'pendente') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Apenas contas em aberto podem ser excluídas']);
        return;
    }

    $db->delete('financeiro_pagamentos', 'id = ?', [$id]);
    echo json_encode(['success' => true, 'message' => 'Conta excluída']);
}

function exportCSV($db, $user, $status_filter, $data_inicio, $data_fim) {
    $where = ["p.status != 'cancelado'"];
    $params = [];
    if ($status_filter === 'aberto') {
        $where[] = "p.status = 'pendente' AND p.vencimento >= CURDATE()";
    } elseif ($status_filter === 'vencido') {
        $where[] = "p.status = 'pendente' AND p.vencimento < CURDATE()";
    } elseif ($status_filter === 'pago') {
        $where[] = "p.status = 'pago'";
    }
    if ($data_inicio) { $where[] = 'p.vencimento >= ?'; $params[] = $data_inicio; }
    if ($data_fim) { $where[] = 'p.vencimento <= ?'; $params[] = $data_fim; }
    $whereClause = implode(' AND ', $where);

    $despesas = $db->fetchAll("
        SELECT p.*, u.nome as criado_por_nome
        FROM financeiro_pagamentos p
        LEFT JOIN usuarios u ON p.criado_por = u.id
        WHERE $whereClause
        ORDER BY p.vencimento DESC, p.criado_em DESC
    ", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=contas_a_pagar_' . date('Y-m-d') . '.csv');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID', 'Descrição', 'Fornecedor', 'Categoria', 'Valor', 'Status', 'Vencimento', 'Data Pagamento', 'Observações', 'Criado em']);

    foreach ($despesas as $d) {
        $statusEx = statusExibicao($d);
        fputcsv($out, [
            $d['id'],
            $d['descricao'] ?? $d['fornecedor'],
            $d['fornecedor'],
            $d['categoria'],
            number_format($d['valor'], 2, ',', '.'),
            $statusEx,
            date('d/m/Y', strtotime($d['vencimento'])),
            $d['data_pagamento'] ? date('d/m/Y', strtotime($d['data_pagamento'])) : '',
            $d['observacoes'] ?? '',
            date('d/m/Y H:i', strtotime($d['criado_em']))
        ]);
    }
    fclose($out);
}
