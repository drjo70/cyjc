-- 가족 테이블 완전 재생성 마이그레이션

-- 기존 테이블들을 백업
CREATE TABLE IF NOT EXISTS family_members_backup AS SELECT * FROM family_members;
CREATE TABLE IF NOT EXISTS spouses_backup AS SELECT * FROM spouses;

-- 기존 테이블 삭제 (CASCADE로 모든 외래 키 참조 제거)
DROP TABLE IF EXISTS family_members;
DROP TABLE IF EXISTS spouses;

-- 가족 구성원 테이블 재생성 (외래 키 제약 없이)
CREATE TABLE family_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_code TEXT NOT NULL UNIQUE,
    parent_code TEXT,
    name TEXT NOT NULL,
    name_hanja TEXT,
    gender INTEGER NOT NULL DEFAULT 1,
    generation INTEGER NOT NULL DEFAULT 1,
    sibling_order INTEGER NOT NULL DEFAULT 1,
    child_count INTEGER NOT NULL DEFAULT 0,
    birth_date TEXT,
    death_date TEXT,
    is_deceased BOOLEAN NOT NULL DEFAULT FALSE,
    phone_number TEXT,
    email TEXT,
    home_address JSON,
    work_address JSON,
    biography TEXT,
    biography_hanja TEXT,
    is_adopted BOOLEAN NOT NULL DEFAULT FALSE,
    access_level INTEGER NOT NULL DEFAULT 3,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 배우자 테이블 재생성 (외래 키 제약 없이)
CREATE TABLE spouses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_code TEXT NOT NULL,
    spouse_name TEXT NOT NULL,
    spouse_name_hanja TEXT,
    spouse_family_origin TEXT,
    spouse_father_name TEXT,
    marriage_order INTEGER NOT NULL DEFAULT 1,
    spouse_birth_date TEXT,
    spouse_death_date TEXT,
    marriage_date TEXT,
    is_divorced BOOLEAN NOT NULL DEFAULT FALSE,
    spouse_phone TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 인덱스 재생성
CREATE INDEX idx_family_members_person_code ON family_members(person_code);
CREATE INDEX idx_family_members_parent_code ON family_members(parent_code);
CREATE INDEX idx_family_members_generation ON family_members(generation);
CREATE INDEX idx_family_members_phone ON family_members(phone_number);
CREATE INDEX idx_spouses_person_code ON spouses(person_code);