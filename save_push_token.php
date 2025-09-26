<?php
// 푸시 알림 토큰 저장 API
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 로그인 확인
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// JSON 데이터 파싱
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['token']) || empty($input['token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Token is required']);
    exit;
}

$token = $input['token'];
$user_code = $input['user_code'] ?? getCurrentUserPersonCode();

if (!$user_code) {
    http_response_code(400);
    echo json_encode(['error' => 'User code is required']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // 기존 토큰이 있는지 확인
    $stmt = $pdo->prepare("
        SELECT id FROM push_tokens 
        WHERE user_code = ? AND token = ?
    ");
    $stmt->execute([$user_code, $token]);
    
    if ($stmt->fetch()) {
        // 이미 존재하는 토큰
        echo json_encode(['success' => true, 'message' => 'Token already exists']);
        exit;
    }
    
    // 새 토큰 저장 (기존 토큰들은 비활성화)
    $pdo->beginTransaction();
    
    // 기존 토큰들 비활성화
    $stmt = $pdo->prepare("
        UPDATE push_tokens 
        SET is_active = 0, updated_at = NOW()
        WHERE user_code = ?
    ");
    $stmt->execute([$user_code]);
    
    // 새 토큰 추가
    $stmt = $pdo->prepare("
        INSERT INTO push_tokens (user_code, token, is_active, created_at, updated_at)
        VALUES (?, ?, 1, NOW(), NOW())
    ");
    $stmt->execute([$user_code, $token]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Push token saved successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log('Push token save error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>