-- ===================================================
-- 창녕조씨 가족 데이터베이스 - 세대 정규화
-- 생성일: 2025-09-11
-- 목적: 37-44세대를 1-8세대로 정규화
-- ===================================================

-- 1세대부터 시작하도록 세대 번호 정규화
-- 기존: 37세대(술진) → 44세대(영국)
-- 변경: 1세대(술진) → 8세대(영국)

UPDATE family_members 
SET generation = generation - 36
WHERE generation BETWEEN 37 AND 44;

-- 세대별 확인 쿼리 (참고용)
-- SELECT generation, COUNT(*) as count, 
--        GROUP_CONCAT(name) as members 
-- FROM family_members 
-- GROUP BY generation 
-- ORDER BY generation ASC;

-- 예상 결과:
-- 1세대: 술진(述振) - 시조 (1733년)
-- 2세대: 석렴(錫廉)
-- 3세대: 혁승(赫承)  
-- 4세대: 병필(秉弼)
-- 5세대: 갑환(甲煥)
-- 6세대: 규서(圭瑞)
-- 7세대: 태현(泰鉉)
-- 8세대: 영국(永國), 은영(恩永), 영순(英順)