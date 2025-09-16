-- 카페24 MySQL용 족보 데이터베이스 스키마
-- 원본: Cloudflare D1 SQLite에서 MySQL로 변환

-- 가족 구성원 테이블 (핵심)
CREATE TABLE family_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    person_code VARCHAR(50) NOT NULL UNIQUE COMMENT '개인 고유 코드',
    parent_code VARCHAR(50) COMMENT '부모 코드 (외래키)',
    name VARCHAR(100) NOT NULL COMMENT '성명',
    name_hanja VARCHAR(100) COMMENT '한자명',
    gender TINYINT NOT NULL DEFAULT 1 COMMENT '성별 (1:남, 2:여)',
    generation INT NOT NULL DEFAULT 1 COMMENT '세대수',
    sibling_order INT NOT NULL DEFAULT 1 COMMENT '형제 순서',
    child_count INT NOT NULL DEFAULT 0 COMMENT '자녀 수',
    birth_date VARCHAR(20) COMMENT '생년월일',
    death_date VARCHAR(20) COMMENT '사망일',
    is_deceased BOOLEAN NOT NULL DEFAULT FALSE COMMENT '사망 여부',
    phone_number VARCHAR(20) COMMENT '전화번호',
    email VARCHAR(100) COMMENT '이메일',
    home_address JSON COMMENT '거주지 주소 (JSON)',
    work_address JSON COMMENT '직장 주소 (JSON)',
    biography TEXT COMMENT '전기/이력',
    biography_hanja TEXT COMMENT '전기 한자',
    is_adopted BOOLEAN NOT NULL DEFAULT FALSE COMMENT '양자 여부',
    access_level INT NOT NULL DEFAULT 3 COMMENT '접근 권한 (1:관리자, 2:편집자, 3:일반)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    
    INDEX idx_parent_code (parent_code),
    INDEX idx_generation (generation),
    INDEX idx_person_code (person_code),
    INDEX idx_name (name),
    FOREIGN KEY (parent_code) REFERENCES family_members(person_code) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='가족 구성원 정보';

-- 배우자 정보 테이블
CREATE TABLE spouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    person_code VARCHAR(50) NOT NULL COMMENT '본인 코드',
    spouse_name VARCHAR(100) COMMENT '배우자 성명',
    spouse_name_hanja VARCHAR(100) COMMENT '배우자 한자명',
    spouse_family_origin VARCHAR(100) COMMENT '배우자 본관',
    spouse_father_name VARCHAR(100) COMMENT '배우자 아버지명',
    marriage_order INT DEFAULT 1 COMMENT '결혼 순서 (재혼 등)',
    spouse_birth_date VARCHAR(20) COMMENT '배우자 생년월일',
    spouse_death_date VARCHAR(20) COMMENT '배우자 사망일',
    marriage_date VARCHAR(20) COMMENT '결혼일',
    children_list TEXT COMMENT '자녀 목록',
    notes TEXT COMMENT '비고',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    
    INDEX idx_person_code (person_code),
    FOREIGN KEY (person_code) REFERENCES family_members(person_code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='배우자 정보';

-- 활동 로그 테이블 (변경 이력 추적)
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier VARCHAR(100) NOT NULL COMMENT '사용자 식별자',
    app_package VARCHAR(100) NOT NULL DEFAULT 'com.changnyeongjo.family' COMMENT '앱 패키지명',
    action_type VARCHAR(50) NOT NULL COMMENT '작업 유형 (INSERT/UPDATE/DELETE)',
    table_name VARCHAR(50) NOT NULL COMMENT '대상 테이블명',
    target_person_code VARCHAR(50) COMMENT '대상 인물 코드',
    changes JSON COMMENT '변경 내용 (JSON)',
    action_date DATE NOT NULL COMMENT '작업 날짜',
    action_time INT NOT NULL COMMENT '작업 시간 (타임스탬프)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '로그 생성일시',
    can_restore BOOLEAN NOT NULL DEFAULT TRUE COMMENT '복원 가능 여부',
    is_restored BOOLEAN NOT NULL DEFAULT FALSE COMMENT '복원 여부',
    
    INDEX idx_user_identifier (user_identifier),
    INDEX idx_target_person_code (target_person_code),
    INDEX idx_action_date (action_date),
    FOREIGN KEY (target_person_code) REFERENCES family_members(person_code) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='활동 로그';

-- 사용자 요청 테이블
CREATE TABLE user_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_phone VARCHAR(20) NOT NULL COMMENT '요청자 전화번호',
    requester_name VARCHAR(100) COMMENT '요청자 성명',
    request_type VARCHAR(50) NOT NULL COMMENT '요청 유형',
    request_content TEXT COMMENT '요청 내용',
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' COMMENT '처리 상태',
    processed_by VARCHAR(100) COMMENT '처리자',
    processed_at DATETIME COMMENT '처리일시',
    admin_notes TEXT COMMENT '관리자 메모',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '요청일시',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    
    INDEX idx_requester_phone (requester_phone),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사용자 요청';

-- 차단된 사용자 테이블
CREATE TABLE blocked_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) UNIQUE NOT NULL COMMENT '차단된 전화번호',
    blocked_reason TEXT COMMENT '차단 사유',
    blocked_by VARCHAR(100) NOT NULL COMMENT '차단 처리자',
    blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '차단일시',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT '차단 활성 상태',
    unblocked_by VARCHAR(100) COMMENT '차단 해제자',
    unblocked_at DATETIME COMMENT '차단 해제일시',
    
    INDEX idx_phone_number (phone_number),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='차단된 사용자';

-- 공지사항 테이블
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL COMMENT '공지 제목',
    content TEXT NOT NULL COMMENT '공지 내용',
    author VARCHAR(100) NOT NULL COMMENT '작성자',
    is_important BOOLEAN NOT NULL DEFAULT FALSE COMMENT '중요 공지 여부',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT '활성 상태',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '작성일시',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    
    INDEX idx_is_active (is_active),
    INDEX idx_is_important (is_important),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='공지사항';

-- 시스템 정보 테이블
CREATE TABLE system_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20) NOT NULL COMMENT '버전',
    release_date DATE NOT NULL COMMENT '배포일',
    description TEXT COMMENT '버전 설명',
    is_current BOOLEAN NOT NULL DEFAULT FALSE COMMENT '현재 버전 여부',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    
    INDEX idx_version (version),
    INDEX idx_is_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='시스템 정보';

-- 마이그레이션 테이블 (스키마 버전 관리)
CREATE TABLE schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL COMMENT '마이그레이션 파일명',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT '적용일시'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='스키마 마이그레이션';