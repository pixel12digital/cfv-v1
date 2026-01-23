<?php
/**
 * Script de Teste - Erro ao Carregar Agenda
 * Execute: https://localhost/cfc-v.1/public_html/tools/test_agenda_error.php
 * 
 * Este script tenta reproduzir o erro da agenda localmente
 */

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');

// Iniciar sessão
session_start();

// Simular login como instrutor
$_SESSION['user_id'] = 5; // Robson Wagner Alves vieira
$_SESSION['current_role'] = 'INSTRUTOR';
$_SESSION['user_type'] = 'instrutor';
$_SESSION['user_name'] = 'Robson Wagner Alves vieira';
$_SESSION['user_email'] = 'rwavieira@gmail.com';
$_SESSION['cfc_id'] = 1;

// Autoload
require_once APP_PATH . '/autoload.php';

use App\Config\Database;
use App\Models\User;
use App\Models\Lesson;
use App\Models\Instructor;
use App\Models\Vehicle;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste - Erro Agenda</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; font-size: 14px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; border: 1px solid #ddd; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste - Erro ao Carregar Agenda</h1>

<?php
echo "<div class='section'>";
echo "<h2>1. Informações da Sessão</h2>";
echo "<pre>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'N/A') . "\n";
echo "current_role: " . ($_SESSION['current_role'] ?? 'N/A') . "\n";
echo "user_type: " . ($_SESSION['user_type'] ?? 'N/A') . "\n";
echo "cfc_id: " . ($_SESSION['cfc_id'] ?? 'N/A') . "\n";
echo "</pre>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>2. Teste de Conexão com Banco</h2>";

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT DATABASE() as current_db");
    $currentDb = $stmt->fetch();
    echo "<p class='success'>✓ Conexão estabelecida</p>";
    echo "<p><strong>Banco:</strong> " . ($currentDb['current_db'] ?? 'N/A') . "</p>";
} catch (\Exception $e) {
    echo "<p class='error'>✗ Erro na conexão: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></div></body></html>";
    exit;
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>3. Verificar Tabelas</h2>";

$tables = ['instructors', 'vehicles', 'lessons', 'students', 'enrollments', 'usuarios'];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            $countStmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $countStmt->fetch()['count'];
            echo "<p class='success'>✓ Tabela '{$table}' existe ({$count} registro(s))</p>";
        } else {
            echo "<p class='error'>✗ Tabela '{$table}' NÃO existe</p>";
        }
    } catch (\Exception $e) {
        echo "<p class='error'>✗ Erro ao verificar '{$table}': " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>4. Teste: User::findWithLinks()</h2>";

try {
    $userModel = new User();
    $user = $userModel->findWithLinks($_SESSION['user_id']);
    
    if ($user) {
        echo "<p class='success'>✓ findWithLinks() executado com sucesso</p>";
        echo "<pre>";
        echo "user_id: " . ($user['id'] ?? 'N/A') . "\n";
        echo "nome: " . ($user['nome'] ?? 'N/A') . "\n";
        echo "instructor_id: " . ($user['instructor_id'] ?? 'N/A') . "\n";
        echo "student_id: " . ($user['student_id'] ?? 'N/A') . "\n";
        echo "</pre>";
        $instructorId = $user['instructor_id'] ?? $_SESSION['user_id'];
    } else {
        echo "<p class='error'>✗ Usuário não encontrado</p>";
        $instructorId = $_SESSION['user_id'];
    }
} catch (\Exception $e) {
    echo "<p class='error'>✗ Erro em findWithLinks():</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    $instructorId = $_SESSION['user_id'];
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>5. Teste: Lesson::findByInstructorWithTheoryDedupe()</h2>";

try {
    $lessonModel = new Lesson();
    $lessons = $lessonModel->findByInstructorWithTheoryDedupe(
        $instructorId,
        $_SESSION['cfc_id'],
        ['tab' => 'proximas']
    );
    
    echo "<p class='success'>✓ findByInstructorWithTheoryDedupe() executado com sucesso</p>";
    echo "<p><strong>Aulas encontradas:</strong> " . count($lessons) . "</p>";
    
    if (!empty($lessons)) {
        echo "<pre>";
        foreach (array_slice($lessons, 0, 3) as $idx => $lesson) {
            echo "Aula " . ($idx + 1) . ":\n";
            echo "  ID: " . ($lesson['id'] ?? 'N/A') . "\n";
            echo "  Data: " . ($lesson['scheduled_date'] ?? 'N/A') . "\n";
            echo "  Hora: " . ($lesson['scheduled_time'] ?? 'N/A') . "\n";
            echo "  Status: " . ($lesson['status'] ?? 'N/A') . "\n";
            echo "  Tipo: " . ($lesson['type'] ?? 'N/A') . "\n";
            echo "\n";
        }
        echo "</pre>";
    }
} catch (\PDOException $e) {
    echo "<p class='error'>✗ PDOException em findByInstructorWithTheoryDedupe():</p>";
    echo "<pre>";
    echo "SQLSTATE: " . $e->getCode() . "\n";
    echo "Mensagem: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
    echo "</pre>";
} catch (\Exception $e) {
    echo "<p class='error'>✗ Exception em findByInstructorWithTheoryDedupe():</p>";
    echo "<pre>";
    echo "Classe: " . get_class($e) . "\n";
    echo "Mensagem: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
    echo "</pre>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>6. Teste: Instructor::findAvailableForAgenda()</h2>";

try {
    $instructorModel = new Instructor();
    $instructors = $instructorModel->findAvailableForAgenda($_SESSION['cfc_id']);
    
    echo "<p class='success'>✓ findAvailableForAgenda() executado com sucesso</p>";
    echo "<p><strong>Instrutores encontrados:</strong> " . count($instructors) . "</p>";
} catch (\Exception $e) {
    echo "<p class='error'>✗ Erro em findAvailableForAgenda():</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>7. Teste: Simular AgendaController::index()</h2>";

try {
    $currentRole = $_SESSION['current_role'] ?? '';
    $userId = $_SESSION['user_id'] ?? null;
    $isInstrutor = ($currentRole === 'INSTRUTOR');
    $view = 'list';
    $date = date('Y-m-d');
    
    $lessonModel = new Lesson();
    $instructorModel = new Instructor();
    $vehicleModel = new Vehicle();
    $userModel = new User();
    
    $loggedInstructorId = null;
    
    if ($isInstrutor && $userId) {
        $user = $userModel->findWithLinks($userId);
        if ($user && !empty($user['instructor_id'])) {
            $loggedInstructorId = $user['instructor_id'];
        }
    }
    
    $instructorId = $isInstrutor ? null : null;
    if ($isInstrutor && $loggedInstructorId) {
        $instructorId = $loggedInstructorId;
    }
    
    $startDate = $date;
    $endDate = $date;
    
    if ($isInstrutor && $loggedInstructorId && $view === 'list') {
        $instructorFilters = ['tab' => 'proximas'];
        if ($startDate && $endDate) {
            $instructorFilters['start_date'] = $startDate;
            $instructorFilters['end_date'] = $endDate;
        }
        
        echo "<p>Executando findByInstructorWithTheoryDedupe() com:</p>";
        echo "<pre>";
        echo "instructor_id: {$loggedInstructorId}\n";
        echo "cfc_id: " . $_SESSION['cfc_id'] . "\n";
        echo "filters: " . json_encode($instructorFilters, JSON_PRETTY_PRINT) . "\n";
        echo "</pre>";
        
        $lessons = $lessonModel->findByInstructorWithTheoryDedupe(
            $loggedInstructorId, 
            $_SESSION['cfc_id'], 
            $instructorFilters
        );
        
        echo "<p class='success'>✓ Simulação executada com sucesso!</p>";
        echo "<p><strong>Aulas encontradas:</strong> " . count($lessons) . "</p>";
    } else {
        echo "<p class='warning'>⚠ Condições não atendidas para teste completo</p>";
        echo "<pre>";
        echo "isInstrutor: " . ($isInstrutor ? 'true' : 'false') . "\n";
        echo "loggedInstructorId: " . ($loggedInstructorId ?? 'null') . "\n";
        echo "view: {$view}\n";
        echo "</pre>";
    }
    
} catch (\PDOException $e) {
    echo "<p class='error'>✗ PDOException na simulação:</p>";
    echo "<pre>";
    echo "SQLSTATE: " . $e->getCode() . "\n";
    echo "Mensagem: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
    echo "</pre>";
} catch (\Exception $e) {
    echo "<p class='error'>✗ Exception na simulação:</p>";
    echo "<pre>";
    echo "Classe: " . get_class($e) . "\n";
    echo "Mensagem: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
    echo "</pre>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>8. Verificar Logs Recentes</h2>";
echo "<p class='info'>Verifique o arquivo error_log do PHP para ver os logs detalhados do AgendaController::index()</p>";
echo "<p><strong>Caminho do error_log:</strong> " . ini_get('error_log') . "</p>";
echo "</div>";

?>

    </div>
</body>
</html>
