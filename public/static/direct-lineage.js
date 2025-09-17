// direct-lineage.js - 직계혈통보기 프론트엔드

let lineageData = null;

// 페이지 로드시 초기화
document.addEventListener('DOMContentLoaded', function() {
    loadDirectLineage();
});

// 직계혈통 데이터 로드
async function loadDirectLineage() {
    try {
        showLoading();
        
        const token = sessionStorage.getItem('userPhone');
        if (!token) {
            showError('로그인이 필요합니다.');
            setTimeout(() => {
                window.location.href = '/';
            }, 2000);
            return;
        }
        
        const response = await fetch('/api/protected/direct-lineage', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            if (response.status === 401) {
                sessionStorage.removeItem('userPhone');
                sessionStorage.removeItem('userInfo');
                showError('로그인이 만료되었습니다. 다시 로그인해주세요.');
                setTimeout(() => {
                    window.location.href = '/';
                }, 2000);
                return;
            }
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.data) {
            lineageData = result.data;
            renderDirectLineage();
        } else {
            throw new Error('데이터 형식이 올바르지 않습니다.');
        }
        
    } catch (error) {
        console.error('직계혈통 로드 오류:', error);
        showError(`오류가 발생했습니다: ${error.message}`);
    }
}

// 로딩 표시
function showLoading() {
    document.getElementById('loadingSection').classList.remove('hidden');
    document.getElementById('lineageContent').classList.add('hidden');
    document.getElementById('errorSection').classList.add('hidden');
}

// 에러 표시
function showError(message) {
    document.getElementById('loadingSection').classList.add('hidden');
    document.getElementById('lineageContent').classList.add('hidden');
    document.getElementById('errorSection').classList.remove('hidden');
    document.getElementById('errorMessage').textContent = message;
}

// 직계혈통 렌더링
function renderDirectLineage() {
    if (!lineageData || !lineageData.lineage || lineageData.lineage.length === 0) {
        showError('직계혈통 정보가 없습니다.');
        return;
    }

    // 요약 정보 업데이트
    const summaryElement = document.getElementById('generationSummary');
    summaryElement.textContent = `총 ${lineageData.total_generations}대에 걸친 직계혈통`;

    // 직계혈통 목록 생성
    const listContainer = document.getElementById('lineageList');
    listContainer.innerHTML = '';

    lineageData.lineage.forEach((person, index) => {
        const card = createPersonCard(person, index);
        listContainer.appendChild(card);
    });

    // 콘텐츠 표시
    document.getElementById('loadingSection').classList.add('hidden');
    document.getElementById('errorSection').classList.add('hidden');
    document.getElementById('lineageContent').classList.remove('hidden');
}

// 개인 카드 생성
function createPersonCard(person, index) {
    const isCurrentUser = person.person_code === lineageData.current_person;
    
    const card = document.createElement('div');
    card.className = `generation-card ${isCurrentUser ? 'current-person' : ''}`;
    
    // 생몰년도 계산
    const birthYear = person.birth_date ? formatDate(person.birth_date) : '';
    const deathYear = person.death_date ? formatDate(person.death_date) : '';
    let lifeSpan = '';
    
    if (birthYear) {
        if (person.is_deceased && deathYear) {
            lifeSpan = `${birthYear} - ${deathYear}`;
        } else if (person.is_deceased) {
            lifeSpan = `${birthYear} - ?`;
        } else {
            lifeSpan = `${birthYear} -`;
        }
    }
    
    // 세대 표시 (1세조, 2세조, ... 현재)
    let generationTitle = `${person.generation}세`;
    if (index === 0) {
        generationTitle = `시조 (${person.generation}세)`;
    } else if (isCurrentUser) {
        generationTitle = `나 (${person.generation}세)`;
    }
    
    card.innerHTML = `
        <div class="generation-header">
            <span>${generationTitle}</span>
            <span class="generation-number">${index + 1}대</span>
        </div>
        <div class="person-info">
            <div class="person-name">
                <span>${person.name}</span>
                ${person.name_hanja ? `<span class="person-hanja">(${person.name_hanja})</span>` : ''}
                ${isCurrentUser ? '<i class="fas fa-star text-yellow-500" title="나"></i>' : ''}
            </div>
            
            <div class="person-details">
                ${person.gender ? `
                    <div class="detail-item">
                        <i class="fas ${person.gender === 'M' ? 'fa-mars text-blue-500' : 'fa-venus text-pink-500'} detail-icon"></i>
                        <span>${person.gender === 'M' ? '남' : '여'}</span>
                    </div>
                ` : ''}
                
                ${lifeSpan ? `
                    <div class="detail-item">
                        <i class="fas fa-calendar detail-icon"></i>
                        <span>${lifeSpan}</span>
                    </div>
                ` : ''}
                

                
                ${person.phone_number ? `
                    <div class="detail-item">
                        <i class="fas fa-phone detail-icon"></i>
                        <span>${formatPhoneNumber(person.phone_number)}</span>
                    </div>
                ` : ''}
            </div>
            
            <div class="action-buttons">
                <a href="/person/${person.person_code}" class="btn btn-primary">
                    <i class="fas fa-user"></i>
                    상세보기
                </a>
                
                <a href="/lineage?focus=${person.person_code}" class="btn btn-outline">
                    <i class="fas fa-project-diagram"></i>
                    계통도
                </a>
            </div>
        </div>
    `;
    
    return card;
}

// 날짜 포맷팅
function formatDate(dateStr) {
    if (!dateStr) return '';
    
    try {
        // YYYY-MM-DD 또는 YYYY 형식 처리
        if (dateStr.includes('-')) {
            const [year, month, day] = dateStr.split('-');
            return `${year}년`;
        } else if (dateStr.length === 4) {
            return `${dateStr}년`;
        }
        return dateStr;
    } catch (error) {
        return dateStr;
    }
}

// 전화번호 포맷팅
function formatPhoneNumber(phone) {
    if (!phone) return '';
    
    // 010-1234-5678 형식으로 포맷팅
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    if (cleanPhone.length === 11 && cleanPhone.startsWith('010')) {
        return `${cleanPhone.slice(0,3)}-${cleanPhone.slice(3,7)}-${cleanPhone.slice(7)}`;
    }
    return phone;
}

// 텍스트 자르기
function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

// 새로고침
function refreshLineage() {
    loadDirectLineage();
}

// 스크롤 애니메이션 (부드러운 스크롤)
function smoothScrollTo(element) {
    element.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
}

// 현재 사용자로 스크롤
function scrollToCurrentUser() {
    const currentUserCard = document.querySelector('.current-person');
    if (currentUserCard) {
        setTimeout(() => {
            smoothScrollTo(currentUserCard);
        }, 500);
    }
}

// 카드 클릭 시 상세보기로 이동
document.addEventListener('click', function(e) {
    const card = e.target.closest('.generation-card');
    if (card && !e.target.closest('.action-buttons')) {
        const personCode = card.querySelector('a[href^="/person/"]')?.getAttribute('href')?.split('/')[2];
        if (personCode) {
            window.location.href = `/person/${personCode}`;
        }
    }
});

// 페이지 로드 완료 후 현재 사용자로 스크롤
window.addEventListener('load', function() {
    if (lineageData && lineageData.current_person) {
        scrollToCurrentUser();
    }
});