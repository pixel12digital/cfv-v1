<?php
/**
 * Central de Avisos / Notificações do Aluno
 * 
 * FASE 2 - NOTIFICACOES ALUNO - Implementação: 2025
 * Arquivo: aluno/notificacoes.php
 * 
 * Funcionalidades:
 * - Listagem completa de notificações do aluno
 * - Marcar como lida/deslida
 * - Visualizar detalhes da notificação
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/services/SistemaNotificacoes.php';

// FASE 2 - NOTIFICACOES ALUNO - Verificação de autenticação
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'aluno') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();
$notificacoes = new SistemaNotificacoes();

// FASE 2 - NOTIFICACOES ALUNO - Obter aluno_id usando getCurrentAlunoId()
$alunoId = getCurrentAlunoId($user['id']);

if (!$alunoId) {
    // Se não encontrou o aluno, redirecionar com erro
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/aluno/dashboard.php?erro=aluno_nao_encontrado');
    exit();
}

// FASE 2 - NOTIFICACOES ALUNO - Buscar dados do aluno
// Nota: A tabela alunos não possui coluna usuario_id, então buscamos diretamente da tabela alunos
$aluno = $db->fetch("SELECT * FROM alunos WHERE id = ?", [$alunoId]);

if ($aluno) {
    // Buscar dados do usuário relacionado (se existir) usando email ou CPF
    $usuario = null;
    if (!empty($aluno['email'])) {
        $usuario = $db->fetch("SELECT id, nome, email FROM usuarios WHERE email = ? AND tipo = 'aluno' LIMIT 1", [$aluno['email']]);
    }
    
    // Se não encontrou por email, tentar por CPF
    if (!$usuario && !empty($aluno['cpf'])) {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $aluno['cpf']);
        $usuario = $db->fetch("SELECT id, nome, email FROM usuarios WHERE cpf = ? AND tipo = 'aluno' LIMIT 1", [$cpfLimpo]);
    }
    
    // Adicionar dados do usuário ao array do aluno
    if ($usuario) {
        $aluno['nome_usuario'] = $usuario['nome'] ?? $aluno['nome'] ?? 'Aluno';
        $aluno['email_usuario'] = $usuario['email'] ?? $aluno['email'] ?? '';
    } else {
        $aluno['nome_usuario'] = $aluno['nome'] ?? 'Aluno';
        $aluno['email_usuario'] = $aluno['email'] ?? '';
    }
}

if (!$aluno) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/aluno/dashboard.php?erro=aluno_nao_encontrado');
    exit();
}

$aluno['nome'] = $aluno['nome'] ?? $aluno['nome_usuario'] ?? $user['nome'] ?? 'Aluno';

// FASE 2 - NOTIFICACOES ALUNO - Buscar todas as notificações do aluno
// IMPORTANTE: As notificações são armazenadas com usuario_id = aluno_id (da tabela alunos)
// e tipo_usuario = 'aluno'
$todasNotificacoes = [];
try {
    $todasNotificacoes = $db->fetchAll("
        SELECT n.*, 
               a.nome as nome_usuario
        FROM notificacoes n
        LEFT JOIN alunos a ON n.usuario_id = a.id AND n.tipo_usuario = 'aluno'
        WHERE n.usuario_id = ? AND n.tipo_usuario = 'aluno'
        ORDER BY n.criado_em DESC
        LIMIT 100
    ", [$alunoId]);
} catch (Exception $e) {
    error_log('Erro ao buscar notificações do aluno: ' . $e->getMessage());
}

// FASE 2 - NOTIFICACOES ALUNO - Estatísticas
$stats = [
    'total' => count($todasNotificacoes),
    'nao_lidas' => 0,
    'lidas' => 0
];

foreach ($todasNotificacoes as $notif) {
    if ($notif['lida'] == 0 || $notif['lida'] === false) {
        $stats['nao_lidas']++;
    } else {
        $stats['lidas']++;
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
    <title>Central de Avisos - <?php echo htmlspecialchars($aluno['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme-overrides.css">
    <link rel="stylesheet" href="../assets/css/aluno-dashboard.css">
    <style>
        /* Removido header-notificacoes - agora é card dentro do conteúdo */
        .stat-item {
            background: white;
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.2s;
        }
        .stat-item:hover {
            transform: translateY(-2px);
        }
        .stat-item .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stat-item .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .notificacao-item {
            border: 1px solid #e2e8f0;
            border-left: 4px solid #94a3b8;
            border-radius: 8px;
            padding: 1rem;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0.75rem;
        }
        .notificacao-item.nao-lida {
            border-left-color: #3b82f6;
            background: #f0f7ff;
        }
        .notificacao-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .notificacao-detalhes {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: none;
        }
        .notificacao-detalhes.show {
            display: block;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
        @media (max-width: 576px) {
            /* Removido media query de header-notificacoes */
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
                            <i class="fas fa-bell me-2 text-primary"></i>
                            Central de Avisos
                        </h1>
                        <p class="text-muted mb-0 small">Mensagens importantes enviadas pelo CFC.</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
        <!-- Estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4">
                <div class="stat-item">
                    <div class="stat-value text-primary"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="stat-item">
                    <div class="stat-value text-warning"><?php echo $stats['nao_lidas']; ?></div>
                    <div class="stat-label">Não Lidas</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="stat-item">
                    <div class="stat-value text-success"><?php echo $stats['lidas']; ?></div>
                    <div class="stat-label">Lidas</div>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($stats['nao_lidas'] > 0): ?>
                    <button 
                        id="marcarTodasLidas" 
                        class="btn btn-success"
                    >
                        <i class="fas fa-check-double me-2"></i>Marcar Todas como Lidas
                    </button>
                    <?php endif; ?>
                    <button 
                        id="filtrarNaoLidas" 
                        class="btn btn-warning"
                    >
                        <i class="fas fa-filter me-2"></i>Mostrar Apenas Não Lidas
                    </button>
                    <button 
                        id="mostrarTodas" 
                        class="btn btn-secondary"
                    >
                        <i class="fas fa-list me-2"></i>Mostrar Todas
                    </button>
                </div>
            </div>
        </div>

        <!-- Lista de Notificações -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($todasNotificacoes)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <h5 class="fw-semibold mb-2">Nenhum aviso por aqui.</h5>
                    <p class="text-muted mb-0">
                        Quando o CFC enviar alguma mensagem importante, ela aparecerá nesta área.
                    </p>
                </div>
                <?php else: ?>
                <div class="notificacoes-list">
                    <?php foreach ($todasNotificacoes as $notificacao): ?>
                    <div 
                        class="notificacao-item <?php echo (!$notificacao['lida']) ? 'nao-lida' : ''; ?>" 
                        data-id="<?php echo $notificacao['id']; ?>"
                        data-lida="<?php echo $notificacao['lida'] ? '1' : '0'; ?>"
                        onclick="toggleDetalhes(<?php echo $notificacao['id']; ?>)"
                    >
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <?php if (!$notificacao['lida']): ?>
                                    <span style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; display: inline-block;"></span>
                                    <?php endif; ?>
                                    <h6 class="mb-0 fw-semibold">
                                        <?php echo htmlspecialchars($notificacao['titulo'] ?? 'Notificação'); ?>
                                    </h6>
                                </div>
                                <div class="text-muted mb-2" style="font-size: 0.9rem;">
                                    <?php 
                                    $mensagemResumo = htmlspecialchars($notificacao['mensagem'] ?? '');
                                    if (strlen($mensagemResumo) > 120) {
                                        echo substr($mensagemResumo, 0, 120) . '...';
                                    } else {
                                        echo $mensagemResumo;
                                    }
                                    ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.8rem;">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($notificacao['criado_em'])); ?>
                                </div>
                            </div>
                            <div class="ms-3">
                                <?php if (!$notificacao['lida']): ?>
                                <button 
                                    class="btn btn-sm btn-primary btn-marcar-lida" 
                                    data-id="<?php echo $notificacao['id']; ?>"
                                    onclick="event.stopPropagation(); marcarComoLida(<?php echo $notificacao['id']; ?>, false);"
                                    title="Marcar como lida"
                                >
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Detalhes (oculto por padrão) -->
                        <div 
                            id="detalhes-<?php echo $notificacao['id']; ?>" 
                            class="notificacao-detalhes"
                        >
                            <div class="text-dark" style="white-space: pre-wrap; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($notificacao['mensagem'] ?? ''); ?>
                            </div>
                            <?php if (!empty($notificacao['dados'])): ?>
                            <div class="mt-3 p-3 bg-light rounded" style="font-size: 0.85rem;">
                                <strong>Dados adicionais:</strong>
                                <pre class="mt-2 mb-0" style="font-size: 0.75rem; overflow-x: auto;"><?php echo htmlspecialchars(json_encode(json_decode($notificacao['dados']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // FASE 2 - NOTIFICACOES ALUNO - Funções JavaScript para gerenciar notificações
        
        // Toggle detalhes da notificação
        function toggleDetalhes(notificacaoId) {
            const detalhes = document.getElementById('detalhes-' + notificacaoId);
            if (detalhes) {
                detalhes.classList.toggle('show');
            }
        }

        // Marcar notificação como lida
        async function marcarComoLida(notificacaoId, estaLida) {
            try {
                const url = '../admin/api/notificacoes.php';
                
                if (estaLida) {
                    // Se já está lida, não fazer nada (API não suporta desmarcar)
                    return;
                }
                
                // Marcar como lida
                const response = await fetch(url, {
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
                    // Recarregar a página para atualizar contadores e badge do dashboard
                    window.location.reload();
                } else {
                    alert(result.message || 'Erro ao atualizar notificação.');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de conexão. Tente novamente.');
            }
        }

        // Marcar todas como lidas
        document.getElementById('marcarTodasLidas')?.addEventListener('click', async function() {
            if (!confirm('Deseja marcar todas as notificações como lidas?')) {
                return;
            }

            try {
                const response = await fetch('../admin/api/notificacoes.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });

                const result = await response.json();

                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Erro ao marcar notificações.');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de conexão. Tente novamente.');
            }
        });

        // Filtrar apenas não lidas
        document.getElementById('filtrarNaoLidas')?.addEventListener('click', function() {
            const items = document.querySelectorAll('.notificacao-item');
            items.forEach(item => {
                const lida = item.getAttribute('data-lida') === '1';
                if (lida) {
                    item.style.display = 'none';
                } else {
                    item.style.display = 'block';
                }
            });
        });

        // Mostrar todas
        document.getElementById('mostrarTodas')?.addEventListener('click', function() {
            const items = document.querySelectorAll('.notificacao-item');
            items.forEach(item => {
                item.style.display = 'block';
            });
        });
    </script>
</body>
</html>

