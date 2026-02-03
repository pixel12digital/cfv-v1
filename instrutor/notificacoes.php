<?php
/**
 * Central de Avisos / Notificações do Instrutor
 * 
 * FASE 2 - Implementação: 2024
 * Arquivo: instrutor/notificacoes.php
 * 
 * Funcionalidades:
 * - Listagem completa de notificações do instrutor
 * - Marcar como lida/deslida
 * - Visualizar detalhes da notificação
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/services/SistemaNotificacoes.php';

// FASE 2 - Verificação de autenticação (padrão do portal)
// Arquivo: instrutor/notificacoes.php (linha ~13)
$user = getCurrentUser();
if (!$user || $user['tipo'] !== 'instrutor') {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    header('Location: ' . $basePath . '/login.php');
    exit();
}

$db = db();
$notificacoes = new SistemaNotificacoes();

// FASE 2 - Verificação de precisa_trocar_senha (padrão do portal)
// Arquivo: instrutor/notificacoes.php (linha ~22)
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

// FASE 2 - Buscar dados do instrutor (padrão do portal)
// Arquivo: instrutor/notificacoes.php (linha ~37)
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

// FASE 2 - Buscar todas as notificações do instrutor
// Arquivo: instrutor/notificacoes.php (linha ~60)
// Reutiliza a mesma fonte de dados do dashboard
$todasNotificacoes = [];
try {
    $todasNotificacoes = $db->fetchAll("
        SELECT n.*, 
               CASE 
                   WHEN n.tipo_usuario = 'instrutor' THEN i.nome
                   WHEN n.tipo_usuario = 'admin' THEN u.nome
                   WHEN n.tipo_usuario = 'secretaria' THEN u.nome
               END as nome_usuario
        FROM notificacoes n
        LEFT JOIN instrutores i ON n.usuario_id = i.id AND n.tipo_usuario = 'instrutor'
        LEFT JOIN usuarios u ON n.usuario_id = u.id AND n.tipo_usuario IN ('admin', 'secretaria')
        WHERE n.usuario_id = ? AND n.tipo_usuario = ?
        ORDER BY n.criado_em DESC
        LIMIT 100
    ", [$user['id'], 'instrutor']);
} catch (Exception $e) {
    error_log('Erro ao buscar notificações: ' . $e->getMessage());
}

// FASE 2 - Estatísticas
// Arquivo: instrutor/notificacoes.php (linha ~80)
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
    <title>Central de Avisos - <?php echo htmlspecialchars($instrutor['nome']); ?></title>
    <link rel="stylesheet" href="../assets/css/theme-tokens.css">
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
                <h1>Central de Avisos</h1>
                <div class="subtitle">Todas as suas notificações</div>
            </div>
            <a href="dashboard.php" style="color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 8px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="container" style="max-width: 1000px; margin: 0 auto; padding: 20px 16px;">
        <!-- Estatísticas -->
        <div class="grid grid-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
            <div class="stat-item" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #2563eb; margin-bottom: 4px;"><?php echo $stats['total']; ?></div>
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase;">Total</div>
            </div>
            <div class="stat-item" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #f59e0b; margin-bottom: 4px;"><?php echo $stats['nao_lidas']; ?></div>
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase;">Não Lidas</div>
            </div>
            <div class="stat-item" style="background: white; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: #10b981; margin-bottom: 4px;"><?php echo $stats['lidas']; ?></div>
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase;">Lidas</div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 16px; margin-bottom: 20px;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <button 
                    id="marcarTodasLidas" 
                    style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;"
                >
                    <i class="fas fa-check-double"></i> Marcar Todas como Lidas
                </button>
                <button 
                    id="filtrarNaoLidas" 
                    style="padding: 8px 16px; background: #f59e0b; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;"
                >
                    <i class="fas fa-filter"></i> Mostrar Apenas Não Lidas
                </button>
                <button 
                    id="mostrarTodas" 
                    style="padding: 8px 16px; background: #64748b; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;"
                >
                    <i class="fas fa-list"></i> Mostrar Todas
                </button>
            </div>
        </div>

        <!-- Lista de Notificações -->
        <div class="card" style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 24px;">
            <?php if (empty($todasNotificacoes)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-bell-slash" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
                <h3 style="color: #64748b; margin-bottom: 8px;">Nenhuma notificação</h3>
                <p style="color: #94a3b8;">Você não possui notificações no momento.</p>
            </div>
            <?php else: ?>
            <div class="notificacoes-list" style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($todasNotificacoes as $notificacao): ?>
                <div 
                    class="notificacao-item" 
                    data-id="<?php echo $notificacao['id']; ?>"
                    data-lida="<?php echo $notificacao['lida'] ? '1' : '0'; ?>"
                    style="border: 1px solid <?php echo $notificacao['lida'] ? '#e2e8f0' : '#3b82f6'; ?>; border-left: 4px solid <?php echo $notificacao['lida'] ? '#94a3b8' : '#3b82f6'; ?>; border-radius: 8px; padding: 16px; background: <?php echo $notificacao['lida'] ? '#ffffff' : '#f0f7ff'; ?>; cursor: pointer; transition: all 0.2s;"
                    onclick="toggleDetalhes(<?php echo $notificacao['id']; ?>)"
                >
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <?php if (!$notificacao['lida']): ?>
                                <span style="width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; display: inline-block;"></span>
                                <?php endif; ?>
                                <h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin: 0;">
                                    <?php echo htmlspecialchars($notificacao['titulo'] ?? 'Notificação'); ?>
                                </h3>
                            </div>
                            <div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">
                                <?php 
                                $mensagemResumo = htmlspecialchars($notificacao['mensagem'] ?? '');
                                if (strlen($mensagemResumo) > 100) {
                                    echo substr($mensagemResumo, 0, 100) . '...';
                                } else {
                                    echo $mensagemResumo;
                                }
                                ?>
                            </div>
                            <div style="font-size: 12px; color: #94a3b8;">
                                <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($notificacao['criado_em'])); ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; margin-left: 12px;">
                            <?php if (!$notificacao['lida']): ?>
                            <button 
                                class="btn-marcar-lida" 
                                data-id="<?php echo $notificacao['id']; ?>"
                                onclick="event.stopPropagation(); marcarComoLida(<?php echo $notificacao['id']; ?>, false);"
                                style="padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;"
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
                        style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;"
                    >
                        <div style="font-size: 14px; color: #1e293b; white-space: pre-wrap;">
                            <?php echo htmlspecialchars($notificacao['mensagem'] ?? ''); ?>
                        </div>
                        <?php if (!empty($notificacao['dados'])): ?>
                        <div style="margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 6px; font-size: 12px; color: #64748b;">
                            <strong>Dados adicionais:</strong>
                            <pre style="margin: 8px 0 0 0; font-size: 11px; overflow-x: auto;"><?php echo htmlspecialchars(json_encode(json_decode($notificacao['dados']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // FASE 2 - Funções JavaScript para gerenciar notificações
        // Arquivo: instrutor/notificacoes.php (linha ~280)
        // Reutiliza a mesma API do dashboard (admin/api/notificacoes.php)

        // Toggle detalhes da notificação
        function toggleDetalhes(notificacaoId) {
            const detalhes = document.getElementById('detalhes-' + notificacaoId);
            if (detalhes) {
                detalhes.style.display = detalhes.style.display === 'none' ? 'block' : 'none';
            }
        }

        // Marcar notificação como lida
        // FASE 2 - Usar API existente admin/api/notificacoes.php
        // Arquivo: instrutor/notificacoes.php (linha ~300)
        // Nota: A API atual só suporta marcar como lida, não como não lida
        async function marcarComoLida(notificacaoId, estaLida) {
            try {
                const url = '../admin/api/notificacoes.php';
                
                if (estaLida) {
                    // Se já está lida, não fazer nada (API não suporta desmarcar)
                    alert('A notificação já está marcada como lida. A API atual não suporta desmarcar.');
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
        document.getElementById('marcarTodasLidas').addEventListener('click', async function() {
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
        document.getElementById('filtrarNaoLidas').addEventListener('click', function() {
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
        document.getElementById('mostrarTodas').addEventListener('click', function() {
            const items = document.querySelectorAll('.notificacao-item');
            items.forEach(item => {
                item.style.display = 'block';
            });
        });
    </script>
</body>
</html>

