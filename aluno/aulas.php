<?php
/**
 * REFACTOR 2025-11 - UX Minhas Aulas (aluno)
 * Melhoria de layout: próxima aula em destaque, filtros rápidos, abas por tipo, agrupamento por data e cards colapsáveis.
 * Lógica de dados e segurança mantidas conforme implementação original da FASE 1.
 * 
 * FASE 1 - AREA ALUNO PENDENCIAS - Página de Todas as Aulas do Aluno
 * Arquivo: aluno/aulas.php
 * 
 * Funcionalidades:
 * - Listar todas as aulas práticas do aluno (passadas e futuras)
 * - Listar todas as aulas teóricas das turmas em que o aluno está matriculado
 * - Filtros por período, tipo e status
 * - Visualização unificada ou por abas
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// FASE 1 - AREA ALUNO PENDENCIAS - Verificar autenticação específica para aluno
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'aluno') {
    header('Location: login.php');
    exit();
}

$db = db();

// FASE 1 - AREA ALUNO PENDENCIAS - Buscar dados do aluno
$aluno = $db->fetch("SELECT * FROM usuarios WHERE id = ? AND tipo = 'aluno'", [$user['id']]);

if (!$aluno) {
    header('Location: login.php');
    exit();
}

// FASE 1 - AREA ALUNO PENDENCIAS - Buscar o ID do aluno na tabela alunos usando getCurrentAlunoId()
$alunoId = getCurrentAlunoId($user['id']);

// FASE 1 - AREA ALUNO PENDENCIAS - Processar filtros (sempre, mesmo sem aluno_id)
// FASE 1.1 - UX AULAS ALUNO - Período padrão: Próximos 30 dias (ao invés de "todas")
$periodoFiltro = $_GET['periodo'] ?? 'proximos_30_dias';
$tipoFiltro = $_GET['tipo'] ?? 'todas';
$statusFiltro = $_GET['status'] ?? '';

if (!$alunoId) {
    $error = 'Aluno não encontrado no sistema. Entre em contato com a secretaria.';
    // Não continuar executando queries se não houver aluno_id válido
    $aulasPraticas = [];
    $aulasTeoricas = [];
    $stats = [
        'total' => 0,
        'praticas' => 0,
        'teoricas' => 0,
        'agendadas' => 0,
        'concluidas' => 0,
        'canceladas' => 0
    ];
    $proximaAula = null;
    $aulasAgrupadas = [];
    $aulasPassadas = [];
    $aulasFuturas = [];
    $totalAulasPassadas = 0;
    $aulasPassadasIniciaisAgrupadas = [];
    $aulasPassadasExtrasAgrupadas = [];
    $temMuitasPassadas = false;
    $gradeCurso = [];
} else {
    // FASE 1 - AREA ALUNO PENDENCIAS - Calcular datas baseadas no período
    $dataInicio = null;
    $dataFim = null;

    switch ($periodoFiltro) {
        case 'ultimos_7_dias':
            $dataInicio = date('Y-m-d', strtotime('-7 days'));
            $dataFim = date('Y-m-d');
            break;
        case 'ultimos_30_dias':
            $dataInicio = date('Y-m-d', strtotime('-30 days'));
            $dataFim = date('Y-m-d');
            break;
        case 'proximos_7_dias':
            $dataInicio = date('Y-m-d');
            $dataFim = date('Y-m-d', strtotime('+7 days'));
            break;
        case 'proximos_30_dias':
            $dataInicio = date('Y-m-d');
            $dataFim = date('Y-m-d', strtotime('+30 days'));
            break;
        case 'hoje':
            $dataInicio = date('Y-m-d');
            $dataFim = date('Y-m-d');
            break;
        case 'todas':
        default:
            // Por padrão, mostrar últimos 30 dias + próximos 30 dias
            $dataInicio = date('Y-m-d', strtotime('-30 days'));
            $dataFim = date('Y-m-d', strtotime('+30 days'));
            break;
    }

    // FASE 1 - AREA ALUNO PENDENCIAS - Buscar aulas práticas do aluno
    $aulasPraticas = [];
    if ($alunoId && ($tipoFiltro === 'todas' || $tipoFiltro === 'pratica')) {
        $sqlPraticas = "
            SELECT a.*, 
                   i.nome as instrutor_nome,
                   v.modelo as veiculo_modelo, 
                   v.placa as veiculo_placa
            FROM aulas a
            JOIN instrutores i ON a.instrutor_id = i.id
            LEFT JOIN veiculos v ON a.veiculo_id = v.id
            WHERE a.aluno_id = ?
              AND a.data_aula >= ?
              AND a.data_aula <= ?
        ";
        
        $paramsPraticas = [$alunoId, $dataInicio, $dataFim];
        
        if ($statusFiltro && in_array($statusFiltro, ['agendada', 'em_andamento', 'concluida', 'cancelada'])) {
            $sqlPraticas .= " AND a.status = ?";
            $paramsPraticas[] = $statusFiltro;
        }
        
        $sqlPraticas .= " ORDER BY a.data_aula ASC, a.hora_inicio ASC";
        
        $aulasPraticas = $db->fetchAll($sqlPraticas, $paramsPraticas);
    }

    // FASE 1 - AREA ALUNO PENDENCIAS - Buscar aulas teóricas das turmas do aluno
    $aulasTeoricas = [];
    if ($alunoId && ($tipoFiltro === 'todas' || $tipoFiltro === 'teorica')) {
        // Primeiro, buscar turmas em que o aluno está matriculado
        $turmasAluno = $db->fetchAll("
            SELECT tm.turma_id
            FROM turma_matriculas tm
            WHERE tm.aluno_id = ?
            AND tm.status IN ('matriculado', 'cursando', 'concluido')
        ", [$alunoId]);
        
        if (!empty($turmasAluno)) {
            $turmaIds = array_column($turmasAluno, 'turma_id');
            $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
            
            $sqlTeoricas = "
                SELECT 
                    taa.id,
                    taa.turma_id,
                    taa.disciplina,
                    taa.nome_aula,
                    taa.data_aula,
                    taa.hora_inicio,
                    taa.hora_fim,
                    taa.status,
                    taa.observacoes,
                    tt.nome as turma_nome,
                    i.nome as instrutor_nome,
                    s.nome as sala_nome
                FROM turma_aulas_agendadas taa
                JOIN turmas_teoricas tt ON taa.turma_id = tt.id
                LEFT JOIN instrutores i ON taa.instrutor_id = i.id
                LEFT JOIN salas s ON taa.sala_id = s.id
                WHERE taa.turma_id IN ($placeholders)
                  AND taa.data_aula >= ?
                  AND taa.data_aula <= ?
            ";
            
            $paramsTeoricas = array_merge($turmaIds, [$dataInicio, $dataFim]);
            
            // Aplicar filtro de status (adaptar para status de aulas teóricas)
            if ($statusFiltro) {
                if ($statusFiltro === 'concluida') {
                    $sqlTeoricas .= " AND taa.status = 'realizada'";
                } elseif ($statusFiltro === 'agendada') {
                    $sqlTeoricas .= " AND taa.status = 'agendada'";
                } elseif ($statusFiltro === 'cancelada') {
                    $sqlTeoricas .= " AND taa.status = 'cancelada'";
                }
            } else {
                // Por padrão, mostrar apenas agendadas e realizadas
                $sqlTeoricas .= " AND taa.status IN ('agendada', 'realizada')";
            }
            
            $sqlTeoricas .= " ORDER BY taa.data_aula ASC, taa.hora_inicio ASC";
            
            $aulasTeoricas = $db->fetchAll($sqlTeoricas, $paramsTeoricas);
        }
    }

    // REFACTOR 2025-11 - Encontrar próxima aula (futura mais próxima)
    $proximaAula = null;
    $hoje = date('Y-m-d');
    $agora = date('H:i:s');
    
    // Combinar todas as aulas e encontrar a próxima
    $todasAulas = [];
    
    foreach ($aulasPraticas as $aula) {
        $aula['tipo'] = 'pratica';
        $aula['tipo_label'] = 'Prática';
        $todasAulas[] = $aula;
    }
    
    foreach ($aulasTeoricas as $aula) {
        $aula['tipo'] = 'teorica';
        $aula['tipo_label'] = 'Teórica';
        $todasAulas[] = $aula;
    }
    
    // Ordenar por data e hora (mais próxima primeiro)
    usort($todasAulas, function($a, $b) {
        $dataA = $a['data_aula'] . ' ' . ($a['hora_inicio'] ?? '00:00:00');
        $dataB = $b['data_aula'] . ' ' . ($b['hora_inicio'] ?? '00:00:00');
        return strtotime($dataA) - strtotime($dataB);
    });
    
    // Encontrar primeira aula futura
    foreach ($todasAulas as $aula) {
        $dataAula = $aula['data_aula'];
        $horaAula = $aula['hora_inicio'] ?? '00:00:00';
        
        if ($dataAula > $hoje || ($dataAula === $hoje && $horaAula >= $agora)) {
            // FASE 2 - GRADE DO CURSO - Garantir que tipo e tipo_label estejam definidos
            if (!isset($aula['tipo'])) {
                $aula['tipo'] = 'teorica'; // Default, mas deveria estar definido
            }
            if (!isset($aula['tipo_label'])) {
                $aula['tipo_label'] = $aula['tipo'] === 'teorica' ? 'Teórica' : 'Prática';
            }
            $proximaAula = $aula;
            break;
        }
    }

    // REFACTOR 2025-11 - Agrupar aulas por data
    $aulasAgrupadas = [];
    $todasAulasParaAgrupar = [];
    
    foreach ($aulasPraticas as $aula) {
        $aula['tipo'] = 'pratica';
        $aula['tipo_label'] = 'Prática';
        $todasAulasParaAgrupar[] = $aula;
    }
    
    foreach ($aulasTeoricas as $aula) {
        $aula['tipo'] = 'teorica';
        $aula['tipo_label'] = 'Teórica';
        $todasAulasParaAgrupar[] = $aula;
    }
    
    // Ordenar por data e hora
    usort($todasAulasParaAgrupar, function($a, $b) {
        $dataA = $a['data_aula'] . ' ' . ($a['hora_inicio'] ?? '00:00:00');
        $dataB = $b['data_aula'] . ' ' . ($b['hora_inicio'] ?? '00:00:00');
        return strtotime($dataA) - strtotime($dataB);
    });
    
    // FASE 1.1 - UX AULAS ALUNO - Separar aulas passadas das futuras para renderização
    $hoje = date('Y-m-d');
    $aulasPassadas = [];
    $aulasFuturas = [];
    
    // Agrupar por data e separar passadas/futuras
    foreach ($todasAulasParaAgrupar as $aula) {
        $data = $aula['data_aula'];
        if ($data < $hoje) {
            // Aula passada
            if (!isset($aulasPassadas[$data])) {
                $aulasPassadas[$data] = [];
            }
            $aulasPassadas[$data][] = $aula;
        } else {
            // Aula futura ou hoje
            if (!isset($aulasFuturas[$data])) {
                $aulasFuturas[$data] = [];
            }
            $aulasFuturas[$data][] = $aula;
        }
    }
    
    // Manter $aulasAgrupadas para compatibilidade (todas as aulas)
    $aulasAgrupadas = array_merge($aulasPassadas, $aulasFuturas);
    
    // FASE 1.1 - UX AULAS ALUNO - Preparar aulas passadas para "Ver mais"
    $totalAulasPassadas = 0;
    $aulasPassadasFlat = [];
    foreach ($aulasPassadas as $data => $aulas) {
        $totalAulasPassadas += count($aulas);
        foreach ($aulas as $aula) {
            $aulasPassadasFlat[] = ['data' => $data, 'aula' => $aula];
        }
    }
    
    $limiteInicialPassadas = 10;
    $temMuitasPassadas = count($aulasPassadasFlat) > $limiteInicialPassadas;
    
    $aulasPassadasIniciais = $temMuitasPassadas
        ? array_slice($aulasPassadasFlat, 0, $limiteInicialPassadas)
        : $aulasPassadasFlat;
    
    $aulasPassadasExtras = $temMuitasPassadas
        ? array_slice($aulasPassadasFlat, $limiteInicialPassadas)
        : [];
    
    // Reagrupar aulas passadas iniciais por data
    $aulasPassadasIniciaisAgrupadas = [];
    foreach ($aulasPassadasIniciais as $item) {
        $data = $item['data'];
        if (!isset($aulasPassadasIniciaisAgrupadas[$data])) {
            $aulasPassadasIniciaisAgrupadas[$data] = [];
        }
        $aulasPassadasIniciaisAgrupadas[$data][] = $item['aula'];
    }
    
    // Reagrupar aulas passadas extras por data
    $aulasPassadasExtrasAgrupadas = [];
    foreach ($aulasPassadasExtras as $item) {
        $data = $item['data'];
        if (!isset($aulasPassadasExtrasAgrupadas[$data])) {
            $aulasPassadasExtrasAgrupadas[$data] = [];
        }
        $aulasPassadasExtrasAgrupadas[$data][] = $item['aula'];
    }

    // FASE 1 - AREA ALUNO PENDENCIAS - Estatísticas
    $stats = [
        'total' => count($aulasPraticas) + count($aulasTeoricas),
        'praticas' => count($aulasPraticas),
        'teoricas' => count($aulasTeoricas),
        'agendadas' => 0,
        'concluidas' => 0,
        'canceladas' => 0
    ];

    foreach ($aulasPraticas as $aula) {
        if ($aula['status'] === 'agendada' || $aula['status'] === 'em_andamento') {
            $stats['agendadas']++;
        } elseif ($aula['status'] === 'concluida') {
            $stats['concluidas']++;
        } elseif ($aula['status'] === 'cancelada') {
            $stats['canceladas']++;
        }
    }

    foreach ($aulasTeoricas as $aula) {
        if ($aula['status'] === 'agendada') {
            $stats['agendadas']++;
        } elseif ($aula['status'] === 'realizada') {
            $stats['concluidas']++;
        } elseif ($aula['status'] === 'cancelada') {
            $stats['canceladas']++;
        }
    }
}

// FASE 2 - GRADE DO CURSO - INÍCIO
// Buscar turmas teóricas do aluno e montar grade do curso
$gradeCurso = [];
if ($alunoId) {
    // Buscar turmas teóricas do aluno
    $turmasTeoricas = $db->fetchAll("
        SELECT 
            tm.turma_id,
            tt.nome as turma_nome,
            tt.curso_tipo,
            tt.data_inicio,
            tt.data_fim,
            tt.status as turma_status
        FROM turma_matriculas tm
        JOIN turmas_teoricas tt ON tm.turma_id = tt.id
        WHERE tm.aluno_id = ?
        AND tm.status IN ('matriculado', 'cursando', 'concluido')
        ORDER BY tm.data_matricula DESC
    ", [$alunoId]);
    
    foreach ($turmasTeoricas as $turma) {
        $turmaId = $turma['turma_id'];
        $cursoTipo = $turma['curso_tipo'];
        
        // Buscar disciplinas configuradas para este curso
        $disciplinasConfig = $db->fetchAll("
            SELECT 
                disciplina,
                nome_disciplina,
                aulas_obrigatorias,
                ordem
            FROM disciplinas_configuracao
            WHERE curso_tipo = ? AND ativa = 1
            ORDER BY ordem ASC
        ", [$cursoTipo]);
        
        // Se não encontrar na config, buscar disciplinas das aulas agendadas
        if (empty($disciplinasConfig)) {
            $disciplinasUnicas = $db->fetchAll("
                SELECT DISTINCT disciplina
                FROM turma_aulas_agendadas
                WHERE turma_id = ? AND status != 'cancelada'
                ORDER BY MIN(ordem_global) ASC
            ", [$turmaId]);
            
            foreach ($disciplinasUnicas as $disc) {
                $disciplinasConfig[] = [
                    'disciplina' => $disc['disciplina'],
                    'nome_disciplina' => ucfirst(str_replace('_', ' ', $disc['disciplina'])),
                    'aulas_obrigatorias' => 0, // Será calculado abaixo
                    'ordem' => 0
                ];
            }
        }
        
        $disciplinasGrade = [];
        foreach ($disciplinasConfig as $discConfig) {
            $disciplina = $discConfig['disciplina'];
            
            // FASE 2 - GRADE DO CURSO - Total de aulas: usar aulas_obrigatorias da config (como no admin)
            // Se não houver aulas_obrigatorias na config, contar o total agendado como fallback
            $totalAulasObrigatorias = (int)($discConfig['aulas_obrigatorias'] ?? 0);
            
            if ($totalAulasObrigatorias == 0) {
                // Fallback: contar aulas agendadas reais se não houver config
                $totalAulasAgendadas = $db->fetch("
                    SELECT COUNT(*) as total
                    FROM turma_aulas_agendadas
                    WHERE turma_id = ? AND disciplina = ? AND status != 'cancelada'
                ", [$turmaId, $disciplina]);
                $totalAulasObrigatorias = (int)($totalAulasAgendadas['total'] ?? 0);
            }
            
            // Aulas realizadas pelo aluno (presenças marcadas como presente)
            // FASE 2 - GRADE DO CURSO - Contar presenças do aluno nesta disciplina
            // Abordagem: buscar presenças e depois filtrar por disciplina das aulas
            try {
                // Primeiro, buscar todas as presenças do aluno na turma
                $presencas = $db->fetchAll("
                    SELECT aula_id
                    FROM turma_presencas
                    WHERE aluno_id = ? 
                    AND turma_id = ? 
                    AND presente = 1
                ", [$alunoId, $turmaId]);
                
                $aulasRealizadas = 0;
                if (!empty($presencas)) {
                    // Extrair IDs das aulas
                    $aulaIds = array_column($presencas, 'aula_id');
                    
                    // Verificar quantas dessas aulas pertencem à disciplina
                    if (!empty($aulaIds)) {
                        $placeholders = implode(',', array_fill(0, count($aulaIds), '?'));
                        $aulasDisciplina = $db->fetch("
                            SELECT COUNT(*) as total
                            FROM turma_aulas_agendadas
                            WHERE id IN ($placeholders)
                            AND turma_id = ?
                            AND disciplina = ?
                            AND status != 'cancelada'
                        ", array_merge($aulaIds, [$turmaId, $disciplina]));
                        $aulasRealizadas = (int)($aulasDisciplina['total'] ?? 0);
                    }
                }
            } catch (Exception $e) {
                error_log("FASE 2 - GRADE DO CURSO - Erro ao contar aulas realizadas: " . $e->getMessage());
                $aulasRealizadas = 0;
            }
            
            // Próxima aula desta disciplina
            $proximaAula = $db->fetch("
                SELECT 
                    taa.data_aula,
                    taa.hora_inicio,
                    taa.hora_fim,
                    s.nome as sala_nome
                FROM turma_aulas_agendadas taa
                LEFT JOIN salas s ON taa.sala_id = s.id
                WHERE taa.turma_id = ? 
                AND taa.disciplina = ?
                AND taa.status IN ('agendada', 'realizada')
                AND (taa.data_aula > CURDATE() OR (taa.data_aula = CURDATE() AND taa.hora_inicio > CURTIME()))
                ORDER BY taa.data_aula ASC, taa.hora_inicio ASC
                LIMIT 1
            ", [$turmaId, $disciplina]);
            
            // FASE 2 - GRADE DO CURSO - Calcular percentual e status usando aulas_obrigatorias
            $total = max(1, $totalAulasObrigatorias); // Evitar divisão por zero
            $percentual = round(($aulasRealizadas / $total) * 100);
            $percentual = max(0, min(100, $percentual)); // Clamp entre 0-100
            
            if ($aulasRealizadas <= 0) {
                $status = 'nao_iniciada';
            } elseif ($aulasRealizadas >= $total) {
                $status = 'concluida';
            } else {
                $status = 'em_andamento';
            }
            
            $disciplinasGrade[] = [
                'disciplina' => $disciplina,
                'nome' => $discConfig['nome_disciplina'],
                'aulas_total' => $totalAulasObrigatorias, // Total obrigatório (como no admin)
                'aulas_realizadas' => $aulasRealizadas,
                'percentual' => $percentual,
                'status' => $status,
                'proxima_aula' => $proximaAula ? [
                    'data' => date('d/m/Y', strtotime($proximaAula['data_aula'])),
                    'horario' => date('H:i', strtotime($proximaAula['hora_inicio'])) . ' - ' . date('H:i', strtotime($proximaAula['hora_fim'])),
                    'sala' => $proximaAula['sala_nome'] ?? null
                ] : null
            ];
        }
        
        if (!empty($disciplinasGrade)) {
            $gradeCurso[] = [
                'turma_id' => $turmaId,
                'turma_nome' => $turma['turma_nome'],
                'data_inicio' => $turma['data_inicio'],
                'data_fim' => $turma['data_fim'],
                'data_inicio_formatada' => date('d/m/Y', strtotime($turma['data_inicio'])),
                'data_fim_formatada' => date('d/m/Y', strtotime($turma['data_fim'])),
                'disciplinas' => $disciplinasGrade
            ];
        }
    }
}
// FASE 2 - GRADE DO CURSO - FIM

// Mapear nomes das disciplinas
$nomesDisciplinas = [
    'legislacao_transito' => 'Legislação de Trânsito',
    'direcao_defensiva' => 'Direção Defensiva',
    'primeiros_socorros' => 'Primeiros Socorros',
    'meio_ambiente_cidadania' => 'Meio Ambiente e Cidadania',
    'mecanica_basica' => 'Mecânica Básica'
];

// Função auxiliar para formatar data relativa
function formatarDataRelativa($data) {
    $hoje = date('Y-m-d');
    $amanha = date('Y-m-d', strtotime('+1 day'));
    
    if ($data === $hoje) {
        return 'Hoje';
    } elseif ($data === $amanha) {
        return 'Amanhã';
    } elseif ($data > $hoje) {
        return 'Próximas aulas';
    } else {
        return 'Aulas passadas';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <title>Minhas Aulas - <?php echo htmlspecialchars($aluno['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <link rel="stylesheet" href="../assets/css/aluno-aulas.css">
    <script>
        (function(){var m=document.getElementById('theme-color-meta');if(!m)return;function u(){var d=window.matchMedia('(prefers-color-scheme: dark)').matches;m.setAttribute('content',d?'#1e293b':'#10b981');}u();window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',u);})();
    </script>
</head>
<body>
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 20px 16px;">
        <!-- Card de Título (não é header, é card dentro do conteúdo) -->
        <div class="card card-aluno-dashboard mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h4 mb-1">
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>
                            Minhas Aulas
                        </h1>
                        <p class="text-muted mb-0 small">Todas as suas aulas práticas e teóricas</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Voltar
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Card Próxima Aula -->
        <?php if ($proximaAula): ?>
        <div class="proxima-aula-card">
            <div class="proxima-aula-card-header">
                <h3 class="proxima-aula-card-title">
                    <i class="fas fa-calendar-check proxima-aula-card-icon"></i>
                    Próxima aula
                </h3>
                <?php 
                // FASE 2 - GRADE DO CURSO - Garantir que tipo e tipo_label estejam definidos
                $tipoAula = $proximaAula['tipo'] ?? 'teorica';
                $tipoLabelAula = $proximaAula['tipo_label'] ?? ($tipoAula === 'teorica' ? 'Teórica' : 'Prática');
                ?>
                <span class="badge-aula-tipo <?php echo htmlspecialchars($tipoAula); ?>">
                    <?php echo htmlspecialchars($tipoLabelAula); ?>
                </span>
            </div>
            <div class="proxima-aula-info">
                <div class="proxima-aula-info-item">
                    <i class="fas fa-calendar"></i>
                    <strong><?php echo date('d/m/Y', strtotime($proximaAula['data_aula'])); ?></strong>
                </div>
                <div class="proxima-aula-info-item">
                    <i class="fas fa-clock"></i>
                    <strong><?php echo date('H:i', strtotime($proximaAula['hora_inicio'])); ?> - <?php echo date('H:i', strtotime($proximaAula['hora_fim'] ?? $proximaAula['hora_inicio'])); ?></strong>
                </div>
                <?php if ($tipoAula === 'teorica'): ?>
                    <div class="proxima-aula-info-item">
                        <i class="fas fa-book"></i>
                        <?php echo htmlspecialchars($proximaAula['turma_nome'] ?? ''); ?>
                    </div>
                    <?php if (!empty($proximaAula['disciplina'] ?? null)): ?>
                    <div class="proxima-aula-info-item">
                        <i class="fas fa-book-open"></i>
                        <?php echo htmlspecialchars($nomesDisciplinas[$proximaAula['disciplina']] ?? $proximaAula['disciplina']); ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="proxima-aula-info-item">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <?php echo htmlspecialchars($proximaAula['instrutor_nome'] ?? ''); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="proxima-aula-card empty">
            <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
            <p style="margin: 0;">Você não possui aulas futuras agendadas no período selecionado.</p>
        </div>
        <?php endif; ?>

        <!-- Chips de Filtro Rápido -->
        <div class="filtro-chips">
            <a href="?periodo=hoje&tipo=<?php echo $tipoFiltro; ?>&status=<?php echo $statusFiltro; ?>" 
               class="filtro-chip <?php echo $periodoFiltro === 'hoje' ? 'active' : ''; ?>">
                Hoje
            </a>
            <a href="?periodo=proximos_7_dias&tipo=<?php echo $tipoFiltro; ?>&status=<?php echo $statusFiltro; ?>" 
               class="filtro-chip <?php echo $periodoFiltro === 'proximos_7_dias' ? 'active' : ''; ?>">
                Próximos 7 dias
            </a>
            <a href="?periodo=proximos_30_dias&tipo=<?php echo $tipoFiltro; ?>&status=<?php echo $statusFiltro; ?>" 
               class="filtro-chip <?php echo $periodoFiltro === 'proximos_30_dias' ? 'active' : ''; ?>">
                Próximos 30 dias
            </a>
            <a href="?periodo=todas&tipo=<?php echo $tipoFiltro; ?>&status=<?php echo $statusFiltro; ?>" 
               class="filtro-chip <?php echo $periodoFiltro === 'todas' ? 'active' : ''; ?>">
                Todas
            </a>
        </div>

        <!-- Filtros Desktop -->
        <div class="filtros-desktop">
            <div class="card mb-3">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="periodo" class="form-label">Período</label>
                        <select id="periodo" name="periodo" class="form-select">
                            <option value="proximos_30_dias" <?php echo $periodoFiltro === 'proximos_30_dias' ? 'selected' : ''; ?>>Próximos 30 dias</option>
                            <option value="hoje" <?php echo $periodoFiltro === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                            <option value="proximos_7_dias" <?php echo $periodoFiltro === 'proximos_7_dias' ? 'selected' : ''; ?>>Próximos 7 dias</option>
                            <option value="ultimos_7_dias" <?php echo $periodoFiltro === 'ultimos_7_dias' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                            <option value="ultimos_30_dias" <?php echo $periodoFiltro === 'ultimos_30_dias' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                            <option value="todas" <?php echo $periodoFiltro === 'todas' ? 'selected' : ''; ?>>Todas (últimos + próximos 30 dias)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select">
                            <option value="todas" <?php echo $tipoFiltro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                            <option value="pratica" <?php echo $tipoFiltro === 'pratica' ? 'selected' : ''; ?>>Aulas Práticas</option>
                            <option value="teorica" <?php echo $tipoFiltro === 'teorica' ? 'selected' : ''; ?>>Aulas Teóricas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="agendada" <?php echo $statusFiltro === 'agendada' ? 'selected' : ''; ?>>Agendada</option>
                            <option value="concluida" <?php echo $statusFiltro === 'concluida' ? 'selected' : ''; ?>>Concluída</option>
                            <option value="cancelada" <?php echo $statusFiltro === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filtros Mobile -->
        <button class="btn btn-outline-primary filtros-mobile-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtrosOffcanvas">
            <i class="fas fa-filter me-2"></i> Filtros
        </button>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="filtrosOffcanvas">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Filtros</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <form method="GET" action="">
                    <div class="mb-3">
                        <label for="periodo_mobile" class="form-label">Período</label>
                        <select id="periodo_mobile" name="periodo" class="form-select">
                            <option value="todas" <?php echo $periodoFiltro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                            <option value="hoje" <?php echo $periodoFiltro === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                            <option value="ultimos_7_dias" <?php echo $periodoFiltro === 'ultimos_7_dias' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                            <option value="ultimos_30_dias" <?php echo $periodoFiltro === 'ultimos_30_dias' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                            <option value="proximos_7_dias" <?php echo $periodoFiltro === 'proximos_7_dias' ? 'selected' : ''; ?>>Próximos 7 dias</option>
                            <option value="proximos_30_dias" <?php echo $periodoFiltro === 'proximos_30_dias' ? 'selected' : ''; ?>>Próximos 30 dias</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="tipo_mobile" class="form-label">Tipo</label>
                        <select id="tipo_mobile" name="tipo" class="form-select">
                            <option value="todas" <?php echo $tipoFiltro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                            <option value="pratica" <?php echo $tipoFiltro === 'pratica' ? 'selected' : ''; ?>>Aulas Práticas</option>
                            <option value="teorica" <?php echo $tipoFiltro === 'teorica' ? 'selected' : ''; ?>>Aulas Teóricas</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status_mobile" class="form-label">Status</label>
                        <select id="status_mobile" name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="agendada" <?php echo $statusFiltro === 'agendada' ? 'selected' : ''; ?>>Agendada</option>
                            <option value="concluida" <?php echo $statusFiltro === 'concluida' ? 'selected' : ''; ?>>Concluída</option>
                            <option value="cancelada" <?php echo $statusFiltro === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Aplicar Filtros
                    </button>
                </form>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-valor total"><?php echo $stats['total']; ?></div>
                <div class="stat-card-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-valor praticas"><?php echo $stats['praticas']; ?></div>
                <div class="stat-card-label">Práticas</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-valor teoricas"><?php echo $stats['teoricas']; ?></div>
                <div class="stat-card-label">Teóricas</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-valor agendadas"><?php echo $stats['agendadas']; ?></div>
                <div class="stat-card-label">Agendadas</div>
            </div>
        </div>

        <!-- FASE 2 - GRADE DO CURSO - Grade do Curso (Aulas Teóricas) -->
        <div class="card card-grade-curso mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Grade do Curso (Aulas Teóricas)</h5>
                        <small class="text-muted">Acompanhe o seu avanço em cada disciplina.</small>
                    </div>
                </div>
                
                <?php if (empty($gradeCurso)): ?>
                <div class="alert alert-light border d-flex align-items-center gap-2 mb-0">
                    <i class="fas fa-info-circle text-muted fs-5"></i>
                    <div>
                        <div class="fw-semibold mb-0">Nenhuma grade encontrada</div>
                        <small class="text-muted">
                            Você ainda não possui turmas teóricas ativas. Entre em contato com a secretaria do CFC para mais informações.
                        </small>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($gradeCurso as $turmaGrade): ?>
                    <div class="grade-turma mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 pb-2 border-bottom">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary me-2">Turma</span>
                                <strong><?php echo htmlspecialchars($turmaGrade['turma_nome']); ?></strong>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">
                                    Período: <?php echo $turmaGrade['data_inicio_formatada']; ?> a <?php echo $turmaGrade['data_fim_formatada']; ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="table-responsive grade-disciplinas-table">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Disciplina</th>
                                        <th class="text-center">Aulas</th>
                                        <th class="text-center">Progresso</th>
                                        <th>Próxima aula</th>
                                        <th class="text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($turmaGrade['disciplinas'] as $disc): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($disc['nome']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <small>
                                                <?php echo $disc['aulas_realizadas']; ?> / <?php echo $disc['aulas_total']; ?> aulas
                                            </small>
                                        </td>
                                        <td class="text-center" style="min-width: 140px;">
                                            <div class="progress grade-progress">
                                                <div class="progress-bar" role="progressbar"
                                                     style="width: <?php echo $disc['percentual']; ?>%;"
                                                     aria-valuenow="<?php echo $disc['percentual']; ?>"
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $disc['percentual']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($disc['proxima_aula'])): ?>
                                            <div class="small">
                                                <i class="fas fa-calendar-alt me-1"></i><?php echo $disc['proxima_aula']['data']; ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-clock me-1"></i><?php echo $disc['proxima_aula']['horario']; ?>
                                                <?php if (!empty($disc['proxima_aula']['sala'])): ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-door-open me-1"></i><?php echo htmlspecialchars($disc['proxima_aula']['sala']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php else: ?>
                                            <small class="text-muted">Nenhuma aula futura</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php
                                            $badgeClass = 'bg-secondary bg-opacity-10 text-secondary';
                                            $badgeText = 'Não iniciada';
                                            if ($disc['status'] === 'em_andamento') {
                                                $badgeClass = 'bg-primary bg-opacity-10 text-primary';
                                                $badgeText = 'Em andamento';
                                            } elseif ($disc['status'] === 'concluida') {
                                                $badgeClass = 'bg-success bg-opacity-10 text-success';
                                                $badgeText = 'Concluída';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Abas por Tipo -->
        <ul class="nav nav-tabs aulas-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tipoFiltro === 'todas' ? 'active' : ''; ?>" 
                   href="?periodo=<?php echo $periodoFiltro; ?>&tipo=todas&status=<?php echo $statusFiltro; ?>">
                    Todas
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tipoFiltro === 'teorica' ? 'active' : ''; ?>" 
                   href="?periodo=<?php echo $periodoFiltro; ?>&tipo=teorica&status=<?php echo $statusFiltro; ?>">
                    Teóricas
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tipoFiltro === 'pratica' ? 'active' : ''; ?>" 
                   href="?periodo=<?php echo $periodoFiltro; ?>&tipo=pratica&status=<?php echo $statusFiltro; ?>">
                    Práticas
                </a>
            </li>
        </ul>

        <!-- Lista de Aulas Agrupada por Data -->
        <?php if (empty($aulasAgrupadas)): ?>
        <div class="card">
            <div class="text-center py-5">
                <i class="fas fa-calendar-times" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                <h3 class="text-muted mb-2">Nenhuma aula encontrada</h3>
                <p class="text-muted">Não há aulas no período selecionado com os filtros aplicados.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="aulas-por-data">
            <?php 
            // FASE 1.1 - UX AULAS ALUNO - Aulas passadas (colapsável)
            if (!empty($aulasPassadas)): 
                $aulaIndexPassadas = 0;
            ?>
            <div class="card mb-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center" 
                     data-bs-toggle="collapse" 
                     data-bs-target="#collapseAulasPassadas" 
                     aria-expanded="false" 
                     aria-controls="collapseAulasPassadas"
                     style="cursor: pointer;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-history text-muted"></i>
                        <strong>Aulas passadas</strong>
                        <span class="badge bg-secondary ms-2"><?php echo $totalAulasPassadas; ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small" id="toggleAulasPassadasTexto">Mostrar</span>
                        <i class="fas fa-chevron-down" id="toggleAulasPassadasIcon"></i>
                    </div>
                </div>
                <div class="collapse" id="collapseAulasPassadas">
                    <div class="card-body">
                        <?php foreach ($aulasPassadasIniciaisAgrupadas as $data => $aulas): 
                            $dataFormatada = date('d/m/Y', strtotime($data));
                        ?>
                        <div class="data-separador">
                            <span class="data-separador-label">Aulas passadas</span>
                            <span class="data-separador-data"><?php echo $dataFormatada; ?></span>
                        </div>
                        
                        <div class="accordion" id="accordionAulasPassadas<?php echo str_replace('-', '', $data); ?>">
                            <?php foreach ($aulas as $aula): 
                                $aulaId = 'aula-passada-' . $aulaIndexPassadas++;
                                $statusAula = $aula['status'] === 'realizada' ? 'concluida' : $aula['status'];
                                $statusLabel = [
                                    'agendada' => 'Agendada',
                                    'em_andamento' => 'Em Andamento',
                                    'concluida' => 'Concluída',
                                    'realizada' => 'Realizada',
                                    'cancelada' => 'Cancelada'
                                ];
                            ?>
                <div class="aula-card">
                    <div class="aula-card-header" 
                         data-bs-toggle="collapse" 
                         data-bs-target="#<?php echo $aulaId; ?>" 
                         aria-expanded="false" 
                         aria-controls="<?php echo $aulaId; ?>">
                        <div class="aula-card-info">
                            <div class="aula-card-badges">
                                <span class="badge-aula-tipo <?php echo $aula['tipo']; ?>">
                                    <?php echo $aula['tipo_label']; ?>
                                </span>
                                <span class="badge-aula-status <?php echo $statusAula; ?>">
                                    <?php echo $statusLabel[$aula['status']] ?? ucfirst($aula['status']); ?>
                                </span>
                            </div>
                            <div class="aula-card-titulo">
                                <?php if ($aula['tipo'] === 'teorica'): ?>
                                    <?php echo htmlspecialchars($aula['turma_nome'] ?? ''); ?>
                                    <?php if ($aula['nome_aula']): ?>
                                        - <?php echo htmlspecialchars($aula['nome_aula']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Aula Prática
                                <?php endif; ?>
                            </div>
                            <div class="aula-card-subtitulo">
                                <?php if ($aula['tipo'] === 'teorica' && $aula['disciplina']): ?>
                                    <?php echo htmlspecialchars($nomesDisciplinas[$aula['disciplina']] ?? $aula['disciplina']); ?>
                                <?php elseif ($aula['tipo'] === 'pratica' && isset($aula['instrutor_nome'])): ?>
                                    Instrutor: <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="aula-card-horario">
                                <i class="fas fa-clock"></i>
                                <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - 
                                <?php echo date('H:i', strtotime($aula['hora_fim'] ?? $aula['hora_inicio'])); ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down aula-card-toggle"></i>
                    </div>
                    <div id="<?php echo $aulaId; ?>" 
                         class="collapse aula-card-body" 
                         data-bs-parent="#accordionAulasPassadas<?php echo str_replace('-', '', $data); ?>">
                        <div class="aula-card-detalhes">
                            <?php if ($aula['tipo'] === 'teorica'): ?>
                                <?php if ($aula['turma_nome']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-users"></i>
                                    <div>
                                        <strong>Turma:</strong><br>
                                        <?php echo htmlspecialchars($aula['turma_nome']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['disciplina']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-book-open"></i>
                                    <div>
                                        <strong>Disciplina:</strong><br>
                                        <?php echo htmlspecialchars($nomesDisciplinas[$aula['disciplina']] ?? $aula['disciplina']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['nome_aula']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-file-alt"></i>
                                    <div>
                                        <strong>Aula:</strong><br>
                                        <?php echo htmlspecialchars($aula['nome_aula']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['instrutor_nome']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <div>
                                        <strong>Instrutor:</strong><br>
                                        <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['sala_nome']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-door-open"></i>
                                    <div>
                                        <strong>Sala:</strong><br>
                                        <?php echo htmlspecialchars($aula['sala_nome']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (isset($aula['instrutor_nome'])): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <div>
                                        <strong>Instrutor:</strong><br>
                                        <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($aula['veiculo_modelo'])): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-car"></i>
                                    <div>
                                        <strong>Veículo:</strong><br>
                                        <?php echo htmlspecialchars($aula['veiculo_modelo']); ?>
                                        <?php if (isset($aula['veiculo_placa'])): ?>
                                            - <?php echo htmlspecialchars($aula['veiculo_placa']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="aula-card-detalhe-item">
                                <i class="fas fa-calendar"></i>
                                <div>
                                    <strong>Data:</strong><br>
                                    <?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?>
                                </div>
                            </div>
                            <div class="aula-card-detalhe-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <strong>Horário:</strong><br>
                                    <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - 
                                    <?php echo date('H:i', strtotime($aula['hora_fim'] ?? $aula['hora_inicio'])); ?>
                                </div>
                            </div>
                            <?php if (!empty($aula['observacoes'])): ?>
                            <div class="aula-card-detalhe-item">
                                <i class="fas fa-sticky-note"></i>
                                <div>
                                    <strong>Observações:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($aula['observacoes'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- FASE 1.1 - UX AULAS ALUNO - Aulas passadas extras (inicialmente ocultas) -->
                        <?php if ($temMuitasPassadas && !empty($aulasPassadasExtrasAgrupadas)): ?>
                        <div id="aulasPassadasExtras" class="d-none">
                            <?php foreach ($aulasPassadasExtrasAgrupadas as $data => $aulas): 
                                $dataFormatada = date('d/m/Y', strtotime($data));
                            ?>
                            <div class="data-separador">
                                <span class="data-separador-label">Aulas passadas</span>
                                <span class="data-separador-data"><?php echo $dataFormatada; ?></span>
                            </div>
                            
                            <div class="accordion" id="accordionAulasPassadasExtras<?php echo str_replace('-', '', $data); ?>">
                                <?php foreach ($aulas as $aula): 
                                    $aulaId = 'aula-passada-extra-' . $aulaIndexPassadas++;
                                    $statusAula = $aula['status'] === 'realizada' ? 'concluida' : $aula['status'];
                                    $statusLabel = [
                                        'agendada' => 'Agendada',
                                        'em_andamento' => 'Em Andamento',
                                        'concluida' => 'Concluída',
                                        'realizada' => 'Realizada',
                                        'cancelada' => 'Cancelada'
                                    ];
                                ?>
                                <div class="aula-card">
                                    <div class="aula-card-header" 
                                         data-bs-toggle="collapse" 
                                         data-bs-target="#<?php echo $aulaId; ?>" 
                                         aria-expanded="false" 
                                         aria-controls="<?php echo $aulaId; ?>">
                                        <div class="aula-card-info">
                                            <div class="aula-card-badges">
                                                <span class="badge-aula-tipo <?php echo $aula['tipo']; ?>">
                                                    <?php echo $aula['tipo_label']; ?>
                                                </span>
                                                <span class="badge-aula-status <?php echo $statusAula; ?>">
                                                    <?php echo $statusLabel[$aula['status']] ?? ucfirst($aula['status']); ?>
                                                </span>
                                            </div>
                                            <div class="aula-card-titulo">
                                                <?php if ($aula['tipo'] === 'teorica'): ?>
                                                    <?php echo htmlspecialchars($aula['turma_nome'] ?? ''); ?>
                                                    <?php if ($aula['nome_aula']): ?>
                                                        - <?php echo htmlspecialchars($aula['nome_aula']); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    Aula Prática
                                                <?php endif; ?>
                                            </div>
                                            <div class="aula-card-subtitulo">
                                                <?php if ($aula['tipo'] === 'teorica' && $aula['disciplina']): ?>
                                                    <?php echo htmlspecialchars($nomesDisciplinas[$aula['disciplina']] ?? $aula['disciplina']); ?>
                                                <?php elseif ($aula['tipo'] === 'pratica' && isset($aula['instrutor_nome'])): ?>
                                                    Instrutor: <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="aula-card-horario">
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - 
                                                <?php echo date('H:i', strtotime($aula['hora_fim'] ?? $aula['hora_inicio'])); ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-chevron-down aula-card-toggle"></i>
                                    </div>
                                    <div id="<?php echo $aulaId; ?>" 
                                         class="collapse aula-card-body" 
                                         data-bs-parent="#accordionAulasPassadasExtras<?php echo str_replace('-', '', $data); ?>">
                                        <div class="aula-card-detalhes">
                                            <?php if ($aula['tipo'] === 'teorica'): ?>
                                                <?php if ($aula['turma_nome']): ?>
                                                <div class="aula-card-detalhe-item">
                                                    <i class="fas fa-users"></i>
                                                    <div>
                                                        <strong>Turma:</strong><br>
                                                        <?php echo htmlspecialchars($aula['turma_nome']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($aula['disciplina']): ?>
                                                <div class="aula-card-detalhe-item">
                                                    <i class="fas fa-book-open"></i>
                                                    <div>
                                                        <strong>Disciplina:</strong><br>
                                                        <?php echo htmlspecialchars($nomesDisciplinas[$aula['disciplina']] ?? $aula['disciplina']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($aula['nome_aula']): ?>
                                                <div class="aula-card-detalhe-item">
                                                    <i class="fas fa-file-alt"></i>
                                                    <div>
                                                        <strong>Aula:</strong><br>
                                                        <?php echo htmlspecialchars($aula['nome_aula']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($aula['instrutor_nome']): ?>
                                                <div class="aula-card-detalhe-item">
                                                    <i class="fas fa-chalkboard-teacher"></i>
                                                    <div>
                                                        <strong>Instrutor:</strong><br>
                                                        <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($aula['sala_nome']): ?>
                                                <div class="aula-card-detalhe-item">
                                                    <i class="fas fa-door-open"></i>
                                                    <div>
                                                        <strong>Sala:</strong><br>
                                                        <?php echo htmlspecialchars($aula['sala_nome']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if (isset($aula['instrutor_nome'])): ?>
                                                <div class="aula-card-detalhe-item">
                                                    <i class="fas fa-chalkboard-teacher"></i>
                                                    <div>
                                                        <strong>Instrutor:</strong><br>
                                                        <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (isset($aula['veiculo_modelo'])): ?>
                                                <div class="aula-card-detalhe-item">
                                                    <i class="fas fa-car"></i>
                                                    <div>
                                                        <strong>Veículo:</strong><br>
                                                        <?php echo htmlspecialchars($aula['veiculo_modelo']); ?>
                                                        <?php if (isset($aula['veiculo_placa'])): ?>
                                                            - <?php echo htmlspecialchars($aula['veiculo_placa']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <div class="aula-card-detalhe-item">
                                                <i class="fas fa-calendar"></i>
                                                <div>
                                                    <strong>Data:</strong><br>
                                                    <?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?>
                                                </div>
                                            </div>
                                            <div class="aula-card-detalhe-item">
                                                <i class="fas fa-clock"></i>
                                                <div>
                                                    <strong>Horário:</strong><br>
                                                    <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - 
                                                    <?php echo date('H:i', strtotime($aula['hora_fim'] ?? $aula['hora_inicio'])); ?>
                                                </div>
                                            </div>
                                            <?php if (!empty($aula['observacoes'])): ?>
                                            <div class="aula-card-detalhe-item">
                                                <i class="fas fa-sticky-note"></i>
                                                <div>
                                                    <strong>Observações:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($aula['observacoes'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($temMuitasPassadas): ?>
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnVerMaisAulasPassadas">
                                Ver mais aulas passadas
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Aulas futuras (hoje e próximas) -->
            <?php 
            $aulaIndex = 0;
            foreach ($aulasFuturas as $data => $aulas): 
                $dataFormatada = date('d/m/Y', strtotime($data));
                $dataRelativa = formatarDataRelativa($data);
            ?>
            <div class="data-separador">
                <span class="data-separador-label"><?php echo $dataRelativa; ?></span>
                <span class="data-separador-data"><?php echo $dataFormatada; ?></span>
            </div>
            
            <div class="accordion" id="accordionAulas<?php echo str_replace('-', '', $data); ?>">
                <?php foreach ($aulas as $aula): 
                    $aulaId = 'aula-' . $aulaIndex++;
                    $statusAula = $aula['status'] === 'realizada' ? 'concluida' : $aula['status'];
                    $statusLabel = [
                        'agendada' => 'Agendada',
                        'em_andamento' => 'Em Andamento',
                        'concluida' => 'Concluída',
                        'realizada' => 'Realizada',
                        'cancelada' => 'Cancelada'
                    ];
                ?>
                <div class="aula-card">
                    <div class="aula-card-header" 
                         data-bs-toggle="collapse" 
                         data-bs-target="#<?php echo $aulaId; ?>" 
                         aria-expanded="false" 
                         aria-controls="<?php echo $aulaId; ?>">
                        <div class="aula-card-info">
                            <div class="aula-card-badges">
                                <span class="badge-aula-tipo <?php echo $aula['tipo']; ?>">
                                    <?php echo $aula['tipo_label']; ?>
                                </span>
                                <span class="badge-aula-status <?php echo $statusAula; ?>">
                                    <?php echo $statusLabel[$aula['status']] ?? ucfirst($aula['status']); ?>
                                </span>
                            </div>
                            <div class="aula-card-titulo">
                                <?php if ($aula['tipo'] === 'teorica'): ?>
                                    <?php echo htmlspecialchars($aula['turma_nome'] ?? ''); ?>
                                    <?php if ($aula['nome_aula']): ?>
                                        - <?php echo htmlspecialchars($aula['nome_aula']); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Aula Prática
                                <?php endif; ?>
                            </div>
                            <div class="aula-card-subtitulo">
                                <?php if ($aula['tipo'] === 'teorica' && $aula['disciplina']): ?>
                                    <?php echo htmlspecialchars($nomesDisciplinas[$aula['disciplina']] ?? $aula['disciplina']); ?>
                                <?php elseif ($aula['tipo'] === 'pratica' && isset($aula['instrutor_nome'])): ?>
                                    Instrutor: <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="aula-card-horario">
                                <i class="fas fa-clock"></i>
                                <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - 
                                <?php echo date('H:i', strtotime($aula['hora_fim'] ?? $aula['hora_inicio'])); ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down aula-card-toggle"></i>
                    </div>
                    <div id="<?php echo $aulaId; ?>" 
                         class="collapse aula-card-body" 
                         data-bs-parent="#accordionAulas<?php echo str_replace('-', '', $data); ?>">
                        <div class="aula-card-detalhes">
                            <?php if ($aula['tipo'] === 'teorica'): ?>
                                <?php if ($aula['turma_nome']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-users"></i>
                                    <div>
                                        <strong>Turma:</strong><br>
                                        <?php echo htmlspecialchars($aula['turma_nome']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['disciplina']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-book-open"></i>
                                    <div>
                                        <strong>Disciplina:</strong><br>
                                        <?php echo htmlspecialchars($nomesDisciplinas[$aula['disciplina']] ?? $aula['disciplina']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['nome_aula']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-file-alt"></i>
                                    <div>
                                        <strong>Aula:</strong><br>
                                        <?php echo htmlspecialchars($aula['nome_aula']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['instrutor_nome']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <div>
                                        <strong>Instrutor:</strong><br>
                                        <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['sala_nome']): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-door-open"></i>
                                    <div>
                                        <strong>Sala:</strong><br>
                                        <?php echo htmlspecialchars($aula['sala_nome']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (isset($aula['instrutor_nome'])): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <div>
                                        <strong>Instrutor:</strong><br>
                                        <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($aula['veiculo_modelo'])): ?>
                                <div class="aula-card-detalhe-item">
                                    <i class="fas fa-car"></i>
                                    <div>
                                        <strong>Veículo:</strong><br>
                                        <?php echo htmlspecialchars($aula['veiculo_modelo']); ?>
                                        <?php if (isset($aula['veiculo_placa'])): ?>
                                            - <?php echo htmlspecialchars($aula['veiculo_placa']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="aula-card-detalhe-item">
                                <i class="fas fa-calendar"></i>
                                <div>
                                    <strong>Data:</strong><br>
                                    <?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?>
                                </div>
                            </div>
                            <div class="aula-card-detalhe-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <strong>Horário:</strong><br>
                                    <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - 
                                    <?php echo date('H:i', strtotime($aula['hora_fim'] ?? $aula['hora_inicio'])); ?>
                                </div>
                            </div>
                            <?php if (!empty($aula['observacoes'])): ?>
                            <div class="aula-card-detalhe-item">
                                <i class="fas fa-sticky-note"></i>
                                <div>
                                    <strong>Observações:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($aula['observacoes'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // FASE 1.1 - UX AULAS ALUNO - Controle de "Aulas passadas" colapsável
    document.addEventListener('DOMContentLoaded', function () {
        var collapseAulasPassadas = document.getElementById('collapseAulasPassadas');
        var toggleTexto = document.getElementById('toggleAulasPassadasTexto');
        var toggleIcon = document.getElementById('toggleAulasPassadasIcon');
        
        if (collapseAulasPassadas && toggleTexto && toggleIcon) {
            collapseAulasPassadas.addEventListener('show.bs.collapse', function () {
                toggleTexto.textContent = 'Ocultar';
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-up');
            });
            
            collapseAulasPassadas.addEventListener('hide.bs.collapse', function () {
                toggleTexto.textContent = 'Mostrar';
                toggleIcon.classList.remove('fa-chevron-up');
                toggleIcon.classList.add('fa-chevron-down');
            });
        }
        
        // FASE 1.1 - UX AULAS ALUNO - Ver mais aulas passadas
        var btnVerMais = document.getElementById('btnVerMaisAulasPassadas');
        if (btnVerMais) {
            btnVerMais.addEventListener('click', function () {
                var extras = document.getElementById('aulasPassadasExtras');
                if (extras) {
                    extras.classList.remove('d-none');
                    btnVerMais.classList.add('d-none');
                }
            });
        }
    });
    </script>
</body>
</html>
