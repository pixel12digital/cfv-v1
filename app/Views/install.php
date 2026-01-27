<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#023A8D">
    <title>Instalar app do aluno</title>
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/components.css') ?>">
    <style>
        .install-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--spacing-md); background: linear-gradient(135deg, var(--color-primary, #023A8D) 0%, #03408a 100%); }
        .install-card { width: 100%; max-width: 420px; background: var(--color-bg, #fff); border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); padding: var(--spacing-2xl); }
        .install-title { font-size: var(--font-size-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: var(--spacing-md); text-align: center; }
        .install-block { margin-top: var(--spacing-lg); padding: var(--spacing-md); background: var(--color-bg-light, #f8f9fa); border-radius: var(--radius-md); border: 1px solid var(--color-border); }
        .install-block h4 { margin: 0 0 var(--spacing-sm); font-size: var(--font-size-md); }
        .install-block p { margin: 0; font-size: var(--font-size-sm); color: var(--color-text-muted); }
        .install-cta { display: block; width: 100%; margin-top: var(--spacing-md); padding: var(--spacing-md); text-align: center; border-radius: var(--radius-md); font-weight: 600; text-decoration: none; }
        .install-cta.primary { background: var(--color-primary); color: #fff; border: none; }
        .install-cta.primary:hover { opacity: 0.95; color: #fff; }
        .install-cta.outline { background: transparent; border: 2px solid var(--color-primary); color: var(--color-primary); }
        .install-hidden { display: none !important; }
    </style>
</head>
<body class="install-page">
    <div class="install-card">
        <h1 class="install-title">Instalar app do aluno</h1>
        <p style="text-align: center; color: var(--color-text-muted); margin-bottom: 0;">Use o app para acompanhar aulas, financeiro e mais.</p>

        <!-- Estado: Já instalado → CTA principal (portal ou login) -->
        <div id="install-state-installed" class="install-block install-hidden">
            <h4>App já instalado</h4>
            <a href="<?= htmlspecialchars($primaryCtaUrl ?? $loginUrl ?? base_url('login')) ?>" class="install-cta primary"><?= htmlspecialchars($primaryCtaLabel ?? 'Abrir app') ?></a>
        </div>

        <!-- Estado: Android/Chrome → botão "Instalar app" (quando beforeinstallprompt) -->
        <div id="install-state-android" class="install-block install-hidden">
            <h4>Android / Chrome</h4>
            <p>Toque no botão abaixo para instalar.</p>
            <button type="button" id="install-btn-android" class="install-cta primary" style="cursor: pointer; border: none;">Instalar app</button>
            <p style="margin-top: var(--spacing-sm); font-size: 0.8rem;">Se o botão não aparecer, use o menu ⋮ do navegador → “Instalar app”.</p>
            <a href="<?= htmlspecialchars($primaryCtaUrl ?? $loginUrl ?? base_url('login')) ?>" class="install-cta outline" style="margin-top: var(--spacing-sm); display: block;"><?= htmlspecialchars($primaryCtaLabel ?? 'Abrir app') ?></a>
        </div>

        <!-- Estado: iOS / Safari -->
        <div id="install-state-ios" class="install-block install-hidden">
            <h4>iPhone / Safari</h4>
            <p>Toque em <strong>Compartilhar</strong> (ícone na barra) e depois em <strong>Adicionar à Tela de Início</strong>.</p>
            <a href="<?= htmlspecialchars($primaryCtaUrl ?? $loginUrl ?? base_url('login')) ?>" class="install-cta outline"><?= htmlspecialchars($primaryCtaLabel ?? 'Abrir no Safari') ?></a>
        </div>

        <!-- Estado: fallback (desktop/outros) ou quando não há deferredPrompt -->
        <div id="install-state-fallback" class="install-block install-hidden">
            <h4>Abrir no celular</h4>
            <p>Abra o link no Chrome (Android) ou Safari (iPhone) para instalar.</p>
            <a href="<?= htmlspecialchars($primaryCtaUrl ?? $loginUrl ?? base_url('login')) ?>" class="install-cta primary"><?= htmlspecialchars($primaryCtaLabel ?? 'Abrir app') ?></a>
        </div>
    </div>

    <script>
(function() {
    var loginUrl = <?= json_encode($loginUrl ?? '') ?>;
    var stateInstalled = document.getElementById('install-state-installed');
    var stateAndroid = document.getElementById('install-state-android');
    var stateIos = document.getElementById('install-state-ios');
    var stateFallback = document.getElementById('install-state-fallback');
    var btnAndroid = document.getElementById('install-btn-android');

    function show(el) { el.classList.remove('install-hidden'); }
    function hide(el) { el.classList.add('install-hidden'); }

    function isStandalone() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
               (window.navigator.standalone === true) ||
               (document.referrer && document.referrer.indexOf('android-app://') === 0);
    }
    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }

    var deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
    });

    function chooseState() {
        if (isStandalone()) {
            show(stateInstalled);
            hide(stateAndroid); hide(stateIos); hide(stateFallback);
            return;
        }
        if (isIOS()) {
            show(stateIos);
            hide(stateAndroid); hide(stateInstalled); hide(stateFallback);
            return;
        }
        if (deferredPrompt) {
            show(stateAndroid);
            hide(stateIos); hide(stateInstalled); hide(stateFallback);
            return;
        }
        show(stateFallback);
        hide(stateAndroid); hide(stateIos); hide(stateInstalled);
    }

    if (btnAndroid) {
        btnAndroid.addEventListener('click', function() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choice) {
                    if (choice.outcome === 'accepted') chooseState();
                    deferredPrompt = null;
                });
            }
        });
    }

    chooseState();
    window.addEventListener('beforeinstallprompt', chooseState);
})();
    </script>
</body>
</html>
