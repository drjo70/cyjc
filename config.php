<?php
// 창녕조씨 족보 시스템 - 설정 파일

// 세션 안전 시작 함수 (각 페이지에서 호출)
function safeSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        // 고정된 세션 이름 설정 (일관성 확보)
        session_name('CHANGNYEONG_JO_SESSION');
        
        // 세션 쿠키 설정 개선 (Cafe24 최적화)
        session_set_cookie_params([
            'lifetime' => 86400,  // 24시간 (0은 브라우저 종료시인데 일부 환경에서 문제 발생 가능)
            'path' => '/',        // 전체 사이트에서 접근 가능
            'domain' => '',       // 현재 도메인 (공백이 가장 안전)
            'secure' => false,    // Cafe24에서는 HTTPS 강제하지 않음
            'httponly' => true,   // XSS 공격 방지
            'samesite' => 'Lax'   // CSRF 공격 방지, None보다 안전
        ]);
        session_start();
        return true;
    }
    return false;
}

// 환경 변수 로드 함수
function loadEnvFile($file = '.env') {
    if (!file_exists($file)) {
        return;
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue; // 주석 건너뛰기
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// .env 파일 로드 시도
loadEnvFile(__DIR__ . '/.env');

// 데이터베이스 연결 설정
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname' => $_ENV['DB_NAME'] ?? 'cyjc25', 
    'username' => $_ENV['DB_USERNAME'] ?? 'cyjc25',
    'password' => $_ENV['DB_PASSWORD'] ?? 'DEFAULT_PASSWORD_CHANGE_ME'
];

// 구글 OAuth 설정
$google_config = [
    'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? 'GOOGLE_CLIENT_ID_REQUIRED',
    'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'GOOGLE_CLIENT_SECRET_REQUIRED',
    'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? 'https://cyjc.jou.kr/auth_callback.php',
    'scope' => 'openid email profile'
];

// 카카오 OAuth 설정
$kakao_config = [
    'client_id' => $_ENV['KAKAO_CLIENT_ID'] ?? 'KAKAO_CLIENT_ID_REQUIRED',
    'client_secret' => '', // 카카오는 client_secret 선택사항
    'redirect_uri' => $_ENV['KAKAO_REDIRECT_URI'] ?? 'https://cyjc.jou.kr/kakao_callback.php',
    'scope' => 'profile_nickname,account_email'
];

// 데이터베이스 연결 함수
function getDbConnection() {
    global $db_config;
    
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
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB 연결 실패: " . $e->getMessage());
        return null;
    }
}

// 로그인 체크 함수 (세션 자동 시작)
function isLoggedIn() {
    // 세션이 시작되지 않았다면 시작
    if (session_status() === PHP_SESSION_NONE) {
        safeSessionStart();
    }
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// 인증된 가문 구성원인지 체크 (세션 자동 시작)
function isVerifiedMember() {
    // 세션이 시작되지 않았다면 시작
    if (session_status() === PHP_SESSION_NONE) {
        safeSessionStart();
    }
    return isset($_SESSION['verification_status']) && $_SESSION['verification_status'] === 'verified';
}

// 로그인 필수 페이지 체크
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// 인증된 구성원만 접근 가능
function requireVerification() {
    requireLogin();
    if (!isVerifiedMember()) {
        header('Location: verification.php');
        exit;
    }
}

// 사용자 정보 가져오기
function getUserInfo() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT ua.*, fm.name as family_name, fm.generation, fm.name_hanja, fm.person_code, fm.access_level
            FROM user_auth ua
            LEFT JOIN family_members fm ON ua.family_member_id = fm.id
            WHERE ua.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("사용자 정보 조회 실패: " . $e->getMessage());
        return null;
    }
}

// 현재 로그인 사용자의 person_code 가져오기 (DB 기준)
function getCurrentUserPersonCode() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT fm.person_code
            FROM user_auth ua
            INNER JOIN family_members fm ON ua.family_member_id = fm.id
            WHERE ua.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            // 세션에도 저장해서 다음에 빠르게 사용
            $_SESSION['user_person_code'] = $result['person_code'];
            return $result['person_code'];
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("person_code 조회 실패: " . $e->getMessage());
        return null;
    }
}

// 현재 로그인 사용자의 access_level 가져오기 (DB 기준)
function getCurrentUserAccessLevel() {
    if (!isLoggedIn()) {
        return 999; // 기본값: 권한 없음
    }
    
    $pdo = getDbConnection();
    if (!$pdo) {
        return 999;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT fm.access_level
            FROM user_auth ua
            INNER JOIN family_members fm ON ua.family_member_id = fm.id
            WHERE ua.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        return $result ? (int)$result['access_level'] : 999;
    } catch (PDOException $e) {
        error_log("access_level 조회 실패: " . $e->getMessage());
        return 999;
    }
}

// 구글 OAuth URL 생성
function getGoogleAuthUrl() {
    global $google_config;
    
    $params = [
        'client_id' => $google_config['client_id'],
        'redirect_uri' => $google_config['redirect_uri'],
        'scope' => $google_config['scope'],
        'response_type' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];
    
    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
}

// 카카오 OAuth URL 생성
function getKakaoAuthUrl() {
    global $kakao_config;
    
    $params = [
        'client_id' => $kakao_config['client_id'],
        'redirect_uri' => $kakao_config['redirect_uri'],
        'response_type' => 'code',
        'scope' => $kakao_config['scope']
    ];
    
    return 'https://kauth.kakao.com/oauth/authorize?' . http_build_query($params);
}

// 에러 메시지 표시 함수
function showError($message) {
    return '<div class="alert alert-error mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">' . htmlspecialchars($message) . '</div>';
}

// 성공 메시지 표시 함수
function showSuccess($message) {
    return '<div class="alert alert-success mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">' . htmlspecialchars($message) . '</div>';
}

// 정보 메시지 표시 함수
function showInfo($message) {
    return '<div class="alert alert-info mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-700">' . htmlspecialchars($message) . '</div>';
}
?>