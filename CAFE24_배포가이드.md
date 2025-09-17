# 🏢 창녕조씨 족보 시스템 - Cafe24 배포 가이드

## 👨‍💼 개발자 정보
- **개발자**: 닥터조 (컴퓨터 IT 박사)
- **회사**: (주)조유 대표이사
- **프로젝트**: 창녕조씨 디지털 족보 시스템 v3.0
- **배포일**: 2024-09-17

---

## 📋 시스템 요구사항

### 🔧 **Cafe24 호스팅 환경**
- **PHP**: 8.0 이상 권장 (최소 7.4)
- **MySQL**: 8.0 이상 (또는 MariaDB 10.4+)  
- **Apache**: mod_rewrite 활성화 필요
- **SSL**: HTTPS 권장 (Let's Encrypt 무료 SSL)
- **용량**: 최소 1GB (이미지 업로드 고려시 더 필요)

### 🗄️ **데이터베이스**
- **인코딩**: UTF8MB4 (이모지 지원)
- **테이블 엔진**: InnoDB
- **백업**: 정기 백업 필수

---

## 🚀 배포 단계별 가이드

### **1단계: Cafe24 호스팅 준비**

1. **Cafe24 호스팅 가입**
   - 웹호스팅 또는 클라우드 호스팅 선택
   - PHP 8.0, MySQL 지원 확인
   - SSL 인증서 설정

2. **도메인 설정**
   ```
   예시: changnyeong-jo.cafe24.com
   또는 사용자 도메인: www.changnyeong-jo.co.kr
   ```

### **2단계: 파일 업로드 (FTP)**

1. **FTP 접속 정보** (Cafe24에서 제공)
   ```
   호스트: ftp.cafe24.com
   포트: 21
   사용자명: [Cafe24 제공]
   비밀번호: [Cafe24 제공]
   ```

2. **업로드할 파일들**
   ```
   📁 /public_html/ (웹루트)
   ├── 📄 index.php (메인 페이지)
   ├── 📄 .htaccess (Apache 설정)
   ├── 📁 config/
   │   └── 📄 database.php
   ├── 📁 controllers/
   ├── 📁 models/
   ├── 📁 views/
   ├── 📁 assets/ (CSS, JS, 이미지)
   ├── 📁 api/
   ├── 📄 ai_analyzer.php
   ├── 📄 tree_3d.php
   ├── 📄 voice_search.php
   ├── 📄 family_tree.php
   ├── 📄 admin.php
   └── 📄 advanced_search.php
   ```

### **3단계: 데이터베이스 설정**

1. **Cafe24 MySQL 데이터베이스 생성**
   - Cafe24 관리자 패널 접속
   - 데이터베이스 메뉴 → 새 DB 생성
   - DB명: `changnyeong_jo`
   - 문자셋: `utf8mb4_unicode_ci`

2. **데이터베이스 정보 확인**
   ```php
   호스트: [Cafe24 제공] (예: localhost 또는 mysql.cafe24.com)
   DB명: changnyeong_jo
   사용자: [Cafe24 제공]
   비밀번호: [Cafe24 제공]
   ```

3. **config/database.php 수정**
   ```php
   define('DB_HOST', 'localhost');  // Cafe24 제공 호스트
   define('DB_NAME', 'changnyeong_jo');
   define('DB_USER', 'cafe24_username');  // Cafe24 제공
   define('DB_PASS', 'cafe24_password');  // Cafe24 제공
   ```

### **4단계: 데이터베이스 스키마 생성**

1. **phpMyAdmin 접속**
   - Cafe24 관리자 패널 → 데이터베이스 → phpMyAdmin

2. **테이블 생성 SQL 실행**
   ```sql
   -- 기존 SQL 파일 업로드
   -- cyjc_cafe24_mysql_safe.sql 파일 실행
   
   CREATE DATABASE IF NOT EXISTS changnyeong_jo 
   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   
   USE changnyeong_jo;
   -- 나머지 테이블 생성...
   ```

### **5단계: 환경 설정 및 테스트**

1. **PHP 설정 확인**
   - 브라우저에서 `https://도메인/config/database.php?test_db=true` 접속
   - 연결 성공 메시지 확인

2. **URL 리라이트 테스트**
   ```
   https://도메인/tree/3d → tree_3d.php
   https://도메인/ai/analyzer → ai_analyzer.php
   https://도메인/voice/search → voice_search.php
   ```

3. **보안 설정 확인**
   - HTTPS 리다이렉트 동작 확인
   - .htaccess 보안 규칙 테스트

---

## 🔧 환경별 설정 파일

### **개발환경 (로컬)**
```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### **운영환경 (Cafe24)**
```php
// config/database.php
define('DB_HOST', 'localhost');  // 또는 Cafe24 제공 호스트
define('DB_USER', 'cafe24_user');
define('DB_PASS', 'cafe24_password');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
```

---

## 📊 성능 최적화

### **1. 캐싱 설정**
- **브라우저 캐시**: .htaccess에서 설정됨
- **PHP OPcache**: Cafe24에서 기본 제공
- **데이터베이스**: 쿼리 최적화 및 인덱스 설정

### **2. 이미지 최적화**
- **WebP 포맷** 사용 권장
- **이미지 압축** 도구 활용
- **CDN** 고려 (Cafe24 CDN 서비스)

### **3. 파일 압축**
- **Gzip 압축**: .htaccess에서 설정됨
- **CSS/JS 압축**: 빌드 도구 사용

---

## 🔒 보안 설정

### **1. 파일 권한 설정**
```bash
# FTP에서 설정 필요
디렉토리: 755
PHP 파일: 644
config 디렉토리: 750
.htaccess: 644
```

### **2. 데이터베이스 보안**
- **SQL 인젝션 방지**: PDO Prepared Statement 사용
- **비밀번호 해시**: password_hash() 함수 사용
- **세션 보안**: HTTPS Only, Secure Cookie

### **3. 파일 업로드 보안**
- **파일 타입 검증**
- **용량 제한**: 10MB
- **업로드 디렉토리** 실행 권한 제거

---

## 🌐 SEO 및 성능

### **1. URL 구조**
```
메인페이지: https://도메인/
인물페이지: https://도메인/person/123
3D 족보: https://도메인/tree/3d
AI 분석: https://도메인/ai/analyzer
음성검색: https://도메인/voice/search
```

### **2. 메타태그 설정**
- **Title**: 페이지별 고유 제목
- **Description**: 200자 내외 설명
- **Keywords**: 관련 키워드
- **Open Graph**: 소셜 공유용

---

## 📱 모바일 최적화

### **1. 반응형 디자인**
- **TailwindCSS**: 모바일 우선 설계
- **터치 인터페이스**: 버튼 크기 최적화
- **속도 최적화**: 모바일 네트워크 고려

### **2. PWA (Progressive Web App)**
- **Service Worker**: 오프라인 지원
- **Web App Manifest**: 앱 설치 기능
- **Push 알림**: 족보 업데이트 알림

---

## 🔍 모니터링 및 유지보수

### **1. 로그 관리**
```php
// 에러 로그
/logs/error.log
/logs/access.log
/logs/debug.log
```

### **2. 백업 전략**
- **자동 백업**: Cafe24 백업 서비스
- **수동 백업**: 주요 업데이트 전
- **데이터베이스**: 매일 자동 백업

### **3. 업데이트 관리**
- **보안 패치**: 정기적 PHP/MySQL 업데이트
- **기능 업데이트**: 버전 관리 시스템 사용
- **테스트**: 스테이징 환경에서 먼저 테스트

---

## 📞 지원 및 문의

### **개발자 연락처**
- **이름**: 닥터조 (컴퓨터 IT 박사)
- **회사**: (주)조유 대표이사
- **전문분야**: 컨설팅, AI, 시스템 개발

### **기술 지원**
- **Cafe24 기술지원**: 1588-2233
- **시스템 문의**: 개발자 직접 문의
- **긴급 상황**: 24시간 모니터링

---

## 📈 향후 개발 계획

### **단기 계획 (1-3개월)**
- ✅ AI 족보 분석 시스템
- ✅ 3D 족보 시각화
- ✅ 음성 검색 시스템
- 🔄 QR 코드 인물 카드
- 🔄 실시간 족보 채팅

### **중기 계획 (3-6개월)**
- 📱 모바일 앱 개발
- 🤖 ChatGPT 통합
- 📊 고급 통계 대시보드
- 🌐 다국어 지원

### **장기 계획 (6개월+)**
- 🏗️ 마이크로서비스 아키텍처
- ☁️ 클라우드 네이티브 전환
- 🔗 블록체인 기반 족보 인증
- 🎯 AI 추천 시스템

---

**© 2024 창녕조씨 족보 시스템 | 개발: 닥터조 ((주)조유) | Cafe24 호스팅**