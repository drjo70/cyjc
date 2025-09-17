# 📦 창녕조씨 족보 시스템 v4.0 - 다운로드 가이드

## 👨‍💼 개발자 정보
- **개발자**: 닥터조 (컴퓨터 IT 박사)
- **회사**: (주)조유 대표이사
- **버전**: v4.0 (Cafe24 호스팅 최적화)
- **배포일**: 2024-09-17

---

## 🔗 **다운로드 링크**

### **📱 GitHub 저장소**
```
https://github.com/drjo70/cyjc
```

### **📦 ZIP 다운로드**
```
https://github.com/drjo70/cyjc/archive/refs/heads/main.zip
```

### **🏢 라이브 사이트**
```
https://cyjc25.mycafe24.com/
```

---

## 📋 **포함된 파일들**

### **🏠 메인 페이지**
- `index.php` - 닥터조님 중심 1~15세대 직계 족보
- `search.php` - 인물 검색 (이름/세대/거주지)
- `person_detail.php` - 인물 상세 페이지

### **🚀 혁신 기능들**
- `ai_analyzer.php` - AI 족보 분석 시스템
- `tree_3d.php` - 3D 족보 시각화
- `voice_search.php` - 음성 검색 시스템
- `family_tree.php` - 기본 가계도

### **⚙️ 설정 파일들**
- `config/database.php` - MySQL 데이터베이스 설정
- `.htaccess` - Apache 서버 설정
- `CAFE24_배포가이드.md` - 상세 배포 가이드

### **📁 디렉토리 구조**
```
webapp/
├── config/           # 데이터베이스 설정
├── controllers/      # PHP 컨트롤러
├── models/          # 데이터 모델
├── views/           # 뷰 템플릿
├── public/          # 정적 파일 (CSS, JS, 이미지)
├── api/             # REST API 엔드포인트
├── migrations/      # 데이터베이스 마이그레이션
├── utils/           # 유틸리티 함수들
└── *.php           # 메인 페이지들
```

---

## 🚀 **설치 방법**

### **1. GitHub에서 다운로드**
```bash
# 방법 1: ZIP 다운로드
https://github.com/drjo70/cyjc/archive/refs/heads/main.zip

# 방법 2: Git Clone
git clone https://github.com/drjo70/cyjc.git
```

### **2. Cafe24 FTP 업로드**
1. **ZIP 파일 해제**
2. **webapp 폴더 내용**을 **public_html**에 업로드
3. **데이터베이스 설정** (`config/database.php` 수정)
4. **MySQL 스키마** 생성 및 데이터 입력

### **3. 데이터베이스 설정**
```php
// config/database.php 수정
define('DB_HOST', 'localhost');  // Cafe24 호스트
define('DB_NAME', 'changnyeong_jo');  // DB명
define('DB_USER', 'cafe24_username');  // Cafe24 사용자명
define('DB_PASS', 'cafe24_password');  // Cafe24 비밀번호
```

---

## ✅ **주요 기능들**

### **🏠 닥터조님 중심 족보**
- 1세대부터 15세대까지 직계 조상
- 클릭으로 각 세대 상세 정보
- 현재 세대 하이라이트 표시

### **🔍 고급 검색**
- **이름 검색**: 한글명, 별칭 지원
- **세대 검색**: 1~20세대 선택
- **거주지 검색**: 지역별 필터링
- **복합 검색**: 여러 조건 동시 적용

### **👤 상세 프로필**
- 완전한 개인 정보
- 가족 관계도 (부모, 배우자, 자녀)
- 학력 및 경력 이력
- 성과 및 취미
- 연락처 정보

### **🤖 혁신 기능들**
- **AI 분석**: 혈통 패턴, 관계 추천, DNA 시뮬레이션
- **3D 시각화**: Galaxy, Tree, Sphere, Helix 모드
- **음성 검색**: 한국어 음성인식 및 TTS

---

## 🔧 **기술 스택**

### **Backend**
- PHP 8+ 
- MySQL 8.0
- Apache Server
- REST API

### **Frontend** 
- HTML5 / CSS3 / JavaScript ES6+
- TailwindCSS (CDN)
- FontAwesome Icons
- AOS Animations
- Three.js (3D 시각화)
- Chart.js (데이터 시각화)

### **Hosting**
- Cafe24 공유 호스팅
- UTF8MB4 인코딩
- 반응형 디자인 (모바일 완벽 지원)

---

## 📞 **지원 및 문의**

### **개발자 연락처**
- **이름**: 닥터조 (컴퓨터 IT 박사)  
- **회사**: (주)조유 대표이사
- **전문분야**: 컨설팅, AI, 시스템 개발, 족보 연구

### **기술 지원**
- **GitHub Issues**: https://github.com/drjo70/cyjc/issues
- **라이브 사이트**: https://cyjc25.mycafe24.com/
- **시스템 문의**: 개발자 직접 문의

---

## 🎯 **향후 개발 계획**

### **단기 (1-3개월)**
- ✅ AI 족보 분석 시스템
- ✅ 3D 족보 시각화  
- ✅ 음성 검색 시스템
- 🔄 QR 코드 인물 카드
- 🔄 실시간 족보 채팅

### **중기 (3-6개월)**
- 📱 모바일 앱 개발
- 🤖 ChatGPT 통합
- 📊 고급 통계 대시보드
- 🌐 다국어 지원

### **장기 (6개월+)**
- ☁️ 클라우드 네이티브 전환
- 🔗 블록체인 기반 족보 인증
- 🎯 AI 추천 시스템
- 🌍 글로벌 족보 플랫폼

---

**© 2024 창녕조씨 족보 시스템 | 개발: 닥터조 ((주)조유) | GitHub: drjo70/cyjc**