<?php
/**
 * 창녕조씨 족보 시스템 - 관리자 페이지
 * 
 * @author 닥터조 (주)조유
 * @version 1.0
 * @date 2024-09-17
 */

session_start();

require_once 'config/database.php';
require_once 'models/Person.php';

// 간단한 인증 (실제로는 더 강력한 인증 시스템 필요)
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// 로그인 처리
if (isset($_POST['admin_login'])) {
    $password = $_POST['password'] ?? '';
    // 간단한 비밀번호 (실제로는 해시된 비밀번호 사용)
    if ($password === 'cyjc2024admin') {
        $_SESSION['admin_logged_in'] = true;
        $is_admin = true;
    } else {
        $login_error = "비밀번호가 틀렸습니다.";
    }
}

// 로그아웃 처리
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$person = new Person();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$error = '';

// 관리자 작업 처리
if ($is_admin && $action) {
    switch ($action) {
        case 'add_person':
            $result = addPerson();
            if ($result['success']) {
                $message = "인물이 성공적으로 추가되었습니다.";
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'edit_person':
            $result = editPerson();
            if ($result['success']) {
                $message = "인물 정보가 성공적으로 수정되었습니다.";
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'delete_person':
            $result = deletePerson();
            if ($result['success']) {
                $message = "인물이 성공적으로 삭제되었습니다.";
            } else {
                $error = $result['message'];
            }
            break;
    }
}

/**
 * 인물 추가 함수
 */
function addPerson() {
    try {
        $db = getDB();
        
        $person_code = $_POST['person_code'] ?? '';
        $parent_code = $_POST['parent_code'] ?? null;
        $name = $_POST['name'] ?? '';
        $name_hanja = $_POST['name_hanja'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $generation = $_POST['generation'] ?? '';
        $sibling_order = $_POST['sibling_order'] ?? 1;
        $child_count = $_POST['child_count'] ?? 0;
        $birth_date = $_POST['birth_date'] ?? null;
        $death_date = $_POST['death_date'] ?? null;
        
        // 필수 필드 검증
        if (empty($person_code) || empty($name) || empty($gender) || empty($generation)) {
            return ['success' => false, 'message' => '필수 정보를 모두 입력해주세요.'];
        }
        
        // 중복 인물 코드 확인
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM family_members WHERE person_code = ?");
        $checkStmt->execute([$person_code]);
        if ($checkStmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => '이미 존재하는 인물 코드입니다.'];
        }
        
        // 빈 날짜를 NULL로 처리
        if (empty($birth_date)) $birth_date = null;
        if (empty($death_date)) $death_date = null;
        if (empty($parent_code)) $parent_code = null;
        
        $stmt = $db->prepare("
            INSERT INTO family_members 
            (person_code, parent_code, name, name_hanja, gender, generation, 
             sibling_order, child_count, birth_date, death_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $person_code, $parent_code, $name, $name_hanja, $gender, 
            $generation, $sibling_order, $child_count, $birth_date, $death_date
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '추가 실패: ' . $e->getMessage()];
    }
}

/**
 * 인물 수정 함수
 */
function editPerson() {
    try {
        $db = getDB();
        
        $id = $_POST['edit_id'] ?? '';
        $person_code = $_POST['edit_person_code'] ?? '';
        $parent_code = $_POST['edit_parent_code'] ?? null;
        $name = $_POST['edit_name'] ?? '';
        $name_hanja = $_POST['edit_name_hanja'] ?? '';
        $gender = $_POST['edit_gender'] ?? '';
        $generation = $_POST['edit_generation'] ?? '';
        $sibling_order = $_POST['edit_sibling_order'] ?? 1;
        $child_count = $_POST['edit_child_count'] ?? 0;
        $birth_date = $_POST['edit_birth_date'] ?? null;
        $death_date = $_POST['edit_death_date'] ?? null;
        
        // 필수 필드 검증
        if (empty($id) || empty($person_code) || empty($name) || empty($gender) || empty($generation)) {
            return ['success' => false, 'message' => '필수 정보를 모두 입력해주세요.'];
        }
        
        // 빈 날짜를 NULL로 처리
        if (empty($birth_date)) $birth_date = null;
        if (empty($death_date)) $death_date = null;
        if (empty($parent_code)) $parent_code = null;
        
        $stmt = $db->prepare("
            UPDATE family_members SET 
                person_code = ?, parent_code = ?, name = ?, name_hanja = ?, 
                gender = ?, generation = ?, sibling_order = ?, child_count = ?, 
                birth_date = ?, death_date = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $person_code, $parent_code, $name, $name_hanja, $gender, 
            $generation, $sibling_order, $child_count, $birth_date, $death_date, $id
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '수정 실패: ' . $e->getMessage()];
    }
}

/**
 * 인물 삭제 함수
 */
function deletePerson() {
    try {
        $db = getDB();
        
        $id = $_POST['delete_id'] ?? '';
        
        if (empty($id)) {
            return ['success' => false, 'message' => 'ID가 필요합니다.'];
        }
        
        // 자녀가 있는지 확인
        $person_result = $person->getPersonById($id);
        if ($person_result['success']) {
            $person_data = $person_result['data']['person'];
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM family_members WHERE parent_code = ?");
            $checkStmt->execute([$person_data['person_code']]);
            
            if ($checkStmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => '자녀가 있는 인물은 삭제할 수 없습니다.'];
            }
        }
        
        $stmt = $db->prepare("DELETE FROM family_members WHERE id = ?");
        $stmt->execute([$id]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '삭제 실패: ' . $e->getMessage()];
    }
}

// 최근 추가된 인물들
if ($is_admin) {
    $recent_persons_result = $person->getAllPersons(1, 20);
    $recent_persons = $recent_persons_result['success'] ? $recent_persons_result['data'] : [];
    
    // 통계 정보
    $stats_result = $person->getGenealogyStats();
    $stats = $stats_result['success'] ? $stats_result['data'] : [];
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 페이지 - 창녕조씨 족보 시스템</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        .admin-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .admin-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .recent-person {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #3498db;
        }
        
        .login-card {
            max-width: 400px;
            margin: 5rem auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .form-floating label {
            color: #6c757d;
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            border: none;
            border-radius: 25px;
        }
        
        .btn-success {
            background: linear-gradient(45deg, #27ae60, #229954);
            border: none;
            border-radius: 25px;
        }
        
        .btn-warning {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            border: none;
            border-radius: 25px;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>

<body class="bg-light">
    <?php if (!$is_admin): ?>
        <!-- 로그인 폼 -->
        <div class="container">
            <div class="login-card">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock display-4 text-danger mb-3"></i>
                        <h3 class="fw-bold">관리자 로그인</h3>
                        <p class="text-muted">족보 관리를 위해 인증이 필요합니다</p>
                    </div>
                    
                    <?php if (isset($login_error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?= $login_error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <label for="password">관리자 비밀번호</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="admin_login" class="btn btn-danger btn-lg">
                                <i class="bi bi-key"></i> 로그인
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="index.php" class="text-decoration-none">
                            <i class="bi bi-arrow-left"></i> 메인으로 돌아가기
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php include 'views/header.php'; ?>
        
        <!-- 관리자 헤더 -->
        <div class="admin-header">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="display-6 fw-bold mb-2">
                            <i class="bi bi-gear-fill"></i> 관리자 페이지
                        </h1>
                        <p class="lead mb-0">창녕조씨 족보 데이터를 관리하세요</p>
                    </div>
                    <div>
                        <a href="?logout=1" class="btn btn-outline-light">
                            <i class="bi bi-box-arrow-right"></i> 로그아웃
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- 메시지 표시 -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- 통계 카드들 -->
                <div class="col-md-3 mb-4">
                    <div class="stat-card">
                        <i class="bi bi-people display-6 mb-2"></i>
                        <h3 class="fw-bold"><?= number_format($stats['total_persons'] ?? 0) ?></h3>
                        <p class="mb-0">총 인원</p>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="stat-card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%);">
                        <i class="bi bi-heart display-6 mb-2"></i>
                        <h3 class="fw-bold"><?= number_format($stats['alive_persons'] ?? 0) ?></h3>
                        <p class="mb-0">생존 추정</p>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="stat-card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                        <i class="bi bi-diagram-2 display-6 mb-2"></i>
                        <h3 class="fw-bold"><?= count($stats['by_generation'] ?? []) ?></h3>
                        <p class="mb-0">활성 세대</p>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="stat-card" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                        <i class="bi bi-clock-history display-6 mb-2"></i>
                        <h3 class="fw-bold">5</h3>
                        <p class="mb-0">최근 등록</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- 빠른 작업 -->
                <div class="col-lg-8">
                    <div class="card admin-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> 빠른 작업</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-grid">
                                        <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addPersonModal">
                                            <i class="bi bi-person-plus"></i> 인물 추가
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-grid">
                                        <button type="button" class="btn btn-warning btn-lg" onclick="showBulkImport()">
                                            <i class="bi bi-upload"></i> 일괄 가져오기
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-grid">
                                        <a href="advanced_search.php" class="btn btn-info btn-lg">
                                            <i class="bi bi-search"></i> 고급 검색
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-grid">
                                        <button type="button" class="btn btn-secondary btn-lg" onclick="showBackup()">
                                            <i class="bi bi-download"></i> 백업/복원
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 최근 활동 -->
                <div class="col-lg-4">
                    <div class="card admin-card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-clock"></i> 최근 등록</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_persons)): ?>
                                <?php foreach (array_slice($recent_persons, 0, 5) as $rp): ?>
                                    <div class="recent-person">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($rp['name']) ?></strong>
                                            <small class="text-muted"><?= $rp['generation'] ?>세</small>
                                        </div>
                                        <small class="text-muted">
                                            <?= $rp['gender'] ?> • 
                                            <?= $rp['child_count'] ?>자녀
                                            <button class="btn btn-sm btn-outline-primary ms-2" onclick="editPerson(<?= $rp['id'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">최근 등록된 인물이 없습니다.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 세대별 통계 차트 -->
            <div class="row">
                <div class="col-12">
                    <div class="card admin-card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> 세대별 인구 분포</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="generationChart" width="400" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 인물 추가 모달 -->
        <div class="modal fade" id="addPersonModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus"></i> 새 인물 추가</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_person">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="person_code" name="person_code" required>
                                        <label for="person_code">인물코드 *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="parent_code" name="parent_code">
                                        <label for="parent_code">부모코드</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="name" name="name" required>
                                        <label for="name">이름 *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="name_hanja" name="name_hanja">
                                        <label for="name_hanja">한자명</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">선택</option>
                                            <option value="남">남성</option>
                                            <option value="여">여성</option>
                                        </select>
                                        <label for="gender">성별 *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="generation" name="generation" min="1" max="50" required>
                                        <label for="generation">세대 *</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="sibling_order" name="sibling_order" min="1" value="1">
                                        <label for="sibling_order">형제순서</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="child_count" name="child_count" min="0" value="0">
                                        <label for="child_count">자녀 수</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="birth_date" name="birth_date">
                                        <label for="birth_date">생년월일</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="death_date" name="death_date">
                                        <label for="death_date">사망일</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check"></i> 추가
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 인물 수정 모달 -->
        <div class="modal fade" id="editPersonModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> 인물 정보 수정</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body" id="editPersonForm">
                            <!-- 동적으로 로드 -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-check"></i> 수정
                            </button>
                            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                                <i class="bi bi-trash"></i> 삭제
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include 'views/footer.php'; ?>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <?php if ($is_admin): ?>
    <script>
        // 세대별 차트
        const generationData = <?= json_encode($stats['by_generation'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
        
        if (generationData.length > 0) {
            const ctx = document.getElementById('generationChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: generationData.map(item => item.generation + '세대'),
                    datasets: [{
                        label: '인구 수',
                        data: generationData.map(item => item.count),
                        backgroundColor: 'rgba(52, 152, 219, 0.8)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // 인물 수정 함수
        function editPerson(id) {
            // AJAX로 인물 정보 로드
            fetch(`api/get_person.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const person = data.data.person;
                        const formHtml = `
                            <input type="hidden" name="action" value="edit_person">
                            <input type="hidden" name="edit_id" value="${person.id}">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="edit_person_code" value="${person.person_code || ''}" required>
                                        <label>인물코드 *</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="edit_parent_code" value="${person.parent_code || ''}">
                                        <label>부모코드</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="edit_name" value="${person.name || ''}" required>
                                        <label>이름 *</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="edit_name_hanja" value="${person.name_hanja || ''}">
                                        <label>한자명</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="edit_gender" required>
                                            <option value="남" ${person.gender === '남' ? 'selected' : ''}>남성</option>
                                            <option value="여" ${person.gender === '여' ? 'selected' : ''}>여성</option>
                                        </select>
                                        <label>성별 *</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" name="edit_generation" value="${person.generation || ''}" min="1" max="50" required>
                                        <label>세대 *</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" name="edit_sibling_order" value="${person.sibling_order || 1}" min="1">
                                        <label>형제순서</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" name="edit_child_count" value="${person.child_count || 0}" min="0">
                                        <label>자녀 수</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" name="edit_birth_date" value="${person.birth_date || ''}">
                                        <label>생년월일</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" name="edit_death_date" value="${person.death_date || ''}">
                                        <label>사망일</label>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('editPersonForm').innerHTML = formHtml;
                        const modal = new bootstrap.Modal(document.getElementById('editPersonModal'));
                        modal.show();
                    }
                })
                .catch(error => {
                    alert('인물 정보를 불러오는데 실패했습니다.');
                });
        }
        
        // 삭제 확인
        function confirmDelete() {
            if (confirm('정말로 이 인물을 삭제하시겠습니까?\n자녀가 있는 인물은 삭제할 수 없습니다.')) {
                const form = document.querySelector('#editPersonModal form');
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'action';
                deleteInput.value = 'delete_person';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'delete_id';
                idInput.value = form.querySelector('input[name="edit_id"]').value;
                
                form.appendChild(deleteInput);
                form.appendChild(idInput);
                form.submit();
            }
        }
        
        function showBulkImport() {
            alert('일괄 가져오기 기능은 향후 업데이트 예정입니다.');
        }
        
        function showBackup() {
            alert('백업/복원 기능은 향후 업데이트 예정입니다.');
        }
    </script>
    <?php endif; ?>
</body>
</html>