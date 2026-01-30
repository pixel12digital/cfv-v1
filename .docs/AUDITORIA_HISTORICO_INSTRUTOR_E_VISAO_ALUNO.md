# Auditoria: Histórico do Instrutor (vazio) + Visão de Histórico por Aluno

**Objetivo:** Diagnóstico do porquê o Histórico do instrutor está vazio (caso Robson Wagner com aulas concluídas) e proposta de arquitetura/UX para: (1) garantir que o instrutor veja todas as aulas concluídas; (2) exibir resumo do histórico do aluno na tela de detalhes da aula, sem novas telas.

**Restrições:** Apenas levantamento e proposta — **nada implementado**.

**Cenário de validação:** Instrutor Robson Wagner; aluno Carlos Roberto; próxima aula 30/01 às 11h; sintoma: Histórico vazio e sem visão consolidada do aluno nos detalhes.

---

## 1. Checklist de diagnóstico do “Histórico vazio”

### 1.1 Onde o sistema define “histórico”

| Critério | Onde está | Detalhe |
|----------|-----------|--------|
| **Por status** | `app/Models/Lesson.php` → `findByInstructorWithTheoryDedupe()` | Aba “Histórico” = `l.status IN ('concluida', 'cancelada', 'no_show')`. Não é por data nem por presença; é **somente por status** na tabela `lessons`. |
| **Por data** | Não define “histórico” | A data é usada como **filtro adicional** (intervalo), não como definição de histórico. |
| **Por conclusão** | Idem status | “Concluída” = `lessons.status = 'concluida'` (e `completed_at` preenchido no fluxo de concluir). |
| **Por presença** | Não usado no histórico da agenda | Presença (teórica) é em `theory_attendance`; o filtro da aba Histórico não considera presença. |
| **Por encerramento manual** | Coincide com status | Aula concluída pelo instrutor/secretaria → `AgendaController::concluir()` ou equivalente → `status = 'concluida'`. |

**Resumo:** “Histórico” na agenda do instrutor = aulas com `status IN ('concluida', 'cancelada', 'no_show')` na tabela **`lessons`**.

---

### 1.2 O que pode estar impedindo aulas concluídas de aparecerem

| # | Hipótese | Onde verificar | Conclusão |
|---|----------|----------------|-----------|
| 1 | **Filtro de data restringe a um único dia** | `AgendaController::index()` + `Lesson::findByInstructorWithTheoryDedupe()` | **Causa principal.** Em view=list (instrutor), `$date = $_GET['date'] ?? date('Y-m-d')` → sempre definido (hoje se não houver `date` na URL). Depois `$startDate = $date`, `$endDate = $date`. Ou seja: **sempre um único dia**. Para aba Histórico, o controller passa `start_date` e `end_date` = esse dia. A query fica: status IN ('concluida', 'cancelada', 'no_show') **e** `scheduled_date BETWEEN hoje AND hoje`. Só aparecem aulas concluídas **desse dia** → normalmente vazio. |
| 2 | **Fonte de dados diferente** | Agenda app usa `lessons`; dashboard/legado usam `aulas` | **Possível causa adicional.** Dashboard do instrutor (`instrutor/dashboard.php`) e admin “Histórico do Instrutor” (`admin/pages/historico-instrutor.php`) leem da tabela **`aulas`** (aluno_id, data_aula, status 'concluida'). A **agenda do app** lê da tabela **`lessons`** (student_id, scheduled_date, status 'concluida'). Se as aulas do Robson Wagner forem concluídas apenas no fluxo que grava em `aulas` (ex.: admin ou painel legado) e não em `lessons`, o Histórico da agenda (app) continuaria vazio mesmo corrigindo o filtro de data. |
| 3 | **Status não é “concluida” ao finalizar** | Onde a aula é marcada como concluída | No app: `AgendaController::concluir()` atualiza `lessons` (status + `completed_at`). Em teóricas: `TheorySessionsController` / `TheoryAttendanceController` podem setar `lessons.status = 'concluida'`. Se algum fluxo “finalizar aula” não atualizar `lessons.status` para `concluida`, a aula não entra no Histórico. |
| 4 | **CFC / instrutor** | Filtro por `cfc_id` e `instructor_id` | `findByInstructorWithTheoryDedupe($loggedInstructorId, $this->cfcId, ...)` já filtra por instrutor e CFC. Se o usuário “Robson Wagner” não estiver mapeado corretamente para `instructor_id` em sessão, ou se `cfc_id` estiver errado, poderia reduzir resultados — mas o mais provável é o item 1. |

**Conclusão do diagnóstico:**  
A causa mais provável do Histórico vazio é o **filtro de data fixo em um único dia (hoje)**. Uma causa possível adicional é **dados só em `aulas` e não em `lessons`**.

---

### 1.3 Queries / rotas / filtros: Histórico vs Todas vs Próximas

| Aba | Rota (ex.) | Controller | Model / método | Filtros aplicados |
|-----|------------|------------|----------------|-------------------|
| **Próximas** | `agenda?view=list&tab=proximas` | `AgendaController::index()` | `Lesson::findByInstructorWithTheoryDedupe(..., ['tab'=>'proximas', 'start_date'=>..., 'end_date'=>...])` | Status IN ('agendada','em_andamento') **e** (scheduled_date > hoje OU (scheduled_date = hoje e scheduled_time >= agora)). **Mais** filtro de data (um dia). |
| **Histórico** | `agenda?view=list&tab=historico` | Idem | Idem com `tab=>'historico'` | Status IN ('concluida','cancelada','no_show'). **Mais** filtro de data (um dia = hoje por padrão) → restringe a um dia. |
| **Todas** | `agenda?view=list&tab=todas` | Idem | Idem com `tab=>'todas'` | Sem filtro de status por aba (só exclui canceladas se não houver show_canceled). **Mais** filtro de data (um dia). |

- **Rotas:** GET `/agenda` (e links com `view=list&tab=historico|proximas|todas`).
- **Fonte de lista:** sempre `lessons` (práticas + teóricas com dedupe por `theory_session_id`).

---

### 1.4 Campo/status que deveria estar marcado ao concluir e pode não estar

| Tipo | Onde é marcada conclusão | Campo/status esperado |
|------|---------------------------|------------------------|
| **Prática (app)** | `AgendaController::concluir()` | `lessons.status = 'concluida'`, `lessons.completed_at` preenchido. |
| **Teórica (app)** | Sessão encerrada / chamada | `theory_sessions.status = 'done'` e sincronização para `lessons.status = 'concluida'` (ex. `TheorySessionsController` / `TheoryAttendanceController`). |
| **Legado (`aulas`)** | Admin / instrutor legado | `aulas.status = 'concluida'` (prática) ou em teóricas `turma_aulas_agendadas.status = 'realizada'`. **Não** atualizam `lessons`. |

Se as aulas do Robson forem concluídas apenas no fluxo que grava em `aulas` (ou em `turma_aulas_agendadas`), a tabela `lessons` pode não ter essas aulas ou não ter `status = 'concluida'`. Para o Histórico da agenda (app) encher, é necessário que **`lessons.status`** esteja `concluida` (ou cancelada/no_show) para essas aulas.

---

### 1.5 Pontos para inspecionar (sem adivinhar)

Para fechar o diagnóstico no ambiente do Robson Wagner:

1. **Data no Histórico**  
   - Ao abrir `agenda?view=list&tab=historico`, verificar na requisição se existe `date` na URL.  
   - No controller, logar `$startDate`, `$endDate` e `$tab` quando for instrutor e view=list.  
   - Confirmar se, para tab=historico, está sendo aplicado `BETWEEN um_dia AND um_dia` (e qual dia).

2. **Dados do instrutor**  
   - Logar `$loggedInstructorId` e `$this->cfcId` na mesma requisição.  
   - No banco: `SELECT id, instructor_id, cfc_id, status, scheduled_date FROM lessons WHERE instructor_id = <id_robson> AND status IN ('concluida','cancelada','no_show') ORDER BY scheduled_date DESC LIMIT 20;`  
   - Se retornar linhas: o problema é só o filtro de data (um dia). Se não retornar: ou as aulas estão em `aulas` e não em `lessons`, ou o status nunca é setado para `concluida` nesse fluxo.

3. **Conclusão em duas tabelas**  
   - Para o mesmo instrutor:  
     - `SELECT COUNT(*), status FROM aulas WHERE instrutor_id = <id_robson> AND status = 'concluida';`  
     - `SELECT COUNT(*), status FROM lessons WHERE instructor_id = <id_robson> AND status = 'concluida';`  
   - Se `aulas` tiver muitas concluídas e `lessons` poucas/nenhuma, a causa é dupla fonte de dados.

---

## 2. Proposta de UI/UX (sem novas telas)

### 2.1 Onde entra o “resumo do aluno”

- **Tela:** Detalhes da aula já existente → **`app/Views/agenda/show.php`** (rota `GET /agenda/{id}`).
- **Quem usa:** Instrutor (e admin/secretaria) ao abrir uma aula; o aluno já vê seus dados, então o foco é **instrutor ver contexto do aluno** sem sair da tela.
- **Onde colocar:** Dentro do mesmo layout, por exemplo **abaixo do bloco “Informações da Aula”** (ou na coluna direita, no bloco “Informações Adicionais”), um **novo bloco único**: “Resumo do aluno” / “Histórico do aluno nesta matrícula”.

Não criar nova rota nem nova página; apenas **um bloco novo na mesma view**, condicionado a “é instrutor (ou admin) e a aula tem aluno/matrícula”.

---

### 2.2 Blocos que fazem sentido no detalhe (resumo do aluno)

| Bloco | Conteúdo | Fonte de dados (conceitual) |
|-------|----------|----------------------------|
| **Lista curta de aulas anteriores** | Últimas N aulas (ex.: 5–8) do **mesmo aluno** na mesma matrícula: data, hora, tipo (prática/teórica), status (concluída/cancelada/etc.). | `lessons` WHERE student_id = aluno da aula AND enrollment_id = matrícula da aula AND (scheduled_date < hoje OU (scheduled_date = hoje e scheduled_time < hora)) ORDER BY scheduled_date DESC, scheduled_time DESC. |
| **Total realizadas vs pendentes** | Ex.: “X aulas realizadas de Y obrigatórias” (se houver meta por matrícula/curso) ou só “X concluídas / Y canceladas / Z agendadas” no período. | Contagem por status em `lessons` (e, se existir, regra de carga horária obrigatória). |
| **Última aula e próxima aula** | “Última: DD/MM às HH:mm – [Concluída/Cancelada]”; “Próxima: DD/MM às HH:mm – [Agendada]”. | Última: mesma query acima, primeira linha. Próxima: `lessons` do aluno com data/hora > agora e status agendada/em_andamento, ORDER BY data/hora ASC, LIMIT 1. |
| **Observações/ocorrências por aula** | Se existir tabela de ocorrências ou notas por aula (ex.: `instructor_notes` em `lessons`, ou tabela de ocorrências vinculada à aula), mostrar as últimas 2–3 em texto curto. | `lessons.instructor_notes` e/ou tabela de ocorrências por lesson_id (se houver). |

Tudo pode ser carregado no **mesmo request** do `AgendaController::show()`: o controller chama o model da aula e um método tipo “resumo do aluno para esta aula” (student_id + enrollment_id + lesson_id para “excluir a atual” ou “incluir na lista”) e passa para a view.

---

### 2.3 Como não poluir a tela

- **Bloco colapsável (recomendado):** Título “Resumo do aluno” / “Histórico do aluno” com ícone de expandir/retrair; **fechado por padrão** (só “X aulas concluídas • Última: DD/MM” em uma linha). Ao abrir, mostra a lista curta + totais + última/próxima + eventualmente observações.
- **Ou abas dentro do detalhe:** Aba 1 “Aula” (conteúdo atual), Aba 2 “Aluno” (resumo acima). Evita poluir a primeira tela; um pouco mais de implementação.
- **Mobile:** O mesmo bloco colapsável funciona; na coluna direita (desktop) pode ficar abaixo de “Informações Adicionais”; em uma coluna (mobile), abaixo do card principal.

Recomendação: **bloco único colapsável** com resumo em uma linha quando fechado e detalhes ao expandir — mínima intervenção e reaproveitamento total da tela atual.

---

## 3. Resposta à pergunta-chave

**“Qual é a melhor forma de fazermos isso mantendo estabilidade, reaproveitando a estrutura atual e sem criar telas novas?”**

1. **Histórico do instrutor não vazio**  
   - **Ajuste de filtro de data:** Para o instrutor em view=list, quando `tab=historico` (e opcionalmente `tab=todas`), **não** passar `start_date`/`end_date` ou passar um intervalo amplo (ex.: `start_date = hoje - 1 ano`, `end_date = hoje` ou hoje + 1 mês), em vez de um único dia. Assim a lista “Histórico” passa a mostrar todas as aulas concluídas/canceladas/no_show do instrutor no período.  
   - **Validar fonte de dados:** Se no diagnóstico aparecer que as “concluídas” do Robson estão em `aulas` e não em `lessons`, aí é decisão de produto: ou migrar/sincronizar conclusões para `lessons`, ou fazer a agenda do instrutor também ler de `aulas` (mais acoplado e duplicado). Recomendação: manter uma única fonte para a agenda app (`lessons`) e garantir que todo fluxo de “concluir aula” atualize `lessons`.

2. **Visão do aluno no detalhe da aula**  
   - **Reaproveitar 100% a tela atual:** Manter `agenda/show.php` e a rota `GET /agenda/{id}`. No `AgendaController::show($id)`, além de `findWithDetails($id)`, buscar um “resumo do aluno” (student_id + enrollment_id da aula; opcionalmente lesson_id para ordenar/excluir).  
   - **Um bloco novo na view:** Um único bloco “Resumo do aluno” (colapsável), com: última/próxima aula, total realizadas/pendentes, lista curta de aulas anteriores, e (se houver) observações/ocorrências. Sem novas rotas, sem novas telas, sem novo layout — só um bloco a mais na mesma página.

Assim se mantém estabilidade (uma mudança pontual no filtro de data; um bloco opcional na view), reaproveitamento total da estrutura (controller show + view show) e zero telas novas.

---

## 4. Resumo executivo

| Tópico | Conclusão |
|--------|-----------|
| **Por que o Histórico está vazio?** | Principal: filtro de data está fixo em **um único dia** (hoje por padrão), então a aba Histórico mostra só aulas concluídas daquele dia. Secundário: aulas concluídas podem estar só em `aulas`, enquanto a agenda do app lê `lessons`. |
| **Onde “histórico” é definido?** | Por **status** em `lessons`: `concluida`, `cancelada`, `no_show`. Não por data nem por presença. |
| **Queries/rotas** | GET `/agenda` com `view=list&tab=historico`; `Lesson::findByInstructorWithTheoryDedupe()` com `tab=historico` + intervalo de data (hoje–hoje hoje). |
| **Campo a garantir ao concluir** | `lessons.status = 'concluida'` (e `completed_at`) para que a aula entre no Histórico da agenda app. |
| **Melhor forma (mínima intervenção)** | (1) Para tab=historico (e talvez todas), não restringir a um dia: sem data ou intervalo amplo. (2) Na tela de detalhes da aula, um bloco colapsável “Resumo do aluno” com última/próxima, totais e lista curta de aulas, sem novas telas. |

**Esta auditoria é apenas diagnóstico e proposta; nenhuma alteração de código foi feita.**
