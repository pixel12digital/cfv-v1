<?php

namespace App\Controllers;

use App\Models\Enrollment;
use App\Services\EfiPaymentService;
use App\Services\PermissionService;
use App\Config\Constants;

class PaymentsController extends Controller
{
    private $efiService;
    private $enrollmentModel;

    public function __construct()
    {
        $this->efiService = new EfiPaymentService();
        $this->enrollmentModel = new Enrollment();
    }

    /**
     * POST /api/payments/generate
     * Gera cobrança na Efí para uma matrícula
     */
    public function generate()
    {
        // Sempre retornar JSON, mesmo em erro
        try {
            // Definir header JSON ANTES de qualquer saída
            header('Content-Type: application/json; charset=utf-8');

            // Verificar autenticação
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'message' => 'Você precisa fazer login para continuar'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar permissão (admin ou secretaria)
            $currentRole = $_SESSION['current_role'] ?? '';
            if (!in_array($currentRole, [Constants::ROLE_ADMIN, Constants::ROLE_SECRETARIA])) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Você não tem permissão para realizar esta ação'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Validar método
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'message' => 'Operação não permitida'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Obter enrollment_id
            $input = json_decode(file_get_contents('php://input'), true);
            $enrollmentId = $input['enrollment_id'] ?? $_POST['enrollment_id'] ?? null;

            if (!$enrollmentId) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'É necessário informar o ID da matrícula'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Buscar matrícula com detalhes
            $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
            if (!$enrollment) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Matrícula não encontrada'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar se matrícula pertence ao CFC do usuário
            $cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
            if ($enrollment['cfc_id'] != $cfcId) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Acesso negado a esta matrícula'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Validar saldo devedor antes de gerar
            $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?? 0);
            if ($outstandingAmount <= 0) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Não é possível gerar cobrança. Esta matrícula não possui saldo devedor.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar idempotência: se já existe cobrança ativa, retornar dados existentes
            if (!empty($enrollment['gateway_charge_id']) && 
                $enrollment['billing_status'] === 'generated' &&
                !in_array($enrollment['gateway_last_status'] ?? '', ['canceled', 'expired', 'error'])) {
                
                http_response_code(200);
                echo json_encode([
                    'ok' => true,
                    'charge_id' => $enrollment['gateway_charge_id'],
                    'status' => $enrollment['gateway_last_status'],
                    'payment_url' => $enrollment['gateway_payment_url'] ?? null,
                    'pix_code' => $enrollment['gateway_pix_code'] ?? null,
                    'barcode' => $enrollment['gateway_barcode'] ?? null,
                    'message' => 'Esta cobrança já foi gerada anteriormente'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Gerar cobrança
            $result = $this->efiService->createCharge($enrollment);

            if (!$result['ok']) {
                http_response_code(400);
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            
        } catch (\Throwable $e) {
            // Capturar qualquer erro (Exception, Error, etc)
            http_response_code(500);
            
            // Log com prefixo PAYMENTS-ERROR
            $logFile = __DIR__ . '/../../storage/logs/php_errors.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = sprintf(
                "[%s] PAYMENTS-ERROR: PaymentsController::generate() - %s in %s:%d\nStack trace:\n%s\n",
                $timestamp,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            
            // Garantir que header JSON foi enviado
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            
            echo json_encode([
                'ok' => false,
                'message' => 'Ocorreu um erro ao gerar a cobrança. Por favor, tente novamente.',
                'details' => [
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }

    /**
     * POST /api/payments/webhook/efi
     * Recebe webhook da Efí (público, mas com validação de assinatura)
     */
    public function webhookEfi()
    {
        header('Content-Type: application/json');

        // Validar método
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Operação não permitida']);
            exit;
        }

        // Obter payload
        $payload = json_decode(file_get_contents('php://input'), true);
        
        if (!$payload) {
            // Tentar obter de POST se não vier em JSON
            $payload = $_POST;
        }

        if (empty($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Dados não recebidos. Por favor, tente novamente.']);
            exit;
        }

        // Processar webhook
        $result = $this->efiService->parseWebhook($payload);

        // Sempre retornar 200 para evitar retry infinito
        http_response_code(200);
        echo json_encode($result);
        exit;
    }

    /**
     * POST /api/payments/sync
     * Sincroniza status de cobrança EFI manualmente
     */
    public function sync()
    {
        header('Content-Type: application/json');

        // Verificar autenticação
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Você precisa fazer login para continuar']);
            exit;
        }

        // Verificar permissão (admin ou secretaria)
        $currentRole = $_SESSION['current_role'] ?? '';
        if (!in_array($currentRole, [Constants::ROLE_ADMIN, Constants::ROLE_SECRETARIA])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Você não tem permissão para realizar esta ação']);
            exit;
        }

        // Validar método
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Operação não permitida']);
            exit;
        }

        // Obter enrollment_id
        $input = json_decode(file_get_contents('php://input'), true);
        $enrollmentId = $input['enrollment_id'] ?? $_POST['enrollment_id'] ?? null;

        if (!$enrollmentId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'É necessário informar o ID da matrícula']);
            exit;
        }

        // Buscar matrícula com detalhes
        $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
        if (!$enrollment) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Matrícula não encontrada']);
            exit;
        }

        // Verificar se matrícula pertence ao CFC do usuário
        $cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        if ($enrollment['cfc_id'] != $cfcId) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Acesso negado a esta matrícula']);
            exit;
        }

        // Verificar se existe cobrança gerada
        if (empty($enrollment['gateway_charge_id'])) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Esta matrícula ainda não possui cobrança gerada. Por favor, gere uma cobrança primeiro.'
            ]);
            exit;
        }

        // Sincronizar cobrança
        try {
            $result = $this->efiService->syncCharge($enrollment);
            
            if (!$result['ok']) {
                http_response_code(502); // Bad Gateway - problema ao consultar EFI
            }
            
            echo json_encode($result);
        } catch (\Exception $e) {
            // Log erro sem expor detalhes sensíveis
            error_log(sprintf(
                "EFI Sync Error: enrollment_id=%d, error=%s",
                $enrollmentId,
                $e->getMessage()
            ));
            
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Não foi possível sincronizar a cobrança. Por favor, tente novamente mais tarde.'
            ]);
        }
        
        exit;
    }

    /**
     * POST /api/payments/sync-pendings
     * Sincroniza cobranças pendentes em lote (somente página atual)
     */
    public function syncPendings()
    {
        header('Content-Type: application/json');

        // Verificar autenticação
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Você precisa fazer login para continuar']);
            exit;
        }

        // Verificar permissão (admin ou secretaria)
        $currentRole = $_SESSION['current_role'] ?? '';
        if (!in_array($currentRole, [Constants::ROLE_ADMIN, Constants::ROLE_SECRETARIA])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Você não tem permissão para realizar esta ação']);
            exit;
        }

        // Validar método
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Operação não permitida']);
            exit;
        }

        // Obter parâmetros
        $input = json_decode(file_get_contents('php://input'), true);
        $page = max(1, intval($input['page'] ?? $_POST['page'] ?? 1));
        $perPage = min(20, max(1, intval($input['per_page'] ?? $_POST['per_page'] ?? 10))); // Máximo 20
        $search = trim($input['search'] ?? $_POST['search'] ?? '');

        $cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $db = \App\Config\Database::getInstance()->getConnection();

        // Verificar se coluna gateway_charge_id existe (migration 030)
        $columnCheck = $db->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'enrollments' 
            AND COLUMN_NAME = 'gateway_charge_id'
        ");
        $columnCheck->execute();
        $hasColumn = $columnCheck->fetch()['count'] > 0;

        if (!$hasColumn) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Colunas do gateway não encontradas. Execute a migration 030 primeiro.'
            ]);
            exit;
        }

        // Verificar se coluna outstanding_amount existe
        $columnCheck = $db->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'enrollments' 
            AND COLUMN_NAME = 'outstanding_amount'
        ");
        $columnCheck->execute();
        $hasOutstandingAmount = $columnCheck->fetch()['count'] > 0;

        // Buscar matrículas com saldo devedor E cobrança gerada (mesma query da listagem)
        $offset = ($page - 1) * $perPage;
        
        // Base da query: matrículas com saldo devedor
        if ($hasOutstandingAmount) {
            $whereClause = "AND (e.outstanding_amount > 0 OR (e.outstanding_amount IS NULL AND e.final_price > COALESCE(e.entry_amount, 0)))";
        } else {
            $whereClause = "AND (e.final_price > COALESCE(e.entry_amount, 0))";
        }
        
        // Verificar se colunas do gateway existem
        $columnCheck = $db->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'enrollments' 
            AND COLUMN_NAME = 'gateway_charge_id'
        ");
        $columnCheck->execute();
        $hasGatewayColumns = $columnCheck->fetch()['count'] > 0;

        if (!$hasGatewayColumns) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Colunas do gateway não encontradas. Execute a migration 030 primeiro.'
            ]);
            exit;
        }
        
        $sql = "SELECT e.*, 
                       s.name as student_name, 
                       s.full_name as student_full_name, 
                       s.cpf as student_cpf,
                       sv.name as service_name
                FROM enrollments e
                INNER JOIN students s ON s.id = e.student_id
                INNER JOIN services sv ON sv.id = e.service_id
                WHERE e.cfc_id = ?
                AND e.status != 'cancelada'
                {$whereClause}
                AND e.gateway_charge_id IS NOT NULL
                AND e.gateway_charge_id != ''";
        
        $params = [$cfcId];
        
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $sql .= " AND (s.name LIKE ? OR s.full_name LIKE ? OR s.cpf LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY 
                    CASE 
                        WHEN COALESCE(
                            NULLIF(e.first_due_date, '0000-00-00'),
                            NULLIF(e.down_payment_due_date, '0000-00-00'),
                            '9999-12-31'
                        ) < CURDATE() THEN 0
                        ELSE 1
                    END ASC,
                    COALESCE(
                        NULLIF(e.first_due_date, '0000-00-00'),
                        NULLIF(e.down_payment_due_date, '0000-00-00'),
                        DATE(e.created_at)
                    ) ASC,
                    e.id ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $enrollments = $stmt->fetchAll();

        // Sincronizar cada matrícula
        $synced = 0;
        $errors = [];
        $items = [];

        foreach ($enrollments as $enrollment) {
            // Buscar matrícula com detalhes completos
            $enrollmentFull = $this->enrollmentModel->findWithDetails($enrollment['id']);
            if (!$enrollmentFull) {
                $errors[] = [
                    'enrollment_id' => $enrollment['id'],
                    'reason' => 'Matrícula não encontrada'
                ];
                continue;
            }

            // Verificar se tem gateway_charge_id
            if (empty($enrollmentFull['gateway_charge_id'])) {
                $errors[] = [
                    'enrollment_id' => $enrollment['id'],
                    'reason' => 'Nenhuma cobrança gerada'
                ];
                continue;
            }

            // Sincronizar (com timeout)
            try {
                $result = $this->efiService->syncCharge($enrollmentFull);
                
                if ($result['ok']) {
                    $synced++;
                    $items[] = [
                        'enrollment_id' => $enrollment['id'],
                        'charge_id' => $result['charge_id'],
                        'status' => $result['status'],
                        'billing_status' => $result['billing_status'],
                        'financial_status' => $result['financial_status']
                    ];
                } else {
                    $errors[] = [
                        'enrollment_id' => $enrollment['id'],
                        'reason' => $result['message'] ?? 'Erro desconhecido'
                    ];
                }
            } catch (\Exception $e) {
                // Log erro sem dados sensíveis
                error_log(sprintf(
                    "EFI Sync Pendings Error: enrollment_id=%d, error=%s",
                    $enrollment['id'],
                    $e->getMessage()
                ));
                
                $errors[] = [
                    'enrollment_id' => $enrollment['id'],
                    'reason' => 'Não foi possível sincronizar: ' . $e->getMessage()
                ];
            }
        }

        // Contar total (mesma query sem LIMIT e sem filtro de gateway_charge_id)
        $countSql = "SELECT COUNT(*) as total
                     FROM enrollments e
                     INNER JOIN students s ON s.id = e.student_id
                     WHERE e.cfc_id = ?
                     AND e.status != 'cancelada'
                     {$whereClause}";
        
        $countParams = [$cfcId];
        
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countSql .= " AND (s.name LIKE ? OR s.full_name LIKE ? OR s.cpf LIKE ?)";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }
        
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetch()['total'];

        echo json_encode([
            'ok' => true,
            'total' => $total,
            'synced' => $synced,
            'errors' => $errors,
            'items' => $items
        ]);
        exit;
    }

    /**
     * GET /api/payments/status
     * Retorna status e detalhes da cobrança (suporta Carnê e cobrança única)
     * 
     * Parâmetros:
     * - enrollment_id (obrigatório)
     * - refresh (opcional): se true, consulta Efí antes de retornar
     */
    public function status()
    {
        header('Content-Type: application/json; charset=utf-8');

        // Verificar autenticação
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Você precisa fazer login para continuar'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar permissão (admin ou secretaria)
        $currentRole = $_SESSION['current_role'] ?? '';
        if (!in_array($currentRole, [Constants::ROLE_ADMIN, Constants::ROLE_SECRETARIA])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Você não tem permissão para realizar esta ação'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validar método
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Operação não permitida'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Obter enrollment_id
        $enrollmentId = $_GET['enrollment_id'] ?? null;
        $refresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

        if (!$enrollmentId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'É necessário informar o ID da matrícula'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Buscar matrícula com detalhes
        $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
        if (!$enrollment) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Matrícula não encontrada'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar se matrícula pertence ao CFC do usuário
        $cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        if ($enrollment['cfc_id'] != $cfcId) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Acesso negado a esta matrícula'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar se existe cobrança gerada
        if (empty($enrollment['gateway_charge_id'])) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Esta matrícula ainda não possui cobrança gerada.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Se refresh=true, sincronizar com Efí antes de retornar
        if ($refresh) {
            try {
                $syncResult = $this->efiService->syncCharge($enrollment);
                if ($syncResult['ok']) {
                    // Recarregar matrícula após sincronização
                    $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
                }
            } catch (\Exception $e) {
                // Log erro mas continua com dados do banco
                error_log(sprintf(
                    "EFI Status Refresh Error: enrollment_id=%d, error=%s",
                    $enrollmentId,
                    $e->getMessage()
                ));
            }
        }

        // Decodificar gateway_payment_url (JSON)
        $paymentData = null;
        if (!empty($enrollment['gateway_payment_url'])) {
            $paymentData = json_decode($enrollment['gateway_payment_url'], true);
        }

        // Determinar tipo de cobrança
        $type = 'charge'; // padrão
        if ($paymentData && isset($paymentData['type']) && $paymentData['type'] === 'carne') {
            $type = 'carne';
        }

        // Montar resposta padronizada
        if ($type === 'carne' && $paymentData) {
            // Resposta para Carnê
            $charges = [];
            if (isset($paymentData['charges']) && is_array($paymentData['charges'])) {
                foreach ($paymentData['charges'] as $charge) {
                    $charges[] = [
                        'charge_id' => $charge['charge_id'] ?? null,
                        'expire_at' => $charge['expire_at'] ?? null,
                        'status' => $charge['status'] ?? 'waiting',
                        'billet_link' => $charge['billet_link'] ?? null
                    ];
                }
            }

        echo json_encode([
            'ok' => true,
            'type' => 'carne',
            'carnet_id' => $paymentData['carnet_id'] ?? $enrollment['gateway_charge_id'],
            'status' => $paymentData['status'] ?? $enrollment['gateway_last_status'] ?? 'waiting',
            'cover' => $paymentData['cover'] ?? null,
            'download_link' => $paymentData['download_link'] ?? null,
            'charges' => $charges
        ], JSON_UNESCAPED_UNICODE);
        } else {
            // Resposta para cobrança única
            echo json_encode([
                'ok' => true,
                'type' => 'charge',
                'charge_id' => $enrollment['gateway_charge_id'],
                'status' => $enrollment['gateway_last_status'] ?? 'waiting',
                'payment_url' => $enrollment['gateway_payment_url'] ?? null
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /**
     * POST /api/payments/cancel
     * Cancela uma cobrança (Carnê ou cobrança única)
     * 
     * Parâmetros:
     * - enrollment_id (obrigatório)
     */
    public function cancel()
    {
        header('Content-Type: application/json; charset=utf-8');

        // Verificar autenticação
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Você precisa fazer login para continuar'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar permissão (admin ou secretaria)
        $currentRole = $_SESSION['current_role'] ?? '';
        if (!in_array($currentRole, [Constants::ROLE_ADMIN, Constants::ROLE_SECRETARIA])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Você não tem permissão para realizar esta ação'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validar método
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Operação não permitida'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Obter enrollment_id
        $input = json_decode(file_get_contents('php://input'), true);
        $enrollmentId = $input['enrollment_id'] ?? $_POST['enrollment_id'] ?? null;

        if (!$enrollmentId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'É necessário informar o ID da matrícula'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Buscar matrícula com detalhes
        $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
        if (!$enrollment) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Matrícula não encontrada'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar se matrícula pertence ao CFC do usuário
        $cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        if ($enrollment['cfc_id'] != $cfcId) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Acesso negado a esta matrícula'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verificar se existe cobrança gerada
        if (empty($enrollment['gateway_charge_id'])) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Esta matrícula ainda não possui cobrança gerada.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Determinar tipo de cobrança
        $paymentData = null;
        if (!empty($enrollment['gateway_payment_url'])) {
            $paymentData = json_decode($enrollment['gateway_payment_url'], true);
        }
        $isCarnet = $paymentData && isset($paymentData['type']) && $paymentData['type'] === 'carne';

        // Cancelar cobrança
        try {
            if ($isCarnet) {
                $result = $this->efiService->cancelCarnet($enrollment);
            } else {
                // Para cobrança única, ainda não implementado
                // Por enquanto, apenas atualizar status local
                http_response_code(501);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Cancelamento de cobrança única ainda não implementado. Use apenas para Carnê.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (!$result['ok']) {
                http_response_code(400);
            }
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log(sprintf(
                "EFI Cancel Error: enrollment_id=%d, error=%s",
                $enrollmentId,
                $e->getMessage()
            ));
            
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Não foi possível cancelar a cobrança: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * POST /api/payments/mark-paid
     * Marca pagamento como pago (baixa manual, sem gateway)
     * Usado apenas para payment_method = 'cartao' (pagamento na maquininha local)
     */
    public function markPaid()
    {
        // Sempre retornar JSON, mesmo em erro
        try {
            // Definir header JSON ANTES de qualquer saída
            header('Content-Type: application/json; charset=utf-8');

            // Verificar autenticação
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'message' => 'Você precisa fazer login para continuar'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar permissão (admin ou secretaria)
            $currentRole = $_SESSION['current_role'] ?? '';
            if (!in_array($currentRole, [Constants::ROLE_ADMIN, Constants::ROLE_SECRETARIA])) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Você não tem permissão para realizar esta ação'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Validar método
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'message' => 'Operação não permitida'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Obter dados do POST
            $input = json_decode(file_get_contents('php://input'), true);
            $enrollmentId = $input['enrollment_id'] ?? null;
            $paymentMethod = $input['payment_method'] ?? null;
            $installments = isset($input['installments']) ? intval($input['installments']) : null;
            $confirmAmount = isset($input['confirm_amount']) ? floatval($input['confirm_amount']) : null;

            // Validações obrigatórias
            if (!$enrollmentId) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'É necessário informar o ID da matrícula'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($paymentMethod !== 'cartao') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Baixa manual só é permitida para cartão de crédito'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (!$installments || $installments < 1 || $installments > 24) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Número de parcelas deve ser entre 1 e 24 para cartão'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Buscar matrícula com detalhes
            $enrollment = $this->enrollmentModel->findWithDetails($enrollmentId);
            if (!$enrollment) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Matrícula não encontrada'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar se matrícula pertence ao CFC do usuário
            $cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
            if ($enrollment['cfc_id'] != $cfcId) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Acesso negado a esta matrícula'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Validar que payment_method da matrícula é cartão
            if ($enrollment['payment_method'] !== 'cartao') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Esta matrícula não está configurada para pagamento com cartão'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Sanity check: validar valor se fornecido
            $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? $enrollment['final_price'] ?? 0);
            if ($confirmAmount !== null && abs($confirmAmount - $outstandingAmount) > 0.01) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Valor informado não confere com o saldo devedor atual. Recarregue a página e tente novamente.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar se já está pago
            if ($outstandingAmount <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Esta matrícula já está quitada'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Obter conexão do banco
            $db = \App\Config\Database::getInstance()->getConnection();
            
            // Iniciar transação para garantir consistência
            $db->beginTransaction();
            
            try {
                // Calcular entry_amount necessário para zerar outstanding_amount
                // outstanding_amount = final_price - entry_amount
                // Para zerar: entry_amount = final_price
                $finalPrice = floatval($enrollment['final_price']);
                
                // Atualizar matrícula de forma consistente
                // CRÍTICO: Ajustar entry_amount para evitar recálculo de outstanding_amount
                $stmt = $db->prepare("
                    UPDATE enrollments 
                    SET 
                        payment_method = 'cartao',
                        installments = ?,
                        outstanding_amount = 0,
                        entry_amount = ?,
                        financial_status = 'em_dia',
                        billing_status = 'generated',
                        gateway_provider = 'local',
                        gateway_last_status = 'paid',
                        gateway_last_event_at = NOW(),
                        gateway_charge_id = NULL,
                        gateway_payment_url = NULL,
                        gateway_pix_code = NULL,
                        gateway_barcode = NULL
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $installments,
                    $finalPrice, // entry_amount = final_price para evitar recálculo
                    $enrollmentId
                ]);
                
                // Commit transação
                $db->commit();
                
                // Log de sucesso
                error_log(sprintf(
                    "PAYMENTS-MARK-PAID: enrollment_id=%d, installments=%d, amount=%.2f",
                    $enrollmentId,
                    $installments,
                    $outstandingAmount
                ));
                
                http_response_code(200);
                echo json_encode([
                    'ok' => true,
                    'message' => 'Pagamento confirmado com sucesso',
                    'enrollment_id' => $enrollmentId,
                    'outstanding_amount' => 0,
                    'financial_status' => 'em_dia'
                ], JSON_UNESCAPED_UNICODE);
                
            } catch (\Exception $e) {
                // Rollback em caso de erro
                $db->rollBack();
                throw $e;
            }
            
        } catch (\Throwable $e) {
            // Capturar qualquer erro (Exception, Error, etc)
            http_response_code(500);
            
            // Log com prefixo PAYMENTS-ERROR
            $logFile = __DIR__ . '/../../storage/logs/php_errors.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = sprintf(
                "[%s] PAYMENTS-ERROR: PaymentsController::markPaid() - %s in %s:%d\nStack trace:\n%s\n",
                $timestamp,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            
            // Garantir que header JSON foi enviado
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            
            echo json_encode([
                'ok' => false,
                'message' => 'Ocorreu um erro ao confirmar o pagamento. Por favor, tente novamente.',
                'details' => [
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
}
