<div class="page-header">
    <div class="page-header-content">
        <div>
            <h1>Gerenciamento de Usuários</h1>
            <p class="text-muted">Central de Acessos - Controle de identidades e credenciais</p>
        </div>
        <?php if (in_array($_SESSION['current_role'] ?? '', ['ADMIN', 'SECRETARIA'])): ?>
        <a href="<?= base_path('usuarios/novo') ?>" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Criar Acesso
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($studentsWithoutAccess) || !empty($instructorsWithoutAccess)): ?>
<div class="card" style="margin-bottom: var(--spacing-md); background-color: #fff3cd; border-color: #ffc107;">
    <div class="card-header" style="background-color: #ffc107; color: #000;">
        <h3 style="margin: 0; font-size: var(--font-size-lg); display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Pendências de Acesso
        </h3>
    </div>
    <div class="card-body">
        <p style="margin-bottom: var(--spacing-md);">
            <strong>Alunos e instrutores sem acesso ao sistema:</strong> Estes cadastros existem, mas não possuem credenciais de login. 
            Você pode criar acesso vinculado clicando em "Criar Acesso" abaixo.
        </p>
        
        <?php if (!empty($studentsWithoutAccess)): ?>
        <div style="margin-bottom: var(--spacing-md);">
            <h4 style="margin-bottom: var(--spacing-sm);">Alunos sem acesso (<?= count($studentsWithoutAccess) ?>)</h4>
            <table class="table" style="background: white;">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>E-mail</th>
                        <th style="width: 150px;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentsWithoutAccess as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['full_name'] ?: $student['name']) ?></td>
                        <td><?= htmlspecialchars($student['cpf']) ?></td>
                        <td><?= htmlspecialchars($student['email']) ?></td>
                        <td>
                            <form method="POST" action="<?= base_path('usuarios/criar-acesso-aluno') ?>" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Criar Acesso
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($instructorsWithoutAccess)): ?>
        <div>
            <h4 style="margin-bottom: var(--spacing-sm);">Instrutores sem acesso (<?= count($instructorsWithoutAccess) ?>)</h4>
            <table class="table" style="background: white;">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>E-mail</th>
                        <th style="width: 150px;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instructorsWithoutAccess as $instructor): ?>
                    <tr>
                        <td><?= htmlspecialchars($instructor['name']) ?></td>
                        <td><?= htmlspecialchars($instructor['cpf']) ?></td>
                        <td><?= htmlspecialchars($instructor['email']) ?></td>
                        <td>
                            <form method="POST" action="<?= base_path('usuarios/criar-acesso-instrutor') ?>" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="instructor_id" value="<?= $instructor['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Criar Acesso
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <div class="card">
        <div class="card-body text-center" style="padding: 60px 20px;">
            <p class="text-muted">Nenhum usuário cadastrado ainda.</p>
            <?php if (in_array($_SESSION['current_role'] ?? '', ['ADMIN', 'SECRETARIA'])): ?>
            <a href="<?= base_path('usuarios/novo') ?>" class="btn btn-primary mt-3">
                Criar primeiro acesso
            </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Vínculo</th>
                        <th>Status</th>
                        <th style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($user['nome']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php 
                            $roles = $user['roles_array'] ?? [];
                            foreach ($roles as $role): 
                            ?>
                                <span class="badge badge-primary"><?= htmlspecialchars($role) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php if ($user['instructor_id']): ?>
                                <span class="text-muted">Instrutor: <?= htmlspecialchars($user['instructor_name']) ?></span>
                            <?php elseif ($user['student_id']): ?>
                                <span class="text-muted">Aluno: <?= htmlspecialchars($user['student_full_name'] ?: $user['student_name']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Administrativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['status'] === 'ativo'): ?>
                                <span class="badge badge-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <?php 
                                $canEditUser = ($_SESSION['current_role'] ?? '') === 'ADMIN' 
                                    || (($_SESSION['current_role'] ?? '') === 'SECRETARIA' && !in_array('ADMIN', $user['roles_array'] ?? []));
                                if ($canEditUser): ?>
                                <a href="<?= base_path("usuarios/{$user['id']}/editar") ?>" class="btn-icon" title="Editar">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <?php endif; ?>
                                <?php if (($_SESSION['current_role'] ?? '') === 'ADMIN' && $user['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                                <form method="POST" action="<?= base_path("usuarios/{$user['id']}/excluir") ?>" style="display: inline-flex; margin: 0; padding: 0;" onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.');">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Excluir">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<style>
.table-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid var(--color-border, #e0e0e0);
    background: transparent;
    color: var(--color-text, #333);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-icon:hover {
    background: var(--color-bg-light, #f5f5f5);
    border-color: var(--color-primary, #007bff);
    color: var(--color-primary, #007bff);
}

.btn-icon-danger {
    color: var(--color-danger, #dc3545);
    border-color: var(--color-border, #e0e0e0);
}

.btn-icon-danger:hover {
    background: #fee;
    border-color: var(--color-danger, #dc3545);
    color: var(--color-danger, #dc3545);
}

.table-actions form {
    display: inline-flex;
    margin: 0;
    padding: 0;
}

.table-actions button {
    margin: 0;
    font-family: inherit;
}
</style>
