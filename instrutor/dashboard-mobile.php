<?php
/**
 * Dashboard do Instrutor - Mobile First + PWA
 * Interface focada em usabilidade móvel
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/services/SistemaNotificacoes.php';

// DEBUG: dashboard instrutor carregado (instrutor/dashboard-mobile.php)

// Verificar autenticação
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'instrutor') {
    header('Location: /login.php');
    exit();
}

$db = db();
$notificacoes = new SistemaNotificacoes();

// Resumo rápido (desktop x mobile):
// - Desktop (instrutor/dashboard.php) usa instrutores.id e combina aulas (prática) + turma_aulas_agendadas (teórica) com filtros por data/status.
// - Mobile (esta página) usava usuarios.id e apenas tabela aulas. Ajuste abaixo alinha com o desktop sem alterar o layout/HTML.

// Buscar dados do instrutor via usuario_id -> instrutores.id
$instrutorId = getCurrentInstrutorId($user['id']);
$instrutor = $instrutorId ? $db->fetch("SELECT * FROM instrutores WHERE id = ?", [$instrutorId]) : null;
if (!$instrutor) {
    // Fallback seguro se não encontrar instrutor vinculado
    $instrutor = [
        'id' => null,
        'usuario_id' => $user['id'],
        'nome' => $user['nome'] ?? 'Instrutor',
        'email' => $user['email'] ?? '',
        'cfc_id' => null
    ];
}

// Log leve para homolog
error_log('[DEBUG AULAS PWA] user_id=' . ($user['id'] ?? 'null') . ' instrutor_id=' . ($instrutor['id'] ?? 'null'));

// Buscar aulas do dia (práticas + teóricas)
$hoje = date('Y-m-d');
$aulasPraticasHoje = [];
$aulasTeoricasHoje = [];
$proximasAulasPraticas = [];
$proximasAulasTeoricas = [];

if ($instrutor['id']) {
    $aulasPraticasHoje = $db->fetchAll("
        SELECT a.*, 
               a.aluno_id,
               al.nome as aluno_nome, al.telefone as aluno_telefone,
               v.modelo as veiculo_modelo, v.placa as veiculo_placa,
               'pratica' as tipo_aula
        FROM aulas a
        JOIN alunos al ON a.aluno_id = al.id
        LEFT JOIN veiculos v ON a.veiculo_id = v.id
        WHERE a.instrutor_id = ? 
          AND a.data_aula = ?
          AND a.status != 'cancelada'
        ORDER BY a.hora_inicio ASC
    ", [$instrutor['id'], $hoje]);

    $aulasTeoricasHoje = $db->fetchAll("
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
            s.nome as sala_nome,
            'teorica' as tipo_aula,
            NULL as aluno_nome,
            NULL as aluno_telefone,
            NULL as veiculo_modelo,
            NULL as veiculo_placa
        FROM turma_aulas_agendadas taa
        JOIN turmas_teoricas tt ON taa.turma_id = tt.id
        LEFT JOIN salas s ON taa.sala_id = s.id
        WHERE taa.instrutor_id = ?
          AND taa.data_aula = ?
          AND taa.status != 'cancelada'
        ORDER BY taa.hora_inicio ASC
    ", [$instrutor['id'], $hoje]);

    // Próximas aulas (7 dias) - práticas
    $proximasAulasPraticas = $db->fetchAll("
        SELECT a.*, 
               a.aluno_id,
               al.nome as aluno_nome, al.telefone as aluno_telefone,
               v.modelo as veiculo_modelo, v.placa as veiculo_placa,
               'pratica' as tipo_aula
        FROM aulas a
        JOIN alunos al ON a.aluno_id = al.id
        LEFT JOIN veiculos v ON a.veiculo_id = v.id
        WHERE a.instrutor_id = ? 
          AND a.data_aula > ?
          AND a.data_aula <= DATE_ADD(?, INTERVAL 7 DAY)
          AND a.status != 'cancelada'
        ORDER BY a.data_aula ASC, a.hora_inicio ASC
        LIMIT 10
    ", [$instrutor['id'], $hoje, $hoje]);

    // Próximas aulas (7 dias) - teóricas
    $proximasAulasTeoricas = $db->fetchAll("
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
            s.nome as sala_nome,
            'teorica' as tipo_aula,
            NULL as aluno_nome,
            NULL as aluno_telefone,
            NULL as veiculo_modelo,
            NULL as veiculo_placa
        FROM turma_aulas_agendadas taa
        JOIN turmas_teoricas tt ON taa.turma_id = tt.id
        LEFT JOIN salas s ON taa.sala_id = s.id
        WHERE taa.instrutor_id = ?
          AND taa.data_aula > ?
          AND taa.data_aula <= DATE_ADD(?, INTERVAL 7 DAY)
          AND taa.status != 'cancelada'
        ORDER BY taa.data_aula ASC, taa.hora_inicio ASC
        LIMIT 10
    ", [$instrutor['id'], $hoje, $hoje]);
}

// Normalizar listas unificadas
$aulasHoje = array_merge($aulasPraticasHoje, $aulasTeoricasHoje);
usort($aulasHoje, function($a, $b) {
    return strcmp($a['hora_inicio'] ?? '00:00:00', $b['hora_inicio'] ?? '00:00:00');
});

// CORREÇÃO: Verificar chamada registrada para todas as aulas teóricas (igual ao desktop)
foreach ($aulasHoje as &$aula) {
    if ($aula['tipo_aula'] === 'teorica' && isset($aula['id'])) {
        try {
            $presencasCount = $db->fetch("
                SELECT COUNT(*) as total
                FROM turma_presencas
                WHERE turma_aula_id = ? AND turma_id = ?
            ", [$aula['id'], $aula['turma_id'] ?? 0]);
            $aula['chamada_registrada'] = ($presencasCount['total'] ?? 0) > 0;
        } catch (Exception $e) {
            $aula['chamada_registrada'] = false;
        }
    } else {
        $aula['chamada_registrada'] = false; // Aulas práticas não têm chamada teórica
    }
}
unset($aula); // Remover referência do último elemento

// BLOCOS DE AULAS PRÁTICAS CONSECUTIVAS (igual ao desktop)
$aulasHojeProcessadas = [];
if (!empty($aulasHoje)) {
    $i = 0;
    while ($i < count($aulasHoje)) {
        $aula = $aulasHoje[$i];
        if ($aula['tipo_aula'] === 'teorica') {
            $aulasHojeProcessadas[] = ['type' => 'teorica', 'aula' => $aula];
            $i++;
            continue;
        }
        $bloco = [$aula];
        $j = $i + 1;
        while ($j < count($aulasHoje)) {
            $proxima = $aulasHoje[$j];
            if ($proxima['tipo_aula'] !== 'pratica') break;
            $ultima = end($bloco);
            $fimUltima = substr($ultima['hora_fim'] ?? '00:00', 0, 5);
            $inicioProxima = substr($proxima['hora_inicio'] ?? '00:00', 0, 5);
            $mesmoAluno = ($ultima['aluno_id'] ?? null) == ($proxima['aluno_id'] ?? null);
            $mesmoVeiculo = ($ultima['veiculo_id'] ?? null) == ($proxima['veiculo_id'] ?? null);
            $consecutiva = ($fimUltima === $inicioProxima) && $mesmoAluno && $mesmoVeiculo;
            if (!$consecutiva) break;
            $bloco[] = $proxima;
            $j++;
        }
        $i = $j;
        if (count($bloco) > 1) {
            $primeira = $bloco[0];
            $ultima = end($bloco);
            $aulasHojeProcessadas[] = [
                'type' => 'bloco',
                'aulas' => $bloco,
                'first_lesson' => $primeira,
                'hora_inicio' => $primeira['hora_inicio'],
                'hora_fim' => $ultima['hora_fim'],
                'status' => array_reduce($bloco, function($carry, $a) {
                    if (($a['status'] ?? '') === 'em_andamento') return 'em_andamento';
                    return $carry;
                }, $bloco[0]['status'] ?? 'agendada'),
                'is_group' => true
            ];
        } else {
            $aulasHojeProcessadas[] = ['type' => 'pratica_single', 'aula' => $bloco[0]];
        }
    }
}

// CORREÇÃO: Selecionar próxima aula pendente (igual ao desktop)
// Selecionar primeira aula pendente (status IN ('agendada','em_andamento')) ordenada por horário
$aulasPendentes = array_filter($aulasHoje, function($aula) {
    if ($aula['tipo_aula'] === 'pratica') {
        return in_array($aula['status'] ?? '', ['agendada', 'em_andamento']);
    } else {
        // Teórica: pendente se não tem chamada registrada E status não é 'realizada'
        return !($aula['chamada_registrada'] ?? false) && ($aula['status'] ?? '') !== 'realizada';
    }
});

if (!empty($aulasPendentes)) {
    // Primeira pendente ordenada por horário
    $proximaAula = reset($aulasPendentes);
} else {
    // Se não há pendentes, pode mostrar a última concluída apenas para visualização (sem ações)
    $aulasConcluidas = array_filter($aulasHoje, function($aula) {
        if ($aula['tipo_aula'] === 'pratica') {
            return ($aula['status'] ?? '') === 'concluida';
        } else {
            return ($aula['chamada_registrada'] ?? false) || ($aula['status'] ?? '') === 'realizada';
        }
    });
    $proximaAula = !empty($aulasConcluidas) ? end($aulasConcluidas) : null;
}

// CORREÇÃO: Calcular contadores baseados APENAS nas aulas do array $aulasHoje (mesmo dataset da tabela)
$statsHoje = [
    'total_aulas' => 0,
    'pendentes' => 0,
    'concluidas' => 0
];

if ($instrutor['id'] && !empty($aulasHoje)) {
    $totalAulas = count($aulasHoje);
    $concluidas = 0;
    $pendentes = 0;
    
    foreach ($aulasHoje as $aula) {
        if ($aula['tipo_aula'] === 'pratica') {
            // Aula prática: regras simples baseadas apenas em status
            $status = $aula['status'] ?? '';
            if ($status === 'concluida') {
                $concluidas++;
            } elseif (in_array($status, ['agendada', 'em_andamento'])) {
                // NUNCA contar 'concluida' nem 'cancelada' como pendente
                $pendentes++;
            }
            // 'cancelada' não conta em nenhum dos dois
        } else {
            // Aula teórica: considerar status do banco
            $status = $aula['status'] ?? '';
            // Concluída se status = 'realizada' (independente de chamada_registrada)
            if ($status === 'realizada') {
                $concluidas++;
            } elseif ($status !== 'cancelada') {
                // Pendente: qualquer outro status que não seja 'realizada' nem 'cancelada'
                // (inclui 'agendada' e outros estados possíveis)
                $pendentes++;
            }
            // 'cancelada' não conta em nenhum dos dois
        }
    }
    
    $statsHoje = [
        'total_aulas' => $totalAulas,
        'pendentes' => $pendentes,
        'concluidas' => $concluidas
    ];
}

$proximasAulas = array_merge($proximasAulasPraticas, $proximasAulasTeoricas);
usort($proximasAulas, function($a, $b) {
    $dataA = $a['data_aula'] . ' ' . ($a['hora_inicio'] ?? '00:00:00');
    $dataB = $b['data_aula'] . ' ' . ($b['hora_inicio'] ?? '00:00:00');
    return strcmp($dataA, $dataB);
});
$proximasAulas = array_slice($proximasAulas, 0, 10);

// Buscar notificações não lidas
$notificacoesNaoLidas = $notificacoes->buscarNotificacoesNaoLidas($user['id'], 'instrutor');

// Buscar turmas teóricas do instrutor (CORRIGIDO: usar turmas_teoricas e turma_matriculas)
// CORREÇÃO: turmas_teoricas não tem instrutor_id - o instrutor está em turma_aulas_agendadas
$turmasTeoricas = $db->fetchAll("
    SELECT 
        tt.*,
        COUNT(DISTINCT tm.id) as total_alunos
    FROM turmas_teoricas tt
    INNER JOIN turma_aulas_agendadas taa_instrutor ON tt.id = taa_instrutor.turma_id 
        AND taa_instrutor.instrutor_id = ?
    LEFT JOIN turma_matriculas tm ON tt.id = tm.turma_id 
        AND tm.status IN ('matriculado', 'cursando', 'concluido')
    WHERE tt.status IN ('ativa', 'completa', 'cursando', 'concluida')
    GROUP BY tt.id
    ORDER BY tt.nome ASC
", [$user['id']]);

// Configurar variáveis para o layout
$pageTitle = 'Dashboard - ' . htmlspecialchars($instrutor['nome']);
$homeUrl = '/instrutor/dashboard.php';

// Incluir layout mobile-first
ob_start();
?>

<!-- Conteúdo do Dashboard -->
<div class="row">
    <div class="col-12">
        <div class="card card-mobile mb-mobile">
            <div class="card-header">
                <h2 class="card-title fs-mobile-2">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Olá, <?php echo htmlspecialchars($instrutor['nome']); ?>!
                </h2>
                <p class="card-subtitle text-muted mb-0">Suas aulas e turmas de hoje</p>
            </div>
        </div>
    </div>
</div>

<!-- Notificações -->
<?php if (!empty($notificacoesNaoLidas)): ?>
<div class="row">
    <div class="col-12">
        <div class="card card-mobile mb-mobile">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title fs-mobile-3 mb-0">
                    <i class="fas fa-bell me-2"></i>
                    Notificações
                </h3>
                <span class="badge bg-primary"><?php echo count($notificacoesNaoLidas); ?></span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($notificacoesNaoLidas as $notificacao): ?>
                <div class="border-bottom p-3" data-id="<?php echo $notificacao['id']; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?php echo htmlspecialchars($notificacao['titulo']); ?></h6>
                            <p class="text-muted mb-1 fs-mobile-6"><?php echo htmlspecialchars($notificacao['mensagem']); ?></p>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($notificacao['criado_em'])); ?></small>
                        </div>
                        <button class="btn btn-sm btn-outline-primary marcar-lida" data-id="<?php echo $notificacao['id']; ?>">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Aulas de Hoje -->
<div class="row">
    <div class="col-12">
        <div class="card card-mobile mb-mobile">
            <div class="card-header">
                <h3 class="card-title fs-mobile-3">
                    <i class="fas fa-calendar-day me-2"></i>
                    Aulas de Hoje
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($aulasHoje)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Nenhuma aula hoje</h5>
                    <p class="text-muted fs-mobile-6">Você não possui aulas agendadas para hoje.</p>
                </div>
                <?php else: ?>
                <div class="aula-list">
                    <?php foreach ($aulasHojeProcessadas as $item): ?>
                    <?php
                    $aula = ($item['type'] === 'teorica') ? $item['aula'] : (($item['type'] === 'bloco') ? $item['first_lesson'] : $item['aula']);
                    $isBloco = ($item['type'] === 'bloco');
                    $blocoAulas = $isBloco ? $item['aulas'] : [$aula];
                    $blocoCount = count($blocoAulas);
                    $blocoStatus = $isBloco ? $item['status'] : ($aula['status'] ?? 'agendada');
                    $aulaIdsStr = $isBloco ? implode(',', array_column($blocoAulas, 'id')) : (string)$aula['id'];
                    $aulasValidasBloco = $isBloco ? array_filter($blocoAulas, fn($a) => ($a['status'] ?? '') !== 'cancelada') : [$aula];
                    ?>
                    <div class="card mb-3 aula-item" data-aula-id="<?php echo $aula['id']; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-<?php echo $aula['tipo_aula'] === 'teorica' ? 'info' : 'success'; ?> mb-1">
                                        <?php echo ucfirst($aula['tipo_aula']); ?>
                                    </span>
                                    <?php if ($isBloco): ?>
                                    <span class="badge bg-light text-primary ms-1" style="font-size: 0.7rem;"><?php echo $blocoCount; ?> aulas</span>
                                    <?php endif; ?>
                                    <h6 class="mb-1">
                                        <?php if ($aula['tipo_aula'] === 'pratica' && isset($aula['aluno_id'])): ?>
                                        <a href="#" onclick="abrirModalAluno(<?= $aula['aluno_id'] ?>); return false;" 
                                           class="text-primary text-decoration-none" 
                                           title="Ver detalhes do aluno">
                                            <?php echo htmlspecialchars($aula['aluno_nome']); ?>
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary ml-2 p-1" 
                                                onclick="abrirModalAluno(<?= $aula['aluno_id'] ?>); return false;"
                                                title="Ver detalhes do aluno"
                                                style="line-height: 1; min-width: 28px; height: 28px; vertical-align: middle;">
                                            <i class="fas fa-user" style="font-size: 0.75rem;"></i>
                                        </button>
                                        <?php else: ?>
                                        <?php echo htmlspecialchars($aula['aluno_nome']); ?>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('H:i', strtotime($isBloco ? $item['hora_inicio'] : $aula['hora_inicio'])); ?> - 
                                        <?php echo date('H:i', strtotime($isBloco ? $item['hora_fim'] : $aula['hora_fim'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?php echo ($isBloco ? $blocoStatus : $aula['status']) === 'agendada' ? 'warning' : (($isBloco ? $blocoStatus : $aula['status']) === 'em_andamento' ? 'primary' : 'success'); ?>">
                                    <?php echo ucfirst($isBloco ? $blocoStatus : $aula['status']); ?>
                                </span>
                            </div>
                            
                            <div class="aula-detalhes mb-3">
                                <?php if ($aula['veiculo_modelo']): ?>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="fas fa-car text-muted me-2"></i>
                                    <small><?php echo htmlspecialchars($aula['veiculo_modelo']); ?> - <?php echo htmlspecialchars($aula['veiculo_placa']); ?></small>
                                </div>
                                <?php endif; ?>
                                <?php if ($isBloco): ?>
                                <div class="small text-muted mb-1" style="font-size: 0.75rem;">
                                    <?php foreach ($blocoAulas as $idx => $a): ?>
                                    Aula <?= $idx + 1 ?>: <?= ($a['status'] ?? '') === 'cancelada' ? '<span style="color: #dc2626; text-decoration: line-through;">cancelada</span>' : ($a['status'] ?? 'agendada'); ?>
                                    <?= $idx < count($blocoAulas) - 1 ? ' · ' : ''; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['tipo_aula'] === 'pratica'): ?>
                                    <?php if ($aula['status'] === 'em_andamento' && isset($aula['km_inicial']) && $aula['km_inicial'] !== null): ?>
                                    <div class="d-flex align-items-center mb-1">
                                        <small class="text-muted">KM inicial: <?php echo number_format($aula['km_inicial'], 0, ',', '.'); ?></small>
                                    </div>
                                    <?php elseif ($aula['status'] === 'concluida' && isset($aula['km_inicial']) && $aula['km_inicial'] !== null && isset($aula['km_final']) && $aula['km_final'] !== null): ?>
                                    <?php $kmRodados = $aula['km_final'] - $aula['km_inicial']; ?>
                                    <div class="d-flex align-items-center mb-1">
                                        <small class="text-muted">KM: <?php echo number_format($aula['km_inicial'], 0, ',', '.'); ?> → <?php echo number_format($aula['km_final'], 0, ',', '.'); ?> (<?php echo $kmRodados >= 0 ? '+' : ''; ?><?php echo number_format($kmRodados, 0, ',', '.'); ?>)</small>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($aula['observacoes']): ?>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-sticky-note text-muted me-2"></i>
                                    <small><?php echo htmlspecialchars($aula['observacoes']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php 
                            $mostrarBotoes = true;
                            if ($aula['tipo_aula'] === 'pratica') {
                                $mostrarBotoes = !in_array($blocoStatus, ['concluida']) && !empty($aulasValidasBloco);
                            } else {
                                $mostrarBotoes = !($aula['chamada_registrada'] ?? false) && ($aula['status'] ?? '') !== 'realizada';
                            }
                            ?>
                            <?php if ($mostrarBotoes): ?>
                            <div class="d-grid gap-2">
                                <?php if ($aula['tipo_aula'] === 'teorica'): ?>
                                <button class="btn btn-primary btn-mobile fazer-chamada" 
                                        data-aula-id="<?php echo $aula['id']; ?>"
                                        data-turma-id="<?php echo $aula['turma_id']; ?>">
                                    <i class="fas fa-clipboard-list me-2"></i>
                                    Fazer Chamada
                                </button>
                                <button class="btn btn-outline-primary btn-mobile abrir-diario" 
                                        data-aula-id="<?php echo $aula['id']; ?>"
                                        data-turma-id="<?php echo $aula['turma_id']; ?>">
                                    <i class="fas fa-book me-2"></i>
                                    Abrir Diário
                                </button>
                                <?php else: ?>
                                <?php if ($blocoStatus === 'agendada'): ?>
                                <button class="btn btn-primary btn-mobile <?php echo $isBloco ? 'iniciar-bloco' : 'iniciar-aula'; ?>" 
                                        data-aula-id="<?php echo $aula['id']; ?>"
                                        data-aula-ids="<?php echo htmlspecialchars($aulaIdsStr); ?>">
                                    <i class="fas fa-play me-2"></i>
                                    <?php echo $isBloco ? 'Iniciar Bloco' : 'Iniciar Aula'; ?>
                                </button>
                                <?php elseif ($blocoStatus === 'em_andamento'): ?>
                                <button class="btn btn-success btn-mobile <?php echo $isBloco ? 'finalizar-bloco' : 'finalizar-aula'; ?>" 
                                        data-aula-id="<?php echo $aula['id']; ?>"
                                        data-aula-ids="<?php echo htmlspecialchars($aulaIdsStr); ?>">
                                    <i class="fas fa-stop me-2"></i>
                                    <?php echo $isBloco ? 'Finalizar Bloco' : 'Finalizar Aula'; ?>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-warning btn-mobile cancelar-aula" 
                                        data-aula-id="<?php echo $aula['id']; ?>"
                                        data-data="<?php echo $aula['data_aula']; ?>"
                                        data-hora="<?php echo $aula['hora_inicio']; ?>">
                                    <i class="fas fa-times me-2"></i>
                                    Cancelar
                                </button>
                                
                                <button class="btn btn-outline-info btn-mobile transferir-aula" 
                                        data-aula-id="<?php echo $aula['id']; ?>"
                                        data-data="<?php echo $aula['data_aula']; ?>"
                                        data-hora="<?php echo $aula['hora_inicio']; ?>">
                                    <i class="fas fa-exchange-alt me-2"></i>
                                    Transferir
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>Aula já concluída
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Próximas Aulas -->
<div class="row">
    <div class="col-12">
        <div class="card card-mobile mb-mobile">
            <div class="card-header">
                <h3 class="card-title fs-mobile-3">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Próximas Aulas
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($proximasAulas)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Nenhuma aula agendada</h5>
                    <p class="text-muted fs-mobile-6">Você não possui aulas agendadas para os próximos 7 dias.</p>
                </div>
                <?php else: ?>
                <div class="aula-list">
                    <?php foreach ($proximasAulas as $aula): ?>
                    <div class="card mb-3 aula-item" data-aula-id="<?php echo $aula['id']; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-<?php echo $aula['tipo_aula'] === 'teorica' ? 'info' : 'success'; ?> mb-1">
                                        <?php echo ucfirst($aula['tipo_aula']); ?>
                                    </span>
                                    <h6 class="mb-1">
                                        <?php if ($aula['tipo_aula'] === 'pratica' && isset($aula['aluno_id'])): ?>
                                        <a href="#" onclick="abrirModalAluno(<?= $aula['aluno_id'] ?>); return false;" 
                                           class="text-primary text-decoration-none" 
                                           title="Ver detalhes do aluno">
                                            <?php echo htmlspecialchars($aula['aluno_nome']); ?>
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary ml-2 p-1" 
                                                onclick="abrirModalAluno(<?= $aula['aluno_id'] ?>); return false;"
                                                title="Ver detalhes do aluno"
                                                style="line-height: 1; min-width: 28px; height: 28px; vertical-align: middle;">
                                            <i class="fas fa-user" style="font-size: 0.75rem;"></i>
                                        </button>
                                        <?php else: ?>
                                        <?php echo htmlspecialchars($aula['aluno_nome']); ?>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?> - 
                                        <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?php echo $aula['status'] === 'agendada' ? 'warning' : 'success'; ?>">
                                    <?php echo ucfirst($aula['status']); ?>
                                </span>
                            </div>
                            
                            <div class="aula-detalhes mb-3">
                                <?php if ($aula['veiculo_modelo']): ?>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="fas fa-car text-muted me-2"></i>
                                    <small><?php echo htmlspecialchars($aula['veiculo_modelo']); ?> - <?php echo htmlspecialchars($aula['veiculo_placa']); ?></small>
                                </div>
                                <?php endif; ?>
                                <?php if ($aula['tipo_aula'] === 'pratica'): ?>
                                    <?php if ($aula['status'] === 'em_andamento' && isset($aula['km_inicial']) && $aula['km_inicial'] !== null): ?>
                                    <div class="d-flex align-items-center mb-1">
                                        <small class="text-muted">KM inicial: <?php echo number_format($aula['km_inicial'], 0, ',', '.'); ?></small>
                                    </div>
                                    <?php elseif ($aula['status'] === 'concluida' && isset($aula['km_inicial']) && $aula['km_inicial'] !== null && isset($aula['km_final']) && $aula['km_final'] !== null): ?>
                                    <?php $kmRodados = $aula['km_final'] - $aula['km_inicial']; ?>
                                    <div class="d-flex align-items-center mb-1">
                                        <small class="text-muted">KM: <?php echo number_format($aula['km_inicial'], 0, ',', '.'); ?> → <?php echo number_format($aula['km_final'], 0, ',', '.'); ?> (<?php echo $kmRodados >= 0 ? '+' : ''; ?><?php echo number_format($kmRodados, 0, ',', '.'); ?>)</small>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($aula['observacoes']): ?>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-sticky-note text-muted me-2"></i>
                                    <small><?php echo htmlspecialchars($aula['observacoes']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php 
                            // CORREÇÃO: Só mostrar botões se a aula não estiver concluída ou cancelada
                            $mostrarBotoes = true;
                            if ($aula['tipo_aula'] === 'pratica') {
                                $mostrarBotoes = !in_array($aula['status'] ?? '', ['concluida', 'cancelada']);
                            } else {
                                // Teórica: não mostrar botões se status = 'realizada'
                                $mostrarBotoes = ($aula['status'] ?? '') !== 'realizada';
                            }
                            ?>
                            <?php if ($mostrarBotoes): ?>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-warning btn-mobile cancelar-aula" 
                                        data-aula-id="<?php echo $aula['id']; ?>"
                                        data-data="<?php echo $aula['data_aula']; ?>"
                                        data-hora="<?php echo $aula['hora_inicio']; ?>">
                                    <i class="fas fa-times me-2"></i>
                                    Cancelar
                                </button>
                                
                                <button class="btn btn-outline-info btn-mobile transferir-aula" 
                                        data-aula-id="<?php echo $aula['id']; ?>"
                                        data-data="<?php echo $aula['data_aula']; ?>"
                                        data-hora="<?php echo $aula['hora_inicio']; ?>">
                                    <i class="fas fa-exchange-alt me-2"></i>
                                    Transferir
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>Aula já concluída
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Turmas Teóricas -->
<?php if (!empty($turmasTeoricas)): ?>
<div class="row">
    <div class="col-12">
        <div class="card card-mobile mb-mobile">
            <div class="card-header">
                <h3 class="card-title fs-mobile-3">
                    <i class="fas fa-users-class me-2"></i>
                    Minhas Turmas Teóricas
                </h3>
            </div>
            <div class="card-body">
                <div class="turma-list">
                    <?php foreach ($turmasTeoricas as $turma): ?>
                    <div class="card mb-3 turma-item" data-turma-id="<?php echo $turma['id']; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($turma['nome']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($turma['descricao']); ?></small>
                                </div>
                                <span class="badge bg-info"><?php echo $turma['total_alunos']; ?> alunos</span>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="/admin/index.php?page=turma-chamada&turma_id=<?php echo $turma['id']; ?>" 
                                   class="btn btn-primary btn-mobile">
                                    <i class="fas fa-clipboard-list me-2"></i>
                                    Fazer Chamada
                                </a>
                                <a href="/admin/index.php?page=turma-diario&turma_id=<?php echo $turma['id']; ?>" 
                                   class="btn btn-outline-primary btn-mobile">
                                    <i class="fas fa-book me-2"></i>
                                    Abrir Diário
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Ações Rápidas -->
<div class="row">
    <div class="col-12">
        <div class="card card-mobile mb-mobile">
            <div class="card-header">
                <h3 class="card-title fs-mobile-3">
                    <i class="fas fa-bolt me-2"></i>
                    Ações Rápidas
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="/instrutor/aulas.php" class="btn btn-primary btn-mobile w-100">
                            <i class="fas fa-list me-2"></i>
                            Ver Todas as Aulas
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/instrutor/turmas.php" class="btn btn-secondary btn-mobile w-100">
                            <i class="fas fa-users-class me-2"></i>
                            Minhas Turmas
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/instrutor/ocorrencias.php" class="btn btn-outline-primary btn-mobile w-100">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Registrar Ocorrência
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/instrutor/contato.php" class="btn btn-outline-secondary btn-mobile w-100">
                            <i class="fas fa-phone me-2"></i>
                            Contatar CFC
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Cancelamento/Transferência -->
<div class="modal fade" id="modalAcao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitulo">Cancelar Aula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAcao" class="form-mobile">
                    <input type="hidden" id="aulaId" name="aula_id">
                    <input type="hidden" id="tipoAcao" name="tipo_acao">
                    
                    <div class="mb-3">
                        <label class="form-label">Data da Aula</label>
                        <input type="text" id="dataAula" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Horário da Aula</label>
                        <input type="text" id="horaAula" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3" id="novaDataGroup" style="display: none;">
                        <label class="form-label">Nova Data</label>
                        <input type="date" id="novaData" name="nova_data" class="form-control">
                    </div>
                    
                    <div class="mb-3" id="novaHoraGroup" style="display: none;">
                        <label class="form-label">Novo Horário</label>
                        <input type="time" id="novaHora" name="nova_hora" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <select id="motivo" name="motivo" class="form-select">
                            <option value="">Selecione um motivo</option>
                            <option value="problema_saude">Problema de saúde</option>
                            <option value="imprevisto_pessoal">Imprevisto pessoal</option>
                            <option value="problema_veiculo">Problema com veículo</option>
                            <option value="falta_aluno">Falta do aluno</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Justificativa *</label>
                        <textarea id="justificativa" name="justificativa" class="form-control" 
                                  placeholder="Descreva o motivo da ação..." required rows="3"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Política:</strong> Cancelamentos devem ser feitos com no mínimo 24 horas de antecedência.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="enviarAcao()">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript específico do dashboard do instrutor
document.addEventListener('DOMContentLoaded', function() {
    // Botões de cancelamento
    document.querySelectorAll('.cancelar-aula').forEach(btn => {
        btn.addEventListener('click', function() {
            const aulaId = this.dataset.aulaId;
            const data = this.dataset.data;
            const hora = this.dataset.hora;
            abrirModal('cancelar', aulaId, data, hora);
        });
    });

    // Botões de transferência
    document.querySelectorAll('.transferir-aula').forEach(btn => {
        btn.addEventListener('click', function() {
            const aulaId = this.dataset.aulaId;
            const data = this.dataset.data;
            const hora = this.dataset.hora;
            abrirModal('transferir', aulaId, data, hora);
        });
    });

    // Iniciar Bloco
    document.querySelectorAll('.iniciar-bloco').forEach(btn => {
        btn.addEventListener('click', async function() {
            const aulaIds = (this.dataset.aulaIds || this.dataset.aulaId || '').toString().split(',').filter(Boolean);
            if (aulaIds.length === 0) return;
            const kmInicialStr = prompt('Informe o KM inicial do veículo (será aplicado a todas as aulas do bloco):');
            if (kmInicialStr === null || kmInicialStr.trim() === '') return;
            const kmInicial = Number(kmInicialStr.trim());
            if (isNaN(kmInicial) || kmInicial < 0) {
                showToast('KM inicial deve ser um número válido (>= 0).', 'error');
                return;
            }
            showLoading('Iniciando bloco...');
            try {
                const response = await fetch('../admin/api/instrutor-aulas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tipo_acao: 'iniciar_bloco', aula_ids: aulaIds.join(','), km_inicial: kmInicial })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message || 'Bloco iniciado com sucesso!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message || 'Erro ao iniciar bloco.', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showToast('Erro de conexão. Tente novamente.', 'error');
            } finally {
                hideLoading();
            }
        });
    });

    // Finalizar Bloco
    document.querySelectorAll('.finalizar-bloco').forEach(btn => {
        btn.addEventListener('click', async function() {
            const aulaIds = (this.dataset.aulaIds || this.dataset.aulaId || '').toString().split(',').filter(Boolean);
            if (aulaIds.length === 0) return;
            const kmFinalStr = prompt('Informe o KM final do veículo (será aplicado a todas as aulas do bloco):');
            if (kmFinalStr === null || kmFinalStr.trim() === '') return;
            const kmFinal = Number(kmFinalStr.trim());
            if (isNaN(kmFinal) || kmFinal < 0) {
                showToast('KM final deve ser um número válido (>= 0).', 'error');
                return;
            }
            showLoading('Finalizando bloco...');
            try {
                const response = await fetch('../admin/api/instrutor-aulas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tipo_acao: 'finalizar_bloco', aula_ids: aulaIds.join(','), km_final: kmFinal })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message || 'Bloco finalizado com sucesso!', 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message || 'Erro ao finalizar bloco.', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showToast('Erro de conexão. Tente novamente.', 'error');
            } finally {
                hideLoading();
            }
        });
    });

    // TAREFA 2.2 - Botões de iniciar aula
    document.querySelectorAll('.iniciar-aula').forEach(btn => {
        btn.addEventListener('click', async function() {
            const aulaId = this.dataset.aulaId;
            
            // Coletar KM inicial via prompt
            const kmInicialStr = prompt('Informe o KM inicial do veículo:');
            
            // Se cancelou ou vazio, abortar
            if (kmInicialStr === null || kmInicialStr.trim() === '') {
                return;
            }
            
            // Validar numérico
            const kmInicial = Number(kmInicialStr.trim());
            if (isNaN(kmInicial)) {
                showToast('KM inicial deve ser um número válido.', 'error');
                return;
            }
            
            // Validar >= 0
            if (kmInicial < 0) {
                showToast('KM inicial deve ser maior ou igual a zero.', 'error');
                return;
            }
            
            showLoading('Iniciando aula...');
            
            try {
                const response = await fetch('../admin/api/instrutor-aulas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        aula_id: aulaId,
                        tipo_acao: 'iniciar',
                        km_inicial: kmInicial
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Aula iniciada com sucesso!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(result.message || 'Erro ao iniciar aula.', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showToast('Erro de conexão. Tente novamente.', 'error');
            } finally {
                hideLoading();
            }
        });
    });

    // TAREFA 2.2 - Botões de finalizar aula
    document.querySelectorAll('.finalizar-aula').forEach(btn => {
        btn.addEventListener('click', async function() {
            const aulaId = this.dataset.aulaId;
            
            // Coletar KM final via prompt
            const kmFinalStr = prompt('Informe o KM final do veículo:');
            
            // Se cancelou ou vazio, abortar
            if (kmFinalStr === null || kmFinalStr.trim() === '') {
                return;
            }
            
            // Validar numérico
            const kmFinal = Number(kmFinalStr.trim());
            if (isNaN(kmFinal)) {
                showToast('KM final deve ser um número válido.', 'error');
                return;
            }
            
            // Validar >= 0
            if (kmFinal < 0) {
                showToast('KM final deve ser maior ou igual a zero.', 'error');
                return;
            }
            
            showLoading('Finalizando aula...');
            
            try {
                const response = await fetch('../admin/api/instrutor-aulas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        aula_id: aulaId,
                        tipo_acao: 'finalizar',
                        km_final: kmFinal
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Aula finalizada com sucesso!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(result.message || 'Erro ao finalizar aula.', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showToast('Erro de conexão. Tente novamente.', 'error');
            } finally {
                hideLoading();
            }
        });
    });

    // Botões de marcar notificação como lida
    document.querySelectorAll('.marcar-lida').forEach(btn => {
        btn.addEventListener('click', function() {
            const notificacaoId = this.dataset.id;
            marcarNotificacaoComoLida(notificacaoId);
        });
    });
});

function abrirModal(tipo, aulaId, data, hora) {
    document.getElementById('tipoAcao').value = tipo;
    document.getElementById('aulaId').value = aulaId;
    document.getElementById('dataAula').value = data;
    document.getElementById('horaAula').value = hora;
    
    const modal = new bootstrap.Modal(document.getElementById('modalAcao'));
    const titulo = document.getElementById('modalTitulo');
    const novaDataGroup = document.getElementById('novaDataGroup');
    const novaHoraGroup = document.getElementById('novaHoraGroup');
    
    if (tipo === 'transferir') {
        titulo.textContent = 'Transferir Aula';
        novaDataGroup.style.display = 'block';
        novaHoraGroup.style.display = 'block';
    } else {
        titulo.textContent = 'Cancelar Aula';
        novaDataGroup.style.display = 'none';
        novaHoraGroup.style.display = 'none';
    }
    
    modal.show();
}

async function enviarAcao() {
    const form = document.getElementById('formAcao');
    const formData = new FormData(form);
    
    if (!formData.get('justificativa').trim()) {
        showToast('Por favor, preencha a justificativa.', 'error');
        return;
    }

    showLoading('Enviando solicitação...');

    try {
        const response = await fetch('../admin/api/agendamento.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                aula_id: formData.get('aula_id'),
                acao: formData.get('tipo_acao'),
                nova_data: formData.get('nova_data'),
                nova_hora: formData.get('nova_hora'),
                motivo: formData.get('motivo'),
                justificativa: formData.get('justificativa')
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Ação realizada com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalAcao')).hide();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Erro ao realizar ação.', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro de conexão. Tente novamente.', 'error');
    } finally {
        hideLoading();
    }
}

async function marcarNotificacaoComoLida(notificacaoId) {
    try {
        const response = await fetch('../admin/api/notificacoes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notificacao_id: notificacaoId
            })
        });

        const result = await response.json();

        if (result.success) {
            const notificacaoItem = document.querySelector(`[data-id="${notificacaoId}"]`);
            if (notificacaoItem) {
                notificacaoItem.remove();
            }
            
            const badge = document.querySelector('.badge');
            if (badge) {
                const count = parseInt(badge.textContent) - 1;
                if (count > 0) {
                    badge.textContent = count;
                } else {
                    badge.parentElement.parentElement.remove();
                }
            }
        }
    } catch (error) {
        console.error('Erro:', error);
    }
}

// Função para abrir modal de aluno (suporta aulas práticas e teóricas)
function abrirModalAluno(alunoId, turmaId = null) {
    // Verificar se Bootstrap está disponível
    if (typeof bootstrap === 'undefined') {
        alert('Erro: Bootstrap não está carregado. Recarregue a página.');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modalAlunoInstrutor'));
    const modalBody = document.getElementById('modalAlunoInstrutorBody');
    
    // Mostrar loading
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-2">Carregando informações do aluno...</p>
        </div>
    `;
    
    // Abrir modal
    modal.show();
    
    // Montar URL (turma_id é opcional para aulas práticas)
    let url = '../admin/api/aluno-detalhes-instrutor.php?aluno_id=' + alunoId;
    if (turmaId) {
        url += '&turma_id=' + turmaId;
    }
    
    // Buscar dados do aluno via endpoint restrito
    fetch(url)
        .then(response => {
            if (!response.ok) {
                if (response.status === 403) {
                    throw new Error('Você não tem permissão para visualizar este aluno');
                }
                throw new Error('Erro ao carregar dados do aluno');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const aluno = data.aluno;
                const turma = data.turma || null;
                const matricula = data.matricula || null;
                const frequencia = data.frequencia || null;
                
                // Formatar CPF
                function formatarCPF(cpf) {
                    if (!cpf) return 'Não informado';
                    const cpfLimpo = cpf.replace(/\D/g, '');
                    return cpfLimpo.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                }
                
                // Formatar telefone
                function formatarTelefone(tel) {
                    if (!tel) return 'Não informado';
                    const telLimpo = tel.replace(/\D/g, '');
                    if (telLimpo.length === 11) {
                        return telLimpo.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                    } else if (telLimpo.length === 10) {
                        return telLimpo.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                    }
                    return tel;
                }
                
                // Formatar data de nascimento
                const dataNasc = aluno.data_nascimento ? new Date(aluno.data_nascimento).toLocaleDateString('pt-BR') : 'Não informado';
                
                // Categoria CNH
                const categoriaCNH = aluno.categoria_cnh || 'Não informado';
                
                // Montar HTML
                let turmaHtml = '';
                if (turma && matricula) {
                    let frequenciaHtml = '';
                    if (frequencia) {
                        const freqPercent = frequencia.frequencia_percentual.toFixed(1);
                        const freqBadgeClass = freqPercent >= 75 ? 'bg-success' : (freqPercent >= 60 ? 'bg-warning' : 'bg-danger');
                        frequenciaHtml = `
                            <dt class="col-sm-4">Frequência:</dt>
                            <dd class="col-sm-8">
                                <span class="badge ${freqBadgeClass}">${freqPercent}%</span>
                                <small class="text-muted ms-2">
                                    (${frequencia.total_presentes} presentes / ${frequencia.total_aulas} aulas)
                                </small>
                            </dd>
                        `;
                    }
                    
                    turmaHtml = `
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <h6>Matrícula na Turma</h6>
                                <dl class="row mb-3">
                                    <dt class="col-sm-4">Turma:</dt>
                                    <dd class="col-sm-8">${turma.nome}</dd>
                                    
                                    <dt class="col-sm-4">Status:</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-primary">${matricula.status}</span>
                                    </dd>
                                    
                                    <dt class="col-sm-4">Data Matrícula:</dt>
                                    <dd class="col-sm-8">${new Date(matricula.data_matricula).toLocaleDateString('pt-BR')}</dd>
                                    
                                    ${frequenciaHtml}
                                </dl>
                            </div>
                        </div>
                    `;
                }
                
                modalBody.innerHTML = `
                    <div class="text-center mb-3">
                        ${aluno.foto && aluno.foto.trim() !== '' 
                            ? `<img src="../${aluno.foto}" 
                                   alt="Foto do aluno ${aluno.nome}" 
                                   class="rounded-circle" 
                                   style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #dee2e6;"
                                   onerror="this.outerHTML='<div class=\\'rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto\\' style=\\'width:100px;height:100px;border:3px solid #dee2e6;\\'><i class=\\'fas fa-user fa-3x text-white\\'></i></div>'">`
                            : `<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto" 
                                   style="width: 100px; height: 100px; border: 3px solid #dee2e6;">
                                    <i class="fas fa-user fa-3x text-white"></i>
                                  </div>`
                        }
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Dados Pessoais</h6>
                            <dl class="row mb-3">
                                <dt class="col-sm-4">Nome:</dt>
                                <dd class="col-sm-8"><strong>${aluno.nome}</strong></dd>
                                
                                <dt class="col-sm-4">CPF:</dt>
                                <dd class="col-sm-8">${formatarCPF(aluno.cpf)}</dd>
                                
                                <dt class="col-sm-4">Data Nascimento:</dt>
                                <dd class="col-sm-8">${dataNasc}</dd>
                                
                                <dt class="col-sm-4">Categoria CNH:</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-info">${categoriaCNH}</span>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <h6>Contato</h6>
                            <dl class="row mb-3">
                                <dt class="col-sm-4">E-mail:</dt>
                                <dd class="col-sm-8">
                                    ${aluno.email ? `<a href="mailto:${aluno.email}">${aluno.email}</a>` : 'Não informado'}
                                </dd>
                                
                                <dt class="col-sm-4">Telefone:</dt>
                                <dd class="col-sm-8">
                                    ${aluno.telefone ? `
                                        <a href="tel:${aluno.telefone.replace(/\D/g, '')}">${formatarTelefone(aluno.telefone)}</a>
                                        <a href="https://wa.me/55${aluno.telefone.replace(/\D/g, '')}" 
                                           target="_blank" 
                                           class="btn btn-sm btn-success ms-2" 
                                           title="Abrir WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    ` : 'Não informado'}
                                </dd>
                            </dl>
                        </div>
                    </div>
                    
                    ${turmaHtml}
                `;
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ${data.message || 'Erro ao carregar dados do aluno'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> ${error.message || 'Erro ao carregar dados do aluno'}
                </div>
            `;
        });
}
</script>

<!-- Modal para Visualizar Aluno -->
<div class="modal fade" id="modalAlunoInstrutor" tabindex="-1" aria-labelledby="modalAlunoInstrutorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAlunoInstrutorLabel">
                    <i class="fas fa-user"></i> Detalhes do Aluno
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="modalAlunoInstrutorBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2">Carregando informações do aluno...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php
// Finalizar buffer e incluir layout
$pageContent = ob_get_clean();
include __DIR__ . '/../includes/layout/mobile-first.php';
?>
