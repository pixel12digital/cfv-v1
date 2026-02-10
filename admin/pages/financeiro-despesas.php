<?php
/**
 * Contas a Pagar (Agenda de Pagamentos)
 * Tabela: financeiro_pagamentos. Status exibição: aberto (pendente + não vencido) | vencido | pago.
 */

if (!defined('FINANCEIRO_ENABLED') || !FINANCEIRO_ENABLED) {
    echo '<div class="alert alert-danger">Módulo financeiro não está habilitado.</div>';
    return;
}

if (!$isAdmin && ($user['tipo'] ?? '') !== 'secretaria') {
    echo '<div class="alert alert-danger">Você não tem permissão para acessar esta página.</div>';
    return;
}

// Status para exibição (igual à API)
function status_exibicao_cap($row) {
    if ($row['status'] === 'pago') return 'pago';
    if ($row['status'] === 'pendente') {
        $venc = strtotime($row['vencimento']);
        return $venc < strtotime(date('Y-m-d')) ? 'vencido' : 'aberto';
    }
    return $row['status'];
}

$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_status = $_GET['status'] ?? ''; // aberto | pago | vencido
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

$where = ["p.status != 'cancelado'"];
$params = [];
if ($filtro_categoria) {
    $where[] = 'p.categoria = ?';
    $params[] = $filtro_categoria;
}
if ($filtro_data_inicio) {
    $where[] = 'p.vencimento >= ?';
    $params[] = $filtro_data_inicio;
}
if ($filtro_data_fim) {
    $where[] = 'p.vencimento <= ?';
    $params[] = $filtro_data_fim;
}
if ($filtro_status === 'aberto') {
    $where[] = "p.status = 'pendente'";
    $where[] = 'p.vencimento >= CURDATE()';
} elseif ($filtro_status === 'vencido') {
    $where[] = "p.status = 'pendente'";
    $where[] = 'p.vencimento < CURDATE()';
} elseif ($filtro_status === 'pago') {
    $where[] = "p.status = 'pago'";
}

$where_sql = implode(' AND ', $where);

try {
    $despesas = $db->fetchAll("
        SELECT p.*, u.nome as criado_por_nome
        FROM financeiro_pagamentos p
        LEFT JOIN usuarios u ON p.criado_por = u.id
        WHERE $where_sql
        ORDER BY p.vencimento ASC, p.criado_em DESC
        LIMIT 500
    ", $params);
} catch (Exception $e) {
    $despesas = [];
}

foreach ($despesas as &$d) {
    $d['status_exibicao'] = status_exibicao_cap($d);
}

// Relatório totais do período (mesmos filtros de data)
$where_totais = '1=1';
$params_totais = [];
if ($filtro_data_inicio) { $where_totais .= ' AND vencimento >= ?'; $params_totais[] = $filtro_data_inicio; }
if ($filtro_data_fim) { $where_totais .= ' AND vencimento <= ?'; $params_totais[] = $filtro_data_fim; }

try {
    $totais_aberto = $db->fetch("SELECT COALESCE(SUM(valor), 0) as valor, COUNT(*) as qtd FROM financeiro_pagamentos WHERE status = 'pendente' AND vencimento >= CURDATE() AND $where_totais", $params_totais);
    $totais_vencido = $db->fetch("SELECT COALESCE(SUM(valor), 0) as valor, COUNT(*) as qtd FROM financeiro_pagamentos WHERE status = 'pendente' AND vencimento < CURDATE() AND $where_totais", $params_totais);
    $totais_pago = $db->fetch("SELECT COALESCE(SUM(valor), 0) as valor, COUNT(*) as qtd FROM financeiro_pagamentos WHERE status = 'pago' AND $where_totais", $params_totais);
} catch (Exception $e) {
    $totais_aberto = $totais_vencido = $totais_pago = ['valor' => 0, 'qtd' => 0];
}
$totais_aberto = is_array($totais_aberto) ? $totais_aberto : ['valor' => 0, 'qtd' => 0];
$totais_vencido = is_array($totais_vencido) ? $totais_vencido : ['valor' => 0, 'qtd' => 0];
$totais_pago = is_array($totais_pago) ? $totais_pago : ['valor' => 0, 'qtd' => 0];

$categorias_cap = ['combustivel' => 'Combustível', 'manutencao' => 'Manutenção', 'salarios' => 'Salários', 'aluguel' => 'Aluguel', 'energia' => 'Energia', 'agua' => 'Água', 'telefone' => 'Telefone', 'internet' => 'Internet', 'outros' => 'Outros'];
?>
<style>
.stats-card { border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1rem; color: #fff; }
.stats-card.cap-total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stats-card.cap-aberto { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stats-card.cap-vencido { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stats-card.cap-pago { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.badge-aberto { background-color: #0d6efd; }
.badge-vencido { background-color: #dc3545; }
.badge-pago { background-color: #198754; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="fas fa-calendar-check me-2"></i>Contas a Pagar</h2>
        <p class="text-muted mb-0">Agenda de pagamentos: cadastro, status e relatório por período</p>
    </div>
    <div class="d-flex gap-2">
        <a href="api/financeiro-despesas.php?export=csv<?php echo $filtro_status ? '&status=' . urlencode($filtro_status) : ''; ?><?php echo $filtro_data_inicio ? '&data_inicio=' . urlencode($filtro_data_inicio) : ''; ?><?php echo $filtro_data_fim ? '&data_fim=' . urlencode($filtro_data_fim) : ''; ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">
            <i class="fas fa-download me-1"></i>Exportar CSV
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaConta">
            <i class="fas fa-plus me-1"></i>Nova conta
        </button>
    </div>
</div>

<!-- Resumo do período (relatório) -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Resumo do período</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Período: <?php echo $filtro_data_inicio ? date('d/m/Y', strtotime($filtro_data_inicio)) : 'início'; ?>
            até <?php echo $filtro_data_fim ? date('d/m/Y', strtotime($filtro_data_fim)) : 'hoje'; ?>
            <?php if (!$filtro_data_inicio && !$filtro_data_fim) echo '(todos)'; ?>
        </p>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="stats-card cap-aberto">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Em aberto</small>
                            <h4 class="mb-0">R$ <?php echo number_format($totais_aberto['valor'], 2, ',', '.'); ?></h4>
                            <small><?php echo (int)$totais_aberto['qtd']; ?> conta(s)</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card cap-vencido">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Vencidas</small>
                            <h4 class="mb-0">R$ <?php echo number_format($totais_vencido['valor'], 2, ',', '.'); ?></h4>
                            <small><?php echo (int)$totais_vencido['qtd']; ?> conta(s)</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card cap-pago">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Pagas</small>
                            <h4 class="mb-0">R$ <?php echo number_format($totais_pago['valor'], 2, ',', '.'); ?></h4>
                            <small><?php echo (int)$totais_pago['qtd']; ?> conta(s)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5></div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="page" value="financeiro-despesas">
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="aberto" <?php echo $filtro_status === 'aberto' ? 'selected' : ''; ?>>Em aberto</option>
                    <option value="vencido" <?php echo $filtro_status === 'vencido' ? 'selected' : ''; ?>>Vencidas</option>
                    <option value="pago" <?php echo $filtro_status === 'pago' ? 'selected' : ''; ?>>Pagas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Categoria</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categorias_cap as $k => $v): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $filtro_categoria === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Venc. de</label>
                <input type="date" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Venc. até</label>
                <input type="date" name="data_fim" class="form-control" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search me-1"></i>Filtrar</button>
                <a href="?page=financeiro-despesas" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de contas</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Data pag.</th>
                        <th width="180">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($despesas)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhuma conta encontrada.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($despesas as $d): 
                        $st = $d['status_exibicao'];
                        $label = $st === 'aberto' ? 'Em aberto' : ($st === 'vencido' ? 'Vencida' : 'Paga');
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($d['descricao'] ?: $d['fornecedor']); ?></strong>
                            <?php if (!empty($d['observacoes'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($d['observacoes'], 0, 50)); ?><?php echo mb_strlen($d['observacoes']) > 50 ? '...' : ''; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?php echo $categorias_cap[$d['categoria']] ?? $d['categoria']; ?></span></td>
                        <td><strong>R$ <?php echo number_format($d['valor'], 2, ',', '.'); ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($d['vencimento'])); ?></td>
                        <td><span class="badge badge-<?php echo $st; ?>"><?php echo $label; ?></span></td>
                        <td><?php echo $d['data_pagamento'] ? date('d/m/Y', strtotime($d['data_pagamento'])) : '—'; ?></td>
                        <td>
                            <?php if ($st === 'aberto' || $st === 'vencido'): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="baixarPagamento(<?php echo (int)$d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['descricao'] ?: $d['fornecedor'])); ?>')" title="Baixar pagamento"><i class="fas fa-check"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarConta(<?php echo (int)$d['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirConta(<?php echo (int)$d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['descricao'] ?: $d['fornecedor'])); ?>')" title="Excluir"><i class="fas fa-trash"></i></button>
                            <?php elseif ($st === 'pago'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="estornarConta(<?php echo (int)$d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['descricao'] ?: $d['fornecedor'])); ?>')" title="Estornar (reabrir)"><i class="fas fa-undo"></i> Estornar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nova conta -->
<div class="modal fade" id="modalNovaConta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova conta a pagar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovaConta">
                    <div class="mb-3">
                        <label class="form-label">Descrição <span class="text-danger">*</span></label>
                        <input type="text" name="descricao" class="form-control" required placeholder="O que pagar">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vencimento <span class="text-danger">*</span></label>
                        <input type="date" name="vencimento" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select name="categoria" class="form-select">
                            <?php foreach ($categorias_cap as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarNova">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar (só em aberto) -->
<div class="modal fade" id="modalEditarConta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar conta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarConta">
                    <input type="hidden" name="id" id="editId">
                    <div class="mb-3">
                        <label class="form-label">Descrição <span class="text-danger">*</span></label>
                        <input type="text" name="descricao" id="editDescricao" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor (R$) <span class="text-danger">*</span></label>
                        <input type="number" name="valor" id="editValor" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vencimento <span class="text-danger">*</span></label>
                        <input type="date" name="vencimento" id="editVencimento" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select name="categoria" id="editCategoria" class="form-select">
                            <?php foreach ($categorias_cap as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="editObservacoes" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarEditar">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Baixar pagamento -->
<div class="modal fade" id="modalBaixar" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Baixar pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="baixarDescricao" class="mb-2"></p>
                <label class="form-label">Data do pagamento</label>
                <input type="date" id="baixarData" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="modal-footer">
                <input type="hidden" id="baixarId">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarBaixar">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var baseUrl = 'api/financeiro-despesas.php';

    function api(method, url, body) {
        var opt = { method: method, headers: { 'Content-Type': 'application/json' } };
        if (body) opt.body = JSON.stringify(body);
        return fetch(url, opt).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); });
    }

    document.getElementById('btnSalvarNova').addEventListener('click', function() {
        var form = document.getElementById('formNovaConta');
        var data = {
            descricao: form.descricao.value.trim(),
            valor: parseFloat(form.valor.value),
            vencimento: form.vencimento.value,
            categoria: form.categoria.value,
            observacoes: form.observacoes.value.trim() || null
        };
        if (!data.descricao || !data.vencimento || !data.valor) { alert('Preencha descrição, valor e vencimento.'); return; }
        api('POST', baseUrl, data).then(function(res) {
            if (res.ok && res.json.success) { window.location.reload(); return; }
            alert(res.json.error || 'Erro ao salvar');
        });
    });

    window.editarConta = function(id) {
        fetch(baseUrl + '?id=' + id).then(function(r) { return r.json(); }).then(function(res) {
            if (!res.success || !res.data) { alert('Conta não encontrada'); return; }
            var d = res.data;
            document.getElementById('editId').value = d.id;
            document.getElementById('editDescricao').value = d.descricao || d.fornecedor || '';
            document.getElementById('editValor').value = d.valor;
            document.getElementById('editVencimento').value = d.vencimento;
            document.getElementById('editCategoria').value = d.categoria || 'outros';
            document.getElementById('editObservacoes').value = d.observacoes || '';
            new bootstrap.Modal(document.getElementById('modalEditarConta')).show();
        });
    };

    document.getElementById('btnSalvarEditar').addEventListener('click', function() {
        var id = document.getElementById('editId').value;
        var data = {
            descricao: document.getElementById('editDescricao').value.trim(),
            valor: parseFloat(document.getElementById('editValor').value),
            vencimento: document.getElementById('editVencimento').value,
            categoria: document.getElementById('editCategoria').value,
            observacoes: document.getElementById('editObservacoes').value.trim() || null
        };
        api('PUT', baseUrl + '?id=' + id, data).then(function(res) {
            if (res.ok && res.json.success) { window.location.reload(); return; }
            alert(res.json.error || 'Erro ao atualizar');
        });
    });

    window.baixarPagamento = function(id, desc) {
        document.getElementById('baixarId').value = id;
        document.getElementById('baixarDescricao').textContent = desc;
        document.getElementById('baixarData').value = '<?php echo date("Y-m-d"); ?>';
        new bootstrap.Modal(document.getElementById('modalBaixar')).show();
    };

    document.getElementById('btnConfirmarBaixar').addEventListener('click', function() {
        var id = document.getElementById('baixarId').value;
        var data = { action: 'baixar', data_pagamento: document.getElementById('baixarData').value };
        api('PUT', baseUrl + '?id=' + id, data).then(function(res) {
            if (res.ok && res.json.success) { bootstrap.Modal.getInstance(document.getElementById('modalBaixar')).hide(); window.location.reload(); return; }
            alert(res.json.error || 'Erro ao baixar');
        });
    });

    window.estornarConta = function(id, desc) {
        if (!confirm('Estornar esta conta?\n\n' + desc + '\n\nEla voltará a aparecer como em aberto.')) return;
        api('PUT', baseUrl + '?id=' + id, { action: 'estornar' }).then(function(res) {
            if (res.ok && res.json.success) { window.location.reload(); return; }
            alert(res.json.error || 'Erro ao estornar');
        });
    };

    window.excluirConta = function(id, desc) {
        if (!confirm('Excluir esta conta?\n\n' + desc)) return;
        api('DELETE', baseUrl + '?id=' + id).then(function(res) {
            if (res.ok && res.json.success) { window.location.reload(); return; }
            alert(res.json.error || 'Erro ao excluir');
        });
    };
})();
</script>
