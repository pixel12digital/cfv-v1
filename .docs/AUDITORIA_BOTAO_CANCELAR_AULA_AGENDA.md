# Auditoria: Botão "Cancelar Aula" — Detalhes da Aula (/agenda/{id}) — Modo Instrutor

**Data:** 05/02/2025  
**Escopo:** Comportamento atual do botão "Cancelar Aula" na tela Detalhes da Aula, especialmente em Modo Instrutor.  
**Tipo:** Diagnóstico e mapeamento — sem alteração de lógica.

---

## 1) Onde o botão está definido e para onde aponta

### View/Template

| Item | Detalhe |
|------|---------|
| **Arquivo** | `app/Views/agenda/show.php` |
| **Seção** | Card "Ações" (linhas 354–385) |
| **Condição de exibição** | `<?php if (!$isAluno): ?>` — visível para **INSTRUTOR** e **ADMIN/SECRETARIA** |
| **Condição do botão** | `<?php if (in_array($lesson['status'], ['agendada', 'em_andamento'])): ?>` — só aparece para aulas agendadas ou em andamento |

**Trecho relevante (linhas 376–381):**

```php
<?php if (in_array($lesson['status'], ['agendada', 'em_andamento'])): ?>
    <!-- Cancelar Aula -->
    <button type="button" class="btn btn-outline btn-danger" style="width: 100%;" onclick="showCancelModal()">
        Cancelar Aula
    </button>
<?php endif; ?>
```

O botão **não** faz submit direto. Ele abre um modal (`showCancelModal()`).

### Modal de cancelamento (linhas 476–499)

- **Formulário:** `method="POST"` → `action="<?= base_path("agenda/{$lesson['id']}/cancelar") ?>"`
- **Campo opcional:** `name="reason"` (textarea) — motivo do cancelamento
- **Botão de envio:** "Confirmar Cancelamento"

### Rota / Controller / Método

| Item | Valor |
|------|-------|
| **Rota** | `POST /agenda/{id}/cancelar` |
| **Arquivo de rotas** | `app/routes/web.php` (linha 91) |
| **Controller** | `AgendaController` |
| **Action** | `cancelar($id)` |
| **Método HTTP** | **POST** |

### Middlewares / Guards

| Camada | Detalhe |
|--------|---------|
| **Rota** | `[AuthMiddleware::class]` — exige usuário autenticado |
| **Controller** | Bloqueia apenas **ALUNO**; INSTRUTOR e ADMIN/SECRETARIA podem cancelar |
| **Validação RBAC** | Não há checagem de que o INSTRUTOR só cancela suas próprias aulas (diferente de `iniciar` e `concluir`) |

---

## 2) O que acontece ao clicar (comportamento real)

### Tipo de operação

**Soft cancel** — o registro permanece na tabela `lessons`; apenas o status e campos relacionados são alterados. Não há `DELETE`.

### Fluxo no controller

**Arquivo:** `app/Controllers/AgendaController.php` — método `cancelar` (linhas 556–631)

1. Bloqueia ALUNO
2. Exige POST e CSRF
3. Carrega aula com `findWithDetails($id)`
4. Verifica se aula existe e pertence ao CFC
5. Impede cancelar se status já for `concluida` ou `cancelada`
6. Lê `$_POST['reason']`; se vazio, usa `"Sem motivo informado"`
7. Atualiza a aula via `$lessonModel->update($id, $updateData)`
8. Registra no histórico do aluno (`StudentHistoryService::logAgendaEvent`)
9. Registra auditoria (`AuditService::logUpdate`)
10. Redireciona para `base_url('agenda')` com mensagem de sucesso

### Campos gravados/alterados

| Campo | Valor |
|-------|-------|
| `status` | `Constants::AULA_CANCELADA` → `'cancelada'` |
| `canceled_at` | `date('Y-m-d H:i:s')` |
| `canceled_by` | `$_SESSION['user_id']` |
| `cancel_reason` | Motivo informado ou `"Sem motivo informado"` |
| `notes` | Se houver motivo, concatena: `"Cancelada em DD/MM/YYYY HH:mm. Motivo: {reason}"` |

**Trecho (linhas 800–817):**

```php
$updateData = [
    'status' => Constants::AULA_CANCELADA,
    'canceled_at' => date('Y-m-d H:i:s'),
    'canceled_by' => $_SESSION['user_id'] ?? null,
    'cancel_reason' => $reason
];
if ($reason && $reason !== 'Sem motivo informado') {
    $updateData['notes'] = ($lesson['notes'] ? $lesson['notes'] . "\n\n" : '') . "Cancelada em " . date('d/m/Y H:i') . ". Motivo: {$reason}";
}
$lessonModel->update($id, $updateData);
```

### Schema (migration 015)

**Arquivo:** `database/migrations/015_add_lesson_cancellation_fields.sql`

- `canceled_at` (timestamp)
- `canceled_by` (int, FK `usuarios.id`)
- `cancel_reason` (text)

---

## 3) Impacto em listagens e histórico

### Instrutor — Aba "Próximas"

- **Query:** `Lesson::findByInstructorWithTheoryDedupe` com `tab=proximas`
- **Filtro:** `status IN ('agendada', 'em_andamento')` e data/hora futura
- **Efeito:** Aula cancelada **não aparece** em "Próximas"

### Instrutor — Aba "Histórico"

- **Filtro:** `status IN ('concluida', 'cancelada', 'no_show')`
- **Efeito:** Aula cancelada **aparece** em "Histórico"

### Instrutor — Aba "Todas"

- **Filtro:** `tab=todas` sem filtro de status
- **Efeito:** Aula cancelada **aparece** em "Todas"

### Aluno

- **Query:** `Lesson::findByStudent($studentId)` — sem filtro de status
- **Efeito:** Aula cancelada **aparece** na lista do aluno (com badge "Cancelada")

### Acesso via URL

- **Rota:** `GET /agenda/{id}` → `AgendaController::show`
- **Comportamento:** Aula cancelada **continua acessível** via URL; não retorna 404
- **View:** Exibe status "Cancelada", dados de cancelamento e motivo; não exibe botões de ação (Iniciar/Concluir/Cancelar)

### Queries / telas que filtram por status

| Local | Filtro | Resultado para cancelada |
|-------|--------|--------------------------|
| `Lesson::findByInstructorWithTheoryDedupe` (tab=proximas) | `status IN ('agendada','em_andamento')` | excluída |
| `Lesson::findByInstructorWithTheoryDedupe` (tab=historico) | `status IN ('concluida','cancelada','no_show')` | incluída |
| `Lesson::findByPeriod` | `status != 'cancelada'` (padrão) | excluída |
| `Lesson::findByPeriodWithTheoryDedupe` (admin) | `show_canceled` controla | depende do filtro |
| `Lesson::findByStudent` | nenhum | incluída |
| `Lesson::findByStudentAndDate` | `status != 'cancelada'` | excluída |

---

## 4) Modal / confirmação / motivo

### Confirmação

- Existe modal de confirmação antes do envio
- Usuário clica em "Cancelar Aula" → modal abre → preenche motivo (opcional) → clica em "Confirmar Cancelamento"

### Motivo de cancelamento

- **Backend:** Campo `reason` em `$_POST`; gravado em `cancel_reason` e, se aplicável, em `notes`
- **UI:** Textarea opcional com label "Motivo do cancelamento (opcional)"
- **Valor padrão:** Se vazio, usa `"Sem motivo informado"`

### Suporte a motivo

- **Backend:** Suporte completo
- **UI:** Campo de motivo já presente no modal
- **Banco:** `cancel_reason` existe e é preenchido

---

## 5) Resumo executivo

| Item | Valor |
|------|-------|
| **Rota** | `POST /agenda/{id}/cancelar` |
| **Controller** | `AgendaController::cancelar` |
| **Tipo** | Soft cancel |
| **Campos afetados** | `status`, `canceled_at`, `canceled_by`, `cancel_reason`, `notes` (opcional) |
| **Status** | `cancelada` |
| **Após cancelar** | Some de "Próximas"; aparece em "Histórico" e "Todas"; continua acessível via `/agenda/{id}` |
| **Modal de confirmação** | Sim |
| **Motivo no backend** | Sim |
| **Motivo na UI** | Sim (textarea opcional) |

### Observação de segurança

O método `cancelar` não valida se o INSTRUTOR está cancelando apenas suas próprias aulas. `iniciar` e `concluir` fazem essa checagem; `cancelar` não. Assim, um instrutor pode cancelar aulas de outros instrutores se acessar a URL diretamente (ex.: POST com ID de outra aula).
