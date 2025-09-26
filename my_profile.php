<?php
/**
 * 닥터조님 개인 족보 프로필 페이지
 * 
 * @author 닥터조 (주)조유
 * @version 1.0
 * @date 2024-09-17
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB 연결
require_once 'config/database.php';

try {
    $db = getDB();
    
    // 닥터조님 연락처로 검색
    $phone = '010-9272-9081';
    $stmt = $db->prepare("
        SELECT * FROM family_members 
        WHERE REPLACE(REPLACE(REPLACE(person_code, '-', ''), ' ', ''), '.', '') 
        LIKE ? OR name LIKE '%조%'
        ORDER BY generation DESC
        LIMIT 10
    ");
    $stmt->execute(["%{$phone}%"]);
    $candidates = $stmt->fetchAll();
    
    // 시조 정보
    $founderStmt = $db->query("
        SELECT * FROM family_members 
        WHERE generation = 1 
        ORDER BY id ASC 
        LIMIT 1
    ");
    $founder = $founderStmt->fetch();
    
    // 최신 세대 정보
    $latestGenStmt = $db->query("
        SELECT MAX(generation) as max_gen FROM family_members
    ");
    $maxGen = $latestGenStmt->fetch()['max_gen'];
    
    // 전체 통계
    $statsStmt = $db->query("SELECT COUNT(*) as total FROM family_members");
    $totalMembers = $statsStmt->fetch()['total'];
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>닥터조님 개인 족보 프로필</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Noto Sans KR', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 2rem 0;
        }
        
        .profile-header {
            background: linear-gradient(45deg, #f39c12, #e74c3c);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 4rem;
        }
        
        .profile-body {
            padding: 2rem;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
            border-left: 5px solid #007bff;
        }
        
        .lineage-tree {
            background: linear-gradient(45deg, #74b9ff, #0984e3);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 1rem 0;
            text-align: center;
        }
        
        .generation-badge {
            background: linear-gradient(45deg, #fd79a8, #e84393);
            color: white;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
            margin: 0.2rem;
        }
        
        .contact-info {
            background: linear-gradient(45deg, #00b894, #00cec9);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .clan-info {
            background: linear-gradient(45deg, #6c5ce7, #a29bfe);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .btn-custom {
            background: linear-gradient(45deg, #3498db, #2980b9);
            border: none;
            border-radius: 25px;
            color: white;
            padding: 0.7rem 1.5rem;
            font-weight: 500;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- 헤더 -->
        <div class="text-center mb-4">
            <a href="index.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left"></i> 메인 족보로 돌아가기
            </a>
        </div>

        <!-- 프로필 카드 -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h1><strong>닥터조</strong></h1>
                <p class="mb-1">(주)조유 대표이사</p>
                <p class="mb-0"><i class="fas fa-graduation-cap"></i> IT 박사 | 컨설팅 전문가 | 프로그램 개발</p>
            </div>

            <div class="profile-body">
                <!-- 연락처 정보 -->
                <div class="contact-info">
                    <h4><i class="fas fa-phone"></i> 연락처 정보</h4>
                    <p class="mb-1"><i class="fas fa-mobile-alt"></i> 010-9272-9081</p>
                    <p class="mb-0"><i class="fas fa-building"></i> (주)조유 - 컴퓨터 IT 박사, 컨설팅 전문</p>
                </div>

                <!-- 창녕조씨 정보 -->
                <div class="clan-info">
                    <h4><i class="fas fa-tree"></i> 창녕조씨 족보</h4>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h5><?= number_format($totalMembers ?? 0) ?>명</h5>
                            <small>전체 족보</small>
                        </div>
                        <div class="col-md-3">
                            <h5><?= $maxGen ?? 0 ?>세대</h5>
                            <small>최신 세대</small>
                        </div>
                        <div class="col-md-3">
                            <h5><?= $founder['name'] ?? '조계룡' ?></h5>
                            <small>시조</small>
                        </div>
                        <div class="col-md-3">
                            <h5>2024년</h5>
                            <small>시스템 구축</small>
                        </div>
                    </div>
                </div>

                <!-- 혈통 정보 -->
                <div class="lineage-tree">
                    <h4><i class="fas fa-sitemap"></i> 혈통 정보</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>시조 (1세대)</h6>
                            <p class="mb-1"><strong><?= $founder['name'] ?? '조계룡' ?></strong></p>
                            <p><small><?= $founder['name_hanja'] ?? '趙季龍' ?></small></p>
                        </div>
                        <div class="col-md-6">
                            <h6>현재 추정 세대</h6>
                            <p class="mb-1"><strong>약 30-40세대</strong></p>
                            <p><small>정확한 세대 확인 필요</small></p>
                        </div>
                    </div>
                    <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                    <p class="mb-0"><i class="fas fa-info-circle"></i> 정확한 혈통 정보는 족보 전문가와 상담 후 확정</p>
                </div>

                <?php if (!empty($candidates)): ?>
                <!-- 추정 관련 인물 -->
                <div class="info-card">
                    <h5><i class="fas fa-search"></i> 추정 관련 인물들</h5>
                    <p class="text-muted">동일 성씨 또는 유사한 정보를 가진 족보 인물들:</p>
                    
                    <div class="row">
                        <?php foreach ($candidates as $person): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <?= htmlspecialchars($person['name']) ?>
                                        <?php if (!empty($person['name_hanja'])): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($person['name_hanja']) ?>)</small>
                                        <?php endif; ?>
                                    </h6>
                                    <span class="generation-badge"><?= $person['generation'] ?>세대</span>
                                    <?php if (!empty($person['birth_date'])): ?>
                                    <p class="card-text small">생: <?= htmlspecialchars($person['birth_date']) ?></p>
                                    <?php endif; ?>
                                    <a href="?page=person&code=<?= $person['person_code'] ?>" class="btn btn-sm btn-outline-primary">상세보기</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 전문 분야 -->
                <div class="info-card">
                    <h5><i class="fas fa-briefcase"></i> 전문 분야</h5>
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-primary text-white rounded-circle mx-auto mb-2" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-laptop-code fa-lg"></i>
                            </div>
                            <h6>IT 개발</h6>
                            <small>프로그램 개발 전문</small>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-success text-white rounded-circle mx-auto mb-2" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chart-line fa-lg"></i>
                            </div>
                            <h6>컨설팅</h6>
                            <small>비즈니스 컨설팅 전문</small>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-warning text-white rounded-circle mx-auto mb-2" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-graduation-cap fa-lg"></i>
                            </div>
                            <h6>연구</h6>
                            <small>IT 박사 학위</small>
                        </div>
                    </div>
                </div>

                <!-- 족보 시스템 개발 정보 -->
                <div class="info-card">
                    <h5><i class="fas fa-code"></i> 족보 시스템 개발 현황</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-check-circle text-success"></i> 완성된 기능</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> 600+ 명 족보 데이터베이스</li>
                                <li><i class="fas fa-check text-success"></i> 45세대 완전 관리</li>
                                <li><i class="fas fa-check text-success"></i> 실시간 검색 시스템</li>
                                <li><i class="fas fa-check text-success"></i> 가족 관계도 시각화</li>
                                <li><i class="fas fa-check text-success"></i> 모바일 반응형 디자인</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-cog text-primary"></i> 기술 스택</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-code text-primary"></i> PHP 8.2 + MySQL</li>
                                <li><i class="fas fa-paint-brush text-primary"></i> Bootstrap 5 + CSS3</li>
                                <li><i class="fas fa-server text-primary"></i> Cafe24 웹호스팅</li>
                                <li><i class="fas fa-database text-primary"></i> UTF8MB4 한글 지원</li>
                                <li><i class="fas fa-mobile-alt text-primary"></i> 반응형 웹 디자인</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 액션 버튼들 -->
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-custom me-2">
                        <i class="fas fa-home"></i> 메인 족보 보기
                    </a>
                    <a href="?page=persons&search=조" class="btn btn-custom me-2">
                        <i class="fas fa-search"></i> 조씨 성씨 검색
                    </a>
                    <a href="?page=generation&start=1&end=10" class="btn btn-custom">
                        <i class="fas fa-layer-group"></i> 세대별 족보
                    </a>
                </div>
            </div>
        </div>

        <!-- 시스템 정보 -->
        <div class="text-center text-light mt-4">
            <p><i class="fas fa-star"></i> 창녕조씨 족보시스템 v1.0 | 개발: 닥터조 (주)조유 | 2024.09.17</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>