<?php
/**
 * 빠른 족보 데이터 테스트
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🏮 창녕조씨 족보 데이터 확인</h2>";

try {
    $host = 'localhost';
    $user = 'cyjc25';
    $pass = 'whdudrnr!!70';
    $dbname = 'cyjc25';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<div style='background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;'>";
    echo "<h3>✅ DB 연결 성공!</h3>";
    
    // family_members 테이블 확인
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM family_members");
    $total = $stmt->fetch()['total'];
    echo "<p><strong>총 족보 인원:</strong> " . number_format($total) . "명</p>";
    
    // 샘플 데이터 5명
    $stmt = $pdo->query("
        SELECT name, name_hanja, generation, birth_date 
        FROM family_members 
        ORDER BY id ASC 
        LIMIT 5
    ");
    $samples = $stmt->fetchAll();
    
    echo "<h4>📝 족보 데이터 샘플:</h4>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>이름</th><th>한자명</th><th>세대</th><th>생년</th></tr>";
    
    foreach ($samples as $person) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($person['name']) . "</td>";
        echo "<td>" . htmlspecialchars($person['name_hanja']) . "</td>";
        echo "<td>" . $person['generation'] . "세대</td>";
        echo "<td>" . htmlspecialchars($person['birth_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 세대별 통계
    $stmt = $pdo->query("
        SELECT generation, COUNT(*) as count 
        FROM family_members 
        GROUP BY generation 
        ORDER BY generation 
        LIMIT 10
    ");
    $generations = $stmt->fetchAll();
    
    echo "<h4>📊 세대별 인원 (상위 10개):</h4>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>세대</th><th>인원</th></tr>";
    
    foreach ($generations as $gen) {
        echo "<tr>";
        echo "<td>" . $gen['generation'] . "세대</td>";
        echo "<td>" . number_format($gen['count']) . "명</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "</div>";
    
    echo "<div style='background:#cce5ff;padding:15px;border-radius:5px;margin:10px 0;'>";
    echo "<h3>🎉 족보 시스템 준비 완료!</h3>";
    echo "<p><a href='index.php'>👉 메인 족보 시스템 바로가기</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;'>";
    echo "<h3>❌ 오류 발생</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>