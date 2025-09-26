<?php
// 창녕조씨 족보 시스템 - 족보 편집 시스템
session_start();
require_once 'config.php';

// 기본 인증 체크
if (!isLoggedIn() && !isVerifiedMember()) {
    header('Location: /');
    exit;
}

// 편집 대상 person_code
$target_person_code = $_GET['person_code'] ?? '';
$action = $_GET['action'] ?? 'view'; // view, edit, add_child, add_spouse

if (empty($target_person_code)) {
    header('Location: search.php');
    exit;
}

// 데이터베이스 연결
$pdo = getDbConnection();
if (!$pdo) {
    die('데이터베이스 연결 오류');
}

// 현재 로그인 사용자의 정확한 정보 가져오기 (DB 기준)
$current_user_code = getCurrentUserPersonCode();
if (!$current_user_code) {
    $current_user_code = '441258'; // 테스트용 기본값
}

// 현재 사용자의 access_level 조회 (DB 기준)
$user_access_level = getCurrentUserAccessLevel();

// access_level 기준 권한: 1-2레벨(관리자), 3이상(4촌제한)
$is_admin = ($user_access_level <= 2);

// 인물 정보 조회 함수
function getPersonInfo($pdo, $person_code) {
    if (empty($person_code)) return null;
    
    $stmt = $pdo->prepare("
        SELECT person_code, name, name_hanja, gender, birth_date, death_date, is_deceased, 
               parent_code, generation, sibling_order, child_count, phone_number, email,
               home_address, work_address, biography, biography_hanja, access_level
        FROM family_members 
        WHERE person_code = ?
    ");
    $stmt->execute([$person_code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4촌 관계 계산 함수 (기존 relationship_tree.php에서 가져옴)
function getAncestorPath($pdo, $person_code) {
    $path = [];
    $current_code = $person_code;
    
    for ($i = 0; $i < 20 && !empty($current_code); $i++) {
        $person = getPersonInfo($pdo, $current_code);
        if (!$person) break;
        
        $path[] = $person;
        $current_code = $person['parent_code'];
    }
    
    return $path;
}

function findCommonAncestor($path1, $path2) {
    $reversed_path1 = array_reverse($path1);
    $reversed_path2 = array_reverse($path2);
    
    $common_ancestor = null;
    $min_length = min(count($reversed_path1), count($reversed_path2));
    
    for ($i = 0; $i < $min_length; $i++) {
        if ($reversed_path1[$i]['person_code'] === $reversed_path2[$i]['person_code']) {
            $common_ancestor = $reversed_path1[$i];
        } else {
            break;
        }
    }
    
    return $common_ancestor;
}

function calculateRelationshipDistance($current_path, $target_path, $common_ancestor) {
    if (!$common_ancestor) return 999; // 관계없음
    
    $current_distance = 0;
    $target_distance = 0;
    
    foreach ($current_path as $person) {
        if ($person['person_code'] === $common_ancestor['person_code']) break;
        $current_distance++;
    }
    
    foreach ($target_path as $person) {
        if ($person['person_code'] === $common_ancestor['person_code']) break;
        $target_distance++;
    }
    
    return $current_distance + $target_distance; // 촌수
}

// 배우자 관계 확인 함수
function isSpouse($person_code1, $person_code2) {
    // W/H 접미사 제거하여 기본 코드 비교
    $base1 = preg_replace('/[WH]$/', '', $person_code1);
    $base2 = preg_replace('/[WH]$/', '', $person_code2);
    
    return ($base1 === $base2 && $person_code1 !== $person_code2);
}

// 확장된 가족 관계 계산 함수
function calculateExtendedRelationship($pdo, $current_user_code, $target_person_code) {
    // 1. 직접적인 혈연 관계 확인
    $current_path = getAncestorPath($pdo, $current_user_code);
    $target_path = getAncestorPath($pdo, $target_person_code);
    $common_ancestor = findCommonAncestor($current_path, $target_path);
    
    if ($common_ancestor) {
        $chon = calculateRelationshipDistance($current_path, $target_path, $common_ancestor);
        if ($chon <= 4) {
            return ['chon' => $chon, 'type' => 'blood', 'path' => 'direct'];
        }
    }
    
    // 2. 배우자를 통한 관계 확인 (인척 관계)
    // 현재 사용자의 형제자매들의 배우자 확인
    foreach ($current_path as $index => $person) {
        if ($index === 0) continue; // 본인 제외
        
        // 형제자매인 경우 (2촌)
        if ($index === 1 && !empty($person['parent_code'])) {
            // 형제자매 찾기
            $siblings_stmt = $pdo->prepare("
                SELECT person_code, name, gender 
                FROM family_members 
                WHERE parent_code = ? AND person_code != ?
            ");
            $siblings_stmt->execute([$person['parent_code'], $current_user_code]);
            $siblings = $siblings_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($siblings as $sibling) {
                // 형제자매의 배우자 코드 생성
                $spouse_code = $sibling['person_code'] . ($sibling['gender'] == 1 ? 'W' : 'H');
                
                if ($spouse_code === $target_person_code) {
                    return ['chon' => 3, 'type' => 'marriage', 'path' => 'sibling_spouse'];
                }
            }
        }
    }
    
    // 3. 역방향 확인: 대상이 나의 가족의 배우자인지
    $current_person = getPersonInfo($pdo, $current_user_code);
    if ($current_person && !empty($current_person['parent_code'])) {
        // 나의 형제자매들 조회
        $siblings_stmt = $pdo->prepare("
            SELECT person_code, name, gender 
            FROM family_members 
            WHERE parent_code = ? AND person_code != ?
        ");
        $siblings_stmt->execute([$current_person['parent_code'], $current_user_code]);
        $siblings = $siblings_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($siblings as $sibling) {
            $spouse_code = $sibling['person_code'] . ($sibling['gender'] == 1 ? 'W' : 'H');
            
            if ($spouse_code === $target_person_code) {
                return ['chon' => 3, 'type' => 'marriage', 'path' => 'my_sibling_spouse'];
            }
        }
    }
    
    return ['chon' => 999, 'type' => 'none', 'path' => 'no_relation'];
}

// 편집 권한 체크 함수
function canEditPerson($pdo, $current_user_code, $target_person_code, $is_admin = false) {
    // 강화된 관리자 권한 체크 - 여러 방법으로 확인
    $user_access_level = getCurrentUserAccessLevel();
    
    // 방법 1: 전달받은 $is_admin 파라미터 체크
    if ($is_admin) {
        return ['can_edit' => true, 'reason' => '관리자 권한 (파라미터 기준)', 'chon' => 0];
    }
    
    // 방법 2: 직접 access_level 체크 (1-2 레벨은 관리자)
    if ($user_access_level && $user_access_level <= 2) {
        return ['can_edit' => true, 'reason' => '관리자 권한 (access_level ' . $user_access_level . ')', 'chon' => 0];
    }
    
    // 방법 3: 특정 관리자 코드 하드코딩 체크 (닥터조님)
    if ($current_user_code === '441258') {
        return ['can_edit' => true, 'reason' => '시스템 관리자 (441258)', 'chon' => 0];
    }
    
    // 본인은 편집 가능
    if ($current_user_code === $target_person_code) {
        return ['can_edit' => true, 'reason' => '본인', 'chon' => 0];
    }
    
    // 확장된 가족 관계 계산 (혈연 + 인척)
    $relationship = calculateExtendedRelationship($pdo, $current_user_code, $target_person_code);
    $chon = $relationship['chon'];
    
    if ($chon <= 4) {
        $relations = [
            0 => '본인',
            1 => '부모/자녀',
            2 => '조부모/손자녀/형제자매',
            3 => '증조부모/증손/삼촌고모/조카/동서관계',
            4 => '사촌'
        ];
        $relation = $relations[$chon] ?? ($chon . '촌');
        
        // 관계 타입 추가 정보
        $type_info = '';
        if ($relationship['type'] === 'marriage') {
            $type_info = ' (인척관계)';
        } elseif ($relationship['type'] === 'blood') {
            $type_info = ' (혈연관계)';
        }
        
        return ['can_edit' => true, 'reason' => $relation . $type_info, 'chon' => $chon];
    }
    
    return ['can_edit' => false, 'reason' => $chon . '촌 관계 (4촌 초과)', 'chon' => $chon];
}

// 자녀 추가 함수
function addChild($pdo, $parent_code, $child_data) {
    try {
        // 새로운 person_code 생성 (간단한 증가 방식)
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(person_code, 1, 6) AS UNSIGNED)) as max_code FROM family_members WHERE LENGTH(person_code) = 6");
        $stmt->execute();
        $result = $stmt->fetch();
        $new_code = ($result['max_code'] ?? 440000) + 1;
        
        // 자녀 정보 삽입
        $stmt = $pdo->prepare("
            INSERT INTO family_members (
                person_code, parent_code, name, name_hanja, gender, birth_date, 
                sibling_order, generation, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $new_code,
            $parent_code,
            $child_data['name'],
            $child_data['name_hanja'] ?? '',
            $child_data['gender'],
            $child_data['birth_date'] ?? null,
            $child_data['sibling_order'] ?? 1,
            ($child_data['parent_generation'] ?? 30) + 1 // 부모 세대 + 1
        ]);
        
        return ['success' => true, 'person_code' => $new_code];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 배우자 추가 함수
function addSpouse($pdo, $person_code, $spouse_data) {
    try {
        // 성별에 따른 배우자 코드 생성
        $person = getPersonInfo($pdo, $person_code);
        if (!$person) {
            return ['success' => false, 'error' => '대상 인물을 찾을 수 없습니다.'];
        }
        
        // 배우자 코드 생성 (DB 패턴에 맞는 올바른 로직)
        if (preg_match('/H$/', $person_code)) {
            // H로 끝나는 남편은 이미 배우자 코드이므로 오류
            return ['success' => false, 'error' => '이미 배우자 코드입니다. 기본 코드를 사용해주세요.'];
        } elseif (preg_match('/W$/', $person_code)) {
            // W로 끝나는 아내는 이미 배우자 코드이므로 오류
            return ['success' => false, 'error' => '이미 배우자 코드입니다. 기본 코드를 사용해주세요.'];
        } else {
            // 기본코드 -> 성별에 따라 배우자 코드 생성
            $spouse_code = $person_code . ($person['gender'] == 1 ? 'W' : 'H'); // 남성→아내(W), 여성→남편(H)
        }
        
        // 배우자 정보 삽입
        $stmt = $pdo->prepare("
            INSERT INTO family_members (
                person_code, name, name_hanja, gender, birth_date, 
                generation, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $spouse_code,
            $spouse_data['name'],
            $spouse_data['name_hanja'] ?? '',
            $spouse_data['gender'],
            $spouse_data['birth_date'] ?? null,
            $person['generation'] // 같은 세대
        ]);
        
        return ['success' => true, 'person_code' => $spouse_code];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// POST 요청 처리
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 권한 체크 (디버그 정보 포함)
    $permission = canEditPerson($pdo, $current_user_code, $target_person_code, $is_admin);
    
    // 디버그 정보 로깅 (관리자용)
    if ($user_access_level <= 2) {
        error_log("권한 체크 - 사용자: $current_user_code, 대상: $target_person_code, access_level: $user_access_level, is_admin: " . ($is_admin ? 'true' : 'false') . ", 결과: " . ($permission['can_edit'] ? '허용' : '거부') . ", 이유: " . $permission['reason']);
    }
    
    if (!$permission['can_edit']) {
        $message = "편집 권한이 없습니다. (" . $permission['reason'] . ") [디버그: 사용자코드=$current_user_code, access_level=$user_access_level]";
        $message_type = 'error';
    } else {
        switch ($action) {
            case 'update_basic':
                // 기본 정보 수정
                try {
                    $stmt = $pdo->prepare("
                        UPDATE family_members 
                        SET name = ?, name_hanja = ?, birth_date = ?, death_date = ?, 
                            phone_number = ?, email = ?, home_address = ?, biography = ?, 
                            updated_at = NOW()
                        WHERE person_code = ?
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['name_hanja'] ?? '',
                        $_POST['birth_date'] ?? null,
                        $_POST['death_date'] ?? null,
                        $_POST['phone_number'] ?? '',
                        $_POST['email'] ?? '',
                        $_POST['home_address'] ?? '',
                        $_POST['biography'] ?? '',
                        $target_person_code
                    ]);
                    
                    $message = "정보가 성공적으로 수정되었습니다.";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = "수정 중 오류가 발생했습니다: " . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'add_child':
                // 자녀 추가
                $child_data = [
                    'name' => $_POST['child_name'],
                    'name_hanja' => $_POST['child_name_hanja'] ?? '',
                    'gender' => $_POST['child_gender'],
                    'birth_date' => $_POST['child_birth_date'] ?? null,
                    'sibling_order' => $_POST['child_sibling_order'] ?? 1,
                    'parent_generation' => getPersonInfo($pdo, $target_person_code)['generation']
                ];
                
                $result = addChild($pdo, $target_person_code, $child_data);
                
                if ($result['success']) {
                    $message = "자녀가 성공적으로 추가되었습니다. (코드: " . $result['person_code'] . ")";
                    $message_type = 'success';
                } else {
                    $message = "자녀 추가 중 오류가 발생했습니다: " . $result['error'];
                    $message_type = 'error';
                }
                break;
                
            case 'add_spouse':
                // 배우자 추가
                $spouse_data = [
                    'name' => $_POST['spouse_name'],
                    'name_hanja' => $_POST['spouse_name_hanja'] ?? '',
                    'gender' => $_POST['spouse_gender'],
                    'birth_date' => $_POST['spouse_birth_date'] ?? null
                ];
                
                $result = addSpouse($pdo, $target_person_code, $spouse_data);
                
                if ($result['success']) {
                    $message = "배우자가 성공적으로 추가되었습니다. (코드: " . $result['person_code'] . ")";
                    $message_type = 'success';
                } else {
                    $message = "배우자 추가 중 오류가 발생했습니다: " . $result['error'];
                    $message_type = 'error';
                }
                break;
        }
    }
}

// 대상 인물 정보 조회
$person = getPersonInfo($pdo, $target_person_code);
if (!$person) {
    header('Location: search.php');
    exit;
}

// 편집 권한 체크
$permission = canEditPerson($pdo, $current_user_code, $target_person_code, $is_admin);

// 자녀 목록 조회
$children = [];
$stmt = $pdo->prepare("
    SELECT person_code, name, name_hanja, gender, birth_date, sibling_order
    FROM family_members 
    WHERE parent_code = ?
    ORDER BY sibling_order, birth_date
");
$stmt->execute([$target_person_code]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 배우자 조회 (DB 패턴에 맞는 올바른 로직)
$spouse = null;
if (preg_match('/H$/', $target_person_code)) {
    // H로 끝나는 남편 -> H를 제거한 기본코드가 아내
    $spouse_code = preg_replace('/H$/', '', $target_person_code);
} elseif (preg_match('/W$/', $target_person_code)) {
    // W로 끝나는 아내 -> W를 제거한 기본코드가 남편
    $spouse_code = preg_replace('/W$/', '', $target_person_code);
} else {
    // 기본코드 -> 성별에 따라 배우자 코드 생성
    $spouse_code = $target_person_code . ($person['gender'] == 1 ? 'W' : 'H');
}
$spouse = getPersonInfo($pdo, $spouse_code);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>족보 편집 - <?= htmlspecialchars($person['name']) ?> | 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .edit-section {
            border-left: 4px solid #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }
        
        .permission-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .permission-allowed {
            background: #dcfce7;
            color: #166534;
        }
        
        .permission-denied {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .form-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-gradient-to-r from-blue-600 to-green-600 text-white py-4 shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">
                    <i class="fas fa-edit mr-2"></i>
                    족보 편집 시스템
                </h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm">
                        <i class="fas fa-user mr-1"></i>
                        편집 대상: <?= htmlspecialchars($person['name']) ?>
                    </span>
                    <a href="person_detail.php?person_code=<?= $person['person_code'] ?>" class="bg-white text-blue-600 px-4 py-2 rounded hover:bg-blue-50">
                        <i class="fas fa-eye mr-1"></i> 상세보기
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- 권한 표시 -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-lg font-bold mb-4">
                <i class="fas fa-shield-alt mr-2 text-blue-600"></i>편집 권한
            </h2>
            
            <div class="flex items-center gap-4">
                <span class="permission-badge <?= $permission['can_edit'] ? 'permission-allowed' : 'permission-denied' ?>">
                    <?php if ($permission['can_edit']): ?>
                        <i class="fas fa-check mr-1"></i>편집 가능
                    <?php else: ?>
                        <i class="fas fa-times mr-1"></i>편집 불가
                    <?php endif; ?>
                </span>
                
                <span class="text-sm text-gray-600">
                    사유: <?= htmlspecialchars($permission['reason']) ?>
                    <?php if ($permission['chon'] > 0): ?>
                        (<?= $permission['chon'] ?>촌)
                    <?php endif; ?>
                </span>
                
                <?php if ($is_admin): ?>
                    <span class="permission-badge" style="background: #fef3c7; color: #92400e;">
                        <i class="fas fa-crown mr-1"></i>관리자 (access_level 1-2)
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 메시지 표시 -->
        <?php if (!empty($message)): ?>
            <div class="mb-6">
                <?php if ($message_type === 'success'): ?>
                    <?= showSuccess($message) ?>
                <?php elseif ($message_type === 'error'): ?>
                    <?= showError($message) ?>
                <?php else: ?>
                    <?= showInfo($message) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($permission['can_edit']): ?>
            
            <!-- 권한 안내 -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-bold text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-1"></i>편집 권한 안내
                </h3>
                <div class="text-sm text-blue-700">
                    <?php if ($is_admin): ?>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-crown text-yellow-500"></i>
                            <strong>관리자 권한</strong>: 모든 인물의 족보 정보를 편집할 수 있습니다. (access_level 1-2)
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-users text-blue-500"></i>
                            <strong>4촌 이내 편집 권한</strong>: 당신과 <?= $permission['chon'] ?>촌 관계로 편집 가능합니다.
                        </div>
                        <div class="text-xs text-blue-600 mt-1 ml-6">
                            • 본인, 부모/자녀, 조부모/손자녀/형제자매, 삼촌고모/조카, 사촌까지 편집 가능 (access_level 3+)
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 기본 정보 편집 -->
            <div class="form-section edit-section">
                <h3 class="text-lg font-bold mb-4">
                    <i class="fas fa-user-edit mr-2 text-blue-600"></i>기본 정보 수정
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_basic">
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">이름 *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($person['name']) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">한자명</label>
                            <input type="text" name="name_hanja" value="<?= htmlspecialchars($person['name_hanja']) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">생년월일</label>
                            <input type="date" name="birth_date" value="<?= $person['birth_date'] ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">사망일</label>
                            <input type="date" name="death_date" value="<?= $person['death_date'] ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">전화번호</label>
                            <input type="tel" name="phone_number" value="<?= htmlspecialchars($person['phone_number']) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">이메일</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($person['email']) ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                        <input type="text" name="home_address" value="<?= htmlspecialchars($person['home_address']) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">약력</label>
                        <textarea name="biography" rows="4" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($person['biography']) ?></textarea>
                    </div>
                    
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-1"></i>정보 수정
                    </button>
                </form>
            </div>

            <!-- 배우자 관리 -->
            <div class="form-section">
                <h3 class="text-lg font-bold mb-4">
                    <i class="fas fa-heart mr-2 text-pink-600"></i>배우자 관리
                </h3>
                
                <?php if ($spouse): ?>
                    <div class="bg-pink-50 border border-pink-200 rounded p-4 mb-4">
                        <h4 class="font-medium text-pink-800 mb-2">현재 배우자</h4>
                        <div class="text-sm text-pink-700">
                            <strong><?= htmlspecialchars($spouse['name']) ?></strong>
                            <?php if (!empty($spouse['name_hanja'])): ?>
                                (<?= htmlspecialchars($spouse['name_hanja']) ?>)
                            <?php endif; ?>
                            <?php if (!empty($spouse['birth_date'])): ?>
                                · <?= $spouse['birth_date'] ?>
                            <?php endif; ?>
                        </div>
                        <a href="genealogy_edit.php?person_code=<?= $spouse['person_code'] ?>" class="text-pink-600 text-sm hover:underline">
                            <i class="fas fa-edit mr-1"></i>배우자 정보 수정
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_spouse">
                        
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">배우자 이름 *</label>
                                <input type="text" name="spouse_name" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-pink-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">한자명</label>
                                <input type="text" name="spouse_name_hanja" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-pink-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">성별 *</label>
                                <select name="spouse_gender" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-pink-500 focus:border-transparent">
                                    <option value="<?= $person['gender'] == 1 ? '2' : '1' ?>">
                                        <?= $person['gender'] == 1 ? '여성' : '남성' ?>
                                    </option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">생년월일</label>
                                <input type="date" name="spouse_birth_date" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-pink-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <button type="submit" class="bg-pink-600 text-white px-6 py-2 rounded hover:bg-pink-700 transition-colors">
                            <i class="fas fa-plus mr-1"></i>배우자 추가
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- 자녀 관리 -->
            <div class="form-section">
                <h3 class="text-lg font-bold mb-4">
                    <i class="fas fa-baby mr-2 text-green-600"></i>자녀 관리
                </h3>
                
                <!-- 기존 자녀 목록 -->
                <?php if (!empty($children)): ?>
                    <div class="bg-green-50 border border-green-200 rounded p-4 mb-4">
                        <h4 class="font-medium text-green-800 mb-2">현재 자녀 (<?= count($children) ?>명)</h4>
                        <div class="space-y-2">
                            <?php foreach ($children as $child): ?>
                                <div class="flex items-center justify-between text-sm text-green-700 bg-white p-2 rounded">
                                    <span>
                                        <strong><?= htmlspecialchars($child['name']) ?></strong>
                                        <?php if (!empty($child['name_hanja'])): ?>
                                            (<?= htmlspecialchars($child['name_hanja']) ?>)
                                        <?php endif; ?>
                                        · <?= $child['gender'] == 1 ? '남' : '여' ?>
                                        <?php if ($child['sibling_order']): ?>
                                            · <?= $child['sibling_order'] ?>째
                                        <?php endif; ?>
                                        <?php if (!empty($child['birth_date'])): ?>
                                            · <?= $child['birth_date'] ?>
                                        <?php endif; ?>
                                    </span>
                                    <a href="genealogy_edit.php?person_code=<?= $child['person_code'] ?>" class="text-green-600 hover:underline">
                                        <i class="fas fa-edit mr-1"></i>수정
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 자녀 추가 폼 -->
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_child">
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">자녀 이름 *</label>
                            <input type="text" name="child_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">한자명</label>
                            <input type="text" name="child_name_hanja" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">성별 *</label>
                            <select name="child_gender" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="">선택하세요</option>
                                <option value="1">남성</option>
                                <option value="2">여성</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">출생 순서</label>
                            <input type="number" name="child_sibling_order" value="<?= count($children) + 1 ?>" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">생년월일</label>
                            <input type="date" name="child_birth_date" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition-colors">
                        <i class="fas fa-plus mr-1"></i>자녀 추가
                    </button>
                </form>
            </div>

        <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <i class="fas fa-lock text-4xl text-red-400 mb-4"></i>
                <h3 class="text-lg font-bold text-red-800 mb-2">편집 권한이 없습니다</h3>
                <p class="text-red-600">
                    <?= htmlspecialchars($permission['reason']) ?>
                    <?php if ($permission['chon'] > 4): ?>
                        <br>4촌 이내의 가족만 편집할 수 있습니다.
                    <?php endif; ?>
                </p>
                <div class="mt-4">
                    <a href="person_detail.php?person_code=<?= $person['person_code'] ?>" class="text-blue-600 hover:underline">
                        <i class="fas fa-eye mr-1"></i>상세정보 보기
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 푸터 -->
    <footer class="bg-gray-800 text-white py-6 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 창녕조씨 족보 시스템. 족보 편집 시스템.</p>
            <p class="text-sm text-gray-400 mt-2">관리자(access_level 1-2): 전체 편집 · 일반사용자(3+): 4촌이내 편집 · 안전한 데이터 관리</p>
        </div>
    </footer>

    <script>
        // 폼 제출 확인
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const action = form.querySelector('input[name="action"]').value;
                    let confirmMessage = '';
                    
                    switch(action) {
                        case 'update_basic':
                            confirmMessage = '기본 정보를 수정하시겠습니까?';
                            break;
                        case 'add_child':
                            confirmMessage = '새 자녀를 추가하시겠습니까?';
                            break;
                        case 'add_spouse':
                            confirmMessage = '배우자를 추가하시겠습니까?';
                            break;
                    }
                    
                    if (confirmMessage && !confirm(confirmMessage)) {
                        e.preventDefault();
                    }
                });
            });
            
            console.log('족보 편집 시스템 로드 완료');
        });
    </script>
</body>
</html>