<?php if (isset($error) && $error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php else: ?>

<!-- 통계 카드들 -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                <h3 class="text-primary"><?= number_format($stats['total_persons'] ?? 0) ?></h3>
                <p class="card-text">전체 인물</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-heartbeat fa-3x text-success mb-3"></i>
                <h3 class="text-success"><?= number_format($stats['alive_persons'] ?? 0) ?></h3>
                <p class="card-text">생존 인물</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-layer-group fa-3x text-info mb-3"></i>
                <h3 class="text-info"><?= count($stats['by_generation'] ?? []) ?></h3>
                <p class="card-text">활성 세대</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar fa-3x text-warning mb-3"></i>
                <h3 class="text-warning">45</h3>
                <p class="card-text">전체 세대</p>
            </div>
        </div>
    </div>
</div>

<!-- 빠른 액션 버튼들 -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-rocket"></i> 빠른 메뉴
            </div>
            <div class="card-body text-center">
                <a href="?page=persons" class="btn btn-primary btn-lg me-3 mb-2">
                    <i class="fas fa-users"></i> 전체 인물 보기
                </a>
                <a href="?page=generation&start=1&end=10" class="btn btn-outline-primary btn-lg me-3 mb-2">
                    <i class="fas fa-layer-group"></i> 1-10세대 보기
                </a>
                <button onclick="showGenerationSearch()" class="btn btn-outline-primary btn-lg me-3 mb-2">
                    <i class="fas fa-search"></i> 세대 검색
                </button>
                <button onclick="analyzeRelationship()" class="btn btn-outline-primary btn-lg mb-2">
                    <i class="fas fa-project-diagram"></i> 관계 분석
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- 세대별 통계 -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar"></i> 세대별 인구 분포
            </div>
            <div class="card-body">
                <?php if (isset($stats['by_generation']) && !empty($stats['by_generation'])): ?>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead class="sticky-top">
                            <tr>
                                <th>세대</th>
                                <th>인원수</th>
                                <th>비율</th>
                                <th>액션</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalPersons = $stats['total_persons'] ?? 1;
                            foreach ($stats['by_generation'] as $gen): 
                                $percentage = round(($gen['count'] / $totalPersons) * 100, 1);
                            ?>
                            <tr>
                                <td>
                                    <span class="generation-badge"><?= $gen['generation'] ?>세대</span>
                                </td>
                                <td><?= number_format($gen['count']) ?>명</td>
                                <td>
                                    <div class="progress" style="width: 60px; height: 10px;">
                                        <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <small><?= $percentage ?>%</small>
                                </td>
                                <td>
                                    <a href="?page=persons&generation=<?= $gen['generation'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> 보기
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-chart-bar fa-3x mb-3"></i>
                    <p>세대별 데이터가 없습니다.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 최근 등록 인물 -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock"></i> 최근 등록된 인물
            </div>
            <div class="card-body">
                <?php if (isset($stats['recent_persons']) && !empty($stats['recent_persons'])): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($stats['recent_persons'] as $person): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($person['name']) ?></h6>
                                <?php if (!empty($person['name_hanja'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($person['name_hanja']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="generation-badge"><?= $person['generation'] ?>세대</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="?page=persons" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list"></i> 전체 목록 보기
                    </a>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-user-plus fa-3x mb-3"></i>
                    <p>등록된 인물이 없습니다.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 시스템 정보 -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> 시스템 정보
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>족보 시스템</strong><br>
                        <small class="text-muted">창녕조씨 족보관리</small>
                    </div>
                    <div class="col-md-3">
                        <strong>시조</strong><br>
                        <small class="text-muted">조계룡(趙季龍)</small>
                    </div>
                    <div class="col-md-3">
                        <strong>관리 범위</strong><br>
                        <small class="text-muted">1세대 ~ 45세대</small>
                    </div>
                    <div class="col-md-3">
                        <strong>개발</strong><br>
                        <small class="text-muted">닥터조 (주)조유</small>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <a href="test_db.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-database"></i> DB 연결 상태
                    </a>
                    <a href="?page=api&action=stats" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-chart-line"></i> 상세 통계
                    </a>
                    <a href="#" onclick="copyToClipboard(window.location.href)" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-link"></i> URL 복사
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>