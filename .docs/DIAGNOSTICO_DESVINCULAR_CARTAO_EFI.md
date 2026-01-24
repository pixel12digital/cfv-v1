# Diagnóstico: Desvincular Cartão da EFI

**Data:** 24/01/2026  
**Objetivo:** Mapear fluxo atual e criar plano de alteração para desvincular Cartão da EFI

---

## 📋 TAREFA 1: FLUXO ATUAL (COM EVIDÊNCIAS)

### 1.1. Onde nasce a cobrança na matrícula?

#### **Ponto de Entrada Principal:**
- **Arquivo:** `app/Views/alunos/matricula_show.php`
- **Função JavaScript:** `gerarCobrancaEfi()` (linha ~775)
- **Endpoint chamado:** `POST /api/payments/generate`
- **Trigger:** Botão "Gerar Cobrança Efí" aparece quando:
  - `outstanding_amount > 0`
  - `installments` não está vazio
  - `billing_status` é 'draft', 'ready' ou 'error'
  - Não existe cobrança ativa (`gateway_charge_id` vazio OU status finalizado)

#### **Fluxo Completo:**

```
1. Usuário (Admin/Secretaria) acessa: /matriculas/{id}
   ↓
2. Visualiza formulário de edição (matricula_show.php)
   ↓
3. Clica em "Gerar Cobrança Efí" (se condições atendidas)
   ↓
4. JavaScript: gerarCobrancaEfi()
   ├─ Valida outstanding_amount > 0
   ├─ Mostra confirmação com valores
   └─ Faz fetch POST /api/payments/generate
      ↓
5. Backend: PaymentsController::generate()
   ├─ Valida autenticação (sessão)
   ├─ Valida permissão (ADMIN/SECRETARIA)
   ├─ Busca matrícula: Enrollment::findWithDetails($enrollmentId)
   ├─ Valida outstanding_amount > 0
   ├─ Verifica idempotência (cobrança já existe?)
   └─ Chama: EfiPaymentService::createCharge($enrollment)
      ↓
6. Service: EfiPaymentService::createCharge()
   ├─ Valida configuração (client_id, client_secret)
   ├─ Obtém token OAuth: getAccessToken()
   ├─ Determina tipo de pagamento:
   │  ├─ payment_method = 'pix' → API Pix (/v2/cob)
   │  ├─ payment_method = 'cartao' → API Cobranças (/v1/charge/one-step)
   │  ├─ payment_method = 'boleto' + installments=1 → API Cobranças (/v1/charge/one-step)
   │  └─ payment_method = 'boleto' + installments>1 → API Carnê (/v1/carnet)
   ├─ Monta payload conforme tipo
   ├─ Faz requisição HTTP à EFI
   ├─ Processa resposta
   └─ Atualiza banco: updateEnrollmentStatus()
      ├─ gateway_charge_id
      ├─ gateway_last_status
      ├─ gateway_payment_url
      ├─ billing_status = 'generated'
      ├─ financial_status (recalculado)
      └─ gateway_last_event_at
```

---

### 1.2. Arquivos/Controllers/Services Envolvidos

#### **Frontend:**
- **`app/Views/alunos/matricula_show.php`**
  - Linha ~775: Função `gerarCobrancaEfi()`
  - Linha ~763: `fetch('<?= base_path('api/payments/generate') ?>', ...)`
  - Linha ~148: Select `payment_method` (pix/boleto/cartao/entrada_parcelas)

#### **Backend - Controller:**
- **`app/Controllers/PaymentsController.php`**
  - Linha ~25: Método `generate()`
  - Linha ~110: `$this->efiService->createCharge($enrollment)`
  - Linha ~158: Método `webhookEfi()` (recebe notificações)

#### **Backend - Service:**
- **`app/Services/EfiPaymentService.php`**
  - Linha ~68: Método `createCharge($enrollment)` - **PRINCIPAL**
  - Linha ~529: Método `createCarnet()` (para boleto parcelado)
  - Linha ~984: Método `parseWebhook()` (processa webhooks)
  - Linha ~2343: Método `syncCharge()` (sincroniza status)
  - Linha ~2521: Método `updateEnrollmentStatus()` (atualiza banco)

#### **Backend - Model:**
- **`app/Models/Enrollment.php`**
  - Método `findWithDetails($id)` - busca matrícula com dados do aluno

#### **Rotas:**
- **`app/routes/web.php`**
  - Linha ~201: `POST /api/payments/generate` → `PaymentsController::generate()`
  - Linha ~206: `POST /api/payments/webhook/efi` → `PaymentsController::webhookEfi()`

---

### 1.3. Rotas/Endpoints chamados no front

| Endpoint | Método | Controller | Quando é chamado |
|----------|--------|------------|------------------|
| `/api/payments/generate` | POST | `PaymentsController::generate()` | Botão "Gerar Cobrança Efí" |
| `/api/payments/sync` | POST | `PaymentsController::sync()` | Botão "Sincronizar Cobrança" |
| `/api/payments/status` | GET | `PaymentsController::status()` | Atualizar status do carnê |
| `/api/payments/cancel` | POST | `PaymentsController::cancel()` | Cancelar carnê |
| `/matriculas/{id}/atualizar` | POST | `AlunosController::updateEnrollment()` | Salvar edição de matrícula |

---

### 1.4. Como o sistema decide o gateway (EFI) conforme forma de pagamento?

#### **Decisão no Service:**
**Arquivo:** `app/Services/EfiPaymentService.php`  
**Método:** `createCharge()` (linhas 127-301)

```php
// Linha 127-130: Determina método de pagamento
$paymentMethod = $enrollment['payment_method'] ?? 'pix';
$installments = intval($enrollment['installments'] ?? 1);
$isPix = ($paymentMethod === 'pix' && $installments === 1);

// Linha 201-204: Árvore de decisão
$isCreditCard = ($paymentMethod === 'cartao' || $paymentMethod === 'credit_card') && $installments > 1;
$isCreditCardSingle = ($paymentMethod === 'cartao' || $paymentMethod === 'credit_card') && $installments === 1;
$isBoletoSingle = ($paymentMethod === 'boleto') && $installments === 1;
$isCarnet = ($paymentMethod === 'boleto') && $installments > 1;

// Linha 206-240: Se cartão (parcelado ou à vista)
if ($isCreditCard || $isCreditCardSingle) {
    // Monta payload com payment.credit_card
    // Endpoint: POST /v1/charge/one-step
}
```

#### **Campo de Decisão:**
- **Tabela:** `enrollments`
- **Campo:** `payment_method` (ENUM: 'pix', 'boleto', 'cartao', 'entrada_parcelas')
- **Campo auxiliar:** `installments` (INT, 1-12)

#### **Observação Importante:**
**Atualmente, TODOS os métodos de pagamento passam pela EFI:**
- ✅ PIX → API Pix (`/v2/cob`)
- ✅ Boleto → API Cobranças (`/v1/charge/one-step`)
- ✅ Cartão → API Cobranças (`/v1/charge/one-step`) ← **PRECISA SER DESVINCULADO**
- ✅ Carnê (Boleto parcelado) → API Carnê (`/v1/carnet`)

---

### 1.5. Existe enum/tabela/campo para gateway?

#### **Campos na Tabela `enrollments`:**

| Campo | Tipo | Descrição | Migration |
|-------|------|-----------|-----------|
| `payment_method` | ENUM('pix','boleto','cartao','entrada_parcelas') | Forma de pagamento | 002/009 |
| `gateway_provider` | VARCHAR(50) | Provedor ('efi', 'asaas', etc) | 030 |
| `gateway_charge_id` | VARCHAR(255) | ID da cobrança no gateway | 030 |
| `gateway_last_status` | VARCHAR(50) | Último status do gateway | 030 |
| `gateway_payment_url` | TEXT | Link/JSON do pagamento | 031 |
| `gateway_pix_code` | TEXT | Código PIX (copia-e-cola) | 030 |
| `gateway_barcode` | VARCHAR(255) | Linha digitável do boleto | 030 |
| `gateway_last_event_at` | DATETIME | Data/hora do último evento | 030 |
| `billing_status` | ENUM('draft','ready','generated','error') | Status da geração | 009 |

#### **Não existe:**
- ❌ Tabela separada `payments` ou `transactions`
- ❌ Tabela separada `installments` (parcelas individuais)
- ❌ Campo `is_gateway_required` ou similar

---

### 1.6. Quais chamadas EFI acontecem quando método é cartão?

#### **Endpoint Chamado:**
- **URL:** `POST /v1/charge/one-step`
- **Base URL:** `https://apis.gerencianet.com.br` (produção) ou `https://apis-h.gerencianet.com.br` (sandbox)
- **Arquivo:** `app/Services/EfiPaymentService.php`
- **Método:** `createCharge()` → `makeRequest()` (linha ~397)

#### **Payload Enviado (Cartão):**
```php
// Linha 178-186: Base do payload
$payload = [
    'items' => [
        [
            'name' => $enrollment['service_name'] ?? 'Matrícula',
            'value' => $amountInCents, // outstanding_amount * 100
            'amount' => 1
        ]
    ]
];

// Linha 212-240: Dados do cliente e cartão
$payload['customer'] = [
    'name' => $customerName,
    'cpf' => $cpf,
    'email' => $student['email'] ?? null,
    'phone_number' => $student['phone'] ?? null
];

$payload['payment'] = [
    'credit_card' => [
        'installments' => $installments,
        'customer' => $payload['customer'], // Duplicado aqui também
        'billing_address' => [
            'street' => $student['street'] ?? 'Não informado',
            'number' => $student['number'] ?? 'S/N',
            'neighborhood' => $student['neighborhood'] ?? '',
            'zipcode' => preg_replace('/[^0-9]/', '', $student['cep'] ?? ''),
            'city' => $student['city'] ?? '',
            'state' => $student['state_uf'] ?? ''
        ]
    ]
];
```

#### **Momento da Chamada:**
- **Quando:** Ao clicar "Gerar Cobrança Efí" com `payment_method = 'cartao'`
- **Antes:** Validações de autenticação, permissão, saldo devedor
- **Depois:** Atualização do banco com `gateway_charge_id` e status

#### **Resposta Esperada:**
```json
{
  "data": {
    "charge_id": 123456,
    "status": "waiting",
    "payment": {
      "credit_card": {
        "payment_link": "https://..."
      }
    }
  }
}
```

---

### 1.7. Como o financeiro é atualizado depois do pagamento?

#### **Atualização via Webhook (Automática):**

**Fluxo:**
```
1. EFI envia webhook → POST /api/payments/webhook/efi
   ↓
2. PaymentsController::webhookEfi()
   └─ Chama: EfiPaymentService::parseWebhook($payload)
      ↓
3. EfiPaymentService::parseWebhook()
   ├─ Extrai charge_id do payload
   ├─ Busca matrícula por gateway_charge_id
   ├─ Extrai status do payload
   ├─ Mapeia status:
   │  ├─ 'paid' → financial_status = 'em_dia'
   │  ├─ 'settled' → financial_status = 'em_dia'
   │  ├─ 'waiting' → financial_status = 'pendente'
   │  └─ 'unpaid' → financial_status = 'pendente'
   └─ Atualiza banco:
      ├─ gateway_last_status
      ├─ gateway_last_event_at
      ├─ financial_status (mapeado ou recalculado)
      └─ billing_status
```

**Arquivo:** `app/Services/EfiPaymentService.php`  
**Método:** `parseWebhook()` (linha ~984)  
**Método auxiliar:** `mapGatewayStatusToFinancialStatus()` (linha ~2011)

#### **Atualização Manual (Sincronização):**

**Fluxo:**
```
1. Usuário clica "Sincronizar Cobrança"
   ↓
2. JavaScript: sincronizarCobrancaEfi()
   └─ fetch POST /api/payments/sync
      ↓
3. PaymentsController::sync()
   └─ Chama: EfiPaymentService::syncCharge($enrollment)
      ↓
4. EfiPaymentService::syncCharge()
   ├─ GET /v1/charge/{charge_id} (consulta EFI)
   ├─ Extrai status atualizado
   ├─ Mapeia para financial_status
   └─ Atualiza banco (mesmo processo do webhook)
```

**Arquivo:** `app/Services/EfiPaymentService.php`  
**Método:** `syncCharge()` (linha ~2343)

#### **Recálculo Automático:**

**Método:** `recalculateFinancialStatus()` (linha ~2488)

```php
// Lógica:
if (financial_status === 'bloqueado') {
    return 'bloqueado'; // Mantém bloqueado
}

$outstandingAmount = floatval($enrollment['outstanding_amount'] ?? 0);
if ($outstandingAmount <= 0) {
    return 'em_dia';
} else {
    return 'pendente';
}
```

**Chamado em:**
- `updateEnrollmentStatus()` (linha ~2560)
- `syncCharge()` (linha ~2447)
- `syncCarnet()` (linha ~2164)

---

### 1.8. Tabela/Colunas que representam status financeiro

#### **Tabela Principal: `enrollments`**

| Coluna | Tipo | Valores | Significado |
|--------|------|---------|-------------|
| `financial_status` | ENUM | 'em_dia', 'pendente', 'bloqueado' | Status financeiro interno |
| `outstanding_amount` | DECIMAL(10,2) | >= 0 | Saldo devedor (final_price - entry_amount) |
| `gateway_last_status` | VARCHAR(50) | 'paid', 'waiting', 'unpaid', etc | Status do gateway |
| `billing_status` | ENUM | 'draft', 'ready', 'generated', 'error' | Status da geração de cobrança |
| `entry_amount` | DECIMAL(10,2) | >= 0 | Valor da entrada recebida |
| `final_price` | DECIMAL(10,2) | > 0 | Valor final da matrícula |

#### **Não existe:**
- ❌ Campo `paid_amount` (valor pago)
- ❌ Campo `paid_date` (data de pagamento)
- ❌ Campo `interest_amount` (juros)
- ❌ Tabela `payments` (histórico de pagamentos)

**Conclusão:** O sistema não rastreia pagamentos individuais. Apenas:
- `outstanding_amount` indica quanto falta pagar
- `financial_status` indica se está em dia/pendente/bloqueado
- `gateway_last_status` indica status no gateway (quando há cobrança gerada)

---

### 1.9. Existe "baixa manual" hoje?

#### **❌ NÃO existe baixa manual direta**

**O que existe:**
- ✅ Edição manual de `financial_status` no formulário de matrícula
- ✅ Edição manual de `outstanding_amount` (via `entry_amount`)
- ✅ Sincronização manual com EFI (botão "Sincronizar Cobrança")

**O que NÃO existe:**
- ❌ Botão "Marcar como Pago" que atualiza financeiro sem gateway
- ❌ Formulário de "Baixa Manual" de pagamento
- ❌ Endpoint `/api/payments/mark-paid` ou similar

**Evidência:**
- `app/Views/alunos/matricula_show.php`: Não há botão de baixa manual
- `app/Controllers/PaymentsController.php`: Não há método de baixa manual
- `app/Services/EfiPaymentService.php`: Não há método de baixa manual

---

### 1.10. Onde o histórico guarda parcelas hoje?

#### **✅ Existe Service de Visualização de Parcelas:**

**Arquivo:** `app/Services/InstallmentsViewService.php`  
**Método:** `getInstallmentsViewForEnrollment($enrollment)` (linha ~21)

#### **Fontes de Dados (em ordem de prioridade):**

1. **Carnê (JSON em `gateway_payment_url`):**
   - Se `gateway_payment_url` é JSON com `type: 'carne'`
   - Extrai parcelas de `charges[]` array
   - Cada parcela tem: `charge_id`, `expire_at`, `status`, `billet_link`

2. **Cobrança Única (`gateway_charge_id`):**
   - Se existe `gateway_charge_id` mas não é carnê
   - Cria 1 parcela virtual com dados da cobrança

3. **Cálculo Dinâmico (sem cobrança gerada):**
   - Usa `installments` e `first_due_date`
   - Calcula parcelas dividindo `outstanding_amount` por `installments`
   - Gera datas adicionando meses a partir de `first_due_date`

#### **Armazenamento Real:**

**Tabela `enrollments`:**
- `installments` (INT) - Número de parcelas (1-12)
- `first_due_date` (DATE) - Vencimento da 1ª parcela
- `gateway_payment_url` (TEXT) - JSON com dados do carnê (quando aplicável)

**Não existe:**
- ❌ Tabela `installments` separada
- ❌ Tabela `payment_installments` ou similar
- ❌ Histórico de pagamentos individuais por parcela

**Conclusão:** Parcelas são **calculadas dinamicamente** ou **extraídas do JSON do carnê**. Não há persistência individual de cada parcela.

---

## 📋 TAREFA 2: MAPA DE IMPACTO (MUDANÇAS NECESSÁRIAS)

### 2.1. Cartão sem EFI (bloquear/evitar qualquer request ao gateway)

#### **Ponto 1: Bloquear criação de cobrança EFI para cartão**

**Arquivo:** `app/Services/EfiPaymentService.php`  
**Método:** `createCharge()` (linha ~68)

**O que remover/condicionar:**
```php
// ANTES (linha 201-240):
if ($isCreditCard || $isCreditCardSingle) {
    // Monta payload de cartão
    // Faz POST /v1/charge/one-step
}

// DEPOIS:
if ($isCreditCard || $isCreditCardSingle) {
    // Retornar erro informando que cartão não usa gateway
    return [
        'ok' => false,
        'message' => 'Cartão de crédito não utiliza gateway. Use a opção "Já está pago?" para confirmar pagamento local.'
    ];
}
```

**Comportamento novo:**
- Se `payment_method = 'cartao'`, não fazer requisição à EFI
- Retornar erro claro informando que deve usar baixa manual

---

#### **Ponto 2: Ocultar botão "Gerar Cobrança Efí" para cartão**

**Arquivo:** `app/Views/alunos/matricula_show.php`  
**Linha:** ~597-607 (condição do botão)

**O que condicionar:**
```php
// ANTES:
if (!empty($enrollment['installments']) && $hasOutstanding && !$hasActiveCharge && ...) {
    // Mostra botão "Gerar Cobrança Efí"
}

// DEPOIS:
if (!empty($enrollment['installments']) && 
    $hasOutstanding && 
    !$hasActiveCharge && 
    $enrollment['payment_method'] !== 'cartao' && // ← ADICIONAR
    ...) {
    // Mostra botão "Gerar Cobrança Efí"
}
```

**Comportamento novo:**
- Botão não aparece quando `payment_method = 'cartao'`
- Em vez disso, mostrar botão "Confirmar Pagamento" (novo)

---

### 2.2. Popup "Já está pago?" e ação de "confirmar pago"

#### **Ponto 3: Adicionar popup ao selecionar Cartão**

**Arquivo:** `app/Views/alunos/matricula_show.php`  
**Linha:** ~148-156 (select `payment_method`)

**O que adicionar:**
```javascript
// Adicionar listener ao select payment_method
document.getElementById('payment_method').addEventListener('change', function() {
    if (this.value === 'cartao') {
        // Mostrar popup "Já está pago?"
        const isPaid = confirm('Já está pago?\n\nSelecione:\n- OK = Sim, já foi pago na maquininha\n- Cancelar = Não, ainda não foi pago');
        
        if (isPaid) {
            // Chamar função de confirmar pagamento
            confirmarPagamentoCartao();
        }
    }
});
```

**Comportamento novo:**
- Ao selecionar "Cartão", popup aparece imediatamente
- Se confirmar, chama função de baixa manual
- Se cancelar, mantém seleção mas não faz nada ainda

---

#### **Ponto 4: Criar função JavaScript de confirmar pagamento**

**Arquivo:** `app/Views/alunos/matricula_show.php`  
**Linha:** ~750 (após `updatePaymentPlanFields()`)

**O que adicionar:**
```javascript
function confirmarPagamentoCartao() {
    const enrollmentId = <?= $enrollment['id'] ?>;
    const outstandingAmount = <?= $enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?? 0 ?>;
    
    if (outstandingAmount <= 0) {
        alert('Não há saldo devedor para confirmar pagamento.');
        return;
    }
    
    if (!confirm(`Confirmar pagamento de R$ ${outstandingAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2})}?\n\nEste pagamento foi realizado na maquininha local e será registrado imediatamente.`)) {
        return;
    }
    
    // Chamar endpoint de baixa manual
    fetch('<?= base_path('api/payments/mark-paid') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            enrollment_id: enrollmentId,
            payment_method: 'cartao'
        })
    })
    .then(async response => {
        const data = await response.json();
        if (data.ok) {
            alert('Pagamento confirmado com sucesso!');
            window.location.reload();
        } else {
            alert('Erro: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao confirmar pagamento. Tente novamente.');
    });
}
```

**Comportamento novo:**
- Função JavaScript que chama endpoint de baixa manual
- Atualiza financeiro imediatamente sem passar pela EFI

---

#### **Ponto 5: Criar endpoint de baixa manual**

**Arquivo:** `app/Controllers/PaymentsController.php`  
**Linha:** Após método `cancel()` (~773)

**O que adicionar:**
```php
/**
 * POST /api/payments/mark-paid
 * Marca pagamento como pago (baixa manual, sem gateway)
 */
public function markPaid()
{
    header('Content-Type: application/json; charset=utf-8');
    
    // Validações padrão (autenticação, permissão, método POST)
    // ... (mesmo padrão dos outros métodos)
    
    $input = json_decode(file_get_contents('php://input'), true);
    $enrollmentId = $input['enrollment_id'] ?? null;
    $paymentMethod = $input['payment_method'] ?? null;
    
    // Validar que é cartão
    if ($paymentMethod !== 'cartao') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'Baixa manual só é permitida para cartão de crédito.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Buscar matrícula
    $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
    // ... validações ...
    
    // Validar que payment_method é cartão
    if ($enrollment['payment_method'] !== 'cartao') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'Esta matrícula não está configurada para pagamento com cartão.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Atualizar financeiro
    $db = \App\Config\Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE enrollments 
        SET outstanding_amount = 0,
            financial_status = 'em_dia',
            billing_status = 'generated',
            gateway_provider = 'local',
            gateway_last_status = 'paid',
            gateway_last_event_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$enrollmentId]);
    
    echo json_encode([
        'ok' => true,
        'message' => 'Pagamento confirmado com sucesso.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
```

**Comportamento novo:**
- Endpoint que atualiza `outstanding_amount = 0` e `financial_status = 'em_dia'`
- Marca `gateway_provider = 'local'` para diferenciar de EFI
- Não faz nenhuma chamada HTTP externa

---

#### **Ponto 6: Adicionar rota do endpoint**

**Arquivo:** `app/routes/web.php`  
**Linha:** Após linha ~205

**O que adicionar:**
```php
$router->post('/api/payments/mark-paid', [PaymentsController::class, 'markPaid'], [AuthMiddleware::class]);
```

---

### 2.3. Persistir parcelas (1-24) somente para cartão, sem duplicar fluxo financeiro

#### **Ponto 7: Aumentar limite de parcelas para cartão**

**Arquivo:** `app/Views/alunos/matricula_show.php`  
**Linha:** ~162-175 (campo `installments`)

**O que alterar:**
```php
// ANTES:
<input 
    type="number" 
    id="installments" 
    name="installments" 
    min="1"
    max="12"  // ← ALTERAR
    ...
>

// DEPOIS:
<input 
    type="number" 
    id="installments" 
    name="installments" 
    min="1"
    max="<?= ($enrollment['payment_method'] ?? '') === 'cartao' ? 24 : 12 ?>"  // ← DINÂMICO
    ...
>
```

**Comportamento novo:**
- Se `payment_method = 'cartao'`, máximo é 24
- Se outro método, máximo continua 12

---

#### **Ponto 8: Ajustar validação no Controller**

**Arquivo:** `app/Controllers/AlunosController.php`  
**Linha:** ~777 (validação de installments)

**O que alterar:**
```php
// ANTES:
if (!$installments || $installments < 1 || $installments > 12) {
    $_SESSION['error'] = 'Número de parcelas deve ser entre 1 e 12.';
    redirect(...);
}

// DEPOIS:
$maxInstallments = ($paymentMethod === 'cartao') ? 24 : 12;
if (!$installments || $installments < 1 || $installments > $maxInstallments) {
    $_SESSION['error'] = "Número de parcelas deve ser entre 1 e {$maxInstallments}.";
    redirect(...);
}
```

**Comportamento novo:**
- Validação dinâmica conforme método de pagamento
- Cartão aceita até 24, outros métodos até 12

---

#### **Ponto 9: Usar mesmo fluxo financeiro existente**

**✅ NÃO precisa criar nova tabela ou duplicar lógica**

**Estrutura atual já suporta:**
- `enrollments.installments` (INT) - pode armazenar 1-24
- `enrollments.first_due_date` (DATE) - vencimento da 1ª parcela
- `enrollments.outstanding_amount` (DECIMAL) - saldo devedor total
- `InstallmentsViewService` - já calcula parcelas dinamicamente

**O que fazer:**
- ✅ Apenas aumentar limite de `installments` para cartão
- ✅ `InstallmentsViewService` já funciona com qualquer número de parcelas
- ✅ Não precisa criar tabela `installments` separada

---

## 📊 RESUMO DAS MUDANÇAS

### Arquivos a Modificar:

1. **`app/Services/EfiPaymentService.php`**
   - Bloquear `createCharge()` para cartão (retornar erro)

2. **`app/Views/alunos/matricula_show.php`**
   - Ocultar botão "Gerar Cobrança Efí" para cartão
   - Adicionar popup ao selecionar cartão
   - Adicionar função `confirmarPagamentoCartao()`
   - Aumentar `max` de `installments` para 24 quando cartão

3. **`app/Controllers/PaymentsController.php`**
   - Adicionar método `markPaid()` (baixa manual)

4. **`app/Controllers/AlunosController.php`**
   - Ajustar validação de `installments` (24 para cartão, 12 para outros)

5. **`app/routes/web.php`**
   - Adicionar rota `POST /api/payments/mark-paid`

### Arquivos que NÃO precisam mudar:

- ✅ `app/Services/InstallmentsViewService.php` - já funciona dinamicamente
- ✅ `app/Models/Enrollment.php` - estrutura já suporta
- ✅ Banco de dados - não precisa migration (campos já existem)

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### Fase 1: Bloquear EFI para Cartão
- [ ] Modificar `EfiPaymentService::createCharge()` para retornar erro quando cartão
- [ ] Ocultar botão "Gerar Cobrança Efí" quando `payment_method = 'cartao'`

### Fase 2: Popup e Baixa Manual
- [ ] Adicionar popup ao selecionar cartão no formulário
- [ ] Criar função JavaScript `confirmarPagamentoCartao()`
- [ ] Criar método `PaymentsController::markPaid()`
- [ ] Adicionar rota `/api/payments/mark-paid`

### Fase 3: Parcelas até 24x para Cartão
- [ ] Aumentar `max` de `installments` para 24 quando cartão
- [ ] Ajustar validação no Controller (24 para cartão, 12 para outros)

### Fase 4: Testes
- [ ] Testar seleção de cartão → popup aparece
- [ ] Testar confirmação de pagamento → financeiro atualiza
- [ ] Testar parcelas 24x para cartão → validação passa
- [ ] Testar outros métodos → continuam funcionando normalmente

---

**Fim do Diagnóstico**
