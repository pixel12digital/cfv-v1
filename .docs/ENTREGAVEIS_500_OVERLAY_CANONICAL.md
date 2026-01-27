# Entregáveis — 500 em /aluno/dashboard.php, overlay e canonical do aluno

**Contexto:** Em produção (painel.cfcbomconselho.com.br), `/dashboard` abre normal para aluno; `/aluno/dashboard.php` retorna HTTP 500. Overlay/sino/CTA estão em `aluno/dashboard.php` com `$showPwaInstallOverlay`. Objetivo: restaurar 200 em `/aluno/dashboard.php` e confirmar canonical = `/aluno/dashboard.php`.

---

## 1) Erro fatal do 500 (mensagem + arquivo + linha)

### Situação

Não é possível ver o log do servidor daqui. É necessário usar o ambiente de produção (Hostinger/Apache/PHP) no momento do request.

### Onde pegar o erro real

- **Hostinger:** Painel → Arquivos → `logs/` ou `error_log` na pasta do domínio/subdomínio; ou “Erros” / “Logs” no painel.
- **Apache:** `error_log` do VirtualHost (caminho em `ErrorLog` da config).
- **PHP-FPM:** `php-fpm.log` ou `www-error.log` (depende do host).

Reproduza o 500 acessando `https://painel.cfcbomconselho.com.br/aluno/dashboard.php` e anote o horário; em seguida abra o log e localize a entrada na mesma hora. A mensagem típica é algo como:

```
PHP Fatal error: ... in /caminho/arquivo.php on line N
```

ou:

```
PHP Parse error: syntax error, unexpected '...' in /caminho/arquivo.php on line N
```

### O que já foi feito no código (sem ver o log)

- **`includes/layout/mobile-first.php`:** uso de `$_GET['pwa_debug']` foi protegido com `isset($_GET['pwa_debug']) && $_GET['pwa_debug'] !== ''` nos dois pontos do bloco de debug (comentário `PWA_OVERLAY_ACTIVE=1` e injeção de `window.__PWA_DEBUG`), para evitar “undefined array key” em PHP 8+ e possíveis 500.
- **`php -l`** em `mobile-first.php` e `aluno/dashboard.php`: **nenhum erro de sintaxe** (veja seção 2).

### Preenchimento (você)

| Campo | Conteúdo |
|------|----------|
| **Erro fatal completo** | (colar a linha do log no momento do request) |
| **Arquivo** | |
| **Linha** | |
| **Correção aplicada** | Patch isset em mobile-first.php já em uso; se o log apontar outro arquivo/linha, descrever a correção extra. |

---

## 2) Resultado do php -l (mobile-first.php e aluno/dashboard.php)

Executado em:

- `includes/layout/mobile-first.php`
- `aluno/dashboard.php`

**Resultado:**

```
No syntax errors detected in includes/layout/mobile-first.php
No syntax errors detected in aluno/dashboard.php
```

Conclusão: **não há Parse error** nesses dois arquivos no repositório atual. Um 500 em produção pode vir de:

- outro arquivo da cadeia (config, database, auth, SistemaNotificacoes);
- versão/opções de PHP diferentes;
- caminhos/permissões em produção;
- ou código em produção ainda sem o patch do `isset($_GET['pwa_debug'])`. Garanta que o `mobile-first.php` com esse patch está no ambiente que serve `/aluno/dashboard.php`.

---

## 3) View Source de /aluno/dashboard.php (após o 500 estar resolvido)

Depois que GET `/aluno/dashboard.php` responder **200**, confira no **View Source** da página em produção se aparecem estes 6 itens:

| # | Item | Como conferir |
|--|------|----------------|
| 1 | `window.__PWA_INSTALL_URL` | Inline `<script>window.__PWA_INSTALL_URL = ...` antes de `pwa-install-overlay.js` |
| 2 | `<div id="pwa-install-overlay" ...>` sem `pwa-overlay-hidden` | Overlay visível por padrão no HTML |
| 3 | `id="pwa-install-header-cta"` | Link “Instalar app” no header |
| 4 | `id="pwa-install-bell"` e `id="pwa-install-bell-badge"` | Sino (dropdown) com badge |
| 5 | `<link .../assets/css/pwa-install-overlay.css>` | CSS do overlay no `<head>` |
| 6 | `<script .../assets/js/pwa-install-overlay.js>` **antes** de `mobile-first.js` | Ordem dos scripts |

Se **qualquer um** faltar: não é bug de lógica do JS, e sim layout/condicional não rodando ou arquivo em produção diferente (deploy, cache, outro layout). Nesse caso, conferir se a página está mesmo usando `includes/layout/mobile-first.php` e se `$showPwaInstallOverlay` foi definido em `aluno/dashboard.php` antes do include.

**Deliverable Passo 2:** Print ou trecho do View Source com esses 6 itens, ou lista do que ainda falta.

---

## 4) Canonical do aluno: decisão e correção mínima

### Decisão

**Aluno deve ficar em `/aluno/dashboard.php`** (canonical legado).  
Overlay/sino/CTA já estão nessa página; não é necessário portar overlay para a rota `/dashboard` do app.

### Onde estava a ambiguidade

- **AuthController::redirectToUserDashboard:** já envia aluno para `base_url('/aluno/dashboard.php')`.
- **DashboardController (rota `/dashboard`):** quando `current_role === ROLE_ALUNO`, antes chamava `dashboardAluno($userId)` e renderizava a view do app, então o aluno **ficava** em `/dashboard` (sem overlay).
- **Layout mobile-first:** quando o include vem de `aluno/dashboard.php`, `$homeUrl` já é `/aluno/dashboard.php`; logo e “Início” já apontam para o canonical nesse caso.

### Correção mínima aplicada

Em **`app/Controllers/DashboardController.php`**:

- **Antes:**  
  `if ($currentRole === Constants::ROLE_ALUNO && $userId) { return $this->dashboardAluno($userId); }`
- **Depois:**  
  em vez de renderizar o dashboard do app, o controller **redireciona** o aluno para o dashboard legado:

```php
if ($currentRole === Constants::ROLE_ALUNO && $userId) {
    $basePath = function_exists('base_url') ? rtrim(base_url(), '/') : (defined('BASE_PATH') ? BASE_PATH : '');
    header('Location: ' . $basePath . '/aluno/dashboard.php');
    exit;
}
```

Assim, ao acessar `/dashboard` logado como aluno, o usuário passa a ser mandado para `/aluno/dashboard.php` e o overlay/sino/CTA passam a valer.

### Resumo do fluxo (canonical = /aluno/dashboard.php)

| Ação | Destino |
|------|---------|
| Login (AuthController) | `/aluno/dashboard.php` |
| Acesso a `/dashboard` com role aluno | Redirect para `/aluno/dashboard.php` |
| Logo / “Início” no mobile-first (quando incluído por aluno/dashboard.php) | `$homeUrl` = `/aluno/dashboard.php` |

Não foi necessária alteração no mobile-first para “Home”/logo, pois nessa página o próprio `aluno/dashboard.php` já define `$homeUrl = '/aluno/dashboard.php'`.

---

## 5) Checklist pós-correção (para você validar)

- [ ] GET `https://painel.cfcbomconselho.com.br/aluno/dashboard.php` → **200** (sem 500).
- [ ] GET `https://painel.cfcbomconselho.com.br/dashboard` logado como aluno → **redirect** para `/aluno/dashboard.php`.
- [ ] View Source de `/aluno/dashboard.php` contém os 6 itens da seção 3.
- [ ] Em “não instalado”: sino + CTA aparecem; overlay respeita throttle 1x/dia.
- [ ] Em “instalado” (standalone): overlay/sino/CTA não aparecem.

---

*Documento para fechar causa do 500, validar overlay no HTML e garantir canonical do aluno em /aluno/dashboard.php.*
