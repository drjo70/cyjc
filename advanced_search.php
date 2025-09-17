<?php
/**
 * 창녕조씨 족보 시스템 - 고급 검색 페이지
 * 
 * @author 닥터조 (주)조유
 * @version 1.0
 * @date 2024-09-17
 */

require_once 'config/database.php';
require_once 'models/Person.php';

$person = new Person();
$search_results = [];
$search_performed = false;

// 검색 처리
if (isset($_GET['search']) && !empty($_GET['query'])) {
    $search_performed = true;
    $search_type = $_GET['search_type'] ?? 'all';
    $query = trim($_GET['query']);
    $generation = $_GET['generation'] ?? '';
    $gender = $_GET['gender'] ?? '';
    
    // 복합 검색 쿼리 구성
    $search_results = performAdvancedSearch($query, $search_type, $generation, $gender);
}

/**
 * 고급 검색 함수
 */
function performAdvancedSearch($query, $search_type, $generation = '', $gender = '') {
    global $person;
    
    try {
        $db = getDB();
        $conditions = [];
        $params = [];
        
        // 검색 조건 구성
        if (!empty($query)) {
            switch ($search_type) {
                case 'name':
                    $conditions[] = "(name LIKE ? OR name_hanja LIKE ?)";
                    $params[] = "%$query%";
                    $params[] = "%$query%";
                    break;
                    
                case 'hanja_only':
                    $conditions[] = "name_hanja LIKE ?";
                    $params[] = "%$query%";
                    break;
                    
                case 'korean_only':
                    $conditions[] = "name LIKE ?";
                    $params[] = "%$query%";
                    break;
                    
                case 'person_code':
                    $conditions[] = "person_code LIKE ?";
                    $params[] = "%$query%";
                    break;
                    
                default: // 'all'
                    $conditions[] = "(name LIKE ? OR name_hanja LIKE ? OR person_code LIKE ?)";
                    $params[] = "%$query%";
                    $params[] = "%$query%";
                    $params[] = "%$query%";
                    break;
            }
        }
        
        // 세대 필터
        if (!empty($generation)) {
            $conditions[] = "generation = ?";
            $params[] = $generation;
        }
        
        // 성별 필터
        if (!empty($gender)) {
            $conditions[] = "gender = ?";
            $params[] = $gender;
        }
        
        // 기본 WHERE 조건이 없으면 전체 조회
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT 
                id, person_code, parent_code, name, name_hanja, 
                gender, generation, sibling_order, child_count,
                birth_date, death_date
            FROM family_members 
            $whereClause
            ORDER BY generation ASC, sibling_order ASC, name ASC
            LIMIT 100
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $results,
            'count' => count($results),
            'query_info' => [
                'query' => $query,
                'type' => $search_type,
                'generation' => $generation,
                'gender' => $gender
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '검색 실패: ' . $e->getMessage(),
            'data' => []
        ];
    }
}

// 통계 정보 가져오기
$stats_result = $person->getGenealogyStats();
$stats = $stats_result['success'] ? $stats_result['data'] : [];

// 세대 범위 가져오기
$gen_range_result = $person->getGenerationRange();
$gen_range = $gen_range_result['success'] ? $gen_range_result['data'] : ['min_gen' => 1, 'max_gen' => 45];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>고급 검색 - 창녕조씨 족보 시스템</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- 커스텀 스타일 -->
    <style>
        .search-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .search-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .search-card .card-body {
            color: #333;
        }
        
        .result-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e9ecef;
            margin-bottom: 1rem;
        }
        
        .result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .generation-badge {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            font-weight: bold;
        }
        
        .gender-badge {
            font-size: 0.8rem;
        }
        
        .search-stats {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-filters {
            background: #fff;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'views/header.php'; ?>

    <!-- 검색 헤더 섹션 -->
    <div class="search-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="text-center mb-4">
                        <h1 class="display-5 fw-bold mb-3">
                            <i class="bi bi-search"></i> 고급 검색
                        </h1>
                        <p class="lead">이름, 한자명, 세대, 성별 등 다양한 조건으로 족보를 검색하세요</p>
                    </div>
                    
                    <!-- 검색 폼 -->
                    <div class="card search-card">
                        <div class="card-body">
                            <form method="GET" action="advanced_search.php">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="query" class="form-label fw-bold">검색어</label>
                                        <input type="text" class="form-control form-control-lg" 
                                               id="query" name="query" 
                                               value="<?= htmlspecialchars($_GET['query'] ?? '') ?>"
                                               placeholder="이름, 한자명, 또는 인물코드 입력">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="search_type" class="form-label fw-bold">검색 유형</label>
                                        <select class="form-select form-select-lg" id="search_type" name="search_type">
                                            <option value="all" <?= ($_GET['search_type'] ?? 'all') == 'all' ? 'selected' : '' ?>>전체 검색</option>
                                            <option value="name" <?= ($_GET['search_type'] ?? '') == 'name' ? 'selected' : '' ?>>이름 (한글+한자)</option>
                                            <option value="korean_only" <?= ($_GET['search_type'] ?? '') == 'korean_only' ? 'selected' : '' ?>>한글명만</option>
                                            <option value="hanja_only" <?= ($_GET['search_type'] ?? '') == 'hanja_only' ? 'selected' : '' ?>>한자명만</option>
                                            <option value="person_code" <?= ($_GET['search_type'] ?? '') == 'person_code' ? 'selected' : '' ?>>인물코드</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="generation" class="form-label fw-bold">세대</label>
                                        <select class="form-select" id="generation" name="generation">
                                            <option value="">전체 세대</option>
                                            <?php for ($i = $gen_range['min_gen']; $i <= $gen_range['max_gen']; $i++): ?>
                                                <option value="<?= $i ?>" <?= ($_GET['generation'] ?? '') == $i ? 'selected' : '' ?>>
                                                    <?= $i ?>세대
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="gender" class="form-label fw-bold">성별</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">전체</option>
                                            <option value="남" <?= ($_GET['gender'] ?? '') == '남' ? 'selected' : '' ?>>남성</option>
                                            <option value="여" <?= ($_GET['gender'] ?? '') == '여' ? 'selected' : '' ?>>여성</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" name="search" class="btn btn-primary btn-lg">
                                                <i class="bi bi-search"></i> 검색
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- 빠른 필터 -->
        <div class="quick-filters">
            <h5 class="fw-bold mb-3"><i class="bi bi-lightning"></i> 빠른 검색</h5>
            <div class="row g-2">
                <div class="col-auto">
                    <a href="?search=1&search_type=all&generation=1" class="btn btn-outline-primary btn-sm">1세대 (시조)</a>
                </div>
                <div class="col-auto">
                    <a href="?search=1&search_type=all&generation=<?= $gen_range['max_gen'] ?>" class="btn btn-outline-success btn-sm"><?= $gen_range['max_gen'] ?>세대 (최신)</a>
                </div>
                <div class="col-auto">
                    <a href="?search=1&search_type=all&gender=남" class="btn btn-outline-info btn-sm">남성 전체</a>
                </div>
                <div class="col-auto">
                    <a href="?search=1&search_type=all&gender=여" class="btn btn-outline-warning btn-sm">여성 전체</a>
                </div>
                <div class="col-auto">
                    <a href="?search=1&query=조" class="btn btn-outline-dark btn-sm">'조'씨 검색</a>
                </div>
            </div>
        </div>

        <?php if ($search_performed): ?>
            <!-- 검색 결과 -->
            <div class="search-stats">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><i class="bi bi-graph-up"></i> 검색 결과</h5>
                        <p class="mb-0">
                            <strong><?= $search_results['count'] ?? 0 ?>명</strong>이 검색되었습니다
                            <?php if (!empty($_GET['query'])): ?>
                                (검색어: "<?= htmlspecialchars($_GET['query']) ?>")
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="text-muted">
                        <small><i class="bi bi-clock"></i> <?= date('Y-m-d H:i:s') ?></small>
                    </div>
                </div>
            </div>

            <div class="row">
                <?php if ($search_results['success'] && count($search_results['data']) > 0): ?>
                    <?php foreach ($search_results['data'] as $result): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card result-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0 fw-bold">
                                            <?= htmlspecialchars($result['name']) ?>
                                        </h6>
                                        <span class="badge generation-badge"><?= $result['generation'] ?>세</span>
                                    </div>
                                    
                                    <?php if (!empty($result['name_hanja'])): ?>
                                        <p class="text-muted mb-2">
                                            <i class="bi bi-translate"></i> <?= htmlspecialchars($result['name_hanja']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-auto">
                                            <span class="badge bg-secondary gender-badge">
                                                <i class="bi bi-person"></i> <?= $result['gender'] ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($result['child_count'] > 0): ?>
                                            <div class="col-auto">
                                                <span class="badge bg-info gender-badge">
                                                    <i class="bi bi-people"></i> 자녀 <?= $result['child_count'] ?>명
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($result['birth_date']) && $result['birth_date'] != '0000-00-00'): ?>
                                            <div class="col-12">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar"></i> <?= $result['birth_date'] ?>
                                                    <?php if (!empty($result['death_date']) && $result['death_date'] != '0000-00-00'): ?>
                                                        ~ <?= $result['death_date'] ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <a href="index.php?page=person&id=<?= $result['id'] ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye"></i> 상세보기
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle fs-2 mb-3"></i>
                            <h5>검색 결과가 없습니다</h5>
                            <p class="mb-0">다른 검색어나 조건으로 다시 시도해보세요.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- 기본 통계 정보 -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> 족보 통계</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4 text-center">
                                    <div class="fs-2 text-primary fw-bold"><?= number_format($stats['total_persons'] ?? 0) ?></div>
                                    <div class="text-muted">총 인원</div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="fs-2 text-success fw-bold"><?= $gen_range['max_gen'] ?? 0 ?></div>
                                    <div class="text-muted">최대 세대</div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="fs-2 text-info fw-bold"><?= number_format($stats['alive_persons'] ?? 0) ?></div>
                                    <div class="text-muted">생존 추정</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-search"></i> 검색 도움말</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><i class="bi bi-check-circle text-success"></i> 부분 검색 가능</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success"></i> 한글/한자 동시 검색</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success"></i> 세대별 필터링</li>
                                <li><i class="bi bi-check-circle text-success"></i> 성별 구분 검색</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'views/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 검색 개선 스크립트 -->
    <script>
        // 엔터키로 검색
        document.getElementById('query').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
        
        // 실시간 검색 카운터 (향후 구현)
        function updateSearchCounter() {
            const query = document.getElementById('query').value;
            const searchType = document.getElementById('search_type').value;
            
            if (query.length >= 2) {
                // AJAX로 실시간 카운트 (향후 구현)
                console.log('실시간 검색 카운트:', query, searchType);
            }
        }
        
        // 검색어 입력 시 실시간 반응
        document.getElementById('query').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(updateSearchCounter, 500);
        });
    </script>
</body>
</html>