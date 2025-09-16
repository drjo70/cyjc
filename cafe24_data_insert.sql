-- 카페24 MySQL용 족보 데이터 삽입 스크립트
-- 원본 SQLite 데이터를 MySQL 형식으로 변환

-- 스키마 마이그레이션 기록
INSERT INTO schema_migrations (name) VALUES 
('0001_initial_schema.sql'),
('0001_create_family_members.sql'),
('0002_normalize_generation.sql'),
('0003_recreate_family_table.sql');

-- 시스템 정보 삽입
INSERT INTO system_info (version, release_date, description, is_current) VALUES 
('1.0.0', '2025-09-16', '창녕조씨 족보 시스템 초기 버전', TRUE);

-- 중요 공지사항 삽입
INSERT INTO announcements (title, content, author, is_important, is_active) VALUES 
('창녕조씨 족보 시스템 개통', '카페24 서버에 족보 시스템이 개통되었습니다. 정확한 족보 정보 관리를 위해 지속적으로 업데이트됩니다.', '관리자', TRUE, TRUE),
('데이터 정확성 안내', '족보 데이터의 정확성을 위해 지속적인 검증이 이루어집니다. 오류 발견 시 연락 부탁드립니다.', '관리자', FALSE, TRUE);

-- 가족 구성원 데이터 삽입 (샘플 데이터 - 주요 인물들)
INSERT INTO family_members (
    person_code, parent_code, name, name_hanja, gender, generation, sibling_order, 
    child_count, birth_date, death_date, is_deceased, phone_number, email, 
    home_address, work_address, biography, biography_hanja, is_adopted, access_level
) VALUES 
-- 시조 
('1', '-1', '조계룡', '曺繼龍', 1, 1, 1, 1, '0000-00', '0000-00', TRUE, NULL, NULL, 
 '{"address": "0000-00", "detail": ""}', NULL, '신라진평왕여서봉창성부원군관지태사', 
 '新羅眞平王女壻封昌城府院君官至太師', FALSE, 3),

-- 2세
('2', '1', '응신', '應神', 1, 2, 1, 3, '0000-00', '0000-00', TRUE, NULL, NULL,
 '{"address": "0000-00", "detail": ""}', NULL, 
 '거경후손명교출재북오십리허초제유상갈가징별설단입석이동복거민지십여호면개기지십이삼대운자몽일아시조정승여배장자칙필뇌회전풍우총운신라선덕녀왕등',
 '居京後孫命敎出宰北五十里許草堤有上碣可徵別設壇立石以洞卜居民至十餘戶面皆旣至十二三代云自夢曰我是曺政丞汝輩葬者則必大雷電風雨塚云新羅善德女王登', FALSE, 3),

-- 현대 인물들 (41-45세대)
('410854', '400512', '갑환', '甲煥', 1, 41, 1, 3, '1878-10-01', '1950-08-20', TRUE, NULL, NULL, 
 NULL, NULL, NULL, '錫濂三代孫', FALSE, 3),

('421453', '410854', '규서', '圭瑞', 1, 42, 1, 2, '1898-05-25', '1970-12-15', TRUE, NULL, NULL, 
 NULL, NULL, NULL, '錫濂四代孫', FALSE, 3),

('431997', '421453', '태현', '泰鉉', 1, 43, 2, 3, '1941-05-06', NULL, FALSE, NULL, NULL, 
 NULL, NULL, NULL, '錫濂五代孫', FALSE, 3),

('431998', '421453', '광현', '光鉉', 1, 43, 3, 3, '1943-12-04', NULL, TRUE, NULL, NULL, 
 NULL, NULL, NULL, '錫濂五代孫', FALSE, 3),

-- 44세대 (현재 활동 세대)
('441258', '431997', '조영국', '永國', 1, 44, 1, 0, '1970-03-04', NULL, FALSE, '010-9272-9081', 'jo@jou.kr',
 '{"address": "강원도 강릉시 화부산로99번길 12 (교동,강릉 교동 롯데캐슬 1단지)", "detail": "102동 403호"}',
 '{"address": "강원도 강릉시 사임당로 641-22 (대전동)", "detail": "212호"}',
 '컴퓨터 IT 박사, 컨설팅 전문가, 프로그램 개발자, (주)조유 대표이사',
 '錫濂五代孫泰鉉子 庚戌西紀一九七〇年三月四日生', FALSE, 1),

('441259', '431997', '영순', '永順', 2, 44, 2, 0, '1974-01-12', NULL, FALSE, NULL, NULL, 
 NULL, NULL, NULL, '錫濂五代孫泰鉉女', FALSE, 3),

('441260', '431997', '은영', '恩永', 2, 44, 3, 2, '1980-01-15', NULL, FALSE, NULL, NULL, 
 NULL, NULL, NULL, '錫濂五代孫泰鉉女', FALSE, 3),

('441261', '431998', '영석', '永鍚', 1, 44, 1, 3, '1974-03-18', NULL, TRUE, NULL, NULL, 
 NULL, NULL, NULL, '錫濂五代孫光鉉子 甲寅西紀一九七四년三月十八日生', FALSE, 3),

('441262', '431998', '영미', '永美', 2, 44, 2, 0, '1977-12-07', NULL, TRUE, NULL, NULL, 
 NULL, NULL, NULL, '丁巳西紀一九七七年十二月七日生', FALSE, 3),

('441263', '431998', '영애', '永愛', 2, 44, 3, 0, '1979-09-30', NULL, TRUE, NULL, NULL, 
 NULL, NULL, NULL, '己未西紀一九七九년九월三十日生', FALSE, 3),

-- 45세대 (차세대)
('441264', '441260', '박수현', '朴秀賢', 2, 45, 1, 0, '1995-01-01', NULL, FALSE, '010-3333-3333', NULL, 
 NULL, NULL, NULL, '錫濂六代孫', FALSE, 3),

('441265', '441260', '박도현', '朴道賢', 1, 45, 2, 0, '1997-01-01', NULL, FALSE, '010-4444-4444', NULL, 
 NULL, NULL, NULL, '錫濂六代孫', FALSE, 3);

-- 시스템 로그 초기 데이터
INSERT INTO activity_logs (
    user_identifier, action_type, table_name, target_person_code, changes, 
    action_date, action_time, can_restore, is_restored
) VALUES 
('system', 'INITIAL_IMPORT', 'family_members', NULL, 
 '{"action": "초기 데이터 임포트", "source": "SQLite 백업", "count": 13}', 
 '2025-09-16', UNIX_TIMESTAMP(), FALSE, FALSE);

-- 기본 설정 데이터
INSERT INTO user_requests (
    requester_phone, requester_name, request_type, request_content, status, processed_by, admin_notes
) VALUES 
('010-0000-0000', '시스템관리자', 'SYSTEM_INIT', '족보 시스템 초기 설정', 'approved', 'system', '초기 시스템 설정 완료');

-- 주요 족보 계보 정보 추가 삽입을 위한 준비
-- (실제 운영 시 전체 데이터를 배치로 삽입할 수 있도록 스크립트 확장 가능)

-- 인덱스 최적화를 위한 ANALYZE
-- ANALYZE TABLE family_members;
-- ANALYZE TABLE activity_logs;