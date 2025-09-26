<?php
// 단순화된 관리자용 접속 통계 페이지
require_once 'config.php';

// 세션 시작 및 관리자 권한 체크
safeSessionStart();

// 로그인 여부 및 관리자 권한 확인
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 세션에서 access_level 확인 (1=관리자, 2=일반 등급)
$user_access_level = isset($_SESSION['access_level']) ? (int)$_SESSION['access_level'] : 99;
if ($user_access_level > 2) {
    header('Location: index.php');
    exit;
}

// 통계 기간 설정
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if ($days < 1 || $days > 365) $days = 7;

// 데이터베이스 연결
$pdo = getDbConnection();
$stats_available = false;

if ($pdo) {
    try {
        // access_logs 테이블 존재 확인
        $stmt = $pdo->query("SHOW TABLES LIKE 'access_logs'");
        if ($stmt->rowCount() > 0) {
            // 기본 통계 조회
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT ip_address) as unique_visitors,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    COUNT(CASE WHEN user_id IS NOT NULL THEN 1 END) as logged_users
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $basic_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 일별 통계
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(access_time) as visit_date,
                    COUNT(*) as visits,
                    COUNT(DISTINCT ip_address) as unique_visitors
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(access_time)
                ORDER BY visit_date DESC
                LIMIT 30
            ");
            $stmt->execute([$days]);
            $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 디바이스별 통계
            $stmt = $pdo->prepare("
                SELECT 
                    device_type,
                    COUNT(*) as visits,
                    ROUND(COUNT(*) * 100.0 / (
                        SELECT COUNT(*) FROM access_logs 
                        WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ), 1) as percentage
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY device_type
                ORDER BY visits DESC
            ");
            $stmt->execute([$days, $days]);
            $device_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 페이지별 통계
            $stmt = $pdo->prepare("
                SELECT 
                    page_url,
                    COUNT(*) as visits,
                    COUNT(DISTINCT ip_address) as unique_visitors
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY page_url
                ORDER BY visits DESC
                LIMIT 10
            ");
            $stmt->execute([$days]);
            $page_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 브라우저별 통계
            $stmt = $pdo->prepare("
                SELECT 
                    browser,
                    COUNT(*) as visits,
                    ROUND(COUNT(*) * 100.0 / (
                        SELECT COUNT(*) FROM access_logs 
                        WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ), 1) as percentage
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY) AND browser != 'unknown'
                GROUP BY browser
                ORDER BY visits DESC
                LIMIT 5
            ");
            $stmt->execute([$days, $days]);
            $browser_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 최근 접속자 (24시간) - users 테이블 없이 처리
            $stmt = $pdo->query("
                SELECT 
                    ip_address,
                    device_type,
                    browser,
                    access_time as last_visit,
                    user_id,
                    page_url
                FROM access_logs
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY access_time DESC
                LIMIT 20
            ");
            $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats_available = true;
            
        } else {
            $error_message = "access_logs 테이블이 존재하지 않습니다. 먼저 테이블을 생성해주세요.";
        }
    } catch (PDOException $e) {
        $error_message = "데이터베이스 오류: " . $e->getMessage();
    }
} else {
    $error_message = "데이터베이스 연결 실패";
}

// 페이지 이름 매핑
function getPageName($url) {
    $pages = [
        '/' => '메인 페이지',
        '/index.php' => '메인 페이지',
        '/search.php' => '인물 검색',
        '/family_lineage.php' => '직계 혈통',
        '/person_detail.php' => '인물 상세',
        '/genealogy_edit.php' => '족보 편집',
        '/relationship_tree.php' => '관계 분석',
        '/login.php' => '로그인',
        '/admin.php' => '관리 페이지',
        '/my_profile.php' => '내 정보'
    ];
    
    // 쿼리 파라미터 제거
    $clean_url = parse_url($url, PHP_URL_PATH);
    
    return $pages[$clean_url] ?? $clean_url;
}

// 디바이스 타입 아이콘
function getDeviceIcon($device) {
    $icons = [
        'desktop' => 'fas fa-desktop',
        'mobile' => 'fas fa-mobile-alt', 
        'tablet' => 'fas fa-tablet-alt'
    ];
    return $icons[$device] ?? 'fas fa-question';
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#3b82f6">
    <title>접속 통계 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <?php if (file_exists('common_header.php')) include 'common_header.php'; ?>
    
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-7xl mx-auto">
            <!-- 헤더 -->
            <div class="bg-white rounded-lg shadow-lg p-4 md:p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800">
                            <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                            접속 통계 (단순 버전)
                        </h1>
                        <p class="text-sm text-gray-600 mt-1">최근 <?= $days ?>일간 접속 현황</p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-2">
                        <!-- 기간 선택 -->
                        <select onchange="location.href='?days='+this.value" 
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="1" <?= $days == 1 ? 'selected' : '' ?>>오늘</option>
                            <option value="7" <?= $days == 7 ? 'selected' : '' ?>>최근 7일</option>
                            <option value="30" <?= $days == 30 ? 'selected' : '' ?>>최근 30일</option>
                            <option value="90" <?= $days == 90 ? 'selected' : '' ?>>최근 90일</option>
                        </select>
                        
                        <a href="admin.php" class="px-3 py-2 bg-gray-500 text-white rounded-md text-sm hover:bg-gray-600 text-center">
                            <i class="fas fa-arrow-left mr-1"></i>관리 메뉴
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <p class="text-red-800"><?= htmlspecialchars($error_message) ?></p>
                    <div class="mt-2">
                        <a href="fix_access_logs.php" class="text-red-600 hover:text-red-800 underline">
                            → access_logs 테이블 생성하기
                        </a>
                    </div>
                </div>
            <?php elseif ($stats_available): ?>

            <!-- 기본 통계 카드 -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-eye text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xs md:text-sm text-gray-500">총 방문</h3>
                            <p class="text-lg md:text-2xl font-bold text-gray-800">
                                <?= number_format($basic_stats['total_visits']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-users text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xs md:text-sm text-gray-500">순 방문자</h3>
                            <p class="text-lg md:text-2xl font-bold text-gray-800">
                                <?= number_format($basic_stats['unique_visitors']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-user-check text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xs md:text-sm text-gray-500">로그인 사용자</h3>
                            <p class="text-lg md:text-2xl font-bold text-gray-800">
                                <?= number_format($basic_stats['logged_users']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-3 rounded-full">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xs md:text-sm text-gray-500">세션</h3>
                            <p class="text-lg md:text-2xl font-bold text-gray-800">
                                <?= number_format($basic_stats['unique_sessions']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                <!-- 일별 방문자 차트 -->
                <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                        일별 방문 현황
                    </h3>
                    <?php if (!empty($daily_stats)): ?>
                    <div class="h-64">
                        <canvas id="dailyChart"></canvas>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500">데이터가 없습니다.</p>
                    <?php endif; ?>
                </div>

                <!-- 디바이스별 통계 -->
                <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-devices text-green-600 mr-2"></i>
                        디바이스별 접속
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($device_stats as $device): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="<?= getDeviceIcon($device['device_type']) ?> text-lg text-gray-600 mr-3 w-5"></i>
                                <span class="capitalize"><?= ucfirst($device['device_type']) ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium"><?= number_format($device['visits']) ?>회</span>
                                <div class="w-20 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $device['percentage'] ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-500 w-10"><?= $device['percentage'] ?>%</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 인기 페이지 & 브라우저 통계 -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
                <!-- 인기 페이지 -->
                <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-star text-yellow-600 mr-2"></i>
                        인기 페이지
                    </h3>
                    <div class="space-y-2">
                        <?php foreach ($page_stats as $page): ?>
                        <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                            <div>
                                <div class="font-medium text-sm"><?= getPageName($page['page_url']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($page['page_url']) ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium"><?= number_format($page['visits']) ?>회</div>
                                <div class="text-xs text-gray-500"><?= number_format($page['unique_visitors']) ?>명</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 브라우저 통계 -->
                <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-globe text-purple-600 mr-2"></i>
                        브라우저별 접속
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($browser_stats as $browser): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fab fa-<?= strtolower($browser['browser']) ?> text-lg text-gray-600 mr-3 w-5"></i>
                                <span><?= htmlspecialchars($browser['browser']) ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium"><?= number_format($browser['visits']) ?>회</span>
                                <div class="w-20 bg-gray-200 rounded-full h-2">
                                    <div class="bg-purple-600 h-2 rounded-full" style="width: <?= $browser['percentage'] ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-500 w-10"><?= $browser['percentage'] ?>%</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 최근 접속자 목록 -->
            <div class="bg-white rounded-lg shadow-lg p-4 md:p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-history text-indigo-600 mr-2"></i>
                    최근 접속자 (24시간)
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left">사용자</th>
                                <th class="px-3 py-2 text-left">IP 주소</th>
                                <th class="px-3 py-2 text-left">디바이스</th>
                                <th class="px-3 py-2 text-left">브라우저</th>
                                <th class="px-3 py-2 text-left">최근 접속</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2">
                                    <?php if ($user['user_id']): ?>
                                        <div class="font-medium">사용자 ID: <?= htmlspecialchars($user['user_id']) ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($user['page_url']) ?></div>
                                    <?php else: ?>
                                        <span class="text-gray-500">비회원</span>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($user['page_url']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 font-mono text-xs"><?= htmlspecialchars($user['ip_address']) ?></td>
                                <td class="px-3 py-2">
                                    <i class="<?= getDeviceIcon($user['device_type']) ?> mr-1"></i>
                                    <?= ucfirst($user['device_type']) ?>
                                </td>
                                <td class="px-3 py-2"><?= htmlspecialchars($user['browser']) ?></td>
                                <td class="px-3 py-2"><?= date('m/d H:i', strtotime($user['last_visit'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <?php if ($stats_available && !empty($daily_stats)): ?>
    <script>
        // 일별 방문자 차트
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        
        const dailyData = <?= json_encode(array_reverse($daily_stats)) ?>;
        const dailyLabels = dailyData.map(item => {
            const date = new Date(item.visit_date);
            return (date.getMonth() + 1) + '/' + date.getDate();
        });
        const dailyVisits = dailyData.map(item => parseInt(item.visits));
        const dailyUnique = dailyData.map(item => parseInt(item.unique_visitors));
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [
                    {
                        label: '총 방문',
                        data: dailyVisits,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: '순 방문자',
                        data: dailyUnique,
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>