<?php
/**
 * 🤖 음성 검색 및 TTS 시스템
 * 
 * Speech Recognition & Text-to-Speech 기반 음성 족보 검색
 * 
 * @author 닥터조 (주)조유
 * @version 2.0 VOICE-AI
 * @date 2024-09-17
 */

require_once 'config/database.php';
require_once 'models/Person.php';

$person = new Person();

// 음성 검색 결과
$voice_results = [];
$search_performed = false;

// 텍스트 검색 처리 (음성→텍스트 변환 후)
if (isset($_POST['voice_search']) && !empty($_POST['search_text'])) {
    $search_performed = true;
    $search_text = trim($_POST['search_text']);
    $search_type = $_POST['search_type'] ?? 'name';
    
    $voice_results = performVoiceSearch($search_text, $search_type);
}

/**
 * 음성 검색 처리
 */
function performVoiceSearch($text, $type) {
    global $person;
    
    try {
        // 음성 명령 분석
        $command_analysis = analyzeVoiceCommand($text);
        
        if ($command_analysis['is_command']) {
            return executeVoiceCommand($command_analysis);
        } else {
            // 일반 검색
            return performSmartSearch($text, $type);
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => '음성 검색 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * 음성 명령 분석
 */
function analyzeVoiceCommand($text) {
    $text = strtolower(trim($text));
    
    // 명령어 패턴들
    $patterns = [
        // 세대 검색
        '/(\d+)세대/u' => 'generation_search',
        '/(\d+)대/u' => 'generation_search',
        
        // 관계 검색
        '/(.+)의?\s*아버지/u' => 'father_search',
        '/(.+)의?\s*어머니/u' => 'mother_search', 
        '/(.+)의?\s*자녀/u' => 'children_search',
        '/(.+)의?\s*형제/u' => 'siblings_search',
        
        // 통계 명령
        '/전체\s*통계/u' => 'stats_command',
        '/족보\s*통계/u' => 'stats_command',
        '/인구\s*수/u' => 'population_command',
        
        // 네비게이션
        '/홈으?로\s*가/u' => 'home_navigation',
        '/대시보드/u' => 'dashboard_navigation',
        '/관리자/u' => 'admin_navigation',
        
        // 도움말
        '/도움말/u' => 'help_command',
        '/사용법/u' => 'help_command',
    ];
    
    foreach ($patterns as $pattern => $command_type) {
        if (preg_match($pattern, $text, $matches)) {
            return [
                'is_command' => true,
                'type' => $command_type,
                'matches' => $matches,
                'original_text' => $text
            ];
        }
    }
    
    return [
        'is_command' => false,
        'text' => $text
    ];
}

/**
 * 음성 명령 실행
 */
function executeVoiceCommand($analysis) {
    global $person;
    
    switch ($analysis['type']) {
        case 'generation_search':
            $generation = intval($analysis['matches'][1]);
            $result = $person->getPersonsByGeneration($generation);
            return [
                'success' => true,
                'type' => 'generation_search',
                'generation' => $generation,
                'data' => $result['success'] ? $result['data'] : [],
                'tts_text' => "{$generation}세대에는 " . count($result['data']) . "명이 있습니다.",
                'command' => $analysis['original_text']
            ];
            
        case 'stats_command':
            $stats_result = $person->getGenealogyStats();
            $stats = $stats_result['success'] ? $stats_result['data'] : [];
            return [
                'success' => true,
                'type' => 'stats_command', 
                'data' => $stats,
                'tts_text' => "전체 인물은 " . ($stats['total_persons'] ?? 0) . "명이고, 생존 추정은 " . ($stats['alive_persons'] ?? 0) . "명입니다.",
                'command' => $analysis['original_text']
            ];
            
        case 'father_search':
        case 'children_search':
            $name = trim($analysis['matches'][1]);
            return searchRelatives($name, $analysis['type']);
            
        case 'help_command':
            return [
                'success' => true,
                'type' => 'help_command',
                'data' => getVoiceHelp(),
                'tts_text' => "음성 명령을 사용하여 족보를 검색할 수 있습니다. 예를 들어 '5세대' 또는 '조철수의 아버지'라고 말해보세요.",
                'command' => $analysis['original_text']
            ];
            
        default:
            return performSmartSearch($analysis['original_text'], 'name');
    }
}

/**
 * 가족 관계 검색
 */
function searchRelatives($name, $relation_type) {
    global $person;
    
    try {
        // 이름으로 인물 검색
        $search_result = $person->searchPersonsByName($name, 5);
        
        if (!$search_result['success'] || empty($search_result['data'])) {
            return [
                'success' => false,
                'error' => "'{$name}'님을 찾을 수 없습니다.",
                'tts_text' => "{$name}님을 찾을 수 없습니다."
            ];
        }
        
        $target_person = $search_result['data'][0]; // 첫 번째 결과 사용
        $relatives = [];
        
        switch ($relation_type) {
            case 'father_search':
                if ($target_person['parent_code']) {
                    $parent_result = $person->getPersonByCode($target_person['parent_code']);
                    if ($parent_result['success']) {
                        $relatives[] = $parent_result['data'];
                    }
                }
                $relation_text = '아버지';
                break;
                
            case 'children_search':
                $db = getDB();
                $stmt = $db->prepare("
                    SELECT * FROM family_members 
                    WHERE parent_code = ? 
                    ORDER BY sibling_order ASC
                ");
                $stmt->execute([$target_person['person_code']]);
                $relatives = $stmt->fetchAll();
                $relation_text = '자녀';
                break;
        }
        
        $count = count($relatives);
        $tts_text = "{$target_person['name']}님의 {$relation_text}는 ";
        
        if ($count === 0) {
            $tts_text .= "없습니다.";
        } else if ($count === 1) {
            $tts_text .= $relatives[0]['name'] . "님입니다.";
        } else {
            $names = array_column($relatives, 'name');
            $tts_text .= implode(', ', $names) . " 총 {$count}명입니다.";
        }
        
        return [
            'success' => true,
            'type' => $relation_type,
            'target_person' => $target_person,
            'relatives' => $relatives,
            'tts_text' => $tts_text,
            'relation_text' => $relation_text
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => '관계 검색 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * 스마트 검색
 */
function performSmartSearch($text, $type) {
    global $person;
    
    $search_result = $person->searchPersonsByName($text, 10);
    
    if ($search_result['success'] && !empty($search_result['data'])) {
        $results = $search_result['data'];
        $count = count($results);
        
        if ($count === 1) {
            $tts_text = $results[0]['name'] . "님을 찾았습니다. " . $results[0]['generation'] . "세대입니다.";
        } else {
            $tts_text = "'{$text}' 검색 결과 {$count}명을 찾았습니다.";
        }
        
        return [
            'success' => true,
            'type' => 'smart_search',
            'query' => $text,
            'data' => $results,
            'tts_text' => $tts_text
        ];
    } else {
        return [
            'success' => false,
            'error' => "'{$text}' 검색 결과가 없습니다.",
            'tts_text' => "{$text}에 대한 검색 결과가 없습니다."
        ];
    }
}

/**
 * 음성 도움말
 */
function getVoiceHelp() {
    return [
        'basic_commands' => [
            '이름 검색: "조철수", "김영희" 등',
            '세대 검색: "5세대", "10대" 등',
            '관계 검색: "조철수의 아버지", "김영희의 자녀"',
            '통계 조회: "전체 통계", "인구 수"',
            '도움말: "도움말", "사용법"'
        ],
        'navigation_commands' => [
            '"홈으로 가", "대시보드", "관리자"'
        ],
        'tips' => [
            '명확하게 발음하세요',
            '한 번에 하나씩 명령하세요',
            '이름은 정확히 발음하세요'
        ]
    ];
}

// 최근 인물들 (음성 테스트용)
$recent_persons_result = $person->getAllPersons(1, 20);
$recent_persons = $recent_persons_result['success'] ? $recent_persons_result['data'] : [];

// 통계 정보
$stats_result = $person->getGenealogyStats();
$stats = $stats_result['success'] ? $stats_result['data'] : [];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🤖 음성 검색 - 창녕조씨 족보 시스템</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        .voice-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .voice-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        
        .voice-card .card-body {
            color: #333;
        }
        
        .mic-button {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            border: none;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 8px 25px rgba(238, 90, 36, 0.4);
            transition: all 0.3s ease;
        }
        
        .mic-button:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(238, 90, 36, 0.6);
        }
        
        .mic-button.listening {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .voice-wave {
            display: none;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
        }
        
        .voice-wave.active {
            display: flex;
        }
        
        .wave-bar {
            width: 4px;
            height: 20px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            margin: 0 2px;
            border-radius: 2px;
            animation: wave 0.6s infinite alternate;
        }
        
        .wave-bar:nth-child(2) { animation-delay: 0.1s; }
        .wave-bar:nth-child(3) { animation-delay: 0.2s; }
        .wave-bar:nth-child(4) { animation-delay: 0.3s; }
        .wave-bar:nth-child(5) { animation-delay: 0.4s; }
        
        @keyframes wave {
            0% { height: 20px; }
            100% { height: 40px; }
        }
        
        .voice-status {
            font-size: 1.1rem;
            margin-top: 15px;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .command-examples {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .example-command {
            background: white;
            border-radius: 10px;
            padding: 10px 15px;
            margin: 5px 0;
            border-left: 4px solid #667eea;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .example-command:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .tts-controls {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .voice-result-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .speak-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 25px;
            padding: 8px 15px;
            transition: all 0.3s ease;
        }
        
        .speak-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .recognition-text {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 1.1rem;
        }
        
        .voice-settings {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'views/header.php'; ?>

    <!-- 음성 검색 헤더 -->
    <div class="voice-container">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-3">
                🤖 음성 검색 시스템
            </h1>
            <p class="lead mb-4">
                음성으로 족보를 검색하고, AI가 결과를 읽어드립니다
            </p>
            <div class="row text-center">
                <div class="col-md-4">
                    <h3 class="fw-bold"><?= number_format($stats['total_persons'] ?? 0) ?></h3>
                    <small>검색 가능 인물</small>
                </div>
                <div class="col-md-4">
                    <h3 class="fw-bold">12</h3>
                    <small>음성 명령 종류</small>
                </div>
                <div class="col-md-4">
                    <h3 class="fw-bold">한국어</h3>
                    <small>지원 언어</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- 음성 검색 컨트롤 -->
            <div class="col-lg-8">
                <div class="card voice-card">
                    <div class="card-body text-center">
                        <h5 class="card-title mb-4">
                            <i class="bi bi-mic"></i> 음성 검색
                        </h5>
                        
                        <!-- 마이크 버튼 -->
                        <div class="mb-4">
                            <button id="micButton" class="mic-button" type="button">
                                <i class="bi bi-mic-fill" id="micIcon"></i>
                            </button>
                        </div>
                        
                        <!-- 음성 파형 -->
                        <div class="voice-wave" id="voiceWave">
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                            <div class="wave-bar"></div>
                        </div>
                        
                        <!-- 상태 표시 -->
                        <div class="voice-status" id="voiceStatus">
                            마이크 버튼을 클릭하고 말씀하세요
                        </div>
                        
                        <!-- 인식된 텍스트 -->
                        <div class="recognition-text" id="recognitionText" style="display: none;">
                            <!-- 인식 결과 표시 -->
                        </div>
                        
                        <!-- 음성 설정 -->
                        <div class="voice-settings">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small">음성 속도</label>
                                    <input type="range" class="form-range" id="speechRate" min="0.5" max="2" step="0.1" value="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">음성 높이</label>
                                    <input type="range" class="form-range" id="speechPitch" min="0" max="2" step="0.1" value="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">볼륨</label>
                                    <input type="range" class="form-range" id="speechVolume" min="0" max="1" step="0.1" value="1">
                                </div>
                            </div>
                        </div>
                        
                        <!-- 숨겨진 폼 (음성→텍스트 결과 전송용) -->
                        <form method="POST" id="voiceSearchForm" style="display: none;">
                            <input type="hidden" name="voice_search" value="1">
                            <input type="hidden" name="search_text" id="searchTextInput">
                            <input type="hidden" name="search_type" id="searchTypeInput" value="name">
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 명령어 예시 -->
            <div class="col-lg-4">
                <div class="card voice-card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-lightbulb"></i> 음성 명령 예시</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="command-examples">
                            <h6 class="fw-bold mb-3">📝 기본 검색</h6>
                            <div class="example-command" onclick="testVoiceCommand(this)">
                                "조철수"
                            </div>
                            <div class="example-command" onclick="testVoiceCommand(this)">
                                "5세대"
                            </div>
                            <div class="example-command" onclick="testVoiceCommand(this)">
                                "10대"
                            </div>
                            
                            <h6 class="fw-bold mb-3 mt-4">👨‍👩‍👧‍👦 관계 검색</h6>
                            <div class="example-command" onclick="testVoiceCommand(this)">
                                "조철수의 아버지"
                            </div>
                            <div class="example-command" onclick="testVoiceCommand(this)">
                                "김영희의 자녀"
                            </div>
                            
                            <h6 class="fw-bold mb-3 mt-4">📊 통계 명령</h6>
                            <div class="example-command" onclick="testVoiceCommand(this)">
                                "전체 통계"
                            </div>
                            <div class="example-command" onclick="testVoiceCommand(this)">
                                "인구 수"
                            </div>
                            
                            <h6 class="fw-bold mb-3 mt-4">🔧 시스템 명령</h6>
                            <div class="example-command" onclick="testVoiceCommand(this)">
                                "도움말"
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 음성 검색 결과 -->
        <?php if ($search_performed): ?>
            <div class="voice-result-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-search"></i> 음성 검색 결과
                    </h5>
                    <button class="speak-btn" onclick="speakResult()">
                        <i class="bi bi-volume-up"></i> 결과 읽기
                    </button>
                </div>
                
                <?php if ($voice_results['success']): ?>
                    <div class="row">
                        <div class="col-md-8">
                            <?php if ($voice_results['type'] === 'stats_command'): ?>
                                <h6>📊 족보 통계</h6>
                                <p>전체 인물: <strong><?= number_format($voice_results['data']['total_persons'] ?? 0) ?>명</strong></p>
                                <p>생존 추정: <strong><?= number_format($voice_results['data']['alive_persons'] ?? 0) ?>명</strong></p>
                                
                            <?php elseif ($voice_results['type'] === 'generation_search'): ?>
                                <h6><?= $voice_results['generation'] ?>세대 인물들</h6>
                                <div class="row">
                                    <?php foreach (array_slice($voice_results['data'], 0, 6) as $result): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="bg-white bg-opacity-10 rounded p-2">
                                                <strong><?= htmlspecialchars($result['name']) ?></strong>
                                                <?php if ($result['name_hanja']): ?>
                                                    <br><small><?= htmlspecialchars($result['name_hanja']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif (in_array($voice_results['type'], ['father_search', 'children_search'])): ?>
                                <h6><?= htmlspecialchars($voice_results['target_person']['name']) ?>님의 <?= $voice_results['relation_text'] ?></h6>
                                <?php if (!empty($voice_results['relatives'])): ?>
                                    <?php foreach ($voice_results['relatives'] as $relative): ?>
                                        <div class="bg-white bg-opacity-10 rounded p-2 mb-2">
                                            <strong><?= htmlspecialchars($relative['name']) ?></strong>
                                            <?php if ($relative['name_hanja']): ?>
                                                (<?= htmlspecialchars($relative['name_hanja']) ?>)
                                            <?php endif; ?>
                                            - <?= $relative['generation'] ?>세
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>관련 정보가 없습니다.</p>
                                <?php endif; ?>
                                
                            <?php elseif ($voice_results['type'] === 'smart_search'): ?>
                                <h6>"<?= htmlspecialchars($voice_results['query']) ?>" 검색 결과</h6>
                                <div class="row">
                                    <?php foreach (array_slice($voice_results['data'], 0, 6) as $result): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="bg-white bg-opacity-10 rounded p-2">
                                                <strong><?= htmlspecialchars($result['name']) ?></strong>
                                                <?php if ($result['name_hanja']): ?>
                                                    <br><small><?= htmlspecialchars($result['name_hanja']) ?></small>
                                                <?php endif; ?>
                                                <br><small><?= $result['generation'] ?>세 • <?= $result['gender'] ?> • 자녀 <?= $result['child_count'] ?>명</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif ($voice_results['type'] === 'help_command'): ?>
                                <h6>🤖 음성 명령 도움말</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="small">기본 명령</h6>
                                        <ul class="small">
                                            <?php foreach ($voice_results['data']['basic_commands'] as $cmd): ?>
                                                <li><?= htmlspecialchars($cmd) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="small">사용 팁</h6>
                                        <ul class="small">
                                            <?php foreach ($voice_results['data']['tips'] as $tip): ?>
                                                <li><?= htmlspecialchars($tip) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="tts-controls">
                                <h6><i class="bi bi-volume-up"></i> 음성 출력</h6>
                                <p class="small mb-3" id="ttsText">
                                    <?= htmlspecialchars($voice_results['tts_text'] ?? '') ?>
                                </p>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-light btn-sm" onclick="speakResult()">
                                        <i class="bi bi-play"></i> 재생
                                    </button>
                                    <button class="btn btn-outline-light btn-sm" onclick="stopSpeaking()">
                                        <i class="bi bi-stop"></i> 정지
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= htmlspecialchars($voice_results['error']) ?>
                    </div>
                    <?php if (isset($voice_results['tts_text'])): ?>
                        <script>
                            // 오류도 음성으로 출력
                            speakText('<?= addslashes($voice_results['tts_text']) ?>');
                        </script>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'views/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 음성 인식 변수들
        let recognition;
        let speechSynthesis = window.speechSynthesis;
        let isListening = false;
        let currentUtterance = null;
        
        // 브라우저 지원 확인
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            
            // 음성 인식 설정
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = 'ko-KR';
            recognition.maxAlternatives = 1;
        } else {
            document.getElementById('voiceStatus').innerHTML = 
                '<div class="alert alert-warning">이 브라우저는 음성 인식을 지원하지 않습니다.</div>';
        }
        
        // DOM 요소들
        const micButton = document.getElementById('micButton');
        const micIcon = document.getElementById('micIcon');
        const voiceStatus = document.getElementById('voiceStatus');
        const voiceWave = document.getElementById('voiceWave');
        const recognitionText = document.getElementById('recognitionText');
        const searchTextInput = document.getElementById('searchTextInput');
        const voiceSearchForm = document.getElementById('voiceSearchForm');
        
        // 마이크 버튼 클릭
        micButton.addEventListener('click', function() {
            if (!recognition) {
                alert('음성 인식이 지원되지 않는 브라우저입니다.');
                return;
            }
            
            if (isListening) {
                stopListening();
            } else {
                startListening();
            }
        });
        
        // 음성 인식 시작
        function startListening() {
            isListening = true;
            micButton.classList.add('listening');
            micIcon.className = 'bi bi-mic-mute-fill';
            voiceStatus.innerHTML = '<i class="bi bi-mic"></i> 듣고 있습니다... 말씀하세요';
            voiceWave.classList.add('active');
            
            recognition.start();
        }
        
        // 음성 인식 중지
        function stopListening() {
            isListening = false;
            micButton.classList.remove('listening');
            micIcon.className = 'bi bi-mic-fill';
            voiceStatus.innerHTML = '음성 처리 중...';
            voiceWave.classList.remove('active');
            
            recognition.stop();
        }
        
        // 음성 인식 이벤트 핸들러
        if (recognition) {
            recognition.onresult = function(event) {
                const transcript = event.results[0][0].transcript;
                const confidence = event.results[0][0].confidence;
                
                recognitionText.style.display = 'block';
                recognitionText.innerHTML = `
                    <strong>인식된 음성:</strong> "${transcript}"<br>
                    <small class="text-muted">신뢰도: ${(confidence * 100).toFixed(1)}%</small>
                `;
                
                voiceStatus.innerHTML = '<i class="bi bi-search"></i> 검색 중...';
                
                // 폼 제출
                searchTextInput.value = transcript;
                setTimeout(() => {
                    voiceSearchForm.submit();
                }, 1000);
            };
            
            recognition.onerror = function(event) {
                console.error('음성 인식 오류:', event.error);
                
                let errorMessage = '음성 인식 오류가 발생했습니다.';
                
                switch (event.error) {
                    case 'no-speech':
                        errorMessage = '음성이 감지되지 않았습니다. 다시 시도해주세요.';
                        break;
                    case 'audio-capture':
                        errorMessage = '마이크에 접근할 수 없습니다.';
                        break;
                    case 'not-allowed':
                        errorMessage = '마이크 권한을 허용해주세요.';
                        break;
                    case 'network':
                        errorMessage = '네트워크 오류가 발생했습니다.';
                        break;
                }
                
                voiceStatus.innerHTML = `<i class="bi bi-exclamation-triangle text-warning"></i> ${errorMessage}`;
                stopListening();
                
                speakText(errorMessage);
            };
            
            recognition.onend = function() {
                stopListening();
            };
        }
        
        // 텍스트 음성 변환
        function speakText(text) {
            if (currentUtterance) {
                speechSynthesis.cancel();
            }
            
            currentUtterance = new SpeechSynthesisUtterance(text);
            currentUtterance.lang = 'ko-KR';
            currentUtterance.rate = parseFloat(document.getElementById('speechRate').value);
            currentUtterance.pitch = parseFloat(document.getElementById('speechPitch').value);
            currentUtterance.volume = parseFloat(document.getElementById('speechVolume').value);
            
            // 한국어 음성 선택 (사용 가능한 경우)
            const voices = speechSynthesis.getVoices();
            const koreanVoice = voices.find(voice => voice.lang.includes('ko'));
            if (koreanVoice) {
                currentUtterance.voice = koreanVoice;
            }
            
            speechSynthesis.speak(currentUtterance);
        }
        
        // 검색 결과 음성 출력
        function speakResult() {
            const ttsText = document.getElementById('ttsText')?.textContent;
            if (ttsText) {
                speakText(ttsText);
            }
        }
        
        // 음성 출력 정지
        function stopSpeaking() {
            speechSynthesis.cancel();
            currentUtterance = null;
        }
        
        // 예시 명령 테스트
        function testVoiceCommand(element) {
            const command = element.textContent.trim().replace(/["""]/g, '');
            
            recognitionText.style.display = 'block';
            recognitionText.innerHTML = `
                <strong>테스트 명령:</strong> "${command}"<br>
                <small class="text-muted">예시 명령어로 검색합니다.</small>
            `;
            
            voiceStatus.innerHTML = '<i class="bi bi-search"></i> 검색 중...';
            
            searchTextInput.value = command;
            setTimeout(() => {
                voiceSearchForm.submit();
            }, 500);
        }
        
        // 음성 설정 변경 시 미리보기
        document.getElementById('speechRate').addEventListener('change', function() {
            speakText('음성 속도가 변경되었습니다.');
        });
        
        document.getElementById('speechPitch').addEventListener('change', function() {
            speakText('음성 높이가 변경되었습니다.');
        });
        
        document.getElementById('speechVolume').addEventListener('change', function() {
            speakText('볼륨이 변경되었습니다.');
        });
        
        // 페이지 로드 시 음성 안내
        window.addEventListener('load', function() {
            setTimeout(() => {
                speakText('음성 검색 시스템에 오신 것을 환영합니다. 마이크 버튼을 클릭하고 말씀하세요.');
            }, 1000);
        });
        
        // 키보드 단축키
        document.addEventListener('keydown', function(event) {
            if (event.code === 'Space' && event.ctrlKey) {
                event.preventDefault();
                micButton.click();
            }
            
            if (event.code === 'Escape') {
                stopSpeaking();
                if (isListening) {
                    stopListening();
                }
            }
        });
        
        <?php if ($search_performed && isset($voice_results['tts_text'])): ?>
        // 검색 완료 후 자동 음성 출력
        setTimeout(() => {
            speakText('<?= addslashes($voice_results['tts_text']) ?>');
        }, 500);
        <?php endif; ?>
    </script>
</body>
</html>