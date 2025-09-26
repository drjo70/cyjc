<?php
// 창녕조씨 족보 시스템 - 검색 기능 (독립형 버전)
require_once 'config.php';
require_once 'access_logger.php';

// 인증된 구성원만 접근 가능
requireVerification();

// 접속 로그 기록
logPageAccess('/search.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// AJAX 요청 처리
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// 검색 변수들
$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$search_name = isset($_GET['name']) ? trim($_GET['name']) : '';
$search_generation = isset($_GET['generation']) ? trim($_GET['generation']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

// 실제 검색어 결정
$actual_query = !empty($search_name) ? $search_name : $search_query;

$search_results = [];
$search_error = '';
$total_count = 0;

// 데이터베이스 연결
$pdo = getDbConnection();

if ($pdo && !empty($actual_query)) {
    try {
        // 조건 구성
        $where_conditions = [];
        $params = [];
        
        // 이름 검색
        if (!empty($actual_query)) {
            $where_conditions[] = "(name LIKE ? OR name_hanja LIKE ? OR person_code LIKE ?)";
            $search_param = "%{$actual_query}%";
            $params[] = $search_param;
            $params[] = $search_param; 
            $params[] = $search_param;
        }
        
        // 세대 검색
        if (!empty($search_generation)) {
            $where_conditions[] = "generation = ?";
            $params[] = $search_generation;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // 총 개수 조회 (AJAX용)
        if ($is_ajax) {
            $count_stmt = $pdo->prepare("
                SELECT COUNT(*) as total
                FROM family_members 
                WHERE {$where_clause}
            ");
            $count_stmt->execute($params);
            $total_count = $count_stmt->fetch()['total'];
        }
        
        // 검색 실행
        $stmt = $pdo->prepare("
            SELECT id, name, name_hanja, generation, birth_date, death_date, 
                   person_code, parent_code, gender, sibling_order, child_count
            FROM family_members 
            WHERE {$where_clause}
            ORDER BY generation, name
            LIMIT {$limit}
        ");
        
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // 각 결과에 대해 가족 관계 정보 추가
        foreach ($results as $row) {
            // 부모 정보 조회
            $parent_info = [];
            if ($row['parent_code']) {
                $parent_stmt = $pdo->prepare("SELECT name, name_hanja, person_code FROM family_members WHERE person_code = ?");
                $parent_stmt->execute([$row['parent_code']]);
                $parent_data = $parent_stmt->fetch();
                if ($parent_data) {
                    $parent_info[] = $parent_data['name'] . ($parent_data['name_hanja'] ? " ({$parent_data['name_hanja']})" : "");
                }
            }

            // 자녀 정보 조회 (최대 5명까지)
            $children_info = [];
            if ($row['person_code']) {
                $children_stmt = $pdo->prepare("SELECT name, name_hanja FROM family_members WHERE parent_code = ? LIMIT 5");
                $children_stmt->execute([$row['person_code']]);
                while ($child = $children_stmt->fetch()) {
                    $children_info[] = $child['name'] . ($child['name_hanja'] ? " ({$child['name_hanja']})" : "");
                }
            }

            // 형제 정보 조회 (본인 제외, 최대 5명까지)
            $siblings_info = [];
            if ($row['parent_code']) {
                $siblings_stmt = $pdo->prepare("SELECT name, name_hanja FROM family_members WHERE parent_code = ? AND person_code != ? LIMIT 5");
                $siblings_stmt->execute([$row['parent_code'], $row['person_code']]);
                while ($sibling = $siblings_stmt->fetch()) {
                    $siblings_info[] = $sibling['name'] . ($sibling['name_hanja'] ? " ({$sibling['name_hanja']})" : "");
                }
            }

            $search_results[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'name_hanja' => $row['name_hanja'],
                'generation' => $row['generation'],
                'birth_date' => $row['birth_date'],
                'death_date' => $row['death_date'],
                'person_code' => $row['person_code'],
                'parent_code' => $row['parent_code'],
                'gender' => $row['gender'],
                'sibling_order' => $row['sibling_order'],
                'child_count' => $row['child_count'],
                // 가족 관계 정보
                'parent_names' => $parent_info,
                'children_names' => $children_info,
                'siblings_names' => $siblings_info,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

    } catch (PDOException $e) {
        $search_error = "검색 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// AJAX 요청인 경우 JSON 응답
if ($is_ajax) {
    header('Content-Type: application/json');
    
    if (!empty($search_error)) {
        echo json_encode([
            'success' => false,
            'error' => $search_error
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'results' => $search_results,
            'total' => $total_count,
            'query' => $actual_query
        ]);
    }
    exit;
}

function formatDate($date) {
    if (!$date) return null;
    return date('Y년', strtotime($date));
}

// 성별 표시 함수
function getGenderDisplay($gender) {
    return ($gender == 1) ? '남성' : (($gender == 2) ? '여성' : '미상');
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
    <title>인물 검색 | 창녕조씨 족보</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/static/mobile-optimized.css" rel="stylesheet">
    <style>
        /* 모바일 최적화 */
        @media (max-width: 768px) {
            .touch-target {
                min-height: 44px;
                min-width: 44px;
            }
            
            .mobile-card {
                margin-bottom: 1rem;
                padding: 1rem;
            }
            
            .mobile-btn {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
            }
            
            .mobile-scroll {
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'common_header.php'; ?>
                        <a href="index.php" class="text-gray-600 hover:text-gray-800">대시보드</a>
                        <a href="search.php" class="text-blue-600 font-semibold">인물검색</a>
                        <a href="generation.php" class="text-gray-600 hover:text-gray-800">세대별조회</a>
                        <a href="family_lineage.php" class="text-gray-600 hover:text-gray-800">내 가계도</a>
                    </nav>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="my_profile.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-user mr-1"></i>내 정보
                    </a>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>로그아웃
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8">
        <!-- 검색 폼 (모바일 최적화) -->
        <div class="bg-white rounded-lg shadow-sm p-4 md:p-6 mb-6 md:mb-8 mobile-card">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 md:mb-6 gap-3">
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">
                    <i class="fas fa-search text-blue-500 mr-2"></i>인물 검색
                </h1>
                <a href="index.php" class="text-blue-600 hover:text-blue-800 touch-target">
                    <i class="fas fa-home mr-1"></i>메인으로
                </a>
            </div>
            
            <form method="GET" class="space-y-3 md:space-y-4">
                <div class="flex flex-col gap-3 md:gap-4">
                    <div class="flex-1">
                        <input type="text" 
                               name="query" 
                               value="<?= htmlspecialchars($search_query) ?>"
                               placeholder="이름, 한자명 입력 (예: 영국, 趙英國)"
                               class="w-full px-3 md:px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-base touch-target">
                    </div>
                    <button type="submit" 
                            class="w-full sm:w-auto px-6 md:px-8 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors touch-target mobile-btn">
                        <i class="fas fa-search mr-2"></i>검색
                    </button>
                </div>
            </form>
        </div>

        <!-- 검색 결과 -->
        <?php if ($search_error): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-600 mr-3"></i>
                    <p class="text-red-800"><?= htmlspecialchars($search_error) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($search_query)): ?>
            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6 mobile-card">
                <div class="flex items-center justify-between mb-4 md:mb-6">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800">
                        검색 결과 
                        <?php if (!$search_error): ?>
                            <span class="text-sm font-normal text-gray-500">(총 <?= count($search_results) ?>건)</span>
                        <?php endif; ?>
                    </h2>
                </div>

                <?php if (empty($search_results) && !$search_error): ?>
                    <div class="text-center py-8 md:py-12">
                        <i class="fas fa-search text-3xl md:text-4xl text-gray-300 mb-3 md:mb-4"></i>
                        <p class="text-gray-500 text-base md:text-lg mb-2">검색 결과가 없습니다</p>
                        <p class="text-gray-400 text-sm">다른 검색어로 다시 시도해보세요</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4 md:space-y-6 mobile-scroll">
                        <?php foreach ($search_results as $person): ?>
                            <div class="border border-gray-200 rounded-lg p-4 md:p-6 hover:shadow-md transition-shadow">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
                                    
                                    <!-- 기본 정보 (모바일 최적화) -->
                                    <div>
                                        <h3 class="text-base md:text-lg font-bold text-gray-800 mb-2">
                                            <?= htmlspecialchars($person['name']) ?>
                                            <?php if ($person['name_hanja']): ?>
                                                <span class="text-gray-600 font-normal ml-2 text-sm md:text-base">(<?= htmlspecialchars($person['name_hanja']) ?>)</span>
                                            <?php endif; ?>
                                        </h3>
                                        
                                        <div class="space-y-1 text-xs md:text-sm text-gray-600 mb-3 md:mb-4">
                                            <p><i class="fas fa-sitemap w-4 mr-2"></i>
                                                <?= $person['generation'] ?>세 
                                                <?php if ($person['gender']): ?>
                                                    • <?= getGenderDisplay($person['gender']) ?>
                                                <?php endif; ?>
                                            </p>
                                            
                                            <?php 
                                            $birth_year = $person['birth_date'] ? formatDate($person['birth_date']) : null;
                                            $death_year = $person['death_date'] ? formatDate($person['death_date']) : null;
                                            ?>
                                            <?php if ($birth_year || $death_year): ?>
                                                <p><i class="fas fa-calendar w-4 mr-2"></i>
                                                    <?= $birth_year ?: '미상' ?>
                                                    <?php if ($death_year): ?>
                                                        ~ <?= $death_year ?>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <p class="text-xs text-gray-500">
                                                <i class="fas fa-barcode w-4 mr-2"></i>
                                                <?= htmlspecialchars($person['person_code']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- 가족 관계 -->
                                    <div>
                                        <h5 class="font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-users text-green-600 mr-1"></i>가족 관계
                                        </h5>
                                        <div class="space-y-1 text-sm text-gray-600">
                                            <?php if (!empty($person['parent_names'])): ?>
                                                <p><i class="fas fa-arrow-up text-blue-500 w-4 mr-2"></i>
                                                    부모: <?= htmlspecialchars(implode(', ', array_slice($person['parent_names'], 0, 2))) ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($person['children_names'])): ?>
                                                <p><i class="fas fa-arrow-down text-green-500 w-4 mr-2"></i>
                                                    자녀: <?= htmlspecialchars(implode(', ', array_slice($person['children_names'], 0, 3))) ?>
                                                    <?php if (count($person['children_names']) > 3): ?>
                                                        외 <?= count($person['children_names']) - 3 ?>명
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($person['siblings_names'])): ?>
                                                <p><i class="fas fa-arrows-alt-h text-purple-500 w-4 mr-2"></i>
                                                    형제: <?= htmlspecialchars(implode(', ', array_slice($person['siblings_names'], 0, 3))) ?>
                                                    <?php if (count($person['siblings_names']) > 3): ?>
                                                        외 <?= count($person['siblings_names']) - 3 ?>명
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- 액션 버튼 (모바일 최적화) -->
                                    <div class="flex flex-col justify-between">
                                        <div class="grid grid-cols-2 lg:grid-cols-1 gap-2">
                                            <a href="person_detail.php?person_code=<?= $person['person_code'] ?>" 
                                               class="block w-full text-center bg-blue-500 text-white py-3 px-3 md:px-4 rounded hover:bg-blue-600 transition-colors touch-target text-sm">
                                                <i class="fas fa-eye mr-1"></i><span class="hidden sm:inline">상세</span><span class="sm:hidden">상세</span>
                                            </a>
                                            <a href="family_lineage.php?person_code=<?= $person['person_code'] ?>" 
                                               class="block w-full text-center bg-green-500 text-white py-3 px-3 md:px-4 rounded hover:bg-green-600 transition-colors touch-target text-sm">
                                                <i class="fas fa-sitemap mr-1"></i><span class="hidden sm:inline">가계도</span><span class="sm:hidden">가계도</span>
                                            </a>
                                            <a href="relationship_tree.php?target_person=<?= $person['person_code'] ?>" 
                                               class="block w-full text-center bg-purple-500 text-white py-3 px-3 md:px-4 rounded hover:bg-purple-600 transition-colors touch-target text-sm">
                                                <i class="fas fa-route mr-1"></i><span class="hidden sm:inline">관계</span><span class="sm:hidden">관계</span>
                                            </a>
                                            <a href="genealogy_edit.php?person_code=<?= $person['person_code'] ?>" 
                                               class="block w-full text-center bg-orange-500 text-white py-3 px-3 md:px-4 rounded hover:bg-orange-600 transition-colors touch-target text-sm">
                                                <i class="fas fa-edit mr-1"></i><span class="hidden sm:inline">편집</span><span class="sm:hidden">편집</span>
                                            </a>
                                        </div>
                                        
                                        <div class="text-xs text-gray-400 mt-4">
                                            ID: <?= $person['id'] ?> 
                                            <?php if ($person['sibling_order']): ?>
                                                • <?= $person['sibling_order'] ?>번째
                                            <?php endif; ?>
                                            <?php if ($person['child_count']): ?>
                                                • 자녀 <?= $person['child_count'] ?>명
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 간단한 푸터 -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2025 창녕조씨 족보 시스템. All rights reserved.</p>
            <p class="text-sm text-gray-400 mt-2">개발: 닥터조님 ((주)조유)</p>
        </div>
    </footer>

</body>
</html>