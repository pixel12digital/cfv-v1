<?php
/**
 * Script para atualizar o status dos alunos baseado em suas matrículas
 * 
 * Lógica:
 * - matriculado: Tem matrícula ativa mas não iniciou aulas
 * - em_andamento: Tem matrícula ativa e já fez pelo menos 1 aula
 * - concluido: Matrícula concluída
 * - cancelado: Matrícula cancelada (e não tem outra matrícula ativa)
 * - sem_matricula: Aluno cadastrado mas sem matrícula ativa
 */

// Conectar diretamente ao banco remoto
try {
    $db = new PDO(
        'mysql:host=auth-db803.hstgr.io;port=3306;dbname=u502697186_cfcv1;charset=utf8mb4',
        'u502697186_cfcv1',
        'Los@ngo#081081',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10
        ]
    );
    
    echo "=== ATUALIZANDO STATUS DOS ALUNOS ===\n\n";
    
    // 1. Alunos SEM matrícula ativa = sem_matricula
    $sql = "UPDATE students s
            SET s.status = 'sem_matricula'
            WHERE NOT EXISTS (
                SELECT 1 FROM enrollments e 
                WHERE e.student_id = s.id 
                AND e.deleted_at IS NULL 
                AND e.status IN ('ativa', 'concluida')
            )
            AND s.status NOT IN ('sem_matricula', 'cancelado')";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $semMatriculaAtualizados = $stmt->rowCount();
    echo "✅ Alunos atualizados para 'sem_matricula': $semMatriculaAtualizados\n";
    
    // 2. Alunos COM matrícula ativa mas SEM aulas = matriculado
    $sql = "UPDATE students s
            INNER JOIN enrollments e ON e.student_id = s.id
            SET s.status = 'matriculado'
            WHERE e.deleted_at IS NULL
            AND e.status = 'ativa'
            AND NOT EXISTS (
                SELECT 1 FROM lessons l 
                WHERE l.student_id = s.id 
                AND l.enrollment_id = e.id
                AND l.status = 'concluida'
            )
            AND s.status != 'matriculado'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $matriculadosAtualizados = $stmt->rowCount();
    echo "✅ Alunos atualizados para 'matriculado': $matriculadosAtualizados\n";
    
    // 3. Alunos COM matrícula ativa e COM aulas realizadas = em_andamento
    $sql = "UPDATE students s
            INNER JOIN enrollments e ON e.student_id = s.id
            SET s.status = 'em_andamento'
            WHERE e.deleted_at IS NULL
            AND e.status = 'ativa'
            AND EXISTS (
                SELECT 1 FROM lessons l 
                WHERE l.student_id = s.id 
                AND l.enrollment_id = e.id
                AND l.status = 'concluida'
            )
            AND s.status != 'em_andamento'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $emAndamentoAtualizados = $stmt->rowCount();
    echo "✅ Alunos atualizados para 'em_andamento': $emAndamentoAtualizados\n";
    
    // 4. Alunos COM matrícula concluída = concluido
    $sql = "UPDATE students s
            INNER JOIN enrollments e ON e.student_id = s.id
            SET s.status = 'concluido'
            WHERE e.deleted_at IS NULL
            AND e.status = 'concluida'
            AND s.status != 'concluido'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $concluidosAtualizados = $stmt->rowCount();
    echo "✅ Alunos atualizados para 'concluido': $concluidosAtualizados\n";
    
    // 5. Alunos COM matrícula cancelada (e sem outras ativas) = cancelado
    $sql = "UPDATE students s
            SET s.status = 'cancelado'
            WHERE EXISTS (
                SELECT 1 FROM enrollments e 
                WHERE e.student_id = s.id 
                AND e.deleted_at IS NULL 
                AND e.status = 'cancelada'
            )
            AND NOT EXISTS (
                SELECT 1 FROM enrollments e2 
                WHERE e2.student_id = s.id 
                AND e2.deleted_at IS NULL 
                AND e2.status IN ('ativa', 'concluida')
            )
            AND s.status != 'cancelado'";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $canceladosAtualizados = $stmt->rowCount();
    echo "✅ Alunos atualizados para 'cancelado': $canceladosAtualizados\n";
    
    // 6. Converter todos os 'lead' antigos para 'sem_matricula'
    $sql = "UPDATE students SET status = 'sem_matricula' WHERE status = 'lead'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $leadConvertidos = $stmt->rowCount();
    if ($leadConvertidos > 0) {
        echo "✅ Convertidos 'lead' para 'sem_matricula': $leadConvertidos\n";
    }
    
    // Mostrar distribuição final
    echo "\n=== DISTRIBUIÇÃO FINAL DE STATUS ===\n";
    $stmt = $db->query("SELECT status, COUNT(*) as total FROM students GROUP BY status ORDER BY total DESC");
    $distribuicao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($distribuicao as $row) {
        echo "  - " . ucfirst($row['status']) . ": " . $row['total'] . "\n";
    }
    
    echo "\n✅ Atualização concluída com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
