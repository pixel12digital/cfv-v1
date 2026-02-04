<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Models\City;
use App\Models\Enrollment;
use App\Models\Student;

class ApiController extends Controller
{
    public function switchRole()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $role = $input['role'] ?? '';
        
        if (empty($role)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Papel não especificado']);
            exit;
        }
        
        $authService = new AuthService();
        $success = $authService->switchRole($role);
        
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Papel não disponível']);
        }
        exit;
    }

    /**
     * Endpoint para listar cidades por UF com busca opcional
     * GET /api/geo/cidades?uf=SC
     * GET /api/geo/cidades?uf=SC&q=camp (busca com query)
     */
    public function getCidades()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $uf = strtoupper(trim($_GET['uf'] ?? ''));
        $query = trim($_GET['q'] ?? '');
        
        if (empty($uf)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'UF não especificada']);
            exit;
        }
        
        // Validar UF (2 caracteres)
        if (strlen($uf) !== 2) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'UF inválida']);
            exit;
        }
        
        $cityModel = new City();
        
        // Se houver query, usar busca; senão, retornar todas (compatibilidade)
        if (!empty($query) && strlen($query) >= 2) {
            $cidades = $cityModel->searchByUf($uf, $query, 30);
        } else {
            // Para compatibilidade, se não houver query, retornar todas
            // Mas limitar a 100 para evitar problemas de performance
            $cidades = $cityModel->searchByUf($uf, '', 100);
        }
        
        // Formatar resposta
        $result = array_map(function($cidade) {
            return [
                'id' => (int)$cidade['id'],
                'name' => $cidade['name']
            ];
        }, $cidades);
        
        echo json_encode($result);
        exit;
    }

    /**
     * Endpoint para consultar CEP via ViaCEP e resolver city_id
     * GET /api/geo/cep?cep=89010000
     */
    public function getCep()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $cep = preg_replace('/[^0-9]/', '', $_GET['cep'] ?? '');
        
        if (empty($cep) || strlen($cep) !== 8) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'CEP inválido',
                'erro' => true
            ]);
            exit;
        }
        
        // Consultar ViaCEP
        $viaCepUrl = "https://viacep.com.br/ws/{$cep}/json/";
        
        $ch = curl_init($viaCepUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response || $curlError) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Falha ao consultar CEP',
                'erro' => true
            ]);
            exit;
        }
        
        $viaCepData = json_decode($response, true);
        
        // Verificar se ViaCEP retornou erro
        if (isset($viaCepData['erro']) && $viaCepData['erro'] === true) {
            echo json_encode([
                'success' => false,
                'message' => 'CEP não encontrado',
                'erro' => true
            ]);
            exit;
        }
        
        // Normalizar dados do ViaCEP
        $uf = strtoupper(trim($viaCepData['uf'] ?? ''));
        $localidade = trim($viaCepData['localidade'] ?? '');
        $logradouro = trim($viaCepData['logradouro'] ?? '');
        $bairro = trim($viaCepData['bairro'] ?? '');
        $complemento = trim($viaCepData['complemento'] ?? '');
        
        // Resolver city_id usando nossa base IBGE
        $cityId = null;
        $cityName = null;
        
        if (!empty($uf) && !empty($localidade)) {
            $cityModel = new City();
            $city = $cityModel->findByUfAndName($uf, $localidade);
            
            if ($city) {
                $cityId = (int)$city['id'];
                $cityName = $city['name'];
            }
        }
        
        // Formatar resposta
        $result = [
            'success' => true,
            'erro' => false,
            'cep' => $cep,
            'uf' => $uf,
            'cidade' => $localidade,
            'logradouro' => $logradouro,
            'bairro' => $bairro,
            'complemento' => $complemento,
            'city_id' => $cityId,
            'city_name' => $cityName,
            'city_found' => $cityId !== null
        ];
        
        echo json_encode($result);
        exit;
    }

    /**
     * Endpoint para buscar matrículas de um aluno
     * GET /api/students/{id}/enrollments
     */
    public function getStudentEnrollments($studentId)
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        // Verificar autenticação
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }
        
        $cfcId = $_SESSION['cfc_id'] ?? 1;
        
        // Validar que o aluno pertence ao CFC
        $studentModel = new Student();
        $student = $studentModel->find($studentId);
        
        if (!$student || $student['cfc_id'] != $cfcId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Aluno não encontrado']);
            exit;
        }
        
        // Buscar matrículas (excluir canceladas - alinhado com aba Matrículas do perfil)
        $enrollmentModel = new Enrollment();
        $enrollments = $enrollmentModel->findByStudent($studentId);
        $enrollments = array_values(array_filter($enrollments, fn($e) => ($e['status'] ?? '') !== 'cancelada'));
        
        // Formatar resposta
        $result = array_map(function($enr) {
            return [
                'id' => (int)$enr['id'],
                'service_name' => $enr['service_name'] ?? 'Matrícula',
                'financial_status' => $enr['financial_status'],
                'status' => $enr['status']
            ];
        }, $enrollments);
        
        echo json_encode([
            'success' => true,
            'enrollments' => $result
        ]);
        exit;
    }
}
