/**
 * Service Worker - Sistema CFC Bom Conselho
 * Versão: 1.0.0
 * Estratégias de cache otimizadas para PWA
 */

const CACHE_VERSION = 'cfc-v1.0.9';
const CACHE_NAME = `cfc-cache-${CACHE_VERSION}`;
const OFFLINE_CACHE = 'cfc-offline-v1';

// App Shell - recursos críticos que devem estar sempre disponíveis
const APP_SHELL = [
  '/instrutor/dashboard.php',
  '/admin/',
  '/admin/assets/css/admin.css',
  '/admin/assets/js/admin.js',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Recursos estáticos que podem ser cacheados
const STATIC_RESOURCES = [
  '/admin/assets/css/',
  '/admin/assets/js/',
  '/admin/assets/images/',
  '/assets/css/',
  '/assets/js/',
  '/assets/img/',
  '/pwa/icons/'
];

// Rotas que NÃO devem ser cacheadas (conteúdo sensível)
const EXCLUDED_ROUTES = [
  '/admin/logout.php',
  '/admin/login.php',
  '/instrutor/logout.php',
  '/login.php',
  '/logout.php',
  '/admin/api/auth/',
  '/admin/api/sensitive/',
  '/admin/pages/usuarios.php',
  '/admin/pages/configuracoes.php'
];

// Página offline
const OFFLINE_PAGE = '/pwa/offline.html';

/**
 * Evento de instalação - cache do App Shell
 */
self.addEventListener('install', (event) => {
  console.log(`[SW] Instalando versão ${CACHE_VERSION}`);
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[SW] Cacheando App Shell...');
        // Cache individual com tratamento de erro
        return Promise.allSettled(
          APP_SHELL.map(url => 
            cache.add(url).catch(error => {
              console.warn(`[SW] Falha ao cachear ${url}:`, error);
              return null; // Continua mesmo se um recurso falhar
            })
          )
        );
      })
      .then(() => {
        console.log('[SW] App Shell cacheado com sucesso');
        // Forçar ativação imediata
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('[SW] Erro ao cachear App Shell:', error);
        // Mesmo com erro, continua a instalação
        return self.skipWaiting();
      })
  );
});

/**
 * Evento de ativação - limpeza de caches antigos
 */
self.addEventListener('activate', (event) => {
  console.log(`[SW] Ativando versão ${CACHE_VERSION}`);
  
  event.waitUntil(
    Promise.all([
      // Limpar caches antigos
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(cacheName => 
              cacheName.startsWith('cfc-cache-') && 
              cacheName !== CACHE_NAME
            )
            .map(cacheName => {
              console.log(`[SW] Removendo cache antigo: ${cacheName}`);
              return caches.delete(cacheName);
            })
        );
      }),
      // CRÍTICO: Assumir controle imediatamente de todas as páginas
      // Isso é essencial para o PWA ser elegível para instalação
      self.clients.claim().then(() => {
        console.log('[SW] ✅ Controle reivindicado de todas as páginas');
      })
    ])
    .then(() => {
      console.log('[SW] Cache antigo removido');
      console.log('[SW] ✅ Service Worker ativado e controlando todas as páginas');
      
      // Notificar clientes sobre a ativação
      return self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({ 
            type: 'SW_ACTIVATED', 
            version: CACHE_VERSION,
            timestamp: Date.now()
          });
        });
        console.log(`[SW] Notificados ${clients.length} cliente(s) sobre ativação`);
      });
    })
    .catch(error => {
      console.error('[SW] Erro na ativação:', error);
    })
  );
});

/**
 * Evento de fetch - estratégias de cache
 */
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);
  
  // Ignorar requisições não-GET
  if (request.method !== 'GET') {
    return;
  }
  
  // Ignorar requisições de outros domínios
  if (url.origin !== location.origin) {
    return;
  }
  
  // Verificar se a rota deve ser excluída do cache
  if (shouldExcludeFromCache(url.pathname)) {
    console.log(`[SW] Rota excluída do cache: ${url.pathname}`);
    return;
  }
  
  // Estratégias baseadas no tipo de recurso
  if (isAppShellRequest(url.pathname)) {
    // App Shell: Cache First
    event.respondWith(cacheFirstStrategy(request));
  } else if (isAPIRequest(url.pathname)) {
    // APIs: Network First com fallback offline
    event.respondWith(networkFirstStrategy(request));
  } else if (isImageRequest(url.pathname)) {
    // Imagens: Stale While Revalidate
    event.respondWith(staleWhileRevalidateStrategy(request));
  } else if (isStaticResource(url.pathname)) {
    // Recursos estáticos: Cache First
    event.respondWith(cacheFirstStrategy(request));
  } else {
    // Páginas HTML: Network First com fallback offline
    event.respondWith(networkFirstWithOfflineFallback(request));
  }
});

/**
 * Estratégia Cache First - para App Shell e recursos estáticos
 */
async function cacheFirstStrategy(request) {
  try {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      console.log(`[SW] Cache First - servindo do cache: ${request.url}`);
      return cachedResponse;
    }
    
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      // CRÍTICO: Clonar ANTES de qualquer uso da response
      const responseClone = networkResponse.clone();
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, responseClone).catch(err => {
        console.error(`[SW] Erro ao cachear ${request.url}:`, err);
      });
    }
    
    return networkResponse;
  } catch (error) {
    console.error(`[SW] Erro na estratégia Cache First: ${error}`);
    return new Response('Erro ao carregar recurso', { status: 500 });
  }
}

/**
 * Estratégia Network First - para APIs e páginas dinâmicas
 */
async function networkFirstStrategy(request) {
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // CRÍTICO: Clonar ANTES de qualquer uso da response
      const responseClone = networkResponse.clone();
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, responseClone).catch(err => {
        console.error(`[SW] Erro ao cachear ${request.url}:`, err);
      });
    }
    
    return networkResponse;
  } catch (error) {
    console.log(`[SW] Network First - servindo do cache: ${request.url}`);
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Fallback para página offline se for uma página HTML
    if (request.headers.get('accept').includes('text/html')) {
      return caches.match(OFFLINE_PAGE);
    }
    
    return new Response('Recurso não disponível offline', { status: 503 });
  }
}

/**
 * Estratégia Stale While Revalidate - para imagens
 */
async function staleWhileRevalidateStrategy(request) {
  const cachedResponse = await caches.match(request);
  
  const fetchPromise = fetch(request).then(async networkResponse => {
    if (networkResponse.ok) {
      // CRÍTICO: Clonar ANTES de qualquer uso da response
      // O body de uma Response só pode ser lido uma vez
      const responseClone = networkResponse.clone();
      
      // Cachear em background (não bloquear a resposta)
      caches.open(CACHE_NAME).then(cache => {
        cache.put(request, responseClone).catch(err => {
          console.error(`[SW] Erro ao cachear ${request.url}:`, err);
        });
      });
    }
    return networkResponse;
  }).catch(() => {
    // Ignorar erros de rede para imagens
    return null;
  });
  
  return cachedResponse || fetchPromise;
}

/**
 * Network First com fallback para página offline
 */
async function networkFirstWithOfflineFallback(request) {
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cachear apenas páginas HTML bem-sucedidas
      if (request.headers.get('accept').includes('text/html')) {
        // CRÍTICO: Clonar ANTES de qualquer uso da response
        const responseClone = networkResponse.clone();
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, responseClone).catch(err => {
          console.error(`[SW] Erro ao cachear ${request.url}:`, err);
        });
      }
    }
    
    return networkResponse;
  } catch (error) {
    console.log(`[SW] Network First - fallback offline: ${request.url}`);
    
    // Tentar servir do cache primeiro
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Fallback para página offline
    if (request.headers.get('accept').includes('text/html')) {
      return caches.match(OFFLINE_PAGE);
    }
    
    return new Response('Conteúdo não disponível offline', { status: 503 });
  }
}

/**
 * Verificar se a rota deve ser excluída do cache
 */
function shouldExcludeFromCache(pathname) {
  return EXCLUDED_ROUTES.some(route => pathname.includes(route));
}

/**
 * Verificar se é uma requisição do App Shell
 */
function isAppShellRequest(pathname) {
  return APP_SHELL.some(resource => pathname.includes(resource));
}

/**
 * Verificar se é uma requisição de API
 */
function isAPIRequest(pathname) {
  return pathname.includes('/api/') || pathname.includes('.php?action=');
}

/**
 * Verificar se é uma requisição de imagem
 */
function isImageRequest(pathname) {
  return /\.(jpg|jpeg|png|gif|webp|svg|ico)$/i.test(pathname);
}

/**
 * Verificar se é um recurso estático
 */
function isStaticResource(pathname) {
  return STATIC_RESOURCES.some(resource => pathname.includes(resource));
}

/**
 * Evento de mensagem - comunicação com a página
 */
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log('[SW] Recebida mensagem SKIP_WAITING');
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({ version: CACHE_VERSION });
  }
});

/**
 * Evento de push - para notificações futuras
 */
self.addEventListener('push', (event) => {
  if (event.data) {
    const data = event.data.json();
    const options = {
      body: data.body,
      icon: '/pwa/icons/icon-192.png',
      badge: '/pwa/icons/icon-72.png',
      tag: data.tag || 'cfc-notification',
      data: data.data || {}
    };
    
    event.waitUntil(
      self.registration.showNotification(data.title, options)
    );
  }
});

/**
 * Evento de clique em notificação
 */
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  
  const urlToOpen = event.notification.data?.url || '/admin/';
  
  event.waitUntil(
    clients.matchAll({ type: 'window' })
      .then(clientList => {
        // Verificar se já existe uma janela aberta
        for (const client of clientList) {
          if (client.url.includes('/admin/') && 'focus' in client) {
            return client.focus();
          }
        }
        
        // Abrir nova janela
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

console.log(`[SW] Service Worker ${CACHE_VERSION} carregado`);