<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\Constants;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Service;

/**
 * Controller para geração de contrato/recibo de matrícula em PDF
 * Acesso restrito a ADMIN e SECRETARIA
 */
class EnrollmentContractController extends Controller
{
    private $cfcId;
    private $db;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Gera contrato/recibo de matrícula para impressão/PDF
     */
    public function generate($enrollmentId)
    {
        // Verificar permissões RBAC
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;
        if (!in_array($role, ['ADMIN', 'SECRETARIA'], true)) {
            http_response_code(403);
            echo 'Acesso negado. Apenas administradores e secretárias podem gerar contratos de matrícula.';
            return;
        }

        try {
            // Buscar dados da matrícula
            $enrollment = $this->getEnrollmentData($enrollmentId);
            
            if (!$enrollment) {
                http_response_code(404);
                echo 'Matrícula não encontrada.';
                return;
            }

            // Validar que a matrícula pertence ao CFC
            if ($enrollment['cfc_id'] != $this->cfcId) {
                http_response_code(403);
                echo 'Acesso negado. Esta matrícula não pertence ao seu CFC.';
                return;
            }

            // Buscar dados do aluno
            $student = $this->getStudentData($enrollment['student_id']);

            // Buscar dados do CFC
            $cfc = $this->getCfcData($this->cfcId);

            // Buscar dados do serviço
            $service = $this->getServiceData($enrollment['service_id']);

            // Buscar dados de pagamento adicional
            $paymentDetails = $this->getPaymentDetails($enrollmentId);

            // Preparar dados para a view
            $data = [
                'pageTitle' => 'Contrato de Matrícula',
                'enrollment' => $enrollment,
                'student' => $student,
                'cfc' => $cfc,
                'service' => $service,
                'paymentDetails' => $paymentDetails,
                'printMode' => true
            ];

            $this->view('enrollment/contract', $data);

        } catch (\Exception $e) {
            error_log("Erro ao gerar contrato de matrícula: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo "Erro ao gerar contrato. Por favor, tente novamente mais tarde.<br>";
            echo "Detalhes do erro: " . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Busca dados completos da matrícula
     */
    private function getEnrollmentData($enrollmentId)
    {
        $stmt = $this->db->prepare(
            "SELECT e.*,
                    u.nome as created_by_name
             FROM enrollments e
             LEFT JOIN usuarios u ON e.created_by_user_id = u.id
             WHERE e.id = ?
             LIMIT 1"
        );
        $stmt->execute([$enrollmentId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados do aluno
     */
    private function getStudentData($studentId)
    {
        $stmt = $this->db->prepare(
            "SELECT s.*,
                    c.name as city_name,
                    st.uf as state_uf,
                    bc.name as birth_city_name,
                    bst.uf as birth_state_uf
             FROM students s
             LEFT JOIN cities c ON s.city_id = c.id
             LEFT JOIN states st ON c.state_id = st.id
             LEFT JOIN cities bc ON s.birth_city_id = bc.id
             LEFT JOIN states bst ON bc.state_id = bst.id
             WHERE s.id = ?
             LIMIT 1"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados do CFC
     */
    private function getCfcData($cfcId)
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM cfcs
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$cfcId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados do serviço
     */
    private function getServiceData($serviceId)
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM services
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$serviceId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca detalhes de pagamento (entrada, parcelas, etc)
     */
    private function getPaymentDetails($enrollmentId)
    {
        $stmt = $this->db->prepare(
            "SELECT 
                entry_amount,
                entry_payment_method,
                entry_payment_date,
                outstanding_amount,
                installments,
                down_payment_amount,
                down_payment_due_date,
                first_due_date,
                billing_status,
                gateway_provider,
                gateway_last_status
             FROM enrollments
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$enrollmentId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
