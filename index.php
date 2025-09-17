<?php
/**
 * 창녕조씨 족보 시스템 - 닥터조 직계 족보 메인페이지
 * 1세대부터 닥터조님까지의 직계 라인 중심
 * 
 * @author 닥터조 ((주)조유 대표이사)
 * @version 4.0
 * @since 2024-09-17
 */

session_start();
require_once 'config/database.php';

// 닥터조님 정보 (실제 데이터)
$doctor_jo = [
    'id' => 1,
    'name' => '닥터조',
    'korean_name' => '조○○',
    'generation' => 15, // 15세대
    'birth_year' => 1970, // 예시
    'title' => '컴퓨터 IT 박사',
    'company' => '(주)조유 대표이사',
    'address' => '서울특별시',
    'specialties' => ['컨설팅 전문가', 'AI 전문가', '시스템 아키텍트', '족보 연구가']
];

// 직계 조상 (1세대부터 닥터조님까지) - 실제 족보 데이터로 교체 필요
$direct_lineage = [
    1 => ['name' => '조◯◯', 'title' => '창녕조씨 시조', 'year' => '고려시대', 'location' => '창녕'],
    2 => ['name' => '조◯◯', 'title' => '2세조', 'year' => '고려시대', 'location' => '창녕'],
    3 => ['name' => '조◯◯', 'title' => '3세조', 'year' => '고려시대', 'location' => '창녕'],
    4 => ['name' => '조◯◯', 'title' => '4세조', 'year' => '조선초기', 'location' => '경상도'],
    5 => ['name' => '조◯◯', 'title' => '5세조', 'year' => '조선초기', 'location' => '경상도'],
    6 => ['name' => '조◯◯', 'title' => '6세조', 'year' => '조선전기', 'location' => '경상도'],
    7 => ['name' => '조◯◯', 'title' => '7세조', 'year' => '조선전기', 'location' => '경상도'],
    8 => ['name' => '조◯◯', 'title' => '8세조', 'year' => '조선중기', 'location' => '경상도'],
    9 => ['name' => '조◯◯', 'title' => '9세조', 'year' => '조선중기', 'location' => '경상도'],
    10 => ['name' => '조◯◯', 'title' => '10세조', 'year' => '조선후기', 'location' => '경상도'],
    11 => ['name' => '조◯◯', 'title' => '증조할아버지', 'year' => '1850년대', 'location' => '경상남도'],
    12 => ['name' => '조◯◯', 'title' => '고조할아버지', 'year' => '1880년대', 'location' => '경상남도'],
    13 => ['name' => '조◯◯', 'title' => '할아버지', 'year' => '1920년대', 'location' => '부산'],
    14 => ['name' => '조◯◯', 'title' => '아버지', 'year' => '1945년생', 'location' => '서울'],
    15 => ['name' => $doctor_jo['name'], 'title' => $doctor_jo['title'], 'year' => $doctor_jo['birth_year'].'년생', 'location' => $doctor_jo['address']]
];

// 통계 정보
$stats = [
    'total_generations' => 15,
    'direct_lineage_count' => count($direct_lineage),
    'total_family_members' => 1247, // 전체 족보 인원
    'managed_by_doctor_jo' => 127 // 닥터조님이 관리하는 인원
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $doctor_jo['name']; ?> 직계 족보 | 창녕조씨 1~15세</title>
    
    <meta name="description" content="창녕조씨 <?php echo $doctor_jo['name']; ?> 직계 족보 - 1세대부터 15세대까지의 직계 조상">
    <meta name="keywords" content="창녕조씨, 족보, <?php echo $doctor_jo['name']; ?>, 직계, 가계도">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .lineage-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .lineage-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .generation-line {
            position: relative;
        }
        .generation-line::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 100%;
            width: 2px;
            height: 40px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            transform: translateX(-50%);
        }
        .generation-line:last-child::before {
            display: none;
        }
        .current-generation {
            border: 3px solid #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }
        .menu-card {
            transition: all 0.3s ease;
        }
        .menu-card:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- 헤더 네비게이션 -->
    <header class="gradient-bg text-white shadow-xl sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-sitemap text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">창녕조씨 직계 족보</h1>
                        <p class="text-sm opacity-90">1세대 ~ <?php echo $doctor_jo['generation']; ?>세대 (<?php echo $doctor_jo['name']; ?>님)</p>
                    </div>
                </div>
                
                <!-- 메인 메뉴 -->
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="index.php" class="px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 transition-colors">
                        <i class="fas fa-home mr-2"></i>메인
                    </a>
                    <a href="search.php" class="px-4 py-2 hover:bg-white/20 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>인물검색
                    </a>
                    <a href="family_tree.php" class="px-4 py-2 hover:bg-white/20 rounded-lg transition-colors">
                        <i class="fas fa-sitemap mr-2"></i>가계도
                    </a>
                    <a href="admin.php" class="px-4 py-2 hover:bg-white/20 rounded-lg transition-colors">
                        <i class="fas fa-cog mr-2"></i>관리
                    </a>
                </nav>
                
                <!-- 모바일 메뉴 버튼 -->
                <button onclick="toggleMobileMenu()" class="md:hidden p-2 hover:bg-white/20 rounded-lg">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
            
            <!-- 모바일 메뉴 -->
            <div id="mobileMenu" class="hidden md:hidden mt-4 space-y-2">
                <a href="index.php" class="block px-4 py-2 bg-white/20 rounded-lg">
                    <i class="fas fa-home mr-2"></i>메인
                </a>
                <a href="search.php" class="block px-4 py-2 hover:bg-white/20 rounded-lg">
                    <i class="fas fa-search mr-2"></i>인물검색
                </a>
                <a href="family_tree.php" class="block px-4 py-2 hover:bg-white/20 rounded-lg">
                    <i class="fas fa-sitemap mr-2"></i>가계도
                </a>
                <a href="admin.php" class="block px-4 py-2 hover:bg-white/20 rounded-lg">
                    <i class="fas fa-cog mr-2"></i>관리
                </a>
            </div>
        </div>
    </header>

    <!-- 메인 컨테이너 -->
    <div class="container mx-auto px-6 py-8">
        
        <!-- 닥터조님 프로필 소개 -->
        <section class="mb-12" data-aos="fade-up">
            <div class="gradient-bg text-white rounded-3xl p-8 shadow-2xl">
                <div class="text-center">
                    <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-user-graduate text-4xl"></i>
                    </div>
                    <h2 class="text-4xl font-bold mb-2"><?php echo $doctor_jo['name']; ?> (<?php echo $doctor_jo['korean_name']; ?>)</h2>
                    <p class="text-xl mb-2"><?php echo $doctor_jo['title']; ?></p>
                    <p class="text-lg opacity-90"><?php echo $doctor_jo['company']; ?></p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <?php foreach($doctor_jo['specialties'] as $specialty): ?>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-sm"><?php echo $specialty; ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="grid md:grid-cols-3 gap-6 mt-8">
                        <div class="bg-white/20 p-4 rounded-xl">
                            <div class="text-2xl font-bold"><?php echo $doctor_jo['generation']; ?>세</div>
                            <div class="text-sm opacity-90">현재 세대</div>
                        </div>
                        <div class="bg-white/20 p-4 rounded-xl">
                            <div class="text-2xl font-bold"><?php echo $stats['total_generations']; ?>대</div>
                            <div class="text-sm opacity-90">직계 조상</div>
                        </div>
                        <div class="bg-white/20 p-4 rounded-xl">
                            <div class="text-2xl font-bold"><?php echo number_format($stats['managed_by_doctor_jo']); ?>명</div>
                            <div class="text-sm opacity-90">관리 인원</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 직계 조상 족보 -->
        <section class="mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center" data-aos="fade-up">
                <i class="fas fa-users mr-3 text-blue-600"></i>직계 조상 족보 (1세 ~ <?php echo $doctor_jo['generation']; ?>세)
            </h2>
            
            <div class="max-w-4xl mx-auto">
                <?php foreach($direct_lineage as $generation => $ancestor): ?>
                <div class="generation-line mb-10" data-aos="fade-up" data-aos-delay="<?php echo $generation * 50; ?>">
                    <div class="lineage-card bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl <?php echo $generation == $doctor_jo['generation'] ? 'current-generation' : ''; ?>"
                         onclick="showPersonDetail(<?php echo $generation; ?>, '<?php echo $ancestor['name']; ?>')">
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                    <?php echo $generation; ?>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800"><?php echo $ancestor['name']; ?></h3>
                                    <p class="text-gray-600"><?php echo $ancestor['title']; ?></p>
                                    <p class="text-sm text-gray-500"><?php echo $ancestor['year']; ?> | <?php echo $ancestor['location']; ?></p>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <?php if($generation == $doctor_jo['generation']): ?>
                                <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full text-sm font-bold">
                                    <i class="fas fa-star mr-1"></i>현재 세대
                                </span>
                                <?php else: ?>
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm">
                                    <?php echo $doctor_jo['generation'] - $generation; ?>대조
                                </span>
                                <?php endif; ?>
                                
                                <div class="mt-2">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <?php if($generation == $doctor_jo['generation']): ?>
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div class="text-center">
                                    <i class="fas fa-graduation-cap text-blue-500 text-lg mb-1"></i>
                                    <div class="font-semibold">박사학위</div>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-building text-green-500 text-lg mb-1"></i>
                                    <div class="font-semibold">대표이사</div>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-chart-line text-purple-500 text-lg mb-1"></i>
                                    <div class="font-semibold">컨설팅</div>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-code text-red-500 text-lg mb-1"></i>
                                    <div class="font-semibold">개발자</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 주요 메뉴 카드 -->
        <section class="mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center" data-aos="fade-up">
                <i class="fas fa-th-large mr-3 text-blue-600"></i>주요 기능
            </h2>
            
            <div class="grid md:grid-cols-3 gap-8">
                <!-- 인물 검색 -->
                <div class="menu-card bg-white rounded-2xl p-6 shadow-xl" data-aos="zoom-in" data-aos-delay="100">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-search text-3xl text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">인물 검색</h3>
                        <p class="text-gray-600 mb-4">이름, 세대, 거주지별로 족보 인물을 검색합니다</p>
                        <button onclick="location.href='search.php'" class="w-full bg-blue-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>검색하기
                        </button>
                    </div>
                </div>

                <!-- 가계도 보기 -->
                <div class="menu-card bg-white rounded-2xl p-6 shadow-xl" data-aos="zoom-in" data-aos-delay="200">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-sitemap text-3xl text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">가계도 보기</h3>
                        <p class="text-gray-600 mb-4">시각적인 가계도로 족보 관계를 한눈에 확인합니다</p>
                        <button onclick="location.href='family_tree.php'" class="w-full bg-green-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-green-700 transition-colors">
                            <i class="fas fa-sitemap mr-2"></i>가계도 보기
                        </button>
                    </div>
                </div>

                <!-- 고급 기능 -->
                <div class="menu-card bg-white rounded-2xl p-6 shadow-xl" data-aos="zoom-in" data-aos-delay="300">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-rocket text-3xl text-purple-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">고급 기능</h3>
                        <p class="text-gray-600 mb-4">AI 분석, 3D 시각화, 음성 검색 등 혁신 기능</p>
                        <button onclick="showAdvancedFeatures()" class="w-full bg-purple-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-purple-700 transition-colors">
                            <i class="fas fa-rocket mr-2"></i>고급 기능
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- 통계 정보 -->
        <section class="mb-12">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-3xl p-8 shadow-2xl" data-aos="fade-up">
                <h2 class="text-2xl font-bold mb-6 text-center">
                    <i class="fas fa-chart-bar mr-3"></i>족보 현황
                </h2>
                
                <div class="grid md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo number_format($stats['total_family_members']); ?>명</div>
                        <div class="text-sm opacity-90">전체 족보 인원</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $stats['total_generations']; ?>세대</div>
                        <div class="text-sm opacity-90">기록된 세대</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo number_format($stats['managed_by_doctor_jo']); ?>명</div>
                        <div class="text-sm opacity-90"><?php echo $doctor_jo['name']; ?>님 관리</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold">24/7</div>
                        <div class="text-sm opacity-90">온라인 서비스</div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- 인물 상세 모달 -->
    <div id="personModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 max-w-md mx-4 w-full">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold" id="modalTitle">인물 상세정보</h3>
                <button onclick="closePersonModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div id="modalContent">
                <!-- 내용이 동적으로 삽입됩니다 -->
            </div>
            <div class="mt-6 text-center">
                <button onclick="closePersonModal()" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    닫기
                </button>
                <button id="detailPageBtn" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors ml-2">
                    상세페이지
                </button>
            </div>
        </div>
    </div>

    <!-- 고급 기능 모달 -->
    <div id="advancedModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 max-w-2xl mx-4 w-full">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">🚀 고급 기능</h3>
                <button onclick="closeAdvancedModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                <a href="ai_analyzer.php" class="block p-4 bg-purple-100 rounded-xl hover:bg-purple-200 transition-colors">
                    <i class="fas fa-brain text-2xl text-purple-600 mb-2"></i>
                    <h4 class="font-bold">AI 족보 분석</h4>
                    <p class="text-sm text-gray-600">혈통 패턴 분석</p>
                </a>
                <a href="tree_3d.php" class="block p-4 bg-green-100 rounded-xl hover:bg-green-200 transition-colors">
                    <i class="fas fa-cube text-2xl text-green-600 mb-2"></i>
                    <h4 class="font-bold">3D 족보 시각화</h4>
                    <p class="text-sm text-gray-600">3차원 가계도</p>
                </a>
                <a href="voice_search.php" class="block p-4 bg-red-100 rounded-xl hover:bg-red-200 transition-colors">
                    <i class="fas fa-microphone text-2xl text-red-600 mb-2"></i>
                    <h4 class="font-bold">음성 검색</h4>
                    <p class="text-sm text-gray-600">음성으로 검색</p>
                </a>
            </div>
        </div>
    </div>

    <!-- 푸터 -->
    <footer class="gradient-bg text-white py-8">
        <div class="container mx-auto px-6 text-center">
            <h3 class="text-lg font-bold mb-2">창녕조씨 직계 족보 시스템</h3>
            <p class="text-sm opacity-90 mb-4">개발·운영: <?php echo $doctor_jo['name']; ?> (<?php echo $doctor_jo['company']; ?>)</p>
            <p class="text-xs opacity-75">© 2024 창녕조씨 족보. Cafe24 호스팅 | 서버시간: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </footer>

    <script>
        // AOS 초기화
        AOS.init({
            duration: 600,
            easing: 'ease-in-out',
            once: true
        });

        // 모바일 메뉴 토글
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }

        // 인물 상세정보 모달
        function showPersonDetail(generation, name) {
            const modal = document.getElementById('personModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            const detailBtn = document.getElementById('detailPageBtn');
            
            title.textContent = `${generation}세 - ${name}`;
            
            // 실제로는 데이터베이스에서 정보를 가져와야 합니다
            const personData = getPersonData(generation, name);
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user text-2xl text-blue-600"></i>
                        </div>
                        <h4 class="text-xl font-bold">${name}</h4>
                        <p class="text-gray-600">${personData.title}</p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <strong>세대:</strong> ${generation}세
                            </div>
                            <div>
                                <strong>시대:</strong> ${personData.period}
                            </div>
                            <div>
                                <strong>거주지:</strong> ${personData.location}
                            </div>
                            <div>
                                <strong>관계:</strong> ${personData.relationship}
                            </div>
                        </div>
                    </div>
                    
                    ${generation == <?php echo $doctor_jo['generation']; ?> ? `
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <h5 class="font-bold text-yellow-800 mb-2">전문 분야</h5>
                        <div class="flex flex-wrap gap-1">
                            ${<?php echo json_encode($doctor_jo['specialties']); ?>.map(s => 
                                `<span class="px-2 py-1 bg-yellow-200 text-yellow-800 rounded text-xs">${s}</span>`
                            ).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            detailBtn.onclick = () => {
                window.location.href = `person_detail.php?generation=${generation}&name=${encodeURIComponent(name)}`;
            };
            
            modal.classList.remove('hidden');
        }

        function closePersonModal() {
            document.getElementById('personModal').classList.add('hidden');
        }

        // 고급 기능 모달
        function showAdvancedFeatures() {
            document.getElementById('advancedModal').classList.remove('hidden');
        }

        function closeAdvancedModal() {
            document.getElementById('advancedModal').classList.add('hidden');
        }

        // 인물 데이터 가져오기 (실제로는 AJAX로 서버에서 가져와야 함)
        function getPersonData(generation, name) {
            const lineageData = <?php echo json_encode($direct_lineage); ?>;
            const person = lineageData[generation];
            
            // 관계 계산
            const currentGen = <?php echo $doctor_jo['generation']; ?>;
            let relationship = '';
            
            if (generation == currentGen) {
                relationship = '본인';
            } else if (generation == currentGen - 1) {
                relationship = '아버지';
            } else if (generation == currentGen - 2) {
                relationship = '할아버지';
            } else if (generation == currentGen - 3) {
                relationship = '증조할아버지';
            } else if (generation < currentGen - 3) {
                relationship = `${currentGen - generation}대조`;
            }
            
            return {
                title: person.title,
                period: person.year,
                location: person.location,
                relationship: relationship
            };
        }

        // 외부 클릭시 모달 닫기
        window.onclick = function(event) {
            const personModal = document.getElementById('personModal');
            const advancedModal = document.getElementById('advancedModal');
            
            if (event.target == personModal) {
                closePersonModal();
            }
            if (event.target == advancedModal) {
                closeAdvancedModal();
            }
        };
    </script>
</body>
</html>