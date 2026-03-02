<?php
// Script para verificar categorias das aulas - acessível via web
require_once __DIR__ . '/../../app/Config/Env.php';
\App\Config\Env::load();

header('Content-Type: text/plain; charset=utf-8');

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? 3306;
$dbname = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASS'] ?? '';

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "=== VERIFICAÇÃO: CATEGORIAS DAS AULAS ===\n\n";
    
    // Verificar aulas do aluno teste programa
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.aluno_id,
            a.enrollment_id,
            a.lesson_category_id,
            a.data_aula,
            a.hora_inicio,
            al.nome as aluno_nome,
            lc.code as categoria_code,
            lc.name as categoria_name
        FROM aulas a
        JOIN alunos al ON a.aluno_id = al.id
        LEFT JOIN lesson_categories lc ON a.lesson_category_id = lc.id
        WHERE al.nome LIKE '%teste programa%'
        ORDER BY a.data_aula DESC, a.hora_inicio DESC
        LIMIT 10
    ");
    $stmt->execute();
    $aulas = $stmt->fetchAll();
    
    echo "Aulas do aluno 'teste programa':\n";
    echo str_repeat('-', 80) . "\n";
    
    if (empty($aulas)) {
        echo "❌ Nenhuma aula encontrada.\n";
    } else {
        foreach ($aulas as $aula) {
            $categoria = $aula['categoria_code'] 
                ? "✅ {$aula['categoria_name']} ({$aula['categoria_code']})" 
                : "❌ SEM CATEGORIA (lesson_category_id = NULL)";
            
            echo sprintf(
                "Aula #%d - %s %s - Matrícula #%s - %s\n",
                $aula['id'],
                $aula['data_aula'],
                $aula['hora_inicio'],
                $aula['enrollment_id'] ?? 'N/A',
                $categoria
            );
        }
    }
    
    echo "\n" . str_repeat('-', 80) . "\n\n";
    
    // Verificar se há quotas para as matrículas
    echo "Quotas das matrículas:\n";
    echo str_repeat('-', 80) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            e.id as enrollment_id,
            e.service_name,
            e.aulas_contratadas,
            COUNT(q.id) as quotas_count
        FROM enrollments e
        JOIN alunos al ON e.student_id = al.id
        LEFT JOIN enrollment_lesson_quotas q ON e.id = q.enrollment_id
        WHERE al.nome LIKE '%teste programa%'
        GROUP BY e.id
        ORDER BY e.id DESC
    ");
    $stmt->execute();
    $matriculas = $stmt->fetchAll();
    
    foreach ($matriculas as $mat) {
        echo sprintf(
            "Matrícula #%d - %s - %d aulas contratadas - %d quotas\n",
            $mat['enrollment_id'],
            $mat['service_name'],
            $mat['aulas_contratadas'] ?? 0,
            $mat['quotas_count']
        );
        
        // Buscar detalhes das quotas
        if ($mat['quotas_count'] > 0) {
            $stmtQuotas = $pdo->prepare("
                SELECT lc.code, lc.name, q.quantity
                FROM enrollment_lesson_quotas q
                JOIN lesson_categories lc ON q.lesson_category_id = lc.id
                WHERE q.enrollment_id = ?
            ");
            $stmtQuotas->execute([$mat['enrollment_id']]);
            $quotas = $stmtQuotas->fetchAll();
            
            foreach ($quotas as $quota) {
                echo "  → {$quota['name']} ({$quota['code']}): {$quota['quantity']} aulas\n";
            }
        }
    }
    
    echo "\n=== FIM DA VERIFICAÇÃO ===\n";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
