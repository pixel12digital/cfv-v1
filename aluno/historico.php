<?php
/**
 * FASE 1 - PRESENCA TEORICA - Página de Histórico do Aluno
 * Arquivo: aluno/historico.php
 * 
 * Funcionalidades:
 * - Exibir histórico completo do aluno (apenas seus próprios dados)
 * - Bloco de Presença Teórica (reaproveitado de historico-aluno.php)
 * - Acesso seguro: aluno só vê seus próprios dados
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
    $error = 'Aluno não encontrado no sistema. Entre em contato com a secretaria.';
    // Não continuar executando queries se não houver aluno_id válido
    $turmasTeoricasAluno = [];
    $presencaTeoricaDetalhada = [];
}

// FASE 1 - PRESENCA TEORICA - Buscar turmas teóricas do aluno (mesma lógica de historico-aluno.php)
$turmasTeoricasAluno = [];
$presencaTeoricaDetalhada = [];

if ($alunoId) {
    $turmasTeoricasAluno = $db->fetchAll("
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
    ", [$alunoId]);
    
    // Para cada turma, buscar aulas e presenças
    foreach ($turmasTeoricasAluno as $turma) {
        // Buscar aulas agendadas da turma
        $aulasTurma = $db->fetchAll("
            SELECT 
                taa.id as aula_id,
                taa.nome_aula,
                taa.disciplina,
                taa.data_aula,
                taa.hora_inicio,
                taa.hora_fim,
                taa.status as aula_status,
                taa.ordem_global
            FROM turma_aulas_agendadas taa
            WHERE taa.turma_id = ?
            AND taa.status IN ('agendada', 'realizada')
            ORDER BY taa.ordem_global ASC
        ", [$turma['turma_id']]);
        
        // Buscar presenças do aluno nesta turma
        $presencasAluno = $db->fetchAll("
            SELECT 
                tp.aula_id,
                tp.presente,
                tp.justificativa,
                tp.registrado_em
            FROM turma_presencas tp
            WHERE tp.turma_id = ? AND tp.aluno_id = ?
        ", [$turma['turma_id'], $alunoId]);
        
        // Criar mapa de presenças por aula_id
        $presencasMap = [];
        foreach ($presencasAluno as $presenca) {
            $presencasMap[$presenca['aula_id']] = $presenca;
        }
        
        // Montar lista de aulas com status de presença
        $aulasComPresenca = [];
        foreach ($aulasTurma as $aula) {
            $presenca = $presencasMap[$aula['aula_id']] ?? null;
            $aulasComPresenca[] = [
                'aula' => $aula,
                'presenca' => $presenca,
                'status_presenca' => $presenca ? ($presenca['presente'] ? 'presente' : 'ausente') : 'nao_registrado'
            ];
        }
        
        $presencaTeoricaDetalhada[] = [
            'turma' => $turma,
            'aulas' => $aulasComPresenca
        ];
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
    <title>Meu Histórico - <?php echo htmlspecialchars($aluno['nome']); ?></title>
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
    </style>
    <script>
        (function(){var m=document.getElementById('theme-color-meta');if(!m)return;function u(){var d=window.matchMedia('(prefers-color-scheme: dark)').matches;m.setAttribute('content',d?'#1e293b':'#10b981');}u();window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',u);})();
    </script>
</head>
<body>
    <!-- Header -->
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 20px 16px;">
        <!-- Card de Título (não é header, é card dentro do conteúdo) -->
        <div class="card card-aluno-dashboard mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h1 class="h4 mb-1">
                            <i class="fas fa-history me-2 text-primary"></i>
                            Meu Histórico
                        </h1>
                        <p class="text-muted mb-0 small">Acompanhe seu progresso completo</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- FASE 1 - PRESENCA TEORICA - Bloco de Presença Teórica (reaproveitado de historico-aluno.php) -->
        <?php if (!empty($presencaTeoricaDetalhada)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Presença Teórica
                </h2>
            </div>
            <div class="card-body">
                <?php foreach ($presencaTeoricaDetalhada as $item): ?>
                    <?php 
                    $turma = $item['turma'];
                    $aulas = $item['aulas'];
                    $frequencia = (float)($turma['frequencia_percentual'] ?? 0);
                    
                    // Determinar status da matrícula
                    $statusMatricula = $turma['status_matricula'];
                    $statusLabel = [
                        'matriculado' => 'Matriculado',
                        'cursando' => 'Cursando',
                        'concluido' => 'Concluído',
                        'evadido' => 'Evadido',
                        'transferido' => 'Transferido'
                    ][$statusMatricula] ?? ucfirst($statusMatricula);
                    
                    // Badge de frequência
                    $freqBadgeClass = 'bg-success';
                    if ($frequencia < 75) {
                        $freqBadgeClass = 'bg-danger';
                    } elseif ($frequencia < 90) {
                        $freqBadgeClass = 'bg-warning';
                    }
                    ?>
                    <div class="mb-4 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-book me-2"></i>
                                    <?php echo htmlspecialchars($turma['turma_nome']); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($nomesCursos[$turma['curso_tipo']] ?? $turma['curso_tipo']); ?> | 
                                    <?php echo date('d/m/Y', strtotime($turma['data_inicio'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($turma['data_fim'])); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div>
                                    <span class="badge <?php echo $freqBadgeClass; ?>">
                                        Frequência: <?php echo number_format($frequencia, 1); ?>%
                                    </span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Status: <?php echo $statusLabel; ?>
                                </small>
                            </div>
                        </div>
                        
                        <?php if (!empty($aulas)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Disciplina</th>
                                        <th>Horário</th>
                                        <th>Presença</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($aulas as $itemAula): ?>
                                        <?php 
                                        $aula = $itemAula['aula'];
                                        $statusPresenca = $itemAula['status_presenca'];
                                        
                                        $presencaBadge = '';
                                        if ($statusPresenca === 'presente') {
                                            $presencaBadge = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Presente</span>';
                                        } elseif ($statusPresenca === 'ausente') {
                                            $presencaBadge = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Ausente</span>';
                                        } else {
                                            $presencaBadge = '<span class="badge bg-secondary"><i class="fas fa-minus me-1"></i>Não registrado</span>';
                                        }
                                        
                                        $disciplinaNome = $nomesDisciplinas[$aula['disciplina']] ?? ucfirst(str_replace('_', ' ', $aula['disciplina']));
                                        ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?></td>
                                            <td><?php echo htmlspecialchars($disciplinaNome); ?></td>
                                            <td>
                                                <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - 
                                                <?php echo date('H:i', strtotime($aula['hora_fim'])); ?>
                                            </td>
                                            <td>
                                                <?php echo $presencaBadge; ?>
                                                <?php if ($itemAula['presenca'] && !empty($itemAula['presenca']['justificativa'])): ?>
                                                    <i class="fas fa-comment-alt text-info ms-2" 
                                                       data-bs-toggle="tooltip" 
                                                       title="<?php echo htmlspecialchars($itemAula['presenca']['justificativa']); ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-0">Nenhuma aula registrada nesta turma.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Nenhuma turma teórica encontrada</h5>
                <p class="text-muted">Você ainda não está matriculado em nenhuma turma teórica.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Link para página de presenças detalhadas -->
        <div class="card">
            <div class="card-body text-center">
                <a href="presencas-teoricas.php" class="btn btn-primary">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Ver Presenças Detalhadas
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

