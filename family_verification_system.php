<?php
// 족보 기반 본인 인증 시스템
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
safeSessionStart();

// 로그인 확인
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'email' => $_SESSION['email'] ?? '',
    'name' => $_SESSION['name'] ?? '',
    'verification_status' => $_SESSION['verification_status'] ?? 'pending'
];

$step = $_GET['step'] ?? '1';
$verification_result = null;
$error_message = '';
$success_message = '';

try {
    $db_config = [
        'host' => 'localhost',
        'dbname' => 'cyjc25',
        'username' => 'cyjc25',
        'password' => 'whdudrnr!!70'
    ];
    
    $pdo = new PDO("mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4", 
                   $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 인증 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $verification_type = $_POST['verification_type'] ?? '';
        
        switch ($verification_type) {
            case 'phone':
                $phone = trim($_POST['phone'] ?? '');
                if (!empty($phone)) {
                    // 연락처로 검색 (다양한 형태의 연락처 매칭)
                    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
                    $stmt = $pdo->prepare("
                        SELECT *, 
                               CASE 
                                   WHEN phone = ? THEN 100
                                   WHEN REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '(', '') LIKE ? THEN 90
                                   WHEN contact_info LIKE ? THEN 80
                                   ELSE 0
                               END as match_score
                        FROM family_members 
                        WHERE phone = ? 
                           OR REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '(', '') LIKE ?
                           OR contact_info LIKE ?
                        ORDER BY match_score DESC
                        LIMIT 5
                    ");
                    $phone_pattern = '%' . $phone_clean . '%';
                    $stmt->execute([$phone, $phone_pattern, $phone_pattern, $phone, $phone_pattern, $phone_pattern]);
                    $verification_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
                
            case 'father':
                $father_name = trim($_POST['father_name'] ?? '');
                $my_name = trim($_POST['my_name'] ?? '');
                if (!empty($father_name) && !empty($my_name)) {
                    // 아버지 이름으로 검색 후, 그 자녀 중에서 내 이름 찾기
                    $stmt = $pdo->prepare("
                        SELECT child.*, parent.name as father_name,
                               CASE 
                                   WHEN child.name = ? THEN 100
                                   WHEN child.name LIKE ? THEN 80
                                   ELSE 0
                               END as match_score
                        FROM family_members parent
                        JOIN family_members child ON child.father_id = parent.id
                        WHERE parent.name = ? OR parent.name LIKE ?
                        ORDER BY match_score DESC, child.name
                        LIMIT 10
                    ");
                    $my_name_pattern = '%' . $my_name . '%';
                    $father_name_pattern = '%' . $father_name . '%';
                    $stmt->execute([$my_name, $my_name_pattern, $father_name, $father_name_pattern]);
                    $verification_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
                
            case 'sibling':
                $sibling_name = trim($_POST['sibling_name'] ?? '');
                $my_name = trim($_POST['my_name'] ?? '');
                if (!empty($sibling_name) && !empty($my_name)) {
                    // 형제 이름으로 검색 후, 같은 아버지를 가진 자녀들 찾기
                    $stmt = $pdo->prepare("
                        SELECT fm.*, sibling.name as sibling_name, parent.name as father_name,
                               CASE 
                                   WHEN fm.name = ? THEN 100
                                   WHEN fm.name LIKE ? THEN 80
                                   ELSE 0
                               END as match_score
                        FROM family_members sibling
                        JOIN family_members fm ON fm.father_id = sibling.father_id AND fm.id != sibling.id
                        LEFT JOIN family_members parent ON fm.father_id = parent.id
                        WHERE sibling.name = ? OR sibling.name LIKE ?
                        ORDER BY match_score DESC, fm.name
                        LIMIT 10
                    ");
                    $my_name_pattern = '%' . $my_name . '%';
                    $sibling_name_pattern = '%' . $sibling_name . '%';
                    $stmt->execute([$my_name, $my_name_pattern, $sibling_name, $sibling_name_pattern]);
                    $verification_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
        }
        
        // 인증 성공 처리
        if (isset($_POST['confirm_member_id']) && is_numeric($_POST['confirm_member_id'])) {
            $member_id = (int)$_POST['confirm_member_id'];
            
            // user_auth 테이블 업데이트
            $stmt = $pdo->prepare("UPDATE user_auth SET family_member_id = ?, verification_status = 'verified', updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$member_id, $current_user['id']])) {
                // 세션 업데이트
                $_SESSION['family_member_id'] = $member_id;
                $_SESSION['verification_status'] = 'verified';
                
                // family_members에서 access_level 가져와서 세션 업데이트
                $stmt = $pdo->prepare("SELECT access_level FROM family_members WHERE id = ?");
                $stmt->execute([$member_id]);
                $family_data = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($family_data) {
                    $_SESSION['access_level'] = $family_data['access_level'] ?? 2;
                }
                
                $success_message = "인증이 완료되었습니다! 족보 시스템을 이용하실 수 있습니다.";
                
                // 3초 후 메인 페이지로 리다이렉트
                header("refresh:3;url=index.php?welcome=1");
            }
        }
    }
    
} catch (Exception $e) {
    $error_message = "오류가 발생했습니다: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>창녕조씨 족보 - 본인 인증</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- 헤더 -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-indigo-800 mb-2">
                    <i class="fas fa-users mr-3"></i>창녕조씨 족보 본인 인증
                </h1>
                <p class="text-gray-600">가족 관계 정보를 통해 족보에서의 위치를 확인합니다</p>
            </div>
            
            <!-- 현재 사용자 정보 -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-user mr-2"></i>로그인 정보
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <strong>이름:</strong> <?php echo htmlspecialchars($current_user['name']); ?>
                    </div>
                    <div>
                        <strong>이메일:</strong> <?php echo htmlspecialchars($current_user['email']); ?>
                    </div>
                    <div class="md:col-span-2">
                        <strong>인증 상태:</strong> 
                        <span class="<?php echo $current_user['verification_status'] === 'verified' ? 'text-green-600' : 'text-yellow-600'; ?>">
                            <?php echo $current_user['verification_status'] === 'verified' ? '인증 완료' : '인증 대기'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <!-- 인증 단계별 탭 -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="flex border-b">
                    <button onclick="showStep('phone')" id="tab-phone" class="flex-1 px-6 py-3 text-center border-r hover:bg-gray-50 tab-button active">
                        <i class="fas fa-phone mr-2"></i>연락처 인증
                    </button>
                    <button onclick="showStep('father')" id="tab-father" class="flex-1 px-6 py-3 text-center border-r hover:bg-gray-50 tab-button">
                        <i class="fas fa-male mr-2"></i>아버지 성명
                    </button>
                    <button onclick="showStep('sibling')" id="tab-sibling" class="flex-1 px-6 py-3 text-center hover:bg-gray-50 tab-button">
                        <i class="fas fa-users mr-2"></i>형제 성명
                    </button>
                </div>
                
                <!-- 연락처 인증 -->
                <div id="step-phone" class="p-6 step-content">
                    <h3 class="text-xl font-bold mb-4">📱 연락처로 본인 확인</h3>
                    <p class="text-gray-600 mb-4">족보에 등록된 연락처와 일치하는지 확인합니다.</p>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="verification_type" value="phone">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">연락처</label>
                            <input type="tel" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="010-1234-5678" required>
                            <p class="text-xs text-gray-500 mt-1">하이픈(-) 포함해서 입력해주세요</p>
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">
                            <i class="fas fa-search mr-2"></i>연락처로 검색
                        </button>
                    </form>
                </div>
                
                <!-- 아버지 성명 인증 -->
                <div id="step-father" class="p-6 step-content hidden">
                    <h3 class="text-xl font-bold mb-4">👨 아버지 성명으로 본인 확인</h3>
                    <p class="text-gray-600 mb-4">아버지 성명과 본인 성명을 입력하여 족보에서 찾아보세요.</p>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="verification_type" value="father">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">아버지 성명</label>
                            <input type="text" name="father_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="조○○" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">본인 성명</label>
                            <input type="text" name="my_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="조○○" value="<?php echo htmlspecialchars($current_user['name']); ?>">
                        </div>
                        <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700">
                            <i class="fas fa-search mr-2"></i>아버지 성명으로 검색
                        </button>
                    </form>
                </div>
                
                <!-- 형제 성명 인증 -->
                <div id="step-sibling" class="p-6 step-content hidden">
                    <h3 class="text-xl font-bold mb-4">👫 형제 성명으로 본인 확인</h3>
                    <p class="text-gray-600 mb-4">형제/자매의 성명과 본인 성명을 입력하여 족보에서 찾아보세요.</p>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="verification_type" value="sibling">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">형제/자매 성명</label>
                            <input type="text" name="sibling_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="조○○" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">본인 성명</label>
                            <input type="text" name="my_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" 
                                   placeholder="조○○" value="<?php echo htmlspecialchars($current_user['name']); ?>">
                        </div>
                        <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700">
                            <i class="fas fa-search mr-2"></i>형제 성명으로 검색
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- 검색 결과 -->
            <?php if ($verification_result): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <h3 class="text-xl font-bold mb-4">
                    <i class="fas fa-list mr-2"></i>검색 결과 (<?php echo count($verification_result); ?>명)
                </h3>
                
                <?php foreach ($verification_result as $member): ?>
                <div class="border border-gray-200 rounded-lg p-4 mb-4 hover:bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <strong class="text-lg"><?php echo htmlspecialchars($member['name'] ?? ''); ?></strong>
                            <?php if (!empty($member['generation'])): ?>
                                <span class="text-sm text-gray-600">(<?php echo $member['generation']; ?>세)</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-gray-600">
                            <?php if (!empty($member['phone'])): ?>
                                📱 <?php echo htmlspecialchars($member['phone']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($member['father_name'])): ?>
                                👨 아버지: <?php echo htmlspecialchars($member['father_name']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($member['sibling_name'])): ?>
                                👫 형제: <?php echo htmlspecialchars($member['sibling_name']); ?><br>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <form method="POST" class="inline">
                                <input type="hidden" name="confirm_member_id" value="<?php echo $member['id']; ?>">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700" 
                                        onclick="return confirm('이 정보가 본인의 것이 맞습니까?')">
                                    <i class="fas fa-check mr-1"></i>이 사람이 저입니다
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($verification_result)): ?>
                <div class="text-center text-gray-600 py-8">
                    <i class="fas fa-search text-4xl mb-4"></i>
                    <p>입력하신 정보와 일치하는 족보 구성원을 찾을 수 없습니다.</p>
                    <p class="mt-2">다른 방법을 시도해보시거나 관리자에게 문의해주세요.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- 도움말 -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mt-6">
                <h3 class="text-lg font-bold text-yellow-800 mb-2">
                    <i class="fas fa-lightbulb mr-2"></i>인증 도움말
                </h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• <strong>연락처</strong>: 족보에 등록된 휴대폰 번호나 집 전화번호를 입력하세요</li>
                    <li>• <strong>아버지 성명</strong>: 정확한 한글 이름을 입력하세요 (호는 제외)</li>
                    <li>• <strong>형제 성명</strong>: 형, 동생, 누나, 언니 중 아무나 입력하세요</li>
                    <li>• <strong>동명이인</strong>: 여러 결과가 나올 수 있으니 신중하게 선택하세요</li>
                    <li>• <strong>문의사항</strong>: 인증이 어려우시면 관리자에게 연락주세요</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function showStep(step) {
            // 모든 탭 버튼 비활성화
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active', 'bg-indigo-50', 'text-indigo-600', 'border-b-2', 'border-indigo-600');
            });
            
            // 모든 컨텐츠 숨기기
            document.querySelectorAll('.step-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // 선택된 탭 활성화
            const activeTab = document.getElementById('tab-' + step);
            activeTab.classList.add('active', 'bg-indigo-50', 'text-indigo-600', 'border-b-2', 'border-indigo-600');
            
            // 선택된 컨텐츠 보이기
            document.getElementById('step-' + step).classList.remove('hidden');
        }
        
        // 초기 로딩시 첫 번째 탭 활성화
        showStep('phone');
    </script>
    
    <style>
        .tab-button.active {
            background-color: #f0f4ff;
            color: #4f46e5;
            border-bottom: 2px solid #4f46e5;
        }
    </style>
</body>
</html>