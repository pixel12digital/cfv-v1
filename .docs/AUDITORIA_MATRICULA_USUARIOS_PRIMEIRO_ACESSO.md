# Auditoria: Matrícula vs Usuários — Primeiro Acesso e Acesso/Segurança

**Data:** 2025-01-27  
**Objetivo:** Mapear como funcionam HOJE os fluxos de primeiro acesso em **Matrícula** e em **Usuários → Acesso e Segurança**, para subsidiar uma futura simplificação/unificação em Usuários.  
**Restrição:** Apenas auditoria e recomendações — **nenhuma implementação** nesta etapa.

---

## A) Como funciona em Matrícula hoje

### 1. Paths e telas

| Onde | Path / rota | Arquivo(s) principal(is) |
|------|-------------|---------------------------|
| Editar Matrícula (tela principal) | `GET /matriculas/{id}` | `app/Controllers/AlunosController.php` → `showMatricula($id)` |
| View da matrícula | — | `app/Views/alunos/matricula_show.php` |
| Aba Matrícula do aluno (com CTA “Envie o app”) | `GET /alunos/{id}?tab=matricula` | `AlunosController::show($id)` → `app/Views/alunos/show.php` (bloco condicional `showInstallCta`) |

O link de primeiro acesso é calculado em **dois pontos**:
- **showMatricula:** sempre calcula e exibe os CTAs no header da página de edição da matrícula.
- **show (aluno):** calcula quando `tab === 'matricula'`; o bloco “Envie o app ao aluno” só aparece se `$_SESSION['show_install_cta']` estiver setado (ex.: após criar matrícula).

### 2. Componentes / partials e CTAs

**Em `matricula_show.php` (Editar Matrícula):**
- **CTAs no header (linhas 14–15):**
  - **“Enviar app no WhatsApp”** — `<a id="matricula-cta-wa">`  
    - `data-phone`, `data-message`, `data-install-url`.  
    - Desabilitado (opacity 0.6, pointer-events none) se `empty($hasValidPhone)`.
  - **“Copiar link”** — `<button id="matricula-cta-copy">`  
    - `data-install-url`. Sempre clicável.
- **Regras de habilitação:**
  - WhatsApp: apenas se o aluno tiver telefone válido (`$hasValidPhone`), normalizado para wa.me (DDI 55, 12–13 dígitos).
  - Copiar link: sempre habilitado quando há `installUrl` (mesmo que seja fallback `/install`).
- **Mensagens/UX:**
  - Se aluno sem telefone: texto “Aluno sem telefone.”
  - Se erro ao gerar link de primeiro acesso: `$installLinkError` em `<span class="alert alert-warning">`.
  - “Enviar pelo app”: **wa.me** — `window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(message), '_blank')`. Não usa Web Share API.
  - “Copiar link”: `navigator.clipboard.writeText(url)` com fallback `document.execCommand('copy')`; feedback via `alert('Link copiado.')`.

**Em `alunos/show.php` (aba Matrícula):**
- Bloco “Envie o app ao aluno” só existe quando `$showInstallCta === true` (pós-matrícula).
- Mesmo padrão: “WhatsApp” e “Copiar link” com `data-phone`, `data-message`, `data-install-url`; feedback de cópia em elemento na página em vez de `alert`.

### 3. Endpoints / rotas e geração do link

Não há endpoint dedicado “gerar link” em Matrícula. O link é **gerado na hora**, durante o **render da página**:

| Chamada | Onde | Método |
|--------|------|--------|
| `resolveInstallOrStartUrl($studentId)` | `AlunosController::showMatricula()` e `AlunosController::show()` | Private, invocado ao montar os dados da view |

**Lógica de `resolveInstallOrStartUrl($studentId, $student = null)`** (AlunosController, ~linhas 1771–1815):

1. Se aluno não carregado, carrega via `Student::find($studentId)`.
2. `user_id` e `email`:
   - Se `user_id <= 0` e tem `email`: chama `UserCreationService::createForStudent()` (cria usuário, vincula ao aluno), depois usa o novo `user_id`.
   - Se `user_id <= 0` e sem email: retorna `['url' => base_url('install'), 'error' => 'no_email']`.
   - Se `user_id <= 0` após criar: retorna `['url' => base_url('install'), 'error' => 'create_failed']` ou `'email_in_use'`.
3. Com `user_id` válido: `FirstAccessToken::create($userId, 48)` → token em texto puro, hash na tabela, 48h de validade.
4. Retorno: `['url' => base_url('start?token=' . $plainToken), 'error' => null]` ou `['url' => base_url('install'), 'error' => ...]`.

Ou seja: **nenhuma rota POST** para “gerar link” na matrícula; é tudo sob demanda na leitura da página.

### 4. Geração do token, armazenamento e validade

| Aspecto | Implementação |
|---------|----------------|
| Model | `App\Models\FirstAccessToken` |
| Tabela | `first_access_tokens` |
| Colunas | `id`, `user_id`, `token_hash` (SHA256), `expires_at`, `used_at`, `created_at` |
| Geração | `bin2hex(random_bytes(32))` → 64 caracteres hex |
| Armazenamento | Apenas `hash('sha256', $token)`; token puro **nunca** persistido |
| Validade | 48 horas (parâmetro em `create($userId, 48)`) |
| One-time | Sim: ao definir senha, `FirstAccessToken::markAsUsed($tokenId)` é chamado em `AuthController::definePassword()` |
| Tokens anteriores | **Não** são invalidados ao gerar novo: cada `create()` faz `INSERT`; vários links podem coexistir até expirarem ou serem usados |

### 5. Como o sistema entende “primeiro acesso”

- **Não** há flag explícita “primeiro acesso” na matrícula. O que existe é:
  - Aluno com `user_id` e possivelmente usuário recém-criado (senha ainda não definida ou troca obrigatória).
  - Se o link gerado contém `/start?token=...`, é “link de primeiro acesso” (definir senha).
  - Se o link é apenas `base_url('install')`, é fallback “só instalar app” (aluno sem email/user).
- Na prática, “primeiro acesso” = ter usuário vinculado + token de `first_access_tokens` válido, consumido na tela “Definir senha” (`/define-password`).

### 6. Ação “enviar pelo app”

- **Implementação:** `https://wa.me/{numero}?text={mensagem}` em nova aba (`window.open(..., '_blank')`).
- **Não** usa Web Share API.
- **Não** abre modal intermediário; vai direto ao WhatsApp Web/App.
- Mensagem quando é primeiro acesso:  
  `"Olá! Sua matrícula no CFC foi confirmada.\n\n📱 Clique no link para ativar seu acesso e instalar o app:\n\n{LINK}"`.  
  Caso contrário, mensagem mais longa com instruções de instalação e lugar do link.

### 7. Fluxo até “definir senha”

| Passo | Rota / tela | Responsável |
|-------|--------------|-------------|
| 1. Admin clica “Enviar app no WhatsApp” ou “Copiar link” | — | Front (matricula_show / show) |
| 2. Aluno abre o link | `GET /start?token=...` | `StartController::show()` |
| 3. Validação do token | — | `FirstAccessToken::findWithReason($token)` → ok/not_found/expired/used |
| 4. Sessão de onboarding | `$_SESSION['onboarding_user_id']`, `onboarding_token_id`, `force_password_change` | StartController |
| 5. Tela “Definir senha” | Mesma resposta de `/start` (sem redirect), view `auth/define-password` | StartController (evita perda de cookie no in-app do WhatsApp) |
| 6. POST senha | `POST /define-password` | `AuthController::definePassword()` |
| 7. Marca token usado, login, redirect | — | AuthController (FirstAccessToken::markAsUsed, login, redirect dashboard/install) |

---

## B) Como funciona em Usuários hoje

### 1. Paths e telas

| Onde | Path / rota | Arquivo(s) |
|------|-------------|------------|
| Editar usuário | `GET /usuarios/{id}/editar` | `app/Controllers/UsuariosController.php` → `editar($id)` |
| Seção “Acesso e Segurança” | Na mesma página (fora do form principal) | `app/Views/usuarios/form.php` (~linhas 152–312), condicionada a `$isEdit` |

### 2. Status de acesso e regras

**Status exibidos** (form.php, grid “Status de Acesso”):

| Status | Origem | Como é calculado |
|--------|--------|-------------------|
| **Senha definida** | `$hasPassword` | `!empty($user['password'])` no controller |
| **Troca obrigatória** | `$user['must_change_password']` | Coluna `usuarios.must_change_password` (0/1) |
| **Link de ativação ativo** | `$hasActiveToken` | `AccountActivationToken::findActiveToken($id)` — existe token com `used_at IS NULL` e `expires_at > NOW()` |

**Três ações (botões):**

1. **Gerar Senha Temporária**  
   - Form POST → `POST /usuarios/{id}/gerar-senha-temporaria`  
   - Sempre habilitado (para o usuário em edição).  
   - Gera senha 12 chars, `password_hash(..., PASSWORD_BCRYPT)`, `UPDATE usuarios SET password=?, must_change_password=1`.  
   - Coloca em `$_SESSION['temp_password_generated']` e redireciona para editar; a view mostra uma vez e o controller limpa a sessão após render.

2. **Gerar Link de Ativação**  
   - Form POST → `POST /usuarios/{id}/gerar-link-ativacao`  
   - Sempre habilitado.  
   - Gera token 64 hex, hash SHA256, expiração 24h, `AccountActivationToken::create()` (que **invalida** tokens anteriores do mesmo usuário).  
   - Coloca URL e dados em `$_SESSION['activation_link_generated']` e redireciona; na view aparece uma vez “Link de Ativação Gerado” com “Copiar Link” e data de expiração.

3. **Enviar Link por E-mail**  
   - Form POST → `POST /usuarios/{id}/enviar-link-email`  
   - **Habilitado só se** `$hasActiveToken === true` (token ativo no banco ou link recém-gerado na sessão).  
   - Caso contrário: botão desabilitado com texto “Gere um link primeiro”.

### 3. Endpoints e rotas

| Ação | Rota | Método controller |
|------|------|--------------------|
| Gerar senha temporária | `POST /usuarios/{id}/gerar-senha-temporaria` | `UsuariosController::gerarSenhaTemporaria($id)` |
| Gerar link de ativação | `POST /usuarios/{id}/gerar-link-ativacao` | `UsuariosController::gerarLinkAtivacao($id)` |
| Enviar link por e-mail | `POST /usuarios/{id}/enviar-link-email` | `UsuariosController::enviarLinkEmail($id)` |

Todas atrás de `AuthMiddleware`; permissão: `PermissionService::check('usuarios','update')` ou `$_SESSION['current_role'] === 'ADMIN'`.

### 4. Geração do token (Usuários) e armazenamento

| Aspecto | Implementação |
|---------|----------------|
| Model | `App\Models\AccountActivationToken` |
| Tabela | `account_activation_tokens` |
| Colunas | `id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`, `created_by` |
| Geração | `bin2hex(random_bytes(32))` no controller; hash SHA256 antes de salvar |
| Validade | 24 horas |
| One-time | Sim: `markAsUsed()` ao ativar conta |
| Tokens anteriores | **Invalidados** ao gerar novo: `AccountActivationToken::create()` chama `invalidatePreviousTokens($userId)` (UPDATE `used_at = NOW()` nos ativos). |

### 5. Impacto da “Senha Temporária” no login

- `must_change_password = 1` → no login o usuário é redirecionado para troca de senha e vê aviso “troca obrigatória no primeiro acesso”.
- Senha temporária é exibida **uma única vez** (sessão + limpeza após exibir).

### 6. Link de ativação — invalidação e segurança

- Ao **gerar** novo link: todos os tokens anteriores do usuário (não usados e não expirados) são marcados como usados.
- **Enviar por e-mail:** usa token da sessão `activation_link_generated` se ainda houver; senão gera novo token, atualiza o hash no **mesmo** registro do token “ativo” no BD (comportamento especial em `enviarLinkEmail`), monta a URL e envia ou devolve link copiável em caso de falha de SMTP.
- Token puro **não** fica em log; fica só na sessão/URL para envio.

### 7. Fluxo de ativação de conta (Usuários)

| Passo | Rota / tela | Responsável |
|-------|--------------|-------------|
| 1. Admin gera link e opcionalmente envia por e-mail | POST gerar-link / enviar-link | UsuariosController |
| 2. Usuário abre link | `GET /ativar-conta?token=...` | `AuthController::showActivateAccount()` |
| 3. Validação | `AccountActivationToken::findByTokenHash(hash)` | AuthController |
| 4. Formulário “Definir senha” | View `auth/activate-account` | AuthController |
| 5. POST | `POST /ativar-conta` | `AuthController::activateAccount()` |
| 6. Atualiza senha, `must_change_password=0`, marca token usado, redirect login | — | AuthController |

---

## C) Gap & Redundâncias

### O que é comum aos dois fluxos

- Objetivo: dar ao usuário um meio de definir (ou redefinir) senha sem saber a atual.
- Token em texto puro só na URL/sessão; no BD só hash, com expiração e uso único.
- Tela final “definir senha” + atualização de senha e remoção de “troca obrigatória” quando aplicável.

### Diferenças importantes

| Aspecto | Matrícula | Usuários |
|--------|-----------|----------|
| **Quando** o link é gerado | Sob demanda ao carregar a página (sem POST) | Ação explícita “Gerar Link” (POST) |
| **Onde** o link aparece | Já na tela, com “Enviar no WhatsApp” e “Copiar link” | Após gerar: alert + “Copiar Link”; “Enviar” é outra ação e depende de “gerar primeiro” |
| **Entrega** | wa.me + copiar; sempre visível na matrícula | E-mail (se SMTP) ou link copiável; sem WhatsApp/Share |
| **Validade** | 48h | 24h |
| **Invalidação** de links antigos | Não invalida ao gerar novo | Invalida tokens anteriores ao gerar novo |
| **Tabela/token** | `first_access_tokens` | `account_activation_tokens` |
| **Rota do link** | `/start?token=...` → define-password (onboarding) | `/ativar-conta?token=...` → define senha e login |

### Redundâncias e confusão em Usuários

1. **Três botões** (Gerar Senha, Gerar Link, Enviar Link) com dependência “Gere um link primeiro” para o terceiro → fluxo não óbvio para “só quero enviar o acesso”.
2. **Nenhum atalho** “enviar por WhatsApp” ou “compartilhar” como na matrícula; só e-mail ou copiar.
3. **Dois mecanismos** de “dar acesso” (senha temporária vs link) sem um CTA principal que una “gerar + entregar” para o caso mais comum (primeiro acesso / reset).
4. **Ausência** de helper/serviço compartilhado que receba “user_id, tipo (primeiro acesso vs reset), opções de entrega” e devolva “url + expiração + mensagem sugerida”.

### O que pode ser reaproveitado de Matrícula em Usuários

- Padrão **“um bloco com link + Enviar (WhatsApp ou Share) + Copiar”** na própria tela, sem obrigar “gerar → fechar → depois enviar”.
- Uso de **wa.me** quando houver telefone (ex.: usuário ou vínculo aluno/instrutor com telefone).
- **Web Share API** (ou fallback copiar) em mobile/PWA para “Enviar acesso” genérico.
- Mensagem padrão “Clique no link para ativar seu acesso…” reutilizável para primeiro acesso / reset.

---

## D) Recomendação de simplificação para Usuários

### Objetivo da proposta

- Um **CTA principal** para o caso “preciso mandar o acesso pro usuário” (primeiro acesso ou “esqueci a senha”).
- Manter segurança (token imprevisível, expiração, one-time, invalidar anteriores ao regenerar).
- Melhorar entrega: copiar + opção compartilhar/WhatsApp quando fizer sentido.

### 1. CTA principal sugerido

- **“Gerar e enviar acesso”** ou **“Enviar acesso”** (se já houver link ativo e válido).
- Comportamento sugerido:
  - Se não há token ativo: **gerar** token (como hoje em “Gerar Link”), invalidando anteriores; mostrar link + opções de entrega.
  - Se já há token ativo: mostrar de novo o link + opções de entrega (sem novo POST de “gerar”).
- Assim, uma ação cobre “gerar + ver link + copiar/compartilhar”.

### 2. Estados e regras (primeiro acesso vs reset)

- **Primeiro acesso:** usuário nunca definiu senha (ou só recebeu senha temporária e não trocou). Tratamento pode ser o mesmo: link de ativação leva a definir senha.
- **Reset / “não consigo acessar”:** mesmo fluxo de “Gerar e enviar acesso” → mesmo link `/ativar-conta?token=...`; do ponto de vista do usuário final é “recebi um link para criar/alterar minha senha”.
- Não é obrigatório distinguir “primeiro acesso” vs “reset” na UI; o backend já suporta ambos com o mesmo tipo de token. Opcionalmente, textos ou ícones podem diferenciar (“Primeiro acesso” vs “Reenviar acesso”) para o admin.

### 3. Como exibir/entregar: copiar + compartilhar/enviar

- **Sempre:** botão “Copiar link” e exibição da URL (ou “Link copiado” após sucesso), com fallback para `execCommand('copy')` quando não houver Clipboard API.
- **Quando houver telefone** (ex.: usuário ou vínculo com telefone): botão “Enviar no WhatsApp” (wa.me), como na matrícula.
- **Mobile/PWA:** usar **Web Share API** quando disponível (navigator.share com title + text + url) para “Compartilhar”; fallback para copiar.
- **E-mail:** manter “Enviar por e-mail” como ação separada ou dentro do mesmo bloco “Enviar acesso”, desde que haja token ativo (ou seja gerado na mesma ação).

### 4. Senha temporária

- **Manter** como opção secundária/avançada (ex.: em “Mais ações” ou seção recolhida “Avançado”).
- Casos de uso: teste rápido, atendimento presencial em que o admin fala a senha, ou quando não há canal (e-mail/telefone) para enviar link.
- Na mesma seção, deixar explícito: “Senha temporária: o usuário precisará trocar no próximo login. Para enviar por link (e-mail/WhatsApp), use ‘Gerar e enviar acesso’.”

### 5. O que fazer com os três botões atuais

- **“Gerar e enviar acesso”** (ou “Enviar acesso”): ação principal que gera (se necessário) + mostra link + Copiar + WhatsApp (se telefone) + Share (se disponível). Opcionalmente incluir “Enviar por e-mail” no mesmo bloco.
- **“Gerar Link de Ativação”**: pode virar ação secundária (“Gerar novo link”) para quando o admin só quer gerar/copiar sem pensar em “enviar”.
- **“Enviar Link por E-mail”**: integrar ao bloco do CTA principal (habilitado quando há link ativo ou recém-gerado) em vez de botão isolado que depende de “gerar primeiro”.
- **“Gerar Senha Temporária”**: manter como opção avançada, com texto explicando quando usar.

### 6. Helper/service compartilhável (sugestão para implementação futura)

- Um serviço ou helper, ex.: `AccessDeliveryService::linkForUser($userId, $options)` que:
  - Decide se usa `first_access_tokens` ou `account_activation_tokens` (ou só um deles após unificação),
  - Gera token, invalida anteriores quando for o caso,
  - Retorna `['url' => ..., 'expires_at' => ..., 'message_suggestion' => ...]`.
- Tanto o fluxo de Matrícula quanto o de Usuários poderiam usar esse retorno para montar wa.me, Share, e-mail e “Copiar link”, reduzindo duplicação de lógica.

---

## E) Checklist de aceitação para a futura implementação

Use este checklist na fase de implementação (sem codar nesta etapa).

### Funcional

- [ ] Existe um CTA principal do tipo “Gerar e enviar acesso” / “Enviar acesso” na seção Acesso e Segurança.
- [ ] Esse CTA gera link de ativação quando não há token ativo e invalida tokens anteriores ao gerar.
- [ ] Quando já existe token ativo, o CTA exibe o mesmo link e as opções de entrega sem exigir novo “Gerar link”.
- [ ] “Copiar link” está sempre disponível quando há link (ativo ou recém-gerado), com feedback claro (“Link copiado” ou similar).
- [ ] “Enviar no WhatsApp” (wa.me) aparece quando há telefone utilizável (do usuário ou do vínculo aluno/instrutor).
- [ ] Em ambiente mobile/PWA, “Compartilhar” usa Web Share API quando disponível, com fallback para copiar.
- [ ] “Enviar por e-mail” permanece disponível quando há token ativo (ou foi gerado na mesma ação), com fallback “link copiável” se SMTP falhar ou não estiver configurado.
- [ ] “Gerar Senha Temporária” continua acessível como opção avançada, com texto que explica o uso (teste, presencial, etc.).
- [ ] Os três status (Senha definida, Troca obrigatória, Link ativo) continuam visíveis e calculados como hoje (hasPassword, must_change_password, hasActiveToken).

### Segurança

- [ ] Token continua imprevisível (ex.: `random_bytes(32)`), com hash SHA256 no banco; token puro não é persistido nem logado.
- [ ] Token tem expiração definida (ex.: 24h para usuários, ou alinhado ao que for definido) e é one-time (marcado como usado após ativar).
- [ ] Ao gerar novo link em Usuários, tokens anteriores (não usados e não expirados) são invalidados.

### UX e compatibilidade

- [ ] Fluxo funciona em desktop (copiar, wa.me em nova aba) e em mobile/PWA (compartilhar quando possível).
- [ ] Mensagens de erro/aviso (ex.: “Nenhum link ativo”, “Gere um link primeiro”, SMTP não configurado) permanecem claras.
- [ ] Permissões atuais (usuarios/update ou ADMIN) são respeitadas para todas as ações da seção.

### Não regressão

- [ ] Fluxo atual de “Gerar Link” + “Enviar por E-mail” continua válido (mesmo que reorganizado na tela).
- [ ] Fluxo de “Gerar Senha Temporária” e impacto em `must_change_password` e login permanecem iguais.
- [ ] Fluxo de Matrícula (“Enviar app no WhatsApp” / “Copiar link”) não é quebrado; unificação é optativa e pode ser feita depois via helper compartilhado.

---

## Resumo executivo

- **Matrícula:** link de primeiro acesso é gerado ao carregar a página (`resolveInstallOrStartUrl`), usa `first_access_tokens` (48h), **não** invalida tokens antigos. CTAs “Enviar app no WhatsApp” e “Copiar link” ficam sempre à mão no header de edição da matrícula; entrega é simples e direta.
- **Usuários:** três ações separadas (Gerar Senha, Gerar Link, Enviar Link), com dependência “Gere um link primeiro” para enviar; usa `account_activation_tokens` (24h) e **invalida** tokens anteriores. Não há wa.me nem Share; só e-mail e copiar.
- **Gap:** Usuários pode incorporar o padrão “um CTA principal + copiar + WhatsApp/Share” inspirado na matrícula, mantendo segurança (invalidação, one-time, hash) e deixando “Senha temporária” como opção avançada.
- **Próximo passo:** usar este documento como base para definir escopo e telas da implementação, sem alterar comportamento ou código nesta etapa de auditoria.
