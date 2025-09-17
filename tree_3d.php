<?php
/**
 * 📊 3D 인터랙티브 가계도 시스템
 * 
 * Three.js 기반 몰입형 3D 족보 시각화
 * 
 * @author 닥터조 (주)조유
 * @version 2.0 REVOLUTIONARY
 * @date 2024-09-17
 */

require_once 'config/database.php';
require_once 'models/Person.php';

$person = new Person();

// 3D 가계도 설정
$start_generation = $_GET['generation'] ?? 1;
$focus_person_id = $_GET['person_id'] ?? null;
$view_mode = $_GET['view'] ?? 'galaxy'; // galaxy, tree, sphere, helix
$max_generations = $_GET['depth'] ?? 10;

// 3D 데이터 준비
$tree3d_data = build3DTreeData($start_generation, $max_generations, $focus_person_id, $view_mode);

/**
 * 3D 가계도 데이터 구성
 */
function build3DTreeData($start_gen, $max_gen, $focus_id = null, $view_mode = 'galaxy') {
    global $person;
    
    try {
        $db = getDB();
        
        $end_gen = min(45, $start_gen + $max_gen);
        
        // 해당 세대 범위의 모든 인물 조회
        $stmt = $db->prepare("
            SELECT 
                id, person_code, parent_code, name, name_hanja, 
                gender, generation, sibling_order, child_count,
                birth_date, death_date
            FROM family_members 
            WHERE generation BETWEEN ? AND ?
            ORDER BY generation ASC, sibling_order ASC, name ASC
        ");
        
        $stmt->execute([$start_gen, $end_gen]);
        $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3D 좌표 생성
        $nodes_3d = [];
        $links_3d = [];
        
        foreach ($persons as $p) {
            // 뷰 모드별 3D 좌표 계산
            $coordinates = calculate3DPosition($p, $view_mode, $persons);
            
            $nodes_3d[] = [
                'id' => $p['id'],
                'person_code' => $p['person_code'],
                'name' => $p['name'],
                'name_hanja' => $p['name_hanja'],
                'gender' => $p['gender'],
                'generation' => $p['generation'],
                'child_count' => $p['child_count'],
                'x' => $coordinates['x'],
                'y' => $coordinates['y'], 
                'z' => $coordinates['z'],
                'color' => getPersonColor($p),
                'size' => getPersonSize($p),
                'is_focus' => ($focus_id && $p['id'] == $focus_id)
            ];
            
            // 부모-자녀 연결 생성
            if ($p['parent_code']) {
                $parent = array_filter($persons, function($pp) use ($p) {
                    return $pp['person_code'] === $p['parent_code'];
                });
                
                if (!empty($parent)) {
                    $parent = reset($parent);
                    $links_3d[] = [
                        'source' => $parent['id'],
                        'target' => $p['id'],
                        'strength' => calculateConnectionStrength($parent, $p)
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'view_mode' => $view_mode,
            'nodes' => $nodes_3d,
            'links' => $links_3d,
            'generation_range' => [$start_gen, $end_gen],
            'focus_person' => $focus_id ? getPersonById($focus_id) : null,
            'camera_position' => getCameraPosition($view_mode, $start_gen, $end_gen),
            'lighting' => getLightingConfig($view_mode)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '3D 가계도 데이터 구성 실패: ' . $e->getMessage()
        ];
    }
}

/**
 * 뷰 모드별 3D 좌표 계산
 */
function calculate3DPosition($person, $view_mode, $all_persons) {
    $gen = $person['generation'];
    $sibling_order = $person['sibling_order'] ?? 1;
    
    switch ($view_mode) {
        case 'galaxy':
            // 나선 은하 모양
            $radius = $gen * 20;
            $angle = ($sibling_order * 0.5) + ($gen * 0.3);
            return [
                'x' => $radius * cos($angle),
                'y' => ($gen - 20) * 15, // Y축은 세대
                'z' => $radius * sin($angle)
            ];
            
        case 'tree':
            // 전통적인 트리 구조
            $gen_siblings = array_filter($all_persons, function($p) use ($gen) {
                return $p['generation'] == $gen;
            });
            $sibling_count = count($gen_siblings);
            $position_offset = ($sibling_order - ($sibling_count / 2)) * 40;
            
            return [
                'x' => $position_offset,
                'y' => ($gen - 1) * 60, // 위로 올라갈수록 높은 세대
                'z' => 0
            ];
            
        case 'sphere':
            // 구형 배치
            $phi = ($gen / 45) * M_PI; // 세로각
            $theta = ($sibling_order * 0.8) % (2 * M_PI); // 가로각
            $radius = 200;
            
            return [
                'x' => $radius * sin($phi) * cos($theta),
                'y' => $radius * cos($phi),
                'z' => $radius * sin($phi) * sin($theta)
            ];
            
        case 'helix':
            // DNA 나선 구조
            $radius = 100;
            $height_per_gen = 20;
            $angle = $gen * 0.5 + ($sibling_order * 0.2);
            
            return [
                'x' => $radius * cos($angle) + ($sibling_order % 2 ? 30 : -30),
                'y' => $gen * $height_per_gen,
                'z' => $radius * sin($angle)
            ];
            
        default:
            return ['x' => 0, 'y' => $gen * 50, 'z' => $sibling_order * 30];
    }
}

/**
 * 인물별 색상 결정
 */
function getPersonColor($person) {
    // 성별 기반 색상
    if ($person['gender'] === '남') {
        return '#4A90E2'; // 파란색
    } else {
        return '#E24A90'; // 분홍색
    }
}

/**
 * 인물별 크기 결정
 */
function getPersonSize($person) {
    // 자녀 수에 따른 크기
    $base_size = 1;
    $child_bonus = ($person['child_count'] ?? 0) * 0.2;
    return min(3, $base_size + $child_bonus);
}

/**
 * 카메라 위치 설정
 */
function getCameraPosition($view_mode, $start_gen, $end_gen) {
    switch ($view_mode) {
        case 'galaxy':
            return ['x' => 300, 'y' => 200, 'z' => 300];
        case 'tree':
            return ['x' => 0, 'y' => 100, 'z' => 400];
        case 'sphere':
            return ['x' => 400, 'y' => 200, 'z' => 400];
        case 'helix':
            return ['x' => 200, 'y' => 300, 'z' => 200];
        default:
            return ['x' => 200, 'y' => 200, 'z' => 200];
    }
}

/**
 * 조명 설정
 */
function getLightingConfig($view_mode) {
    return [
        'ambient' => ['color' => 0x404040, 'intensity' => 0.5],
        'directional' => [
            ['color' => 0xffffff, 'intensity' => 1, 'position' => [100, 100, 100]],
            ['color' => 0x4444ff, 'intensity' => 0.3, 'position' => [-100, -100, -100]]
        ]
    ];
}

// 통계 정보
$stats_result = $person->getGenealogyStats();
$stats = $stats_result['success'] ? $stats_result['data'] : [];

// 세대 범위
$gen_range_result = $person->getGenerationRange();
$gen_range = $gen_range_result['success'] ? $gen_range_result['data'] : ['min_gen' => 1, 'max_gen' => 45];

// 최근 인물들 (선택용)
$recent_persons_result = $person->getAllPersons(1, 30);
$recent_persons = $recent_persons_result['success'] ? $recent_persons_result['data'] : [];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 3D 몰입형 가계도 - 창녕조씨 족보</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Three.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    
    <style>
        body {
            margin: 0;
            overflow: hidden;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: white;
        }
        
        #tree3d-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
        }
        
        .control-panel {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            z-index: 100;
            min-width: 300px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stats-panel {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px;
            z-index: 100;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            z-index: 100;
            max-width: 350px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: none;
        }
        
        .view-mode-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 8px;
            padding: 8px 12px;
            margin: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-mode-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .view-mode-btn.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-color: #667eea;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 8px;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #667eea;
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-select option {
            background: #1a1a2e;
            color: white;
        }
        
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            color: white;
        }
        
        .loading-content {
            text-align: center;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .person-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .controls-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        
        .fullscreen-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        .fullscreen-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }
        
        .generation-slider {
            -webkit-appearance: none;
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.2);
            outline: none;
            margin: 10px 0;
        }
        
        .generation-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            cursor: pointer;
        }
    </style>
</head>

<body>
    <!-- 로딩 화면 -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>🌌 3D 가계도 생성 중...</h3>
            <p>몰입형 족보 시각화를 준비하고 있습니다</p>
        </div>
    </div>

    <!-- 3D 렌더링 컨테이너 -->
    <div id="tree3d-container"></div>

    <!-- 컨트롤 패널 -->
    <div class="control-panel">
        <h5 class="mb-3">
            <i class="bi bi-joystick"></i> 3D 가계도 컨트롤
        </h5>
        
        <!-- 뷰 모드 선택 -->
        <div class="mb-3">
            <label class="form-label fw-bold">시각화 모드</label>
            <div class="d-flex flex-wrap">
                <button class="view-mode-btn active" data-mode="galaxy">
                    🌌 은하수
                </button>
                <button class="view-mode-btn" data-mode="tree">
                    🌳 트리
                </button>
                <button class="view-mode-btn" data-mode="sphere">
                    🌍 구형
                </button>
                <button class="view-mode-btn" data-mode="helix">
                    🧬 나선
                </button>
            </div>
        </div>
        
        <!-- 세대 범위 -->
        <div class="mb-3">
            <label class="form-label fw-bold">세대 범위</label>
            <input type="range" class="generation-slider" 
                   id="generationRange" min="1" max="45" value="<?= $start_generation ?>" step="1">
            <div class="d-flex justify-content-between small">
                <span id="genRangeMin">1세대</span>
                <span id="genRangeCurrent"><?= $start_generation ?>세대부터</span>
                <span id="genRangeMax">45세대</span>
            </div>
        </div>
        
        <!-- 표시 깊이 -->
        <div class="mb-3">
            <label class="form-label fw-bold">표시 세대 수</label>
            <select class="form-select" id="depthSelect">
                <option value="5">5세대</option>
                <option value="10" selected>10세대</option>
                <option value="15">15세대</option>
                <option value="20">20세대</option>
            </select>
        </div>
        
        <!-- 중심 인물 -->
        <div class="mb-3">
            <label class="form-label fw-bold">중심 인물</label>
            <select class="form-select" id="focusPersonSelect">
                <option value="">전체 보기</option>
                <?php foreach ($recent_persons as $rp): ?>
                    <option value="<?= $rp['id'] ?>">
                        <?= htmlspecialchars($rp['name']) ?> (<?= $rp['generation'] ?>세)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- 컨트롤 버튼 -->
        <div class="controls-grid">
            <button class="btn btn-outline-light btn-sm" onclick="resetCamera()">
                <i class="bi bi-house"></i> 리셋
            </button>
            <button class="btn btn-outline-light btn-sm" onclick="autoRotate()">
                <i class="bi bi-arrow-clockwise"></i> 회전
            </button>
            <button class="btn btn-outline-light btn-sm" onclick="takeScreenshot()">
                <i class="bi bi-camera"></i> 캡처
            </button>
            <button class="btn btn-outline-light btn-sm" onclick="showHelp()">
                <i class="bi bi-question-circle"></i> 도움말
            </button>
        </div>
    </div>

    <!-- 통계 패널 -->
    <div class="stats-panel">
        <h6><i class="bi bi-graph-up"></i> 실시간 통계</h6>
        <div class="small">
            <div>노드: <span id="nodeCount">0</span>개</div>
            <div>연결: <span id="linkCount">0</span>개</div>
            <div>FPS: <span id="fpsCounter">60</span></div>
            <div>세대: <span id="generationSpan">1-45</span></div>
        </div>
    </div>

    <!-- 인물 정보 패널 -->
    <div class="info-panel" id="personInfoPanel">
        <h5><i class="bi bi-person-circle"></i> 인물 정보</h5>
        <div id="personInfoContent">
            <!-- 동적으로 로드 -->
        </div>
        <button class="btn btn-outline-light btn-sm mt-3" onclick="closePersonInfo()">
            <i class="bi bi-x"></i> 닫기
        </button>
    </div>

    <!-- 전체화면 버튼 -->
    <button class="fullscreen-btn" onclick="toggleFullscreen()">
        <i class="bi bi-fullscreen"></i>
    </button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 3D 장면 변수들
        let scene, camera, renderer, controls;
        let personNodes = [];
        let familyLinks = [];
        let currentViewMode = 'galaxy';
        let isAutoRotating = false;
        let selectedPerson = null;
        
        // 족보 데이터
        const genealogyData = <?= json_encode($tree3d_data, JSON_UNESCAPED_UNICODE) ?>;
        
        // 3D 장면 초기화
        function init3DScene() {
            // Scene 생성
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0x0f0f23);
            
            // Camera 생성 
            camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 2000);
            
            // Renderer 생성
            renderer = new THREE.WebGLRenderer({ antialias: true });
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.shadowMap.enabled = true;
            renderer.shadowMap.type = THREE.PCFSoftShadowMap;
            
            document.getElementById('tree3d-container').appendChild(renderer.domElement);
            
            // Controls 설정
            controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.maxDistance = 1000;
            controls.minDistance = 50;
            
            // 조명 설정
            setupLighting();
            
            // 족보 데이터 로드
            if (genealogyData.success) {
                load3DGenealogy(genealogyData);
            }
            
            // 카메라 위치 설정
            setCameraPosition();
            
            // 렌더링 시작
            animate();
            
            // 로딩 완료
            setTimeout(() => {
                document.getElementById('loadingScreen').style.display = 'none';
            }, 2000);
        }
        
        // 조명 설정
        function setupLighting() {
            // 환경광
            const ambientLight = new THREE.AmbientLight(0x404040, 0.4);
            scene.add(ambientLight);
            
            // 주 조명
            const mainLight = new THREE.DirectionalLight(0xffffff, 1);
            mainLight.position.set(100, 100, 100);
            mainLight.castShadow = true;
            mainLight.shadow.mapSize.width = 2048;
            mainLight.shadow.mapSize.height = 2048;
            scene.add(mainLight);
            
            // 보조 조명
            const secondLight = new THREE.DirectionalLight(0x4444ff, 0.3);
            secondLight.position.set(-100, -100, -100);
            scene.add(secondLight);
            
            // 포인트 라이트 (중심)
            const centerLight = new THREE.PointLight(0xffffff, 0.5, 500);
            centerLight.position.set(0, 100, 0);
            scene.add(centerLight);
        }
        
        // 3D 족보 데이터 로드
        function load3DGenealogy(data) {
            // 기존 객체 제거
            clearScene();
            
            const nodes = data.nodes || [];
            const links = data.links || [];
            
            // 인물 노드 생성
            nodes.forEach(node => {
                createPersonNode(node);
            });
            
            // 가족 연결선 생성
            links.forEach(link => {
                createFamilyLink(link, nodes);
            });
            
            // 통계 업데이트
            updateStats(nodes.length, links.length);
        }
        
        // 인물 노드 생성
        function createPersonNode(nodeData) {
            // 구 기하학 생성
            const geometry = new THREE.SphereGeometry(nodeData.size * 3, 16, 12);
            
            // 성별별 머티리얼
            let material;
            if (nodeData.gender === '남') {
                material = new THREE.MeshLambertMaterial({ 
                    color: 0x4A90E2,
                    emissive: nodeData.is_focus ? 0x222244 : 0x000000
                });
            } else {
                material = new THREE.MeshLambertMaterial({ 
                    color: 0xE24A90,
                    emissive: nodeData.is_focus ? 0x442222 : 0x000000
                });
            }
            
            // 포커스 인물 강조
            if (nodeData.is_focus) {
                material.emissiveIntensity = 0.3;
                
                // 글로우 효과 (아우라)
                const glowGeometry = new THREE.SphereGeometry(nodeData.size * 4, 16, 12);
                const glowMaterial = new THREE.MeshBasicMaterial({
                    color: 0xffd700,
                    transparent: true,
                    opacity: 0.2
                });
                const glowMesh = new THREE.Mesh(glowGeometry, glowMaterial);
                glowMesh.position.set(nodeData.x, nodeData.y, nodeData.z);
                scene.add(glowMesh);
            }
            
            const sphere = new THREE.Mesh(geometry, material);
            sphere.position.set(nodeData.x, nodeData.y, nodeData.z);
            sphere.castShadow = true;
            sphere.receiveShadow = true;
            
            // 사용자 데이터 저장
            sphere.userData = nodeData;
            
            // 클릭 이벤트용 추가
            personNodes.push(sphere);
            scene.add(sphere);
            
            // 이름 라벨 (옵션)
            if (nodeData.is_focus || nodeData.child_count > 5) {
                createNameLabel(nodeData);
            }
        }
        
        // 이름 라벨 생성
        function createNameLabel(nodeData) {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = 256;
            canvas.height = 64;
            
            context.fillStyle = 'rgba(0, 0, 0, 0.8)';
            context.fillRect(0, 0, canvas.width, canvas.height);
            
            context.fillStyle = 'white';
            context.font = '20px Arial';
            context.textAlign = 'center';
            context.fillText(nodeData.name, canvas.width / 2, canvas.height / 2 + 7);
            
            const texture = new THREE.CanvasTexture(canvas);
            const material = new THREE.SpriteMaterial({ map: texture });
            const sprite = new THREE.Sprite(material);
            
            sprite.position.set(nodeData.x, nodeData.y + 15, nodeData.z);
            sprite.scale.set(30, 7.5, 1);
            
            scene.add(sprite);
        }
        
        // 가족 연결선 생성
        function createFamilyLink(linkData, nodes) {
            const sourceNode = nodes.find(n => n.id === linkData.source);
            const targetNode = nodes.find(n => n.id === linkData.target);
            
            if (!sourceNode || !targetNode) return;
            
            // 곡선 연결선
            const curve = new THREE.QuadraticBezierCurve3(
                new THREE.Vector3(sourceNode.x, sourceNode.y, sourceNode.z),
                new THREE.Vector3(
                    (sourceNode.x + targetNode.x) / 2,
                    (sourceNode.y + targetNode.y) / 2 + 20,
                    (sourceNode.z + targetNode.z) / 2
                ),
                new THREE.Vector3(targetNode.x, targetNode.y, targetNode.z)
            );
            
            const points = curve.getPoints(50);
            const geometry = new THREE.BufferGeometry().setFromPoints(points);
            
            const material = new THREE.LineBasicMaterial({
                color: 0x888888,
                opacity: 0.6,
                transparent: true
            });
            
            const line = new THREE.Line(geometry, material);
            familyLinks.push(line);
            scene.add(line);
        }
        
        // 장면 정리
        function clearScene() {
            // 인물 노드 제거
            personNodes.forEach(node => {
                scene.remove(node);
            });
            personNodes = [];
            
            // 연결선 제거
            familyLinks.forEach(link => {
                scene.remove(link);
            });
            familyLinks = [];
        }
        
        // 카메라 위치 설정
        function setCameraPosition() {
            if (genealogyData.success && genealogyData.camera_position) {
                const pos = genealogyData.camera_position;
                camera.position.set(pos.x, pos.y, pos.z);
            } else {
                camera.position.set(300, 200, 300);
            }
            camera.lookAt(0, 0, 0);
        }
        
        // 애니메이션 루프
        function animate() {
            requestAnimationFrame(animate);
            
            // 자동 회전
            if (isAutoRotating) {
                scene.rotation.y += 0.005;
            }
            
            // 컨트롤 업데이트
            controls.update();
            
            // 렌더링
            renderer.render(scene, camera);
            
            // FPS 업데이트
            updateFPS();
        }
        
        // 통계 업데이트
        function updateStats(nodeCount, linkCount) {
            document.getElementById('nodeCount').textContent = nodeCount;
            document.getElementById('linkCount').textContent = linkCount;
            
            if (genealogyData.success) {
                const range = genealogyData.generation_range;
                document.getElementById('generationSpan').textContent = `${range[0]}-${range[1]}`;
            }
        }
        
        // FPS 업데이트
        let lastTime = performance.now();
        let frameCount = 0;
        
        function updateFPS() {
            frameCount++;
            const currentTime = performance.now();
            
            if (currentTime - lastTime >= 1000) {
                const fps = Math.round(frameCount * 1000 / (currentTime - lastTime));
                document.getElementById('fpsCounter').textContent = fps;
                frameCount = 0;
                lastTime = currentTime;
            }
        }
        
        // 뷰 모드 변경
        function changeViewMode(mode) {
            currentViewMode = mode;
            
            // 버튼 상태 업데이트
            document.querySelectorAll('.view-mode-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-mode="${mode}"]`).classList.add('active');
            
            // 새 URL로 리로드
            const url = new URL(window.location);
            url.searchParams.set('view', mode);
            window.location.href = url.toString();
        }
        
        // 컨트롤 함수들
        function resetCamera() {
            setCameraPosition();
            controls.reset();
        }
        
        function autoRotate() {
            isAutoRotating = !isAutoRotating;
            const btn = event.target.closest('button');
            btn.innerHTML = isAutoRotating ? 
                '<i class="bi bi-pause-circle"></i> 정지' : 
                '<i class="bi bi-arrow-clockwise"></i> 회전';
        }
        
        function takeScreenshot() {
            const link = document.createElement('a');
            link.download = '창녕조씨_3D가계도.png';
            link.href = renderer.domElement.toDataURL();
            link.click();
        }
        
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }
        
        function showHelp() {
            alert(`🌌 3D 가계도 도움말

마우스 조작:
• 드래그: 시점 회전
• 휠: 줌 인/아웃
• 우클릭 드래그: 패닝

뷰 모드:
• 은하수: 나선 은하 형태
• 트리: 전통적인 트리 구조  
• 구형: 3D 구 형태 배치
• 나선: DNA 이중나선 구조

인물 노드:
• 파란색: 남성
• 분홍색: 여성
• 크기: 자녀 수에 비례
• 광택: 중심 인물`);
        }
        
        // 마우스 클릭 이벤트
        function onMouseClick(event) {
            const mouse = new THREE.Vector2();
            mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
            mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
            
            const raycaster = new THREE.Raycaster();
            raycaster.setFromCamera(mouse, camera);
            
            const intersects = raycaster.intersectObjects(personNodes);
            
            if (intersects.length > 0) {
                const selectedObject = intersects[0].object;
                showPersonInfo(selectedObject.userData);
            }
        }
        
        // 인물 정보 표시
        function showPersonInfo(personData) {
            const panel = document.getElementById('personInfoPanel');
            const content = document.getElementById('personInfoContent');
            
            content.innerHTML = `
                <div class="person-info">
                    <h6 class="fw-bold">${personData.name}</h6>
                    ${personData.name_hanja ? `<p class="small mb-1">한자: ${personData.name_hanja}</p>` : ''}
                    <p class="small mb-1">세대: ${personData.generation}세</p>
                    <p class="small mb-1">성별: ${personData.gender}</p>
                    <p class="small mb-0">자녀: ${personData.child_count}명</p>
                </div>
                <button class="btn btn-outline-light btn-sm w-100" onclick="focusOnPerson(${personData.id})">
                    <i class="bi bi-crosshair"></i> 중심으로 이동
                </button>
            `;
            
            panel.style.display = 'block';
            selectedPerson = personData;
        }
        
        function closePersonInfo() {
            document.getElementById('personInfoPanel').style.display = 'none';
            selectedPerson = null;
        }
        
        function focusOnPerson(personId) {
            const url = new URL(window.location);
            url.searchParams.set('person_id', personId);
            window.location.href = url.toString();
        }
        
        // 이벤트 리스너들
        document.addEventListener('DOMContentLoaded', function() {
            // 뷰 모드 버튼
            document.querySelectorAll('.view-mode-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    changeViewMode(this.getAttribute('data-mode'));
                });
            });
            
            // 세대 슬라이더
            document.getElementById('generationRange').addEventListener('input', function() {
                document.getElementById('genRangeCurrent').textContent = this.value + '세대부터';
            });
            
            // 마우스 클릭
            document.addEventListener('click', onMouseClick);
            
            // 윈도우 리사이즈
            window.addEventListener('resize', function() {
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(window.innerWidth, window.innerHeight);
            });
            
            // 키보드 단축키
            document.addEventListener('keydown', function(event) {
                switch(event.code) {
                    case 'KeyR':
                        resetCamera();
                        break;
                    case 'KeyA':
                        autoRotate();
                        break;
                    case 'KeyS':
                        takeScreenshot();
                        break;
                    case 'KeyH':
                        showHelp();
                        break;
                    case 'F11':
                        event.preventDefault();
                        toggleFullscreen();
                        break;
                }
            });
            
            // 3D 장면 초기화
            init3DScene();
        });
    </script>
</body>
</html>