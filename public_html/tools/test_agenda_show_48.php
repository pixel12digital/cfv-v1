<?php
/**
 * Diagnóstico: Erro 500 em /agenda/48
 * Execute: https://painel.cfcbomconselho.com.br/tools/test_agenda_show_48.php
 * 
 * Simula AgendaController::show(48) para isolar a causa do erro 500
 */

// Detectar ROOT_PATH: .env fica na raiz do projeto
// Em painel/tools/ -> ROOT_PATH = painel (dirname(__DIR__))
// Em public_html/tools/ -> ROOT_PATH = projeto (dirname(__DIR__, 2))
$scriptDir = __DIR__;
$candidate = dirname($scriptDir);
$ROOT_PATH = $candidate;
if (!file_exists($candidate . '/.env') && file_exists(dirname($candidate) . '/.env')) {
    $ROOT_PATH = dirname($candidate);
}
define('ROOT_PATH', $ROOT_PATH);
define('APP_PATH', ROOT_PATH . '/app');

// Iniciar sessão (mesmo nome do painel)
if (session_status() === PHP_SESSION_NONE) {
    session_name('CFC_SESSION');
    session_start();
}

// Carregar env
if (file_exists(ROOT_PATH . '/.env')) {
    $lines = file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

require_once APP_PATH . '/autoload.php';

use App\Models\Lesson;
use App\Models\User;
use App\Models\RescheduleRequest;
use App\Config\Constants;

header('Content-Type: text/html; charset=utf-8');

$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 48;
$cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT ?? 1;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico /agenda/<?= $lessonId ?></title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; font-size: 14px; }
        .ok { color: #28a745; font-weight: bold; }
        .err { color: #dc3545; font-weight: bold; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        .section { margin: 20px 0; padding: 15px; background: white; border-radius: 4px; }
    </style>
</head>
<body>
<h1>Diagnóstico: GET /agenda/<?= $lessonId ?></h1>

<?php
echo "<div class='section'><h2>1. Sessão</h2>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'N/A') . "<br>";
echo "current_role: " . ($_SESSION['current_role'] ?? 'N/A') . "<br>";
echo "cfc_id: " . $cfcId . "<br>";
echo "</div>";

echo "<div class='section'><h2>2. Lesson::findWithDetails($lessonId)</h2>";
try {
    $lessonModel = new Lesson();
    $lesson = $lessonModel->findWithDetails($lessonId);
    if ($lesson) {
        echo "<p class='ok'>✓ findWithDetails OK</p>";
        echo "<pre>" . htmlspecialchars(print_r(array_intersect_key($lesson, array_flip(['id','cfc_id','student_id','enrollment_id','instructor_id','type','status','theory_session_id'])), true)) . "</pre>";
    } else {
        echo "<p class='err'>✗ Aula não encontrada (retorno vazio)</p>";
    }
} catch (\Throwable $e) {
    echo "<p class='err'>✗ ERRO:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString()) . "</pre>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

if (!$lesson) {
    echo "</body></html>";
    exit;
}

$currentRole = $_SESSION['current_role'] ?? '';
$isInstrutor = ($currentRole === Constants::ROLE_INSTRUTOR);

echo "<div class='section'><h2>3. getStudentSummaryForInstructor (se instrutor)</h2>";
if ($isInstrutor && $lesson['instructor_id'] && $lesson['student_id'] && $lesson['enrollment_id']) {
    try {
        $studentSummary = $lessonModel->getStudentSummaryForInstructor(
            $lesson['instructor_id'],
            $lesson['student_id'],
            $lesson['enrollment_id']
        );
        echo "<p class='ok'>✓ getStudentSummaryForInstructor OK</p>";
    } catch (\Throwable $e) {
        echo "<p class='err'>✗ ERRO (possível coluna practice_type ausente):</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
} else {
    echo "<p>Pulado (não é instrutor ou falta instructor_id/student_id/enrollment_id)</p>";
}
echo "</div>";

echo "<div class='section'><h2>4. findConsecutiveBlock</h2>";
try {
    $allLessons = $lessonModel->query(
        "SELECT * FROM lessons 
         WHERE student_id = ? 
           AND scheduled_date = ?
           AND status != 'cancelada'
         ORDER BY scheduled_time ASC",
        [$lesson['student_id'], $lesson['scheduled_date']]
    )->fetchAll();
    echo "<p class='ok'>✓ Query de aulas consecutivas OK (" . count($allLessons) . " aulas no dia)</p>";
} catch (\Throwable $e) {
    echo "<p class='err'>✗ ERRO:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
echo "</div>";

echo "<div class='section'><h2>5. RescheduleRequest::findPendingByLessonAndStudent (se ALUNO)</h2>";
if ($currentRole === Constants::ROLE_ALUNO) {
    try {
        $rescheduleModel = new RescheduleRequest();
        $pending = $rescheduleModel->findPendingByLessonAndStudent($lessonId, $lesson['student_id']);
        echo "<p class='ok'>✓ OK</p>";
    } catch (\Throwable $e) {
        echo "<p class='err'>✗ ERRO:</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
} else {
    echo "<p>Pulado (não é ALUNO)</p>";
}
echo "</div>";

echo "<div class='section'><h2>6. Verificar coluna practice_type</h2>";
$db = null;
try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW COLUMNS FROM lessons LIKE 'practice_type'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='ok'>✓ Coluna practice_type existe</p>";
    } else {
        echo "<p class='err'>✗ Coluna practice_type NÃO existe — execute migration 043</p>";
    }
} catch (\Throwable $e) {
    echo "<p class='err'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

echo "<div class='section'><h2>7. Verificar tabelas theory_*</h2>";
if (!$db) {
    try { $db = \App\Config\Database::getInstance()->getConnection(); } catch (\Throwable $e) { $db = null; }
}
if ($db) {
$theoryTables = ['theory_sessions', 'theory_disciplines', 'theory_classes', 'theory_courses'];
foreach ($theoryTables as $t) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$t'");
        echo $stmt->rowCount() ? "<p class='ok'>✓ $t</p>" : "<p class='err'>✗ $t não existe</p>";
    } catch (\Throwable $e) {
        echo "<p class='err'>✗ $t: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
} else {
    echo "<p>Banco não disponível para verificação</p>";
}
echo "</div>";
?>
</body>
</html>
