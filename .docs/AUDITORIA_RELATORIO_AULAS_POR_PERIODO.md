# Auditoria: Relatório de Aulas por Período

**Data:** 2025-02-10  
**Objetivo:** Permitir gerar relatório de aulas por período (status, instrutor, aluno) com filtros e totais, sem duplicar funcionalidade e com menor impacto.

---

## 1. Análise prévia

### 1.1 O que já existe

| Item | Local | Observação |
|------|--------|------------|
| Listagem de aulas | `admin/pages/listar-aulas.php` | Carregada via `index.php?page=agendar-aula&action=list`. Query fixa: últimos 7 dias, LIMIT 100, sem filtros por período/instrutor/aluno/status. Exibe cards (aluno, data/hora, instrutor, tipo, status). |
| Dados de aulas | Tabela `aulas` | Campos: id, aluno_id, instrutor_id, cfc_id, tipo_aula (teorica/pratica), data_aula, hora_inicio, hora_fim, status (agendada, em_andamento, concluida, cancelada), observacoes, veiculo_id, disciplina (se existir em migrações). |
| API agenda | `admin/api/agendamento.php` | `buscarAulas()` retorna aulas para calendário (6 meses atrás/à frente), sem filtros por período. |
| Exportação | `admin/api/exportar-agendamentos.php` | Exporta **turmas teóricas** (`aulas_agendadas`), não a tabela `aulas` da agenda geral. |
| Permissões | `includes/guards/AgendamentoPermissions.php` | Admin e Secretaria podem criar/editar/cancelar. Relatório deve seguir: apenas ADMIN e SECRETARIA. |
| Bloqueio secretaria | `admin/index.php` | Secretaria bloqueada em: instrutores, veículos, configuracoes-salas, servicos, **financeiro-relatorios**. Relatório de aulas **não** deve estar nessa lista (secretaria pode ver). |
| Padrão impressão | `admin/pages/financeiro-relatorios.php` | `window.print()` + botão Exportar (placeholder). Uso de filtros por período via GET. |

### 1.2 Decisão de implementação

- **Nova página dedicada** `relatorio-aulas` em vez de estender `listar-aulas`:
  - `listar-aulas` continua como “lista rápida” (últimos 7 dias, link para nova aula).
  - Relatório tem foco em período, filtros, totais e exportação/impressão.
- **Fonte de dados única:** tabela `aulas` com os mesmos JOINs usados na agenda (alunos, instrutores, usuarios para nome do instrutor, veículos, cfcs), garantindo que o resultado **bata com a agenda**.
- **Totais:** calculados na mesma query (ou em PHP a partir da lista filtrada) para que total realizadas + canceladas + agendadas (+ em_andamento) bata com a quantidade de linhas.
- **Exportação:** novo endpoint `admin/api/exportar-relatorio-aulas.php` (CSV com BOM UTF-8, separador `;`), aplicando os mesmos filtros da tela e mesma regra de permissão (ADMIN/SECRETARIA).
- **Impressão:** `window.print()` com área imprimível (classe `.relatorio-aulas-print`), ocultando menu/sidebar no print (padrão já usado em outras telas).
- **Permissões:** Acesso à página e à API apenas para `admin` e `secretaria`. Instrutor não acessa; bloqueio no início da página e na API.

---

## 2. Garantias pós-implementação

1. **Listar aulas por período com filtros (instrutor, aluno, status)** usando a mesma base da agenda (`aulas` + JOINs), resultado consistente com a agenda.
2. **Colunas mínimas:** data/hora, aluno, instrutor, tipo, status.
3. **Totais no topo:** total realizadas (concluida), canceladas, agendadas (e opcionalmente em andamento), batendo com a lista exibida.
4. **Exportação/impressão:** CSV com mesmo layout de colunas; impressão sem quebrar layout (ocultar navegação na impressão).
5. **Permissões:** Apenas ADMIN e SECRETARIA; bloqueio de acesso direto (redirect + mensagem) para outros perfis.

---

## 3. Arquivos alterados/criados

| Arquivo | Ação |
|---------|------|
| `.docs/AUDITORIA_RELATORIO_AULAS_POR_PERIODO.md` | Criado (este documento) |
| `admin/pages/relatorio-aulas.php` | Criado – filtros, totais, tabela, botões exportar/imprimir |
| `admin/index.php` | Incluir case `relatorio-aulas`, carregar dados e link no menu (Acadêmico) para admin/secretaria |
| `admin/api/exportar-relatorio-aulas.php` | Criado – CSV com filtros e permissão ADMIN/SECRETARIA |

---

## 4. Status na base

- `agendada` → contabilizada como “Agendadas”.
- `concluida` → contabilizada como “Realizadas”.
- `cancelada` → contabilizada como “Canceladas”.
- `em_andamento` → exibida na lista; pode ser totalizada separadamente ou agrupada conforme regra de negócio (implementação usa “Em andamento” opcional nos totais).
