<?php

namespace App\Controllers;

use App\Models\Student;
use App\Models\Service;
use App\Models\Enrollment;
use App\Models\Step;
use App\Models\StudentStep;
use App\Models\State;
use App\Models\City;
use App\Models\StudentHistory;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\EnrollmentPolicy;
use App\Services\StudentHistoryService;
use App\Services\UserCreationService;
use App\Services\EmailService;
use App\Config\Constants;
use App\Helpers\ValidationHelper;

class AlunosController extends Controller
{
    private $cfcId;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        
        if (!PermissionService::check('alunos', 'view')) {
            $_SESSION['error'] = 'Você não tem permissão para acessar este módulo.';
            redirect(base_url('dashboard'));
        }
    }

    public function index()
    {
        $search = $_GET['q'] ?? '';
        $studentModel = new Student();
        $students = $studentModel->findByCfc($this->cfcId, $search);

        $data = [
            'pageTitle' => 'Alunos',
            'students' => $students,
            'search' => $search
        ];
        $this->view('alunos/index', $data);
    }

    public function novo()
    {
        if (!PermissionService::check('alunos', 'create')) {
            $_SESSION['error'] = 'Você não tem permissão para criar alunos.';
            redirect(base_url('alunos'));
        }

        $stateModel = new State();
        $states = $stateModel->findAll();

        $data = [
            'pageTitle' => 'Novo Aluno',
            'student' => null,
            'states' => $states,
            'currentCity' => null,
            'currentBirthCity' => null
        ];
        $this->view('alunos/form', $data);
    }

    public function criar()
    {
        if (!PermissionService::check('alunos', 'create')) {
            $_SESSION['error'] = 'Você não tem permissão para criar alunos.';
            redirect(base_url('alunos'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('alunos/novo'));
        }

        // Validações e processamento de dados
        $errors = $this->validateStudentData($_POST, null);
        if (!empty($errors)) {
            $_SESSION['error'] = implode(' ', $errors);
            redirect(base_url('alunos/novo'));
        }

        $data = $this->prepareStudentData($_POST);
        
        $studentModel = new Student();
        
        // Verificar CPF único
        $existing = $studentModel->findByCpf($this->cfcId, $data['cpf']);
        if ($existing) {
            $_SESSION['error'] = 'Já existe um aluno cadastrado com este CPF.';
            redirect(base_url('alunos/novo'));
        }

        $auditService = new AuditService();
        $historyService = new StudentHistoryService();

        $id = $studentModel->create($data);
        
        $auditService->logCreate('alunos', $id, $data);
        
        // Registrar no histórico do aluno
        $fullName = trim($_POST['full_name'] ?? '');
        $historyService->logStudentCreated($id, $fullName ?: 'Aluno');

        // Criar usuário automaticamente se houver e-mail
        $email = trim($_POST['email'] ?? '');
        
        // Log para diagnóstico
        error_log("[ALUNO_CRIAR] Aluno ID {$id} criado. Email recebido: " . ($email ?: '(vazio)'));
        
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("[ALUNO_CRIAR] Tentando criar usuário para aluno ID {$id} com email: {$email}");
            
            try {
                $userService = new UserCreationService();
                $userData = $userService->createForStudent($id, $email, $fullName ?: null);
                
                error_log("[ALUNO_CRIAR] Usuário criado com sucesso! User ID: {$userData['user_id']}, Email: {$email}");
                
                // Tentar enviar e-mail com credenciais (não bloqueia se falhar)
                try {
                    $emailService = new EmailService();
                    $loginUrl = base_url('/login');
                    $emailService->sendAccessCreated($email, $userData['temp_password'], $loginUrl);
                    error_log("[ALUNO_CRIAR] E-mail de acesso enviado com sucesso para: {$email}");
                } catch (\Exception $e) {
                    // Log mas não bloqueia
                    error_log("[ALUNO_CRIAR] Erro ao enviar e-mail de acesso para {$email}: " . $e->getMessage());
                }
                
                $_SESSION['success'] = 'Aluno criado com sucesso! Acesso ao sistema criado automaticamente.';
            } catch (\Exception $e) {
                // Log detalhado do erro
                error_log("[ALUNO_CRIAR] ERRO ao criar acesso para aluno ID {$id}: " . $e->getMessage());
                error_log("[ALUNO_CRIAR] Stack trace: " . $e->getTraceAsString());
                error_log("[ALUNO_CRIAR] Dados: Email={$email}, Nome={$fullName}");
                
                // Se falhar, apenas logar mas não bloquear criação do aluno
                $_SESSION['success'] = 'Aluno criado com sucesso!';
                $_SESSION['warning'] = 'Não foi possível criar acesso automático: ' . $e->getMessage();
            }
        } else {
            error_log("[ALUNO_CRIAR] Email não informado ou inválido para aluno ID {$id}. Email recebido: " . ($email ?: '(vazio)'));
            $_SESSION['success'] = 'Aluno criado com sucesso! (Acesso não criado: e-mail não informado ou inválido)';
        }

        redirect(base_url("alunos/{$id}"));
    }

    public function show($id)
    {
        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('alunos'));
        }

        $enrollments = $studentModel->getEnrollments($id);
        $tab = $_GET['tab'] ?? 'dados';

        $fullName = $studentModel->getFullName($student);

        // Carregar cidades para exibição
        $cityModel = new City();
        $addressCity = null;
        $birthCity = null;
        
        if (!empty($student['city_id'])) {
            $addressCity = $cityModel->findById($student['city_id']);
        }
        
        if (!empty($student['birth_city_id'])) {
            $birthCity = $cityModel->findById($student['birth_city_id']);
        }

        // Buscar informações do acesso vinculado (se houver)
        $userInfo = null;
        $userRoles = [];
        if (!empty($student['user_id'])) {
            $db = \App\Config\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$student['user_id']]);
            $userInfo = $stmt->fetch();
            if ($userInfo) {
                $userRoles = \App\Models\User::getUserRoles($student['user_id']);
            }
        }
        
        $data = [
            'pageTitle' => 'Aluno: ' . $fullName,
            'student' => $student,
            'enrollments' => $enrollments,
            'tab' => $tab,
            'addressCity' => $addressCity,
            'birthCity' => $birthCity,
            'userInfo' => $userInfo,
            'userRoles' => $userRoles
        ];

        if ($tab === 'progresso' && !empty($enrollments)) {
            $enrollmentId = $_GET['enrollment_id'] ?? $enrollments[0]['id'];
            $stepModel = new Step();
            $studentStepModel = new StudentStep();
            
            $steps = $stepModel->findAllActive();
            $studentSteps = $studentStepModel->findByEnrollment($enrollmentId);
            
            $data['enrollment_id'] = $enrollmentId;
            $data['steps'] = $steps;
            $data['student_steps'] = $studentSteps;
        }

        if ($tab === 'historico') {
            $historyModel = new StudentHistory();
            $history = $historyModel->findByStudent($id);
            
            // Agrupar eventos similares próximos (mesmo tipo, mesma data)
            $groupedHistory = $this->groupSimilarHistoryEvents($history);
            $data['history'] = $groupedHistory;
        }

        $this->view('alunos/show', $data);
    }

    public function editar($id)
    {
        if (!PermissionService::check('alunos', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para editar alunos.';
            redirect(base_url('alunos'));
        }

        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('alunos'));
        }

        $stateModel = new State();
        $states = $stateModel->findAll();

        // Carregar cidade atual do endereço se houver city_id
        $currentCity = null;
        if (!empty($student['city_id'])) {
            $cityModel = new City();
            $currentCity = $cityModel->findById($student['city_id']);
        }

        // Carregar cidade de nascimento se houver birth_city_id
        $currentBirthCity = null;
        if (!empty($student['birth_city_id'])) {
            $cityModel = new City();
            $currentBirthCity = $cityModel->findById($student['birth_city_id']);
        }

        $data = [
            'pageTitle' => 'Editar Aluno',
            'student' => $student,
            'states' => $states,
            'currentCity' => $currentCity,
            'currentBirthCity' => $currentBirthCity
        ];
        $this->view('alunos/form', $data);
    }

    public function atualizar($id)
    {
        if (!PermissionService::check('alunos', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para editar alunos.';
            redirect(base_url('alunos'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("alunos/{$id}/editar"));
        }

        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('alunos'));
        }

        // Validações
        $errors = $this->validateStudentData($_POST, $id);
        if (!empty($errors)) {
            $_SESSION['error'] = implode(' ', $errors);
            redirect(base_url("alunos/{$id}/editar"));
        }

        // Verificar CPF único (exceto o próprio aluno)
        $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
        if ($cpf !== $student['cpf']) {
            $existing = $studentModel->findByCpf($this->cfcId, $cpf);
            if ($existing) {
                $_SESSION['error'] = 'Já existe um aluno cadastrado com este CPF.';
                redirect(base_url("alunos/{$id}/editar"));
            }
        }

        $auditService = new AuditService();
        $historyService = new StudentHistoryService();
        $dataBefore = $student;

        $data = $this->prepareStudentData($_POST);

        // Identificar mudanças relevantes para o histórico
        $changes = [];
        $relevantFields = ['full_name', 'cpf', 'birth_date', 'phone_primary', 'email'];
        foreach ($relevantFields as $field) {
            $oldValue = $dataBefore[$field] ?? null;
            $newValue = $data[$field] ?? null;
            if ($oldValue != $newValue) {
                $changes[$field] = $newValue;
            }
        }
        
        // Verificar mudança de endereço
        $addressChanged = false;
        $addressFields = ['cep', 'street', 'number', 'neighborhood', 'city_id', 'state_uf'];
        foreach ($addressFields as $field) {
            $oldValue = $dataBefore[$field] ?? null;
            $newValue = $data[$field] ?? null;
            if ($oldValue != $newValue) {
                $addressChanged = true;
                break;
            }
        }
        if ($addressChanged) {
            $changes['address'] = true;
        }

        $studentModel->update($id, $data);
        
        $dataAfter = array_merge($student, $data);
        $auditService->logUpdate('alunos', $id, $dataBefore, $dataAfter);
        
        // Registrar no histórico do aluno
        if (!empty($changes)) {
            $historyService->logStudentUpdated($id, $changes);
        }

        $_SESSION['success'] = 'Aluno atualizado com sucesso!';
        redirect(base_url("alunos/{$id}"));
    }

    public function matricular($id)
    {
        if (!PermissionService::check('enrollments', 'create')) {
            $_SESSION['error'] = 'Você não tem permissão para criar matrículas.';
            redirect(base_url("alunos/{$id}"));
        }

        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('alunos'));
        }

        $serviceModel = new Service();
        $services = $serviceModel->findActiveByCfc($this->cfcId);

        // Buscar cursos e turmas teóricas para seleção
        $theoryCourseModel = new \App\Models\TheoryCourse();
        $theoryClassModel = new \App\Models\TheoryClass();
        $courses = $theoryCourseModel->findActiveByCfc($this->cfcId);
        $classes = $theoryClassModel->findByCfc($this->cfcId, ['scheduled', 'in_progress']); // Apenas turmas agendadas/em andamento

        // Buscar contas PIX do CFC (com tratamento de erro caso tabela não exista ainda)
        $pixAccounts = [];
        try {
            $pixAccountModel = new \App\Models\CfcPixAccount();
            $pixAccounts = $pixAccountModel->findByCfc($this->cfcId, true); // Apenas ativas
        } catch (\Exception $e) {
            // Se a tabela não existir ainda (migrations não executadas), usar array vazio
            error_log("AlunosController::matricular() - Erro ao buscar contas PIX (tabela pode não existir ainda): " . $e->getMessage());
            $pixAccounts = [];
        }

        $data = [
            'pageTitle' => 'Nova Matrícula',
            'student' => $student,
            'services' => $services,
            'theoryCourses' => $courses,
            'theoryClasses' => $classes,
            'pixAccounts' => $pixAccounts
        ];
        $this->view('alunos/matricular', $data);
    }

    public function criarMatricula($id)
    {
        if (!PermissionService::check('enrollments', 'create')) {
            $_SESSION['error'] = 'Você não tem permissão para criar matrículas.';
            redirect(base_url("alunos/{$id}"));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("alunos/{$id}/matricular"));
        }

        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('alunos'));
        }

        $serviceModel = new Service();
        $service = $serviceModel->find($_POST['service_id'] ?? 0);

        if (!$service || $service['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Serviço inválido.';
            redirect(base_url("alunos/{$id}/matricular"));
        }

        $basePrice = floatval($service['base_price']);
        $discountValue = floatval($_POST['discount_value'] ?? 0);
        $extraValue = floatval($_POST['extra_value'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'pix';

        $enrollmentModel = new Enrollment();
        $finalPrice = $enrollmentModel->calculateFinalPrice($basePrice, $discountValue, $extraValue);

        $auditService = new AuditService();

        // Processar campos de entrada
        $entryAmount = !empty($_POST['entry_amount']) ? floatval($_POST['entry_amount']) : 0;
        $entryPaymentMethod = !empty($_POST['entry_payment_method']) ? $_POST['entry_payment_method'] : null;
        $entryPaymentDate = !empty($_POST['entry_payment_date']) ? $_POST['entry_payment_date'] : null;
        
        // Validações de entrada
        if ($entryAmount > 0) {
            if ($entryAmount < 0) {
                $_SESSION['error'] = 'O valor da entrada não pode ser negativo.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
            
            if ($entryAmount >= $finalPrice) {
                $_SESSION['error'] = 'O valor da entrada deve ser menor que o valor final da matrícula.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
            
            if (!$entryPaymentMethod) {
                $_SESSION['error'] = 'Se houver entrada, a forma de pagamento da entrada é obrigatória.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
            
            if (!$entryPaymentDate) {
                $_SESSION['error'] = 'Se houver entrada, a data da entrada é obrigatória.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
        } else {
            // Se não há entrada, limpar campos relacionados
            $entryAmount = null;
            $entryPaymentMethod = null;
            $entryPaymentDate = null;
        }
        
        // Calcular saldo devedor
        $outstandingAmount = $entryAmount > 0 ? max(0, $finalPrice - $entryAmount) : $finalPrice;
        
        // Tratamento especial para Cartão: se pagamento foi confirmado no popup
        $cartaoPaidConfirmed = !empty($_POST['cartao_paid_confirmed']) && $_POST['cartao_paid_confirmed'] === '1';
        if ($paymentMethod === 'cartao' && $cartaoPaidConfirmed) {
            // Cartão pago localmente: zerar saldo devedor e marcar como em_dia
            $outstandingAmount = 0;
            $financialStatus = 'em_dia';
        } else {
            // Recalcular financial_status baseado em outstanding_amount (coerência)
            // Se outstanding_amount > 0, deve ser 'pendente' (padrão inicial)
            $financialStatus = $outstandingAmount > 0 ? 'pendente' : 'em_dia';
        }

        // Processar campos de parcelamento
        $installments = null;
        $downPaymentAmount = null;
        $downPaymentDueDate = null;
        $firstDueDate = null;

        // Validações de parcelamento conforme método de pagamento
        if (in_array($paymentMethod, ['boleto', 'pix', 'cartao', 'entrada_parcelas'])) {
            $installments = !empty($_POST['installments']) ? intval($_POST['installments']) : null;
            
            // Validação dinâmica de parcelas conforme método de pagamento
            $maxInstallments = ($paymentMethod === 'cartao') ? 24 : 12;
            if (!$installments || $installments < 1 || $installments > $maxInstallments) {
                $_SESSION['error'] = "Número de parcelas deve ser entre 1 e {$maxInstallments}.";
                redirect(base_url("alunos/{$id}/matricular"));
            }
        }

        // Validações específicas por método
        if (in_array($paymentMethod, ['boleto', 'pix'])) {
            $firstDueDate = !empty($_POST['first_due_date']) ? $_POST['first_due_date'] : null;
            if (!$firstDueDate) {
                $_SESSION['error'] = 'Data de vencimento da primeira parcela é obrigatória.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
        } elseif ($paymentMethod === 'entrada_parcelas') {
            $downPaymentAmount = !empty($_POST['down_payment_amount']) ? floatval($_POST['down_payment_amount']) : null;
            $downPaymentDueDate = !empty($_POST['down_payment_due_date']) ? $_POST['down_payment_due_date'] : null;
            $firstDueDate = !empty($_POST['first_due_date']) ? $_POST['first_due_date'] : null;
            
            if (!$downPaymentAmount || $downPaymentAmount <= 0) {
                $_SESSION['error'] = 'Valor da entrada é obrigatório e deve ser maior que zero.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
            
            if ($downPaymentAmount >= $finalPrice) {
                $_SESSION['error'] = 'O valor da entrada deve ser menor que o valor final da matrícula.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
            
            if (!$downPaymentDueDate) {
                $_SESSION['error'] = 'Data de vencimento da entrada é obrigatória.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
            
            if (!$firstDueDate) {
                $_SESSION['error'] = 'Data de vencimento da primeira parcela restante é obrigatória.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
        }

        // Processar campos DETRAN (opcionais)
        $renach = !empty($_POST['renach']) ? trim($_POST['renach']) : null;
        $detranProtocolo = !empty($_POST['detran_protocolo']) ? trim($_POST['detran_protocolo']) : null;
        $numeroProcesso = !empty($_POST['numero_processo']) ? trim($_POST['numero_processo']) : null;
        $situacaoProcesso = !empty($_POST['situacao_processo']) ? $_POST['situacao_processo'] : 'nao_iniciado';

        // Validações básicas dos campos DETRAN
        if ($renach && strlen($renach) > 20) {
            $_SESSION['error'] = 'RENACH deve ter no máximo 20 caracteres.';
            redirect(base_url("alunos/{$id}/matricular"));
        }
        if ($detranProtocolo && strlen($detranProtocolo) > 50) {
            $_SESSION['error'] = 'Protocolo DETRAN deve ter no máximo 50 caracteres.';
            redirect(base_url("alunos/{$id}/matricular"));
        }
        if ($numeroProcesso && strlen($numeroProcesso) > 50) {
            $_SESSION['error'] = 'Número do Processo deve ter no máximo 50 caracteres.';
            redirect(base_url("alunos/{$id}/matricular"));
        }

        // Processar conta PIX selecionada (se payment_method = 'pix')
        $pixAccountId = null;
        if ($paymentMethod === 'pix') {
            try {
                $pixAccountId = !empty($_POST['pix_account_id']) ? intval($_POST['pix_account_id']) : null;
                
                // Validar se conta existe e pertence ao CFC
                if ($pixAccountId) {
                    $pixAccountModel = new \App\Models\CfcPixAccount();
                    $pixAccount = $pixAccountModel->findByIdAndCfc($pixAccountId, $this->cfcId);
                    if (!$pixAccount || !$pixAccount['is_active']) {
                        $_SESSION['error'] = 'Conta PIX selecionada não é válida ou está inativa.';
                        redirect(base_url("alunos/{$id}/matricular"));
                    }
                } else {
                    // Se não selecionou conta mas tem contas disponíveis, usar padrão
                    $pixAccountModel = new \App\Models\CfcPixAccount();
                    $defaultAccount = $pixAccountModel->findDefaultByCfc($this->cfcId);
                    if ($defaultAccount) {
                        $pixAccountId = $defaultAccount['id'];
                    }
                }
            } catch (\Exception $e) {
                // Se a tabela não existir ainda, continuar sem pix_account_id (retrocompatibilidade)
                error_log("AlunosController::criarMatricula() - Erro ao processar conta PIX (tabela pode não existir ainda): " . $e->getMessage());
                $pixAccountId = null;
            }
        }

        $enrollmentData = [
            'cfc_id' => $this->cfcId,
            'student_id' => $id,
            'service_id' => $service['id'],
            'base_price' => $basePrice,
            'discount_value' => $discountValue,
            'extra_value' => $extraValue,
            'final_price' => $finalPrice,
            'payment_method' => $paymentMethod,
            'financial_status' => $financialStatus, // Calculado baseado em outstanding_amount
            'status' => 'ativa',
            'created_by_user_id' => $_SESSION['user_id'] ?? null,
            'renach' => $renach,
            'detran_protocolo' => $detranProtocolo,
            'numero_processo' => $numeroProcesso,
            'situacao_processo' => $situacaoProcesso,
            // Campos de entrada
            'entry_amount' => $entryAmount,
            'entry_payment_method' => $entryPaymentMethod,
            'entry_payment_date' => $entryPaymentDate,
            'outstanding_amount' => $outstandingAmount,
            // Campos de parcelamento
            'installments' => $installments,
            'down_payment_amount' => $downPaymentAmount,
            'down_payment_due_date' => $downPaymentDueDate,
            'first_due_date' => $firstDueDate,
            'billing_status' => ($paymentMethod === 'cartao' && $cartaoPaidConfirmed) ? 'generated' : 'draft', // Se cartão pago, já está gerado (pago localmente)
            'theory_course_id' => $theoryCourseId,
            'theory_class_id' => $theoryClassId,
            // Campos específicos para cartão pago localmente
            'gateway_provider' => ($paymentMethod === 'cartao' && $cartaoPaidConfirmed) ? 'local' : null,
            'gateway_last_status' => ($paymentMethod === 'cartao' && $cartaoPaidConfirmed) ? 'paid' : null,
            'gateway_last_event_at' => ($paymentMethod === 'cartao' && $cartaoPaidConfirmed) ? date('Y-m-d H:i:s') : null,
            // Conta PIX selecionada
            'pix_account_id' => $pixAccountId
        ];

        // Validar curso/turma teórica se informado
        if ($theoryClassId) {
            $theoryClassModel = new \App\Models\TheoryClass();
            $theoryClass = $theoryClassModel->find($theoryClassId);
            if (!$theoryClass || $theoryClass['cfc_id'] != $this->cfcId || !in_array($theoryClass['status'], ['scheduled', 'in_progress'])) {
                $_SESSION['error'] = 'Turma teórica inválida ou não disponível.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
        }
        
        if ($theoryCourseId) {
            $theoryCourseModel = new \App\Models\TheoryCourse();
            $theoryCourse = $theoryCourseModel->find($theoryCourseId);
            if (!$theoryCourse || $theoryCourse['cfc_id'] != $this->cfcId || !$theoryCourse['active']) {
                $_SESSION['error'] = 'Curso teórico inválido ou inativo.';
                redirect(base_url("alunos/{$id}/matricular"));
            }
        }

        $db = \App\Config\Database::getInstance()->getConnection();
        $db->beginTransaction();
        
        try {
            $enrollmentId = $enrollmentModel->create($enrollmentData);
            $auditService->logCreate('enrollments', $enrollmentId, $enrollmentData);
        
        // Registrar no histórico do aluno
        $historyService = new StudentHistoryService();
        $historyService->logEnrollmentCreated($id, $enrollmentId, $service['name']);
        
        // Registrar evento financeiro se houver entrada
        if ($entryAmount > 0) {
            $historyService->logFinancialEvent($id, "Entrada registrada: R$ " . number_format($entryAmount, 2, ',', '.') . " ({$entryPaymentMethod})");
        }
        
        // Registrar parcelamento se definido
        if ($installments && $installments > 1) {
            $historyService->logFinancialEvent($id, "Parcelamento definido: {$installments}x");
        }
        
        // Registrar RENACH se informado
        if ($renach) {
            $historyService->logRenachInformed($id, $renach);
        }
        
        // Registrar situação do processo DETRAN se diferente de não iniciado
        if ($situacaoProcesso !== 'nao_iniciado') {
            $historyService->logDetranProcessStatusChanged($id, 'nao_iniciado', $situacaoProcesso);
        }

        // Criar etapas padrão
        $stepModel = new Step();
        $steps = $stepModel->findAllActive();
        $studentStepModel = new StudentStep();

        foreach ($steps as $step) {
            $studentStepData = [
                'enrollment_id' => $enrollmentId,
                'step_id' => $step['id'],
                'status' => $step['code'] === 'MATRICULA' ? 'concluida' : 'pendente',
                'source' => $step['code'] === 'MATRICULA' ? 'cfc' : null,
                'validated_by_user_id' => $step['code'] === 'MATRICULA' ? ($_SESSION['user_id'] ?? null) : null,
                'validated_at' => $step['code'] === 'MATRICULA' ? date('Y-m-d H:i:s') : null
            ];
            $studentStepModel->create($studentStepData);
        }

        // Se turma teórica selecionada, criar theory_enrollment (idempotente)
        if ($theoryClassId) {
            $theoryEnrollmentModel = new \App\Models\TheoryEnrollment();
            
            // Verificar se já existe (idempotência)
            if (!$theoryEnrollmentModel->isEnrolled($theoryClassId, $id)) {
                $theoryEnrollmentData = [
                    'class_id' => $theoryClassId,
                    'student_id' => $id,
                    'enrollment_id' => $enrollmentId,
                    'status' => 'active',
                    'created_by' => $_SESSION['user_id'] ?? null
                ];
                $theoryEnrollmentModel->create($theoryEnrollmentData);
            }
        }

            $db->commit();
            
            $_SESSION['success'] = 'Matrícula criada com sucesso!';
            redirect(base_url("alunos/{$id}?tab=matricula"));
        } catch (\Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Erro ao criar matrícula: ' . $e->getMessage();
            redirect(base_url("alunos/{$id}/matricular"));
        }
    }

    public function showMatricula($id)
    {
        $enrollmentModel = new Enrollment();
        $enrollment = $enrollmentModel->findWithDetails($id);

        if (!$enrollment || $enrollment['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Matrícula não encontrada.';
            redirect(base_url('alunos'));
        }

        // Buscar dados do CFC (incluindo configurações PIX)
        $cfcModel = new \App\Models\Cfc();
        $cfc = $cfcModel->getCurrent();

        // Buscar conta PIX usada na matrícula (se houver)
        $pixAccountModel = new \App\Models\CfcPixAccount();
        $pixAccount = null;
        $pixAccountSnapshot = null;
        $pixAccounts = []; // Lista de contas PIX disponíveis para seleção
        
        try {
            if (!empty($enrollment['pix_account_id'])) {
                $pixAccount = $pixAccountModel->findByIdAndCfc($enrollment['pix_account_id'], $this->cfcId);
            }
            
            // Se não encontrou conta mas tem snapshot, usar snapshot
            if (!$pixAccount && !empty($enrollment['pix_account_snapshot'])) {
                $pixAccountSnapshot = json_decode($enrollment['pix_account_snapshot'], true);
            }
            
            // Se ainda não tem nada, buscar padrão (fallback)
            if (!$pixAccount && !$pixAccountSnapshot) {
                $pixAccount = $pixAccountModel->getPixDataForCfc($this->cfcId);
            }
            
            // Carregar todas as contas PIX ativas para o seletor
            $pixAccounts = $pixAccountModel->findByCfc($this->cfcId, true); // true = apenas ativas
        } catch (\Exception $e) {
            // Se a tabela não existir ainda, usar dados antigos do CFC (retrocompatibilidade)
            error_log("AlunosController::showMatricula() - Erro ao buscar conta PIX (tabela pode não existir ainda): " . $e->getMessage());
            // Tentar usar snapshot se existir
            if (!empty($enrollment['pix_account_snapshot'])) {
                $pixAccountSnapshot = json_decode($enrollment['pix_account_snapshot'], true);
            }
            $pixAccounts = []; // Array vazio se tabela não existir
        }

        $data = [
            'pageTitle' => 'Matrícula #' . $id,
            'enrollment' => $enrollment,
            'cfc' => $cfc,
            'pixAccount' => $pixAccount,
            'pixAccountSnapshot' => $pixAccountSnapshot,
            'pixAccounts' => $pixAccounts // Contas disponíveis para seleção
        ];
        $this->view('alunos/matricula_show', $data);
    }

    public function atualizarMatricula($id)
    {
        if (!PermissionService::check('enrollments', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para editar matrículas.';
            redirect(base_url("matriculas/{$id}"));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("matriculas/{$id}"));
        }

        $enrollmentModel = new Enrollment();
        $enrollment = $enrollmentModel->find($id);

        if (!$enrollment || $enrollment['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Matrícula não encontrada.';
            redirect(base_url('alunos'));
        }

        $basePrice = floatval($enrollment['base_price']);
        $discountValue = floatval($_POST['discount_value'] ?? 0);
        $extraValue = floatval($_POST['extra_value'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'pix';
        $financialStatus = $_POST['financial_status'] ?? 'em_dia';
        $status = $_POST['status'] ?? 'ativa';
        
        // Processar conta PIX selecionada (se payment_method = 'pix')
        $pixAccountId = null;
        if ($paymentMethod === 'pix') {
            $pixAccountId = !empty($_POST['pix_account_id']) ? intval($_POST['pix_account_id']) : null;
            
            // Validar se a conta PIX existe e pertence ao CFC
            if ($pixAccountId) {
                try {
                    $pixAccountModel = new \App\Models\CfcPixAccount();
                    $pixAccount = $pixAccountModel->findByIdAndCfc($pixAccountId, $this->cfcId);
                    
                    if (!$pixAccount || !$pixAccount['is_active']) {
                        $_SESSION['error'] = 'Conta PIX selecionada não encontrada ou inativa.';
                        redirect(base_url("matriculas/{$id}"));
                    }
                } catch (\Exception $e) {
                    error_log("AlunosController::atualizarMatricula() - Erro ao validar conta PIX: " . $e->getMessage());
                    // Se a tabela não existir, permitir continuar sem pix_account_id (retrocompatibilidade)
                }
            }
        }

        // Processar campos de entrada
        $entryAmount = !empty($_POST['entry_amount']) ? floatval($_POST['entry_amount']) : 0;
        $entryPaymentMethod = !empty($_POST['entry_payment_method']) ? $_POST['entry_payment_method'] : null;
        $entryPaymentDate = !empty($_POST['entry_payment_date']) ? $_POST['entry_payment_date'] : null;
        
        // Recalcular valor final
        $finalPrice = $enrollmentModel->calculateFinalPrice($basePrice, $discountValue, $extraValue);
        
        // Validações de entrada
        if ($entryAmount > 0) {
            if ($entryAmount < 0) {
                $_SESSION['error'] = 'O valor da entrada não pode ser negativo.';
                redirect(base_url("matriculas/{$id}"));
            }
            
            if ($entryAmount >= $finalPrice) {
                $_SESSION['error'] = 'O valor da entrada deve ser menor que o valor final da matrícula.';
                redirect(base_url("matriculas/{$id}"));
            }
            
            if (!$entryPaymentMethod) {
                $_SESSION['error'] = 'Se houver entrada, a forma de pagamento da entrada é obrigatória.';
                redirect(base_url("matriculas/{$id}"));
            }
            
            if (!$entryPaymentDate) {
                $_SESSION['error'] = 'Se houver entrada, a data da entrada é obrigatória.';
                redirect(base_url("matriculas/{$id}"));
            }
        } else {
            // Se não há entrada, limpar campos relacionados
            $entryAmount = null;
            $entryPaymentMethod = null;
            $entryPaymentDate = null;
        }
        
        // Calcular saldo devedor
        // IMPORTANTE: Não recalcular se pagamento foi confirmado localmente (gateway_provider='local')
        // Isso evita regressão de saldo após markPaid()
        $gatewayProvider = $enrollment['gateway_provider'] ?? '';
        $isLocalPaid = ($gatewayProvider === 'local' && ($enrollment['gateway_last_status'] ?? '') === 'paid');
        
        if ($isLocalPaid) {
            // Manter outstanding_amount atual (já está zerado pelo markPaid)
            $outstandingAmount = floatval($enrollment['outstanding_amount'] ?? 0);
        } else {
            // Recalcular normalmente
            $outstandingAmount = $entryAmount > 0 ? max(0, $finalPrice - $entryAmount) : $finalPrice;
        }
        
        // Recalcular financial_status baseado em outstanding_amount (coerência)
        // Se outstanding_amount > 0 e não está bloqueado, deve ser 'pendente'
        // Se outstanding_amount = 0, deve ser 'em_dia' (a menos que esteja bloqueado)
        if ($financialStatus !== 'bloqueado') {
            $financialStatus = $outstandingAmount > 0 ? 'pendente' : 'em_dia';
        }

        // Verificar se pode alterar parcelamento (não pode se já gerou cobrança)
        $billingStatus = $enrollment['billing_status'] ?? 'draft';
        $canEditPaymentPlan = ($billingStatus === 'draft' || $billingStatus === 'ready' || $billingStatus === 'error');

        // Processar campos de parcelamento (apenas se não gerou cobrança)
        $installments = null;
        $downPaymentAmount = null;
        $downPaymentDueDate = null;
        $firstDueDate = null;

        if ($canEditPaymentPlan) {
            // Validações de parcelamento conforme método de pagamento
            if (in_array($paymentMethod, ['boleto', 'pix', 'cartao', 'entrada_parcelas'])) {
                // Em edição de matrícula, o formulário atual exibe as parcelas apenas em modo leitura.
                // Portanto, se o campo "installments" não vier no POST, usamos o valor já salvo na matrícula.
                if (isset($_POST['installments']) && $_POST['installments'] !== '') {
                    $installments = intval($_POST['installments']);
                } else {
                    $installments = isset($enrollment['installments']) ? intval($enrollment['installments']) : null;
                }
                
                // Validação dinâmica de parcelas conforme método de pagamento
                $maxInstallments = ($paymentMethod === 'cartao') ? 24 : 12;
                if (!$installments || $installments < 1 || $installments > $maxInstallments) {
                    $_SESSION['error'] = "Número de parcelas deve ser entre 1 e {$maxInstallments}.";
                    redirect(base_url("matriculas/{$id}"));
                }
            }

            // Validações específicas por método
            if (in_array($paymentMethod, ['boleto', 'pix'])) {
                // Se não veio no POST, usar o valor já salvo na matrícula
                if (isset($_POST['first_due_date']) && $_POST['first_due_date'] !== '') {
                    $firstDueDate = $_POST['first_due_date'];
                } else {
                    $firstDueDate = $enrollment['first_due_date'] ?? null;
                }
                
                // Validar como obrigatório apenas se pode editar (não gerou cobrança ainda)
                // Se já gerou cobrança, usa o valor existente (não precisa validar)
                if ($canEditPaymentPlan && !$firstDueDate) {
                    $_SESSION['error'] = 'Data de vencimento da primeira parcela é obrigatória.';
                    redirect(base_url("matriculas/{$id}"));
                }
            } elseif ($paymentMethod === 'entrada_parcelas') {
                $downPaymentAmount = !empty($_POST['down_payment_amount']) ? floatval($_POST['down_payment_amount']) : null;
                $downPaymentDueDate = !empty($_POST['down_payment_due_date']) ? $_POST['down_payment_due_date'] : null;
                $firstDueDate = !empty($_POST['first_due_date']) ? $_POST['first_due_date'] : null;
                
                if (!$downPaymentAmount || $downPaymentAmount <= 0) {
                    $_SESSION['error'] = 'Valor da entrada é obrigatório e deve ser maior que zero.';
                    redirect(base_url("matriculas/{$id}"));
                }
                
                $finalPriceTemp = $enrollmentModel->calculateFinalPrice($basePrice, $discountValue, $extraValue);
                if ($downPaymentAmount >= $finalPriceTemp) {
                    $_SESSION['error'] = 'O valor da entrada deve ser menor que o valor final da matrícula.';
                    redirect(base_url("matriculas/{$id}"));
                }
                
                if (!$downPaymentDueDate) {
                    $_SESSION['error'] = 'Data de vencimento da entrada é obrigatória.';
                    redirect(base_url("matriculas/{$id}"));
                }
                
                if (!$firstDueDate) {
                    $_SESSION['error'] = 'Data de vencimento da primeira parcela restante é obrigatória.';
                    redirect(base_url("matriculas/{$id}"));
                }
            }
        } else {
            // Se já gerou cobrança, manter valores atuais
            $installments = $enrollment['installments'] ?? null;
            $downPaymentAmount = $enrollment['down_payment_amount'] ?? null;
            $downPaymentDueDate = $enrollment['down_payment_due_date'] ?? null;
            $firstDueDate = $enrollment['first_due_date'] ?? null;
        }

        // Processar campos DETRAN (opcionais)
        $renach = !empty($_POST['renach']) ? trim($_POST['renach']) : null;
        $detranProtocolo = !empty($_POST['detran_protocolo']) ? trim($_POST['detran_protocolo']) : null;
        $numeroProcesso = !empty($_POST['numero_processo']) ? trim($_POST['numero_processo']) : null;
        $situacaoProcesso = !empty($_POST['situacao_processo']) ? $_POST['situacao_processo'] : 'nao_iniciado';

        // Validações básicas dos campos DETRAN
        if ($renach && strlen($renach) > 20) {
            $_SESSION['error'] = 'RENACH deve ter no máximo 20 caracteres.';
            redirect(base_url("matriculas/{$id}"));
        }
        if ($detranProtocolo && strlen($detranProtocolo) > 50) {
            $_SESSION['error'] = 'Protocolo DETRAN deve ter no máximo 50 caracteres.';
            redirect(base_url("matriculas/{$id}"));
        }
        if ($numeroProcesso && strlen($numeroProcesso) > 50) {
            $_SESSION['error'] = 'Número do Processo deve ter no máximo 50 caracteres.';
            redirect(base_url("matriculas/{$id}"));
        }

        $auditService = new AuditService();
        $historyService = new StudentHistoryService();
        $dataBefore = $enrollment;
        $studentId = $enrollment['student_id'];

        $data = [
            'discount_value' => $discountValue,
            'extra_value' => $extraValue,
            'final_price' => $finalPrice,
            'payment_method' => $paymentMethod,
            'financial_status' => $financialStatus,
            'status' => $status,
            'renach' => $renach,
            'detran_protocolo' => $detranProtocolo,
            'numero_processo' => $numeroProcesso,
            'situacao_processo' => $situacaoProcesso,
            // Campos de entrada
            'entry_amount' => $entryAmount,
            'entry_payment_method' => $entryPaymentMethod,
            'entry_payment_date' => $entryPaymentDate,
            'outstanding_amount' => $outstandingAmount,
            // Campos de parcelamento (só atualiza se pode editar)
            'installments' => $installments,
            'down_payment_amount' => $downPaymentAmount,
            'down_payment_due_date' => $downPaymentDueDate,
            'first_due_date' => $firstDueDate,
            // Conta PIX selecionada
            'pix_account_id' => $pixAccountId
        ];

        // Registrar mudanças no histórico
        if ($dataBefore['status'] !== $status) {
            $historyService->logEnrollmentStatusChanged($studentId, $id, $dataBefore['status'], $status);
        }
        
        if ($dataBefore['financial_status'] !== $financialStatus) {
            $historyService->logFinancialStatusChanged($studentId, $dataBefore['financial_status'], $financialStatus);
        }
        
        if ($renach && $renach !== ($dataBefore['renach'] ?? null)) {
            $historyService->logRenachInformed($studentId, $renach);
        }
        
        if ($situacaoProcesso !== ($dataBefore['situacao_processo'] ?? 'nao_iniciado')) {
            $historyService->logDetranProcessStatusChanged($studentId, $dataBefore['situacao_processo'] ?? 'nao_iniciado', $situacaoProcesso);
        }
        
        // Registrar entrada se foi adicionada
        if ($entryAmount > 0 && empty($dataBefore['entry_amount'])) {
            $historyService->logFinancialEvent($studentId, "Entrada registrada: R$ " . number_format($entryAmount, 2, ',', '.') . " ({$entryPaymentMethod})");
        }

        $enrollmentModel->update($id, $data);
        
        $dataAfter = array_merge($enrollment, $data);
        $auditService->logUpdate('enrollments', $id, $dataBefore, $dataAfter);

        $_SESSION['success'] = 'Matrícula atualizada com sucesso!';
        redirect(base_url("alunos/{$studentId}?tab=matricula"));
    }

    public function toggleStep($id)
    {
        if (!PermissionService::check('steps', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para atualizar etapas.';
            redirect(base_url('alunos'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('alunos'));
        }

        $studentStepModel = new StudentStep();
        $studentStep = $studentStepModel->find($id);

        if (!$studentStep) {
            $_SESSION['error'] = 'Etapa não encontrada.';
            redirect(base_url('alunos'));
        }

        $enrollmentModel = new Enrollment();
        $enrollment = $enrollmentModel->find($studentStep['enrollment_id']);

        if (!$enrollment || $enrollment['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Matrícula não encontrada.';
            redirect(base_url('alunos'));
        }

        $newStatus = $studentStep['status'] === 'concluida' ? 'pendente' : 'concluida';
        $source = 'cfc';
        $validatedByUserId = $newStatus === 'concluida' ? ($_SESSION['user_id'] ?? null) : null;

        $auditService = new AuditService();
        $historyService = new StudentHistoryService();
        $dataBefore = $studentStep;

        $studentStepModel->toggleStatus($id, $newStatus, $source, $validatedByUserId);
        
        $studentStepAfter = $studentStepModel->find($id);
        $auditService->logUpdate('steps', $id, $dataBefore, $studentStepAfter);
        
        // Registrar no histórico do aluno
        $stepModel = new Step();
        $step = $stepModel->find($studentStep['step_id']);
        if ($step) {
            if ($newStatus === 'concluida') {
                $historyService->logAgendaEvent($enrollment['student_id'], "Etapa concluída: {$step['name']}");
            } else {
                $historyService->logAgendaEvent($enrollment['student_id'], "Etapa desmarcada: {$step['name']}");
            }
        }

        $_SESSION['success'] = 'Etapa atualizada com sucesso!';
        redirect(base_url("alunos/{$enrollment['student_id']}?tab=progresso&enrollment_id={$enrollment['id']}"));
    }

    /**
     * Upload de foto do aluno
     */
    public function uploadFoto($id)
    {
        if (!PermissionService::check('alunos', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para atualizar foto.';
            redirect(base_url("alunos/{$id}"));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("alunos/{$id}"));
        }

        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('alunos'));
        }

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Erro ao enviar arquivo.';
            redirect(base_url("alunos/{$id}"));
        }

        $file = $_FILES['photo'];
        
        // Validar tipo
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $_SESSION['error'] = 'Tipo de arquivo inválido. Use JPG, PNG ou WEBP.';
            redirect(base_url("alunos/{$id}"));
        }

        // Validar tamanho (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['error'] = 'Arquivo muito grande. Máximo 2MB.';
            redirect(base_url("alunos/{$id}"));
        }

        // Criar diretório se não existir
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/students/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Gerar nome único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'student_' . $id . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Remover foto antiga se existir
        if (!empty($student['photo_path'])) {
            $oldPath = dirname(__DIR__, 2) . '/' . $student['photo_path'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $_SESSION['error'] = 'Erro ao salvar arquivo.';
            redirect(base_url("alunos/{$id}"));
        }

        // Atualizar banco
        $relativePath = 'storage/uploads/students/' . $filename;
        $studentModel->update($id, ['photo_path' => $relativePath]);

        // Auditoria
        $auditService = new AuditService();
        $auditService->log('upload_photo', 'alunos', $id, ['old_photo' => $student['photo_path'] ?? null], ['new_photo' => $relativePath]);
        
        // Registrar no histórico do aluno
        $historyService = new StudentHistoryService();
        $historyService->logStudentUpdated($id, ['photo' => true]);

        $_SESSION['success'] = 'Foto atualizada com sucesso!';
        redirect(base_url("alunos/{$id}"));
    }

    /**
     * Remover foto do aluno
     */
    public function removerFoto($id)
    {
        if (!PermissionService::check('alunos', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para remover foto.';
            redirect(base_url("alunos/{$id}"));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("alunos/{$id}"));
        }

        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('alunos'));
        }

        if (!empty($student['photo_path'])) {
            $filepath = dirname(__DIR__, 2) . '/' . $student['photo_path'];
            if (file_exists($filepath)) {
                @unlink($filepath);
            }

            $studentModel->update($id, ['photo_path' => null]);

            // Auditoria
            $auditService = new AuditService();
            $auditService->log('remove_photo', 'alunos', $id, ['old_photo' => $student['photo_path']], null);
            
            // Registrar no histórico do aluno
            $historyService = new StudentHistoryService();
            $historyService->logStudentUpdated($id, ['photo' => true]);

            $_SESSION['success'] = 'Foto removida com sucesso!';
        }

        redirect(base_url("alunos/{$id}"));
    }

    /**
     * Servir foto do aluno (protegido)
     */
    public function foto($id)
    {
        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            http_response_code(404);
            exit('Foto não encontrada');
        }

        if (empty($student['photo_path'])) {
            http_response_code(404);
            exit('Foto não encontrada');
        }

        $filepath = dirname(__DIR__, 2) . '/' . $student['photo_path'];
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            exit('Foto não encontrada');
        }

        // Determinar tipo MIME
        $mimeType = mime_content_type($filepath);
        if (!$mimeType) {
            $extension = pathinfo($filepath, PATHINFO_EXTENSION);
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp'
            ];
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'image/jpeg';
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, max-age=3600');
        readfile($filepath);
        exit;
    }

    /**
     * Valida dados do aluno
     */
    private function validateStudentData($post, $studentId = null)
    {
        $errors = [];

        // Nome completo obrigatório
        $fullName = trim($post['full_name'] ?? '');
        if (empty($fullName)) {
            $errors[] = 'Nome completo é obrigatório.';
        }

        // CPF obrigatório e válido
        $cpf = preg_replace('/[^0-9]/', '', $post['cpf'] ?? '');
        if (empty($cpf)) {
            $errors[] = 'CPF é obrigatório.';
        } elseif (!ValidationHelper::validateCpf($cpf)) {
            $errors[] = 'CPF inválido.';
        }

        // Data de nascimento obrigatória
        $birthDate = trim($post['birth_date'] ?? '');
        if (empty($birthDate)) {
            $errors[] = 'Data de nascimento é obrigatória.';
        } elseif (!ValidationHelper::validateBirthDate($birthDate)) {
            $errors[] = 'Data de nascimento inválida ou idade fora do permitido (16-120 anos).';
        }

        // Telefone principal obrigatório
        $phonePrimary = preg_replace('/[^0-9]/', '', $post['phone_primary'] ?? '');
        if (empty($phonePrimary)) {
            $errors[] = 'Telefone principal é obrigatório.';
        }

        // Email obrigatório e válido (necessário para criar acesso)
        $email = trim($post['email'] ?? '');
        if (empty($email)) {
            $errors[] = 'E-mail é obrigatório (necessário para acesso ao sistema).';
        } elseif (!ValidationHelper::validateEmail($email)) {
            $errors[] = 'E-mail inválido.';
        }
        
        // Verificar se e-mail já está em uso em outro aluno (se editando, excluir o próprio)
        if (!empty($email)) {
            $studentModel = new Student();
            $existingByEmail = $studentModel->findByEmail($this->cfcId, $email);
            if ($existingByEmail && ($studentId === null || $existingByEmail['id'] != $studentId)) {
                $errors[] = 'Este e-mail já está em uso por outro aluno.';
            }
            
            // Verificar se e-mail já está em uso na tabela usuarios
            $existingUser = User::findByEmail($email);
            if ($existingUser) {
                // Se editando, verificar se o usuário vinculado é do próprio aluno
                if ($studentId) {
                    $currentStudent = $studentModel->find($studentId);
                    if (empty($currentStudent) || $currentStudent['user_id'] != $existingUser['id']) {
                        $errors[] = 'Este e-mail já está em uso por outro usuário do sistema.';
                    }
                } else {
                    // Criando novo aluno - e-mail não pode estar em uso
                    $errors[] = 'Este e-mail já está em uso por outro usuário do sistema.';
                }
            }
        }

        // CEP válido se preenchido
        $cep = preg_replace('/[^0-9]/', '', $post['cep'] ?? '');
        if (!empty($cep) && !ValidationHelper::validateCep($cep)) {
            $errors[] = 'CEP inválido.';
        }

        // UF válida se preenchida
        $stateUf = strtoupper(trim($post['state_uf'] ?? ''));
        if (!empty($stateUf) && !ValidationHelper::validateUf($stateUf)) {
            $errors[] = 'UF inválida.';
        }

        // Validar city_id se state_uf estiver preenchido
        if (!empty($stateUf)) {
            $cityId = !empty($post['city_id']) ? (int)$post['city_id'] : null;
            if (empty($cityId)) {
                $errors[] = 'Cidade é obrigatória quando UF está preenchida.';
            } else {
                // Validar se a cidade pertence ao estado selecionado
                $cityModel = new City();
                $city = $cityModel->findByIdAndUf($cityId, $stateUf);
                if (!$city) {
                    $errors[] = 'Cidade selecionada não pertence ao estado informado.';
                }
            }
        }

        // Validar birth_city_id se birth_state_uf estiver preenchido
        $birthStateUf = strtoupper(trim($post['birth_state_uf'] ?? ''));
        if (!empty($birthStateUf)) {
            if (!ValidationHelper::validateUf($birthStateUf)) {
                $errors[] = 'UF de nascimento inválida.';
            } else {
                $birthCityId = !empty($post['birth_city_id']) ? (int)$post['birth_city_id'] : null;
                if (empty($birthCityId)) {
                    $errors[] = 'Cidade de nascimento é obrigatória quando UF de nascimento está preenchida.';
                } else {
                    // Validar se a cidade pertence ao estado selecionado
                    $cityModel = new City();
                    $city = $cityModel->findByIdAndUf($birthCityId, $birthStateUf);
                    if (!$city) {
                        $errors[] = 'Cidade de nascimento selecionada não pertence ao estado informado.';
                    }
                }
            }
        }

        $rgUf = strtoupper(trim($post['rg_uf'] ?? ''));
        if (!empty($rgUf) && !ValidationHelper::validateUf($rgUf)) {
            $errors[] = 'UF do RG inválida.';
        }

        return $errors;
    }

    /**
     * Prepara dados do aluno para inserção/atualização
     */
    private function prepareStudentData($post)
    {
        // Derivar primeiro nome do nome completo
        $fullName = trim($post['full_name'] ?? '');
        $firstName = '';
        if (!empty($fullName)) {
            $nameParts = explode(' ', $fullName);
            $firstName = $nameParts[0] ?? '';
        }
        
        $data = [
            'cfc_id' => $this->cfcId,
            'name' => $firstName,
            'full_name' => $fullName ?: null,
            'cpf' => preg_replace('/[^0-9]/', '', $post['cpf'] ?? ''),
            'birth_date' => !empty($post['birth_date']) ? $post['birth_date'] : null,
            'remunerated_activity' => isset($post['remunerated_activity']) ? 1 : 0,
            'marital_status' => !empty($post['marital_status']) ? trim($post['marital_status']) : null,
            'profession' => !empty($post['profession']) ? trim($post['profession']) : null,
            'education_level' => !empty($post['education_level']) ? trim($post['education_level']) : null,
            'nationality' => !empty($post['nationality']) ? trim($post['nationality']) : null,
            'birth_state_uf' => !empty($post['birth_state_uf']) ? strtoupper(trim($post['birth_state_uf'])) : null,
            'birth_city_id' => !empty($post['birth_city_id']) ? (int)$post['birth_city_id'] : null,
            // 'birth_city' => null, // DEPRECATED: Não usar mais, usar birth_city_id
            'rg_number' => !empty($post['rg_number']) ? trim($post['rg_number']) : null,
            'rg_issuer' => !empty($post['rg_issuer']) ? trim($post['rg_issuer']) : null,
            'rg_uf' => !empty($post['rg_uf']) ? strtoupper(trim($post['rg_uf'])) : null,
            'rg_issue_date' => !empty($post['rg_issue_date']) ? $post['rg_issue_date'] : null,
            'nome_mae' => !empty($post['nome_mae']) ? trim($post['nome_mae']) : null,
            'nome_pai' => !empty($post['nome_pai']) ? trim($post['nome_pai']) : null,
            'phone' => preg_replace('/[^0-9]/', '', $post['phone'] ?? '') ?: null,
            'phone_primary' => preg_replace('/[^0-9]/', '', $post['phone_primary'] ?? '') ?: null,
            'phone_secondary' => preg_replace('/[^0-9]/', '', $post['phone_secondary'] ?? '') ?: null,
            'email' => !empty($post['email']) ? trim($post['email']) : null,
            'emergency_contact_name' => !empty($post['emergency_contact_name']) ? trim($post['emergency_contact_name']) : null,
            'emergency_contact_phone' => preg_replace('/[^0-9]/', '', $post['emergency_contact_phone'] ?? '') ?: null,
            'cep' => preg_replace('/[^0-9]/', '', $post['cep'] ?? '') ?: null,
            'street' => !empty($post['street']) ? trim($post['street']) : null,
            'number' => !empty($post['number']) ? trim($post['number']) : null,
            'complement' => !empty($post['complement']) ? trim($post['complement']) : null,
            'neighborhood' => !empty($post['neighborhood']) ? trim($post['neighborhood']) : null,
            // 'city' => null, // DEPRECATED: Não usar mais, usar city_id
            'state_uf' => !empty($post['state_uf']) ? strtoupper(trim($post['state_uf'])) : null,
            'city_id' => !empty($post['city_id']) ? (int)$post['city_id'] : null,
            'notes' => !empty($post['notes']) ? trim($post['notes']) : null
        ];

        return $data;
    }

    /**
     * Agrupa eventos similares próximos no histórico
     * Se houver múltiplas alterações do mesmo tipo em sequência, agrupa
     */
    private function groupSimilarHistoryEvents($history)
    {
        if (empty($history)) {
            return [];
        }

        $grouped = [];
        $currentGroup = null;
        $groupWindow = 300; // 5 minutos em segundos

        foreach ($history as $item) {
            $itemTime = strtotime($item['created_at']);
            
            // Se é o primeiro item
            if ($currentGroup === null) {
                $currentGroup = [
                    'type' => $item['type'],
                    'items' => [$item],
                    'start_time' => $itemTime,
                    'end_time' => $itemTime
                ];
            } else {
                // Verifica se pode agrupar (mesmo tipo 'cadastro' e dentro da janela de tempo)
                $timeDiff = abs($itemTime - $currentGroup['end_time']);
                $canGroup = ($item['type'] === 'cadastro' && 
                            $currentGroup['type'] === 'cadastro' &&
                            $timeDiff <= $groupWindow);

                if ($canGroup) {
                    $currentGroup['items'][] = $item;
                    $currentGroup['end_time'] = $itemTime;
                } else {
                    // Finaliza grupo atual e inicia novo
                    $grouped[] = $this->formatHistoryGroup($currentGroup);
                    $currentGroup = [
                        'type' => $item['type'],
                        'items' => [$item],
                        'start_time' => $itemTime,
                        'end_time' => $itemTime
                    ];
                }
            }
        }

        // Adiciona último grupo
        if ($currentGroup !== null) {
            $grouped[] = $this->formatHistoryGroup($currentGroup);
        }

        return $grouped;
    }

    /**
     * Formata um grupo de eventos para exibição
     */
    private function formatHistoryGroup($group)
    {
        if (count($group['items']) === 1) {
            // Se só tem um item, retorna como está
            return $group['items'][0];
        }

        // Se tem múltiplos itens do tipo cadastro, agrupa
        if ($group['type'] === 'cadastro' && count($group['items']) > 1) {
            $firstItem = $group['items'][0];
            return [
                'id' => $firstItem['id'],
                'student_id' => $firstItem['student_id'],
                'type' => $group['type'],
                'description' => 'Dados do aluno atualizados',
                'created_by' => $firstItem['created_by'],
                'created_by_name' => $firstItem['created_by_name'] ?? null,
                'created_at' => date('Y-m-d H:i:s', $group['start_time']),
                'is_grouped' => true,
                'group_count' => count($group['items'])
            ];
        }

        // Para outros tipos, retorna o primeiro item
        return $group['items'][0];
    }

    /**
     * Adiciona observação manual ao histórico do aluno
     */
    public function adicionarObservacao($id)
    {
        if (!PermissionService::check('alunos', 'update')) {
            $_SESSION['error'] = 'Você não tem permissão para adicionar observações.';
            redirect(base_url("alunos/{$id}?tab=historico"));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("alunos/{$id}?tab=historico"));
        }

        $studentModel = new Student();
        $student = $studentModel->find($id);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('alunos'));
        }

        $observation = trim($_POST['observation'] ?? '');
        
        if (empty($observation)) {
            $_SESSION['error'] = 'A observação não pode estar vazia.';
            redirect(base_url("alunos/{$id}?tab=historico"));
        }

        // Limitar tamanho (máximo 200 caracteres)
        if (mb_strlen($observation) > 200) {
            $_SESSION['error'] = 'A observação deve ter no máximo 200 caracteres.';
            redirect(base_url("alunos/{$id}?tab=historico"));
        }

        $historyService = new StudentHistoryService();
        $historyService->logManualObservation($id, $observation);

        $_SESSION['success'] = 'Observação adicionada com sucesso!';
        redirect(base_url("alunos/{$id}?tab=historico"));
    }

    /**
     * Exclui uma matrícula (soft delete)
     * 
     * @param int $id ID da matrícula
     */
    public function excluirMatricula($id)
    {
        // Apenas ADMIN pode excluir (verificar role diretamente)
        $currentRole = $_SESSION['current_role'] ?? '';
        if ($currentRole !== Constants::ROLE_ADMIN) {
            $_SESSION['error'] = 'Apenas administradores podem excluir matrículas.';
            redirect(base_url("matriculas/{$id}"));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url("matriculas/{$id}"));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("matriculas/{$id}"));
        }

        $enrollmentModel = new Enrollment();
        $enrollment = $enrollmentModel->findWithDetails($id);

        if (!$enrollment || $enrollment['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Matrícula não encontrada.';
            redirect(base_url('alunos'));
        }

        // Validação 1: Não pode excluir se já está cancelada
        if ($enrollment['status'] === 'cancelada') {
            $_SESSION['error'] = 'Esta matrícula já está cancelada.';
            redirect(base_url("matriculas/{$id}"));
        }

        // Validação 2: Não pode excluir se tiver cobrança ativa na EFI
        // Status considerados inativos: canceled, expired, finished, settled, paid
        $gatewayStatusLower = strtolower($enrollment['gateway_last_status'] ?? '');
        $inactiveStatuses = ['canceled', 'expired', 'cancelado', 'expirado', 'finished', 'settled', 'paid'];
        $hasActiveCharge = !empty($enrollment['gateway_charge_id']) && 
                          !in_array($gatewayStatusLower, $inactiveStatuses);
        
        if ($hasActiveCharge) {
            $_SESSION['error'] = 'Não é possível excluir matrícula com cobrança ativa na EFI. Primeiro cancele a cobrança na EFI, sincronize e depois exclua no sistema.';
            redirect(base_url("matriculas/{$id}"));
        }

        // Validação 3: Verificar se tem aulas agendadas ou em andamento
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM lessons 
            WHERE enrollment_id = ? 
            AND status IN ('agendada', 'em_andamento')
        ");
        $stmt->execute([$id]);
        $lessonsCount = $stmt->fetch()['total'] ?? 0;

        if ($lessonsCount > 0) {
            $_SESSION['error'] = "Não é possível excluir matrícula com {$lessonsCount} aula(s) agendada(s) ou em andamento. Cancele ou conclua as aulas primeiro.";
            redirect(base_url("matriculas/{$id}"));
        }

        // Obter motivo da exclusão (opcional)
        $deleteReason = trim($_POST['delete_reason'] ?? '');
        if (empty($deleteReason)) {
            $deleteReason = 'Exclusão manual pelo usuário';
        }

        $dataBefore = $enrollment;
        $studentId = $enrollment['student_id'];

        $db->beginTransaction();
        
        try {
            // Soft delete: atualizar status e zerar saldo devedor
            $updateData = [
                'status' => 'cancelada',
                'outstanding_amount' => 0,
                'financial_status' => 'em_dia', // Sem saldo, está em dia
                'gateway_charge_id' => null, // Limpar referência à cobrança EFI
                'gateway_payment_url' => null,
                'gateway_pix_code' => null,
                'gateway_barcode' => null,
                'billing_status' => 'error' // Marcar como erro (cancelada)
            ];

            $setParts = [];
            $params = [];
            foreach ($updateData as $key => $value) {
                $setParts[] = "`{$key}` = ?";
                $params[] = $value;
            }
            $params[] = $id;

            $sql = "UPDATE enrollments SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // Registrar no histórico do aluno
            $historyService = new StudentHistoryService();
            $historyService->logFinancialEvent(
                $studentId, 
                "Matrícula #{$id} excluída: {$enrollment['service_name']}. Motivo: {$deleteReason}"
            );

            // Auditoria
            $auditService = new AuditService();
            $auditService->logUpdate('enrollments', $id, $dataBefore, $updateData, "Exclusão: {$deleteReason}");

            $db->commit();
            
            $_SESSION['success'] = 'Matrícula excluída com sucesso!';
            
            // Verificar se veio da página financeira para redirecionar de volta
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, '/financeiro') !== false) {
                redirect(base_url("financeiro?student_id={$studentId}"));
            } else {
                redirect(base_url("alunos/{$studentId}?tab=matricula"));
            }
            
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Erro ao excluir matrícula: " . $e->getMessage());
            $_SESSION['error'] = 'Erro ao excluir matrícula: ' . $e->getMessage();
            redirect(base_url("matriculas/{$id}"));
        }
    }
}
