// 검색 페이지 JavaScript
let currentResults = [];

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    // 테스트용 자동 로그인 (개발 환경에서만)
    if (!sessionStorage.getItem('userPhone')) {
        console.log('[DEBUG] 테스트용 자동 로그인: 검색 페이지');
        sessionStorage.setItem('userPhone', '010-9272-9081');
    }
    
    // URL 파라미터 확인해서 세대별 보기 자동 실행
    const urlParams = new URLSearchParams(window.location.search);
    const generation = urlParams.get('generation');
    
    if (generation) {
        document.getElementById('generationFilter').value = generation;
        loadGenerationView();
    }
    
    // 검색어 입력 시 엔터키 처리
    document.getElementById('searchQuery').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
});

// 검색 수행
async function performSearch() {
    const query = document.getElementById('searchQuery').value.trim();
    const type = document.getElementById('searchType').value;
    const generation = document.getElementById('generationFilter').value;
    
    if (!query) {
        alert('검색어를 입력해주세요.');
        return;
    }
    
    console.log('[DEBUG] 검색 시작:', { query, type, generation });
    
    try {
        showLoading();
        
        // API 호출
        const userPhone = sessionStorage.getItem('userPhone');
        if (!userPhone) {
            throw new Error('로그인이 필요합니다.');
        }
        
        const params = new URLSearchParams({
            query: query,
            type: type,
            generation: generation
        });
        
        const response = await fetch(`/api/protected/search?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + userPhone,
                'Content-Type': 'application/json'
            }
        });
        
        console.log('[DEBUG] 검색 API 응답:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error('검색 실패: ' + errorText);
        }
        
        const data = await response.json();
        console.log('[DEBUG] 검색 결과:', data);
        
        currentResults = data.results || [];
        displaySearchResults(data);
        
    } catch (error) {
        console.error('[ERROR] 검색 오류:', error);
        showError('검색 중 오류가 발생했습니다: ' + error.message);
    }
}

// 세대별 보기
async function loadGenerationView() {
    const generation = document.getElementById('generationFilter').value;
    
    console.log('[DEBUG] 세대별 보기 시작:', generation);
    
    try {
        showLoading();
        
        // API 호출
        const userPhone = sessionStorage.getItem('userPhone');
        if (!userPhone) {
            throw new Error('로그인이 필요합니다.');
        }
        
        const params = new URLSearchParams({
            generation: generation,
            limit: '200'
        });
        
        const response = await fetch(`/api/protected/generations?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + userPhone,
                'Content-Type': 'application/json'
            }
        });
        
        console.log('[DEBUG] 세대별 API 응답:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error('세대별 조회 실패: ' + errorText);
        }
        
        const data = await response.json();
        console.log('[DEBUG] 세대별 결과:', data);
        
        currentResults = data.results || [];
        displayGenerationResults(data);
        
    } catch (error) {
        console.error('[ERROR] 세대별 보기 오류:', error);
        showError('세대별 보기 중 오류가 발생했습니다: ' + error.message);
    }
}

// 검색 결과 표시
function displaySearchResults(data) {
    hideAllSections();
    
    const { query, type, total_count, results } = data;
    
    // 결과 제목 설정
    let typeText = '';
    switch (type) {
        case 'name': typeText = '이름'; break;
        case 'phone': typeText = '전화번호'; break;
        case 'address': typeText = '주소'; break;
        default: typeText = '전체';
    }
    
    document.getElementById('resultTitle').textContent = `"${query}" ${typeText} 검색 결과`;
    document.getElementById('resultCount').textContent = `${total_count}명`;
    
    if (total_count === 0) {
        document.getElementById('emptyResults').classList.remove('hidden');
        return;
    }
    
    // 테이블 생성
    displayResultTable(results);
    document.getElementById('searchResults').classList.remove('hidden');
}

// 세대별 결과 표시
function displayGenerationResults(data) {
    hideAllSections();
    
    const { generation, total_count, results } = data;
    
    // 결과 제목 설정
    const genText = generation === 'all' ? '전체 세대' : `${generation}세대`;
    document.getElementById('resultTitle').textContent = `${genText} 구성원`;
    document.getElementById('resultCount').textContent = `${total_count}명`;
    
    if (total_count === 0) {
        document.getElementById('emptyResults').classList.remove('hidden');
        return;
    }
    
    // 테이블 생성
    displayResultTable(results);
    document.getElementById('searchResults').classList.remove('hidden');
}

// 결과 테이블 생성
function displayResultTable(results) {
    const tbody = document.getElementById('resultTableBody');
    tbody.innerHTML = '';
    
    results.forEach(member => {
        const row = document.createElement('tr');
        
        // 이름 표시 (한자명 있으면 함께 표시)
        let nameDisplay = member.name;
        if (member.name_hanja && member.name_hanja !== member.name) {
            nameDisplay += `(${member.name_hanja})`;
        }
        
        // 부모 표시
        let parentDisplay = '-';
        if (member.parent && member.parent.name) {
            parentDisplay = member.parent.name;
            if (member.parent.name_hanja && member.parent.name_hanja !== member.parent.name) {
                parentDisplay += `(${member.parent.name_hanja})`;
            }
        }
        
        // 전화번호 표시
        const phoneDisplay = member.phone_number || '-';
        
        row.innerHTML = `
            <td class="font-medium">${member.generation}세대</td>
            <td>
                <div class="member-info">
                    <div class="member-name">${nameDisplay}</div>
                    ${member.is_deceased ? '<div class="text-xs text-red-600 mt-1"><i class="fas fa-cross mr-1"></i>고인</div>' : ''}
                </div>
            </td>
            <td>${parentDisplay}</td>
            <td class="text-sm">${phoneDisplay}</td>
            <td>
                <a href="/person/${member.person_code}" class="detail-btn">
                    <i class="fas fa-eye mr-1"></i>
                    상세보기
                </a>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

// UI 상태 관리
function showLoading() {
    hideAllSections();
    document.getElementById('loadingSection').classList.remove('hidden');
}

function showError(message) {
    hideAllSections();
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorSection').classList.remove('hidden');
}

function hideAllSections() {
    document.getElementById('loadingSection').classList.add('hidden');
    document.getElementById('searchResults').classList.add('hidden');
    document.getElementById('emptyResults').classList.add('hidden');
    document.getElementById('errorSection').classList.add('hidden');
}

// 로그인 확인
function checkLogin() {
    const userPhone = sessionStorage.getItem('userPhone');
    if (!userPhone) {
        console.log('[DEBUG] 로그인이 필요합니다.');
        return false;
    }
    return true;
}

// 페이지 진입 시 로그인 확인은 DOMContentLoaded에서 처리