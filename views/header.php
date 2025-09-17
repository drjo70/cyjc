<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? '창녕조씨 족보 시스템' ?> | 창녕조씨 족보</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#667eea">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="창녕조씨족보">
    <meta name="msapplication-TileColor" content="#667eea">
    <meta name="msapplication-tap-highlight" content="no">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="72x72" href="assets/icon-72x72.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="128x128" href="assets/icon-128x128.png">
    <link rel="icon" type="image/png" sizes="144x144" href="assets/icon-144x144.png">
    <link rel="icon" type="image/png" sizes="152x152" href="assets/icon-152x152.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="384x384" href="assets/icon-384x384.png">
    <link rel="icon" type="image/png" sizes="512x512" href="assets/icon-512x512.png">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="assets/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="72x72" href="assets/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="assets/icon-96x96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="assets/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icon-192x192.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts - Noto Sans KR -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- 커스텀 CSS -->
    <style>
        body {
            font-family: 'Noto Sans KR', sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: #2c3e50 !important;
        }
        
        .main-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: linear-gradient(45deg, #f39c12, #e74c3c);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #3498db, #2980b9);
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
        }
        
        .btn-outline-primary {
            border: 2px solid #3498db;
            color: #3498db;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
        }
        
        .btn-outline-primary:hover {
            background: #3498db;
            border-color: #3498db;
        }
        
        .badge {
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .pagination .page-link {
            border-radius: 20px;
            margin: 0 2px;
            border: none;
            color: #3498db;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(45deg, #3498db, #2980b9);
            border: none;
        }
        
        .search-box {
            background: white;
            border-radius: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .genealogy-tree {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 1rem 0;
        }
        
        .person-card {
            background: linear-gradient(45deg, #74b9ff, #0984e3);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .person-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .generation-badge {
            background: linear-gradient(45deg, #fd79a8, #e84393);
            color: white;
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .error-box {
            background: #ffe6e6;
            border: 1px solid #ff9999;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            color: #cc0000;
        }
        
        .success-box {
            background: #e6ffe6;
            border: 1px solid #99ff99;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            color: #006600;
        }
    </style>
</head>
<body>
    <!-- 네비게이션 바 -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="?page=dashboard">
                <i class="fas fa-tree me-2"></i>
                창녕조씨 족보시스템
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= ($_GET['page'] ?? 'dashboard') === 'dashboard' ? 'active' : '' ?>" 
                           href="?page=dashboard">
                            <i class="fas fa-home"></i> 대시보드
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'persons' ? 'active' : '' ?>" 
                           href="?page=persons">
                            <i class="fas fa-users"></i> 인물 목록
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'generation' ? 'active' : '' ?>" 
                           href="?page=generation">
                            <i class="fas fa-layer-group"></i> 세대별 족보
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="advanced_search.php">
                            <i class="fas fa-search-plus"></i> 고급 검색
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="family_tree.php">
                            <i class="fas fa-project-diagram"></i> 가계도
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> 관리
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admin.php"><i class="fas fa-user-shield"></i> 관리자 페이지</a></li>
                            <li><a class="dropdown-item" href="my_profile.php"><i class="fas fa-user"></i> 내 프로필</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?page=api&action=stats"><i class="fas fa-chart-bar"></i> 통계</a></li>
                        </ul>
                    </li>
                </ul>
                
                <!-- 검색 바 -->
                <form class="d-flex search-box" method="GET" action="">
                    <input type="hidden" name="page" value="persons">
                    <input class="form-control border-0" type="search" name="search" 
                           placeholder="이름으로 검색..." value="<?= $_GET['search'] ?? '' ?>">
                    <button class="btn btn-outline-primary border-0" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- 메인 헤더 (페이지별로 다름) -->
    <?php if (isset($page_title)): ?>
    <div class="main-header">
        <div class="container text-center">
            <h1><i class="fas fa-tree"></i> <?= htmlspecialchars($page_title) ?></h1>
            <?php if (isset($error) && $error): ?>
            <div class="alert alert-danger mt-3">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 메인 컨테이너 시작 -->
    <div class="container">