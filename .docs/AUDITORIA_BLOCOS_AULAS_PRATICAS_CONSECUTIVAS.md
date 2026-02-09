# Auditoria: Blocos de Aulas Práticas Consecutivas

**Data:** 09/02/2025  
**Objetivo:** Agrupar aulas práticas consecutivas em blocos para **Iniciar** e **Finalizar** uma única vez, mantendo registro granular por aula.

---

## 1) O que já existe hoje

### 1.1 Conceito de "consecutivas"

| Aspecto | Implementação atual |
|---------|---------------------|
| **Onde** | `DashboardController::groupConsecutiveLessons()` e `findConsecutiveLessons()` |
| **Lógica** | Mesmo aluno + mesma data + horários contíguos (fim da aula N = início da aula N+1) |
| **Não verifica** | Mesmo instrutor (implícito no filtro), mesma categoria prática (rua/garagem/baliza), mesmo veículo |

### 1.2 Visualização em bloco

| Local | Situação |
|-------|----------|
| **app/Views/dashboard/instrutor.php** (MVC) | Agrupa em "Aulas de Hoje" com badge "2 aulas" / "3 aulas" e duração total |
| **app/Views/agenda/show.php** | Mostra "X aulas consecutivas" + horário do bloco (início–fim) |
| **instrutor/dashboard.php** (legado) | **Não agrupa** — cada aula é uma linha separada |

### 1.3 Ações (Iniciar / Finalizar)

| Aspecto | Situação |
|---------|----------|
| **Iniciar** | Por aula: `agenda/{id}/iniciar` — uma aula por vez |
| **Finalizar** | Por aula: `agenda/{id}/concluir` — uma aula por vez |
| **KM** | Registrado por aula (km_inicial, km_end) |
| **Status** | Cada aula tem status próprio (agendada, em_andamento, concluída, cancelada) |

### 1.4 Cancelamento

| Aspecto | Situação |
|---------|----------|
| **Cancelar aula** | Existe em `AgendaController::cancelar()` (MVC) e `admin/api/cancelar-aula.php` (legado) |
| **Efeito no bloco** | Cancelamento é individual — não há recálculo/adaptação do bloco na UI |

### 1.5 Dupla base de dados

- **`lessons`** (MVC): usado por `app/Controllers/AgendaController`, `DashboardController`, `app/Models/Lesson`
- **`aulas`** (legado): usado por `instrutor/dashboard.php`, `admin/api/instrutor-aulas.php`, `admin/api/cancelar-aula.php`

---

## 2) O que falta implementar

### 2.1 Regra de bloco (definição)

| Critério sugerido | Status atual | Ajuste |
|-------------------|-------------|--------|
| Aulas contíguas (sem intervalo) | ✅ Já existe | Manter |
| Mesmo instrutor | ⚠️ Implícito | Tornar explícito na função de bloco |
| Mesma categoria prática | ❌ Não verifica | Adicionar (rua/garagem/baliza) |
| Mesmo aluno/veículo | ⚠️ Aluno sim; veículo não | Avaliar: incluir veículo na regra |

### 2.2 UI em bloco no portal do instrutor

| Item | Status | Necessário |
|------|--------|------------|
| Card único com "Aula 1/2/3" dentro | ⚠️ Parcial (MVC) | Unificar: um card por bloco mostrando lista de aulas |
| Botão **Iniciar Bloco** | ❌ | Trocar "Iniciar" por "Iniciar Bloco" quando bloco ≥ 2 aulas |
| Botão **Finalizar Bloco** | ❌ | Trocar "Finalizar" por "Finalizar Bloco" quando bloco ≥ 2 aulas |
| Status por aula dentro do bloco | ❌ | Mostrar cada aula com seu status (agendada, em andamento, concluída, cancelada) |

### 2.3 Fluxo Iniciar Bloco

| Etapa | Necessário |
|-------|------------|
| Rota / Action | Nova rota ou parâmetro: ex. `agenda/iniciar-bloco?ids=1,2,3` ou `agenda/1/iniciar-bloco` |
| Validação | Todas as aulas do bloco devem estar agendadas; todas do mesmo instrutor |
| KM inicial | Único para o bloco (compartilhado por todas as aulas) |
| Tipos de aula | Um único conjunto de tipos (Rua/Garagem/Baliza) para o bloco ou por aula — definir regra |
| Atualização | Marcar todas as aulas do bloco como `em_andamento` com mesmo `km_start` e `started_at` |

### 2.4 Fluxo Finalizar Bloco

| Etapa | Necessário |
|-------|------------|
| Rota / Action | Nova rota: ex. `agenda/finalizar-bloco?ids=1,2,3` |
| Validação | Todas as aulas do bloco em `em_andamento` (ou agendada no caso de "concluir direto") |
| KM final | Único para o bloco |
| Atualização | Marcar todas as aulas do bloco como `concluida` com mesmo `km_end` e `completed_at` |

### 2.5 Aula cancelada no meio do bloco

| Momento | Comportamento necessário |
|---------|---------------------------|
| **Antes de iniciar** | Bloco deve exibir aulas canceladas (ou "pular") — lista deve mostrar "Aula 1, Aula 2 (cancelada), Aula 3" |
| **Durante o bloco** | Definir: encerrar bloco na última aula válida ou dividir em dois blocos — regra deve ser explícita e consistente |

### 2.6 Portal legado (instrutor/dashboard.php)

| Item | Status | Necessário |
|------|--------|------------|
| Agrupamento em blocos | ❌ | Implementar lógica equivalente (tabela `aulas`) |
| Iniciar/Finalizar Bloco | ❌ | Integrar com API ou rotas novas |

---

## 3) Resumo executivo

| Categoria | Tem | Precisa |
|-----------|-----|---------|
| **Lógica de consecutivas** | Sim (aluno + data + contíguo) | Ajustar para instrutor + categoria + veículo (opcional) |
| **Visualização em bloco** | Parcial (MVC) | Card único com "Aula 1/2/3" e status por aula |
| **Iniciar Bloco** | Não | Iniciar todas as aulas do bloco de uma vez |
| **Finalizar Bloco** | Não | Finalizar todas as aulas do bloco de uma vez |
| **Cancelamento no bloco** | Individual apenas | Exibir aula cancelada no bloco; regra clara durante sessão |
| **Sincronização MVC vs legado** | Duas bases | Decidir se unifica ou implementa nos dois fluxos |

---

## 4) Implementação (09/02/2025)

### 4.1 O que foi implementado

| Item | Status |
|------|--------|
| **Agrupamento em blocos** | ✅ `instrutor/dashboard.php` — mesma regra: aluno + veículo + data + contíguo |
| **Iniciar Bloco** | ✅ API `instrutor-aulas.php` (tipo_acao: `iniciar_bloco`) + UI botão |
| **Finalizar Bloco** | ✅ API `instrutor-aulas.php` (tipo_acao: `finalizar_bloco`) + UI botão |
| **KM único por bloco** | ✅ Um km_inicial e um km_final aplicados a todas as aulas do bloco |
| **Card único com badge** | ✅ Badge "X aulas" + horário do bloco (início–fim) |

### 4.2 Regra de bloco implementada

- Mesmo aluno (`aluno_id`)
- Mesmo veículo (`veiculo_id`)
- Mesma data (`data_aula`)
- Horários contíguos (`hora_fim` da aula N = `hora_inicio` da aula N+1)

### 4.3 Arquivos alterados

| Arquivo | Alteração |
|---------|------------|
| `instrutor/dashboard.php` | Lógica de blocos, loop por `$aulasHojeProcessadas`, botões Iniciar/Finalizar Bloco, JS |
| `admin/api/instrutor-aulas.php` | Novos tipos `iniciar_bloco` e `finalizar_bloco`, atualização em lote de aulas |

### 4.4 Implementação complementar (09/02/2025 - fase 2)

- ✅ `dashboard-mobile.php` — blocos e ações Iniciar/Finalizar Bloco
- ✅ MVC (`app/Views/dashboard/instrutor.php`) — Iniciar Bloco, Finalizar Bloco, rotas e controller
- ✅ Status por aula dentro do card do bloco (Aula 1: agendada, Aula 2: cancelada, etc.)
- ✅ Exibição de aula cancelada no bloco (vermelho, riscado)

---

## 5) Arquivos impactados (referência)

| Área | Arquivos principais |
|------|---------------------|
| Controller | `app/Controllers/AgendaController.php`, `app/Controllers/DashboardController.php` |
| Views | `app/Views/dashboard/instrutor.php`, `app/Views/agenda/show.php`, `app/Views/agenda/iniciar.php`, `app/Views/agenda/concluir.php` |
| Legado | `instrutor/dashboard.php`, `admin/api/instrutor-aulas.php` |
| Rotas | `app/routes/web.php` |
