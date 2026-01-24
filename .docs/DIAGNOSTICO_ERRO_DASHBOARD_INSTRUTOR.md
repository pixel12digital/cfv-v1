# Diagnóstico: Erro "Erro ao conectar ao banco" no Dashboard do Instrutor

## Arquivos que geram a mensagem "Erro ao conectar ao banco"

1. **`app/Controllers/DashboardController.php`** (linhas 85, 151, 194)
   - Método `index()` - captura `\PDOException` e mostra mensagem genérica
   
2. **`app/Core/Router.php`** (linha 107)
   - Método `dispatch()` - captura `\PDOException` globalmente e mostra mensagem genérica

## Problema Identificado

O método `dashboardInstrutor()` chama `$userModel->findWithLinks($userId)` que faz:
```sql
LEFT JOIN instructors i ON i.user_id = u.id
```

**A tabela `instructors` pode não existir** no banco de dados. O sistema legado usa `instrutores` (com 'u'), mas o novo sistema espera `instructors` (sem 'u').

## Correções Implementadas

### 1. Logging Detalhado Adicionado

**`app/Controllers/DashboardController.php`**:
- Logging completo em `dashboardInstrutor()` com:
  - Classe da exceção
  - SQLSTATE completo
  - Mensagem completa
  - Arquivo e linha
  - Stack trace completo
  - Variáveis de sessão (user_id, current_role, user_type)

**`app/Core/Router.php`**:
- Logging detalhado em `dispatch()` para capturar erros globais
- Inclui REQUEST_URI e variáveis de sessão

### 2. Tratamento de Erro de Tabela Não Encontrada

**`app/Controllers/DashboardController.php::dashboardInstrutor()`**:
- Captura `\PDOException` em `findWithLinks()`
- Se SQLSTATE = `42S02` (tabela não encontrada):
  - Busca usuário sem links usando `find()`
  - Tenta buscar `instructor_id` da tabela `instructors` (novo sistema)
  - Se falhar, tenta tabela `instrutores` (sistema legado)
  - Se ambas falharem, usa `user_id` como `instructor_id` (fallback)
- Logging detalhado de cada tentativa

### 3. Tratamento de Erros em Queries

- `findByInstructorWithTheoryDedupe()` - captura erro e continua com `$nextLesson = null`
- Query de aulas de hoje - captura erro e continua com `$todayLessons = []`

## Como Reproduzir e Coletar Dados

1. **Login como instrutor**: `rwavieira@gmail.com`
2. **Acessar**: `/dashboard`
3. **Verificar logs** (error_log do PHP):
   - Buscar por `[DashboardController::dashboardInstrutor]`
   - Buscar por `[Router]`
   - Coletar:
     - SQLSTATE completo (ex: `42S02`, `42S22`, `1146`)
     - Mensagem completa do erro
     - Tabela/coluna mencionada no erro
     - Stack trace completo

## Próximos Passos

Após coletar os logs:

1. **Se SQLSTATE = `42S02` (tabela não encontrada)**:
   - Verificar qual tabela está faltando (`instructors` ou `instrutores`)
   - Criar tabela ou ajustar queries

2. **Se SQLSTATE = `42S22` (coluna não encontrada)**:
   - Verificar qual coluna está faltando
   - Ajustar query ou adicionar coluna

3. **Se SQLSTATE = `1146` (tabela não existe)**:
   - Mesmo tratamento de `42S02`

## Arquivos Modificados

- `app/Controllers/DashboardController.php` - Adicionado logging e tratamento de erros
- `app/Core/Router.php` - Adicionado logging detalhado

## Entregáveis

✅ Caminho do arquivo que imprime a mensagem: `app/Controllers/DashboardController.php` (linhas 85, 151, 194) e `app/Core/Router.php` (linha 107)

✅ Logging detalhado implementado para capturar:
- SQLSTATE completo
- Mensagem completa
- Tabela/coluna mencionada
- Stack trace completo
- Variáveis de sessão

✅ Tratamento de erro de tabela não encontrada implementado

⏳ **Aguardando logs de produção para identificar SQLSTATE e tabela/coluna específica**
