# 창녕조씨 족보 시스템 배포 가이드

## 📋 배포 전 준비사항

### 1. 환경 변수 설정
프로젝트 루트에 `.env` 파일을 생성하고 다음 정보를 입력하세요:

```bash
# 데이터베이스 설정 (Cafe24)
DB_HOST=localhost
DB_NAME=cyjc25
DB_USERNAME=cyjc25
DB_PASSWORD=실제_데이터베이스_비밀번호

# Google OAuth 설정
GOOGLE_CLIENT_ID=실제_구글_클라이언트_ID
GOOGLE_CLIENT_SECRET=실제_구글_클라이언트_시크릿
GOOGLE_REDIRECT_URI=https://cyjc.jou.kr/auth_callback.php

# 카카오 OAuth 설정  
KAKAO_CLIENT_ID=실제_카카오_REST_API_키
KAKAO_REDIRECT_URI=https://cyjc.jou.kr/kakao_callback.php

# 기본 도메인 설정
BASE_URL=https://cyjc.jou.kr
```

### 2. 데이터베이스 테이블 생성
다음 SQL 파일들을 순서대로 실행하세요:

1. `setup_auth_tables.sql` - 인증 테이블 생성
2. `access_logs_table.sql` - 접근 로그 테이블 생성
3. `push_tokens_table.sql` - 푸시 알림 토큰 테이블 생성

### 3. 파일 권한 설정
Cafe24에서 다음 파일들의 권한을 설정하세요:
- PHP 파일: 644
- 디렉토리: 755
- logs/ 디렉토리: 777 (로그 작성을 위해)

## 🚀 주요 기능

### OAuth 로그인 시스템
- **Google OAuth 2.0**: 구글 계정으로 로그인
- **Kakao OAuth 2.0**: 카카오톡 계정으로 로그인
- **세션 관리**: 안전한 세션 기반 인증
- **자동 연결**: 이메일 기반 가족 구성원 자동 매칭

### 가족 관계 인증 시스템
- **전화번호 인증**: 등록된 전화번호로 본인 확인
- **부친명 인증**: 아버지 성명으로 가족 관계 확인
- **형제명 인증**: 형제자매 이름으로 가족 관계 확인
- **자동 권한 부여**: 인증 완료시 자동으로 접근 권한 부여

### 접근 권한 관리
- **관리자 권한 (access_level = 1)**: 전체 시스템 관리
- **일반 사용자 (access_level = 2)**: 기본 족보 조회
- **미인증 사용자**: 제한적 접근

### PWA (Progressive Web App)
- **모바일 최적화**: 반응형 웹 디자인
- **오프라인 지원**: 서비스 워커 기반
- **앱 설치**: 홈 화면에 앱처럼 설치 가능
- **푸시 알림**: Firebase 기반 알림 시스템

### 접근 통계 시스템
- **실시간 통계**: Chart.js 기반 시각화
- **페이지별 접근 로그**: 상세한 접근 기록
- **사용자별 통계**: 사용자 활동 분석

## 📁 주요 파일 설명

### 인증 관련
- `config.php` - 시스템 설정 및 OAuth 구성
- `auth_callback.php` - Google OAuth 콜백 처리
- `kakao_callback.php` - Kakao OAuth 콜백 처리
- `family_verification_system.php` - 가족 관계 인증 시스템

### 핵심 기능
- `index.php` - 메인 페이지 (로그인 상태 확인)
- `admin.php` - 관리자 페이지 (권한 기반 접근)
- `family_tree.php` - 족보 트리 조회
- `person_detail.php` - 개인 상세 정보
- `search.php` - 족보 검색 기능

### PWA 관련
- `public/sw.js` - 서비스 워커
- `public/manifest.json` - 앱 매니페스트
- `public/offline.html` - 오프라인 페이지

### 통계 및 로그
- `admin_stats.php` - 관리자용 통계 대시보드
- `access_logger.php` - 접근 로그 기록
- `simple_stats.php` - 간단한 통계 조회

## 🔧 문제 해결

### OAuth 로그인 문제
1. `.env` 파일의 클라이언트 ID/시크릿 확인
2. 리다이렉트 URI가 정확한지 확인
3. 세션 설정 문제시 `debug_session.php` 실행

### 권한 접근 문제
1. `family_members` 테이블의 `access_level` 확인
2. `user_auth` 테이블의 `family_member_id` 연결 상태 확인
3. 세션 변수 상태 확인

### 데이터베이스 연결 문제
1. `.env` 파일의 DB 설정 확인
2. Cafe24 데이터베이스 서버 상태 확인
3. `check_table_relationship.php`로 테이블 관계 확인

## 📞 지원

시스템 관련 문의나 오류 발생시:
- 개발자: 조유 (drjo70)
- 이메일: drjo70@gmail.com
- GitHub: https://github.com/drjo70/cyjc

---
**보안 주의사항**: `.env` 파일은 절대 GitHub에 업로드하지 마세요. 민감한 정보가 포함되어 있습니다.