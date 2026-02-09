<?php

namespace App\Controllers;

use App\Models\Vehicle;
use App\Services\AuditService;
use App\Config\Constants;

class VeiculosController extends Controller
{
    private $cfcId;
    private $auditService;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->auditService = new AuditService();

        // Apenas ADMIN pode gerenciar veículos (SECRETARIA não)
        if (($_SESSION['current_role'] ?? '') !== Constants::ROLE_ADMIN) {
            error_log('[BLOQUEIO] VeiculosController negado: role=' . ($_SESSION['current_role'] ?? '') . ', user_id=' . ($_SESSION['user_id'] ?? ''));
            $_SESSION['error'] = 'Você não tem permissão.';
            redirect(base_url('dashboard'));
        }
    }

    public function index()
    {
        $vehicleModel = new Vehicle();
        $vehicles = $vehicleModel->findByCfc($this->cfcId, false); // Todos, incluindo inativos
        
        $data = [
            'pageTitle' => 'Veículos',
            'vehicles' => $vehicles
        ];
        $this->view('veiculos/index', $data);
    }

    public function novo()
    {
        $data = [
            'pageTitle' => 'Novo Veículo'
        ];
        $this->view('veiculos/form', $data);
    }

    public function criar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('veiculos'));
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('veiculos'));
        }
        
        $plate = strtoupper(trim($_POST['plate'] ?? ''));
        $category = trim($_POST['category'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($plate)) {
            $_SESSION['error'] = 'Placa é obrigatória.';
            redirect(base_url('veiculos/novo'));
        }
        
        if (empty($category)) {
            $_SESSION['error'] = 'Categoria é obrigatória.';
            redirect(base_url('veiculos/novo'));
        }
        
        // Verificar se placa já existe
        $vehicleModel = new Vehicle();
        $existing = $vehicleModel->findByPlate($this->cfcId, $plate);
        if ($existing) {
            $_SESSION['error'] = 'Já existe um veículo cadastrado com esta placa.';
            redirect(base_url('veiculos/novo'));
        }
        
        $data = [
            'cfc_id' => $this->cfcId,
            'plate' => $plate,
            'category' => $category,
            'brand' => !empty($_POST['brand']) ? trim($_POST['brand']) : null,
            'model' => !empty($_POST['model']) ? trim($_POST['model']) : null,
            'year' => !empty($_POST['year']) ? (int)$_POST['year'] : null,
            'color' => !empty($_POST['color']) ? trim($_POST['color']) : null,
            'is_active' => $isActive
        ];
        
        $vehicleId = $vehicleModel->create($data);
        
        $this->auditService->logCreate('veiculos', $vehicleId, $data);
        
        $_SESSION['success'] = 'Veículo cadastrado com sucesso!';
        redirect(base_url('veiculos'));
    }

    public function editar($id)
    {
        $vehicleModel = new Vehicle();
        $vehicle = $vehicleModel->find($id);
        
        if (!$vehicle || $vehicle['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Veículo não encontrado.';
            redirect(base_url('veiculos'));
        }
        
        $data = [
            'pageTitle' => 'Editar Veículo',
            'vehicle' => $vehicle
        ];
        $this->view('veiculos/form', $data);
    }

    public function atualizar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('veiculos'));
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('veiculos'));
        }
        
        $vehicleModel = new Vehicle();
        $vehicle = $vehicleModel->find($id);
        
        if (!$vehicle || $vehicle['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Veículo não encontrado.';
            redirect(base_url('veiculos'));
        }
        
        $plate = strtoupper(trim($_POST['plate'] ?? ''));
        $category = trim($_POST['category'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($plate)) {
            $_SESSION['error'] = 'Placa é obrigatória.';
            redirect(base_url('veiculos/' . $id . '/editar'));
        }
        
        if (empty($category)) {
            $_SESSION['error'] = 'Categoria é obrigatória.';
            redirect(base_url('veiculos/' . $id . '/editar'));
        }
        
        // Verificar se placa já existe em outro veículo
        $existing = $vehicleModel->findByPlate($this->cfcId, $plate);
        if ($existing && $existing['id'] != $id) {
            $_SESSION['error'] = 'Já existe um veículo cadastrado com esta placa.';
            redirect(base_url('veiculos/' . $id . '/editar'));
        }
        
        $dataBefore = $vehicle;
        $updateData = [
            'plate' => $plate,
            'category' => $category,
            'brand' => !empty($_POST['brand']) ? trim($_POST['brand']) : null,
            'model' => !empty($_POST['model']) ? trim($_POST['model']) : null,
            'year' => !empty($_POST['year']) ? (int)$_POST['year'] : null,
            'color' => !empty($_POST['color']) ? trim($_POST['color']) : null,
            'is_active' => $isActive
        ];
        
        $vehicleModel->update($id, $updateData);
        
        $this->auditService->logUpdate('veiculos', $id, $dataBefore, array_merge($vehicle, $updateData));

        $_SESSION['success'] = 'Veículo atualizado com sucesso!';
        redirect(base_url('veiculos'));
    }

    /**
     * Excluir veículo e todos os dados relacionados
     */
    public function excluir($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('veiculos'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('veiculos'));
        }

        $vehicleModel = new Vehicle();
        $vehicle = $vehicleModel->find($id);

        if (!$vehicle || $vehicle['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Veículo não encontrado.';
            redirect(base_url('veiculos'));
        }

        // Salvar dados para auditoria
        $dataBefore = $vehicle;

        try {
            // 1. Deletar todas as aulas (lessons) relacionadas
            // Como vehicle_id é NOT NULL, precisamos deletar as aulas ou atribuir outro veículo
            // Vamos deletar as aulas para manter consistência
            $lessonModel = new \App\Models\Lesson();
            $lessons = $this->query(
                "SELECT id FROM lessons WHERE vehicle_id = ?",
                [$id]
            )->fetchAll();
            
            foreach ($lessons as $lesson) {
                $lessonModel->delete($lesson['id']);
            }

            // 2. Registrar auditoria antes de deletar
            $this->auditService->logDelete('veiculos', $id, $dataBefore);

            // 3. Deletar o veículo
            $vehicleModel->delete($id);

            $_SESSION['success'] = 'Veículo e todos os dados relacionados foram excluídos com sucesso!';
        } catch (\Exception $e) {
            error_log("Erro ao excluir veículo: " . $e->getMessage());
            $_SESSION['error'] = 'Erro ao excluir veículo: ' . $e->getMessage();
        }

        redirect(base_url('veiculos'));
    }

    /**
     * Método auxiliar para executar queries diretas
     */
    private function query($sql, $params = [])
    {
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
