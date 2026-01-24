<?php

namespace App\Services;

use App\Config\Database;
use App\Config\Env;
use App\Models\Enrollment;
use App\Models\Student;

class EfiPaymentService
{
    private $db;
    private $clientId;
    private $clientSecret;
    private $sandbox;
    private $certPath;
    private $certPassword;
    private $webhookSecret;
    
    // URLs para API de Cobranças (boletos/cartão)
    private $baseUrlCharges;
    private $oauthUrlCharges;
    
    // URLs para API Pix
    private $baseUrlPix;
    private $oauthUrlPix;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        Env::load();
        
        $this->clientId = $_ENV['EFI_CLIENT_ID'] ?? null;
        $this->clientSecret = $_ENV['EFI_CLIENT_SECRET'] ?? null;
        $this->sandbox = ($_ENV['EFI_SANDBOX'] ?? 'true') === 'true';
        $this->certPath = $_ENV['EFI_CERT_PATH'] ?? null;
        $this->certPassword = $_ENV['EFI_CERT_PASSWORD'] ?? null;
        $this->webhookSecret = $_ENV['EFI_WEBHOOK_SECRET'] ?? null;
        
        // URLs para API de Cobranças (boletos/cartão de crédito)
        // NUNCA usar apis.gerencianet.com.br - usar cobrancas.api.efipay.com.br
        // OAuth de Cobranças usa /v1/authorize (não /oauth/token)
        if ($this->sandbox) {
            $this->oauthUrlCharges = 'https://cobrancas-h.api.efipay.com.br/v1/authorize';
            $this->baseUrlCharges = 'https://cobrancas-h.api.efipay.com.br';
        } else {
            $this->oauthUrlCharges = 'https://cobrancas.api.efipay.com.br/v1/authorize';
            $this->baseUrlCharges = 'https://cobrancas.api.efipay.com.br';
        }
        
        // URLs para API Pix (NUNCA usar apis.gerencianet.com.br)
        $this->oauthUrlPix = $this->sandbox 
            ? 'https://pix-h.api.efipay.com.br'
            : 'https://pix.api.efipay.com.br';
        
        // API Pix usa /v2 (sem /v1)
        $this->baseUrlPix = $this->sandbox 
            ? 'https://pix-h.api.efipay.com.br'
            : 'https://pix.api.efipay.com.br';
    }

    /**
     * Cria uma cobrança na Efí a partir de uma matrícula
     * 
     * @param array $enrollment Matrícula com dados completos
     * @return array {ok: bool, charge_id?: string, status?: string, payment_url?: string, message?: string}
     */
    public function createCharge($enrollment)
    {
        // Validar configuração
        if (!$this->clientId || !$this->clientSecret) {
            return [
                'ok' => false,
                'message' => 'Configuração do gateway não encontrada'
            ];
        }

        // BLOQUEAR EFI para Cartão (fail-safe)
        $paymentMethod = $enrollment['payment_method'] ?? 'pix';
        if ($paymentMethod === 'cartao' || $paymentMethod === 'credit_card') {
            $this->efiLog('WARN', 'createCharge: Tentativa de gerar cobrança EFI para cartão bloqueada', [
                'enrollment_id' => $enrollment['id'] ?? null,
                'payment_method' => $paymentMethod
            ]);
            return [
                'ok' => false,
                'message' => 'Cartão de crédito é pagamento local (maquininha). Use a opção "Confirmar Pagamento" para dar baixa manual.'
            ];
        }

        // Validar saldo devedor
        $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?? 0);
        if ($outstandingAmount <= 0) {
            return [
                'ok' => false,
                'message' => 'Sem saldo devedor para gerar cobrança'
            ];
        }

        // Verificar se já existe cobrança ativa (idempotência)
        if (!empty($enrollment['gateway_charge_id']) && 
            $enrollment['billing_status'] === 'generated' &&
            !in_array($enrollment['gateway_last_status'] ?? '', ['canceled', 'expired', 'error'])) {
            return [
                'ok' => false,
                'message' => 'Cobrança já existe',
                'charge_id' => $enrollment['gateway_charge_id'],
                'status' => $enrollment['gateway_last_status']
            ];
        }

        // Dados do aluno já devem vir no enrollment (via findWithDetails)
        // Se não vierem, buscar separadamente
        if (empty($enrollment['student_name']) && !empty($enrollment['student_id'])) {
            $studentModel = new Student();
            $student = $studentModel->find($enrollment['student_id']);
            if (!$student) {
                return [
                    'ok' => false,
                    'message' => 'Aluno não encontrado'
                ];
            }
        } else {
            // Usar dados que já vêm no enrollment
            $student = [
                'cpf' => $enrollment['student_cpf'] ?? null,
                'name' => $enrollment['student_name'] ?? null,
                'full_name' => $enrollment['full_name'] ?? null,
                'email' => $enrollment['email'] ?? null,
                'phone' => $enrollment['phone'] ?? $enrollment['phone_primary'] ?? null,
                'street' => $enrollment['street'] ?? null,
                'number' => $enrollment['number'] ?? null,
                'neighborhood' => $enrollment['neighborhood'] ?? null,
                'cep' => $enrollment['cep'] ?? null,
                'city' => $enrollment['city'] ?? null,
                'state_uf' => $enrollment['state_uf'] ?? null
            ];
        }

        // Determinar se é PIX para usar a API correta
        $paymentMethod = $enrollment['payment_method'] ?? 'pix';
        $installments = intval($enrollment['installments'] ?? 1);
        $isPix = ($paymentMethod === 'pix' && $installments === 1);
        
        // Obter token de autenticação (usar OAuth Pix se for PIX)
        $token = $this->getAccessToken($isPix);
        if (!$token) {
            // Verificar se credenciais estão configuradas
            if (empty($this->clientId) || empty($this->clientSecret)) {
                return [
                    'ok' => false,
                    'message' => 'Configuração do gateway incompleta. Verifique EFI_CLIENT_ID e EFI_CLIENT_SECRET no arquivo .env'
                ];
            }
            
            return [
                'ok' => false,
                'message' => 'Falha ao autenticar no gateway. Verifique se as credenciais estão corretas e se o ambiente (sandbox/produção) está configurado adequadamente.'
            ];
        }
        
        // Validar e sanitizar token
        if (!is_string($token)) {
            $this->efiLog('ERROR', 'createCharge: Token não é uma string', [
                'token_type' => gettype($token)
            ]);
            return [
                'ok' => false,
                'message' => 'Erro interno: Token de autenticação inválido'
            ];
        }
        
        $token = trim($token);
        if (empty($token)) {
            $this->efiLog('ERROR', 'createCharge: Token está vazio após trim', []);
            return [
                'ok' => false,
                'message' => 'Erro interno: Token de autenticação vazio'
            ];
        }

        // Montar payload conforme o tipo de API
        // Se for PIX, o payload será montado dentro do bloco if ($isPix) abaixo
        // Se não for PIX, montar payload da API de Cobranças
        $payload = null;
        
        if (!$isPix) {
            // Payload para API de Cobranças (boletos/cartão)
            $amountInCents = intval($outstandingAmount * 100); // Converter para centavos

            $payload = [
                'items' => [
                    [
                        'name' => $enrollment['service_name'] ?? 'Matrícula',
                        'value' => $amountInCents,
                        'amount' => 1
                    ]
                ]
            ];
            
            // NOTA: A API de Cobranças EFI não aceita metadata no formato padrão
            // Se precisar rastrear enrollment_id, usar no campo de observações do boleto ou em outro lugar

            // ÁRVORE DE DECISÃO CORRETA (conforme regra de negócio):
            // 
            // 1. payment_method = 'pix' → Pix (único, sempre installments = 1)
            // 2. payment_method = 'cartao' + installments > 1 → Cartão parcelado
            // 3. payment_method = 'cartao' + installments = 1 → Cartão à vista
            // 4. payment_method = 'boleto' + installments = 1 → Boleto à vista
            // 5. payment_method = 'boleto' + installments > 1 → Carnê (N boletos via /v1/carnet)
            //
            // IMPORTANTE: Boleto + parcelas deve usar Carnê, NÃO cartão!
            
            $isCreditCard = ($paymentMethod === 'cartao' || $paymentMethod === 'credit_card') && $installments > 1;
            $isCreditCardSingle = ($paymentMethod === 'cartao' || $paymentMethod === 'credit_card') && $installments === 1;
            $isBoletoSingle = ($paymentMethod === 'boleto') && $installments === 1;
            $isCarnet = ($paymentMethod === 'boleto') && $installments > 1;
            
            if ($isCreditCard || $isCreditCardSingle) {
                // Cartão de crédito (parcelado ou à vista)
                // A API de Cobranças EFI exige o campo customer em /payment/credit_card/customer
                if (!empty($student['cpf'])) {
                    $cpf = preg_replace('/[^0-9]/', '', $student['cpf']);
                    if (strlen($cpf) === 11) {
                        $customerName = $this->sanitizeCustomerName($student['full_name'] ?? $student['name'] ?? 'Cliente');
                        $payload['customer'] = [
                            'name' => $customerName,
                            'cpf' => $cpf,
                            'email' => $student['email'] ?? null,
                            'phone_number' => !empty($student['phone']) ? preg_replace('/[^0-9]/', '', $student['phone']) : null
                        ];
                    }
                }
                
                $payload['payment'] = [
                    'credit_card' => [
                        'installments' => $installments,
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

                // Replicar customer também dentro de payment.credit_card,
                // evitando o erro "A propriedade [customer] é obrigatória."
                if (!empty($payload['customer'])) {
                    $payload['payment']['credit_card']['customer'] = $payload['customer'];
                }
            } elseif ($isBoletoSingle) {
                // Boleto à vista (payment_method = 'boleto' + installments = 1)
                // IMPORTANTE: customer NÃO deve estar no root do payload para boleto
                // customer deve estar APENAS dentro de payment.banking_billet.customer
                // banking_billet deve ser um OBJETO, não array vazio
                $bankingBillet = [];
                
                // Adicionar dados do pagador se disponível
                if (!empty($student['cpf'])) {
                    $cpf = preg_replace('/[^0-9]/', '', $student['cpf']);
                    if (strlen($cpf) === 11) {
                        $customerName = $this->sanitizeCustomerName($student['full_name'] ?? $student['name'] ?? 'Cliente');
                        $bankingBillet['customer'] = [
                            'name' => $customerName,
                            'cpf' => $cpf,
                            'email' => $student['email'] ?? null,
                            'phone_number' => !empty($student['phone']) ? preg_replace('/[^0-9]/', '', $student['phone']) : null
                        ];
                        
                        // Adicionar endereço se disponível
                        if (!empty($student['cep'])) {
                            $cep = preg_replace('/[^0-9]/', '', $student['cep']);
                            if (strlen($cep) === 8) {
                                $bankingBillet['customer']['address'] = [
                                    'street' => $student['street'] ?? 'Não informado',
                                    'number' => $student['number'] ?? 'S/N',
                                    'neighborhood' => $student['neighborhood'] ?? '',
                                    'zipcode' => $cep,
                                    'city' => $student['city'] ?? '',
                                    'state' => $student['state_uf'] ?? ''
                                ];
                            }
                        }
                    }
                }
                
                // Data de vencimento (padrão: 3 dias)
                $bankingBillet['expire_at'] = date('Y-m-d', strtotime('+3 days'));
                
                // Mensagem opcional
                $bankingBillet['message'] = 'Pagamento referente a matrícula';
                
                $payload['payment'] = ['banking_billet' => $bankingBillet];
            } elseif ($isCarnet) {
                // Carnê (boleto parcelado): boleto + installments > 1
                // Usa endpoint /v1/carnet para criar múltiplos boletos
                // Retornar diretamente após criar o Carnê (não seguir fluxo normal)
                return $this->createCarnet($enrollment, $student, $outstandingAmount, $installments);
            } else {
                // Método de pagamento inválido ou não suportado
                $this->efiLog('ERROR', 'createCharge: Método de pagamento não suportado', [
                    'payment_method' => $paymentMethod,
                    'installments' => $installments,
                    'enrollment_id' => $enrollment['id']
                ]);
                $this->updateEnrollmentStatus($enrollment['id'], 'error', 'error', null);
                return [
                    'ok' => false,
                    'message' => 'Método de pagamento não suportado. Verifique payment_method e installments.'
                ];
            }
        }

        // Criar cobrança na API Efí
        // Se for PIX, usar API Pix (/v2/cob), senão usar API de Cobranças (/v1/charges)
        // NOTA: Carnê já foi tratado acima e retornou diretamente
        if ($isPix) {
            // API Pix: converter payload para formato Pix e usar endpoint /v2/cob
            // A API Pix tem estrutura diferente da API de Cobranças
            // Validar chave PIX (obrigatória para API Pix)
            $pixKey = $_ENV['EFI_PIX_KEY'] ?? null;
            if (empty($pixKey)) {
                $this->efiLog('ERROR', 'createCharge Pix: EFI_PIX_KEY não configurada', [
                    'enrollment_id' => $enrollment['id']
                ]);
                $this->updateEnrollmentStatus($enrollment['id'], 'error', 'error', null);
                return [
                    'ok' => false,
                    'message' => 'Chave PIX não configurada. Configure EFI_PIX_KEY no arquivo .env'
                ];
            }
            
            $pixPayload = [
                'calendario' => [
                    'expiracao' => 3600 // 1 hora em segundos
                ],
                'valor' => [
                    'original' => number_format($outstandingAmount, 2, '.', '')
                ],
                'chave' => $pixKey, // Chave Pix (obrigatória)
                'solicitacaoPagador' => $enrollment['service_name'] ?? 'Matrícula',
                'infoAdicionais' => [
                    [
                        'nome' => 'enrollment_id',
                        'valor' => (string)$enrollment['id']
                    ],
                    [
                        'nome' => 'cfc_id',
                        'valor' => (string)($enrollment['cfc_id'] ?? 1)
                    ],
                    [
                        'nome' => 'student_id',
                        'valor' => (string)$enrollment['student_id']
                    ]
                ]
            ];
            
            // Adicionar dados do pagador se disponível
            if (!empty($student['cpf'])) {
                $cpf = preg_replace('/[^0-9]/', '', $student['cpf']);
                if (strlen($cpf) === 11) {
                    $devedorName = $this->sanitizeCustomerName($student['full_name'] ?? $student['name'] ?? 'Cliente');
                    $pixPayload['devedor'] = [
                        'cpf' => $cpf,
                        'nome' => $devedorName
                    ];
                }
            }
            
            $response = $this->makeRequest('POST', '/v2/cob', $pixPayload, $token, true);
            
            // makeRequest agora sempre retorna array com http_code
            $httpCode = $response['http_code'] ?? 0;
            $responseData = $response['response'] ?? $response;
            
            // API Pix retorna dados diretamente (não dentro de 'data')
            if ($httpCode >= 400 || !$responseData || isset($responseData['error']) || isset($responseData['mensagem'])) {
                $errorMessage = $responseData['mensagem'] ?? $responseData['error_description'] ?? $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido ao criar cobrança Pix';
                
                $this->efiLog('ERROR', 'createCharge Pix falhou', [
                    'enrollment_id' => $enrollment['id'],
                    'http_code' => $httpCode,
                    'error' => substr($errorMessage, 0, 180),
                    'response_snippet' => substr(json_encode($responseData, JSON_UNESCAPED_UNICODE), 0, 180)
                ]);
                
                $this->updateEnrollmentStatus($enrollment['id'], 'error', 'error', null);
                
                return [
                    'ok' => false,
                    'message' => $errorMessage
                ];
            }
            
            // Processar resposta da API Pix
            $chargeId = $responseData['txid'] ?? null;
            $status = 'waiting'; // Pix geralmente inicia como 'waiting'
            $paymentUrl = $responseData['pixCopiaECola'] ?? $responseData['qrCode'] ?? null;
            // Extrair PIX copia-e-cola para persistência
            $pixCode = $responseData['pixCopiaECola'] ?? $responseData['qrCode'] ?? null;
            $barcode = null; // PIX não tem linha digitável
            
        } else {
            // API de Cobranças: usar endpoint one-step (cria e define pagamento em uma única chamada)
            // makeRequest já adiciona /v1/ automaticamente para Cobranças
            // Endpoint correto: POST /v1/charge/one-step
            $response = $this->makeRequest('POST', '/charge/one-step', $payload, $token, false);
            
            // makeRequest agora sempre retorna array com http_code
            $httpCode = $response['http_code'] ?? 0;
            $responseData = $response['response'] ?? $response;
            
            // Log detalhado para debug
            $this->efiLog('DEBUG', 'createCharge Cobranças response', [
                'enrollment_id' => $enrollment['id'],
                'http_code' => $httpCode,
                'has_data' => isset($responseData['data']),
                'response_keys' => is_array($responseData) ? array_keys($responseData) : []
            ]);
            
            if ($httpCode >= 400 || !$responseData) {
                // Capturar mensagem de erro mais detalhada
                $errorMessage = $responseData['error_description'] ?? $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido ao criar cobrança';
                
                // Garantir que errorMessage seja string
                if (is_array($errorMessage) || is_object($errorMessage)) {
                    $errorMessage = json_encode($errorMessage, JSON_UNESCAPED_UNICODE);
                }
                $errorMessage = (string)$errorMessage;
                
                // Se houver detalhes adicionais, incluir
                if (isset($responseData['error_detail'])) {
                    $errorDetail = is_array($responseData['error_detail']) || is_object($responseData['error_detail'])
                        ? json_encode($responseData['error_detail'], JSON_UNESCAPED_UNICODE)
                        : (string)$responseData['error_detail'];
                    $errorMessage .= ' - ' . $errorDetail;
                }
                
                // Log detalhado para debug
                $this->efiLog('ERROR', 'createCharge Cobranças falhou', [
                    'enrollment_id' => $enrollment['id'],
                    'http_code' => $httpCode,
                    'error' => substr($errorMessage, 0, 180),
                    'response_snippet' => substr(json_encode($responseData, JSON_UNESCAPED_UNICODE), 0, 180)
                ]);
                
                // Atualizar status de erro no banco
                $this->updateEnrollmentStatus($enrollment['id'], 'error', 'error', null);
                
                return [
                    'ok' => false,
                    'message' => $errorMessage
                ];
            }
            
            // Processar resposta da API de Cobranças
            // A resposta pode vir diretamente ou dentro de 'data'
            $chargeData = $responseData['data'] ?? $responseData;
            $chargeId = $chargeData['charge_id'] ?? $chargeData['id'] ?? null;
            $status = $chargeData['status'] ?? 'unknown';
            $paymentUrl = null;
            $pixCode = null;
            $barcode = null;
            
            // Extrair URL de pagamento e dados de pagamento se disponível
            if (isset($chargeData['payment'])) {
                // Boleto
                if (isset($chargeData['payment']['banking_billet']['link'])) {
                    $paymentUrl = $chargeData['payment']['banking_billet']['link'];
                }
                // Linha digitável do boleto (barcode)
                if (isset($chargeData['payment']['banking_billet']['barcode'])) {
                    $barcode = $chargeData['payment']['banking_billet']['barcode'];
                    // Se não tiver link, usar barcode como payment_url
                    if (!$paymentUrl) {
                        $paymentUrl = $barcode;
                    }
                }
                // Pix (se houver)
                if (isset($chargeData['payment']['pix']['qr_code'])) {
                    $pixCode = $chargeData['payment']['pix']['qr_code'];
                    $paymentUrl = $pixCode;
                }
                // PIX copia-e-cola (campo específico)
                if (isset($chargeData['payment']['pix']['pixCopiaECola'])) {
                    $pixCode = $chargeData['payment']['pix']['pixCopiaECola'];
                }
            }
            
            // Log de sucesso
            $this->efiLog('INFO', 'createCharge Cobranças sucesso', [
                'enrollment_id' => $enrollment['id'],
                'charge_id' => $chargeId,
                'status' => $status,
                'has_payment_url' => !empty($paymentUrl)
            ]);
        }

        // Atualizar matrícula com dados da cobrança (incluindo payment_url, pix_code e barcode)
        $this->updateEnrollmentStatus(
            $enrollment['id'],
            'generated',
            $status,
            $chargeId,
            null,
            $paymentUrl,
            $pixCode,
            $barcode
        );

        // Determinar tipo de pagamento para o retorno
        $paymentType = 'boleto'; // padrão
        if ($isPix) {
            $paymentType = 'pix';
        } elseif ($isCreditCard || $isCreditCardSingle) {
            $paymentType = 'cartao';
        } elseif ($isBoletoSingle) {
            $paymentType = 'boleto';
        }

        return [
            'ok' => true,
            'type' => $paymentType,
            'charge_id' => $chargeId,
            'status' => $status,
            'payment_url' => $paymentUrl
        ];
    }

    /**
     * Cria um Carnê (múltiplos boletos) na API EFI
     * 
     * @param array $enrollment Dados da matrícula
     * @param array $student Dados do aluno
     * @param float $totalAmount Valor total a ser parcelado
     * @param int $installments Número de parcelas
     * @return array Resultado da criação do Carnê
     */
    public function createCarnet($enrollment, $student, $totalAmount, $installments)
    {
        // Validar configuração
        if (!$this->clientId || !$this->clientSecret) {
            return [
                'ok' => false,
                'message' => 'Configuração do gateway incompleta. Verifique EFI_CLIENT_ID e EFI_CLIENT_SECRET no arquivo .env'
            ];
        }

        // Validar se já existe carnê ativo para esta matrícula
        $existingCarnetId = $enrollment['gateway_charge_id'] ?? null;
        if (!empty($existingCarnetId)) {
            // Verificar se o carnê está ativo (não cancelado/expirado)
            $paymentData = null;
            if (!empty($enrollment['gateway_payment_url'])) {
                $paymentData = json_decode($enrollment['gateway_payment_url'], true);
            }
            
            $isActive = false;
            if ($paymentData && isset($paymentData['type']) && $paymentData['type'] === 'carne') {
                $carnetStatus = $paymentData['status'] ?? $enrollment['gateway_last_status'] ?? 'waiting';
                // Status que indicam carnê ativo
                $activeStatuses = ['waiting', 'up_to_date', 'paid_partial', 'paid'];
                $isActive = in_array($carnetStatus, $activeStatuses);
            } else {
                // Se tem gateway_charge_id mas não é carnê, pode ser cobrança única ativa
                $billingStatus = $enrollment['billing_status'] ?? 'draft';
                $gatewayStatus = $enrollment['gateway_last_status'] ?? null;
                $activeBillingStatuses = ['ready', 'generated'];
                $activeGatewayStatuses = ['waiting', 'unpaid', 'pending', 'processing', 'new', 'paid_partial', 'paid'];
                $isActive = in_array($billingStatus, $activeBillingStatuses) || 
                           ($gatewayStatus && in_array($gatewayStatus, $activeGatewayStatuses));
            }
            
            if ($isActive) {
                $this->efiLog('WARN', 'createCarnet: Já existe cobrança ativa para esta matrícula', [
                    'enrollment_id' => $enrollment['id'],
                    'existing_charge_id' => $existingCarnetId,
                    'billing_status' => $enrollment['billing_status'] ?? null,
                    'gateway_status' => $enrollment['gateway_last_status'] ?? null
                ]);
                return [
                    'ok' => false,
                    'message' => 'Já existe uma cobrança ativa para esta matrícula. Por favor, cancele a cobrança existente antes de gerar uma nova.'
                ];
            }
        }

        // Obter token de autenticação (Carnê usa API de Cobranças, não PIX)
        $token = $this->getAccessToken(false);
        if (!$token) {
            return [
                'ok' => false,
                'message' => 'Falha ao autenticar no gateway. Verifique se as credenciais estão corretas.'
            ];
        }

        // Calcular valor por parcela
        $parcelValue = $totalAmount / $installments;
        $parcelValueInCents = intval($parcelValue * 100);
        
        // Obter data da primeira parcela
        $firstDueDate = $enrollment['first_due_date'] ?? null;
        if (!$firstDueDate || $firstDueDate === '0000-00-00') {
            // Se não tiver data configurada, usar 30 dias a partir de hoje
            $firstDueDate = date('Y-m-d', strtotime('+30 days'));
        }

        // Preparar payload do Carnê conforme schema oficial da API Efí
        // Schema: POST /v1/carnet
        // - items[] (obrigatório)
        // - customer{} (opcional mas recomendado)
        // - expire_at (obrigatório no nível raiz) - formato YYYY-MM-DD
        // - repeats (obrigatório) - INT (número de parcelas), não array!
        // - message (opcional)
        // - configurations{} (opcional)
        
        // Validar que a data está no futuro
        $expireDate = date('Y-m-d', strtotime($firstDueDate));
        if (strtotime($expireDate) < time()) {
            $this->efiLog('WARNING', 'createCarnet: Data de vencimento no passado, ajustando', [
                'enrollment_id' => $enrollment['id'],
                'data_original' => $expireDate
            ]);
            // Se a data estiver no passado, usar pelo menos 3 dias a partir de hoje
            $expireDate = date('Y-m-d', strtotime('+3 days'));
        }

        // Montar payload no formato correto do Carnê
        $payload = [
            'items' => [
                [
                    'name' => ($enrollment['service_name'] ?? 'Matrícula') . ' - Parcela 1/' . $installments,
                    'value' => $parcelValueInCents,
                    'amount' => 1
                ]
            ],
            'expire_at' => $expireDate, // ✅ OBRIGATÓRIO no nível raiz (formato YYYY-MM-DD)
            'repeats' => $installments, // ✅ OBRIGATÓRIO - INT (número de parcelas), não array!
            'message' => 'Pagamento referente a matrícula'
        ];

        // Adicionar dados do cliente
        if (!empty($student['cpf'])) {
            $cpf = preg_replace('/[^0-9]/', '', $student['cpf']);
            if (strlen($cpf) === 11) {
                // Sanitizar nome do cliente conforme padrão Efí
                // Regex Efí: ^(?!.*[؀-ۿ])[ ]*(.+[ ]+)+.+[ ]*$
                // - Não pode conter caracteres árabes (؀-ۿ)
                // - Deve ter pelo menos um espaço entre palavras (mínimo 2 palavras)
                // - Pode ter espaços no início/fim (serão removidos)
                $customerName = $this->sanitizeCustomerName($student['full_name'] ?? $student['name'] ?? 'Cliente');
                
                $payload['customer'] = [
                    'name' => $customerName,
                    'cpf' => $cpf,
                    'email' => $student['email'] ?? null,
                    'phone_number' => !empty($student['phone']) ? preg_replace('/[^0-9]/', '', $student['phone']) : null
                ];

                // Adicionar endereço se disponível
                if (!empty($student['cep'])) {
                    $cep = preg_replace('/[^0-9]/', '', $student['cep']);
                    if (strlen($cep) === 8) {
                        $payload['customer']['address'] = [
                            'street' => $student['street'] ?? 'Não informado',
                            'number' => $student['number'] ?? 'S/N',
                            'neighborhood' => $student['neighborhood'] ?? '',
                            'zipcode' => $cep,
                            'city' => $student['city'] ?? '',
                            'state' => $student['state_uf'] ?? ''
                        ];
                    }
                }
            }
        }
        
        // Remover campos nulos/vazios do customer para evitar problemas na API
        if (isset($payload['customer'])) {
            $payload['customer'] = array_filter($payload['customer'], function($value) {
                return $value !== null && $value !== '';
            });
            
            // Se address existe mas está vazio, remover
            if (isset($payload['customer']['address'])) {
                $address = array_filter($payload['customer']['address'], function($value) {
                    return $value !== null && $value !== '';
                });
                if (empty($address)) {
                    unset($payload['customer']['address']);
                } else {
                    $payload['customer']['address'] = $address;
                }
            }
            
            // Se customer ficou vazio, remover
            if (empty($payload['customer'])) {
                unset($payload['customer']);
            }
        }
        
        // REMOVER campo 'message' - não está na documentação oficial do Carnê
        // A documentação oficial não menciona 'message' no nível raiz para Carnê
        // Campos permitidos: items, expire_at, repeats, customer, instructions, custom_id, notification_url, configurations
        if (isset($payload['message'])) {
            unset($payload['message']);
        }
        
        // VALIDAÇÃO EXPLÍCITA DO PAYLOAD ANTES DO ENVIO
        $validationErrors = [];
        
        // 1. Validar items existe e é ARRAY
        if (!isset($payload['items']) || !is_array($payload['items'])) {
            $validationErrors[] = 'items deve existir e ser um ARRAY';
        } elseif (empty($payload['items'])) {
            $validationErrors[] = 'items não pode estar vazio';
        } else {
            // Validar items[0]
            if (!isset($payload['items'][0])) {
                $validationErrors[] = 'items[0] não existe';
            } else {
                $item = $payload['items'][0];
                // Validar name
                if (!isset($item['name']) || empty($item['name'])) {
                    $validationErrors[] = 'items[0].name é obrigatório';
                }
                // Validar value é INT em centavos
                if (!isset($item['value'])) {
                    $validationErrors[] = 'items[0].value é obrigatório';
                } elseif (!is_int($item['value']) || $item['value'] <= 0) {
                    $validationErrors[] = 'items[0].value deve ser INT positivo (em centavos), recebido: ' . gettype($item['value']) . ' = ' . $item['value'];
                }
                // Validar amount
                if (!isset($item['amount']) || !is_int($item['amount']) || $item['amount'] <= 0) {
                    $validationErrors[] = 'items[0].amount deve ser INT positivo';
                }
            }
        }
        
        // 2. Validar expire_at está no root e formato YYYY-MM-DD
        if (!isset($payload['expire_at'])) {
            $validationErrors[] = 'expire_at é obrigatório no nível raiz';
        } elseif (!is_string($payload['expire_at'])) {
            $validationErrors[] = 'expire_at deve ser STRING, recebido: ' . gettype($payload['expire_at']);
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['expire_at'])) {
            $validationErrors[] = 'expire_at deve estar no formato YYYY-MM-DD, recebido: ' . $payload['expire_at'];
        }
        
        // 3. Validar repeats é INT
        if (!isset($payload['repeats'])) {
            $validationErrors[] = 'repeats é obrigatório';
        } elseif (!is_int($payload['repeats']) || $payload['repeats'] <= 0) {
            $validationErrors[] = 'repeats deve ser INT positivo, recebido: ' . gettype($payload['repeats']) . ' = ' . $payload['repeats'];
        }
        
        // 4. Garantir que NÃO existe installments no payload
        if (isset($payload['installments'])) {
            $validationErrors[] = 'installments NÃO deve existir no payload (usar repeats)';
            unset($payload['installments']); // Remover se existir
        }
        
        // 5. Validar customer contém apenas campos permitidos
        // Campos permitidos: name, cpf, cnpj, email, phone_number, address
        // address: street, number, neighborhood, zipcode, city, state
        if (isset($payload['customer']) && is_array($payload['customer'])) {
            $allowedCustomerFields = ['name', 'cpf', 'cnpj', 'email', 'phone_number', 'address'];
            foreach (array_keys($payload['customer']) as $field) {
                if (!in_array($field, $allowedCustomerFields)) {
                    $validationErrors[] = "customer contém campo não permitido: {$field}";
                }
            }
            
            // Validar address se existir
            if (isset($payload['customer']['address']) && is_array($payload['customer']['address'])) {
                $allowedAddressFields = ['street', 'number', 'neighborhood', 'zipcode', 'city', 'state'];
                foreach (array_keys($payload['customer']['address']) as $field) {
                    if (!in_array($field, $allowedAddressFields)) {
                        $validationErrors[] = "customer.address contém campo não permitido: {$field}";
                    }
                }
            }
        }
        
        // Se houver erros de validação, retornar erro
        if (!empty($validationErrors)) {
            $this->efiLog('ERROR', 'createCarnet: Validação do payload falhou', [
                'enrollment_id' => $enrollment['id'],
                'validation_errors' => $validationErrors,
                'payload_keys' => array_keys($payload)
            ]);
            $this->updateEnrollmentStatus($enrollment['id'], 'error', 'error', null);
            return [
                'ok' => false,
                'message' => 'Erro de validação do payload: ' . implode('; ', $validationErrors)
            ];
        }
        
        // Log do payload FINAL (antes do envio) - sem dados sensíveis
        $logPayload = $payload;
        if (isset($logPayload['customer']['cpf'])) {
            $logPayload['customer']['cpf'] = '***';
        }
        if (isset($logPayload['customer']['email'])) {
            $logPayload['customer']['email'] = '***';
        }
        if (isset($logPayload['customer']['phone_number'])) {
            $logPayload['customer']['phone_number'] = '***';
        }
        
        $this->efiLog('INFO', 'createCarnet: Payload FINAL validado e pronto para envio', [
            'enrollment_id' => $enrollment['id'],
            'endpoint' => '/v1/carnet',
            'host' => $this->baseUrlCharges,
            'payload_final' => json_encode($logPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'validation_passed' => true,
            'items_count' => count($payload['items'] ?? []),
            'items[0].value' => $payload['items'][0]['value'] ?? null,
            'items[0].value_type' => isset($payload['items'][0]['value']) ? gettype($payload['items'][0]['value']) : null,
            'expire_at' => $payload['expire_at'] ?? null,
            'repeats' => $payload['repeats'] ?? null,
            'repeats_type' => isset($payload['repeats']) ? gettype($payload['repeats']) : null,
            'has_installments' => isset($payload['installments']),
            'has_customer' => !empty($payload['customer']),
            'has_message' => isset($payload['message'])
        ]);

        // Fazer requisição para criar Carnê - endpoint correto: /v1/carnet
        $response = $this->makeRequest('POST', '/v1/carnet', $payload, $token, false);

        $httpCode = $response['http_code'] ?? 0;
        $responseData = $response['response'] ?? null;

        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMessage = 'Erro ao criar Carnê';
            $errorDetails = [];
            
            if (is_array($responseData)) {
                if (isset($responseData['error_description'])) {
                    $errorDesc = $responseData['error_description'];
                    if (is_array($errorDesc)) {
                        $errorMessage = json_encode($errorDesc, JSON_UNESCAPED_UNICODE);
                        $errorDetails = $errorDesc;
                    } else {
                        $errorMessage = (string)$errorDesc;
                    }
                } elseif (isset($responseData['message'])) {
                    $errorMessage = $responseData['message'];
                } elseif (isset($responseData['error'])) {
                    $errorMessage = $responseData['error'];
                }
                
                // Extrair detalhes específicos de validação
                if (isset($responseData['errors']) && is_array($responseData['errors'])) {
                    $errorDetails = $responseData['errors'];
                }
            } else {
                $errorMessage = (string)$responseData;
            }

            // Log detalhado incluindo payload (sem dados sensíveis)
            $logPayload = $payload;
            // Remover dados sensíveis do log
            if (isset($logPayload['customer']['cpf'])) {
                $logPayload['customer']['cpf'] = '***';
            }
            if (isset($logPayload['customer']['email'])) {
                $logPayload['customer']['email'] = '***';
            }
            if (isset($logPayload['customer']['phone_number'])) {
                $logPayload['customer']['phone_number'] = '***';
            }

            $this->efiLog('ERROR', 'createCarnet: Falha ao criar Carnê', [
                'enrollment_id' => $enrollment['id'],
                'http_code' => $httpCode,
                'endpoint' => '/v1/carnet',
                'host' => $this->baseUrlCharges,
                'error' => substr($errorMessage, 0, 500),
                'error_details' => $errorDetails,
                'payload_summary' => [
                    'installments' => $installments,
                    'repeats' => $installments,
                    'expire_at' => $expireDate,
                    'first_due_date' => $firstDueDate
                ],
                'response_snippet' => is_array($responseData) ? json_encode($responseData, JSON_UNESCAPED_UNICODE) : substr((string)$responseData, 0, 500)
            ]);

            $this->updateEnrollmentStatus($enrollment['id'], 'error', 'error', null);
            return [
                'ok' => false,
                'message' => 'Erro ao criar Carnê: ' . $errorMessage
            ];
        }

        // Processar resposta do Carnê
        $carnetData = $responseData['data'] ?? $responseData;
        $carnetId = $carnetData['carnet_id'] ?? null;
        $carnetStatus = $carnetData['status'] ?? 'waiting';
        $cover = $carnetData['cover'] ?? null; // Link de visualização do carnê
        $downloadLink = $carnetData['link'] ?? null; // Link de download do carnê
        $charges = $carnetData['charges'] ?? [];

        // Extrair dados completos de cada parcela
        $chargeIds = [];
        $paymentUrls = [];
        $chargesData = [];
        foreach ($charges as $charge) {
            $chargeId = $charge['charge_id'] ?? null;
            if ($chargeId) {
                $chargeIds[] = $chargeId;
                
                // Extrair URL de pagamento se disponível
                $billetLink = $charge['payment']['banking_billet']['link'] ?? null;
                if ($billetLink) {
                    $paymentUrls[] = $billetLink;
                }
                
                // Salvar dados completos da parcela
                $chargesData[] = [
                    'charge_id' => $chargeId,
                    'expire_at' => $charge['expire_at'] ?? null,
                    'status' => $charge['status'] ?? 'waiting',
                    'total' => $charge['total'] ?? null,
                    'billet_link' => $billetLink
                ];
            }
        }

        // Log de sucesso
        $this->efiLog('INFO', 'createCarnet: Carnê criado com sucesso', [
            'enrollment_id' => $enrollment['id'],
            'carnet_id' => $carnetId,
            'installments' => $installments,
            'charge_ids_count' => count($chargeIds),
            'has_cover' => !empty($cover),
            'has_download_link' => !empty($downloadLink)
        ]);

        // Atualizar matrícula com dados do Carnê
        // gateway_charge_id = carnet_id (compatibilidade)
        // gateway_payment_url = JSON completo com todos os dados do carnê
        $firstPaymentUrl = !empty($paymentUrls) ? $paymentUrls[0] : null;

        $this->updateEnrollmentStatus(
            $enrollment['id'],
            'generated',
            $carnetStatus, // Status do carnê (up_to_date, waiting, etc)
            $carnetId, // Usar carnet_id como identificador principal
            null,
            $firstPaymentUrl // URL do primeiro boleto (compatibilidade)
        );

        // Salvar JSON completo com todos os dados do carnê (com versão e updated_at)
        $carnetDataJson = json_encode([
            'schema_version' => 1, // Versão do schema para evolução futura
            'type' => 'carne',
            'carnet_id' => $carnetId,
            'status' => $carnetStatus,
            'cover' => $cover, // Link de visualização
            'download_link' => $downloadLink, // Link de download
            'charge_ids' => $chargeIds, // Lista de IDs (compatibilidade)
            'payment_urls' => $paymentUrls, // Lista de URLs (compatibilidade)
            'charges' => $chargesData, // Dados completos de cada parcela
            'updated_at' => date('Y-m-d H:i:s') // Timestamp da última atualização
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt = $this->db->prepare("
            UPDATE enrollments 
            SET gateway_payment_url = ? 
            WHERE id = ?
        ");
        $stmt->execute([$carnetDataJson, $enrollment['id']]);

        return [
            'ok' => true,
            'type' => 'carne',
            'carnet_id' => $carnetId,
            'status' => $carnetStatus,
            'cover' => $cover,
            'download_link' => $downloadLink,
            'charge_ids' => $chargeIds,
            'installments' => $installments,
            'payment_urls' => $paymentUrls,
            'charges' => $chargesData
        ];
    }

    /**
     * Processa webhook da Efí e atualiza status da matrícula
     * 
     * @param array $requestPayload Payload recebido do webhook
     * @return array {ok: bool, processed: bool, message?: string}
     */
    public function parseWebhook($requestPayload)
    {
        // Validar assinatura do webhook (se configurado)
        if ($this->webhookSecret) {
            $signature = $_SERVER['HTTP_X_GN_SIGNATURE'] ?? '';
            if (!$this->validateWebhookSignature($requestPayload, $signature)) {
                return [
                    'ok' => false,
                    'processed' => false,
                    'message' => 'Assinatura inválida'
                ];
            }
        }

        // Normalizar payload
        $chargeId = $requestPayload['identifiers']['charge_id'] ?? $requestPayload['charge_id'] ?? null;
        $carnetId = $requestPayload['identifiers']['carnet_id'] ?? $requestPayload['carnet_id'] ?? null;
        $status = $requestPayload['current']['status'] ?? $requestPayload['status'] ?? null;
        $occurredAt = $requestPayload['occurred_at'] ?? date('Y-m-d H:i:s');
        
        // Extrair dados de pagamento do payload (se disponíveis)
        $pixCode = null;
        $barcode = null;
        if (isset($requestPayload['current']['payment'])) {
            $payment = $requestPayload['current']['payment'];
            // PIX copia-e-cola
            if (isset($payment['pix']['pixCopiaECola'])) {
                $pixCode = $payment['pix']['pixCopiaECola'];
            } elseif (isset($payment['pix']['qr_code'])) {
                $pixCode = $payment['pix']['qr_code'];
            }
            // Linha digitável do boleto
            if (isset($payment['banking_billet']['barcode'])) {
                $barcode = $payment['banking_billet']['barcode'];
            }
        }
        // Também verificar no nível raiz do payload
        if (isset($requestPayload['payment'])) {
            $payment = $requestPayload['payment'];
            if (isset($payment['pix']['pixCopiaECola'])) {
                $pixCode = $payment['pix']['pixCopiaECola'];
            } elseif (isset($payment['pix']['qr_code'])) {
                $pixCode = $payment['pix']['qr_code'];
            }
            if (isset($payment['banking_billet']['barcode'])) {
                $barcode = $payment['banking_billet']['barcode'];
            }
        }

        if ((!$chargeId && !$carnetId) || !$status) {
            return [
                'ok' => false,
                'processed' => false,
                'message' => 'Payload inválido'
            ];
        }

        // Buscar matrícula
        $enrollmentModel = new Enrollment();
        $enrollment = null;
        
        // Se tiver carnet_id, buscar diretamente
        if ($carnetId) {
            $stmt = $this->db->prepare("
                SELECT * FROM enrollments 
                WHERE gateway_charge_id = ? AND gateway_provider = 'efi'
                LIMIT 1
            ");
            $stmt->execute([$carnetId]);
            $enrollment = $stmt->fetch();
        }
        
        // Se não encontrou e tem charge_id, buscar em carnês (charge_id pode estar no JSON)
        if (!$enrollment && $chargeId) {
            // Primeiro tentar buscar por gateway_charge_id (pode ser charge_id de cobrança única)
            $stmt = $this->db->prepare("
                SELECT * FROM enrollments 
                WHERE gateway_charge_id = ? AND gateway_provider = 'efi'
                LIMIT 1
            ");
            $stmt->execute([$chargeId]);
            $enrollment = $stmt->fetch();
            
            // Se não encontrou, buscar em carnês (charge_id pode estar no JSON de gateway_payment_url)
            // Buscar todas as matrículas com carnê e verificar no JSON
            if (!$enrollment) {
                $stmt = $this->db->prepare("
                    SELECT * FROM enrollments 
                    WHERE gateway_provider = 'efi'
                    AND gateway_payment_url IS NOT NULL
                    AND gateway_payment_url != ''
                    LIMIT 100
                ");
                $stmt->execute();
                $enrollments = $stmt->fetchAll();
                
                foreach ($enrollments as $enr) {
                    $paymentData = json_decode($enr['gateway_payment_url'], true);
                    if ($paymentData && isset($paymentData['type']) && $paymentData['type'] === 'carne') {
                        // Verificar se charge_id está nas parcelas
                        if (isset($paymentData['charges']) && is_array($paymentData['charges'])) {
                            foreach ($paymentData['charges'] as $charge) {
                                if (($charge['charge_id'] ?? null) == $chargeId) {
                                    $enrollment = $enr;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!$enrollment) {
            // Logar mas não quebrar (idempotência)
            $this->efiLog('WARN', 'parseWebhook: Matrícula não encontrada', [
                'charge_id' => $chargeId,
                'carnet_id' => $carnetId
            ]);
            return [
                'ok' => true,
                'processed' => false,
                'message' => 'Matrícula não encontrada'
            ];
        }

        // Verificar se é Carnê
        $paymentData = null;
        if (!empty($enrollment['gateway_payment_url'])) {
            $paymentData = json_decode($enrollment['gateway_payment_url'], true);
        }
        $isCarnet = $paymentData && isset($paymentData['type']) && $paymentData['type'] === 'carne';

        if ($isCarnet && $chargeId) {
            // Webhook de parcela do carnê - atualizar status da parcela específica
            // IDEMPOTÊNCIA: não regredir status (paid não volta para waiting, canceled não reabre)
            if (isset($paymentData['charges']) && is_array($paymentData['charges'])) {
                $updated = false;
                $currentStatus = null;
                foreach ($paymentData['charges'] as &$charge) {
                    if (($charge['charge_id'] ?? null) == $chargeId) {
                        $currentStatus = $charge['status'] ?? 'waiting';
                        
                        // Regras de idempotência: não regredir status
                        $statusHierarchy = ['waiting' => 1, 'unpaid' => 1, 'pending' => 2, 'processing' => 3, 'paid_partial' => 4, 'paid' => 5, 'canceled' => 0, 'expired' => 0];
                        $currentLevel = $statusHierarchy[$currentStatus] ?? 0;
                        $newLevel = $statusHierarchy[$status] ?? 0;
                        
                        // Não atualizar se:
                        // - Status já é paid e novo status é waiting/unpaid (regressão)
                        // - Status já é canceled e novo status não é canceled (não reabrir)
                        if ($currentStatus === 'paid' && in_array($status, ['waiting', 'unpaid', 'pending', 'processing'])) {
                            $this->efiLog('INFO', 'parseWebhook: Ignorando regressão de status (paid -> ' . $status . ')', [
                                'enrollment_id' => $enrollment['id'],
                                'charge_id' => $chargeId,
                                'current_status' => $currentStatus,
                                'new_status' => $status
                            ]);
                            break;
                        }
                        
                        if ($currentStatus === 'canceled' && $status !== 'canceled') {
                            $this->efiLog('INFO', 'parseWebhook: Ignorando reabertura de parcela cancelada', [
                                'enrollment_id' => $enrollment['id'],
                                'charge_id' => $chargeId,
                                'current_status' => $currentStatus,
                                'new_status' => $status
                            ]);
                            break;
                        }
                        
                        // Atualizar apenas se o novo status for "maior" ou igual na hierarquia
                        if ($newLevel >= $currentLevel || $status === $currentStatus) {
                            $charge['status'] = $status;
                            $updated = true;
                        }
                        break;
                    }
                }
                
                if ($updated) {
                    // Preservar schema_version e atualizar updated_at
                    if (!isset($paymentData['schema_version'])) {
                        $paymentData['schema_version'] = 1;
                    }
                    $paymentData['updated_at'] = date('Y-m-d H:i:s');
                    
                    // Atualizar JSON no banco
                    // Usar occurredAt do webhook (não "agora") para gateway_last_event_at
                    $updateFields = [
                        'gateway_payment_url = ?',
                        'gateway_last_status = ?',
                        'gateway_last_event_at = ?'
                    ];
                    $updateParams = [
                        json_encode($paymentData, JSON_UNESCAPED_UNICODE),
                        $status,
                        $occurredAt // Timestamp do evento (não "agora")
                    ];
                    
                    // Adicionar pix_code e barcode se disponíveis
                    if ($pixCode !== null) {
                        $updateFields[] = 'gateway_pix_code = ?';
                        $updateParams[] = $pixCode;
                    }
                    if ($barcode !== null) {
                        $updateFields[] = 'gateway_barcode = ?';
                        $updateParams[] = $barcode;
                    }
                    
                    $updateParams[] = $enrollment['id'];
                    
                    $stmt = $this->db->prepare("
                        UPDATE enrollments 
                        SET " . implode(', ', $updateFields) . "
                        WHERE id = ?
                    ");
                    $stmt->execute($updateParams);
                    
                    $this->efiLog('INFO', 'parseWebhook: Parcela do carnê atualizada', [
                        'enrollment_id' => $enrollment['id'],
                        'carnet_id' => $carnetId,
                        'charge_id' => $chargeId,
                        'status' => $status
                    ]);
                }
            }
        } else {
            // Webhook de cobrança única ou carnê completo
            $billingStatus = $this->mapGatewayStatusToBillingStatus($status);
            
            // Se for carnê completo, atualizar status geral
            // IDEMPOTÊNCIA: não regredir status
            if ($isCarnet && $paymentData) {
                $currentCarnetStatus = $paymentData['status'] ?? $enrollment['gateway_last_status'] ?? 'waiting';
                
                // Não regredir status (paid não volta para waiting)
                $statusHierarchy = ['waiting' => 1, 'unpaid' => 1, 'pending' => 2, 'processing' => 3, 'paid_partial' => 4, 'paid' => 5, 'canceled' => 0, 'expired' => 0];
                $currentLevel = $statusHierarchy[$currentCarnetStatus] ?? 0;
                $newLevel = $statusHierarchy[$status] ?? 0;
                
                if ($currentCarnetStatus === 'paid' && in_array($status, ['waiting', 'unpaid', 'pending', 'processing'])) {
                    $this->efiLog('INFO', 'parseWebhook: Ignorando regressão de status do carnê (paid -> ' . $status . ')', [
                        'enrollment_id' => $enrollment['id'],
                        'carnet_id' => $carnetId,
                        'current_status' => $currentCarnetStatus,
                        'new_status' => $status
                    ]);
                } elseif ($newLevel >= $currentLevel || $status === $currentCarnetStatus) {
                    $paymentData['status'] = $status;
                    if (isset($paymentData['charges']) && is_array($paymentData['charges'])) {
                        // Se não especificou charge_id, atualizar todas as parcelas (respeitando idempotência)
                        foreach ($paymentData['charges'] as &$charge) {
                            $chargeCurrentStatus = $charge['status'] ?? 'waiting';
                            $chargeCurrentLevel = $statusHierarchy[$chargeCurrentStatus] ?? 0;
                            // Atualizar apenas se não for regressão
                            if ($newLevel >= $chargeCurrentLevel || $status === $chargeCurrentStatus) {
                                $charge['status'] = $status;
                            }
                        }
                    }
                    
                    // Preservar schema_version e atualizar updated_at
                    if (!isset($paymentData['schema_version'])) {
                        $paymentData['schema_version'] = 1;
                    }
                    $paymentData['updated_at'] = date('Y-m-d H:i:s');
                    
                    $updateFields = [
                        'gateway_payment_url = ?',
                        'gateway_last_status = ?',
                        'gateway_last_event_at = ?',
                        'billing_status = ?'
                    ];
                    $updateParams = [
                        json_encode($paymentData, JSON_UNESCAPED_UNICODE),
                        $status,
                        $occurredAt, // Timestamp do evento (não "agora")
                        $billingStatus
                    ];
                    
                    // Adicionar pix_code e barcode se disponíveis
                    if ($pixCode !== null) {
                        $updateFields[] = 'gateway_pix_code = ?';
                        $updateParams[] = $pixCode;
                    }
                    if ($barcode !== null) {
                        $updateFields[] = 'gateway_barcode = ?';
                        $updateParams[] = $barcode;
                    }
                    
                    $updateParams[] = $enrollment['id'];
                    
                    $stmt = $this->db->prepare("
                        UPDATE enrollments 
                        SET " . implode(', ', $updateFields) . "
                        WHERE id = ?
                    ");
                    $stmt->execute($updateParams);
                }
            } else {
                // Cobrança única
                // Usar updateEnrollmentStatus com os dados de pagamento se disponíveis
                $paymentUrl = null;
                if ($pixCode) {
                    $paymentUrl = $pixCode;
                } elseif ($barcode) {
                    $paymentUrl = $barcode;
                }
                $this->updateEnrollmentStatus(
                    $enrollment['id'],
                    $billingStatus,
                    $status,
                    $chargeId,
                    $occurredAt,
                    $paymentUrl,
                    $pixCode,
                    $barcode
                );
            }
        }

        return [
            'ok' => true,
            'processed' => true,
            'charge_id' => $chargeId,
            'carnet_id' => $carnetId,
            'status' => $status,
            'is_carnet' => $isCarnet
        ];
    }

    /**
     * Consulta status de uma cobrança na Efí
     * 
     * @param string $chargeId ID da cobrança
     * @return array|null Dados da cobrança ou null em caso de erro
     */
    /**
     * Consulta status de uma cobrança na Efí
     * 
     * @param string $chargeId ID da cobrança
     * @param bool $isPix Se true, consulta na API Pix, senão na API de Cobranças
     * @return array|null Dados da cobrança ou null em caso de erro
     */
    public function getChargeStatus($chargeId, $isPix = false)
    {
        $token = $this->getAccessToken($isPix);
        if (!$token) {
            $this->efiLog('ERROR', 'getChargeStatus: Token não obtido', [
                'charge_id' => $chargeId,
                'isPix' => $isPix
            ]);
            return null;
        }

        // API Pix usa /v2/cob/{txid}, API de Cobranças usa /v1/charge/{charge_id} (singular, não plural)
        // makeRequest já adiciona /v1/ automaticamente para Cobranças
        $endpoint = $isPix ? "/v2/cob/{$chargeId}" : "/charge/{$chargeId}";
        $response = $this->makeRequest('GET', $endpoint, null, $token, $isPix);
        
        // makeRequest agora sempre retorna array com http_code
        $httpCode = $response['http_code'] ?? 0;
        $responseData = $response['response'] ?? $response;
        $rawResponse = $response['raw_response'] ?? '';
        $curlError = $response['curl_error'] ?? null;
        
        // Log detalhado para debug
        $this->efiLog('DEBUG', 'getChargeStatus response', [
            'charge_id' => $chargeId,
            'isPix' => $isPix,
            'http_code' => $httpCode,
            'has_response_data' => !empty($responseData),
            'has_data_key' => isset($responseData['data']),
            'response_keys' => is_array($responseData) ? array_keys($responseData) : [],
            'curl_error' => $curlError
        ]);
        
        if ($curlError) {
            $this->efiLog('ERROR', 'getChargeStatus: Erro cURL', [
                'charge_id' => $chargeId,
                'isPix' => $isPix,
                'curl_error' => $curlError
            ]);
            return null;
        }
        
        if ($httpCode >= 400 || !$responseData) {
            $errorMessage = 'Erro desconhecido';
            if (is_array($responseData)) {
                $errorMessage = $responseData['error_description'] ?? $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido';
                if (is_array($errorMessage) || is_object($errorMessage)) {
                    $errorMessage = json_encode($errorMessage, JSON_UNESCAPED_UNICODE);
                }
            }
            
            $this->efiLog('ERROR', 'getChargeStatus: Falha na consulta', [
                'charge_id' => $chargeId,
                'isPix' => $isPix,
                'http_code' => $httpCode,
                'error' => substr((string)$errorMessage, 0, 180),
                'response_snippet' => substr($rawResponse, 0, 200)
            ]);
            return null;
        }
        
        // API Pix retorna dados diretamente, API de Cobranças retorna dentro de 'data'
        if ($isPix) {
            // API Pix: verificar se há erro
            if (isset($responseData['error']) || isset($responseData['mensagem'])) {
                $this->efiLog('ERROR', 'getChargeStatus: API Pix retornou erro', [
                    'charge_id' => $chargeId,
                    'error' => $responseData['error'] ?? $responseData['mensagem'] ?? 'Erro desconhecido'
                ]);
                return null;
            }
            // Retornar dados diretamente
            return $responseData;
        } else {
            // API de Cobranças: dados vêm dentro de 'data'
            if (!isset($responseData['data'])) {
                // Verificar se a resposta está em formato diferente
                // Algumas APIs podem retornar dados diretamente se houver apenas um item
                if (is_array($responseData) && isset($responseData['charge_id'])) {
                    // Dados estão diretamente na resposta (não em 'data')
                    $this->efiLog('INFO', 'getChargeStatus: Dados retornados diretamente (sem data)', [
                        'charge_id' => $chargeId
                    ]);
                    return $responseData;
                }
                
                $this->efiLog('ERROR', 'getChargeStatus: Resposta sem campo data', [
                    'charge_id' => $chargeId,
                    'response_keys' => is_array($responseData) ? array_keys($responseData) : [],
                    'response_snippet' => substr(json_encode($responseData, JSON_UNESCAPED_UNICODE), 0, 300)
                ]);
                return null;
            }
            return $responseData['data'];
        }
    }

    /**
     * Verifica se debug está habilitado
     * 
     * @return bool True se EFI_DEBUG está habilitado
     */
    private function efiDebugEnabled(): bool
    {
        $raw = $_ENV['EFI_DEBUG'] ?? getenv('EFI_DEBUG') ?? 'false';
        return in_array(strtolower(trim((string)$raw)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Helper para log padronizado da integração EFI
     * Grava diretamente no arquivo storage/logs/php_errors.log
     * 
     * @param string $level Nível do log: DEBUG, INFO, WARN, ERROR
     * @param string $message Mensagem do log
     * @param array $context Contexto adicional (host, url, endpoint, isPix, http_code, etc.)
     * @return void
     */
    private function efiLog(string $level, string $message, array $context = []): void
    {
        // DEBUG só grava se debug estiver habilitado
        if ($level === 'DEBUG' && !$this->efiDebugEnabled()) {
            return;
        }
        
        // INFO, WARN, ERROR sempre gravam
        $level = strtoupper($level);
        if (!in_array($level, ['DEBUG', 'INFO', 'WARN', 'ERROR'], true)) {
            $level = 'INFO';
        }
        
        // Sanitizar contexto: nunca incluir tokens completos, client_secret, pix_key
        $safeContext = [];
        foreach ($context as $key => $value) {
            // Mascarar dados sensíveis
            if (in_array($key, ['token', 'client_secret', 'pix_key', 'access_token', 'authorization', 'auth_header', 'header'])) {
                if (is_string($value) && strlen($value) > 0) {
                    if ($key === 'token' || $key === 'access_token') {
                        $safeContext['token_len'] = strlen($value);
                        $safeContext['token_prefix'] = substr($value, 0, 10);
                    } elseif ($key === 'authorization' || $key === 'auth_header' || $key === 'header') {
                        $safeContext[$key . '_len'] = strlen($value);
                    } else {
                        // client_secret, pix_key: não logar nada
                        continue;
                    }
                }
            } else {
                // Outros valores podem ser logados (mas limitar tamanho de strings)
                if (is_string($value) && strlen($value) > 200) {
                    $safeContext[$key] = substr($value, 0, 200) . '...';
                } else {
                    $safeContext[$key] = $value;
                }
            }
        }
        
        // Montar linha de log
        $timestamp = date('Y-m-d H:i:s');
        $timezone = date_default_timezone_get();
        $contextJson = !empty($safeContext) ? ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $line = "[{$timestamp} {$timezone}] EFI-{$level}: {$message}{$contextJson}";
        
        // Gravar diretamente no arquivo
        $logFile = __DIR__ . '/../../storage/logs/php_errors.log';
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Obtém token de autenticação OAuth da Efí
     * 
     * @param bool $forPix Se true, usa OAuth da API Pix, senão usa OAuth de Cobranças
     * @return string|null Token de acesso ou null em caso de erro
     */
    private function getAccessToken($forPix = false)
    {
        // Validar credenciais antes de fazer requisição
        if (empty($this->clientId) || empty($this->clientSecret)) {
            $this->efiLog('ERROR', 'getAccessToken: Credenciais não configuradas', [
                'forPix' => $forPix,
                'has_client_id' => !empty($this->clientId),
                'has_client_secret' => !empty($this->clientSecret)
            ]);
            return null;
        }

        // OAuth Pix e OAuth Cobranças usam formatos diferentes
        // GARANTIR SEGREGAÇÃO ABSOLUTA: nunca misturar OAuth Pix com Cobranças
        if ($forPix) {
            // OAuth Pix: usa /oauth/token com form-urlencoded
            // SEMPRE usar oauthUrlPix (nunca oauthUrlCharges)
            $url = $this->oauthUrlPix . '/oauth/token';
            $payload = ['grant_type' => 'client_credentials'];
            $contentType = 'application/x-www-form-urlencoded';
            $postData = http_build_query($payload);
        } else {
            // OAuth Cobranças: usa /v1/authorize com JSON
            // SEMPRE usar oauthUrlCharges (nunca oauthUrlPix)
            // oauthUrlCharges já inclui /v1/authorize (não adicionar /oauth/token)
            $url = $this->oauthUrlCharges;
            $payload = ['grant_type' => 'client_credentials'];
            $contentType = 'application/json';
            $postData = json_encode($payload);
        }
        
        // Log já feito acima com efiLog()

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $contentType,
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        // Se certificado for necessário (geralmente exigido em produção)
        if ($this->certPath && file_exists($this->certPath)) {
            // Configurar certificado cliente para mutual TLS (mTLS)
            curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
            // Para P12, também pode precisar especificar a chave (mesmo arquivo)
            curl_setopt($ch, CURLOPT_SSLKEY, $this->certPath);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'P12');
            // Se tiver senha do certificado, usar
            if ($this->certPassword) {
                curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->certPassword);
                curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->certPassword);
            } else {
                // Tentar sem senha (certificado pode não ter senha)
                curl_setopt($ch, CURLOPT_SSLCERTPASSWD, '');
                curl_setopt($ch, CURLOPT_SSLKEYPASSWD, '');
            }
        } elseif (!$this->sandbox) {
            // Em produção, certificado pode ser obrigatório
            $this->efiLog('WARN', 'getAccessToken: Produção sem certificado configurado', [
                'forPix' => $forPix,
                'sandbox' => $this->sandbox
            ]);
        }

        // Captura verbose do cURL para debug (apenas em desenvolvimento ou se habilitado)
        $debugMode = ($_ENV['EFI_DEBUG'] ?? 'false') === 'true' || ($_ENV['APP_ENV'] ?? 'local') === 'local';
        $verboseLog = null;
        if ($debugMode) {
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        
        // Capturar verbose log se habilitado
        if ($debugMode && isset($verbose)) {
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            fclose($verbose);
        }
        
        curl_close($ch);

        // Função helper para debug (não expor segredos completos)
        $tailHex = function($s, $n = 6) {
            if (strlen($s) <= $n) return '***';
            $t = substr($s, -$n);
            return bin2hex($t);
        };

        if ($curlError) {
            $errorDetails = "cURL error: {$curlError} (errno: {$curlErrNo})";
            
            // Debug detalhado se habilitado
            if ($debugMode) {
                $errorDetails .= "\nDEBUG INFO:";
                $errorDetails .= "\n- HTTP_CODE: {$httpCode}";
                $errorDetails .= "\n- CURL_ERRNO: {$curlErrNo}";
                $errorDetails .= "\n- CLIENT_ID_LEN: " . strlen($this->clientId) . " TAIL: " . $tailHex($this->clientId);
                $errorDetails .= "\n- CLIENT_SECRET_LEN: " . strlen($this->clientSecret) . " TAIL: " . $tailHex($this->clientSecret);
                $errorDetails .= "\n- CERT_PATH: " . ($this->certPath ?? 'não configurado');
                $errorDetails .= "\n- CERT_EXISTS: " . ($this->certPath && file_exists($this->certPath) ? 'sim' : 'não');
                if ($verboseLog) {
                    $errorDetails .= "\n- CURL_VERBOSE:\n" . $verboseLog;
                }
                $errorDetails .= "\n- RESPONSE: " . substr($response, 0, 500);
            }
            
            // Mensagens mais específicas para erros comuns
            if (strpos($curlError, 'Connection was reset') !== false || strpos($curlError, 'Recv failure') !== false) {
                $errorDetails .= " | Possíveis causas: 1) Certificado cliente necessário em produção, 2) Firewall bloqueando, 3) Problema de rede";
            } elseif (strpos($curlError, 'SSL') !== false || strpos($curlError, 'certificate') !== false) {
                $errorDetails .= " | Problema com certificado SSL. Verifique EFI_CERT_PATH no .env";
            } elseif (strpos($curlError, 'timeout') !== false) {
                $errorDetails .= " | Timeout na conexão. Verifique conectividade com a internet";
            }
            
            // Log já feito acima com efiLog()
            return null;
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error_description'] ?? $errorData['error'] ?? $errorData['message'] ?? 'Erro desconhecido';
            
            // Debug detalhado se habilitado
            $debugInfo = "";
            if (($debugMode = ($_ENV['EFI_DEBUG'] ?? 'false') === 'true' || ($_ENV['APP_ENV'] ?? 'local') === 'local')) {
                $tailHex = function($s, $n = 6) {
                    if (strlen($s) <= $n) return '***';
                    $t = substr($s, -$n);
                    return bin2hex($t);
                };
                $debugInfo = "\nDEBUG INFO:";
                $debugInfo .= "\n- HTTP_CODE: {$httpCode}";
                $debugInfo .= "\n- CLIENT_ID_LEN: " . strlen($this->clientId) . " TAIL: " . $tailHex($this->clientId);
                $debugInfo .= "\n- CLIENT_SECRET_LEN: " . strlen($this->clientSecret) . " TAIL: " . $tailHex($this->clientSecret);
                $debugInfo .= "\n- CERT_PATH: " . ($this->certPath ?? 'não configurado');
                $debugInfo .= "\n- CERT_EXISTS: " . ($this->certPath && file_exists($this->certPath) ? 'sim' : 'não');
                $debugInfo .= "\n- CERT_HAS_PASSWORD: " . (!empty($this->certPassword) ? 'sim' : 'não');
                if ($verboseLog) {
                    $debugInfo .= "\n- CURL_VERBOSE:\n" . substr($verboseLog, 0, 2000);
                }
                $debugInfo .= "\n- RESPONSE_BODY: " . substr($response, 0, 500);
            }
            
            $this->efiLog('ERROR', 'getAccessToken failed', [
                'forPix' => $forPix,
                'http_code' => $httpCode,
                'curl_error' => $curlError ?: null,
                'error' => substr($errorMessage, 0, 180),
                'response_snippet' => substr($response, 0, 180)
            ]);
            return null;
        }

        if (!$response) {
            $this->efiLog('ERROR', 'getAccessToken: Resposta vazia da API', [
                'forPix' => $forPix,
                'host' => parse_url($url, PHP_URL_HOST),
                'url' => $url
            ]);
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            $this->efiLog('ERROR', 'getAccessToken: access_token não encontrado na resposta', [
                'forPix' => $forPix,
                'host' => parse_url($url, PHP_URL_HOST),
                'url' => $url,
                'response_snippet' => substr($response, 0, 180)
            ]);
            return null;
        }

        $accessToken = $data['access_token'];
        
        // Validar que o token é uma string válida
        if (!is_string($accessToken) || empty(trim($accessToken))) {
            $this->efiLog('ERROR', 'getAccessToken: access_token não é uma string válida', [
                'forPix' => $forPix,
                'host' => parse_url($url, PHP_URL_HOST),
                'url' => $url,
                'token_type' => gettype($accessToken)
            ]);
            return null;
        }
        
        $accessToken = trim($accessToken);
        
        // Log após sucesso
        $this->efiLog('INFO', 'getAccessToken result', [
            'forPix' => $forPix,
            'http_code' => $httpCode,
            'curl_error' => null,
            'token' => $accessToken, // será sanitizado pelo efiLog
            'token_len' => strlen($accessToken),
            'token_prefix' => substr($accessToken, 0, 10)
        ]);
        
        return $accessToken;
    }

    /**
     * Faz requisição HTTP para API Efí
     * 
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $endpoint Endpoint da API (ex: /charges, /v2/cob)
     * @param array|null $payload Dados para enviar (POST/PUT)
     * @param string|null $token Token de autenticação Bearer
     * @param bool $isPix Se true, usa baseUrlPix, senão usa baseUrlCharges
     * @return array|null Resposta da API ou null em caso de erro
     */
    private function makeRequest($method, $endpoint, $payload = null, $token = null, $isPix = false)
    {
        // Usar base URL Pix se for requisição Pix, senão usar base URL de Cobranças
        $baseUrl = $isPix ? $this->baseUrlPix : $this->baseUrlCharges;
        
        // Para Cobranças, garantir que endpoint começa com /v1/
        // baseUrlCharges NÃO inclui /v1 (foi removido)
        if (!$isPix && strpos($endpoint, '/v1/') !== 0 && strpos($endpoint, '/v1') !== 0) {
            // Se endpoint não começa com /v1, adicionar
            if (strpos($endpoint, '/') === 0) {
                $endpoint = '/v1' . $endpoint;
            } else {
                $endpoint = '/v1/' . $endpoint;
            }
        }
        
        $url = $baseUrl . $endpoint;
        
        // GUARDRAIL: Bloquear URLs antigas (apis.gerencianet.com.br)
        // Nenhuma requisição deve usar apis.gerencianet.com.br
        if (strpos($url, 'apis.gerencianet.com.br') !== false || strpos($url, 'api.gerencianet.com.br') !== false) {
            $this->efiLog('ERROR', 'makeRequest: URL antiga detectada e bloqueada', [
                'isPix' => $isPix,
                'url' => $url,
                'endpoint' => $endpoint
            ]);
            return [
                'http_code' => 400,
                'response' => [
                    'error' => 'URL incorreta',
                    'error_description' => 'Não use apis.gerencianet.com.br. Use cobrancas.api.efipay.com.br para Cobranças ou pix.api.efipay.com.br para Pix.'
                ],
                'raw_response' => 'URL bloqueada',
                'curl_error' => null
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // IMPORTANTE: Authorization DEVE ser o primeiro header
        // A API da EFI é muito sensível à ordem e formato dos headers
        $headers = [];
        
        if ($token) {
            // Garantir que token é string e está limpo
            $token = is_string($token) ? trim($token) : (string)$token;
            
            // Validar formato do token (deve ser JWT ou string alfanumérica)
            if (empty($token) || strlen($token) < 10) {
                $this->efiLog('ERROR', 'makeRequest: Token inválido ou muito curto', [
                    'isPix' => $isPix,
                    'host' => parse_url($url, PHP_URL_HOST),
                    'url' => $url,
                    'token_len' => strlen($token)
                ]);
                return [
                    'http_code' => 401,
                    'response' => ['error' => 'Token de autenticação inválido', 'error_description' => 'Token muito curto ou vazio'],
                    'raw_response' => 'Token inválido',
                    'curl_error' => null
                ];
            }
            
            // IMPORTANTE: Garantir que não há espaços extras ou caracteres especiais
            // A API da EFI em produção é muito sensível ao formato do header
            $token = trim($token);
            
            // Verificar se há caracteres problemáticos no token
            if (preg_match('/[^\x20-\x7E]/', $token)) {
                $this->efiLog('WARN', 'makeRequest: Token contém caracteres não-ASCII', [
                    'isPix' => $isPix,
                    'host' => parse_url($url, PHP_URL_HOST),
                    'url' => $url,
                    'token' => $token // será sanitizado pelo efiLog
                ]);
                // Remover caracteres não-ASCII do token
                $token = preg_replace('/[^\x20-\x7E]/', '', $token);
                $token = trim($token);
            }
            
            // Montar header Authorization - DEVE ser exatamente "Authorization: Bearer {token}"
            // Sem espaços extras, sem quebras de linha, sem caracteres especiais
            $authHeader = 'Authorization: Bearer ' . $token;
            
            // Verificar se o header está correto
            if (strlen($authHeader) !== strlen('Authorization: Bearer ') + strlen($token)) {
                $this->efiLog('ERROR', 'makeRequest: Header Authorization tem tamanho incorreto', [
                    'isPix' => $isPix,
                    'host' => parse_url($url, PHP_URL_HOST),
                    'url' => $url,
                    'expected_len' => strlen('Authorization: Bearer ') + strlen($token),
                    'actual_len' => strlen($authHeader)
                ]);
            }
            
            // Authorization DEVE ser o primeiro header
            $headers[] = $authHeader;
        }
        
        // Content-Type vem depois do Authorization
        $headers[] = 'Content-Type: application/json';

        if ($payload && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        
        // NÃO usar usort - pode corromper o header
        // Headers já estão na ordem correta: Authorization primeiro, depois Content-Type
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Se certificado for necessário (obrigatório em produção)
        if ($this->certPath && file_exists($this->certPath)) {
            // Configurar certificado cliente para mutual TLS (mTLS)
            curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
            // Para P12, também pode precisar especificar a chave (mesmo arquivo)
            curl_setopt($ch, CURLOPT_SSLKEY, $this->certPath);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'P12');
            // Se tiver senha do certificado, usar
            if ($this->certPassword) {
                curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->certPassword);
                curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->certPassword);
            } else {
                curl_setopt($ch, CURLOPT_SSLCERTPASSWD, '');
                curl_setopt($ch, CURLOPT_SSLKEYPASSWD, '');
            }
        } elseif (!$this->sandbox) {
            // Em produção, certificado é obrigatório para requisições da API
            $this->efiLog('WARN', 'makeRequest: Produção sem certificado configurado', [
                'isPix' => $isPix,
                'host' => parse_url($url, PHP_URL_HOST),
                'url' => $url
            ]);
        }

        // Log dos headers (apenas tamanho, nunca conteúdo completo)
        if ($token) {
            $this->efiLog('DEBUG', 'makeRequest headers', [
                'isPix' => $isPix,
                'auth_header_len' => strlen($authHeader ?? ''),
                'token' => $token, // será sanitizado pelo efiLog
                'token_len' => strlen($token),
                'token_prefix' => substr($token, 0, 10)
            ]);
        }
        
        // LOG DO PAYLOAD FINAL EXATAMENTE ANTES DO curl_exec
        // Este é o payload EXATO que será enviado à API Efí
        if ($payload && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $finalPayloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
            // Sanitizar dados sensíveis para o log
            $logPayload = $payload;
            if (isset($logPayload['customer']['cpf'])) {
                $logPayload['customer']['cpf'] = '***';
            }
            if (isset($logPayload['customer']['email'])) {
                $logPayload['customer']['email'] = '***';
            }
            if (isset($logPayload['customer']['phone_number'])) {
                $logPayload['customer']['phone_number'] = '***';
            }
            $logPayloadJson = json_encode($logPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
            $this->efiLog('INFO', 'makeRequest: PAYLOAD FINAL antes de curl_exec', [
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'isPix' => $isPix,
                'payload_final_json' => $logPayloadJson,
                'payload_size_bytes' => strlen($finalPayloadJson),
                'payload_keys' => array_keys($payload)
            ]);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        // SEMPRE retornar array com http_code, response e raw_response
        $result = [
            'http_code' => $httpCode,
            'response' => null,
            'raw_response' => $response,
            'curl_error' => $curlError ?: null
        ];

        if ($curlError) {
            $this->efiLog('ERROR', 'makeRequest: Erro cURL', [
                'isPix' => $isPix,
                'host' => parse_url($url, PHP_URL_HOST),
                'url' => $url,
                'curl_error' => $curlError
            ]);
            $result['response'] = ['error' => 'cURL Error', 'error_description' => $curlError];
            return $result;
        }

        $data = json_decode($response, true);
        $result['response'] = $data !== null ? $data : ['raw' => $response];
        
        // LOG DA RESPOSTA COMPLETA (status HTTP e body)
        $this->efiLog('INFO', 'makeRequest: Resposta recebida da API', [
            'method' => $method,
            'endpoint' => $endpoint,
            'url' => $url,
            'isPix' => $isPix,
            'http_code' => $httpCode,
            'response_body' => is_string($response) ? substr($response, 0, 2000) : json_encode($response, JSON_UNESCAPED_UNICODE),
            'response_is_json' => $data !== null,
            'response_keys' => is_array($data) ? array_keys($data) : []
        ]);

        // Logs já foram feitos acima no bloco "Log após requisição"
        
        if ($httpCode >= 400) {
            $errorDetails = [
                'http_code' => $httpCode,
                'response' => $data,
                'raw_response' => substr($response, 0, 1000), // Primeiros 1000 caracteres
                'url' => $url,
                'isPix' => $isPix,
                'method' => $method,
                'endpoint' => $endpoint
            ];
            
            // Log já feito acima com efiLog()
        }

        return $result;
    }

    /**
     * Valida assinatura do webhook
     */
    private function validateWebhookSignature($payload, $signature)
    {
        if (!$this->webhookSecret || !$signature) {
            return false;
        }

        $payloadString = is_array($payload) ? json_encode($payload) : $payload;
        $expectedSignature = hash_hmac('sha256', $payloadString, $this->webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Mapeia status do gateway para billing_status interno
     * 
     * @param string $gatewayStatus Status retornado pela EFI
     * @return string billing_status (draft/ready/generated/error)
     */
    private function mapGatewayStatusToBillingStatus($gatewayStatus)
    {
        $status = strtolower($gatewayStatus);
        
        // Status que indicam sucesso/gerado
        $successStatuses = ['paid', 'settled', 'waiting'];
        // Status que indicam erro/cancelado
        $errorStatuses = ['unpaid', 'refunded', 'canceled', 'expired', 'cancelado', 'expirado'];
        
        // Tratar "finished" - pode ser status final (verificar contexto)
        // Se vier "finished" mas a API retornar status cancelado, tratar como erro
        if ($status === 'finished') {
            // "finished" pode ser status intermediário, tratar como 'ready' por padrão
            // Mas se vier cancelado da API, será tratado como 'canceled' acima
            return 'ready';
        }
        
        if (in_array($status, $successStatuses)) {
            return 'generated';
        }
        
        if (in_array($status, $errorStatuses)) {
            return 'error';
        }
        
        // Status intermediários
        return 'ready';
    }

    /**
     * Mapeia status do gateway para financial_status interno
     * 
     * @param string $gatewayStatus Status retornado pela EFI
     * @return string|null financial_status (em_dia/pendente/bloqueado) ou null se não deve alterar
     */
    public function mapGatewayStatusToFinancialStatus($gatewayStatus)
    {
        $status = strtolower($gatewayStatus);
        
        // Status que indicam pagamento confirmado
        if (in_array($status, ['paid', 'settled', 'approved'])) {
            return 'em_dia';
        }
        
        // Status que indicam cancelamento/expirado
        // IMPORTANTE: Quando cancelado, outstanding_amount será zerado em syncCharge()
        // Então financial_status deve ser 'em_dia' (sem saldo devedor)
        if (in_array($status, ['canceled', 'expired', 'cancelado', 'expirado'])) {
            // Retornar null aqui - o syncCharge() vai zerar outstanding_amount e forçar 'em_dia'
            // Isso permite que o recalculateFinancialStatus() calcule corretamente baseado em outstanding_amount = 0
            return null; // Será recalculado baseado em outstanding_amount = 0
        }
        
        // Tratar "finished" - pode ser status final
        // Se for "finished" mas não cancelado, tratar como aguardando ou não alterar
        if ($status === 'finished') {
            // Não alterar - pode ser status intermediário
            // Se for cancelado, a API deve retornar "canceled" explicitamente
            return null;
        }
        
        // Status aguardando pagamento (mantém pendente)
        if (in_array($status, ['waiting', 'unpaid', 'pending', 'processing', 'new'])) {
            return 'pendente';
        }
        
        // Outros status: não altera financial_status (retorna null)
        return null;
    }

    /**
     * Sincroniza status de um Carnê consultando a API da EFI
     * 
     * @param array $enrollment Matrícula com gateway_charge_id (carnet_id)
     * @return array {ok: bool, carnet_id?: string, status?: string, charges?: array, message?: string}
     */
    public function syncCarnet($enrollment)
    {
        // Validar configuração
        if (!$this->clientId || !$this->clientSecret) {
            return [
                'ok' => false,
                'message' => 'Configuração do gateway não encontrada'
            ];
        }

        // Validar que existe carnê gerado
        $carnetId = $enrollment['gateway_charge_id'] ?? null;
        if (empty($carnetId)) {
            return [
                'ok' => false,
                'message' => 'Nenhum carnê gerado para esta matrícula'
            ];
        }

        // Obter token de autenticação
        $token = $this->getAccessToken(false);
        if (!$token) {
            return [
                'ok' => false,
                'message' => 'Falha ao autenticar no gateway'
            ];
        }

        // Consultar status do carnê na Efí
        // Endpoint: GET /v1/carnet/{carnet_id}
        $response = $this->makeRequest('GET', "/carnet/{$carnetId}", null, $token, false);

        $httpCode = $response['http_code'] ?? 0;
        $responseData = $response['response'] ?? null;

        if ($httpCode >= 400 || !$responseData) {
            $errorMessage = 'Erro desconhecido';
            if (is_array($responseData)) {
                $errorMessage = $responseData['error_description'] ?? $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido';
            }
            
            $this->efiLog('ERROR', 'syncCarnet: Falha ao consultar carnê', [
                'enrollment_id' => $enrollment['id'],
                'carnet_id' => $carnetId,
                'http_code' => $httpCode,
                'error' => substr((string)$errorMessage, 0, 180)
            ]);
            
            return [
                'ok' => false,
                'message' => 'Não foi possível consultar status do carnê: ' . $errorMessage
            ];
        }

        // Processar resposta do carnê
        $carnetData = $responseData['data'] ?? $responseData;
        $carnetStatus = $carnetData['status'] ?? 'waiting';
        $cover = $carnetData['cover'] ?? null;
        $downloadLink = $carnetData['link'] ?? null;
        $charges = $carnetData['charges'] ?? [];

        // Ler JSON existente para preservar schema_version e aplicar idempotência
        $existingPaymentData = null;
        if (!empty($enrollment['gateway_payment_url'])) {
            $existingPaymentData = json_decode($enrollment['gateway_payment_url'], true);
        }
        $schemaVersion = $existingPaymentData['schema_version'] ?? 1;
        $existingCarnetStatus = $existingPaymentData['status'] ?? $enrollment['gateway_last_status'] ?? 'waiting';
        $existingCharges = $existingPaymentData['charges'] ?? [];
        
        // IDEMPOTÊNCIA: não regredir status do carnê
        $statusHierarchy = ['waiting' => 1, 'unpaid' => 1, 'pending' => 2, 'processing' => 3, 'paid_partial' => 4, 'paid' => 5, 'canceled' => 0, 'expired' => 0];
        $currentLevel = $statusHierarchy[$existingCarnetStatus] ?? 0;
        $newLevel = $statusHierarchy[$carnetStatus] ?? 0;
        
        // Se o status atual é "maior" (paid) e o novo é "menor" (waiting), manter o atual
        if ($currentLevel > $newLevel && $existingCarnetStatus === 'paid') {
            $carnetStatus = $existingCarnetStatus;
            $this->efiLog('INFO', 'syncCarnet: Mantendo status paid (idempotência)', [
                'enrollment_id' => $enrollment['id'],
                'existing_status' => $existingCarnetStatus,
                'api_status' => $carnetStatus
            ]);
        }
        
        // Extrair dados completos de cada parcela (aplicando idempotência)
        $chargesData = [];
        foreach ($charges as $charge) {
            $chargeId = $charge['charge_id'] ?? null;
            if ($chargeId) {
                // Buscar status existente da parcela para idempotência
                $existingChargeStatus = 'waiting';
                foreach ($existingCharges as $existingCharge) {
                    if (($existingCharge['charge_id'] ?? null) == $chargeId) {
                        $existingChargeStatus = $existingCharge['status'] ?? 'waiting';
                        break;
                    }
                }
                
                $newChargeStatus = $charge['status'] ?? 'waiting';
                $chargeCurrentLevel = $statusHierarchy[$existingChargeStatus] ?? 0;
                $chargeNewLevel = $statusHierarchy[$newChargeStatus] ?? 0;
                
                // Não regredir status da parcela
                if ($chargeCurrentLevel > $chargeNewLevel && $existingChargeStatus === 'paid') {
                    $newChargeStatus = $existingChargeStatus;
                }
                
                $chargesData[] = [
                    'charge_id' => $chargeId,
                    'expire_at' => $charge['expire_at'] ?? null,
                    'status' => $newChargeStatus,
                    'total' => $charge['total'] ?? null,
                    'billet_link' => $charge['payment']['banking_billet']['link'] ?? null
                ];
            }
        }
        
        // Mapear status para billing_status e financial_status (igual ao syncCharge)
        $billingStatus = $this->mapGatewayStatusToBillingStatus($carnetStatus);
        $financialStatus = $this->mapGatewayStatusToFinancialStatus($carnetStatus);
        
        // Se financial_status não foi mapeado, recalcular baseado em outstanding_amount
        if ($financialStatus === null) {
            $financialStatus = $this->recalculateFinancialStatus($enrollment);
        }

        // Atualizar JSON no banco com dados atualizados (com versão e updated_at)
        $carnetDataJson = json_encode([
            'schema_version' => $schemaVersion,
            'type' => 'carne',
            'carnet_id' => $carnetId,
            'status' => $carnetStatus,
            'cover' => $cover,
            'download_link' => $downloadLink,
            'charge_ids' => array_column($chargesData, 'charge_id'),
            'payment_urls' => array_filter(array_column($chargesData, 'billet_link')),
            'charges' => $chargesData,
            'updated_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);

        // Atualizar todos os campos necessários (igual ao syncCharge)
        $stmt = $this->db->prepare("
            UPDATE enrollments 
            SET gateway_payment_url = ?,
                gateway_last_status = ?,
                gateway_last_event_at = ?,
                billing_status = ?,
                financial_status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $carnetDataJson,
            $carnetStatus,
            date('Y-m-d H:i:s'), // Refresh manual usa timestamp atual
            $billingStatus,
            $financialStatus,
            $enrollment['id']
        ]);

        // Determinar status agregado (se todas parcelas pagas, etc)
        $allPaid = true;
        $hasWaiting = false;
        foreach ($chargesData as $charge) {
            if ($charge['status'] !== 'paid' && $charge['status'] !== 'settled') {
                $allPaid = false;
            }
            if ($charge['status'] === 'waiting') {
                $hasWaiting = true;
            }
        }

        $aggregatedStatus = $allPaid ? 'paid' : ($hasWaiting ? 'waiting' : $carnetStatus);

        return [
            'ok' => true,
            'carnet_id' => $carnetId,
            'status' => $carnetStatus,
            'aggregated_status' => $aggregatedStatus,
            'billing_status' => $billingStatus,
            'financial_status' => $financialStatus,
            'cover' => $cover,
            'download_link' => $downloadLink,
            'charges' => $chargesData
        ];
    }

    /**
     * Cancela um Carnê na API da EFI
     * 
     * @param array $enrollment Matrícula com gateway_charge_id (carnet_id)
     * @return array {ok: bool, message?: string}
     */
    public function cancelCarnet($enrollment)
    {
        // Validar configuração
        if (!$this->clientId || !$this->clientSecret) {
            return [
                'ok' => false,
                'message' => 'Configuração do gateway não encontrada'
            ];
        }

        // Validar que existe carnê gerado
        $carnetId = $enrollment['gateway_charge_id'] ?? null;
        if (empty($carnetId)) {
            return [
                'ok' => false,
                'message' => 'Nenhum carnê gerado para esta matrícula'
            ];
        }

        // Obter token de autenticação
        $token = $this->getAccessToken(false);
        if (!$token) {
            return [
                'ok' => false,
                'message' => 'Falha ao autenticar no gateway'
            ];
        }

        // Cancelar carnê na Efí
        // Endpoint: PUT /v1/carnet/{carnet_id}/cancel
        $response = $this->makeRequest('PUT', "/carnet/{$carnetId}/cancel", null, $token, false);

        $httpCode = $response['http_code'] ?? 0;
        $responseData = $response['response'] ?? null;

        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMessage = 'Erro desconhecido';
            if (is_array($responseData)) {
                $errorMessage = $responseData['error_description'] ?? $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido';
            }
            
            $this->efiLog('ERROR', 'cancelCarnet: Falha ao cancelar carnê', [
                'enrollment_id' => $enrollment['id'],
                'carnet_id' => $carnetId,
                'http_code' => $httpCode,
                'error' => substr((string)$errorMessage, 0, 180)
            ]);
            
            return [
                'ok' => false,
                'message' => 'Não foi possível cancelar o carnê: ' . $errorMessage
            ];
        }

        // Atualizar status no banco
        $this->updateEnrollmentStatus(
            $enrollment['id'],
            'canceled', // billing_status = canceled (não error)
            'canceled', // gateway_last_status = canceled
            $carnetId,
            date('Y-m-d H:i:s')
        );

        // Atualizar JSON no banco marcando como cancelado (preservar schema_version)
        $paymentData = null;
        if (!empty($enrollment['gateway_payment_url'])) {
            $paymentData = json_decode($enrollment['gateway_payment_url'], true);
        }
        
        if ($paymentData && isset($paymentData['type']) && $paymentData['type'] === 'carne') {
            // Preservar schema_version se existir
            if (!isset($paymentData['schema_version'])) {
                $paymentData['schema_version'] = 1;
            }
            $paymentData['status'] = 'canceled';
            $paymentData['updated_at'] = date('Y-m-d H:i:s');
            if (isset($paymentData['charges']) && is_array($paymentData['charges'])) {
                foreach ($paymentData['charges'] as &$charge) {
                    $charge['status'] = 'canceled';
                }
            }
            
            $stmt = $this->db->prepare("
                UPDATE enrollments 
                SET gateway_payment_url = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($paymentData, JSON_UNESCAPED_UNICODE),
                $enrollment['id']
            ]);
        }

        $this->efiLog('INFO', 'cancelCarnet: Carnê cancelado com sucesso', [
            'enrollment_id' => $enrollment['id'],
            'carnet_id' => $carnetId
        ]);

        return [
            'ok' => true,
            'message' => 'Carnê cancelado com sucesso'
        ];
    }

    /**
     * Sincroniza status de uma cobrança consultando a API da EFI
     * 
     * @param array $enrollment Matrícula com gateway_charge_id
     * @return array {ok: bool, charge_id?: string, status?: string, payment_url?: string, financial_status?: string, message?: string}
     */
    public function syncCharge($enrollment)
    {
        // Validar configuração
        if (!$this->clientId || !$this->clientSecret) {
            return [
                'ok' => false,
                'message' => 'Configuração do gateway não encontrada'
            ];
        }

        // Validar que existe cobrança gerada
        $chargeId = $enrollment['gateway_charge_id'] ?? null;
        if (empty($chargeId)) {
            return [
                'ok' => false,
                'message' => 'Nenhuma cobrança gerada para esta matrícula'
            ];
        }

        // Verificar se é Carnê
        $paymentData = null;
        if (!empty($enrollment['gateway_payment_url'])) {
            $paymentData = json_decode($enrollment['gateway_payment_url'], true);
        }
        $isCarnet = $paymentData && isset($paymentData['type']) && $paymentData['type'] === 'carne';

        // Se for Carnê, usar syncCarnet
        if ($isCarnet) {
            return $this->syncCarnet($enrollment);
        }

        // Determinar se é PIX baseado no payment_method da matrícula
        $paymentMethod = $enrollment['payment_method'] ?? null;
        $isPix = ($paymentMethod === 'pix');

        // Consultar status na EFI (usar API Pix se for PIX)
        $chargeData = $this->getChargeStatus($chargeId, $isPix);
        if (!$chargeData) {
            return [
                'ok' => false,
                'message' => 'Não foi possível consultar status da cobrança na EFI. Verifique se a cobrança existe ou se há problemas de conexão.'
            ];
        }

        $status = 'unknown';
        $paymentUrl = null;

        // Processar resposta conforme o tipo de API
        if ($isPix) {
            // API Pix: dados vêm diretamente (não dentro de 'data')
            $status = $chargeData['status'] ?? 'ATIVA'; // API Pix usa 'ATIVA', 'CONCLUIDA', etc.
            // Mapear status Pix para formato padrão
            if ($status === 'CONCLUIDA') {
                $status = 'paid';
            } elseif ($status === 'ATIVA') {
                $status = 'waiting';
            }
            
            // Extrair QR Code da API Pix
            $paymentUrl = $chargeData['pixCopiaECola'] ?? $chargeData['qrCode'] ?? null;
            
            // Se não tiver QR Code direto, pode estar em location
            if (empty($paymentUrl) && isset($chargeData['location'])) {
                // TODO: Consultar location se necessário
            }
        } else {
            // API de Cobranças: dados vêm dentro de 'data'
            $status = $chargeData['status'] ?? 'unknown';
            
            // Extrair URL de pagamento se disponível
            if (isset($chargeData['payment'])) {
                if (isset($chargeData['payment']['pix']['qr_code'])) {
                    $paymentUrl = $chargeData['payment']['pix']['qr_code'];
                } elseif (isset($chargeData['payment']['banking_billet']['link'])) {
                    $paymentUrl = $chargeData['payment']['banking_billet']['link'];
                }
            }
        }

        // Verificar se status "finished" foi cancelado/expirado ou pago ANTES de mapear
        $statusLower = strtolower($status);
        $isFinished = ($statusLower === 'finished');
        
        if ($isFinished) {
            // Verificar campos adicionais na resposta da API
            $hasCanceledAt = !empty($chargeData['canceled_at'] ?? null);
            $hasExpiredAt = !empty($chargeData['expired_at'] ?? null);
            $hasPaidAt = !empty($chargeData['paid_at'] ?? null);
            
            // Se tem canceled_at ou expired_at, foi cancelada/expirada
            if ($hasCanceledAt || $hasExpiredAt) {
                $status = $hasCanceledAt ? 'canceled' : 'expired';
            } 
            // Se tem paid_at, foi paga
            elseif ($hasPaidAt) {
                $status = 'paid';
            }
            // Se não tem nenhum desses campos, assumir que foi cancelada/expirada
            // (pois "finished" sem paid_at geralmente indica cancelamento/expirado)
            else {
                $status = 'canceled'; // Assumir cancelado se não há informação de pagamento
            }
        }

        // Mapear status (agora com status corrigido se era "finished")
        $billingStatus = $this->mapGatewayStatusToBillingStatus($status);
        $financialStatus = $this->mapGatewayStatusToFinancialStatus($status);

        // Atualizar matrícula
        $eventAt = isset($chargeData['updated_at']) ? date('Y-m-d H:i:s', strtotime($chargeData['updated_at'])) : date('Y-m-d H:i:s');
        
        // Preparar dados de atualização
        $updateData = [
            'billing_status' => $billingStatus,
            'gateway_last_status' => $status, // Sempre atualizar com status real da API (não manter "finished" se vier "canceled")
            'gateway_last_event_at' => $eventAt,
            'gateway_provider' => 'efi'
        ];

        // Atualizar payment_url se fornecido e ainda não existir
        if ($paymentUrl && empty($enrollment['gateway_payment_url'])) {
            $updateData['gateway_payment_url'] = $paymentUrl;
        }

        // Se cobrança foi cancelada, expirada ou paga, zerar outstanding_amount
        $statusLower = strtolower($status);
        $isCanceledOrExpired = in_array($statusLower, ['canceled', 'expired', 'cancelado', 'expirado']);
        $isPaid = in_array($statusLower, ['paid', 'settled', 'approved']);
        
        if ($isCanceledOrExpired || $isPaid) {
            // Cobrança cancelada/expirada ou paga: zerar saldo devedor
            $updateData['outstanding_amount'] = 0;
            
            if ($isCanceledOrExpired) {
                // Atualizar billing_status para 'error' se cancelada/expirada
                if ($billingStatus !== 'error') {
                    $billingStatus = 'error';
                    $updateData['billing_status'] = $billingStatus;
                }
            }
            
            // Se não tinha mapeamento de financial_status, forçar 'em_dia' (sem saldo)
            if ($financialStatus === null) {
                $financialStatus = 'em_dia';
            }
        }

        // Atualizar financial_status se mapeado
        if ($financialStatus !== null) {
            $updateData['financial_status'] = $financialStatus;
        } else {
            // Se não foi mapeado, recalcular baseado em outstanding_amount
            $updateData['financial_status'] = $this->recalculateFinancialStatus($enrollment);
        }

        $setParts = [];
        $params = [];
        foreach ($updateData as $key => $value) {
            $setParts[] = "`{$key}` = ?";
            $params[] = $value;
        }
        $params[] = $enrollment['id'];

        $sql = "UPDATE enrollments SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        // Log (sem dados sensíveis)
        error_log(sprintf(
            "EFI Sync: enrollment_id=%d, charge_id=%s, status=%s, billing_status=%s, financial_status=%s, outstanding_amount=%s",
            $enrollment['id'],
            $chargeId,
            $status,
            $billingStatus,
            $financialStatus ?? 'não alterado',
            isset($updateData['outstanding_amount']) ? $updateData['outstanding_amount'] : 'não alterado'
        ));

        return [
            'ok' => true,
            'charge_id' => $chargeId,
            'status' => $status,
            'billing_status' => $billingStatus,
            'financial_status' => $financialStatus,
            'payment_url' => $paymentUrl ?: $enrollment['gateway_payment_url'] ?? null
        ];
    }

    /**
     * Recalcula financial_status baseado em outstanding_amount
     * 
     * @param array $enrollment Matrícula com dados
     * @return string financial_status ('em_dia', 'pendente', 'bloqueado')
     */
    private function recalculateFinancialStatus($enrollment)
    {
        // Se já está bloqueado, manter bloqueado
        if (($enrollment['financial_status'] ?? '') === 'bloqueado') {
            return 'bloqueado';
        }
        
        // Calcular saldo devedor
        $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? 0);
        if ($outstandingAmount <= 0) {
            // Se não tem coluna outstanding_amount, calcular
            if (empty($enrollment['outstanding_amount'])) {
                $finalPrice = floatval($enrollment['final_price'] ?? 0);
                $entryAmount = floatval($enrollment['entry_amount'] ?? 0);
                $outstandingAmount = max(0, $finalPrice - $entryAmount);
            }
        }
        
        // Se tem saldo devedor, deve ser 'pendente'
        // Se não tem saldo, deve ser 'em_dia'
        return $outstandingAmount > 0 ? 'pendente' : 'em_dia';
    }

    /**
     * Atualiza status da matrícula no banco
     * 
     * @param int $enrollmentId ID da matrícula
     * @param string $billingStatus Status interno (draft/ready/generated/error)
     * @param string $gatewayStatus Status do gateway (paid/waiting/canceled/etc)
     * @param string|null $chargeId ID da cobrança no gateway
     * @param string|null $eventAt Data/hora do evento (formato Y-m-d H:i:s)
     * @param string|null $paymentUrl URL de pagamento (PIX ou Boleto)
     */
    private function updateEnrollmentStatus($enrollmentId, $billingStatus, $gatewayStatus, $chargeId = null, $eventAt = null, $paymentUrl = null, $pixCode = null, $barcode = null)
    {
        // Buscar matrícula atual para recalcular financial_status
        $stmt = $this->db->prepare("SELECT * FROM enrollments WHERE id = ?");
        $stmt->execute([$enrollmentId]);
        $currentEnrollment = $stmt->fetch();
        
        if (!$currentEnrollment) {
            return;
        }
        
        $updateData = [
            'billing_status' => $billingStatus,
            'gateway_last_status' => $gatewayStatus,
            'gateway_last_event_at' => $eventAt ?: date('Y-m-d H:i:s'),
            'gateway_provider' => 'efi'
        ];

        if ($chargeId) {
            $updateData['gateway_charge_id'] = $chargeId;
        }

        // Salvar payment_url se fornecido (não sobrescreve se já existir e novo for vazio)
        if ($paymentUrl !== null) {
            $updateData['gateway_payment_url'] = $paymentUrl;
        }
        
        // Salvar PIX copia-e-cola se fornecido
        if ($pixCode !== null) {
            $updateData['gateway_pix_code'] = $pixCode;
        }
        
        // Salvar linha digitável do boleto se fornecido
        if ($barcode !== null) {
            $updateData['gateway_barcode'] = $barcode;
        }
        
        // Recalcular financial_status baseado em outstanding_amount
        // (exceto se já está bloqueado ou se foi mapeado pelo gateway)
        $updateData['financial_status'] = $this->recalculateFinancialStatus($currentEnrollment);

        $setParts = [];
        $params = [];
        foreach ($updateData as $key => $value) {
            $setParts[] = "`{$key}` = ?";
            $params[] = $value;
        }
        $params[] = $enrollmentId;

        $sql = "UPDATE enrollments SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Sanitiza nome do cliente conforme padrão da API Efí
     * 
     * Regex Efí: ^(?!.*[؀-ۿ])[ ]*(.+[ ]+)+.+[ ]*$
     * - Não pode conter caracteres árabes (؀-ۿ)
     * - Deve ter pelo menos um espaço entre palavras (mínimo 2 palavras)
     * - Pode ter espaços no início/fim (serão removidos)
     * 
     * @param string $name Nome original
     * @return string Nome sanitizado
     */
    private function sanitizeCustomerName($name)
    {
        if (empty($name)) {
            return 'Cliente';
        }
        
        // Remover caracteres árabes (؀-ۿ) e outros caracteres especiais problemáticos
        $name = preg_replace('/[\x{0600}-\x{06FF}]/u', '', $name); // Remove árabes
        
        // Remover caracteres especiais não permitidos (manter apenas letras, números, espaços e acentos)
        $name = preg_replace('/[^\p{L}\p{N}\s\-\.]/u', '', $name);
        
        // Normalizar espaços múltiplos para espaço único
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Remover espaços no início e fim
        $name = trim($name);
        
        // Se ficou vazio após sanitização, usar fallback
        if (empty($name)) {
            return 'Cliente';
        }
        
        // Verificar se tem pelo menos 2 palavras (requisito do regex Efí)
        $words = explode(' ', $name);
        $words = array_filter($words, function($word) {
            return !empty(trim($word));
        });
        
        if (count($words) < 2) {
            // Se tem apenas uma palavra, adicionar "Cliente" no início
            $name = 'Cliente ' . $name;
        }
        
        // Garantir que o nome não exceda 100 caracteres (limite comum de APIs)
        if (mb_strlen($name) > 100) {
            $name = mb_substr($name, 0, 100);
            // Garantir que não cortou no meio de uma palavra
            $lastSpace = mb_strrpos($name, ' ');
            if ($lastSpace !== false) {
                $name = mb_substr($name, 0, $lastSpace);
            }
        }
        
        return trim($name);
    }
}
