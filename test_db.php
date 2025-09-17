<?php
/**
 * 창녕조씨 족보 시스템 - 데이터베이스 연결 테스트
 * 
 * 이 파일을 Cafe24에 업로드해서 DB 연결을 테스트하세요
 * URL: https://yourdomain.cafe24.com/test_db.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB 설정 불러오기
require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>창녕조씨 족보 시스템 - DB 연결 테스트</title>
    <style>
        body { 
            font-family: 'Noto Sans KR', Arial, sans-serif; 
            margin: 40px; 
            background-color: #f5f5f5; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            padding-bottom: 20px; 
            border-bottom: 2px solid #007bff; 
        }
        .success { 
            background-color: #d4edda; 
            color: #155724; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0; 
        }
        .error { 
            background-color: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0; 
        }
        .info { 
            background-color: #d1ecf1; 
            color: #0c5460; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0; 
        }
        .test-section { 
            margin: 20px 0; 
            padding: 20px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
        }
        th, td { 
            padding: 8px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background-color: #f2f2f2; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏮 창녕조씨 족보 시스템</h1>
            <h2>데이터베이스 연결 테스트</h2>
        </div>

        <?php
        echo "<div class='info'>";
        echo "<strong>📊 테스트 정보:</strong><br>";
        echo "• 테스트 시간: " . date('Y-m-d H:i:s') . "<br>";
        echo "• PHP 버전: " . PHP_VERSION . "<br>";
        echo "• 서버: " . $_SERVER['SERVER_NAME'] . "<br>";
        echo "</div>";

        // 1. DB 연결 테스트
        echo "<div class='test-section'>";
        echo "<h3>🔌 1. 데이터베이스 연결 테스트</h3>";
        
        try {
            $result = testDBConnection();
            
            if ($result['success']) {
                echo "<div class='success'>";
                echo "✅ " . $result['message'] . "<br>";
                if (isset($result['server_info'])) {
                    echo "• 서버 정보: " . $result['server_info'];
                }
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "❌ " . $result['message'];
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "❌ 연결 테스트 중 오류 발생: " . $e->getMessage();
            echo "</div>";
        }
        echo "</div>";

        // 2. 테이블 목록 조회
        echo "<div class='test-section'>";
        echo "<h3>📋 2. 족보 테이블 목록 확인</h3>";
        
        try {
            $db = DatabaseConfig::getInstance();
            $tableResult = $db->getTableList();
            
            if ($tableResult['success']) {
                echo "<div class='success'>";
                echo "✅ 총 " . $tableResult['count'] . "개의 테이블이 발견되었습니다.";
                echo "</div>";
                
                if ($tableResult['count'] > 0) {
                    echo "<table>";
                    echo "<tr><th>번호</th><th>테이블명</th><th>용도</th></tr>";
                    
                    $genealogy_tables = [
                        'family_members' => '인물 정보',
                        'families' => '가족 관계',
                        'spouses' => '배우자 정보',
                        'children' => '자녀 정보',
                        'photos' => '사진 정보',
                        'achievements' => '업적/경력',
                        'locations' => '거주지 정보',
                        'events' => '생애 이벤트',
                        'sources' => '출처 정보',
                        'lineages' => '족보 계통',
                        'phone_numbers' => '연락처',
                        'users' => '사용자 관리'
                    ];
                    
                    foreach ($tableResult['tables'] as $index => $table) {
                        $purpose = isset($genealogy_tables[$table]) ? $genealogy_tables[$table] : '기타';
                        echo "<tr>";
                        echo "<td>" . ($index + 1) . "</td>";
                        echo "<td><strong>$table</strong></td>";
                        echo "<td>$purpose</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<div class='error'>";
                    echo "❌ 족보 테이블이 없습니다. 먼저 SQL 파일을 임포트하세요.";
                    echo "</div>";
                }
            } else {
                echo "<div class='error'>";
                echo "❌ " . $tableResult['message'];
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "❌ 테이블 조회 중 오류 발생: " . $e->getMessage();
            echo "</div>";
        }
        echo "</div>";

        // 3. 샘플 데이터 조회 (family_members 테이블)
        echo "<div class='test-section'>";
        echo "<h3>👨‍👩‍👧‍👦 3. 샘플 족보 데이터 확인</h3>";
        
        try {
            $db = getDB();
            $stmt = $db->query("SELECT COUNT(*) as total FROM family_members");
            $personCount = $stmt->fetch();
            
            if ($personCount && $personCount['total'] > 0) {
                echo "<div class='success'>";
                echo "✅ 총 " . number_format($personCount['total']) . "명의 족보 데이터가 있습니다.";
                echo "</div>";
                
                // 최근 5명 데이터 샘플 조회
                $stmt = $db->query("SELECT person_code, name, name_hanja, generation FROM family_members ORDER BY id DESC LIMIT 5");
                $samples = $stmt->fetchAll();
                
                if ($samples) {
                    echo "<h4>📝 최근 등록된 인물 5명:</h4>";
                    echo "<table>";
                    echo "<tr><th>코드</th><th>한글명</th><th>한자명</th><th>세대</th></tr>";
                    
                    foreach ($samples as $person) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($person['person_code']) . "</td>";
                        echo "<td><strong>" . htmlspecialchars($person['name']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($person['name_hanja']) . "</td>";
                        echo "<td>" . $person['generation'] . "세대</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<div class='error'>";
                echo "❌ 족보 데이터가 없습니다. SQL 데이터 임포트가 필요합니다.";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "❌ 데이터 조회 중 오류 발생: " . $e->getMessage();
            echo "</div>";
        }
        echo "</div>";

        // 4. 한글 인코딩 테스트
        echo "<div class='test-section'>";
        echo "<h3>🔤 4. 한글 인코딩 테스트</h3>";
        
        try {
            $db = getDB();
            // 한글 테스트 쿼리
            $testKorean = "창녕조씨 족보시스템 테스트 - 한글 처리 확인";
            $stmt = $db->prepare("SELECT ? as korean_test");
            $stmt->execute([$testKorean]);
            $result = $stmt->fetch();
            
            if ($result && $result['korean_test'] === $testKorean) {
                echo "<div class='success'>";
                echo "✅ 한글 인코딩이 정상적으로 작동합니다.<br>";
                echo "• 테스트 문구: " . $result['korean_test'];
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "❌ 한글 인코딩에 문제가 있을 수 있습니다.";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "❌ 한글 테스트 중 오류 발생: " . $e->getMessage();
            echo "</div>";
        }
        echo "</div>";
        ?>

        <div class="info">
            <strong>📌 다음 단계:</strong><br>
            1. 모든 테스트가 성공하면 족보 시스템 개발을 진행합니다<br>
            2. 오류가 있다면 DB 연결 정보를 확인하고 수정하세요<br>
            3. 문의사항: 닥터조 (주)조유 대표이사
        </div>
    </div>
</body>
</html>