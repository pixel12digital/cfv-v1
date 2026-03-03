<?php
// Script de teste para verificar dados no banco
require_once __DIR__ . '/app/Config/Database.php';

use App\Config\Database;

$db = Database::getInstance()->getConnection();

echo "=== DIAGNÓSTICO DO BANCO DE DADOS ===\n\n";

// 1. Total de alunos
$stmt = $db->query("SELECT COUNT(*) as total FROM students");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "1. Total de alunos: " . $result['total'] . "\n";

// 2. Total de matrículas ativas
$stmt = $db->query("SELECT COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "2. Total de matrículas ativas: " . $result['total'] . "\n";

// 3. Matrículas no período
$stmt = $db->prepare("SELECT COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL AND created_at >= ? AND created_at <= ?");
$stmt->execute(['2025-12-01 00:00:00', '2026-03-03 23:59:59']);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "3. Matrículas no período (01/12/2025 a 03/03/2026): " . $result['total'] . "\n\n";

// 4. Amostra de matrículas (últimas 10)
echo "4. Últimas 10 matrículas criadas:\n";
$stmt = $db->query("
    SELECT 
        e.id,
        e.student_id,
        s.name as aluno_nome,
        s.status as aluno_status,
        e.created_at,
        e.deleted_at
    FROM enrollments e
    LEFT JOIN students s ON e.student_id = s.id
    ORDER BY e.created_at DESC
    LIMIT 10
");
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($enrollments as $enroll) {
    echo sprintf(
        "   ID: %d | Aluno: %s (Status: %s) | Criada em: %s | Deletada: %s\n",
        $enroll['id'],
        $enroll['aluno_nome'],
        $enroll['aluno_status'],
        $enroll['created_at'],
        $enroll['deleted_at'] ?? 'NÃO'
    );
}

echo "\n5. Testando a query do relatório SEM filtros de data:\n";
$sql = "
    SELECT 
        s.id,
        s.name AS aluno_nome,
        s.status AS aluno_status,
        e.id AS enrollment_id,
        e.created_at AS data_matricula
    FROM students s
    LEFT JOIN cfcs c ON s.cfc_id = c.id
    LEFT JOIN enrollments e ON e.student_id = s.id 
        AND e.deleted_at IS NULL
    ORDER BY s.name ASC
    LIMIT 10
";
$stmt = $db->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Resultados encontrados: " . count($results) . "\n";
foreach ($results as $row) {
    echo sprintf(
        "   Aluno: %s | Status: %s | Enrollment ID: %s | Data Matrícula: %s\n",
        $row['aluno_nome'],
        $row['aluno_status'],
        $row['enrollment_id'] ?? 'NULL',
        $row['data_matricula'] ?? 'NULL'
    );
}

echo "\n6. Testando a query COM filtros de data:\n";
$sql = "
    SELECT 
        s.id,
        s.name AS aluno_nome,
        s.status AS aluno_status,
        e.id AS enrollment_id,
        e.created_at AS data_matricula
    FROM students s
    LEFT JOIN cfcs c ON s.cfc_id = c.id
    LEFT JOIN enrollments e ON e.student_id = s.id 
        AND e.deleted_at IS NULL
    WHERE e.id IS NOT NULL 
        AND e.created_at >= '2025-12-01 00:00:00' 
        AND e.created_at <= '2026-03-03 23:59:59'
    ORDER BY s.name ASC
    LIMIT 10
";
$stmt = $db->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Resultados encontrados: " . count($results) . "\n";
foreach ($results as $row) {
    echo sprintf(
        "   Aluno: %s | Status: %s | Enrollment ID: %s | Data Matrícula: %s\n",
        $row['aluno_nome'],
        $row['aluno_status'],
        $row['enrollment_id'],
        $row['data_matricula']
    );
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
