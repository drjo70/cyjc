<?php
// 창녕조씨 족보 시스템 - 나와의 관계 족보 트리
session_start();
require_once 'config.php';

// 기본 인증 체크
if (!isLoggedIn() && !isVerifiedMember()) {
    header('Location: /');
    exit;
}

// 현재 로그인 사용자의 정확한 person_code 가져오기 (DB 기준)
$current_user_code = getCurrentUserPersonCode();

// 로그인하지 않았거나 person_code를 찾을 수 없으면 기본값
if (!$current_user_code) {
    $current_user_code = '441258'; // 테스트용 기본값
}

$target_person_code = $_GET['target_person'] ?? '';

if (empty($target_person_code)) {
    header('Location: search.php');
    exit;
}

// 데이터베이스 연결
$pdo = getDbConnection();
if (!$pdo) {
    die('데이터베이스 연결 오류');
}

// 인물 정보 조회 함수
function getPersonInfo($pdo, $person_code) {
    if (empty($person_code)) return null;
    
    $stmt = $pdo->prepare("
        SELECT person_code, name, name_hanja, gender, birth_date, death_date, is_deceased, parent_code
        FROM family_members 
        WHERE person_code = ?
    ");
    $stmt->execute([$person_code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 조상 경로 찾기 (공통 조상까지 올라가기)
function getAncestorPath($pdo, $person_code) {
    $path = [];
    $current_code = $person_code;
    
    // 최대 30세대까지 올라가기 (더 깊은 조상까지 추적)
    for ($i = 0; $i < 30 && !empty($current_code); $i++) {
        $person = getPersonInfo($pdo, $current_code);
        if (!$person) break;
        
        $path[] = $person;
        
        // 무한 루프 방지 (같은 코드가 반복되면 중단)
        if ($i > 0 && $path[$i-1]['person_code'] === $person['person_code']) {
            break;
        }
        
        $current_code = $person['parent_code'];
    }
    
    return $path;
}

// 공통 조상 찾기 (개선된 알고리즘)
function findCommonAncestor($path1, $path2) {
    // 방법 1: 두 경로를 뒤집어서 최고 조상부터 비교 (기존 방식)
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
    
    // 방법 2: 만약 위 방법으로 못 찾으면, 전체 경로에서 교집합 찾기 (백업 방법)
    if (!$common_ancestor) {
        foreach ($path1 as $person1) {
            foreach ($path2 as $person2) {
                if ($person1['person_code'] === $person2['person_code']) {
                    // 가장 가까운 공통조상 (두 경로에서 가장 앞쪽에 있는 것)
                    $distance1 = array_search($person1, $path1);
                    $distance2 = array_search($person2, $path2);
                    
                    if (!$common_ancestor || ($distance1 + $distance2) < 
                        (array_search($common_ancestor, $path1) + array_search($common_ancestor, $path2))) {
                        $common_ancestor = $person1;
                    }
                }
            }
        }
    }
    
    return $common_ancestor;
}

// 촌수 및 관계 계산
function calculateRelationship($current_path, $target_path, $common_ancestor) {
    if (!$common_ancestor) {
        return ['relationship' => '관계없음', 'chon' => 0, 'description' => '공통 조상을 찾을 수 없습니다.'];
    }
    
    // 공통 조상에서 각자까지의 거리 계산
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
    
    // 촌수 계산 (공통조상에서의 거리의 합)
    $chon = $current_distance + $target_distance;
    
    // 관계명 결정
    $relationship = '';
    $description = '';
    
    if ($chon === 0) {
        $relationship = '본인';
        $description = '동일 인물입니다.';
    } elseif ($current_distance === 0) {
        // 현재 사용자가 공통조상인 경우 (후손 관계)
        if ($target_distance === 1) {
            $relationship = '자녀';
        } elseif ($target_distance === 2) {
            $relationship = '손자/손녀';
        } elseif ($target_distance === 3) {
            $relationship = '증손자/증손녀';
        } else {
            $relationship = $target_distance . '대손';
        }
        $description = "나의 {$relationship}입니다.";
    } elseif ($target_distance === 0) {
        // 대상이 공통조상인 경우 (조상 관계)
        if ($current_distance === 1) {
            $relationship = '부모';
        } elseif ($current_distance === 2) {
            $relationship = '조부모';
        } elseif ($current_distance === 3) {
            $relationship = '증조부모';
        } else {
            $relationship = $current_distance . '대조';
        }
        $description = "나의 {$relationship}입니다.";
    } elseif ($current_distance === 1 && $target_distance === 1) {
        // 형제자매
        $relationship = '형제자매';
        $description = "나의 형제자매입니다. ({$chon}촌)";
    } elseif ($current_distance === 2 && $target_distance === 2) {
        // 사촌
        $relationship = '사촌';
        $description = "나의 사촌입니다. ({$chon}촌)";
    } elseif ($current_distance === 1 && $target_distance === 2) {
        // 조카
        $relationship = '조카';
        $description = "나의 조카입니다. ({$chon}촌)";
    } elseif ($current_distance === 2 && $target_distance === 1) {
        // 숙부/고모
        $relationship = '숙부/고모';
        $description = "나의 숙부/고모입니다. ({$chon}촌)";
    } else {
        // 기타 관계
        $relationship = $chon . '촌';
        $description = "공통조상: {$common_ancestor['name']}, 나에서 {$current_distance}대, 상대에서 {$target_distance}대";
    }
    
    return [
        'relationship' => $relationship,
        'chon' => $chon,
        'description' => $description,
        'current_distance' => $current_distance,
        'target_distance' => $target_distance
    ];
}

try {
    // 현재 사용자와 대상 인물 정보 조회
    $current_person = getPersonInfo($pdo, $current_user_code);
    $target_person = getPersonInfo($pdo, $target_person_code);
    
    if (!$current_person || !$target_person) {
        throw new Exception("인물 정보를 찾을 수 없습니다.");
    }
    
    // 조상 경로 추적
    $current_ancestor_path = getAncestorPath($pdo, $current_user_code);
    $target_ancestor_path = getAncestorPath($pdo, $target_person_code);
    
    // 공통 조상 찾기
    $common_ancestor = findCommonAncestor($current_ancestor_path, $target_ancestor_path);
    
    // 관계 계산
    $relationship_info = calculateRelationship($current_ancestor_path, $target_ancestor_path, $common_ancestor);
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// 나이 계산 함수
function calculateAge($birth_date) {
    if (empty($birth_date) || $birth_date === '0000-00-00') return '';
    try {
        $birth = new DateTime($birth_date);
        $today = new DateTime();
        $age = $today->diff($birth)->y;
        return $age . '세';
    } catch (Exception $e) {
        return '';
    }
}

// 성별 표시 함수
function getGenderDisplay($gender) {
    return ($gender == 1) ? '남' : (($gender == 2) ? '여' : '미상');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>나와의 관계 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .relationship-tree {
            min-height: 800px;
            position: relative;
        }
        
        .tree-path {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            padding: 40px 20px;
        }
        
        .person-node {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 20px;
            margin: 10px;
            border: 3px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 200px;
            text-align: center;
            position: relative;
        }
        
        .person-node:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            transform: translateY(-3px);
        }
        
        .person-node.current-user {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        }
        
        .person-node.target-person {
            border-color: #ec4899;
            background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
        }
        
        .person-node.common-ancestor {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
        }
        
        .person-node.ancestor {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
        }
        
        .connection-line {
            width: 4px;
            height: 40px;
            background: linear-gradient(to bottom, #6b7280, #9ca3af);
            margin: 0 auto;
            position: relative;
        }
        
        .connection-line::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 12px;
            height: 12px;
            background: #6b7280;
            border-radius: 50%;
        }
        
        .relationship-summary {
            position: sticky;
            top: 20px;
            z-index: 10;
        }
        
        .path-section {
            margin: 30px 0;
            padding: 20px;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            background: #f9fafb;
        }
        
        .tree-container {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 30px;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .genealogy-flow {
            display: flex;
            align-items: stretch;
            gap: 40px;
            min-height: 600px;
        }
        
        .genealogy-branch {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .branch-connector {
            position: absolute;
            top: 50%;
            right: -20px;
            width: 40px;
            height: 4px;
            background: linear-gradient(to right, #6b7280, #ec4899);
            transform: translateY(-50%);
        }
        
        .branch-connector::after {
            content: '';
            position: absolute;
            right: -8px;
            top: 50%;
            width: 0;
            height: 0;
            border-left: 8px solid #ec4899;
            border-top: 4px solid transparent;
            border-bottom: 4px solid transparent;
            transform: translateY(-50%);
        }
        
        @media (max-width: 1024px) {
            .tree-container {
                grid-template-columns: 1fr;
            }
            
            .genealogy-flow {
                flex-direction: column;
                gap: 20px;
            }
            
            .branch-connector {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php if (isset($error_message)): ?>
        <div class="container mx-auto px-4 py-8">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <h2 class="text-xl font-bold mb-2">오류 발생</h2>
                <p><?= htmlspecialchars($error_message) ?></p>
                <a href="search.php" class="text-blue-600 hover:underline">검색으로 돌아가기</a>
            </div>
        </div>
    <?php else: ?>
        <!-- 헤더 -->
        <header class="bg-gradient-to-r from-purple-600 to-pink-600 text-white py-6 shadow-lg">
            <div class="container mx-auto px-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">
                        <i class="fas fa-route mr-2"></i>
                        나와의 관계
                    </h1>
                    <div class="flex items-center gap-4">
                        <div class="text-sm">
                            <div class="mb-1">
                                <i class="fas fa-user mr-1"></i>
                                나: <strong><?= htmlspecialchars($current_person['name']) ?></strong>
                                <span class="text-xs text-blue-200">(<?= htmlspecialchars($current_user_code) ?>)</span>
                            </div>
                            <div>
                                <i class="fas fa-crosshairs mr-1"></i>
                                대상: <strong><?= htmlspecialchars($target_person['name']) ?></strong>
                                <span class="text-xs text-pink-200">(<?= htmlspecialchars($target_person_code) ?>)</span>
                            </div>
                        </div>
                        <a href="search.php" class="bg-white text-purple-600 px-4 py-2 rounded hover:bg-purple-50">
                            <i class="fas fa-search mr-1"></i> 검색으로
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- 메인 컨텐츠 -->
        <div class="container mx-auto px-4 py-8">
            <div class="tree-container">
                <!-- 족보 트리 -->
                <div class="relationship-tree bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-xl font-bold mb-6 text-center text-gray-800">
                        <i class="fas fa-sitemap mr-2 text-purple-600"></i>
                        족보 관계 경로
                    </h2>
                    
                    <div class="text-center mb-6 p-4 bg-gradient-to-r from-blue-50 to-pink-50 rounded-lg">
                        <div class="text-sm text-gray-600 mb-2">
                            <i class="fas fa-route mr-1"></i>
                            올바른 족보 구조: 공통조상 → 후손들
                        </div>
                        <div class="text-xs text-gray-500">
                            왼쪽: 나의 조상 계보 | 오른쪽: 상대방의 조상 계보
                        </div>
                    </div>
                    
                    <div class="genealogy-flow">
                        <!-- 나의 계보 (공통조상에서 나까지) -->
                        <div class="genealogy-branch">
                        <?php if (!empty($current_ancestor_path) && $common_ancestor): ?>
                            <div class="path-section">
                                <h3 class="text-center font-bold text-gray-700 mb-4">
                                    <i class="fas fa-arrow-down mr-1"></i>
                                    공통조상에서 <?= htmlspecialchars($current_person['name']) ?>님까지 (나의 계보)
                                </h3>
                                
                                <?php 
                                // 공통조상부터 현재 사용자까지의 경로 (위에서 아래로)
                                $current_downward_path = [];
                                $found_common = false;
                                
                                // 조상 경로를 뒤집어서 최고조상부터 시작
                                foreach (array_reverse($current_ancestor_path) as $ancestor) {
                                    if ($ancestor['person_code'] === $common_ancestor['person_code']) {
                                        $found_common = true;
                                    }
                                    if ($found_common) {
                                        $current_downward_path[] = $ancestor;
                                    }
                                }
                                ?>
                                
                                <?php foreach ($current_downward_path as $index => $person): ?>
                                    <?php 
                                    $node_class = '';
                                    $generation_from_common = $index;
                                    
                                    if ($person['person_code'] === $common_ancestor['person_code']) {
                                        $node_class = 'common-ancestor';
                                    } elseif ($person['person_code'] === $current_person['person_code']) {
                                        $node_class = 'current-user';
                                    } else {
                                        $node_class = 'ancestor';
                                    }
                                    ?>
                                    
                                    <div class="person-node <?= $node_class ?>" onclick="goToDetail('<?= $person['person_code'] ?>')">
                                        <div class="font-bold text-lg">
                                            <?php if ($person['person_code'] === $current_person['person_code']): ?>
                                                <i class="fas fa-user mr-1"></i>
                                            <?php elseif ($person['person_code'] === $common_ancestor['person_code']): ?>
                                                <i class="fas fa-crown mr-1"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($person['name']) ?>
                                        </div>
                                        <?php if (!empty($person['name_hanja'])): ?>
                                            <div class="text-sm text-gray-600"><?= htmlspecialchars($person['name_hanja']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-500 mt-2">
                                            <?php if ($person['person_code'] === $common_ancestor['person_code']): ?>
                                                <span class="text-orange-600 font-medium">공통조상</span>
                                            <?php elseif ($person['person_code'] === $current_person['person_code']): ?>
                                                <span class="text-blue-600 font-medium">나 (<?= getGenderDisplay($person['gender']) ?>)</span>
                                            <?php else: ?>
                                                <?php 
                                                $relation_names = [1 => '아버지/어머니', 2 => '조부모', 3 => '증조부모', 4 => '고조부모'];
                                                $relation = $relation_names[$generation_from_common] ?? ($generation_from_common . '대조');
                                                ?>
                                                <?= $relation ?> (<?= getGenderDisplay($person['gender']) ?>)
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($person['birth_date']) && $person['birth_date'] !== '0000-00-00'): ?>
                                            <div class="text-xs text-gray-500"><?= substr($person['birth_date'], 0, 4) ?>년생</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($index < count($current_downward_path) - 1): ?>
                                        <div class="connection-line"></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        </div>
                        
                        <!-- 연결선 -->
                        <div class="branch-connector"></div>
                        
                        <!-- 상대방의 계보 (공통조상에서 대상인물까지) -->
                        <div class="genealogy-branch">
                        <?php if (!empty($target_ancestor_path) && $common_ancestor): ?>
                            <div class="path-section">
                                <h3 class="text-center font-bold text-gray-700 mb-4">
                                    <i class="fas fa-arrow-down mr-1"></i>
                                    공통조상에서 <?= htmlspecialchars($target_person['name']) ?>님까지 (상대방의 계보)
                                </h3>
                                
                                <?php 
                                // 공통조상부터 대상까지의 경로 구성 (위에서 아래로)
                                $target_downward_path = [];
                                $found_common = false;
                                
                                // 조상 경로를 뒤집어서 최고조상부터 시작
                                foreach (array_reverse($target_ancestor_path) as $ancestor) {
                                    if ($ancestor['person_code'] === $common_ancestor['person_code']) {
                                        $found_common = true;
                                    }
                                    if ($found_common) {
                                        $target_downward_path[] = $ancestor;
                                    }
                                }
                                ?>
                                
                                <?php foreach ($target_downward_path as $index => $person): ?>
                                    <?php 
                                    $node_class = '';
                                    $generation_from_common = $index;
                                    
                                    if ($person['person_code'] === $common_ancestor['person_code']) {
                                        $node_class = 'common-ancestor';
                                    } elseif ($person['person_code'] === $target_person['person_code']) {
                                        $node_class = 'target-person';
                                    } else {
                                        $node_class = 'ancestor';
                                    }
                                    ?>
                                    
                                    <div class="person-node <?= $node_class ?>" onclick="goToDetail('<?= $person['person_code'] ?>')">
                                        <div class="font-bold text-lg">
                                            <?php if ($person['person_code'] === $target_person['person_code']): ?>
                                                <i class="fas fa-crosshairs mr-1"></i>
                                            <?php elseif ($person['person_code'] === $common_ancestor['person_code']): ?>
                                                <i class="fas fa-crown mr-1"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($person['name']) ?>
                                        </div>
                                        <?php if (!empty($person['name_hanja'])): ?>
                                            <div class="text-sm text-gray-600"><?= htmlspecialchars($person['name_hanja']) ?></div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-500 mt-2">
                                            <?php if ($person['person_code'] === $common_ancestor['person_code']): ?>
                                                <span class="text-orange-600 font-medium">공통조상</span>
                                            <?php elseif ($person['person_code'] === $target_person['person_code']): ?>
                                                <span class="text-pink-600 font-medium">대상인물 (<?= getGenderDisplay($person['gender']) ?>)</span>
                                            <?php else: ?>
                                                <?php 
                                                $relation_names = [1 => '아버지/어머니', 2 => '조부모', 3 => '증조부모', 4 => '고조부모'];
                                                $relation = $relation_names[$generation_from_common] ?? ($generation_from_common . '대조');
                                                ?>
                                                <?= $relation ?> (<?= getGenderDisplay($person['gender']) ?>)
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($person['birth_date']) && $person['birth_date'] !== '0000-00-00'): ?>
                                            <div class="text-xs text-gray-500"><?= substr($person['birth_date'], 0, 4) ?>년생</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($index < count($target_downward_path) - 1): ?>
                                        <div class="connection-line"></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 관계 요약 정보 -->
                <div class="relationship-summary">
                    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                        <h3 class="text-lg font-bold mb-4 text-gray-800">
                            <i class="fas fa-calculator mr-2 text-purple-600"></i>관계 분석
                        </h3>
                        
                        <div class="space-y-4">
                            <div class="text-center p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg">
                                <div class="text-2xl font-bold text-purple-700 mb-2">
                                    <?= htmlspecialchars($relationship_info['relationship']) ?>
                                </div>
                                <?php if ($relationship_info['chon'] > 0): ?>
                                    <div class="text-lg text-purple-600">
                                        <?= $relationship_info['chon'] ?>촌 관계
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-sm text-gray-600 text-center">
                                <?= htmlspecialchars($relationship_info['description']) ?>
                            </div>
                            
                            <?php if ($common_ancestor): ?>
                                <div class="border-t pt-4">
                                    <div class="text-sm font-medium text-gray-700 mb-2">공통조상</div>
                                    <div class="text-sm text-gray-600">
                                        <?= htmlspecialchars($common_ancestor['name']) ?>
                                        <?php if (!empty($common_ancestor['name_hanja'])): ?>
                                            (<?= htmlspecialchars($common_ancestor['name_hanja']) ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 text-xs text-gray-500 border-t pt-4">
                                    <div class="text-center">
                                        <div class="font-medium">나의 거리</div>
                                        <div><?= $relationship_info['current_distance'] ?>대</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="font-medium">상대의 거리</div>
                                        <div><?= $relationship_info['target_distance'] ?>대</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 액션 버튼 -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h3 class="text-lg font-bold mb-4 text-gray-800">
                            <i class="fas fa-tools mr-2 text-blue-600"></i>더 보기
                        </h3>
                        
                        <div class="space-y-3">
                            <a href="person_detail.php?person_code=<?= $target_person['person_code'] ?>" 
                               class="block w-full text-center bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 transition-colors">
                                <i class="fas fa-eye mr-1"></i>상세정보
                            </a>
                            
                            <a href="family_lineage.php?person_code=<?= $target_person['person_code'] ?>" 
                               class="block w-full text-center bg-green-500 text-white py-2 px-4 rounded hover:bg-green-600 transition-colors">
                                <i class="fas fa-sitemap mr-1"></i>가계도 보기
                            </a>
                            
                            <a href="search.php" 
                               class="block w-full text-center bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600 transition-colors">
                                <i class="fas fa-search mr-1"></i>다른 인물 검색
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 푸터 -->
    <footer class="bg-gray-800 text-white py-6 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 창녕조씨 족보 시스템. 나와의 관계 분석.</p>
            <p class="text-sm text-gray-400 mt-2">정확한 족보 관계를 시각적으로 보여주는 현대적 족보 시스템</p>
        </div>
    </footer>

    <script>
        // 상세 페이지로 이동
        function goToDetail(personCode) {
            if (personCode) {
                window.location.href = 'person_detail.php?person_code=' + encodeURIComponent(personCode);
            }
        }
        
        // 페이지 로드 시 애니메이션
        document.addEventListener('DOMContentLoaded', function() {
            console.log('나와의 관계 페이지 로드 완료');
            
            // 노드들을 순차적으로 나타나게 하는 애니메이션
            const nodes = document.querySelectorAll('.person-node');
            nodes.forEach((node, index) => {
                node.style.opacity = '0';
                node.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    node.style.transition = 'all 0.5s ease';
                    node.style.opacity = '1';
                    node.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>