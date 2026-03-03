<?php
// Diagnóstico do Relatório de Alunos - Banco Remoto
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $conn = getConnection();
    
    echo "<pre>";
    echo "=== DIAGNÓSTICO DO RELATÓRIO DE ALUNOS ===\n\n";
    
    // 1. Total de alunos
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
    $row = mysqli_fetch_assoc($result);
    echo "1. Total de alunos: " . $row['total'] . "\n";
    
    // 2. Total de matrículas ativas
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL");
    $row = mysqli_fetch_assoc($result);
    echo "2. Total de matrículas ativas: " . $row['total'] . "\n";
    
    // 3. Matrículas no período
    $sql = "SELECT COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL AND created_at >= '2025-12-01 00:00:00' AND created_at <= '2026-03-03 23:59:59'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    echo "3. Matrículas no período (01/12/2025 a 03/03/2026): " . $row['total'] . "\n\n";
    
    // 4. Últimas 10 matrículas
    echo "4. Últimas 10 matrículas criadas:\n";
    $sql = "
        SELECT 
            e.id,
            s.name as aluno_nome,
            s.status as aluno_status,
            e.created_at,
            e.deleted_at
        FROM enrollments e
        LEFT JOIN students s ON e.student_id = s.id
        ORDER BY e.created_at DESC
        LIMIT 10
    ";
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo sprintf(
            "   ID: %d | Aluno: %s | Status: %s | Criada: %s | Deletada: %s\n",
            $row['id'],
            $row['aluno_nome'] ?? 'N/A',
            $row['aluno_status'] ?? 'N/A',
            $row['created_at'],
            $row['deleted_at'] ?? 'NÃO'
        );
    }
    
    echo "\n5. Query COM filtros de data (igual ao relatório):\n";
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
    $result = mysqli_query($conn, $sql);
    
    $count = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $count++;
        echo sprintf(
            "   %d. Aluno: %s | Status: %s | Matrícula: %s\n",
            $count,
            $row['aluno_nome'],
            $row['aluno_status'],
            $row['data_matricula']
        );
    }
    echo "   Total de resultados: $count\n";
    
    echo "\n=== FIM DO DIAGNÓSTICO ===\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<pre>ERRO: " . $e->getMessage() . "</pre>";
}
