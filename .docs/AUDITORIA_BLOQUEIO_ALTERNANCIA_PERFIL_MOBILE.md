# Auditoria: Bloquear alternância de perfil no mobile

**Data:** 2025-02-02  
**Objetivo:** No mobile/PWA, exibir apenas "INSTRUTOR" sem dropdown; desktop mantém seletor.

---

## 1. Onde o seletor de perfil é renderizado

| Item | Localização |
|------|-------------|
| **Arquivo** | `app/Views/layouts/shell.php` |
| **Linhas** | 98-157 |
| **Bloco** | `<?php if ($hasMultipleRoles): ?>` → `<div class="topbar-role-selector">` |
| **Elementos** | Botão com "Modo: X" / "X" (desktop/mobile), dropdown com itens por role |

---

## 2. Variáveis de sessão utilizadas

| Variável | Uso |
|----------|-----|
| `$_SESSION['available_roles']` | Lista de papéis do usuário (ex.: ADMIN, INSTRUTOR) |
| `$_SESSION['active_role']` | Modo ativo (prioridade) |
| `$_SESSION['current_role']` | Fallback legado; usado em `getMenuItems()` e comparações |

**Leitura em shell.php:** linhas 101-133.

---

## 3. Regra "mobile prioriza INSTRUTOR"

| Local | Situação |
|-------|----------|
| `instrutor/app.php` | Apenas ao acessar `/instrutor/` (PWA entry). Força `active_role = INSTRUTOR`. |
| **Não existe** | `isMobileOrPwaInstrutor()` ou `applyMobileInstructorPreference()` |
| **Não existe** | Lógica de mobile no login ou no fluxo principal (public_html) |

**Conclusão:** Ao entrar pelo app principal (public_html), não há forçar INSTRUTOR em mobile.

---

## 4. Detecção de mobile

| Método | Onde existe |
|--------|-------------|
| CSS `@media (max-width: 768px)` | `layout.css` (sidebar, role-label-mobile) |
| JS `window.innerWidth <= 768` | `app.js` (sidebar toggle) |
| User-Agent | `login.php` (evitar auto-focus em mobile) |

**Recomendação:** Usar User-Agent no PHP para garantir sessão correta antes do render.

---

## 5. Cache / PWA

| Item | Observação |
|------|------------|
| shell.php | HTML dinâmico; não deve ser cacheado pelo SW (AuthMiddleware já envia `Cache-Control: no-store`) |
| layout.css, app.js | Versionados com `?v=filemtime` em `asset_url()` |
| **Ação** | Não é necessário bust manual; alterações em shell.php são servidas direto |

---

## 6. Arquivos envolvidos (resumo)

| Arquivo | Alteração |
|---------|-----------|
| `app/Views/layouts/shell.php` | Condição mobile: renderizar só label "INSTRUTOR" (sem dropdown) |
| `app/Bootstrap.php` | Helper `is_mobile_request()` |
| `app/Middlewares/AuthMiddleware.php` | Forçar `active_role = INSTRUTOR` em mobile quando usuário tiver INSTRUTOR |

---

## 7. Condição para "mobile"

```php
// User-Agent (mesmo padrão do login.php)
preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $_SERVER['HTTP_USER_AGENT'] ?? '')
```

---

## 8. Onde aplicar a mudança (menor impacto)

1. **AuthMiddleware** – após validar sessão: se mobile + INSTRUTOR em `available_roles` → `active_role` e `current_role` = INSTRUTOR.
2. **shell.php** – no bloco do seletor: se mobile + `hasMultipleRoles` + INSTRUTOR em roles → renderizar só `<span>INSTRUTOR</span>`; senão, manter dropdown atual.

---

## 9. Pontos de teste

| Cenário | Esperado |
|---------|----------|
| Desktop, ADMIN+INSTRUTOR | Seletor visível, alternância funciona |
| Mobile, ADMIN+INSTRUTOR | Só label "INSTRUTOR", sem dropdown |
| Mobile, sessão era ADMIN | Ao carregar, sessão vira INSTRUTOR |
| Mobile, só ADMIN | Sem seletor (hasMultipleRoles=false) |
| Mobile, ADMIN+SECRETARIA | Sem seletor (não tem INSTRUTOR, não inventar) |
| Aluno | Sem mudança (painel único) |
