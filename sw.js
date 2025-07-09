const CACHE_NAME = 'accounting-app-v1.0.1';
const urlsToCache = [
  './',
  './manifest.json',
  './assets/css/bootstrap.min.css',
  './assets/js/bootstrap.bundle.min.js',
  './assets/js/chart.min.js',
  './icon-192.png',
  './icon-512.png'
];

// 安装事件 - 缓存核心资源
self.addEventListener('install', event => {
  console.log('Service Worker installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .catch(error => {
        console.error('Cache installation failed:', error);
      })
  );
  // 强制激活新的 service worker
  self.skipWaiting();
});

// 激活事件 - 清理旧缓存
self.addEventListener('activate', event => {
  console.log('Service Worker activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  // 立即控制所有页面
  self.clients.claim();
});

// 检查是否为动态内容
function isDynamicContent(url) {
  return url.includes('index.php') && !url.includes('action=login');
}

// 检查是否为静态资源
function isStaticResource(url) {
  return url.includes('/assets/') || 
         url.includes('.png') || 
         url.includes('.jpg') || 
         url.includes('.css') || 
         url.includes('.js') ||
         url.includes('manifest.json');
}

// 网络优先策略（用于动态内容）
async function networkFirst(request, cacheName) {
  try {
    console.log('Network first for:', request.url);
    const networkResponse = await fetch(request);
    
    if (networkResponse.status === 200) {
      const cache = await caches.open(cacheName);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.log('Network failed, trying cache:', request.url);
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // 如果缓存中也没有，返回离线页面
    return new Response(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>离线模式</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
          body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
          .offline { color: #666; }
        </style>
      </head>
      <body>
        <div class="offline">
          <h1>🔌 离线模式</h1>
          <p>当前无网络连接，请检查网络后刷新页面</p>
          <button onclick="window.location.reload()">重新加载</button>
        </div>
      </body>
      </html>
    `, {
      status: 503,
      statusText: 'Service Unavailable',
      headers: { 'Content-Type': 'text/html' }
    });
  }
}

// 缓存优先策略（用于静态资源）
async function cacheFirst(request, cacheName) {
  const cachedResponse = await caches.match(request);
  
  if (cachedResponse) {
    console.log('Serving from cache:', request.url);
    return cachedResponse;
  }
  
  console.log('Fetching from network:', request.url);
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.status === 200) {
      const cache = await caches.open(cacheName);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.error('Fetch failed:', error);
    return new Response('离线模式下无法获取资源', {
      status: 503,
      statusText: 'Service Unavailable'
    });
  }
}

// 拦截网络请求 - 智能缓存策略
self.addEventListener('fetch', event => {
  // 只处理 GET 请求
  if (event.request.method !== 'GET') {
    return;
  }

  // 跳过非同源请求
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  const url = event.request.url;

  // 动态内容使用网络优先策略
  if (isDynamicContent(url)) {
    event.respondWith(networkFirst(event.request, CACHE_NAME));
  }
  // 静态资源使用缓存优先策略
  else if (isStaticResource(url)) {
    event.respondWith(cacheFirst(event.request, CACHE_NAME));
  }
  // 其他请求直接从网络获取
  else {
    event.respondWith(
      fetch(event.request).catch(error => {
        console.error('Fetch failed:', error);
        return new Response('离线模式下无法获取资源', {
          status: 503,
          statusText: 'Service Unavailable'
        });
      })
    );
  }
});

// 后台同步事件
self.addEventListener('sync', event => {
  console.log('Background sync triggered:', event.tag);
  
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

// 执行后台同步
async function doBackgroundSync() {
  console.log('Performing background sync...');
  
  try {
    // 这里可以添加需要在后台同步的逻辑
    // 比如同步离线时创建的交易记录
    const pendingTransactions = await getPendingTransactions();
    
    for (const transaction of pendingTransactions) {
      await syncTransaction(transaction);
    }
    
    console.log('Background sync completed');
  } catch (error) {
    console.error('Background sync failed:', error);
  }
}

// 获取待同步的交易记录
async function getPendingTransactions() {
  // 从 IndexedDB 或其他本地存储获取待同步的数据
  return [];
}

// 同步单个交易记录
async function syncTransaction(transaction) {
  // 发送到服务器的逻辑
  console.log('Syncing transaction:', transaction);
}

// 推送消息事件
self.addEventListener('push', event => {
  console.log('Push message received');
  
  const options = {
    body: event.data ? event.data.text() : '您有新的财务提醒',
    icon: '/icon-192.png',
    badge: '/icon-192.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: '查看详情',
        icon: '/icon-192.png'
      },
      {
        action: 'close',
        title: '关闭',
        icon: '/icon-192.png'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification('记账本提醒', options)
  );
});

// 通知点击事件
self.addEventListener('notificationclick', event => {
  console.log('Notification click received');
  
  event.notification.close();
  
  if (event.action === 'explore') {
    // 打开应用
    event.waitUntil(
      clients.openWindow('./')
    );
  } else if (event.action === 'close') {
    // 关闭通知
    event.notification.close();
  } else {
    // 默认行为：打开应用
    event.waitUntil(
      clients.openWindow('./')
    );
  }
});

// 消息事件 - 与主线程通信
self.addEventListener('message', event => {
  console.log('Message received in SW:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({ version: CACHE_NAME });
  }
});

// 错误处理
self.addEventListener('error', event => {
  console.error('Service Worker error:', event.error);
});

self.addEventListener('unhandledrejection', event => {
  console.error('Service Worker unhandled promise rejection:', event.reason);
}); 