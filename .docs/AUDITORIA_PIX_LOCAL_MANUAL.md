# AUDITORIA: PIX Local/Manual na Matrícula

## 0. RESUMO DO FLUXO ATUAL (ANTES DO PATCH)

### 0.1. Onde o botão "Gerar Cobrança TF/EFI" é habilitado

**Arquivo:** `app/Views/alunos/matricula_show.php` (linhas 570-605)

**Condições para exibir o botão:**
```php
// Linha 580-581: Ocultar quando payment_method = 'cartao'
$isCartao = ($enrollment['payment_method'] ?? '') === 'cartao';

// Linha 584-590: Mostrar botão apenas se:
if (!$isCartao && 
    !empty($enrollment['installments']) && 
    $hasOutstanding && 
    !$hasActiveCharge && 
    ($enrollment['billing_status'] === 'draft' || 
     $enrollment['billing_status'] === 'ready' || 
     $enrollment['billing_status'] === 'error')) {
    // Botão "Gerar Cobrança Efí" aparece
}
```

**Resumo:**
- ✅ Botão é **ocultado** quando `payment_method = 'cartao'`
- ❌ Botão **aparece** para `payment_method = 'pix'` (precisa ser ocultado)
- ❌ Botão **aparece** para `payment_method = 'boleto'` (deve continuar aparecendo)

**Endpoint chamado:** `POST /api/payments/generate` → `PaymentsController::generate()`

---

### 0.2. Tabelas/Campos que guardam método de pagamento e status

**Tabela Principal:** `enrollments`

#### Campos de Método de Pagamento:
- `payment_method` (ENUM: 'pix','boleto','cartao','entrada_parcelas')
- `entry_payment_method` (ENUM: 'dinheiro','pix','cartao','boleto') - Forma de pagamento da entrada

#### Campos de Status Financeiro:
- `financial_status` (ENUM: 'em_dia','pendente','bloqueado')
- `billing_status` (ENUM: 'draft','ready','generated','error')

#### Campos de Gateway (Cobrança):
- `gateway_provider` (VARCHAR 50) - Provedor ('efi', 'local', etc.)
- `gateway_charge_id` (VARCHAR 255) - ID da cobrança no gateway (NULL para pagamentos locais)
- `gateway_last_status` (VARCHAR 50) - Último status ('paid', 'waiting', 'paid_local', etc.)
- `gateway_last_event_at` (DATETIME) - Data/hora do último evento
- `gateway_payment_url` (TEXT) - Link/JSON do pagamento
- `gateway_pix_code` (TEXT) - Código PIX (copia-e-cola)
- `gateway_barcode` (VARCHAR 255) - Linha digitável do boleto

#### Campos de Valores:
- `final_price` (DECIMAL 10,2) - Preço final
- `entry_amount` (DECIMAL 10,2) - Valor da entrada recebida
- `outstanding_amount` (DECIMAL 10,2) - Saldo devedor (final_price - entry_amount)

**Migrations:**
- `002_create_phase1_tables.sql` - Campos básicos
- `009_add_payment_plan_to_enrollments.sql` - Parcelamento + billing_status
- `010_add_entry_fields_to_enrollments.sql` - Entrada + outstanding_amount
- `030_add_gateway_fields_to_enrollments.sql` - Campos do gateway

---

### 0.3. Como o "cartão local" funciona

**Arquivo:** `app/Controllers/PaymentsController.php` → método `markPaid()` (linhas 800-977)

#### Fluxo do Cartão Local:

1. **Validação:**
   - Verifica que `payment_method = 'cartao'`
   - Verifica saldo devedor > 0
   - Valida número de parcelas (1-24)

2. **Atualização no Banco:**
```sql
UPDATE enrollments 
SET 
    payment_method = 'cartao',
    installments = ?,
    outstanding_amount = 0,
    entry_amount = final_price,  -- Para zerar outstanding_amount
    financial_status = 'em_dia',
    billing_status = 'generated',
    gateway_provider = 'local',      -- ← Identifica como pagamento local
    gateway_last_status = 'paid',    -- ← Status pago
    gateway_last_event_at = NOW(),
    gateway_charge_id = NULL,         -- ← Sem cobrança no gateway
    gateway_payment_url = NULL,
    gateway_pix_code = NULL,
    gateway_barcode = NULL
WHERE id = ?
```

3. **Campos preenchidos ao dar baixa:**
   - `gateway_provider = 'local'` - Identifica como pagamento local/manual
   - `gateway_last_status = 'paid'` - Status pago
   - `outstanding_amount = 0` - Zera saldo devedor
   - `financial_status = 'em_dia'` - Status financeiro em dia
   - `billing_status = 'generated'` - Marca como gerado (mas localmente)
   - `entry_amount = final_price` - Ajusta entrada para zerar saldo

4. **UI (Botão):**
   - Arquivo: `app/Views/alunos/matricula_show.php` (linhas 607-614)
   - Botão "✅ Confirmar Pagamento" aparece quando:
     - `payment_method = 'cartao'`
     - `outstanding_amount > 0`
     - `billing_status` em 'draft', 'ready', 'error' ou `gateway_provider = 'local'`

5. **JavaScript:**
   - Função: `confirmarPagamentoCartao()` (linha ~1150)
   - Chama: `POST /api/payments/mark-paid` com `payment_method: 'cartao'`

---

### 0.4. Bloqueio EFI para Cartão

**Arquivo:** `app/Services/EfiPaymentService.php` (linhas 78-89)

```php
// BLOQUEAR EFI para Cartão (fail-safe)
$paymentMethod = $enrollment['payment_method'] ?? 'pix';
if ($paymentMethod === 'cartao' || $paymentMethod === 'credit_card') {
    return [
        'ok' => false,
        'message' => 'Cartão de crédito é pagamento local (maquininha). Use a opção "Confirmar Pagamento" para dar baixa manual.'
    ];
}
```

**Status:** ✅ Cartão já está bloqueado no service (fail-safe)

---

### 0.5. Estrutura de Configurações do CFC

**Tabela:** `cfcs`

**Campos existentes:**
- `id` (PK)
- `nome` (VARCHAR 255)
- `cnpj` (VARCHAR 18)
- `endereco` (TEXT) - ❌ Não usado
- `telefone` (VARCHAR 20) - ❌ Não usado
- `email` (VARCHAR 255) - ❌ Não usado
- `logo_path` (VARCHAR 255) - ✅ Usado
- `created_at`, `updated_at`

**Controller:** `app/Controllers/ConfiguracoesController.php`
- Método `cfc()` - Exibe página
- Método `salvarCfc()` - Salva configurações

**View:** `app/Views/configuracoes/cfc.php`

**Model:** `app/Models/Cfc.php`

---

## 1. COMPORTAMENTO DESEJADO (PIX = MANUAL/LOCAL)

### 1.1. UI (Matrícula)

#### Ao selecionar PIX:
- ❌ **NÃO** mostrar "Gerar cobrança TF/EFI"
- ✅ Mostrar ação/UX de "Ver dados do PIX" (modal/pop-up)
- ✅ Adicionar checkbox "Pagamento já realizado / Dar baixa agora"
- ✅ Se marcado: salvar com status pago imediatamente
- ✅ Se não marcado: fica pendente e poderá ser baixado depois

### 1.2. Backend (sem quebrar EFI)

- ✅ Boleto continua com EFI (inalterado)
- ✅ PIX passa a ser tratado como "pagamento local/manual", igual cartão:
  - `gateway_charge_id` deve permanecer NULL
  - `gateway_last_status` deve registrar 'paid_local' ou 'paid' (reaproveitar padrão do cartão)
  - `gateway_provider = 'local'`
  - Baixa manual preenche data/usuário (se já existir essa estrutura)

### 1.3. Onde armazenar dados do PIX do CFC

**Opção escolhida:** Adicionar campos na tabela `cfcs`

**Campos a criar:**
- `pix_banco` (VARCHAR 255) - Banco/Instituição
- `pix_titular` (VARCHAR 255) - Nome do titular
- `pix_chave` (VARCHAR 255) - Chave PIX
- `pix_observacao` (TEXT) - Observação opcional

**UI de configuração:**
- Adicionar seção "PIX" em `app/Views/configuracoes/cfc.php`
- Salvar via `ConfiguracoesController::salvarCfc()`

---

## 2. ARQUIVOS QUE SERÃO MODIFICADOS

### 2.1. Migrations
- `database/migrations/036_add_pix_fields_to_cfcs.sql` (NOVO)

### 2.2. Views
- `app/Views/alunos/matricula_show.php` - Ocultar botão EFI para PIX, adicionar modal e botão de baixa
- `app/Views/configuracoes/cfc.php` - Adicionar campos de configuração PIX

### 2.3. Controllers
- `app/Controllers/PaymentsController.php` - Estender `markPaid()` para aceitar PIX
- `app/Controllers/ConfiguracoesController.php` - Adicionar salvamento de campos PIX

### 2.4. Services
- `app/Services/EfiPaymentService.php` - Bloquear geração EFI quando PIX (fail-safe)

### 2.5. Models
- `app/Models/Cfc.php` - Adicionar métodos para buscar dados PIX (opcional)

---

## 3. CRITÉRIOS DE ACEITE

- ✅ Método PIX nunca chama geração EFI / nunca exibe "Gerar cobrança TF"
- ✅ Modal "Dados do PIX" aparece e usa os dados cadastrados
- ✅ Na matrícula, posso marcar pago na hora (entrada e/ou saldo) e o status financeiro reflete isso
- ✅ Se eu não marcar pago, fica pendente e consigo dar baixa depois (mesmo fluxo do cartão)
- ✅ Boleto segue 100% intacto

---

## 4. OBSERVAÇÕES IMPORTANTES

- Não alterar nomes/semântica de status existentes
- Reaproveitar exatamente o mesmo padrão do "cartão local" para PIX manual
- Mínima intervenção e máxima estabilidade
- Manter compatibilidade com dados existentes
