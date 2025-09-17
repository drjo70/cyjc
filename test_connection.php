<?php
/**
 * 다양한 DB 호스트로 연결 테스트
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$user = 'cyjc25';
$pass = 'whdudrnr!!70';
$dbname = 'cyjc25';

// 시도할 호스트들
$hosts = [
    'localhost',
    '127.0.0.1',
    'mysql.cafe24.com',
    'db.cafe24.com',
    'cyjc25.mysql.cafe24.com',
    'cyjc25.db.cafe24.com'
];

echo "<h2>🔧 DB 연결 테스트 (모든 호스트)</h2>";
echo "<p><strong>DB 정보:</strong> 사용자=$user, DB명=$dbname</p><hr>";

foreach ($hosts as $host) {
    echo "<h3>🔍 테스트 중: <code>$host</code></h3>";
    
    try {
        $start_time = microtime(true);
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5  // 5초 타임아웃
        ]);
        
        $end_time = microtime(true);
        $connection_time = round(($end_time - $start_time) * 1000, 2);
        
        echo "<div style='background:#d4edda;color:#155724;padding:10px;border-radius:5px;margin:10px 0;'>";
        echo "✅ <strong>연결 성공!</strong><br>";
        echo "• 연결 시간: {$connection_time}ms<br>";
        
        // 테이블 확인
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "• 테이블 수: " . count($tables) . "개<br>";
        
        if (in_array('family_members', $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM family_members");
            $count = $stmt->fetch()['count'];
            echo "• family_members: $count 명<br>";
        }
        
        echo "<strong>👉 이 호스트를 사용하세요: <code>$host</code></strong>";
        echo "</div>";
        
        // 첫 번째 성공한 호스트만 사용
        break;
        
    } catch (PDOException $e) {
        echo "<div style='background:#f8d7da;color:#721c24;padding:10px;border-radius:5px;margin:10px 0;'>";
        echo "❌ <strong>연결 실패:</strong><br>";
        echo "• 오류: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "</div>";
    }
}

echo "<hr>";
echo "<p><a href='index.html'>← 돌아가기</a> | <a href='simple_test.php'>간단 테스트</a></p>";
?>