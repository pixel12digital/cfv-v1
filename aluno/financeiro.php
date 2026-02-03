<?php
/**
 * Financeiro do Aluno
 * 
 * FASE 3 - FINANCEIRO ALUNO - Implementação: 2025
 * Arquivo: aluno/financeiro.php
 * 
 * Funcionalidades:
 * - Visualização de faturas do aluno
 * - Filtros por período e status
 * - Resumo financeiro (em aberto, em atraso, pagas)
 * - Links para pagamento/boleto (se disponível)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// FASE 3 - FINANCEIRO ALUNO - Verificação de autenticação
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'aluno') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();

// FASE 3 - FINANCEIRO ALUNO - Obter aluno_id usando getCurrentAlunoId()
$alunoId = getCurrentAlunoId($user['id']);

if (!$alunoId) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/aluno/dashboard.php?erro=aluno_nao_encontrado');
    exit();
}

// FASE 3 - FINANCEIRO ALUNO - Buscar dados do aluno
$aluno = $db->fetch("SELECT * FROM alunos WHERE id = ?", [$alunoId]);
if (!$aluno) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/aluno/dashboard.php?erro=aluno_nao_encontrado');
    exit();
}

$aluno['nome'] = $aluno['nome'] ?? 'Aluno';

// FASE 3 - FINANCEIRO ALUNO - Processar filtros
$periodoFiltro = $_GET['periodo'] ?? 'ultimos_proximos_90';
$statusFiltro = $_GET['status'] ?? '';

// Calcular datas baseado no período
$dataInicio = null;
$dataFim = null;
$hoje = date('Y-m-d');

switch ($periodoFiltro) {
    case 'vencidas':
        $dataFim = $hoje;
        break;
    case 'ultimos_30':
        $dataInicio = date('Y-m-d', strtotime('-30 days'));
        $dataFim = date('Y-m-d', strtotime('+30 days'));
        break;
    case 'proximos_30':
        $dataInicio = $hoje;
        $dataFim = date('Y-m-d', strtotime('+30 days'));
        break;
    case 'ultimos_proximos_90':
    default:
        $dataInicio = date('Y-m-d', strtotime('-90 days'));
        $dataFim = date('Y-m-d', strtotime('+90 days'));
        break;
}

// FASE 3 - FINANCEIRO ALUNO - Buscar faturas via API (ou query direta)
// Usando query direta para evitar dependência de API externa
$where = ['f.aluno_id = ?'];
$params = [$alunoId];

if ($statusFiltro) {
    $where[] = 'f.status = ?';
    $params[] = $statusFiltro;
}

if ($dataInicio) {
    $where[] = 'f.data_vencimento >= ?';
    $params[] = $dataInicio;
}

if ($dataFim) {
    $where[] = 'f.data_vencimento <= ?';
    $params[] = $dataFim;
}

// Se filtro for "vencidas", adicionar condição de status
if ($periodoFiltro === 'vencidas') {
    $where[] = "f.status IN ('aberta', 'vencida')";
}

$whereClause = implode(' AND ', $where);

// Buscar faturas
$faturas = $db->fetchAll("
    SELECT f.*
    FROM financeiro_faturas f
    WHERE $whereClause
    ORDER BY f.data_vencimento DESC, f.criado_em DESC
", $params);

// FASE 3 - FINANCEIRO ALUNO - Calcular estatísticas
$stats = [
    'em_aberto' => ['qtd' => 0, 'valor' => 0],
    'em_atraso' => ['qtd' => 0, 'valor' => 0],
    'pagas' => ['qtd' => 0, 'valor' => 0],
    'canceladas' => ['qtd' => 0, 'valor' => 0]
];

// Buscar todas as faturas do aluno para calcular estatísticas completas (sem filtro de período)
$todasFaturas = $db->fetchAll("
    SELECT f.status, f.valor_total, f.data_vencimento
    FROM financeiro_faturas f
    WHERE f.aluno_id = ?
", [$alunoId]);

foreach ($todasFaturas as $f) {
    $status = $f['status'] ?? 'aberta';
    $valor = (float)($f['valor_total'] ?? 0);
    $vencimento = $f['data_vencimento'] ?? null;
    
    // Verificar se está em atraso (aberta e vencida)
    $estaVencida = false;
    if ($status === 'aberta' && $vencimento && strtotime($vencimento) < strtotime($hoje)) {
        $estaVencida = true;
    }
    
    if ($status === 'vencida' || $estaVencida) {
        $stats['em_atraso']['qtd']++;
        $stats['em_atraso']['valor'] += $valor;
    } elseif ($status === 'paga' || $status === 'parcial') {
        $stats['pagas']['qtd']++;
        $stats['pagas']['valor'] += $valor;
    } elseif ($status === 'cancelada') {
        $stats['canceladas']['qtd']++;
        $stats['canceladas']['valor'] += $valor;
    } elseif ($status === 'aberta' && !$estaVencida) {
        $stats['em_aberto']['qtd']++;
        $stats['em_aberto']['valor'] += $valor;
    }
}

// Função auxiliar para formatar status
function formatarStatusFatura($status, $dataVencimento = null) {
    $hoje = date('Y-m-d');
    
    if ($status === 'vencida' || ($status === 'aberta' && $dataVencimento && strtotime($dataVencimento) < strtotime($hoje))) {
        return ['label' => 'EM ATRASO', 'class' => 'danger'];
    }
    
    $map = [
        'aberta' => ['label' => 'EM ABERTO', 'class' => 'primary'],
        'paga' => ['label' => 'PAGA', 'class' => 'success'],
        'parcial' => ['label' => 'PARCIAL', 'class' => 'warning'],
        'cancelada' => ['label' => 'CANCELADA', 'class' => 'secondary']
    ];
    
    return $map[$status] ?? ['label' => strtoupper($status), 'class' => 'secondary'];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10b981" id="theme-color-meta">
    <title>Financeiro - <?php echo htmlspecialchars($aluno['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <link rel="stylesheet" href="../assets/css/aluno-dashboard.css">
    <style>
        /* Removido header-financeiro - agora é card dentro do conteúdo */
        .stat-card {
            background: white;
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .fatura-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }
        .fatura-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .fatura-card.vencida {
            border-left: 4px solid #dc3545;
        }
        .fatura-card.aberta {
            border-left: 4px solid #0d6efd;
        }
        .fatura-card.paga {
            border-left: 4px solid #198754;
        }
        @media (max-width: 576px) {
            .fatura-card {
                padding: 0.75rem;
            }
        }
    </style>
    <script>
        (function(){var m=document.getElementById('theme-color-meta');if(!m)return;function u(){var d=window.matchMedia('(prefers-color-scheme: dark)').matches;m.setAttribute('content',d?'#1e293b':'#10b981');}u();window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',u);})();
    </script>
</head>
<body>
    <div class="container" style="max-width: 1000px; margin: 0 auto; padding: 20px 16px;">
        <!-- Card de Título (não é header, é card dentro do conteúdo) -->
        <div class="card card-aluno-dashboard mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h1 class="h4 mb-1">
                            <i class="fas fa-credit-card me-2 text-primary"></i>
                            Financeiro
                        </h1>
                        <p class="text-muted mb-0 small">Acompanhe suas cobranças e pagamentos.</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-6">
                        <label for="periodo" class="form-label">Período</label>
                        <select name="periodo" id="periodo" class="form-select">
                            <option value="ultimos_proximos_90" <?php echo $periodoFiltro === 'ultimos_proximos_90' ? 'selected' : ''; ?>>Todas (últimos + próximos 90 dias)</option>
                            <option value="vencidas" <?php echo $periodoFiltro === 'vencidas' ? 'selected' : ''; ?>>Somente vencidas</option>
                            <option value="ultimos_30" <?php echo $periodoFiltro === 'ultimos_30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                            <option value="proximos_30" <?php echo $periodoFiltro === 'proximos_30' ? 'selected' : ''; ?>>Próximos 30 dias</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="" <?php echo $statusFiltro === '' ? 'selected' : ''; ?>>Todos</option>
                            <option value="aberta" <?php echo $statusFiltro === 'aberta' ? 'selected' : ''; ?>>Em aberto</option>
                            <option value="vencida" <?php echo $statusFiltro === 'vencida' ? 'selected' : ''; ?>>Em atraso</option>
                            <option value="paga" <?php echo $statusFiltro === 'paga' ? 'selected' : ''; ?>>Paga</option>
                            <option value="cancelada" <?php echo $statusFiltro === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        <a href="financeiro.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resumo -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-value text-primary"><?php echo $stats['em_aberto']['qtd']; ?></div>
                    <div class="stat-label">Em Aberto</div>
                    <div class="text-muted small mt-1">R$ <?php echo number_format($stats['em_aberto']['valor'], 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-value text-danger"><?php echo $stats['em_atraso']['qtd']; ?></div>
                    <div class="stat-label">Em Atraso</div>
                    <div class="text-muted small mt-1">R$ <?php echo number_format($stats['em_atraso']['valor'], 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-value text-success"><?php echo $stats['pagas']['qtd']; ?></div>
                    <div class="stat-label">Pagas</div>
                    <div class="text-muted small mt-1">R$ <?php echo number_format($stats['pagas']['valor'], 2, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-value text-secondary"><?php echo $stats['canceladas']['qtd']; ?></div>
                    <div class="stat-label">Canceladas</div>
                    <div class="text-muted small mt-1">R$ <?php echo number_format($stats['canceladas']['valor'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>

        <!-- Lista de Faturas -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Faturas</h5>
                
                <?php if (empty($faturas)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-invoice" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <h6 class="fw-semibold mb-2">Nenhuma fatura encontrada</h6>
                    <p class="text-muted mb-0">
                        Não há faturas para o período e status selecionados.
                    </p>
                </div>
                <?php else: ?>
                <div class="faturas-list">
                    <?php foreach ($faturas as $fatura): 
                        $statusInfo = formatarStatusFatura($fatura['status'], $fatura['data_vencimento']);
                        $cardClass = '';
                        if ($statusInfo['class'] === 'danger') {
                            $cardClass = 'vencida';
                        } elseif ($fatura['status'] === 'aberta') {
                            $cardClass = 'aberta';
                        } elseif ($fatura['status'] === 'paga') {
                            $cardClass = 'paga';
                        }
                    ?>
                    <div class="fatura-card <?php echo $cardClass; ?>">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-6 mb-2 mb-md-0">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-<?php echo $statusInfo['class']; ?>">
                                        <?php echo $statusInfo['label']; ?>
                                    </span>
                                    <small class="text-muted">#<?php echo $fatura['id']; ?></small>
                                </div>
                                <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($fatura['titulo'] ?? 'Fatura'); ?></h6>
                                <?php if (!empty($fatura['observacoes'])): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($fatura['observacoes']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-md-3 text-center text-md-start mb-2 mb-md-0">
                                <div class="small text-muted">Vencimento</div>
                                <div class="fw-semibold">
                                    <?php echo $fatura['data_vencimento'] ? date('d/m/Y', strtotime($fatura['data_vencimento'])) : 'N/A'; ?>
                                </div>
                            </div>
                            <div class="col-12 col-md-3 text-center text-md-end">
                                <div class="h5 mb-0 text-primary">
                                    R$ <?php echo number_format((float)($fatura['valor_total'] ?? 0), 2, ',', '.'); ?>
                                </div>
                                <?php if (!empty($fatura['link_pagamento']) || !empty($fatura['boleto_url'])): ?>
                                <a href="<?php echo htmlspecialchars($fatura['link_pagamento'] ?? $fatura['boleto_url']); ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-primary mt-2">
                                    <i class="fas fa-external-link-alt me-1"></i>Ver boleto / Pagar
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

