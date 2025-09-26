<?php
// 간단한 접속 통계 페이지 (오류 없는 버전)
session_start();

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
    die("DB 연결 실패: " . $e->getMessage());
}

// 기본 통계 조회
$total_visits = 0;
$unique_visitors = 0;
$recent_visits = [];

try {
    // access_logs 테이블 존재 확인
    $stmt = $pdo->query("SHOW TABLES LIKE 'access_logs'");
    if ($stmt->fetch()) {
        // 총 방문수
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM access_logs");
        $total_visits = $stmt->fetch()['count'];
        
        // 순 방문자
        $stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) as count FROM access_logs");
        $unique_visitors = $stmt->fetch()['count'];
        
        // 최근 방문 기록 (10개)
        $stmt = $pdo->query("
            SELECT page_url, ip_address, device_type, browser, access_time 
            FROM access_logs 
            ORDER BY access_time DESC 
            LIMIT 10
        ");
        $recent_visits = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = "통계 조회 오류: " . $e->getMessage();
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
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-6 py-8">
        <!-- 제목 -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-bar text-blue-600 mr-3"></i>접속 통계
            </h1>
            <div class="flex space-x-4">
                <a href="admin.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>관리자 페이지
                </a>
                <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-sync-alt mr-2"></i>새로고침
                </button>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
                <h3 class="text-red-800 font-bold mb-2">오류 발생</h3>
                <p class="text-red-600"><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- 주요 통계 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-4 rounded-full">
                        <i class="fas fa-eye text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">총 방문수</h3>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($total_visits) ?></p>
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
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($unique_visitors) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-4 rounded-full">
                        <i class="fas fa-clock text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">데이터 수집</h3>
                        <p class="text-lg font-bold text-gray-800">
                            <?= $total_visits > 0 ? '활성화' : '준비중' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 최근 방문 기록 -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-history text-indigo-600 mr-2"></i>최근 방문 기록
            </h2>
            
            <?php if (empty($recent_visits)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-info-circle text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500 text-lg">아직 수집된 접속 로그가 없습니다.</p>
                    <p class="text-gray-400 text-sm mt-2">웹사이트를 방문하면 통계가 수집됩니다.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 font-medium text-gray-600">시간</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-600">페이지</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-600">IP 주소</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-600">디바이스</th>
                                <th class="text-left py-3 px-4 font-medium text-gray-600">브라우저</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_visits as $visit): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm">
                                        <?= date('m/d H:i', strtotime($visit['access_time'])) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <span class="text-blue-600"><?= htmlspecialchars($visit['page_url']) ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-sm font-mono">
                                        <?= htmlspecialchars($visit['ip_address']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                                            <?= htmlspecialchars($visit['device_type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <?= htmlspecialchars($visit['browser']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 테이블 상태 확인 -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mt-8">
            <h3 class="text-blue-800 font-bold mb-4">
                <i class="fas fa-database mr-2"></i>데이터베이스 상태
            </h3>
            <?php
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE 'access_logs'");
                if ($stmt->fetch()) {
                    echo '<p class="text-green-600"><i class="fas fa-check-circle mr-2"></i>access_logs 테이블: 정상</p>';
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM access_logs");
                    $count = $stmt->fetch()['count'];
                    echo '<p class="text-blue-600 mt-2"><i class="fas fa-chart-bar mr-2"></i>수집된 로그: ' . number_format($count) . '건</p>';
                } else {
                    echo '<p class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>access_logs 테이블이 없습니다.</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="text-red-600"><i class="fas fa-times-circle mr-2"></i>데이터베이스 오류: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>
    </div>
</body>
</html>