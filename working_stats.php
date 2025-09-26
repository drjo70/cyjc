<?php
// 정상 작동하는 접속 통계 페이지 (오류 해결 버전)
session_start();

// 간단한 권한 체크 (세션 기반)
$is_admin = false;
if (isset($_SESSION['access_level']) && $_SESSION['access_level'] == 1) {
    $is_admin = true;
} elseif (isset($_SESSION['user_name']) && $_SESSION['user_name'] == '조영국') {
    $is_admin = true;
}

// 관리자가 아니면 리다이렉트 (하지만 오류는 발생시키지 않음)
if (!$is_admin) {
    // 대신 경고 메시지만 표시
    $access_warning = "관리자 권한이 필요합니다. emergency_admin.php로 로그인해주세요.";
}

// 데이터베이스 연결
$db_config = [
    'host' => 'localhost',
    'dbname' => 'cyjc25',
    'username' => 'cyjc25',
    'password' => 'whdudrnr!!70'
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['username'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    $db_error = "DB 연결 실패: " . $e->getMessage();
}

// 통계 기간 설정
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if ($days < 1 || $days > 365) $days = 7;

// 기본 통계 조회
$stats = [
    'total_visits' => 0,
    'unique_visitors' => 0,
    'unique_sessions' => 0,
    'logged_in_visits' => 0,
    'online_users' => 0,
    'daily_data' => [],
    'page_data' => [],
    'device_data' => [],
    'browser_data' => []
];

if (isset($pdo)) {
    try {
        // 테이블 존재 확인
        $stmt = $pdo->query("SHOW TABLES LIKE 'access_logs'");
        if ($stmt->fetch()) {
            
            // 기본 통계
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT ip_address) as unique_visitors,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    COUNT(CASE WHEN user_id IS NOT NULL THEN 1 END) as logged_in_visits
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $basic_stats = $stmt->fetch();
            
            if ($basic_stats) {
                $stats['total_visits'] = $basic_stats['total_visits'];
                $stats['unique_visitors'] = $basic_stats['unique_visitors'];
                $stats['unique_sessions'] = $basic_stats['unique_sessions'];
                $stats['logged_in_visits'] = $basic_stats['logged_in_visits'];
            }
            
            // 실시간 접속자 (최근 5분)
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT ip_address) as online_users
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $online = $stmt->fetch();
            $stats['online_users'] = $online['online_users'];
            
            // 일별 데이터 (차트용)
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(access_time) as date,
                    COUNT(*) as visits,
                    COUNT(DISTINCT ip_address) as unique_visitors
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(access_time)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            $stats['daily_data'] = $stmt->fetchAll();
            
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
            $stats['page_data'] = $stmt->fetchAll();
            
            // 디바이스별 통계
            $stmt = $pdo->prepare("
                SELECT 
                    device_type,
                    COUNT(*) as visits
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY device_type
                ORDER BY visits DESC
            ");
            $stmt->execute([$days]);
            $stats['device_data'] = $stmt->fetchAll();
            
            // 브라우저별 통계
            $stmt = $pdo->prepare("
                SELECT 
                    browser,
                    COUNT(*) as visits
                FROM access_logs 
                WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY) AND browser IS NOT NULL
                GROUP BY browser
                ORDER BY visits DESC
                LIMIT 5
            ");
            $stmt->execute([$days]);
            $stats['browser_data'] = $stmt->fetchAll();
            
        } else {
            $table_missing = true;
        }
        
    } catch (PDOException $e) {
        $stats_error = "통계 조회 오류: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>접속 통계 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <!-- 상단 네비게이션 -->
    <nav class="bg-gradient-to-r from-blue-600 to-purple-600 shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h1 class="text-white text-2xl font-bold">
                        <i class="fas fa-chart-line mr-2"></i>접속 통계
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (isset($_SESSION['user_name'])): ?>
                        <span class="text-indigo-200">
                            <i class="fas fa-user mr-1"></i>
                            <?= htmlspecialchars($_SESSION['user_name']) ?>님
                        </span>
                    <?php endif; ?>
                    <a href="admin.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-cogs mr-1"></i>관리자
                    </a>
                    <a href="index.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-home mr-1"></i>홈
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-8">
        <!-- 경고 메시지 -->
        <?php if (isset($access_warning)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8">
                <h3 class="text-yellow-800 font-bold mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>권한 안내
                </h3>
                <p class="text-yellow-700"><?= $access_warning ?></p>
                <a href="emergency_admin.php" class="inline-block mt-3 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                    관리자 로그인
                </a>
            </div>
        <?php endif; ?>

        <!-- 오류 메시지 -->
        <?php if (isset($db_error)): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
                <h3 class="text-red-800 font-bold mb-2">데이터베이스 오류</h3>
                <p class="text-red-600"><?= htmlspecialchars($db_error) ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($stats_error)): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
                <h3 class="text-red-800 font-bold mb-2">통계 조회 오류</h3>
                <p class="text-red-600"><?= htmlspecialchars($stats_error) ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($table_missing)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8">
                <h3 class="text-yellow-800 font-bold mb-2">테이블 없음</h3>
                <p class="text-yellow-700">access_logs 테이블이 존재하지 않습니다.</p>
                <a href="fix_access_logs.php" class="inline-block mt-3 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                    테이블 생성하기
                </a>
            </div>
        <?php endif; ?>

        <!-- 기간 선택 -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">통계 기간: 최근 <?= $days ?>일</h2>
                <div class="flex space-x-2">
                    <a href="?days=7" class="px-4 py-2 <?= $days == 7 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> rounded-lg">7일</a>
                    <a href="?days=30" class="px-4 py-2 <?= $days == 30 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> rounded-lg">30일</a>
                    <a href="?days=90" class="px-4 py-2 <?= $days == 90 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> rounded-lg">90일</a>
                </div>
            </div>
        </div>

        <!-- 주요 통계 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-4 rounded-full">
                        <i class="fas fa-eye text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">총 방문수</h3>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['total_visits']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-4 rounded-full">
                        <i class="fas fa-users text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">순 방문자</h3>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['unique_visitors']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-4 rounded-full">
                        <i class="fas fa-user-check text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">로그인 방문</h3>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['logged_in_visits']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-orange-100 p-4 rounded-full">
                        <i class="fas fa-clock text-orange-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">실시간 접속</h3>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['online_users']) ?></p>
                        <p class="text-xs text-gray-500">최근 5분</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 차트 영역 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- 일별 방문자 차트 -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h3 class="text-xl font-bold text-gray-800 mb-6">일별 방문자 추이</h3>
                <canvas id="dailyChart" height="300"></canvas>
            </div>

            <!-- 디바이스별 차트 -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h3 class="text-xl font-bold text-gray-800 mb-6">디바이스별 분포</h3>
                <canvas id="deviceChart" height="300"></canvas>
            </div>
        </div>

        <!-- 상세 데이터 테이블 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- 페이지별 통계 -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h3 class="text-xl font-bold text-gray-800 mb-6">인기 페이지</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">페이지</th>
                                <th class="text-right py-2">방문수</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($stats['page_data'])): ?>
                                <?php foreach ($stats['page_data'] as $page): ?>
                                    <tr class="border-b border-gray-100">
                                        <td class="py-2 text-sm text-blue-600"><?= htmlspecialchars($page['page_url']) ?></td>
                                        <td class="py-2 text-sm text-right"><?= number_format($page['visits']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="py-4 text-center text-gray-500">데이터가 없습니다</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 브라우저별 통계 -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h3 class="text-xl font-bold text-gray-800 mb-6">브라우저별 통계</h3>
                <div class="space-y-3">
                    <?php if (!empty($stats['browser_data'])): ?>
                        <?php foreach ($stats['browser_data'] as $browser): ?>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700"><?= htmlspecialchars($browser['browser'] ?: 'Unknown') ?></span>
                                <span class="text-gray-800 font-medium"><?= number_format($browser['visits']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">데이터가 없습니다</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // 일별 방문자 차트
        const dailyData = <?= json_encode($stats['daily_data']) ?>;
        const dailyLabels = dailyData.map(item => item.date);
        const dailyVisits = dailyData.map(item => item.visits);

        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: '방문수',
                    data: dailyVisits,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // 디바이스별 차트
        const deviceData = <?= json_encode($stats['device_data']) ?>;
        const deviceLabels = deviceData.map(item => item.device_type);
        const deviceValues = deviceData.map(item => item.visits);

        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: deviceLabels,
                datasets: [{
                    data: deviceValues,
                    backgroundColor: [
                        'rgb(59, 130, 246)',
                        'rgb(34, 197, 94)', 
                        'rgb(168, 85, 247)',
                        'rgb(248, 113, 113)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>