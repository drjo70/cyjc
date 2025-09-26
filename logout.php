<?php
// 창녕조씨 족보 시스템 - 로그아웃 처리
require_once 'config.php';

// 세션 데이터 모두 삭제
$_SESSION = array();

// 세션 쿠키 삭제
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 세션 종료
session_destroy();

// 로그인 페이지로 리다이렉트
header('Location: login.php?success=logout');
exit;
?>