// 계통도 페이지 JavaScript - 확장 기능 포함

let currentFocus = null; // 현재 포커스된 인물
let currentData = null; // 현재 계통도 데이터

// 페이지 로드 시 계통도 데이터 로드
document.addEventListener('DOMContentLoaded', function() {
    // 테스트용 자동 로그인 (개발 환경에서만)
    if (!sessionStorage.getItem('userPhone')) {
        console.log('[DEBUG] 테스트용 자동 로그인: 닥터조님');
        sessionStorage.setItem('userPhone', '010-9272-9081');
    }
    
    loadLineage();
});

// 간소화된 가계도 데이터 로드 - 직계가족 중심
async function loadLineage(focusPersonCode = null) {
    // sessionStorage에서 사용자 정보 확인
    let userPhone = sessionStorage.getItem('userPhone');
    
    if (!userPhone && typeof currentUser !== 'undefined' && currentUser) {
        userPhone = currentUser.phone;
        sessionStorage.setItem('userPhone', userPhone);
    }
    
    if (!userPhone) {
        showError('로그인이 필요합니다. 메인 페이지에서 로그인해주세요.');
        
        // 로그인 버튼 추가
        const errorSection = document.getElementById('errorSection');
        const loginButton = document.createElement('a');
        loginButton.href = '/';
        loginButton.className = 'mt-3 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm inline-block';
        loginButton.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>로그인하러 가기';
        errorSection.appendChild(loginButton);
        return;
    }

    try {
        showLoading();
        console.log('[DEBUG] 간소화된 계통도 API 호출 시작 - focus:', focusPersonCode || '441258');

        // URL 파라미터 구성 - 4촌까지 모든 친척 표시
        const params = new URLSearchParams();
        const targetPersonCode = focusPersonCode || '441258'; // 기본은 닥터조님
        params.append('focus', targetPersonCode);
        params.append('up', '4'); // 4세대 위까지 (고조부모까지)
        params.append('down', '4'); // 4세대 아래까지 (현손까지)  
        params.append('siblings', 'true'); // 형제자매 포함

        const response = await fetch('/api/protected/lineage?' + params.toString(), {
            method: 'GET',
            headers: { 
                'Authorization': 'Bearer ' + userPhone,
                'Content-Type': 'application/json'
            }
        });

        console.log('[DEBUG] 계통도 API 응답:', response.status);

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error('HTTP ' + response.status + ': ' + errorText);
        }

        const data = await response.json();
        console.log('[DEBUG] 계통도 데이터:', data);
        
        currentData = data;
        currentFocus = focusPersonCode || data.current_user.person_code;
        
        displayLineage(data);
        hideLoading();

    } catch (error) {
        console.error('[ERROR] 계통도 로드 오류:', error);
        showError('계통도를 불러오는 중 오류가 발생했습니다: ' + error.message);
    }
}

// 계통도 표시
function displayLineage(data) {
    const { 
        focus_person, 
        focus_spouses, 
        focus_children, 
        current_user, 
        direct_ancestors, 
        direct_descendants, 
        collateral_relatives, 
        statistics,
        expand_info 
    } = data;

    console.log('[DEBUG] 8촌 데이터:', {
        direct_ancestors: direct_ancestors?.length || 0,
        direct_descendants: direct_descendants?.length || 0,
        collateral_relatives: collateral_relatives?.length || 0,
        statistics: statistics
    });

    // 사용자 정보 업데이트
    document.getElementById('currentUserName').textContent = focus_person.name;
    document.getElementById('currentUserInfo').textContent = 
        focus_person.generation + '세대 • ' + focus_person.name + '(' + (focus_person.name_hanja || '') + ')';
    
    // 8촌 통계 정보 표시
    let ancestorInfo = '';
    if (direct_ancestors && direct_ancestors.length > 0) {
        ancestorInfo = direct_ancestors.length + '세대 직계';
    }
    if (statistics && statistics.collateral_count > 0) {
        ancestorInfo += (ancestorInfo ? ', ' : '') + statistics.collateral_count + '명 방계';
    }
    if (statistics && statistics.total_relatives) {
        ancestorInfo += ' (총 ' + statistics.total_relatives + '명)';
    }
    document.getElementById('ancestorsCount').textContent = ancestorInfo || '0세대';

    // 계통도 트리 생성
    const treeContainer = document.getElementById('lineageTree');
    treeContainer.innerHTML = '';

    // 촌수별 통계 표시
    if (statistics && statistics.kinship_distribution) {
        const statsContainer = document.createElement('div');
        statsContainer.className = 'mb-6 p-4 bg-blue-50 rounded-lg border';
        
        let statsHtml = '<div class="text-sm font-semibold text-blue-800 mb-2">촌수별 친척 현황</div>';
        statsHtml += '<div class="grid grid-cols-4 gap-2 text-xs">';
        
        for (let i = 1; i <= 8; i++) {
            const count = statistics.kinship_distribution[i] || 0;
            if (count > 0) {
                statsHtml += `<div class="bg-white p-2 rounded border text-center">`;
                statsHtml += `<div class="font-semibold text-blue-600">${i}촌</div>`;
                statsHtml += `<div class="text-gray-600">${count}명</div>`;
                statsHtml += `</div>`;
            }
        }
        
        statsHtml += '</div>';
        statsContainer.innerHTML = statsHtml;
        treeContainer.appendChild(statsContainer);
    }

    // 가족 트리 구조를 위한 데이터 준비
    const allPersons = [];
    const personMap = new Map(); // person_code -> person 매핑
    
    // 직계 조상들 추가
    if (direct_ancestors) {
        direct_ancestors.forEach(person => {
            const enhancedPerson = {...person, type: 'direct_ancestor', display_type: 'ancestor'};
            allPersons.push(enhancedPerson);
            personMap.set(person.person_code, enhancedPerson);
        });
    }
    
    // 포커스 인물 추가
    const focusPerson = {...focus_person, type: 'focus', display_type: 'focus'};
    allPersons.push(focusPerson);
    personMap.set(focus_person.person_code, focusPerson);
    
    // 직계 후손들 추가
    if (direct_descendants) {
        direct_descendants.forEach(person => {
            const enhancedPerson = {...person, type: 'direct_descendant', display_type: 'descendant'};
            allPersons.push(enhancedPerson);
            personMap.set(person.person_code, enhancedPerson);
        });
    }
    
    // 방계 친척들 추가
    if (collateral_relatives) {
        collateral_relatives.forEach(person => {
            // 촌수에 따른 표시 유형 결정
            let display_type = 'collateral';
            if (person.kinship_distance === 2) display_type = 'sibling';
            else if (person.kinship_distance === 3) display_type = 'uncle_nephew';
            else if (person.kinship_distance === 4) display_type = 'cousin';
            
            const enhancedPerson = {...person, type: 'collateral', display_type};
            allPersons.push(enhancedPerson);
            personMap.set(person.person_code, enhancedPerson);
        });
    }

    // 부모-자식 관계 매핑 생성
    const parentChildMap = new Map(); // parent_code -> [children]
    const childParentMap = new Map(); // child_code -> parent_code
    
    allPersons.forEach(person => {
        if (person.parent_code) {
            childParentMap.set(person.person_code, person.parent_code);
            
            if (!parentChildMap.has(person.parent_code)) {
                parentChildMap.set(person.parent_code, []);
            }
            parentChildMap.get(person.parent_code).push(person);
        }
    });
    
    console.log('[DEBUG] 가족 관계 매핑:', {
        총인원: allPersons.length,
        부모자식관계: parentChildMap.size,
        자식부모관계: childParentMap.size
    });
    
    // 부모-자식 관계 상세 로그
    console.log('[DEBUG] parentChildMap 내용:');
    parentChildMap.forEach((children, parentCode) => {
        const parent = personMap.get(parentCode);
        console.log(`  ${parent?.name || parentCode} -> ${children.map(c => c.name).join(', ')}`);
    });

    // 가족 트리 구조로 렌더링
    renderFamilyTree(treeContainer, allPersons, personMap, parentChildMap, childParentMap, focusPerson);
}

// 4촌까지 확장된 가족 중심 렌더링
function renderFamilyTree(container, allPersons, personMap, parentChildMap, childParentMap, focusPerson) {
    console.log('[DEBUG] 4촌까지 확장된 가계도 렌더링 시작 - 포커스:', focusPerson.name);
    
    // 4촌까지 가족 필터링
    const coreFamily = filterCoreFamily(allPersons, focusPerson, parentChildMap, childParentMap);
    
    console.log('[DEBUG] 핵심 가족구성원:', coreFamily.map(p => `${p.name}(${p.generation}세대, ${p.relationship_type})`));
    
    // 제목 섹션
    const titleSection = document.createElement('div');
    titleSection.style.cssText = `
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border-radius: 12px;
        color: white;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    `;
    titleSection.innerHTML = `
        <h2 style="margin: 0; font-size: 24px; font-weight: bold;">
            👨‍👩‍👧‍👦 ${focusPerson.name}님 4촌 가족도
        </h2>
        <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">
            🔗 카드를 클릭하면 해당 인물 중심으로 확장됩니다
        </p>
        <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.8;">
            💡 1촌(부모/자녀) → 2촌(조부모/형제/손자녀) → 3촌(삼촌/조카) → 4촌(사촌)
        </p>
    `;
    container.appendChild(titleSection);
    
    // 확장된 가족 트리 컨테이너 (PC에서 더 넓게)
    const isDesktop = window.innerWidth >= 1024;
    const isWideScreen = window.innerWidth >= 1440;
    
    const familyTreeContainer = document.createElement('div');
    familyTreeContainer.style.cssText = `
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: ${isWideScreen ? '50px' : '40px'};
        padding: ${isWideScreen ? '40px 30px' : '30px 20px'};
        background: #fafafa;
        border-radius: 12px;
        min-height: ${isWideScreen ? '800px' : '500px'};
        max-width: ${isWideScreen ? '1800px' : (isDesktop ? '1400px' : '100%')};
        margin: 0 auto;
        overflow-x: auto;
    `;
    
    // SVG 연결선 오버레이
    const svgOverlay = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svgOverlay.style.cssText = `
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 5;
        overflow: visible;
    `;
    svgOverlay.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    
    const cardPositions = new Map();
    
    // 4촌까지 세대별로 그룹화
    const generationGroups = {};
    coreFamily.forEach(person => {
        if (!generationGroups[person.generation]) {
            generationGroups[person.generation] = [];
        }
        generationGroups[person.generation].push(person);
    });
    
    // 세대 순서로 정렬 (조상 → 본인 → 후손)
    const sortedGenerations = Object.keys(generationGroups).map(gen => parseInt(gen)).sort((a, b) => a - b);
    
    console.log(`[DEBUG] 표시할 세대: ${sortedGenerations.join('세대, ')}세대`);
    
    sortedGenerations.forEach(generation => {
        const genContainer = createSimpleGenerationRow(
            generationGroups[generation], 
            generation, 
            focusPerson,
            cardPositions
        );
        familyTreeContainer.appendChild(genContainer);
        
        // 세대 간 간격 추가
        if (generation !== sortedGenerations[sortedGenerations.length - 1]) {
            const spacer = document.createElement('div');
            spacer.style.cssText = 'height: 20px;';
            familyTreeContainer.appendChild(spacer);
        }
    });
    
    familyTreeContainer.appendChild(svgOverlay);
    
    // 간소화된 연결선 그리기
    setTimeout(() => {
        drawSimpleConnectionLines(svgOverlay, coreFamily, focusPerson, cardPositions, parentChildMap);
    }, 100);
    
    container.appendChild(familyTreeContainer);
}

// 4촌까지 모든 친척 한번에 표시 - 확장된 필터링
function filterCoreFamily(allPersons, focusPerson, parentChildMap, childParentMap) {
    const coreFamily = [];
    const focusCode = focusPerson.person_code;
    const addedPersons = new Set(); // 중복 방지
    
    // 1. 본인 추가
    coreFamily.push({...focusPerson, relationship_type: 'self', kinship_level: 0});
    addedPersons.add(focusCode);
    
    // 2. 직계 조상들 (부모, 조부모, 증조부모 등) - API에서 가져온 모든 직계 조상
    allPersons.forEach(person => {
        if (person.type === 'direct_ancestor' && !addedPersons.has(person.person_code)) {
            let relationship_type = 'ancestor';
            let kinship_level = Math.abs(person.generation - focusPerson.generation);
            
            if (kinship_level === 1) relationship_type = 'parent';
            else if (kinship_level === 2) relationship_type = 'grandparent';
            else if (kinship_level === 3) relationship_type = 'great_grandparent';
            else relationship_type = 'ancestor';
            
            if (kinship_level <= 4) { // 4촌까지만
                coreFamily.push({...person, relationship_type, kinship_level});
                addedPersons.add(person.person_code);
            }
        }
    });
    
    // 3. 직계 후손들 (자녀, 손자녀 등)
    allPersons.forEach(person => {
        if (person.type === 'direct_descendant' && !addedPersons.has(person.person_code)) {
            let relationship_type = 'descendant';
            let kinship_level = Math.abs(person.generation - focusPerson.generation);
            
            if (kinship_level === 1) relationship_type = 'child';
            else if (kinship_level === 2) relationship_type = 'grandchild';
            else if (kinship_level === 3) relationship_type = 'great_grandchild';
            else relationship_type = 'descendant';
            
            if (kinship_level <= 4) { // 4촌까지만
                coreFamily.push({...person, relationship_type, kinship_level});
                addedPersons.add(person.person_code);
            }
        }
    });
    
    // 4. 방계 친척들 - API에서 가져온 모든 방계 친척 중 4촌까지
    allPersons.forEach(person => {
        if (person.type === 'collateral' && person.kinship_distance <= 4 && !addedPersons.has(person.person_code)) {
            let relationship_type = 'relative';
            let kinship_level = person.kinship_distance;
            
            // 촌수별 관계 타입 결정
            if (kinship_level === 2) {
                // 2촌: 형제자매, 조부모, 손자녀
                if (person.generation === focusPerson.generation) {
                    relationship_type = 'sibling';
                } else if (person.generation < focusPerson.generation) {
                    relationship_type = 'grandparent';
                } else {
                    relationship_type = 'grandchild';
                }
            } else if (kinship_level === 3) {
                // 3촌: 삼촌/고모, 조카
                if (person.generation < focusPerson.generation) {
                    relationship_type = 'uncle';
                } else if (person.generation > focusPerson.generation) {
                    relationship_type = 'nephew';
                } else {
                    relationship_type = 'relative'; // 같은 세대 3촌
                }
            } else if (kinship_level === 4) {
                // 4촌: 사촌
                if (person.generation === focusPerson.generation) {
                    relationship_type = 'cousin';
                } else {
                    relationship_type = 'relative'; // 다른 세대 4촌
                }
            }
            
            coreFamily.push({...person, relationship_type, kinship_level});
            addedPersons.add(person.person_code);
        }
    });
    
    console.log(`[DEBUG] ${focusPerson.name} 4촌까지 전체 가족: 총 ${coreFamily.length}명`);
    console.log(`[DEBUG] 촌수별 분포: 0촌 1명, 1촌 ${coreFamily.filter(p => p.kinship_level === 1).length}명, 2촌 ${coreFamily.filter(p => p.kinship_level === 2).length}명, 3촌 ${coreFamily.filter(p => p.kinship_level === 3).length}명, 4촌 ${coreFamily.filter(p => p.kinship_level === 4).length}명`);
    
    return coreFamily;
}

// PC 반응형 세대별 행 생성
function createSimpleGenerationRow(persons, generation, focusPerson, cardPositions) {
    const rowContainer = document.createElement('div');
    rowContainer.className = 'simple-generation-row';
    rowContainer.setAttribute('data-generation', generation);
    
    // PC용 확장된 반응형 스타일
    const isDesktop = window.innerWidth >= 1024;
    const isWideScreen = window.innerWidth >= 1440;
    const maxWidth = isWideScreen ? '1800px' : (isDesktop ? '1400px' : '100%');
    const gap = isWideScreen ? '15px' : (isDesktop ? '20px' : '15px');
    
    rowContainer.style.cssText = `
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: ${gap};
        flex-wrap: wrap;
        z-index: 10;
        min-height: 160px;
        max-width: ${maxWidth};
        margin: 0 auto;
        padding: 0 20px;
    `;
    
    // 세대 레이블
    const generationLabel = document.createElement('div');
    generationLabel.style.cssText = `
        position: absolute;
        top: -20px;
        left: 0;
        background: #6366f1;
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
        z-index: 15;
    `;
    generationLabel.textContent = `${generation}세대`;
    rowContainer.appendChild(generationLabel);
    
    // 촌수와 관계별로 정렬 (4촌까지)
    const relationshipOrder = ['grandparent', 'parent', 'uncle', 'self', 'sibling', 'cousin', 'child', 'nephew', 'grandchild'];
    persons.sort((a, b) => {
        // 먼저 촌수로 정렬
        if (a.kinship_level !== b.kinship_level) {
            return (a.kinship_level || 0) - (b.kinship_level || 0);
        }
        // 같은 촌수 내에서 관계별 정렬
        const aIndex = relationshipOrder.indexOf(a.relationship_type);
        const bIndex = relationshipOrder.indexOf(b.relationship_type);
        if (aIndex !== bIndex) return aIndex - bIndex;
        // 같은 관계 내에서 형제 순서로 정렬
        return (a.sibling_order || 0) - (b.sibling_order || 0);
    });
    
    persons.forEach(person => {
        const cardWrapper = document.createElement('div');
        cardWrapper.id = `simple-card-${person.person_code}`;
        cardWrapper.style.cssText = 'position: relative; display: flex; flex-direction: column; align-items: center;';
        
        const personCard = createClickableCard(person, focusPerson);
        cardWrapper.appendChild(personCard);
        
        rowContainer.appendChild(cardWrapper);
        
        // 카드 위치 저장
        cardPositions.set(person.person_code, {
            element: cardWrapper,
            person: person
        });
    });
    
    return rowContainer;
}

// 이미지와 동일한 스타일의 가족 카드 생성 (기존 함수 - 사용 안 함)
function createFamilyCard(person, focusPerson) {
    const rowContainer = document.createElement('div');
    rowContainer.className = 'generation-row';
    rowContainer.setAttribute('data-generation', generation);
    rowContainer.style.cssText = `
        position: relative;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        gap: 30px;
        flex-wrap: wrap;
        z-index: 10;
        min-height: 160px;
    `;
    
    // 형제 순서로 정렬
    persons.sort((a, b) => (a.sibling_order || 0) - (b.sibling_order || 0));
    
    // 세대 제목 추가
    const generationTitle = document.createElement('div');
    generationTitle.style.cssText = `
        position: absolute;
        top: -30px;
        left: 50%;
        transform: translateX(-50%);
        background: #16a34a;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
        z-index: 15;
    `;
    generationTitle.textContent = `${generation}세대 (${persons.length}명)`;
    rowContainer.appendChild(generationTitle);
    
    persons.forEach((person, personIndex) => {
        const cardWrapper = document.createElement('div');
        cardWrapper.id = `card-wrapper-${person.person_code}`;
        cardWrapper.style.cssText = 'position: relative; display: flex; flex-direction: column; align-items: center;';
        
        const personCard = createFamilyCard(person, focusPerson);
        personCard.id = `card-${person.person_code}`;
        cardWrapper.appendChild(personCard);
        
        // 자식 정보 표시
        const children = parentChildMap.get(person.person_code) || [];
        if (children.length > 0) {
            console.log(`[DEBUG] ✅ ${person.name}에게 ${children.length}명의 자식이 있음:`, children.map(c => c.name));
            
            // 자식 연결 표시기 추가
            const childrenIndicator = document.createElement('div');
            childrenIndicator.style.cssText = `
                margin-top: 8px;
                background: #dc2626;
                color: white;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            `;
            childrenIndicator.textContent = `자녀 ${children.length}명`;
            cardWrapper.appendChild(childrenIndicator);
        }
        
        rowContainer.appendChild(cardWrapper);
        
        // 카드 위치 저장 (SVG 연결선용)
        cardPositions.set(person.person_code, {
            element: cardWrapper,
            person: person,
            generation: generation,
            children: children
        });
    });
    
    return rowContainer;
}

// 4촌까지 확장된 연결선 그리기
function drawSimpleConnectionLines(svgOverlay, coreFamily, focusPerson, cardPositions, parentChildMap) {
    console.log('[DEBUG] 4촌까지 확장된 연결선 그리기 시작');
    
    // SVG 크기 설정
    const containerRect = svgOverlay.parentElement.getBoundingClientRect();
    svgOverlay.style.width = '100%';
    svgOverlay.style.height = '100%';
    svgOverlay.setAttribute('viewBox', `0 0 ${containerRect.width} ${containerRect.height}`);
    
    let lineCount = 0;
    
    // parentChildMap을 사용한 정확한 부모-자식 연결선만 그리기
    coreFamily.forEach(person => {
        const actualChildren = parentChildMap.get(person.person_code) || [];
        const childrenInFamily = actualChildren.filter(child => 
            coreFamily.some(member => member.person_code === child.person_code)
        );
        
        if (childrenInFamily.length > 0) {
            // 촌수에 따른 연결선 색상과 굵기 결정
            let color, strokeWidth;
            if (person.kinship_level === 1 || (person.relationship_type === 'parent' || person.relationship_type === 'self')) {
                // 1촌 관계 (부모→자녀, 본인→자녀)
                color = '#16a34a'; // 녹색
                strokeWidth = 4;
            } else if (person.kinship_level === 2) {
                // 2촌 관계 (조부모→부모, 자녀→손자녀)
                color = '#3b82f6'; // 파란색
                strokeWidth = 3;
            } else if (person.kinship_level === 3) {
                // 3촌 관계 (삼촌→사촌, 형제→조카)
                color = '#a855f7'; // 보라색
                strokeWidth = 2;
            } else {
                // 기타
                color = '#64748b'; // 회색
                strokeWidth = 2;
            }
            
            childrenInFamily.forEach(child => {
                const childInFamily = coreFamily.find(member => member.person_code === child.person_code);
                if (childInFamily) {
                    drawSimpleLine(svgOverlay, person, childInFamily, cardPositions, color, strokeWidth);
                    lineCount++;
                    console.log(`[DEBUG] ✅ 정확한 연결선: ${person.name} → ${childInFamily.name} (${person.kinship_level}촌 관계)`);
                }
            });
        }
    });
    
    console.log(`[DEBUG] 4촌까지 확장된 연결선 ${lineCount}개 생성 완료`);
}

// 간단한 직선 연결선 그리기
function drawSimpleLine(svgOverlay, parent, child, cardPositions, color, strokeWidth) {
    const parentPos = cardPositions.get(parent.person_code);
    const childPos = cardPositions.get(child.person_code);
    
    if (!parentPos || !childPos) return;
    
    const containerRect = svgOverlay.parentElement.getBoundingClientRect();
    
    const parentRect = parentPos.element.getBoundingClientRect();
    const childRect = childPos.element.getBoundingClientRect();
    
    // 좌표 계산 (컨테이너 기준)
    const parentX = parentRect.left - containerRect.left + parentRect.width / 2;
    const parentY = parentRect.bottom - containerRect.top;
    const childX = childRect.left - containerRect.left + childRect.width / 2;
    const childY = childRect.top - containerRect.top;
    
    // 부드러운 곡선 경로
    const midY = parentY + (childY - parentY) / 2;
    const pathData = `M ${parentX} ${parentY} 
                     C ${parentX} ${midY} ${childX} ${midY} ${childX} ${childY}`;
    
    // SVG 경로 생성
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', pathData);
    path.setAttribute('stroke', color);
    path.setAttribute('stroke-width', strokeWidth.toString());
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    path.style.filter = 'drop-shadow(0 2px 4px rgba(0,0,0,0.3))';
    
    svgOverlay.appendChild(path);
    
    // 화살표 끝
    const arrowHead = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
    const arrowSize = 8;
    arrowHead.setAttribute('points', `${childX-arrowSize},${childY-10} ${childX+arrowSize},${childY-10} ${childX},${childY}`);
    arrowHead.setAttribute('fill', color);
    arrowHead.style.filter = 'drop-shadow(0 1px 2px rgba(0,0,0,0.3))';
    
    svgOverlay.appendChild(arrowHead);
    
    console.log(`[DEBUG] ✅ 간단한 연결선: ${parent.name} → ${child.name}`);
}

// 클릭 가능한 간소화된 가족 카드 생성
function createClickableCard(person, focusPerson) {
    const card = document.createElement('div');
    card.className = 'clickable-family-card';
    
    // 촌수와 관계에 따른 색상 결정
    let cardColor, borderColor, textColor;
    const relationship = person.relationship_type;
    const kinshipLevel = person.kinship_level || 0;
    
    if (relationship === 'self') {
        // 본인 (0촌) - 골드
        cardColor = '#fef3c7';
        borderColor = '#d97706';
        textColor = '#92400e';
    } else if (kinshipLevel === 1) {
        // 1촌 (부모, 자녀) - 진한 녹색
        cardColor = '#dcfce7';
        borderColor = '#16a34a';
        textColor = '#166534';
    } else if (kinshipLevel === 2) {
        // 2촌 (조부모, 형제자매, 손자녀) - 파란색
        cardColor = '#dbeafe';
        borderColor = '#3b82f6';
        textColor = '#1d4ed8';
    } else if (kinshipLevel === 3) {
        // 3촌 (삼촌/고모, 조카) - 보라색
        cardColor = '#e9d5ff';
        borderColor = '#a855f7';
        textColor = '#7c3aed';
    } else if (kinshipLevel === 4) {
        // 4촌 (사촌) - 분홍색
        cardColor = '#fce7f3';
        borderColor = '#ec4899';
        textColor = '#be185d';
    } else {
        // 기타 - 회색
        cardColor = '#f1f5f9';
        borderColor = '#64748b';
        textColor = '#475569';
    }
    
    // 컴팩트한 카드 크기 - 훨씬 작게!
    const isMainFocus = relationship === 'self';
    
    let cardWidth, cardHeight, fontSize, paddingSize;
    if (isMainFocus) {
        // 포커스 인물은 조금 더 크게
        cardWidth = '100px';
        cardHeight = '80px';
        fontSize = '14px';
        paddingSize = '8px';
    } else {
        // 일반 카드는 매우 컴팩트하게
        cardWidth = '80px';
        cardHeight = '60px';
        fontSize = '11px';
        paddingSize = '4px';
    }
    
    card.style.cssText = `
        position: relative;
        width: ${cardWidth};
        height: ${cardHeight};
        background: ${cardColor};
        border: 2px solid ${borderColor};
        border-radius: 8px;
        padding: ${paddingSize};
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        color: ${textColor};
        font-family: 'Noto Sans KR', sans-serif;
        font-size: ${fontSize};
        box-shadow: ${isMainFocus ? '0 4px 12px rgba(217, 119, 6, 0.4)' : '0 2px 8px rgba(0,0,0,0.1)'};
        cursor: pointer;
        transition: all 0.2s ease;
        ${isMainFocus ? 'transform: scale(1.1);' : ''}
    `;
    
    // 클릭 이벤트 추가
    card.addEventListener('click', () => {
        if (!isMainFocus) {
            console.log(`[DEBUG] ${person.name} 카드 클릭 - 해당 인물 중심으로 재로드`);
            loadLineage(person.person_code);
        }
    });
    
    // 컴팩트한 호버 효과
    card.addEventListener('mouseenter', () => {
        if (!isMainFocus) {
            card.style.transform = 'scale(1.05) translateY(-2px)';
            card.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
        }
    });
    
    card.addEventListener('mouseleave', () => {
        if (!isMainFocus) {
            card.style.transform = 'scale(1)';
            card.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        }
    });
    
    // 관계 배지 (촌수 포함) - 4촌까지 확장
    const relationshipLabels = {
        'self': '본인',
        'parent': '부모',
        'grandparent': '조부모', 
        'great_grandparent': '증조부모',
        'ancestor': '조상',
        'sibling': '형제',
        'child': '자녀',
        'grandchild': '손자녀',
        'great_grandchild': '증손자녀',
        'descendant': '후손',
        'uncle': '삼촌/고모',
        'nephew': '조카',
        'cousin': '사촌',
        'relative': '친척'
    };
    
    if (relationship !== 'self') {
        // 컴팩트한 촌수 표시
        const relationshipBadge = document.createElement('div');
        const kinshipText = kinshipLevel > 0 ? `${kinshipLevel}촌` : '';
        relationshipBadge.textContent = kinshipText;
        relationshipBadge.style.cssText = `
            position: absolute;
            top: -5px;
            right: -5px;
            background: ${borderColor};
            color: white;
            padding: 2px 4px;
            border-radius: 6px;
            font-size: 8px;
            font-weight: bold;
            border: 1px solid white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        `;
        if (kinshipText) card.appendChild(relationshipBadge);
    } else {
        // 본인은 작은 왕관 아이콘
        const crownIcon = document.createElement('div');
        crownIcon.textContent = '👑';
        crownIcon.style.cssText = `
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 12px;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.3));
        `;
        card.appendChild(crownIcon);
    }
    
    // 컴팩트한 성별 아이콘
    const genderSymbol = document.createElement('div');
    genderSymbol.textContent = person.gender === 1 ? '♂' : '♀';
    genderSymbol.style.cssText = `
        position: absolute;
        top: 2px;
        left: 3px;
        font-size: 10px;
        font-weight: bold;
        color: ${borderColor};
        opacity: 0.7;
    `;
    card.appendChild(genderSymbol);
    
    // 컬팩트한 카드 내용 - 이름만!
    const cardContent = document.createElement('div');
    cardContent.style.cssText = `
        display: flex;
        flex-direction: column;
        height: 100%;
        justify-content: center;
        align-items: center;
        gap: 1px;
    `;
    
    // 이름만 표시 (가장 중요한 정보)
    const nameElement = document.createElement('div');
    nameElement.textContent = person.name;
    nameElement.style.cssText = `
        font-size: ${fontSize};
        font-weight: bold;
        color: ${textColor};
        line-height: 1.2;
        text-align: center;
        word-break: keep-all;
    `;
    
    // 포커스가 아닌 경우 한자명도 작게 표시 (선택적)
    if (isMainFocus && person.name_hanja) {
        const hanjaElement = document.createElement('div');
        hanjaElement.textContent = person.name_hanja;
        hanjaElement.style.cssText = `
            font-size: 9px;
            color: ${textColor};
            opacity: 0.6;
            margin-top: 1px;
        `;
        cardContent.appendChild(hanjaElement);
    }
    
    cardContent.appendChild(nameElement);
    card.appendChild(cardContent);
    
    // 자식이 있으면 아래쪽에 선 그리기
    // (이 함수를 호출하는 곳에서 parentChildMap 확인)
    
    // 호버 효과
    card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-2px)';
        card.style.boxShadow = '0 8px 20px rgba(0,0,0,0.15)';
    });
    
    card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0)';
        card.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
    });
    
    return card;
}

// 간단하고 명확한 연결선 그리기 - 사용하지 않음 (카드별 연결선 사용)
function createConnectionLines(parents, children, parentChildMap) {
    // 이 함수는 더 이상 사용하지 않음
    // 대신 각 카드에 개별적으로 연결선을 추가함
    return document.createElement('div');
}

// 깔끔한 트리 노드 생성 (직계 위주)
function createCleanTreeNode(person, personMap, parentChildMap, focusPerson, directLineage, depth) {
    const nodeContainer = document.createElement('div');
    nodeContainer.className = 'clean-tree-node-container';
    
    // 직계 라인에 포함되는지 확인
    const isInDirectLine = directLineage.some(p => p.person_code === person.person_code);
    
    nodeContainer.style.cssText = `
        position: relative;
        margin: 15px 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        ${isInDirectLine ? 'background: linear-gradient(to right, #fef3c7, transparent); padding: 15px; border-radius: 12px; border-left: 4px solid #f59e0b;' : ''}
    `;
    
    // 사람 노드 생성
    const personElement = createPersonNode8Chon(person);
    personElement.style.cssText += `
        display: inline-block;
        position: relative;
        z-index: 10;
        ${isInDirectLine ? 'transform: scale(1.1); box-shadow: 0 6px 16px rgba(0,0,0,0.2);' : ''}
        margin-bottom: 10px;
    `;
    
    // 직계 혈통 표시 레이블
    if (isInDirectLine) {
        const directLabel = document.createElement('div');
        directLabel.textContent = '직계 혈통';
        directLabel.style.cssText = `
            position: absolute;
            top: -10px;
            right: -10px;
            background: #f59e0b;
            color: white;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            z-index: 15;
            font-weight: bold;
        `;
        personElement.appendChild(directLabel);
    }
    
    nodeContainer.appendChild(personElement);
    
    // 자식들 가져오기 - 직계 위주로 필터링
    let children = parentChildMap.get(person.person_code) || [];
    
    // 직계가 아닌 경우, 자식 수를 제한 (너무 복잡해지지 않도록)
    if (!isInDirectLine && children.length > 3) {
        children = children.slice(0, 2); // 처음 2명만 표시
        
        const moreLabel = document.createElement('div');
        moreLabel.style.cssText = `
            color: #6b7280;
            font-size: 12px;
            margin-top: 5px;
            text-align: center;
        `;
        moreLabel.textContent = `외 ${(parentChildMap.get(person.person_code) || []).length - 2}명`;
        nodeContainer.appendChild(moreLabel);
    }
    
    if (children.length > 0) {
        // 자식 컨테이너 생성
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'clean-children-container';
        childrenContainer.style.cssText = `
            position: relative;
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        `;
        
        // 수직 연결선 (부모에서 아래로)
        const verticalLine = document.createElement('div');
        verticalLine.className = 'clean-vertical-connector';
        verticalLine.style.cssText = `
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: ${isInDirectLine ? '4px' : '3px'};
            height: 20px;
            background: ${isInDirectLine ? '#f59e0b' : '#94a3b8'};
            z-index: 5;
            border-radius: 2px;
        `;
        childrenContainer.appendChild(verticalLine);
        
        // 자식들이 여러 명이면 수평 분배선 생성
        if (children.length > 1) {
            const horizontalLine = document.createElement('div');
            horizontalLine.className = 'clean-horizontal-connector';
            horizontalLine.style.cssText = `
                position: absolute;
                top: 0;
                left: 15%;
                width: 70%;
                height: ${isInDirectLine ? '4px' : '3px'};
                background: ${isInDirectLine ? '#f59e0b' : '#94a3b8'};
                z-index: 5;
                border-radius: 2px;
            `;
            childrenContainer.appendChild(horizontalLine);
        }
        
        // 직계 자식을 우선으로 정렬
        children.sort((a, b) => {
            const aInDirectLine = directLineage.some(p => p.person_code === a.person_code);
            const bInDirectLine = directLineage.some(p => p.person_code === b.person_code);
            
            // 직계 라인이 먼저
            if (aInDirectLine && !bInDirectLine) return -1;
            if (!aInDirectLine && bInDirectLine) return 1;
            
            // 그 다음 sibling_order
            return (a.sibling_order || 0) - (b.sibling_order || 0);
        });
        
        // 자식들을 가로로 배치하는 컨테이너
        const siblingsRow = document.createElement('div');
        siblingsRow.className = 'clean-siblings-row';
        siblingsRow.style.cssText = `
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 25px;
            margin-top: ${children.length > 1 ? '25px' : '15px'};
            flex-wrap: wrap;
        `;
        
        children.forEach((child, childIndex) => {
            const childWrapper = document.createElement('div');
            childWrapper.className = 'clean-child-wrapper';
            childWrapper.style.cssText = `
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
            `;
            
            // 자식으로의 수직 연결선 (수평선에서 아래로)
            if (children.length > 1) {
                const childInDirectLine = directLineage.some(p => p.person_code === child.person_code);
                const childVerticalLine = document.createElement('div');
                childVerticalLine.className = 'clean-child-vertical-connector';
                childVerticalLine.style.cssText = `
                    position: absolute;
                    top: -25px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: ${childInDirectLine ? '4px' : '3px'};
                    height: 25px;
                    background: ${childInDirectLine ? '#f59e0b' : '#94a3b8'};
                    z-index: 5;
                    border-radius: 2px;
                `;
                childWrapper.appendChild(childVerticalLine);
            }
            
            const childNode = createCleanTreeNode(child, personMap, parentChildMap, focusPerson, directLineage, depth + 1);
            childWrapper.appendChild(childNode);
            siblingsRow.appendChild(childWrapper);
        });
        
        childrenContainer.appendChild(siblingsRow);
        nodeContainer.appendChild(childrenContainer);
    }
    
    return nodeContainer;
}

// 직계 라인을 강조하는 트리 노드 생성 (기존 버전 - 호환성용)
function createTreeNodeWithDirectLine(person, personMap, parentChildMap, focusPerson, directLineage, depth) {
    const nodeContainer = document.createElement('div');
    nodeContainer.className = 'tree-node-container';
    
    // 직계 라인에 포함되는지 확인
    const isInDirectLine = directLineage.some(p => p.person_code === person.person_code);
    
    nodeContainer.style.cssText = `
        position: relative;
        margin: 10px 0;
        ${isInDirectLine ? 'background: linear-gradient(to right, #fef3c7, transparent); padding: 10px; border-radius: 8px; border-left: 4px solid #f59e0b;' : ''}
    `;
    
    // 사람 노드 생성
    const personElement = createPersonNode8Chon(person);
    personElement.style.cssText += `
        display: inline-block;
        position: relative;
        z-index: 10;
        ${isInDirectLine ? 'transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.15);' : ''}
    `;
    
    // 직계 라인 표시 레이블
    if (isInDirectLine) {
        const directLabel = document.createElement('div');
        directLabel.textContent = '직계';
        directLabel.style.cssText = `
            position: absolute;
            top: -8px;
            right: -8px;
            background: #f59e0b;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            z-index: 15;
        `;
        personElement.appendChild(directLabel);
    }
    
    nodeContainer.appendChild(personElement);
    
    // 자식들 가져오기
    const children = parentChildMap.get(person.person_code) || [];
    
    if (children.length > 0) {
        // 자식 컨테이너 생성 - 더 명확한 족보 스타일
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'children-container';
        childrenContainer.style.cssText = `
            position: relative;
            margin-left: 0px;
            margin-top: 30px;
            padding-left: 0px;
            display: flex;
            flex-direction: column;
            align-items: center;
        `;
        
        // 수직 연결선 (부모에서 아래로)
        const verticalLine = document.createElement('div');
        verticalLine.className = 'vertical-connector';
        verticalLine.style.cssText = `
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: ${isInDirectLine ? '4px' : '2px'};
            height: 30px;
            background: ${isInDirectLine ? '#f59e0b' : '#6b7280'};
            z-index: 5;
        `;
        childrenContainer.appendChild(verticalLine);
        
        // 자식들이 여러 명이면 수평 분배선 생성
        if (children.length > 1) {
            const horizontalLine = document.createElement('div');
            horizontalLine.className = 'horizontal-connector';
            horizontalLine.style.cssText = `
                position: absolute;
                top: 0;
                left: 20%;
                width: 60%;
                height: ${isInDirectLine ? '4px' : '2px'};
                background: ${isInDirectLine ? '#f59e0b' : '#6b7280'};
                z-index: 5;
            `;
            childrenContainer.appendChild(horizontalLine);
        }
        
        // 직계 자식을 우선으로 정렬
        children.sort((a, b) => {
            const aInDirectLine = directLineage.some(p => p.person_code === a.person_code);
            const bInDirectLine = directLineage.some(p => p.person_code === b.person_code);
            
            // 직계 라인이 먼저
            if (aInDirectLine && !bInDirectLine) return -1;
            if (!aInDirectLine && bInDirectLine) return 1;
            
            // 그 다음 sibling_order
            return (a.sibling_order || 0) - (b.sibling_order || 0);
        });
        
        // 자식들을 가로로 배치하는 컨테이너
        const siblingsRow = document.createElement('div');
        siblingsRow.className = 'siblings-row';
        siblingsRow.style.cssText = `
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 20px;
            margin-top: ${children.length > 1 ? '20px' : '10px'};
            flex-wrap: wrap;
        `;
        
        children.forEach((child, childIndex) => {
            const childWrapper = document.createElement('div');
            childWrapper.className = 'child-wrapper';
            childWrapper.style.cssText = `
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
            `;
            
            // 자식으로의 수직 연결선 (수평선에서 아래로)
            if (children.length > 1) {
                const childVerticalLine = document.createElement('div');
                childVerticalLine.className = 'child-vertical-connector';
                childVerticalLine.style.cssText = `
                    position: absolute;
                    top: -20px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: ${isInDirectLine && directLineage.some(p => p.person_code === child.person_code) ? '4px' : '2px'};
                    height: 20px;
                    background: ${isInDirectLine && directLineage.some(p => p.person_code === child.person_code) ? '#f59e0b' : '#6b7280'};
                    z-index: 5;
                `;
                childWrapper.appendChild(childVerticalLine);
            }
            
            const childNode = createTreeNodeWithDirectLine(child, personMap, parentChildMap, focusPerson, directLineage, depth + 1);
            childWrapper.appendChild(childNode);
            siblingsRow.appendChild(childWrapper);
        });
        
        childrenContainer.appendChild(siblingsRow);
        
        nodeContainer.appendChild(childrenContainer);
    }
    
    return nodeContainer;
}

// 트리 노드 생성 (재귀적으로 자식들 포함) - 일반 버전
function createTreeNode(person, personMap, parentChildMap, focusPerson, depth) {
    const nodeContainer = document.createElement('div');
    nodeContainer.className = 'tree-node-container';
    nodeContainer.style.cssText = `
        position: relative;
        margin: 10px 0;
    `;
    
    // 사람 노드 생성
    const personElement = createPersonNode8Chon(person);
    personElement.style.cssText += `
        display: inline-block;
        position: relative;
        z-index: 10;
    `;
    
    nodeContainer.appendChild(personElement);
    
    // 자식들 가져오기
    const children = parentChildMap.get(person.person_code) || [];
    
    if (children.length > 0) {
        // 자식 컨테이너 생성 - 족보 스타일
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'children-container';
        childrenContainer.style.cssText = `
            position: relative;
            margin-left: 0px;
            margin-top: 30px;
            padding-left: 0px;
            display: flex;
            flex-direction: column;
            align-items: center;
        `;
        
        // 수직 연결선 (부모에서 아래로)
        const verticalLine = document.createElement('div');
        verticalLine.className = 'vertical-connector';
        verticalLine.style.cssText = `
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 30px;
            background: #6b7280;
            z-index: 5;
        `;
        childrenContainer.appendChild(verticalLine);
        
        // 자식들이 여러 명이면 수평 분배선 생성
        if (children.length > 1) {
            const horizontalLine = document.createElement('div');
            horizontalLine.className = 'horizontal-connector';
            horizontalLine.style.cssText = `
                position: absolute;
                top: 0;
                left: 20%;
                width: 60%;
                height: 2px;
                background: #6b7280;
                z-index: 5;
            `;
            childrenContainer.appendChild(horizontalLine);
        }
        
        // 형제 관계별로 그룹화 (같은 부모를 가진 자식들)
        children.sort((a, b) => {
            // 세대 우선, 그 다음 sibling_order
            if (a.generation !== b.generation) {
                return a.generation - b.generation;
            }
            return (a.sibling_order || 0) - (b.sibling_order || 0);
        });
        
        // 자식들을 가로로 배치하는 컨테이너
        const siblingsRow = document.createElement('div');
        siblingsRow.className = 'siblings-row';
        siblingsRow.style.cssText = `
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 20px;
            margin-top: ${children.length > 1 ? '20px' : '10px'};
            flex-wrap: wrap;
        `;
        
        children.forEach((child, childIndex) => {
            const childWrapper = document.createElement('div');
            childWrapper.className = 'child-wrapper';
            childWrapper.style.cssText = `
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
            `;
            
            // 자식으로의 수직 연결선 (수평선에서 아래로)
            if (children.length > 1) {
                const childVerticalLine = document.createElement('div');
                childVerticalLine.className = 'child-vertical-connector';
                childVerticalLine.style.cssText = `
                    position: absolute;
                    top: -20px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 2px;
                    height: 20px;
                    background: #6b7280;
                    z-index: 5;
                `;
                childWrapper.appendChild(childVerticalLine);
            }
            
            const childNode = createTreeNode(child, personMap, parentChildMap, focusPerson, depth + 1);
            childWrapper.appendChild(childNode);
            siblingsRow.appendChild(childWrapper);
        });
        
        childrenContainer.appendChild(siblingsRow);
        nodeContainer.appendChild(childrenContainer);
    }
    
    return nodeContainer;
}

// 세대 그룹 생성 (본인 + 형제들) - 기존 함수 유지 (호환성용)
function createGenerationGroup(person, isAncestor) {
    const generationGroup = document.createElement('div');
    generationGroup.className = 'generation-level';

    // 형제들이 있으면 가로 배치 컨테이너 생성
    if (person.siblings && person.siblings.length > 0) {
        const siblingsContainer = document.createElement('div');
        siblingsContainer.className = 'siblings-container';

        // 모든 형제들을 배치 순서대로 정렬
        const allSiblings = [person, ...person.siblings].sort((a, b) => 
            (a.sibling_order || 0) - (b.sibling_order || 0)
        );

        allSiblings.forEach(sibling => {
            const isMainPerson = sibling.person_code === person.person_code;
            const siblingNode = createPersonNode(sibling, person.level, isAncestor, isMainPerson);
            siblingsContainer.appendChild(siblingNode);
        });

        generationGroup.appendChild(siblingsContainer);
    } else {
        // 형제가 없으면 본인만 표시
        const personNode = createPersonNode(person, person.level, isAncestor, true);
        generationGroup.appendChild(personNode);
    }

    return generationGroup;
}

// 8촌 시스템용 사람 노드 생성
function createPersonNode8Chon(person) {
    const node = document.createElement('div');
    
    // 전통 족보 스타일로 개선
    let baseClass = 'person-node p-4 m-2 rounded-xl cursor-pointer transition-all hover:shadow-lg transform hover:-translate-y-1';
    let bgColor, borderColor, textColor, accentColor;
    
    // 관계별 전통 색상 적용
    if (person.display_type === 'focus') {
        bgColor = 'bg-gradient-to-br from-yellow-50 to-yellow-100';
        borderColor = 'border-yellow-600';
        textColor = 'text-yellow-900';
        accentColor = 'bg-yellow-600';
    } else if (person.display_type === 'ancestor') {
        bgColor = 'bg-gradient-to-br from-green-50 to-emerald-50';
        borderColor = 'border-emerald-500';
        textColor = 'text-emerald-800';
        accentColor = 'bg-emerald-500';
    } else if (person.display_type === 'descendant') {
        bgColor = 'bg-gradient-to-br from-blue-50 to-indigo-50';
        borderColor = 'border-indigo-400';
        textColor = 'text-indigo-800';
        accentColor = 'bg-indigo-400';
    } else if (person.display_type === 'sibling') {
        bgColor = 'bg-gradient-to-br from-amber-50 to-orange-50';
        borderColor = 'border-amber-500';
        textColor = 'text-amber-800';
        accentColor = 'bg-amber-500';
    } else if (person.display_type === 'uncle_nephew') {
        bgColor = 'bg-gradient-to-br from-rose-50 to-pink-50';
        borderColor = 'border-rose-400';
        textColor = 'text-rose-800';
        accentColor = 'bg-rose-400';
    } else if (person.display_type === 'cousin') {
        bgColor = 'bg-gradient-to-br from-purple-50 to-violet-50';
        borderColor = 'border-purple-400';
        textColor = 'text-purple-800';
        accentColor = 'bg-purple-400';
    } else {
        bgColor = 'bg-gradient-to-br from-slate-50 to-gray-50';
        borderColor = 'border-slate-300';
        textColor = 'text-slate-700';
        accentColor = 'bg-slate-400';
    }
    
    node.className = `${baseClass} ${bgColor} border-2 ${borderColor} ${textColor} shadow-md`;
    
    // 전통 족보 스타일 촌수 배지
    let kinshipBadge = '';
    if (person.kinship_distance && person.kinship_distance > 0) {
        kinshipBadge = `
            <div class="absolute -top-2 -right-2 w-6 h-6 ${accentColor} text-white rounded-full flex items-center justify-center text-xs font-bold shadow-md">
                ${person.kinship_distance}
            </div>
        `;
    }
    
    // 성별 표시 아이콘
    let genderIcon = '';
    if (person.gender === 1) {
        genderIcon = '<i class="fas fa-mars text-blue-600 text-xs mr-1"></i>';
    } else if (person.gender === 2) {
        genderIcon = '<i class="fas fa-venus text-pink-600 text-xs mr-1"></i>';
    }
    
    // 생년 표시 (한국 전통 방식)
    let birthInfo = '';
    if (person.birth_date && person.birth_date !== '0000-00') {
        const year = person.birth_date.split('-')[0];
        if (year && year !== '0000') {
            birthInfo = `<div class="text-xs text-gray-600 mt-1">${year}년생</div>`;
        }
    }
    
    node.innerHTML = `
        <div class="relative text-center min-w-[120px]">
            ${kinshipBadge}
            <div class="font-bold text-base mb-2 font-serif">
                ${genderIcon}${person.name}
            </div>
            <div class="text-sm text-gray-600 mb-1 font-serif">
                ${person.name_hanja || '未記'}
            </div>
            <div class="text-xs text-gray-500">
                ${person.generation}세대
            </div>
            ${birthInfo}
            ${person.sibling_order ? `<div class="text-xs text-gray-400 mt-1">${person.sibling_order}번째</div>` : ''}
        </div>
    `;
    
    // 클릭 이벤트 - 포커스 전환
    node.addEventListener('click', () => {
        console.log('[계통도] 포커스 전환:', person.name, person.person_code);
        loadLineage(person.person_code, 8, 8);
    });
    
    return node;
}

// 인물 노드 생성 (이전 버전 - 호환성용)
function createPersonNode(person, level, isAncestor, isMainLineage) {
    const node = document.createElement('div');
    let nodeClass = 'lineage-node';
    
    // 현재 포커스된 인물인지 정확하게 판단
    const isFocusPerson = (currentFocus === person.person_code) || 
                         (currentData && currentData.focus_person && currentData.focus_person.person_code === person.person_code);
    
    if (isFocusPerson) {
        nodeClass += ' current-user';
    } else if (isAncestor) {
        nodeClass += ' ancestor';
    } else {
        nodeClass += ' descendant';
    }

    // 메인 혈통이 아닌 형제들은 스타일 구분
    if (!isMainLineage) {
        nodeClass += ' sibling';
    }

    // 클릭 가능하도록 표시
    nodeClass += ' clickable';
    
    node.className = nodeClass;
    
    // 클릭 이벤트 추가
    node.addEventListener('click', () => {
        expandFromPerson(person.person_code);
    });
    
    // 기본 인물 정보
    let innerHTML = 
        '<div class="font-bold text-base">' + person.name + '</div>' +
        '<div class="text-sm text-gray-600">' + (person.name_hanja || '') + '</div>' +
        '<div class="text-xs text-gray-500 mt-1">' +
        person.generation + '세대' +
        '<span class="generation-badge">' + getRelationship(level) + '</span>' +
        '</div>' +
        (person.birth_date ? '<div class="text-xs text-gray-400 mt-1">' + formatDate(person.birth_date) + '</div>' : '') +
        (person.is_deceased ? '<div class="text-xs text-red-500">🕊️ 별세</div>' : '');

    // 포커스 인물인 경우 배우자와 자녀 정보 추가
    if (isFocusPerson && currentData) {
        // 배우자 정보 표시
        if (currentData.focus_spouses && currentData.focus_spouses.length > 0) {
            innerHTML += '<div class="text-xs text-purple-600 mt-2"><i class="fas fa-heart mr-1"></i>배우자</div>';
            currentData.focus_spouses.forEach(spouse => {
                innerHTML += '<div class="text-xs text-purple-500 ml-2">• ' + spouse.spouse_name + 
                    (spouse.spouse_name_hanja ? ' (' + spouse.spouse_name_hanja + ')' : '') + '</div>';
            });
        }
        
        // 자녀 정보 표시
        if (currentData.focus_children && currentData.focus_children.length > 0) {
            innerHTML += '<div class="text-xs text-green-600 mt-2"><i class="fas fa-baby mr-1"></i>자녀 ' + currentData.focus_children.length + '명</div>';
            currentData.focus_children.slice(0, 3).forEach(child => { // 최대 3명까지만 표시
                innerHTML += '<div class="text-xs text-green-500 ml-2">• ' + child.name + 
                    (child.name_hanja ? ' (' + child.name_hanja + ')' : '') + '</div>';
            });
            if (currentData.focus_children.length > 3) {
                innerHTML += '<div class="text-xs text-green-400 ml-2">• 외 ' + (currentData.focus_children.length - 3) + '명</div>';
            }
        }
    }
    
    innerHTML += '<div class="text-xs text-blue-500 mt-1"><i class="fas fa-expand-arrows-alt mr-1"></i>클릭하여 확장</div>';
    
    node.innerHTML = innerHTML;
    return node;
}

// 연결선 추가
function addConnector(container) {
    const connector = document.createElement('div');
    connector.className = 'lineage-connector';
    container.appendChild(connector);
}

// 후손을 레벨별로 그룹화
function groupByLevel(descendants) {
    return descendants.reduce((groups, person) => {
        const level = person.level;
        if (!groups[level]) {
            groups[level] = [];
        }
        groups[level].push(person);
        return groups;
    }, {});
}

// 특정 인물을 중심으로 확장
function expandFromPerson(personCode) {
    console.log('[DEBUG] 인물 확장:', personCode);
    
    // 클릭된 노드에 확장 애니메이션 효과
    const clickedNode = event.target.closest('.lineage-node');
    if (clickedNode) {
        clickedNode.classList.add('expanding');
        setTimeout(() => {
            clickedNode.classList.remove('expanding');
        }, 300);
    }
    
    // 확장 범위 설정 (8촌까지)
    const expandUp = 8; // 조상 8세대까지 (8촌 범위)
    const expandDown = 8; // 후손 8세대까지 (8촌 범위)
    
    // 로딩 표시
    showLoading();
    
    // 약간의 딜레이 후 새로운 중심으로 계통도 다시 로드
    setTimeout(() => {
        loadLineage(personCode, expandUp, expandDown);
    }, 150);
}

// 관계 표시 (8촌까지 확장)
function getRelationship(level) {
    if (level === 0) return '본인';
    if (level === 1) return '부친';
    if (level === 2) return '조부';
    if (level === 3) return '증조부';
    if (level === 4) return '고조부';
    if (level === 5) return '5대조';
    if (level === 6) return '6대조';
    if (level === 7) return '7대조';
    if (level === 8) return '8대조';
    if (level >= 9) return level + '대조';
    if (level === -1) return '자녀';
    if (level === -2) return '손자';
    if (level === -3) return '증손';
    if (level === -4) return '고손';
    if (level === -5) return '5대손';
    if (level === -6) return '6대손';
    if (level === -7) return '7대손';
    if (level === -8) return '8대손';
    if (level <= -9) return Math.abs(level) + '대손';
    return level + '세대';
}

// 날짜 포맷
function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.getFullYear() + '년 ' + (date.getMonth() + 1) + '월 ' + date.getDate() + '일';
}

// UI 상태 관리
function showLoading() {
    document.getElementById('loadingSection').classList.remove('hidden');
    document.getElementById('errorSection').classList.add('hidden');
    document.getElementById('lineageContent').classList.add('hidden');
}

function hideLoading() {
    document.getElementById('loadingSection').classList.add('hidden');
    document.getElementById('errorSection').classList.add('hidden');
    document.getElementById('lineageContent').classList.remove('hidden');
}

function showError(message) {
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('loadingSection').classList.add('hidden');
    document.getElementById('errorSection').classList.remove('hidden');
    document.getElementById('lineageContent').classList.add('hidden');
}