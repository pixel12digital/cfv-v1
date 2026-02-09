<?php

namespace App\Controllers;

use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\State;
use App\Models\City;
use App\Services\AuditService;
use App\Services\UserCreationService;
use App\Services\EmailService;
use App\Config\Constants;
use App\Config\Database;

class InstrutoresController extends Controller
{
    private $cfcId;
    private $auditService;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->auditService = new AuditService();

        // Apenas ADMIN pode gerenciar instrutores (SECRETARIA não)
        if (($_SESSION['current_role'] ?? '') !== Constants::ROLE_ADMIN) {
            $_SESSION['error'] = 'Acesso restrito ao administrador.';
            redirect(base_url('dashboard'));
        }
    }

    public function index()
    {
        $instructorModel = new Instructor();
        $instructors = $instructorModel->findByCfc($this->cfcId, false); // Todos, incluindo inativos
        
        // Verificar credenciais vencidas
        foreach ($instructors as &$instructor) {
            $instructor['credential_expired'] = $instructorModel->isCredentialExpired($instructor);
        }
        
        $data = [
            'pageTitle' => 'Instrutores',
            'instructors' => $instructors
        ];
        $this->view('instrutores/index', $data);
    }

    public function novo()
    {
        $stateModel = new State();
        $states = $stateModel->findAll();
        
        $data = [
            'pageTitle' => 'Novo Instrutor',
            'states' => $states,
            'currentCity' => null,
            'availability' => [] // Disponibilidade vazia para novo instrutor
        ];
        $this->view('instrutores/form', $data);
    }

    public function criar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('instrutores'));
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('instrutores'));
        }
        
        // Validações básicas
        $name = trim($_POST['name'] ?? '');
        $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
        $birthDate = $_POST['birth_date'] ?? null;
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($name)) {
            $_SESSION['error'] = 'Nome é obrigatório.';
            redirect(base_url('instrutores/novo'));
        }
        
        if (empty($cpf)) {
            $_SESSION['error'] = 'CPF é obrigatório.';
            redirect(base_url('instrutores/novo'));
        }
        
        if (empty($birthDate)) {
            $_SESSION['error'] = 'Data de nascimento é obrigatória.';
            redirect(base_url('instrutores/novo'));
        }
        
        if (empty($email)) {
            $_SESSION['error'] = 'E-mail é obrigatório (necessário para acesso ao sistema).';
            redirect(base_url('instrutores/novo'));
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'E-mail inválido.';
            redirect(base_url('instrutores/novo'));
        }
        
        // Verificar se já existe usuário com este e-mail
        $userModel = new \App\Models\User();
        $existingUser = $userModel->findByEmail($email);
        
        if (empty($phone)) {
            $_SESSION['error'] = 'Telefone é obrigatório.';
            redirect(base_url('instrutores/novo'));
        }
        
        $instructorModel = new Instructor();
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Preparar dados
        $data = [
            'cfc_id' => $this->cfcId,
            'name' => $name,
            'cpf' => $cpf,
            'birth_date' => $birthDate,
            'phone' => $phone,
            'email' => $email,
            'license_number' => !empty($_POST['license_number']) ? trim($_POST['license_number']) : null,
            'license_category' => !empty($_POST['license_category']) ? trim($_POST['license_category']) : null,
            'license_categories' => !empty($_POST['license_categories']) ? trim($_POST['license_categories']) : null,
            'credential_number' => !empty($_POST['credential_number']) ? trim($_POST['credential_number']) : null,
            'credential_expiry_date' => !empty($_POST['credential_expiry_date']) ? $_POST['credential_expiry_date'] : null,
            'is_active' => $isActive,
            'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
            // Endereço
            'cep' => !empty($_POST['cep']) ? preg_replace('/[^0-9]/', '', $_POST['cep']) : null,
            'address_street' => !empty($_POST['address_street']) ? trim($_POST['address_street']) : null,
            'address_number' => !empty($_POST['address_number']) ? trim($_POST['address_number']) : null,
            'address_complement' => !empty($_POST['address_complement']) ? trim($_POST['address_complement']) : null,
            'address_neighborhood' => !empty($_POST['address_neighborhood']) ? trim($_POST['address_neighborhood']) : null,
            'address_city_id' => !empty($_POST['address_city_id']) ? (int)$_POST['address_city_id'] : null,
            'address_state_id' => !empty($_POST['address_state_id']) ? (int)$_POST['address_state_id'] : null
        ];
        
        $instructorId = $instructorModel->create($data);

        // Se já existe usuário com este e-mail, vincular e garantir role INSTRUTOR
        if ($existingUser) {
            // Regra: e-mail igual -> vínculo automático
            $instructorModel->update($instructorId, ['user_id' => $existingUser['id']]);
            $this->ensureInstructorRole($existingUser['id']);
            $_SESSION['success'] = 'Instrutor cadastrado e vinculado ao usuário existente com sucesso.';
        }
        
        // Processar upload de foto se houver
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $this->processPhotoUpload($instructorId, $_FILES['photo']);
        }
        
        // Salvar disponibilidade
        $availabilityModel = new InstructorAvailability();
        $daysOfWeek = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
        
        foreach ($daysOfWeek as $dayNum => $dayName) {
            $isAvailable = isset($_POST["availability_day_{$dayNum}"]) ? 1 : 0;
            $startTime = $_POST["availability_start_{$dayNum}"] ?? '08:00';
            $endTime = $_POST["availability_end_{$dayNum}"] ?? '18:00';
            
            if ($isAvailable) {
                $availabilityModel->saveAvailability($instructorId, $dayNum, $startTime, $endTime, true);
            }
        }
        
        $this->auditService->logCreate('instrutores', $instructorId, $data);

        // Se NÃO existe usuário com este e-mail, manter fluxo atual de criação automática
        if (!$existingUser) {
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    $userService = new UserCreationService();
                    $userData = $userService->createForInstructor($instructorId, $email, $name);
                    
                    // Tentar enviar e-mail com credenciais (não bloqueia se falhar)
                    try {
                        $emailService = new EmailService();
                        $loginUrl = base_url('/login');
                        $emailService->sendAccessCreated($email, $userData['temp_password'], $loginUrl);
                    } catch (\Exception $e) {
                        // Log mas não bloqueia
                        error_log("Erro ao enviar e-mail de acesso: " . $e->getMessage());
                    }
                    
                    $_SESSION['success'] = 'Instrutor cadastrado com sucesso! Acesso ao sistema criado automaticamente.';
                } catch (\Exception $e) {
                    // Se falhar, apenas logar mas não bloquear criação do instrutor
                    error_log("Erro ao criar acesso para instrutor: " . $e->getMessage());
                    $_SESSION['success'] = 'Instrutor cadastrado com sucesso! (Aviso: não foi possível criar acesso automático - ' . $e->getMessage() . ')';
                }
            } else {
                $_SESSION['success'] = 'Instrutor cadastrado com sucesso! (Acesso não criado: e-mail inválido)';
            }
        }
        
        redirect(base_url('instrutores'));
    }

    public function editar($id)
    {
        $instructorModel = new Instructor();
        $instructor = $instructorModel->findWithDetails($id);
        
        if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Instrutor não encontrado.';
            redirect(base_url('instrutores'));
        }
        
        // Buscar disponibilidade
        $availabilityModel = new InstructorAvailability();
        $availability = $availabilityModel->findByInstructor($id);
        $availabilityByDay = [];
        foreach ($availability as $av) {
            $availabilityByDay[$av['day_of_week']] = $av;
        }
        
        // Buscar cidade atual
        $currentCity = null;
        if (!empty($instructor['address_city_id'])) {
            $cityModel = new City();
            $currentCity = $cityModel->find($instructor['address_city_id']);
        }
        
        $stateModel = new State();
        $states = $stateModel->findAll();
        
        $data = [
            'pageTitle' => 'Editar Instrutor',
            'instructor' => $instructor,
            'states' => $states,
            'currentCity' => $currentCity,
            'availability' => $availabilityByDay
        ];
        $this->view('instrutores/form', $data);
    }

    public function atualizar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('instrutores'));
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('instrutores'));
        }
        
        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($id);
        
        if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Instrutor não encontrado.';
            redirect(base_url('instrutores'));
        }
        
        // Validações básicas
        $name = trim($_POST['name'] ?? '');
        $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
        $birthDate = $_POST['birth_date'] ?? null;
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($name)) {
            $_SESSION['error'] = 'Nome é obrigatório.';
            redirect(base_url('instrutores/' . $id . '/editar'));
        }
        
        if (empty($cpf)) {
            $_SESSION['error'] = 'CPF é obrigatório.';
            redirect(base_url('instrutores/' . $id . '/editar'));
        }
        
        if (empty($birthDate)) {
            $_SESSION['error'] = 'Data de nascimento é obrigatória.';
            redirect(base_url('instrutores/' . $id . '/editar'));
        }
        
        if (empty($email)) {
            $_SESSION['error'] = 'E-mail é obrigatório (necessário para acesso ao sistema).';
            redirect(base_url('instrutores/' . $id . '/editar'));
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'E-mail inválido.';
            redirect(base_url('instrutores/' . $id . '/editar'));
        }
        
        // Verificar vínculos de usuário/e-mail
        $userModel = new \App\Models\User();
        $existingUser = $userModel->findByEmail($email);
        $linkedUser = !empty($instructor['user_id']) ? $userModel->find($instructor['user_id']) : null;

        // Regra: se já existe vínculo por user_id e o e-mail desse usuário é diferente do informado, bloquear
        if ($linkedUser && strcasecmp($linkedUser['email'], $email) !== 0) {
            $_SESSION['error'] = 'E-mail diferente do usuário já vinculado. Ajuste o vínculo em "Gerenciamento de Usuários" antes de alterar o e-mail do instrutor.';
            redirect(base_url('instrutores/' . $id . '/editar'));
        }

        // Se não há vínculo (user_id vazio) mas já existe usuário com este e-mail, vincular automaticamente
        if (!$linkedUser && $existingUser) {
            $instructorModel->update($id, ['user_id' => $existingUser['id']]);
            $this->ensureInstructorRole($existingUser['id']);
        }

        // Se há vínculo (user_id) mas nenhum usuário com este e-mail, não tentar "adivinhar"
        if ($linkedUser && !$existingUser && strcasecmp($linkedUser['email'], $email) !== 0) {
            $_SESSION['error'] = 'E-mail informado não existe em nenhum usuário e diverge do usuário vinculado. Faça o ajuste manual no usuário antes de alterar o e-mail.';
            redirect(base_url('instrutores/' . $id . '/editar'));
        }
        
        if (empty($phone)) {
            $_SESSION['error'] = 'Telefone é obrigatório.';
            redirect(base_url('instrutores/' . $id . '/editar'));
        }
        
        $dataBefore = $instructor;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $updateData = [
            'name' => $name,
            'cpf' => $cpf,
            'birth_date' => $birthDate,
            'phone' => $phone,
            'email' => $email,
            'license_number' => !empty($_POST['license_number']) ? trim($_POST['license_number']) : null,
            'license_category' => !empty($_POST['license_category']) ? trim($_POST['license_category']) : null,
            'license_categories' => !empty($_POST['license_categories']) ? trim($_POST['license_categories']) : null,
            'credential_number' => !empty($_POST['credential_number']) ? trim($_POST['credential_number']) : null,
            'credential_expiry_date' => !empty($_POST['credential_expiry_date']) ? $_POST['credential_expiry_date'] : null,
            'is_active' => $isActive,
            'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
            // Endereço
            'cep' => !empty($_POST['cep']) ? preg_replace('/[^0-9]/', '', $_POST['cep']) : null,
            'address_street' => !empty($_POST['address_street']) ? trim($_POST['address_street']) : null,
            'address_number' => !empty($_POST['address_number']) ? trim($_POST['address_number']) : null,
            'address_complement' => !empty($_POST['address_complement']) ? trim($_POST['address_complement']) : null,
            'address_neighborhood' => !empty($_POST['address_neighborhood']) ? trim($_POST['address_neighborhood']) : null,
            'address_city_id' => !empty($_POST['address_city_id']) ? (int)$_POST['address_city_id'] : null,
            'address_state_id' => !empty($_POST['address_state_id']) ? (int)$_POST['address_state_id'] : null
        ];
        
        $instructorModel->update($id, $updateData);
        
        // Atualizar disponibilidade
        $availabilityModel = new InstructorAvailability();
        $daysOfWeek = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
        
        foreach ($daysOfWeek as $dayNum => $dayName) {
            $isAvailable = isset($_POST["availability_day_{$dayNum}"]) ? 1 : 0;
            $startTime = $_POST["availability_start_{$dayNum}"] ?? '08:00';
            $endTime = $_POST["availability_end_{$dayNum}"] ?? '18:00';
            
            if ($isAvailable) {
                $availabilityModel->saveAvailability($id, $dayNum, $startTime, $endTime, true);
            } else {
                // Remover disponibilidade se não estiver marcado
                $existing = $availabilityModel->findByInstructorAndDay($id, $dayNum);
                if ($existing) {
                    $availabilityModel->delete($existing['id']);
                }
            }
        }
        
        $this->auditService->logUpdate('instrutores', $id, $dataBefore, array_merge($instructor, $updateData));
        
        $_SESSION['success'] = 'Instrutor atualizado com sucesso!';
        redirect(base_url('instrutores'));
    }

    /**
     * Processa upload de foto (método auxiliar)
     */
    private function processPhotoUpload($instructorId, $file)
    {
        // Validar tipo
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return false;
        }

        // Validar tamanho (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return false;
        }

        // Criar diretório se não existir
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/instructors/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Gerar nome único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'instructor_' . $instructorId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Buscar instrutor para remover foto antiga
        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($instructorId);
        
        if ($instructor && !empty($instructor['photo_path'])) {
            $oldPath = dirname(__DIR__, 2) . '/' . $instructor['photo_path'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Mover arquivo
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $relativePath = 'storage/uploads/instructors/' . $filename;
            $instructorModel->update($instructorId, ['photo_path' => $relativePath]);
            return true;
        }

        return false;
    }

    /**
     * Upload de foto do instrutor (endpoint separado)
     */
    public function uploadFoto($id)
    {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("instrutores/{$id}/editar"));
        }

        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($id);

        if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Instrutor não encontrado.';
            redirect(base_url('instrutores'));
        }

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Erro ao enviar arquivo.';
            redirect(base_url("instrutores/{$id}/editar"));
        }

        $file = $_FILES['photo'];
        
        // Validar tipo
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $_SESSION['error'] = 'Tipo de arquivo inválido. Use JPG, PNG ou WEBP.';
            redirect(base_url("instrutores/{$id}/editar"));
        }

        // Validar tamanho (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['error'] = 'Arquivo muito grande. Máximo 2MB.';
            redirect(base_url("instrutores/{$id}/editar"));
        }

        // Criar diretório se não existir
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/instructors/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Gerar nome único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'instructor_' . $id . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Remover foto antiga se existir
        if (!empty($instructor['photo_path'])) {
            $oldPath = dirname(__DIR__, 2) . '/' . $instructor['photo_path'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $_SESSION['error'] = 'Erro ao salvar arquivo.';
            redirect(base_url("instrutores/{$id}/editar"));
        }

        // Atualizar banco
        $relativePath = 'storage/uploads/instructors/' . $filename;
        $instructorModel->update($id, ['photo_path' => $relativePath]);

        // Auditoria
        $this->auditService->log('upload_photo', 'instrutores', $id, ['old_photo' => $instructor['photo_path'] ?? null], ['new_photo' => $relativePath]);

        $_SESSION['success'] = 'Foto atualizada com sucesso!';
        redirect(base_url("instrutores/{$id}/editar"));
    }

    /**
     * Remover foto do instrutor
     */
    public function removerFoto($id)
    {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("instrutores/{$id}/editar"));
        }

        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($id);

        if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Instrutor não encontrado.';
            redirect(base_url('instrutores'));
        }

        if (!empty($instructor['photo_path'])) {
            $filepath = dirname(__DIR__, 2) . '/' . $instructor['photo_path'];
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
            $instructorModel->update($id, ['photo_path' => null]);

            $this->auditService->log('remove_photo', 'instrutores', $id, ['old_photo' => $instructor['photo_path']], null);

            $_SESSION['success'] = 'Foto removida com sucesso!';
        }

        redirect(base_url("instrutores/{$id}/editar"));
    }

    /**
     * Servir foto do instrutor (protegido)
     */
    public function foto($id)
    {
        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($id);

        if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
            http_response_code(404);
            exit('Foto não encontrada');
        }

        if (empty($instructor['photo_path'])) {
            http_response_code(404);
            exit('Foto não encontrada');
        }

        $filepath = dirname(__DIR__, 2) . '/' . $instructor['photo_path'];

        if (!file_exists($filepath)) {
            http_response_code(404);
            exit('Foto não encontrada');
        }

        $mimeType = mime_content_type($filepath);
        header('Content-Type: ' . $mimeType);
        readfile($filepath);
        exit;
    }

    /**
     * Excluir instrutor e todos os dados relacionados
     */
    public function excluir($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('instrutores'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('instrutores'));
        }

        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($id);

        if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Instrutor não encontrado.';
            redirect(base_url('instrutores'));
        }

        // Salvar dados para auditoria
        $dataBefore = $instructor;

        try {
            // 1. Deletar todas as aulas (lessons) relacionadas
            $lessonModel = new \App\Models\Lesson();
            $lessons = $this->query(
                "SELECT id FROM lessons WHERE instructor_id = ?",
                [$id]
            )->fetchAll();
            
            foreach ($lessons as $lesson) {
                $lessonModel->delete($lesson['id']);
            }

            // 2. Deletar todas as turmas teóricas relacionadas
            // (isso deletará automaticamente as sessões e matrículas por CASCADE)
            $theoryClassModel = new \App\Models\TheoryClass();
            $theoryClasses = $this->query(
                "SELECT id FROM theory_classes WHERE instructor_id = ?",
                [$id]
            )->fetchAll();
            
            foreach ($theoryClasses as $theoryClass) {
                $theoryClassModel->delete($theoryClass['id']);
            }

            // 3. Deletar disponibilidade (já tem CASCADE, mas garantindo)
            $availabilityModel = new InstructorAvailability();
            $availability = $availabilityModel->findByInstructor($id);
            foreach ($availability as $av) {
                $availabilityModel->delete($av['id']);
            }

            // 4. Remover foto do instrutor se existir
            if (!empty($instructor['photo_path'])) {
                $filepath = dirname(__DIR__, 2) . '/' . $instructor['photo_path'];
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
            }

            // 5. Deletar usuário relacionado (se houver)
            if (!empty($instructor['user_id'])) {
                $userModel = new \App\Models\User();
                // Verificar se o usuário não está vinculado a outro registro
                $otherInstructor = $this->query(
                    "SELECT id FROM instructors WHERE user_id = ? AND id != ?",
                    [$instructor['user_id'], $id]
                )->fetch();
                
                if (!$otherInstructor) {
                    // Verificar se não está vinculado a um aluno
                    $student = $this->query(
                        "SELECT id FROM students WHERE user_id = ?",
                        [$instructor['user_id']]
                    )->fetch();
                    
                    if (!$student) {
                        // Deletar roles do usuário
                        $this->query(
                            "DELETE FROM usuario_roles WHERE usuario_id = ?",
                            [$instructor['user_id']]
                        );
                        
                        // Deletar usuário
                        $userModel->delete($instructor['user_id']);
                    }
                }
            }

            // 6. Registrar auditoria antes de deletar
            $this->auditService->logDelete('instrutores', $id, $dataBefore);

            // 7. Deletar o instrutor
            $instructorModel->delete($id);

            $_SESSION['success'] = 'Instrutor e todos os dados relacionados foram excluídos com sucesso!';
        } catch (\Exception $e) {
            error_log("Erro ao excluir instrutor: " . $e->getMessage());
            $_SESSION['error'] = 'Erro ao excluir instrutor: ' . $e->getMessage();
        }

        redirect(base_url('instrutores'));
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

    /**
     * Garante que o usuário tenha o papel INSTRUTOR em usuario_roles (upsert idempotente).
     */
    private function ensureInstructorRole($userId)
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT 1 FROM usuario_roles WHERE usuario_id = ? AND role = 'INSTRUTOR' LIMIT 1");
        $stmt->execute([$userId]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $stmt = $db->prepare("INSERT INTO usuario_roles (usuario_id, role) VALUES (?, 'INSTRUTOR')");
            $stmt->execute([$userId]);
        }
    }
}
