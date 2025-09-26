<?php
// 창녕조씨 족보 시스템 - 로그인 페이지
require_once 'config.php';

// 이미 로그인된 경우 리다이렉트
if (isLoggedIn()) {
    if (isVerifiedMember()) {
        header('Location: index.php');
    } else {
        header('Location: verification.php');
    }
    exit;
}

$error_message = '';
$success_message = '';

// 메시지 처리
if (isset($_GET['error'])) {
    $detailed_msg = isset($_GET['msg']) ? $_GET['msg'] : '';
    
    switch ($_GET['error']) {
        case 'auth_failed':
            $error_message = '구글 로그인에 실패했습니다. 다시 시도해주세요.';
            if ($detailed_msg) {
                $error_message .= '<br><small>상세: ' . htmlspecialchars($detailed_msg) . '</small>';
            }
            break;
        case 'no_code':
            $error_message = '인증 코드를 받지 못했습니다. 다시 로그인해주세요.';
            break;
        case 'no_email':
            $error_message = '이메일 정보를 가져올 수 없습니다. 구글 계정 권한을 확인해주세요.';
            break;
        case 'registration_failed':
            $error_message = '회원가입 처리 중 오류가 발생했습니다.';
            if ($detailed_msg) {
                $error_message .= '<br><small>오류 내용: ' . htmlspecialchars($detailed_msg) . '</small>';
            }
            break;
        default:
            $error_message = '로그인 중 오류가 발생했습니다.';
            if ($detailed_msg) {
                $error_message .= '<br><small>' . htmlspecialchars($detailed_msg) . '</small>';
            }
    }
}

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'logout':
            $success_message = '성공적으로 로그아웃되었습니다.';
            break;
    }
}

$google_auth_url = getGoogleAuthUrl();
$kakao_auth_url = getKakaoAuthUrl();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 창녕조씨 족보 시스템</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .google-btn {
            background: #4285f4;
            transition: all 0.3s ease;
        }
        .google-btn:hover {
            background: #357ae8;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(66, 133, 244, 0.4);
        }
        .kakao-btn {
            background: #FEE500 !important;
            color: #000000 !important;
            transition: all 0.3s ease;
        }
        .kakao-btn:hover {
            background: #FDD835 !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(254, 229, 0, 0.4);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- 상단 헤더 -->
    <header class="gradient-bg shadow-lg">
        <div class="container mx-auto px-6 py-6">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-white mb-2">
                    <i class="fas fa-tree mr-3"></i>창녕조씨 족보 시스템
                </h1>
                <p class="text-xl text-indigo-100">가문 구성원 인증 로그인</p>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8">
        <div class="max-w-md mx-auto">
            <!-- 로그인 카드 -->
            <div class="bg-white rounded-2xl shadow-2xl p-8 card-hover">
                <div class="text-center mb-8">
                    <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-shield-alt text-white text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">가문 구성원 로그인</h2>
                    <p class="text-gray-600">구글 계정으로 로그인 후 가문 구성원 인증을 진행합니다</p>
                </div>

                <!-- 메시지 표시 -->
                <?php if ($error_message): ?>
                    <?= showError($error_message) ?>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <?= showSuccess($success_message) ?>
                <?php endif; ?>

                <!-- 로그인 버튼들 -->
                <div class="space-y-4">
                    <!-- 구글 로그인 버튼 -->
                    <a href="<?= htmlspecialchars($google_auth_url) ?>" 
                       class="google-btn w-full flex items-center justify-center px-6 py-4 text-white rounded-xl font-medium text-lg">
                        <svg class="w-6 h-6 mr-3" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Google로 로그인
                    </a>

                    <!-- 카카오 로그인 버튼 -->
                    <a href="<?= htmlspecialchars($kakao_auth_url) ?>" 
                       class="kakao-btn w-full flex items-center justify-center px-6 py-4 text-black rounded-xl font-medium text-lg bg-yellow-400 hover:bg-yellow-500 transition-colors">
                        <svg class="w-6 h-6 mr-3" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 3C7.03 3 3 6.26 3 10.25c0 2.52 1.63 4.75 4.1 6.05L6.12 19.5c-.1.35.27.64.58.44l3.24-2.12c.65.08 1.32.13 2.01.13 4.97 0 9-3.26 9-7.25S16.97 3 12 3z"/>
                        </svg>
                        카카오톡으로 로그인
                    </a>

                    <!-- 구분선 -->
                    <div class="relative my-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">또는</span>
                        </div>
                    </div>

                    <div class="text-center">
                        <div class="border-t border-gray-200 pt-4">
                            <p class="text-sm text-gray-500 mb-2">로그인 후 진행 단계</p>
                            <div class="space-y-2 text-left">
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                    <span>1. 구글 계정으로 로그인</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-user-check text-blue-500 mr-2"></i>
                                    <span>2. 이름 + 전화번호 인증</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-family text-purple-500 mr-2"></i>
                                    <span>3. 가족 관계 확인 (필요시)</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-unlock text-emerald-500 mr-2"></i>
                                    <span>4. 족보 시스템 접근</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 도움말 카드 -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mt-8">
                <h3 class="text-lg font-semibold text-blue-800 mb-3">
                    <i class="fas fa-info-circle mr-2"></i>인증 안내
                </h3>
                <div class="space-y-2 text-sm text-blue-700">
                    <p>• <strong>가문 구성원만</strong> 접근할 수 있는 시스템입니다</p>
                    <p>• <strong>실명</strong>과 <strong>전화번호</strong>로 신원을 확인합니다</p>
                    <p>• 전화번호가 없는 경우 <strong>가족 관계</strong>로 인증합니다</p>
                    <p>• 인증이 완료되면 모든 족보 기능을 이용할 수 있습니다</p>
                </div>
            </div>

            <!-- 문의 정보 -->
            <div class="text-center mt-8">
                <p class="text-gray-500 text-sm">
                    인증에 문제가 있으시면 
                    <a href="mailto:admin@changnyeongjo.com" class="text-blue-600 hover:text-blue-800 font-medium">
                        관리자에게 문의
                    </a>
                    해주세요
                </p>
            </div>

            <!-- 홈으로 돌아가기 -->
            <div class="text-center mt-6">
                <a href="index.php" class="inline-flex items-center text-gray-600 hover:text-gray-800 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    로그인 없이 둘러보기
                </a>
            </div>
        </div>
    </main>

    <!-- 푸터 -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="container mx-auto px-6 text-center">
            <p class="mb-4">&copy; 2024 창녕조씨 족보 시스템. 닥터조 개발.</p>
            <p class="text-gray-400 text-sm">
                가문의 역사를 디지털로 보존하고 전승하는 현대적 족보 시스템
            </p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 로그인 카드 애니메이션
            const card = document.querySelector('.card-hover');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 200);

            // 구글 로그인 버튼 클릭 효과
            const googleBtn = document.querySelector('.google-btn');
            googleBtn.addEventListener('click', function() {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
</body>
</html>