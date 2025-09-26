<?php
// 창녕조씨 족보 시스템 - 개인 상세 정보 (완전한 가족 관계)
require_once 'config.php';

// 안전한 세션 시작
if (function_exists('safeSessionStart')) {
    safeSessionStart();
} else if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 기본 인증 체크 (verification.php 없이도 동작)
if (!isLoggedIn() && !isVerifiedMember()) {
    header('Location: /');
    exit;
}

// person_code 파라미터 받기
$person_code = $_GET['person_code'] ?? '';
$search_id = $_GET['id'] ?? '';

if (empty($person_code) && empty($search_id)) {
    header('Location: search.php');
    exit;
}

// 성별 표시 함수
function getGenderDisplay($gender) {
    if ($gender == 1 || $gender == '1') return '남';
    if ($gender == 2 || $gender == '2') return '여';
    return '미상';
}

// 나이 계산 함수
function calculateAge($birth_date) {
    if (empty($birth_date) || $birth_date === '0000-00-00') {
        return '';
    }
    
    try {
        $birth = new DateTime($birth_date);
        $today = new DateTime();
        $age = $today->diff($birth)->y;
        return $age . '세';
    } catch (Exception $e) {
        return '';
    }
}

// JSON 주소 파싱 함수
function parseAddress($address_json) {
    if (empty($address_json)) return '';
    
    $address_data = json_decode($address_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $address_json; // JSON이 아니면 원본 반환
    }
    
    return $address_data['address'] ?? '';
}

// 데이터베이스 연결
$pdo = getDbConnection();
if (!$pdo) {
    die('<div style="padding: 20px; background: #ffe6e6; border: 1px solid #ff9999; border-radius: 5px; margin: 20px;">
         <h2>데이터베이스 연결 오류</h2>
         <p>시스템 관리자에게 문의하세요.</p>
         <a href="search.php">검색으로 돌아가기</a>
         </div>');
}

try {
    // id로 넘어온 경우 먼저 person_code를 찾기
    if (!empty($search_id) && empty($person_code)) {
        $id_stmt = $pdo->prepare("SELECT person_code FROM family_members WHERE id = ?");
        $id_stmt->execute([$search_id]);
        $id_result = $id_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($id_result) {
            $person_code = $id_result['person_code'];
        } else {
            throw new Exception("해당 ID의 인물을 찾을 수 없습니다. (ID: " . $search_id . ")");
        }
    }
    
    // 기본 정보 조회 (person_code 우선 사용)
    $stmt = $pdo->prepare("
        SELECT id, person_code, parent_code, name, name_hanja, gender, generation, 
               sibling_order, child_count, birth_date, death_date, is_deceased,
               phone_number, email, home_address, work_address, biography, biography_hanja,
               is_adopted, access_level, created_at, updated_at
        FROM family_members 
        WHERE person_code = ?
    ");
    $stmt->execute([$person_code]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$person) {
        throw new Exception("해당 인물을 찾을 수 없습니다. (person_code: " . $person_code . ")");
    }

    // === 완벽한 가족 관계 조회 ===
    
    // 1. 아버지 조회 (parent_code)
    $father = null;
    if (!empty($person['parent_code'])) {
        $father_stmt = $pdo->prepare("
            SELECT name, name_hanja, person_code, gender, birth_date, death_date
            FROM family_members 
            WHERE person_code = ?
        ");
        $father_stmt->execute([$person['parent_code']]);
        $father = $father_stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. 어머니 조회 (parent_code + 'W')
    $mother = null;
    if (!empty($person['parent_code'])) {
        $mother_code = $person['parent_code'] . 'W';
        $mother_stmt = $pdo->prepare("
            SELECT name, name_hanja, person_code, gender, birth_date, death_date
            FROM family_members 
            WHERE person_code = ?
        ");
        $mother_stmt->execute([$mother_code]);
        $mother = $mother_stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 3. 배우자 조회 (DB 패턴에 맞는 올바른 로직)
    $spouse = null;
    
    // 배우자 코드 생성 로직 (DB 구조 기반)
    if (preg_match('/H$/', $person_code)) {
        // H로 끝나는 남편 -> H를 제거한 기본코드가 아내
        $spouse_code = preg_replace('/H$/', '', $person_code);
    } elseif (preg_match('/W$/', $person_code)) {
        // W로 끝나는 아내 -> W를 제거한 기본코드가 남편
        $spouse_code = preg_replace('/W$/', '', $person_code);
    } else {
        // 기본코드 -> 성별에 따라 배우자 코드 생성
        if ($person['gender'] == 1 || $person['gender'] == '1') {
            $spouse_code = $person_code . 'W';  // 남성 -> 아내
        } else {
            $spouse_code = $person_code . 'H';  // 여성 -> 남편
        }
    }
    
    $spouse_stmt = $pdo->prepare("
        SELECT name, name_hanja, person_code, birth_date, death_date
        FROM family_members 
        WHERE person_code = ?
    ");
    $spouse_stmt->execute([$spouse_code]);
    $spouse = $spouse_stmt->fetch(PDO::FETCH_ASSOC);

    // 4. 형제자매 조회 (같은 parent_code + 다른 person_code)
    $siblings = [];
    if (!empty($person['parent_code'])) {
        $siblings_stmt = $pdo->prepare("
            SELECT name, name_hanja, person_code, gender, birth_date, sibling_order
            FROM family_members 
            WHERE parent_code = ? AND person_code != ?
            ORDER BY sibling_order, birth_date
        ");
        $siblings_stmt->execute([$person['parent_code'], $person_code]);
        $siblings = $siblings_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. 자녀 조회 (parent_code = 본인 person_code)
    $children = [];
    $children_stmt = $pdo->prepare("
        SELECT name, name_hanja, person_code, gender, birth_date, sibling_order
        FROM family_members 
        WHERE parent_code = ?
        ORDER BY sibling_order, birth_date
        LIMIT 30
    ");
    $children_stmt->execute([$person_code]);
    $children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#3b82f6">
    <title><?= htmlspecialchars($person['name'] ?? '인물 정보') ?> - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/static/mobile-optimized.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php if (isset($error_message)): ?>
        <div class="container mx-auto px-4 py-8">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <h2 class="text-xl font-bold mb-2">오류 발생</h2>
                <p><?= htmlspecialchars($error_message) ?></p>
                <p class="mt-2">검색한 코드: <?= htmlspecialchars($person_code) ?></p>
                <a href="search.php" class="text-blue-600 hover:underline">검색으로 돌아가기</a>
            </div>
        </div>
    <?php else: ?>
        <!-- 헤더 -->
        <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-6 shadow-lg">
            <div class="container mx-auto px-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">
                        <i class="fas fa-user-circle mr-2"></i>
                        <?= htmlspecialchars($person['name']) ?>
                        <?php if (!empty($person['name_hanja'])): ?>
                            <span class="text-lg text-blue-200">(<?= htmlspecialchars($person['name_hanja']) ?>)</span>
                        <?php endif; ?>
                    </h1>
                    <a href="search.php" class="bg-white text-blue-600 px-4 py-2 rounded hover:bg-blue-50">
                        <i class="fas fa-arrow-left mr-1"></i> 검색으로 돌아가기
                    </a>
                </div>
            </div>
        </header>

        <!-- 메인 컨텐츠 -->
        <div class="container mx-auto px-4 py-8">
            <div class="grid md:grid-cols-2 gap-8">
                <!-- 기본 정보 -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>기본 정보
                    </h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">이름:</span>
                            <span class="text-gray-800"><?= htmlspecialchars($person['name']) ?></span>
                        </div>
                        <?php if (!empty($person['name_hanja'])): ?>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">한자명:</span>
                            <span class="text-gray-800"><?= htmlspecialchars($person['name_hanja']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">성별:</span>
                            <span class="text-gray-800"><?= getGenderDisplay($person['gender']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">세대:</span>
                            <span class="text-gray-800"><?= htmlspecialchars($person['generation'] ?? '-') ?>세</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">항렬:</span>
                            <span class="text-gray-800"><?= htmlspecialchars($person['sibling_order'] ?? '-') ?>번째</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">인물 코드:</span>
                            <span class="text-gray-800 font-mono"><?= htmlspecialchars($person['person_code']) ?></span>
                        </div>
                        <?php if (!empty($person['birth_date']) && $person['birth_date'] !== '0000-00-00'): ?>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">생년월일:</span>
                            <span class="text-gray-800">
                                <?= htmlspecialchars($person['birth_date']) ?>
                                <?php if ($age = calculateAge($person['birth_date'])): ?>
                                    <span class="text-sm text-gray-500">(<?= $age ?>)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($person['is_deceased'] && !empty($person['death_date']) && $person['death_date'] !== '0000-00-00'): ?>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">사망일:</span>
                            <span class="text-gray-800"><?= htmlspecialchars($person['death_date']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($person['phone_number'])): ?>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">연락처:</span>
                            <span class="text-gray-800"><?= htmlspecialchars($person['phone_number']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($person['email'])): ?>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-600">이메일:</span>
                            <span class="text-gray-800"><?= htmlspecialchars($person['email']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 가족 정보 -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">
                        <i class="fas fa-users mr-2 text-green-500"></i>가족 관계
                    </h2>
                    
                    <!-- 부모 -->
                    <?php if ($father || $mother): ?>
                    <div class="mb-4">
                        <h3 class="font-medium text-gray-600 mb-2">부모</h3>
                        <div class="space-y-2">
                            <?php if ($father): ?>
                            <div class="bg-blue-50 p-3 rounded border-l-4 border-blue-400">
                                <a href="person_detail.php?person_code=<?= urlencode($father['person_code']) ?>" 
                                   class="text-blue-700 hover:underline font-medium">
                                    <?= htmlspecialchars($father['name']) ?>
                                    <?php if (!empty($father['name_hanja'])): ?>
                                        (<?= htmlspecialchars($father['name_hanja']) ?>)
                                    <?php endif; ?>
                                </a>
                                <span class="text-sm text-gray-500 ml-2">아버지</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($mother): ?>
                            <div class="bg-pink-50 p-3 rounded border-l-4 border-pink-400">
                                <a href="person_detail.php?person_code=<?= urlencode($mother['person_code']) ?>" 
                                   class="text-pink-700 hover:underline font-medium">
                                    <?= htmlspecialchars($mother['name']) ?>
                                    <?php if (!empty($mother['name_hanja'])): ?>
                                        (<?= htmlspecialchars($mother['name_hanja']) ?>)
                                    <?php endif; ?>
                                </a>
                                <span class="text-sm text-gray-500 ml-2">어머니</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 배우자 -->
                    <?php if ($spouse): ?>
                    <div class="mb-4">
                        <h3 class="font-medium text-gray-600 mb-2">배우자</h3>
                        <div class="bg-purple-50 p-3 rounded border-l-4 border-purple-400">
                            <a href="person_detail.php?person_code=<?= urlencode($spouse['person_code']) ?>" 
                               class="text-purple-700 hover:underline font-medium">
                                <?= htmlspecialchars($spouse['name']) ?>
                                <?php if (!empty($spouse['name_hanja'])): ?>
                                    (<?= htmlspecialchars($spouse['name_hanja']) ?>)
                                <?php endif; ?>
                            </a>
                            <?php if (!empty($spouse['birth_date']) && $spouse['birth_date'] !== '0000-00-00'): ?>
                                <span class="text-sm text-gray-500 ml-2"><?= substr($spouse['birth_date'], 0, 4) ?>년생</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 형제자매 -->
                    <?php if (count($siblings) > 0): ?>
                    <div class="mb-4">
                        <h3 class="font-medium text-gray-600 mb-2">형제자매 (<?= count($siblings) ?>명)</h3>
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            <?php foreach ($siblings as $sibling): ?>
                            <div class="bg-orange-50 p-3 rounded border-l-4 border-orange-400">
                                <a href="person_detail.php?person_code=<?= urlencode($sibling['person_code']) ?>" 
                                   class="text-orange-700 hover:underline font-medium">
                                    <?= htmlspecialchars($sibling['name']) ?>
                                    <?php if (!empty($sibling['name_hanja'])): ?>
                                        (<?= htmlspecialchars($sibling['name_hanja']) ?>)
                                    <?php endif; ?>
                                </a>
                                <span class="text-sm text-gray-500 ml-2">
                                    <?= getGenderDisplay($sibling['gender']) ?>
                                    <?php if ($sibling['sibling_order']): ?>
                                        · <?= $sibling['sibling_order'] ?>번째
                                    <?php endif; ?>
                                    <?php if (!empty($sibling['birth_date']) && $sibling['birth_date'] !== '0000-00-00'): ?>
                                        · <?= substr($sibling['birth_date'], 0, 4) ?>년생
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 자녀 -->
                    <?php if (count($children) > 0): ?>
                    <div>
                        <h3 class="font-medium text-gray-600 mb-2">자녀 (<?= count($children) ?>명)</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <?php foreach ($children as $child): ?>
                            <div class="bg-green-50 p-3 rounded border-l-4 border-green-400">
                                <a href="person_detail.php?person_code=<?= urlencode($child['person_code']) ?>" 
                                   class="text-green-700 hover:underline font-medium">
                                    <?= htmlspecialchars($child['name']) ?>
                                    <?php if (!empty($child['name_hanja'])): ?>
                                        (<?= htmlspecialchars($child['name_hanja']) ?>)
                                    <?php endif; ?>
                                </a>
                                <span class="text-sm text-gray-500 ml-2">
                                    <?= getGenderDisplay($child['gender']) ?>
                                    <?php if ($child['sibling_order']): ?>
                                        · <?= $child['sibling_order'] ?>번째
                                    <?php endif; ?>
                                    <?php if (!empty($child['birth_date']) && $child['birth_date'] !== '0000-00-00'): ?>
                                        · <?= substr($child['birth_date'], 0, 4) ?>년생
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 추가 정보 -->
            <?php if (!empty($person['home_address']) || !empty($person['work_address']) || !empty($person['biography'])): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mt-8">
                <h2 class="text-xl font-bold mb-4 text-gray-800">
                    <i class="fas fa-scroll mr-2 text-purple-500"></i>상세 정보
                </h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <?php if (!empty($person['home_address'])): ?>
                    <div>
                        <h3 class="font-medium text-gray-600 mb-2">거주지</h3>
                        <p class="text-gray-800"><?= htmlspecialchars(parseAddress($person['home_address'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($person['work_address'])): ?>
                    <div>
                        <h3 class="font-medium text-gray-600 mb-2">근무지</h3>
                        <p class="text-gray-800"><?= htmlspecialchars(parseAddress($person['work_address'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($person['biography'])): ?>
                    <div class="md:col-span-2">
                        <h3 class="font-medium text-gray-600 mb-2">약력</h3>
                        <p class="text-gray-800"><?= nl2br(htmlspecialchars($person['biography'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($person['biography_hanja'])): ?>
                    <div class="md:col-span-2">
                        <h3 class="font-medium text-gray-600 mb-2">한문 약력</h3>
                        <p class="text-gray-800 text-lg"><?= nl2br(htmlspecialchars($person['biography_hanja'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 편집 버튼 -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-8">
                <h2 class="text-xl font-bold mb-4 text-gray-800">
                    <i class="fas fa-tools mr-2 text-blue-500"></i>족보 관리
                </h2>
                <div class="flex flex-wrap gap-3">
                    <a href="genealogy_edit.php?person_code=<?= $person['person_code'] ?>" 
                       class="bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-edit mr-2"></i>족보 편집
                    </a>
                    
                    <a href="family_lineage.php?person_code=<?= $person['person_code'] ?>" 
                       class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition-colors">
                        <i class="fas fa-sitemap mr-2"></i>가계도 보기
                    </a>
                    
                    <a href="relationship_tree.php?target_person=<?= $person['person_code'] ?>" 
                       class="bg-purple-500 text-white px-6 py-3 rounded-lg hover:bg-purple-600 transition-colors">
                        <i class="fas fa-route mr-2"></i>나와의 관계
                    </a>
                    
                    <a href="search.php" 
                       class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-colors">
                        <i class="fas fa-search mr-2"></i>다른 인물 검색
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 푸터 -->
    <footer class="bg-gray-800 text-white py-6 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 창녕조씨 족보 시스템. 닥터조 개발.</p>
            <p class="text-sm text-gray-400 mt-2">가문의 역사를 디지털로 보존하고 전승하는 현대적 족보 시스템</p>
        </div>
    </footer>
</body>
</html>