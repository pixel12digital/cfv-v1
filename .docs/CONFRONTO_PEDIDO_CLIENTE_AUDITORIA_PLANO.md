# Confronto: Pedido do Cliente × Auditoria + Plano

**Objetivo:** Responder aos 4 itens solicitados para fechar o desenho antes de qualquer implementação: (1) onde é o “começar aula”; (2) fonte de dados real (Robson Wagner); (3) existência de meta para “faltam Y”; (4) local exato na UI para os contadores (instrutor e aluno).

**Restrições:** Apenas desenho e diagnóstico — **nada implementado**.

---

## 0. Pedido do cliente (resumo)

- **Instrutor:** Na hora de “começar” a aula do aluno, ver: “Você já deu X aulas para este aluno”; “Última aula foi no dia tal”; “Faltam Y” (se existir meta).
- **Aluno:** No painel do aluno: “Você já teve X aulas”; “Faltam Y”.
- **Formato:** Algo marcado/resumido, direto, sem telas novas.

---

## 1. Confirmação do “começar aula”: rota e view

**Resposta direta:** O momento “começar aula” no **fluxo do app** (portal do instrutor) é:

| Item | Valor |
|------|--------|
| **Rota** | GET `/agenda/{id}/iniciar` (exibir formulário) e POST `/agenda/{id}/iniciar` (processar início). |
| **Controller** | `App\Controllers\AgendaController::iniciar($id)`. |
| **View** | `app/Views/agenda/iniciar.php` — tela “Iniciar Aula” com: informações da aula (aluno, data/hora, veículo), formulário de KM inicial e observação, botão “Iniciar Aula”. |

**Como o instrutor chega nessa tela:**

1. **Detalhes da aula** (`GET /agenda/{id}` → `agenda/show.php`) → link “Iniciar Aula” → GET `/agenda/{id}/iniciar`.
2. **Lista da agenda** (`GET /agenda?view=list&tab=proximas` → `agenda/index.php`) → botão “Iniciar Aula” no card da aula → GET `/agenda/{id}/iniciar`.
3. **Dashboard do instrutor (app)** (`GET /dashboard` → view instrutor) → link para próxima aula “Iniciar” → GET `/agenda/{id}/iniciar`.

**Conclusão para o pedido:** O “na hora que começar do aluno” no app é **a própria tela `agenda/iniciar`**. Para atender o cliente sem criar telas novas, o resumo (“X aulas já deu / última dia tal / faltam Y ou alternativa”) deve aparecer **nessa view** e, em paralelo (auditoria), no **detalhe da aula** (`agenda/show`) para quem abre o detalhe antes de clicar em “Iniciar Aula”.

---

## 2. Fonte de dados real: Robson Wagner tem histórico em `lessons`, `aulas` ou ambos?

**Resposta:** A auditoria **não tem acesso ao banco em produção**. A resposta exata depende de rodar as checagens no ambiente onde o Robson Wagner está.

**O que o código define:**

| Fonte | Uso no sistema | Tabela / origem |
|-------|----------------|-----------------|
| **Agenda do app (instrutor)** | Lista “Próximas / Histórico / Todas” | `lessons` (model `Lesson`, método `findByInstructorWithTheoryDedupe`). |
| **Dashboard do instrutor (legado)** | Próximas aulas, aulas de hoje | Práticas: `aulas`; teóricas: `turma_aulas_agendadas`. |
| **Admin “Histórico do Instrutor”** | Lista de aulas do instrutor | `aulas`. |
| **Concluir aula no app** | Instrutor/secretaria conclui pela agenda app | Atualiza `lessons` (status, `completed_at`). |
| **Concluir aula no legado** | Instrutor conclui pelo painel legado ou admin | Atualiza `aulas` (e teóricas em `turma_aulas_agendadas`). |

**Como fechar o diagnóstico (Etapa A):**

1. No banco, para o `instructor_id` do Robson Wagner:
   - `SELECT COUNT(*), status FROM lessons WHERE instructor_id = ? AND status IN ('concluida','cancelada','no_show');`
   - `SELECT COUNT(*), status FROM aulas WHERE instrutor_id = ? AND status = 'concluida';`
2. Interpretação:
   - Se **`lessons`** tiver várias concluídas e o Histórico da agenda app estiver vazio → causa é o **filtro de data** (um único dia), como na auditoria.
   - Se **`lessons`** tiver zero/poucas e **`aulas`** tiver várias concluídas → causa é **fonte duplicada**: conclusões só no legado (`aulas`), e a agenda app lê só `lessons`.
   - Se **ambos** tiverem dados → definir qual é o “source of truth” para o contador (recomendação: `lessons` para o app e para o novo resumo).

**Resposta objetiva para o plano:**  
“Fonte de dados real” = **a definir com a Etapa A**. Até lá, assumir: (1) Histórico vazio por **filtro de data** (um dia); (2) possível **duplicidade** `aulas` vs `lessons`; (3) contador do pedido do cliente deve usar **`lessons`** no app e, se no futuro houver unificação, refletir a estratégia escolhida.

---

## 3. Existência de meta para “faltam Y”

**Pergunta técnica:** Existe hoje, em alguma tabela/regra do **app**, o “total obrigatório/previsto” por matrícula ou curso que permita calcular “faltam Y” para **aulas práticas**?

**Resposta:**

| Contexto | Existe meta no app? | Onde está (se existir) |
|----------|----------------------|-------------------------|
| **Aulas práticas (app)** | **Não.** Na base do app (`enrollments`, `lessons`, `services`) **não há** campo de carga horária obrigatória ou quantidade total de aulas por matrícula/serviço. |
| **Aulas teóricas (app)** | Parcial. Existe conceito de “total” por disciplina/curso em `theory_course_disciplines` (ex.: `lessons_count`) e em `theory_sessions` / disciplinas, mas **não** vinculado de forma pronta a “faltam Y” por aluno na tela de aula prática ou no dashboard do aluno hoje. |
| **Legado / admin** | Sim, para teóricas e para “categoria”. Teóricas: `disciplinas_configuracao` (aulas_obrigatorias por curso_tipo), turmas teóricas. Categoria: `admin/includes/categorias_habilitacao.php` (ex.: B = 20h prática, A = 20h, etc.) — **não** ligado a `enrollments` no app. |

**Conclusão:** Para o **app**, hoje **não existe** meta confiável por matrícula para aulas práticas que permita um “faltam Y” correto sem nova regra/cadastro.

**Proposta de texto alternativo (sem inventar regra):**

- **Em vez de “Faltam Y”:** usar algo neutro que não implique obrigatoriedade inventada, por exemplo:
  - “Próximas agendadas: N” (número de aulas futuras agendadas com aquele aluno / para aquele aluno), ou
  - “Aulas agendadas (futuras): N”.
- **Se no futuro** existir meta (ex.: campo em `enrollments` ou em `services` com total de aulas/carga obrigatória), aí sim exibir “Faltam Y” com Y = meta − concluídas.

---

## 4. Proposta final: local exato na UI (instrutor e aluno)

### 4.1 Instrutor — “X aulas já deu / última dia tal / faltam Y ou alternativa”

**Onde exibir (sem novas telas):**

| Local | Conteúdo sugerido | Observação |
|-------|-------------------|------------|
| **1) Tela “Iniciar Aula”** (`app/Views/agenda/iniciar.php`) | Bloco **acima** do card “Dados para Início da Aula” (ou logo abaixo de “Informações da Aula”): “Com este aluno: X aulas concluídas • Última: DD/MM às HH:mm • Próximas agendadas: N”. | Corresponde ao “na hora que começar do aluno”. Fonte: `lessons` por `student_id` + `instructor_id` (e mesma matrícula, se aplicável). |
| **2) Detalhes da aula** (`app/Views/agenda/show.php`) | Mesmo bloco “Resumo do aluno” (colapsável) já proposto na auditoria: “Aulas concluídas com este aluno: X”; “Última aula: DD/MM às HH:mm”; “Próxima agendada: DD/MM às HH:mm”; “Próximas agendadas: N” (em vez de “faltam Y”). | Quem abre o detalhe antes de iniciar também vê o resumo. |

**Textos sugeridos (objetivos):**

- “Aulas concluídas com este aluno: **X**”
- “Última aula: **DD/MM às HH:mm**”
- “Próxima aula agendada: **DD/MM às HH:mm**” (se houver)
- “Próximas agendadas: **N**” (em vez de “faltam Y”, até existir meta no app)

**Fonte de dados:** `lessons` (WHERE `instructor_id` = logado AND `student_id` = aluno da aula), status concluída/cancelada/no_show para “concluídas”; agendada/em_andamento para “próximas agendadas”. Última = MAX(scheduled_date, scheduled_time) com status concluída; próxima = MIN(scheduled_date, scheduled_time) com data/hora > agora.

---

### 4.2 Aluno — “X aulas já teve / faltam Y ou alternativa”

**Onde exibir (sem novas telas):**

| Local | Conteúdo sugerido | Observação |
|-------|-------------------|------------|
| **Dashboard do aluno** (`app/Views/dashboard/aluno.php`) | Bloco **acima** do card “Próxima Aula / Aula Atual” (ou no topo do bloco “Minhas Aulas Práticas”): “Aulas concluídas: **X**” e “Próximas agendadas: **N**”. Opcional: “Última aula: DD/MM” em uma linha. | Reaproveita a tela já existente; não cria nova página. Fonte: `lessons` por `student_id` do aluno logado. |

**Textos sugeridos:**

- “Aulas concluídas: **X**”
- “Próximas agendadas: **N**” (em vez de “faltam Y”, até existir meta)
- (Opcional) “Última aula: **DD/MM**”

**Fonte de dados:** `lessons` (WHERE `student_id` = aluno logado), mesma lógica: concluídas = status concluída/cancelada/no_show; próximas = agendada/em_andamento com data/hora > agora.

**Não usar para esse contador:** detalhe de matrícula ou agenda como **único** lugar — o cliente pediu “no aluno também” no **painel**; o painel do aluno no app é o dashboard. Detalhe da matrícula pode ser complementar depois, se fizer sentido.

---

## 5. Resumo dos 4 itens (para fechar o desenho)

| # | Pergunta | Resposta |
|---|----------|----------|
| 1 | Onde é o “começar aula” no fluxo do instrutor? | **Rota:** GET/POST `/agenda/{id}/iniciar`. **View:** `app/Views/agenda/iniciar.php`. Acesso a partir de: detalhes da aula, lista da agenda ou dashboard do instrutor (app). |
| 2 | Robson Wagner tem histórico em `lessons`, `aulas` ou ambos? | **A definir na Etapa A** (queries no banco). App usa `lessons`; legado/admin usam `aulas`. Contador do pedido deve usar `lessons` no app. |
| 3 | Existe meta para “faltam Y”? | **Não** no app para aulas práticas (nenhum campo em enrollments/services). Usar **“Próximas agendadas: N”** (ou equivalente) até existir meta. |
| 4 | Local exato na UI para os contadores? | **Instrutor:** (1) tela “Iniciar Aula” (`agenda/iniciar`) e (2) detalhes da aula (`agenda/show`), com “X concluídas • última • próximas agendadas: N”. **Aluno:** dashboard do aluno (`dashboard/aluno`), bloco “Aulas concluídas: X” e “Próximas agendadas: N”. |

Com isso o desenho fica fechado para, em seguida, definir o plano de implementação em fases (correção do histórico vazio → contadores), ainda sem codar.
