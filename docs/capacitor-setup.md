# 📱 Capacitor로 안드로이드 APK 생성하기

## 🛠️ 준비 사항
- Node.js 설치
- Android Studio 설치  
- Java JDK 설치

## 📋 단계별 가이드

### 1단계: Capacitor 프로젝트 초기화
```bash
# 프로젝트 폴더에서
npm install @capacitor/core @capacitor/cli
npm install @capacitor/android

# Capacitor 초기화
npx cap init "창녕조씨족보" "com.cyjc.genealogy"
```

### 2단계: 안드로이드 플랫폼 추가
```bash
# 안드로이드 플랫폼 추가
npx cap add android

# 웹 파일 복사
npx cap copy android
```

### 3단계: Android Studio에서 빌드
```bash
# Android Studio 열기
npx cap open android

# Android Studio에서:
# - Build > Build APK(s) 선택
# - APK 파일 생성 완료
```

### 4단계: APK 파일 위치
```
android/app/build/outputs/apk/debug/app-debug.apk
```

## 📱 APK 배포 방법

### 직접 배포 (사이드로딩)
1. **APK 파일 공유**
   - 카카오톡, 이메일, USB 등으로 전송
   
2. **지인 스마트폰에서 설치**
   - "알 수 없는 소스" 허용
   - APK 파일 실행하여 설치

### 내부 배포
1. **Google Play Console**
   - 내부 테스트 트랙 사용
   - 특정 이메일 주소에만 배포

2. **Firebase App Distribution**
   - 무료 내부 배포 서비스
   - 링크로 간편 배포