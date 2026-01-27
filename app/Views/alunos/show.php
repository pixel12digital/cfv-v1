<?php
use App\Helpers\ValidationHelper;
use App\Models\Student;
$studentModel = new Student();
$fullName = $studentModel->getFullName($student);
$primaryPhone = $studentModel->getPrimaryPhone($student);
?>

<div class="page-header">
    <div style="display: flex; align-items: center; gap: var(--spacing-md);">
        <?php if (!empty($student['photo_path'])): ?>
        <div class="student-avatar">
            <img src="<?= base_path("alunos/{$student['id']}/foto") ?>" alt="Foto do aluno" class="avatar-img">
        </div>
        <?php else: ?>
        <div class="student-avatar">
            <div class="avatar-placeholder">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
        </div>
        <?php endif; ?>
        <div>
            <h1><?= htmlspecialchars($fullName) ?></h1>
            <p class="text-muted">CPF: <?= htmlspecialchars(ValidationHelper::formatCpf($student['cpf'])) ?></p>
        </div>
    </div>
    <div style="display: flex; gap: var(--spacing-sm); flex-wrap: wrap;">
        <?php if (\App\Services\PermissionService::check('enrollments', 'create')): ?>
        <a href="<?= base_path("alunos/{$student['id']}/matricular") ?>" class="btn btn-primary btn-sm">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nova Matrícula
        </a>
        <?php endif; ?>
        <?php if (\App\Services\PermissionService::check('alunos', 'update')): ?>
        <a href="<?= base_path("alunos/{$student['id']}/editar") ?>" class="btn btn-outline btn-sm">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Editar
        </a>
        <?php endif; ?>
        <a href="<?= base_path('alunos') ?>" class="btn btn-outline btn-sm">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Voltar
        </a>
    </div>
</div>

<div class="tabs">
    <a href="<?= base_path("alunos/{$student['id']}?tab=dados") ?>" class="tab-item <?= $tab === 'dados' ? 'active' : '' ?>">
        Dados
    </a>
    <a href="<?= base_path("alunos/{$student['id']}?tab=matricula") ?>" class="tab-item <?= $tab === 'matricula' ? 'active' : '' ?>">
        Matrículas
    </a>
    <a href="<?= base_path("alunos/{$student['id']}?tab=documentos") ?>" class="tab-item <?= $tab === 'documentos' ? 'active' : '' ?>">
        Documentos
    </a>
    <a href="<?= base_path("alunos/{$student['id']}?tab=progresso") ?>" class="tab-item <?= $tab === 'progresso' ? 'active' : '' ?>">
        Progresso
    </a>
    <a href="<?= base_path("alunos/{$student['id']}?tab=historico") ?>" class="tab-item <?= $tab === 'historico' ? 'active' : '' ?>">
        Histórico
    </a>
</div>

<?php if ($tab === 'dados'): ?>
    <!-- Upload de Foto -->
    <?php if (\App\Services\PermissionService::check('alunos', 'update')): ?>
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-body">
            <div style="display: flex; align-items: center; gap: var(--spacing-md); flex-wrap: wrap;">
                <div>
                    <?php if (!empty($student['photo_path'])): ?>
                    <img src="<?= base_path("alunos/{$student['id']}/foto") ?>" alt="Foto" style="width: 120px; height: 120px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                    <?php else: ?>
                    <div style="width: 120px; height: 120px; background: var(--color-bg-light); border-radius: var(--radius-md); border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: center;">
                        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--color-text-muted);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <form method="POST" action="<?= base_path("alunos/{$student['id']}/foto/upload") ?>" enctype="multipart/form-data" style="margin-bottom: var(--spacing-sm);">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png,image/webp" required style="margin-bottom: var(--spacing-sm);">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            Enviar Foto
                        </button>
                    </form>
                    <?php if (!empty($student['photo_path'])): ?>
                    <form method="POST" action="<?= base_path("alunos/{$student['id']}/foto/remover") ?>" onsubmit="return confirm('Deseja realmente remover a foto?');">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="btn btn-outline btn-sm">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Remover Foto
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dados Pessoais -->
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-header">
            <h3 style="margin: 0;">Dados Pessoais</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Nome Completo</label>
                    <div><?= htmlspecialchars($fullName) ?></div>
                </div>
                <div class="info-item">
                    <label>Data de Nascimento</label>
                    <div><?= !empty($student['birth_date']) ? date('d/m/Y', strtotime($student['birth_date'])) : '-' ?></div>
                </div>
                <div class="info-item">
                    <label>Estado Civil</label>
                    <div><?= htmlspecialchars($student['marital_status'] ? ucfirst(str_replace('_', ' ', $student['marital_status'])) : '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Profissão</label>
                    <div><?= htmlspecialchars($student['profession'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Escolaridade</label>
                    <div><?= htmlspecialchars($student['education_level'] ? ucfirst(str_replace('_', ' ', $student['education_level'])) : '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Nacionalidade</label>
                    <div><?= htmlspecialchars($student['nationality'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Atividade Remunerada</label>
                    <div><?= !empty($student['remunerated_activity']) ? 'Sim' : 'Não' ?></div>
                </div>
                <div class="info-item">
                    <label>Local de Nascimento</label>
                    <div>
                        <?php if (!empty($birthCity) || !empty($student['birth_state_uf'])): ?>
                        <?= htmlspecialchars(trim(($birthCity['name'] ?? '') . ' / ' . ($student['birth_state_uf'] ?? ''), ' /') ?: '-') ?>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contato -->
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-header">
            <h3 style="margin: 0;">Contato</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Telefone Principal</label>
                    <div><?= htmlspecialchars($primaryPhone ? ValidationHelper::formatPhone($primaryPhone) : '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Telefone Secundário</label>
                    <div><?= htmlspecialchars($student['phone_secondary'] ? ValidationHelper::formatPhone($student['phone_secondary']) : '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <div><?= htmlspecialchars($student['email'] ?: '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Acesso ao Sistema -->
    <?php if (!empty($userInfo)): ?>
    <div class="card" style="margin-bottom: var(--spacing-md); border-left: 4px solid #007bff;">
        <div class="card-header" style="background-color: #f8f9fa;">
            <h3 style="margin: 0; font-size: var(--font-size-lg); color: #007bff; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Acesso ao Sistema
            </h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Status do Acesso</label>
                    <div>
                        <?php if ($userInfo['status'] === 'ativo'): ?>
                            <span class="badge badge-success">Ativo</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inativo</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Email de Acesso</label>
                    <div><?= htmlspecialchars($userInfo['email']) ?></div>
                </div>
                <div class="info-item">
                    <label>Perfis/Roles</label>
                    <div>
                        <?php if (!empty($userRoles)): ?>
                            <?php foreach ($userRoles as $role): ?>
                                <span class="badge badge-primary"><?= htmlspecialchars($role['role']) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">Nenhum perfil atribuído</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Acesso Criado em</label>
                    <div><?= date('d/m/Y H:i', strtotime($userInfo['created_at'])) ?></div>
                </div>
            </div>
            <?php if (\App\Services\PermissionService::check('usuarios', 'update') || $_SESSION['current_role'] === 'ADMIN'): ?>
            <div style="margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--color-border);">
                <a href="<?= base_path("usuarios/{$userInfo['id']}/editar") ?>" class="btn btn-primary btn-sm">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Gerenciar Acesso
                </a>
                <small class="text-muted" style="display: block; margin-top: var(--spacing-xs);">
                    Você pode gerar senha temporária, link de ativação ou alterar o status do acesso
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (!empty($student['email']) && filter_var($student['email'], FILTER_VALIDATE_EMAIL)): ?>
    <div class="card" style="margin-bottom: var(--spacing-md); border-left: 4px solid #ffc107;">
        <div class="card-header" style="background-color: #fff3cd;">
            <h3 style="margin: 0; font-size: var(--font-size-lg); color: #856404; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Acesso ao Sistema
            </h3>
        </div>
        <div class="card-body">
            <p style="margin: 0 0 var(--spacing-sm) 0; color: #856404;">
                <strong>Este aluno ainda não possui acesso ao sistema.</strong>
            </p>
            <?php if (\App\Services\PermissionService::check('usuarios', 'create') || $_SESSION['current_role'] === 'ADMIN'): ?>
            <a href="<?= base_path('usuarios/novo') ?>?link_type=student&link_id=<?= $student['id'] ?>" class="btn btn-primary btn-sm">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Criar Acesso para este Aluno
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contato de Emergência -->
    <?php if (!empty($student['emergency_contact_name']) || !empty($student['emergency_contact_phone'])): ?>
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-header">
            <h3 style="margin: 0;">Contato de Emergência</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Nome do Contato</label>
                    <div><?= htmlspecialchars($student['emergency_contact_name'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Telefone do Contato</label>
                    <div><?= htmlspecialchars($student['emergency_contact_phone'] ? ValidationHelper::formatPhone($student['emergency_contact_phone']) : '-') ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Endereço -->
    <?php if (!empty($student['street']) || !empty($addressCity)): ?>
    <div class="card" style="margin-bottom: var(--spacing-md);">
        <div class="card-header">
            <h3 style="margin: 0;">Endereço</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>CEP</label>
                    <div><?= htmlspecialchars($student['cep'] ? ValidationHelper::formatCep($student['cep']) : '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Logradouro</label>
                    <div><?= htmlspecialchars($student['street'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Número</label>
                    <div><?= htmlspecialchars($student['number'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Complemento</label>
                    <div><?= htmlspecialchars($student['complement'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Bairro</label>
                    <div><?= htmlspecialchars($student['neighborhood'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Cidade / UF</label>
                    <div>
                        <?php if (!empty($addressCity) || !empty($student['state_uf'])): ?>
                        <?= htmlspecialchars(trim(($addressCity['name'] ?? '') . ' / ' . ($student['state_uf'] ?? ''), ' /') ?: '-') ?>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Observações -->
    <?php if (!empty($student['notes'])): ?>
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">Observações</h3>
        </div>
        <div class="card-body">
            <div><?= nl2br(htmlspecialchars($student['notes'])) ?></div>
        </div>
    </div>
    <?php endif; ?>

<?php elseif ($tab === 'matricula'): ?>
    <?php if (empty($enrollments)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding: 60px 20px;">
                <p class="text-muted">Nenhuma matrícula cadastrada.</p>
                <?php if (\App\Services\PermissionService::check('enrollments', 'create')): ?>
                <a href="<?= base_path("alunos/{$student['id']}/matricular") ?>" class="btn btn-primary mt-3">
                    Nova Matrícula
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($enrollments as $enrollment): ?>
        <div class="card" style="margin-bottom: var(--spacing-md);">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--spacing-sm);">
                    <div>
                        <strong><?= htmlspecialchars($enrollment['service_name']) ?></strong>
                        <span class="text-muted" style="margin-left: var(--spacing-sm);">
                            #<?= $enrollment['id'] ?>
                        </span>
                    </div>
                    <div>
                        <?php
                        $statusLabels = [
                            'ativa' => ['label' => 'Ativa', 'class' => 'badge-success'],
                            'concluida' => ['label' => 'Concluída', 'class' => 'badge-primary'],
                            'cancelada' => ['label' => 'Cancelada', 'class' => 'badge-danger']
                        ];
                        $statusInfo = $statusLabels[$enrollment['status']] ?? ['label' => $enrollment['status'], 'class' => 'badge-secondary'];
                        ?>
                        <span class="badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Preço Base</label>
                        <div>R$ <?= number_format($enrollment['base_price'], 2, ',', '.') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Desconto</label>
                        <div>R$ <?= number_format($enrollment['discount_value'], 2, ',', '.') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Acréscimo</label>
                        <div>R$ <?= number_format($enrollment['extra_value'], 2, ',', '.') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Valor Final</label>
                        <div><strong>R$ <?= number_format($enrollment['final_price'], 2, ',', '.') ?></strong></div>
                    </div>
                    <div class="info-item">
                        <label>Forma de Pagamento</label>
                        <div><?= ucfirst($enrollment['payment_method']) ?></div>
                    </div>
                    <div class="info-item">
                        <label>Status Financeiro</label>
                        <div>
                            <?php
                            $finLabels = [
                                'em_dia' => ['label' => 'Em Dia', 'class' => 'badge-success'],
                                'pendente' => ['label' => 'Pendente', 'class' => 'badge-warning'],
                                'bloqueado' => ['label' => 'Bloqueado', 'class' => 'badge-danger']
                            ];
                            $finInfo = $finLabels[$enrollment['financial_status']] ?? ['label' => $enrollment['financial_status'], 'class' => 'badge-secondary'];
                            ?>
                            <span class="badge <?= $finInfo['class'] ?>"><?= $finInfo['label'] ?></span>
                        </div>
                    </div>
                </div>
                <?php if (\App\Services\PermissionService::check('enrollments', 'update')): ?>
                <div style="margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--color-border);">
                    <a href="<?= base_path("matriculas/{$enrollment['id']}") ?>" class="btn btn-outline btn-sm">
                        Editar Matrícula
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (\App\Services\PermissionService::check('enrollments', 'create')): ?>
        <div style="margin-top: var(--spacing-md);">
            <a href="<?= base_path("alunos/{$student['id']}/matricular") ?>" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nova Matrícula
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($showInstallCta)): ?>
        <div class="card" style="margin-top: var(--spacing-lg); border-left: 4px solid var(--color-primary);">
            <div class="card-body">
                <h4 style="margin-top: 0; margin-bottom: var(--spacing-sm);">Envie o app ao aluno</h4>
                <?php if (!empty($installLinkError)): ?>
                <div class="alert alert-warning" style="margin-bottom: var(--spacing-md);"><?= htmlspecialchars($installLinkError) ?></div>
                <?php endif; ?>
                <p class="text-muted" style="margin-bottom: var(--spacing-md); font-size: var(--font-size-sm);">Link para instalação do app (Android/iPhone).</p>
                <div style="display: flex; flex-wrap: wrap; gap: var(--spacing-sm); align-items: center;">
                    <a href="#" id="pwa-cta-wa" class="btn btn-primary btn-sm" data-phone="<?= htmlspecialchars($studentPhoneForWa ?? '') ?>" data-message="<?= htmlspecialchars($waMessage ?? '') ?>" data-install-url="<?= htmlspecialchars($installUrl ?? '') ?>" <?= empty($hasValidPhone) ? ' style="pointer-events: none; opacity: 0.6;"' : '' ?>>
                        WhatsApp
                    </a>
                    <button type="button" class="btn btn-outline btn-sm" id="pwa-cta-copy" data-install-url="<?= htmlspecialchars($installUrl ?? '') ?>">Copiar link</button>
                    <?php if (empty($hasValidPhone)): ?>
                    <span class="text-muted" style="font-size: var(--font-size-sm);">Aluno sem telefone cadastrado.</span>
                    <?php endif; ?>
                </div>
                <div id="pwa-copy-fallback" style="display: none; margin-top: var(--spacing-sm);">
                    <input type="text" readonly class="form-input" id="pwa-copy-input" value="" style="font-size: 0.85rem;">
                </div>
                <p id="pwa-copy-feedback" style="display: none; margin: var(--spacing-sm) 0 0; color: var(--color-success); font-size: var(--font-size-sm);">Link copiado.</p>
            </div>
        </div>
        <script>
        (function(){
            var waEl = document.getElementById('pwa-cta-wa');
            var copyEl = document.getElementById('pwa-cta-copy');
            if (waEl && waEl.dataset.phone && waEl.dataset.message) {
                waEl.addEventListener('click', function(e){ e.preventDefault(); if (this.dataset.phone) window.open('https://wa.me/' + this.dataset.phone + '?text=' + encodeURIComponent(this.dataset.message), '_blank'); });
            }
            if (copyEl && copyEl.dataset.installUrl) {
                copyEl.addEventListener('click', function(){
                    var u = this.dataset.installUrl;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(u).then(function(){ var fb = document.getElementById('pwa-copy-feedback'); if(fb){ fb.style.display='block'; setTimeout(function(){ fb.style.display='none'; }, 2000); }});
                    } else {
                        var fallback = document.getElementById('pwa-copy-fallback'); var inp = document.getElementById('pwa-copy-input');
                        if (fallback && inp) { inp.value = u; fallback.style.display = 'block'; inp.select(); document.execCommand('copy'); }
                    }
                });
            }
        })();
        </script>
        <?php endif; ?>
    <?php endif; ?>

<?php elseif ($tab === 'documentos'): ?>
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">Documentos</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>CPF</label>
                    <div><?= htmlspecialchars(ValidationHelper::formatCpf($student['cpf'])) ?></div>
                </div>
                <div class="info-item">
                    <label>RG</label>
                    <div><?= htmlspecialchars($student['rg_number'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Órgão Emissor</label>
                    <div><?= htmlspecialchars($student['rg_issuer'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>UF do RG</label>
                    <div><?= htmlspecialchars($student['rg_uf'] ?: '-') ?></div>
                </div>
                <div class="info-item">
                    <label>Data de Emissão do RG</label>
                    <div><?= !empty($student['rg_issue_date']) ? date('d/m/Y', strtotime($student['rg_issue_date'])) : '-' ?></div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'progresso'): ?>
    <?php if (empty($enrollments)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding: 60px 20px;">
                <p class="text-muted">Nenhuma matrícula cadastrada para exibir progresso.</p>
            </div>
        </div>
    <?php else: ?>
        <?php if (count($enrollments) > 1): ?>
        <div class="card" style="margin-bottom: var(--spacing-md);">
            <div class="card-body">
                <label class="form-label">Selecione a matrícula:</label>
                <select class="form-select" onchange="window.location.href='<?= base_path("alunos/{$student['id']}?tab=progresso&enrollment_id=") ?>' + this.value">
                    <?php foreach ($enrollments as $enr): ?>
                    <option value="<?= $enr['id'] ?>" <?= ($enrollment_id ?? $enrollments[0]['id']) == $enr['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($enr['service_name']) ?> - #<?= $enr['id'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom: var(--spacing-lg);">Etapas do Processo</h3>
                
                <?php
                $studentStepsMap = [];
                foreach ($student_steps ?? [] as $ss) {
                    $studentStepsMap[$ss['step_id']] = $ss;
                }
                ?>

                <div class="timeline">
                    <?php foreach ($steps ?? [] as $step): ?>
                    <?php 
                    $studentStep = $studentStepsMap[$step['id']] ?? null;
                    $isCompleted = $studentStep && $studentStep['status'] === 'concluida';
                    ?>
                    <div class="timeline-item <?= $isCompleted ? 'completed' : '' ?>">
                        <div class="timeline-marker">
                            <?php if ($isCompleted): ?>
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            <?php else: ?>
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="timeline-content">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--spacing-sm);">
                                <div>
                                    <h4 style="margin: 0 0 var(--spacing-xs) 0;"><?= htmlspecialchars($step['name']) ?></h4>
                                    <?php if ($step['description']): ?>
                                    <p class="text-muted" style="margin: 0; font-size: var(--font-size-sm);"><?= htmlspecialchars($step['description']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($isCompleted && $studentStep['validated_at']): ?>
                                    <p class="text-muted" style="margin: var(--spacing-xs) 0 0 0; font-size: var(--font-size-xs);">
                                        Concluído em <?= date('d/m/Y H:i', strtotime($studentStep['validated_at'])) ?>
                                        <?php if ($studentStep['source'] === 'cfc'): ?>
                                            (CFC)
                                        <?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($studentStep && \App\Services\PermissionService::check('steps', 'update')): ?>
                                <form method="POST" action="<?= base_path("student-steps/{$studentStep['id']}/toggle") ?>" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <button type="submit" class="btn btn-sm <?= $isCompleted ? 'btn-outline' : 'btn-primary' ?>">
                                        <?= $isCompleted ? 'Desmarcar' : 'Marcar como concluída' ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($tab === 'historico'): ?>
    <?php if (empty($history)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding: 60px 20px;">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--color-text-muted); margin: 0 auto 20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-muted" style="font-size: var(--font-size-lg);">Nenhuma atividade registrada até o momento.</p>
            </div>
        </div>
    <?php else: ?>
        <?php if (\App\Services\PermissionService::check('alunos', 'update')): ?>
        <div class="card" style="margin-bottom: var(--spacing-md);">
            <div class="card-body">
                <form method="POST" action="<?= base_path("alunos/{$student['id']}/historico/observacao") ?>" style="display: flex; gap: var(--spacing-sm); align-items: flex-start;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div style="flex: 1;">
                        <label class="form-label" for="observation" style="font-size: var(--font-size-sm); margin-bottom: var(--spacing-xs);">Adicionar observação</label>
                        <input 
                            type="text" 
                            id="observation" 
                            name="observation" 
                            class="form-input" 
                            placeholder="Digite uma observação curta (máx. 200 caracteres)" 
                            maxlength="200"
                            required
                            style="font-size: var(--font-size-sm);"
                        >
                    </div>
                    <div style="padding-top: 24px;">
                        <button type="submit" class="btn btn-outline btn-sm">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Adicionar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0;">Histórico de Atividades</h3>
            </div>
            <div class="card-body">
                <div class="history-timeline">
                    <?php foreach ($history as $item): ?>
                    <?php
                    // Definir classes de prioridade visual
                    $priorityClasses = [
                        'financeiro' => 'history-priority-high',
                        'agenda' => 'history-priority-high',
                        'detran' => 'history-priority-high',
                        'matricula' => 'history-priority-medium',
                        'cadastro' => 'history-priority-low',
                        'observacao' => 'history-priority-low',
                        'administrativo' => 'history-priority-low'
                    ];
                    $priorityClass = $priorityClasses[$item['type']] ?? 'history-priority-medium';
                    $isGrouped = isset($item['is_grouped']) && $item['is_grouped'];
                    ?>
                    <div class="history-item <?= $priorityClass ?>">
                        <div class="history-icon">
                            <?php
                            $iconMap = [
                                'cadastro' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
                                'matricula' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
                                'financeiro' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                                'agenda' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                                'detran' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                                'observacao' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
                                'administrativo' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'
                            ];
                            $icon = $iconMap[$item['type']] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>';
                            ?>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?= $icon ?>
                            </svg>
                        </div>
                        <div class="history-content">
                            <div class="history-description">
                                <?= htmlspecialchars($item['description']) ?>
                                <?php if ($isGrouped && isset($item['group_count'])): ?>
                                <span class="history-group-badge"><?= $item['group_count'] ?> alterações</span>
                                <?php endif; ?>
                            </div>
                            <div class="history-meta">
                                <span class="history-date">
                                    <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                                </span>
                                <?php if (!empty($item['created_by_name'])): ?>
                                <span class="history-separator">•</span>
                                <span class="history-user">
                                    <?= htmlspecialchars($item['created_by_name']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<style>
.tabs {
    display: flex;
    gap: var(--spacing-xs);
    margin-bottom: var(--spacing-lg);
    border-bottom: 2px solid var(--color-border);
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.tab-item {
    padding: var(--spacing-sm) var(--spacing-md);
    text-decoration: none;
    color: var(--color-text-muted);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all var(--transition-base);
    white-space: nowrap;
}

.tab-item:hover {
    color: var(--color-primary);
}

.tab-item.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    font-weight: var(--font-weight-medium);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-md);
}

.info-item label {
    display: block;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
    margin-bottom: var(--spacing-xs);
}

.info-item div {
    color: var(--color-text);
    font-size: var(--font-size-base);
}

.student-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    overflow: hidden;
    border: 2px solid var(--color-border);
    flex-shrink: 0;
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: var(--color-bg-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
}

.timeline {
    position: relative;
    padding-left: var(--spacing-lg);
}

.timeline-item {
    position: relative;
    padding-bottom: var(--spacing-lg);
    padding-left: var(--spacing-lg);
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 9px;
    top: 32px;
    bottom: -var(--spacing-lg);
    width: 2px;
    background: var(--color-border);
}

.timeline-item.completed:not(:last-child)::before {
    background: var(--color-success);
}

.timeline-marker {
    position: absolute;
    left: 0;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--color-bg);
    border: 2px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
}

.timeline-item.completed .timeline-marker {
    background: var(--color-success);
    border-color: var(--color-success);
    color: white;
}

.timeline-content {
    min-height: 40px;
}

.btn-sm {
    padding: var(--spacing-xs) var(--spacing-sm);
    font-size: var(--font-size-sm);
}

.history-timeline {
    position: relative;
    padding-left: var(--spacing-lg);
}

.history-item {
    position: relative;
    padding-bottom: var(--spacing-lg);
    padding-left: var(--spacing-lg);
}

.history-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 9px;
    top: 32px;
    bottom: -var(--spacing-lg);
    width: 2px;
    background: var(--color-border);
}

.history-icon {
    position: absolute;
    left: 0;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--color-bg);
    border: 2px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    flex-shrink: 0;
}

.history-content {
    min-height: 40px;
}

.history-description {
    color: var(--color-text);
    font-size: var(--font-size-base);
    margin-bottom: var(--spacing-xs);
    line-height: 1.5;
}

.history-meta {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
}

.history-date {
    font-weight: var(--font-weight-medium);
}

.history-separator {
    color: var(--color-text-muted);
}

.history-user {
    color: var(--color-text-muted);
}

/* Prioridade visual */
.history-priority-high {
    border-left: 3px solid var(--color-primary);
    padding-left: calc(var(--spacing-lg) - 3px);
}

.history-priority-high .history-icon {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: white;
}

.history-priority-medium {
    border-left: 2px solid var(--color-border);
    padding-left: calc(var(--spacing-lg) - 2px);
}

.history-priority-low {
    opacity: 0.85;
}

.history-priority-low .history-icon {
    background: var(--color-bg-light);
    border-color: var(--color-border);
    color: var(--color-text-muted);
}

.history-group-badge {
    display: inline-block;
    margin-left: var(--spacing-xs);
    padding: 2px 6px;
    background: var(--color-bg-light);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
    font-weight: var(--font-weight-medium);
}
</style>
