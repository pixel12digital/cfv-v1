# Trace Pack — Primeiro acesso (uma tentativa real)

**Objetivo:** Fechar causa raiz com evidência de uma tentativa real. Preencher após executar **uma** tentativa completa: GET /start?token=... → definir senha → observar redirect → tentar login manual com email+senha.

**Sintoma:** POST /define-password sem erro visível, mas cai em tela de login; login manual (email + senha recém-definida) não autentica.  
**Esperado:** Após definir senha → login automático → redirect para /aluno/dashboard.php + banner/CTA “Instalar app”.

---

## Cole aqui as 3 peças (para o Cursor preencher o restante)

Depois de rodar **uma** tentativa completa, cole só isto e peça ao Cursor: "Preencha o Trace Pack e devolva causa + plano".

1. **URL exata onde você caiu após definir senha** (barra de endereço): `/login` ou `/aluno/login.php` ou a URL completa
2. **Linha [definePassword]:** (cola a linha completa do log)
3. **Linha(s) [aluno/dashboard]:** (cola a(s) linha(s) completa(s))
4. **Linha [login]** (somente se testou login manual no /login do app com email+senha): (cola ou "não testei")

**Link de teste já gerado (aluno Ana):**
- URL: `http://localhost/cfc-v.1/public_html/start?token=967ec553a4f81fd4fa8a88d3fe7059aa77a44d26b448e634178db04404f9fd67`
- Aluno: id=3 ANA BEZERRA DE OLIVEIRA, user_id=13, email=shirlleysoares6@gmal.com
- Gerar outro: `php tools/gerar_link_start_trace.php Ana`

---

## 1) Network (prova)

Preencher a partir do DevTools (Network), na ordem dos requests.

| # | Request | Status | Location (se 302) | Observação |
|---|---------|--------|-------------------|------------|
| 1 | GET /start?token=... | | | |
| 2 | POST /define-password | | | Copie o valor exato do header **Location** |
| 3 | Request seguinte ao redirect do passo 2 | | — | URL completa e status (ex.: GET https://.../aluno/dashboard.php → 302 ou 200) |

**Qual tela de login o usuário vê?**

- [ ] **/login** (app/router — barra de endereço termina em `/login` ou equivalente via front controller)
- [ ] **/aluno/login.php** (legado — barra de endereço contém `/aluno/login.php`)

**URL exata na barra de endereço quando o usuário está na tela de login:**  
`________________________________________________`

---

## 2) Logs (colar linhas completas)

Cole aqui as linhas de log correspondentes a **esta** tentativa (mesmo horário/request). Procurar por `[START]`, `[definePassword]`, `[aluno/dashboard]`, `[login]`.

### 2.1 [START] (token ok)

```
(cola aqui a linha com [START] token_result=ok …)
```

### 2.2 [definePassword]

```
(cola aqui a(s) linha(s) com [definePassword] TRACE …)
```

### 2.3 [aluno/dashboard]

```
(cola aqui a(s) linha(s) com [aluno/dashboard] TRACE … e redirect_reason/redirect_location se houver)
```

### 2.4 [login] (tentativa manual após falhar)

```
(cola aqui a linha com [login] email_normalized=… failure_reason=… …
 — só existe se o usuário tentou login manual na tela do app /login com email+senha)
```

---

## 3) Quadro comparativo — definePassword vs dashboard

Preencher a partir dos logs e, se necessário, dos INI/headers.

| Campo | definePassword (após update + login, antes do redirect) | aluno/dashboard (primeira linha, ao receber o request) |
|-------|--------------------------------------------------------|--------------------------------------------------------|
| HTTP_HOST | | |
| REQUEST_URI | | |
| session_name() | | |
| session_id() | | |
| cookie_present (CFC_SESSION) | | |
| session.cookie_path | | |
| session.cookie_domain | | |
| session.cookie_secure | | |
| session.cookie_samesite | | |
| session.save_handler | | |
| session.save_path | | |
| session.use_strict_mode | | |

**Conclusão rápida:**  
Se `session_id` em definePassword é “A” e no dashboard vira “none” ou “B” → cookie não foi enviado/aceito ou storage/host diverge.

---

## 4) Credencial e login manual

### 4.1 No POST /define-password (do log [definePassword])

| Campo | Valor |
|-------|--------|
| rows_affected | |
| verify_after_update | ok / fail |
| password_hash_prefix (primeiros 15 chars) | |
| email | (pode mascarar no relatório) |

Se `verify_after_update=ok` e o login manual ainda dá `wrong_password` → o login está buscando **outro** usuário/coluna/DB.

### 4.2 No POST /login (tentativa manual — do log [login])

| Campo | Valor |
|-------|--------|
| failure_reason | user_not_found / wrong_password / inactive / credentials_invalid |
| email_normalized | |
| email_from_db (mascarado) | |
| user_found_id | |
| user_status | |
| password_verify | ok / fail / n/a |

---

## 5) Para onde o dashboard redireciona

Quando o dashboard “derruba” por não ver sessão, o script `aluno/dashboard.php` envia:

- **Header Location exato:** `login.php` (relativo) → o navegador resolve para **/aluno/login.php** (legado).
- O app **/login** não é chamado por esse redirect; o usuário cai na tela legada **/aluno/login.php** (formulário com **CPF** + senha, não email+senha).

Portanto, para “login manual com **email** + senha”, o usuário precisa ir manualmente para a URL do app (ex.: `/login`) e submeter o form de email+senha. Só assim o log `[login]` do AuthController será gerado.

---

## 6) Diagnóstico — causa principal (escolher uma)

Com base no Trace Pack preenchido, assinale **uma** causa principal:

- [ ] **A. Cookie não chega no /aluno/dashboard.php**  
  (path/domain/secure/samesite/host mismatch — session_id no definePassword ≠ no dashboard ou cookie_present=0 no dashboard)

- [ ] **B. Sessão chega, mas storage é diferente**  
  (save_path/handler divergente → sessão “vazia” no dashboard mesmo com mesmo session_id)

- [ ] **C. Senha não foi gravada**  
  (rows_affected=0 ou verify_after_update=fail)

- [ ] **D. Senha foi gravada, mas login manual busca outro registro**  
  (verify_after_update=ok + failure_reason=wrong_password ou user_not_found → email mismatch / DB mismatch / coluna mismatch)

- [ ] **E. Dashboard bloqueia por role/tipo**  
  (user_type não bate, user inválido — ex.: has_user_id=1 mas isLoggedIn=0 ou redirect por user_not_aluno)

**Causa principal escolhida:** _____

---

## 7) Plano de correção mínima (2–3 passos)

Preencher **somente após** fechar a causa no item 6.

| # | Ação |
|---|------|
| 1 | |
| 2 | |
| 3 | |

---

## 8) Checklist da execução real

- [ ] Um único aluno escolhido para o teste
- [ ] Fluxo executado uma vez: link /start?token=... (WhatsApp in-app ou Chrome anônimo)
- [ ] Tela “Definir sua senha” preenchida e enviada
- [ ] Observada a URL final após redirects (tela de login? qual?)
- [ ] Tentativa de login manual com **email** + senha recém-definida na URL do app (**/login**)
- [ ] Network capturado (POST /define-password + request seguinte)
- [ ] Logs copiados do servidor ( [START], [definePassword], [aluno/dashboard], [login] )
- [ ] Trace Pack preenchido (seções 1–4)
- [ ] Causa principal e plano (seções 6–7) preenchidos após análise

---

*Documento de suporte à investigação em `.docs/INVESTIGACAO_PRIMEIRO_ACESSO_ROTAS_AUTH_SESSAO.md`.*
