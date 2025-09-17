    </div>
    <!-- 메인 컨테이너 끝 -->

    <!-- 푸터 -->
    <footer class="footer mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-tree me-2"></i>창녕조씨 족보시스템</h5>
                    <p class="mb-1">시조 조계룡(趙季龍)부터 45세대까지</p>
                    <p class="mb-1">600여 명의 족보 데이터 관리</p>
                    <p class="text-muted small">Developed by 닥터조 (주)조유</p>
                </div>
                <div class="col-md-3">
                    <h6>주요 기능</h6>
                    <ul class="list-unstyled">
                        <li><a href="?page=dashboard" class="text-light text-decoration-none">대시보드</a></li>
                        <li><a href="?page=persons" class="text-light text-decoration-none">인물 검색</a></li>
                        <li><a href="?page=generation" class="text-light text-decoration-none">세대별 족보</a></li>
                        <li><a href="test_db.php" class="text-light text-decoration-none">시스템 상태</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>시스템 정보</h6>
                    <p class="mb-1 small">Version: 1.0</p>
                    <p class="mb-1 small">Build: <?= date('Y-m-d') ?></p>
                    <p class="mb-1 small">Database: MySQL (Cafe24)</p>
                    <p class="mb-1 small">Framework: PHP Native</p>
                </div>
            </div>
            <hr class="my-3">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0 small">&copy; <?= date('Y') ?> 창녕조씨 족보시스템. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 small">
                        <a href="#" class="text-light text-decoration-none me-3">개인정보처리방침</a>
                        <a href="#" class="text-light text-decoration-none me-3">이용약관</a>
                        <a href="mailto:support@example.com" class="text-light text-decoration-none">문의하기</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- PWA 설치 알림 -->
    <div id="pwaInstallPrompt" class="position-fixed bottom-0 start-50 translate-middle-x bg-primary text-white p-3 rounded-top shadow" style="display: none; z-index: 1050;">
        <div class="d-flex align-items-center">
            <i class="fas fa-mobile-alt me-2"></i>
            <div class="flex-grow-1">
                <small><strong>앱으로 설치하기</strong></small><br>
                <small>홈 화면에 추가하여 더욱 편리하게 이용하세요!</small>
            </div>
            <button id="pwaInstallBtn" class="btn btn-light btn-sm ms-2">설치</button>
            <button id="pwaCloseBtn" class="btn btn-outline-light btn-sm ms-1">×</button>
        </div>
    </div>

    <!-- 오프라인 알림 -->
    <div id="offlineNotification" class="position-fixed top-0 start-50 translate-middle-x bg-warning text-dark p-2 rounded-bottom shadow" style="display: none; z-index: 1060;">
        <small><i class="fas fa-wifi-slash me-1"></i> 오프라인 모드 - 일부 기능이 제한됩니다</small>
    </div>

    <!-- PWA 및 Service Worker 스크립트 -->
    <script>
        // PWA 관련 변수
        let deferredPrompt;
        let isAppInstalled = false;

        // Service Worker 등록
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('✅ SW 등록 성공:', registration.scope);
                        
                        // 업데이트 체크
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // 새 버전 알림
                                    showUpdateNotification();
                                }
                            });
                        });
                    })
                    .catch(function(err) {
                        console.log('❌ SW 등록 실패:', err);
                    });
            });
        }

        // 앱 설치 프롬프트 처리
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('📱 PWA 설치 프롬프트 준비');
            e.preventDefault();
            deferredPrompt = e;
            showInstallPrompt();
        });

        // 앱 설치 완료 감지
        window.addEventListener('appinstalled', (evt) => {
            console.log('🎉 PWA 설치 완료');
            isAppInstalled = true;
            hideInstallPrompt();
            showToast('앱이 성공적으로 설치되었습니다!', 'success');
        });

        // PWA 설치 프롬프트 표시
        function showInstallPrompt() {
            // 이미 설치되었거나 iOS Safari인 경우 제외
            if (isAppInstalled || isIOSSafari()) {
                return;
            }

            const prompt = document.getElementById('pwaInstallPrompt');
            if (prompt) {
                prompt.style.display = 'block';
                
                // 자동으로 3초 후에 표시 (한 번만)
                setTimeout(() => {
                    if (!localStorage.getItem('pwa-prompt-shown')) {
                        prompt.style.display = 'block';
                        localStorage.setItem('pwa-prompt-shown', 'true');
                    }
                }, 3000);
            }
        }

        // PWA 설치 프롬프트 숨기기
        function hideInstallPrompt() {
            const prompt = document.getElementById('pwaInstallPrompt');
            if (prompt) {
                prompt.style.display = 'none';
            }
        }

        // PWA 설치 버튼 클릭
        document.getElementById('pwaInstallBtn')?.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                
                if (outcome === 'accepted') {
                    console.log('👍 사용자가 PWA 설치 수락');
                } else {
                    console.log('👎 사용자가 PWA 설치 거부');
                }
                
                deferredPrompt = null;
                hideInstallPrompt();
            } else if (isIOSSafari()) {
                // iOS Safari 사용자를 위한 안내
                showIOSInstallGuide();
            }
        });

        // PWA 프롬프트 닫기
        document.getElementById('pwaCloseBtn')?.addEventListener('click', () => {
            hideInstallPrompt();
            localStorage.setItem('pwa-prompt-dismissed', 'true');
        });

        // iOS Safari 감지
        function isIOSSafari() {
            const ua = window.navigator.userAgent;
            const iOS = /iPad|iPhone|iPod/.test(ua);
            const webkit = /WebKit/.test(ua);
            const safari = /Safari/.test(ua);
            return iOS && webkit && safari && !window.MSStream;
        }

        // iOS 설치 가이드 표시
        function showIOSInstallGuide() {
            const guide = `
                iPhone/iPad에서 앱으로 설치하기:
                
                1. 하단의 공유 버튼 (📤) 터치
                2. "홈 화면에 추가" 선택
                3. "추가" 버튼 터치
                
                그러면 홈 화면에서 앱처럼 사용하실 수 있습니다!
            `;
            
            showModal('앱 설치 안내', guide);
        }

        // 온라인/오프라인 상태 모니터링
        function updateOnlineStatus() {
            const offlineNotification = document.getElementById('offlineNotification');
            
            if (!navigator.onLine) {
                offlineNotification.style.display = 'block';
            } else {
                offlineNotification.style.display = 'none';
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus(); // 초기 상태 체크

        // SW 업데이트 알림
        function showUpdateNotification() {
            const updateMsg = '새로운 버전이 있습니다. 페이지를 새로고침하시겠습니까?';
            
            if (confirm(updateMsg)) {
                window.location.reload();
            }
        }

        // 토스트 메시지 표시
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '1070';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        // 간단한 모달 표시
        function showModal(title, content) {
            const modalHtml = `
                <div class="modal fade" id="infoModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" style="white-space: pre-line;">
                                ${content}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">확인</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('infoModal'));
            modal.show();
            
            // 모달 숨김 후 제거
            document.getElementById('infoModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // 페이지 로딩 성능 측정
        window.addEventListener('load', function() {
            const perfData = performance.getEntriesByType('navigation')[0];
            if (perfData) {
                const loadTime = Math.round(perfData.loadEventEnd - perfData.loadEventStart);
                console.log(`📊 페이지 로딩 시간: ${loadTime}ms`);
                
                // 느린 로딩 시 캐시 안내
                if (loadTime > 3000) {
                    console.log('💡 페이지 로딩이 느립니다. PWA를 설치하시면 더 빠르게 이용하실 수 있습니다.');
                }
            }
        });
    </script>
    
    <!-- 커스텀 JavaScript -->
    <script>
        // 세대 검색 모달
        function showGenerationSearch() {
            const generation = prompt("검색하실 세대를 입력하세요 (1-45):");
            if (generation && generation >= 1 && generation <= 45) {
                window.location.href = `?page=persons&generation=${generation}`;
            }
        }
        
        // 인물 상세 보기 (Ajax)
        function showPersonDetail(personCode) {
            window.location.href = `?page=person&code=${personCode}`;
        }
        
        // 가족 관계도 보기
        function showFamilyTree(personCode) {
            window.location.href = `?page=family&code=${personCode}`;
        }
        
        // 혈통 추적
        function showLineage(personCode) {
            window.location.href = `?page=lineage&code=${personCode}`;
        }
        
        // 관계 분석 (두 인물 간)
        function analyzeRelationship() {
            const code1 = prompt("첫 번째 인물 코드를 입력하세요:");
            const code2 = prompt("두 번째 인물 코드를 입력하세요:");
            
            if (code1 && code2) {
                fetch(`?page=api&action=relationship&code1=${code1}&code2=${code2}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`관계: ${data.relationship}\n공통조상: ${data.common_ancestor?.name || '없음'}`);
                        } else {
                            alert('관계 분석 실패: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('오류가 발생했습니다.');
                    });
            }
        }
        
        // 페이지 로딩 완료 시
        document.addEventListener('DOMContentLoaded', function() {
            // 툴팁 초기화
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // 인물 카드 클릭 이벤트
            document.querySelectorAll('.person-card').forEach(function(card) {
                card.addEventListener('click', function() {
                    const personCode = this.getAttribute('data-person-code');
                    if (personCode) {
                        showPersonDetail(personCode);
                    }
                });
            });
            
            // 검색 자동완성 (간단한 버전)
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value;
                    if (query.length >= 2) {
                        // 실시간 검색 suggestions (선택사항)
                        // 여기에 Ajax 자동완성 코드 추가 가능
                    }
                });
            }
        });
        
        // 유틸리티 함수들
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('ko-KR');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('클립보드에 복사되었습니다: ' + text);
            });
        }
        
        // 관계도 분석 버튼 추가 (네비게이션에)
        if (document.querySelector('.navbar-nav')) {
            const analyzeBtn = document.createElement('button');
            analyzeBtn.className = 'btn btn-sm btn-outline-light ms-2';
            analyzeBtn.innerHTML = '<i class="fas fa-analytics"></i> 관계분석';
            analyzeBtn.onclick = analyzeRelationship;
            
            const navbarNav = document.querySelector('.navbar-nav').parentNode;
            if (navbarNav) {
                navbarNav.appendChild(analyzeBtn);
            }
        }
    </script>
    
    <!-- 개발 환경에서만 표시되는 디버그 정보 -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
    <div style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 9999;">
        <strong>Debug Info:</strong><br>
        Page: <?= $_GET['page'] ?? 'dashboard' ?><br>
        PHP: <?= PHP_VERSION ?><br>
        Memory: <?= round(memory_get_usage() / 1024 / 1024, 2) ?>MB<br>
        Time: <?= date('Y-m-d H:i:s') ?>
    </div>
    <?php endif; ?>
</body>
</html>