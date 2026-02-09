<?php

namespace App\Controllers;

use App\Models\Service;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Config\Constants;

class ServicosController extends Controller
{
    private $cfcId;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;

        // Apenas ADMIN pode gerenciar serviços (SECRETARIA não)
        if (($_SESSION['current_role'] ?? '') !== Constants::ROLE_ADMIN) {
            $_SESSION['error'] = 'Acesso restrito ao administrador.';
            redirect(base_url('dashboard'));
        }
    }

    public function index()
    {
        $serviceModel = new Service();
        $services = $serviceModel->findByCfc($this->cfcId);

        $data = [
            'pageTitle' => 'Serviços',
            'services' => $services
        ];
        $this->view('servicos/index', $data);
    }

    public function novo()
    {
        if (!PermissionService::check('servicos', 'create')) {
            $_SESSION['error'] = 'Você não tem permissão para criar serviços.';
            redirect(base_url('servicos'));
        }

        $data = [
            'pageTitle' => 'Novo Serviço',
            'service' => null
        ];
        $this->view('servicos/form', $data);
    }

    public function criar()
    {
        if (!PermissionService::check('servicos', 'create')) {
            $_SESSION['error'] = 'Você não tem permissão para criar serviços.';
            redirect(base_url('servicos'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('servicos/novo'));
        }

        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $basePrice = floatval($_POST['base_price'] ?? 0);
        $paymentMethods = $_POST['payment_methods'] ?? [];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($category)) {
            $_SESSION['error'] = 'Nome e categoria são obrigatórios.';
            redirect(base_url('servicos/novo'));
        }

        $serviceModel = new Service();
        $auditService = new AuditService();

        $data = [
            'cfc_id' => $this->cfcId,
            'name' => $name,
            'category' => $category,
            'base_price' => $basePrice,
            'payment_methods_json' => json_encode($paymentMethods),
            'is_active' => $isActive
        ];

        $id = $serviceModel->create($data);
        
        $auditService->logCreate('servicos', $id, $data);

        $_SESSION['success'] = 'Serviço criado com sucesso!';
        redirect(base_url('servicos'));
    }

    public function editar($id)
    {
        if (!PermissionService::check('servicos', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para editar serviços.';
            redirect(base_url('servicos'));
        }

        $serviceModel = new Service();
        $service = $serviceModel->find($id);

        if (!$service || $service['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Serviço não encontrado.';
            redirect(base_url('servicos'));
        }

        $data = [
            'pageTitle' => 'Editar Serviço',
            'service' => $service
        ];
        $this->view('servicos/form', $data);
    }

    public function atualizar($id)
    {
        if (!PermissionService::check('servicos', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para editar serviços.';
            redirect(base_url('servicos'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("servicos/{$id}/editar"));
        }

        $serviceModel = new Service();
        $service = $serviceModel->find($id);

        if (!$service || $service['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Serviço não encontrado.';
            redirect(base_url('servicos'));
        }

        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $basePrice = floatval($_POST['base_price'] ?? 0);
        $paymentMethods = $_POST['payment_methods'] ?? [];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($category)) {
            $_SESSION['error'] = 'Nome e categoria são obrigatórios.';
            redirect(base_url("servicos/{$id}/editar"));
        }

        $auditService = new AuditService();
        $dataBefore = $service;

        $data = [
            'name' => $name,
            'category' => $category,
            'base_price' => $basePrice,
            'payment_methods_json' => json_encode($paymentMethods),
            'is_active' => $isActive
        ];

        $serviceModel->update($id, $data);
        
        $dataAfter = array_merge($service, $data);
        $auditService->logUpdate('servicos', $id, $dataBefore, $dataAfter);

        $_SESSION['success'] = 'Serviço atualizado com sucesso!';
        redirect(base_url('servicos'));
    }

    public function toggle($id)
    {
        if (!PermissionService::check('servicos', 'toggle')) {
            $_SESSION['error'] = 'Você não tem permissão para alterar status de serviços.';
            redirect(base_url('servicos'));
        }

        $serviceModel = new Service();
        $service = $serviceModel->find($id);

        if (!$service || $service['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Serviço não encontrado.';
            redirect(base_url('servicos'));
        }

        $auditService = new AuditService();
        $dataBefore = $service;

        $serviceModel->toggleActive($id);
        
        $serviceAfter = $serviceModel->find($id);
        $auditService->logToggle('servicos', $id, $dataBefore, $serviceAfter);

        $_SESSION['success'] = 'Status do serviço alterado com sucesso!';
        redirect(base_url('servicos'));
    }
}
