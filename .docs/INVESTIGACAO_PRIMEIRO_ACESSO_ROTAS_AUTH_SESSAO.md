# Investigação: primeiro acesso (rotas + auth + sessão)

**Data:** 2025-01-27  
**Sintoma:** Após "Definir senha e continuar", volta para /login; login manual com email+senha não autentica.

---

## 1) Mapa de rotas e handlers

| Rota | Método | Controller::action / arquivo | Middlewares | Resultado esperado |
|------|--------|------------------------------|-------------|--------------------|
| `/` | GET | AuthController::showLogin | — | Tela de login (app) |
| `/login` | GET | AuthController::showLogin | — | Tela de login (app) |
| `/login` | POST | AuthController::login | — | Valida credenciais → redirectToUserDashboard ou /login com erro |
| `/logout` | GET | AuthController::logout | — | Limpa sessão, redirect /login |
| `/forgot-password` | GET | AuthController::showForgotPassword | — | Form "Esqueci minha senha" |
| `/forgot-password` | POST | AuthController::forgotPassword | — | Envia email / mensagem |
| `/start` | GET | StartController::show | — | Valida token → render auth/define-password **na mesma resposta** (sem redirect) ou fallback |
| `/define-password` | GET | AuthController::showDefinePassword | — | Só se onboarding_user_id + force_password_change → form definir senha |
| `/define-password` | POST | AuthController::definePassword | — | Atualiza senha, login, `$_SESSION['first_access']=1`, redirectToUserDashboard |
| `/install` | GET | InstallController::show | — | Landing instalar app (CTA “Abrir portal” ou “Fazer login” conforme sessão) |
| `/dashboard` | GET | DashboardController::index | AuthMiddleware | Dashboard genérico (admin/secretaria) |
| `/aluno/dashboard.php` | GET | **Arquivo direto** `aluno/dashboard.php` | — | Guard: `isLoggedIn()` + `getCurrentUser()['tipo']==='aluno'` → conteúdo dashboard ou redirect para `login.php` |
| `/instrutor/dashboard.php` | GET | **Arquivo direto** (legado) | — | Guard legado |
| `/admin/index.php` | GET | **Arquivo direto** (legado) | — | Guard legado |

**redirectToUserDashboard()** (AuthController) usa:
- `aluno` → `base_url('/aluno/dashboard.php')`
- `instrutor` → `base_url('/instrutor/dashboard.php')`
- `admin` / `secretaria` → `base_url('/admin/index.php')`
- default → `base_url('/dashboard')`

**Rotas/entrypoints que competem:**
- **App:** Requisições que passam pelo front controller (root `index.php` ou `public_html/index.php`) e pelo `app/routes/web.php`. Em produção com DocumentRoot na raiz: tudo que não for arquivo físico cai em `index.php`; se painel, executa `public_html/index.php` → Bootstrap → Router → handlers do app.
- **Legado:** URLs que apontam para arquivos físicos (`/aluno/dashboard.php`, `/aluno/login.php`, etc.) são servidos **diretamente** pelo Apache (regra “se arquivo existe, não reescreve” no .htaccess).
- **public_html/login.php legado:** Não existe como rota nomeada no app. O app expõe `/login` via router. Existe **aluno/login.php** (arquivo físico) para login do aluno no legado. O login do **app** é `POST /login` → AuthController::login. Ou seja: há **dois** fluxos de login — app (email+senha no AuthController) e legado (aluno/login.php + includes/auth.php).

---

## 2) Cadeia de requests/redirects (prova)

Fluxo esperado para um teste completo:

| # | Request | Status esperado | Observação |
|---|---------|-----------------|------------|
| 1 | GET `/start?token=...` | 200 | Render da view `auth/define-password` (mesma de GET /define-password). Cookie **CFC_SESSION** deve ser emitido se sessão for iniciada neste request. |
| 2 | POST `/define-password` (body: csrf_token, new_password, new_password_confirm) | 302 | Headers: `Set-Cookie: CFC_SESSION=...`, `Location: <base>/aluno/dashboard.php` (para usuario tipo aluno). |
| 3 | GET `<base>/aluno/dashboard.php` (redirect do passo 2) | 200 | Dashboard do aluno; cookie CFC_SESSION deve ir no request. Se sessão não for vista como logada, responde 302 para `login.php` (legado). |

**Se cair no login:** a rota exata é a que o **Location** aponta:
- `Location: .../login` ou `.../public_html/index.php` com path /login → router do app (AuthController::showLogin).
- `Location: .../aluno/login.php` → legado (aluno/login.php).

Para fechar: capturar no Network (DevTools) o **Location** do 302 do POST /define-password e o **destino** do GET seguinte (URL + status).

---

## 3) Sessão/cookies: onde session_name() e session_start() são aplicados

### 3.1 Ordem e locais

| Ordem | Arquivo | Condição | session_name antes de session_start? |
|-------|---------|----------|--------------------------------------|
| 1 | `public_html/index.php` | `$isPainelSubdomain && session_status()===PHP_SESSION_NONE` | Sim: `session_name('CFC_SESSION'); session_start();` |
| 2 | `app/Bootstrap.php` | `session_status()===PHP_SESSION_NONE` | Sim: `session_name('CFC_SESSION'); session_start([...]);` |
| 3 | `includes/config.php` | `session_status()===PHP_SESSION_NONE && !headers_sent()` | Sim: `session_name(SESSION_NAME);` (SESSION_NAME='CFC_SESSION') `session_start();` |

**Risco:** Qualquer script que chame `session_start()` **antes** de definir o nome usa o padrão (PHPSESSID). No fluxo do app, o primeiro request (ex.: GET /start) passa por public_html/index.php (quando painel) ou por Bootstrap; em ambos os casos o nome é CFC_SESSION antes de abrir sessão.

O **aluno/dashboard.php** não chama session_start diretamente; faz `require_once '../includes/config.php'`, e config inicia a sessão com `session_name(SESSION_NAME)` + `session_start()`. Ou seja, no legado o nome também é CFC_SESSION.

Outros arquivos (admin/*.php, public_html/tools/*.php, etc.) chamam `session_start()` sem `session_name('CFC_SESSION')`. Eles só afetam o fluxo primeiro-acesso se forem o **entrypoint** do request. No fluxo clássico ( GET /start → POST /define-password → GET /aluno/dashboard.php ) os entrypoints são: app (index + Bootstrap) e depois includes/config.php no dashboard. Nenhum deles inicia sessão com nome errado nesse fluxo.

### 3.2 Cookie emitido e aceite

No POST /define-password é necessário garantir:
- `session_name()` === 'CFC_SESSION'
- `session_id()` preenchido depois do login
- headers enviados só após sessão escrita
- Envio de `Set-Cookie: CFC_SESSION=...` com path/domain/secure/samesite corretos

No início de `/aluno/dashboard.php` (após includes):
- `session_name()` deve ser CFC_SESSION (via config)
- `$_COOKIE['CFC_SESSION']` deve existir no request
- `$_SESSION['user_id']` e `$_SESSION['last_activity']` preenchidos se o mesmo cookie/sessão criada no app for usada.

---

## 4) Autenticação: por que email+senha não loga?

### 4.1 Banco e algoritmo

- **Tabela:** `usuarios`.
- **Coluna de hash:** `password` (varchar 255). Migrations e seeds usam `password`; não há coluna `senha` nas migrations do app.
- **definePassword:**  
  `$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);`  
  `$userModel->updatePassword($userId, $hashedPassword);`  
  → `UPDATE usuarios SET password = ? WHERE id = ?`.
- **AuthController::login (app):**  
  `$user = $this->authService->attempt($email, $password);`  
  → AuthService::attempt usa `User::findByEmail($email)` e `password_verify($password, $user['password'])`.  
  Ou seja: app lê e grava **sempre** na coluna `password` e usa **bcrypt**.

### 4.2 Legado (includes/auth.php)

- Login legado usa `$usuario['senha'] ?? $usuario['password']`.  
- Se a base tiver apenas `password`, está alinhado ao app. Se em algum ambiente existir só `senha`, o app nunca preenche esse campo ao definir senha.

### 4.3 Possíveis causas (a validar)

1. **Coluna errada:** definePassword grava em `password`; algum login lê outro campo (ex.: `senha`) → pouco provável no app; possível em legado se a tabela tiver só `senha`.
2. **Hash incompatível:** app usa bcrypt em ambos os fluxos; legado usa `password_verify` com `senha`/`password`; desde que o campo seja o mesmo, tende a estar ok.
3. **Email usado no login:** AuthService::attempt usa **email**; User::findByEmail. Se o usuário do primeiro acesso foi criado com um email e o usuário digita outro no login, não encontra.
4. **user_id/tipo/tenant:** redirectToUserDashboard e dashboard checam tipo e user_id; se o token estiver associado a um user_id diferente do que o usuário imagina (ex.: outro aluno/email), o “mesmo” login não seria o mesmo usuário.
5. **Transação/rollback:** updatePassword só faz `execute`; não há transação explícita no definePassword. Rollback silencioso só se algum outro código abrir transação e dar rollback depois — a conferir nos includes do legado ou em middlewares.

---

## 5) Instrumentação mínima (obrigatória)

Foram adicionados/ajustados logs nos pontos abaixo. Exemplos são “uma tentativa típica”.

### StartController::show (token ok)

- **Já existe:** log só quando token não é ok (logStartAttempt).
- **Recomendado adicionar:** quando `result === 'ok'`, uma linha com:  
  `token_result=ok`, `token_id`, `user_id`, `session_name()`, `session_id()`, `cookie_present=(isset($_COOKIE[session_name()])?'1':'0')`.

### AuthController::definePassword (antes de salvar e depois)

- **Antes de salvar:** userId alvo.
- **Depois de salvar:** resultado do update (linhas afetadas), session_name(), session_id(), redirect_target; opcional: headers_sent, isset($_COOKIE['CFC_SESSION']).

### AuthController::login (POST login)

- **Recomendado:** email mascarado (ex.: primeiros 2 + *** + domínio), user encontrado (id ou null), password_verify ok?, motivo de falha (user_not_found / wrong_password / inactive / wrong_tenant).

### aluno/dashboard.php (primeiras linhas)

- **Já existe:** session_id, has_user_id, has_last_activity, isLoggedIn, redirect_reason.
- **Recomendado:** incluir `session_name()` e, se houver redirect, o valor exato de `redirect_reason`.

---

## 6) Causa raiz principal e secundárias

- **Principal:** Sessão não compartilhada entre app e legado. O app (Bootstrap / public_html/index quando painel) usa `session_name('CFC_SESSION')` e sessão em disco/memória; o legado (aluno/dashboard.php) inicia sessão via includes/config.php também com CFC_SESSION. **Se** em algum ambiente ou em algum request o primeiro `session_start()` que rodar for em contexto que **não** chame `session_name('CFC_SESSION')` antes (por exemplo outro entrypoint ou ordem de requires), o cookie seria PHPSESSID e o dashboard esperaria CFC_SESSION → sessão “vazia” → redirect para login.  
  Já foi feita a correção para usar CFC_SESSION no app e no index do painel; se o sintoma persistir, o próximo passo é **confirmar em log** no POST /define-password e no primeiro line do dashboard: session_name(), session_id(), e se o cookie CFC_SESSION vem no request do dashboard.

- **Secundária (email+senha não loga):** Se depois de definir senha o login manual com “aquele” email e “aquela” senha falha, hipóteses úteis: (1) o usuário está usando outro email (ex.: o do aluno vs o do usuario); (2) a coluna de hash no ambiente real não é `password` ou não foi atualizada (por exemplo outro schema/outro servidor); (3) algum wrap de transação faz rollback após o update de senha. A instrumentação no definePassword (linhas afetadas) e no login (user encontrado, password_verify) permite fechar qual desses é.

---

## 7) Plano de correção mínima (sem refatorar)

1. **Garantir session_name antes de qualquer session_start no fluxo primeiro-acesso**  
   - Revisar todos os entrypoints possíveis para /start e /define-password (incl. se há outro index ou redirect que carregue Bootstrap/outro core antes).  
   - Garantir que, nesses entrypoints, a primeira chamada a session_start seja sempre precedida de session_name('CFC_SESSION').

2. **Confirmar escrita da senha e uso no login**  
   - No definePassword: logar “linhas afetadas” do UPDATE (ex.: `$stmt->rowCount()` ou equivalente).  
   - No AuthController::login (attempt): logar “user found id”, “password_verify ok”, e em caso de falha “reason”.  
   - Garantir que o login do app (AuthController::login) use o mesmo User::findByEmail + password_verify($password, $user['password']) e que a tabela realmente use a coluna `password`.

3. **Unificar critério de “logado” com o legado**  
   - O legado (includes/auth isLoggedIn) exige `$_SESSION['user_id']` e `$_SESSION['last_activity']`.  
   - AuthService::login já preenche isso. Manter e garantir que nenhum código apague ou sobrescreva sessão entre o redirect do definePassword e o primeiro line do aluno/dashboard.

4. **Logs de sessão no redirect e no dashboard**  
   - No final de definePassword, antes de redirect: log session_name(), session_id(), redirect_target, e (se útil) “cookie_header_sent”.  
   - No início de aluno/dashboard.php, após config/auth: log session_name(), session_id(), has_user_id, has_last_activity, isLoggedIn, redirect_reason.  
   - Com isso, fica mensurável se o cookie CFC_SESSION está sendo enviado e aceito no request do dashboard.

5. **Evitar “voltar mudo” para o login**  
   - Se o redirect for para login (app ou legado), passar um motivo na URL ou em flash (ex.: `?reason=session_invalid`) e exibir uma frase curta (“Sessão inválida ou expirada. Faça login novamente.”) para não parecer falha silenciosa.

---

**Arquivos instrumentados:**

- **`app/Controllers/StartController.php`** — Quando token ok: `[START] token_result=ok token_id=… user_id=… session_name=… session_id=… cookie_present=0|1`
- **`app/Controllers/AuthController.php`**  
  - definePassword (após salvar e login): `[definePassword] userId=… rows_affected=… userType=… session_name=… session_id=… headers_sent=… cookie_CFC_SESSION=… session_keys=… redirect_target=…`  
  - login (após attempt): se ok `[login] email_masked=… user_found_id=… password_verify=ok`; se falha `[login] email_masked=… user_found_id=null password_verify=fail failure_reason=user_not_found|wrong_password|inactive|credentials_invalid`
- **`app/Services/AuthService.php`** — `getLastAttemptFailureReason()` retorna `user_not_found`, `wrong_password`, `inactive` ou `credentials_invalid`
- **`app/Models/User.php`** — `updatePassword()` retorna `$stmt->rowCount()` (linhas afetadas)
- **`aluno/dashboard.php`** — `[aluno/dashboard] session_name=… session_id=… has_user_id=… has_last_activity=… isLoggedIn=…` e, ao redirecionar, `[aluno/dashboard] redirect_reason=…`

**Exemplo de log “quando quebra” (volta para login + email+senha não loga):**

```
[definePassword] userId=123 rows_affected=1 userType=aluno session_name=CFC_SESSION session_id=xyz123 headers_sent=0 cookie_CFC_SESSION=1 session_keys={"user_id":true,"last_activity":true,"user_type":true} redirect_target=https://painel.xxx.com/aluno/dashboard.php
[aluno/dashboard] session_name=CFC_SESSION session_id=none has_user_id=0 has_last_activity=0 isLoggedIn=0
[aluno/dashboard] redirect_reason=isLoggedIn_false
```

Se `session_id` no definePassword estiver preenchido e no dashboard for `none` ou vazio, o cookie não está sendo aceito no request do dashboard (path/domain/secure/samesite).  
Se no dashboard `session_id` for o mesmo mas `has_user_id=0`, a sessão foi iniciada de novo vazia (outro session_id no cookie ou storage).

**Exemplo quando “email+senha não loga” após definir senha:**

```
[login] email_masked=an***@email.com user_found_id=null password_verify=fail failure_reason=user_not_found
```
→ Usuário não existe com esse email; ou o login está buscando em outra base/tenant.

```
[login] email_masked=an***@email.com user_found_id=123 password_verify=fail failure_reason=wrong_password
```
→ Senha não confere com o hash em `usuarios.password` (coluna certa? mesmo registro que o definePassword atualizou?).
