// 창녕조씨 족보 시스템 JavaScript - 클라이언트 전용 버전

// 전역 변수
let currentData = [];

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    loadStatistics();
    
    // 엔터키 검색 지원
    document.getElementById('search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchFamily();
        }
    });
});

// 통계 데이터 로드 (클라이언트 사이드)
function loadStatistics() {
    try {
        const stats = API.getStats().data;
        document.getElementById('total-members').textContent = stats.total_members;
        document.getElementById('generations').textContent = stats.generations + '세대';
        document.getElementById('living-members').textContent = stats.living_members;
    } catch (error) {
        console.error('통계 데이터 로드 실패:', error);
    }
}

// 가족 검색
function searchFamily() {
    const searchTerm = document.getElementById('search-input').value.trim();
    if (!searchTerm) {
        alert('검색어를 입력해주세요.');
        return;
    }

    try {
        showLoading();
        
        // API 호출 시뮬레이션 (약간의 지연)
        setTimeout(() => {
            const response = API.searchFamily(searchTerm);
            
            if (response.success) {
                currentData = response.data;
                document.getElementById('results-title').innerHTML = 
                    `<i class="fas fa-search mr-2"></i>검색 결과: "${searchTerm}" (${response.total}명)`;
                displayFamilyMembers(currentData);
            } else {
                showNoResults(`"${searchTerm}" 검색 결과가 없습니다.`);
            }
        }, 300);
    } catch (error) {
        console.error('검색 실패:', error);
        showError('검색 중 오류가 발생했습니다.');
    }
}

// 세대별 조회
function showGeneration(generation) {
    try {
        showLoading();
        
        // API 호출 시뮬레이션
        setTimeout(() => {
            const response = API.getFamilyByGeneration(generation);
            
            if (response.success) {
                currentData = response.data;
                document.getElementById('results-title').innerHTML = 
                    `<i class="fas fa-sitemap mr-2"></i>${generation}세대 (${response.total}명)`;
                displayFamilyMembers(currentData);
            } else {
                showNoResults(`${generation}세대 데이터가 없습니다.`);
            }
        }, 300);
    } catch (error) {
        console.error('세대 조회 실패:', error);
        showError('세대 조회 중 오류가 발생했습니다.');
    }
}

// 전체 가족 구성원 조회
function showAllGenerations() {
    try {
        showLoading();
        
        // API 호출 시뮬레이션
        setTimeout(() => {
            const response = API.getFamily();
            
            if (response.success) {
                currentData = response.data;
                document.getElementById('results-title').innerHTML = 
                    `<i class="fas fa-users mr-2"></i>전체 족보 (${response.total}명)`;
                displayFamilyMembers(currentData);
            } else {
                showError('데이터를 불러올 수 없습니다.');
            }
        }, 300);
    } catch (error) {
        console.error('전체 조회 실패:', error);
        showError('데이터 조회 중 오류가 발생했습니다.');
    }
}

// 가족 구성원 표시
function displayFamilyMembers(members) {
    const container = document.getElementById('results-container');
    
    if (!members || members.length === 0) {
        showNoResults('조회된 데이터가 없습니다.');
        return;
    }

    let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
    
    members.forEach(member => {
        html += createMemberCard(member);
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// 개별 구성원 카드 생성
function createMemberCard(member) {
    const genderIcon = member.gender === 1 ? 'fas fa-mars text-blue-500' : 'fas fa-venus text-pink-500';
    const statusBadge = member.is_deceased ? 
        '<span class="px-2 py-1 bg-gray-500 text-white text-xs rounded">故人</span>' : 
        '<span class="px-2 py-1 bg-green-500 text-white text-xs rounded">생존</span>';
    
    const birthYear = member.birth_date && member.birth_date !== '0000-00' ? 
        ` (${member.birth_date})` : '';
    
    return `
        <div class="member-card bg-gray-50 rounded-lg p-6 hover:shadow-md transition-shadow cursor-pointer" 
             onclick="showMemberDetail('${member.person_code}')">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center space-x-2">
                    <i class="${genderIcon}"></i>
                    <span class="font-bold text-lg">${member.name}</span>
                    ${statusBadge}
                </div>
                <span class="text-sm text-gray-500">${member.generation}세대</span>
            </div>
            
            <div class="space-y-2">
                ${member.name_hanja ? `
                    <p class="text-gray-600 hanja-text">
                        <i class="fas fa-language mr-2"></i>
                        <span class="font-medium">${member.name_hanja}</span>
                    </p>
                ` : ''}
                
                <p class="text-gray-600">
                    <i class="fas fa-id-card mr-2"></i>
                    <span class="text-sm">인물코드: ${member.person_code}</span>
                </p>
                
                ${member.phone_number ? `
                    <p class="text-gray-600">
                        <i class="fas fa-phone mr-2"></i>
                        <span class="text-sm">${member.phone_number}</span>
                    </p>
                ` : ''}
                
                ${member.email ? `
                    <p class="text-gray-600">
                        <i class="fas fa-envelope mr-2"></i>
                        <span class="text-sm">${member.email}</span>
                    </p>
                ` : ''}
                
                ${member.biography ? `
                    <p class="text-gray-600 text-sm mt-3 line-clamp-2">
                        <i class="fas fa-book mr-2"></i>
                        ${member.biography}
                    </p>
                ` : ''}
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-200 text-xs text-gray-500">
                형제순위: ${member.sibling_order}째 | 자녀수: ${member.child_count}명${birthYear}
            </div>
        </div>
    `;
}

// 개별 구성원 상세 조회
function showMemberDetail(personCode) {
    try {
        const response = API.getFamilyMember(personCode);
        
        if (response.success) {
            showMemberModal(response.data);
        } else {
            alert('상세 정보를 불러올 수 없습니다.');
        }
    } catch (error) {
        console.error('상세 조회 실패:', error);
        alert('상세 정보 조회 중 오류가 발생했습니다.');
    }
}

// 구성원 상세 모달 표시
function showMemberModal(member) {
    const genderText = member.gender === 1 ? '남성' : '여성';
    const statusText = member.is_deceased ? '고인' : '생존';
    
    let addressInfo = '';
    if (member.home_address && typeof member.home_address === 'object') {
        addressInfo = `
            <div class="mb-4">
                <h4 class="font-semibold text-gray-700 mb-2">거주지</h4>
                <p class="text-gray-600">${member.home_address.address || ''}</p>
                ${member.home_address.detail ? `<p class="text-gray-600 text-sm">${member.home_address.detail}</p>` : ''}
            </div>
        `;
    }
    
    if (member.work_address && typeof member.work_address === 'object') {
        addressInfo += `
            <div class="mb-4">
                <h4 class="font-semibold text-gray-700 mb-2">직장</h4>
                <p class="text-gray-600">${member.work_address.address || ''}</p>
                ${member.work_address.detail ? `<p class="text-gray-600 text-sm">${member.work_address.detail}</p>` : ''}
            </div>
        `;
    }
    
    const modal = `
        <div class="modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="closeModal()">
            <div class="modal-content bg-white rounded-lg max-w-2xl w-full mx-4 max-h-screen overflow-y-auto" onclick="event.stopPropagation()">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold text-gray-900">${member.name}</h2>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 focusable">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    ${member.name_hanja ? `<p class="text-lg text-gray-600 mt-1 hanja-text">${member.name_hanja}</p>` : ''}
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <span class="text-sm text-gray-500">인물코드</span>
                            <p class="font-medium">${member.person_code}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">세대</span>
                            <p class="font-medium">${member.generation}세대</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">성별</span>
                            <p class="font-medium">${genderText}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">상태</span>
                            <p class="font-medium">${statusText}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">형제순위</span>
                            <p class="font-medium">${member.sibling_order}째</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">자녀수</span>
                            <p class="font-medium">${member.child_count}명</p>
                        </div>
                    </div>
                    
                    ${member.birth_date && member.birth_date !== '0000-00' ? `
                        <div class="mb-4">
                            <span class="text-sm text-gray-500">생년월일</span>
                            <p class="font-medium">${member.birth_date}</p>
                        </div>
                    ` : ''}
                    
                    ${member.death_date && member.death_date !== '0000-00' ? `
                        <div class="mb-4">
                            <span class="text-sm text-gray-500">사망일</span>
                            <p class="font-medium">${member.death_date}</p>
                        </div>
                    ` : ''}
                    
                    ${member.phone_number ? `
                        <div class="mb-4">
                            <span class="text-sm text-gray-500">전화번호</span>
                            <p class="font-medium">${member.phone_number}</p>
                        </div>
                    ` : ''}
                    
                    ${member.email ? `
                        <div class="mb-4">
                            <span class="text-sm text-gray-500">이메일</span>
                            <p class="font-medium">${member.email}</p>
                        </div>
                    ` : ''}
                    
                    ${addressInfo}
                    
                    ${member.biography ? `
                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-700 mb-2">전기</h4>
                            <p class="text-gray-600 leading-relaxed">${member.biography}</p>
                        </div>
                    ` : ''}
                    
                    ${member.biography_hanja ? `
                        <div class="mb-4">
                            <h4 class="font-semibold text-gray-700 mb-2">한문 전기</h4>
                            <p class="text-gray-600 leading-relaxed text-sm hanja-text">${member.biography_hanja}</p>
                        </div>
                    ` : ''}
                    
                    <div class="text-xs text-gray-500 mt-6 pt-4 border-t border-gray-200">
                        <p>등록일: ${new Date(member.created_at).toLocaleDateString('ko-KR')}</p>
                        <p>수정일: ${new Date(member.updated_at).toLocaleDateString('ko-KR')}</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modal);
}

// 모달 닫기
function closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.remove();
    }
}

// 로딩 표시
function showLoading() {
    const container = document.getElementById('results-container');
    container.innerHTML = `
        <div class="flex items-center justify-center py-12">
            <div class="loading-spinner rounded-full h-12 w-12 border-b-2 border-korean-500"></div>
            <span class="ml-3 text-gray-600">데이터를 불러오는 중...</span>
        </div>
    `;
}

// 결과 없음 표시
function showNoResults(message) {
    const container = document.getElementById('results-container');
    container.innerHTML = `
        <div class="text-center py-12">
            <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">${message}</p>
        </div>
    `;
}

// 오류 표시
function showError(message) {
    const container = document.getElementById('results-container');
    container.innerHTML = `
        <div class="text-center py-12">
            <i class="fas fa-exclamation-triangle text-4xl text-red-300 mb-4"></i>
            <p class="text-red-500">${message}</p>
            <button onclick="location.reload()" class="focusable mt-4 px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">
                페이지 새로고침
            </button>
        </div>
    `;
}

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// 족보 트리 보기 (향후 확장용)
function showFamilyTree(personCode = '1') {
    try {
        const response = API.getTree(personCode);
        if (response.success) {
            // 트리 뷰어 구현 (현재는 알림으로 대체)
            alert('족보 트리 기능은 추후 구현 예정입니다.');
        }
    } catch (error) {
        console.error('트리 조회 실패:', error);
        alert('족보 트리 조회 중 오류가 발생했습니다.');
    }
}

// 데이터 내보내기 (향후 확장용)
function exportData(format = 'json') {
    try {
        const response = API.getFamily();
        if (response.success) {
            const data = response.data;
            let content, filename;
            
            switch (format) {
                case 'json':
                    content = JSON.stringify(data, null, 2);
                    filename = 'cyjc-family-tree.json';
                    break;
                case 'csv':
                    // CSV 변환 로직 구현 필요
                    alert('CSV 내보내기는 추후 구현 예정입니다.');
                    return;
                default:
                    alert('지원하지 않는 형식입니다.');
                    return;
            }
            
            // 파일 다운로드
            const blob = new Blob([content], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    } catch (error) {
        console.error('데이터 내보내기 실패:', error);
        alert('데이터 내보내기 중 오류가 발생했습니다.');
    }
}