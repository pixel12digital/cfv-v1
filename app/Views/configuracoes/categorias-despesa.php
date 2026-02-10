<?php
$apiUrl = $apiUrl ?? base_path('admin/api/financeiro-categorias.php');
$apiUrlSafe = htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8');
?><!-- categorias-despesa-view-v2 -->
<div class="page-header" id="categorias-despesa-page" data-api-url="<?= $apiUrlSafe ?>">
    <div class="page-header-content" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--spacing-md);">
        <div>
            <h1>Categorias de despesa</h1>
            <p class="text-muted">Categorias usadas em Contas a Pagar. Edite, desative ou adicione novas.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btnNovaCategoria">Nova categoria</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Slug</th>
                        <th>Ordem</th>
                        <th>Ativa</th>
                        <th style="width: 140px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="tbody-categorias">
                    <tr><td colspan="5" class="text-center text-muted" style="padding: var(--spacing-lg);">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<dialog id="modalCategoria" style="border-radius: var(--radius-md); border: 1px solid var(--color-border, #e2e8f0); max-width: 420px; padding: 0;">
    <div style="padding: var(--spacing-lg); border-bottom: 1px solid var(--color-border, #e2e8f0);"><strong id="modalCategoriaTitulo">Nova categoria</strong></div>
    <form id="formCategoria" style="padding: var(--spacing-lg);">
        <input type="hidden" name="id" id="catId">
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Nome *</label>
            <input type="text" name="nome" id="catNome" class="form-input" required placeholder="Ex: Combustível">
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Slug (opcional)</label>
            <input type="text" name="slug" id="catSlug" class="form-input" placeholder="Ex: combustivel (gerado automaticamente se vazio)">
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md);">
            <label class="form-label">Ordem</label>
            <input type="number" name="ordem" id="catOrdem" class="form-input" value="0" min="0">
        </div>
        <div class="form-group" style="margin-bottom: var(--spacing-md); display: none;" id="wrapCatAtivo">
            <label style="display: flex; align-items: center; gap: var(--spacing-sm);">
                <input type="checkbox" name="ativo" id="catAtivo" value="1" checked>
                <span>Ativa (aparece no dropdown de Contas a Pagar)</span>
            </label>
        </div>
    </form>
    <div style="padding: var(--spacing-lg); border-top: 1px solid var(--color-border, #e2e8f0); display: flex; justify-content: flex-end; gap: var(--spacing-sm);">
        <button type="button" class="btn btn-outline" id="btnCatCancelar">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnCatSalvar">Salvar</button>
    </div>
</dialog>

<script>
(function() {
    var el = document.getElementById('categorias-despesa-page');
    var apiUrl = (el && el.getAttribute('data-api-url')) || '';

    function api(method, url, body) {
        var opt = { method: method, headers: { 'Content-Type': 'application/json' } };
        if (body) opt.body = JSON.stringify(body);
        return fetch(url, opt).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, json: j }; }); });
    }

    function loadCategorias() {
        var tbody = document.getElementById('tbody-categorias');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted" style="padding: var(--spacing-lg);">Carregando...</td></tr>';
        if (!apiUrl) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger" style="padding: var(--spacing-lg);">URL da API não configurada.</td></tr>';
            return;
        }
        fetch(apiUrl + '?all=1')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                var list = (res.success && res.data) ? res.data : [];
                if (list.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted" style="padding: var(--spacing-lg);">Nenhuma categoria. Clique em Nova categoria.</td></tr>';
                    return;
                }
                tbody.innerHTML = list.map(function(c) {
                    var ativo = c.ativo == 1 ? 'Sim' : 'Não';
                    var ativoClass = c.ativo == 1 ? '' : 'text-muted';
                    return '<tr><td>' + (c.nome || '') + '</td><td><code>' + (c.slug || '') + '</code></td><td>' + (c.ordem || 0) + '</td><td class="' + ativoClass + '">' + ativo + '</td><td>' +
                        '<button type="button" class="btn btn-sm btn-outline" data-action="editar" data-id="' + c.id + '" data-nome="' + (c.nome || '').replace(/"/g, '&quot;') + '" data-slug="' + (c.slug || '').replace(/"/g, '&quot;') + '" data-ordem="' + (c.ordem || 0) + '" data-ativo="' + (c.ativo || 0) + '">Editar</button> ' +
                        '<button type="button" class="btn btn-sm btn-outline" data-action="excluir" data-id="' + c.id + '" data-nome="' + (c.nome || '').replace(/"/g, '&quot;') + '">Excluir</button>' +
                        '</td></tr>';
                }).join('');
                tbody.querySelectorAll('[data-action]').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var action = this.getAttribute('data-action');
                        var id = this.getAttribute('data-id');
                        var nome = this.getAttribute('data-nome') || '';
                        if (action === 'editar') {
                            document.getElementById('modalCategoriaTitulo').textContent = 'Editar categoria';
                            document.getElementById('wrapCatAtivo').style.display = 'block';
                            document.getElementById('catId').value = id;
                            document.getElementById('catNome').value = nome;
                            document.getElementById('catSlug').value = this.getAttribute('data-slug') || '';
                            document.getElementById('catOrdem').value = this.getAttribute('data-ordem') || '0';
                            document.getElementById('catAtivo').checked = this.getAttribute('data-ativo') == '1';
                            document.getElementById('modalCategoria').showModal();
                        } else if (action === 'excluir') {
                            if (!confirm('Excluir a categoria “‘ + nome + '”?\n\nSó é possível se não houver contas a pagar usando ela.')) return;
                            api('DELETE', apiUrl + '?id=' + id).then(function(res) {
                                if (res.ok && res.json.success) loadCategorias();
                                else alert(res.json.error || 'Erro ao excluir');
                            });
                        }
                    });
                });
            })
            .catch(function(err) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger" style="padding: var(--spacing-lg);">Erro ao carregar categorias. Verifique o console.</td></tr>';
                console.error('Categorias despesa:', err);
            });
    }

    document.getElementById('btnNovaCategoria').addEventListener('click', function() {
        document.getElementById('modalCategoriaTitulo').textContent = 'Nova categoria';
        document.getElementById('wrapCatAtivo').style.display = 'none';
        document.getElementById('formCategoria').reset();
        document.getElementById('catId').value = '';
        document.getElementById('catOrdem').value = '0';
        document.getElementById('modalCategoria').showModal();
    });

    document.getElementById('btnCatCancelar').addEventListener('click', function() { document.getElementById('modalCategoria').close(); });
    document.getElementById('btnCatSalvar').addEventListener('click', function() {
        var id = document.getElementById('catId').value;
        var payload = {
            nome: document.getElementById('catNome').value.trim(),
            slug: document.getElementById('catSlug').value.trim() || undefined,
            ordem: parseInt(document.getElementById('catOrdem').value, 10) || 0
        };
        if (!payload.nome) { alert('Preencha o nome.'); return; }
        if (id) {
            payload.id = parseInt(id, 10);
            payload.ativo = document.getElementById('catAtivo').checked ? 1 : 0;
            api('PUT', apiUrl, payload).then(function(res) {
                if (res.ok && res.json.success) { document.getElementById('modalCategoria').close(); loadCategorias(); }
                else alert(res.json.error || 'Erro ao atualizar');
            });
        } else {
            api('POST', apiUrl, payload).then(function(res) {
                if (res.ok && res.json.success) { document.getElementById('modalCategoria').close(); loadCategorias(); }
                else alert(res.json.error || 'Erro ao criar');
            });
        }
    });

    loadCategorias();
})();
</script>
