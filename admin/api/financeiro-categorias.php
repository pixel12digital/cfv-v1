<?php
/**
 * API Categorias de Despesa (Contas a Pagar)
 * CRUD para financeiro_categorias_despesa. Uso: listagem em Contas a Pagar e tela de configuração.
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

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$currentUser = getCurrentUser();
if (empty($currentUser) || !in_array($currentUser['tipo'] ?? '', ['admin', 'secretaria'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão. Apenas Admin e Secretaria.']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $all = isset($_GET['all']) && $_GET['all'] === '1';
            $sql = "SELECT id, slug, nome, ativo, ordem, criado_em FROM financeiro_categorias_despesa";
            if (!$all) {
                $sql .= " WHERE ativo = 1";
            }
            $sql .= " ORDER BY ordem ASC, nome ASC";
            $rows = $db->fetchAll($sql);
            $list = [];
            foreach ($rows as $r) {
                $list[$r['slug']] = $r['nome'];
            }
            if ($all) {
                echo json_encode(['success' => true, 'data' => $rows, 'list' => $list]);
            } else {
                echo json_encode(['success' => true, 'data' => $list]);
            }
            break;
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $nome = trim($input['nome'] ?? '');
            if ($nome === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nome é obrigatório']);
                exit;
            }
            $slug = isset($input['slug']) && trim($input['slug']) !== '' 
                ? strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($input['slug']))) 
                : strtolower(preg_replace('/[^a-z0-9]/', '_', preg_replace('/\s+/', '_', $nome)));
            if ($slug === '') $slug = 'outros';
            $existe = $db->fetch("SELECT id FROM financeiro_categorias_despesa WHERE slug = ?", [$slug]);
            if ($existe) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Já existe uma categoria com este slug. Escolha outro nome.']);
                exit;
            }
            $ordem = (int)($input['ordem'] ?? 0);
            $db->insert('financeiro_categorias_despesa', [
                'slug' => $slug,
                'nome' => $nome,
                'ativo' => 1,
                'ordem' => $ordem
            ]);
            $id = $db->lastInsertId();
            $row = $db->fetch("SELECT id, slug, nome, ativo, ordem FROM financeiro_categorias_despesa WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $row]);
            break;
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = isset($input['id']) ? (int)$input['id'] : (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }
            $row = $db->fetch("SELECT id FROM financeiro_categorias_despesa WHERE id = ?", [$id]);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Categoria não encontrada']);
                exit;
            }
            $up = [];
            if (array_key_exists('nome', $input)) {
                $nome = trim($input['nome']);
                if ($nome === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nome não pode ser vazio']);
                    exit;
                }
                $up['nome'] = $nome;
            }
            if (array_key_exists('slug', $input)) {
                $slug = strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($input['slug'])));
                if ($slug !== '') $up['slug'] = $slug;
            }
            if (array_key_exists('ativo', $input)) $up['ativo'] = (int)$input['ativo'] ? 1 : 0;
            if (array_key_exists('ordem', $input)) $up['ordem'] = (int)$input['ordem'];
            if (!empty($up)) {
                $db->update('financeiro_categorias_despesa', $up, 'id = :id', ['id' => $id]);
            }
            $row = $db->fetch("SELECT id, slug, nome, ativo, ordem FROM financeiro_categorias_despesa WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $row]);
            break;
        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                exit;
            }
            $row = $db->fetch("SELECT id, slug FROM financeiro_categorias_despesa WHERE id = ?", [$id]);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Categoria não encontrada']);
                exit;
            }
            $uso = $db->fetch("SELECT COUNT(*) as n FROM financeiro_pagamentos WHERE categoria = ? AND status != 'cancelado'", [$row['slug']]);
            if (!empty($uso['n']) && (int)$uso['n'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Não é possível excluir: existem contas a pagar usando esta categoria. Desative-a em vez de excluir.']);
                exit;
            }
            $db->delete('financeiro_categorias_despesa', 'id = ?', [$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
