// 상세보기 페이지 JavaScript

// 상세 정보 로드
async function loadPersonDetail() {
    console.log('[DEBUG] 상세 정보 로드 시작:', personCode);
    
    // 테스트용 자동 로그인 (개발 환경에서만)
    if (!sessionStorage.getItem('userPhone')) {
        console.log('[DEBUG] 테스트용 자동 로그인: 상세보기 페이지');
        sessionStorage.setItem('userPhone', '010-9272-9081');
    }
    
    if (!checkLogin()) {
        return;
    }
    
    try {
        const userPhone = sessionStorage.getItem('userPhone');
        
        const response = await fetch(`/api/protected/person/${personCode}`, {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + userPhone,
                'Content-Type': 'application/json'
            }
        });
        
        console.log('[DEBUG] 상세보기 API 응답:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error('상세정보 조회 실패: ' + errorText);
        }
        
        const data = await response.json();
        console.log('[DEBUG] 상세정보 데이터:', data);
        
        currentPersonData = data;
        displayPersonDetail(data);
        
    } catch (error) {
        console.error('[ERROR] 상세정보 로드 오류:', error);
        showError('상세정보를 불러오는 중 오류가 발생했습니다: ' + error.message);
    }
}

// 상세 정보 표시
function displayPersonDetail(data) {
    hideLoading();
    
    const { person, parent, grandparent, children, siblings, relationship_with_user, statistics } = data;
    
    // 페이지 제목 설정
    let titleDisplay = person.name;
    if (person.name_hanja && person.name_hanja !== person.name) {
        titleDisplay += `(${person.name_hanja})`;
    }
    titleDisplay += ` - ${person.generation}세대`;
    document.getElementById('personTitle').textContent = titleDisplay;
    document.title = `${titleDisplay} - 창녕조씨 강릉파보`;
    
    // 네비게이션 버튼 설정
    setupNavigationButtons(parent, grandparent, children);
    
    // 기본 정보 표시
    displayBasicInfo(person);
    
    // 형제자매 표시
    displaySiblings(siblings);
    
    // 자식들 정보 저장 (토글용)
    window.childrenData = children;
    
    // 관계 정보 저장
    window.relationshipData = relationship_with_user;
    
    document.getElementById('detailContent').classList.remove('hidden');
}

// 네비게이션 버튼 설정
function setupNavigationButtons(parent, grandparent, children) {
    // 조부님 버튼
    const grandparentBtn = document.getElementById('grandparentBtn');
    if (grandparent) {
        grandparentBtn.href = `/person/${grandparent.person_code}`;
        grandparentBtn.classList.remove('hidden');
        grandparentBtn.innerHTML = `<i class="fas fa-arrow-up"></i> 조부님 (${grandparent.name})`;
    }
    
    // 부친 버튼
    const parentBtn = document.getElementById('parentBtn');
    if (parent) {
        parentBtn.href = `/person/${parent.person_code}`;
        parentBtn.classList.remove('hidden');
        parentBtn.innerHTML = `<i class="fas fa-arrow-up"></i> 부친 (${parent.name})`;
    }
    
    // 자식보기 버튼
    const childrenBtn = document.getElementById('childrenBtn');
    if (children && children.length > 0) {
        childrenBtn.classList.remove('hidden');
        childrenBtn.innerHTML = `<i class="fas fa-arrow-down"></i> 자식보기 (${children.length}명)`;
    }
}

// 기본 정보 표시
function displayBasicInfo(person) {
    const basicInfo = document.getElementById('basicInfo');
    basicInfo.innerHTML = '';
    
    const infos = [
        { label: '이름', value: person.name },
        { label: '한자명', value: person.name_hanja || '-' },
        { label: '세대', value: `${person.generation}세대` },
        { label: '성별', value: person.gender === 1 ? '남성' : '여성' },
        { label: '형제순서', value: `${person.sibling_order}번째` },
        { label: '생년월일', value: person.birth_date || '-' },
        { label: '기일', value: person.death_date || (person.is_deceased ? '미상' : '-') },
        { label: '생존여부', value: person.is_deceased ? '고인' : '생존' },
        { label: '전화번호', value: person.phone_number || '-' },
        { label: '이메일', value: person.email || '-' }
    ];
    
    // 주소 정보 처리
    if (person.home_address) {
        try {
            const homeAddr = typeof person.home_address === 'string' 
                ? JSON.parse(person.home_address) 
                : person.home_address;
            if (homeAddr.address) {
                infos.push({ label: '자택주소', value: homeAddr.address });
            }
        } catch (e) {
            if (typeof person.home_address === 'string' && person.home_address.trim()) {
                infos.push({ label: '자택주소', value: person.home_address });
            }
        }
    }
    
    if (person.work_address) {
        try {
            const workAddr = typeof person.work_address === 'string' 
                ? JSON.parse(person.work_address) 
                : person.work_address;
            if (workAddr.address) {
                infos.push({ label: '직장주소', value: workAddr.address });
            }
        } catch (e) {
            if (typeof person.work_address === 'string' && person.work_address.trim()) {
                infos.push({ label: '직장주소', value: person.work_address });
            }
        }
    }
    
    if (person.biography) {
        infos.push({ label: '약력', value: person.biography });
    }
    
    // HTML 생성
    infos.forEach(info => {
        if (info.value && info.value !== '-') {
            const div = document.createElement('div');
            div.className = 'info-item';
            div.innerHTML = `
                <div class="info-label">${info.label}</div>
                <div class="info-value">${info.value}</div>
            `;
            basicInfo.appendChild(div);
        }
    });
}

// 형제자매 표시
function displaySiblings(siblings) {
    const siblingsSection = document.getElementById('siblingsSection');
    const siblingsList = document.getElementById('siblingsList');
    
    if (!siblings || siblings.length === 0) {
        siblingsSection.classList.add('hidden');
        return;
    }
    
    siblingsSection.classList.remove('hidden');
    siblingsList.innerHTML = '';
    
    siblings.forEach(sibling => {
        const div = document.createElement('div');
        div.className = 'family-member';
        
        let nameDisplay = sibling.name;
        if (sibling.name_hanja && sibling.name_hanja !== sibling.name) {
            nameDisplay += `(${sibling.name_hanja})`;
        }
        
        const genderIcon = sibling.gender === 1 ? 'fas fa-mars text-blue-600' : 'fas fa-venus text-pink-600';
        const statusText = sibling.is_deceased ? '고인' : '생존';
        const statusClass = sibling.is_deceased ? 'text-red-600' : 'text-green-600';
        
        div.innerHTML = `
            <div class="member-info">
                <div class="member-name">
                    <i class="${genderIcon} mr-1"></i>
                    ${nameDisplay}
                </div>
                <div class="member-details">
                    ${sibling.sibling_order}번째 • <span class="${statusClass}">${statusText}</span>
                    ${sibling.birth_date ? ` • ${sibling.birth_date}` : ''}
                </div>
            </div>
            <a href="/person/${sibling.person_code}" class="nav-btn outline">
                <i class="fas fa-eye mr-1"></i>
                보기
            </a>
        `;
        
        siblingsList.appendChild(div);
    });
}

// 자식보기 토글
function toggleChildren() {
    const childrenSection = document.getElementById('childrenSection');
    const childrenList = document.getElementById('childrenList');
    
    if (childrenSection.classList.contains('hidden')) {
        // 자식 목록 표시
        const children = window.childrenData || [];
        
        childrenList.innerHTML = '';
        children.forEach(child => {
            const div = document.createElement('div');
            div.className = 'family-member';
            
            let nameDisplay = child.name;
            if (child.name_hanja && child.name_hanja !== child.name) {
                nameDisplay += `(${child.name_hanja})`;
            }
            
            const genderIcon = child.gender === 1 ? 'fas fa-mars text-blue-600' : 'fas fa-venus text-pink-600';
            const statusText = child.is_deceased ? '고인' : '생존';
            const statusClass = child.is_deceased ? 'text-red-600' : 'text-green-600';
            
            div.innerHTML = `
                <div class="member-info">
                    <div class="member-name">
                        <i class="${genderIcon} mr-1"></i>
                        ${nameDisplay}
                    </div>
                    <div class="member-details">
                        ${child.sibling_order}번째 • <span class="${statusClass}">${statusText}</span>
                        ${child.birth_date ? ` • ${child.birth_date}` : ''}
                    </div>
                </div>
                <a href="/person/${child.person_code}" class="nav-btn outline">
                    <i class="fas fa-eye mr-1"></i>
                    보기
                </a>
            `;
            
            childrenList.appendChild(div);
        });
        
        childrenSection.classList.remove('hidden');
        document.getElementById('childrenBtn').innerHTML = '<i class="fas fa-arrow-up"></i> 자식숨기기';
    } else {
        // 자식 목록 숨기기
        childrenSection.classList.add('hidden');
        document.getElementById('childrenBtn').innerHTML = `<i class="fas fa-arrow-down"></i> 자식보기 (${window.childrenData?.length || 0}명)`;
    }
}

// 나와의 관계 표시
function showRelationship() {
    const relationshipSection = document.getElementById('relationshipSection');
    const relationshipInfo = document.getElementById('relationshipInfo');
    
    if (relationshipSection.classList.contains('hidden')) {
        // 관계 정보 표시
        const relationship = window.relationshipData;
        
        relationshipInfo.innerHTML = '';
        
        if (relationship) {
            const div = document.createElement('div');
            div.className = 'info-grid';
            
            div.innerHTML = `
                <div class="info-item">
                    <div class="info-label">세대 차이</div>
                    <div class="info-value">${relationship.generation_difference}세대</div>
                </div>
                <div class="info-item">
                    <div class="info-label">관계</div>
                    <div class="info-value">${relationship.estimated_relationship}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">동일 세대</div>
                    <div class="info-value">${relationship.is_same_generation ? '예' : '아니오'}</div>
                </div>
            `;
            
            relationshipInfo.appendChild(div);
        } else {
            relationshipInfo.innerHTML = '<p class="text-gray-600">본인입니다.</p>';
        }
        
        relationshipSection.classList.remove('hidden');
        document.getElementById('relationshipBtn').innerHTML = '<i class="fas fa-heart"></i> 관계숨기기';
    } else {
        // 관계 정보 숨기기
        relationshipSection.classList.add('hidden');
        document.getElementById('relationshipBtn').innerHTML = '<i class="fas fa-heart"></i> 나와의 관계';
    }
}

// UI 상태 관리
function showError(message) {
    document.getElementById('loadingSection').classList.add('hidden');
    document.getElementById('detailContent').classList.add('hidden');
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorSection').classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loadingSection').classList.add('hidden');
}

// 로그인 확인
function checkLogin() {
    const userPhone = sessionStorage.getItem('userPhone');
    if (!userPhone) {
        showError('로그인이 필요합니다.');
        return false;
    }
    return true;
}