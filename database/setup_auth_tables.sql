-- 사용자 인증 시스템 테이블 생성 SQL
-- 구글 OAuth 로그인 사용자 정보 저장

-- 1. 사용자 인증 테이블
CREATE TABLE IF NOT EXISTS user_auth (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(50) UNIQUE NOT NULL COMMENT '구글 사용자 ID',
    email VARCHAR(255) UNIQUE NOT NULL COMMENT '이메일 주소',
    name VARCHAR(100) NOT NULL COMMENT '사용자 이름',
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending' COMMENT '인증 상태',
    family_member_id INT UNSIGNED NULL COMMENT '족보 내 가족 구성원 ID',
    phone_number VARCHAR(20) NULL COMMENT '전화번호 (인증용)',
    verified_at TIMESTAMP NULL COMMENT '인증 완료 시간',
    last_login_at TIMESTAMP NULL COMMENT '마지막 로그인 시간',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_google_id (google_id),
    INDEX idx_email (email),
    INDEX idx_verification_status (verification_status),
    INDEX idx_family_member_id (family_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='구글 OAuth 사용자 인증 정보';

-- 2. 로그인 로그 테이블
CREATE TABLE IF NOT EXISTS login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'user_auth 테이블의 사용자 ID',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IP 주소',
    user_agent TEXT COMMENT '브라우저 정보',
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '로그인 시간',
    logout_time TIMESTAMP NULL COMMENT '로그아웃 시간',
    session_duration INT UNSIGNED NULL COMMENT '세션 지속 시간(초)',
    
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time),
    INDEX idx_ip_address (ip_address),
    
    FOREIGN KEY (user_id) REFERENCES user_auth(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 로그인 기록';

-- 3. 전화번호 인증 테이블
CREATE TABLE IF NOT EXISTS phone_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'user_auth 테이블의 사용자 ID',
    phone_number VARCHAR(20) NOT NULL COMMENT '인증할 전화번호',
    verification_code VARCHAR(6) NOT NULL COMMENT '인증 코드',
    expires_at TIMESTAMP NOT NULL COMMENT '인증 코드 만료 시간',
    verified_at TIMESTAMP NULL COMMENT '인증 완료 시간',
    attempts INT UNSIGNED DEFAULT 0 COMMENT '인증 시도 횟수',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_verification_code (verification_code),
    INDEX idx_expires_at (expires_at),
    
    FOREIGN KEY (user_id) REFERENCES user_auth(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='전화번호 인증 기록';

-- 4. 기존 family_members 테이블에 access_level 컬럼 추가 (없는 경우)
ALTER TABLE family_members 
ADD COLUMN IF NOT EXISTS access_level TINYINT UNSIGNED DEFAULT 2 COMMENT '접근 권한 레벨 (1=관리자, 2=일반회원)';

-- 5. 관리자 계정 설정 (조영국님을 Level 1 관리자로)
UPDATE family_members 
SET access_level = 1 
WHERE name = '조영국' OR name_hanja = '趙永國' OR person_code = 441301;

-- 6. 샘플 관리자 사용자 추가 (테스트용)
INSERT IGNORE INTO user_auth (google_id, email, name, verification_status, family_member_id, verified_at) 
VALUES 
('sample_admin_google_id', 'admin@changnyeongjo.com', '조영국', 'verified', 
 (SELECT id FROM family_members WHERE name = '조영국' LIMIT 1), 
 NOW());

-- 7. 인덱스 최적화
CREATE INDEX IF NOT EXISTS idx_family_access_level ON family_members(access_level);
CREATE INDEX IF NOT EXISTS idx_user_auth_status_member ON user_auth(verification_status, family_member_id);
CREATE INDEX IF NOT EXISTS idx_login_logs_user_time ON login_logs(user_id, login_time);