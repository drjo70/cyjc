# 🏮 창녕조씨 족보시스템 v2.0

## 📋 프로젝트 개요

**창녕조씨 족보시스템**은 시조 조계룡(趙季龍)부터 45세대까지의 족보 데이터를 체계적으로 관리하는 **최신 웹 기반 PWA 시스템**입니다.

- **개발자**: 닥터조 (주)조유 대표이사 (IT 박사, 컨설팅 전문가)
- **버전**: 2.0 (2024-09-17 대폭 업데이트)
- **플랫폼**: Cafe24 웹호스팅 (PHP + MySQL)
- **GitHub**: https://github.com/drjo70/cyjc
- **연락처**: 010-9272-9081

## ✨ v2.0 주요 업데이트

### 🔥 새로 추가된 기능
1. **🔍 고급 검색 시스템** - 다중 조건 검색, 실시간 제안, 검색 통계
2. **📊 인터랙티브 가계도** - D3.js 기반 시각화, 줌/팬 기능, 관계 추적
3. **⚙️ 관리자 페이지** - 완전한 CRUD 기능, 통계 차트, 일괄 관리
4. **📱 PWA (Progressive Web App)** - 앱 설치, 오프라인 지원, 푸시 알림
5. **👤 개인 프로필 페이지** - 닥터조님 전용 상세 페이지

## 🎯 완성된 주요 기능

### 📊 대시보드 시스템
- 족보 통계 대시보드 (총 600여 명, 45세대)
- 세대별 인구 분포 차트 (Chart.js)
- 최근 활동 및 빠른 액세스 메뉴
- 반응형 카드 레이아웃

### 🔍 검색 및 분석
- **기본 검색**: 헤더 통합 검색바
- **고급 검색**: 다중 조건 (이름/한자명/세대/성별)
- **실시간 제안**: 자동완성 검색어 제안
- **검색 통계**: 인기 검색어, 세대별 밀도

### 📊 가계도 시각화
- **D3.js 기반** 인터랙티브 가계도
- **줌/팬 기능**: 마우스 휠, 드래그
- **세대별 레이아웃**: 자동 배치 알고리즘
- **인물 상세**: 클릭 시 모달 팝업

### 👑 관리자 시스템
- **보안 로그인**: 세션 기반 인증
- **CRUD 작업**: 인물 추가/수정/삭제
- **통계 차트**: 세대별 분포 시각화
- **데이터 검증**: 중복 방지, 관계 체크

### 📱 PWA 모바일 최적화
- **앱 설치**: 홈 화면 추가 지원
- **오프라인 모드**: Service Worker 캐싱
- **반응형 디자인**: 모바일 퍼스트 UI
- **푸시 알림**: 업데이트 알림 (준비됨)

## 🏗️ 시스템 구조

```
cyjc-genealogy/
├── 📁 config/
│   └── database.php              # DB 연결 (싱글톤 패턴)
├── 📁 models/
│   ├── Person.php               # 인물 모델 (고급 검색 포함)
│   └── Family.php               # 가족 관계 모델
├── 📁 controllers/
│   └── GenealogyController.php  # 메인 컨트롤러
├── 📁 views/
│   ├── header.php              # 공통 헤더 (PWA 메타태그)
│   ├── footer.php              # 공통 푸터 (PWA 스크립트)
│   ├── dashboard.php           # 대시보드
│   └── person_list.php         # 인물 목록
├── 📁 api/
│   └── get_person.php          # REST API 엔드포인트
├── 📁 assets/
│   ├── generate_icons.md       # PWA 아이콘 가이드
│   └── (icon files)            # PWA 아이콘들
├── 📄 index.php                # 메인 라우터
├── 📄 my_profile.php           # 닥터조님 개인 페이지
├── 📄 advanced_search.php      # 고급 검색 페이지
├── 📄 family_tree.php          # 인터랙티브 가계도
├── 📄 admin.php                # 관리자 페이지
├── 📄 manifest.json            # PWA 매니페스트
├── 📄 sw.js                    # Service Worker
└── 📄 README.md               # 이 문서
```

## 🚀 배포 및 접속 URL

### 🌐 주요 페이지 URL
```
https://yourdomain.cafe24.com/
├── /                          # 대시보드 (기본)
├── /my_profile.php           # 👤 닥터조님 프로필
├── /advanced_search.php      # 🔍 고급 검색
├── /family_tree.php          # 📊 인터랙티브 가계도  
├── /admin.php                # ⚙️ 관리자 페이지
├── ?page=persons             # 👥 전체 인물 목록
├── ?page=person&id=xxx       # 👤 인물 상세
├── ?page=generation          # 📚 세대별 족보
└── test_db.php              # 🔧 DB 상태 확인
```

### 📱 PWA 설치 방법
1. **Android Chrome**: "홈 화면에 추가" 알림 클릭
2. **iOS Safari**: 공유 버튼 → "홈 화면에 추가"
3. **Desktop**: 주소창의 설치 아이콘 클릭

## 🔧 기술 스택 v2.0

### Backend
- **PHP 7.4+** (Native, 모듈식 MVC 패턴)
- **MySQL 5.7+** (UTF8MB4, 관계형 DB 설계)
- **PDO** (Prepared Statements, SQL Injection 방지)

### Frontend  
- **Bootstrap 5.3** (반응형 프레임워크)
- **D3.js v7** (데이터 시각화)
- **Chart.js** (통계 차트)
- **Font Awesome 6.4** (아이콘)

### PWA 기술
- **Service Worker** (캐싱, 오프라인 지원)  
- **Web App Manifest** (앱 설치)
- **Responsive Design** (모바일 최적화)

## 📊 데이터베이스 정보

### 📈 데이터 현황 (2024-09-17 기준)
- **총 인물 수**: 600여 명
- **활성 세대**: 45세대 (시조 → 현재)
- **데이터 테이블**: `family_members` (통합 테이블)
- **관계 매핑**: parent_code 기반 트리 구조

### 🏗️ DB 스키마
```sql
CREATE TABLE family_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    person_code VARCHAR(20) UNIQUE,
    parent_code VARCHAR(20),
    name VARCHAR(100) NOT NULL,
    name_hanja VARCHAR(100),
    gender ENUM('남', '여'),
    generation INT,
    sibling_order INT,
    child_count INT DEFAULT 0,
    birth_date DATE,
    death_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 🔍 사용 가능한 검색 옵션

### 기본 검색 (헤더)
- **이름 검색**: 한글명, 한자명 부분 매칭
- **실시간 검색**: 입력과 동시에 결과 표시

### 고급 검색 (/advanced_search.php)
- **검색 유형**: 전체 / 이름 / 한글만 / 한자만 / 인물코드
- **세대 필터**: 1-45세대 선택
- **성별 필터**: 남성 / 여성
- **정렬 옵션**: 세대순 / 이름순 / 생년월일순

### 빠른 검색 버튼
- **1세대 (시조)** 바로가기
- **45세대 (최신)** 바로가기  
- **성별별** 전체 조회
- **특정 성씨** 검색

## ⚙️ 관리자 기능 (admin.php)

### 🔐 접속 방법
- **URL**: `/admin.php`
- **비밀번호**: `cyjc2024admin`
- **권한**: 세션 기반 관리

### 📝 CRUD 작업
- **인물 추가**: 신규 족보 데이터 입력
- **정보 수정**: 기존 인물 정보 업데이트  
- **인물 삭제**: 자녀 관계 확인 후 안전 삭제
- **데이터 검증**: 중복 방지, 필수 필드 체크

### 📊 관리 통계
- **실시간 차트**: 세대별 인구 분포
- **최근 활동**: 신규 등록 인물 목록
- **시스템 상태**: DB 연결, 성능 모니터링

## 📱 모바일 및 PWA 기능

### 📱 모바일 최적화
- **반응형 디자인**: 스마트폰, 태블릿 완벽 지원
- **터치 친화적 UI**: 큰 버튼, 스와이프 제스처
- **빠른 로딩**: 이미지 최적화, 지연 로딩

### 🚀 PWA 기능
- **오프라인 지원**: Service Worker 캐싱
- **앱 설치**: 홈 화면 추가 (Android, iOS, Desktop)
- **푸시 알림**: 업데이트 알림 (향후 활성화)
- **백그라운드 동기화**: 자동 데이터 업데이트

## 🔄 업데이트 계획

### 🎯 단기 계획 (1-2개월)
- [ ] **PWA 아이콘** 디자인 및 적용
- [ ] **사진 업로드** 기능 (인물별)
- [ ] **족보 PDF** 내보내기
- [ ] **일괄 가져오기** (CSV, Excel)

### 🚀 중장기 계획 (3-6개월)
- [ ] **사용자 권한** 시스템 (회원가입/로그인)
- [ ] **댓글/메모** 기능 (인물별)
- [ ] **족보 인쇄용** 최적화 레이아웃
- [ ] **모바일 네이티브** 앱 버전

## 🛠️ 유지보수 가이드

### 📂 파일별 역할
- **config/database.php**: DB 연결 설정 변경
- **models/Person.php**: 검색 로직, 데이터 처리
- **views/header.php**: 네비게이션, PWA 메타태그
- **sw.js**: PWA 캐싱 정책, 오프라인 기능

### 🔧 자주 수정하는 설정
```php
// DB 연결 정보 (config/database.php)
define('DB_HOST', 'localhost');
define('DB_USER', 'cyjc25'); 
define('DB_PASS', 'your_password');
define('DB_NAME', 'cyjc25');

// 관리자 비밀번호 (admin.php)
if ($password === 'cyjc2024admin') {

// PWA 캐시 버전 (sw.js)
const CACHE_NAME = 'cyjc-genealogy-v1.0.0';
```

## 📞 지원 및 문의

### 👨‍💻 개발자 정보
- **회사**: (주)조유
- **대표**: 닥터조 (컴퓨터 IT 박사)
- **전문분야**: IT 개발, 컨설팅, 프로그램 문서화
- **연락처**: 010-9272-9081

### 🌐 온라인 지원
- **GitHub 저장소**: https://github.com/drjo70/cyjc
- **이슈 제보**: GitHub Issues 사용
- **업데이트 알림**: GitHub Watch 권장

### 🏥 기술 지원
- **DB 문제**: test_db.php로 연결 상태 확인
- **성능 이슈**: 브라우저 개발자 도구 콘솔 확인
- **PWA 설치**: 브라우저별 설치 가이드 제공

---

## 📈 개발 성과

### ✅ 완성된 기능 (2024-09-17)
1. ✅ **GitHub 동기화** - 버전 관리 완료
2. ✅ **고급 검색 시스템** - 다중 조건 검색
3. ✅ **D3.js 가계도** - 인터랙티브 시각화
4. ✅ **관리자 CRUD** - 완전한 데이터 관리
5. ✅ **PWA 최적화** - 모바일 앱 수준 UX
6. ✅ **개인 프로필** - 닥터조님 전용 페이지

### 🎯 시스템 안정성
- **보안**: SQL Injection 방지, 세션 관리
- **성능**: 캐싱, 페이지네이션, 인덱스 최적화  
- **호환성**: 모던 브라우저 완벽 지원
- **확장성**: 모듈식 구조, API 기반

---

**🏮 창녕조씨 족보시스템 v2.0** - 전통 족보와 최신 웹 기술의 완벽한 융합

*"조상의 뿌리를 현대 기술로 이어나가다"* - 닥터조 (주)조유