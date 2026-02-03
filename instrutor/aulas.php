<?php
/**
 * Página de Listagem de Aulas do Instrutor
 * 
 * FASE 1 - Implementação: 2024
 * Arquivo: instrutor/aulas.php
 * 
 * Funcionalidades:
 * - Listar todas as aulas do instrutor (passadas, de hoje, futuras)
 * - Filtros por período (data inicial/final) e status
 * - Ações: visualizar detalhes, cancelar, transferir
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar autenticação
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'instrutor') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();

// Verificar se precisa trocar senha
try {
    $checkColumn = $db->fetch("SHOW COLUMNS FROM usuarios LIKE 'precisa_trocar_senha'");
    if ($checkColumn) {
        $usuarioCompleto = $db->fetch("SELECT precisa_trocar_senha FROM usuarios WHERE id = ?", [$user['id']]);
        if ($usuarioCompleto && isset($usuarioCompleto['precisa_trocar_senha']) && $usuarioCompleto['precisa_trocar_senha'] == 1) {
            $currentPage = basename($_SERVER['PHP_SELF']);
            if ($currentPage !== 'trocar-senha.php') {
                $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                header('Location: ' . $basePath . '/instrutor/trocar-senha.php?forcado=1');
                exit();
            }
        }
    }
} catch (Exception $e) {
    // Continuar normalmente
}

// Buscar dados do instrutor
$instrutor = $db->fetch("
    SELECT i.*, u.nome as nome_usuario, u.email as email_usuario 
    FROM instrutores i 
    LEFT JOIN usuarios u ON i.usuario_id = u.id 
    WHERE i.usuario_id = ?
", [$user['id']]);

if (!$instrutor) {
    $instrutor = [
        'id' => null,
        'usuario_id' => $user['id'],
        'nome' => $user['nome'] ?? 'Instrutor',
        'nome_usuario' => $user['nome'] ?? 'Instrutor',
        'email_usuario' => $user['email'] ?? '',
        'credencial' => null,
        'cfc_id' => null
    ];
}

$instrutor['nome'] = $instrutor['nome'] ?? $instrutor['nome_usuario'] ?? $user['nome'] ?? 'Instrutor';
$instrutorId = $instrutor['id'] ?? null;

// FASE 1 - PRESENCA TEORICA - Processar filtros
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
$statusFiltro = $_GET['status'] ?? '';
$tipoFiltro = $_GET['tipo'] ?? ''; // FASE 1 - PRESENCA TEORICA - Filtro por tipo (pratica/teorica)

// Validar datas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    $dataInicio = date('Y-m-d', strtotime('-30 days'));
    $dataFim = date('Y-m-d', strtotime('+30 days'));
}

// FASE INSTRUTOR - AULAS TEORICAS - Inicializar variáveis
$aulas = [];
$aulasPraticas = [];
$aulasTeoricas = [];

// FASE INSTRUTOR - AULAS TEORICAS - Buscar aulas práticas
if ($instrutorId && ($tipoFiltro === '' || $tipoFiltro === 'pratica')) {
    $sql = "
        SELECT a.*, 
               a.aluno_id,
               al.nome as aluno_nome, al.telefone as aluno_telefone,
               v.modelo as veiculo_modelo, v.placa as veiculo_placa,
               'pratica' as tipo_aula
        FROM aulas a
        JOIN alunos al ON a.aluno_id = al.id
        LEFT JOIN veiculos v ON a.veiculo_id = v.id
        WHERE a.instrutor_id = ?
          AND a.data_aula >= ?
          AND a.data_aula <= ?
    ";
    
    $params = [$instrutorId, $dataInicio, $dataFim];
    
    if ($statusFiltro && in_array($statusFiltro, ['agendada', 'em_andamento', 'concluida', 'cancelada'])) {
        $sql .= " AND a.status = ?";
        $params[] = $statusFiltro;
    }
    
    $sql .= " ORDER BY a.data_aula DESC, a.hora_inicio DESC";
    
    $aulasPraticas = $db->fetchAll($sql, $params);
}

// FASE INSTRUTOR - AULAS TEORICAS - Buscar aulas teóricas (turma_aulas_agendadas)
// Nota: $aulasTeoricas já foi inicializada acima na linha 84
if ($instrutorId && ($tipoFiltro === '' || $tipoFiltro === 'teorica')) {
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
            s.nome as sala_nome,
            'teorica' as tipo_aula
        FROM turma_aulas_agendadas taa
        JOIN turmas_teoricas tt ON taa.turma_id = tt.id
        LEFT JOIN salas s ON taa.sala_id = s.id
        WHERE taa.instrutor_id = ?
          AND taa.data_aula >= ?
          AND taa.data_aula <= ?
    ";
    
    $paramsTeoricas = [$instrutorId, $dataInicio, $dataFim];
    
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
    
    $sqlTeoricas .= " ORDER BY taa.data_aula DESC, taa.hora_inicio DESC";
    
    $aulasTeoricas = $db->fetchAll($sqlTeoricas, $paramsTeoricas);
}

// FASE INSTRUTOR - AULAS TEORICAS - Combinar aulas práticas e teóricas
// Garantir que as variáveis estejam sempre definidas como arrays antes do merge
// (Proteção extra mesmo que já tenham sido inicializadas acima)
$aulasPraticas = isset($aulasPraticas) && is_array($aulasPraticas) ? $aulasPraticas : [];
$aulasTeoricas = isset($aulasTeoricas) && is_array($aulasTeoricas) ? $aulasTeoricas : [];
$aulas = array_merge($aulasPraticas, $aulasTeoricas);

// Ordenar por data e horário
usort($aulas, function($a, $b) {
    $dataA = $a['data_aula'] . ' ' . ($a['hora_inicio'] ?? '00:00:00');
    $dataB = $b['data_aula'] . ' ' . ($b['hora_inicio'] ?? '00:00:00');
    return strtotime($dataB) - strtotime($dataA); // DESC
});

// FASE INSTRUTOR - AULAS TEORICAS - Estatísticas (práticas + teóricas)
$stats = [
    'total' => count($aulas),
    'agendadas' => 0,
    'concluidas' => 0,
    'canceladas' => 0,
    'em_andamento' => 0,
    'praticas' => count($aulasPraticas),
    'teoricas' => count($aulasTeoricas)
];

foreach ($aulas as $aula) {
    $status = $aula['status'];
    // Adaptar status de teóricas (realizada = concluida)
    if ($aula['tipo_aula'] === 'teorica' && $status === 'realizada') {
        $status = 'concluida';
    }
    
    if (isset($stats[$status])) {
        $stats[$status]++;
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
    <title>Todas as Aulas - <?php echo htmlspecialchars($instrutor['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/mobile-first.css">
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        (function(){var m=document.getElementById('theme-color-meta');if(!m)return;function u(){var d=window.matchMedia('(prefers-color-scheme: dark)').matches;m.setAttribute('content',d?'#1e293b':'#10b981');}u();window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',u);})();
    </script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Todas as Aulas</h1>
                <div class="subtitle">Gerencie todas as suas aulas</div>
            </div>
            <a href="dashboard.php" style="color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 8px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 20px 16px;">
        <!-- Filtros -->
        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px;">
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end;">
                <div>
                    <label for="data_inicio" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Data Inicial</label>
                    <input 
                        type="date" 
                        id="data_inicio" 
                        name="data_inicio" 
                        value="<?php echo htmlspecialchars($dataInicio); ?>"
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                    >
                </div>
                <div>
                    <label for="data_fim" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Data Final</label>
                    <input 
                        type="date" 
                        id="data_fim" 
                        name="data_fim" 
                        value="<?php echo htmlspecialchars($dataFim); ?>"
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                    >
                </div>
                <!-- FASE 1 - PRESENCA TEORICA - Filtro por tipo -->
                <div>
                    <label for="tipo" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Tipo</label>
                    <select 
                        id="tipo" 
                        name="tipo" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                    >
                        <option value="">Todas</option>
                        <option value="pratica" <?php echo $tipoFiltro === 'pratica' ? 'selected' : ''; ?>>Aulas Práticas</option>
                        <option value="teorica" <?php echo $tipoFiltro === 'teorica' ? 'selected' : ''; ?>>Aulas Teóricas</option>
                    </select>
                </div>
                <div>
                    <label for="status" style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Status</label>
                    <select 
                        id="status" 
                        name="status" 
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                    >
                        <option value="">Todos</option>
                        <option value="agendada" <?php echo $statusFiltro === 'agendada' ? 'selected' : ''; ?>>Agendada</option>
                        <option value="em_andamento" <?php echo $statusFiltro === 'em_andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="realizada" <?php echo $statusFiltro === 'realizada' ? 'selected' : ''; ?>>Realizada</option>
                        <option value="concluida" <?php echo $statusFiltro === 'concluida' ? 'selected' : ''; ?>>Concluída</option>
                        <option value="cancelada" <?php echo $statusFiltro === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                <div>
                    <button 
                        type="submit" 
                        style="width: 100%; padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;"
                    >
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Estatísticas -->
        <div class="grid grid-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
            <div class="stat-item" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #2563eb; margin-bottom: 4px;"><?php echo $stats['total']; ?></div>
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase;">Total</div>
            </div>
            <div class="stat-item" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #10b981; margin-bottom: 4px;"><?php echo $stats['agendadas']; ?></div>
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase;">Agendadas</div>
            </div>
            <div class="stat-item" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #059669; margin-bottom: 4px;"><?php echo $stats['concluidas']; ?></div>
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase;">Concluídas</div>
            </div>
            <div class="stat-item" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #ef4444; margin-bottom: 4px;"><?php echo $stats['canceladas']; ?></div>
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase;">Canceladas</div>
            </div>
        </div>

        <!-- FASE INSTRUTOR - AULAS TEORICAS - Lista de Aulas Práticas -->
        <?php if ($tipoFiltro === '' || $tipoFiltro === 'pratica'): ?>
        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px;">
            <div style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #2563eb;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">
                    <i class="fas fa-car me-2" style="color: #10b981;"></i>
                    Aulas Práticas
                </h3>
            </div>
            <?php 
            // FASE INSTRUTOR - AULAS TEORICAS - Filtrar apenas práticas para esta seção
            $aulasPraticasFiltradas = array_filter($aulas, function($a) { return ($a['tipo_aula'] ?? 'pratica') === 'pratica'; });
            ?>
            <?php if (empty($aulasPraticasFiltradas)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-calendar-times" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
                <h3 style="color: #64748b; margin-bottom: 8px;">Nenhuma aula prática encontrada</h3>
                <p style="color: #94a3b8;">Não há aulas práticas no período selecionado.</p>
            </div>
            <?php else: ?>
            <div class="aula-list" style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($aulasPraticasFiltradas as $aula): ?>
                <div class="aula-item" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #f8fafc;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <span style="padding: 4px 8px; background: <?php echo $aula['tipo_aula'] === 'teorica' ? '#3b82f6' : '#10b981'; ?>; color: white; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                    <?php echo ucfirst($aula['tipo_aula']); ?>
                                </span>
                                <span style="padding: 4px 8px; background: <?php 
                                    echo $aula['status'] === 'agendada' ? '#fbbf24' : 
                                        ($aula['status'] === 'concluida' ? '#10b981' : 
                                        ($aula['status'] === 'cancelada' ? '#ef4444' : '#3b82f6')); 
                                ?>; color: white; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                    <?php echo ucfirst($aula['status']); ?>
                                </span>
                            </div>
                            <div style="font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">
                                <a href="#" onclick="abrirModalAluno(<?= $aula['aluno_id'] ?? 0 ?>); return false;" 
                                   class="text-primary text-decoration-none" 
                                   title="Ver detalhes do aluno">
                                    <?php echo htmlspecialchars($aula['aluno_nome'] ?? 'Aluno não informado'); ?>
                                </a>
                                <button class="btn btn-sm btn-outline-primary ml-2" 
                                        onclick="abrirModalAluno(<?= $aula['aluno_id'] ?? 0 ?>); return false;"
                                        title="Ver detalhes do aluno"
                                        style="line-height: 1; padding: 2px 8px; font-size: 0.75rem;">
                                    <i class="fas fa-user"></i> Ver Aluno
                                </button>
                            </div>
                            <div style="font-size: 14px; color: #64748b;">
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?>
                                <i class="fas fa-clock" style="margin-left: 12px;"></i> <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - <?php echo date('H:i', strtotime($aula['hora_fim'])); ?>
                            </div>
                            <?php if ($aula['veiculo_modelo']): ?>
                            <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                                <i class="fas fa-car"></i> <?php echo htmlspecialchars($aula['veiculo_modelo']); ?> - <?php echo htmlspecialchars($aula['veiculo_placa']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($aula['tipo_aula'] === 'pratica'): ?>
                                <?php if ($aula['status'] === 'em_andamento' && isset($aula['km_inicial']) && $aula['km_inicial'] !== null): ?>
                                <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">
                                    KM inicial: <?php echo number_format($aula['km_inicial'], 0, ',', '.'); ?>
                                </div>
                                <?php elseif ($aula['status'] === 'concluida' && isset($aula['km_inicial']) && $aula['km_inicial'] !== null && isset($aula['km_final']) && $aula['km_final'] !== null): ?>
                                <?php $kmRodados = $aula['km_final'] - $aula['km_inicial']; ?>
                                <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">
                                    KM: <?php echo number_format($aula['km_inicial'], 0, ',', '.'); ?> → <?php echo number_format($aula['km_final'], 0, ',', '.'); ?> (<?php echo $kmRodados >= 0 ? '+' : ''; ?><?php echo number_format($kmRodados, 0, ',', '.'); ?>)
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Ações -->
                    <?php if ($aula['status'] !== 'cancelada' && $aula['status'] !== 'concluida'): ?>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <!-- FASE INSTRUTOR - AULAS TEORICAS - Botões apenas para aulas práticas nesta seção -->
                        <?php 
                        // TAREFA 2.2 - Adicionar botões de iniciar/finalizar aula
                        $statusAula = $aula['status'] ?? 'agendada';
                        ?>
                        <?php if ($statusAula === 'agendada'): ?>
                        <button class="btn btn-sm btn-success iniciar-aula" 
                                data-aula-id="<?php echo $aula['id']; ?>"
                                style="padding: 6px 12px; background: #10b981; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                            <i class="fas fa-play"></i> Iniciar
                        </button>
                        <?php elseif ($statusAula === 'em_andamento'): ?>
                        <button class="btn btn-sm btn-primary finalizar-aula" 
                                data-aula-id="<?php echo $aula['id']; ?>"
                                style="padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                            <i class="fas fa-stop"></i> Finalizar
                        </button>
                        <?php endif; ?>
                        <?php if ($statusAula === 'agendada'): ?>
                        <button 
                            class="btn btn-sm btn-warning solicitar-transferencia" 
                            data-aula-id="<?php echo $aula['id']; ?>"
                            data-data="<?php echo $aula['data_aula']; ?>"
                            data-hora="<?php echo $aula['hora_inicio']; ?>"
                            style="padding: 6px 12px; background: #f59e0b; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;"
                        >
                            <i class="fas fa-exchange-alt"></i> Transferir
                        </button>
                        <button 
                            class="btn btn-sm btn-danger cancelar-aula" 
                            data-aula-id="<?php echo $aula['id']; ?>"
                            data-data="<?php echo $aula['data_aula']; ?>"
                            data-hora="<?php echo $aula['hora_inicio']; ?>"
                            style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;"
                        >
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- FASE 1 - PRESENCA TEORICA - Lista de Aulas Teóricas -->
        <?php if ($tipoFiltro === '' || $tipoFiltro === 'teorica'): ?>
        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;">
            <div style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #3b82f6;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">
                    <i class="fas fa-book me-2" style="color: #3b82f6;"></i>
                    Aulas Teóricas
                </h3>
            </div>
            <?php if (empty($aulasTeoricas)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-calendar-times" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
                <h3 style="color: #64748b; margin-bottom: 8px;">Nenhuma aula teórica encontrada</h3>
                <p style="color: #94a3b8;">Não há aulas teóricas no período selecionado.</p>
            </div>
            <?php else: ?>
            <div class="aula-list" style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($aulasTeoricas as $aula): ?>
                    <?php 
                    $nomesDisciplinas = [
                        'legislacao_transito' => 'Legislação de Trânsito',
                        'direcao_defensiva' => 'Direção Defensiva',
                        'primeiros_socorros' => 'Primeiros Socorros',
                        'meio_ambiente_cidadania' => 'Meio Ambiente e Cidadania',
                        'mecanica_basica' => 'Mecânica Básica'
                    ];
                    $disciplinaNome = $nomesDisciplinas[$aula['disciplina']] ?? ucfirst(str_replace('_', ' ', $aula['disciplina']));
                    ?>
                    <div class="aula-item" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #f8fafc;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span style="padding: 4px 8px; background: #3b82f6; color: white; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                        Teórica
                                    </span>
                                    <span style="padding: 4px 8px; background: <?php 
                                        echo $aula['status'] === 'agendada' ? '#fbbf24' : 
                                            ($aula['status'] === 'realizada' ? '#10b981' : '#ef4444'); 
                                    ?>; color: white; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                        <?php echo ucfirst($aula['status']); ?>
                                    </span>
                                </div>
                                <div style="font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($aula['turma_nome']); ?>
                                </div>
                                <div style="font-size: 14px; color: #64748b; margin-bottom: 4px;">
                                    <i class="fas fa-book"></i> <?php echo htmlspecialchars($disciplinaNome); ?>
                                </div>
                                <div style="font-size: 14px; color: #64748b;">
                                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($aula['data_aula'])); ?>
                                    <i class="fas fa-clock" style="margin-left: 12px;"></i> <?php echo date('H:i', strtotime($aula['hora_inicio'])); ?> - <?php echo date('H:i', strtotime($aula['hora_fim'])); ?>
                                </div>
                                <?php if ($aula['sala_nome']): ?>
                                <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                                    <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($aula['sala_nome']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Ações -->
                        <?php if ($aula['status'] !== 'cancelada' && $aula['status'] !== 'realizada'): ?>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <a 
                                href="../admin/index.php?page=turma-chamada&turma_id=<?php echo (int)($aula['turma_id'] ?? 0); ?>&aula_id=<?php echo (int)($aula['id'] ?? 0); ?>&origem=instrutor" 
                                class="btn btn-sm btn-primary" 
                                style="padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block;"
                            >
                                <i class="fas fa-clipboard-check"></i> Abrir Chamada
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Cancelamento/Transferência (reutilizado do dashboard) -->
    <div id="modalAcao" class="modal-overlay hidden" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal" style="background: white; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h3 class="modal-title" id="modalTitulo" style="font-size: 20px; font-weight: 600; color: #1e293b;">Cancelar Aula</h3>
            </div>
            <div class="modal-body">
                <form id="formAcao">
                    <input type="hidden" id="aulaId" name="aula_id">
                    <input type="hidden" id="tipoAcao" name="tipo_acao">
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Data da Aula</label>
                        <input type="text" id="dataAula" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; background: #f1f5f9;">
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Horário</label>
                        <input type="text" id="horaAula" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; background: #f1f5f9;">
                    </div>
                    
                    <div id="novaDataGroup" style="margin-bottom: 16px; display: none;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Nova Data</label>
                        <input type="date" id="novaData" name="nova_data" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    
                    <div id="novaHoraGroup" style="margin-bottom: 16px; display: none;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Novo Horário</label>
                        <input type="time" id="novaHora" name="nova_hora" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Motivo</label>
                        <select id="motivo" name="motivo" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                            <option value="">Selecione um motivo</option>
                            <option value="problema_saude">Problema de saúde</option>
                            <option value="imprevisto_pessoal">Imprevisto pessoal</option>
                            <option value="problema_veiculo">Problema com veículo</option>
                            <option value="ausencia_aluno">Ausência do aluno</option>
                            <option value="condicoes_climaticas">Condições climáticas</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 14px;">Justificativa <span style="color: #ef4444;">*</span></label>
                        <textarea 
                            id="justificativa" 
                            name="justificativa" 
                            required
                            placeholder="Descreva o motivo da ação..."
                            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; min-height: 100px; resize: vertical;"
                        ></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button 
                            type="button" 
                            onclick="fecharModal()" 
                            style="flex: 1; padding: 10px; background: #e2e8f0; color: #475569; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="button" 
                            onclick="enviarAcao()" 
                            style="flex: 1; padding: 10px; background: #2563eb; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;"
                        >
                            Confirmar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

    <!-- Bootstrap 5 JS (para modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // FASE 1 - Reutilização: Mesmo código JavaScript do dashboard.php
        // Arquivo: instrutor/aulas.php (linha ~350)
        let modalAberto = false;

        function abrirModal(tipo, aulaId, data, hora) {
            const tipoNormalizado = tipo === 'cancelamento' ? 'cancelamento' : 
                                   tipo === 'transferencia' ? 'transferencia' : 
                                   tipo === 'cancelar' ? 'cancelamento' : 
                                   tipo === 'transferir' ? 'transferencia' : tipo;
            
            document.getElementById('tipoAcao').value = tipoNormalizado;
            document.getElementById('aulaId').value = aulaId;
            document.getElementById('dataAula').value = data;
            document.getElementById('horaAula').value = hora;
            
            const modal = document.getElementById('modalAcao');
            const titulo = document.getElementById('modalTitulo');
            const novaDataGroup = document.getElementById('novaDataGroup');
            const novaHoraGroup = document.getElementById('novaHoraGroup');
            
            if (tipoNormalizado === 'transferencia') {
                titulo.textContent = 'Solicitar Transferência';
                novaDataGroup.style.display = 'block';
                novaHoraGroup.style.display = 'block';
            } else {
                titulo.textContent = 'Cancelar Aula';
                novaDataGroup.style.display = 'none';
                novaHoraGroup.style.display = 'none';
            }
            
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            modalAberto = true;
        }

        function fecharModal() {
            document.getElementById('modalAcao').style.display = 'none';
            document.getElementById('modalAcao').classList.add('hidden');
            document.getElementById('formAcao').reset();
            modalAberto = false;
        }

        async function enviarAcao() {
            const form = document.getElementById('formAcao');
            const formData = new FormData(form);
            
            if (!formData.get('justificativa').trim()) {
                alert('Por favor, preencha a justificativa.');
                return;
            }

            try {
                const response = await fetch('../admin/api/instrutor-aulas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        aula_id: formData.get('aula_id'),
                        tipo_acao: formData.get('tipo_acao'),
                        nova_data: formData.get('nova_data'),
                        nova_hora: formData.get('nova_hora'),
                        motivo: formData.get('motivo'),
                        justificativa: formData.get('justificativa')
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Ação realizada com sucesso!');
                    fecharModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    alert(result.message || 'Erro ao realizar ação.');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de conexão. Tente novamente.');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.cancelar-aula').forEach(btn => {
                btn.addEventListener('click', function() {
                    const aulaId = this.dataset.aulaId;
                    const data = this.dataset.data;
                    const hora = this.dataset.hora;
                    abrirModal('cancelamento', aulaId, data, hora);
                });
            });

            document.querySelectorAll('.solicitar-transferencia').forEach(btn => {
                btn.addEventListener('click', function() {
                    const aulaId = this.dataset.aulaId;
                    const data = this.dataset.data;
                    const hora = this.dataset.hora;
                    abrirModal('transferencia', aulaId, data, hora);
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
                        alert('KM inicial deve ser um número válido.');
                        return;
                    }
                    
                    // Validar >= 0
                    if (kmInicial < 0) {
                        alert('KM inicial deve ser maior ou igual a zero.');
                        return;
                    }
                    
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
                            alert('Aula iniciada com sucesso!');
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            alert(result.message || 'Erro ao iniciar aula.');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        alert('Erro de conexão. Tente novamente.');
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
                        alert('KM final deve ser um número válido.');
                        return;
                    }
                    
                    // Validar >= 0
                    if (kmFinal < 0) {
                        alert('KM final deve ser maior ou igual a zero.');
                        return;
                    }
                    
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
                            alert('Aula finalizada com sucesso!');
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            alert(result.message || 'Erro ao finalizar aula.');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        alert('Erro de conexão. Tente novamente.');
                    }
                });
            });

            document.getElementById('modalAcao').addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModal();
                }
            });
        });

        // Função para abrir modal de aluno (suporta aulas práticas e teóricas)
        function abrirModalAluno(alunoId, turmaId = null) {
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
                        
                        // Foto ou avatar padrão (fallback para ícone se não existir)
                        let fotoUrl = '';
                        if (aluno.foto && aluno.foto.trim() !== '') {
                            fotoUrl = '../' + aluno.foto;
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
</body>
</html>

