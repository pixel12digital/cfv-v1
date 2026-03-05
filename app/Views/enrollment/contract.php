<?php
$enrollment = $enrollment ?? [];
$student = $student ?? [];
$cfc = $cfc ?? [];
$service = $service ?? [];
$paymentDetails = $paymentDetails ?? [];

function formatPaymentMethod($method) {
    $map = [
        'pix' => 'PIX',
        'boleto' => 'Boleto',
        'cartao' => 'Cartão de Crédito',
        'entrada_parcelas' => 'Entrada + Parcelas',
        'dinheiro' => 'Dinheiro'
    ];
    return $map[$method] ?? ucfirst($method);
}

function formatFinancialStatus($status) {
    $map = [
        'em_dia' => 'Em Dia',
        'pendente' => 'Pendente',
        'bloqueado' => 'Bloqueado'
    ];
    return $map[$status] ?? ucfirst($status);
}

function formatEnrollmentStatus($status) {
    $map = [
        'ativa' => 'Ativa',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada'
    ];
    return $map[$status] ?? ucfirst($status);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Matrícula #<?= $enrollment['id'] ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            background: white;
            margin: 0;
            padding: 0;
        }
        
        .container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm 15mm;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-logo {
            max-width: 80px;
            max-height: 60px;
            object-fit: contain;
        }
        
        .header-info {
            text-align: right;
        }
        
        .header-info h1 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 3px;
            color: #000;
        }
        
        .header-info p {
            font-size: 8pt;
            margin: 1px 0;
            color: #333;
        }
        
        .title {
            text-align: center;
            margin: 15px 0 12px 0;
        }
        
        .title h2 {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #000;
            margin-bottom: 3px;
        }
        
        .title .contract-number {
            font-size: 9pt;
            color: #333;
        }
        
        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #ccc;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px 15px;
            margin-bottom: 8px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        
        .info-label {
            font-size: 8pt;
            font-weight: bold;
            color: #555;
            margin-bottom: 1px;
        }
        
        .info-value {
            font-size: 9pt;
            color: #000;
        }
        
        .financial-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .financial-table th {
            background: #f0f0f0;
            padding: 6px 8px;
            text-align: left;
            font-size: 9pt;
            font-weight: bold;
            border: 1px solid #ccc;
        }
        
        .financial-table td {
            padding: 6px 8px;
            border: 1px solid #ccc;
            font-size: 9pt;
        }
        
        .financial-table .text-right {
            text-align: right;
        }
        
        .financial-table .total-row {
            background: #f9f9f9;
            font-weight: bold;
        }
        
        .payment-summary {
            background: #f8f8f8;
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
        }
        
        .payment-summary-item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 9pt;
        }
        
        .payment-summary-item.total {
            font-size: 11pt;
            font-weight: bold;
            border-top: 2px solid #000;
            margin-top: 6px;
            padding-top: 6px;
        }
        
        .signatures {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        
        .signature-block {
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 3px;
            padding-top: 3px;
        }
        
        .signature-label {
            font-size: 8pt;
            color: #555;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 7pt;
            color: #666;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .section {
                page-break-inside: avoid;
            }
            
            @page {
                size: A4 portrait;
                margin: 12mm 15mm;
            }
        }
    </style>
</head>
<body>
    <script>
        // Auto-trigger print dialog when page loads
        window.onload = function() {
            window.print();
        };
        
        // Close window after print dialog is closed
        window.onafterprint = function() {
            window.close();
        };
    </script>

    <div class="container">
        <!-- Cabeçalho -->
        <div class="header">
            <div class="header-left">
                <?php
                $logoPath = __DIR__ . '/../../../public/uploads/logo.png';
                if (file_exists($logoPath)) {
                    echo '<img src="' . base_path('public/uploads/logo.png') . '" alt="Logo" class="header-logo">';
                }
                ?>
                <div>
                    <h1 style="font-size: 14pt; margin: 0;"><?= htmlspecialchars($cfc['nome'] ?? 'CFC') ?></h1>
                </div>
            </div>
            <div class="header-info">
                <?php if (!empty($cfc['cnpj'])): ?>
                <p><strong>CNPJ:</strong> <?= htmlspecialchars($cfc['cnpj']) ?></p>
                <?php endif; ?>
                <?php if (!empty($cfc['endereco'])): ?>
                <p><?= htmlspecialchars($cfc['endereco']) ?></p>
                <?php endif; ?>
                <?php if (!empty($cfc['telefone'])): ?>
                <p><strong>Tel:</strong> <?= htmlspecialchars($cfc['telefone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($cfc['email'])): ?>
                <p><strong>Email:</strong> <?= htmlspecialchars($cfc['email']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Título -->
        <div class="title">
            <h2>Contrato de Matrícula</h2>
            <p class="contract-number">Matrícula Nº <?= str_pad($enrollment['id'], 6, '0', STR_PAD_LEFT) ?></p>
            <p class="contract-number">Data: <?= date('d/m/Y H:i', strtotime($enrollment['created_at'])) ?></p>
        </div>

        <!-- Dados do Aluno -->
        <div class="section">
            <div class="section-title">Dados do Aluno</div>
            <div class="info-grid">
                <div class="info-item full-width">
                    <span class="info-label">Nome Completo</span>
                    <span class="info-value"><?= htmlspecialchars($student['full_name'] ?? $student['name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">CPF</span>
                    <span class="info-value"><?= htmlspecialchars($student['cpf'] ?? '—') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">RG</span>
                    <span class="info-value"><?= htmlspecialchars($student['rg'] ?? '—') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Data de Nascimento</span>
                    <span class="info-value"><?= !empty($student['birth_date']) ? date('d/m/Y', strtotime($student['birth_date'])) : '—' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Telefone</span>
                    <span class="info-value"><?= htmlspecialchars($student['phone'] ?? '—') ?></span>
                </div>
                <?php if (!empty($student['email'])): ?>
                <div class="info-item full-width">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($student['email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($student['address'])): ?>
                <div class="info-item full-width">
                    <span class="info-label">Endereço</span>
                    <span class="info-value">
                        <?= htmlspecialchars($student['address']) ?>
                        <?php if (!empty($student['city_name'])): ?>
                            - <?= htmlspecialchars($student['city_name']) ?>/<?= htmlspecialchars($student['state_uf']) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dados da Matrícula -->
        <div class="section">
            <div class="section-title">Dados da Matrícula</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Número da Matrícula</span>
                    <span class="info-value"><?= str_pad($enrollment['id'], 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Data da Matrícula</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($enrollment['created_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value"><?= formatEnrollmentStatus($enrollment['status']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Situação Financeira</span>
                    <span class="info-value"><?= formatFinancialStatus($enrollment['financial_status']) ?></span>
                </div>
                <?php if (!empty($enrollment['created_by_name'])): ?>
                <div class="info-item">
                    <span class="info-label">Responsável</span>
                    <span class="info-value"><?= htmlspecialchars($enrollment['created_by_name']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Serviço Contratado -->
        <div class="section">
            <div class="section-title">Serviço Contratado</div>
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($service['name'] ?? 'Serviço') ?></td>
                        <td class="text-right">R$ <?= number_format($enrollment['final_price'], 2, ',', '.') ?></td>
                    </tr>
                    <?php if (!empty($service['description'])): ?>
                    <tr>
                        <td colspan="2" style="font-size: 9pt; color: #666;">
                            <?= nl2br(htmlspecialchars($service['description'])) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Informações Financeiras -->
        <div class="section">
            <div class="section-title">Informações Financeiras</div>
            <div class="payment-summary">
                <div class="payment-summary-item total">
                    <span>Valor Total do Contrato:</span>
                    <span>R$ <?= number_format($enrollment['final_price'], 2, ',', '.') ?></span>
                </div>
                
                <?php if (!empty($paymentDetails['entry_amount']) && $paymentDetails['entry_amount'] > 0): ?>
                <div class="payment-summary-item">
                    <span>Entrada Paga (<?= formatPaymentMethod($paymentDetails['entry_payment_method']) ?>):</span>
                    <span>R$ <?= number_format($paymentDetails['entry_amount'], 2, ',', '.') ?></span>
                </div>
                <?php if (!empty($paymentDetails['entry_payment_date'])): ?>
                <div class="payment-summary-item" style="font-size: 9pt; color: #666;">
                    <span>Data do Pagamento:</span>
                    <span><?= date('d/m/Y', strtotime($paymentDetails['entry_payment_date'])) ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($paymentDetails['outstanding_amount']) && $paymentDetails['outstanding_amount'] > 0): ?>
                <div class="payment-summary-item">
                    <span>Saldo Devedor:</span>
                    <span>R$ <?= number_format($paymentDetails['outstanding_amount'], 2, ',', '.') ?></span>
                </div>
                <?php endif; ?>
                
                <div class="payment-summary-item">
                    <span>Forma de Pagamento:</span>
                    <span><?= formatPaymentMethod($enrollment['payment_method']) ?></span>
                </div>
                
                <?php if (!empty($paymentDetails['installments']) && $paymentDetails['installments'] > 1): ?>
                <div class="payment-summary-item">
                    <span>Parcelas:</span>
                    <span><?= $paymentDetails['installments'] ?>x</span>
                </div>
                <?php if (!empty($paymentDetails['first_due_date'])): ?>
                <div class="payment-summary-item">
                    <span>Vencimento 1ª Parcela:</span>
                    <span><?= date('d/m/Y', strtotime($paymentDetails['first_due_date'])) ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assinaturas -->
        <div class="signatures">
            <div class="signature-block">
                <div class="signature-line">
                    <?= htmlspecialchars($student['full_name'] ?? $student['name']) ?>
                </div>
                <div class="signature-label">Aluno(a)</div>
            </div>
            <div class="signature-block">
                <div class="signature-line">
                    <?= htmlspecialchars($cfc['nome'] ?? 'CFC') ?>
                </div>
                <div class="signature-label">Autoescola</div>
            </div>
        </div>

        <!-- Rodapé -->
        <div class="footer">
            <p>Documento gerado em <?= date('d/m/Y H:i:s') ?></p>
            <p><?= htmlspecialchars($cfc['nome'] ?? 'CFC') ?> - CNPJ: <?= htmlspecialchars($cfc['cnpj'] ?? '—') ?></p>
        </div>
    </div>
</body>
</html>
