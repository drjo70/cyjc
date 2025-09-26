<?php
// 창녕조씨 족보 시스템 - 세대별 족보 보기
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

// 선택된 세대 또는 기본값
$selected_generation = (int)($_GET['gen'] ?? 1);
$view_mode = $_GET['view'] ?? 'timeline'; // timeline, list, stats

// 세대별 데이터 가져오기
$generation_data = [];
$generation_stats = [];

if (isset($pdo) && $pdo !== null) {
    try {
        // 전체 세대 통계
        $stats_stmt = $pdo->query("
            SELECT 
                generation,
                COUNT(*) as count,
                COUNT(CASE WHEN death_date IS NULL THEN 1 END) as living_count,
                AVG(CASE WHEN birth_date IS NOT NULL THEN YEAR(birth_date) END) as avg_birth_year,
                MIN(CASE WHEN birth_date IS NOT NULL THEN YEAR(birth_date) END) as min_birth_year,
                MAX(CASE WHEN birth_date IS NOT NULL THEN YEAR(birth_date) END) as max_birth_year
            FROM family_members 
            WHERE generation IS NOT NULL 
            GROUP BY generation 
            ORDER BY generation
        ");
        $generation_stats = $stats_stmt->fetchAll();

        // 선택된 세대의 상세 데이터
        if ($selected_generation > 0) {
            $data_stmt = $pdo->prepare("
                SELECT id, name, name_hanja, generation, birth_date, death_date, 
                       person_code, parent_code, gender, sibling_order, child_count
                FROM family_members 
                WHERE generation = ? 
                ORDER BY name
            ");
            $data_stmt->execute([$selected_generation]);
            $db_generation_data = $data_stmt->fetchAll();
            
            // 데이터 형식 변환
            $generation_data = [];
            foreach ($db_generation_data as $row) {
                $generation_data[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'name_hanja' => $row['name_hanja'] ?? '',
                    'generation' => $row['generation'],
                    'birth_year' => $row['birth_date'] ? date('Y', strtotime($row['birth_date'])) : null,
                    'death_year' => $row['death_date'] ? date('Y', strtotime($row['death_date'])) : null,
                    'father_name' => '', // parent_code로 참조 가능
                    'mother_name' => '',
                    'spouse_name' => '',
                    'notes' => "코드: {$row['person_code']}"
                ];
            }
        }

    } catch (PDOException $e) {
        $db_error = "데이터 조회 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// 세대별 설명 데이터 (실제 족보 데이터에 맞게 수정 필요)
$generation_descriptions = [
    1 => "시조 조계룡(趙繼龍) - 창녕조씨의 시조로 고려 충렬왕 때 문과에 급제하여 벼슬을 지내셨습니다.",
    2 => "2세조 - 시조의 아들들로 가문의 기틀을 다졌습니다.",
    3 => "3세조 - 고려 말 조선 초의 격동기를 살아간 세대입니다.",
    4 => "4세조 - 조선 건국 초기 새로운 시대에 적응한 세대입니다.",
    5 => "5세조 - 조선 전기 문물이 발달하던 시기의 세대입니다.",
    // 더 많은 세대 설명 추가 가능
];

function getGenerationDescription($gen) {
    global $generation_descriptions;
    
    if (isset($generation_descriptions[$gen])) {
        return $generation_descriptions[$gen];
    }
    
    // 기본 설명 생성
    $period = '';
    if ($gen <= 5) $period = '고려 말 ~ 조선 전기';
    elseif ($gen <= 10) $period = '조선 전기';
    elseif ($gen <= 15) $period = '조선 중기';
    elseif ($gen <= 20) $period = '조선 후기';
    elseif ($gen <= 25) $period = '조선 말기';
    elseif ($gen <= 30) $period = '일제강점기';
    elseif ($gen <= 35) $period = '광복 ~ 한국전쟁';
    elseif ($gen <= 40) $period = '산업화 시대';
    elseif ($gen <= 45) $period = '현대 초기';
    else $period = '현대';
    
    return "{$gen}세조 - {$period}를 살아간 우리 가문의 소중한 세대입니다.";
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>세대별 족보 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        .timeline-item {
            position: relative;
        }
        .timeline-line {
            position: absolute;
            left: 2rem;
            top: 4rem;
            bottom: -2rem;
            width: 2px;
            background: linear-gradient(to bottom, #4f46e5, #7c3aed);
        }
        .timeline-dot {
            position: absolute;
            left: 1.5rem;
            top: 2rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            box-shadow: 0 0 0 4px white, 0 0 0 6px #4f46e5;
        }
        .generation-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-left: 4px solid #4f46e5;
        }
        .generation-card.active {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-left-color: #1d4ed8;
            transform: scale(1.02);
        }
        .person-card {
            background: white;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .person-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
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
                    <span class="text-white text-lg">세대별 보기</span>
                </div>
                
                <!-- 메뉴 버튼들 -->
                <div class="flex space-x-4">
                    <a href="index.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-home mr-1"></i>홈
                    </a>
                    <a href="search.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-search mr-1"></i>검색
                    </a>
                    <a href="family_tree.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-sitemap mr-1"></i>가계도
                    </a>
                    <a href="admin.php" class="text-white px-4 py-2 rounded-lg hover:bg-white hover:bg-opacity-20 transition-all">
                        <i class="fas fa-cogs mr-1"></i>관리
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-8">
        <!-- 제목 및 필터 -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
                <h2 class="text-3xl font-bold text-gray-800 mb-4 lg:mb-0">
                    <i class="fas fa-layer-group text-blue-600 mr-3"></i>세대별 족보
                </h2>
                
                <div class="flex flex-wrap gap-4">
                    <!-- 보기 방식 선택 -->
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <a href="?gen=<?= $selected_generation ?>&view=timeline" 
                           class="px-4 py-2 rounded-md <?= $view_mode === 'timeline' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-800' ?> transition-colors">
                            <i class="fas fa-stream mr-1"></i>타임라인
                        </a>
                        <a href="?gen=<?= $selected_generation ?>&view=list" 
                           class="px-4 py-2 rounded-md <?= $view_mode === 'list' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-800' ?> transition-colors">
                            <i class="fas fa-list mr-1"></i>목록
                        </a>
                        <a href="?gen=<?= $selected_generation ?>&view=stats" 
                           class="px-4 py-2 rounded-md <?= $view_mode === 'stats' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-800' ?> transition-colors">
                            <i class="fas fa-chart-bar mr-1"></i>통계
                        </a>
                    </div>
                </div>
            </div>

            <!-- 세대 선택 -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">세대 선택</label>
                <select onchange="location.href='?gen=' + this.value + '&view=<?= $view_mode ?>'" 
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">세대를 선택하세요</option>
                    <?php foreach ($generation_stats as $stat): ?>
                        <option value="<?= $stat['generation'] ?>" <?= $selected_generation == $stat['generation'] ? 'selected' : '' ?>>
                            <?= $stat['generation'] ?>세대 (<?= $stat['count'] ?>명)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-8 text-center">
                <i class="fas fa-database text-red-500 text-4xl mb-4"></i>
                <h3 class="text-xl font-medium text-red-800 mb-2">데이터베이스 연결 오류</h3>
                <p class="text-red-600"><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php elseif ($view_mode === 'stats'): ?>
            <!-- 통계 보기 -->
            <div class="space-y-6">
                <!-- 전체 통계 -->
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-chart-pie text-purple-600 mr-2"></i>전체 세대 통계
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-blue-50 rounded-lg p-6 text-center">
                            <i class="fas fa-layer-group text-blue-600 text-3xl mb-2"></i>
                            <h4 class="text-xl font-bold text-blue-800"><?= count($generation_stats) ?></h4>
                            <p class="text-blue-600">총 세대 수</p>
                        </div>
                        
                        <div class="bg-green-50 rounded-lg p-6 text-center">
                            <i class="fas fa-users text-green-600 text-3xl mb-2"></i>
                            <h4 class="text-xl font-bold text-green-800">
                                <?= array_sum(array_column($generation_stats, 'count')) ?>
                            </h4>
                            <p class="text-green-600">총 인원 수</p>
                        </div>
                        
                        <div class="bg-purple-50 rounded-lg p-6 text-center">
                            <i class="fas fa-heart text-purple-600 text-3xl mb-2"></i>
                            <h4 class="text-xl font-bold text-purple-800">
                                <?= array_sum(array_column($generation_stats, 'living_count')) ?>
                            </h4>
                            <p class="text-purple-600">생존 인원</p>
                        </div>
                        
                        <div class="bg-orange-50 rounded-lg p-6 text-center">
                            <i class="fas fa-calendar text-orange-600 text-3xl mb-2"></i>
                            <h4 class="text-xl font-bold text-orange-800">
                                <?= !empty($generation_stats) ? max(array_column($generation_stats, 'generation')) : 0 ?>
                            </h4>
                            <p class="text-orange-600">최신 세대</p>
                        </div>
                    </div>

                    <!-- 세대별 상세 통계 -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b-2 border-gray-200">
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">세대</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">총 인원</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">생존 인원</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">생년 범위</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">평균 생년</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">상세보기</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($generation_stats as $stat): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <span class="font-medium text-blue-600"><?= $stat['generation'] ?>세대</span>
                                        </td>
                                        <td class="py-3 px-4"><?= number_format($stat['count']) ?>명</td>
                                        <td class="py-3 px-4">
                                            <span class="text-green-600"><?= number_format($stat['living_count']) ?>명</span>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-600">
                                            <?php if ($stat['min_birth_year'] && $stat['max_birth_year']): ?>
                                                <?= $stat['min_birth_year'] ?>년 ~ <?= $stat['max_birth_year'] ?>년
                                            <?php else: ?>
                                                미상
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-600">
                                            <?= $stat['avg_birth_year'] ? round($stat['avg_birth_year']) . '년' : '미상' ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <a href="?gen=<?= $stat['generation'] ?>&view=timeline" 
                                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                <i class="fas fa-eye mr-1"></i>보기
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($selected_generation > 0): ?>
            <!-- 선택된 세대 정보 -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
                <div class="text-center">
                    <h3 class="text-3xl font-bold text-gray-800 mb-4">
                        <?= $selected_generation ?>세대
                        <span class="text-lg text-gray-600 ml-2">(<?= count($generation_data) ?>명)</span>
                    </h3>
                    <p class="text-gray-600 text-lg mb-6">
                        <?= getGenerationDescription($selected_generation) ?>
                    </p>
                    
                    <!-- 세대 네비게이션 -->
                    <div class="flex justify-center space-x-4 mb-6">
                        <?php if ($selected_generation > 1): ?>
                            <a href="?gen=<?= $selected_generation - 1 ?>&view=<?= $view_mode ?>" 
                               class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-chevron-left mr-1"></i><?= $selected_generation - 1 ?>세대
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $next_gen_exists = false;
                        foreach ($generation_stats as $stat) {
                            if ($stat['generation'] == $selected_generation + 1) {
                                $next_gen_exists = true;
                                break;
                            }
                        }
                        if ($next_gen_exists):
                        ?>
                            <a href="?gen=<?= $selected_generation + 1 ?>&view=<?= $view_mode ?>" 
                               class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <?= $selected_generation + 1 ?>세대<i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($view_mode === 'timeline'): ?>
                <!-- 타임라인 보기 -->
                <div class="space-y-8">
                    <?php foreach ($generation_data as $index => $person): ?>
                        <div class="timeline-item relative">
                            <?php if ($index < count($generation_data) - 1): ?>
                                <div class="timeline-line"></div>
                            <?php endif; ?>
                            <div class="timeline-dot"></div>
                            
                            <div class="ml-16 person-card p-6 shadow-lg">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <!-- 기본 정보 -->
                                    <div>
                                        <h4 class="text-xl font-bold text-gray-800 mb-2">
                                            <?= htmlspecialchars($person['name']) ?>
                                        </h4>
                                        <div class="space-y-1 text-sm text-gray-600">
                                            <p><i class="fas fa-calendar-alt w-4 mr-2"></i>
                                                생년: <?= $person['birth_year'] ? $person['birth_year'] . '년' : '미상' ?>
                                                <?php if ($person['death_year']): ?>
                                                    ~ <?= $person['death_year'] ?>년
                                                <?php endif; ?>
                                            </p>

                                        </div>
                                    </div>

                                    <!-- 가족 관계 -->
                                    <div>
                                        <h5 class="font-semibold text-gray-700 mb-2">가족 관계</h5>
                                        <div class="space-y-1 text-sm text-gray-600">
                                            <?php if ($person['father_name']): ?>
                                                <p><i class="fas fa-male w-4 mr-2"></i>부: <?= htmlspecialchars($person['father_name']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($person['mother_name']): ?>
                                                <p><i class="fas fa-female w-4 mr-2"></i>모: <?= htmlspecialchars($person['mother_name']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($person['spouse_name']): ?>
                                                <p><i class="fas fa-heart w-4 mr-2"></i>배우자: <?= htmlspecialchars($person['spouse_name']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- 추가 정보 -->
                                    <div>
                                        <h5 class="font-semibold text-gray-700 mb-2">추가 정보</h5>
                                        <div class="space-y-1 text-sm text-gray-600">

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($generation_data)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-users text-gray-300 text-6xl mb-4"></i>
                            <h4 class="text-xl font-medium text-gray-500 mb-2"><?= $selected_generation ?>세대에 등록된 인물이 없습니다</h4>
                            <p class="text-gray-400">다른 세대를 선택하거나 새로운 인물을 등록해보세요.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- 목록 보기 -->
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <?php if (!empty($generation_data)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($generation_data as $person): ?>
                                <div class="person-card p-6 border border-gray-200">
                                    <h4 class="text-lg font-bold text-gray-800 mb-3">
                                        <?= htmlspecialchars($person['name']) ?>
                                    </h4>
                                    
                                    <div class="space-y-2 text-sm text-gray-600 mb-4">
                                        <p><i class="fas fa-calendar w-4 mr-2"></i>
                                            <?= $person['birth_year'] ? $person['birth_year'] . '년생' : '생년 미상' ?>
                                        </p>
                                        <?php if ($person['father_name']): ?>
                                            <p><i class="fas fa-male w-4 mr-2"></i><?= htmlspecialchars($person['father_name']) ?></p>
                                        <?php endif; ?>

                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <a href="person_detail.php?id=<?= $person['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm">
                                            <i class="fas fa-eye mr-1"></i>상세
                                        </a>
                                        <a href="family_tree.php?focus=<?= $person['id'] ?>" 
                                           class="text-green-600 hover:text-green-800 text-sm">
                                            <i class="fas fa-sitemap mr-1"></i>가계도
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-users text-gray-300 text-6xl mb-4"></i>
                            <h4 class="text-xl font-medium text-gray-500 mb-2"><?= $selected_generation ?>세대에 등록된 인물이 없습니다</h4>
                            <p class="text-gray-400">다른 세대를 선택하거나 새로운 인물을 등록해보세요.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- 세대 선택 안내 -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center">
                <i class="fas fa-layer-group text-blue-500 text-6xl mb-4"></i>
                <h3 class="text-2xl font-medium text-blue-800 mb-2">세대를 선택해주세요</h3>
                <p class="text-blue-600 mb-6">위의 세대 선택 드롭다운에서 보고 싶은 세대를 선택하세요.</p>
                
                <!-- 빠른 세대 선택 -->
                <div class="flex flex-wrap justify-center gap-2">
                    <?php foreach (array_slice($generation_stats, 0, 10) as $stat): ?>
                        <a href="?gen=<?= $stat['generation'] ?>&view=<?= $view_mode ?>" 
                           class="px-4 py-2 bg-blue-100 text-blue-700 rounded-full hover:bg-blue-200 transition-colors text-sm">
                            <?= $stat['generation'] ?>세대 (<?= $stat['count'] ?>명)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

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
        document.addEventListener('DOMContentLoaded', function() {
            // 타임라인 아이템 애니메이션
            const timelineItems = document.querySelectorAll('.timeline-item');
            timelineItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.6s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, index * 150);
            });

            // 카드 애니메이션
            const cards = document.querySelectorAll('.person-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>