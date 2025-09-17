<?php
/**
 * 창녕조씨 족보 시스템 - 데이터베이스 설정
 * Cafe24 호스팅 환경용 MySQL 연결 설정
 * 
 * @author 닥터조 ((주)조유 대표이사)
 * @version 3.0
 * @since 2024-09-17
 */

// 데이터베이스 연결 설정
// Cafe24에서 제공하는 데이터베이스 정보로 수정 필요
define('DB_HOST', 'localhost');  // 또는 Cafe24에서 제공하는 호스트
define('DB_NAME', 'changnyeong_jo');  // 데이터베이스명
define('DB_USER', 'root');  // 사용자명 (Cafe24에서 제공)
define('DB_PASS', '');  // 비밀번호 (Cafe24에서 제공)
define('DB_CHARSET', 'utf8mb4');

// 에러 리포팅 설정 (운영환경에서는 Off)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 타임존 설정
date_default_timezone_set('Asia/Seoul');

// 보안 설정
define('SECURE_KEY', 'changnyeong_jo_2024_secure_key_12345');
define('HASH_ALGO', 'sha256');

/**
 * 데이터베이스 연결 클래스
 */
class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("데이터베이스 연결에 실패했습니다. 관리자에게 문의하세요.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * 안전한 쿼리 실행
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }
    
    /**
     * 단일 레코드 조회
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 다중 레코드 조회
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * INSERT 실행 후 ID 반환
     */
    public function insert($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }
    
    /**
     * UPDATE/DELETE 실행 후 영향받은 행 수 반환
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
}

/**
 * 전역 데이터베이스 인스턴스 가져오기
 */
function getDB() {
    return Database::getInstance();
}

/**
 * SQL 인젝션 방지 함수
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * 패스워드 해시 함수
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * 패스워드 검증 함수
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 디버그 로그 함수
 */
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    error_log($logMessage);
}

// 연결 테스트 (개발 환경에서만)
if (isset($_GET['test_db']) && $_GET['test_db'] === 'true') {
    try {
        $db = getDB();
        $result = $db->fetchOne("SELECT 1 as test");
        if ($result) {
            echo json_encode([
                'status' => 'success',
                'message' => '데이터베이스 연결 성공',
                'php_version' => phpversion(),
                'mysql_version' => $db->fetchOne("SELECT VERSION() as version")['version'],
                'charset' => DB_CHARSET,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => '데이터베이스 연결 실패: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>