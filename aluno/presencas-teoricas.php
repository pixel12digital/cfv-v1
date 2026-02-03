<?php
/**
 * FASE 1 - PRESENCA TEORICA - Página de Presenças Teóricas do Aluno
 * Arquivo: aluno/presencas-teoricas.php
 * 
 * Funcionalidades:
 * - Listar turmas teóricas em que o aluno está matriculado
 * - Exibir frequência percentual por turma
 * - Listar aulas com status de presença (Presente/Ausente/Não registrado)
 * - Exibir justificativas (se houver)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// FASE 1 - PRESENCA TEORICA - Verificar autenticação específica para aluno
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

// FASE 1 - PRESENCA TEORICA - Buscar dados do aluno
$aluno = $db->fetch("SELECT * FROM usuarios WHERE id = ? AND tipo = 'aluno'", [$user['id']]);

if (!$aluno) {
    header('Location: login.php');
    exit();
}

// FASE 1 - AREA ALUNO PENDENCIAS - Buscar o ID do aluno usando getCurrentAlunoId() (função robusta)
$alunoId = getCurrentAlunoId($user['id']);

if (!$alunoId) {
    // Aluno não encontrado na tabela alunos
    $error = 'Aluno não encontrado no sistema. Entre em contato com a secretaria.';
    // Não continuar executando queries se não houver aluno_id válido
    $turmasTeoricasAluno = [];
    $turmaSelecionada = null;
    $aulasTurmaSelecionada = [];
}

// FASE 1 - PRESENCA TEORICA - Processar filtro de período
$filtroPeriodo = $_GET['periodo'] ?? 'todas';
$dataInicioFiltro = null;
$dataFimFiltro = null;

switch ($filtroPeriodo) {
    case 'ultimos_30':
        $dataInicioFiltro = date('Y-m-d', strtotime('-30 days'));
        $dataFimFiltro = date('Y-m-d');
        break;
    case 'ultimos_90':
        $dataInicioFiltro = date('Y-m-d', strtotime('-90 days'));
        $dataFimFiltro = date('Y-m-d');
        break;
    case 'ultimo_mes':
        $dataInicioFiltro = date('Y-m-d', strtotime('first day of this month'));
        $dataFimFiltro = date('Y-m-d', strtotime('last day of this month'));
        break;
    default:
        $filtroPeriodo = 'todas';
        break;
}

// FASE 1 - PRESENCA TEORICA - Buscar turmas teóricas do aluno
$turmasTeoricasAluno = [];
if ($alunoId) {
    $sql = "
        SELECT 
            tm.id as matricula_id,
            tm.turma_id,
            tm.status as status_matricula,
            tm.data_matricula,
            tm.frequencia_percentual,
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
    ";
    
    $turmasTeoricasAluno = $db->fetchAll($sql, [$alunoId]);
}

// FASE 1 - PRESENCA TEORICA - Turma selecionada (se houver)
$turmaSelecionadaId = $_GET['turma_id'] ?? null;
$turmaSelecionada = null;
$aulasTurmaSelecionada = [];

if ($turmaSelecionadaId && $alunoId) {
    // Verificar se a turma pertence ao aluno (segurança)
    $turmaSelecionada = $db->fetch("
        SELECT 
            tm.turma_id,
            tm.frequencia_percentual,
            tt.nome as turma_nome,
            tt.curso_tipo,
            tt.data_inicio,
            tt.data_fim,
            tt.status as turma_status
        FROM turma_matriculas tm
        JOIN turmas_teoricas tt ON tm.turma_id = tt.id
        WHERE tm.turma_id = ? AND tm.aluno_id = ?
        AND tm.status IN ('matriculado', 'cursando', 'concluido')
    ", [$turmaSelecionadaId, $alunoId]);
    
    if ($turmaSelecionada) {
        // Buscar aulas agendadas da turma
        $sqlAulas = "
            SELECT 
                taa.id as aula_id,
                taa.nome_aula,
                taa.disciplina,
                taa.data_aula,
                taa.hora_inicio,
                taa.hora_fim,
                taa.status as aula_status,
                taa.ordem_global,
                i.nome as instrutor_nome,
                s.nome as sala_nome
            FROM turma_aulas_agendadas taa
            LEFT JOIN instrutores i ON taa.instrutor_id = i.id
            LEFT JOIN usuarios u ON i.usuario_id = u.id
            LEFT JOIN salas s ON taa.sala_id = s.id
            WHERE taa.turma_id = ?
            AND taa.status IN ('agendada', 'realizada')
        ";
        
        $paramsAulas = [$turmaSelecionadaId];
        
        // Aplicar filtro de período se selecionado
        if ($dataInicioFiltro && $dataFimFiltro) {
            $sqlAulas .= " AND taa.data_aula >= ? AND taa.data_aula <= ?";
            $paramsAulas[] = $dataInicioFiltro;
            $paramsAulas[] = $dataFimFiltro;
        }
        
        $sqlAulas .= " ORDER BY taa.ordem_global ASC";
        
        $aulasTurma = $db->fetchAll($sqlAulas, $paramsAulas);
        
        // Buscar presenças do aluno nesta turma
        $presencasAluno = $db->fetchAll("
            SELECT 
                tp.aula_id,
                tp.presente,
                tp.justificativa,
                tp.registrado_em
            FROM turma_presencas tp
            WHERE tp.turma_id = ? AND tp.aluno_id = ?
        ", [$turmaSelecionadaId, $alunoId]);
        
        // Criar mapa de presenças por aula_id
        $presencasMap = [];
        foreach ($presencasAluno as $presenca) {
            $presencasMap[$presenca['aula_id']] = $presenca;
        }
        
        // Montar lista de aulas com status de presença
        foreach ($aulasTurma as $aula) {
            $presenca = $presencasMap[$aula['aula_id']] ?? null;
            $aulasTurmaSelecionada[] = [
                'aula' => $aula,
                'presenca' => $presenca,
                'status_presenca' => $presenca ? ($presenca['presente'] ? 'presente' : 'ausente') : 'nao_registrado'
            ];
        }
    }
}

// Mapear nomes dos cursos
$nomesCursos = [
    'formacao_45h' => 'Formação 45h',
    'formacao_acc_20h' => 'Formação ACC 20h',
    'reciclagem_infrator' => 'Reciclagem Infrator',
    'atualizacao' => 'Atualização'
];

// Mapear nomes das disciplinas
$nomesDisciplinas = [
    'legislacao_transito' => 'Legislação de Trânsito',
    'direcao_defensiva' => 'Direção Defensiva',
    'primeiros_socorros' => 'Primeiros Socorros',
    'meio_ambiente_cidadania' => 'Meio Ambiente e Cidadania',
    'mecanica_basica' => 'Mecânica Básica'
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <title>Minhas Presenças Teóricas - <?php echo htmlspecialchars($aluno['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <style>
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .turma-item {
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .turma-item:hover {
            background: #f8f9fa;
            border-color: #2563eb;
        }
        
        .turma-item.active {
            background: #eff6ff;
            border-color: #2563eb;
        }
        
        .frequencia-badge {
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .frequencia-badge.alta {
            background: #dcfce7;
            color: #166534;
        }
        
        .frequencia-badge.media {
            background: #fef3c7;
            color: #92400e;
        }
        
        .frequencia-badge.baixa {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .presenca-badge {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .table-responsive {
            margin-top: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            color: #cbd5e1;
        }
    </style>
    <script>
        (function(){var m=document.getElementById('theme-color-meta');if(!m)return;function u(){var d=window.matchMedia('(prefers-color-scheme: dark)').matches;m.setAttribute('content',d?'#1e293b':'#10b981');}u();window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',u);})();
    </script>
</head>
<body>
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 20px 16px;">
        <!-- Card de Título (não é header, é card dentro do conteúdo) -->
        <div class="card card-aluno-dashboard mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h1 class="h4 mb-1">
                            <i class="fas fa-clipboard-check me-2 text-primary"></i>
                            Minhas Presenças Teóricas
                        </h1>
                        <p class="text-muted mb-0 small">Acompanhe sua frequência nas aulas teóricas</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="historico.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-history me-1"></i> Histórico
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if (empty($turmasTeoricasAluno)): ?>
        <!-- Estado vazio -->
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3 style="color: #1e293b; margin-bottom: 8px;">Nenhuma turma teórica encontrada</h3>
                <p style="color: #64748b; margin: 0;">Você ainda não está matriculado em nenhuma turma teórica.</p>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Card 1: Resumo das Turmas Teóricas -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-users-class me-2"></i>
                    Minhas Turmas Teóricas
                </h2>
            </div>
            <div class="turma-list">
                <?php foreach ($turmasTeoricasAluno as $turma): ?>
                    <?php 
                    $frequencia = (float)($turma['frequencia_percentual'] ?? 0);
                    $freqClass = 'alta';
                    if ($frequencia < 75) {
                        $freqClass = 'baixa';
                    } elseif ($frequencia < 90) {
                        $freqClass = 'media';
                    }
                    
                    $statusMatricula = $turma['status_matricula'];
                    $statusLabel = [
                        'matriculado' => 'Matriculado',
                        'cursando' => 'Cursando',
                        'concluido' => 'Concluído',
                        'evadido' => 'Evadido',
                        'transferido' => 'Transferido'
                    ][$statusMatricula] ?? ucfirst($statusMatricula);
                    
                    $isActive = $turmaSelecionadaId && (int)$turmaSelecionadaId === (int)$turma['turma_id'];
                    ?>
                    <div class="turma-item <?php echo $isActive ? 'active' : ''; ?>" 
                         onclick="selecionarTurma(<?php echo $turma['turma_id']; ?>)">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <h6 style="margin: 0 0 8px 0; font-weight: 600; color: #1e293b;">
                                    <?php echo htmlspecialchars($turma['turma_nome']); ?>
                                </h6>
                                <div style="font-size: 13px; color: #64748b; margin-bottom: 4px;">
                                    <i class="fas fa-graduation-cap me-1"></i>
                                    <?php echo htmlspecialchars($nomesCursos[$turma['curso_tipo']] ?? $turma['curso_tipo']); ?>
                                </div>
                                <div style="font-size: 13px; color: #64748b; margin-bottom: 4px;">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($turma['data_inicio'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($turma['data_fim'])); ?>
                                </div>
                                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                                    Status: <?php echo $statusLabel; ?>
                                </div>
                            </div>
                            <div style="text-align: right; margin-left: 16px;">
                                <div class="frequencia-badge <?php echo $freqClass; ?>">
                                    <?php echo number_format($frequencia, 1); ?>%
                                </div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
                                    Frequência
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Card 2: Detalhamento da Turma Selecionada -->
        <?php if ($turmaSelecionada): ?>
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title">
                        <i class="fas fa-list me-2"></i>
                        Aulas da Turma: <?php echo htmlspecialchars($turmaSelecionada['turma_nome']); ?>
                    </h2>
                    <div>
                        <select id="filtroPeriodo" class="form-select form-select-sm" onchange="aplicarFiltroPeriodo()" style="width: auto; display: inline-block;">
                            <option value="todas" <?php echo $filtroPeriodo === 'todas' ? 'selected' : ''; ?>>Todas</option>
                            <option value="ultimos_30" <?php echo $filtroPeriodo === 'ultimos_30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                            <option value="ultimos_90" <?php echo $filtroPeriodo === 'ultimos_90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                            <option value="ultimo_mes" <?php echo $filtroPeriodo === 'ultimo_mes' ? 'selected' : ''; ?>>Este mês</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php if (empty($aulasTurmaSelecionada)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h4 style="color: #1e293b; margin-bottom: 8px;">Nenhuma aula encontrada</h4>
                <p style="color: #64748b; margin: 0;">
                    <?php if ($filtroPeriodo !== 'todas'): ?>
                        Não há aulas no período selecionado.
                    <?php else: ?>
                        Esta turma ainda não possui aulas agendadas.
                    <?php endif; ?>
                </p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Disciplina</th>
                            <th>Instrutor</th>
                            <th>Sala</th>
                            <th>Presença</th>
                            <th>Justificativa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aulasTurmaSelecionada as $itemAula): ?>
                            <?php 
                            $aula = $itemAula['aula'];
                            $statusPresenca = $itemAula['status_presenca'];
                            
                            $presencaBadge = '';
                            if ($statusPresenca === 'presente') {
                                $presencaBadge = '<span class="presenca-badge bg-success text-white"><i class="fas fa-check me-1"></i>Presente</span>';
                            } elseif ($statusPresenca === 'ausente') {
                                $presencaBadge = '<span class="presenca-badge bg-danger text-white"><i class="fas fa-times me-1"></i>Ausente</span>';
                            } else {
                                $presencaBadge = '<span class="presenca-badge bg-secondary text-white"><i class="fas fa-minus me-1"></i>Não registrado</span>';
                            }
                            
                            $disciplinaNome = $nomesDisciplinas[$aula['disciplina']] ?? ucfirst(str_replace('_', ' ', $aula['disciplina']));
                            $instrutorNome = $aula['instrutor_nome'] ?? 'Não definido';
                            $salaNome = $aula['sala_nome'] ?? 'Não definida';
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?></td>
                                <td>
                                    <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - 
                                    <?php echo date('H:i', strtotime($aula['hora_fim'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($disciplinaNome); ?></td>
                                <td><?php echo htmlspecialchars($instrutorNome); ?></td>
                                <td><?php echo htmlspecialchars($salaNome); ?></td>
                                <td><?php echo $presencaBadge; ?></td>
                                <td>
                                    <?php if ($itemAula['presenca'] && !empty($itemAula['presenca']['justificativa'])): ?>
                                        <span data-bs-toggle="tooltip" 
                                              data-bs-placement="top" 
                                              title="<?php echo htmlspecialchars($itemAula['presenca']['justificativa']); ?>">
                                            <i class="fas fa-comment-alt text-info"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-hand-pointer"></i>
                </div>
                <h4 style="color: #1e293b; margin-bottom: 8px;">Selecione uma turma</h4>
                <p style="color: #64748b; margin: 0;">Clique em uma turma acima para ver o detalhamento das aulas.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // FASE 1 - PRESENCA TEORICA - Selecionar turma
        function selecionarTurma(turmaId) {
            const url = new URL(window.location.href);
            url.searchParams.set('turma_id', turmaId);
            window.location.href = url.toString();
        }
        
        // FASE 1 - PRESENCA TEORICA - Aplicar filtro de período
        function aplicarFiltroPeriodo() {
            const periodo = document.getElementById('filtroPeriodo').value;
            const url = new URL(window.location.href);
            url.searchParams.set('periodo', periodo);
            // Manter turma_id se existir
            window.location.href = url.toString();
        }
        
        // Inicializar tooltips do Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>

