<?php
// 공통 헤더 메뉴 컴포넌트
// 모든 페이지에서 include하여 사용
?>
<nav class="bg-gradient-to-r from-blue-600 to-green-600 text-white shadow-lg sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- 로고 -->
            <div class="flex items-center">
                <a href="index.php" class="flex items-center space-x-2 hover:text-blue-200 transition-colors">
                    <i class="fas fa-tree text-xl"></i>
                    <span class="font-bold text-lg">창녕조씨 족보</span>
                </a>
            </div>
            
            <!-- 메인 메뉴 -->
            <div class="hidden md:flex items-center space-x-1">
                <a href="index.php" class="px-3 py-2 rounded-lg text-sm font-medium hover:bg-white hover:bg-opacity-10 transition-colors">
                    <i class="fas fa-home mr-1"></i>메인
                </a>
                <a href="search.php" class="px-3 py-2 rounded-lg text-sm font-medium hover:bg-white hover:bg-opacity-10 transition-colors">
                    <i class="fas fa-search mr-1"></i>인물검색
                </a>
                <a href="family_lineage.php" class="px-3 py-2 rounded-lg text-sm font-medium hover:bg-white hover:bg-opacity-10 transition-colors">
                    <i class="fas fa-project-diagram mr-1"></i>직계혈통
                </a>
                
                <?php
                // 로그인 상태 확인
                $user_info = function_exists('getUserInfo') ? getUserInfo() : null;
                $is_verified = function_exists('isVerifiedMember') ? isVerifiedMember() : false;
                
                if ($is_verified && $user_info):
                ?>
                <div class="relative group">
                    <button class="px-3 py-2 rounded-lg text-sm font-medium hover:bg-white hover:bg-opacity-10 transition-colors flex items-center">
                        <i class="fas fa-edit mr-1"></i>족보관리
                        <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                        <a href="genealogy_edit.php?person_code=<?= $user_info['person_code'] ?? '' ?>" class="block px-4 py-2 text-gray-800 hover:bg-blue-50 text-sm">
                            <i class="fas fa-user-edit mr-2 text-blue-600"></i>내 정보 수정
                        </a>
                        <a href="my_profile.php" class="block px-4 py-2 text-gray-800 hover:bg-blue-50 text-sm">
                            <i class="fas fa-user mr-2 text-green-600"></i>내 프로필
                        </a>
                    </div>
                </div>
                
                <?php if ($user_info && isset($user_info['access_level']) && $user_info['access_level'] == 1): ?>
                <a href="admin.php" class="px-3 py-2 rounded-lg text-sm font-medium hover:bg-white hover:bg-opacity-10 transition-colors bg-yellow-500 bg-opacity-20 border border-yellow-300">
                    <i class="fas fa-crown mr-1 text-yellow-200"></i>관리자
                </a>
                <?php endif; ?>
                
                <div class="pl-2 ml-2 border-l border-white border-opacity-30">
                    <span class="text-sm text-blue-100"><?= htmlspecialchars($user_info['name'] ?? '사용자') ?>님</span>
                    <a href="logout.php" class="ml-2 px-2 py-1 text-xs bg-white bg-opacity-20 rounded hover:bg-opacity-30 transition-colors">
                        로그아웃
                    </a>
                </div>
                
                <?php else: ?>
                <a href="login.php" class="px-3 py-2 bg-white bg-opacity-20 rounded-lg text-sm font-medium hover:bg-opacity-30 transition-colors">
                    <i class="fas fa-sign-in-alt mr-1"></i>로그인
                </a>
                <?php endif; ?>
            </div>
            
            <!-- 모바일 메뉴 버튼 -->
            <div class="md:hidden">
                <button id="mobile-menu-button" class="p-2 rounded-lg hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- 모바일 메뉴 (개선된 슬라이딩 애니메이션) -->
        <div id="mobile-menu" class="md:hidden overflow-hidden transition-all duration-300 ease-in-out max-h-0">
            <div class="pb-4 space-y-2 pt-4 border-t border-white border-opacity-20">
                <a href="index.php" class="block px-3 py-2 rounded-lg text-sm hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-home mr-2"></i>메인
                </a>
                <a href="search.php" class="block px-3 py-2 rounded-lg text-sm hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-search mr-2"></i>인물검색
                </a>
                <a href="family_lineage.php" class="block px-3 py-2 rounded-lg text-sm hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-project-diagram mr-2"></i>직계혈통
                </a>
                
                <?php if ($is_verified && $user_info): ?>
                <a href="genealogy_edit.php?person_code=<?= $user_info['person_code'] ?? '' ?>" class="block px-3 py-2 rounded-lg text-sm hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-user-edit mr-2"></i>내 정보 수정
                </a>
                <a href="my_profile.php" class="block px-3 py-2 rounded-lg text-sm hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-user mr-2"></i>내 프로필
                </a>
                
                <?php if (isset($user_info['access_level']) && $user_info['access_level'] == 1): ?>
                <a href="admin.php" class="block px-3 py-2 rounded-lg text-sm hover:bg-white hover:bg-opacity-10 bg-yellow-500 bg-opacity-20">
                    <i class="fas fa-crown mr-2 text-yellow-200"></i>관리자
                </a>
                <?php endif; ?>
                
                <div class="pt-2 mt-2 border-t border-white border-opacity-30">
                    <div class="px-3 py-2 text-sm text-blue-100">
                        <?= htmlspecialchars($user_info['name'] ?? '사용자') ?>님
                    </div>
                    <a href="logout.php" class="block px-3 py-2 rounded-lg text-sm hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-sign-out-alt mr-2"></i>로그아웃
                    </a>
                </div>
                
                <?php else: ?>
                <a href="login.php" class="block px-3 py-2 rounded-lg text-sm hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-sign-in-alt mr-2"></i>로그인
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
// 모바일 메뉴 토글 (개선된 애니메이션)
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = mobileMenuButton.querySelector('i');
    let isOpen = false;
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            isOpen = !isOpen;
            
            if (isOpen) {
                // 메뉴 열기
                mobileMenu.style.maxHeight = mobileMenu.scrollHeight + 'px';
                hamburgerIcon.className = 'fas fa-times text-xl';
            } else {
                // 메뉴 닫기
                mobileMenu.style.maxHeight = '0';
                hamburgerIcon.className = 'fas fa-bars text-xl';
            }
        });
        
        // 외부 클릭시 메뉴 닫기
        document.addEventListener('click', function(e) {
            if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                if (isOpen) {
                    isOpen = false;
                    mobileMenu.style.maxHeight = '0';
                    hamburgerIcon.className = 'fas fa-bars text-xl';
                }
            }
        });
    }
});
</script>