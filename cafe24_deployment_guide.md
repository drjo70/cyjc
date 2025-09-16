# 창녕조씨 족보 시스템 - 카페24 배포 가이드

## 개요
이 문서는 창녕조씨 족보 시스템을 카페24 웹호스팅 서비스에 배포하는 방법을 설명합니다.

## 시스템 구성
- **프론트엔드**: HTML/CSS/JavaScript (Vanilla JS + TailwindCSS)
- **백엔드**: Hono.js (Node.js 웹 프레임워크)
- **데이터베이스**: MySQL (카페24 DB 서버)
- **호스팅**: 카페24 웹호스팅

## 카페24 배포 준비사항

### 1. 카페24 웹호스팅 요구사항
- **호스팅 타입**: 리눅스 웹호스팅 (Node.js 지원)
- **Node.js 버전**: 18.0 이상
- **MySQL**: 5.7 이상
- **디스크 용량**: 최소 500MB
- **트래픽**: 월 10GB 권장

### 2. 필요한 카페24 서비스
```
□ 웹호스팅 (Node.js 지원)
□ MySQL 데이터베이스
□ SSL 인증서 (무료 Let's Encrypt)
□ 도메인 (예: cyjc-family.co.kr)
```

## 데이터베이스 설정

### 1. 카페24 MySQL 데이터베이스 생성
1. 카페24 관리콘솔 로그인
2. **hosting 관리 → 데이터베이스 → MySQL 관리**
3. 새 데이터베이스 생성:
   - DB명: `cyjc_family`
   - 문자셋: `utf8mb4_unicode_ci`

### 2. 스키마 및 데이터 임포트
```bash
# 1. 스키마 생성
mysql -h [DB서버] -u [사용자명] -p [DB명] < cafe24_schema.sql

# 2. 기본 데이터 삽입
mysql -h [DB서버] -u [사용자명] -p [DB명] < cafe24_data_insert.sql
```

### 3. DB 연결 설정
`.env` 파일 생성:
```env
# 카페24 MySQL 설정
DB_HOST=your-db-host.cafe24.com
DB_USER=your-db-user
DB_PASSWORD=your-db-password
DB_NAME=cyjc_family
DB_PORT=3306

# 애플리케이션 설정
NODE_ENV=production
PORT=3000
SESSION_SECRET=your-session-secret-key
```

## 파일 업로드 및 배포

### 1. 프로젝트 빌드
```bash
# 로컬에서 빌드 실행
npm install
npm run build
```

### 2. 카페24 FTP 업로드
```
업로드 대상 폴더: /public_html/
업로드 파일:
├── dist/                 # 빌드된 파일들
│   ├── _worker.js       # Hono 백엔드
│   ├── _routes.json     # 라우팅 설정
│   └── static/          # 정적 파일들
├── package.json         # 패키지 설정
├── .env                 # 환경 변수
└── ecosystem.config.js  # PM2 설정 (선택사항)
```

### 3. 카페24 Node.js 설정
1. **hosting 관리 → Node.js → 애플리케이션 관리**
2. 새 애플리케이션 등록:
   - **앱 이름**: cyjc-family-tree
   - **Node.js 버전**: 18.x
   - **시작 파일**: `dist/_worker.js`
   - **포트**: 3000 (또는 카페24 할당 포트)

### 4. 환경 변수 설정
카페24 Node.js 관리 페이지에서 환경 변수 등록:
```
DB_HOST=your-db-host.cafe24.com
DB_USER=your-db-user
DB_PASSWORD=your-db-password
DB_NAME=cyjc_family
NODE_ENV=production
```

## SSL 및 도메인 설정

### 1. SSL 인증서 설치
1. **hosting 관리 → SSL 인증서**
2. **Let's Encrypt 무료 SSL** 선택
3. 도메인 인증 완료

### 2. 도메인 연결
1. **도메인 관리 → DNS 관리**
2. A레코드 또는 CNAME 설정:
   ```
   www    CNAME    your-site.cafe24.com
   @      A        카페24-IP-주소
   ```

## 모니터링 및 관리

### 1. 로그 확인
```bash
# 카페24 SSH 접속 후
tail -f logs/combined.log
tail -f logs/error.log
```

### 2. 애플리케이션 재시작
```bash
# 카페24 Node.js 관리 페이지에서 재시작 버튼 클릭
# 또는 SSH로
pm2 restart cyjc-family-tree
```

### 3. 백업 설정
```bash
# 정기 백업 스크립트
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -h [DB서버] -u [사용자] -p[비밀번호] cyjc_family > backup_${DATE}.sql
tar -czf app_backup_${DATE}.tar.gz /public_html/dist/
```

## 성능 최적화

### 1. 캐싱 설정
`.htaccess` 파일 생성:
```apache
# 정적 파일 캐싱
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
</IfModule>

# Gzip 압축
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

### 2. 데이터베이스 최적화
```sql
-- 자주 사용되는 쿼리 인덱스 추가
ALTER TABLE family_members ADD INDEX idx_name_search (name, name_hanja);
ALTER TABLE family_members ADD INDEX idx_generation_active (generation, is_deceased);

-- 테이블 최적화
OPTIMIZE TABLE family_members;
OPTIMIZE TABLE activity_logs;
```

## 보안 설정

### 1. 기본 보안 조치
- 관리자 계정 2단계 인증 설정
- 정기적인 패스워드 변경
- 불필요한 파일 권한 제한

### 2. 애플리케이션 보안
```javascript
// 입력 검증 및 XSS 방지
const validateInput = (input) => {
    return input.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
};

// SQL 인젝션 방지 (Prepared Statement 사용)
const query = "SELECT * FROM family_members WHERE name = ?";
```

## 문제 해결

### 자주 발생하는 문제들

1. **Node.js 앱 시작 실패**
   - 포트 충돌 확인
   - 환경 변수 설정 확인
   - 의존성 설치 확인

2. **데이터베이스 연결 실패**
   - DB 서버 정보 확인
   - 방화벽 설정 확인
   - 사용자 권한 확인

3. **정적 파일 로드 실패**
   - 파일 경로 확인
   - 권한 설정 확인
   - .htaccess 설정 확인

## 연락처
- **개발자**: 조영국
- **이메일**: jo@jou.kr
- **전화**: 010-9272-9081

## 버전 정보
- **시스템 버전**: 1.0.0
- **최종 수정일**: 2025-09-16
- **라이센스**: MIT