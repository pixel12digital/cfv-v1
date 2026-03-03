<?php
/**
 * Diagnóstico do Relatório de Alunos por Status
 * Acesse via: http://localhost/cfc-v.1/tools/diagnostico_relatorio_alunos.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/autoload.php';

use App\Config\Env;
use App\Config\Database;

Env::load();

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || 
           strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

if (!$isLocal) {
    die('⚠️ Este script só pode ser executado em ambiente local!');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico - Relatório de Alunos</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .success { background: #d4edda; padding: 10px; margin: 10px 0; }
        .error { background: #f8d7da; padding: 10px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico do Relatório de Alunos por Status</h1>

    <?php
    try {
        $db = Database::getInstance()->getConnection();
        
        echo '<div class="card">';
        echo '<h2>1. Estatísticas Gerais</h2>';
        
        // Total de alunos
        $stmt = $db->query("SELECT COUNT(*) as total FROM students");
        $result = $stmt->fetch();
        echo "<p><strong>Total de alunos:</strong> " . $result['total'] . "</p>";
        
        // Total de matrículas ativas
        $stmt = $db->query("SELECT COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL");
        $result = $stmt->fetch();
        echo "<p><strong>Total de matrículas ativas:</strong> " . $result['total'] . "</p>";
        
        // Matrículas no período
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM enrollments WHERE deleted_at IS NULL AND created_at >= ? AND created_at <= ?");
        $stmt->execute(['2025-12-01 00:00:00', '2026-03-03 23:59:59']);
        $result = $stmt->fetch();
        echo "<p><strong>Matrículas no período (01/12/2025 a 03/03/2026):</strong> " . $result['total'] . "</p>";
        
        echo '</div>';
        
        // Últimas 10 matrículas
        echo '<div class="card">';
        echo '<h2>2. Últimas 10 Matrículas Criadas</h2>';
        echo '<pre>';
        
        $stmt = $db->query("
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
        ");
        
        while ($row = $stmt->fetch()) {
            printf(
                "ID: %d | Aluno: %s | Status: %s | Criada: %s | Deletada: %s\n",
                $row['id'],
                $row['aluno_nome'] ?? 'N/A',
                $row['aluno_status'] ?? 'N/A',
                $row['created_at'],
                $row['deleted_at'] ?? 'NÃO'
            );
        }
        
        echo '</pre>';
        echo '</div>';
        
        // Query COM filtros de data
        echo '<div class="card">';
        echo '<h2>3. Teste da Query do Relatório (COM filtros de data)</h2>';
        echo '<p><strong>Query executada:</strong></p>';
        echo '<pre>';
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
LIMIT 10";
        echo htmlspecialchars($sql);
        echo '</pre>';
        
        echo '<p><strong>Resultados:</strong></p>';
        echo '<pre>';
        
        $stmt = $db->query($sql);
        $count = 0;
        
        while ($row = $stmt->fetch()) {
            $count++;
            printf(
                "%d. Aluno: %s | Status: %s | Matrícula: %s\n",
                $count,
                $row['aluno_nome'],
                $row['aluno_status'],
                $row['data_matricula']
            );
        }
        
        if ($count === 0) {
            echo "❌ NENHUM RESULTADO ENCONTRADO!\n\n";
            echo "Isso significa que NÃO EXISTEM matrículas no período de 01/12/2025 a 03/03/2026.\n";
            echo "Tente remover os filtros de data ou ampliar o período.";
        } else {
            echo "\n✅ Total de resultados: $count";
        }
        
        echo '</pre>';
        echo '</div>';
        
        // Query SEM filtros de data
        echo '<div class="card">';
        echo '<h2>4. Teste da Query SEM Filtros de Data</h2>';
        echo '<pre>';
        
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
LIMIT 10";
        
        $stmt = $db->query($sql);
        $count = 0;
        
        while ($row = $stmt->fetch()) {
            $count++;
            printf(
                "%d. Aluno: %s | Status: %s | Enrollment: %s | Data: %s\n",
                $count,
                $row['aluno_nome'],
                $row['aluno_status'],
                $row['enrollment_id'] ?? 'NULL',
                $row['data_matricula'] ?? 'NULL'
            );
        }
        
        echo "\n✅ Total de resultados: $count";
        echo '</pre>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="card">';
        echo '<div class="error">❌ ERRO: ' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '</div>';
    }
    ?>

    <div class="card">
        <h2>💡 Conclusão</h2>
        <p>Se a query COM filtros retornar 0 resultados, mas a query SEM filtros retornar resultados, 
        significa que não existem matrículas no período específico que você está filtrando.</p>
        <p><strong>Solução:</strong> Remova os filtros de data ou amplie o período no relatório.</p>
    </div>

</body>
</html>
