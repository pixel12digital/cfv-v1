<?php
$currentRole = $_SESSION['current_role'] ?? '';
?>

<div class="content-header">
    <h1 class="content-title">Dashboard</h1>
    <p class="content-subtitle">Bem-vindo, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?>!</p>
</div>

<!-- Ações Rápidas -->
<div class="card" style="margin-bottom: var(--spacing-md);">
    <div class="card-header">
        <h3 style="margin: 0;">Ações Rápidas</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: var(--spacing-sm);">
            <a href="<?= base_path('agenda/novo') ?>" class="btn btn-primary" style="display: flex; flex-direction: column; align-items: center; gap: var(--spacing-xs); padding: var(--spacing-md);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Nova Aula</span>
            </a>
            
            <a href="<?= base_path('solicitacoes-reagendamento') ?>" class="btn btn-outline" style="display: flex; flex-direction: column; align-items: center; gap: var(--spacing-xs); padding: var(--spacing-md); position: relative;">
                <?php if ($pendingRequestsCount > 0): ?>
                <span style="position: absolute; top: 4px; right: 4px; background: var(--color-danger, #dc3545); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold;">
                    <?= $pendingRequestsCount > 99 ? '99+' : $pendingRequestsCount ?>
                </span>
                <?php endif; ?>
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Reagendamentos</span>
            </a>
            
            <a href="<?= base_path('notificacoes') ?>" class="btn btn-outline" style="display: flex; flex-direction: column; align-items: center; gap: var(--spacing-xs); padding: var(--spacing-md); position: relative;">
                <?php if ($unreadNotificationsCount > 0): ?>
                <span style="position: absolute; top: 4px; right: 4px; background: var(--color-danger, #dc3545); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold;">
                    <?= $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount ?>
                </span>
                <?php endif; ?>
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span>Notificações</span>
            </a>
            
            <a href="<?= base_path('financeiro?filter=pending') ?>" class="btn btn-outline" style="display: flex; flex-direction: column; align-items: center; gap: var(--spacing-xs); padding: var(--spacing-md);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Financeiro</span>
            </a>
        </div>
    </div>
</div>

<!-- Alertas (apenas se houver) -->
<?php if ($hasUrgentReschedule): ?>
<div class="card" style="margin-bottom: var(--spacing-md); background: var(--color-warning-bg, #fff3cd); border-color: var(--color-warning, #ffc107);">
    <div class="card-body">
        <div style="display: flex; align-items: center; gap: var(--spacing-sm);">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--color-warning, #ffc107); flex-shrink: 0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <strong>Atenção:</strong> Há solicitações de reagendamento pendentes para hoje ou amanhã.
                <a href="<?= base_path('solicitacoes-reagendamento') ?>" style="margin-left: var(--spacing-xs);">Ver solicitações</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Grid Principal: Desktop 2 colunas, Mobile empilhado -->
<div class="dashboard-grid">
    
    <!-- Coluna Esquerda (Desktop) / Primeiro (Mobile) -->
    <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
        
        <!-- Pendências -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">Pendências</h3>
                <?php if ($pendingRequestsCount > 0 || $unreadNotificationsCount > 0): ?>
                <span style="background: var(--color-danger, #dc3545); color: white; padding: 2px 8px; border-radius: 12px; font-size: var(--font-size-sm); font-weight: bold;">
                    <?= ($pendingRequestsCount + $unreadNotificationsCount) ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <!-- Solicitações de Reagendamento -->
                <?php if (!empty($pendingRequests)): ?>
                <div style="margin-bottom: var(--spacing-md);">
                    <h4 style="margin: 0 0 var(--spacing-sm) 0; font-size: var(--font-size-base); font-weight: var(--font-weight-semibold);">
                        Solicitações de Reagendamento (<?= $pendingRequestsCount ?>)
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                        <?php foreach ($pendingRequests as $req): ?>
                        <?php
                        $lessonDate = new \DateTime($req['scheduled_date'] . ' ' . $req['scheduled_time']);
                        $requestDate = new \DateTime($req['created_at']);
                        ?>
                        <div style="padding: var(--spacing-sm); background: var(--color-bg-secondary, #f8f9fa); border-radius: var(--border-radius, 4px); border-left: 3px solid var(--color-warning, #ffc107);">
                            <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: var(--spacing-xs);">
                                <div style="flex: 1; min-width: 200px;">
                                    <div style="font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-xs);">
                                        <?= htmlspecialchars($req['student_name'] ?? 'N/A') ?>
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                                        Aula: <?= $lessonDate->format('d/m/Y H:i') ?>
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                                        Motivo: <?= htmlspecialchars($req['reason'] ?? 'Não informado') ?>
                                    </div>
                                    <div style="font-size: var(--font-size-xs); color: var(--color-text-muted, #999);">
                                        Solicitação: <?= $requestDate->format('d/m/Y H:i') ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: var(--spacing-xs); flex-wrap: wrap;">
                                    <a href="<?= base_path("solicitacoes-reagendamento/{$req['id']}") ?>" class="btn btn-sm btn-primary">
                                        Abrir
                                    </a>
                                    <a href="<?= base_path("agenda/{$req['lesson_id']}") ?>" class="btn btn-sm btn-outline">
                                        Ver Aula
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted" style="font-size: var(--font-size-sm);">Nenhuma solicitação pendente.</p>
                <?php endif; ?>
                
                <!-- Notificações Não Lidas -->
                <?php if (!empty($unreadNotifications)): ?>
                <div>
                    <h4 style="margin: 0 0 var(--spacing-sm) 0; font-size: var(--font-size-base); font-weight: var(--font-weight-semibold);">
                        Notificações Não Lidas (<?= $unreadNotificationsCount ?>)
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                        <?php foreach ($unreadNotifications as $notif): ?>
                        <div style="padding: var(--spacing-sm); background: var(--color-bg-secondary, #f8f9fa); border-radius: var(--border-radius, 4px);">
                            <div style="font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-xs);">
                                <?= htmlspecialchars($notif['title'] ?? 'Notificação') ?>
                            </div>
                            <?php if ($notif['body']): ?>
                            <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                                <?= htmlspecialchars($notif['body']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($notif['link']): ?>
                            <a href="<?= base_path($notif['link']) ?>" class="btn btn-sm btn-outline" style="margin-top: var(--spacing-xs);">
                                Ver mais
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif (empty($pendingRequests)): ?>
                <p class="text-muted" style="font-size: var(--font-size-sm);">Nenhuma notificação não lida.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Hoje -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0;">Hoje</h3>
            </div>
            <div class="card-body">
                <!-- Contadores -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: var(--spacing-sm); margin-bottom: var(--spacing-md);">
                    <div style="text-align: center; padding: var(--spacing-sm); background: var(--color-bg-secondary, #f8f9fa); border-radius: var(--border-radius, 4px);">
                        <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-bold);">
                            <?= $totalToday ?>
                        </div>
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666);">
                            Total
                        </div>
                    </div>
                    <div style="text-align: center; padding: var(--spacing-sm); background: var(--color-bg-secondary, #f8f9fa); border-radius: var(--border-radius, 4px);">
                        <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-bold); color: var(--color-success, #28a745);">
                            <?= $completedToday ?>
                        </div>
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666);">
                            Concluídas
                        </div>
                    </div>
                    <div style="text-align: center; padding: var(--spacing-sm); background: var(--color-bg-secondary, #f8f9fa); border-radius: var(--border-radius, 4px);">
                        <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-bold); color: var(--color-primary, #007bff);">
                            <?= $inProgressToday ?>
                        </div>
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666);">
                            Em andamento
                        </div>
                    </div>
                    <?php if ($canceledToday > 0): ?>
                    <div style="text-align: center; padding: var(--spacing-sm); background: var(--color-bg-secondary, #f8f9fa); border-radius: var(--border-radius, 4px);">
                        <div style="font-size: var(--font-size-lg); font-weight: var(--font-weight-bold); color: var(--color-danger, #dc3545);">
                            <?= $canceledToday ?>
                        </div>
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666);">
                            Canceladas
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Lista de Aulas -->
                <?php if (!empty($todayLessons)): ?>
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <?php foreach ($todayLessons as $lesson): ?>
                    <?php
                    $lessonTime = new \DateTime($lesson['scheduled_time']);
                    $statusLabels = [
                        'agendada' => 'Agendada',
                        'em_andamento' => 'Em andamento',
                        'concluida' => 'Concluída',
                        'cancelada' => 'Cancelada',
                        'no_show' => 'No Show'
                    ];
                    $statusColors = [
                        'agendada' => '#007bff',
                        'em_andamento' => '#ffc107',
                        'concluida' => '#28a745',
                        'cancelada' => '#dc3545',
                        'no_show' => '#6c757d'
                    ];
                    $statusLabel = $statusLabels[$lesson['status']] ?? $lesson['status'];
                    $statusColor = $statusColors[$lesson['status']] ?? '#666';
                    ?>
                    <div style="padding: var(--spacing-sm); background: var(--color-bg-secondary, #f8f9fa); border-radius: var(--border-radius, 4px); border-left: 3px solid <?= $statusColor ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: var(--spacing-xs);">
                            <div style="flex: 1; min-width: 200px;">
                                <div style="font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-xs);">
                                    <?= $lessonTime->format('H:i') ?> - <?= htmlspecialchars($lesson['student_name'] ?? 'N/A') ?>
                                </div>
                                <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                                    Instrutor: <?= htmlspecialchars($lesson['instructor_name'] ?? 'N/A') ?>
                                </div>
                                <?php if ($lesson['vehicle_plate']): ?>
                                <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                                    Veículo: <?= htmlspecialchars($lesson['vehicle_plate']) ?>
                                </div>
                                <?php endif; ?>
                                <div style="font-size: var(--font-size-xs); color: <?= $statusColor ?>; font-weight: var(--font-weight-semibold);">
                                    <?= $statusLabel ?>
                                </div>
                            </div>
                            <a href="<?= base_path("agenda/{$lesson['id']}") ?>" class="btn btn-sm btn-outline">
                                Ver
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">Nenhuma aula hoje.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Coluna Direita (Desktop) / Segundo (Mobile) -->
    <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
        
        <!-- Próximas Aulas -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0;">Próximas Aulas</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($upcomingLessons)): ?>
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <?php foreach ($upcomingLessons as $lesson): ?>
                    <?php
                    $lessonDate = new \DateTime($lesson['scheduled_date'] . ' ' . $lesson['scheduled_time']);
                    ?>
                    <div style="padding: var(--spacing-sm); background: var(--color-bg-secondary, #f8f9fa); border-radius: var(--border-radius, 4px);">
                        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: var(--spacing-xs);">
                            <div style="flex: 1; min-width: 200px;">
                                <div style="font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-xs);">
                                    <?= $lessonDate->format('d/m/Y H:i') ?>
                                </div>
                                <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                                    <?= htmlspecialchars($lesson['student_name'] ?? 'N/A') ?>
                                </div>
                                <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666);">
                                    <?= htmlspecialchars($lesson['instructor_name'] ?? 'N/A') ?>
                                </div>
                            </div>
                            <a href="<?= base_path("agenda/{$lesson['id']}") ?>" class="btn btn-sm btn-outline">
                                Ver
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">Nenhuma aula futura agendada.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Financeiro Rápido -->
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0;">Financeiro Rápido</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                    <div style="padding: var(--spacing-md); background: var(--color-success-bg, #d4edda); border-radius: var(--border-radius, 4px); border-left: 3px solid var(--color-success, #28a745);">
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                            Total Recebido
                        </div>
                        <div style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); color: var(--color-success, #28a745);">
                            R$ <?= number_format($totalRecebido, 2, ',', '.') ?>
                        </div>
                    </div>
                    
                    <div style="padding: var(--spacing-md); background: var(--color-warning-bg, #fff3cd); border-radius: var(--border-radius, 4px); border-left: 3px solid var(--color-warning, #ffc107);">
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                            Total a Receber
                        </div>
                        <div style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); color: var(--color-warning, #ffc107);">
                            R$ <?= number_format($totalAReceber, 2, ',', '.') ?>
                        </div>
                    </div>
                    
                    <a href="<?= base_path('financeiro?filter=pending') ?>" style="text-decoration: none; color: inherit; display: block; padding: var(--spacing-md); background: var(--color-danger-bg, #f8d7da); border-radius: var(--border-radius, 4px); border-left: 3px solid var(--color-danger, #dc3545); cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'" title="Ver matrículas com saldo devedor">
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #666); margin-bottom: var(--spacing-xs);">
                            Alunos com Saldo Devedor
                        </div>
                        <div style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); color: var(--color-danger, #dc3545);">
                            <?= $qtdDevedores ?>
                        </div>
                    </a>
                </div>
                
                <div style="margin-top: var(--spacing-md);">
                    <a href="<?= base_path('financeiro?filter=pending') ?>" class="btn btn-outline" style="width: 100%;">
                        Ver detalhes financeiros
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Mobile-first: grid empilhado por padrão (1 coluna) */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--spacing-md);
}

/* Desktop: 2 colunas */
@media (min-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr 1fr;
    }
}

/* Ajustes para mobile 375px */
@media (max-width: 375px) {
    .card-body > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .btn[style*="flex-direction: column"] {
        font-size: var(--font-size-xs, 12px);
        padding: var(--spacing-sm) !important;
    }
}
</style>
