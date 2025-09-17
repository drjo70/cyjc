<?php if (isset($error) && $error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php else: ?>

<!-- 검색/필터 옵션 -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="page" value="persons">
                    
                    <div class="col-md-4">
                        <label class="form-label">이름 검색</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="한글명 또는 한자명" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">세대 선택</label>
                        <select class="form-select" name="generation">
                            <option value="">전체 세대</option>
                            <?php for ($i = 1; $i <= 45; $i++): ?>
                            <option value="<?= $i ?>" <?= ($_GET['generation'] ?? '') == $i ? 'selected' : '' ?>>
                                <?= $i ?>세대
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">표시 개수</label>
                        <select class="form-select" name="limit">
                            <option value="20" <?= ($_GET['limit'] ?? '20') == '20' ? 'selected' : '' ?>>20개</option>
                            <option value="50" <?= ($_GET['limit'] ?? '20') == '50' ? 'selected' : '' ?>>50개</option>
                            <option value="100" <?= ($_GET['limit'] ?? '20') == '100' ? 'selected' : '' ?>>100개</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> 검색
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 검색 결과 정보 -->
<?php if (isset($search_term)): ?>
<div class="alert alert-info">
    <i class="fas fa-search"></i> 
    "<strong><?= htmlspecialchars($search_term) ?></strong>" 검색 결과: <?= count($persons) ?>명
</div>
<?php elseif (isset($generation)): ?>
<div class="alert alert-info">
    <i class="fas fa-layer-group"></i> 
    <strong><?= $generation ?>세대</strong> 인물 목록: <?= count($persons) ?>명
</div>
<?php endif; ?>

<!-- 인물 목록 -->
<?php if (!empty($persons)): ?>

<!-- 카드 뷰 (모바일 친화적) -->
<div class="d-md-none">
    <?php foreach ($persons as $person): ?>
    <div class="card mb-3 person-card" data-person-code="<?= htmlspecialchars($person['person_code']) ?>" style="cursor: pointer;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="card-title mb-1"><?= htmlspecialchars($person['name']) ?></h5>
                    <?php if (!empty($person['name_hanja'])): ?>
                    <p class="card-text text-muted small"><?= htmlspecialchars($person['name_hanja']) ?></p>
                    <?php endif; ?>
                    <p class="card-text">
                        <span class="generation-badge"><?= $person['generation'] ?>세대</span>
                        <?php if ($person['is_alive']): ?>
                        <span class="badge bg-success ms-1">생존</span>
                        <?php else: ?>
                        <span class="badge bg-secondary ms-1">작고</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="text-end">
                    <small class="text-muted"><?= htmlspecialchars($person['person_code']) ?></small>
                </div>
            </div>
            
            <div class="mt-2">
                <?php if (!empty($person['birth_date'])): ?>
                <small class="text-muted">생: <?= htmlspecialchars($person['birth_date']) ?></small>
                <?php endif; ?>
                <?php if (!empty($person['death_date'])): ?>
                <small class="text-muted"> | 몰: <?= htmlspecialchars($person['death_date']) ?></small>
                <?php endif; ?>
            </div>
            
            <div class="mt-2 d-flex gap-1">
                <button onclick="showPersonDetail('<?= $person['person_code'] ?>')" class="btn btn-sm btn-outline-primary">상세</button>
                <button onclick="showFamilyTree('<?= $person['person_code'] ?>')" class="btn btn-sm btn-outline-secondary">가족</button>
                <button onclick="showLineage('<?= $person['person_code'] ?>')" class="btn btn-sm btn-outline-info">혈통</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- 테이블 뷰 (데스크톱) -->
<div class="d-none d-md-block">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>성명</th>
                        <th>한자명</th>
                        <th>세대</th>
                        <th>생년월일</th>
                        <th>사망일</th>
                        <th>상태</th>
                        <th>연락처</th>
                        <th>액션</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($persons as $person): ?>
                    <tr class="person-row" data-person-code="<?= htmlspecialchars($person['person_code']) ?>">
                        <td>
                            <strong><?= htmlspecialchars($person['name']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($person['person_code']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($person['name_hanja'] ?? '') ?></td>
                        <td>
                            <span class="generation-badge"><?= $person['generation'] ?>세대</span>
                        </td>
                        <td><?= htmlspecialchars($person['birth_date'] ?? '') ?></td>
                        <td><?= htmlspecialchars($person['death_date'] ?? '') ?></td>
                        <td>
                            <?php if ($person['is_alive']): ?>
                            <span class="badge bg-success">생존</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">작고</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($person['phone'] ?? '') ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <button onclick="showPersonDetail('<?= $person['person_code'] ?>')" 
                                        class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="tooltip" title="상세 정보">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="showFamilyTree('<?= $person['person_code'] ?>')" 
                                        class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="tooltip" title="가족 관계도">
                                    <i class="fas fa-sitemap"></i>
                                </button>
                                <button onclick="showLineage('<?= $person['person_code'] ?>')" 
                                        class="btn btn-sm btn-outline-info"
                                        data-bs-toggle="tooltip" title="혈통 추적">
                                    <i class="fas fa-route"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 페이지네이션 (전체 목록일 때만) -->
<?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
<div class="d-flex justify-content-center mt-4">
    <nav>
        <ul class="pagination">
            <?php 
            $currentPage = $pagination['current_page'];
            $totalPages = $pagination['total_pages'];
            $limit = $_GET['limit'] ?? 20;
            
            // 이전 페이지
            if ($currentPage > 1):
            ?>
            <li class="page-item">
                <a class="page-link" href="?page=persons&page=<?= $currentPage - 1 ?>&limit=<?= $limit ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            
            <?php
            // 페이지 번호 표시 (현재 페이지 기준 ±2페이지)
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
            <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=persons&page=<?= $i ?>&limit=<?= $limit ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            
            <?php 
            // 다음 페이지
            if ($currentPage < $totalPages):
            ?>
            <li class="page-item">
                <a class="page-link" href="?page=persons&page=<?= $currentPage + 1 ?>&limit=<?= $limit ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

<div class="text-center text-muted mt-2">
    <small>
        총 <?= number_format($pagination['total']) ?>명 중 
        <?= number_format(($currentPage - 1) * $limit + 1) ?>-<?= number_format(min($currentPage * $limit, $pagination['total'])) ?>번째 표시
    </small>
</div>
<?php endif; ?>

<?php else: ?>
<!-- 검색 결과 없음 -->
<div class="text-center py-5">
    <i class="fas fa-search fa-5x text-muted mb-4"></i>
    <h3 class="text-muted">검색 결과가 없습니다</h3>
    <p class="text-muted">다른 검색어나 필터 조건을 시도해보세요.</p>
    <a href="?page=persons" class="btn btn-primary">전체 목록 보기</a>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- JavaScript 추가 -->
<script>
// 테이블 행 클릭 시 상세 페이지로 이동
document.querySelectorAll('.person-row').forEach(function(row) {
    row.addEventListener('click', function(e) {
        // 버튼 클릭은 제외
        if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'I') {
            const personCode = this.getAttribute('data-person-code');
            showPersonDetail(personCode);
        }
    });
});
</script>