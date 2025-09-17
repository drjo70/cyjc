<?php
/**
 * 창녕조씨 족보 시스템 - 인터랙티브 가계도 페이지
 * 
 * @author 닥터조 (주)조유
 * @version 1.0
 * @date 2024-09-17
 */

require_once 'config/database.php';
require_once 'models/Person.php';

$person = new Person();

// 기본 파라미터
$start_generation = $_GET['generation'] ?? 1;
$focus_person_id = $_GET['person_id'] ?? null;
$max_depth = $_GET['depth'] ?? 5;

// 가계도 데이터 준비
$tree_data = buildFamilyTreeData($start_generation, $max_depth, $focus_person_id);

/**
 * 가계도 데이터 구성 함수
 */
function buildFamilyTreeData($start_gen, $max_depth, $focus_id = null) {
    global $person;
    
    try {
        $db = getDB();
        
        // 포커스 인물이 있으면 해당 인물 중심으로, 없으면 1세대 시조 중심
        if ($focus_id) {
            $focusStmt = $db->prepare("SELECT * FROM family_members WHERE id = ?");
            $focusStmt->execute([$focus_id]);
            $focusPerson = $focusStmt->fetch();
            
            if ($focusPerson) {
                $start_gen = max(1, $focusPerson['generation'] - 2); // 위 2세대부터 시작
                $max_depth = 6; // 아래 5세대까지
            }
        }
        
        $end_gen = min(45, $start_gen + $max_depth);
        
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
        
        // 트리 구조 생성
        $tree = [];
        $personMap = [];
        
        foreach ($persons as $p) {
            $personMap[$p['person_code']] = $p;
            $p['children'] = [];
            $tree[$p['person_code']] = $p;
        }
        
        // 부모-자식 관계 설정
        foreach ($tree as $code => $p) {
            if ($p['parent_code'] && isset($personMap[$p['parent_code']])) {
                if (!isset($tree[$p['parent_code']]['children'])) {
                    $tree[$p['parent_code']]['children'] = [];
                }
                $tree[$p['parent_code']]['children'][] = $p;
            }
        }
        
        // 루트 노드들 찾기 (해당 범위에서 부모가 없는 노드들)
        $roots = [];
        foreach ($tree as $code => $p) {
            if (!$p['parent_code'] || !isset($personMap[$p['parent_code']])) {
                $roots[] = $p;
            }
        }
        
        return [
            'success' => true,
            'data' => [
                'roots' => $roots,
                'all_persons' => $persons,
                'person_map' => $personMap,
                'focus_person' => $focus_id ? ($focusPerson ?? null) : null,
                'generation_range' => [$start_gen, $end_gen]
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '가계도 데이터 구성 실패: ' . $e->getMessage(),
            'data' => []
        ];
    }
}

// 세대 목록 (선택용)
$gen_range_result = $person->getGenerationRange();
$gen_range = $gen_range_result['success'] ? $gen_range_result['data'] : ['min_gen' => 1, 'max_gen' => 45];

// 최근 인물들 (빠른 선택용)
$recent_persons_result = $person->getAllPersons(1, 20);
$recent_persons = $recent_persons_result['success'] ? $recent_persons_result['data'] : [];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>인터랙티브 가계도 - 창녕조씨 족보 시스템</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- D3.js -->
    <script src="https://d3js.org/d3.v7.min.js"></script>
    
    <style>
        .tree-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            min-height: 600px;
            overflow: auto;
            border: 2px solid #e9ecef;
        }
        
        .tree-controls {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .person-node {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .person-node:hover {
            stroke-width: 3px;
        }
        
        .person-node.male {
            fill: #4A90E2;
            stroke: #357ABD;
        }
        
        .person-node.female {
            fill: #E24A90;
            stroke: #BD5785;
        }
        
        .person-node.focus {
            fill: #F5A623;
            stroke: #D4920A;
            stroke-width: 4px;
            filter: drop-shadow(0 0 10px rgba(245, 166, 35, 0.6));
        }
        
        .person-text {
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 11px;
            font-weight: bold;
            text-anchor: middle;
            pointer-events: none;
            fill: white;
        }
        
        .generation-text {
            font-size: 10px;
            fill: #666;
            text-anchor: middle;
            pointer-events: none;
        }
        
        .family-link {
            fill: none;
            stroke: #8E8E8E;
            stroke-width: 2px;
        }
        
        .generation-line {
            stroke: #ddd;
            stroke-width: 1px;
            stroke-dasharray: 5,5;
        }
        
        .tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            pointer-events: none;
            z-index: 1000;
            max-width: 200px;
        }
        
        .tree-legend {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        #family-tree-svg {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: white;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'views/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- 컨트롤 패널 -->
            <div class="col-lg-3">
                <div class="tree-controls">
                    <h5 class="fw-bold mb-3"><i class="bi bi-gear"></i> 가계도 설정</h5>
                    
                    <form method="GET" action="family_tree.php">
                        <!-- 시작 세대 -->
                        <div class="mb-3">
                            <label for="generation" class="form-label fw-bold">시작 세대</label>
                            <select class="form-select" id="generation" name="generation">
                                <?php for ($i = $gen_range['min_gen']; $i <= $gen_range['max_gen']; $i++): ?>
                                    <option value="<?= $i ?>" <?= $start_generation == $i ? 'selected' : '' ?>>
                                        <?= $i ?>세대
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <!-- 표시 깊이 -->
                        <div class="mb-3">
                            <label for="depth" class="form-label fw-bold">표시 범위</label>
                            <select class="form-select" id="depth" name="depth">
                                <option value="3" <?= $max_depth == 3 ? 'selected' : '' ?>>3세대</option>
                                <option value="5" <?= $max_depth == 5 ? 'selected' : '' ?>>5세대</option>
                                <option value="8" <?= $max_depth == 8 ? 'selected' : '' ?>>8세대</option>
                                <option value="10" <?= $max_depth == 10 ? 'selected' : '' ?>>10세대</option>
                            </select>
                        </div>
                        
                        <!-- 포커스 인물 -->
                        <div class="mb-3">
                            <label for="person_id" class="form-label fw-bold">중심 인물</label>
                            <select class="form-select" id="person_id" name="person_id">
                                <option value="">전체 보기</option>
                                <?php foreach ($recent_persons as $rp): ?>
                                    <option value="<?= $rp['id'] ?>" <?= $focus_person_id == $rp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rp['name']) ?> (<?= $rp['generation'] ?>세)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise"></i> 업데이트
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- 범례 -->
                <div class="tree-legend mt-3">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle"></i> 범례</h6>
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#4A90E2;border-radius:50%;margin-right:8px;"></div>
                        <small>남성</small>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#E24A90;border-radius:50%;margin-right:8px;"></div>
                        <small>여성</small>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div style="width:20px;height:20px;background:#F5A623;border-radius:50%;margin-right:8px;box-shadow:0 0 6px rgba(245,166,35,0.6);"></div>
                        <small>중심 인물</small>
                    </div>
                    <hr class="my-2">
                    <small class="text-muted">
                        • 클릭: 상세 정보<br>
                        • 드래그: 이동<br>
                        • 휠: 줌
                    </small>
                </div>
                
                <!-- 통계 정보 -->
                <?php if ($tree_data['success']): ?>
                    <div class="tree-legend mt-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart"></i> 현재 보기</h6>
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="fw-bold text-primary"><?= count($tree_data['data']['all_persons']) ?></div>
                                <small class="text-muted">인물 수</small>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold text-success">
                                    <?= $tree_data['data']['generation_range'][1] - $tree_data['data']['generation_range'][0] + 1 ?>
                                </div>
                                <small class="text-muted">세대 수</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 가계도 영역 -->
            <div class="col-lg-9">
                <div class="tree-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0">
                            <i class="bi bi-diagram-3"></i> 
                            <?php if ($tree_data['success'] && $tree_data['data']['focus_person']): ?>
                                <?= htmlspecialchars($tree_data['data']['focus_person']['name']) ?> 중심 가계도
                            <?php else: ?>
                                창녕조씨 가계도 (<?= $start_generation ?>세 ~ <?= $start_generation + $max_depth ?>세)
                            <?php endif; ?>
                        </h4>
                        
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetZoom()">
                                <i class="bi bi-zoom-out"></i> 리셋
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="fitToScreen()">
                                <i class="bi bi-aspect-ratio"></i> 맞춤
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="downloadSVG()">
                                <i class="bi bi-download"></i> 다운로드
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($tree_data['success']): ?>
                        <div id="tree-visualization">
                            <svg id="family-tree-svg" width="100%" height="600"></svg>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            가계도를 불러올 수 없습니다: <?= htmlspecialchars($tree_data['message']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 인물 상세 모달 -->
    <div class="modal fade" id="personModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">인물 상세 정보</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="personModalBody">
                    <!-- 동적 로드 -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                    <a href="#" id="personDetailLink" class="btn btn-primary">상세 페이지</a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'views/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($tree_data['success']): ?>
    <script>
        // 가계도 데이터
        const treeData = <?= json_encode($tree_data['data'], JSON_UNESCAPED_UNICODE) ?>;
        const focusPersonId = <?= json_encode($focus_person_id) ?>;
        
        // SVG 설정
        const svg = d3.select("#family-tree-svg");
        const container = svg.select("g.main-group").empty() ? svg.append("g").attr("class", "main-group") : svg.select("g.main-group");
        
        let width = 800;
        let height = 600;
        
        // 줌 기능
        const zoom = d3.zoom()
            .scaleExtent([0.1, 3])
            .on("zoom", function(event) {
                container.attr("transform", event.transform);
            });
        
        svg.call(zoom);
        
        // 툴팁
        const tooltip = d3.select("body").append("div")
            .attr("class", "tooltip")
            .style("opacity", 0);
        
        // 가계도 그리기
        function drawFamilyTree() {
            container.selectAll("*").remove();
            
            // SVG 크기 업데이트
            const svgElement = document.getElementById('family-tree-svg');
            width = svgElement.clientWidth;
            height = svgElement.clientHeight;
            
            const persons = treeData.all_persons;
            const genRange = treeData.generation_range;
            
            if (!persons || persons.length === 0) return;
            
            // 세대별 그룹핑
            const generationGroups = d3.group(persons, d => d.generation);
            
            // 레이아웃 계산
            const genHeight = height / (genRange[1] - genRange[0] + 2);
            const nodeRadius = 25;
            const nodeSpacing = 120;
            
            // 세대별 위치 계산
            generationGroups.forEach((genPersons, gen) => {
                const genY = (gen - genRange[0] + 1) * genHeight;
                const totalWidth = (genPersons.length - 1) * nodeSpacing;
                const startX = (width - totalWidth) / 2;
                
                genPersons.forEach((person, index) => {
                    person.x = startX + (index * nodeSpacing);
                    person.y = genY;
                });
            });
            
            // 세대 구분선
            container.selectAll(".generation-line")
                .data(Array.from({length: genRange[1] - genRange[0] + 1}, (_, i) => genRange[0] + i))
                .enter()
                .append("line")
                .attr("class", "generation-line")
                .attr("x1", 0)
                .attr("x2", width)
                .attr("y1", (d, i) => (i + 1) * genHeight)
                .attr("y2", (d, i) => (i + 1) * genHeight);
            
            // 세대 레이블
            container.selectAll(".generation-label")
                .data(Array.from({length: genRange[1] - genRange[0] + 1}, (_, i) => genRange[0] + i))
                .enter()
                .append("text")
                .attr("class", "generation-text")
                .attr("x", 20)
                .attr("y", (d, i) => (i + 1) * genHeight - 10)
                .text(d => d + "세대");
            
            // 부모-자식 연결선
            const links = [];
            persons.forEach(person => {
                if (person.parent_code) {
                    const parent = persons.find(p => p.person_code === person.parent_code);
                    if (parent) {
                        links.push({
                            source: parent,
                            target: person
                        });
                    }
                }
            });
            
            container.selectAll(".family-link")
                .data(links)
                .enter()
                .append("path")
                .attr("class", "family-link")
                .attr("d", d => {
                    const sourceX = d.source.x;
                    const sourceY = d.source.y;
                    const targetX = d.target.x;
                    const targetY = d.target.y;
                    
                    const midY = (sourceY + targetY) / 2;
                    
                    return `M ${sourceX},${sourceY} 
                            C ${sourceX},${midY} ${targetX},${midY} ${targetX},${targetY}`;
                });
            
            // 인물 노드
            const nodes = container.selectAll(".person-node")
                .data(persons)
                .enter()
                .append("g")
                .attr("class", "person-group")
                .attr("transform", d => `translate(${d.x}, ${d.y})`);
            
            // 인물 원
            nodes.append("circle")
                .attr("class", d => {
                    let classes = "person-node " + (d.gender === '남' ? 'male' : 'female');
                    if (focusPersonId && d.id == focusPersonId) {
                        classes += " focus";
                    }
                    return classes;
                })
                .attr("r", nodeRadius)
                .on("click", function(event, d) {
                    showPersonModal(d);
                })
                .on("mouseover", function(event, d) {
                    showTooltip(event, d);
                })
                .on("mouseout", function() {
                    hideTooltip();
                });
            
            // 인물 이름
            nodes.append("text")
                .attr("class", "person-text")
                .attr("dy", "0.35em")
                .text(d => {
                    if (d.name.length > 3) {
                        return d.name.substring(0, 3) + '...';
                    }
                    return d.name;
                })
                .style("font-size", "10px");
            
            // 세대 표시 (작은 텍스트)
            nodes.append("text")
                .attr("class", "generation-text")
                .attr("dy", "-35px")
                .text(d => d.generation + "세")
                .style("font-size", "9px");
        }
        
        // 툴팁 표시
        function showTooltip(event, person) {
            tooltip.transition()
                .duration(200)
                .style("opacity", .9);
                
            const birthInfo = person.birth_date && person.birth_date !== '0000-00-00' ? 
                `<br>생년: ${person.birth_date}` : '';
            const deathInfo = person.death_date && person.death_date !== '0000-00-00' ? 
                `<br>몰년: ${person.death_date}` : '';
            const hanjaInfo = person.name_hanja ? `<br>한자: ${person.name_hanja}` : '';
            
            tooltip.html(`
                <strong>${person.name}</strong> (${person.generation}세)${hanjaInfo}
                <br>성별: ${person.gender}
                <br>자녀: ${person.child_count || 0}명${birthInfo}${deathInfo}
            `)
                .style("left", (event.pageX + 10) + "px")
                .style("top", (event.pageY - 28) + "px");
        }
        
        // 툴팁 숨기기
        function hideTooltip() {
            tooltip.transition()
                .duration(500)
                .style("opacity", 0);
        }
        
        // 인물 상세 모달
        function showPersonModal(person) {
            const modalBody = document.getElementById('personModalBody');
            const detailLink = document.getElementById('personDetailLink');
            
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold">기본 정보</h6>
                        <p><strong>이름:</strong> ${person.name}</p>
                        ${person.name_hanja ? `<p><strong>한자명:</strong> ${person.name_hanja}</p>` : ''}
                        <p><strong>세대:</strong> ${person.generation}세</p>
                        <p><strong>성별:</strong> ${person.gender}</p>
                        <p><strong>자녀 수:</strong> ${person.child_count || 0}명</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">생몰년</h6>
                        <p><strong>출생:</strong> ${person.birth_date && person.birth_date !== '0000-00-00' ? person.birth_date : '미상'}</p>
                        <p><strong>사망:</strong> ${person.death_date && person.death_date !== '0000-00-00' ? person.death_date : '미상'}</p>
                        <p><strong>인물코드:</strong> ${person.person_code}</p>
                        ${person.parent_code ? `<p><strong>부모코드:</strong> ${person.parent_code}</p>` : ''}
                    </div>
                </div>
            `;
            
            detailLink.href = `index.php?page=person&id=${person.id}`;
            
            const modal = new bootstrap.Modal(document.getElementById('personModal'));
            modal.show();
        }
        
        // 컨트롤 함수들
        function resetZoom() {
            svg.transition().duration(750).call(
                zoom.transform,
                d3.zoomIdentity
            );
        }
        
        function fitToScreen() {
            const bounds = container.node().getBBox();
            const fullWidth = width;
            const fullHeight = height;
            const scale = 0.9 * Math.min(fullWidth / bounds.width, fullHeight / bounds.height);
            const translate = [fullWidth / 2 - scale * (bounds.x + bounds.width / 2), 
                             fullHeight / 2 - scale * (bounds.y + bounds.height / 2)];
            
            svg.transition().duration(750).call(
                zoom.transform,
                d3.zoomIdentity.translate(translate[0], translate[1]).scale(scale)
            );
        }
        
        function downloadSVG() {
            const svgData = new XMLSerializer().serializeToString(svg.node());
            const svgBlob = new Blob([svgData], {type: "image/svg+xml;charset=utf-8"});
            const svgUrl = URL.createObjectURL(svgBlob);
            
            const downloadLink = document.createElement("a");
            downloadLink.href = svgUrl;
            downloadLink.download = "창녕조씨_가계도.svg";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        // 초기 그리기
        drawFamilyTree();
        
        // 창 크기 변경 시 재그리기
        window.addEventListener('resize', function() {
            setTimeout(drawFamilyTree, 100);
        });
    </script>
    <?php endif; ?>
</body>
</html>