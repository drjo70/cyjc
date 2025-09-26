<?php
// 접속 로그 수집 및 분석 시스템
// 모든 페이지에서 include하여 방문자 추적

// 데이터베이스 연결 정보 (config.php와 동일)
function getDbConnection() {
    $db_config = [
        'host' => 'localhost',
        'dbname' => 'cyjc25',
        'username' => 'cyjc25',
        'password' => 'whdudrnr!!70'
    ];
    
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
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB 연결 실패 (access_logger): " . $e->getMessage());
        return null;
    }
}

// 실제 IP 주소 가져오기 (프록시, CDN 환경 고려)
function getRealIpAddress() {
    $ip_keys = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'HTTP_CLIENT_IP',            // Proxy
        'REMOTE_ADDR'                // Standard
    ];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            
            // 유효한 IP인지 확인
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// User Agent 파싱 (디바이스, 브라우저, OS 정보 추출)
function parseUserAgent($user_agent) {
    $result = [
        'device_type' => 'unknown',
        'browser' => 'unknown',
        'os' => 'unknown'
    ];
    
    if (empty($user_agent)) {
        return $result;
    }
    
    $user_agent = strtolower($user_agent);
    
    // 봇 감지
    if (preg_match('/bot|crawler|spider|scraper|facebookexternalhit|twitterbot|linkedinbot|googlebot|bingbot|yandexbot/', $user_agent)) {
        $result['device_type'] = 'bot';
        $result['browser'] = 'bot';
        return $result;
    }
    
    // 디바이스 타입 감지
    if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/', $user_agent)) {
        $result['device_type'] = 'mobile';
    } elseif (preg_match('/tablet|ipad/', $user_agent)) {
        $result['device_type'] = 'tablet';
    } else {
        $result['device_type'] = 'desktop';
    }
    
    // 브라우저 감지
    if (preg_match('/edge|edg/', $user_agent)) {
        $result['browser'] = 'Edge';
    } elseif (preg_match('/chrome/', $user_agent)) {
        $result['browser'] = 'Chrome';
    } elseif (preg_match('/firefox/', $user_agent)) {
        $result['browser'] = 'Firefox';
    } elseif (preg_match('/safari/', $user_agent)) {
        $result['browser'] = 'Safari';
    } elseif (preg_match('/opera|opr/', $user_agent)) {
        $result['browser'] = 'Opera';
    } elseif (preg_match('/msie|trident/', $user_agent)) {
        $result['browser'] = 'Internet Explorer';
    }
    
    // 운영체제 감지
    if (preg_match('/windows nt/', $user_agent)) {
        $result['os'] = 'Windows';
    } elseif (preg_match('/mac os x/', $user_agent)) {
        $result['os'] = 'macOS';
    } elseif (preg_match('/linux/', $user_agent)) {
        $result['os'] = 'Linux';
    } elseif (preg_match('/android/', $user_agent)) {
        $result['os'] = 'Android';
    } elseif (preg_match('/ios|iphone|ipad/', $user_agent)) {
        $result['os'] = 'iOS';
    }
    
    return $result;
}

// 페이지 접속 로그 기록
function logPageAccess($page_url = null, $user_id = null) {
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        // 현재 페이지 URL 결정
        if (!$page_url) {
            $page_url = $_SERVER['REQUEST_URI'] ?? '/';
        }
        
        // 기본 정보 수집
        $ip_address = getRealIpAddress();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $session_id = session_id() ?: null;
        
        // User Agent 파싱
        $ua_info = parseUserAgent($user_agent);
        
        // 로그인 사용자 ID 확인
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        // 중복 방문 체크 (같은 세션, 같은 페이지, 10분 이내)
        $stmt = $pdo->prepare("
            SELECT id FROM access_logs 
            WHERE session_id = ? AND page_url = ? AND access_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            LIMIT 1
        ");
        $stmt->execute([$session_id, $page_url]);
        
        if ($stmt->fetch()) {
            return true; // 중복 방문은 기록하지 않음
        }
        
        // 접속 로그 삽입
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (
                session_id, ip_address, user_agent, page_url, referrer, user_id,
                device_type, browser, os, access_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $session_id,
            $ip_address,
            $user_agent,
            $page_url,
            $referrer,
            $user_id,
            $ua_info['device_type'],
            $ua_info['browser'],
            $ua_info['os']
        ]);
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("접속 로그 기록 실패: " . $e->getMessage());
        return false;
    }
}

// 접속 통계 데이터 조회
function getAccessStats($days = 30) {
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }
    
    try {
        $stats = [];
        
        // 1. 기본 통계 (지정된 일수)
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_visits,
                COUNT(DISTINCT ip_address) as unique_visitors,
                COUNT(DISTINCT session_id) as unique_sessions,
                COUNT(CASE WHEN user_id IS NOT NULL THEN 1 END) as logged_in_visits,
                AVG(visit_duration) as avg_duration
            FROM access_logs 
            WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $stats['basic'] = $stmt->fetch();
        
        // 2. 일별 방문자 수 (차트용)
        $stmt = $pdo->prepare("
            SELECT 
                DATE(access_time) as date,
                COUNT(*) as visits,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM access_logs 
            WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(access_time)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        $stats['daily'] = $stmt->fetchAll();
        
        // 3. 페이지별 접속 통계 (상위 10개)
        $stmt = $pdo->prepare("
            SELECT 
                page_url,
                COUNT(*) as visits,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM access_logs 
            WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY page_url
            ORDER BY visits DESC
            LIMIT 10
        ");
        $stmt->execute([$days]);
        $stats['pages'] = $stmt->fetchAll();
        
        // 4. 디바이스별 통계
        $stmt = $pdo->prepare("
            SELECT 
                device_type,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / (
                    SELECT COUNT(*) FROM access_logs 
                    WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ), 1) as percentage
            FROM access_logs 
            WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY device_type
            ORDER BY visits DESC
        ");
        $stmt->execute([$days, $days]);
        $stats['devices'] = $stmt->fetchAll();
        
        // 5. 브라우저별 통계
        $stmt = $pdo->prepare("
            SELECT 
                browser,
                COUNT(*) as visits,
                ROUND(COUNT(*) * 100.0 / (
                    SELECT COUNT(*) FROM access_logs 
                    WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ), 1) as percentage
            FROM access_logs 
            WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY) AND browser != 'unknown'
            GROUP BY browser
            ORDER BY visits DESC
            LIMIT 5
        ");
        $stmt->execute([$days, $days]);
        $stats['browsers'] = $stmt->fetchAll();
        
        // 6. 시간대별 접속 패턴
        $stmt = $pdo->prepare("
            SELECT 
                HOUR(access_time) as hour,
                COUNT(*) as visits
            FROM access_logs 
            WHERE access_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY HOUR(access_time)
            ORDER BY hour
        ");
        $stmt->execute([$days]);
        $stats['hourly'] = $stmt->fetchAll();
        
        // 7. 실시간 접속자 (최근 5분)
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT ip_address) as online_users
            FROM access_logs 
            WHERE access_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stats['realtime'] = $stmt->fetch();
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("통계 조회 실패: " . $e->getMessage());
        return null;
    }
}

// 자동 로그 기록 (이 파일이 include 될 때 실행)
if (!defined('DISABLE_ACCESS_LOG')) {
    // 세션이 시작되지 않았다면 시작
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 로그 기록
    logPageAccess();
}
?>