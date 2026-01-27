<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CFC Sistema</title>
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/components.css') ?>">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            padding: var(--spacing-md);
        }
        
        .login-card {
            width: 100%;
            max-width: 400px;
            background-color: var(--color-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            padding: var(--spacing-2xl);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .login-logo {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary);
            margin-bottom: var(--spacing-sm);
        }
        
        .login-logo-img {
            max-width: 140px;
            max-height: 140px;
            width: auto;
            height: auto;
            margin: 0 auto var(--spacing-sm);
            display: block;
        }
        
        .login-title {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-semibold);
            color: var(--color-text);
            margin-bottom: var(--spacing-xs);
        }
        
        .login-subtitle {
            color: var(--color-text-muted);
            font-size: var(--font-size-sm);
        }
        /* Acessibilidade: "Esqueci minha senha" legível (contraste WCAG AA em fundo claro) */
        .login-forgot-link {
            color: #034ba8;
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-decoration: underline;
        }
        .login-forgot-link:hover { color: #023A8D; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <?php 
                $logoUrl = $logoUrl ?? null;
                if (!empty($logoUrl)): 
                ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo do CFC" class="login-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="login-logo" style="display: none;">CFC</div>
                <?php else: ?>
                    <div class="login-logo">CFC</div>
                <?php endif; ?>
                <h1 class="login-title">Sistema de Gestão</h1>
                <p class="login-subtitle">Acesse sua conta</p>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form method="POST" action="<?= base_url('/login') ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input" 
                           required 
                           autofocus 
                           placeholder="seu@email.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Senha</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           required 
                           placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: var(--spacing-md);">
                    Entrar
                </button>
            </form>
            
            <div style="text-align: center; margin-top: var(--spacing-md);">
                <a href="<?= base_url('/forgot-password') ?>" class="login-forgot-link">
                    Esqueci minha senha
                </a>
            </div>
        </div>
    </div>
</body>
</html>
