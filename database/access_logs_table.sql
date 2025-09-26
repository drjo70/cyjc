-- 접속 로그 테이블 생성 SQL
-- 웹사이트 방문자 추적 및 통계 분석용

CREATE TABLE IF NOT EXISTS access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NULL COMMENT '세션 ID',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IP 주소 (IPv4/IPv6)',
    user_agent TEXT COMMENT '브라우저 정보',
    page_url VARCHAR(500) NOT NULL COMMENT '접속 페이지 URL',
    referrer VARCHAR(500) NULL COMMENT '참조 페이지',
    user_id INT UNSIGNED NULL COMMENT '로그인 사용자 ID',
    device_type ENUM('desktop', 'mobile', 'tablet', 'bot', 'unknown') DEFAULT 'unknown' COMMENT '디바이스 유형',
    browser VARCHAR(50) NULL COMMENT '브라우저명',
    os VARCHAR(50) NULL COMMENT '운영체제',
    country VARCHAR(2) NULL COMMENT '국가 코드',
    city VARCHAR(100) NULL COMMENT '도시명',
    visit_duration INT UNSIGNED DEFAULT 0 COMMENT '페이지 체류 시간(초)',
    access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '접속 시간',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_access_time (access_time),
    INDEX idx_ip_address (ip_address),
    INDEX idx_page_url (page_url(100)),
    INDEX idx_user_id (user_id),
    INDEX idx_device_type (device_type),
    INDEX idx_session_id (session_id),
    INDEX idx_date_page (DATE(access_time), page_url(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='웹사이트 접속 로그';

-- 일별 접속 통계 뷰 생성
CREATE OR REPLACE VIEW daily_access_stats AS
SELECT 
    DATE(access_time) as visit_date,
    COUNT(*) as total_visits,
    COUNT(DISTINCT ip_address) as unique_visitors,
    COUNT(DISTINCT session_id) as unique_sessions,
    COUNT(CASE WHEN device_type = 'mobile' THEN 1 END) as mobile_visits,
    COUNT(CASE WHEN device_type = 'desktop' THEN 1 END) as desktop_visits,
    COUNT(CASE WHEN user_id IS NOT NULL THEN 1 END) as logged_in_visits,
    AVG(visit_duration) as avg_duration
FROM access_logs 
GROUP BY DATE(access_time)
ORDER BY visit_date DESC;

-- 페이지별 접속 통계 뷰 생성
CREATE OR REPLACE VIEW page_access_stats AS
SELECT 
    page_url,
    COUNT(*) as total_visits,
    COUNT(DISTINCT ip_address) as unique_visitors,
    COUNT(DISTINCT session_id) as unique_sessions,
    AVG(visit_duration) as avg_duration,
    MAX(access_time) as last_visit,
    MIN(access_time) as first_visit
FROM access_logs 
GROUP BY page_url
ORDER BY total_visits DESC;

-- 디바이스별 접속 통계 뷰 생성
CREATE OR REPLACE VIEW device_access_stats AS
SELECT 
    device_type,
    COUNT(*) as total_visits,
    COUNT(DISTINCT ip_address) as unique_visitors,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM access_logs), 2) as percentage
FROM access_logs 
GROUP BY device_type
ORDER BY total_visits DESC;