<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Definir senha - Primeiro acesso</title>
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/layout.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/utilities.css') ?>">
    <style>
        /* Acessibilidade: título, labels e texto legíveis (WCAG AA). Em dark: fundo da página E do card escuros para evitar “texto claro em fundo claro”. */
        .define-password-page { --dp-title-color: #1a1a1a; --dp-body-color: #374151; --dp-hint-color: #4b5563; --dp-bg: #f9fafb; --dp-card-bg: #fff; --dp-input-bg: #fff; --dp-input-border: #d1d5db; --dp-input-color: #1a1a1a; }
        @media (prefers-color-scheme: dark) {
            .define-password-page {
                --dp-title-color: #f3f4f6; --dp-body-color: #d1d5db; --dp-hint-color: #9ca3af;
                --dp-bg: #111827; --dp-card-bg: #1f2937; --dp-input-bg: #374151; --dp-input-border: #4b5563; --dp-input-color: #f9fafb;
            }
            .define-password-page { background: var(--dp-bg) !important; }
            .define-password-page .auth-card { background: var(--dp-card-bg) !important; border-color: #374151; }
            .define-password-page .form-input { background: var(--dp-input-bg) !important; border-color: var(--dp-input-border) !important; color: var(--dp-input-color) !important; }
        }
        .define-password-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--dp-bg); }
        .define-password-page .auth-container { width: 100%; max-width: 400px; padding: var(--spacing-lg); }
        .define-password-page .auth-card { background: var(--dp-card-bg); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); padding: var(--spacing-xl); }
        .define-password-page .auth-logo { text-align: center; margin-bottom: var(--spacing-xl); font-size: 24px; font-weight: bold; color: var(--color-primary); }
        .define-password-page .auth-title { margin-bottom: var(--spacing-md); color: var(--dp-title-color); font-size: 1.35rem; font-weight: 600; }
        .define-password-page .auth-intro { margin-bottom: var(--spacing-lg); color: var(--dp-body-color); font-size: 1rem; line-height: 1.5; }
        .define-password-page .form-label { color: var(--dp-title-color) !important; font-weight: 500 !important; }
        .define-password-page .form-text { color: var(--dp-hint-color) !important; }
        .define-password-page .text-link { color: var(--color-primary); }
    </style>
</head>
<body class="define-password-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">CFC Sistema</div>
            <h2 class="auth-title">Definir sua senha</h2>
            <p class="auth-intro">
                Olá, <strong><?= htmlspecialchars($user['nome'] ?? '') ?></strong>. Defina sua senha de acesso abaixo.
            </p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger" style="margin-bottom: var(--spacing-md);">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST" action="<?= base_path('define-password') ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="form-group">
                    <label class="form-label" for="new_password">Nova Senha</label>
                    <input type="password" name="new_password" id="new_password" class="form-input" required minlength="8" autofocus placeholder="Mínimo 8 caracteres">
                    <small class="form-text">A senha deve ter no mínimo 8 caracteres.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_password_confirm">Confirmar Nova Senha</label>
                    <input type="password" name="new_password_confirm" id="new_password_confirm" class="form-input" required minlength="8" placeholder="Digite a senha novamente">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: var(--spacing-md);">Definir senha e continuar</button>
            </form>
            <div style="text-align: center;">
                <a href="<?= base_path('login') ?>" class="text-link">Voltar para login</a>
            </div>
        </div>
    </div>
</body>
</html>
