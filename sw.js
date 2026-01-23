// Service Worker - CFC Sistema PWA Fase 1
// Estratégia: Cache-first para assets estáticos, Network-first para HTML/API

const CACHE_NAME = 'cfc-v1';
const CACHE_VERSION = '1.0.0';

// Assets core para cache (app-shell)
// Paths relativos - funcionam em dev e produção
// O service worker resolve paths relativos à sua própria localização
const CORE_ASSETS = [
  './assets/css/tokens.css',
  './assets/css/components.css',
  './assets/css/layout.css',
  './assets/css/utilities.css',
  './assets/js/app.js'
  // Removido manifest.json e ícones - não são essenciais para cache offline
  // Manifest dinâmico: /public_html/pwa-manifest.php
  // Ícones podem estar em /public_html/icons/ ou /icons/ dependendo do CFC
];

// Rotas que NUNCA devem ser cacheadas (autenticação e dados sensíveis)
const BYPASS_ROUTES = [
  '/login',
  '/logout',
  '/forgot-password',
  '/reset-password',
  '/ativar-conta'
];

// Rotas privadas (HTML dinâmico - nunca cachear)
const PRIVATE_ROUTES = [
  '/dashboard',
  '/alunos',
  '/agenda',
  '/instrutores',
  '/veiculos',
  '/financeiro',
  '/servicos',
  '/usuarios',
  '/configuracoes',
  '/notificacoes',
  '/comunicados',
  '/solicitacoes-reagendamento',
  '/change-password',
  '/matriculas'
];

// Verificar se URL é rota privada
function isPrivateRoute(url) {
  const path = new URL(url).pathname;
  return PRIVATE_ROUTES.some(route => path.startsWith(route));
}

// Verificar se URL é rota de bypass
function isBypassRoute(url) {
  const path = new URL(url).pathname;
  return BYPASS_ROUTES.some(route => path === route);
}

// Verificar se é asset estático
function isStaticAsset(url) {
  const path = new URL(url).pathname;
  return path.startsWith('/assets/') || 
         path.startsWith('/icons/') || 
         path === '/manifest.json' ||
         path === '/sw.js';
}

// Install: Cache app shell
self.addEventListener('install', (event) => {
  console.log('[SW] Installing service worker...');
  
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    console.log('[SW] Caching core assets');
    
    // Converter paths relativos para URLs absolutas
    const assetsToCache = CORE_ASSETS.map(relativePath => {
      // Resolver path relativo baseado na localização do SW
      return new URL(relativePath, self.location.origin).href;
    });
    
    // Cache individual para não quebrar se um asset falhar
    for (const url of assetsToCache) {
      try {
        await cache.add(url);
        console.log('[SW] Cached:', url);
      } catch (e) {
        console.warn('[SW] Failed to cache:', url, e.message);
        // Continuar mesmo se alguns assets falharem (ex: ícones ainda não criados, favicon 404)
      }
    }
  })());
  
  // Forçar ativação imediata (skip waiting)
  self.skipWaiting();
});

// Activate: Limpar caches antigos
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating service worker...');
  
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[SW] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  
  // Tomar controle imediato de todas as páginas
  return self.clients.claim();
});

// Fetch: Estratégia de cache
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Ignorar requisições de outros domínios
  if (url.origin !== location.origin) {
    return;
  }
  
  // Bypass total para rotas de autenticação
  if (isBypassRoute(request.url)) {
    return fetch(request);
  }
  
  // Bypass total para service worker (sempre buscar nova versão)
  if (url.pathname === '/sw.js') {
    return fetch(request);
  }
  
  // Bypass total para API endpoints (dados dinâmicos)
  if (url.pathname.startsWith('/api/')) {
    return fetch(request);
  }
  
  // Bypass total para HTML de rotas privadas (NUNCA cachear)
  if (isPrivateRoute(request.url) && request.method === 'GET') {
    // Verificar se é HTML (não asset)
    if (!isStaticAsset(request.url)) {
      // Network-first para HTML autenticado (segurança crítica)
      event.respondWith(
        fetch(request)
          .catch(() => {
            // Se offline, retornar página de erro genérica
            return new Response('Offline - Conteúdo não disponível', {
              status: 503,
              headers: { 'Content-Type': 'text/plain' }
            });
          })
      );
      return;
    }
  }
  
  // Cache-first para assets estáticos
  if (isStaticAsset(request.url)) {
    event.respondWith(
      caches.match(request)
        .then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          return fetch(request).then((response) => {
            // Não cachear se não for sucesso
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            
            // Clonar resposta para cache
            const responseToCache = response.clone();
            
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseToCache);
            });
            
            return response;
          });
        })
    );
    return;
  }
  
  // Para tudo mais, usar network-first (HTML público, etc)
  event.respondWith(
    fetch(request)
      .then((response) => {
        // Não cachear HTML de rotas privadas
        if (isPrivateRoute(request.url)) {
          return response;
        }
        
        // Cachear apenas respostas de sucesso
        if (response && response.status === 200 && response.type === 'basic') {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, responseToCache);
          });
        }
        
        return response;
      })
      .catch(() => {
        // Se offline, tentar buscar do cache
        return caches.match(request);
      })
  );
});
