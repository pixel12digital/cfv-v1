# Plano: Enviar app PWA no WhatsApp após matrícula

**Data:** 2025-01-27  
**Objetivo:** Atalho para encaminhar ao aluno o link de instalação do app via WhatsApp no ato da matrícula efetivada.

---

## 1. Recomendação: qual opção implementar agora

**Recomendação: Opção 1 (wa.me + mensagem pronta) + landing pública `/install`.**

| Critério | Opção 1 | Opção 2 | Opção 3 |
|----------|---------|----------|---------|
| Prazo / risco | Rápido, risco baixo | Médio | Alto (depende gateway) |
| Backend | Zero | Leve (endpoint) | Integração WhatsApp |
| Quem envia | Atendente (wa.me) | Atendente | Sistema (número oficial) |
| Rastreio | Não | Parcial | Sim |

**Por quê agora a Opção 1:**

1. **Entrega imediata:** Só front + uma URL fixa; não depende de gateway, API ou sessão WhatsApp.
2. **Alinhado ao seu contexto:** Você já sugeriu “implemente Opção 1 + landing /install; evolua para Opção 3 quando o WhatsApp do sistema estiver estável”.
3. **Não quebra nada:** Não mexe em matrícula, financeiro, SW nem manifest; apenas expõe um CTA e uma página nova pública.
4. **Telefone do aluno já existe:** O wa.me usa o número cadastrado do aluno; o atendente escolhe “Enviar no WhatsApp” e o cliente recebe a mensagem pronta no privado dele.
5. **Evolução possível:** Depois, um endpoint (Opção 2) pode centralizar texto/QR e, por fim, a Opção 3 passa a disparar pelo número oficial quando houver integração.

---

## 2. Decisões objetivas

### 2.1 Onde colocar o CTA no fluxo de matrícula

| Local | Quando aparece | Formato sugerido |
|-------|----------------|------------------|
| **Principal:** após redirect de matrícula criada | `alunos/{id}?tab=matricula` com `$_SESSION['success'] = 'Matrícula criada com sucesso!'` | Card/faixa logo abaixo do alert de sucesso: “Envie o app ao aluno: [Enviar no WhatsApp] [Copiar link]” (opcional: [Ver QR]) |
| **Secundário:** tela de detalhe da matrícula | Em `matriculas/{id}` (view `matricula_show.php`) | Botão ou link “Enviar link do app ao aluno” junto aos botões de ação (ex.: ao lado de Voltar/Cancelar ou em uma seção “Compartilhar app”) |

**Fluxo sugerido:**  
Ao efetivar matrícula, o controller continua fazendo `redirect(base_url("alunos/{$id}?tab=matricula"))` com `$_SESSION['success'] = 'Matrícula criada com sucesso!'`. No layout (shell ou no bloco da aba matrícula), quando `tab === 'matricula'` e há success “Matrícula criada…”, exibir um bloco **“Envie o app ao aluno”** com os botões. Na `matricula_show`, o CTA fica sempre visível para aquela matrícula.

### 2.2 URL canônica do PWA para envio

- **Recomendação:** uma única URL pública de “instalação”, sem login.
- **Formato:** `{BASE}/install` em que `BASE` é o mesmo host do painel (ex.: `https://painel.cfcbomconselho.com.br`).
- **Uso:** esse é o “link principal” que vai na mensagem do WhatsApp, no “Copiar link” e no QR Code.
- **Implementação:** rota pública `GET /install` que serve uma página HTML estática ou view dedicada (sem shell, sem auth).

### 2.3 Criar landing pública `/install`?

**Sim.**

- **Onde:** rota `GET /install` tratada pelo app (ex.: `InstallController::show` ou método estático que só inclui uma view).
- **Requisitos:**
  - Sem login; sem auth.
  - Mesmo domínio do painel para não quebrar scope do SW/manifest (ex.: `painel.../install`).
  - Página com:
    - `<link rel="manifest">` e, se fizer sentido, registro do SW (reaproveitando o que já existe em `/sw.js`), **ou** apenas link/logos e botões que levam ao fluxo já existente (ex.: “Abrir no app” → login aluno).
  - Não alterar regras de cache de rotas autenticadas; `/install` é estática/leve e pode ser cacheada com cuidado (ex.: curto max-age) ou não cacheada.

**Conflito com SW/manifest:**  
Não conflita se `/install` for uma página HTML normal no mesmo origem em que o SW já está registrado (ex.: no login ou na raiz). Ou seja: você pode fazer `/install` ser uma “landing de instalação” que só mostra instruções + link “Abrir app” / “Instalar” sem registrar outro SW; o “Instalar” na prática pode abrir o mesmo início do app (ex.: login aluno) onde o `beforeinstallprompt` já é tratado. O essencial é que a URL **/install** seja fixa, memorável e usada em todo o fluxo “Enviar app”.

### 2.4 Texto sugerido para WhatsApp (PT-BR, 1 link principal)

Sugestão objetiva:

```
Olá! Sua matrícula no CFC foi confirmada.

📱 Instale o app do aluno (acompanhe aulas, financeiro e mais):

{LINK_INSTALACAO}

• Android/Chrome: abra o link e toque em "Instalar" ou no menu ⋮ → "Instalar app".
• iPhone/Safari: abra o link, toque em compartilhar e "Adicionar à Tela de Início".

Para acessar depois, use o mesmo link ou o ícone do app na tela inicial.
```

- `{LINK_INSTALACAO}` = URL canônica (ex.: `https://painel.cfcbomconselho.com.br/install`).
- Não incluir link de login na primeira linha para manter “1 link principal”; o próprio `/install` pode ter botão “Já tenho o app – Abrir / Fazer login” apontando para o login do aluno, se quiser.

### 2.5 QR Code: onde aparece e qual URL

- **Onde:** dentro do mesmo bloco “Envie o app ao aluno” (no pós-matrícula ou em `matricula_show`), em modal ou seção recolhível “Ver QR Code”.
- **URL que o QR codifica:** a mesma URL canônica de instalação, ou seja, `{BASE}/install`.
- **Implementação:** 
  - Opção A (mais simples): link “Ver QR” que abre um gerador público (ex.: api.qrserver.com) com `url=https://painel.../install` em nova aba, ou
  - Opção B: lib leve (ex.: um snippet JS com `qrcode.js` ou similar) que gera o QR em um modal. Evitar libs pesadas; priorizar algo já usado no projeto ou ~1 arquivo minificado.

### 2.6 iOS vs Android na landing `/install`

- **Sem gambiarras:** uma única página que detecta o ambiente e mostra o bloco certo.
- **Lógica sugerida:**
  1. **Android/Chrome (ou desktop Chrome):** se `beforeinstallprompt` existir, mostrar botão “Instalar app” que chama `deferredPrompt.prompt()`. Se não existir (ex.: já instalado ou critérios não atendidos), mostrar “Abrir app” (link para login aluno) + instruções genéricas.
  2. **iOS/Safari:** não há `beforeinstallprompt`. Mostrar apenas instrução fixa: “Toque em compartilhar (ícone de compartilhar) e depois em ‘Adicionar à Tela de Início’.” + link “Abrir no Safari” apontando para a mesma `/install` ou para o login aluno.
  3. **Outros:** texto neutro “Abra o link no Celular (Chrome ou Safari) para instalar” + mesmo link.
- **Detecção:** `navigator.userAgent` para iOS (iPad/iPhone/iPod); para “já instalado” usar `window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true`.

### 2.7 “Já instalado” → exibir “Abrir app”

- **Condição:**  
  `window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true` (e, se quiser, referrer android-app://).
- **Comportamento:** na `/install`, se “já instalado”, esconder “Instalar” e mostrar apenas “Abrir app” (link para a start_url do aluno ou para o login do aluno).
- **URL do “Abrir app”:** mesma base do painel, ex.: `https://painel.cfcbomconselho.com.br/...` (login aluno), mantendo consistência com o que está no manifest/start_url do PWA aluno.

---

## 3. Requisitos de implementação (resumo)

- Manter comportamento atual do PWA (manifest/SW, rotas autenticadas, cache).
- Não introduzir dependências pesadas; QR com lib minúscula ou link externo.
- UI: botão(s) discretos e claros para admin/secretaria.
- Checklist de testes: Android/Chrome, iOS/Safari, Desktop/Chrome (instalar, “já instalado”, copiar link, wa.me).

---

## 4. Plano de implementação em passos (sem código)

### Fase A – Landing `/install` e URL canônica

1. **Rota e controller (ou handler)**
   - Registrar `GET /install` como rota pública (antes de middlewares de auth).
   - Handler que serve uma view “install” (HTML próprio, sem layout shell).

2. **View da landing**
   - Página com título “Instalar app do aluno”, texto curto, um link/CTA “Instalar” ou “Abrir app”.
   - Incluir `<link rel="manifest">` (e, se conveniente, registro do SW) quando for a mesma origem do app, para não quebrar installability.
   - Blocos condicionais no JS:
     - Se já instalado → só “Abrir app”.
     - Se Android/Chrome e `beforeinstallprompt` → botão “Instalar”.
     - Se iOS → instrução “Adicionar à Tela de Início”.
   - Link “Abrir app” sempre apontando para a URL de login do aluno na mesma base.

3. **Garantir que não exige login**
   - Rotas públicas já existentes em `web.php` não passam por `AuthMiddleware`; manter `/install` nessa lista.
   - Se houver `.htaccess` ou regras que enviem tudo para `index.php`, a rota `/install` será tratada pelo router como hoje; só não proteger com auth.

### Fase B – CTA “Enviar app no WhatsApp” no fluxo de matrícula

4. **Definir variáveis server-side**
   - Em algum helper/Controller base ou na view, definir:
     - `$installUrl` = URL canônica (ex.: `base_url('install')` ou `'https://painel.cfcbomconselho.com.br/install'`).
     - `$mensagemWhatsApp` = texto da mensagem com placeholder `{LINK}` substituído por `$installUrl`.
   - Telefone do aluno: buscar do `$student` (ou do `$enrollment` em matricula_show) e formatar para wa.me (apenas dígitos, DDI 55).

5. **Pós-matrícula (alunos/{id}?tab=matricula + success)**
   - No layout (ex.: `shell.php`) ou na view da aba “matrícula”:
     - Se `tab === 'matricula'` e `$_SESSION['success']` contém “Matrícula criada” (ou uma flag tipo `$_SESSION['show_install_cta']` setada no controller após criar matrícula):
       - Inserir um bloco “Envie o app ao aluno” com:
         - Botão “Enviar no WhatsApp”: `window.open(waMeUrl)` onde `waMeUrl = 'https://wa.me/55' + numeroLimpo + '?text=' + encodeURIComponent(mensagem)`.
         - Botão “Copiar link”: `navigator.clipboard.writeText(installUrl)` (+ feedback “Link copiado”).
       - Usar `$student` para montar o número no wa.me (e, se fizer sentido, manter um `data-install-url` / `data-wa-message` nos elementos para o JS).
   - Decidir se “Matrícula criada” some após um tempo ou após fechar o card; manter coerente com o resto das mensagens de sucesso.

6. **Tela de detalhe da matrícula (`matricula_show.php`)**
   - Incluir o mesmo bloco (ou um botão “Enviar link do app ao aluno”) reutilizando:
     - `$installUrl`;
     - mensagem WhatsApp;
     - telefone vindo do aluno da matrícula (`$enrollment['student_phone']` ou equivalente).
   - Manter visual discreto (ex.: um botão outline “Enviar app no WhatsApp” e, ao lado, “Copiar link”).

### Fase C – Opcionais

7. **QR Code**
   - No bloco “Envie o app ao aluno”, botão “Ver QR Code” que:
     - Abre modal ou seção com um `<canvas>`/`<img>` onde se desenha o QR da `installUrl`, ou
     - Abre em nova aba um gerador externo com a `installUrl`.
   - Garantir que a URL codificada é sempre a canônica `/install`.

8. **Ajustes finos**
   - Trocar textos hardcoded por chaves de i18n se o projeto já usar.
   - Incluir `autocomplete="current-password"` no campo senha do login (já citado no console da captura) em algum passo de polish, se fizer parte do mesmo escopo.

---

## 5. Arquivos / telas que serão tocados

| Arquivo / recurso | O que fazer |
|-------------------|------------|
| `app/routes/web.php` | Adicionar `GET /install` como rota pública (ex.: `[InstallController::class, 'show']` ou callback que renderiza view). |
| Novo: `app/Controllers/InstallController.php` (ou método em controller existente) | Método `show()` que carrega CFC/nome se precisar do layout, e chama view raw `install` sem shell. |
| Novo: `app/Views/install.php` ou `app/Views/auth/install.php` | HTML da landing: título, instruções Android/iOS, botão “Instalar” / “Abrir app”, `<link rel="manifest">`, trecho JS para `beforeinstallprompt` e “já instalado”. |
| `app/Views/layouts/shell.php` | Onde já existe o bloco `<?php if (isset($_SESSION['success'])) ?>`: estender para, quando a mensagem for de “Matrícula criada” e a aba for matrícula, incluir o card “Envie o app ao aluno” (ou incluir via partial). Alternativa: fazer isso dentro da view `app/Views/alunos/show.php` na seção `tab === 'matricula'`. |
| `app/Views/alunos/show.php` | Se o CTA pós-matrícula ficar aqui: na parte `$tab === 'matricula'`, após listagem de matrículas, incluir o bloco “Envie o app ao aluno” quando houver success de matrícula criada, usando `$student` para telefone e uma variável `$installUrl` (e mensagem) passada pelo controller. |
| `app/Controllers/AlunosController.php` | Em `show($id)` (que serve alunos/{id}): garantir que a view recebe `$installUrl` (e talvez `$waMessage`) e, se for o caso, `$_SESSION['show_install_cta']` ou equivalente para o CTA pós-matrícula. Em `showMatricula` (matricula_show): passar `$installUrl`, mensagem e telefone do aluno para a view. |
| `app/Views/alunos/matricula_show.php` | Incluir bloco ou botão “Enviar link do app ao aluno” (e opcional “Copiar link” / “Ver QR”), usando `$enrollment`, `$installUrl` e a mensagem. |
| `app/Bootstrap.php` ou helper global | (Opcional) helper `install_url()` que retorna `base_url('install')` para uso em várias views. |
| Scripts/estilos | Se o QR for feito no front, um pequeno script (ou lib única) para gerar o QR no modal; ou nenhum arquivo novo se usar apenas link externo para o QR. |

Nenhum arquivo de **PWA** (manifest, SW, pwa-register, install-footer) precisa ser alterado para a Opção 1, desde que a `/install` use o mesmo domínio e, se necessário, apenas encaminhe o usuário para o fluxo já existente de instalação (por exemplo, login aluno onde o `beforeinstallprompt` já é tratado).

---

## 6. Checklist de testes

- [ ] **Android/Chrome:** Abrir `/install` → aparece “Instalar” ou “Abrir app”; instalar pelo botão; depois, “Abrir app” leva ao login/aluno.
- [ ] **iOS/Safari:** Abrir `/install` → aparece instrução “Adicionar à Tela de Início”; seguir e ver ícone na home.
- [ ] **Desktop/Chrome:** Abrir `/install` → “Instalar” ou “Abrir app” conforme installability.
- [ ] **wa.me:** Na tela pós-matrícula (e em matricula_show), “Enviar no WhatsApp” abre o chat com o número do aluno e o texto certo (incluindo o link único).
- [ ] **Copiar link:** “Copiar link” cola a URL canônica; feedback “Link copiado” aparece.
- [ ] **QR (se implementado):** “Ver QR Code” mostra QR que, ao escanear, abre `/install`.
- [ ] **Sem regressão:** Login, matrícula, financeiro, SW e manifest seguem iguais; nenhuma rota protegida vira pública.

---

**Fim do plano.** Implementação deve seguir estes passos na ordem das fases A e B; a fase C é opcional e pode ser feita em iteração seguinte.
