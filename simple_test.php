<?php
/**
 * 간단한 DB 연결 테스트
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Cafe24 DB 연결 테스트</h1>";

// DB 정보
$host = 'localhost';
$user = 'cyjc25';
$pass = 'whdudrnr!!70';
$dbname = 'cyjc25';

echo "<p><strong>시도하는 DB 정보:</strong></p>";
echo "<ul>";
echo "<li>호스트: $host</li>";
echo "<li>사용자: $user</li>";
echo "<li>DB명: $dbname</li>";
echo "</ul>";

// 1. PDO 확장 확인
if (!extension_loaded('pdo')) {
    echo "<p style='color:red'>❌ PDO 확장이 설치되지 않았습니다!</p>";
    exit;
}

if (!extension_loaded('pdo_mysql')) {
    echo "<p style='color:red'>❌ PDO MySQL 확장이 설치되지 않았습니다!</p>";
    exit;
}

echo "<p style='color:green'>✅ PDO MySQL 확장 확인됨</p>";

// 2. DB 연결 시도
try {
    echo "<p>🔄 DB 연결 시도 중...</p>";
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<p style='color:green'>✅ DB 연결 성공!</p>";
    
    // 3. 테이블 확인
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>발견된 테이블 (" . count($tables) . "개):</strong></p>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange'>⚠️ 테이블이 없습니다. SQL 데이터를 먼저 임포트하세요.</p>";
    }
    
    // 4. persons 테이블 확인 (있다면)
    if (in_array('persons', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM persons");
        $count = $stmt->fetch()['count'];
        echo "<p style='color:green'>✅ persons 테이블: $count 명의 데이터</p>";
        
        // 샘플 데이터 1개
        $stmt = $pdo->query("SELECT name, generation FROM persons LIMIT 1");
        $sample = $stmt->fetch();
        if ($sample) {
            echo "<p><strong>샘플:</strong> {$sample['name']} ({$sample['generation']}세대)</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ DB 연결 실패:</p>";
    echo "<pre style='background:#f8d7da;padding:10px;border-radius:5px;'>";
    echo htmlspecialchars($e->getMessage());
    echo "</pre>";
    
    // 다른 호스트명들을 제안
    echo "<h3>🔧 다른 호스트명을 시도해보세요:</h3>";
    echo "<ul>";
    echo "<li>mysql.cafe24.com</li>";
    echo "<li>db.cafe24.com</li>";
    echo "<li>cyjc25.db.cafe24.com</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='index.html'>← 돌아가기</a> | <a href='index.php'>메인 시스템</a></p>";
?>