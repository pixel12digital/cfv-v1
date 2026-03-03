# Relatório de Alunos por Status - Documentação Técnica

## 📋 Visão Geral

Relatório gerencial que exibe informações completas sobre alunos, incluindo status, aulas contratadas, realizadas, agendadas e restantes. Permite visão consolidada do progresso de cada aluno no processo de formação.

## 🎯 Objetivo

Fornecer aos gestores (ADMIN e SECRETARIA) uma visão clara e precisa de:
- Status atual de cada aluno
- Quantidade de aulas contratadas vs realizadas
- Aulas agendadas futuras
- Saldo de aulas restantes
- Percentual de conclusão do curso
- Situação financeira (bloqueios)

## 📁 Arquivos Implementados

### 1. Backend API
- **`admin/api/relatorio-alunos-status.php`**
  - Endpoint JSON para buscar dados
  - Retorna lista de alunos com cálculos de aulas
  - Estatísticas agregadas

### 2. Exportação
- **`admin/api/exportar-relatorio-alunos-status.php`**
  - Exportação em formato CSV
  - Compatível com Excel (UTF-8 BOM)
  - Separador: ponto e vírgula (;)

### 3. Frontend
- **`admin/pages/relatorio-alunos-status.php`**
  - Interface visual do relatório
  - Filtros dinâmicos
  - Carregamento assíncrono via AJAX

### 4. Integração
- **`admin/index.php`** (modificado)
  - Rota adicionada: `relatorio-alunos-status`
  - Links no menu desktop e mobile

## 🔐 Permissões

### Acesso Permitido
- **ADMIN**: Acesso total
- **SECRETARIA**: Acesso total

### Acesso Negado
- **INSTRUTOR**: Bloqueado
- **ALUNO**: Bloqueado
- Outros tipos de usuário: Bloqueado

## 🎨 Funcionalidades

### Filtros Disponíveis

1. **Status do Aluno**
   - Todos (padrão)
   - Lead
   - Matriculado
   - Em Andamento
   - Concluído
   - Cancelado

2. **Unidade/CFC**
   - Todas (padrão)
   - Lista dinâmica de CFCs ativos

3. **Período de Matrícula**
   - Data Início (opcional)
   - Data Fim (opcional)

### Informações Exibidas

#### Por Aluno
- **Dados Pessoais**: Nome, CPF, Telefone, Email
- **Status**: Badge colorido indicando situação atual
- **Status Financeiro**: Em dia, Pendente ou Bloqueado
- **Serviço**: Tipo de serviço contratado
- **Aulas Contratadas**: Total de aulas (ou "Sem limite" se NULL)
- **Aulas Realizadas**: Badge verde com contagem
- **Aulas Agendadas**: Badge azul com contagem
- **Aulas Restantes**: Badge amarelo/vermelho com saldo
- **Progresso**: Barra visual com percentual de conclusão

#### Estatísticas Gerais (Cards no Topo)
- Total de alunos
- Em andamento
- Concluídos
- Matriculados
- Bloqueados financeiramente
- Cancelados

## 🔢 Lógica de Cálculos

### 1. Aulas Contratadas
```sql
-- Verifica se usa sistema de quotas por categoria
IF (SUM(enrollment_lesson_quotas.quantity) > 0) THEN
    total_contratado = SUM(quotas)
ELSE
    total_contratado = enrollments.aulas_contratadas
END IF

-- Se NULL = sem limite de aulas
```

### 2. Aulas Realizadas
```sql
SELECT COUNT(*) 
FROM lessons 
WHERE student_id = ? 
  AND enrollment_id = ? 
  AND status = 'concluida'
```

### 3. Aulas Agendadas (Futuras)
```sql
SELECT COUNT(*) 
FROM lessons 
WHERE student_id = ? 
  AND enrollment_id = ? 
  AND status IN ('agendada', 'em_andamento')
  AND (scheduled_date > CURDATE() 
       OR (scheduled_date = CURDATE() AND scheduled_time >= CURTIME()))
```

### 4. Aulas Restantes
```
aulas_restantes = aulas_contratadas - aulas_realizadas - aulas_agendadas

Se aulas_restantes < 0: aulas_restantes = 0
Se aulas_contratadas = NULL: aulas_restantes = NULL (sem limite)
```

### 5. Percentual de Conclusão
```
percentual = (aulas_realizadas / aulas_contratadas) * 100

Se aulas_contratadas = NULL: percentual = 0
```

## 🗄️ Estrutura de Dados

### Tabelas Utilizadas

1. **`students`**
   - Dados básicos dos alunos
   - Status do aluno

2. **`enrollments`**
   - Matrículas ativas
   - Campo `aulas_contratadas` (sistema simples)
   - Status financeiro

3. **`enrollment_lesson_quotas`**
   - Quotas por categoria (A, B, C, D, E)
   - Sistema avançado de controle

4. **`lessons`**
   - Aulas práticas agendadas/realizadas
   - Status da aula

5. **`cfcs`**
   - Centros de formação
   - Para filtro por unidade

### Compatibilidade

O relatório suporta **dois sistemas**:

#### Sistema Simples
- Usa campo `enrollments.aulas_contratadas`
- Valor único para todas as categorias
- Retrocompatível com matrículas antigas

#### Sistema de Quotas
- Usa tabela `enrollment_lesson_quotas`
- Quotas separadas por categoria (A, B, C, D, E)
- Permite controle granular

**Detecção Automática**: O sistema verifica se existem quotas cadastradas. Se sim, usa quotas; senão, usa `aulas_contratadas`.

## 🎨 Interface

### Cards de Estatísticas
- **Total**: Azul claro (#e3f2fd)
- **Em Andamento**: Laranja claro (#fff3e0)
- **Concluídos**: Verde claro (#e8f5e8)
- **Matriculados**: Roxo claro (#f3e5f5)
- **Bloqueados**: Vermelho claro (#ffebee)
- **Cancelados**: Cinza claro (#fce4ec)

### Badges de Status
- **Lead**: Cinza (secondary)
- **Matriculado**: Azul (primary)
- **Em Andamento**: Amarelo (warning)
- **Concluído**: Verde (success)
- **Cancelado**: Vermelho (danger)

### Barra de Progresso
- Gradiente verde (#28a745 → #20c997)
- Altura: 20px
- Texto centralizado com percentual

## 📤 Exportação CSV

### Formato
- **Encoding**: UTF-8 com BOM (compatível com Excel)
- **Separador**: Ponto e vírgula (;)
- **Nome do arquivo**: `relatorio-alunos-status-YYYY-MM-DD-HHMMSS.csv`

### Colunas Exportadas
1. ID
2. Nome
3. CPF
4. Telefone
5. Email
6. Status
7. CFC
8. Serviço
9. Status Matrícula
10. Status Financeiro
11. Data Cadastro
12. Data Matrícula
13. Aulas Contratadas
14. Aulas Realizadas
15. Aulas Agendadas
16. Aulas Canceladas
17. Aulas Restantes
18. Percentual Conclusão

## 🖨️ Impressão

### Otimizações
- Remove filtros e botões (classe `.no-print`)
- Remove sidebar e topbar
- Ajusta espaçamento para papel
- Mantém cores dos badges para identificação

## 🔍 Validações e Regras de Negócio

### 1. Bloqueio Financeiro
- Alunos com `financial_status = 'bloqueado'` recebem ícone de cadeado
- Badge vermelho "Bloqueado" no status financeiro
- **Importante**: Aluno bloqueado não deve ter saldo incorreto

### 2. Aulas Sem Limite
- Se `aulas_contratadas = NULL`: exibe "Sem limite"
- Aulas restantes também exibe "Sem limite"
- Percentual de conclusão = 0%

### 3. Saldo Negativo
- Se cálculo resultar em negativo: força para 0
- Indica possível inconsistência nos dados

### 4. Matrículas Ativas
- Apenas matrículas com `status = 'ativa'` e `deleted_at IS NULL`
- Garante que apenas dados válidos sejam exibidos

## 🚀 Como Usar

### Acesso ao Relatório
1. Login como ADMIN ou SECRETARIA
2. Menu: **Relatórios → Alunos por Status**
3. Ou URL direta: `admin/index.php?page=relatorio-alunos-status`

### Aplicar Filtros
1. Selecione status desejado (ou deixe "Todos")
2. Escolha unidade/CFC (ou deixe "Todas")
3. Defina período de matrícula (opcional)
4. Clique em "Filtrar"

### Exportar Dados
1. Aplique filtros desejados
2. Clique em "Exportar CSV"
3. Arquivo será baixado automaticamente

### Imprimir
1. Aplique filtros desejados
2. Clique em "Imprimir"
3. Use função de impressão do navegador

## 🐛 Troubleshooting

### Problema: Relatório não carrega
**Solução**: Verificar permissões do usuário (deve ser ADMIN ou SECRETARIA)

### Problema: Aulas restantes negativas
**Solução**: Verificar inconsistência nos dados (aulas agendadas + realizadas > contratadas)

### Problema: "Sem limite" para todos
**Solução**: Verificar se campo `aulas_contratadas` está preenchido ou se existem quotas cadastradas

### Problema: Exportação CSV com caracteres estranhos
**Solução**: Abrir no Excel usando "Dados → De Texto/CSV" e selecionar UTF-8

## 📊 Exemplo de Uso

### Cenário 1: Verificar alunos em andamento
1. Filtro Status: "Em Andamento"
2. Visualizar quantos estão próximos de concluir (>80%)
3. Identificar quem está atrasado (<30%)

### Cenário 2: Identificar bloqueios financeiros
1. Verificar card "Bloqueados" no topo
2. Filtrar por status financeiro "Bloqueado"
3. Exportar lista para cobrança

### Cenário 3: Relatório mensal de conclusões
1. Filtro Status: "Concluído"
2. Período: Primeiro ao último dia do mês
3. Exportar CSV para análise

## 🔄 Manutenção

### Adicionar Novo Filtro
1. Adicionar campo no formulário (`admin/pages/relatorio-alunos-status.php`)
2. Capturar parâmetro GET no backend (`admin/api/relatorio-alunos-status.php`)
3. Adicionar condição WHERE na query SQL
4. Replicar no endpoint de exportação

### Adicionar Nova Coluna
1. Adicionar campo no SELECT da query
2. Processar no loop de resultados
3. Adicionar coluna na tabela HTML
4. Adicionar no CSV de exportação

## ⚠️ Considerações Importantes

1. **Performance**: Query otimizada com subqueries para contagens
2. **Segurança**: Validação de permissões em todos os endpoints
3. **Compatibilidade**: Suporta sistema antigo e novo de quotas
4. **Escalabilidade**: Preparado para grandes volumes de dados
5. **Manutenibilidade**: Código documentado e seguindo padrões do sistema

## 📝 Changelog

### Versão 1.0 (2026-03-03)
- ✅ Implementação inicial
- ✅ Filtros por status, CFC e período
- ✅ Cálculos de aulas (contratadas, realizadas, agendadas, restantes)
- ✅ Estatísticas agregadas
- ✅ Exportação CSV
- ✅ Modo de impressão
- ✅ Suporte a quotas por categoria
- ✅ Validação de bloqueio financeiro
- ✅ Permissões RBAC (ADMIN e SECRETARIA)

## 👥 Suporte

Para dúvidas ou problemas:
1. Verificar esta documentação
2. Consultar logs do sistema
3. Contatar equipe de desenvolvimento
