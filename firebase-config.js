// Firebase 설정 파일
// 실제 프로젝트에서는 Firebase Console에서 받은 설정으로 교체

const firebaseConfig = {
  apiKey: "your-api-key",
  authDomain: "your-project.firebaseapp.com", 
  projectId: "your-project-id",
  storageBucket: "your-project.appspot.com",
  messagingSenderId: "123456789",
  appId: "your-app-id"
};

// Firebase 초기화
import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

// 푸시 알림 권한 요청 및 토큰 획득
export async function initializePush() {
  try {
    // 알림 권한 요청
    const permission = await Notification.requestPermission();
    
    if (permission === 'granted') {
      console.log('알림 권한이 허용되었습니다.');
      
      // FCM 토큰 받기 
      const token = await getToken(messaging, {
        vapidKey: 'your-vapid-key' // Firebase Console에서 생성
      });
      
      if (token) {
        console.log('FCM 토큰:', token);
        // 서버에 토큰 저장
        await saveTokenToServer(token);
        return token;
      }
    } else {
      console.log('알림 권한이 거부되었습니다.');
    }
  } catch (error) {
    console.error('푸시 알림 초기화 오류:', error);
  }
}

// 포그라운드 메시지 처리
export function handleForegroundMessages() {
  onMessage(messaging, (payload) => {
    console.log('포그라운드 메시지 수신:', payload);
    
    // 커스텀 알림 표시
    showCustomNotification(payload);
  });
}

// 서버에 토큰 저장
async function saveTokenToServer(token) {
  try {
    const response = await fetch('/save_push_token.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ 
        token: token,
        user_code: getCurrentUserCode() // 현재 사용자 코드
      })
    });
    
    if (response.ok) {
      console.log('푸시 토큰이 서버에 저장되었습니다.');
    }
  } catch (error) {
    console.error('토큰 저장 오류:', error);
  }
}

// 커스텀 알림 표시
function showCustomNotification(payload) {
  const { title, body, icon, click_action } = payload.notification;
  
  // 브라우저 내장 알림 사용
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready.then(registration => {
      registration.showNotification(title, {
        body: body,
        icon: icon || '/static/icon-192.png',
        badge: '/static/icon-192.png',
        data: {
          url: click_action || '/'
        },
        actions: [
          {
            action: 'open',
            title: '확인하기'
          },
          {
            action: 'close', 
            title: '닫기'
          }
        ]
      });
    });
  }
}

// 현재 사용자 코드 가져오기 (세션에서)
function getCurrentUserCode() {
  // PHP 세션에서 사용자 코드 가져오는 로직
  // 실제로는 서버에서 렌더링시 JavaScript 변수로 전달
  return window.currentUserCode || null;
}