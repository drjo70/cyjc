-- 창녕조씨 족보 테이블 생성
CREATE TABLE IF NOT EXISTS family_members (
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

-- 인덱스 생성
CREATE INDEX IF NOT EXISTS idx_family_members_person_code ON family_members(person_code);
CREATE INDEX IF NOT EXISTS idx_family_members_parent_code ON family_members(parent_code);
CREATE INDEX IF NOT EXISTS idx_family_members_generation ON family_members(generation);
CREATE INDEX IF NOT EXISTS idx_family_members_name ON family_members(name);