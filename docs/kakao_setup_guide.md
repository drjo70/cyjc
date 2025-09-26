# 🟡 카카오 로그인 설정 가이드

닥터조님, 카카오톡 로그인을 사용하려면 **카카오 개발자 콘솔**에서 애플리케이션을 등록해야 합니다.

## 1️⃣ 카카오 개발자 콘솔 접속

1. **카카오 개발자 콘솔** 접속: https://developers.kakao.com/
2. **카카오 계정으로 로그인**
3. **내 애플리케이션** > **애플리케이션 추가하기**

## 2️⃣ 애플리케이션 등록

### 애플리케이션 정보
- **앱 이름**: `창녕조씨 족보 시스템`
- **회사명**: `창녕조씨`
- **카테고리**: `기타`

### 플랫폼 설정
- **Web** 플랫폼 추가
- **사이트 도메인**: `https://cyjc.jou.kr`

## 3️⃣ 카카오 로그인 활성화

### 제품 설정 > 카카오 로그인
1. **카카오 로그인** 활성화
2. **OpenID Connect** 활성화 (선택사항)

### Redirect URI 등록
**필수**: 다음 URL을 정확히 등록해주세요
```
https://cyjc.jou.kr/kakao_callback.php
```

### 동의항목 설정
- **닉네임**: 필수 동의
- **카카오계정(이메일)**: 선택 동의

## 4️⃣ 앱 키 확인

### 앱 설정 > 앱 키에서 확인
- **REST API 키**: `config.php`에 사용할 client_id
- **JavaScript 키**: 웹에서 사용 (선택사항)

## 5️⃣ config.php 설정 업데이트

```php
// 카카오 OAuth 설정 (실제 도메인: cyjc.jou.kr)
$kakao_config = [
    'client_id' => 'YOUR_REST_API_KEY_HERE', // 카카오 REST API 키
    'client_secret' => '', // 카카오는 선택사항 (빈 문자열로 둘 수 있음)
    'redirect_uri' => 'https://cyjc.jou.kr/kakao_callback.php',
    'scope' => 'profile_nickname,account_email'
];
```

## 6️⃣ 테스트

1. **DB 스키마 업데이트**: https://cyjc.jou.kr/add_kakao_support.php 실행
2. **로그인 테스트**: https://cyjc.jou.kr/login.php 에서 카카오 로그인 클릭
3. **인증 확인**: 카카오 로그인 후 정상 작동 확인

## 🔧 문제 해결

### 자주 발생하는 오류들

1. **KOE101 (앱 키가 잘못됨)**
   - config.php의 client_id 확인
   - 카카오 개발자 콘솔의 REST API 키와 일치하는지 확인

2. **KOE201 (Redirect URI 불일치)**
   - 카카오 콘솔에서 정확한 URL 등록: `https://cyjc.jou.kr/kakao_callback.php`
   - 프로토콜(https), 도메인, 경로가 모두 일치해야 함

3. **KOE006 (동의항목 미설정)**
   - 카카오 콘솔에서 닉네임, 이메일 동의항목 설정
   - 필수/선택 동의 설정 확인

## 📞 지원

설정 중 문제가 있으시면 카카오 개발자 문서를 참조하세요:
- 카카오 로그인 가이드: https://developers.kakao.com/docs/latest/ko/kakaologin/common
- API 레퍼런스: https://developers.kakao.com/docs/latest/ko/kakaologin/rest-api