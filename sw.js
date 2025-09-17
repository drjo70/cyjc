/**
 * 창녕조씨 족보시스템 Service Worker
 * 
 * @author 닥터조 (주)조유
 * @version 1.0
 * @date 2024-09-17
 */

const CACHE_NAME = 'cyjc-genealogy-v1.0.0';
const urlsToCache = [
  '/',
  '/index.php',
  '/my_profile.php',
  '/advanced_search.php',
  '/family_tree.php',
  '/admin.php',
  '/assets/icon-192x192.png',
  '/assets/icon-512x512.png',
  // 외부 CDN 캐싱
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css',
  'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap',
  'https://d3js.org/d3.v7.min.js',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// Service Worker 설치
self.addEventListener('install', function(event) {
  console.log('📱 창녕조씨 족보시스템 SW 설치 중...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('📚 캐시 열기 성공');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('✅ 모든 파일 캐싱 완료');
        return self.skipWaiting(); // 즉시 활성화
      })
      .catch(err => {
        console.error('❌ 캐시 설치 실패:', err);
      })
  );
});

// Service Worker 활성화
self.addEventListener('activate', function(event) {
  console.log('🔄 SW 활성화 중...');
  
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          // 이전 버전 캐시 삭제
          if (cacheName !== CACHE_NAME && cacheName.startsWith('cyjc-genealogy-')) {
            console.log('🗑️ 이전 캐시 삭제:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('✅ SW 활성화 완료');
      return self.clients.claim(); // 모든 클라이언트 제어
    })
  );
});

// 네트워크 요청 처리 (Cache First 전략)
self.addEventListener('fetch', function(event) {
  // GET 요청만 캐싱
  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        // 캐시에 있으면 캐시 버전 반환
        if (response) {
          console.log('📁 캐시에서 제공:', event.request.url);
          
          // 백그라운드에서 업데이트 확인 (Stale While Revalidate)
          fetch(event.request)
            .then(fetchResponse => {
              if (fetchResponse && fetchResponse.status === 200) {
                const responseToCache = fetchResponse.clone();
                caches.open(CACHE_NAME)
                  .then(cache => {
                    cache.put(event.request, responseToCache);
                  });
              }
            })
            .catch(err => {
              console.log('🔄 백그라운드 업데이트 실패 (정상):', err.message);
            });
          
          return response;
        }

        // 캐시에 없으면 네트워크에서 가져와서 캐시에 저장
        return fetch(event.request)
          .then(function(response) {
            // 유효하지 않은 응답이면 그대로 반환
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // 응답 복사 (한 번만 읽을 수 있으므로)
            const responseToCache = response.clone();

            caches.open(CACHE_NAME)
              .then(function(cache) {
                console.log('💾 새로 캐싱:', event.request.url);
                cache.put(event.request, responseToCache);
              });

            return response;
          })
          .catch(function(error) {
            console.error('🌐 네트워크 요청 실패:', error);
            
            // 오프라인 대체 페이지 (향후 구현)
            if (event.request.headers.get('accept').includes('text/html')) {
              return caches.match('/offline.html');
            }
            
            throw error;
          });
      })
  );
});

// 푸시 알림 처리 (향후 구현)
self.addEventListener('push', function(event) {
  console.log('📬 푸시 알림 수신:', event);
  
  const options = {
    body: '새로운 족보 정보가 업데이트되었습니다.',
    icon: '/assets/icon-192x192.png',
    badge: '/assets/icon-72x72.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: '확인하기',
        icon: '/assets/check-icon.png'
      },
      {
        action: 'close', 
        title: '닫기',
        icon: '/assets/close-icon.png'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification('창녕조씨 족보시스템', options)
  );
});

// 알림 클릭 처리
self.addEventListener('notificationclick', function(event) {
  console.log('🔔 알림 클릭:', event);
  
  event.notification.close();
  
  if (event.action === 'explore') {
    // 앱 열기
    event.waitUntil(
      clients.openWindow('/')
    );
  } else if (event.action === 'close') {
    // 닫기 (이미 close() 호출됨)
  }
});

// 백그라운드 동기화 (향후 구현)
self.addEventListener('sync', function(event) {
  console.log('🔄 백그라운드 동기화:', event.tag);
  
  if (event.tag === 'genealogy-sync') {
    event.waitUntil(
      // 족보 데이터 동기화 로직
      console.log('📊 족보 데이터 동기화 실행')
    );
  }
});

// 주기적 백그라운드 동기화 (향후 구현)
self.addEventListener('periodicsync', function(event) {
  if (event.tag === 'genealogy-update') {
    event.waitUntil(
      // 정기적인 족보 업데이트 확인
      console.log('⏰ 정기 족보 업데이트 확인')
    );
  }
});

console.log('🚀 창녕조씨 족보시스템 Service Worker 로드 완료');