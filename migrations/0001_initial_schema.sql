-- ===================================================
-- 창녕조씨 가족 데이터베이스 - 초기 스키마
-- 생성일: 2025-09-10
-- 대상: Cloudflare D1 (SQLite)
-- ===================================================

-- 외래키 제약조건 활성화
PRAGMA foreign_keys = ON;

-- ===================================================
-- 1. 가족 구성원 테이블 (핵심 테이블)
-- ===================================================
CREATE TABLE family_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_code TEXT UNIQUE NOT NULL,
    parent_code TEXT,
    
    -- 기본 정보
    name TEXT NOT NULL,
    name_hanja TEXT,
    gender INTEGER NOT NULL DEFAULT 1 CHECK(gender IN (0, 1)), -- 0:여성, 1:남성
    
    -- 가족 관계
    generation INTEGER NOT NULL DEFAULT 1,
    sibling_order INTEGER NOT NULL DEFAULT 1,
    child_count INTEGER NOT NULL DEFAULT 0,
    
    -- 날짜 정보 (ISO 8601 형식)
    birth_date TEXT, -- YYYY-MM-DD 또는 YYYY-MM
    death_date TEXT, -- YYYY-MM-DD 또는 YYYY-MM
    is_deceased BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- 연락처 정보
    phone_number TEXT,
    email TEXT,
    
    -- 주소 정보 (JSON 형식으로 구조화)
    home_address JSON,
    work_address JSON,
    
    -- 상세 정보
    biography TEXT, -- 기존 CONTENTS 필드
    biography_hanja TEXT, -- 기존 CONTENTS_HIER 필드
    
    -- 시스템 정보
    is_adopted BOOLEAN NOT NULL DEFAULT FALSE,
    access_level INTEGER NOT NULL DEFAULT 3, -- 1:관리자, 2:편집자, 3:일반
    
    -- 타임스탬프
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- 외래키 제약조건
    FOREIGN KEY (parent_code) REFERENCES family_members(person_code)
);

-- ===================================================
-- 2. 배우자 정보 테이블
-- ===================================================
CREATE TABLE spouses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_code TEXT NOT NULL,
    spouse_name TEXT NOT NULL,
    spouse_name_hanja TEXT,
    spouse_family_origin TEXT, -- 본관
    spouse_father_name TEXT,
    marriage_order INTEGER NOT NULL DEFAULT 1, -- 첫째 부인, 둘째 부인 등
    
    -- 배우자 날짜 정보
    spouse_birth_date TEXT,
    spouse_death_date TEXT,
    marriage_date TEXT,
    
    -- 추가 정보
    children_list TEXT, -- 자녀 목록
    notes TEXT,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (person_code) REFERENCES family_members(person_code)
);

-- ===================================================
-- 3. 활동 로그 테이블
-- ===================================================
CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- 사용자 정보
    user_identifier TEXT NOT NULL, -- 전화번호 등
    app_package TEXT NOT NULL DEFAULT 'com.changnyeongjo.family',
    
    -- 액션 정보
    action_type TEXT NOT NULL, -- INSERT, UPDATE, DELETE 등
    table_name TEXT NOT NULL,
    target_person_code TEXT,
    
    -- 변경 내용 (JSON 형식)
    changes JSON, -- before/after 데이터를 구조화
    
    -- 타임스탬프
    action_date DATE NOT NULL,
    action_time INTEGER NOT NULL, -- 초 단위 시간
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- 복원 정보
    can_restore BOOLEAN NOT NULL DEFAULT TRUE,
    is_restored BOOLEAN NOT NULL DEFAULT FALSE,
    
    FOREIGN KEY (target_person_code) REFERENCES family_members(person_code)
);

-- ===================================================
-- 4. 사용자 요청 테이블
-- ===================================================
CREATE TABLE user_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requester_phone TEXT NOT NULL,
    requester_name TEXT,
    request_type TEXT NOT NULL,
    request_content TEXT,
    
    -- 상태 관리
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
    
    -- 처리 정보
    processed_by TEXT,
    processed_at DATETIME,
    admin_notes TEXT,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ===================================================
-- 5. 블랙리스트 테이블
-- ===================================================
CREATE TABLE blocked_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phone_number TEXT UNIQUE NOT NULL,
    blocked_reason TEXT,
    blocked_by TEXT NOT NULL,
    blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- 해제 정보
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    unblocked_by TEXT,
    unblocked_at DATETIME
);

-- ===================================================
-- 6. 공지사항 테이블
-- ===================================================
CREATE TABLE announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    author TEXT NOT NULL,
    is_important BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ===================================================
-- 7. 시스템 정보 테이블
-- ===================================================
CREATE TABLE system_info (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version TEXT NOT NULL,
    release_date DATE NOT NULL,
    description TEXT,
    is_current BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ===================================================
-- 인덱스 생성 (성능 최적화)
-- ===================================================

-- 기본 검색 인덱스
CREATE INDEX idx_family_members_person_code ON family_members(person_code);
CREATE INDEX idx_family_members_parent_code ON family_members(parent_code);
CREATE INDEX idx_family_members_generation ON family_members(generation);
CREATE INDEX idx_family_members_name ON family_members(name);
CREATE INDEX idx_family_members_phone ON family_members(phone_number);

-- 배우자 검색 인덱스
CREATE INDEX idx_spouses_person_code ON spouses(person_code);
CREATE INDEX idx_spouses_name ON spouses(spouse_name);

-- 로그 검색 인덱스
CREATE INDEX idx_activity_logs_person_code ON activity_logs(target_person_code);
CREATE INDEX idx_activity_logs_date ON activity_logs(action_date);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_identifier);

-- 요청 관리 인덱스
CREATE INDEX idx_user_requests_phone ON user_requests(requester_phone);
CREATE INDEX idx_user_requests_status ON user_requests(status);
CREATE INDEX idx_user_requests_created ON user_requests(created_at);

-- 블랙리스트 인덱스
CREATE INDEX idx_blocked_users_phone ON blocked_users(phone_number);
CREATE INDEX idx_blocked_users_active ON blocked_users(is_active);

-- 공지사항 인덱스
CREATE INDEX idx_announcements_active ON announcements(is_active);
CREATE INDEX idx_announcements_important ON announcements(is_important);
CREATE INDEX idx_announcements_created ON announcements(created_at);

-- ===================================================
-- 트리거 생성 (자동 업데이트)
-- ===================================================

-- updated_at 자동 업데이트 트리거
CREATE TRIGGER update_family_members_timestamp
    AFTER UPDATE ON family_members
    BEGIN
        UPDATE family_members 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE id = NEW.id;
    END;

CREATE TRIGGER update_spouses_timestamp
    AFTER UPDATE ON spouses
    BEGIN
        UPDATE spouses 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE id = NEW.id;
    END;

CREATE TRIGGER update_user_requests_timestamp
    AFTER UPDATE ON user_requests
    BEGIN
        UPDATE user_requests 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE id = NEW.id;
    END;

CREATE TRIGGER update_announcements_timestamp
    AFTER UPDATE ON announcements
    BEGIN
        UPDATE announcements 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE id = NEW.id;
    END;

-- ===================================================
-- 뷰 생성 (편의 기능)
-- ===================================================

-- 전체 가족 정보 뷰 (배우자 정보 포함)
CREATE VIEW family_tree_view AS
SELECT 
    fm.person_code,
    fm.name,
    fm.name_hanja,
    fm.gender,
    fm.generation,
    fm.sibling_order,
    fm.parent_code,
    fm.birth_date,
    fm.death_date,
    fm.is_deceased,
    fm.phone_number,
    fm.home_address,
    fm.biography,
    GROUP_CONCAT(s.spouse_name, '; ') as spouses,
    fm.created_at,
    fm.updated_at
FROM family_members fm
LEFT JOIN spouses s ON fm.person_code = s.person_code
GROUP BY fm.person_code;

-- 생존 가족 구성원 뷰
CREATE VIEW living_members_view AS
SELECT 
    person_code,
    name,
    name_hanja,
    gender,
    generation,
    phone_number,
    home_address,
    created_at
FROM family_members 
WHERE is_deceased = FALSE
ORDER BY generation, sibling_order;

-- 최근 활동 로그 뷰
CREATE VIEW recent_activities_view AS
SELECT 
    al.id,
    al.user_identifier,
    al.action_type,
    al.table_name,
    al.target_person_code,
    fm.name as target_name,
    al.action_date,
    al.created_at
FROM activity_logs al
LEFT JOIN family_members fm ON al.target_person_code = fm.person_code
ORDER BY al.created_at DESC
LIMIT 100;