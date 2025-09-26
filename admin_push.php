<?php
// 관리자용 푸시 알림 전송 페이지
session_start();
require_once 'config.php';
require_once 'send_push.php';

// 관리자 권한 체크
$user_access_level = getCurrentUserAccessLevel();
if (!$user_access_level || $user_access_level > 2) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// 푸시 알림 전송 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $target_user = $_POST['target_user'] ?? '';
    
    if (empty($title) || empty($body)) {
        $message = '제목과 내용을 입력해주세요.';
        $message_type = 'error';
    } else {
        try {
            $success = false;
            
            switch ($action) {
                case 'send_to_all':
                    $success = sendPushToAllUsers($title, $body, [
                        'type' => 'admin_notice',
                        'sender' => 'admin'
                    ]);
                    $target_desc = '전체 사용자';
                    break;
                    
                case 'send_to_user':
                    if (empty($target_user)) {
                        throw new Exception('대상 사용자를 선택해주세요.');
                    }
                    $success = sendPushToUser($target_user, $title, $body, [
                        'type' => 'personal_message',
                        'sender' => 'admin'
                    ]);
                    $target_desc = "사용자 ($target_user)";
                    break;
                    
                default:
                    throw new Exception('잘못된 전송 유형입니다.');
            }
            
            if ($success) {
                $message = "{$target_desc}에게 푸시 알림이 전송되었습니다.";
                $message_type = 'success';
            } else {
                $message = '푸시 알림 전송에 실패했습니다.';
                $message_type = 'error';
            }
            
        } catch (Exception $e) {
            $message = '오류: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 사용자 목록 조회 (드롭다운용)
$users = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT DISTINCT f.person_code, f.name 
        FROM family_members f
        INNER JOIN user_auth ua ON f.id = ua.family_member_id
        ORDER BY f.name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('사용자 목록 조회 오류: ' . $e->getMessage());
}

// 푸시 토큰 통계
$push_stats = ['total_tokens' => 0, 'active_users' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_tokens FROM push_tokens WHERE is_active = 1");
    $stmt->execute();
    $push_stats['total_tokens'] = $stmt->fetch()['total_tokens'];
    
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_code) as active_users FROM push_tokens WHERE is_active = 1");
    $stmt->execute();
    $push_stats['active_users'] = $stmt->fetch()['active_users'];
} catch (Exception $e) {
    error_log('푸시 통계 조회 오류: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>푸시 알림 관리 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include 'common_header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-bell text-blue-600 mr-2"></i>
                    푸시 알림 관리
                </h1>
                
                <!-- 통계 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-mobile-alt text-blue-600 text-2xl mr-3"></i>
                            <div>
                                <h3 class="font-bold text-gray-800">활성 토큰</h3>
                                <p class="text-2xl font-bold text-blue-600"><?= number_format($push_stats['total_tokens']) ?>개</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-users text-green-600 text-2xl mr-3"></i>
                            <div>
                                <h3 class="font-bold text-gray-800">알림 사용자</h3>
                                <p class="text-2xl font-bold text-green-600"><?= number_format($push_stats['active_users']) ?>명</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 메시지 표시 -->
                <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
                    <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>
                
                <!-- 푸시 알림 전송 폼 -->
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- 전송 유형 선택 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">전송 대상</label>
                            <select name="action" id="send-type" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">전송 대상 선택</option>
                                <option value="send_to_all">전체 사용자</option>
                                <option value="send_to_user">특정 사용자</option>
                            </select>
                        </div>
                        
                        <!-- 사용자 선택 (특정 사용자 선택시만 표시) -->
                        <div id="user-select-container" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">대상 사용자</label>
                            <select name="target_user" id="target-user"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">사용자 선택</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= htmlspecialchars($user['person_code']) ?>">
                                    <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['person_code']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- 알림 제목 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">알림 제목</label>
                        <input type="text" name="title" maxlength="50" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="알림 제목을 입력하세요 (최대 50자)">
                    </div>
                    
                    <!-- 알림 내용 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">알림 내용</label>
                        <textarea name="body" rows="4" maxlength="200" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="알림 내용을 입력하세요 (최대 200자)"></textarea>
                    </div>
                    
                    <!-- 전송 버튼 -->
                    <div class="flex justify-between items-center">
                        <a href="admin.php" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-arrow-left mr-1"></i>관리 메뉴로 돌아가기
                        </a>
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-paper-plane mr-2"></i>푸시 알림 전송
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- 주의사항 -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-bold text-yellow-800 mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>푸시 알림 주의사항
                </h3>
                <ul class="text-yellow-700 text-sm space-y-1">
                    <li>• Firebase FCM 설정이 완료되어야 실제 알림이 전송됩니다</li>
                    <li>• 사용자가 알림을 허용한 경우에만 수신됩니다</li>
                    <li>• 과도한 알림 전송은 사용자 경험을 해칠 수 있습니다</li>
                    <li>• 중요한 공지사항이나 개인 메시지에만 사용하세요</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        // 전송 유형에 따른 UI 변경
        document.getElementById('send-type').addEventListener('change', function() {
            const userSelectContainer = document.getElementById('user-select-container');
            const targetUser = document.getElementById('target-user');
            
            if (this.value === 'send_to_user') {
                userSelectContainer.classList.remove('hidden');
                targetUser.required = true;
            } else {
                userSelectContainer.classList.add('hidden');
                targetUser.required = false;
                targetUser.value = '';
            }
        });
        
        // 폼 유효성 검사
        document.querySelector('form').addEventListener('submit', function(e) {
            const sendType = document.getElementById('send-type').value;
            const targetUser = document.getElementById('target-user').value;
            
            if (sendType === 'send_to_user' && !targetUser) {
                e.preventDefault();
                alert('대상 사용자를 선택해주세요.');
                return false;
            }
            
            // 전송 확인
            const title = document.querySelector('input[name="title"]').value;
            const body = document.querySelector('textarea[name="body"]').value;
            const targetDesc = sendType === 'send_to_all' ? '전체 사용자' : '선택한 사용자';
            
            if (!confirm(`"${title}" 알림을 ${targetDesc}에게 전송하시겠습니까?`)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>