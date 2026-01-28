# Auditoria: Exibição de Aulas Concluídas no Histórico do Aluno

**Objetivo:** Diagnóstico (sem implementação) de como o sistema se comporta hoje em relação a aulas concluídas (teóricas e práticas) e onde elas aparecem para o aluno.

**Data:** 27/01/2025

---

## 1. Hoje funciona assim

### 1.1 Teórica

- **Sistema legado (turma_*):**  
  A tela **aluno/historico.php** (Meu Histórico) mostra apenas **presença teórica** baseada em:
  - `turma_matriculas` + `turmas_teoricas` + `turma_aulas_agendadas` + `turma_presencas`
  - Aulas exibidas: `turma_aulas_agendadas.status IN ('agendada', 'realizada')`
  - “Concluída” no legado = **status `realizada`** na aula agendada; presença vem de `turma_presencas` (presente/ausente/não registrado).

- **Sistema app (theory_* + lessons):**  
  Aulas teóricas do app vêm da tabela **lessons** (com `theory_session_id` e `type = 'teoria'`). O status de “concluída” é:
  - **theory_sessions.status = 'done'** (sessão encerrada)
  - **lessons.status = 'concluida'** (sincronizado quando a sessão é marcada como `done` em `TheoryAttendanceController` e `TheorySessionsController`)
  - Presença por aluno: **theory_attendance** (status: present, absent, justified, makeup).

- **Onde o aluno vê teórica concluída:**
  - **Legado:** em **aluno/historico.php** (só turmas/aulas do legado; status `realizada`).
  - **App:** em **Minha Agenda** (`/agenda`), na mesma lista que as demais aulas; teóricas concluídas aparecem com status “Concluída” (mapeado de `lessons.status = 'concluida'` ou, na view, de `done` → `concluida`).

### 1.2 Prática

- **Fonte:** tabela **lessons** (`type = 'pratica'` ou sem theory_session).
- **“Concluída”:** `lessons.status = 'concluida'` (constante `AULA_CONCLUIDA`). Definido quando o instrutor/secretaria conclui a aula (fluxo “Concluir Aula” em `AgendaController::concluir()`).
- **Onde o aluno vê:** apenas em **Minha Agenda** (`/agenda`). A lista é `Lesson::findByStudent($studentId)` **sem filtro de status**; portanto agendada, em_andamento, concluída, cancelada e no_show aparecem todas.

---

## 2. Onde aparece para o aluno

| Conteúdo | Tela / Rota | Observação |
|----------|-------------|------------|
| **Teórica (legado)** | **aluno/historico.php** (Meu Histórico) | Só turmas/aulas do legado (`turma_aulas_agendadas`). Status `agendada` e `realizada`. Sem link no menu do app; acesso por URL direta ou link em **aluno/presencas-teoricas.php**. |
| **Teórica (app)** | **/agenda** (Minha Agenda) | Aulas da tabela `lessons` com `theory_session_id`; status concluída quando `lessons.status = 'concluida'` (sincronizado com `theory_sessions.status = 'done'`). |
| **Prática** | **/agenda** (Minha Agenda) | Todas as aulas do aluno (qualquer status). Concluídas = `lessons.status = 'concluida'`. |
| **Histórico “admin”** | **alunos/{id}?tab=historico** | Histórico de eventos do aluno (matrícula, financeiro, agenda, etc.). Não é lista de aulas concluídas; é timeline de ações no cadastro. |

**Menu do aluno no app (shell):**  
Meu Progresso (`/dashboard`), Minha Agenda (`/agenda`), Financeiro. **Não há item “Histórico”** no menu; o histórico legado (aluno/historico.php) não está no menu do app.

---

## 3. Critérios de inclusão no histórico

### 3.1 Legado – aluno/historico.php (só teórica)

- **Turmas:** `turma_matriculas.status IN ('matriculado', 'cursando', 'concluido')`.
- **Aulas:** `turma_aulas_agendadas.status IN ('agendada', 'realizada')`.
- **Inclusão:** toda aula da turma que esteja agendada ou realizada; presença por aluno em `turma_presencas` (presente/ausente/não registrado).
- **Exclusão:** aulas com status diferente de agendada/realizada (ex.: cancelada) **não** entram na query, então **não aparecem**.

### 3.2 App – Minha Agenda (/agenda)

- **Fonte:** `Lesson::findByStudent($studentId)` — **sem filtro de status**.
- **Inclusão:** todas as aulas do aluno (lessons onde `student_id = ?`), qualquer status: agendada, em_andamento, concluida, cancelada, no_show.
- **Ordenação:** por `scheduled_date DESC`, `scheduled_time DESC`.
- **Abas:** para **aluno** o parâmetro `tab` é fixo em `todas`; não existe aba “Histórico” para aluno (apenas para instrutor: Próximas, Histórico, Todas).

### 3.3 Resumo de status

| Contexto | “Concluída” | Outros status no histórico/lista |
|----------|-------------|-----------------------------------|
| **Prática (app)** | `lessons.status = 'concluida'` | agendada, em_andamento, cancelada, no_show — todos aparecem na agenda do aluno. |
| **Teórica (app)** | `lessons.status = 'concluida'` (sincronizado de `theory_sessions.status = 'done'`) | agendada, em_andamento, cancelada, no_show — todos na mesma lista. |
| **Teórica (legado)** | `turma_aulas_agendadas.status = 'realizada'` | só `agendada` e `realizada`; canceladas não entram na query. |

---

## 4. Regras atuais: concluída x realizada x encerrada

- **Prática:**  
  - **Concluída** = aula finalizada pelo instrutor/secretaria → `lessons.status = 'concluida'`, `completed_at` preenchido.  
  - Não há uso de “realizada” ou “encerrada” na tabela lessons para prática.

- **Teórica (app):**  
  - **Sessão encerrada** = `theory_sessions.status = 'done'`.  
  - O app sincroniza: ao marcar sessão como `done` (ou ao registrar presenças que levam à conclusão), as **lessons** ligadas a essa sessão são atualizadas para `status = 'concluida'`.  
  - Na prática: “concluída” para teórica no app = sessão done + lessons com status concluida.

- **Teórica (legado):**  
  - **Realizada** = `turma_aulas_agendadas.status = 'realizada'` (aula dada).  
  - Conclusão da aula teórica no legado = status realizada; presença é separada em `turma_presencas`.

---

## 5. Casos de borda

- **Aula cancelada**  
  - **App:** aparece na Minha Agenda (findByStudent não filtra status).  
  - **Legado (historico.php):** não aparece (query só inclui `agendada` e `realizada`).

- **Aula remarcada (reagendamento aprovado)**  
  - O mesmo registro em **lessons** é atualizado (data/hora/instrutor) em `AgendaController::atualizar()`.  
  - Não se cria nova aula nem se cancela a antiga; a aula “remarcada” continua uma única linha e aparece na agenda com a nova data.

- **Aula marcada como concluída pelo instrutor/secretaria**  
  - Prática: `AgendaController::concluir()` atualiza `lessons.status = 'concluida'`. Como o aluno usa `findByStudent` sem filtro, a aula concluída **passa a aparecer imediatamente** na lista (já com status concluída).

- **Impacto de faltas/presença**  
  - **Legado:** presença (presente/ausente) é exibida em aluno/historico.php por aula; não remove a aula da lista (a lista é por status agendada/realizada).  
  - **App:** presença teórica está em `theory_attendance`; a exibição na agenda do aluno é por status da aula (concluída, etc.), não por presença individual. O dashboard do aluno usa presença para progresso do curso teórico (%), mas a lista de aulas na agenda não filtra por presença.

---

## 6. Inconsistências encontradas

1. **Dois mundos de teórica**  
   - Legado: `turma_*` (turma_aulas_agendadas, turma_presencas), status “realizada”.  
   - App: `theory_sessions` + `theory_attendance` + `lessons` (type teoria), status “concluida”.  
   - Se uma turma/aula teórica existir só no legado ou só no app, o aluno verá teóricas concluídas em telas diferentes (historico.php vs /agenda). Risco de duplicação ou ausência conforme origem dos dados.

2. **Histórico legado sem práticas**  
   - aluno/historico.php mostra **apenas** presença teórica (legado). Aulas práticas concluídas **não** aparecem nessa tela; o aluno só vê práticas (concluídas ou não) em **Minha Agenda**.

3. **Sem aba “Histórico” para o aluno no app**  
   - Na agenda do app, o aluno sempre vê “todas” as aulas (tab fixa). Não há aba “Histórico” (como há para o instrutor), o que pode dificultar foco só em aulas já realizadas/concluídas.

4. **Acesso ao histórico legado**  
   - aluno/historico.php não está no menu do app; depende de link em presencas-teoricas.php ou URL direta. Quem usa só o app pode não encontrar o “Meu Histórico” de teóricas do legado.

5. **Teórica concluída no app depende de sincronização**  
   - Teóricas concluídas na agenda do aluno dependem de `lessons.status` ser atualizado quando `theory_sessions.status = 'done'`. Se essa sincronização falhar em algum fluxo, a aula pode estar “done” no banco mas ainda aparecer como não concluída na agenda.

---

## 7. Recomendação (somente diagnóstico)

- **Unificar critérios e telas:**  
  Definir uma única noção de “histórico de aulas” para o aluno (teórica + prática) e uma tela/rota que consolide: (a) aulas do app (lessons + theory_sessions) e (b) se necessário, aulas do legado (turma_aulas_agendadas), com regras claras de status (concluída/realizada/cancelada) e onde cada tipo aparece.

- **Visibilidade do histórico:**  
  Se o “Meu Histórico” (legado) continuar sendo usado, colocá-lo no menu do app ou redirecionar para uma rota do app que mostre o mesmo conteúdo; caso contrário, documentar que histórico de teóricas legado é apenas por aluno/historico.php ou presencas-teoricas.

- **Aba ou filtro “Histórico” para o aluno:**  
  Avaliar adicionar aba ou filtro “Histórico” na Minha Agenda do aluno (ex.: só status concluida, cancelada, no_show) para alinhar à experiência do instrutor e facilitar consulta a aulas já realizadas.

- **Canceladas no legado:**  
  Se for desejável que o aluno veja aulas teóricas canceladas no legado, incluir o status correspondente na query de aluno/historico.php (ou na futura tela unificada).

**Esta auditoria é apenas diagnóstico; nenhuma alteração de lógica ou UI foi implementada.**
