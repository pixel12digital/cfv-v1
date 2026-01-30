<?php
/**
 * Diagnóstico Fase 0 — Validação de Fonte de Dados (Histórico Instrutor/Aluno)
 * 
 * Execute: php tools/diagnostico_fase0_historico.php
 * Ou via browser: /tools/diagnostico_fase0_historico.php
 * 
 * Objetivo: Determinar se estamos no Cenário A (lessons tem histórico) ou
 *           Cenário B (histórico só em aulas).
 */

// Carregar conexão do includes
require_once __DIR__ . '/../includes/database.php';

// Obter instância do banco
$db = Database::getInstance();

// Detectar ambiente
$isCli = php_sapi_name() === 'cli';
$br = $isCli ? "\n" : "<br>";
$hr = $isCli ? str_repeat('-', 60) . "\n" : "<hr>";

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnóstico Fase 0</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee;} table{border-collapse:collapse;margin:10px 0;} th,td{border:1px solid #444;padding:8px;text-align:left;} .ok{color:#4ade80;} .warn{color:#facc15;} .error{color:#f87171;} h1,h2{color:#60a5fa;}</style></head><body>";
    echo "<h1>🔍 Diagnóstico Fase 0 — Histórico Instrutor/Aluno</h1>";
}

echo $hr;
echo "=== FASE 0: VALIDAÇÃO DE FONTE DE DADOS ===$br$br";

// Verificar se as tabelas existem
echo "1. VERIFICANDO TABELAS...$br";

$tabelas = [
    'lessons' => false,
    'aulas' => false,
    'instructors' => false,
    'instrutores' => false,
    'students' => false,
    'alunos' => false,
];

foreach (array_keys($tabelas) as $tabela) {
    try {
        $check = $db->fetch("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$tabela]);
        $tabelas[$tabela] = ($check['cnt'] ?? 0) > 0;
    } catch (Exception $e) {
        $tabelas[$tabela] = false;
    }
    $status = $tabelas[$tabela] ? '✓ existe' : '✗ não existe';
    echo "   - $tabela: $status$br";
}

echo $br . $hr;

// 2. Contagens gerais
echo "2. CONTAGENS GERAIS (todas as aulas)...$br$br";

if ($tabelas['lessons']) {
    try {
        $lessonsTotal = $db->fetch("SELECT COUNT(*) as total FROM lessons");
        $lessonsByStatus = $db->fetchAll("SELECT status, COUNT(*) as total FROM lessons GROUP BY status ORDER BY total DESC");
        
        echo "   TABELA lessons:$br";
        echo "   - Total de registros: " . ($lessonsTotal['total'] ?? 0) . $br;
        echo "   - Por status:$br";
        foreach ($lessonsByStatus as $row) {
            echo "     • {$row['status']}: {$row['total']}$br";
        }
    } catch (Exception $e) {
        echo "   ERRO ao consultar lessons: " . $e->getMessage() . $br;
    }
}

if ($tabelas['aulas']) {
    try {
        $aulasTotal = $db->fetch("SELECT COUNT(*) as total FROM aulas");
        $aulasByStatus = $db->fetchAll("SELECT status, COUNT(*) as total FROM aulas GROUP BY status ORDER BY total DESC");
        
        echo "$br   TABELA aulas (legado):$br";
        echo "   - Total de registros: " . ($aulasTotal['total'] ?? 0) . $br;
        echo "   - Por status:$br";
        foreach ($aulasByStatus as $row) {
            echo "     • {$row['status']}: {$row['total']}$br";
        }
    } catch (Exception $e) {
        echo "   ERRO ao consultar aulas: " . $e->getMessage() . $br;
    }
}

echo $br . $hr;

// 3. Listar instrutores para escolher
echo "3. INSTRUTORES COM MAIS AULAS...$br$br";

if ($tabelas['instructors']) {
    try {
        $instrutores = $db->fetchAll("SELECT id, name FROM instructors ORDER BY name LIMIT 15");
        echo "   Tabela instructors:$br";
        if (count($instrutores) > 0) {
            foreach ($instrutores as $i) {
                echo "   - ID {$i['id']}: {$i['name']}$br";
            }
        } else {
            echo "   (vazia)$br";
        }
    } catch (Exception $e) {
        echo "   ERRO: " . $e->getMessage() . $br;
    }
}

if ($tabelas['instrutores']) {
    try {
        $instrutoresLegado = $db->fetchAll("SELECT id, nome FROM instrutores ORDER BY nome LIMIT 15");
        echo "$br   Tabela instrutores (legado):$br";
        if (count($instrutoresLegado) > 0) {
            foreach ($instrutoresLegado as $i) {
                echo "   - ID {$i['id']}: {$i['nome']}$br";
            }
        } else {
            echo "   (vazia)$br";
        }
    } catch (Exception $e) {
        echo "   ERRO: " . $e->getMessage() . $br;
    }
}

echo $br . $hr;

// 4. Buscar instrutores com histórico em lessons vs aulas
echo "4. COMPARATIVO: INSTRUTORES COM AULAS CONCLUÍDAS...$br$br";

if ($tabelas['lessons'] && $tabelas['instructors']) {
    try {
        $topLessons = $db->fetchAll("
            SELECT i.id, i.name, COUNT(*) as total_concluidas
            FROM lessons l
            JOIN instructors i ON l.instructor_id = i.id
            WHERE l.status = 'concluida'
            GROUP BY i.id, i.name
            ORDER BY total_concluidas DESC
            LIMIT 10
        ");
        echo "   TOP 10 em lessons (concluídas):$br";
        if (count($topLessons) > 0) {
            foreach ($topLessons as $row) {
                echo "   - {$row['name']} (ID {$row['id']}): {$row['total_concluidas']} aulas$br";
            }
        } else {
            echo "   (nenhum instrutor com aulas concluídas em lessons)$br";
        }
    } catch (Exception $e) {
        echo "   ERRO lessons: " . $e->getMessage() . $br;
    }
}

if ($tabelas['aulas'] && $tabelas['instrutores']) {
    try {
        $topAulas = $db->fetchAll("
            SELECT i.id, i.nome, COUNT(*) as total_concluidas
            FROM aulas a
            JOIN instrutores i ON a.instrutor_id = i.id
            WHERE a.status = 'concluida'
            GROUP BY i.id, i.nome
            ORDER BY total_concluidas DESC
            LIMIT 10
        ");
        echo "$br   TOP 10 em aulas/legado (concluídas):$br";
        if (count($topAulas) > 0) {
            foreach ($topAulas as $row) {
                echo "   - {$row['nome']} (ID {$row['id']}): {$row['total_concluidas']} aulas$br";
            }
        } else {
            echo "   (nenhum instrutor com aulas concluídas em aulas)$br";
        }
    } catch (Exception $e) {
        echo "   ERRO aulas: " . $e->getMessage() . $br;
    }
}

echo $br . $hr;

// 5. Diagnóstico por instrutor (se informado)
$instrutorId = $_GET['instrutor_id'] ?? ($argv[1] ?? null);
$alunoId = $_GET['aluno_id'] ?? ($argv[2] ?? null);

if ($instrutorId) {
    echo "5. DIAGNÓSTICO DO INSTRUTOR ID=$instrutorId...$br$br";
    
    if ($tabelas['lessons']) {
        try {
            $lessonsConc = $db->fetch(
                "SELECT COUNT(*) as total FROM lessons WHERE instructor_id = ? AND status IN ('concluida', 'cancelada', 'no_show')", 
                [$instrutorId]
            );
            echo "   lessons (histórico = concluida/cancelada/no_show): " . ($lessonsConc['total'] ?? 0) . $br;
            
            $lessonsPorStatus = $db->fetchAll(
                "SELECT status, COUNT(*) as total FROM lessons WHERE instructor_id = ? GROUP BY status", 
                [$instrutorId]
            );
            foreach ($lessonsPorStatus as $row) {
                echo "     • {$row['status']}: {$row['total']}$br";
            }
            
            $ultimasLessons = $db->fetchAll(
                "SELECT id, student_id, scheduled_date, scheduled_time, status, completed_at 
                 FROM lessons 
                 WHERE instructor_id = ? AND status = 'concluida' 
                 ORDER BY scheduled_date DESC, scheduled_time DESC 
                 LIMIT 5", 
                [$instrutorId]
            );
            echo "$br   Últimas 5 concluídas em lessons:$br";
            if (count($ultimasLessons) > 0) {
                foreach ($ultimasLessons as $l) {
                    echo "     - ID {$l['id']}: {$l['scheduled_date']} {$l['scheduled_time']} (aluno_id={$l['student_id']})$br";
                }
            } else {
                echo "     (nenhuma)$br";
            }
        } catch (Exception $e) {
            echo "   ERRO lessons: " . $e->getMessage() . $br;
        }
    }
    
    if ($tabelas['aulas']) {
        try {
            echo "$br";
            $aulasConc = $db->fetch(
                "SELECT COUNT(*) as total FROM aulas WHERE instrutor_id = ? AND status = 'concluida'", 
                [$instrutorId]
            );
            echo "   aulas (legado, status=concluida): " . ($aulasConc['total'] ?? 0) . $br;
            
            $ultimasAulas = $db->fetchAll(
                "SELECT id, aluno_id, data_aula, hora_inicio, status 
                 FROM aulas 
                 WHERE instrutor_id = ? AND status = 'concluida' 
                 ORDER BY data_aula DESC, hora_inicio DESC 
                 LIMIT 5", 
                [$instrutorId]
            );
            echo "$br   Últimas 5 concluídas em aulas (legado):$br";
            if (count($ultimasAulas) > 0) {
                foreach ($ultimasAulas as $a) {
                    echo "     - ID {$a['id']}: {$a['data_aula']} {$a['hora_inicio']} (aluno_id={$a['aluno_id']})$br";
                }
            } else {
                echo "     (nenhuma)$br";
            }
        } catch (Exception $e) {
            echo "   ERRO aulas: " . $e->getMessage() . $br;
        }
    }
}

echo $br . $hr;

// 6. Diagnóstico por aluno (se informado)
if ($alunoId) {
    echo "6. DIAGNÓSTICO DO ALUNO ID=$alunoId...$br$br";
    
    if ($tabelas['lessons']) {
        try {
            $lessonsConcAluno = $db->fetch(
                "SELECT COUNT(*) as total FROM lessons WHERE student_id = ? AND status = 'concluida'", 
                [$alunoId]
            );
            $lessonsAgendAluno = $db->fetch(
                "SELECT COUNT(*) as total FROM lessons WHERE student_id = ? AND status IN ('agendada', 'em_andamento') AND (scheduled_date > CURDATE() OR (scheduled_date = CURDATE() AND scheduled_time > CURTIME()))", 
                [$alunoId]
            );
            echo "   lessons concluídas: " . ($lessonsConcAluno['total'] ?? 0) . $br;
            echo "   lessons agendadas (futuras): " . ($lessonsAgendAluno['total'] ?? 0) . $br;
        } catch (Exception $e) {
            echo "   ERRO lessons: " . $e->getMessage() . $br;
        }
    }
    
    if ($tabelas['aulas']) {
        try {
            $aulasConcAluno = $db->fetch(
                "SELECT COUNT(*) as total FROM aulas WHERE aluno_id = ? AND status = 'concluida'", 
                [$alunoId]
            );
            echo "   aulas (legado) concluídas: " . ($aulasConcAluno['total'] ?? 0) . $br;
        } catch (Exception $e) {
            echo "   ERRO aulas: " . $e->getMessage() . $br;
        }
    }
}

echo $br . $hr;

// 7. CONCLUSÃO
echo "7. CONCLUSÃO (CENÁRIO)...$br$br";

$lessonsHistorico = 0;
$aulasHistorico = 0;

if ($tabelas['lessons']) {
    try {
        $r = $db->fetch("SELECT COUNT(*) as total FROM lessons WHERE status = 'concluida'");
        $lessonsHistorico = $r['total'] ?? 0;
    } catch (Exception $e) {}
}

if ($tabelas['aulas']) {
    try {
        $r = $db->fetch("SELECT COUNT(*) as total FROM aulas WHERE status = 'concluida'");
        $aulasHistorico = $r['total'] ?? 0;
    } catch (Exception $e) {}
}

$lessonsTotal = $lessonsTotal['total'] ?? 0;
$aulasTotal = $aulasTotal['total'] ?? 0;

echo "   Resumo:$br";
echo "   - lessons total: $lessonsTotal | concluídas: $lessonsHistorico$br";
echo "   - aulas total: $aulasTotal | concluídas: $aulasHistorico$br$br";

if ($lessonsHistorico > 0) {
    echo "   ✅ CENÁRIO A: lessons TEM histórico ($lessonsHistorico concluídas).$br";
    echo "      → Foco: corrigir filtro de data na agenda e implementar contadores.$br";
    if ($aulasHistorico > $lessonsHistorico) {
        echo "      ⚠ Nota: aulas (legado) tem mais ($aulasHistorico). Pode haver dados duplicados.$br";
    }
} elseif ($aulasHistorico > 0) {
    echo "   ⚠ CENÁRIO B: lessons SEM histórico; aulas tem $aulasHistorico.$br";
    echo "      → Problema: conclusões estão indo só para aulas (legado).$br";
    echo "      → Ação: verificar qual fluxo o instrutor usa; garantir que app atualize lessons.$br";
} else {
    echo "   ℹ Nenhum histórico encontrado em lessons nem aulas.$br";
    echo "      → Sistema novo ou sem aulas concluídas ainda.$br";
}

echo $br . $hr;
echo "$br=== INSTRUÇÕES ===$br";
echo "Para diagnóstico de instrutor/aluno específico:$br";
echo "  - Via browser: ?instrutor_id=X&aluno_id=Y$br";
echo "  - Via CLI: php diagnostico_fase0_historico.php <instrutor_id> <aluno_id>$br";

if (!$isCli) {
    echo "</body></html>";
}
