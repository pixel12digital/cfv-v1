<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link inválido - Sistema CFC</title>
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/components.css') ?>">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--color-gray-50); }
        .auth-container { width: 100%; max-width: 400px; padding: var(--spacing-lg); }
        .auth-card { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); padding: var(--spacing-xl); }
        .auth-logo { text-align: center; margin-bottom: var(--spacing-xl); font-size: 24px; font-weight: bold; color: var(--color-primary); }
        .fallback-msg { margin-bottom: var(--spacing-lg); color: var(--color-text-muted); }
        .fallback-actions { display: flex; flex-direction: column; gap: var(--spacing-md); }
        .fallback-actions .btn { width: 100%; display: block; text-align: center; text-decoration: none; }
        .fallback-hint { margin-top: var(--spacing-lg); font-size: var(--font-size-sm); color: var(--color-text-muted); }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">CFC Sistema</div>
            <h2 style="margin-bottom: var(--spacing-md);">Link de primeiro acesso</h2>
            <p class="fallback-msg"><?= htmlspecialchars($message ?? 'Este link não é válido.') ?></p>
            <div class="fallback-actions">
                <a href="<?= htmlspecialchars($loginUrl ?? base_url('login')) ?>" class="btn btn-primary">Ir para login</a>
                <a href="<?= htmlspecialchars($forgotPasswordUrl ?? base_url('forgot-password')) ?>" class="btn btn-outline">Esqueci minha senha</a>
            </div>
            <p class="fallback-hint">Peça para a secretaria reenviar o link de ativação, se necessário.</p>
        </div>
    </div>
</body>
</html>
