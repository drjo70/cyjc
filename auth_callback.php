<?php
// 창녕조씨 족보 시스템 - 구글 OAuth 콜백 처리
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// 안전한 세션 시작 (중요!)
if (function_exists('safeSessionStart')) {
    safeSessionStart();
} else if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 에러 처리
if (isset($_GET['error'])) {
    $error_msg = $_GET['error'] . ': ' . ($_GET['error_description'] ?? '알 수 없는 오류');
    header('Location: login.php?error=auth_failed&msg=' . urlencode($error_msg));
    exit;
}

// Authorization code 확인
if (!isset($_GET['code'])) {
    header('Location: login.php?error=no_code&msg=' . urlencode('인증 코드가 없습니다'));
    exit;
}

$authorization_code = $_GET['code'];

try {
    // 1단계: Access Token 요청
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'code' => $authorization_code,
        'client_id' => $google_config['client_id'],
        'client_secret' => $google_config['client_secret'],
        'redirect_uri' => $google_config['redirect_uri'],
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    
    $token_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('CURL 오류: ' . $curl_error);
    }

    if ($http_code !== 200 || !$token_response) {
        throw new Exception('토큰 요청 실패 (HTTP ' . $http_code . '): ' . $token_response);
    }

    $token_data = json_decode($token_response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('토큰 응답 JSON 파싱 오류: ' . json_last_error_msg());
    }

    if (!isset($token_data['access_token'])) {
        $error_desc = isset($token_data['error_description']) ? $token_data['error_description'] : '토큰 없음';
        throw new Exception('액세스 토큰을 받을 수 없습니다: ' . $error_desc);
    }

    // 2단계: 사용자 정보 요청
    $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $user_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token_data['access_token'],
        'Accept: application/json'
    ]);
    
    $user_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('사용자 정보 CURL 오류: ' . $curl_error);
    }

    if ($http_code !== 200 || !$user_response) {
        throw new Exception('사용자 정보 요청 실패 (HTTP ' . $http_code . '): ' . $user_response);
    }

    $user_info = json_decode($user_response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('사용자 정보 JSON 파싱 오류: ' . json_last_error_msg());
    }
    
    if (!isset($user_info['email']) || !isset($user_info['name'])) {
        throw new Exception('필수 사용자 정보 누락: ' . json_encode($user_info));
    }

    // 3단계: 데이터베이스 처리
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new Exception('데이터베이스 연결 실패');
    }

    // user_auth 테이블이 존재하는지 확인
    $table_check = $pdo->query("SHOW TABLES LIKE 'user_auth'");
    if (!$table_check->fetch()) {
        throw new Exception('user_auth 테이블이 존재하지 않습니다. setup_auth_tables.sql을 실행해주세요.');
    }

    // 기존 사용자 확인 (family_members와 조인하여 access_level 포함)
    $stmt = $pdo->prepare("
        SELECT ua.*, fm.access_level 
        FROM user_auth ua 
        LEFT JOIN family_members fm ON (ua.family_member_id = fm.id OR ua.email = fm.email)
        WHERE ua.google_id = ? OR ua.email = ?
    ");
    $stmt->execute([$user_info['id'], $user_info['email']]);
    $existing_user = $stmt->fetch();

    if ($existing_user) {
        // 기존 사용자 - 로그인 처리
        $_SESSION['user_id'] = $existing_user['id'];
        $_SESSION['email'] = $existing_user['email'];
        $_SESSION['name'] = $existing_user['name'];
        $_SESSION['verification_status'] = $existing_user['verification_status'];
        $_SESSION['family_member_id'] = $existing_user['family_member_id'];
        
        // 관리자 페이지 호환을 위한 추가 세션 변수 설정
        $_SESSION['user_code'] = 'OAUTH_' . $existing_user['id'];
        $_SESSION['user_name'] = $existing_user['name'];
        
        // family_members 테이블에서 조인된 access_level 설정
        $_SESSION['access_level'] = $existing_user['access_level'] ?? 2; // 기본값 2 (일반 사용자)

        // 세션 설정 디버그 (임시)
        if (ini_get('display_errors')) {
            error_log('구글 로그인 후 세션 설정: ' . json_encode([
                'session_id' => session_id(),
                'user_id' => $_SESSION['user_id'],
                'email' => $_SESSION['email'],
                'name' => $_SESSION['name'],
                'verification_status' => $_SESSION['verification_status']
            ]));
        }

        // 세션 데이터 강제 저장
        session_write_close();
        session_start(); // 다시 시작

        // 로그인 로그 기록
        $log_stmt = $pdo->prepare("INSERT INTO login_logs (user_auth_id, ip_address, user_agent, login_at) VALUES (?, ?, ?, NOW())");
        $log_stmt->execute([$existing_user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

        // 인증 상태에 따라 리다이렉트
        if ($existing_user['verification_status'] === 'verified') {
            header('Location: index.php?login=success');
        } else {
            header('Location: family_verification_system.php?step=phone');
        }
    } else {
        // 신규 사용자 - 회원가입 처리
        $stmt = $pdo->prepare("
            INSERT INTO user_auth (google_id, email, name, verification_status, created_at, updated_at)
            VALUES (?, ?, ?, 'pending', NOW(), NOW())
        ");
        
        if ($stmt->execute([$user_info['id'], $user_info['email'], $user_info['name']])) {
            $user_id = $pdo->lastInsertId();
            
            // 이메일로 family_members 테이블에서 기존 구성원 확인
            $family_stmt = $pdo->prepare("SELECT * FROM family_members WHERE email = ?");
            $family_stmt->execute([$user_info['email']]);
            $family_member = $family_stmt->fetch();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $user_info['email'];
            $_SESSION['name'] = $user_info['name'];
            $_SESSION['verification_status'] = 'pending';
            $_SESSION['family_member_id'] = $family_member ? $family_member['id'] : null;
            
            // 관리자 페이지 호환을 위한 추가 세션 변수 설정
            $_SESSION['user_code'] = 'OAUTH_' . $user_id;
            $_SESSION['user_name'] = $user_info['name'];
            
            // family_members 테이블에서 access_level 설정 (없으면 기본값 2)
            $_SESSION['access_level'] = $family_member ? ($family_member['access_level'] ?? 2) : 2;
            
            // family_member_id가 있다면 user_auth 테이블도 업데이트
            if ($family_member) {
                $update_stmt = $pdo->prepare("UPDATE user_auth SET family_member_id = ? WHERE id = ?");
                $update_stmt->execute([$family_member['id'], $user_id]);
            }

            // 세션 설정 디버그 (임시)
            if (ini_get('display_errors')) {
                error_log('구글 신규 사용자 세션 설정: ' . json_encode([
                    'session_id' => session_id(),
                    'user_id' => $_SESSION['user_id'],
                    'email' => $_SESSION['email'],
                    'name' => $_SESSION['name'],
                    'verification_status' => $_SESSION['verification_status']
                ]));
            }

            // 세션 데이터 강제 저장
            session_write_close();
            session_start(); // 다시 시작

            // 가입 로그 기록
            $log_stmt = $pdo->prepare("INSERT INTO login_logs (user_auth_id, ip_address, user_agent, login_at) VALUES (?, ?, ?, NOW())");
            $log_stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            header('Location: family_verification_system.php?step=phone&new_user=1');
        } else {
            $error_info = $stmt->errorInfo();
            throw new Exception('사용자 등록 실패: ' . $error_info[2]);
        }
    }

} catch (Exception $e) {
    $error_msg = $e->getMessage();
    error_log('OAuth 처리 오류: ' . $error_msg);
    
    // 디버그 정보 (개발 환경에서만)
    if (ini_get('display_errors')) {
        $debug_info = [
            'error' => $error_msg,
            'user_info' => isset($user_info) ? $user_info : null,
            'token_data' => isset($token_data) ? ['access_token' => '***'] : null
        ];
        error_log('디버그 정보: ' . json_encode($debug_info));
    }
    
    header('Location: login.php?error=registration_failed&msg=' . urlencode($error_msg));
}
exit;
?>