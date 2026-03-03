<?php
require_once __DIR__ . '/../app/autoload.php';
use App\Config\Database;

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== DIAGNÓSTICO ===\n\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM students");
    $r = $stmt->fetch();
    echo "Total alunos: " . $r['total'] . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL");
    $r = $stmt->fetch();
    echo "Total matrículas: " . $r['total'] . "\n";
    
    $stmt = $db->query("SELECT s.id, s.name, e.id as enroll_id FROM students s LEFT JOIN enrollments e ON e.student_id = s.id AND e.deleted_at IS NULL LIMIT 5");
    echo "\nPrimeiros 5 alunos:\n";
    while ($row = $stmt->fetch()) {
        echo "  ID: {$row['id']} | Nome: {$row['name']} | Enrollment: " . ($row['enroll_id'] ?? 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}
