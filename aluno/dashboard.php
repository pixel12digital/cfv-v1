<?php
/**
 * Dashboard do Aluno - Mobile First + PWA
 * Interface focada em usabilidade móvel
 */

// Redirect absoluto para login legado (evita tela em branco por redirect relativo ou 500)
$alunoLoginUrl = '/aluno/login.php';

// Qualquer exceção não capturada (incl. após login, na renderização) redireciona para login em vez de 500 em branco
set_exception_handler(function (Throwable $e) use ($alunoLoginUrl) {
    if (function_exists('error_log')) {
        error_log('[aluno/dashboard] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
    if (!headers_sent()) {
        header('Location: ' . $alunoLoginUrl . '?erro=system', true, 302);
        exit;
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($alunoLoginUrl) . '"></head><body>Redirecionando para o login...</body></html>';
    exit;
});

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/services/SistemaNotificacoes.php';
} catch (Throwable $e) {
    if (function_exists('error_log')) {
        error_log('[aluno/dashboard] Bootstrap error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
    if (!headers_sent()) {
        header('Location: ' . $alunoLoginUrl . '?erro=system', true, 302);
    } else {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($alunoLoginUrl) . '"></head><body>Redirecionando...</body></html>';
    }
    exit;
}

// Instrumentação e checagem de auth/DB — qualquer falha redireciona para login (evita 500 em branco)
try {
    $dashboardLogged = isLoggedIn();
    if (function_exists('error_log')) {
        $sname = function_exists('session_name') ? session_name() : 'none';
        $sid = function_exists('session_id') ? session_id() : 'none';
        $hasUid = isset($_SESSION['user_id']);
        $hasLa = isset($_SESSION['last_activity']);
        $cookiePresent = isset($_COOKIE['CFC_SESSION']) ? '1' : '0';
        $cp = ini_get('session.cookie_path');
        $cd = ini_get('session.cookie_domain');
        $csec = ini_get('session.cookie_secure');
        $csame = ini_get('session.cookie_samesite');
        $sh = ini_get('session.save_handler');
        $sp = ini_get('session.save_path');
        $strict = ini_get('session.use_strict_mode');
        error_log('[aluno/dashboard] TRACE HTTP_HOST=' . ($_SERVER['HTTP_HOST'] ?? '') . ' REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '') . ' session_name=' . $sname . ' session_id=' . ($sid ?: 'none') . ' cookie_present=' . $cookiePresent . ' cookie_path=' . ($cp ?: '') . ' cookie_domain=' . ($cd ?: '') . ' cookie_secure=' . ($csec ?: '') . ' cookie_samesite=' . ($csame ?: '') . ' save_handler=' . ($sh ?: '') . ' save_path=' . ($sp ?: '') . ' use_strict_mode=' . ($strict ?: '') . ' has_user_id=' . ($hasUid ? 1 : 0) . ' has_last_activity=' . ($hasLa ? 1 : 0) . ' isLoggedIn=' . ($dashboardLogged ? 1 : 0));
    }

    if (!$dashboardLogged) {
        if (function_exists('error_log')) {
            error_log('[aluno/dashboard] redirect_reason=isLoggedIn_false redirect_location=' . $alunoLoginUrl);
        }
        header('Location: ' . $alunoLoginUrl, true, 302);
        exit();
    }

    $user = getCurrentUser();
    if (!$user || ($user['tipo'] ?? '') !== 'aluno') {
        if (function_exists('error_log')) {
            error_log('[aluno/dashboard] redirect_reason=user_not_aluno_or_null tipo=' . ($user['tipo'] ?? 'null') . ' redirect_location=' . $alunoLoginUrl);
        }
        header('Location: ' . $alunoLoginUrl, true, 302);
        exit();
    }

    if (!empty($_GET['first_access']) && $dashboardLogged) {
        $_SESSION['first_access'] = 1;
    }
    if (isset($_GET['dismiss_first_access'])) {
        unset($_SESSION['first_access']);
        header('Location: /aluno/dashboard.php', true, 302);
        exit;
    }

    $db = db();
    $notificacoes = new SistemaNotificacoes();
    $aluno = $db->fetch("SELECT * FROM usuarios WHERE id = ? AND tipo = 'aluno'", [$user['id']]);

    if (!$aluno) {
        header('Location: ' . $alunoLoginUrl, true, 302);
        exit();
    }

    $alunoId = getCurrentAlunoId($user['id']);
} catch (Throwable $e) {
    if (function_exists('error_log')) {
        error_log('[aluno/dashboard] Auth/DB error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
    if (!headers_sent()) {
        header('Location: ' . $alunoLoginUrl . '?erro=system', true, 302);
        exit;
    }
    // fallback se headers já enviados
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($alunoLoginUrl) . '">Redirecionando...';
    exit;
}

/**
 * AUDITORIA DASHBOARD PROXIMAS AULAS - Função para buscar próximas aulas (práticas e teóricas) do aluno
 * Reutiliza a lógica de aluno/aulas.php para garantir consistência
 * 
 * @param object $db Instância do banco de dados
 * @param int $alunoId ID do aluno
 * @param int $dias Número de dias para buscar (padrão: 14)
 * @return array Array de aulas ordenadas por data/hora
 */
function obterProximasAulasAluno($db, $alunoId, $dias = 14) {
    if (!$alunoId) {
        return [];
    }
    
    $dataInicio = date('Y-m-d');
    $dataFim = date('Y-m-d', strtotime("+{$dias} days"));
    $hoje = date('Y-m-d');
    $agora = date('H:i:s');
    
    $todasAulas = [];
    
    // REFERENCIA PROXIMAS AULAS DASHBOARD - Buscar aulas práticas
    $aulasPraticas = $db->fetchAll("
        SELECT a.*, 
               i.nome as instrutor_nome,
               v.modelo as veiculo_modelo, 
               v.placa as veiculo_placa,
               'pratica' as tipo,
               'Prática' as tipo_label
        FROM aulas a
        JOIN instrutores i ON a.instrutor_id = i.id
        LEFT JOIN veiculos v ON a.veiculo_id = v.id
        WHERE a.aluno_id = ?
          AND a.data_aula >= ?
          AND a.data_aula <= ?
          AND a.status != 'cancelada'
        ORDER BY a.data_aula ASC, a.hora_inicio ASC
    ", [$alunoId, $dataInicio, $dataFim]);
    
    foreach ($aulasPraticas as $aula) {
        $todasAulas[] = $aula;
    }
    
    // REFERENCIA PROXIMAS AULAS DASHBOARD - Buscar aulas teóricas das turmas do aluno
    $turmasAluno = $db->fetchAll("
        SELECT tm.turma_id
        FROM turma_matriculas tm
        WHERE tm.aluno_id = ?
          AND tm.status IN ('matriculado', 'cursando', 'concluido')
    ", [$alunoId]);
    
    if (!empty($turmasAluno)) {
        $turmaIds = array_column($turmasAluno, 'turma_id');
        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
        
        $aulasTeoricas = $db->fetchAll("
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
                s.nome as sala_nome,
                'teorica' as tipo,
                'Teórica' as tipo_label
            FROM turma_aulas_agendadas taa
            JOIN turmas_teoricas tt ON taa.turma_id = tt.id
            LEFT JOIN instrutores i ON taa.instrutor_id = i.id
            LEFT JOIN salas s ON taa.sala_id = s.id
            WHERE taa.turma_id IN ($placeholders)
              AND taa.data_aula >= ?
              AND taa.data_aula <= ?
              AND taa.status IN ('agendada', 'realizada')
            ORDER BY taa.data_aula ASC, taa.hora_inicio ASC
        ", array_merge($turmaIds, [$dataInicio, $dataFim]));
        
        foreach ($aulasTeoricas as $aula) {
            $todasAulas[] = $aula;
        }
    }
    
    // Ordenar por data e hora (mais próxima primeiro)
    usort($todasAulas, function($a, $b) {
        $dataA = $a['data_aula'] . ' ' . ($a['hora_inicio'] ?? '00:00:00');
        $dataB = $b['data_aula'] . ' ' . ($b['hora_inicio'] ?? '00:00:00');
        return strtotime($dataA) - strtotime($dataB);
    });
    
    // Filtrar apenas aulas futuras (hoje com hora >= agora, ou datas futuras)
    $aulasFuturas = [];
    foreach ($todasAulas as $aula) {
        $dataAula = $aula['data_aula'];
        $horaAula = $aula['hora_inicio'] ?? '00:00:00';
        
        if ($dataAula > $hoje || ($dataAula === $hoje && $horaAula >= $agora)) {
            $aulasFuturas[] = $aula;
        }
    }
    
    return $aulasFuturas;
}

// DASHBOARD ALUNO - BLOCO PROXIMAS AULAS - INÍCIO
// AUDITORIA DASHBOARD PROXIMAS AULAS - Buscar próximas aulas (práticas e teóricas) usando a função unificada
$proximasAulasBrutas = obterProximasAulasAluno($db, $alunoId, 14);

// DASHBOARD ALUNO - PROXIMAS AULAS - EXIBINDO APENAS PRIMEIRO DIA COM AULAS
// Filtrar para exibir apenas as aulas do primeiro dia com aula agendada
$primeiraDataComAula = null;
$proximasAulas = [];
$aulasFuturasCount = 0;

foreach ($proximasAulasBrutas as $aula) {
    $dataAula = $aula['data_aula']; // Já vem no formato Y-m-d
    
    if ($primeiraDataComAula === null) {
        $primeiraDataComAula = $dataAula;
    }
    
    if ($dataAula === $primeiraDataComAula) {
        $proximasAulas[] = $aula;
    } else {
        $aulasFuturasCount++;
    }
}
// DASHBOARD ALUNO - BLOCO PROXIMAS AULAS - FIM

// Buscar notificações não lidas
$notificacoesNaoLidas = $notificacoes->buscarNotificacoesNaoLidas($user['id'], 'aluno');

// Buscar status dos exames - apenas se aluno existe na tabela alunos
$exames = [];
if ($alunoId) {
    $exames = $db->fetchAll("
        SELECT tipo, status, data_agendada as data_exame
        FROM exames 
        WHERE aluno_id = ? 
        ORDER BY data_agendada DESC
    ", [$alunoId]);
}

// Verificar guardas de negócio
$guardaExames = true;
$guardaFinanceiro = true;

foreach ($exames as $exame) {
    if (in_array($exame['tipo'], ['medico', 'psicologico']) && $exame['status'] !== 'aprovado') {
        $guardaExames = false;
        break;
    }
}

// Configurar variáveis para o layout
$pageTitle = 'Dashboard - ' . htmlspecialchars($aluno['nome']);
$homeUrl = '/aluno/dashboard.php';
$showFirstAccessBanner = !empty($_SESSION['first_access']);
$installUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/install';

// Incluir layout mobile-first
ob_start();
?>
<?php if ($showFirstAccessBanner): ?>
<!-- Banner primeiro acesso: consumido ao clicar Continuar ou ao navegar -->
<div class="card border-success mb-3" id="first-access-banner" style="border-left-width: 4px !important;">
    <div class="card-body">
        <p class="mb-2 fw-bold text-success"><span aria-hidden="true">✅</span> Acesso criado com sucesso!</p>
        <p class="mb-3 text-muted small">Você já pode usar o portal. Instale o app no celular para acessar de qualquer lugar.</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars($installUrl); ?>" class="btn btn-primary btn-sm">Instalar app</a>
            <a href="dashboard.php?dismiss_first_access=1" class="btn btn-outline-secondary btn-sm">Continuar</a>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- Card de Boas-Vindas (dentro do conteúdo, não como header) -->
<div class="card card-aluno-dashboard mb-3">
    <div class="card-body aluno-dashboard-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-1">
                    <i class="fas fa-user-graduate me-2 text-primary"></i>
                    Olá, <?php echo htmlspecialchars($aluno['nome']); ?>!
                </h3>
                <p class="subtitle mb-0 text-muted">Acompanhe suas aulas e progresso</p>
            </div>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>
                Sair
            </a>
        </div>
    </div>
</div>
<!-- Notificações -->
<?php if (!empty($notificacoesNaoLidas)): ?>
<div class="card-aluno-dashboard mb-section">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-bell me-2"></i>
            Notificações
        </h5>
        <span class="badge bg-primary"><?php echo count($notificacoesNaoLidas); ?></span>
    </div>
    <div class="card-body p-0">
        <?php foreach ($notificacoesNaoLidas as $notificacao): ?>
        <div class="border-bottom p-3" data-id="<?php echo $notificacao['id']; ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <h6 class="mb-1"><?php echo htmlspecialchars($notificacao['titulo']); ?></h6>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($notificacao['mensagem']); ?></p>
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
<?php endif; ?>

        <!-- Seu Progresso - Cards -->
        <div class="card-aluno-dashboard mb-section">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-route me-2"></i>
                    Seu Progresso
                </h5>
            </div>
            <div class="card-body">
                <!-- LAYOUT DESKTOP DASHBOARD - Seu Progresso: 3 colunas no desktop (col-lg-4), 2 no tablet (col-md-6), 1 no mobile (col-12) -->
                <div class="row g-3">
                    <!-- Exames Médico e Psicológico -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="progresso-card d-flex align-items-center <?php echo $guardaExames ? 'completed' : 'pending'; ?>">
                            <div class="progresso-card-icon">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                            <div class="progresso-card-content flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="progresso-card-title">Exames Médico e Psicológico</div>
                                    </div>
                                    <span class="progresso-card-badge <?php echo $guardaExames ? 'success' : 'secondary'; ?>">
                                        <?php echo $guardaExames ? 'Aprovados' : 'Pendentes'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aulas Teóricas -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="progresso-card d-flex align-items-center <?php echo $guardaExames ? 'completed' : 'disabled'; ?>">
                            <div class="progresso-card-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="progresso-card-content flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="progresso-card-title">Aulas Teóricas</div>
                                    </div>
                                    <span class="progresso-card-badge <?php echo $guardaExames ? 'primary' : 'secondary'; ?>">
                                        <?php echo $guardaExames ? 'Liberadas' : 'Bloqueadas'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aulas Práticas -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="progresso-card d-flex align-items-center disabled">
                            <div class="progresso-card-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="progresso-card-content flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="progresso-card-title">Aulas Práticas</div>
                                    </div>
                                    <span class="progresso-card-badge secondary">
                                        Após prova teórica
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Próximas Aulas -->
        <div class="card-aluno-dashboard mb-section">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Próximas Aulas
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($proximasAulas)): ?>
                <div class="proximas-aulas-empty">
                    <div class="proximas-aulas-empty-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h6 class="proximas-aulas-empty-title">Nenhuma aula agendada</h6>
                    <p class="proximas-aulas-empty-text">Você não possui aulas agendadas para os próximos 14 dias.</p>
                </div>
                <?php else: ?>
            <!-- LAYOUT DESKTOP DASHBOARD - Próximas Aulas: 2 colunas no desktop (col-md-6), 1 no mobile (col-12) -->
            <div class="aula-list row g-3">
                <?php foreach ($proximasAulas as $aula): ?>
                <div class="col-12 col-md-6">
                    <div class="aula-item" data-aula-id="<?php echo $aula['id']; ?>">
                    <div class="aula-item-header">
                        <div>
                            <div class="aula-tipo <?php echo $aula['tipo'] ?? 'pratica'; ?>">
                                <?php echo htmlspecialchars($aula['tipo_label'] ?? ($aula['tipo'] === 'teorica' ? 'Teórica' : 'Prática')); ?>
                            </div>
                            <div class="aula-data">
                                <?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?>
                            </div>
                            <div class="aula-hora">
                                <?php 
                                $horaInicio = $aula['hora_inicio'] ?? '00:00:00';
                                $horaFim = $aula['hora_fim'] ?? null;
                                echo date('H:i', strtotime($horaInicio));
                                if ($horaFim) {
                                    echo ' - ' . date('H:i', strtotime($horaFim));
                                }
                                ?>
                            </div>
                            <?php if (isset($aula['turma_nome']) || isset($aula['disciplina'])): ?>
                            <div class="aula-turma" style="font-size: 0.875rem; color: #64748b; margin-top: 0.25rem;">
                                <?php if (isset($aula['turma_nome'])): ?>
                                    <?php echo htmlspecialchars($aula['turma_nome']); ?>
                                <?php endif; ?>
                                <?php if (isset($aula['disciplina'])): ?>
                                    <?php if (isset($aula['turma_nome'])): ?> - <?php endif; ?>
                                    <?php echo htmlspecialchars($aula['disciplina']); ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="aula-status <?php echo $aula['status'] ?? 'agendada'; ?>">
                            <?php 
                            $status = $aula['status'] ?? 'agendada';
                            $statusLabel = $status === 'realizada' ? 'Realizada' : ucfirst($status);
                            echo htmlspecialchars($statusLabel);
                            ?>
                        </div>
                    </div>
                    
                    <div class="aula-detalhes">
                        <?php if (!empty($aula['instrutor_nome'])): ?>
                        <div class="aula-detalhe">
                            <i class="fas fa-user aula-detalhe-icon"></i>
                            <?php echo htmlspecialchars($aula['instrutor_nome']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($aula['sala_nome'])): ?>
                        <div class="aula-detalhe">
                            <i class="fas fa-door-open aula-detalhe-icon"></i>
                            Sala: <?php echo htmlspecialchars($aula['sala_nome']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($aula['veiculo_modelo'])): ?>
                        <div class="aula-detalhe">
                            <i class="fas fa-car aula-detalhe-icon"></i>
                            <?php echo htmlspecialchars($aula['veiculo_modelo']); ?> - <?php echo htmlspecialchars($aula['veiculo_placa'] ?? ''); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($aula['observacoes'])): ?>
                        <div class="aula-detalhe">
                            <i class="fas fa-sticky-note aula-detalhe-icon"></i>
                            <?php echo htmlspecialchars($aula['observacoes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (($aula['tipo'] ?? 'pratica') === 'pratica'): ?>
                    <div class="aula-actions">
                        <button class="btn btn-sm btn-outline solicitar-reagendamento" 
                                data-aula-id="<?php echo $aula['id']; ?>"
                                data-data="<?php echo $aula['data_aula']; ?>"
                                data-hora="<?php echo $aula['hora_inicio']; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            Reagendar
                        </button>
                        <button class="btn btn-sm btn-danger solicitar-cancelamento" 
                                data-aula-id="<?php echo $aula['id']; ?>"
                                data-data="<?php echo $aula['data_aula']; ?>"
                                data-hora="<?php echo $aula['hora_inicio']; ?>">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                    </div>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($aulasFuturasCount > 0): ?>
            <div class="mt-3 pt-3 border-top text-center">
                <p class="mb-2 text-muted" style="font-size: 0.875rem;">
                    Você tem mais <strong><?php echo $aulasFuturasCount; ?></strong> aula(s) agendada(s) em datas futuras.
                </p>
                <a href="aulas.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-calendar-alt me-1"></i>
                    Ver todas as aulas
                </a>
            </div>
            <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="card-aluno-dashboard mb-section">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Ações Rápidas
                </h5>
            </div>
            <div class="card-body">
                <div class="acoes-rapidas-wrapper">
                    <button class="btn btn-outline-primary" onclick="verTodasAulas()">
                        <i class="fas fa-list me-2"></i>
                        Ver Todas as Aulas
                    </button>
                    <button class="btn btn-outline-primary" onclick="verNotificacoes()">
                        <i class="fas fa-bell me-2"></i>
                        Central de Avisos
                    </button>
                    <!-- FASE 1 - PRESENCA TEORICA - Link para presenças teóricas com destaque -->
                    <a href="presencas-teoricas.php" class="btn btn-presencas-teoricas">
                        <i class="fas fa-clipboard-check me-2"></i>
                        Minhas Presenças Teóricas
                    </a>
                    <button class="btn btn-outline-primary" onclick="verFinanceiro()">
                        <i class="fas fa-credit-card me-2"></i>
                        Financeiro
                    </button>
                    <button class="btn btn-outline-primary" onclick="contatarCFC()">
                        <i class="fas fa-phone me-2"></i>
                        Contatar CFC
                    </button>
                </div>
            </div>
        </div>

<!-- Modal de Solicitação -->
    <div id="modalSolicitacao" class="modal-overlay hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitulo">Solicitar Reagendamento</h3>
            </div>
            <div class="modal-body">
                <form id="formSolicitacao">
                    <input type="hidden" id="aulaId" name="aula_id">
                    <input type="hidden" id="tipoSolicitacao" name="tipo_solicitacao">
                    
                    <div class="form-group">
                        <label class="form-label">Data Atual</label>
                        <input type="text" id="dataAtual" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Horário Atual</label>
                        <input type="text" id="horaAtual" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group" id="novaDataGroup" style="display: none;">
                        <label class="form-label">Nova Data</label>
                        <input type="date" id="novaData" name="nova_data" class="form-input">
                    </div>
                    
                    <div class="form-group" id="novaHoraGroup" style="display: none;">
                        <label class="form-label">Novo Horário</label>
                        <input type="time" id="novaHora" name="nova_hora" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Motivo</label>
                        <select id="motivo" name="motivo" class="form-select">
                            <option value="">Selecione um motivo</option>
                            <option value="imprevisto_pessoal">Imprevisto pessoal</option>
                            <option value="problema_saude">Problema de saúde</option>
                            <option value="compromisso_trabalho">Compromisso de trabalho</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Justificativa *</label>
                        <textarea id="justificativa" name="justificativa" class="form-textarea" 
                                  placeholder="Descreva o motivo da solicitação..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Política:</strong> Solicitações devem ser feitas com no mínimo 24 horas de antecedência.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="enviarSolicitacao()">Enviar Solicitação</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script>
        // Variáveis globais
        let modalAberto = false;

        // FASE 1 - AREA ALUNO PENDENCIAS - Função de navegação para aulas
        function verTodasAulas() {
            window.location.href = 'aulas.php';
        }

        // FASE 2 - NOTIFICACOES ALUNO - Redirecionar para página de notificações
        function verNotificacoes() {
            window.location.href = 'notificacoes.php';
        }

        // FASE 3 - FINANCEIRO ALUNO - Redirecionar para página de financeiro
        function verFinanceiro() {
            window.location.href = 'financeiro.php';
        }

        // FASE 4 - CONTATO ALUNO - Redirecionar para página de contato
        function contatarCFC() {
            window.location.href = 'contato.php';
        }

        // Funções do modal
        function abrirModal(tipo, aulaId, data, hora) {
            document.getElementById('tipoSolicitacao').value = tipo;
            document.getElementById('aulaId').value = aulaId;
            document.getElementById('dataAtual').value = data;
            document.getElementById('horaAtual').value = hora;
            
            const modal = document.getElementById('modalSolicitacao');
            const titulo = document.getElementById('modalTitulo');
            const novaDataGroup = document.getElementById('novaDataGroup');
            const novaHoraGroup = document.getElementById('novaHoraGroup');
            
            if (tipo === 'reagendamento') {
                titulo.textContent = 'Solicitar Reagendamento';
                novaDataGroup.style.display = 'block';
                novaHoraGroup.style.display = 'block';
            } else {
                titulo.textContent = 'Solicitar Cancelamento';
                novaDataGroup.style.display = 'none';
                novaHoraGroup.style.display = 'none';
            }
            
            modal.classList.remove('hidden');
            modalAberto = true;
        }

        function fecharModal() {
            document.getElementById('modalSolicitacao').classList.add('hidden');
            document.getElementById('formSolicitacao').reset();
            modalAberto = false;
        }

        // Event listeners para botões de ação
        document.addEventListener('DOMContentLoaded', function() {
            // Botões de reagendamento
            document.querySelectorAll('.solicitar-reagendamento').forEach(btn => {
                btn.addEventListener('click', function() {
                    const aulaId = this.dataset.aulaId;
                    const data = this.dataset.data;
                    const hora = this.dataset.hora;
                    abrirModal('reagendamento', aulaId, data, hora);
                });
            });

            // Botões de cancelamento
            document.querySelectorAll('.solicitar-cancelamento').forEach(btn => {
                btn.addEventListener('click', function() {
                    const aulaId = this.dataset.aulaId;
                    const data = this.dataset.data;
                    const hora = this.dataset.hora;
                    abrirModal('cancelamento', aulaId, data, hora);
                });
            });

            // Botões de marcar notificação como lida
            document.querySelectorAll('.marcar-lida').forEach(btn => {
                btn.addEventListener('click', function() {
                    const notificacaoId = this.dataset.id;
                    marcarNotificacaoComoLida(notificacaoId);
                });
            });

            // Fechar modal ao clicar fora
            document.getElementById('modalSolicitacao').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModal();
                }
            });
        });

        // Função para enviar solicitação
        async function enviarSolicitacao() {
            const form = document.getElementById('formSolicitacao');
            const formData = new FormData(form);
            
            // Validação básica
            if (!formData.get('justificativa').trim()) {
                mostrarToast('Por favor, preencha a justificativa.', 'error');
                return;
            }

            try {
                const response = await fetch('../admin/api/solicitacoes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        aula_id: formData.get('aula_id'),
                        tipo_solicitacao: formData.get('tipo_solicitacao'),
                        nova_data: formData.get('nova_data'),
                        nova_hora: formData.get('nova_hora'),
                        motivo: formData.get('motivo'),
                        justificativa: formData.get('justificativa')
                    })
                });

                const result = await response.json();

                if (result.success) {
                    mostrarToast('Solicitação enviada com sucesso!', 'success');
                    fecharModal();
                    // Recarregar a página após um breve delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    mostrarToast(result.message || 'Erro ao enviar solicitação.', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarToast('Erro de conexão. Tente novamente.', 'error');
            }
        }

        // Função para marcar notificação como lida
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
                    // Remover a notificação da interface
                    const notificacaoItem = document.querySelector(`[data-id="${notificacaoId}"]`);
                    if (notificacaoItem) {
                        notificacaoItem.remove();
                    }
                    
                    // Atualizar contador de notificações
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

        // Função para mostrar toast
        function mostrarToast(mensagem, tipo = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${tipo}`;
            
            toast.innerHTML = `
                <div class="toast-header">
                    <i class="fas fa-${tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span class="toast-title">${tipo === 'success' ? 'Sucesso' : tipo === 'error' ? 'Erro' : 'Informação'}</span>
                </div>
                <div class="toast-message">${mensagem}</div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Remover toast após 5 segundos
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // Prevenir envio do formulário com Enter
        document.getElementById('formSolicitacao').addEventListener('submit', function(e) {
            e.preventDefault();
        });
    </script>

    <!-- CSS específico do dashboard do aluno -->
    <link rel="stylesheet" href="../assets/css/aluno-dashboard.css">
    
    <style>
        /* Estilos específicos para notificações e aulas (mantidos do original) */
        .notificacao-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 8px;
            background: #f8fafc;
        }

        .notificacao-content {
            flex: 1;
        }

        .notificacao-content h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .notificacao-content p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .notificacao-content small {
            font-size: 11px;
            color: #94a3b8;
        }

        .aula-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .aula-actions .btn {
            flex: 1;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .alert i {
            font-size: 16px;
        }

        @media (max-width: 480px) {
            .aula-actions {
                flex-direction: column;
            }
            
            .aula-actions .btn {
                width: 100%;
            }
        }
    </style>

<?php
$pageContent = ob_get_clean();

// Overlay PWA "Instalar app" (dashboard aluno): ativa overlay + CTA no header
$showPwaInstallOverlay = true;
$pwaInstallUrl = $installUrl;

// Incluir layout mobile-first
include __DIR__ . '/../includes/layout/mobile-first.php';
?>