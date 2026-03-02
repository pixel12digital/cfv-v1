<?php
require_once __DIR__ . '/app/Config/Env.php';
\App\Config\Env::load();

$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10
    ]);
    
    echo "=== VERIFICAÇÃO: CATEGORIAS DAS AULAS ===\n\n";
    
    // 1. Verificar aulas
    echo "1. Aulas do teste programa:\n";
    $stmt = $pdo->query("
        SELECT l.id, l.scheduled_date, l.scheduled_time, l.lesson_category_id,
               lc.code, lc.name
        FROM lessons l
        JOIN students s ON l.student_id = s.id
        LEFT JOIN lesson_categories lc ON l.lesson_category_id = lc.id
        WHERE s.name LIKE '%teste%'
        ORDER BY l.scheduled_date DESC
        LIMIT 5
    ");
    
    foreach ($stmt->fetchAll() as $row) {
        $cat = $row['code'] ? "{$row['name']} ({$row['code']})" : "SEM CATEGORIA";
        echo "  Aula #{$row['id']} - {$row['scheduled_date']} {$row['scheduled_time']} - {$cat}\n";
    }
    
    // 2. Verificar quotas
    echo "\n2. Quotas das matrículas:\n";
    $stmt = $pdo->query("
        SELECT e.id, COUNT(q.id) as qtd_quotas
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        LEFT JOIN enrollment_lesson_quotas q ON e.id = q.enrollment_id
        WHERE s.name LIKE '%teste%'
        GROUP BY e.id
    ");
    
    foreach ($stmt->fetchAll() as $row) {
        echo "  Matrícula #{$row['id']} - {$row['qtd_quotas']} quotas\n";
        
        if ($row['qtd_quotas'] > 0) {
            $q = $pdo->prepare("
                SELECT lc.code, lc.name, q.quantity
                FROM enrollment_lesson_quotas q
                JOIN lesson_categories lc ON q.lesson_category_id = lc.id
                WHERE q.enrollment_id = ?
            ");
            $q->execute([$row['id']]);
            foreach ($q->fetchAll() as $quota) {
                echo "    → {$quota['name']} ({$quota['code']}): {$quota['quantity']} aulas\n";
            }
        }
    }
    
    echo "\n=== FIM ===\n";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
