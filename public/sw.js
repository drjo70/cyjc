// 창녕조씨 족보 시스템 - 서비스 워커
const CACHE_NAME = 'cyjc-genealogy-v1.0.0';
const urlsToCache = [
    '/',
    '/index.php',
    '/search.php',
    '/family_lineage.php',
    '/static/style.css',
    '/static/app.js',
    '/static/icon-192.png',
    '/static/icon-512.png',
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css'
];

// 설치 이벤트 - 캐시 생성
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('캐시가 열렸습니다');
                return cache.addAll(urlsToCache);
            })
            .catch((error) => {
                console.log('캐시 생성 실패:', error);
            })
    );
});

// 활성화 이벤트 - 오래된 캐시 정리
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('오래된 캐시 삭제:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// 페치 이벤트 - 네트워크 우선, 캐시 백업 전략
self.addEventListener('fetch', (event) => {
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // 네트워크 응답이 유효한 경우
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }

                // 응답을 클론해서 캐시에 저장
                const responseToCache = response.clone();
                caches.open(CACHE_NAME)
                    .then((cache) => {
                        cache.put(event.request, responseToCache);
                    });

                return response;
            })
            .catch(() => {
                // 네트워크 실패시 캐시에서 응답
                return caches.match(event.request)
                    .then((response) => {
                        if (response) {
                            return response;
                        }
                        
                        // 캐시에도 없으면 오프라인 페이지 표시
                        if (event.request.destination === 'document') {
                            return caches.match('/offline.html');
                        }
                    });
            })
    );
});

// 백그라운드 동기화 (향후 확장용)
self.addEventListener('sync', (event) => {
    if (event.tag === 'background-sync') {
        console.log('백그라운드 동기화 실행');
        // 향후 데이터 동기화 로직 추가
    }
});

// 푸시 알림 처리 (완전 구현)
self.addEventListener('push', (event) => {
    let notificationData = {
        title: '창녕조씨 족보',
        body: '새로운 족보 정보가 업데이트되었습니다.',
        icon: '/static/icon-192.png',
        badge: '/static/icon-192.png',
        data: {
            url: '/',
            timestamp: Date.now()
        }
    };

    // FCM 데이터 파싱
    if (event.data) {
        try {
            const payload = event.data.json();
            
            if (payload.notification) {
                notificationData.title = payload.notification.title || notificationData.title;
                notificationData.body = payload.notification.body || notificationData.body;
                notificationData.icon = payload.notification.icon || notificationData.icon;
            }
            
            if (payload.data) {
                notificationData.data = { ...notificationData.data, ...payload.data };
            }
        } catch (error) {
            console.error('푸시 데이터 파싱 오류:', error);
            notificationData.body = event.data.text();
        }
    }

    const options = {
        body: notificationData.body,
        icon: notificationData.icon,
        badge: notificationData.badge,
        vibrate: [200, 100, 200],
        data: notificationData.data,
        requireInteraction: false, // 자동으로 사라지게
        actions: [
            {
                action: 'open',
                title: '확인하기',
                icon: '/static/icon-192.png'
            },
            {
                action: 'close',
                title: '닫기'
            }
        ],
        tag: 'cyjc-genealogy', // 같은 태그의 알림은 하나만 표시
        renotify: true
    };

    event.waitUntil(
        self.registration.showNotification(notificationData.title, options)
    );
});

// 알림 클릭 처리
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const action = event.action;
    const data = event.notification.data || {};
    
    if (action === 'close') {
        return; // 알림만 닫기
    }

    // 기본 액션 또는 'open' 액션
    const urlToOpen = data.url || '/';
    
    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then((clientList) => {
            // 이미 열려있는 창이 있으면 포커스
            for (let client of clientList) {
                if (client.url.includes(self.registration.scope) && 'focus' in client) {
                    client.navigate(urlToOpen);
                    return client.focus();
                }
            }
            
            // 새 창 열기
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

// 알림 닫기 처리
self.addEventListener('notificationclose', (event) => {
    console.log('알림이 닫혔습니다:', event.notification.tag);
    
    // 알림 닫기 통계 등을 위한 처리 (선택사항)
    // analytics 등에 전송 가능
});