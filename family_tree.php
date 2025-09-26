<?php
// 창녕조씨 족보 시스템 - 가계도
require_once 'config.php';

// 인증된 구성원만 접근 가능
requireVerification();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 데이터베이스 연결 설정 (Cafe24 환경)
$db_config = [
    'host' => 'localhost',
    'dbname' => 'cyjc25',
    'username' => 'cyjc25',
    'password' => 'whdudrnr!!70'
];

$use_database = true;
if (!extension_loaded('pdo_mysql')) {
    $use_database = false;
    $error_message = "MySQL PDO 확장이 설치되지 않았습니다.";
}

if ($use_database) {
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
            $db_config['username'],
            $db_config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 5
            ]
        );
    } catch (PDOException $e) {
        $use_database = false;
        $error_message = "데이터베이스 연결에 실패했습니다.";
        if (ini_get('display_errors')) {
            $error_message .= " 오류: " . $e->getMessage();
        }
        $pdo = null;
    }
} else {
    $pdo = null;
}

// 매개변수 처리
$focus_person_id = (int)($_GET['focus'] ?? 0);
$tree_type = $_GET['type'] ?? 'vertical'; // vertical, horizontal, compact
$generation_start = (int)($_GET['gen_start'] ?? 1);
$generation_end = (int)($_GET['gen_end'] ?? 5);

// 가계도 데이터 가져오기
$tree_data = [];
$focus_person = null;

if (isset($pdo) && $pdo !== null) {
    try {
        // 포커스 인물 정보
        if ($focus_person_id > 0) {
            $focus_stmt = $pdo->prepare("SELECT id, name, name_hanja, generation, birth_date, death_date FROM family_members WHERE id = ?");
            $focus_stmt->execute([$focus_person_id]);
            $db_focus = $focus_stmt->fetch();
            if ($db_focus) {
                $focus_person = [
                    'id' => $db_focus['id'],
                    'name' => $db_focus['name'],
                    'name_hanja' => $db_focus['name_hanja'] ?? '',
                    'generation' => $db_focus['generation'],
                    'birth_year' => $db_focus['birth_date'] ? date('Y', strtotime($db_focus['birth_date'])) : null
                ];
            }
        }

        // 가계도 데이터 (세대별로 구성)
        $tree_stmt = $pdo->prepare("
            SELECT id, name, name_hanja, generation, birth_date, death_date, 
                   person_code, parent_code, gender, sibling_order, child_count
            FROM family_members 
            WHERE generation BETWEEN ? AND ?
            ORDER BY generation, name
        ");
        $tree_stmt->execute([$generation_start, $generation_end]);
        $db_all_persons = $tree_stmt->fetchAll();
        
        // 데이터 형식 변환
        $all_persons = [];
        foreach ($db_all_persons as $row) {
            $all_persons[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'name_hanja' => $row['name_hanja'] ?? '',
                'generation' => $row['generation'],
                'birth_year' => $row['birth_date'] ? date('Y', strtotime($row['birth_date'])) : null,
                'death_year' => $row['death_date'] ? date('Y', strtotime($row['death_date'])) : null,
                'person_code' => $row['person_code'],
                'parent_code' => $row['parent_code'],
                'gender' => $row['gender'],
                'occupation' => '',
                'father_name' => '',
                'spouse_name' => ''
            ];
        }

        // 세대별로 그룹화
        foreach ($all_persons as $person) {
            $tree_data[$person['generation']][] = $person;
        }

    } catch (PDOException $e) {
        $db_error = "데이터 조회 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// 관계 찾기 함수
function findChildren($parent_name, $generation, $all_data) {
    $children = [];
    $child_generation = $generation + 1;
    
    if (isset($all_data[$child_generation])) {
        foreach ($all_data[$child_generation] as $person) {
            if ($person['father_name'] === $parent_name || $person['mother_name'] === $parent_name) {
                $children[] = $person;
            }
        }
    }
    
    return $children;
}

function findParents($person, $generation, $all_data) {
    $parents = [];
    $parent_generation = $generation - 1;
    
    if (isset($all_data[$parent_generation])) {
        foreach ($all_data[$parent_generation] as $p) {
            if ($p['name'] === $person['father_name'] || $p['name'] === $person['mother_name']) {
                $parents[] = $p;
            }
        }
    }
    
    return $parents;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>가계도 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* 가계도 스타일 */
        .tree-container {
            min-height: 600px;
            overflow: auto;
            position: relative;
        }
        
        .tree-node {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            margin: 8px;
            min-width: 180px;
            text-align: center;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .tree-node:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: #4f46e5;
        }
        
        .tree-node.focus {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-color: #1d4ed8;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3);
        }
        
        .tree-node.male {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
        }
        
        .tree-node.female {
            border-color: #ec4899;
            background: linear-gradient(135deg, #fce7f3 0%, #fcf4ff 100%);
        }
        
        /* 연결선 스타일 */
        .connection-line {
            position: absolute;
            background: #6366f1;
            z-index: 1;
        }
        
        .connection-vertical {
            width: 2px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .connection-horizontal {
            height: 2px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        /* 세대 레이블 */
        .generation-label {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            position: sticky;
            left: 20px;
            z-index: 10;
        }
        
        /* 수직 트리 레이아웃 */
        .tree-vertical {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 40px;
        }
        
        .tree-generation {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            width: 100%;
            position: relative;
        }
        
        /* 수평 트리 레이아웃 */
        .tree-horizontal {
            display: flex;
            align-items: flex-start;
            gap: 60px;
            padding: 20px;
        }
        
        .tree-horizontal .tree-generation {
            flex-direction: column;
            min-width: 200px;
        }
        
        /* 컴팩트 레이아웃 */
        .tree-compact .tree-node {
            min-width: 120px;
            padding: 12px;
            font-size: 0.875rem;
        }
        
        /* 줌 컨트롤 */
        .zoom-controls {
            position: fixed;
            top: 120px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 20;
        }
        
        .zoom-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #374151;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .zoom-btn:hover {
            background: #f3f4f6;
            transform: scale(1.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 상단 네비게이션 -->
    <nav class="gradient-bg shadow-lg">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-white text-2xl font-bold hover:text-indigo-200 transition-colors">
                        <i class="fas fa-tree mr-2"></i>창녕조씨 족보
                    </a>
                    <span class="text-indigo-200 text-lg">/</span>
                    <span class="text-white text-lg">가계도</span>
                </div>
                
                <!-- 메뉴 버튼들 -->
                <div class="flex space-x-4">
                    <a href="index.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-home mr-1"></i>홈
                    </a>
                    <a href="search.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-search mr-1"></i>검색
                    </a>
                    <a href="generation.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-layer-group mr-1"></i>세대별
                    </a>
                    <a href="admin.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-cogs mr-1"></i>관리
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- 줌 컨트롤 -->
    <div class="zoom-controls">
        <button onclick="zoomIn()" class="zoom-btn" title="확대">
            <i class="fas fa-plus"></i>
        </button>
        <button onclick="zoomOut()" class="zoom-btn" title="축소">
            <i class="fas fa-minus"></i>
        </button>
        <button onclick="resetZoom()" class="zoom-btn" title="원래 크기">
            <i class="fas fa-expand"></i>
        </button>
    </div>

    <main class="container mx-auto px-6 py-8">
        <!-- 제목 및 컨트롤 -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
                <h2 class="text-3xl font-bold text-gray-800 mb-4 lg:mb-0">
                    <i class="fas fa-sitemap text-green-600 mr-3"></i>가계도
                    <?php if ($focus_person): ?>
                        <span class="text-lg text-blue-600 ml-2">- <?= htmlspecialchars($focus_person['name']) ?> 중심</span>
                    <?php endif; ?>
                </h2>
                
                <div class="flex flex-wrap gap-4">
                    <!-- 레이아웃 선택 -->
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'vertical'])) ?>" 
                           class="px-3 py-2 rounded-md text-sm <?= $tree_type === 'vertical' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-800' ?> transition-colors">
                            <i class="fas fa-arrows-alt-v mr-1"></i>세로
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'horizontal'])) ?>" 
                           class="px-3 py-2 rounded-md text-sm <?= $tree_type === 'horizontal' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-800' ?> transition-colors">
                            <i class="fas fa-arrows-alt-h mr-1"></i>가로
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'compact'])) ?>" 
                           class="px-3 py-2 rounded-md text-sm <?= $tree_type === 'compact' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-800' ?> transition-colors">
                            <i class="fas fa-compress mr-1"></i>간단
                        </a>
                    </div>
                </div>
            </div>

            <!-- 세대 범위 설정 -->
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <input type="hidden" name="focus" value="<?= $focus_person_id ?>">
                <input type="hidden" name="type" value="<?= $tree_type ?>">
                
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700">세대 범위:</label>
                    <select name="gen_start" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <?php for ($i = 1; $i <= 50; $i++): ?>
                            <option value="<?= $i ?>" <?= $generation_start == $i ? 'selected' : '' ?>><?= $i ?>세대</option>
                        <?php endfor; ?>
                    </select>
                    <span class="text-gray-500">~</span>
                    <select name="gen_end" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <?php for ($i = 1; $i <= 50; $i++): ?>
                            <option value="<?= $i ?>" <?= $generation_end == $i ? 'selected' : '' ?>><?= $i ?>세대</option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                    <i class="fas fa-sync mr-1"></i>적용
                </button>
                
                <a href="family_tree.php" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm">
                    <i class="fas fa-refresh mr-1"></i>초기화
                </a>
            </form>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-8 text-center">
                <i class="fas fa-database text-red-500 text-4xl mb-4"></i>
                <h3 class="text-xl font-medium text-red-800 mb-2">데이터베이스 연결 오류</h3>
                <p class="text-red-600"><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php elseif (empty($tree_data)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-8 text-center">
                <i class="fas fa-sitemap text-yellow-500 text-6xl mb-4"></i>
                <h3 class="text-2xl font-medium text-yellow-800 mb-2">가계도 데이터가 없습니다</h3>
                <p class="text-yellow-600 mb-4">선택한 세대 범위(<?= $generation_start ?>~<?= $generation_end ?>세대)에 등록된 인물이 없습니다.</p>
                <a href="admin.php" class="inline-block px-6 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>인물 등록하기
                </a>
            </div>
        <?php else: ?>
            <!-- 가계도 -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <div id="treeContainer" class="tree-container <?= $tree_type === 'horizontal' ? 'tree-horizontal' : ($tree_type === 'compact' ? 'tree-vertical tree-compact' : 'tree-vertical') ?>">
                    <?php foreach ($tree_data as $generation => $persons): ?>
                        <div class="tree-generation" data-generation="<?= $generation ?>">
                            <!-- 세대 레이블 -->
                            <div class="generation-label mb-4">
                                <?= $generation ?>세대 (<?= count($persons) ?>명)
                            </div>
                            
                            <!-- 인물들 -->
                            <div class="flex <?= $tree_type === 'horizontal' ? 'flex-col' : 'flex-row' ?> flex-wrap justify-center gap-4">
                                <?php foreach ($persons as $person): ?>
                                    <div class="tree-node <?= $person['id'] == $focus_person_id ? 'focus' : '' ?> 
                                              <?= isset($person['gender']) && $person['gender'] === 'F' ? 'female' : 'male' ?>"
                                         data-person-id="<?= $person['id'] ?>"
                                         onclick="showPersonDetails(<?= $person['id'] ?>)">
                                        
                                        <div class="person-info">
                                            <h4 class="font-bold text-gray-800 mb-1">
                                                <?= htmlspecialchars($person['name']) ?>
                                            </h4>
                                            
                                            <?php if ($person['birth_year']): ?>
                                                <p class="text-xs text-gray-600 mb-1">
                                                    <?= $person['birth_year'] ?>년생
                                                    <?php if ($person['death_year']): ?>
                                                        ~ <?= $person['death_year'] ?>년
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($person['occupation']): ?>
                                                <p class="text-xs text-blue-600 mb-1">
                                                    <?= htmlspecialchars(mb_substr($person['occupation'], 0, 10)) ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <!-- 관계 표시 -->
                                            <div class="mt-2 text-xs">
                                                <?php if ($person['father_name']): ?>
                                                    <p class="text-gray-500">부: <?= htmlspecialchars(mb_substr($person['father_name'], 0, 8)) ?></p>
                                                <?php endif; ?>
                                                <?php if ($person['spouse_name']): ?>
                                                    <p class="text-gray-500">배: <?= htmlspecialchars(mb_substr($person['spouse_name'], 0, 8)) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- 액션 버튼 -->
                                            <div class="mt-3 flex justify-center space-x-1">
                                                <button onclick="event.stopPropagation(); focusPerson(<?= $person['id'] ?>)" 
                                                        class="text-blue-600 hover:text-blue-800 text-xs" title="이 사람 중심으로 보기">
                                                    <i class="fas fa-crosshairs"></i>
                                                </button>
                                                <a href="search.php?name=<?= urlencode($person['name']) ?>" 
                                                   onclick="event.stopPropagation()"
                                                   class="text-green-600 hover:text-green-800 text-xs" title="검색">
                                                    <i class="fas fa-search"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- 세대 간 연결선 -->
                        <?php if (isset($tree_data[$generation + 1])): ?>
                            <div class="connection-line <?= $tree_type === 'horizontal' ? 'connection-horizontal' : 'connection-vertical' ?>" 
                                 style="<?= $tree_type === 'horizontal' ? 'width: 40px; left: 50%;' : 'height: 20px; top: 100%;' ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 범례 -->
            <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
                <h3 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>범례
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-blue-200 border-2 border-blue-400 rounded"></div>
                        <span class="text-sm text-gray-600">남성</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-pink-200 border-2 border-pink-400 rounded"></div>
                        <span class="text-sm text-gray-600">여성</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-blue-100 border-2 border-blue-600 rounded shadow-md"></div>
                        <span class="text-sm text-gray-600">포커스 인물</span>
                    </div>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    <p><i class="fas fa-mouse-pointer mr-1"></i>인물을 클릭하면 상세 정보를 볼 수 있습니다.</p>
                    <p><i class="fas fa-crosshairs mr-1"></i>십자선 아이콘을 클릭하면 해당 인물을 중심으로 가계도를 다시 구성합니다.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- 인물 상세 모달 -->
    <div id="personModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-96 overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-800">인물 상세 정보</h3>
                        <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div id="modalContent">
                        <!-- AJAX로 로드됨 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 푸터 -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-6 text-center">
            <p class="mb-4">&copy; 2024 창녕조씨 족보 시스템. 닥터조 개발.</p>
            <p class="text-gray-400 text-sm">
                가문의 역사를 디지털로 보존하고 전승하는 현대적 족보 시스템
            </p>
        </div>
    </footer>

    <script>
        let zoomLevel = 1;

        // 줌 기능
        function zoomIn() {
            zoomLevel = Math.min(zoomLevel + 0.2, 2);
            applyZoom();
        }

        function zoomOut() {
            zoomLevel = Math.max(zoomLevel - 0.2, 0.5);
            applyZoom();
        }

        function resetZoom() {
            zoomLevel = 1;
            applyZoom();
        }

        function applyZoom() {
            const container = document.getElementById('treeContainer');
            container.style.transform = `scale(${zoomLevel})`;
            container.style.transformOrigin = 'top center';
        }

        // 인물 포커스
        function focusPerson(personId) {
            const url = new URL(window.location);
            url.searchParams.set('focus', personId);
            window.location = url;
        }

        // 인물 상세 정보 모달
        function showPersonDetails(personId) {
            const modal = document.getElementById('personModal');
            const content = document.getElementById('modalContent');
            
            // 로딩 표시
            content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2">로딩 중...</p></div>';
            modal.classList.remove('hidden');
            
            // AJAX로 상세 정보 로드 (실제 구현 시 person_detail.php 필요)
            fetch(`person_detail.php?id=${personId}&ajax=1`)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                            <p>상세 정보를 불러오는데 실패했습니다.</p>
                            <p class="text-sm mt-1">person_detail.php 파일이 필요합니다.</p>
                        </div>
                    `;
                });
        }

        function closeModal() {
            document.getElementById('personModal').classList.add('hidden');
        }

        // 키보드 단축키
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            } else if (e.key === '=' || e.key === '+') {
                e.preventDefault();
                zoomIn();
            } else if (e.key === '-') {
                e.preventDefault();
                zoomOut();
            } else if (e.key === '0') {
                e.preventDefault();
                resetZoom();
            }
        });

        // 모달 외부 클릭 시 닫기
        document.getElementById('personModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // 페이지 로드 시 애니메이션
        document.addEventListener('DOMContentLoaded', function() {
            const nodes = document.querySelectorAll('.tree-node');
            nodes.forEach((node, index) => {
                node.style.opacity = '0';
                node.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    node.style.transition = 'all 0.6s ease';
                    node.style.opacity = '1';
                    node.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // 포커스 인물이 있으면 해당 위치로 스크롤
            const focusNode = document.querySelector('.tree-node.focus');
            if (focusNode) {
                setTimeout(() => {
                    focusNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 1000);
            }
        });

        // 터치/마우스 드래그로 이동
        let isDragging = false;
        let startX, startY, scrollLeft, scrollTop;

        const container = document.getElementById('treeContainer');

        container.addEventListener('mousedown', startDrag);
        container.addEventListener('touchstart', startDrag);

        function startDrag(e) {
            isDragging = true;
            startX = (e.pageX || e.touches[0].pageX) - container.offsetLeft;
            startY = (e.pageY || e.touches[0].pageY) - container.offsetTop;
            scrollLeft = container.scrollLeft;
            scrollTop = container.scrollTop;
        }

        document.addEventListener('mousemove', drag);
        document.addEventListener('touchmove', drag);

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault();
            const x = (e.pageX || e.touches[0].pageX) - container.offsetLeft;
            const y = (e.pageY || e.touches[0].pageY) - container.offsetTop;
            const walkX = (x - startX) * 1;
            const walkY = (y - startY) * 1;
            container.scrollLeft = scrollLeft - walkX;
            container.scrollTop = scrollTop - walkY;
        }

        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchend', stopDrag);

        function stopDrag() {
            isDragging = false;
        }
    </script>
</body>
</html>