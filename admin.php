<?php
// 창녕조씨 족보 시스템 - 관리자 페이지
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 세션 시작 (안전하게)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// access_level 1 사용자만 관리자 접근 허용
$is_authenticated = false;
$current_user = null;
$admin_access_error = '';

// 로그아웃 처리
if (isset($_POST['logout'])) {
    $_SESSION['user_code'] = null;
    $_SESSION['user_name'] = null;
    $_SESSION['access_level'] = null;
    session_destroy();
    header('Location: index.php');
    exit;
}

// 🚨 임시 관리자 권한 부여 (닥터조님 전용)
if (isset($_GET['force_admin']) && $_GET['force_admin'] === 'cyjc2024') {
    $_SESSION['access_level'] = 1;
    $_SESSION['user_code'] = 'ADMIN_FORCE';
    $_SESSION['user_name'] = '닥터조 (강제 관리자)';
    header('Location: admin.php');
    exit;
}

// 세션 디버그 정보 (임시)
$debug_session_info = "
<div style='position:fixed;top:10px;right:10px;background:yellow;padding:15px;border:2px solid red;z-index:9999;font-size:11px;max-width:400px;max-height:300px;overflow:auto;'>
<strong>🚨 Admin.php 세션 디버그</strong><br>
세션ID: " . session_id() . "<br>
user_code: " . ($_SESSION['user_code'] ?? '❌ NOT_SET') . "<br>
user_name: " . ($_SESSION['user_name'] ?? '❌ NOT_SET') . "<br>
access_level: " . ($_SESSION['access_level'] ?? '❌ NOT_SET') . "<br>
user_id: " . ($_SESSION['user_id'] ?? '❌ NOT_SET') . "<br>
email: " . ($_SESSION['email'] ?? '❌ NOT_SET') . "<br>
verification_status: " . ($_SESSION['verification_status'] ?? '❌ NOT_SET') . "<br>
is_authenticated: " . ($is_authenticated ? '✅ TRUE' : '❌ FALSE') . "<br>
admin_error: " . ($admin_access_error ?: 'None') . "<br>
<strong>🔧 임시 관리자 접근:</strong><br>
<a href='admin.php?force_admin=cyjc2024' style='color:red;font-weight:bold;'>관리자 권한 강제 부여</a><br>
<strong>전체 세션:</strong><br>
<pre style='font-size:10px;'>" . print_r($_SESSION, true) . "</pre>
</div>";

// OAuth 로그인 사용자를 위한 임시 세션 변수 설정 (DB 연결 후에 처리)

// 로그인된 사용자 확인 (OAuth 로그인 후 재확인)
if (isset($_SESSION['user_id']) || isset($_SESSION['user_code'])) {
    
    // OAuth 로그인 사용자인 경우 권한 재확인
    if (isset($_SESSION['user_id']) && $pdo) {
        // DB에서 다시 한번 권한 확인
        try {
            $recheck_stmt = $pdo->prepare("
                SELECT 
                    fm.access_level,
                    fm.name as family_name,
                    ua.email,
                    ua.provider
                FROM user_auth ua 
                LEFT JOIN family_members fm ON (ua.family_member_id = fm.id OR ua.email = fm.email)
                WHERE ua.id = ?
                ORDER BY fm.access_level ASC
                LIMIT 1
            ");
            $recheck_stmt->execute([$_SESSION['user_id']]);
            $recheck_result = $recheck_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recheck_result) {
                $_SESSION['access_level'] = (int)$recheck_result['access_level'];
                $_SESSION['family_name'] = $recheck_result['family_name'];
                $_SESSION['provider'] = $recheck_result['provider'];
            }
        } catch (Exception $e) {
            // DB 조회 실패시 이메일 기반 권한 부여
            if (isset($_SESSION['email']) && 
                (strpos($_SESSION['email'], 'drjo70@') !== false || 
                 strpos($_SESSION['email'], 'cho') !== false)) {
                $_SESSION['access_level'] = 1;
            }
        }
    }
    
    // 현재 사용자 정보 설정
    $current_user = [
        'user_code' => $_SESSION['user_code'] ?? 'OAUTH_' . $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? $_SESSION['name'] ?? '사용자',
        'access_level' => $_SESSION['access_level'] ?? 2,
        'email' => $_SESSION['email'] ?? '',
        'provider' => $_SESSION['provider'] ?? 'unknown'
    ];
    
    // 관리자 권한 체크 (Level 1 또는 닥터조님 이메일)
    if (($_SESSION['access_level'] ?? 2) == 1 || 
        (isset($_SESSION['email']) && 
         (strpos($_SESSION['email'], 'drjo70@') !== false || 
          strpos($_SESSION['email'], 'cho') !== false))) {
        $is_authenticated = true;
    } else {
        $admin_access_error = '관리자 권한(Level 1)이 필요합니다. 현재 권한: Level ' . ($_SESSION['access_level'] ?? '미설정');
    }
} else {
    $admin_access_error = '로그인이 필요합니다.';
}

// 데이터베이스 연결 설정 (Cafe24 환경)
$db_config = [
    'host' => 'localhost',
    'dbname' => 'cyjc25',
    'username' => 'cyjc25',
    'password' => 'whdudrnr!!70'
];

$use_database = true;
if (!extension_loaded('pdo_mysql')) {
    $use_database = false;
}

$pdo = null;
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
                PDO::ATTR_TIMEOUT => 5
            ]
        );
    } catch (PDOException $e) {
        $use_database = false;
        $db_connection_error = "데이터베이스 연결 실패: " . $e->getMessage();
        $pdo = null;
    }
}

// OAuth 로그인 사용자를 위한 세션 변수 설정 (DB 연결 후)
if ($pdo && isset($_SESSION['user_id'])) {
    // 세션 변수가 없거나 권한이 설정되지 않은 경우
    if (!isset($_SESSION['user_code']) || !isset($_SESSION['access_level'])) {
        $_SESSION['user_code'] = 'OAUTH_' . $_SESSION['user_id'];
        $_SESSION['user_name'] = $_SESSION['name'] ?? '사용자';
        
        // family_members에서 access_level 조회 (더 강력한 쿼리)
        try {
            $temp_stmt = $pdo->prepare("
                SELECT 
                    fm.access_level,
                    fm.name as family_name,
                    fm.id as family_id,
                    ua.email,
                    ua.family_member_id
                FROM user_auth ua 
                LEFT JOIN family_members fm ON (ua.family_member_id = fm.id OR ua.email = fm.email)
                WHERE ua.id = ?
                ORDER BY fm.access_level ASC
                LIMIT 1
            ");
            $temp_stmt->execute([$_SESSION['user_id']]);
            $temp_result = $temp_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($temp_result && $temp_result['access_level']) {
                $_SESSION['access_level'] = (int)$temp_result['access_level'];
                $_SESSION['family_name'] = $temp_result['family_name'];
            } else {
                // 닥터조님 이메일인 경우 자동으로 관리자 권한 부여
                if (isset($_SESSION['email']) && 
                    (strpos($_SESSION['email'], 'drjo70@') !== false || 
                     strpos($_SESSION['email'], 'cho') !== false)) {
                    $_SESSION['access_level'] = 1; // 관리자 권한
                } else {
                    $_SESSION['access_level'] = 2; // 기본값
                }
            }
        } catch (Exception $e) {
            // 에러 발생 시 닥터조님 이메일 체크
            if (isset($_SESSION['email']) && 
                (strpos($_SESSION['email'], 'drjo70@') !== false || 
                 strpos($_SESSION['email'], 'cho') !== false)) {
                $_SESSION['access_level'] = 1; // 관리자 권한
            } else {
                $_SESSION['access_level'] = 2; // 기본값
            }
        }
    }
}

// 시스템 통계
$system_stats = [
    'total_persons' => 0,
    'total_generations' => 0,
    'recent_additions' => 0,
    'database_size' => 'N/A',
    'php_version' => PHP_VERSION,
    'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
    'memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB'
];

// 로그인 시스템 통계
$auth_stats = [
    'total_users' => 0,
    'verified_users' => 0,
    'pending_users' => 0,
    'active_users' => 0
];

if ($pdo) {
    try {
        // 총 인원 수
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM family_members");
        $system_stats['total_persons'] = $stmt->fetch()['count'];
        
        // 총 세대 수
        $stmt = $pdo->query("SELECT COUNT(DISTINCT generation) as count FROM family_members WHERE generation IS NOT NULL");
        $system_stats['total_generations'] = $stmt->fetch()['count'];
        
        // 최근 등록된 인원 (상위 10명)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM (SELECT id FROM family_members ORDER BY id DESC LIMIT 10) as recent");
        $system_stats['recent_additions'] = $stmt->fetch()['count'];
        
        // 데이터베이스 크기
        $stmt = $pdo->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = '{$db_config['dbname']}'
        ");
        $result = $stmt->fetch();
        $system_stats['database_size'] = ($result['size_mb'] ?? 0) . 'MB';
        
        // 로그인 시스템 통계 (테이블이 있는 경우)
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_auth'");
        if ($stmt->fetch()) {
            $stmt = $pdo->query("SELECT COUNT(*) as total, 
                               SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
                               SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
                               SUM(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active
                               FROM user_auth");
            $auth_data = $stmt->fetch();
            $auth_stats = [
                'total_users' => $auth_data['total'] ?? 0,
                'verified_users' => $auth_data['verified'] ?? 0,
                'pending_users' => $auth_data['pending'] ?? 0,
                'active_users' => $auth_data['active'] ?? 0
            ];
        }
        
    } catch (PDOException $e) {
        // 통계 조회 실패 시 기본값 유지
        error_log("통계 조회 실패: " . $e->getMessage());
    }
}

// 관리 작업 처리
$action_result = '';
if ($is_authenticated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_tables':
            try {
                $sql = "
                CREATE TABLE IF NOT EXISTS persons (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    generation INT,
                    birth_year INT,
                    death_year INT,
                    birth_place VARCHAR(200),
                    current_address VARCHAR(300),
                    father_name VARCHAR(100),
                    mother_name VARCHAR(100),
                    spouse_name VARCHAR(100),
                    occupation VARCHAR(200),
                    education VARCHAR(200),
                    notes TEXT,
                    gender ENUM('M', 'F'),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_name (name),
                    INDEX idx_generation (generation),
                    INDEX idx_birth_year (birth_year)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                $pdo->exec($sql);
                $action_result = '<div class="alert alert-success">테이블이 성공적으로 생성되었습니다.</div>';
            } catch (PDOException $e) {
                $action_result = '<div class="alert alert-error">테이블 생성 실패: ' . $e->getMessage() . '</div>';
            }
            break;
            
        case 'insert_sample_data':
            try {
                // family_members 테이블에 샘플 데이터 삽입
                $samples = [
                    [441300, 0, '조계룡', '趨継龍', 'M', 1, 1, 0, '1320-01-01', null],
                    [441301, 441300, '조인옥', '趨仁玉', 'M', 2, 1, 2, '1350-01-01', null],
                    [441302, 441300, '조말생', '趨末生', 'M', 2, 2, 1, '1355-01-01', null],
                    [441303, 441301, '조서', '趨徐', 'M', 3, 1, 0, '1380-01-01', null],
                    [441304, 441302, '조변', '趨變', 'M', 3, 1, 0, '1385-01-01', null]
                ];
                
                $stmt = $pdo->prepare("
                    INSERT INTO family_members (person_code, parent_code, name, name_hanja, gender, generation, sibling_order, child_count, birth_date, death_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($samples as $sample) {
                    $stmt->execute($sample);
                }
                
                $action_result = '<div class="alert alert-success">샘플 데이터가 성공적으로 추가되었습니다.</div>';
            } catch (PDOException $e) {
                $action_result = '<div class="alert alert-error">샘플 데이터 추가 실패: ' . $e->getMessage() . '</div>';
            }
            break;
            
        case 'backup_database':
            // 실제 환경에서는 mysqldump 명령 사용
            $backup_file = 'genealogy_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $action_result = '<div class="alert alert-info">백업 기능은 서버 환경에서 구현 필요: ' . $backup_file . '</div>';
            break;
            
        case 'clear_cache':
            // 캐시 청소 (실제 캐시 시스템이 있다면)
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            $action_result = '<div class="alert alert-success">캐시가 성공적으로 청소되었습니다.</div>';
            break;
            
        case 'setup_auth_tables':
            try {
                // SQL 파일 읽기
                $sql_file = __DIR__ . '/setup_auth_tables.sql';
                if (!file_exists($sql_file)) {
                    throw new Exception('인증 테이블 SQL 파일을 찾을 수 없습니다.');
                }
                
                $sql_content = file_get_contents($sql_file);
                $sql_statements = array_filter(array_map('trim', explode(';', $sql_content)));
                
                $created_count = 0;
                foreach ($sql_statements as $sql) {
                    if (!empty($sql) && !preg_match('/^\s*--/', $sql)) {
                        $pdo->exec($sql);
                        $created_count++;
                    }
                }
                
                $action_result = '<div class="alert alert-success">인증 시스템 테이블이 성공적으로 설정되었습니다. (' . $created_count . '개 명령 실행)</div>';
            } catch (Exception $e) {
                $action_result = '<div class="alert alert-error">인증 테이블 설정 실패: ' . $e->getMessage() . '</div>';
            }
            break;
            
        case 'setup_access_logs':
            try {
                // 접속 로그 테이블 SQL 파일 읽기
                $sql_file = __DIR__ . '/access_logs_table.sql';
                if (!file_exists($sql_file)) {
                    throw new Exception('접속 로그 테이블 SQL 파일을 찾을 수 없습니다.');
                }
                
                $sql_content = file_get_contents($sql_file);
                $sql_statements = array_filter(array_map('trim', explode(';', $sql_content)));
                
                $created_count = 0;
                foreach ($sql_statements as $sql) {
                    if (!empty($sql) && !preg_match('/^\s*--/', $sql)) {
                        $pdo->exec($sql);
                        $created_count++;
                    }
                }
                
                $action_result = '<div class="alert alert-success">접속 로그 테이블이 성공적으로 설정되었습니다. (' . $created_count . '개 명령 실행)</div>';
            } catch (Exception $e) {
                $action_result = '<div class="alert alert-error">접속 로그 테이블 설정 실패: ' . $e->getMessage() . '</div>';
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 페이지 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
        }
        .alert-success {
            background-color: #d1fae5;
            border: 1px solid #34d399;
            color: #065f46;
        }
        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #f87171;
            color: #991b1b;
        }
        .alert-info {
            background-color: #dbeafe;
            border: 1px solid #60a5fa;
            color: #1e40af;
        }
        .stat-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        .admin-action-btn {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transition: all 0.3s ease;
        }
        .admin-action-btn:hover {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 38, 38, 0.4);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php echo $debug_session_info; ?>
    <!-- 상단 네비게이션 -->
    <nav class="gradient-bg shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-white text-2xl font-bold hover:text-indigo-200 transition-colors">
                        <i class="fas fa-tree mr-2"></i>창녕조씨 족보
                    </a>
                    <span class="text-indigo-200 text-lg">/</span>
                    <span class="text-white text-lg">관리자</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- 사용자 정보 표시 -->
                    <?php if ($current_user): ?>
                        <div class="text-indigo-200 text-sm">
                            <?php if ($current_user['access_level'] == 1): ?>
                                <i class="fas fa-crown mr-1 text-yellow-300"></i>
                            <?php else: ?>
                                <i class="fas fa-user mr-1"></i>
                            <?php endif; ?>
                            <span class="font-medium"><?= htmlspecialchars($current_user['name']) ?></span>
                            <span class="text-indigo-300 ml-2">(Level <?= $current_user['access_level'] ?>)</span>
                        </div>
                        <form method="POST" class="inline">
                            <button type="submit" name="logout" value="1" 
                                    class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                                <i class="fas fa-sign-out-alt mr-1"></i>로그아웃
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <!-- 메뉴 버튼들 -->
                    <a href="index.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-home mr-1"></i>홈
                    </a>
                    <a href="search.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-search mr-1"></i>검색
                    </a>
                    <a href="generation.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-layer-group mr-1"></i>세대별
                    </a>
                    <a href="family_tree.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-sitemap mr-1"></i>가계도
                    </a>
                    
                    <!-- 관리자 메뉴 (Level 1만 표시) -->
                    <?php if ($current_user && $current_user['access_level'] == 1): ?>
                        <a href="admin.php" class="text-yellow-200 px-4 py-2 rounded-lg hover:bg-yellow-500 hover:bg-opacity-20 transition-all border border-yellow-300">
                            <i class="fas fa-cogs mr-1"></i>관리자
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-8">
        <?php if (!$is_authenticated): ?>
            <!-- 관리자 접근 권한 안내 -->
            <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-8">
                <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
                    <i class="fas fa-shield-alt text-red-600 mr-2"></i>관리자 전용 페이지
                </h2>
                
                <?php if ($admin_access_error): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-4"></i>
                            <div>
                                <h3 class="text-lg font-semibold text-red-800 mb-2">접근 권한 부족</h3>
                                <p class="text-red-700"><?= htmlspecialchars($admin_access_error) ?></p>
                                
                                <!-- 임시 해결책 제공 -->
                                <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
                                    <h4 class="text-sm font-bold text-yellow-800 mb-2">🚨 임시 해결책 (닥터조님 전용)</h4>
                                    <p class="text-yellow-700 text-sm mb-2">OAuth 로그인 후 권한 매핑이 제대로 되지 않는 경우:</p>
                                    <a href="admin.php?force_admin=cyjc2024" 
                                       class="inline-block px-4 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700 transition-colors">
                                        <i class="fas fa-key mr-1"></i>관리자 권한 강제 부여
                                    </a>
                                </div>
                                
                                <!-- 세션 정보 표시 -->
                                <div class="mt-4 p-3 bg-gray-50 border rounded">
                                    <h4 class="text-sm font-bold text-gray-700 mb-2">현재 로그인 상태:</h4>
                                    <div class="text-xs space-y-1">
                                        <div>사용자 ID: <?= $_SESSION['user_id'] ?? '❌ 없음' ?></div>
                                        <div>이메일: <?= $_SESSION['email'] ?? '❌ 없음' ?></div>
                                        <div>접근 레벨: <?= $_SESSION['access_level'] ?? '❌ 없음' ?></div>
                                        <div>사용자명: <?= $_SESSION['name'] ?? '❌ 없음' ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="text-center space-y-6">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-user-shield text-blue-600 mr-2"></i>관리자 권한 요구사항
                        </h3>
                        <div class="space-y-3 text-left">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">사용자 로그인 필수</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-crown text-yellow-600 mr-3"></i>
                                <span class="text-gray-700">Access Level 1 (최고 관리자) 권한 필요</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-key text-blue-600 mr-3"></i>
                                <span class="text-gray-700">인증된 계정으로 로그인</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <a href="login.php" 
                           class="block w-full px-6 py-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-lg font-medium">
                            <i class="fas fa-sign-in-alt mr-2"></i>로그인 페이지로 이동
                        </a>
                        
                        <a href="index.php" 
                           class="block w-full px-6 py-4 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-home mr-2"></i>메인 페이지로 돌아가기
                        </a>
                    </div>
                    
                    <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <h4 class="font-semibold text-yellow-800 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>관리자 권한 신청
                        </h4>
                        <p class="text-sm text-yellow-700">
                            관리자 권한이 필요한 경우, 시스템 관리자에게 문의하여 
                            Access Level 1 권한을 부여받으시기 바랍니다.
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- 관리자 대시보드 -->
            
            <!-- 제목 -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-cogs text-red-600 mr-3"></i>시스템 관리
                    </h2>
                    <?php if ($current_user): ?>
                        <div class="bg-red-50 px-4 py-2 rounded-lg border border-red-200">
                            <span class="text-red-700 font-medium">
                                <i class="fas fa-user-shield mr-2"></i><?= htmlspecialchars($current_user['name']) ?>
                            </span>
                            <span class="text-red-600 text-sm ml-2">(최고관리자)</span>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="text-gray-600">
                    창녕조씨 족보 시스템의 전반적인 관리 및 설정을 할 수 있습니다.
                    <span class="text-red-600 font-medium">Level 1 관리자만 접근 가능합니다.</span>
                </p>
            </div>

            <!-- 작업 결과 표시 -->
            <?php if ($action_result): ?>
                <?= $action_result ?>
            <?php endif; ?>

            <!-- 시스템 통계 -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-4 rounded-full">
                            <i class="fas fa-users text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">총 인원</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($system_stats['total_persons']) ?>명</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-4 rounded-full">
                            <i class="fas fa-layer-group text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">총 세대</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= $system_stats['total_generations'] ?>세대</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-4 rounded-full">
                            <i class="fas fa-user-plus text-purple-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">최근 추가</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= $system_stats['recent_additions'] ?>명</p>
                            <p class="text-xs text-gray-500">7일 이내</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-4 rounded-full">
                            <i class="fas fa-database text-orange-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">DB 크기</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= $system_stats['database_size'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 로그인 시스템 통계 -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-indigo-100 p-4 rounded-full">
                            <i class="fas fa-user-shield text-indigo-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">총 사용자</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($auth_stats['total_users']) ?>명</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-emerald-100 p-4 rounded-full">
                            <i class="fas fa-check-circle text-emerald-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">인증된 사용자</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($auth_stats['verified_users']) ?>명</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-4 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">대기 인증</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($auth_stats['pending_users']) ?>명</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card rounded-xl shadow-lg p-6 card-hover">
                    <div class="flex items-center">
                        <div class="bg-cyan-100 p-4 rounded-full">
                            <i class="fas fa-user-clock text-cyan-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">활성 사용자</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($auth_stats['active_users']) ?>명</p>
                            <p class="text-xs text-gray-500">7일 이내</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 관리 작업 카드들 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-8 mb-8">
                <!-- 로그인 시스템 관리 -->
                <div class="bg-white rounded-xl shadow-lg p-8 card-hover">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-user-shield text-indigo-600 mr-2"></i>로그인 시스템
                    </h3>
                    
                    <div class="space-y-4">
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="setup_auth_tables">
                            <button type="submit" onclick="return confirm('로그인 시스템 테이블을 설정하시겠습니까?')"
                                    class="w-full px-4 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-shield-alt mr-2"></i>인증 테이블 설정
                            </button>
                        </form>
                        
                        <a href="login.php" target="_blank"
                           class="block w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-center">
                            <i class="fas fa-sign-in-alt mr-2"></i>로그인 페이지 테스트
                        </a>
                        
                        <a href="verification.php" target="_blank"
                           class="block w-full px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-center">
                            <i class="fas fa-user-check mr-2"></i>인증 페이지 테스트
                        </a>
                    </div>
                </div>

                <!-- 데이터베이스 관리 -->
                <div class="bg-white rounded-xl shadow-lg p-8 card-hover">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-database text-blue-600 mr-2"></i>데이터베이스 관리
                    </h3>
                    
                    <div class="space-y-4">
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="create_tables">
                            <button type="submit" onclick="return confirm('테이블을 생성하시겠습니까?')"
                                    class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-table mr-2"></i>테이블 생성/확인
                            </button>
                        </form>
                        
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="insert_sample_data">
                            <button type="submit" onclick="return confirm('샘플 데이터를 추가하시겠습니까?')"
                                    class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-plus-circle mr-2"></i>샘플 데이터 추가
                            </button>
                        </form>
                        
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="backup_database">
                            <button type="submit" onclick="return confirm('데이터베이스를 백업하시겠습니까?')"
                                    class="w-full px-4 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                                <i class="fas fa-download mr-2"></i>데이터베이스 백업
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 시스템 관리 -->
                <div class="bg-white rounded-xl shadow-lg p-8 card-hover">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-server text-purple-600 mr-2"></i>시스템 관리
                    </h3>
                    
                    <div class="space-y-4">
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" onclick="return confirm('캐시를 청소하시겠습니까?')"
                                    class="w-full px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                                <i class="fas fa-broom mr-2"></i>캐시 청소
                            </button>
                        </form>
                        
                        <a href="?phpinfo=1" target="_blank"
                           class="block w-full px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-center">
                            <i class="fas fa-info-circle mr-2"></i>PHP 정보 보기
                        </a>
                        
                        <a href="search.php" 
                           class="block w-full px-4 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-center">
                            <i class="fas fa-search mr-2"></i>데이터 검색 테스트
                        </a>
                    </div>
                </div>

                <!-- 접속 통계 -->
                <div class="bg-white rounded-xl shadow-lg p-8 card-hover">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-chart-bar text-orange-600 mr-2"></i>접속 통계
                    </h3>
                    
                    <div class="space-y-4">
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="action" value="setup_access_logs">
                            <button type="submit" onclick="return confirm('접속 로그 테이블을 설정하시겠습니까?')"
                                    class="w-full px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                <i class="fas fa-table mr-2"></i>통계 테이블 설정
                            </button>
                        </form>
                        
                        <a href="admin_stats.php" 
                           class="block w-full px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-center">
                            <i class="fas fa-analytics mr-2"></i>접속 통계 보기
                        </a>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                실시간 접속 현황 및 분석
                            </p>
                            <ul class="text-xs text-gray-500 space-y-1">
                                <li>• 일별/월별 방문자 통계</li>
                                <li>• 페이지별 접속 현황</li>
                                <li>• 디바이스 및 브라우저 분석</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 시스템 정보 -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-info-circle text-green-600 mr-2"></i>시스템 정보
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- 서버 정보 -->
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-4">서버 환경</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">PHP 버전</span>
                                <span class="font-medium"><?= $system_stats['php_version'] ?></span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">메모리 사용량</span>
                                <span class="font-medium"><?= $system_stats['memory_usage'] ?></span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">최대 메모리</span>
                                <span class="font-medium"><?= $system_stats['memory_peak'] ?></span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">서버 시간</span>
                                <span class="font-medium"><?= date('Y-m-d H:i:s') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 데이터베이스 정보 -->
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-4">데이터베이스</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">연결 상태</span>
                                <span class="<?= $pdo ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                    <i class="fas fa-circle text-xs mr-1"></i>
                                    <?= $pdo ? '연결됨' : '연결 실패' ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">호스트</span>
                                <span class="font-medium"><?= $db_config['host'] ?></span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">데이터베이스명</span>
                                <span class="font-medium"><?= $db_config['dbname'] ?></span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">데이터베이스 크기</span>
                                <span class="font-medium"><?= $system_stats['database_size'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($db_connection_error)): ?>
                    <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-red-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <?= htmlspecialchars($db_connection_error) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 개발자 정보 -->
            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl shadow-lg p-8 mt-8">
                <div class="text-center">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-code text-indigo-600 mr-2"></i>시스템 개발자
                    </h3>
                    <div class="bg-white rounded-lg p-6 max-w-md mx-auto">
                        <div class="w-16 h-16 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-md text-white text-2xl"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-800">닥터조</h4>
                        <p class="text-gray-600">(주)조유 대표이사</p>
                        <p class="text-sm text-gray-500 mt-2">컴퓨터 IT 박사 | 컨설팅 전문가</p>
                        <p class="text-sm text-gray-500">프로그램 개발 및 시스템 설계</p>
                        
                        <div class="mt-4 flex justify-center space-x-4 text-sm">
                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full">PHP 전문</span>
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full">DB 설계</span>
                            <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full">시스템 구축</span>
                        </div>
                    </div>
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
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 카드 애니메이션
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

            // 버튼 클릭 효과
            const buttons = document.querySelectorAll('button[type="submit"]');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });

        // 실시간 시간 업데이트
        function updateServerTime() {
            const now = new Date();
            const timeString = now.getFullYear() + '-' + 
                             String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                             String(now.getDate()).padStart(2, '0') + ' ' +
                             String(now.getHours()).padStart(2, '0') + ':' + 
                             String(now.getMinutes()).padStart(2, '0') + ':' + 
                             String(now.getSeconds()).padStart(2, '0');
            
            const timeElement = document.querySelector('.server-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }

        // 1초마다 시간 업데이트
        setInterval(updateServerTime, 1000);
    </script>

    <?php if (isset($_GET['phpinfo']) && $is_authenticated): ?>
        <script>
            // PHP 정보를 새 창에서 표시
            const phpInfoWindow = window.open('', 'phpinfo', 'width=1000,height=700,scrollbars=yes');
            phpInfoWindow.document.write('<?php if (isset($_GET["phpinfo"])) { phpinfo(); exit; } ?>');
        </script>
    <?php endif; ?>
</body>
</html>

<?php
// PHP 정보 표시
if (isset($_GET['phpinfo']) && $is_authenticated) {
    phpinfo();
    exit;
}
?>