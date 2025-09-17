<?php
/**
 * 창녕조씨 족보 시스템 - 인물 상세페이지
 * 개별 인물의 상세 정보 표시
 * 
 * @author 닥터조 ((주)조유 대표이사)
 * @version 4.0
 * @since 2024-09-17
 */

session_start();
require_once 'config/database.php';

// URL 파라미터에서 인물 ID 가져오기
$person_id = $_GET['id'] ?? null;
$generation = $_GET['generation'] ?? null;
$name = $_GET['name'] ?? null;

// 샘플 족보 데이터 (실제로는 데이터베이스에서 가져와야 함)
$family_members = [
    1 => [
        'id' => 1, 'name' => '닥터조', 'korean_name' => '조○○', 'generation' => 15, 
        'birth_year' => 1970, 'death_year' => null, 'location' => '서울특별시', 
        'title' => '컴퓨터 IT 박사', 'company' => '(주)조유 대표이사',
        'father_name' => '조부경', 'mother_name' => '김○○', 'spouse_name' => '이○○',
        'children' => ['조현우', '조수정'],
        'education' => ['서울대학교 컴퓨터공학과 학사', '서울대학교 컴퓨터공학과 석사', 'KAIST 컴퓨터공학과 박사'],
        'career' => ['삼성전자 소프트웨어센터 (1995-2000)', '구글코리아 기술이사 (2000-2010)', '(주)조유 설립 및 대표이사 (2010-현재)'],
        'achievements' => ['AI 특허 15건 보유', '정부 ICT 자문위원', '창녕조씨 디지털 족보 시스템 개발'],
        'hobbies' => ['프로그래밍', '족보 연구', '등산', '독서'],
        'phone' => '010-1234-5678', 'email' => 'doctor.jo@joyu.co.kr',
        'address_detail' => '서울특별시 강남구 테헤란로 123'
    ],
    2 => [
        'id' => 2, 'name' => '조부경', 'korean_name' => '조부경', 'generation' => 14,
        'birth_year' => 1945, 'death_year' => null, 'location' => '서울특별시',
        'title' => '아버지', 'company' => '한국전력 (퇴직)',
        'father_name' => '조할아버지', 'mother_name' => '박○○', 'spouse_name' => '김○○',
        'children' => ['닥터조', '조영희'],
        'education' => ['서울공대 전기공학과'],
        'career' => ['한국전력 기술연구원 (1970-2005)'],
        'achievements' => ['전력시스템 개선 공로상'],
        'hobbies' => ['바둑', '서예', '등산'],
        'phone' => '010-9876-5432', 'email' => '',
        'address_detail' => '서울특별시 강동구 천호대로 456'
    ],
    3 => [
        'id' => 3, 'name' => '조할아버지', 'korean_name' => '조○○', 'generation' => 13,
        'birth_year' => 1920, 'death_year' => 1995, 'location' => '부산광역시',
        'title' => '할아버지', 'company' => '부산항만공사 (퇴직)',
        'father_name' => '조증조', 'mother_name' => '이○○', 'spouse_name' => '박○○',
        'children' => ['조부경', '조부용', '조부실'],
        'education' => ['부산상업학교'],
        'career' => ['부산항만공사 (1945-1980)'],
        'achievements' => ['6.25 참전 유공자', '항만 발전 공로상'],
        'hobbies' => ['낚시', '화투', '민요'],
        'phone' => '', 'email' => '',
        'address_detail' => '부산광역시 중구 중앙대로 789 (故)'
    ]
];

// 현재 보고 있는 인물 정보 가져오기
$current_person = null;

if ($person_id && isset($family_members[$person_id])) {
    $current_person = $family_members[$person_id];
} elseif ($generation && $name) {
    // 세대와 이름으로 검색
    foreach ($family_members as $member) {
        if ($member['generation'] == $generation && $member['name'] == $name) {
            $current_person = $member;
            break;
        }
    }
}

// 인물을 찾지 못한 경우
if (!$current_person) {
    header("Location: search.php");
    exit;
}

// 나이 계산
$age = $current_person['death_year'] ? 
    ($current_person['death_year'] - $current_person['birth_year']) . '세 (향년)' : 
    (2024 - $current_person['birth_year']) . '세';

// 관계 계산 (닥터조 기준)
function getRelationship($generation) {
    $doctor_generation = 15;
    $diff = $doctor_generation - $generation;
    
    if ($diff == 0) return $generation == 15 ? '본인' : '동세대';
    elseif ($diff == 1) return '아버지/어머니 세대';
    elseif ($diff == 2) return '할아버지/할머니 세대';
    elseif ($diff == 3) return '증조 세대';
    elseif ($diff > 3) return $diff . '대조 세대';
    elseif ($diff == -1) return '자녀 세대';
    elseif ($diff == -2) return '손자/손녀 세대';
    else return '후손 세대';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_person['name']; ?> | 창녕조씨 족보</title>
    
    <meta name="description" content="창녕조씨 <?php echo $current_person['name']; ?>(<?php echo $current_person['generation']; ?>세)의 상세 족보 정보">
    <meta name="keywords" content="창녕조씨, <?php echo $current_person['name']; ?>, <?php echo $current_person['generation']; ?>세, 족보">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .info-card {
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-2px);
            shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .profile-image {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- 헤더 -->
    <header class="gradient-bg text-white shadow-xl">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button onclick="history.back()" class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center hover:bg-white/30 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div>
                        <h1 class="text-xl font-bold"><?php echo $current_person['name']; ?></h1>
                        <p class="text-sm opacity-90"><?php echo $current_person['generation']; ?>세 · <?php echo $current_person['title']; ?></p>
                    </div>
                </div>
                
                <nav class="hidden md:flex items-center space-x-4">
                    <a href="index.php" class="px-4 py-2 hover:bg-white/20 rounded-lg transition-colors">
                        <i class="fas fa-home mr-2"></i>메인
                    </a>
                    <a href="search.php" class="px-4 py-2 hover:bg-white/20 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>검색
                    </a>
                    <a href="family_tree.php" class="px-4 py-2 hover:bg-white/20 rounded-lg transition-colors">
                        <i class="fas fa-sitemap mr-2"></i>가계도
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-6 py-8">
        
        <!-- 인물 프로필 -->
        <section class="mb-8" data-aos="fade-up">
            <div class="bg-white rounded-3xl p-8 shadow-2xl">
                <div class="grid md:grid-cols-3 gap-8">
                    
                    <!-- 프로필 이미지 및 기본 정보 -->
                    <div class="text-center">
                        <div class="w-32 h-32 profile-image rounded-full flex items-center justify-center mx-auto mb-6 text-white text-4xl font-bold">
                            <?php echo mb_substr($current_person['name'], -1); ?>
                        </div>
                        
                        <h2 class="text-3xl font-bold text-gray-800 mb-2"><?php echo $current_person['name']; ?></h2>
                        <?php if ($current_person['korean_name'] !== $current_person['name']): ?>
                        <p class="text-lg text-gray-600 mb-1"><?php echo $current_person['korean_name']; ?></p>
                        <?php endif; ?>
                        <p class="text-gray-600 mb-4"><?php echo $current_person['title']; ?></p>
                        
                        <div class="space-y-2">
                            <div class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full font-semibold">
                                <?php echo $current_person['generation']; ?>세대
                            </div>
                            <div class="px-4 py-2 bg-green-100 text-green-800 rounded-full">
                                <?php echo getRelationship($current_person['generation']); ?>
                            </div>
                            <?php if ($current_person['name'] === '닥터조'): ?>
                            <div class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full">
                                <i class="fas fa-star mr-1"></i>시스템 개발자
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 기본 정보 -->
                    <div class="md:col-span-2">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">기본 정보</h3>
                        
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="info-card bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-calendar text-blue-500 mr-3"></i>
                                    <span class="font-semibold">생년월일</span>
                                </div>
                                <p class="text-gray-700"><?php echo $current_person['birth_year']; ?>년생 (<?php echo $age; ?>)</p>
                            </div>
                            
                            <div class="info-card bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-map-marker-alt text-red-500 mr-3"></i>
                                    <span class="font-semibold">거주지</span>
                                </div>
                                <p class="text-gray-700"><?php echo $current_person['location']; ?></p>
                            </div>
                            
                            <?php if (!empty($current_person['company'])): ?>
                            <div class="info-card bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-building text-green-500 mr-3"></i>
                                    <span class="font-semibold">소속</span>
                                </div>
                                <p class="text-gray-700"><?php echo $current_person['company']; ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($current_person['phone'])): ?>
                            <div class="info-card bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-phone text-purple-500 mr-3"></i>
                                    <span class="font-semibold">연락처</span>
                                </div>
                                <p class="text-gray-700"><?php echo $current_person['phone']; ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($current_person['address_detail'])): ?>
                        <div class="info-card bg-gray-50 p-4 rounded-lg mt-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-home text-indigo-500 mr-3"></i>
                                <span class="font-semibold">상세 주소</span>
                            </div>
                            <p class="text-gray-700"><?php echo $current_person['address_detail']; ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- 가족 관계 -->
        <section class="mb-8" data-aos="fade-up">
            <div class="bg-white rounded-2xl p-6 shadow-xl">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-users mr-3 text-blue-600"></i>가족 관계
                </h3>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- 부모 -->
                    <div>
                        <h4 class="font-bold text-gray-700 mb-3">부모</h4>
                        <div class="space-y-2">
                            <?php if (!empty($current_person['father_name'])): ?>
                            <div class="flex items-center p-3 bg-blue-50 rounded-lg">
                                <i class="fas fa-male text-blue-600 mr-3"></i>
                                <span class="font-semibold">아버지: <?php echo $current_person['father_name']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($current_person['mother_name'])): ?>
                            <div class="flex items-center p-3 bg-pink-50 rounded-lg">
                                <i class="fas fa-female text-pink-600 mr-3"></i>
                                <span class="font-semibold">어머니: <?php echo $current_person['mother_name']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 배우자 및 자녀 -->
                    <div>
                        <h4 class="font-bold text-gray-700 mb-3">배우자 및 자녀</h4>
                        <div class="space-y-2">
                            <?php if (!empty($current_person['spouse_name'])): ?>
                            <div class="flex items-center p-3 bg-purple-50 rounded-lg">
                                <i class="fas fa-heart text-purple-600 mr-3"></i>
                                <span class="font-semibold">배우자: <?php echo $current_person['spouse_name']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($current_person['children'])): ?>
                            <div class="p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-child text-green-600 mr-3"></i>
                                    <span class="font-semibold">자녀:</span>
                                </div>
                                <div class="ml-6 space-y-1">
                                    <?php foreach ($current_person['children'] as $child): ?>
                                    <div class="text-gray-700">• <?php echo $child; ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 학력 및 경력 -->
        <div class="grid md:grid-cols-2 gap-8 mb-8">
            <!-- 학력 -->
            <?php if (!empty($current_person['education'])): ?>
            <section data-aos="fade-up" data-aos-delay="100">
                <div class="bg-white rounded-2xl p-6 shadow-xl h-full">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-graduation-cap mr-3 text-blue-600"></i>학력
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($current_person['education'] as $edu): ?>
                        <div class="flex items-start p-3 bg-blue-50 rounded-lg">
                            <i class="fas fa-certificate text-blue-600 mr-3 mt-1"></i>
                            <span class="text-gray-700"><?php echo $edu; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- 경력 -->
            <?php if (!empty($current_person['career'])): ?>
            <section data-aos="fade-up" data-aos-delay="200">
                <div class="bg-white rounded-2xl p-6 shadow-xl h-full">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-briefcase mr-3 text-green-600"></i>경력
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($current_person['career'] as $career): ?>
                        <div class="flex items-start p-3 bg-green-50 rounded-lg">
                            <i class="fas fa-building text-green-600 mr-3 mt-1"></i>
                            <span class="text-gray-700"><?php echo $career; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>

        <!-- 성과 및 취미 -->
        <div class="grid md:grid-cols-2 gap-8 mb-8">
            <!-- 주요 성과 -->
            <?php if (!empty($current_person['achievements'])): ?>
            <section data-aos="fade-up" data-aos-delay="300">
                <div class="bg-white rounded-2xl p-6 shadow-xl h-full">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-trophy mr-3 text-yellow-600"></i>주요 성과
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($current_person['achievements'] as $achievement): ?>
                        <div class="flex items-start p-3 bg-yellow-50 rounded-lg">
                            <i class="fas fa-award text-yellow-600 mr-3 mt-1"></i>
                            <span class="text-gray-700"><?php echo $achievement; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- 취미 -->
            <?php if (!empty($current_person['hobbies'])): ?>
            <section data-aos="fade-up" data-aos-delay="400">
                <div class="bg-white rounded-2xl p-6 shadow-xl h-full">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-heart mr-3 text-red-600"></i>취미 및 관심사
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($current_person['hobbies'] as $hobby): ?>
                        <span class="px-3 py-2 bg-red-50 text-red-700 rounded-full text-sm font-medium">
                            <i class="fas fa-star mr-1"></i><?php echo $hobby; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>

        <!-- 액션 버튼 -->
        <section class="mb-8" data-aos="fade-up">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-2xl p-6 shadow-xl">
                <div class="text-center mb-6">
                    <h3 class="text-xl font-bold mb-2">더 많은 정보</h3>
                    <p class="opacity-90">족보 시스템의 다양한 기능을 이용해보세요</p>
                </div>
                
                <div class="grid md:grid-cols-4 gap-4">
                    <button onclick="location.href='family_tree.php'" class="bg-white/20 hover:bg-white/30 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-sitemap text-2xl mb-2"></i>
                        <div class="text-sm">가계도 보기</div>
                    </button>
                    <button onclick="location.href='search.php'" class="bg-white/20 hover:bg-white/30 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-search text-2xl mb-2"></i>
                        <div class="text-sm">다른 인물 검색</div>
                    </button>
                    <button onclick="printProfile()" class="bg-white/20 hover:bg-white/30 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-print text-2xl mb-2"></i>
                        <div class="text-sm">프로필 인쇄</div>
                    </button>
                    <button onclick="shareProfile()" class="bg-white/20 hover:bg-white/30 p-4 rounded-lg text-center transition-colors">
                        <i class="fas fa-share-alt text-2xl mb-2"></i>
                        <div class="text-sm">공유하기</div>
                    </button>
                </div>
            </div>
        </section>
    </div>

    <script>
        // AOS 초기화
        AOS.init({
            duration: 600,
            easing: 'ease-in-out',
            once: true
        });

        // 프로필 인쇄
        function printProfile() {
            window.print();
        }

        // 프로필 공유
        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo $current_person["name"]; ?> - 창녕조씨 족보',
                    text: '<?php echo $current_person["generation"]; ?>세 <?php echo $current_person["title"]; ?>',
                    url: window.location.href
                });
            } else {
                // 클립보드에 URL 복사
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('URL이 클립보드에 복사되었습니다!');
                });
            }
        }

        // 뒤로가기 버튼 개선
        if (document.referrer && document.referrer !== window.location.href) {
            // 이전 페이지가 있는 경우
        } else {
            // 직접 접근한 경우 검색 페이지로
            document.querySelector('button[onclick="history.back()"]').onclick = () => {
                window.location.href = 'search.php';
            };
        }
    </script>
</body>
</html>