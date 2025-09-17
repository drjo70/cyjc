// 창녕조씨 족보 웹앱 - 메인 JavaScript
let currentUser = null;

// 전화번호 입력 포맷팅
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length <= 3) {
                e.target.value = value;
            } else if (value.length <= 7) {
                e.target.value = value.substring(0, 3) + '-' + value.substring(3);
            } else {
                e.target.value = value.substring(0, 3) + '-' + value.substring(3, 7) + '-' + value.substring(7, 11);
            }
        });

        // 엔터 키 로그인
        phoneInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                login();
            }
        });
    }
});

// 로그인 함수 (디버깅 강화)
async function login() {
    const phone = document.getElementById('phoneInput').value.trim();
    
    console.log('[DEBUG] Login 시작 - 입력된 전화번호:', phone);
    
    // 전화번호 형식 검증
    const phoneRegex = /^010-\d{4}-\d{4}$/;
    if (!phone || !phoneRegex.test(phone)) {
        console.log('[DEBUG] 전화번호 형식 검증 실패:', phone);
        alert('올바른 전화번호 형식을 입력하세요 (010-0000-0000)');
        return;
    }
    
    console.log('[DEBUG] 전화번호 형식 검증 통과, API 호출 시작');
    
    try {
        // API 요청 전 로깅
        console.log('[DEBUG] API 요청 URL:', '/api/auth/verify');
        console.log('[DEBUG] Authorization Header:', 'Bearer ' + phone);
        
        const response = await fetch('/api/auth/verify', {
            method: 'GET',
            headers: { 
                'Authorization': 'Bearer ' + phone,
                'Content-Type': 'application/json'
            }
        });
        
        console.log('[DEBUG] API 응답 상태:', response.status, response.statusText);
        console.log('[DEBUG] API 응답 헤더:', Object.fromEntries(response.headers.entries()));
        
        if (!response.ok) {
            console.log('[DEBUG] API 응답 오류 - 응답 읽기 시도');
            const errorText = await response.text();
            console.log('[DEBUG] 에러 응답 텍스트:', errorText);
            
            let errorData;
            try {
                errorData = JSON.parse(errorText);
            } catch (e) {
                console.log('[DEBUG] JSON 파싱 실패, 원본 텍스트 사용');
                errorData = { error: errorText };
            }
            
            throw new Error(errorData.error || 'HTTP ' + response.status + ': ' + errorText);
        }
        
        console.log('[DEBUG] API 응답 성공, 데이터 파싱 시작');
        const responseText = await response.text();
        console.log('[DEBUG] 응답 텍스트:', responseText);
        
        currentUser = JSON.parse(responseText);
        console.log('[DEBUG] 파싱된 사용자 데이터:', currentUser);
        
        // 사용자 정보를 sessionStorage에 저장
        sessionStorage.setItem('userPhone', currentUser.phone);
        sessionStorage.setItem('userInfo', JSON.stringify(currentUser));
        
        showMainMenu();
        loadMyInfo();
    } catch (error) {
        console.error('[ERROR] Login 실패:', error);
        console.error('[ERROR] Error 타입:', typeof error);
        console.error('[ERROR] Error message:', error.message);
        console.error('[ERROR] Error stack:', error.stack);
        
        if (error.message.includes('403') || error.message.includes('등록되지 않은')) {
            alert('등록되지 않은 전화번호입니다. 관리자에게 문의하세요.');
        } else if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            alert('네트워크 연결을 확인하세요. 서버가 응답하지 않습니다.');
        } else {
            alert('로그인 중 오류가 발생했습니다: ' + error.message);
        }
    }
}

// 메인 메뉴 표시
function showMainMenu() {
    const loginSection = document.getElementById('loginSection');
    const mainMenu = document.getElementById('mainMenu');
    
    if (loginSection && mainMenu) {
        loginSection.classList.add('hidden');
        mainMenu.classList.remove('hidden');
    }
}

// 내 정보 로드
async function loadMyInfo() {
    if (!currentUser) return;
    
    try {
        const response = await fetch('/api/protected/my-info', {
            method: 'GET',
            headers: { 
                'Authorization': 'Bearer ' + currentUser.phone,
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        
        const info = await response.json();
        const myInfoCard = document.getElementById('myInfoCard');
        if (myInfoCard) {
            myInfoCard.innerHTML = `
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg mr-4">
                        ${info.name.charAt(0)}
                    </div>
                    <div>
                        <h3 class="font-bold text-lg">${info.name} (${info.name_hanja || ''})</h3>
                        <p class="text-sm text-gray-600">${info.generation}세대 • ${info.gender === 1 ? '남' : '여'}</p>
                        <p class="text-xs text-gray-500">${info.phone_number}</p>
                    </div>
                </div>
            `;
        }
    } catch (error) {
        console.error('내 정보 로드 오류:', error);
    }
}

// 각 기능별 함수들
function openLineage() {
    window.location.href = '/lineage';
}

function openSearch() {
    window.location.href = '/search';
}

function openRelationship() {
    window.location.href = '/relationship';
}

function openDirectLineage() {
    console.log('직계혈통 페이지로 이동');
    window.location.href = '/direct-lineage';
}

function openAnnouncements() {
    window.location.href = '/announcements';
}

function openNearby() {
    alert('내 주변 종인 기능은 준비 중입니다.');
}

function contactAdmin() {
    alert('관리자 문의 기능은 준비 중입니다.');
}