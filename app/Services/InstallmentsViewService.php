<?php

namespace App\Services;

/**
 * Service para normalizar a visualização de parcelas para o aluno
 * 
 * Este service cria uma "vista virtual" de parcelas baseada em:
 * - Carnê (JSON em gateway_payment_url)
 * - Cobrança única (string em gateway_payment_url)
 * - Cálculo dinâmico (quando não há cobrança gerada)
 */
class InstallmentsViewService
{
    /**
     * Retorna array padronizado de parcelas para uma matrícula
     * 
     * @param array $enrollment Matrícula com todos os campos necessários
     * @return array Array de parcelas no formato padronizado
     */
    public function getInstallmentsViewForEnrollment(array $enrollment): array
    {
        $installments = [];
        
        // Caso A: Matrícula com carnê (gateway_payment_url é JSON com type=carne)
        if (!empty($enrollment['gateway_payment_url'])) {
            $paymentData = json_decode($enrollment['gateway_payment_url'], true);
            
            if (json_last_error() === JSON_ERROR_NONE && 
                isset($paymentData['type']) && 
                $paymentData['type'] === 'carne' &&
                isset($paymentData['charges']) && 
                is_array($paymentData['charges'])) {
                
                // Verificar se o carnê foi cancelado
                $carnetStatus = $paymentData['status'] ?? null;
                $billingStatus = $enrollment['billing_status'] ?? null;
                $isCarnetCanceled = ($billingStatus === 'canceled' || $carnetStatus === 'canceled');
                
                // Processar cada parcela do carnê
                $totalAmount = 0;
                foreach ($paymentData['charges'] as $index => $charge) {
                    $chargeId = $charge['charge_id'] ?? null;
                    $expireAt = $charge['expire_at'] ?? null;
                    $gatewayStatus = $charge['status'] ?? 'waiting';
                    $billetLink = $charge['billet_link'] ?? null;
                    
                    // Determinar payment_url (prioridade: billet_link > download_link > cover)
                    $paymentUrl = $billetLink;
                    if (!$paymentUrl && isset($paymentData['download_link'])) {
                        $paymentUrl = $paymentData['download_link'];
                    }
                    if (!$paymentUrl && isset($paymentData['cover'])) {
                        $paymentUrl = $paymentData['cover'];
                    }
                    
                    // Calcular valor da parcela
                    $amount = null;
                    if (isset($charge['value'])) {
                        $amount = floatval($charge['value']) / 100; // Se vier em centavos
                    } elseif (isset($charge['amount'])) {
                        $amount = floatval($charge['amount']);
                    }
                    
                    // Se não tiver valor na parcela, calcular dividindo outstanding_amount
                    if ($amount === null || $amount <= 0) {
                        $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? 
                                                     ($enrollment['final_price'] - ($enrollment['entry_amount'] ?? 0)));
                        $totalCharges = count($paymentData['charges']);
                        if ($totalCharges > 0) {
                            $amount = round($outstandingAmount / $totalCharges, 2);
                        } else {
                            $amount = $outstandingAmount;
                        }
                    }
                    
                    $totalAmount += $amount;
                    
                    // Verificar se a cobrança foi cancelada (prioridade ao billing_status ou status do carnê)
                    if ($isCarnetCanceled || $gatewayStatus === 'canceled') {
                        $status = 'canceled';
                    } else {
                        // Normalizar status
                        $status = $this->normalizeStatus($gatewayStatus, $expireAt);
                    }
                    
                    // Label da parcela
                    $label = ($index + 1) . '/' . count($paymentData['charges']);
                    
                    $installments[] = [
                        'label' => $label,
                        'number' => $index + 1,
                        'due_date' => $expireAt ? date('Y-m-d', strtotime($expireAt)) : null,
                        'amount' => round($amount, 2),
                        'status' => $status,
                        'gateway_status' => $gatewayStatus,
                        'payment_url' => $paymentUrl,
                        'charge_id' => $chargeId,
                        'source' => 'carne_json'
                    ];
                }
                
                return $installments;
            }
        }
        
        // Caso B: Matrícula com cobrança única (gateway_payment_url é string)
        if (!empty($enrollment['gateway_charge_id']) && 
            !empty($enrollment['gateway_payment_url']) &&
            !is_array(json_decode($enrollment['gateway_payment_url'], true))) {
            
            $paymentUrl = $enrollment['gateway_payment_url'];
            $gatewayStatus = $enrollment['gateway_last_status'] ?? 'waiting';
            $billingStatus = $enrollment['billing_status'] ?? null;
            $dueDate = null;
            
            // Tentar obter vencimento
            if (!empty($enrollment['first_due_date']) && $enrollment['first_due_date'] !== '0000-00-00') {
                $dueDate = $enrollment['first_due_date'];
            } elseif (!empty($enrollment['down_payment_due_date']) && $enrollment['down_payment_due_date'] !== '0000-00-00') {
                $dueDate = $enrollment['down_payment_due_date'];
            }
            
            // Calcular valor
            $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? 
                                         ($enrollment['final_price'] - ($enrollment['entry_amount'] ?? 0)));
            
            // Verificar se a cobrança foi cancelada (prioridade ao billing_status)
            if ($billingStatus === 'canceled' || $gatewayStatus === 'canceled') {
                $status = 'canceled';
            } else {
                // Normalizar status
                $status = $this->normalizeStatus($gatewayStatus, $dueDate);
            }
            
            $installments[] = [
                'label' => 'Pagamento',
                'number' => 0,
                'due_date' => $dueDate,
                'amount' => round($outstandingAmount, 2),
                'status' => $status,
                'gateway_status' => $gatewayStatus,
                'payment_url' => $paymentUrl,
                'charge_id' => $enrollment['gateway_charge_id'],
                'source' => 'single_charge'
            ];
            
            return $installments;
        }
        
        // Caso C: Matrícula sem cobrança gerada (calcular parcelas dinamicamente)
        return $this->calculateInstallments($enrollment);
    }
    
    /**
     * Calcula parcelas dinamicamente quando não há cobrança gerada
     * 
     * @param array $enrollment Matrícula
     * @return array Array de parcelas calculadas
     */
    private function calculateInstallments(array $enrollment): array
    {
        $installments = [];
        
        // Calcular saldo devedor
        $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? 
                                     ($enrollment['final_price'] - ($enrollment['entry_amount'] ?? 0)));
        
        // Caso especial: Cartão pago localmente (gateway_provider='local')
        // Exibir parcelas informativas mesmo com outstanding_amount=0
        $isCartaoLocalPaid = ($enrollment['payment_method'] ?? '') === 'cartao' && 
                             ($enrollment['gateway_provider'] ?? '') === 'local' &&
                             $outstandingAmount <= 0;
        
        if ($outstandingAmount <= 0 && !$isCartaoLocalPaid) {
            return $installments; // Sem saldo devedor, sem parcelas
        }
        
        // Para cartão pago localmente, usar final_price para cálculo informativo
        if ($isCartaoLocalPaid) {
            $outstandingAmount = floatval($enrollment['final_price'] ?? 0);
        }
        
        // Verificar se tem entrada separada
        $downPaymentAmount = floatval($enrollment['down_payment_amount'] ?? 0);
        $downPaymentDueDate = !empty($enrollment['down_payment_due_date']) && 
                             $enrollment['down_payment_due_date'] !== '0000-00-00' 
                             ? $enrollment['down_payment_due_date'] : null;
        
        // Se tem entrada e ainda não foi paga (está no saldo devedor)
        if ($downPaymentAmount > 0 && $downPaymentDueDate) {
            $installments[] = [
                'label' => 'Entrada',
                'number' => 0,
                'due_date' => $downPaymentDueDate,
                'amount' => round($downPaymentAmount, 2),
                'status' => $this->getStatusByDueDate($downPaymentDueDate),
                'gateway_status' => null,
                'payment_url' => null,
                'charge_id' => null,
                'source' => 'calculated'
            ];
            
            // Reduzir saldo devedor
            $outstandingAmount -= $downPaymentAmount;
        }
        
        // Calcular parcelas restantes
        $installmentsCount = intval($enrollment['installments'] ?? 1);
        $firstDueDate = !empty($enrollment['first_due_date']) && 
                       $enrollment['first_due_date'] !== '0000-00-00' 
                       ? $enrollment['first_due_date'] : null;
        
        // Para cartão pago localmente, permitir cálculo mesmo sem outstanding_amount
        if ($installmentsCount > 1 && $firstDueDate && ($outstandingAmount > 0 || $isCartaoLocalPaid)) {
            // Dividir saldo restante em parcelas
            $parcelAmount = round($outstandingAmount / $installmentsCount, 2);
            
            // Ajustar última parcela para compensar arredondamentos
            $totalCalculated = $parcelAmount * $installmentsCount;
            $difference = $outstandingAmount - $totalCalculated;
            
            for ($i = 0; $i < $installmentsCount; $i++) {
                $parcelNumber = $i + 1;
                
                // Calcular vencimento (adicionar meses a partir de first_due_date)
                $dueDate = date('Y-m-d', strtotime($firstDueDate . " +{$i} months"));
                
                // Ajustar valor da última parcela se houver diferença
                $amount = $parcelAmount;
                if ($i === $installmentsCount - 1 && abs($difference) > 0.01) {
                    $amount += $difference;
                }
                
                $installments[] = [
                    'label' => $parcelNumber . '/' . $installmentsCount,
                    'number' => $parcelNumber,
                    'due_date' => $dueDate,
                    'amount' => round($amount, 2),
                    'status' => $isCartaoLocalPaid ? 'paid' : $this->getStatusByDueDate($dueDate),
                    'gateway_status' => $isCartaoLocalPaid ? 'paid_local' : null,
                    'payment_url' => null,
                    'charge_id' => null,
                    'source' => $isCartaoLocalPaid ? 'calculated_local' : 'calculated'
                ];
            }
        } elseif ($outstandingAmount > 0) {
            // Sem parcelamento definido, mostrar como "Saldo em aberto"
            $dueDate = $firstDueDate ?? $downPaymentDueDate;
            
            $installments[] = [
                'label' => 'Saldo em aberto',
                'number' => 0,
                'due_date' => $dueDate,
                'amount' => round($outstandingAmount, 2),
                'status' => $dueDate ? $this->getStatusByDueDate($dueDate) : 'open',
                'gateway_status' => null,
                'payment_url' => null,
                'charge_id' => null,
                'source' => 'calculated'
            ];
        }
        
        return $installments;
    }
    
    /**
     * Normaliza status do gateway para status padronizado
     * 
     * @param string|null $gatewayStatus Status do gateway
     * @param string|null $dueDate Data de vencimento
     * @return string Status normalizado: 'paid', 'open', 'overdue', 'canceled', 'unknown'
     */
    private function normalizeStatus(?string $gatewayStatus, ?string $dueDate): string
    {
        if (empty($gatewayStatus)) {
            return $dueDate ? $this->getStatusByDueDate($dueDate) : 'unknown';
        }
        
        $gatewayStatus = strtolower($gatewayStatus);
        
        // Status pagos
        if (in_array($gatewayStatus, ['paid', 'settled'])) {
            return 'paid';
        }
        
        // Status cancelados (não deve ser tratado como overdue)
        if ($gatewayStatus === 'canceled') {
            return 'canceled';
        }
        
        // Status abertos (não vencidos)
        if (in_array($gatewayStatus, ['waiting', 'pending', 'processing', 'new', 'up_to_date'])) {
            if ($dueDate) {
                return $this->getStatusByDueDate($dueDate);
            }
            return 'open';
        }
        
        // Status vencidos/não pagos (mas não cancelados)
        if (in_array($gatewayStatus, ['unpaid', 'expired', 'error'])) {
            return 'overdue';
        }
        
        return 'unknown';
    }
    
    /**
     * Determina status baseado na data de vencimento
     * 
     * @param string|null $dueDate Data de vencimento (YYYY-MM-DD)
     * @return string 'open' ou 'overdue'
     */
    private function getStatusByDueDate(?string $dueDate): string
    {
        if (!$dueDate) {
            return 'open';
        }
        
        $dueTimestamp = strtotime($dueDate);
        $todayTimestamp = strtotime(date('Y-m-d'));
        
        return $dueTimestamp < $todayTimestamp ? 'overdue' : 'open';
    }
    
    /**
     * Retorna estatísticas agregadas de parcelas
     * 
     * @param array $installments Array de parcelas
     * @return array Estatísticas: next_due_date, overdue_count, open_total
     */
    public function getInstallmentsStats(array $installments): array
    {
        $nextDueDate = null;
        $overdueCount = 0;
        $openTotal = 0.0;
        
        $today = date('Y-m-d');
        
        foreach ($installments as $installment) {
            // Contar vencidas
            if ($installment['status'] === 'overdue') {
                $overdueCount++;
            }
            
            // Somar valores em aberto (não pagos)
            if ($installment['status'] !== 'paid') {
                $openTotal += $installment['amount'];
            }
            
            // Próximo vencimento (primeira parcela não paga e não vencida)
            if (!$nextDueDate && 
                $installment['status'] === 'open' && 
                $installment['due_date'] &&
                $installment['due_date'] >= $today) {
                $nextDueDate = $installment['due_date'];
            }
        }
        
        return [
            'next_due_date' => $nextDueDate,
            'overdue_count' => $overdueCount,
            'open_total' => round($openTotal, 2)
        ];
    }
}
