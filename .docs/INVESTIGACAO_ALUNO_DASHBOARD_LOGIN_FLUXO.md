# Investigação: fluxo aluno — mesmo login, tela em branco em aluno/dashboard.php

## O que você descreveu

- **Print 1:** Login em `painel.cfcbomconselho.com.br/login` — entra corretamente (admin).
- **Aluno:** Usava o **mesmo** login, só trocando a senha. Antes abria o dashboard normalmente.
- **Agora:** Aluno é mandado para uma **tela em branco**.
- **Mudança que você apontou:** Antes carregava `dashboard.php`; agora passa a ir para `aluno/dashboard.php`.

---

## O que estava funcionando antes

Antes, depois do login, o **aluno** devia ser redirecionado para alguma URL de dashboard que **respondia 200** e mostrava a interface. Possíveis cenários:

1. **Redirect para `/dashboard` (app):** O app tratava a rota `/dashboard` e, por papel, mostrava o dashboard do aluno. Em produção hoje `/dashboard` responde **404** (não existe arquivo nem rota atendendo isso no docroot do painel), então esse fluxo só faria sentido se, no passado, o docroot ou o router fossem outros.
2. **Redirect para um `dashboard.php` na raiz:** Por exemplo `header('Location: dashboard.php')` no contexto do login. Dependendo do docroot, isso poderia ser `/dashboard.php` e algum handler devolver a página do aluno.
3. **Redirect já para `aluno/dashboard.php`:** O destino era o mesmo de agora, mas o arquivo `aluno/dashboard.php` (legado) **não** quebrava — por exemplo, DB e config ok.

Em qualquer caso, **antes** o usuário aluno chegava num dashboard que carregava; **agora** ele vai para `aluno/dashboard.php` e recebe **500** (tela em branco na prática).

---

## O que mudou no código (canonical aluno → /aluno/dashboard.php)

O redirecionamento de **aluno** para `/aluno/dashboard.php` foi definido em vários pontos, de forma explícita:

### 1. Login principal — `login.php` (raiz do painel)

Quando o tipo escolhido é **aluno** e o login dá certo:

```php
// login.php, ~linha 97
header('Location: aluno/dashboard.php');
exit;
```

Ou seja: **todo** login de aluno feito nessa tela vai para `aluno/dashboard.php`.

### 2. Função centralizada — `includes/auth.php`

`redirectAfterLogin()` envia cada tipo para um destino fixo:

- admin/secretaria → `/admin/index.php`
- instrutor → `/instrutor/dashboard.php`
- **aluno → `/aluno/dashboard.php`**

Usada quando já está logado ou depois do login de admin/instrutor. Para aluno, no `login.php` da raiz, o redirect é direto (trecho acima), não passa por essa função nesse fluxo.

### 3. App — `app/Controllers/AuthController.php`

Após login no app (`/login`):

- aluno → `base_url('/aluno/dashboard.php')`

### 4. App — `app/Controllers/DashboardController.php`

Se alguém acessa **`/dashboard`** e o usuário é aluno:

```php
// DashboardController.php, ~linhas 115–118
if ($currentRole === Constants::ROLE_ALUNO && $userId) {
    header('Location: ' . $basePath . '/aluno/dashboard.php');
    exit;
}
```

Ou seja: no app, “dashboard do aluno” é tratado como “ir para o legado em `/aluno/dashboard.php`”.

---

## Por que aparece tela em branco (500)

A tela em branco vem de **HTTP 500** em:

`GET https://painel.cfcbomconselho.com.br/aluno/dashboard.php`

Esse arquivo é o **legado** (`aluno/dashboard.php`):  
`includes/config.php` → `includes/database.php` → `includes/auth.php` → lógica do dashboard.

Quando esse legado dava erro (por ex. falha de conexão com o banco), o PHP respondia 500 e o corpo da resposta ia vazio (ou genérico), o que na prática vira “tela em branco”.

Ou seja:

- O **mesmo** login (print 1) passa a mandar o aluno para `aluno/dashboard.php`.
- Esse destino **sempre** foi legado; a “mudança” é que **agora** o fluxo de login e do app foram configurados para usar esse canonical.
- O que “quebrou” foi o **legado** (erro em `includes/database.php`), não a decisão de redirecionar para `aluno/dashboard.php`.

---

## O que já foi corrigido

Foi aplicado o patch em `includes/config.php` para o legado usar as mesmas credenciais do app (via `.env` quando existir). Depois do deploy:

- `curl -I .../aluno/dashboard.php` passou a retornar **302** para `login.php` quando **não** há sessão, em vez de 500.

Isso indica que o 500 do legado foi resolvido nesse cenário. O próximo passo é verificar com **sessão de aluno**.

---

## Resumo: por que “estava funcional” e o que mudou

| Aspecto | Antes | Agora |
|--------|--------|--------|
| Onde o aluno é mandado após o login | Provavelmente `/dashboard` ou um `dashboard.php` que respondia 200 | Sempre `/aluno/dashboard.php` (canonical) |
| Quem atende `/aluno/dashboard.php` | Legado (includes/config + database + auth) | Mesmo legado |
| O que “quebrou” | — | Legado passou a dar **500** (config/DB); deploy recente corrigiu isso |
| Comportamento atual esperado | — | 302 sem sessão; com sessão de aluno deve ser **200** |

Conclusão: **estava funcional** porque o aluno ou não ia para `aluno/dashboard.php`, ou o legado estava ok. **Deixou de estar** quando o fluxo passou a mandar sempre para `aluno/dashboard.php` e o legado começou a retornar 500. Com o patch no `config` do legado, o 500 foi endereçado; falta só validar com um login de aluno.

---

## Próximos passos recomendados

1. **Confirmar que o deploy do patch está ativo** no ambiente que o aluno usa (`includes/config.php` com leitura de `.env` para DB).
2. **Testar o fluxo completo com aluno:**
   - Abrir `painel.cfcbomconselho.com.br/login` (ou a URL exata do “print 1”).
   - Escolher/entrar como **aluno** (mesmo login, senha de aluno).
   - Verificar se o redirect vai para `.../aluno/dashboard.php` e se a página **carrega** (status 200, sem tela em branco).
3. Se ainda der 500 ou branco, coletar de novo:
   - Saída de `curl -I .../aluno/dashboard.php` (com e sem cookie de sessão de aluno, se possível).
   - Trecho do log de erros do servidor no horário do acesso (para ver se voltou algum Fatal/Exception em `includes/database.php` ou em `aluno/dashboard.php`).

Com isso dá para fechar se o problema restante é só cache/usuário antigo, ou se ainda há algum caso (sessão, ambiente, etc.) em que o legado volta a falhar.
