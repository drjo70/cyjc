<?php
// 창녕조씨 족보 시스템 - 메인 대시보드
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 기본 설정 로드
if (file_exists('config.php')) {
    require_once 'config.php';
}

// 안전한 세션 시작
if (function_exists('safeSessionStart')) {
    safeSessionStart();
} else if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 기본값 설정
$user_info = null;
$is_logged_in = false;
$is_verified = false;

// 세션 디버그 정보 (임시)
if (ini_get('display_errors')) {
    error_log('세션 디버그: ' . json_encode([
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? 'not_set',
        'email' => $_SESSION['email'] ?? 'not_set',
        'name' => $_SESSION['name'] ?? 'not_set',
        'verification_status' => $_SESSION['verification_status'] ?? 'not_set',
        'all_session' => $_SESSION
    ]));
}

// 직접 세션 기반으로 로그인 상태 확인
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_verified = isset($_SESSION['verification_status']) && $_SESSION['verification_status'] === 'verified';

// 사용자 정보 설정
if ($is_logged_in) {
    $user_info = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['name'] ?? '사용자',
        'email' => $_SESSION['email'] ?? '',
        'verification_status' => $_SESSION['verification_status'] ?? 'pending'
    ];
} else {
    $user_info = null;
}

// 디버그: 세션 정보를 화면에 출력 (임시)
$debug_session_info = "
<div style='position:fixed;top:10px;right:10px;background:yellow;padding:10px;border:2px solid red;z-index:9999;font-size:12px;max-width:300px;'>
<strong>🚨 세션 디버그 (임시)</strong><br>
세션ID: " . session_id() . "<br>
user_id: " . ($_SESSION['user_id'] ?? 'NOT_SET') . "<br>
name: " . ($_SESSION['name'] ?? 'NOT_SET') . "<br>
verification_status: " . ($_SESSION['verification_status'] ?? 'NOT_SET') . "<br>
is_logged_in: " . ($is_logged_in ? 'TRUE' : 'FALSE') . "<br>
is_verified: " . ($is_verified ? 'TRUE' : 'FALSE') . "<br>
isLoggedIn(): " . (isLoggedIn() ? 'TRUE' : 'FALSE') . "<br>
isVerifiedMember(): " . (isVerifiedMember() ? 'TRUE' : 'FALSE') . "
</div>";

// 환영 메시지 처리
$welcome_message = '';
if (isset($_GET['welcome']) && $is_verified) {
    $welcome_message = '가문 구성원 인증이 완료되었습니다! 족보 시스템에 오신 것을 환영합니다.';
} elseif (isset($_GET['login']) && $_GET['login'] === 'success' && $is_logged_in) {
    if ($is_verified) {
        $welcome_message = '카카오 로그인이 완료되었습니다! 환영합니다.';
    } else {
        $welcome_message = '카카오 로그인이 완료되었습니다. 가문 구성원 인증을 기다리고 있습니다.';
    }
}

// 데이터베이스 연결 설정 (Cafe24 환경)
$db_config = [
    'host' => 'localhost',
    'dbname' => 'cyjc25',  // Cafe24 실제 DB명
    'username' => 'cyjc25',  // Cafe24 실제 사용자명
    'password' => 'whdudrnr!!70'  // Cafe24 실제 비밀번호
];

// 개발/테스트 환경에서는 DB 없이도 작동하도록 설정
$use_database = true;
if (!extension_loaded('pdo_mysql')) {
    $use_database = false;
    $error_message = "MySQL PDO 확장이 설치되지 않았습니다.";
}

if ($use_database) {
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
            $db_config['username'],
            $db_config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 5  // 5초 타임아웃
            ]
        );
    } catch (PDOException $e) {
        $use_database = false;
        // 개발 환경에서는 상세 오류, 운영에서는 일반 메시지
        $error_message = "데이터베이스 연결에 실패했습니다.";
        if (ini_get('display_errors')) {
            $error_message .= " 오류: " . $e->getMessage();
        }
        // DB 없이도 계속 실행
        $pdo = null;
    }
} else {
    $pdo = null;
}

// 통계 데이터 가져오기 (안전한 방식)
$stats = [
    'total_members' => 150,  // 기본값 (DB 없을 때)
    'generations' => 25,
    'recent_updates' => 5
];

if (isset($pdo) && $pdo !== null) {
    try {
        // 전체 인원 수
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM family_members");
        $stats['total_members'] = $stmt->fetch()['count'] ?? 0;
        
        // 세대 수
        $stmt = $pdo->query("SELECT COUNT(DISTINCT generation) as count FROM family_members WHERE generation IS NOT NULL");
        $stats['generations'] = $stmt->fetch()['count'] ?? 0;
        
        // 최근 등록된 인원 수 (ID 기준 상위 30명)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM (SELECT id FROM family_members ORDER BY id DESC LIMIT 30) as recent");
        $stats['recent_updates'] = $stmt->fetch()['count'] ?? 0;
        
    } catch (PDOException $e) {
        // 통계 조회 실패 시 기본값 유지
        error_log("통계 조회 실패: " . $e->getMessage());
    }
}

// 최근 등록된 인물들 가져오기 (샘플 데이터로 대체)
$recent_persons = [
    ['name' => '조계룡', 'generation' => 1, 'birth_year' => 1320, 'created_at' => '2024-09-01 10:00:00'],
    ['name' => '조인옥', 'generation' => 2, 'birth_year' => 1350, 'created_at' => '2024-09-10 14:30:00'],
    ['name' => '조말생', 'generation' => 2, 'birth_year' => 1355, 'created_at' => '2024-09-15 09:15:00'],
    ['name' => '조서', 'generation' => 3, 'birth_year' => 1380, 'created_at' => '2024-09-17 16:45:00'],
    ['name' => '조변', 'generation' => 3, 'birth_year' => 1385, 'created_at' => '2024-09-18 11:20:00']
];

if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("
            SELECT name, name_hanja, generation, birth_date, id
            FROM family_members 
            ORDER BY id DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $db_recent_persons = $stmt->fetchAll();
        
        // 데이터 형식 맞추기
        $recent_persons = [];
        foreach ($db_recent_persons as $person) {
            $recent_persons[] = [
                'name' => $person['name'],
                'name_hanja' => $person['name_hanja'],
                'generation' => $person['generation'],
                'birth_year' => $person['birth_date'] ? date('Y', strtotime($person['birth_date'])) : null,
                'created_at' => date('Y-m-d H:i:s') // 기본값
            ];
        }
    } catch (PDOException $e) {
        error_log("최근 인물 조회 실패: " . $e->getMessage());
        // 오류 시 샘플 데이터 유지
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#4f46e5">
    <meta name="description" content="창녕조씨 디지털 족보 관리 시스템 - 가문의 역사를 언제 어디서나">
    <meta name="keywords" content="창녕조씨, 족보, 가문, 계보, 디지털족보">
    
    <!-- PWA 설정 -->
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/static/icon-192.png">
    
    <title>창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js"></script>
    <link href="/static/mobile-optimized.css" rel="stylesheet">
    <script>
        // TailwindCSS 모바일 최적화 설정
        tailwind.config = {
            theme: {
                extend: {
                    screens: {
                        'xs': '475px',
                    },
                    spacing: {
                        'safe-top': 'env(safe-area-inset-top)',
                        'safe-bottom': 'env(safe-area-inset-bottom)',
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .menu-button {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transition: all 0.3s ease;
            padding: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
        }
        .menu-button:hover {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(79, 70, 229, 0.4);
        }
        .doctor-profile {
            background: linear-gradient(135deg, #059669 0%, #0d9488 100%);
        }
        
        /* 모바일 최적화 */
        @media (max-width: 768px) {
            /* 터치 타겟 최소 44px */
            .touch-target {
                min-height: 44px;
                min-width: 44px;
            }
            
            /* 모바일 버튼 크기 증가 */
            .mobile-btn {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
                line-height: 1.5;
            }
            
            /* 모바일 카드 간격 조정 */
            .mobile-card {
                margin-bottom: 1rem;
                padding: 1rem;
            }
            
            /* 모바일 텍스트 크기 */
            .mobile-text-lg {
                font-size: 1.125rem;
                line-height: 1.75rem;
            }
            
            /* 모바일 그리드 조정 */
            .mobile-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            /* 스크롤 개선 */
            .mobile-scroll {
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
            }
        }
        
        /* Safe area 지원 */
        @supports (padding: max(0px)) {
            .safe-area-top {
                padding-top: max(1rem, env(safe-area-inset-top));
            }
            .safe-area-bottom {
                padding-bottom: max(1rem, env(safe-area-inset-bottom));
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php echo $debug_session_info; ?>
    <?php include 'common_header.php'; ?>
    <!-- 대형 헤더 -->
    <header class="gradient-bg shadow-2xl">
        <div class="container mx-auto px-6 py-8">
            <!-- 상단 로고 및 타이틀 -->
            <div class="text-center mb-8">
                <h1 class="text-5xl font-bold text-white mb-4">
                    <i class="fas fa-tree mr-4"></i>창녕조씨 족보 시스템
                </h1>
                <p class="text-xl text-indigo-100 font-medium">천년의 뿌리, 미래로 이어가는 가문의 역사</p>
            </div>

            <!-- 사용자 프로필 섹션 -->
            <div class="doctor-profile rounded-2xl p-6 mb-8 text-white">
                <div class="flex items-center justify-center space-x-4">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                        <?php if ($is_verified): ?>
                            <i class="fas fa-user-check text-2xl text-white"></i>
                        <?php elseif ($is_logged_in): ?>
                            <i class="fas fa-user-clock text-2xl text-white"></i>
                        <?php else: ?>
                            <i class="fas fa-tree text-2xl text-white"></i>
                        <?php endif; ?>
                    </div>
                    <div class="text-center">
                        <?php if ($is_verified && $user_info): ?>
                            <h2 class="text-2xl font-bold"><?= htmlspecialchars($user_info['family_name'] ?? $user_info['name'] ?? '사용자') ?>님 환영합니다!</h2>
                            <p class="text-emerald-100">
                                <?php if (isset($user_info['generation']) && $user_info['generation']): ?>
                                    <?= $user_info['generation'] ?>세대 가문 구성원
                                <?php endif; ?>
                                <?php if (isset($user_info['name_hanja']) && $user_info['name_hanja']): ?>
                                    | <?= htmlspecialchars($user_info['name_hanja']) ?>
                                <?php endif; ?>
                            </p>
                            <p class="text-emerald-100 text-sm mt-1">인증된 창녕조씨 족보 시스템 사용자</p>
                            <a href="logout.php" class="inline-block mt-2 px-3 py-1 bg-white bg-opacity-20 rounded-full text-sm hover:bg-opacity-30 transition-colors">
                                <i class="fas fa-sign-out-alt mr-1"></i>로그아웃
                            </a>
                        <?php elseif ($is_logged_in && $user_info): ?>
                            <h2 class="text-2xl font-bold"><?= htmlspecialchars($user_info['name'] ?? '사용자') ?>님 환영합니다!</h2>
                            <p class="text-yellow-100">가문 구성원 인증을 완료해주세요</p>
                            <p class="text-yellow-100 text-sm mt-1">인증 후 모든 기능을 이용하실 수 있습니다</p>
                            <div class="mt-2 space-x-2">
                                <a href="verification.php" class="inline-block px-3 py-1 bg-yellow-500 text-white rounded-full text-sm hover:bg-yellow-600 transition-colors">
                                    <i class="fas fa-user-check mr-1"></i>인증 완료하기
                                </a>
                                <a href="logout.php" class="inline-block px-3 py-1 bg-white bg-opacity-20 text-white rounded-full text-sm hover:bg-opacity-30 transition-colors">
                                    <i class="fas fa-sign-out-alt mr-1"></i>로그아웃
                                </a>
                            </div>
                        <?php else: ?>
                            <h2 class="text-2xl font-bold">창녕조씨 족보 시스템에 오신 것을 환영합니다!</h2>
                            <p class="text-emerald-100">가문 구성원 로그인 후 모든 기능을 이용하실 수 있습니다</p>
                            <p class="text-emerald-100 text-sm mt-1">가문의 역사를 디지털로 보존하고 전승하는 현대적 족보 시스템</p>
                            <a href="login.php" class="inline-block mt-2 px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                                <i class="fas fa-sign-in-alt mr-1"></i>가문 구성원 로그인
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 대형 메뉴 버튼들 (모바일 최적화) -->
            <?php 
            // 관리자 권한 확인 (access_level 1) - 안전하게
            $is_admin = false;
            try {
                if ($is_logged_in && $is_verified) {
                    $is_admin = (isset($_SESSION['access_level']) && $_SESSION['access_level'] == 1);
                }
            } catch (Exception $e) {
                error_log('관리자 권한 확인 오류: ' . $e->getMessage());
            }
            $menu_grid_class = $is_admin ? 'lg:grid-cols-3' : 'lg:grid-cols-2';
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 <?= $menu_grid_class ?> gap-4 md:gap-6">
                <a href="search.php" class="menu-button text-white rounded-2xl text-center block card-hover touch-target mobile-btn">
                    <i class="fas fa-search text-2xl md:text-3xl mb-2 md:mb-3 block"></i>
                    <span class="text-lg md:text-xl mobile-text-lg">인물 검색</span>
                    <p class="text-indigo-200 text-xs md:text-sm mt-1 md:mt-2">이름으로 빠른 검색</p>
                </a>

                <a href="family_lineage.php" class="menu-button text-white rounded-2xl text-center block card-hover touch-target mobile-btn">
                    <i class="fas fa-project-diagram text-2xl md:text-3xl mb-2 md:mb-3 block"></i>
                    <span class="text-lg md:text-xl mobile-text-lg">직계 혈통</span>
                    <p class="text-indigo-200 text-xs md:text-sm mt-1 md:mt-2">1세대부터 현재까지 직계</p>
                </a>

                <!-- 관리자 메뉴 (Level 1만 표시) -->
                <?php if ($is_admin): ?>
                    <a href="admin.php" class="menu-button bg-gradient-to-r from-yellow-500 to-orange-600 text-white rounded-2xl text-center block card-hover touch-target mobile-btn sm:col-span-2 lg:col-span-1 border-2 border-yellow-300">
                        <i class="fas fa-crown text-2xl md:text-3xl mb-2 md:mb-3 block text-yellow-200"></i>
                        <span class="text-lg md:text-xl mobile-text-lg font-semibold">관리자</span>
                        <p class="text-yellow-100 text-xs md:text-sm mt-1 md:mt-2">시스템 관리 도구</p>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="container mx-auto px-6 py-8">
        <!-- 통계 섹션 (모바일 최적화) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
            <div class="bg-white rounded-xl shadow-lg p-4 md:p-6 card-hover mobile-card">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 md:p-4 rounded-full">
                        <i class="fas fa-users text-blue-600 text-xl md:text-2xl"></i>
                    </div>
                    <div class="ml-3 md:ml-4">
                        <h3 class="text-gray-500 text-xs md:text-sm">총 인원</h3>
                        <p class="text-xl md:text-2xl font-bold text-gray-800"><?= number_format($stats['total_members']) ?>명</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-4 md:p-6 card-hover mobile-card">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 md:p-4 rounded-full">
                        <i class="fas fa-layer-group text-green-600 text-xl md:text-2xl"></i>
                    </div>
                    <div class="ml-3 md:ml-4">
                        <h3 class="text-gray-500 text-xs md:text-sm">총 세대</h3>
                        <p class="text-xl md:text-2xl font-bold text-gray-800"><?= $stats['generations'] ?>세대</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-4 md:p-6 card-hover mobile-card sm:col-span-2 lg:col-span-1">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 md:p-4 rounded-full">
                        <i class="fas fa-clock text-purple-600 text-xl md:text-2xl"></i>
                    </div>
                    <div class="ml-3 md:ml-4">
                        <h3 class="text-gray-500 text-xs md:text-sm">최근 업데이트</h3>
                        <p class="text-xl md:text-2xl font-bold text-gray-800"><?= $stats['recent_updates'] ?>건</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 빠른 메뉴 카드들 (모바일 최적화) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-8 mb-6 md:mb-8">
            <!-- 빠른 검색 (모바일 최적화) -->
            <div class="bg-white rounded-xl shadow-lg p-4 md:p-8 card-hover mobile-card">
                <h3 class="text-xl md:text-2xl font-bold text-gray-800 mb-4 md:mb-6">
                    <i class="fas fa-search text-blue-600 mr-2"></i>빠른 검색
                </h3>
                <form id="quick-search-form" class="space-y-3 md:space-y-4">
                    <div>
                        <input type="text" id="quick-name" name="name" placeholder="이름을 입력하세요..." 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               autocomplete="off">
                    </div>
                    <div class="flex space-x-4">
                        <select id="quick-generation" name="generation" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">전체 세대</option>
                            <?php for ($i = 1; $i <= 50; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?>세대</option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search mr-1"></i>검색
                        </button>
                    </div>
                </form>
                
                <!-- 검색 결과 미리보기 (AJAX) -->
                <div id="quick-search-results" class="mt-4 max-h-60 overflow-y-auto hidden"></div>
            </div>

            <!-- 시스템 상태 -->
            <div class="bg-white rounded-xl shadow-lg p-8 card-hover">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-server text-green-600 mr-2"></i>시스템 상태
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">데이터베이스</span>
                        <span class="<?= isset($pdo) ? 'text-green-600' : 'text-red-600' ?>">
                            <i class="fas fa-circle text-xs mr-1"></i>
                            <?= isset($pdo) ? '연결됨' : '연결 실패' ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">PHP 버전</span>
                        <span class="text-blue-600"><?= PHP_VERSION ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">메모리 사용량</span>
                        <span class="text-orange-600"><?= round(memory_get_usage() / 1024 / 1024, 2) ?>MB</span>
                    </div>
                    <?php if (isset($error_message)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <p class="text-red-600 text-sm"><?= htmlspecialchars($error_message) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 최근 등록된 인물들 -->
        <?php if (!empty($recent_persons)): ?>
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h3 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-user-plus text-indigo-600 mr-2"></i>최근 등록된 인물
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 text-gray-600">이름</th>
                            <th class="text-left py-3 px-4 text-gray-600">세대</th>
                            <th class="text-left py-3 px-4 text-gray-600">생년</th>
                            <th class="text-left py-3 px-4 text-gray-600">등록일</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_persons as $person): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <span class="font-medium text-gray-800"><?= htmlspecialchars($person['name']) ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm">
                                    <?= $person['generation'] ? $person['generation'] . '세대' : '미기입' ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                <?= $person['birth_year'] ?: '미상' ?>년
                            </td>
                            <td class="py-3 px-4 text-gray-500 text-sm">
                                <?= date('Y.m.d', strtotime($person['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- 푸터 -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-6 text-center">
            <p class="mb-4">&copy; 2024 창녕조씨 족보 시스템. 닥터조 개발.</p>
            <p class="text-gray-400 text-sm">
                가문의 역사를 디지털로 보존하고 전승하는 현대적 족보 시스템
            </p>
            <div class="mt-4">
                <span class="text-gray-400 text-xs">
                    Last updated: <?= date('Y.m.d H:i') ?>
                </span>
            </div>
        </div>
    </footer>

    <script>
        // 빠른 검색 AJAX 기능
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.getElementById('quick-search-form');
            const nameInput = document.getElementById('quick-name');
            const generationSelect = document.getElementById('quick-generation');
            const resultsDiv = document.getElementById('quick-search-results');
            
            let searchTimeout;
            
            // 실시간 검색 (타이핑 중)
            nameInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => {
                        performSearch(query, generationSelect.value, false);
                    }, 300);
                } else {
                    resultsDiv.classList.add('hidden');
                }
            });
            
            // 폼 제출 (엔터키 또는 검색 버튼)
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const query = nameInput.value.trim();
                const generation = generationSelect.value;
                
                if (query.length >= 1) {
                    // 검색 페이지로 이동하는 대신 여기서 검색 결과 표시
                    performSearch(query, generation, true);
                } else {
                    alert('검색할 이름을 입력해주세요.');
                }
            });
            
            // 검색 실행 함수
            async function performSearch(name, generation, showAll) {
                try {
                    const params = new URLSearchParams({
                        ajax: '1',
                        name: name,
                        generation: generation || '',
                        limit: showAll ? '20' : '5'
                    });
                    
                    const response = await fetch(`search.php?${params}`);
                    const data = await response.json();
                    
                    displaySearchResults(data, showAll);
                } catch (error) {
                    console.error('검색 오류:', error);
                    resultsDiv.innerHTML = '<div class="p-3 text-red-600 text-sm">검색 중 오류가 발생했습니다.</div>';
                    resultsDiv.classList.remove('hidden');
                }
            }
            
            // 검색 결과 표시
            function displaySearchResults(data, showAll) {
                if (!data.success || data.results.length === 0) {
                    resultsDiv.innerHTML = '<div class="p-3 text-gray-500 text-sm">검색 결과가 없습니다.</div>';
                    resultsDiv.classList.remove('hidden');
                    return;
                }
                
                let html = '<div class="border-t pt-3">';
                html += '<div class="text-sm text-gray-600 mb-2">검색 결과 (' + data.results.length + '개)</div>';
                
                data.results.forEach(person => {
                    html += `
                        <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded border-b last:border-b-0">
                            <div class="flex-1">
                                <div class="font-medium text-gray-800">${person.name}</div>
                                <div class="text-sm text-gray-500">
                                    ${person.generation}세대
                                    ${person.name_hanja ? ' • ' + person.name_hanja : ''}
                                    ${person.birth_date ? ' • ' + person.birth_date.substring(0,4) + '년생' : ''}
                                </div>
                            </div>
                            <div class="flex space-x-1">
                                <a href="person_detail.php?person_code=${person.person_code}" 
                                   class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200">
                                    상세
                                </a>
                                <a href="family_lineage.php?person_code=${person.person_code}" 
                                   class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200">
                                    가계도
                                </a>
                            </div>
                        </div>
                    `;
                });
                
                if (!showAll && data.total > data.results.length) {
                    html += `
                        <div class="text-center pt-2">
                            <a href="search.php?name=${encodeURIComponent(nameInput.value)}&generation=${generationSelect.value}" 
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                전체 ${data.total}개 결과 보기 →
                            </a>
                        </div>
                    `;
                }
                
                html += '</div>';
                resultsDiv.innerHTML = html;
                resultsDiv.classList.remove('hidden');
            }
            
            // 외부 클릭시 검색 결과 숨기기
            document.addEventListener('click', function(e) {
                if (!searchForm.contains(e.target)) {
                    resultsDiv.classList.add('hidden');
                }
            });
            
            // 카드 애니메이션 효과
            const cards = document.querySelectorAll('.card-hover');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // 메뉴 버튼 클릭 효과
            const menuButtons = document.querySelectorAll('.menu-button');
            menuButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });

            // PWA 서비스 워커 등록
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js')
                    .then((registration) => {
                        console.log('서비스 워커 등록 성공:', registration.scope);
                        
                        // 푸시 알림 초기화 (Firebase 설정 필요)
                        initializePushNotifications();
                    })
                    .catch((error) => {
                        console.log('서비스 워커 등록 실패:', error);
                    });
            }
            
            // 푸시 알림 초기화 함수
            function initializePushNotifications() {
                // 알림 권한 확인
                if ('Notification' in window) {
                    // 알림 권한 요청
                    if (Notification.permission === 'default') {
                        // 사용자에게 알림 권한 요청할지 묻기
                        showPushPermissionPrompt();
                    } else if (Notification.permission === 'granted') {
                        console.log('푸시 알림이 이미 허용되었습니다.');
                        // Firebase FCM 토큰 등록 (실제 Firebase 설정 필요)
                    }
                }
            }
            
            // 푸시 알림 권한 요청 프롬프트
            function showPushPermissionPrompt() {
                const promptDiv = document.createElement('div');
                promptDiv.className = 'fixed bottom-4 left-4 right-4 bg-blue-600 text-white p-4 rounded-lg shadow-lg z-50 md:left-auto md:right-4 md:w-80';
                promptDiv.innerHTML = `
                    <div class="flex items-start gap-3">
                        <i class="fas fa-bell text-xl mt-1"></i>
                        <div class="flex-1">
                            <h4 class="font-bold mb-1">알림 받기</h4>
                            <p class="text-sm mb-3">족보 업데이트 소식을 받아보시겠어요?</p>
                            <div class="flex gap-2">
                                <button onclick="enablePushNotifications()" 
                                        class="bg-white text-blue-600 px-3 py-1 rounded text-sm hover:bg-gray-100">
                                    허용
                                </button>
                                <button onclick="this.closest('div').remove()" 
                                        class="bg-blue-700 text-white px-3 py-1 rounded text-sm hover:bg-blue-800">
                                    나중에
                                </button>
                            </div>
                        </div>
                        <button onclick="this.closest('div').remove()" 
                                class="text-white hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                document.body.appendChild(promptDiv);
                
                // 10초 후 자동으로 숨김
                setTimeout(() => {
                    if (promptDiv.parentNode) {
                        promptDiv.remove();
                    }
                }, 10000);
            }
            
            // 푸시 알림 활성화
            window.enablePushNotifications = async function() {
                try {
                    const permission = await Notification.requestPermission();
                    
                    if (permission === 'granted') {
                        console.log('푸시 알림이 허용되었습니다!');
                        
                        // 알림 테스트
                        new Notification('창녕조씨 족보', {
                            body: '알림이 성공적으로 설정되었습니다!',
                            icon: '/static/icon-192.png',
                            badge: '/static/icon-192.png'
                        });
                        
                        // 프롬프트 제거
                        document.querySelector('.fixed.bottom-4')?.remove();
                        
                        // 실제 FCM 토큰 등록은 Firebase 설정 후 구현
                        // await initializeFCM(); // firebase-config.js에서 구현
                        
                    } else {
                        console.log('푸시 알림이 거부되었습니다.');
                        alert('알림을 받으시려면 브라우저 설정에서 알림을 허용해주세요.');
                    }
                } catch (error) {
                    console.error('푸시 알림 설정 오류:', error);
                }
            }

            // PWA 설치 프롬프트
            let deferredPrompt;
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                
                // 설치 버튼 표시 (향후 추가 가능)
                console.log('PWA 설치 가능');
            });

            // 앱 설치 완료 감지
            window.addEventListener('appinstalled', (evt) => {
                console.log('PWA 설치 완료');
                // 설치 완료 알림 (향후 추가 가능)
            });
        });
    </script>
</body>
</html>