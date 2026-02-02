<?php
/**
 * Verificação do schema do banco para /agenda/{id}
 * Executa: php tools/verificar_schema_agenda.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

// Carregar .env
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

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'cfc_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

echo "=== VERIFICAÇÃO SCHEMA BANCO - /agenda/{id} ===\n";
echo "Host: $host | DB: $dbname\n\n";

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "ERRO CONEXÃO: " . $e->getMessage() . "\n";
    exit(1);
}

$results = [];

// 1. Colunas da tabela lessons
echo "1. COLUNAS DA TABELA lessons:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM lessons");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "   " . implode(", ", $cols) . "\n";

$hasPracticeType = in_array('practice_type', $cols);
$hasTheorySessionId = in_array('theory_session_id', $cols);
$hasInstructorNotes = in_array('instructor_notes', $cols);

echo "   practice_type: " . ($hasPracticeType ? "EXISTE" : "NÃO EXISTE") . "\n";
echo "   theory_session_id: " . ($hasTheorySessionId ? "EXISTE" : "NÃO EXISTE") . "\n";
echo "   instructor_notes: " . ($hasInstructorNotes ? "EXISTE" : "NÃO EXISTE") . "\n\n";

// 2. Tabelas theory_*
echo "2. TABELAS theory_*:\n";
$theoryTables = ['theory_sessions', 'theory_disciplines', 'theory_classes', 'theory_courses'];
foreach ($theoryTables as $t) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$t'");
    $exists = $stmt->rowCount() > 0;
    echo "   $t: " . ($exists ? "EXISTE" : "NÃO EXISTE") . "\n";
}
echo "\n";

// 3. Tabela instructors
echo "3. TABELA instructors:\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'instructors'");
echo "   instructors: " . ($stmt->rowCount() > 0 ? "EXISTE" : "NÃO EXISTE") . "\n\n";

// 4. Aula ID 48
echo "4. AULA ID 48:\n";
$stmt = $pdo->prepare("SELECT id, cfc_id, student_id, enrollment_id, instructor_id, vehicle_id, type, status, theory_session_id FROM lessons WHERE id = ?");
$stmt->execute([48]);
$lesson = $stmt->fetch();
if ($lesson) {
    echo "   EXISTE:\n";
    foreach ($lesson as $k => $v) {
        echo "   - $k: " . (string)$v . "\n";
    }
} else {
    echo "   NÃO EXISTE\n";
}
echo "\n";

// 5. Total de aulas
$stmt = $pdo->query("SELECT COUNT(*) as c FROM lessons");
echo "5. TOTAL DE AULAS: " . $stmt->fetch()['c'] . "\n\n";

// 6. Testar query findWithDetails (simulada)
echo "6. TESTE QUERY findWithDetails(48):\n";
$sql = "SELECT l.*,
        COALESCE(s.full_name, s.name) as student_name, s.cpf as student_cpf,
        e.id as enrollment_id, e.financial_status,
        i.name as instructor_name,
        v.plate as vehicle_plate, v.model as vehicle_model,
        u.nome as created_by_name,
        uc.nome as canceled_by_name,
        td.name as theory_discipline_name,
        tc.name as theory_course_name,
        ts.location as theory_location
 FROM lessons l
 INNER JOIN students s ON l.student_id = s.id
 INNER JOIN enrollments e ON l.enrollment_id = e.id
 LEFT JOIN instructors i ON l.instructor_id = i.id
 LEFT JOIN vehicles v ON l.vehicle_id = v.id
 LEFT JOIN usuarios u ON l.created_by = u.id
 LEFT JOIN usuarios uc ON l.canceled_by = uc.id
 LEFT JOIN theory_sessions ts ON l.theory_session_id = ts.id
 LEFT JOIN theory_disciplines td ON ts.discipline_id = td.id
 LEFT JOIN theory_classes tcl ON ts.class_id = tcl.id
 LEFT JOIN theory_courses tc ON tcl.course_id = tc.id
 WHERE l.id = 48";

try {
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch();
    echo "   OK - Query executou sem erro\n";
    if ($row) {
        echo "   student_name: " . ($row['student_name'] ?? 'N/A') . "\n";
        echo "   instructor_name: " . ($row['instructor_name'] ?? 'N/A') . "\n";
    } else {
        echo "   Retornou 0 linhas (aula 48 não encontrada ou JOINs excluíram)\n";
    }
} catch (PDOException $e) {
    echo "   ERRO: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Testar query getStudentSummaryForInstructor (usa practice_type)
if ($lesson) {
    echo "7. TESTE QUERY getStudentSummaryForInstructor (usa practice_type):\n";
    $sql2 = "SELECT practice_type, COUNT(*) as total 
             FROM lessons 
             WHERE instructor_id = ? AND student_id = ? AND enrollment_id = ?
               AND status = 'concluida'
               AND (type = 'pratica' OR type IS NULL OR theory_session_id IS NULL)
               AND practice_type IS NOT NULL
             GROUP BY practice_type";
    try {
        $stmt = $pdo->prepare($sql2);
        $stmt->execute([$lesson['instructor_id'], $lesson['student_id'], $lesson['enrollment_id']]);
        $stmt->fetchAll();
        echo "   OK - Query executou sem erro\n";
    } catch (PDOException $e) {
        echo "   ERRO: " . $e->getMessage() . "\n";
    }
}

echo "\n=== FIM ===\n";
