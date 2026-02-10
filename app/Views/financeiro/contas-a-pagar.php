<?php
$apiUrl = $apiUrl ?? base_path('admin/api/financeiro-despesas.php');
$categorias = [
    'combustivel' => 'Combustível', 'manutencao' => 'Manutenção', 'salarios' => 'Salários',
    'aluguel' => 'Aluguel', 'energia' => 'Energia', 'agua' => 'Água', 'telefone' => 'Telefone',
    'internet' => 'Internet', 'outros' => 'Outros'
];
$filtroStatus = $_GET['status'] ?? '';
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';
?>
<div class="page-header">
    <div class="page-header-content" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--spacing-md);">
        <div>
            <h1>Contas a Pagar</h1>
            <p class="text-muted">Agenda de pagamentos: cadastro, status e relatório por período</p>
        </div>
        <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
            <a href="<?= htmlspecialchars($apiUrl) ?>?export=csv<?= $filtroStatus ? '&status=' . urlencode($filtroStatus) : '' ?><?= $filtroDataInicio ? '&data_inicio=' . urlencode($filtroDataInicio) : '' ?><?= $filtroDataFim ? '&data_fim=' . urlencode($filtroDataFim) : '' ?>" class="btn btn-outline" target="_blank" rel="noopener">
                Exportar CSV
            </a>
            <button type="button" class="btn btn-primary" id="btnNovaConta">
                Nova conta
            </button>
        </div>
    </div>
</div>

<!-- Resumo -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
    <div class="card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: #fff; border: none;">
        <div class="card-body">
            <div style="font-size: var(--font-size-sm); opacity: .9;">Em aberto</div>
            <div id="total-aberto-valor" style="font-size: 1.5rem; font-weight: 700;">—</div>
            <div id="total-aberto-qtd" style="font-size: var(--font-size-sm); opacity: .9;">0 conta(s)</div>
        </div>
    </div>
    <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: #fff; border: none;">
        <div class="card-body">
            <div style="font-size: var(--font-size-sm); opacity: .9;">Vencidas</div>
            <div id="total-vencido-valor" style="font-size: 1.5rem; font-weight: 700;">—</div>
            <div id="total-vencido-qtd" style="font-size: var(--font-size-sm); opacity: .9;">0 conta(s)</div>
        </div>
    </div>
    <div class="card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: #fff; border: none;">
        <div class="card-body">
            <div style="font-size: var(--font-size-sm); opacity: .9;">Pagas</div>
            <div id="total-pago-valor" style="font-size: 1.5rem; font-weight: 700;">—</div>
            <div id="total-pago-qtd" style="font-size: var(--font-size-sm); opacity: .9;">0 conta(s)</div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <form method="get" action="<?= base_url('financeiro/contas-a-pagar') ?>" id="formFiltros" style="display: flex; flex-wrap: wrap; gap: var(--spacing-md); align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-input" style="min-width: 140px;">
                    <option value="">Todos</option>
                    <option value="aberto" <?= $filtroStatus === 'aberto' ? 'selected' : '' ?>>Em aberto</option>
                    <option value="vencido" <?= $filtroStatus === 'vencido' ? 'selected' : '' ?>>Vencidas</option>
                    <option value="pago" <?= $filtroStatus === 'pago' ? 'selected' : '' ?>>Pagas</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Categoria</label>
                <select name="categoria" class="form-input" style="min-width: 140px;">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $k => $v): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= $filtroCategoria === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Venc. de</label>
                <input type="date" name="data_inicio" class="form-input" value="<?= htmlspecialchars($filtroDataInicio) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Venc. até</label>
                <input type="date" name="data_fim" class="form-input" value="<?= htmlspecialchars($filtroDataFim) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="<?= base_url('financeiro/contas-a-pagar') ?>" class="btn btn-outline">Limpar</a>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card" id="card-lista">
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Data pag.</th>
                        <th style="width: 180px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-lista">
                    <tr><td colspan="7" class="text-center text-muted" style="padding: var(--spacing-lg);">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nova conta -->
<dialog id="modalNova" style="border-radius: var(--radius-md); border: 1px solid var(--color-border, #e2e8f0); max-width: 480px; padding: 0;">
    <div style="padding: var(--spacing-lg); border-bottom: 1px solid var(--color-border, #e2e8f0);"><strong>Nova conta a pagar</strong></div>
    <form id="formNova" style="padding: var(--spacing-lg);">
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Descrição *</label>
            <input type="text" name="descricao" class="form-input" required placeholder="O que pagar">
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Valor (R$) *</label>
            <input type="number" name="valor" class="form-input" step="0.01" min="0.01" required>
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Vencimento *</label>
            <input type="date" name="vencimento" class="form-input" required>
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Categoria</label>
            <select name="categoria" class="form-input">
                <?php foreach ($categorias as $k => $v): ?>
                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Observações</label>
            <textarea name="observacoes" class="form-input" rows="2"></textarea>
        </div>
    </form>
    <div style="padding: var(--spacing-lg); border-top: 1px solid var(--color-border, #e2e8f0); display: flex; justify-content: flex-end; gap: var(--spacing-sm);">
        <button type="button" class="btn btn-outline" id="btnNovaCancelar">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnNovaSalvar">Salvar</button>
    </div>
</dialog>

<!-- Modal Editar -->
<dialog id="modalEditar" style="border-radius: var(--radius-md); border: 1px solid var(--color-border, #e2e8f0); max-width: 480px; padding: 0;">
    <div style="padding: var(--spacing-lg); border-bottom: 1px solid var(--color-border, #e2e8f0);"><strong>Editar conta</strong></div>
    <form id="formEditar" style="padding: var(--spacing-lg);">
        <input type="hidden" name="id" id="editId">
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Descrição *</label>
            <input type="text" name="descricao" id="editDescricao" class="form-input" required>
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Valor (R$) *</label>
            <input type="number" name="valor" id="editValor" class="form-input" step="0.01" min="0.01" required>
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Vencimento *</label>
            <input type="date" name="vencimento" id="editVencimento" class="form-input" required>
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Categoria</label>
            <select name="categoria" id="editCategoria" class="form-input">
                <?php foreach ($categorias as $k => $v): ?>
                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Observações</label>
            <textarea name="observacoes" id="editObservacoes" class="form-input" rows="2"></textarea>
        </div>
    </form>
    <div style="padding: var(--spacing-lg); border-top: 1px solid var(--color-border, #e2e8f0); display: flex; justify-content: flex-end; gap: var(--spacing-sm);">
        <button type="button" class="btn btn-outline" id="btnEditarCancelar">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnEditarSalvar">Salvar</button>
    </div>
</dialog>

<!-- Modal Baixar -->
<dialog id="modalBaixar" style="border-radius: var(--radius-md); border: 1px solid var(--color-border, #e2e8f0); max-width: 360px; padding: 0;">
    <div style="padding: var(--spacing-lg); border-bottom: 1px solid var(--color-border, #e2e8f0);"><strong>Baixar pagamento</strong></div>
    <div style="padding: var(--spacing-lg);">
        <p id="baixarDescricao" style="margin-bottom: var(--spacing-md);"></p>
        <div class="form-group">
            <label class="form-label">Data do pagamento</label>
            <input type="date" id="baixarData" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
    <div style="padding: var(--spacing-lg); border-top: 1px solid var(--color-border, #e2e8f0); display: flex; justify-content: flex-end; gap: var(--spacing-sm);">
        <input type="hidden" id="baixarId">
        <button type="button" class="btn btn-outline" id="btnBaixarCancelar">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnBaixarConfirmar">Confirmar</button>
    </div>
</dialog>

<script>
(function() {
    var apiUrl = <?= json_encode($apiUrl) ?>;
    var categorias = <?= json_encode($categorias) ?>;
    var params = {
        status: <?= json_encode($filtroStatus) ?>,
        categoria: <?= json_encode($filtroCategoria) ?>,
        data_inicio: <?= json_encode($filtroDataInicio) ?>,
        data_fim: <?= json_encode($filtroDataFim) ?>
    };

    function queryString() {
        var q = [];
        if (params.status) q.push('status=' + encodeURIComponent(params.status));
        if (params.categoria) q.push('categoria=' + encodeURIComponent(params.categoria));
        if (params.data_inicio) q.push('data_inicio=' + encodeURIComponent(params.data_inicio));
        if (params.data_fim) q.push('data_fim=' + encodeURIComponent(params.data_fim));
        return q.length ? '?' + q.join('&') : '';
    }

    function api(method, url, body) {
        var opt = { method: method, headers: { 'Content-Type': 'application/json' } };
        if (body) opt.body = JSON.stringify(body);
        return fetch(url, opt).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); });
    }

    function statusLabel(st) {
        return st === 'aberto' ? 'Em aberto' : (st === 'vencido' ? 'Vencida' : 'Paga');
    }

    function loadTotais() {
        var q = queryString();
        fetch(apiUrl + (q ? q + '&' : '?') + 'relatorio=totais')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success || !res.data) return;
                var d = res.data;
                var q = function(o, key) { return (o && (o[key] !== undefined && o[key] !== null)) ? o[key] : 0; };
                document.getElementById('total-aberto-valor').textContent = 'R$ ' + (d.aberto && d.aberto.valor != null ? Number(d.aberto.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0,00');
                document.getElementById('total-aberto-qtd').textContent = (q(d.aberto, 'qtd') || q(d.aberto, 'quantidade')) + ' conta(s)';
                document.getElementById('total-vencido-valor').textContent = 'R$ ' + (d.vencido && d.vencido.valor != null ? Number(d.vencido.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0,00');
                document.getElementById('total-vencido-qtd').textContent = (q(d.vencido, 'qtd') || q(d.vencido, 'quantidade')) + ' conta(s)';
                document.getElementById('total-pago-valor').textContent = 'R$ ' + (d.pago && d.pago.valor != null ? Number(d.pago.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0,00');
                document.getElementById('total-pago-qtd').textContent = (q(d.pago, 'qtd') || q(d.pago, 'quantidade')) + ' conta(s)';
            });
    }

    function loadList() {
        var tbody = document.getElementById('tbody-lista');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted" style="padding: var(--spacing-lg);">Carregando...</td></tr>';
        fetch(apiUrl + queryString())
            .then(function(r) { return r.json(); })
            .then(function(res) {
                var list = (res.success && res.data) ? res.data : [];
                if (list.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted" style="padding: var(--spacing-lg);">Nenhuma conta encontrada.</td></tr>';
                    return;
                }
                function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
                tbody.innerHTML = list.map(function(d) {
                    var st = d.status_exibicao || (d.status === 'pago' ? 'pago' : (d.vencimento && d.vencimento < new Date().toISOString().slice(0,10) ? 'vencido' : 'aberto'));
                    var desc = (d.descricao || d.fornecedor || '').trim();
                    var cat = categorias[d.categoria] || d.categoria || '';
                    var valor = Number(d.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    var venc = d.vencimento ? new Date(d.vencimento + 'T12:00:00').toLocaleDateString('pt-BR') : '—';
                    var dataPag = d.data_pagamento ? new Date(d.data_pagamento + 'T12:00:00').toLocaleDateString('pt-BR') : '—';
                    var badgeClass = st === 'aberto' ? 'var(--color-primary)' : (st === 'vencido' ? '#dc3545' : '#198754');
                    var acoes = '';
                    if (st === 'aberto' || st === 'vencido') {
                        acoes = '<button type="button" class="btn btn-sm btn-primary" data-action="baixar" data-id="' + d.id + '" data-desc="' + esc(desc) + '">Baixar</button> ' +
                            '<button type="button" class="btn btn-sm btn-outline" data-action="editar" data-id="' + d.id + '">Editar</button> ' +
                            '<button type="button" class="btn btn-sm btn-outline" data-action="excluir" data-id="' + d.id + '" data-desc="' + esc(desc) + '">Excluir</button>';
                    } else if (st === 'pago') {
                        acoes = '<button type="button" class="btn btn-sm btn-outline" data-action="estornar" data-id="' + d.id + '" data-desc="' + esc(desc) + '">Estornar</button>';
                    }
                    return '<tr><td><strong>' + esc(desc || '—') + '</strong></td><td>' + esc(cat) + '</td><td><strong>R$ ' + valor + '</strong></td><td>' + venc + '</td><td><span style="background:' + badgeClass + ';color:#fff;padding:2px 8px;border-radius:4px;font-size:0.85em;">' + statusLabel(st) + '</span></td><td>' + dataPag + '</td><td>' + acoes + '</td></tr>';
                }).join('');
            });
    }

    document.getElementById('btnNovaConta').addEventListener('click', function() { document.getElementById('formNova').reset(); document.getElementById('modalNova').showModal(); });
    document.getElementById('btnNovaCancelar').addEventListener('click', function() { document.getElementById('modalNova').close(); });
    document.getElementById('btnNovaSalvar').addEventListener('click', function() {
        var form = document.getElementById('formNova');
        var data = { descricao: form.descricao.value.trim(), valor: parseFloat(form.valor.value), vencimento: form.vencimento.value, categoria: form.categoria.value, observacoes: form.observacoes.value.trim() || null };
        if (!data.descricao || !data.vencimento || !data.valor) { alert('Preencha descrição, valor e vencimento.'); return; }
        api('POST', apiUrl, data).then(function(res) {
            if (res.ok && res.json.success) { document.getElementById('modalNova').close(); loadList(); loadTotais(); return; }
            alert(res.json.error || 'Erro ao salvar');
        });
    });

    document.getElementById('btnEditarCancelar').addEventListener('click', function() { document.getElementById('modalEditar').close(); });
    document.getElementById('btnEditarSalvar').addEventListener('click', function() {
        var id = document.getElementById('editId').value;
        var data = { descricao: document.getElementById('editDescricao').value.trim(), valor: parseFloat(document.getElementById('editValor').value), vencimento: document.getElementById('editVencimento').value, categoria: document.getElementById('editCategoria').value, observacoes: document.getElementById('editObservacoes').value.trim() || null };
        api('PUT', apiUrl + '?id=' + id, data).then(function(res) {
            if (res.ok && res.json.success) { document.getElementById('modalEditar').close(); loadList(); loadTotais(); return; }
            alert(res.json.error || 'Erro ao atualizar');
        });
    });

    document.getElementById('btnBaixarCancelar').addEventListener('click', function() { document.getElementById('modalBaixar').close(); });
    document.getElementById('btnBaixarConfirmar').addEventListener('click', function() {
        var id = document.getElementById('baixarId').value;
        var data = { action: 'baixar', data_pagamento: document.getElementById('baixarData').value };
        api('PUT', apiUrl + '?id=' + id, data).then(function(res) {
            if (res.ok && res.json.success) { document.getElementById('modalBaixar').close(); loadList(); loadTotais(); return; }
            alert(res.json.error || 'Erro ao baixar');
        });
    });

    document.getElementById('card-lista').addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-action');
        var id = btn.getAttribute('data-id');
        var desc = btn.getAttribute('data-desc') || '';
        if (action === 'baixar') { document.getElementById('baixarId').value = id; document.getElementById('baixarDescricao').textContent = desc; document.getElementById('baixarData').value = new Date().toISOString().slice(0,10); document.getElementById('modalBaixar').showModal(); }
        else if (action === 'editar') {
            fetch(apiUrl + '?id=' + id).then(function(r) { return r.json(); }).then(function(res) {
                if (!res.success || !res.data) { alert('Conta não encontrada'); return; }
                var d = res.data;
                document.getElementById('editId').value = d.id;
                document.getElementById('editDescricao').value = d.descricao || d.fornecedor || '';
                document.getElementById('editValor').value = d.valor;
                document.getElementById('editVencimento').value = (d.vencimento || '').slice(0,10);
                document.getElementById('editCategoria').value = d.categoria || 'outros';
                document.getElementById('editObservacoes').value = d.observacoes || '';
                document.getElementById('modalEditar').showModal();
            });
        }
        else if (action === 'excluir') { if (confirm('Excluir esta conta?\n\n' + desc)) api('DELETE', apiUrl + '?id=' + id).then(function(res) { if (res.ok && res.json.success) { loadList(); loadTotais(); } else alert(res.json.error || 'Erro'); }); }
        else if (action === 'estornar') { if (confirm('Estornar esta conta?\n\n' + desc + '\n\nEla voltará como em aberto.')) api('PUT', apiUrl + '?id=' + id, { action: 'estornar' }).then(function(res) { if (res.ok && res.json.success) { loadList(); loadTotais(); } else alert(res.json.error || 'Erro'); }); }
    });

    loadTotais();
    loadList();
})();
</script>
