<?php
try {
    $pdo = new PDO(
        'mysql:host=auth-db803.hstgr.io;port=3306;dbname=u502697186_cfcv1;charset=utf8mb4',
        'u502697186_cfcv1',
        'Los@ngo#081081',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10
        ]
    );
    
    echo "=== CONEXÃO ESTABELECIDA COM SUCESSO ===\n\n";
    
    // Verificar quais tabelas existem
    echo "=== TABELAS EXISTENTES NO BANCO ===\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    echo "\n";
    
    // Verificar tabela students
    echo "=== VERIFICAÇÃO DA TABELA STUDENTS ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
    $totalStudents = $stmt->fetch()['total'];
    echo "📊 Total de registros na tabela 'students': $totalStudents\n";
    
    if ($totalStudents > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'ativo'");
        $studentsAtivos = $stmt->fetch()['total'];
        echo "✅ Students ativos: $studentsAtivos\n";
        
        // Verificar alguns registros
        $stmt = $pdo->query("SELECT id, name, cpf, status, created_at FROM students LIMIT 5");
        $samples = $stmt->fetchAll();
        echo "\nAmostras de registros:\n";
        foreach ($samples as $s) {
            echo "  - ID: {$s['id']}, Nome: {$s['name']}, Status: {$s['status']}\n";
        }
    }
    echo "\n";
    
    // Verificar tabela enrollments
    echo "=== VERIFICAÇÃO DA TABELA ENROLLMENTS ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL");
    $totalEnrollments = $stmt->fetch()['total'];
    echo "📝 Total de matrículas (enrollments): $totalEnrollments\n";
    
    if ($totalEnrollments > 0) {
        $stmt = $pdo->query("SELECT status, COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL GROUP BY status");
        $enrollmentsByStatus = $stmt->fetchAll();
        echo "Por status:\n";
        foreach ($enrollmentsByStatus as $e) {
            echo "  - {$e['status']}: {$e['total']}\n";
        }
    }
    echo "\n";
    
    // Verificar total de alunos na tabela usuarios com role ALUNO
    echo "=== VERIFICAÇÃO DA TABELA USUARIOS (LEGADO) ===\n";
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as total
        FROM usuarios u
        INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
        WHERE ur.role = 'ALUNO'
    ");
    $totalAlunos = $stmt->fetch()['total'];
    echo "📊 Total de alunos cadastrados (tabela usuarios): $totalAlunos\n\n";
    
    // Verificar alunos ativos
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as total
        FROM usuarios u
        INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
        WHERE ur.role = 'ALUNO' AND u.status = 'ativo'
    ");
    $alunosAtivos = $stmt->fetch()['total'];
    echo "✅ Alunos ativos: $alunosAtivos\n";
    
    // Verificar alunos inativos
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as total
        FROM usuarios u
        INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
        WHERE ur.role = 'ALUNO' AND u.status = 'inativo'
    ");
    $alunosInativos = $stmt->fetch()['total'];
    echo "❌ Alunos inativos: $alunosInativos\n\n";
    
    // Verificar estrutura da tabela enrollments
    echo "=== ESTRUTURA DA TABELA ENROLLMENTS ===\n";
    $stmt = $pdo->query("DESCRIBE enrollments");
    $columns = $stmt->fetchAll();
    echo "Colunas disponíveis:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
    
    // Testar a query CORRIGIDA do relatório
    echo "=== TESTANDO QUERY DO RELATÓRIO (CORRIGIDA) ===\n";
    $sql = "
        SELECT 
            s.id,
            s.name AS aluno_nome,
            s.cpf,
            s.phone,
            s.email,
            s.status AS aluno_status,
            s.created_at AS data_cadastro,
            c.nome AS cfc_nome,
            e.id AS enrollment_id,
            srv.name AS service_name,
            e.status AS enrollment_status,
            e.financial_status,
            e.aulas_contratadas,
            e.created_at AS data_matricula,
            
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND (e.id IS NULL OR l.enrollment_id = e.id)
             AND l.status = 'concluida'
            ) AS aulas_realizadas,
            
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND (e.id IS NULL OR l.enrollment_id = e.id)
             AND l.status IN ('agendada', 'em_andamento')
             AND (l.scheduled_date > CURDATE() OR (l.scheduled_date = CURDATE() AND l.scheduled_time >= CURTIME()))
            ) AS aulas_agendadas,
            
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND (e.id IS NULL OR l.enrollment_id = e.id)
             AND l.status IN ('cancelada', 'no_show')
            ) AS aulas_canceladas,
            
            (SELECT COALESCE(SUM(elq.quantity), 0)
             FROM enrollment_lesson_quotas elq
             WHERE elq.enrollment_id = e.id
            ) AS total_quotas
            
        FROM students s
        LEFT JOIN cfcs c ON s.cfc_id = c.id
        LEFT JOIN enrollments e ON e.student_id = s.id 
            AND e.deleted_at IS NULL
        LEFT JOIN services srv ON e.service_id = srv.id
        ORDER BY s.name ASC
        LIMIT 5
    ";
    
    try {
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();
        echo "✅ Query executada com sucesso!\n";
        echo "📊 Resultados retornados: " . count($results) . "\n\n";
        
        if (count($results) > 0) {
            echo "Primeiros registros:\n";
            foreach ($results as $r) {
                echo "  - ID: {$r['id']}, Nome: {$r['aluno_nome']}, Status: {$r['aluno_status']}, ";
                echo "Matrícula: " . ($r['enrollment_id'] ? "#{$r['enrollment_id']}" : "SEM") . ", ";
                echo "Aulas: {$r['aulas_contratadas']} contratadas, {$r['aulas_realizadas']} realizadas\n";
            }
        } else {
            echo "⚠️ Nenhum resultado retornado!\n";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao executar query: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Verificar se existe tabela alunos (legado)
    $stmt = $pdo->query("SHOW TABLES LIKE 'alunos'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM alunos");
        $totalAlunosLegado = $stmt->fetch()['total'];
        echo "\n📋 Total de registros na tabela 'alunos' (legado): $totalAlunosLegado\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM alunos WHERE ativo = 1");
        $alunosLegadoAtivos = $stmt->fetch()['total'];
        echo "✅ Alunos ativos (tabela legado): $alunosLegadoAtivos\n";
    }
    
    // Verificar tabela sessoes
    echo "\n=== VERIFICAÇÃO DE TABELAS CRÍTICAS ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'sessoes'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela 'sessoes' existe\n";
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM sessoes");
        $totalSessoes = $stmt->fetch()['total'];
        echo "   Total de sessões registradas: $totalSessoes\n";
    } else {
        echo "❌ Tabela 'sessoes' NÃO existe (problema crítico para login)\n";
    }
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
