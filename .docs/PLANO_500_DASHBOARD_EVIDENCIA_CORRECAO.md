# Plano 500 /aluno/dashboard.php — Evidência e Correção Mínima

**Objetivo:** Fechar o 500 de `/aluno/dashboard.php` em produção com evidência e correção mínima. Não mexer em overlay/PWA até o legado voltar 200.

---

## 1. Provar status HTTP das URLs (sem navegador)

**Comandos para rodar via SSH** (docroot = `.../painel`):

```bash
curl -I -sS https://painel.cfcbomconselho.com.br/aluno/dashboard.php
curl -I -sS "https://painel.cfcbomconselho.com.br/aluno/dashboard.php?pwa_debug=1"
curl -I -sS https://painel.cfcbomconselho.com.br/dashboard
```

**Resultados já coletados (sessão anterior):**

| URL | Status | Observação |
|-----|--------|------------|
| `/aluno/dashboard.php` | **HTTP/2 500** | Corpo vazio em `curl -sS` |
| `/aluno/dashboard.php?pwa_debug=1` | **HTTP/2 500** | Idem |
| `/dashboard` | **HTTP/2 404** | Sem arquivo/rota em produção |

---

## 2. Erro real do servidor no horário do request

**Logs já verificados:**

- `storage/logs/php_errors.log` (painel): só logs de aplicação (DashboardController, login, etc.), **nenhum Fatal/Parse**.
- `../logs/php_errors.log` (public_html): só `[PROD] Sistema CFC inicializado/finalizado`.

**Erro reproduzido via CLI** (mesmo fluxo de includes do legado):

```
PHP Fatal error: Uncaught Exception: 🔐 Credenciais inválidas (usuário/senha). Verifique nas configurações da Hostinger.
  in /home/.../painel/includes/database.php:82
Stack trace:
#0 includes/database.php(24): Database->connect()
#1 includes/database.php(29): Database::getInstance()
#2 includes/database.php(824): db()
#3 includes/auth.php(15): db()
#4 includes/auth.php(1312): Auth->__construct()
#5 aluno/dashboard.php(9): require_once('.../includes/auth.php')
```

**Detalhe do PDO (via CLI):**  
`Access denied for user 'u502697186_cfcbomconselho'@'2a02:4780:13::1f' (using password: YES)`.

**Conclusão:** O 500 em `/aluno/dashboard.php` é exceção não tratada em `includes/database.php:82` (falha de conexão MySQL). Em produção, `display_errors=0` → corpo vazio; o mesmo tipo de exceção gera 500.

---

## 3. Prova: aluno cai no LEGADO; app vs legado

**Fluxos no código:**

| Aspecto | **App** (`/dashboard` etc.) | **Legado** (`/aluno/dashboard.php`) |
|---------|-----------------------------|--------------------------------------|
| Entrada | `public_html/index.php` → Router → controllers | Arquivo direto `aluno/dashboard.php` |
| Bootstrap | `App\Config\Env::load()` + `app/Bootstrap.php` + `App\Core\Router` | `require includes/config.php` → `includes/database.php` → `includes/auth.php` |
| DB | `App\Config\Database` + variáveis de **.env** | `includes/database.php` usa constantes **DB_*** de `includes/config.php` |
| Fonte credenciais | `.env` (via `Env::load()`) | `includes/config.php` (define hardcoded) |

**Prova no código:**

- **Legado**  
  - `aluno/dashboard.php` linhas 7–10:  
    `require_once __DIR__ . '/../includes/config.php';`  
    `require_once __DIR__ . '/../includes/database.php';`  
    `require_once __DIR__ . '/../includes/auth.php';`  
  - `includes/auth.php` linha 15: `db()` → `includes/database.php` linha 824: `Database::getInstance()`.

- **App**  
  - `public_html/index.php`: `Env::load()`, `Bootstrap.php`, `Router`.  
  - Rota `/dashboard` é tratada pelo Router (app); em produção não há recurso físico `painel/dashboard` → **404** para `/dashboard`.

Ou seja: **aluno em `/aluno/dashboard.php` usa sempre o fluxo legado** (config + database + auth em `includes/`).

---

## 4. Fonte de credenciais: App vs Legado

**Legado (`includes/config.php`):**

- **Arquivo:** `includes/config.php`
- **Fonte:** constantes definidas no próprio arquivo (hardcoded), **sem leitura de .env**.
- Trecho (linhas 12–15):

```php
define('DB_HOST', 'auth-db803.hstgr.io');
define('DB_NAME', 'u502697186_cfcbomconselho');
define('DB_USER', 'u502697186_cfcbomconselho');
define('DB_PASS', '…'); // valor fixo no arquivo
```

- Ambiente: `detectEnvironment()` usa `$_SERVER['HTTP_HOST']`.  
  - Web em `painel.cfcbomconselho.com.br` → `production`.  
  - CLI sem `HTTP_HOST` → `'localhost'` → `local`; aí pode rodar `config_local.php` se existir (linhas 309–315).

**App (`public_html/index.php` → Router):**

- **Arquivo:** `app/Config/Env.php` → `.env` em `dirname(__DIR__, 2)` (raiz do projeto).
- **Fonte:** `$_ENV['DB_HOST']`, `$_ENV['DB_USER']`, etc., preenchidos por `Env::load()` a partir do `.env`.
- **App não usa** `includes/config.php` para DB.

**Resumo (sem senha):**

| Fluxo    | DB_HOST           | DB_NAME                 | DB_USER (mascarado)     | Fonte                          |
|----------|-------------------|--------------------------|--------------------------|--------------------------------|
| **Legado** | auth-db803.hstgr.io | u502697186_cfcbomconselho | u502697186_cfc***        | `includes/config.php` (constantes) |
| **App**   | do .env            | do .env                  | do .env                  | `.env` via `Env::load()`       |

**Fallback ambiente:**  
Em `config.php`, se `$environment === 'local'` é feito `require_once __DIR__ . '/../config_local.php'`, que pode redefinir constantes. Em produção (web) isso não é carregado.

---

## 5. Inventário e script de diagnóstico DB

**Inventário:**

- `tools/test_db_connection.php` — usa **App** (Env + Database), não legado.
- `tools/debug_database.php` — idem; restrito a local por `HTTP_HOST`.
- `public_html/tools/diagnostico_erro_dashboard.php` — foca erro de dashboard **instrutor** e controller, não conexão DB legado.
- `public_html/tools/diagnostico_dashboard.php` — instancia `DashboardController` do app, não testa DB do legado.

**Nenhum script existente** usa exatamente o mesmo carregamento do legado (`includes/config.php` + `includes/database.php`).

Foi criado **script mínimo removível** que usa o mesmo include do legado e testa apenas conexão (leitura), restrito a CLI ou token:

- **Arquivo:** `tools/diagnostico_db_legado.php`
- **Uso:** `php tools/diagnostico_db_legado.php` ou, se permitir web: `?token=TOKEN_SECRETO`.
- **Restrição:** só executa se `php_sapi_name() === 'cli'` OU `$_GET['token'] === '...'` (token que você definir/remover depois).

---

## 6. Causa única e ponto exato

**Causa escolhida:** **B — Config divergente / origem do host**

- O legado usa **só** `includes/config.php` (constantes), nunca `.env`.
- O app usa **só** `.env` via `Env::load()`.  
Logo, em produção, **qualquer diferença entre o que está em `includes/config.php` e o que está em `.env`** (host, usuário, senha, regras de “Remote MySQL”) leva a: app OK e legado 500, ou o contrário.

Além disso, o MySQL negou o acesso para `'...@'2a02:4780:13::1f'` (IPv6 do servidor). Isso indica:

- **Causa A** também está presente: o host/origem (`2a02:4780:13::1f` ou o hostname usado pelo PHP na conexão) pode não estar permitido no “Remote MySQL” da Hostinger.

**Ponto exato no código:**

- **Arquivo:** `includes/database.php`  
- **Linha:** 82  
- **Trecho:** `throw new Exception('🔐 Credenciais inválidas …');` dentro do `catch (PDOException $e)` quando `$e->getCode() == 1045`.  
- O PDO falha ao conectar com as constantes `DB_HOST`, `DB_USER`, `DB_PASS` carregadas por `includes/config.php` (e, em ambiente local, por `config_local.php` se existir).

**Resumo:**  
O 500 é **exceção não tratada** em `includes/database.php:82` por falha de conexão (credenciais/host). A origem da config é **sempre** `includes/config.php` (e opcionalmente `config_local.php` em local). A divergência em relação ao app é que o **legado não usa .env**.

---

## 7. Correção mínima sugerida

**Objetivo:** Fazer o legado usar as mesmas credenciais que o app quando houver `.env`, sem refatorar o resto.

**Opção 1 — Fazer `includes/config.php` usar .env para DB (recomendada)**

- Antes de definir `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, carregar um `.env` **só se o arquivo existir** (por exemplo em `__DIR__ . '/../.env'`), ler linhas `DB_HOST=`, `DB_NAME=`, `DB_USER=`, `DB_PASS=` e usar esses valores como padrão, definindo constantes só se ainda não existirem.
- Assim, em produção, um único `.env` serve para app e legado; o `includes/config.php` pode manter os valores atuais como fallback quando não houver `.env`.

**Opção 2 — Ajuste somente em produção (host/permissão)**

- No painel Hostinger (Remote MySQL / allowed hosts), incluir o host/IP (ou IPv6) de onde o PHP faz a conexão (ex.: `2a02:4780:13::1f` ou o hostname do servidor).
- Ou, se a Hostinger exige “localhost” para o PHP no mesmo servidor, em produção usar `DB_HOST=localhost` (ou o valor indicado pela Hostinger) **apenas** no que o legado lê — ou seja, manter um único ponto de configuração (ex. `.env`) e fazer o legado usar esse ponto (como na Opção 1).

**Patch mínimo aplicado (Opção 1):**  
Alterar o início de `includes/config.php` para, antes dos `define('DB_*', ...)` atuais:

1. Detectar arquivo `.env` em `__DIR__ . '/../.env'`.
2. Se existir, parsear linhas `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (mesma lógica simples do `Env.php`: trim, ignorar comentários, `explode('=', $line, 2)`).
3. Definir constantes só se ainda não definidas, por exemplo:  
   `if (!defined('DB_HOST')) define('DB_HOST', $valor_lido ?? 'auth-db803.hstgr.io');`  
   e equivalentes para `DB_NAME`, `DB_USER`, `DB_PASS`, usando os valores atuais de `config.php` como fallback.

**Status:** Patch aplicado em `includes/config.php`. Assim, em produção, colocar em `.env` os mesmos valores que a Hostinger aceita (incluindo o host correto) garante que app e legado usem a mesma config e reduz o 500 a “só” ajuste de permissão de host (Causa A) se ainda falhar.

---

## 8. Reteste

Após aplicar a correção:

1. Via SSH:  
   `curl -I -sS https://painel.cfcbomconselho.com.br/aluno/dashboard.php`  
   Objetivo: **200** (ou 302 para login se sessão inválida).
2. No navegador: acessar `/aluno/dashboard.php` com sessão de aluno; a página deve carregar sem 500.

---

## Anexo — Comandos úteis para o Charles (SSH)

```bash
# Status das URLs
curl -I -sS https://painel.cfcbomconselho.com.br/aluno/dashboard.php
curl -I -sS "https://painel.cfcbomconselho.com.br/aluno/dashboard.php?pwa_debug=1"
curl -I -sS https://painel.cfcbomconselho.com.br/dashboard

# Diagnóstico DB legado (usa mesmo include do legado)
php tools/diagnostico_db_legado.php

# Ver últimas linhas do log do painel
tail -n 100 storage/logs/php_errors.log

# Ver se existe .env e quais chaves DB_ tem (sem mostrar valor)
grep -E '^DB_' .env 2>/dev/null | sed 's/=.*/=***/' || echo "Arquivo .env não encontrado ou sem DB_"
```

Quando você tiver o trecho do `error_log` (ou do log que a Hostinger usar para PHP/LiteSpeed) no horário exato de um request a `/aluno/dashboard.php`, pode colar aqui e fechamos se a causa é 100% A, B ou ambas.
