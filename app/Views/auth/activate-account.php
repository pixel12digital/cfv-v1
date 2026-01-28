<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= base_path('/') ?>">
    <title>Definir Senha - Sistema CFC</title>
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/components.css') ?>">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            padding: var(--spacing-md);
        }
        
        .auth-card {
            width: 100%;
            max-width: 400px;
            background-color: var(--color-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            padding: var(--spacing-2xl);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .auth-header h1 {
            margin: 0 0 var(--spacing-sm) 0;
            color: var(--color-text);
            font-size: var(--font-size-2xl);
        }
        
        .auth-form {
            margin-bottom: var(--spacing-lg);
        }
        
        .auth-footer {
            text-align: center;
            margin-top: var(--spacing-lg);
        }
        
        .auth-footer a {
            color: var(--color-primary);
            text-decoration: none;
        }
        
        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Definir Senha</h1>
                <p class="text-muted">Crie ou redefina sua senha de acesso</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <form method="POST" action="<?= base_path('/ativar-conta') ?>" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

                <div class="form-group">
                    <label class="form-label" for="email">E-mail</label>
                    <input 
                        type="email" 
                        id="email" 
                        class="form-input" 
                        value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                        disabled
                    >
                    <small class="form-text">Este é o e-mail associado à sua conta.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">Nova Senha <span class="text-danger">*</span></label>
                    <input 
                        type="password" 
                        name="new_password" 
                        id="new_password" 
                        class="form-input" 
                        required
                        minlength="8"
                        autofocus
                        placeholder="Mínimo 8 caracteres"
                    >
                    <small class="form-text">A senha deve ter no mínimo 8 caracteres.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password_confirm">Confirmar Nova Senha <span class="text-danger">*</span></label>
                    <input 
                        type="password" 
                        name="new_password_confirm" 
                        id="new_password_confirm" 
                        class="form-input" 
                        required
                        minlength="8"
                        placeholder="Digite a senha novamente"
                    >
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Salvar Senha
                    </button>
                </div>
            </form>

            <div class="auth-footer">
                <p>
                    <a href="<?= base_path('/login') ?>">Voltar para o login</a>
                </p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.auth-form');
        const password = document.getElementById('new_password');
        const passwordConfirm = document.getElementById('new_password_confirm');
        
        form.addEventListener('submit', function(e) {
            if (password.value !== passwordConfirm.value) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                passwordConfirm.focus();
            }
        });
    });
    </script>
</body>
</html>
