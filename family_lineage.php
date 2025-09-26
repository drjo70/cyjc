<?php
// 창녕조씨 족보 시스템 - 직계 혈통 가계도
require_once 'config.php';
require_once 'access_logger.php';

// 기본 인증 체크
if (!isLoggedIn() && !isVerifiedMember()) {
    header('Location: /');
    exit;
}

// 접속 로그 기록
logPageAccess('/family_lineage.php');

// 로그인한 사용자의 person_code 가져오기
$current_user_code = $_SESSION['user_person_code'] ?? null;

// 테스트용: person_code 파라미터로 임시 설정 (실제로는 세션에서 가져와야 함)
if (!$current_user_code && isset($_GET['person_code'])) {
    $current_user_code = $_GET['person_code'];
}

// 기본값 설정 (개발용)
if (!$current_user_code) {
    $current_user_code = '441258'; // 테스트용 기본값
}

// 데이터베이스 연결
$pdo = getDbConnection();
if (!$pdo) {
    die('데이터베이스 연결 오류');
}

// 가족 관계 조회 함수들
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

// 형제자매 조회 함수
function getSiblings($pdo, $person_code, $parent_code) {
    if (empty($parent_code)) return [];
    
    $stmt = $pdo->prepare("
        SELECT person_code, name, name_hanja, gender, birth_date, death_date, is_deceased, sibling_order
        FROM family_members 
        WHERE parent_code = ? AND person_code != ?
        ORDER BY sibling_order, birth_date
    ");
    $stmt->execute([$parent_code, $person_code]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 삼촌/고모 (아버지의 형제자매) 조회 함수
function getUnclesAunts($pdo, $grandfather_code) {
    if (empty($grandfather_code)) return [];
    
    $stmt = $pdo->prepare("
        SELECT person_code, name, name_hanja, gender, birth_date, death_date, is_deceased, sibling_order
        FROM family_members 
        WHERE parent_code = ?
        ORDER BY sibling_order, birth_date
    ");
    $stmt->execute([$grandfather_code]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 4촌 형제 (삼촌/고모의 자녀들) 조회 함수
function getCousins($pdo, $uncles_aunts) {
    $cousins = [];
    foreach ($uncles_aunts as $uncle_aunt) {
        $children = getChildren($pdo, $uncle_aunt['person_code']);
        if (!empty($children)) {
            $cousins[$uncle_aunt['person_code']] = [
                'parent' => $uncle_aunt,
                'children' => $children
            ];
        }
    }
    return $cousins;
}

// 조상 조회 함수 (증조부모, 고조부모 등)
function getAncestors($pdo, $person_code, $generations = 2) {
    $ancestors = [];
    $current_code = $person_code;
    
    for ($i = 0; $i < $generations && !empty($current_code); $i++) {
        $person = getPersonInfo($pdo, $current_code);
        if (!$person || empty($person['parent_code'])) break;
        
        $parent_code = $person['parent_code'];
        $father = getPersonInfo($pdo, $parent_code);
        $mother = getPersonInfo($pdo, $parent_code . 'W');
        
        if ($father || $mother) {
            $ancestors[] = [
                'generation' => $i + 1,
                'father' => $father,
                'mother' => $mother
            ];
        }
        
        $current_code = $father ? $father['parent_code'] : null;
    }
    
    return array_reverse($ancestors); // 최고 조상부터 표시
}

function getSpouse($pdo, $person_code, $gender) {
    if (empty($person_code)) return null;
    
    // 배우자 코드 생성 (DB 패턴에 맞는 올바른 로직)
    if (preg_match('/H$/', $person_code)) {
        // H로 끝나는 남편 -> H를 제거한 기본코드가 아내
        $spouse_code = preg_replace('/H$/', '', $person_code);
    } elseif (preg_match('/W$/', $person_code)) {
        // W로 끝나는 아내 -> W를 제거한 기본코드가 남편
        $spouse_code = preg_replace('/W$/', '', $person_code);
    } else {
        // 기본코드 -> 성별에 따라 배우자 코드 생성
        if ($gender == 1) {
            $spouse_code = $person_code . 'W'; // 남성 → 아내
        } else {
            $spouse_code = $person_code . 'H'; // 여성 → 남편
        }
    }
    
    return getPersonInfo($pdo, $spouse_code);
}

function getChildren($pdo, $person_code) {
    if (empty($person_code)) return [];
    
    $stmt = $pdo->prepare("
        SELECT person_code, name, name_hanja, gender, birth_date, death_date, is_deceased, sibling_order
        FROM family_members 
        WHERE parent_code = ?
        ORDER BY sibling_order, birth_date
    ");
    $stmt->execute([$person_code]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 직계 혈통 데이터 수집
try {
    // 1. 본인 정보
    $current_person = getPersonInfo($pdo, $current_user_code);
    if (!$current_person) {
        throw new Exception("로그인한 사용자 정보를 찾을 수 없습니다.");
    }
    
    // 2. 조부모 (아버지의 부모)
    $grandparents = [];
    $grandfather = null;
    $grandmother = null;
    
    if (!empty($current_person['parent_code'])) {
        $father = getPersonInfo($pdo, $current_person['parent_code']);
        if ($father && !empty($father['parent_code'])) {
            $grandfather = getPersonInfo($pdo, $father['parent_code']);
            $grandmother = getPersonInfo($pdo, $father['parent_code'] . 'W');
        }
    }
    
    // 3. 부모
    $father = null;
    $mother = null;
    if (!empty($current_person['parent_code'])) {
        $father = getPersonInfo($pdo, $current_person['parent_code']);
        $mother = getPersonInfo($pdo, $current_person['parent_code'] . 'W');
    }
    
    // 4. 본인 배우자
    $spouse = getSpouse($pdo, $current_user_code, $current_person['gender']);
    
    // 5. 자녀들
    $children = getChildren($pdo, $current_user_code);
    
    // 6. 손자녀들 (각 자녀의 자녀들)
    $grandchildren = [];
    foreach ($children as $child) {
        $child_children = getChildren($pdo, $child['person_code']);
        if (!empty($child_children)) {
            $grandchildren[$child['person_code']] = $child_children;
        }
    }
    
    // 7. 형제자매 (본인과 같은 부모를 가진 사람들)
    $siblings = [];
    if (!empty($current_person['parent_code'])) {
        $siblings = getSiblings($pdo, $current_user_code, $current_person['parent_code']);
    }
    
    // 8. 삼촌/고모 (조부의 자녀들 중 아버지가 아닌 사람들)
    $uncles_aunts = [];
    if ($grandfather) {
        $uncles_aunts = getUnclesAunts($pdo, $grandfather['person_code']);
        // 현재 아버지 제외
        $uncles_aunts = array_filter($uncles_aunts, function($person) use ($father) {
            return !$father || $person['person_code'] !== $father['person_code'];
        });
    }
    
    // 9. 4촌 형제 (삼촌/고모의 자녀들)
    $cousins = getCousins($pdo, $uncles_aunts);
    
    // 10. 조상들 (증조부모, 고조부모 등)
    $ancestors = [];
    if ($grandfather) {
        $ancestors = getAncestors($pdo, $grandfather['person_code'], 3); // 3세대 더 위까지
    }
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// 성별 표시 함수
function getGenderDisplay($gender) {
    return ($gender == 1) ? '남' : (($gender == 2) ? '여' : '미상');
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
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#3b82f6">
    <title>직계 혈통 가계도 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/static/mobile-optimized.css" rel="stylesheet">
    <style>
        .family-tree {
            min-height: 1200px;
            position: relative;
            overflow-x: auto;
            width: 100%;
        }
        
        .tree-container {
            position: relative;
            min-width: 100%; /* 모바일: 100% 너비 */
            padding: 10px;
        }
        
        /* 데스크톱에서는 넓은 화면 활용 */
        @media (min-width: 1024px) {
            .tree-container {
                min-width: 1400px;
                padding: 20px;
            }
        }
        
        .ancestors-container {
            max-height: 400px;
            overflow-y: auto;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        }
        
        .scroll-hint {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .person-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 12px;
            margin: 8px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 150px;
            text-align: center;
        }
        
        .person-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .person-card.current-user {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        }
        
        .person-card.spouse {
            border-color: #ec4899;
            background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
        }
        
        .person-card.parent {
            border-color: #10b981;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        }
        
        .person-card.child {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
        }
        
        .person-card.grandparent {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
        }
        
        .person-card.grandchild {
            border-color: #ef4444;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        }
        
        .person-card.sibling {
            border-color: #06b6d4;
            background: linear-gradient(135deg, #cffafe 0%, #a5f3fc 100%);
        }
        
        .person-card.uncle-aunt {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        }
        
        .person-card.cousin {
            border-color: #f97316;
            background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
        }
        
        .person-card.ancestor {
            border-color: #64748b;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }
        
        .generation-label {
            position: absolute;
            left: 10px;
            font-weight: bold;
            color: #6b7280;
            font-size: 14px;
        }
        
        /* SVG 연결선 스타일 */
        .family-connections {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }
        
        .connection-line {
            stroke: #6b7280;
            stroke-width: 2;
            fill: none;
        }
        
        .connection-line.main-line {
            stroke: #2563eb;
            stroke-width: 3;
        }
        
        .connection-line.spouse-line {
            stroke: #ec4899;
            stroke-width: 2;
            stroke-dasharray: 5,5;
        }
        
        .connection-line.parent-line {
            stroke: #10b981;
            stroke-width: 2;
        }
        
        .connection-line.child-line {
            stroke: #f59e0b;
            stroke-width: 2;
        }
        
        .generation-row {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 30px 0;
            position: relative;
            min-height: 120px;
        }
        
        .wide-generation-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            justify-items: center;
            margin: 30px 0;
            position: relative;
            min-height: 120px;
        }
        
        .extended-family {
            display: flex;
            justify-content: space-around;
            align-items: flex-start;
            margin: 30px 0;
            position: relative;
            flex-wrap: wrap;
            gap: 30px;
        }
        
        .family-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            min-width: 200px;
        }
        
        .group-label {
            font-weight: bold;
            color: #374151;
            font-size: 14px;
            border-bottom: 2px solid #d1d5db;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        
        .couple {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .siblings {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        /* 모바일 최적화 */
        @media (max-width: 768px) {
            .family-tree {
                min-height: auto;
                overflow-x: visible;
                padding: 0.5rem;
            }
            
            .tree-container {
                min-width: 100%;
                padding: 0.5rem;
            }
            
            .person-card {
                min-width: 120px;
                padding: 8px;
                margin: 4px;
                font-size: 0.875rem;
                touch-action: manipulation;
            }
            
            .person-card:active {
                transform: scale(0.98);
                opacity: 0.8;
            }
            
            .generation-row {
                flex-direction: column;
                gap: 10px;
                margin: 20px 0;
                min-height: auto;
            }
            
            .wide-generation-row {
                grid-template-columns: 1fr;
                gap: 10px;
                margin: 20px 0;
            }
            
            .extended-family {
                flex-direction: column;
                gap: 20px;
                margin: 20px 0;
            }
            
            .family-group {
                min-width: 100%;
                align-items: stretch;
            }
            
            .couple {
                flex-direction: column;
                gap: 10px;
            }
            
            .siblings {
                gap: 8px;
                justify-content: center;
            }
            
            .ancestors-container {
                max-height: 300px;
                padding: 10px;
                margin-bottom: 20px;
            }
            
            .generation-label {
                position: relative;
                left: 0;
                text-align: center;
                margin-bottom: 10px;
                width: 100%;
                background: #f3f4f6;
                padding: 8px;
                border-radius: 6px;
            }
            
            /* 모바일에서 SVG 연결선 숨기기 (복잡함) */
            .family-connections {
                display: none;
            }
            
            /* 헤더 모바일 최적화 */
            .container.mx-auto .flex.items-center.justify-between {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            h1 {
                font-size: 1.5rem !important;
            }
        }
        
        /* 소형 모바일 (iPhone SE 등) */
        @media (max-width: 375px) {
            .person-card {
                min-width: 100px;
                padding: 6px;
                font-size: 0.75rem;
            }
            
            .siblings {
                gap: 6px;
            }
            
            .family-tree {
                padding: 0.25rem;
            }
        }
        
        /* 터치 타겟 최적화 */
        .person-card {
            min-height: 44px;
            touch-action: manipulation;
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
        }
        
        /* 스크롤 최적화 */
        .ancestors-container {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php if (isset($error_message)): ?>
        <div class="container mx-auto px-4 py-8">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <h2 class="text-xl font-bold mb-2">오류 발생</h2>
                <p><?= htmlspecialchars($error_message) ?></p>
                <a href="index.php" class="text-blue-600 hover:underline">메인으로 돌아가기</a>
            </div>
        </div>
    <?php else: ?>
        <!-- 헤더 (모바일 최적화) -->
        <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-4 md:py-6 shadow-lg">
            <div class="container mx-auto px-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <h1 class="text-lg md:text-2xl font-bold text-center md:text-left">
                        <i class="fas fa-sitemap mr-2"></i>
                        직계 혈통 가계도
                    </h1>
                    <div class="flex flex-col sm:flex-row items-center gap-2 md:gap-4">
                        <span class="text-xs md:text-sm text-center">
                            <i class="fas fa-user mr-1"></i>
                            <?= htmlspecialchars($current_person['name']) ?>님 중심
                        </span>
                        
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="index.php" class="bg-white text-blue-600 px-3 md:px-4 py-2 rounded hover:bg-blue-50 text-sm touch-target">
                                <i class="fas fa-home mr-1"></i> 메인으로
                            </a>
                            
                            <!-- 관리자 메뉴 (Level 1만 표시) -->
                            <?php if (isset($_SESSION['access_level']) && $_SESSION['access_level'] == 1): ?>
                                <a href="admin.php" class="bg-yellow-500 text-white px-3 md:px-4 py-2 rounded hover:bg-yellow-600 text-sm touch-target border border-yellow-300">
                                    <i class="fas fa-crown mr-1"></i> 관리자
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- 가계도 컨테이너 (모바일 최적화) -->
        <div class="container mx-auto px-2 md:px-4 py-4 md:py-8">
            <div class="family-tree bg-white rounded-lg shadow-lg p-2 md:p-8">
                <div class="tree-container">
                    <!-- SVG 연결선 컨테이너 -->
                    <svg class="family-connections" id="connectionsSVG">
                        <!-- 연결선들이 여기에 동적으로 추가됨 -->
                    </svg>
                
                    <!-- 조상들 (스크롤 가능 - 모바일 최적화) -->
                    <?php if (!empty($ancestors)): ?>
                    <div class="ancestors-container">
                        <div class="scroll-hint text-center">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span class="hidden md:inline">위쪽 조상들 (스크롤 가능)</span>
                            <span class="md:hidden">조상들</span>
                        </div>
                        <?php foreach ($ancestors as $index => $ancestor_gen): ?>
                        <div class="generation-row" style="margin-bottom: 40px;">
                            <div class="generation-label" style="top: 40px;">
                                <?php 
                                $gen_names = [1 => '증조부모', 2 => '고조부모', 3 => '상조부모'];
                                echo $gen_names[$ancestor_gen['generation']] ?? ($ancestor_gen['generation'] . '대 조상');
                                ?>
                            </div>
                            <div class="couple">
                                <?php if ($ancestor_gen['father']): ?>
                                <div class="person-card ancestor" 
                                     onclick="goToDetail('<?= $ancestor_gen['father']['person_code'] ?>')">
                                    <div class="font-bold text-slate-700"><?= htmlspecialchars($ancestor_gen['father']['name']) ?></div>
                                    <?php if (!empty($ancestor_gen['father']['name_hanja'])): ?>
                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($ancestor_gen['father']['name_hanja']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500"><?= $gen_names[$ancestor_gen['generation']] ?? ($ancestor_gen['generation'] . '대') ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($ancestor_gen['mother']): ?>
                                <div class="person-card ancestor" 
                                     onclick="goToDetail('<?= $ancestor_gen['mother']['person_code'] ?>')">
                                    <div class="font-bold text-slate-700"><?= htmlspecialchars($ancestor_gen['mother']['name']) ?></div>
                                    <?php if (!empty($ancestor_gen['mother']['name_hanja'])): ?>
                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($ancestor_gen['mother']['name_hanja']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500"><?= $gen_names[$ancestor_gen['generation']] ?? ($ancestor_gen['generation'] . '대') ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                
                    <!-- 조부모 세대 -->
                    <?php if ($grandfather || $grandmother): ?>
                    <div class="generation-row" id="grandparents-row">
                        <div class="generation-label" style="top: 40px;">조부모</div>
                        <div class="couple">
                            <?php if ($grandfather): ?>
                            <div class="person-card grandparent" id="grandfather-<?= $grandfather['person_code'] ?>" 
                                 onclick="goToDetail('<?= $grandfather['person_code'] ?>')">
                                <div class="font-bold text-purple-700"><?= htmlspecialchars($grandfather['name']) ?></div>
                                <?php if (!empty($grandfather['name_hanja'])): ?>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($grandfather['name_hanja']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500">조부 (<?= getGenderDisplay($grandfather['gender']) ?>)</div>
                                <?php if (!empty($grandfather['birth_date']) && $grandfather['birth_date'] !== '0000-00-00'): ?>
                                    <div class="text-xs text-gray-500"><?= substr($grandfather['birth_date'], 0, 4) ?>년생</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($grandmother): ?>
                            <div class="person-card grandparent" id="grandmother-<?= $grandmother['person_code'] ?>" 
                                 onclick="goToDetail('<?= $grandmother['person_code'] ?>')">
                                <div class="font-bold text-purple-700"><?= htmlspecialchars($grandmother['name']) ?></div>
                                <?php if (!empty($grandmother['name_hanja'])): ?>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($grandmother['name_hanja']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500">조모 (<?= getGenderDisplay($grandmother['gender']) ?>)</div>
                                <?php if (!empty($grandmother['birth_date']) && $grandmother['birth_date'] !== '0000-00-00'): ?>
                                    <div class="text-xs text-gray-500"><?= substr($grandmother['birth_date'], 0, 4) ?>년생</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 삼촌/고모 세대 (넓은 화면 활용) -->
                    <?php if (!empty($uncles_aunts)): ?>
                    <div class="wide-generation-row" id="uncles-aunts-row">
                        <div class="generation-label" style="top: 10px;">삼촌/고모</div>
                        <?php foreach ($uncles_aunts as $uncle_aunt): ?>
                        <div class="person-card uncle-aunt" id="uncle-aunt-<?= $uncle_aunt['person_code'] ?>" 
                             onclick="goToDetail('<?= $uncle_aunt['person_code'] ?>')">
                            <div class="font-bold text-violet-700"><?= htmlspecialchars($uncle_aunt['name']) ?></div>
                            <?php if (!empty($uncle_aunt['name_hanja'])): ?>
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($uncle_aunt['name_hanja']) ?></div>
                            <?php endif; ?>
                            <div class="text-xs text-gray-500">
                                <?= ($uncle_aunt['gender'] == 1) ? '삼촌' : '고모' ?> (<?= getGenderDisplay($uncle_aunt['gender']) ?>)
                            </div>
                            <?php if (!empty($uncle_aunt['birth_date']) && $uncle_aunt['birth_date'] !== '0000-00-00'): ?>
                                <div class="text-xs text-gray-500"><?= substr($uncle_aunt['birth_date'], 0, 4) ?>년생</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- 부모 세대 (모바일 최적화) -->
                    <?php if ($father || $mother): ?>
                    <div class="generation-row" id="parents-row">
                        <div class="generation-label">부모</div>
                        <div class="couple">
                            <?php if ($father): ?>
                            <div class="person-card parent" id="father-<?= $father['person_code'] ?>" 
                                 onclick="goToDetail('<?= $father['person_code'] ?>')">
                                <div class="font-bold text-green-700"><?= htmlspecialchars($father['name']) ?></div>
                                <?php if (!empty($father['name_hanja'])): ?>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($father['name_hanja']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500">아버지 (<?= getGenderDisplay($father['gender']) ?>)</div>
                                <?php if (!empty($father['birth_date']) && $father['birth_date'] !== '0000-00-00'): ?>
                                    <div class="text-xs text-gray-500"><?= substr($father['birth_date'], 0, 4) ?>년생</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($mother): ?>
                            <div class="person-card parent" id="mother-<?= $mother['person_code'] ?>" 
                                 onclick="goToDetail('<?= $mother['person_code'] ?>')">
                                <div class="font-bold text-green-700"><?= htmlspecialchars($mother['name']) ?></div>
                                <?php if (!empty($mother['name_hanja'])): ?>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($mother['name_hanja']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500">어머니 (<?= getGenderDisplay($mother['gender']) ?>)</div>
                                <?php if (!empty($mother['birth_date']) && $mother['birth_date'] !== '0000-00-00'): ?>
                                    <div class="text-xs text-gray-500"><?= substr($mother['birth_date'], 0, 4) ?>년생</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 형제자매 세대 (넓은 화면 활용) -->
                    <?php if (!empty($siblings)): ?>
                    <div class="extended-family" id="siblings-row">
                        <div class="family-group">
                            <div class="group-label">형제자매</div>
                            <div class="siblings">
                                <?php foreach ($siblings as $sibling): ?>
                                <div class="person-card sibling" id="sibling-<?= $sibling['person_code'] ?>" 
                                     onclick="goToDetail('<?= $sibling['person_code'] ?>')">
                                    <div class="font-bold text-cyan-700"><?= htmlspecialchars($sibling['name']) ?></div>
                                    <?php if (!empty($sibling['name_hanja'])): ?>
                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($sibling['name_hanja']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500">
                                        <?php 
                                        if ($sibling['gender'] == 1) {
                                            echo ($sibling['sibling_order'] ?? 0) < ($current_person['sibling_order'] ?? 1) ? '형' : '동생';
                                        } else {
                                            echo ($sibling['sibling_order'] ?? 0) < ($current_person['sibling_order'] ?? 1) ? '누나' : '여동생';
                                        }
                                        ?>
                                        (<?= getGenderDisplay($sibling['gender']) ?>)
                                    </div>
                                    <?php if (!empty($sibling['birth_date']) && $sibling['birth_date'] !== '0000-00-00'): ?>
                                        <div class="text-xs text-gray-500"><?= substr($sibling['birth_date'], 0, 4) ?>년생</div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                
                    <!-- 본인 & 배우자 세대 (모바일 최적화) -->
                    <div class="generation-row" id="current-user-row">
                        <div class="generation-label">본인</div>
                        <div class="couple">
                            <!-- 본인 -->
                            <div class="person-card current-user" id="current-user-<?= $current_person['person_code'] ?>" 
                                 onclick="goToDetail('<?= $current_person['person_code'] ?>')">
                                <div class="font-bold text-blue-700 text-lg">
                                    <i class="fas fa-crown mr-1"></i>
                                    <?= htmlspecialchars($current_person['name']) ?>
                                </div>
                                <?php if (!empty($current_person['name_hanja'])): ?>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($current_person['name_hanja']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-blue-600 font-medium">본인 (<?= getGenderDisplay($current_person['gender']) ?>)</div>
                                <?php if (!empty($current_person['birth_date']) && $current_person['birth_date'] !== '0000-00-00'): ?>
                                    <div class="text-xs text-gray-500"><?= substr($current_person['birth_date'], 0, 4) ?>년생 (<?= calculateAge($current_person['birth_date']) ?>)</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- 배우자 -->
                            <?php if ($spouse): ?>
                            <div class="person-card spouse" id="spouse-<?= $spouse['person_code'] ?>" 
                                 onclick="goToDetail('<?= $spouse['person_code'] ?>')">
                                <div class="font-bold text-pink-700"><?= htmlspecialchars($spouse['name']) ?></div>
                                <?php if (!empty($spouse['name_hanja'])): ?>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($spouse['name_hanja']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500">배우자 (<?= getGenderDisplay($spouse['gender']) ?>)</div>
                                <?php if (!empty($spouse['birth_date']) && $spouse['birth_date'] !== '0000-00-00'): ?>
                                    <div class="text-xs text-gray-500"><?= substr($spouse['birth_date'], 0, 4) ?>년생</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 4촌 형제 세대 (넓은 화면 활용) -->
                    <?php if (!empty($cousins)): ?>
                    <div class="extended-family" id="cousins-row">
                        <?php foreach ($cousins as $parent_code => $cousin_data): ?>
                        <div class="family-group">
                            <div class="group-label">
                                <?= htmlspecialchars($cousin_data['parent']['name']) ?> 의 자녀 (4촌)
                            </div>
                            <div class="siblings">
                                <?php foreach ($cousin_data['children'] as $cousin): ?>
                                <div class="person-card cousin" id="cousin-<?= $cousin['person_code'] ?>" 
                                     onclick="goToDetail('<?= $cousin['person_code'] ?>')">
                                    <div class="font-bold text-orange-700"><?= htmlspecialchars($cousin['name']) ?></div>
                                    <?php if (!empty($cousin['name_hanja'])): ?>
                                        <div class="text-sm text-gray-600"><?= htmlspecialchars($cousin['name_hanja']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500">
                                        4촌 <?= ($cousin['gender'] == 1) ? '형제' : '자매' ?> (<?= getGenderDisplay($cousin['gender']) ?>)
                                    </div>
                                    <?php if (!empty($cousin['birth_date']) && $cousin['birth_date'] !== '0000-00-00'): ?>
                                        <div class="text-xs text-gray-500"><?= substr($cousin['birth_date'], 0, 4) ?>년생</div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                <!-- 자녀 세대 (모바일 최적화) -->
                <?php if (!empty($children)): ?>
                <div class="generation-row">
                    <div class="generation-label">자녀</div>
                    <div class="siblings">
                        <?php foreach ($children as $child): ?>
                        <div class="person-card child" onclick="goToDetail('<?= $child['person_code'] ?>')">
                            <div class="font-bold text-yellow-700"><?= htmlspecialchars($child['name']) ?></div>
                            <?php if (!empty($child['name_hanja'])): ?>
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($child['name_hanja']) ?></div>
                            <?php endif; ?>
                            <div class="text-xs text-gray-500">
                                <?= ($child['gender'] == 1) ? '아들' : '딸' ?> (<?= getGenderDisplay($child['gender']) ?>)
                                <?php if (!empty($child['sibling_order'])): ?>
                                    · <?= $child['sibling_order'] ?>째
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($child['birth_date']) && $child['birth_date'] !== '0000-00-00'): ?>
                                <div class="text-xs text-gray-500"><?= substr($child['birth_date'], 0, 4) ?>년생</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 손자녀 세대 (모바일 최적화) -->
                <?php if (!empty($grandchildren)): ?>
                <div class="generation-row">
                    <div class="generation-label">손자녀</div>
                    <div class="siblings">
                        <?php foreach ($grandchildren as $parent_code => $grandchild_list): ?>
                            <?php foreach ($grandchild_list as $grandchild): ?>
                            <div class="person-card grandchild" onclick="goToDetail('<?= $grandchild['person_code'] ?>')">
                                <div class="font-bold text-red-700"><?= htmlspecialchars($grandchild['name']) ?></div>
                                <?php if (!empty($grandchild['name_hanja'])): ?>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($grandchild['name_hanja']) ?></div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500">
                                    <?= ($grandchild['gender'] == 1) ? '손자' : '손녀' ?> (<?= getGenderDisplay($grandchild['gender']) ?>)
                                </div>
                                <?php if (!empty($grandchild['birth_date']) && $grandchild['birth_date'] !== '0000-00-00'): ?>
                                    <div class="text-xs text-gray-500"><?= substr($grandchild['birth_date'], 0, 4) ?>년생</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- 범례 -->
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold mb-4 text-gray-800">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>범례
                </h3>
                <div class="grid md:grid-cols-4 lg:grid-cols-8 gap-4">
                    <div class="flex items-center">
                        <div class="person-card current-user w-4 h-4 mr-2"></div>
                        <span class="text-sm">본인</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card spouse w-4 h-4 mr-2"></div>
                        <span class="text-sm">배우자</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card parent w-4 h-4 mr-2"></div>
                        <span class="text-sm">부모</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card child w-4 h-4 mr-2"></div>
                        <span class="text-sm">자녀</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card grandparent w-4 h-4 mr-2"></div>
                        <span class="text-sm">조부모</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card grandchild w-4 h-4 mr-2"></div>
                        <span class="text-sm">손자녀</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card sibling w-4 h-4 mr-2"></div>
                        <span class="text-sm">형제자매</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card uncle-aunt w-4 h-4 mr-2"></div>
                        <span class="text-sm">삼촌/고모</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card cousin w-4 h-4 mr-2"></div>
                        <span class="text-sm">4촌 형제</span>
                    </div>
                    <div class="flex items-center">
                        <div class="person-card ancestor w-4 h-4 mr-2"></div>
                        <span class="text-sm">조상</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 푸터 -->
    <!-- 가계도 통계 -->
    <div class="container mx-auto px-4 py-6">
        <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-bold mb-4 text-gray-800">
                <i class="fas fa-chart-pie mr-2 text-blue-500"></i>가족 현황 통계
            </h3>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 text-center">
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <div class="text-2xl font-bold text-blue-600"><?= count($ancestors) ?></div>
                    <div class="text-sm text-gray-600">조상 세대</div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <div class="text-2xl font-bold text-green-600"><?= count($siblings) ?></div>
                    <div class="text-sm text-gray-600">형제자매</div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <div class="text-2xl font-bold text-orange-600"><?= count($cousins) ?></div>
                    <div class="text-sm text-gray-600">4촌 가족</div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <div class="text-2xl font-bold text-red-600"><?= count($children) + array_sum(array_map('count', $grandchildren)) ?></div>
                    <div class="text-sm text-gray-600">후손</div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-gray-800 text-white py-6 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 창녕조씨 족보 시스템. 확장된 가족 가계도.</p>
            <p class="text-sm text-gray-400 mt-2">직계 혈통부터 4촌 형제까지, 완전한 가족 관계 시각화</p>
        </div>
    </footer>

    <script>
        // 상세 페이지로 이동
        function goToDetail(personCode) {
            if (personCode) {
                window.location.href = 'person_detail.php?person_code=' + encodeURIComponent(personCode);
            }
        }
        
        // SVG 연결선 그리기 함수
        function drawFamilyConnections() {
            const svg = document.getElementById('connectionsSVG');
            if (!svg) return;
            
            // SVG 컨테이너 크기 설정
            const container = document.querySelector('.tree-container');
            svg.setAttribute('width', container.offsetWidth);
            svg.setAttribute('height', container.offsetHeight);
            
            // 기존 연결선 제거
            svg.innerHTML = '';
            
            // 연결선 그리기 함수
            function getElementCenter(elementId) {
                const element = document.getElementById(elementId);
                if (!element) return null;
                
                const rect = element.getBoundingClientRect();
                const containerRect = container.getBoundingClientRect();
                
                return {
                    x: rect.left - containerRect.left + rect.width / 2,
                    y: rect.top - containerRect.top + rect.height / 2
                };
            }
            
            function drawLine(fromId, toId, className = 'connection-line') {
                const from = getElementCenter(fromId);
                const to = getElementCenter(toId);
                
                if (!from || !to) return;
                
                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', from.x);
                line.setAttribute('y1', from.y);
                line.setAttribute('x2', to.x);
                line.setAttribute('y2', to.y);
                line.setAttribute('class', className);
                svg.appendChild(line);
            }
            
            function drawCurvedLine(fromId, toId, className = 'connection-line') {
                const from = getElementCenter(fromId);
                const to = getElementCenter(toId);
                
                if (!from || !to) return;
                
                // 곡선 경로 생성 (부모-자식 연결에 사용)
                const midY = from.y + (to.y - from.y) / 2;
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                const d = `M ${from.x} ${from.y} Q ${from.x} ${midY} ${to.x} ${to.y}`;
                path.setAttribute('d', d);
                path.setAttribute('class', className);
                svg.appendChild(path);
            }
            
            // 주요 연결선 그리기 (닥터조님 요청사항)
            <?php if ($grandfather && $father): ?>
            drawLine('grandfather-<?= $grandfather['person_code'] ?>', 'father-<?= $father['person_code'] ?>', 'connection-line parent-line');
            <?php endif; ?>
            
            <?php if ($father && $current_person): ?>
            drawLine('father-<?= $father['person_code'] ?>', 'current-user-<?= $current_person['person_code'] ?>', 'connection-line main-line');
            <?php endif; ?>
            
            <?php if ($father && $mother): ?>
            drawLine('father-<?= $father['person_code'] ?>', 'mother-<?= $mother['person_code'] ?>', 'connection-line spouse-line');
            <?php endif; ?>
            
            <?php if ($current_person && $spouse): ?>
            drawLine('current-user-<?= $current_person['person_code'] ?>', 'spouse-<?= $spouse['person_code'] ?>', 'connection-line spouse-line');
            <?php endif; ?>
            
            // 자녀 연결선
            <?php if (!empty($children)): ?>
            <?php foreach ($children as $child): ?>
            drawCurvedLine('current-user-<?= $current_person['person_code'] ?>', 'child-<?= $child['person_code'] ?>', 'connection-line child-line');
            <?php endforeach; ?>
            <?php endif; ?>
            
            // 형제자매 연결선 (부모로부터)
            <?php if (!empty($siblings) && $father): ?>
            <?php foreach ($siblings as $sibling): ?>
            drawCurvedLine('father-<?= $father['person_code'] ?>', 'sibling-<?= $sibling['person_code'] ?>', 'connection-line parent-line');
            <?php endforeach; ?>
            <?php endif; ?>
        }
        
        // 페이지 로드 및 리사이즈 이벤트
        document.addEventListener('DOMContentLoaded', function() {
            console.log('확장된 가족 가계도 로드 완료');
            
            // 초기 연결선 그리기
            setTimeout(drawFamilyConnections, 100);
            
            // 창 크기 리사이즈 시 연결선 다시 그리기
            window.addEventListener('resize', function() {
                setTimeout(drawFamilyConnections, 100);
            });
            
            // 스크롤 누르기 기능
            const ancestorsContainer = document.querySelector('.ancestors-container');
            if (ancestorsContainer) {
                ancestorsContainer.addEventListener('scroll', function() {
                    console.log('조상 세대 스크롤 중...');
                });
            }
        });
    </script>
</body>
</html>