<?php
// 푸시 알림 전송 함수들
require_once 'config.php';

/**
 * FCM 푸시 알림 전송
 */
function sendPushNotification($tokens, $title, $body, $data = [], $click_action = null) {
    // Firebase 서버 키 (Firebase Console에서 가져와야 함)
    $server_key = 'your-firebase-server-key';
    
    if (empty($server_key) || $server_key === 'your-firebase-server-key') {
        error_log('Firebase 서버 키가 설정되지 않았습니다.');
        return false;
    }
    
    // 단일 토큰을 배열로 변환
    if (!is_array($tokens)) {
        $tokens = [$tokens];
    }
    
    // FCM 메시지 구성
    $notification = [
        'title' => $title,
        'body' => $body,
        'icon' => '/static/icon-192.png',
        'badge' => '/static/icon-192.png',
        'click_action' => $click_action ?: '/'
    ];
    
    $message = [
        'registration_ids' => $tokens,
        'notification' => $notification,
        'data' => array_merge($data, [
            'title' => $title,
            'body' => $body,
            'timestamp' => time()
        ]),
        'webpush' => [
            'headers' => [
                'TTL' => '300'  // 5분 후 만료
            ],
            'notification' => $notification
        ]
    ];
    
    // FCM API 호출
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $server_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['success'])) {
        error_log("푸시 알림 전송 성공: {$result['success']}개 전송");
        return true;
    } else {
        error_log("푸시 알림 전송 실패: " . $response);
        return false;
    }
}

/**
 * 특정 사용자에게 푸시 알림 전송
 */
function sendPushToUser($user_code, $title, $body, $data = []) {
    try {
        $pdo = getDbConnection();
        
        // 사용자의 활성 토큰들 조회
        $stmt = $pdo->prepare("
            SELECT token FROM push_tokens 
            WHERE user_code = ? AND is_active = 1
        ");
        $stmt->execute([$user_code]);
        
        $tokens = [];
        while ($row = $stmt->fetch()) {
            $tokens[] = $row['token'];
        }
        
        if (empty($tokens)) {
            error_log("사용자 {$user_code}의 활성 토큰이 없습니다.");
            return false;
        }
        
        return sendPushNotification($tokens, $title, $body, $data);
        
    } catch (Exception $e) {
        error_log("푸시 알림 전송 오류: " . $e->getMessage());
        return false;
    }
}

/**
 * 전체 사용자에게 공지사항 푸시
 */
function sendPushToAllUsers($title, $body, $data = []) {
    try {
        $pdo = getDbConnection();
        
        // 모든 활성 토큰 조회
        $stmt = $pdo->prepare("
            SELECT DISTINCT token FROM push_tokens 
            WHERE is_active = 1
            ORDER BY updated_at DESC
        ");
        $stmt->execute();
        
        $tokens = [];
        while ($row = $stmt->fetch()) {
            $tokens[] = $row['token'];
        }
        
        if (empty($tokens)) {
            error_log("활성 토큰이 없습니다.");
            return false;
        }
        
        // FCM은 한 번에 1000개 토큰까지만 지원
        $chunks = array_chunk($tokens, 1000);
        $success_count = 0;
        
        foreach ($chunks as $chunk) {
            if (sendPushNotification($chunk, $title, $body, $data)) {
                $success_count += count($chunk);
            }
        }
        
        error_log("전체 푸시 알림 완료: {$success_count}개 전송");
        return $success_count > 0;
        
    } catch (Exception $e) {
        error_log("전체 푸시 알림 오류: " . $e->getMessage());
        return false;
    }
}

/**
 * 족보 업데이트 알림 전송 (예시)
 */
function sendGenealogyUpdateNotification($person_name, $update_type = 'modified') {
    $messages = [
        'added' => "새로운 인물이 추가되었습니다",
        'modified' => "인물 정보가 수정되었습니다", 
        'deleted' => "인물 정보가 삭제되었습니다"
    ];
    
    $title = "족보 업데이트 알림";
    $body = $messages[$update_type] . ": " . $person_name;
    
    $data = [
        'type' => 'genealogy_update',
        'person_name' => $person_name,
        'update_type' => $update_type
    ];
    
    return sendPushToAllUsers($title, $body, $data);
}

// 사용 예시:
// sendPushToUser('441258', '생일 축하', '닥터조님, 생일을 축하드립니다!');
// sendGenealogyUpdateNotification('조영국', 'added');
// sendPushToAllUsers('시스템 점검 안내', '오늘 밤 10시-12시 시스템 점검이 있습니다.');
?>