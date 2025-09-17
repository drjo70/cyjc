<?php
/**
 * 창녕조씨 족보 시스템 - 인물 검색
 * 이름, 세대, 거주지별 검색 기능
 * 
 * @author 닥터조 ((주)조유 대표이사)
 * @version 4.0
 * @since 2024-09-17
 */

session_start();
require_once 'config/database.php';

// 검색 파라미터 받기
$search_name = $_GET['name'] ?? '';
$search_generation = $_GET['generation'] ?? '';
$search_location = $_GET['location'] ?? '';
$search_type = $_GET['type'] ?? 'all';

// 샘플 족보 데이터 (실제로는 데이터베이스에서 가져와야 함)
$family_members = [
    ['id' => 1, 'name' => '닥터조', 'korean_name' => '조○○', 'generation' => 15, 'birth_year' => 1970, 'location' => '서울특별시', 'title' => '컴퓨터 IT 박사', 'company' => '(주)조유 대표이사'],
    ['id' => 2, 'name' => '조부경', 'korean_name' => '조부경', 'generation' => 14, 'birth_year' => 1945, 'location' => '서울특별시', 'title' => '아버지', 'company' => ''],
    ['id' => 3, 'name' => '조할아버지', 'korean_name' => '조○○', 'generation' => 13, 'birth_year' => 1920, 'location' => '부산광역시', 'title' => '할아버지', 'company' => ''],
    ['id' => 4, 'name' => '조증조', 'korean_name' => '조○○', 'generation' => 12, 'birth_year' => 1880, 'location' => '경상남도', 'title' => '증조할아버지', 'company' => ''],
    ['id' => 5, 'name' => '조고조', 'korean_name' => '조○○', 'generation' => 11, 'birth_year' => 1850, 'location' => '경상남도', 'title' => '고조할아버지', 'company' => ''],
    ['id' => 6, 'name' => '조영진', 'korean_name' => '조영진', 'generation' => 15, 'birth_year' => 1975, 'location' => '인천광역시', 'title' => '사촌형', 'company' => '삼성전자'],
    ['id' => 7, 'name' => '조민수', 'korean_name' => '조민수', 'generation' => 15, 'birth_year' => 1972, 'location' => '대전광역시', 'title' => '사촌동생', 'company' => 'LG전자'],
    ['id' => 8, 'name' => '조현우', 'korean_name' => '조현우', 'generation' => 16, 'birth_year' => 2000, 'location' => '서울특별시', 'title' => '아들', 'company' => '대학생'],
    ['id' => 9, 'name' => '조수진', 'korean_name' => '조수진', 'generation' => 16, 'birth_year' => 1995, 'location' => '부산광역시', 'title' => '조카', 'company' => '교사'],
    ['id' => 10, 'name' => '조태영', 'korean_name' => '조태영', 'generation' => 14, 'birth_year' => 1950, 'location' => '광주광역시', 'title' => '삼촌', 'company' => '퇴직']
];

// 검색 실행
$search_results = [];
if (!empty($search_name) || !empty($search_generation) || !empty($search_location)) {
    foreach ($family_members as $member) {
        $match = true;
        
        // 이름 검색
        if (!empty($search_name)) {
            if (strpos($member['name'], $search_name) === false && 
                strpos($member['korean_name'], $search_name) === false) {
                $match = false;
            }
        }
        
        // 세대 검색
        if (!empty($search_generation)) {
            if ($member['generation'] != $search_generation) {
                $match = false;
            }
        }
        
        // 거주지 검색
        if (!empty($search_location)) {
            if (strpos($member['location'], $search_location) === false) {
                $match = false;
            }
        }
        
        if ($match) {
            $search_results[] = $member;
        }
    }
} else {
    // 검색어가 없으면 모든 인물 표시
    $search_results = $family_members;
}

// 통계
$total_count = count($family_members);
$search_count = count($search_results);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>인물 검색 | 창녕조씨 족보</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .person-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .person-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- 헤더 -->
    <header class="gradient-bg text-white shadow-xl">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="flex items-center space-x-3 hover:opacity-80">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-search text-lg"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold">인물 검색</h1>
                            <p class="text-sm opacity-90">창녕조씨 족보</p>
                        </div>
                    </a>
                </div>
                
                <nav class="hidden md:flex items-center space-x-4">
                    <a href="index.php" class="px-4 py-2 hover:bg-white/20 rounded-lg transition-colors">
                        <i class="fas fa-home mr-2"></i>메인
                    </a>
                    <a href="search.php" class="px-4 py-2 bg-white/20 rounded-lg">
                        <i class="fas fa-search mr-2"></i>인물검색
                    </a>
                    <a href="family_tree.php" class="px-4 py-2 hover:bg-white/20 rounded-lg transition-colors">
                        <i class="fas fa-sitemap mr-2"></i>가계도
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-6 py-8">
        
        <!-- 검색 폼 -->
        <section class="mb-8" data-aos="fade-up">
            <div class="bg-white rounded-2xl p-6 shadow-xl">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-search mr-3 text-blue-600"></i>족보 인물 검색
                </h2>
                
                <form method="GET" action="search.php" class="space-y-4">
                    <div class="grid md:grid-cols-3 gap-4">
                        <!-- 이름 검색 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">이름</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($search_name); ?>"
                                   placeholder="예: 조○○, 닥터조"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <!-- 세대 검색 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">세대</label>
                            <select name="generation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">전체 세대</option>
                                <?php for($i = 1; $i <= 20; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $search_generation == $i ? 'selected' : ''; ?>><?php echo $i; ?>세</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <!-- 거주지 검색 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">거주지</label>
                            <input type="text" name="location" value="<?php echo htmlspecialchars($search_location); ?>"
                                   placeholder="예: 서울, 부산, 경상남도"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-4 items-center">
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>검색
                        </button>
                        <a href="search.php" class="px-6 py-2 bg-gray-500 text-white font-bold rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-redo mr-2"></i>초기화
                        </a>
                        <div class="text-sm text-gray-600">
                            전체 <?php echo number_format($total_count); ?>명 중 <?php echo number_format($search_count); ?>명 검색됨
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- 검색 결과 -->
        <section class="mb-8">
            <?php if (!empty($search_results)): ?>
            <h3 class="text-xl font-bold text-gray-800 mb-6" data-aos="fade-up">
                <i class="fas fa-users mr-2 text-green-600"></i>
                검색 결과 (<?php echo count($search_results); ?>명)
            </h3>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($search_results as $index => $person): ?>
                <div class="person-card bg-white rounded-xl p-6 shadow-lg" 
                     data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>"
                     onclick="showPersonDetail(<?php echo $person['id']; ?>)">
                    
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                            <?php echo $person['generation']; ?>
                        </div>
                        
                        <div class="flex-1">
                            <h4 class="text-lg font-bold text-gray-800"><?php echo $person['name']; ?></h4>
                            <?php if ($person['korean_name'] !== $person['name']): ?>
                            <p class="text-sm text-gray-600"><?php echo $person['korean_name']; ?></p>
                            <?php endif; ?>
                            <p class="text-sm text-gray-500 mt-1"><?php echo $person['title']; ?></p>
                            
                            <div class="mt-3 space-y-1 text-xs text-gray-500">
                                <div><i class="fas fa-calendar mr-1"></i><?php echo $person['birth_year']; ?>년생</div>
                                <div><i class="fas fa-map-marker-alt mr-1"></i><?php echo $person['location']; ?></div>
                                <?php if (!empty($person['company'])): ?>
                                <div><i class="fas fa-building mr-1"></i><?php echo $person['company']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <?php if ($person['name'] === '닥터조'): ?>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold">
                                <i class="fas fa-star mr-1"></i>본인
                            </span>
                            <?php endif; ?>
                            <div class="mt-2">
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php else: ?>
            <div class="text-center py-12" data-aos="fade-up">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-search text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-600 mb-2">검색 결과가 없습니다</h3>
                <p class="text-gray-500 mb-4">다른 검색어로 다시 시도해보세요</p>
                <button onclick="clearSearch()" class="px-6 py-2 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-redo mr-2"></i>전체 보기
                </button>
            </div>
            <?php endif; ?>
        </section>

        <!-- 빠른 검색 -->
        <section class="mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-2xl p-6 shadow-xl" data-aos="fade-up">
                <h3 class="text-xl font-bold mb-4">
                    <i class="fas fa-bolt mr-2"></i>빠른 검색
                </h3>
                
                <div class="grid md:grid-cols-4 gap-3">
                    <a href="search.php?generation=15" class="bg-white/20 hover:bg-white/30 p-3 rounded-lg text-center transition-colors">
                        <i class="fas fa-users text-xl mb-1"></i>
                        <div class="text-sm">15세대</div>
                    </a>
                    <a href="search.php?location=서울" class="bg-white/20 hover:bg-white/30 p-3 rounded-lg text-center transition-colors">
                        <i class="fas fa-map-marker-alt text-xl mb-1"></i>
                        <div class="text-sm">서울 거주</div>
                    </a>
                    <a href="search.php?location=부산" class="bg-white/20 hover:bg-white/30 p-3 rounded-lg text-center transition-colors">
                        <i class="fas fa-map-marker-alt text-xl mb-1"></i>
                        <div class="text-sm">부산 거주</div>
                    </a>
                    <a href="search.php?generation=14" class="bg-white/20 hover:bg-white/30 p-3 rounded-lg text-center transition-colors">
                        <i class="fas fa-users text-xl mb-1"></i>
                        <div class="text-sm">14세대</div>
                    </a>
                </div>
            </div>
        </section>
    </div>

    <!-- 인물 상세 모달 -->
    <div id="personModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 max-w-md mx-4 w-full">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold" id="modalTitle">인물 정보</h3>
                <button onclick="closePersonModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div id="modalContent">
                <!-- 내용이 동적으로 삽입됩니다 -->
            </div>
            <div class="mt-6 flex gap-3">
                <button onclick="closePersonModal()" class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    닫기
                </button>
                <button id="detailPageBtn" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    상세페이지
                </button>
            </div>
        </div>
    </div>

    <script>
        // AOS 초기화
        AOS.init({
            duration: 600,
            easing: 'ease-in-out',
            once: true
        });

        // 전체 인물 데이터
        const familyMembers = <?php echo json_encode($family_members); ?>;

        // 인물 상세 정보 표시
        function showPersonDetail(personId) {
            const person = familyMembers.find(p => p.id === personId);
            if (!person) return;
            
            const modal = document.getElementById('personModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            const detailBtn = document.getElementById('detailPageBtn');
            
            title.textContent = `${person.generation}세 - ${person.name}`;
            
            content.innerHTML = `
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4 text-white text-2xl font-bold">
                        ${person.generation}
                    </div>
                    <h4 class="text-2xl font-bold text-gray-800">${person.name}</h4>
                    ${person.korean_name !== person.name ? `<p class="text-gray-600">${person.korean_name}</p>` : ''}
                    <p class="text-gray-600 mt-1">${person.title}</p>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <i class="fas fa-birthday-cake text-blue-500 mr-2"></i>
                                <strong>출생:</strong> ${person.birth_year}년
                            </div>
                            <div>
                                <i class="fas fa-layer-group text-green-500 mr-2"></i>
                                <strong>세대:</strong> ${person.generation}세
                            </div>
                            <div class="col-span-2">
                                <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                                <strong>거주지:</strong> ${person.location}
                            </div>
                            ${person.company ? `
                            <div class="col-span-2">
                                <i class="fas fa-building text-purple-500 mr-2"></i>
                                <strong>소속:</strong> ${person.company}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    ${person.name === '닥터조' ? `
                    <div class="bg-yellow-50 p-4 rounded-lg border-l-4 border-yellow-400">
                        <h5 class="font-bold text-yellow-800 mb-2">
                            <i class="fas fa-star mr-1"></i>시스템 개발자
                        </h5>
                        <p class="text-sm text-yellow-700">
                            창녕조씨 디지털 족보 시스템의 개발자이자 운영자입니다.
                        </p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            detailBtn.onclick = () => {
                window.location.href = `person_detail.php?id=${person.id}`;
            };
            
            modal.classList.remove('hidden');
        }

        function closePersonModal() {
            document.getElementById('personModal').classList.add('hidden');
        }

        function clearSearch() {
            window.location.href = 'search.php';
        }

        // 모달 외부 클릭시 닫기
        window.onclick = function(event) {
            const modal = document.getElementById('personModal');
            if (event.target === modal) {
                closePersonModal();
            }
        };

        // Enter 키로 검색
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                e.target.closest('form').submit();
            }
        });
    </script>
</body>
</html>