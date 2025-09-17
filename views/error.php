<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-5x text-danger mb-4"></i>
                <h3 class="text-danger">오류가 발생했습니다</h3>
                
                <?php if (isset($error_message)): ?>
                <div class="error-box mt-3">
                    <strong>오류 내용:</strong><br>
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <h5>문제 해결 방법:</h5>
                    <ul class="list-unstyled text-start">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            데이터베이스 연결 상태를 확인해보세요
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            URL 주소가 올바른지 확인해보세요
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            페이지를 새로고침 해보세요
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> 
                            문제가 지속되면 시스템 관리자에게 문의하세요
                        </li>
                    </ul>
                </div>
                
                <div class="mt-4">
                    <a href="?page=dashboard" class="btn btn-primary me-2">
                        <i class="fas fa-home"></i> 대시보드로 돌아가기
                    </a>
                    <a href="test_db.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-database"></i> DB 상태 확인
                    </a>
                    <button onclick="window.history.back()" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 이전 페이지
                    </button>
                </div>
                
                <hr class="my-4">
                
                <div class="text-muted small">
                    <p><strong>기술 지원:</strong> 닥터조 (주)조유</p>
                    <p><strong>발생 시간:</strong> <?= date('Y-m-d H:i:s') ?></p>
                    <?php if (isset($_SERVER['HTTP_USER_AGENT'])): ?>
                    <p><strong>브라우저:</strong> <?= htmlspecialchars($_SERVER['HTTP_USER_AGENT']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>