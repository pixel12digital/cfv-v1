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

    function escAttr(s) {
        if (s == null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\r/g,'').replace(/\n/g,' ');
    }
    function escHtml(s) {
        if (s == null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escJsStr(s) {
        if (s == null || s === undefined) return '';
        return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r/g, '').replace(/\n/g, '\\n');
    }

    function api(method, url, body) {
        var opt = { method: method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
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
        fetch(apiUrl + '?all=1', { credentials: 'same-origin' })
            .then(function(r) {
                var ct = (r.headers.get('Content-Type') || '').toLowerCase();
                if (r.status === 401 || (ct.indexOf('html') >= 0 && r.redirected)) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger" style="padding: var(--spacing-lg);">Sessão expirada ou sem permissão. <a href="login">Fazer login</a>.</td></tr>';
                    return null;
                }
                return r.json();
            })
            .then(function(res) {
                if (res == null) return;
                var list = (res.success && res.data) ? res.data : [];
                if (list.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted" style="padding: var(--spacing-lg);">Nenhuma categoria. Clique em Nova categoria.</td></tr>';
                    return;
                }
                tbody.innerHTML = list.map(function(c) {
                    var ativo = c.ativo == 1 ? 'Sim' : 'Não';
                    var ativoClass = c.ativo == 1 ? '' : 'text-muted';
                    var nome = escHtml(c.nome);
                    var slug = escHtml(c.slug);
                    var nomeAttr = escAttr(c.nome);
                    var slugAttr = escAttr(c.slug);
                    var id = escAttr(c.id);
                    var ordem = escAttr(c.ordem);
                    var ativoVal = escAttr(c.ativo);
                    return '<tr><td>' + nome + '</td><td><code>' + slug + '</code></td><td>' + (c.ordem || 0) + '</td><td class="' + ativoClass + '">' + ativo + '</td><td class="text-nowrap">' +
                        '<button type="button" class="btn btn-sm btn-outline btn-icon" title="Editar" data-action="editar" data-id="' + id + '" data-nome="' + nomeAttr + '" data-slug="' + slugAttr + '" data-ordem="' + ordem + '" data-ativo="' + ativoVal + '"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button> ' +
                        '<button type="button" class="btn btn-sm btn-outline btn-icon" title="Excluir" data-action="excluir" data-id="' + id + '" data-nome="' + nomeAttr + '"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>' +
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
                            if (!confirm("Excluir a categoria \"" + escJsStr(nome) + "\"?\n\nSó é possível se não houver contas a pagar usando ela.")) return;
                            api('DELETE', apiUrl + '?id=' + id).then(function(res) {
                                if (res.ok && res.json.success) loadCategorias();
                                else alert(res.json.error || 'Erro ao excluir');
                            });
                        }
                    });
                });
            })
            .catch(function(err) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger" style="padding: var(--spacing-lg);">Erro ao carregar. Se persistir, faça login novamente ou verifique o console.</td></tr>';
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
