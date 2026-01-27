# Auditoria: link abre direto na tela de login (Ana / primeiro acesso)

**Data:** 2025-01-27  
**Problema:** Ao testar com a aluna Ana, o link enviado abre diretamente a tela de login em vez da tela “Definir nova senha”.

---

## 1. Resumo do que foi implementado

| Componente | Arquivo(s) | Função |
|------------|------------|--------|
| Token primeiro acesso | `FirstAccessToken`, `first_access_tokens` | Token one-time 48h, hash, usado só ao definir senha |
| Geração do link | `AlunosController::resolveInstallOrStartUrl()` | Se aluno tem `user_id` ou email → cria token e retorna `/start?token=...`; senão → `/install` |
| Onde o link é usado | `show($id)`, `showMatricula($id)` | Passam `installUrl`, `waMessage` etc. para a view |
| Rota /start | `StartController::show()` | Valida token, seta sessão onboarding, redireciona para `/define-password` |
| Definir senha | `AuthController::showDefinePassword`, `definePassword` | Só acessa se `$_SESSION['onboarding_user_id']` e `force_password_change`; ao salvar, marca token usado, faz login, redireciona `/install` |
| CTAs | `alunos/show.php`, `alunos/matricula_show.php` | “Enviar app no WhatsApp” e “Copiar link” usam `installUrl` e `waMessage` |

---

## 2. Fluxo esperado (passo a passo)

1. Admin está em **Editar Matrícula** da Ana (ou na aba Matrícula do aluno) e clica em **“Enviar app no WhatsApp”** ou **“Copiar link”**.
2. O sistema chama `resolveInstallOrStartUrl(student_id)`:
   - Carrega o aluno (Ana).
   - Se não tem `user_id` e tem email → `UserCreationService::createForStudent()` (cria usuário e vincula).
   - Gera token de primeiro acesso e monta `installUrl = base_url('start?token=' . $plainToken)`.
   - A mensagem do WhatsApp fica com esse link e texto do tipo: “Clique no link para ativar seu acesso e instalar o app”.
3. Ana abre o link (ex.: pelo celular, pelo WhatsApp).
4. **Esperado:**  
   - GET `/start?token=...` valida o token, seta `onboarding_user_id` e `force_password_change` na sessão, redireciona para `/define-password`.  
   - GET `/define-password` vê a sessão e mostra o formulário “Definir sua senha”.  
   - Ana preenche duas vezes, envia → POST `/define-password` → senha atualizada, login feito, redireciona para `/install`.

5. **Atual (problema):** a tela que aparece é a de **login**, não a de “Definir sua senha”.

---

## 3. Possíveis causas (diagnóstico)

### 3.1 Causa mais provável: sessão perdida entre `/start` e `/define-password`

**O que acontece hoje:**

- Em `Bootstrap.php` a sessão usa **`cookie_samesite => 'Strict'`**.
- O aluno abre o link **saindo do WhatsApp** (navegação “top-level” a partir de outro site/app).
- No **primeiro** request (GET `/start?token=...`):
  - Não há cookie de sessão ainda (primeira visita naquele browser/aba).
  - O backend valida o token, preenche `$_SESSION` e envia **redirect** para `/define-password` junto com **Set-Cookie**.
- No **segundo** request (GET `/define-password`, seguindo o redirect):
  - Em certos navegadores (sobretudo **navegador in-app do WhatsApp** ou de outros apps), o cookie de sessão pode **não ser aceito ou não ser reenviado** nesse segundo request.
  - `showDefinePassword()` não vê `onboarding_user_id` nem `force_password_change` → redireciona para **login**.

Resultado: para o usuário “abre direto na tela de login”.

### 3.2 Link ainda genérico (`/install`) para Ana

Se o link que a Ana recebe for **`/install`** e não **`/start?token=...`**:

- Ela abre `/install`, vê “Abrir app” e cai no **login**.
- Isso acontece se `resolveInstallOrStartUrl()` retornou `base_url('install')`, por exemplo quando:
  - Ana não tem `user_id` e **não tem email** no cadastro, ou
  - Ana não tem `user_id`, tem email, mas `UserCreationService::createForStudent()` **falhou** (ex.: “E-mail já está em uso”).

Nesse cenário o “problema” não é sessão, e sim o link ainda ser o genérico.

### 3.3 Token inválido / expirado / já usado

Se o link for `/start?token=...` mas o token estiver inválido, expirado ou já usado:

- `StartController` cai no fallback “Este link expirou ou já foi utilizado” com botão **“Ir para login”**.
- Se a Ana clica nesse botão, vai para a tela de login.  
  A impressão pode ser de que “abre direto no login” se ela não notar a mensagem de fallback.

### 3.4 Outras causas em sessão

- **Dominío/base da URL:** se em algum ambiente `base_url('define-password')` gerar outro host (ex.: `www` vs sem `www`), o cookie pode não ser enviado no redirect.
- **HTTPS:** com `cookie_secure => isset($_SERVER['HTTPS'])`, em HTTP o cookie não é enviado; improvável em produção, mas possível em testes.

---

## 4. Testes sugeridos (sem implementar nada ainda)

| # | Teste | Objetivo |
|---|--------|----------|
| 1 | Na **Editar Matrícula** da Ana, “Copiar link” e colar em bloco de notas. O início do link é `.../start?token=` ou `.../install`? | Saber se, para a Ana, o sistema está gerando link personalizado ou genérico. |
| 2 | Abrir esse mesmo link em **aba anônima** no Chrome (desktop). Deve aparecer “Definir sua senha” ou “Login”? | Ver se o fluxo sessão + redirect funciona em ambiente “limpo”. |
| 3 | Abrir o mesmo link **dentro do app WhatsApp** (toque no link na conversa). O que aparece: “Definir sua senha”, fallback “link expirou” ou login? | Reproduzir uso real e ver se o in-app browser perde sessão no redirect. |
| 4 | Na ficha da Ana (Alunos → Ana), ver se existe **“Acesso” / usuário vinculado** e se o **email** está preenchido. | Garantir que `resolveInstallOrStartUrl()` pode criar usuário e token (email e, se existir, user_id). |

---

## 5. Correção recomendada (evitar depender do cookie no redirect)

**Ideia:** não redirecionar de `/start` para `/define-password`. Mostrar o formulário **“Definir sua senha” na própria resposta de `/start`** quando o token for válido.

**Vantagens:**

- Um único request/response para “abrir o link e ver o formulário”.
- O cookie de sessão é definido nessa mesma resposta; no **POST** do formulário (action `/define-password`) o usuário já está no domínio do painel e o cookie costuma ser enviado normalmente.
- Reduz o efeito de navegadores in-app (WhatsApp etc.) que não tratam bem redirect + cookie.

**O que mudar (em termos de lógica, sem código aqui):**

- Em **StartController::show()**, quando o token for válido:
  - Manter: setar `$_SESSION['onboarding_user_id']`, `onboarding_token_id`, `force_password_change`.
  - Em vez de: `redirect(base_url('define-password'))`.
  - Fazer: carregar o usuário e chamar a mesma view usada em “definir senha” (ex.: `viewRaw('auth/define-password', ['user' => $user])`), **na mesma resposta**, sem redirect.
- O formulário em `auth/define-password` já submette para `POST /define-password`; não precisa mudar action.
- Rotas e `AuthController::definePassword` permanecem iguais; só o **primeiro acesso** à tela de “definir senha” deixa de depender do redirect.

**Arquivos envolvidos nessa correção:**

- `app/Controllers/StartController.php`: trocar o `redirect` por carregar usuário e exibir a view de definir senha.
- Possível pequeno ajuste em `app/Views/auth/define-password.php` se ela depender de algo que hoje só existe após o redirect (ex.: base_path) — manter action e helpers iguais.

**O que não mexer (por esta correção):**

- Regras de token (FirstAccessToken, quando marcar usado).
- Regras de sessão em `definePassword` (onboarding_user_id, etc.).
- Bootstrap/session em geral (podem ficar para uma etapa posterior, ex. SameSite Lax só para rotas de onboarding, se ainda falhar em algum dispositivo).

---

## 6. Checklist pós-correção

- [ ] Em **Editar Matrícula** da Ana, o link copiado é `.../start?token=...`.
- [ ] Abrir esse link em aba anônima (Chrome) → aparece “Definir sua senha” (sem login).
- [ ] Abrir esse link pelo WhatsApp (in-app) → aparece “Definir sua senha” (sem login).
- [ ] Preencher senha duas vezes e enviar → redireciona para `/install` e aparece mensagem de sucesso.
- [ ] Reabrir o mesmo link → fallback “link expirou ou já foi utilizado” (e não tela de login sem explicação).
- [ ] Login normal (email/senha) continua funcionando.

---

**Fim da auditoria.** A correção sugerida na seção 5 elimina a dependência do cookie no redirect e é a que melhor trata o caso “abre direto na tela de login” quando o link correto é `/start?token=...` e o problema é perda de sessão no segundo request.
