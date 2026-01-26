# Filiação no cadastro de aluno — Implementação

## 1. Diagnóstico (concluído)

- **Tabela fonte:** `students` (cadastro principal do aluno).
- **Colunas/tabelas de filiação:** Nenhuma existia (nenhum `mae`, `pai`, `nome_mae`, `nome_pai`, `filiacao_*` nem tabela separada).
- **Decisão:** Criar `nome_mae` e `nome_pai` em `students`.

## 2. Migração (idempotente, sem duplicidade)

- **Arquivo SQL:** `database/migrations/036_add_filiacao_to_students.sql`
- **Identificador:** Migration 036 — `036_add_filiacao_to_students`
- **Colunas:**  
  - `nome_mae` VARCHAR(255) DEFAULT NULL (após `rg_issue_date`)  
  - `nome_pai` VARCHAR(255) DEFAULT NULL (após `nome_mae`)
- **Segurança:** O SQL usa `INFORMATION_SCHEMA` para checar se cada coluna já existe; só executa `ALTER` se não existir. Pode rodar em local e produção várias vezes sem duplicar colunas.

**Executar migração:**

- **Opção A — Script PHP (recomendado):**  
  `php tools/run_migration_036_filiacao.php`  
  Usa `.env` (DB_HOST, DB_NAME, DB_USER, DB_PASS). Para **banco remoto acessível localmente**, configure o `.env` com os dados do remoto (ex.: `DB_HOST=auth-db803.hstgr.io`) e rode o script na máquina local.

- **Opção B — MySQL:**  
  `mysql -u ... -p ... < database/migrations/036_add_filiacao_to_students.sql`

## 3. UI (formulário aluno)

- **Onde:** `app/Views/alunos/form.php`
- **Seção:** Documentos, após “Data de Emissão do RG”.
- **Campos:**
  - **Filiação — Mãe:** `name="nome_mae"`, `maxlength="255"`, opcional, placeholder “Nome da mãe”.
  - **Filiação — Pai:** `name="nome_pai"`, `maxlength="255"`, opcional, placeholder “Nome do pai”.
- **Novo/Edição:** Os inputs usam `$student['nome_mae']` e `$student['nome_pai']` no editar; no novo, vêm vazios.

## 4. Backend

- **Controller:** `app/Controllers/AlunosController.php`
- **Método:** `prepareStudentData()` — incluídos `nome_mae` e `nome_pai` (trim, null se vazio).
- **Validação:** Nenhuma regra nova; campos opcionais. Não foi adicionado `required`.
- **Persistência:** `Student::create` / `update` usam o mesmo `$data`; as novas chaves são gravadas nas colunas correspondentes.
- **Editar:** `$student` vem de `Student::find($id)` (`SELECT *`), então `nome_mae` e `nome_pai` já vêm do banco e são exibidos no form.

## 5. Checklist de validação

| Item | Como validar |
|------|----------------|
| Criar aluno com filiação vazia | Novo aluno, deixar Mãe/Pai em branco → Salvar → OK. |
| Criar aluno com Mãe/Pai preenchidos | Preencher ambos → Salvar → Reabrir aluno → Valores mantidos. |
| Editar aluno existente | Editar aluno antigo (sem filiação) → Não perde outros dados; pode preencher filiação e salvar. |
| Mesmo código em local e produção | Rodar migração 036 (ou script) em cada ambiente; usar o mesmo código (form + controller). |

## 6. Arquivos alterados/criados

| Arquivo | Alteração |
|---------|-----------|
| `database/migrations/036_add_filiacao_to_students.sql` | **Criado** — migração idempotente. |
| `tools/run_migration_036_filiacao.php` | **Criado** — runner PHP idempotente. |
| `app/Views/alunos/form.php` | **Alterado** — inputs Filiação — Mãe / Pai na seção Documentos. |
| `app/Controllers/AlunosController.php` | **Alterado** — `prepareStudentData` persiste `nome_mae` e `nome_pai`. |
| `.docs/IMPLEMENTACAO_FILIACAO_ALUNO.md` | **Criado** — este documento. |

## 7. Produção / Banco remoto

### Rodar localmente contra o banco remoto

O banco de produção é remoto (`auth-db803.hstgr.io`) mas acessível da sua máquina. Configure o **.env** com:

- `DB_HOST=auth-db803.hstgr.io` (ou o host do remoto)
- `DB_NAME=...`, `DB_USER=...`, `DB_PASS=...`

Depois rode **localmente**:

```bash
php tools/run_migration_036_filiacao.php
```

O script exibe `Banco: ... | Host: ...` e aplica a migration no banco configurado. É idempotente.

### Rodar a migration no servidor (via SSH)

1. Conectar ao servidor e ir para o projeto:
   ```bash
   ssh -p 65002 u502697186@45.152.46.150
   cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel
   ```
   (Ou o caminho onde está o `painel` no seu ambiente.)

2. Atualizar o código e rodar o script **remoto**:
   ```bash
   git pull origin master
   php tools/run_migration_036_filiacao_remote.php
   ```

**Comando único (copiar e colar no terminal SSH):**
```bash
cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel && git pull origin master && php tools/run_migration_036_filiacao_remote.php
```

- O script `run_migration_036_filiacao_remote.php` usa o `.env` do servidor (banco de produção).
- É idempotente: pode rodar mais de uma vez sem duplicar colunas.

Depois do deploy do código (form + controller), confirmar que criar/editar aluno com filiação vazia ou preenchida funciona e que editar não perde dados antigos.
