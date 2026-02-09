# Sugestões de Restrições para SECRETARIA (Mínimo Impacto)

**Objetivo:** Definir o que a SECRETARIA pode e não pode acessar, garantindo que:
- Menus mostrem apenas o que ela pode usar
- Acesso direto por URL também respeite as restrições (não é só esconder botão)

**Data:** 2025-02-09  
**Status:** Implementado (parcial) + Sugestões restantes

---

## 1. Resumo Executivo

| Categoria | O que restringir | Impacto | Prioridade |
|-----------|------------------|---------|------------|
| **Menus** | Já parcialmente feito em `shell.php` | Baixo | — |
| **Rotas (URL)** | Instrutores, Veículos, Serviços, Usuários | Médio | Alta |
| **Ações/Botões** | Excluir matrícula (já feito) | — | — |
| **admin/index.php** | Instrutores, Veículos, Salas, Serviços (dentro de Acadêmico) | Baixo | Média |

---

## 2. Situação Atual por Módulo

### 2.1 O que SECRETARIA já NÃO vê (menu shell.php)

- Instrutores
- Veículos
- Serviços
- Usuários
- Disciplinas
- Cursos Teóricos
- CFC (Configurações)
- Configurações gerais

### 2.2 O que SECRETARIA vê e PODE usar (operacional)

- Dashboard
- Alunos (CRUD, matrículas)
- Agenda
- Turmas Teóricas
- Financeiro
- Comunicados

### 2.3 Proteção por URL (backend)

| Rota / Módulo | Proteção atual | Sugestão |
|---------------|----------------|----------|
| `/usuarios/*` | `PermissionService` + `ADMIN` bypass | ✅ Manter — `role_permissoes` não dá `usuarios` para SECRETARIA |
| `/configuracoes/*` | `ConfiguracoesController::__construct` bloqueia não-ADMIN | ✅ OK |
| `/instrutores/*` | **Nenhuma** | ⚠️ Bloquear SECRETARIA |
| `/veiculos/*` | **Nenhuma** | ⚠️ Bloquear SECRETARIA |
| `/servicos/*` | `PermissionService::check('servicos','view')` | ⚠️ Verificar — se `view` ≠ `listar`, pode já estar bloqueado; senão, bloquear |
| `/matriculas/{id}/excluir` | `AlunosController` — apenas ADMIN | ✅ OK |
| `/matriculas/{id}/excluir-definitivamente` | `AlunosController` — apenas ADMIN | ✅ OK |
| `/notificacoes/excluir-historico` | `NotificationsController` — apenas ADMIN | ✅ OK |

---

## 3. Sugestões com Mínimo Impacto

### 3.1 Rotas — adicionar RoleMiddleware (ADMIN only)

**Onde:** `app/routes/web.php`

**Sugestão:** Usar `RoleMiddleware` nas rotas que devem ser exclusivas de ADMIN:

```php
// Exemplo (não implementar ainda):
use App\Middlewares\RoleMiddleware;

// Instrutores (apenas ADMIN)
$router->get('/instrutores', [InstrutoresController::class, 'index'], [AuthMiddleware::class, new RoleMiddleware('ADMIN')]);
// ... demais rotas de instrutores

// Veículos (apenas ADMIN)
$router->get('/veiculos', [VeiculosController::class, 'index'], [AuthMiddleware::class, new RoleMiddleware('ADMIN')]);
// ... demais rotas de veículos

// Serviços (apenas ADMIN) — se ainda não estiver bloqueado
$router->get('/servicos', [ServicosController::class, 'index'], [AuthMiddleware::class, new RoleMiddleware('ADMIN')]);
// ...
```

**Alternativa (menos invasiva):** Em cada controller, no `__construct` ou no início do método:

```php
if (($_SESSION['current_role'] ?? '') !== Constants::ROLE_ADMIN) {
    $_SESSION['error'] = 'Acesso restrito ao administrador.';
    redirect(base_url('dashboard'));
}
```

**Arquivos a ajustar:**
- `InstrutoresController.php` — adicionar checagem no construtor
- `VeiculosController.php` — adicionar checagem no construtor
- `ServicosController.php` — garantir checagem (já usa PermissionService; validar se `view`/`listar` está correto para SECRETARIA)

---

### 3.2 admin/index.php — menu legado

**Contexto:** O painel legado (`admin/index.php`) usa `$isAdmin || $user['tipo'] === 'secretaria'` para vários itens. O submenu **Acadêmico** inclui:

- Turmas Teóricas ✅ (manter para SECRETARIA)
- Aulas Práticas ✅
- Agenda Geral ✅
- **Instrutores** ⚠️ (SECRETARIA vê hoje)
- **Veículos** ⚠️ (SECRETARIA vê hoje)
- **Salas** ⚠️ (SECRETARIA vê hoje)

**Sugestão:** Trocar para exibir apenas para ADMIN:

```php
// Em vez de: $isAdmin || $user['tipo'] === 'secretaria'
// Usar para Instrutores, Veículos, Salas: apenas $isAdmin
```

**Linhas aproximadas em `admin/index.php`:**
- 1672–1679: link Instrutores
- 1676–1680: link Veículos
- 1681–1685: link Salas (configuracoes-salas)

**Impacto:** SECRETARIA deixa de ver esses itens no submenu Acadêmico, mas continua com Turmas Teóricas, Aulas Práticas e Agenda Geral.

---

### 3.3 Banco de dados — role_permissoes

**Situação:** `001_seed_initial_data.sql` define SECRETARIA com:

```sql
WHERE modulo IN ('alunos', 'matriculas', 'agenda', 'financeiro', 'servicos')
```

**Sugestão:** Remover `servicos` da SECRETARIA para alinhar com o que o plano estratégico prevê (Admin Master gerencia serviços/configurações):

```sql
-- Em vez de incluir 'servicos', usar apenas:
WHERE modulo IN ('alunos', 'matriculas', 'agenda', 'financeiro')
```

**Impacto:** Permissões de serviços passam a ser exclusivas de ADMIN. Preferência: fazer isso **em conjunto** com a proteção de rotas/controllers para evitar inconsistências.

---

### 3.4 Ações específicas — já implementadas

| Ação | Onde | Status |
|------|------|--------|
| Excluir matrícula | `AlunosController::excluirMatricula` | ✅ Bloqueado para não-ADMIN |
| Excluir matrícula definitivamente | `AlunosController::excluirDefinitivamente` | ✅ Bloqueado para não-ADMIN |
| Botão "Excluir Matrícula" na view | `matricula_show.php` | ✅ Oculto para não-ADMIN |
| Financeiro: link matrícula cancelada | `financeiro/index.php` | ✅ Oculto para não-ADMIN |
| Notificações: excluir histórico | `NotificationsController` | ✅ Bloqueado para não-ADMIN |
| **Editar valores após cobrança gerada** | `AlunosController::atualizarMatricula` | ✅ Bloqueado para SECRETARIA (apenas ADMIN) |
| **Form matrícula (desconto, acréscimo, entrada, etc.)** | `matricula_show.php` | ✅ Readonly/desabilitado para SECRETARIA quando `billing_status` = generated/canceled |
| **Usuários (admin legado)** | `admin/api/usuarios.php` | ✅ POST: SECRETARIA não cria admin; PUT: não edita nem atribui admin; DELETE: só ADMIN; reset_password: SECRETARIA não redefini admin |
| **Usuários (admin/pages/usuarios.php)** | `admin/pages/usuarios.php` | ✅ Botão Excluir oculto para SECRETARIA; Editar/Senha ocultos para usuários admin; opção Admin oculta no form |
| **Relatórios financeiros/gerenciais** | `admin/pages/financeiro-relatorios.php`, `admin/api/financeiro-relatorios.php` | ✅ Apenas ADMIN; SECRETARIA não vê menu nem acessa URL; API bloqueada |
| **Excluir matrícula (API legada)** | `admin/api/matriculas.php` DELETE | ✅ Apenas ADMIN; SECRETARIA recebe 403 em requisição direta |
| **Excluir matrícula / Excluir definitivamente (UI)** | `matricula_show.php`, `financeiro/index.php` | ✅ Botões ocultos para não-ADMIN; fallback para `user_type`/`tipo` legado |

---

### 3.5 Relatórios — Regras SECRETARIA (implementado)

| Relatório | Visível para SECRETARIA | Motivo |
|-----------|-------------------------|--------|
| Frequência Teórica | ✅ Sim | Operacional (acompanhamento de turmas) |
| Conclusão Prática | ✅ Sim (em dev) | Operacional |
| Provas (Taxa Aprovação) | ✅ Sim (em dev) | Operacional |
| **Relatórios Financeiros** (receitas, despesas, saldo) | ❌ Não | Sensível — apenas ADMIN |
| **Inadimplência** | ❌ Não | Sensível — apenas ADMIN |

**Proteções:** `rotasBloqueadasSecretaria` inclui `financeiro-relatorios`; página e API checam `$isAdmin`; menu e flyout ocultam itens para SECRETARIA.

---

### 3.6 Botões de ações críticas (implementado)

Em telas de detalhe (aluno, matrícula, aula), botões proibidos para SECRETARIA:
- **Não aparecem** na UI (condição `$isAdmin` ou `current_role === ADMIN`)
- **Não executam** por requisição direta (backend retorna 403)

| Ação | Tela | UI | Backend |
|------|------|-----|---------|
| Excluir Matrícula | matricula_show | Oculta para não-ADMIN | AlunosController::excluirMatricula, admin/api/matriculas DELETE |
| Excluir Definitivamente | matricula_show, financeiro | Oculta para não-ADMIN | AlunosController::excluirDefinitivamente |
| Excluir Usuário | usuarios (admin e app) | Oculta para SECRETARIA | admin/api/usuarios DELETE, UsuariosController::excluir |

**Cancelar aula:** SECRETARIA pode (operacional). **Desativar aluno:** SECRETARIA pode (operacional).

---

### 3.6.1 Padrão de mensagens e logs de bloqueio

| Onde | Mensagem exibida | Log |
|------|------------------|-----|
| Flash/redirect (admin) | "Você não tem permissão." | `error_log('[BLOQUEIO] contexto: ...')` |
| API JSON 403 | `error: "Você não tem permissão."` | `error_log('[BLOQUEIO] contexto: ...')` |
| $_SESSION['error'] | "Você não tem permissão." | `error_log('[BLOQUEIO] contexto: ...')` |

Formato do log: `[BLOQUEIO] contexto: tipo=X, user_id=Y` (ou `page=Z` quando aplicável).

---

### 3.7 Financeiro — Regras SECRETARIA (implementado)

| O que SECRETARIA pode | O que SECRETARIA NÃO pode |
|-----------------------|---------------------------|
| Lançar pagamento (marcar como pago) | Editar valores após cobrança gerada |
| Gerar cobrança (PIX/Boleto) | Excluir matrícula |
| Sincronizar cobranças | Excluir matrícula definitivamente |
| Consultar financeiro | Alterar desconto, acréscimo, entrada, parcelas quando cobrança já gerada |
| Alterar valores antes de gerar cobrança | Consultar relatórios financeiros e inadimplência |

**Permissões:** compatibilidade `enrollments` (seed 002) ou `matriculas` (seed 001).

---

## 4. Fluxo de implementação — status

- [x] **Rotas / Controllers (backend):** Instrutores, Veículos, Serviços — bloqueados para SECRETARIA
- [x] **Menu legado (admin/index.php):** Instrutores, Veículos, Salas — apenas ADMIN
- [x] **Bloqueio URL admin:** `rotasBloqueadasSecretaria` para instrutores, veiculos, configuracoes-salas, servicos, financeiro-relatorios
- [x] **Financeiro:** SECRETARIA não edita valores após cobrança gerada
- [x] **Relatórios:** Relatórios Financeiros e Inadimplência apenas ADMIN
- [x] **Botões críticos:** Excluir matrícula/definitivamente e excluir usuário ocultos na UI e bloqueados no backend
- [x] **Permissões:** Alinhamento `matriculas`/`enrollments` em `atualizarMatricula`
- [ ] **Seeds (opcional):** Remover `servicos` de `role_permissoes` para SECRETARIA

**Validação:** Testar com usuário SECRETARIA: URL direta, menu, edição de matrícula com cobrança gerada.

---

## 5. Referências

- `admin/pages/_PLANO-SISTEMA-CFC.md` — Admin Master vs Admin Secretaria
- `docs/ANALISE_SISTEMA_USUARIOS_PERMISSOES.md` — Perfis e permissões
- `app/Views/layouts/shell.php` — `getMenuItems()` para ADMIN vs SECRETARIA
- `app/Middlewares/RoleMiddleware.php` — Middleware existente para restrição por role
- `app/Config/Constants.php` — `ROLE_ADMIN`, `ROLE_SECRETARIA`
