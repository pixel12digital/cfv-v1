# Diagnóstico: "Carregando..." em Categorias de despesa e Contas a pagar

**Objetivo:** ao abrir **Categorias de despesa**, conseguir **ver** as categorias já cadastradas, **editar**, **excluir** e **adicionar** novas — sem ficar travado em "Carregando..." e sem erros.

---

## Diagnóstico resumido

Dois eixos independentes podem causar o sintoma:

1. **SyntaxError no JS** (categorias-despesa) → o script quebra antes do `fetch`; a tela fica em "Carregando..." e nada mais roda.
2. **API retornando 401** (contas-a-pagar e categorias) → o front chama a API mas recebe "Não autenticado"; a lista não carrega.

---

## Correções já aplicadas no código

- **URL da API fora do script:** a view não imprime mais `<?= ... ?>` dentro do `<script>`. A URL fica em `data-api-url` e o JS lê com `getAttribute`, evitando SyntaxError por interpolação PHP.
- **Escape robusto no `.map()`:** funções `escAttr()` e `escHtml()` garantem que nome/slug (e id, ordem, ativo) nunca quebrem o HTML nem o JS, mesmo com aspas, acentos ou caracteres especiais.
- **Erro "missing ) after argument list" (linha ~378):** se alguma categoria tiver **aspas simples** no nome (ex.: "D'água", "O'Brien"), o texto era interpolado direto no `confirm()`, quebrando o JS. Foi adicionada `escJsStr()` e o `confirm('Excluir...')` passou a usar `escJsStr(nome)` para escapar `\` e `'` no nome.
- **Tratamento de 401/redirect:** se a API responder 401 ou HTML (redirect para login), a tela mostra "Sessão expirada ou sem permissão. Fazer login." em vez de travar.
- **Fetch com `credentials: 'same-origin'`** nas chamadas à API (contas-a-pagar e categorias).
- **Service Worker:** rotas `/configuracoes/`, `/financeiro/`, etc. não são cacheadas; `sw.js` não é cacheado para permitir atualização.
- **Comentário de versão** no HTML: `<!-- categorias-despesa-view-v2 -->` para confirmar no "Ver código-fonte" se a view nova está sendo servida.

---

## Se ainda falhar: checagens rápidas

| Problema | O que fazer |
|----------|-------------|
| **SyntaxError (ex.: "missing ) after argument list" na linha ~378)** | 1) **Causa comum:** nome de categoria com aspas simples (ex.: D'água) quebrava o `confirm()`. Corrigido com `escJsStr(nome)` no confirm. 2) Se o erro continuar: DevTools → **Sources** → documento da URL → linha indicada: ver o trecho exato. 3) View Source: procurar `categorias-despesa-view-v2` e `data-api-url`. Se aparecer `var apiUrl = <?=` → cache antigo. 4) Rodar `tools/clear-opcache.php?clear=1`, desregistrar o Service Worker (Application → Service Workers → Unregister), hard refresh (Ctrl+Shift+R). |
| **401 na API** | 1) Network → clicar na requisição que deu 401 → ver **Response**: JSON "Não autenticado" ou HTML (login). 2) **Headers**: ver se o cookie (ex.: `CFC_SESSION`) está sendo enviado. 3) Application → **Cookies**: ver o **Path** do cookie; deve ser `/` (ou path que inclua `/admin`). Se o cookie tiver Path=/painel e a API estiver em /admin, a API não recebe o cookie → 401. |
| **View v2 some às vezes** | Indício de cache em outra camada (proxy/CDN, Hostinger). Desativar cache para a rota ou purgar cache. |

---

## Resultado esperado

- **Categorias de despesa:** lista carregada; botões **Nova categoria**, **Editar** e **Excluir** funcionando; sem SyntaxError no console; sem 401 (ou mensagem clara de "Fazer login" se sessão expirou).
- **Contas a pagar:** lista e totais carregados; sem 401 (ou mensagem de sessão/permissão).
