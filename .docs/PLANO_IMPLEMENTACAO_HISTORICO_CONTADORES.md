# Plano de Implementação — Histórico do Instrutor + Contadores de Aulas

**Objetivo:** Corrigir "Histórico vazio" e implementar contadores no "Iniciar Aula" (instrutor) e no dashboard do aluno, conforme pedido do cliente.

**Restrições:** Mínima intervenção, sem novas telas, preservando lógica existente.

---

## Decisões de Arquitetura (fechadas)

### 1. O contador "com este aluno" deve considerar o quê?

| Decisão | Valor |
|---------|-------|
| **Tipos de aula** | **Somente práticas** (`type = 'pratica'` ou `type IS NULL` ou `theory_session_id IS NULL`). Teóricas têm dinâmica diferente (sessão com vários alunos); contar práticas é o que faz sentido para o fluxo de "iniciar aula prática". |
| **Escopo** | **Somente mesma matrícula** (`enrollment_id`). Se o aluno tiver múltiplas matrículas (ex.: adição de categoria), cada contexto é separado. Isso evita misturar históricos de cursos diferentes. |
| **Instrutor** | Para a visão do **instrutor**: "com este aluno" = aulas deste instrutor + este aluno + esta matrícula. Para o **aluno**: todas as aulas dele (qualquer instrutor) na mesma matrícula. |

### 2. "Próximas agendadas: N" — escopo

| Contexto | Regra |
|----------|-------|
| **Visão do instrutor** (na tela "Iniciar Aula") | N = aulas futuras deste **aluno** (qualquer instrutor) na mesma **matrícula**. Motivo: o instrutor quer saber quantas aulas o aluno ainda tem agendadas, não só com ele. |
| **Visão do aluno** (no dashboard) | N = aulas futuras deste **aluno** (qualquer instrutor) na mesma **matrícula**. Se o aluno tiver mais de uma matrícula ativa, somar ou mostrar por matrícula (decidir na implementação — recomendo somar por simplicidade). |

### 3. Cenário A/B — fonte de dados

**A validar na Fase 0.** O script `tools/diagnostico_fase0_historico.php` foi criado para isso.

- **Se Cenário A** (lessons tem histórico): prosseguir com Fases 1–4.
- **Se Cenário B** (lessons vazio, aulas tem histórico): antes das Fases 1–4, garantir que o fluxo que o instrutor usa atualize `lessons`. Se for só o legado, avaliar: (a) migrar conclusões de aulas para lessons, ou (b) ler de aulas no contador (menos ideal).

---

## Fases de Implementação

### Fase 0 — Validação de Fonte (obrigatória, ~10 min)

**Ação:** Rodar o diagnóstico no ambiente.

```bash
# Via browser
http://seusite/tools/diagnostico_fase0_historico.php?instrutor_id=<ID_ROBSON>&aluno_id=<ID_CARLOS>

# Via CLI
php tools/diagnostico_fase0_historico.php <ID_ROBSON> <ID_CARLOS>
```

**Entrega:** Resposta objetiva: "Cenário A" ou "Cenário B".

---

### Fase 1 — Corrigir "Histórico vazio" (~30 min)

**Problema:** Em `AgendaController::index()`, para instrutor em view=list, `$startDate = $endDate = $date` (um único dia, hoje por padrão). Isso faz tab=historico mostrar só aulas concluídas de hoje.

**Solução:** Para `tab=historico` (e opcionalmente `tab=todas`), **não** restringir a um único dia.

**Arquivo:** `app/Controllers/AgendaController.php`

**Lógica proposta:**

```php
// Linha ~85-115 (bloco de cálculo de período para view=list)
// ANTES: sempre $startDate = $date, $endDate = $date para não-aluno

// DEPOIS (para instrutor):
if ($isInstrutor && $view === 'list') {
    if ($tab === 'proximas') {
        // Próximas: de hoje em diante (sem limite superior)
        $startDate = $dateFromUrl ?: date('Y-m-d');
        $endDate = null; // ou data futura ampla, ex.: +6 meses
    } elseif ($tab === 'historico') {
        // Histórico: sem restrição de data (ou últimos 12 meses)
        $startDate = null; // ou date('Y-m-d', strtotime('-12 months'))
        $endDate = date('Y-m-d'); // até hoje
    } else { // tab=todas
        // Todas: sem restrição ou período amplo
        $startDate = null;
        $endDate = null;
    }
}
```

**Em `Lesson::findByInstructorWithTheoryDedupe()`:** Já trata `start_date`/`end_date` opcionais (só adiciona BETWEEN se ambos forem fornecidos). Se passarmos `null`, não aplica filtro de data.

**Critérios de aceitação:**
- [ ] Instrutor abre `/agenda?view=list&tab=historico` → vê aulas concluídas (sem precisar setar date).
- [ ] Instrutor abre `/agenda?view=list&tab=proximas` → continua funcionando (aulas futuras).
- [ ] Instrutor abre `/agenda?view=list&tab=todas` → vê todas (sem filtro de data restritivo).
- [ ] Nenhuma regressão para aluno ou admin.

---

### Fase 2 — Contador no "Iniciar Aula" (~1h)

**Onde:** `app/Views/agenda/iniciar.php`

**O que mostrar:**

```
┌─────────────────────────────────────────────────────────────┐
│ 📊 Com este aluno: 5 aulas concluídas • Última: 28/01 14:00 │
│    Próximas agendadas: 3                                    │
└─────────────────────────────────────────────────────────────┘
```

Ou, se não houver histórico:

```
┌─────────────────────────────────────────────────────────────┐
│ 📊 Com este aluno: 0 aulas concluídas • Sem aulas anteriores│
│    Próximas agendadas: 2                                    │
└─────────────────────────────────────────────────────────────┘
```

**Backend — dados necessários:**

No `AgendaController::iniciar()` (GET), além de `$lesson`, passar:

```php
$studentSummary = $lessonModel->getStudentSummaryForInstructor(
    $lesson['instructor_id'],
    $lesson['student_id'],
    $lesson['enrollment_id']
);

$data = [
    'pageTitle' => 'Iniciar Aula',
    'lesson' => $lesson,
    'studentSummary' => $studentSummary
];
```

**Novo método em `Lesson.php`:**

```php
/**
 * Resumo do aluno para exibir ao instrutor antes de iniciar aula
 */
public function getStudentSummaryForInstructor($instructorId, $studentId, $enrollmentId)
{
    // Aulas concluídas deste instrutor com este aluno nesta matrícula (só práticas)
    $completed = $this->query(
        "SELECT COUNT(*) as total 
         FROM {$this->table} 
         WHERE instructor_id = ? 
           AND student_id = ? 
           AND enrollment_id = ?
           AND status = 'concluida'
           AND (type = 'pratica' OR type IS NULL OR theory_session_id IS NULL)",
        [$instructorId, $studentId, $enrollmentId]
    )->fetch();
    
    // Última aula concluída
    $lastLesson = $this->query(
        "SELECT scheduled_date, scheduled_time 
         FROM {$this->table} 
         WHERE instructor_id = ? 
           AND student_id = ? 
           AND enrollment_id = ?
           AND status = 'concluida'
           AND (type = 'pratica' OR type IS NULL OR theory_session_id IS NULL)
         ORDER BY scheduled_date DESC, scheduled_time DESC 
         LIMIT 1",
        [$instructorId, $studentId, $enrollmentId]
    )->fetch();
    
    // Próximas agendadas do aluno nesta matrícula (qualquer instrutor)
    $upcoming = $this->query(
        "SELECT COUNT(*) as total 
         FROM {$this->table} 
         WHERE student_id = ? 
           AND enrollment_id = ?
           AND status IN ('agendada', 'em_andamento')
           AND (scheduled_date > CURDATE() OR (scheduled_date = CURDATE() AND scheduled_time > CURTIME()))
           AND (type = 'pratica' OR type IS NULL OR theory_session_id IS NULL)",
        [$studentId, $enrollmentId]
    )->fetch();
    
    return [
        'completed_count' => (int)($completed['total'] ?? 0),
        'last_lesson_date' => $lastLesson ? $lastLesson['scheduled_date'] : null,
        'last_lesson_time' => $lastLesson ? $lastLesson['scheduled_time'] : null,
        'upcoming_count' => (int)($upcoming['total'] ?? 0)
    ];
}
```

**Frontend — na view `agenda/iniciar.php`:**

Adicionar bloco entre o card "Informações da Aula" e o card "Dados para Início":

```php
<?php if (isset($studentSummary)): ?>
<div class="card" style="margin-bottom: var(--spacing-md); background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-left: 4px solid var(--color-primary, #3b82f6);">
    <div class="card-body" style="padding: var(--spacing-md);">
        <div style="display: flex; align-items: center; gap: var(--spacing-sm); margin-bottom: var(--spacing-xs);">
            <span style="font-size: 1.25rem;">📊</span>
            <strong>Com este aluno</strong>
        </div>
        <div style="font-size: 0.95rem; color: var(--color-text, #333);">
            <?php 
            $count = $studentSummary['completed_count'];
            $lastDate = $studentSummary['last_lesson_date'];
            $lastTime = $studentSummary['last_lesson_time'];
            $upcoming = $studentSummary['upcoming_count'];
            ?>
            <strong><?= $count ?></strong> aula<?= $count !== 1 ? 's' : '' ?> concluída<?= $count !== 1 ? 's' : '' ?>
            <?php if ($lastDate): ?>
                • Última: <?= date('d/m', strtotime($lastDate)) ?> às <?= date('H:i', strtotime($lastTime)) ?>
            <?php else: ?>
                • Sem aulas anteriores
            <?php endif; ?>
        </div>
        <div style="font-size: 0.875rem; color: var(--color-text-muted, #666); margin-top: var(--spacing-xs);">
            Próximas agendadas: <strong><?= $upcoming ?></strong>
        </div>
    </div>
</div>
<?php endif; ?>
```

**Critérios de aceitação:**
- [ ] Ao abrir "Iniciar Aula", o bloco aparece com contador correto.
- [ ] Se não houver histórico, mostra "0 aulas concluídas • Sem aulas anteriores".
- [ ] Próximas agendadas reflete aulas futuras do aluno naquela matrícula.
- [ ] Não quebra nada no fluxo de iniciar (KM, submit, etc.).

---

### Fase 3 — Bloco no "Detalhe da aula" (opcional, ~30 min)

**Onde:** `app/Views/agenda/show.php`

**O que:** Mesmo resumo da Fase 2, em bloco colapsável na seção "Ações" ou abaixo de "Informações Adicionais" (para instrutor/admin).

**Backend:** Mesmo método `getStudentSummaryForInstructor()`, chamado em `AgendaController::show()`.

**Frontend:** Bloco colapsável com `<details>` ou JS simples.

**Critérios de aceitação:**
- [ ] Instrutor/admin vê o resumo ao abrir detalhes da aula.
- [ ] Aluno **não** vê esse bloco (é contexto do instrutor).
- [ ] Bloco não polui a tela principal (colapsado por padrão ou discreto).

---

### Fase 4 — Painel do Aluno (~45 min)

**Onde:** `app/Views/dashboard/aluno.php`

**O que mostrar:**

```
┌─────────────────────────────────────────────────────────────┐
│ 📈 Seu progresso em aulas práticas                         │
│    Concluídas: 8  •  Próximas agendadas: 3                 │
└─────────────────────────────────────────────────────────────┘
```

**Backend — dados necessários:**

No `DashboardController::dashboardAluno()`, adicionar:

```php
$lessonSummary = $lessonModel->getStudentLessonSummary($studentId, $enrollment['id'] ?? null);

// Passar para a view
$data['lessonSummary'] = $lessonSummary;
```

**Novo método em `Lesson.php`:**

```php
/**
 * Resumo de aulas práticas para o aluno
 */
public function getStudentLessonSummary($studentId, $enrollmentId = null)
{
    $params = [$studentId];
    $enrollmentFilter = '';
    
    if ($enrollmentId) {
        $enrollmentFilter = ' AND enrollment_id = ?';
        $params[] = $enrollmentId;
    }
    
    // Concluídas (só práticas)
    $completed = $this->query(
        "SELECT COUNT(*) as total 
         FROM {$this->table} 
         WHERE student_id = ? 
           {$enrollmentFilter}
           AND status = 'concluida'
           AND (type = 'pratica' OR type IS NULL OR theory_session_id IS NULL)",
        $params
    )->fetch();
    
    // Próximas agendadas
    $paramsUpcoming = $params; // mesmo params
    $upcoming = $this->query(
        "SELECT COUNT(*) as total 
         FROM {$this->table} 
         WHERE student_id = ? 
           {$enrollmentFilter}
           AND status IN ('agendada', 'em_andamento')
           AND (scheduled_date > CURDATE() OR (scheduled_date = CURDATE() AND scheduled_time > CURTIME()))
           AND (type = 'pratica' OR type IS NULL OR theory_session_id IS NULL)",
        $paramsUpcoming
    )->fetch();
    
    // Última concluída
    $lastLesson = $this->query(
        "SELECT scheduled_date, scheduled_time 
         FROM {$this->table} 
         WHERE student_id = ? 
           {$enrollmentFilter}
           AND status = 'concluida'
           AND (type = 'pratica' OR type IS NULL OR theory_session_id IS NULL)
         ORDER BY scheduled_date DESC, scheduled_time DESC 
         LIMIT 1",
        $params
    )->fetch();
    
    return [
        'completed_count' => (int)($completed['total'] ?? 0),
        'upcoming_count' => (int)($upcoming['total'] ?? 0),
        'last_lesson_date' => $lastLesson ? $lastLesson['scheduled_date'] : null,
        'last_lesson_time' => $lastLesson ? $lastLesson['scheduled_time'] : null
    ];
}
```

**Frontend — na view `dashboard/aluno.php`:**

Adicionar bloco logo após "Status do Processo" ou antes de "Próxima Aula":

```php
<?php if (isset($lessonSummary) && ($lessonSummary['completed_count'] > 0 || $lessonSummary['upcoming_count'] > 0)): ?>
<div class="card" style="margin-bottom: var(--spacing-md); background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left: 4px solid var(--color-success, #10b981);">
    <div class="card-body" style="padding: var(--spacing-md);">
        <div style="display: flex; align-items: center; gap: var(--spacing-sm); margin-bottom: var(--spacing-xs);">
            <span style="font-size: 1.25rem;">📈</span>
            <strong>Seu progresso em aulas práticas</strong>
        </div>
        <div style="font-size: 0.95rem; color: var(--color-text, #333);">
            Concluídas: <strong><?= $lessonSummary['completed_count'] ?></strong>
            &nbsp;•&nbsp;
            Próximas agendadas: <strong><?= $lessonSummary['upcoming_count'] ?></strong>
        </div>
        <?php if ($lessonSummary['last_lesson_date']): ?>
        <div style="font-size: 0.8rem; color: var(--color-text-muted, #666); margin-top: var(--spacing-xs);">
            Última aula: <?= date('d/m/Y', strtotime($lessonSummary['last_lesson_date'])) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
```

**Critérios de aceitação:**
- [ ] Aluno vê "Concluídas: X • Próximas agendadas: N" no dashboard.
- [ ] Se não tiver aulas, bloco não aparece (ou mostra "0").
- [ ] Não interfere no restante do dashboard.

---

## Ordem de Execução

```
┌─────────────────────────────────────────────────────────────┐
│  Fase 0: Diagnóstico (obrigatório)                         │
│    ↓                                                        │
│  Cenário A? → Prosseguir                                   │
│  Cenário B? → Resolver fonte primeiro                      │
│    ↓                                                        │
│  Fase 1: Corrigir Histórico vazio                          │
│    ↓                                                        │
│  Fase 2: Contador no "Iniciar Aula" (pedido principal)     │
│    ↓                                                        │
│  Fase 3: Bloco no Detalhe (opcional)                       │
│    ↓                                                        │
│  Fase 4: Painel do Aluno (segunda metade do pedido)        │
└─────────────────────────────────────────────────────────────┘
```

---

## Arquivos Afetados

| Fase | Arquivo | Tipo de mudança |
|------|---------|-----------------|
| 1 | `app/Controllers/AgendaController.php` | Ajuste no cálculo de `$startDate`/`$endDate` para instrutor |
| 2 | `app/Controllers/AgendaController.php` | Adicionar busca de `studentSummary` em `iniciar()` |
| 2 | `app/Models/Lesson.php` | Novo método `getStudentSummaryForInstructor()` |
| 2 | `app/Views/agenda/iniciar.php` | Novo bloco HTML com contador |
| 3 | `app/Controllers/AgendaController.php` | Adicionar busca em `show()` |
| 3 | `app/Views/agenda/show.php` | Novo bloco colapsável |
| 4 | `app/Controllers/DashboardController.php` | Adicionar busca em `dashboardAluno()` |
| 4 | `app/Models/Lesson.php` | Novo método `getStudentLessonSummary()` |
| 4 | `app/Views/dashboard/aluno.php` | Novo bloco HTML com contador |

---

## Próximos Passos

1. **Rodar Fase 0** — executar `tools/diagnostico_fase0_historico.php` com ID do Robson Wagner e do Carlos Roberto.
2. **Me informar o resultado** — "Cenário A" ou "Cenário B".
3. **Aprovar plano** — confirmar que as decisões de arquitetura estão OK.
4. **Autorizar implementação** — vou começar pela Fase 1 e seguir em ordem.

**Este plano está completo e pronto para execução.**
