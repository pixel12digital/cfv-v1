/**
 * Overlay "Instalar App" no dashboard do aluno (legado + mobile-first).
 * - Captura beforeinstallprompt; throttle 1x/dia (localStorage); CTA fixo no header.
 * - Não altera SW, manifest, auth ou primeiro acesso.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'pwa_overlay_dismissed_date';
    var deferredPrompt = null;
    var overlayEl = null;
    var headerCtaEl = null;
    var bellEl = null;
    var installUrl = typeof window.__PWA_INSTALL_URL === 'string' ? window.__PWA_INSTALL_URL : '/install';

    function isPwaInstalled() {
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) return true;
        if (window.navigator.standalone === true) return true;
        if (document.referrer && document.referrer.indexOf('android-app://') === 0) return true;
        return false;
    }

    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }

    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function wasDismissedToday() {
        try {
            var d = localStorage.getItem(STORAGE_KEY);
            return d === todayStr();
        } catch (e) {
            return false;
        }
    }

    function setDismissedToday() {
        try {
            localStorage.setItem(STORAGE_KEY, todayStr());
        } catch (e) {}
    }

    function shouldShowOverlay() {
        if (isPwaInstalled()) return false;
        if (wasDismissedToday()) return false;
        return true;
    }

    function showOverlay() {
        if (!overlayEl) return;
        overlayEl.classList.remove('pwa-overlay-hidden');
        overlayEl.setAttribute('aria-hidden', 'false');
        var primary = overlayEl.querySelector('.pwa-overlay-btn-primary');
        if (primary) primary.focus();
    }

    function hideOverlay() {
        if (!overlayEl) return;
        overlayEl.classList.add('pwa-overlay-hidden');
        overlayEl.setAttribute('aria-hidden', 'true');
    }

    function updateHeaderCta(visible) {
        if (!headerCtaEl) return;
        if (visible) {
            headerCtaEl.classList.remove('d-none');
            headerCtaEl.setAttribute('aria-hidden', 'false');
        } else {
            headerCtaEl.classList.add('d-none');
            headerCtaEl.setAttribute('aria-hidden', 'true');
        }
    }

    function updateBell(visible) {
        if (!bellEl) return;
        if (visible) {
            bellEl.classList.remove('d-none');
            bellEl.setAttribute('aria-hidden', 'false');
        } else {
            bellEl.classList.add('d-none');
            bellEl.setAttribute('aria-hidden', 'true');
        }
    }

    function setStateInstallable() {
        var blockInstal = overlayEl && overlayEl.querySelector('.pwa-overlay-state-installable');
        var blockNotInstallable = overlayEl && overlayEl.querySelector('.pwa-overlay-state-not-installable');
        if (blockInstal) blockInstal.classList.remove('d-none');
        if (blockNotInstallable) blockNotInstallable.classList.add('d-none');
    }

    function setStateNotInstallable() {
        var blockInstal = overlayEl && overlayEl.querySelector('.pwa-overlay-state-installable');
        var blockNotInstallable = overlayEl && overlayEl.querySelector('.pwa-overlay-state-not-installable');
        if (blockInstal) blockInstal.classList.add('d-none');
        if (blockNotInstallable) {
            blockNotInstallable.classList.remove('d-none');
            var iosOnly = blockNotInstallable.querySelector('.pwa-overlay-ios-only');
            var androidOnly = blockNotInstallable.querySelector('.pwa-overlay-android-only');
            if (iosOnly && androidOnly) {
                if (isIOS()) {
                    iosOnly.classList.remove('d-none');
                    androidOnly.classList.add('d-none');
                } else {
                    iosOnly.classList.add('d-none');
                    androidOnly.classList.remove('d-none');
                }
            }
        }
    }

    function setupOverlay() {
        overlayEl = document.getElementById('pwa-install-overlay');
        headerCtaEl = document.getElementById('pwa-install-header-cta');
        bellEl = document.getElementById('pwa-install-bell');
        if (!overlayEl) return;

        if (isPwaInstalled()) {
            hideOverlay();
            updateHeaderCta(false);
            updateBell(false);
            if (window.__PWA_DEBUG) {
                console.log('[PWA overlay] installed=1 dismissedToday=' + String(wasDismissedToday()) + ' deferred=' + (deferredPrompt ? '1' : '0') + ' overlay=hide bell=hide cta=hide');
            }
            return;
        }

        updateHeaderCta(true);
        updateBell(true);

        if (deferredPrompt) {
            setStateInstallable();
        } else {
            setStateNotInstallable();
        }

        if (shouldShowOverlay()) {
            showOverlay();
        } else {
            hideOverlay();
        }

        if (window.__PWA_DEBUG) {
            var ov = overlayEl && !overlayEl.classList.contains('pwa-overlay-hidden');
            var bellVis = bellEl && !bellEl.classList.contains('d-none');
            var ctaVis = headerCtaEl && !headerCtaEl.classList.contains('d-none');
            var msg = '[PWA overlay] installed=0 dismissedToday=' + String(wasDismissedToday()) + ' deferred=' + (deferredPrompt ? '1' : '0') + ' overlay=' + (ov ? 'show' : 'hide') + ' bell=' + (bellVis ? 'show' : 'hide') + ' cta=' + (ctaVis ? 'show' : 'hide');
            console.log(msg);
        }

        overlayEl.querySelectorAll('.pwa-overlay-btn-dismiss').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setDismissedToday();
                hideOverlay();
            });
        });

        var btnInstall = overlayEl.querySelector('.pwa-overlay-btn-install');
        if (btnInstall) {
            btnInstall.addEventListener('click', function () {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function (choice) {
                        if (choice.outcome === 'accepted') {
                            deferredPrompt = null;
                            hideOverlay();
                            updateHeaderCta(false);
                            updateBell(false);
                        }
                    });
                }
            });
        }

        var btnVerInstrucoes = overlayEl.querySelector('.pwa-overlay-btn-ver-instrucoes');
        if (btnVerInstrucoes) {
            btnVerInstrucoes.addEventListener('click', function () {
                window.location.href = installUrl;
            });
        }

        overlayEl.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !overlayEl.classList.contains('pwa-overlay-hidden')) {
                setDismissedToday();
                hideOverlay();
            }
        });

        if (headerCtaEl) {
            headerCtaEl.addEventListener('click', function (e) {
                e.preventDefault();
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function (choice) {
                        if (choice.outcome === 'accepted') {
                            deferredPrompt = null;
                            updateHeaderCta(false);
                            updateBell(false);
                        }
                    });
                } else {
                    window.location.href = installUrl;
                }
            });
        }

        var bellItem = document.getElementById('pwa-install-bell-item');
        if (bellItem) {
            bellItem.addEventListener('click', function (e) {
                e.preventDefault();
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function (choice) {
                        if (choice.outcome === 'accepted') {
                            deferredPrompt = null;
                            updateHeaderCta(false);
                            updateBell(false);
                        }
                    });
                } else {
                    window.location.href = installUrl;
                }
            });
        }

        var bellDismiss = document.getElementById('pwa-install-bell-dismiss');
        if (bellDismiss) {
            bellDismiss.addEventListener('click', function () {
                setDismissedToday();
                hideOverlay();
            });
        }
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        window.__deferredPrompt = e;
        if (overlayEl && !isPwaInstalled()) {
            setStateInstallable();
            if (shouldShowOverlay()) showOverlay();
        }
        updateHeaderCta(true);
        updateBell(true);
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        window.__deferredPrompt = null;
        hideOverlay();
        updateHeaderCta(false);
        updateBell(false);
        setDismissedToday();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupOverlay);
    } else {
        setupOverlay();
    }
})();
