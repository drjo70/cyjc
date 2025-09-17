import { Hono } from 'hono'
import { cors } from 'hono/cors'
import { serveStatic } from 'hono/cloudflare-workers'

// 타입 정의
type Bindings = {
  DB: D1Database
}

type Variables = {
  user?: {
    phone: string
    person_code?: string
    access_level: number
  }
}

const app = new Hono<{ Bindings: Bindings; Variables: Variables }>()

// CORS 설정
app.use('/api/*', cors())

// 정적 파일 서빙
app.use('/static/*', serveStatic({ root: './public' }))

// favicon.ico 핸들러 (Context finalization 에러 방지)
app.get('/favicon.ico', async (c) => {
  // 명시적으로 null body로 204 응답
  return new Response(null, {
    status: 204,
    headers: {
      'Cache-Control': 'public, max-age=86400'
    }
  })
})

// manifest.json 핸들러
app.get('/manifest.json', async (c) => {
  const manifest = {
    "name": "창녕조씨 강릉파보 모바일 족보",
    "short_name": "족보앱",
    "description": "창녕조씨 강릉파보 모바일 족보 웹앱",
    "start_url": "/",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#2563eb",
    "icons": [
      {
        "src": "/static/icon-192.png",
        "sizes": "192x192",
        "type": "image/png"
      },
      {
        "src": "/static/icon-512.png",
        "sizes": "512x512",
        "type": "image/png"
      }
    ]
  }
  
  return c.json(manifest, 200, {
    'Content-Type': 'application/manifest+json',
    'Cache-Control': 'public, max-age=86400'
  })
})

// 미들웨어: 사용자 인증 (간단한 전화번호 기반)
app.use('/api/protected/*', async (c, next) => {
  const auth = c.req.header('Authorization')
  if (!auth) {
    return c.json({ error: '인증이 필요합니다' }, 401)
  }
  
  const phone = auth.replace('Bearer ', '')
  if (!phone || !phone.match(/^010-\d{4}-\d{4}$/)) {
    return c.json({ error: '올바른 전화번호 형식이 아닙니다' }, 401)
  }
  
  // DB에서 사용자 확인
  const user = await c.env.DB.prepare(`
    SELECT person_code, access_level FROM family_members 
    WHERE phone_number = ?
  `).bind(phone).first()
  
  if (!user) {
    return c.json({ error: '등록되지 않은 사용자입니다' }, 403)
  }
  
  c.set('user', {
    phone,
    person_code: user.person_code as string,
    access_level: user.access_level as number
  })
  
  await next()
})

// ===================================================
// 메인 페이지
// ===================================================
app.get('/', (c) => {
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>창녕조씨 강릉파보 모바일 족보</title>
        
        <!-- PWA 메타태그 -->
        <meta name="description" content="창녕조씨 강릉파보 모바일 족보 - 언제 어디서나 족보 확인">
        <meta name="theme-color" content="#2563eb">
        <link rel="manifest" href="/manifest.json">
        <link rel="apple-touch-icon" href="/static/icon-192.png">
        
        <!-- CSS 라이브러리 -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
        
        <!-- 모바일 최적화 -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        
        <style>
        body { 
          font-family: 'Noto Sans KR', 'Noto Serif KR', serif;
          -webkit-font-smoothing: antialiased;
          -moz-osx-font-smoothing: grayscale;
          background: #f8faf9;
        }
        
        /* 전통적인 족보 색상 테마 */
        .traditional-header {
          background: linear-gradient(135deg, #1a2e45 0%, #2d5a3f 50%, #4a6741 100%);
          border-bottom: 3px solid #d4a574;
        }
        
        .traditional-card {
          background: #ffffff;
          border: 1px solid #e8e8e6;
          box-shadow: 0 4px 12px rgba(0,0,0,0.05);
          border-radius: 8px;
        }
        
        .traditional-btn {
          background: linear-gradient(135deg, #2d5a3f 0%, #4a6741 100%);
          border: 1px solid #d4a574;
          transition: all 0.3s ease;
          color: white;
        }
        
        .traditional-btn:hover {
          background: linear-gradient(135deg, #4a6741 0%, #5d7a54 100%);
          transform: translateY(-1px);
          box-shadow: 0 6px 15px rgba(45, 90, 63, 0.3);
        }
        
        .family-crest {
          width: 40px;
          height: 40px;
          background: radial-gradient(circle, #d4a574 0%, #b8935f 100%);
          border-radius: 50%;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          color: #1a2e45;
          font-weight: bold;
          font-size: 18px;
        }
        
        .genealogy-title {
          font-family: 'Noto Serif KR', serif;
          font-weight: 700;
          letter-spacing: 2px;
        }
        
        .clan-subtitle {
          color: rgba(255,255,255,0.9);
          font-size: 14px;
          letter-spacing: 1px;
        }
        
        .menu-icon {
          width: 50px;
          height: 50px;
          border-radius: 12px;
          background: linear-gradient(135deg, #f8faf9 0%, #e8ebe9 100%);
          border: 1px solid #d4a574;
          display: flex;
          align-items: center;
          justify-content: center;
          margin: 0 auto 12px;
          color: #2d5a3f;
          transition: all 0.2s ease;
        }
        
        .menu-icon:hover {
          background: linear-gradient(135deg, #2d5a3f 0%, #4a6741 100%);
          color: white;
          transform: scale(1.05);
        }
        
        /* PC 브라우저 최적화 */
        @media (min-width: 768px) {
          .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
          }
          
          .menu-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
          }
          
          .large-menu-card {
            padding: 32px;
            text-align: center;
          }
        }
        
        .korean-text {
          font-family: 'Noto Serif KR', serif;
        }
        
        .hanja-text {
          font-family: 'Noto Serif KR', serif;
          color: #666;
          font-size: 0.9em;
        }
        </style>
    </head>
    <body class="bg-gray-50">
        <!-- 전통 족보 헤더 -->
        <div class="traditional-header text-white p-8 text-center">
            <div class="flex items-center justify-center mb-6">
                <div class="family-crest mr-4">
                    <span class="korean-text">曺</span>
                </div>
                <div class="text-left">
                    <h1 class="genealogy-title text-3xl md:text-4xl mb-1">창녕조씨 강릉파보</h1>
                    <p class="hanja-text text-lg">昌寧曺氏 江陵派譜</p>
                </div>
            </div>
            <p class="clan-subtitle">조계룡(曺繼龍) 후예 · 강릉파 족보 시스템</p>
            <p class="text-sm text-white/70 mt-3">실제 데이터 기반 8촌 계통도 · PC/모바일 최적화</p>
        </div>

        <!-- 메인 메뉴 -->
        <div class="main-container p-6 space-y-6">
            
            <!-- 로그인 섹션 -->
            <div class="traditional-card p-8" id="loginSection">
                <h2 class="text-xl font-bold mb-6 text-center korean-text text-gray-800">
                    <i class="fas fa-key mr-3 text-yellow-600"></i>
                    족보 입문
                </h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">전화번호</label>
                        <input type="tel" id="phoneInput" 
                               placeholder="010-0000-0000" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               maxlength="13">
                        <p class="text-xs text-gray-500 mt-1">족보에 등록된 전화번호를 입력하세요</p>
                    </div>
                    <button onclick="login()" class="w-full traditional-btn py-4 rounded-lg font-medium korean-text">
                        <i class="fas fa-door-open mr-2"></i>
                        족보 접속하기
                    </button>
                </div>
            </div>
            
            <!-- 메인 메뉴 (로그인 후 표시) -->
            <div class="space-y-6 hidden" id="mainMenu">
                
                <!-- 내 정보 카드 -->
                <div class="traditional-card p-6" id="myInfoCard">
                    <!-- JavaScript로 동적 생성 -->
                </div>
                
                <!-- 족보 메뉴 그리드 -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 menu-grid">
                    
                    <!-- 8촌 계통도 -->
                    <div onclick="openLineage()" class="traditional-card large-menu-card cursor-pointer transition-all hover:shadow-lg">
                        <div class="menu-icon">
                            <i class="fas fa-sitemap text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-800 korean-text mb-1">8촌 계통도</h3>
                        <p class="hanja-text text-sm">八寸系統圖</p>
                        <p class="text-xs text-gray-600 mt-2">실제 혈연관계 확인</p>
                    </div>
                    
                    <!-- 종인 검색 -->
                    <div onclick="openSearch()" class="traditional-card large-menu-card cursor-pointer transition-all hover:shadow-lg">
                        <div class="menu-icon">
                            <i class="fas fa-search text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-800 korean-text mb-1">종인 검색</h3>
                        <p class="hanja-text text-sm">宗人檢索</p>
                        <p class="text-xs text-gray-600 mt-2">이름·세대별 찾기</p>
                    </div>
                    
                    <!-- 관계 조회 -->
                    <div onclick="openRelationship()" class="traditional-card large-menu-card cursor-pointer transition-all hover:shadow-lg">
                        <div class="menu-icon">
                            <i class="fas fa-project-diagram text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-800 korean-text mb-1">관계 조회</h3>
                        <p class="hanja-text text-sm">關係照會</p>
                        <p class="text-xs text-gray-600 mt-2">촌수·관계 확인</p>
                    </div>
                    
                    <!-- 직계혈통 -->
                    <div onclick="openDirectLineage()" class="traditional-card large-menu-card cursor-pointer transition-all hover:shadow-lg">
                        <div class="menu-icon">
                            <i class="fas fa-sitemap text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-800 korean-text mb-1">직계혈통</h3>
                        <p class="hanja-text text-sm">直系血統</p>
                        <p class="text-xs text-gray-600 mt-2">시조부터 나까지</p>
                    </div>
                    
                    <!-- 족보 소식 -->
                    <div onclick="openAnnouncements()" class="traditional-card large-menu-card cursor-pointer transition-all hover:shadow-lg">
                        <div class="menu-icon">
                            <i class="fas fa-newspaper text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-800 korean-text mb-1">족보 소식</h3>
                        <p class="hanja-text text-sm">族譜消息</p>
                        <p class="text-xs text-gray-600 mt-2">공지·업데이트</p>
                    </div>
                    
                </div>
                
                <!-- 부가 기능 -->
                <div class="traditional-card p-6">
                    <h3 class="font-bold mb-4 korean-text text-gray-800">
                        <i class="fas fa-tools mr-2 text-yellow-600"></i>
                        부가 기능
                        <span class="hanja-text ml-2">附加機能</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <button onclick="openNearby()" class="text-left p-4 rounded-lg border border-gray-200 hover:border-yellow-400 hover:bg-yellow-50 flex items-center transition-all">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-map-marker-alt text-orange-600"></i>
                            </div>
                            <div>
                                <div class="font-medium korean-text">내 주변 종인</div>
                                <div class="text-xs text-gray-500 hanja-text">位置基盤 宗人 檢索</div>
                            </div>
                        </button>
                        <button onclick="contactAdmin()" class="text-left p-4 rounded-lg border border-gray-200 hover:border-green-400 hover:bg-green-50 flex items-center transition-all">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-comments text-green-600"></i>
                            </div>
                            <div>
                                <div class="font-medium korean-text">관리자 문의</div>
                                <div class="text-xs text-gray-500 hanja-text">管理者 問議</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- 하단 정보 -->
        <div class="text-center text-gray-500 text-sm p-4 mt-8">
            <p>창녕조씨 강릉파보 모바일 족보 v1.0</p>
            <p class="mt-1">© 2025 창녕조씨 강릉파보</p>
            <p class="mt-2 text-xs">
                <i class="fas fa-shield-alt mr-1"></i>
                개인정보보호를 위해 8촌 이내만 편집 가능
            </p>
        </div>

        <!-- JavaScript -->
        <script src="/static/app.js"></script>
    </body>
    </html>
  `)
})

// ===================================================
// API 라우트: 인증
// ===================================================
app.get('/api/auth/verify', async (c) => {
  const auth = c.req.header('Authorization')
  if (!auth) {
    return c.json({ error: '인증이 필요합니다' }, 401)
  }
  
  const phone = auth.replace('Bearer ', '')
  const user = await c.env.DB.prepare(`
    SELECT person_code, name, access_level FROM family_members 
    WHERE phone_number = ?
  `).bind(phone).first()
  
  if (!user) {
    return c.json({ error: '등록되지 않은 사용자입니다' }, 403)
  }
  
  return c.json({
    phone,
    person_code: user.person_code,
    name: user.name,
    access_level: user.access_level
  })
})

// ===================================================
// API 라우트: 내 정보
// ===================================================
app.get('/api/protected/my-info', async (c) => {
  const user = c.get('user')
  if (!user?.person_code) {
    return c.json({ error: '사용자 정보가 없습니다' }, 400)
  }
  
  const info = await c.env.DB.prepare(`
    SELECT * FROM family_members WHERE person_code = ?
  `).bind(user.person_code).first()
  
  if (!info) {
    return c.json({ error: '사용자를 찾을 수 없습니다' }, 404)
  }
  
  return c.json(info)
})

// ===================================================
// API 라우트: 공지사항
// ===================================================
app.get('/api/announcements', async (c) => {
  const announcements = await c.env.DB.prepare(`
    SELECT * FROM announcements 
    WHERE is_active = 1 
    ORDER BY is_important DESC, created_at DESC
    LIMIT 10
  `).all()
  
  return c.json(announcements.results || [])
})

// ===================================================
// API 라우트: 가족 목록 전체 조회
// ===================================================
app.get('/api/family/list', async (c) => {
  try {
    const results = await c.env.DB.prepare(`
      SELECT person_code, name, name_hanja, generation, gender, 
             birth_date, is_deceased, phone_number, parent_code, sibling_order
      FROM family_members 
      ORDER BY generation, sibling_order
    `).all()
    
    return c.json(results.results || [])
  } catch (error) {
    console.error('가족 목록 조회 오류:', error)
    return c.json({ 
      error: 'Internal server error', 
      message: error.message 
    }, 500)
  }
})

// ===================================================
// API 라우트: 가족 검색
// ===================================================
app.get('/api/search', async (c) => {
  const query = c.req.query('q')
  const generation = c.req.query('generation')
  
  let sql = `
    SELECT person_code, name, name_hanja, generation, gender, 
           birth_date, is_deceased, phone_number
    FROM family_members 
    WHERE 1=1
  `
  
  const params = []
  
  if (query) {
    sql += ` AND (name LIKE ? OR name_hanja LIKE ? OR phone_number LIKE ?)`
    params.push(`%${query}%`, `%${query}%`, `%${query}%`)
  }
  
  if (generation) {
    sql += ` AND generation = ?`
    params.push(generation)
  }
  
  sql += ` ORDER BY generation, sibling_order LIMIT 50`
  
  const results = await c.env.DB.prepare(sql).bind(...params).all()
  return c.json(results.results || [])
})

// ===================================================
// API 라우트: 계통도 (확장 가능한 버전) - 성능 최적화 적용
// ===================================================
app.get('/api/protected/lineage', async (c) => {
  // 🚀 캐싱 헤더 설정 (빠른 응답을 위한 브라우저 캐싱)
  c.header('Cache-Control', 'public, max-age=300, s-maxage=600') // 5분 브라우저, 10분 CDN
  c.header('ETag', `lineage-${Date.now()}`)
  const user = c.get('user')
  if (!user?.person_code) {
    return c.json({ error: '사용자 정보가 없습니다' }, 400)
  }

  const focusPersonCode = c.req.query('focus') || user.person_code
  const expandUp = Math.min(parseInt(c.req.query('up') || '4'), 8) // 위로 최대 8세대 (8촌 범위)
  const expandDown = Math.min(parseInt(c.req.query('down') || '4'), 8) // 아래로 최대 8세대 (8촌 범위)
  const includeSiblings = c.req.query('siblings') !== 'false' // 형제 포함 여부

  try {
    // 🚀 성능 개선: 단일 쿼리로 포커스 인물 + 부모 정보 한번에 조회
    const focusPersonQuery = await c.env.DB.prepare(`
      SELECT 
        f.*,
        p.name as parent_name,
        p.name_hanja as parent_name_hanja,
        p.generation as parent_generation
      FROM family_members f
      LEFT JOIN family_members p ON f.parent_code = p.person_code
      WHERE f.person_code = ?
    `).bind(focusPersonCode).first()
    
    const focusPerson = focusPersonQuery

    if (!focusPerson) {
      return c.json({ error: '대상을 찾을 수 없습니다' }, 404)
    }

    // 🚀 성능 개선: 배우자 정보 조회 (인덱스 활용)
    const spouseResults = await c.env.DB.prepare(`
      SELECT spouse_name, spouse_name_hanja, spouse_family_origin, spouse_father_name,
             marriage_order, spouse_birth_date, spouse_death_date, marriage_date
      FROM spouses 
      WHERE person_code = ? 
      ORDER BY marriage_order
      LIMIT 10
    `).bind(focusPersonCode).all()
    
    const focusSpouses = (spouseResults.results || []).map(s => ({
      spouse_name: s.spouse_name,
      spouse_name_hanja: s.spouse_name_hanja,
      spouse_family_origin: s.spouse_family_origin,
      spouse_father_name: s.spouse_father_name,
      marriage_order: s.marriage_order,
      spouse_birth_date: s.spouse_birth_date,
      spouse_death_date: s.spouse_death_date,
      marriage_date: s.marriage_date
    }))

    // 🚀 성능 개선: 형제들 조회 (필요한 필드만 선택, 인덱스 활용)
    let focusSiblings = []
    if (includeSiblings && focusPerson.parent_code) {
      const siblingResults = await c.env.DB.prepare(`
        SELECT person_code, parent_code, name, name_hanja, generation, gender,
               birth_date, is_deceased, sibling_order
        FROM family_members 
        WHERE parent_code = ? AND person_code != ? 
        ORDER BY sibling_order
        LIMIT 20
      `).bind(focusPerson.parent_code, focusPerson.person_code).all()
      
      focusSiblings = (siblingResults.results || []).map(s => ({
        person_code: s.person_code,
        parent_code: s.parent_code,
        name: s.name,
        name_hanja: s.name_hanja,
        generation: s.generation,
        gender: s.gender,
        birth_date: s.birth_date,
        is_deceased: s.is_deceased,
        sibling_order: s.sibling_order,
        level: 0
      }))
    }

    // 🚀 성능 개선: 자녀들 조회 (필요한 필드만 선택, 인덱스 활용)
    const childrenResults = await c.env.DB.prepare(`
      SELECT person_code, parent_code, name, name_hanja, generation, gender,
             birth_date, is_deceased, sibling_order
      FROM family_members 
      WHERE parent_code = ? 
      ORDER BY sibling_order
      LIMIT 30
    `).bind(focusPersonCode).all()
    
    const focusChildren = (childrenResults.results || []).map(c => ({
      person_code: c.person_code,
      parent_code: c.parent_code,
      name: c.name,
      name_hanja: c.name_hanja,
      generation: c.generation,
      gender: c.gender,
      birth_date: c.birth_date,
      is_deceased: c.is_deceased,
      sibling_order: c.sibling_order,
      level: -1,
      kinship_distance: 1,
      relationship_type: 'direct'
    }))

    // 5. 진짜 8촌 시스템 - 모든 방계 친척 수집
    const ancestors = []
    const collateralRelatives = []
    
    // 8촌 전체 수집을 위한 전략: 공통 조상 기반 접근
    // 1. 포커스의 8대조까지 올라가면서 각 세대의 모든 형제와 그 후손 수집
    // 2. 공통조상과의 거리를 기준으로 촌수 계산
    
    // 🚀 성능 개선: 후손 수집 함수 최적화 (배치 제한, 필수 필드만)
    async function getAllDescendantsWithinDistance(personCode, baseGeneration, maxKinship, currentKinship = 1) {
      if (currentKinship > maxKinship || currentKinship > 4) return [] // 4촌까지만 (성능)
      
      const children = await c.env.DB.prepare(`
        SELECT person_code, parent_code, name, name_hanja, generation, gender,
               birth_date, is_deceased, sibling_order
        FROM family_members 
        WHERE parent_code = ? 
        ORDER BY sibling_order 
        LIMIT 20
      `).bind(personCode).all()

      const result = []
      for (const child of children.results || []) {
        const childData = {
          person_code: child.person_code,
          parent_code: child.parent_code,
          name: child.name,
          name_hanja: child.name_hanja,
          generation: child.generation,
          gender: child.gender,
          birth_date: child.birth_date,
          is_deceased: child.is_deceased,
          sibling_order: child.sibling_order,
          kinship_distance: currentKinship,
          relationship_type: 'collateral',
          level: child.generation - focusPerson.generation,
          common_ancestor_distance: Math.abs(baseGeneration - focusPerson.generation)
        }
        
        result.push(childData)
        
        // 재귀적으로 후손들도 수집 (8촌 이내)
        if (currentKinship + 1 <= maxKinship) {
          const grandchildren = await getAllDescendantsWithinDistance(child.person_code, baseGeneration, maxKinship, currentKinship + 1)
          result.push(...grandchildren)
        }
      }
      
      return result
    }

    // 🚀 성능 개선: 4촌까지만 조회 (닥터조님 요구사항 + 성능 고려)
    let currentPersonCode = focusPerson.parent_code
    let currentGeneration = focusPerson.generation
    
    // 4촌까지만 올라가면서 각 세대의 친척들 수집 (성능 최적화)
    for (let ancestorLevel = 1; ancestorLevel <= 4 && currentPersonCode; ancestorLevel++) {
      // 🚀 단일 쿼리로 조상 + 형제들 정보 한번에 조회
      const ancestorAndSiblingsResult = await c.env.DB.prepare(`
        WITH ancestor_info AS (
          SELECT person_code, parent_code, name, name_hanja, generation, gender,
                 birth_date, death_date, is_deceased, sibling_order, 'ancestor' as relation_type
          FROM family_members WHERE person_code = ?
        ),
        siblings_info AS (
          SELECT s.person_code, s.parent_code, s.name, s.name_hanja, s.generation, s.gender,
                 s.birth_date, s.death_date, s.is_deceased, s.sibling_order, 'sibling' as relation_type
          FROM family_members s, ancestor_info a
          WHERE s.parent_code = a.parent_code AND s.person_code != a.person_code
          ORDER BY s.sibling_order LIMIT 15
        )
        SELECT * FROM ancestor_info
        UNION ALL
        SELECT * FROM siblings_info
      `).bind(currentPersonCode).all()
      
      const results = ancestorAndSiblingsResult.results || []
      const ancestor = results.find(r => r.relation_type === 'ancestor')
      const ancestorSiblings = results.filter(r => r.relation_type === 'sibling')

      if (!ancestor) break

      // 직계 조상 저장 (성능 최적화된 데이터)
      ancestors.push({
        person_code: ancestor.person_code,
        parent_code: ancestor.parent_code,
        name: ancestor.name,
        name_hanja: ancestor.name_hanja,
        generation: ancestor.generation,
        gender: ancestor.gender,
        birth_date: ancestor.birth_date,
        is_deceased: ancestor.is_deceased,
        sibling_order: ancestor.sibling_order,
        level: ancestorLevel,
        kinship_distance: ancestorLevel,
        relationship_type: 'direct'
      })

      // 🚀 성능 개선: 이미 조회한 형제 정보 활용
      for (const sibling of ancestorSiblings) {
          const siblingKinship = ancestorLevel + 1
          
          if (siblingKinship <= 4) { // 4촌까지만 (성능 최적화)
            // 조상의 형제 자신 추가 (예: 삼촌, 4촌 등)
            collateralRelatives.push({
              person_code: sibling.person_code,
              parent_code: sibling.parent_code,
              name: sibling.name,
              name_hanja: sibling.name_hanja,
              generation: sibling.generation,
              gender: sibling.gender,
              birth_date: sibling.birth_date,
              is_deceased: sibling.is_deceased,
              sibling_order: sibling.sibling_order,
              kinship_distance: siblingKinship,
              relationship_type: 'collateral',
              level: sibling.generation - focusPerson.generation,
              common_ancestor_distance: ancestorLevel
            })
            
            // 🚀 성능 개선: 4촌 이내로 제한
            const maxRemaining = 4 - siblingKinship
            if (maxRemaining > 0) {
              const siblingDescendants = await getAllDescendantsWithinDistance(
                sibling.person_code, 
                sibling.generation,
                maxRemaining,
                siblingKinship + 1
              )
              collateralRelatives.push(...siblingDescendants)
            }
          }
      }

      // 다음 세대로 올라가기
      currentPersonCode = ancestor.parent_code
      currentGeneration = ancestor.generation
    }
    
    // 🚀 성능 개선: 이미 조회한 focusSiblings 활용
    if (focusPerson.parent_code && focusSiblings.length > 0) {
      for (const sibling of focusSiblings) {
        // 내 형제자매 (2촌)
        collateralRelatives.push({
          person_code: sibling.person_code,
          parent_code: sibling.parent_code,
          name: sibling.name,
          name_hanja: sibling.name_hanja,
          generation: sibling.generation,
          gender: sibling.gender,
          birth_date: sibling.birth_date,
          is_deceased: sibling.is_deceased,
          sibling_order: sibling.sibling_order,
          kinship_distance: 2,
          relationship_type: 'collateral',
          level: 0, // 같은 세대
          common_ancestor_distance: 1
        })
        
        // 🚀 성능 개선: 4촌 이내로 제한 (조카만)
        const nephewNieces = await getAllDescendantsWithinDistance(
          sibling.person_code,
          sibling.generation,
          2, // 4 - 2(형제) = 2촌까지 (4촌 내)
          3 // 3촌부터 시작
        )
        collateralRelatives.push(...nephewNieces)
      }
    }

    // 6. 직계 후손 조회 (포커스 대상부터 아래로)
    const descendants = []
    
    // 🚀 성능 개선: 직계 후손들 조회 (4세대까지, 필수 필드만)
    async function getDirectDescendants(personCode, currentLevel, maxLevel) {
      if (currentLevel >= maxLevel || currentLevel >= 4) return [] // 4세대 제한
      
      const children = await c.env.DB.prepare(`
        SELECT person_code, parent_code, name, name_hanja, generation, gender,
               birth_date, is_deceased, sibling_order
        FROM family_members 
        WHERE parent_code = ? 
        ORDER BY sibling_order 
        LIMIT 15
      `).bind(personCode).all()

      const result = []
      for (const child of children.results || []) {
        const childData = {
          person_code: child.person_code,
          parent_code: child.parent_code,
          name: child.name,
          name_hanja: child.name_hanja,
          generation: child.generation,
          gender: child.gender,
          birth_date: child.birth_date,
          is_deceased: child.is_deceased,
          sibling_order: child.sibling_order,
          level: -(currentLevel + 1),
          kinship_distance: currentLevel + 1,
          relationship_type: 'direct' // 직계
        }
        
        result.push(childData)
        
        // 직계 후손들도 재귀적으로 조회
        const grandchildren = await getDirectDescendants(child.person_code, currentLevel + 1, maxLevel)
        result.push(...grandchildren)
      }
      
      return result
    }

    const descendantsList = await getDirectDescendants(focusPersonCode, 0, Math.min(expandDown, 4))
    descendants.push(...descendantsList)

    // 7. 결과 조합 및 정렬 - 4촌 시스템 (성능 최적화)
    const lineageData = {
      focus_person: {
        person_code: focusPerson.person_code,
        parent_code: focusPerson.parent_code, // ✅ 부모 코드 추가
        name: focusPerson.name,
        name_hanja: focusPerson.name_hanja,
        generation: focusPerson.generation,
        gender: focusPerson.gender,
        birth_date: focusPerson.birth_date,
        is_deceased: focusPerson.is_deceased,
        sibling_order: focusPerson.sibling_order,
        kinship_distance: 0, // 자기 자신
        relationship_type: 'self'
      },
      focus_spouses: focusSpouses, // 포커스 인물의 배우자들
      focus_children: focusChildren, // 포커스 인물의 자녀들 직계 자식
      current_user: {
        person_code: user.person_code,
        name: user.name || '사용자',
        generation: user.generation || 0
      },
      // 직계 친척 (조상 + 후손)
      direct_ancestors: ancestors.sort((a, b) => a.generation - b.generation),
      direct_descendants: descendants.sort((a, b) => a.generation - b.generation),
      // 방계 친척 (8촌 이내 모든 친척)
      collateral_relatives: collateralRelatives.sort((a, b) => {
        // 1차: 촌수 순 (가까운 친척부터)
        if (a.kinship_distance !== b.kinship_distance) {
          return a.kinship_distance - b.kinship_distance
        }
        // 2차: 세대 순
        return a.generation - b.generation
      }),
      // 통계 정보
      statistics: {
        total_relatives: ancestors.length + descendants.length + collateralRelatives.length + 1, // +1 for focus person
        direct_count: ancestors.length + descendants.length + 1,
        collateral_count: collateralRelatives.length,
        kinship_distribution: {
          // 촌수별 인원 수 계산 (직계 + 방계)
          1: (ancestors.filter(a => a.kinship_distance === 1).length || 0) + (descendants.filter(d => d.kinship_distance === 1).length || 0),
          2: collateralRelatives.filter(r => r.kinship_distance === 2).length || 0,
          3: collateralRelatives.filter(r => r.kinship_distance === 3).length || 0,
          4: collateralRelatives.filter(r => r.kinship_distance === 4).length || 0,
          5: collateralRelatives.filter(r => r.kinship_distance === 5).length || 0,
          6: collateralRelatives.filter(r => r.kinship_distance === 6).length || 0,
          7: collateralRelatives.filter(r => r.kinship_distance === 7).length || 0,
          8: collateralRelatives.filter(r => r.kinship_distance === 8).length || 0
        }
      },
      expand_info: {
        up: expandUp,
        down: expandDown,
        siblings: includeSiblings,
        focus: focusPersonCode,
        kinship_system: '8-chon' // 8촌 시스템
      }
    }

    return c.json(lineageData)

  } catch (error) {
    console.error('계통도 조회 오류:', error)
    return c.json({ 
      error: 'Internal server error', 
      message: error.message 
    }, 500)
  }
})

// ===================================================
// API 라우트: 직계혈통보기 - 시조부터 현재 사용자까지 직계 조상
// ===================================================
app.get('/api/protected/direct-lineage', async (c) => {
  const user = c.get('user')
  if (!user || !user.person_code) {
    return c.json({ error: 'Authentication required' }, 401)
  }

  try {
    // 현재 사용자부터 시작하여 조상을 거슬러 올라가며 직계 혈통 추적
    const directLineage = []
    let currentPersonCode = user.person_code
    let depth = 0
    const maxDepth = 50 // 무한 루프 방지

    while (currentPersonCode && depth < maxDepth) {
      // 현재 인물 정보 조회
      const personResult = await c.env.DB.prepare(`
        SELECT person_code, parent_code, name, name_hanja, generation, gender,
               birth_date, death_date, is_deceased, phone_number
        FROM family_members 
        WHERE person_code = ?
      `).bind(currentPersonCode).first()

      if (!personResult) break

      directLineage.push({
        person_code: personResult.person_code,
        parent_code: personResult.parent_code,
        name: personResult.name,
        name_hanja: personResult.name_hanja,
        generation: personResult.generation,
        gender: personResult.gender,
        birth_date: personResult.birth_date,
        death_date: personResult.death_date,
        is_deceased: personResult.is_deceased,
        phone_number: personResult.phone_number,
        level: depth // 0: 본인, 1: 부친, 2: 조부, ...
      })

      // 다음은 부친으로 이동
      currentPersonCode = personResult.parent_code
      depth++
    }

    // 시조부터 현재까지 순서로 정렬 (generation 기준 오름차순)
    directLineage.sort((a, b) => a.generation - b.generation)

    // 각 인물에 대해 level 재계산 (시조부터 0, 1, 2, ...)
    directLineage.forEach((person, index) => {
      person.level = index
    })

    return c.json({
      success: true,
      data: {
        total_generations: directLineage.length,
        lineage: directLineage,
        current_person: user.person_code
      }
    })
  } catch (error) {
    console.error('직계혈통 조회 오류:', error)
    return c.json({ 
      error: 'Internal server error', 
      message: error.message 
    }, 500)
  }
})

// ===================================================
// 페이지 라우트들
// ===================================================

// 계통도 페이지
app.get('/lineage', (c) => {
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>계통도 - 창녕조씨 강릉파보</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
        
        <style>
        .lineage-tree {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            padding: 20px 0;
        }
        
        .generation-level {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        
        .siblings-container {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            max-width: 100%;
            padding: 0 20px;
        }
        
        @media (max-width: 768px) {
            .siblings-container {
                gap: 10px;
                padding: 0 10px;
            }
        }
        
        .lineage-node {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 15px 20px;
            min-width: 200px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            flex-shrink: 0;
            margin: 5px;
        }
        
        @media (max-width: 768px) {
            .lineage-node {
                min-width: 140px;
                padding: 10px 12px;
                margin: 2px;
            }
        }
        
        .lineage-node.clickable {
            cursor: pointer;
        }
        
        .lineage-node:hover {
            border-color: #3b82f6;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }
        
        .lineage-node.current-user {
            border-color: #dc2626;
            background: #fef2f2;
            border-width: 3px;
        }
        
        .lineage-node.ancestor {
            border-color: #059669;
            background: #f0fdf4;
        }
        
        .lineage-node.descendant {
            border-color: #7c3aed;
            background: #faf5ff;
        }
        
        .lineage-node.sibling {
            opacity: 0.8;
            min-width: 120px;
            font-size: 0.9em;
        }
        
        .lineage-node.sibling:hover {
            opacity: 1;
        }
        
        .lineage-connector {
            width: 2px;
            height: 15px;
            background: #9ca3af;
        }
        
        .generation-badge {
            display: inline-block;
            background: #3b82f6;
            color: white;
            font-size: 9px;
            padding: 2px 5px;
            border-radius: 8px;
            margin-left: 4px;
        }
        
        .loading-spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes expand {
            0% { transform: scale(0.95); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .lineage-node.expanding {
            animation: expand 0.3s ease-out;
        }
        
        /* 모바일 최적화 */
        @media (max-width: 640px) {
            .siblings-container {
                gap: 8px;
            }
            
            .lineage-node {
                min-width: 110px;
                padding: 8px 10px;
                font-size: 0.9em;
            }
            
            .lineage-node.sibling {
                min-width: 100px;
                font-size: 0.8em;
            }
        }
        </style>
    </head>
    <body class="bg-gray-50">
        <!-- 헤더 -->
        <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-4">
            <div class="flex items-center justify-between max-w-6xl mx-auto px-4">
                <div class="flex items-center">
                    <a href="/" class="mr-4 p-2 hover:bg-white hover:bg-opacity-20 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <h1 class="text-xl font-bold">계통도</h1>
                </div>
                <i class="fas fa-sitemap text-2xl"></i>
            </div>
        </div>

        <!-- 메인 컨텐츠 -->
        <div class="p-4 max-w-6xl mx-auto">
            
            <!-- 로딩 상태 -->
            <div id="loadingSection" class="text-center py-8">
                <div class="loading-spinner mx-auto mb-4"></div>
                <p class="text-gray-600">계통도를 불러오는 중...</p>
            </div>

            <!-- 에러 상태 -->
            <div id="errorSection" class="hidden bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <div>
                        <h3 class="font-medium text-red-800">오류가 발생했습니다</h3>
                        <p class="text-sm text-red-600" id="errorMessage">계통도를 불러올 수 없습니다.</p>
                    </div>
                </div>
                <button onclick="loadLineage()" class="mt-3 bg-red-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-redo mr-2"></i>
                    다시 시도
                </button>
            </div>

            <!-- 계통도 컨텐츠 -->
            <div id="lineageContent" class="hidden">
                
                <!-- 계통도 정보 -->
                <div class="bg-white rounded-lg p-4 mb-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-bold text-lg" id="currentUserName">사용자</h2>
                            <p class="text-sm text-gray-600" id="currentUserInfo">정보 로드 중...</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500">직계 조상</div>
                            <div class="font-bold text-green-600" id="ancestorsCount">0세대</div>
                        </div>
                    </div>
                </div>

                <!-- 계통도 트리 -->
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <div id="lineageTree" class="lineage-tree">
                        <!-- JavaScript로 동적 생성 -->
                    </div>
                </div>

            </div>
        </div>

        <script src="/static/app.js?v=20250911-11"></script>
        <script src="/static/lineage.js?v=20250911-11"></script>
    </body>
    </html>
  `)
})



// 관계도 페이지
app.get('/relationship', (c) => {
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>관계도 - 창녕조씨 강릉파보</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-50">
        <div class="p-4 max-w-md mx-auto">
            <div class="bg-white rounded-lg p-6 shadow-lg">
                <h1 class="text-2xl font-bold mb-4">
                    <i class="fas fa-project-diagram mr-2"></i>
                    관계도
                </h1>
                <p class="text-gray-600 mb-4">나와의 관계를 확인할 수 있는 관계도입니다.</p>
                <p class="text-sm text-orange-500 mb-6">🚧 현재 개발 중입니다.</p>
                <a href="/" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i>
                    홈으로 돌아가기
                </a>
            </div>
        </div>
    </body>
    </html>
  `)
})

// 공지사항 페이지
app.get('/announcements', (c) => {
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>공지사항 - 창녕조씨 강릉파보</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-50">
        <div class="p-4 max-w-md mx-auto">
            <div class="bg-white rounded-lg p-6 shadow-lg">
                <h1 class="text-2xl font-bold mb-4">
                    <i class="fas fa-bullhorn mr-2"></i>
                    공지사항
                </h1>
                <p class="text-gray-600 mb-4">족보 관련 최신 공지사항을 확인하세요.</p>
                <p class="text-sm text-orange-500 mb-6">🚧 현재 개발 중입니다.</p>
                <a href="/" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i>
                    홈으로 돌아가기
                </a>
            </div>
        </div>
    </body>
    </html>
  `)
})

// ===================================================
// 검색 API
// ===================================================

// 통합 검색 API (이름, 주소, 전화번호)
app.get('/api/protected/search', async (c) => {
  const { query, type, generation, limit = '50' } = c.req.query()
  
  if (!query || query.trim().length === 0) {
    return c.json({ error: '검색어를 입력해주세요' }, 400)
  }

  try {
    let sql = ''
    let params: any[] = []

    // 검색 타입에 따른 쿼리 구성
    switch (type) {
      case 'name':
        sql = `
          SELECT person_code, name, name_hanja, generation, gender, parent_code,
                 birth_date, death_date, is_deceased, phone_number, 
                 home_address, work_address, sibling_order
          FROM family_members 
          WHERE (name LIKE ? OR name_hanja LIKE ?)
        `
        params = [`%${query}%`, `%${query}%`]
        break

      case 'phone':
        sql = `
          SELECT person_code, name, name_hanja, generation, gender, parent_code,
                 birth_date, death_date, is_deceased, phone_number, 
                 home_address, work_address, sibling_order
          FROM family_members 
          WHERE phone_number LIKE ?
        `
        params = [`%${query}%`]
        break

      case 'address':
        sql = `
          SELECT person_code, name, name_hanja, generation, gender, parent_code,
                 birth_date, death_date, is_deceased, phone_number, 
                 home_address, work_address, sibling_order
          FROM family_members 
          WHERE (home_address LIKE ? OR work_address LIKE ?)
        `
        params = [`%${query}%`, `%${query}%`]
        break

      default: // 전체 검색
        sql = `
          SELECT person_code, name, name_hanja, generation, gender, parent_code,
                 birth_date, death_date, is_deceased, phone_number, 
                 home_address, work_address, sibling_order
          FROM family_members 
          WHERE (name LIKE ? OR name_hanja LIKE ? OR phone_number LIKE ? 
                 OR home_address LIKE ? OR work_address LIKE ?)
        `
        params = [`%${query}%`, `%${query}%`, `%${query}%`, `%${query}%`, `%${query}%`]
    }

    // 세대 필터 추가
    if (generation && generation !== 'all') {
      sql += ` AND generation = ?`
      params.push(parseInt(generation))
    }

    // 정렬 및 제한
    sql += ` ORDER BY generation DESC, sibling_order ASC LIMIT ?`
    params.push(parseInt(limit))

    const result = await c.env.DB.prepare(sql).bind(...params).all()
    const members = result.results || []

    // 부모 정보도 함께 조회 (효율성을 위해 별도 쿼리)
    const parentCodes = [...new Set(members.map(m => m.parent_code).filter(Boolean))]
    let parentMap = {}
    
    if (parentCodes.length > 0) {
      const parentPlaceholders = parentCodes.map(() => '?').join(',')
      const parentResult = await c.env.DB.prepare(`
        SELECT person_code, name, name_hanja 
        FROM family_members 
        WHERE person_code IN (${parentPlaceholders})
      `).bind(...parentCodes).all()
      
      parentMap = Object.fromEntries(
        (parentResult.results || []).map(p => [p.person_code, p])
      )
    }

    // 결과 가공
    const searchResults = members.map(member => ({
      person_code: member.person_code,
      name: member.name,
      name_hanja: member.name_hanja,
      generation: member.generation,
      gender: member.gender,
      parent: member.parent_code ? parentMap[member.parent_code] : null,
      birth_date: member.birth_date,
      death_date: member.death_date,
      is_deceased: member.is_deceased,
      phone_number: member.phone_number,
      home_address: member.home_address,
      work_address: member.work_address,
      sibling_order: member.sibling_order
    }))

    return c.json({
      success: true,
      query,
      type,
      generation,
      total_count: searchResults.length,
      results: searchResults
    }, {
      headers: {
        'Cache-Control': 'public, max-age=300' // 5분 캐시
      }
    })

  } catch (error) {
    console.error('Search error:', error)
    return c.json({ 
      error: '검색 중 오류가 발생했습니다',
      message: error.message 
    }, 500)
  }
})

// 세대별 보기 API
app.get('/api/protected/generations', async (c) => {
  const { generation, limit = '100' } = c.req.query()
  
  try {
    let sql = `
      SELECT person_code, name, name_hanja, generation, gender, parent_code,
             birth_date, death_date, is_deceased, phone_number, sibling_order
      FROM family_members
    `
    let params: any[] = []

    if (generation && generation !== 'all') {
      sql += ` WHERE generation = ?`
      params.push(parseInt(generation))
    }

    sql += ` ORDER BY generation DESC, sibling_order ASC LIMIT ?`
    params.push(parseInt(limit))

    const result = await c.env.DB.prepare(sql).bind(...params).all()
    const members = result.results || []

    // 부모 정보도 함께 조회
    const parentCodes = [...new Set(members.map(m => m.parent_code).filter(Boolean))]
    let parentMap = {}
    
    if (parentCodes.length > 0) {
      const parentPlaceholders = parentCodes.map(() => '?').join(',')
      const parentResult = await c.env.DB.prepare(`
        SELECT person_code, name, name_hanja 
        FROM family_members 
        WHERE person_code IN (${parentPlaceholders})
      `).bind(...parentCodes).all()
      
      parentMap = Object.fromEntries(
        (parentResult.results || []).map(p => [p.person_code, p])
      )
    }

    // 결과 가공
    const generationMembers = members.map(member => ({
      person_code: member.person_code,
      name: member.name,
      name_hanja: member.name_hanja,
      generation: member.generation,
      gender: member.gender,
      parent: member.parent_code ? parentMap[member.parent_code] : null,
      birth_date: member.birth_date,
      death_date: member.death_date,
      is_deceased: member.is_deceased,
      phone_number: member.phone_number,
      sibling_order: member.sibling_order
    }))

    return c.json({
      success: true,
      generation: generation || 'all',
      total_count: generationMembers.length,
      results: generationMembers
    }, {
      headers: {
        'Cache-Control': 'public, max-age=600' // 10분 캐시
      }
    })

  } catch (error) {
    console.error('Generation query error:', error)
    return c.json({ 
      error: '세대별 조회 중 오류가 발생했습니다',
      message: error.message 
    }, 500)
  }
})

// 상세보기 API
app.get('/api/protected/person/:person_code', async (c) => {
  const { person_code } = c.req.param()
  
  if (!person_code) {
    return c.json({ error: '인물 코드가 필요합니다' }, 400)
  }

  try {
    // 기본 인물 정보
    const personResult = await c.env.DB.prepare(`
      SELECT * FROM family_members WHERE person_code = ?
    `).bind(person_code).first()

    if (!personResult) {
      return c.json({ error: '해당 인물을 찾을 수 없습니다' }, 404)
    }

    // 부모 정보
    let parent = null
    if (personResult.parent_code) {
      parent = await c.env.DB.prepare(`
        SELECT person_code, name, name_hanja, generation, gender, birth_date, death_date, is_deceased
        FROM family_members WHERE person_code = ?
      `).bind(personResult.parent_code).first()
    }

    // 조부모 정보 (부모의 부모)
    let grandparent = null
    if (parent && parent.parent_code) {
      grandparent = await c.env.DB.prepare(`
        SELECT person_code, name, name_hanja, generation, gender, birth_date, death_date, is_deceased
        FROM family_members WHERE person_code = ?
      `).bind(parent.parent_code).first()
    }

    // 자식들 정보
    const childrenResult = await c.env.DB.prepare(`
      SELECT person_code, name, name_hanja, generation, gender, birth_date, death_date, is_deceased, sibling_order
      FROM family_members 
      WHERE parent_code = ?
      ORDER BY sibling_order ASC
    `).bind(person_code).all()
    const children = childrenResult.results || []

    // 형제자매 정보
    let siblings = []
    if (personResult.parent_code) {
      const siblingsResult = await c.env.DB.prepare(`
        SELECT person_code, name, name_hanja, generation, gender, birth_date, death_date, is_deceased, sibling_order
        FROM family_members 
        WHERE parent_code = ? AND person_code != ?
        ORDER BY sibling_order ASC
      `).bind(personResult.parent_code, person_code).all()
      siblings = siblingsResult.results || []
    }

    // 로그인한 사용자와의 관계 계산 (간단한 버전)
    const currentUser = c.get('user')
    let relationshipWithUser = null
    
    if (currentUser && currentUser.person_code !== person_code) {
      // 기본적인 관계 계산 (동일 세대인지, 직계인지 등)
      const userResult = await c.env.DB.prepare(`
        SELECT generation, parent_code FROM family_members WHERE person_code = ?
      `).bind(currentUser.person_code).first()
      
      if (userResult) {
        const generationDiff = Math.abs(personResult.generation - userResult.generation)
        relationshipWithUser = {
          generation_difference: generationDiff,
          is_same_generation: generationDiff === 0,
          is_direct_ancestor: false, // 복잡한 계산은 나중에 구현
          estimated_relationship: generationDiff === 0 ? '같은 세대' : 
                                 generationDiff === 1 ? '한 세대 차이' : 
                                 `${generationDiff}세대 차이`
        }
      }
    }

    return c.json({
      success: true,
      person: personResult,
      parent,
      grandparent,
      children,
      siblings,
      relationship_with_user: relationshipWithUser,
      statistics: {
        children_count: children.length,
        siblings_count: siblings.length
      }
    }, {
      headers: {
        'Cache-Control': 'public, max-age=300' // 5분 캐시
      }
    })

  } catch (error) {
    console.error('Person detail error:', error)
    return c.json({ 
      error: '인물 상세정보 조회 중 오류가 발생했습니다',
      message: error.message 
    }, 500)
  }
})

// ===================================================
// 직계혈통보기 페이지
// ===================================================

// 직계혈통보기 페이지
app.get('/direct-lineage', (c) => {
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>직계혈통보기 - 창녕조씨 강릉파보</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
        
        <style>
        .lineage-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            background: #f8fafc;
            min-height: calc(100vh - 80px);
        }
        
        .generation-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .generation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .generation-header {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 16px 20px;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .generation-number {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .person-info {
            padding: 20px;
        }
        
        .person-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .person-hanja {
            font-size: 1rem;
            color: #6b7280;
            font-weight: 400;
        }
        
        .person-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 16px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4b5563;
            font-size: 0.9rem;
        }
        
        .detail-icon {
            color: #3b82f6;
            width: 16px;
            text-align: center;
        }
        
        .action-buttons {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-outline {
            background: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
            color: #374151;
        }
        
        .current-person {
            border: 3px solid #10b981;
        }
        
        .current-person .generation-header {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .navigation-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            z-index: 1000;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            font-size: 0.75rem;
            padding: 5px 10px;
        }
        
        .nav-item.active {
            color: #3b82f6;
        }
        
        .nav-item i {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        
        body {
            padding-bottom: 80px;
            background: #f8fafc;
        }
        
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
        }
        
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 16px;
            border-radius: 8px;
            margin: 20px;
            text-align: center;
        }
        
        @media (min-width: 640px) {
            .person-details {
                grid-template-columns: 1fr 1fr 1fr;
            }
            
            .lineage-container {
                max-width: 600px;
            }
        }
        </style>
    </head>
    <body>
        <!-- 헤더 -->
        <div class="bg-white border-b border-gray-200 p-4 sticky top-0 z-50">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-sitemap mr-2 text-blue-600"></i>
                    직계혈통보기
                </h1>
                <div class="flex items-center gap-3">
                    <button onclick="refreshLineage()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <a href="/search" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="lineage-container">
            <!-- 로딩 표시 -->
            <div id="loadingSection" class="loading-spinner">
                <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i>
                <p class="text-gray-600">직계혈통 정보를 불러오는 중...</p>
            </div>

            <!-- 직계혈통 목록 -->
            <div id="lineageContent" class="hidden">
                <div class="text-center mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-2">시조부터 나까지</h2>
                    <p id="generationSummary" class="text-gray-600"></p>
                </div>
                
                <div id="lineageList">
                    <!-- 동적으로 생성 -->
                </div>
            </div>

            <!-- 에러 표시 -->
            <div id="errorSection" class="hidden">
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle text-2xl mb-3"></i>
                    <h3 class="font-medium mb-2">정보를 불러올 수 없습니다</h3>
                    <p id="errorMessage" class="text-sm"></p>
                    <button onclick="refreshLineage()" class="btn btn-primary mt-3">
                        <i class="fas fa-sync-alt mr-1"></i>
                        다시 시도
                    </button>
                </div>
            </div>
        </div>

        <!-- 하단 네비게이션 -->
        <nav class="navigation-bar">
            <a href="/" class="nav-item">
                <i class="fas fa-home"></i>
                <span>홈</span>
            </a>
            <a href="/lineage" class="nav-item">
                <i class="fas fa-project-diagram"></i>
                <span>계통도</span>
            </a>
            <a href="/direct-lineage" class="nav-item active">
                <i class="fas fa-sitemap"></i>
                <span>직계혈통</span>
            </a>
            <a href="/search" class="nav-item">
                <i class="fas fa-search"></i>
                <span>검색</span>
            </a>
            <a href="/announcements" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>설정</span>
            </a>
        </nav>

        <script src="/static/direct-lineage.js"></script>
    </body>
    </html>
  `)
})

// ===================================================
// 검색 및 세대별 페이지
// ===================================================

// 검색 페이지
app.get('/search', (c) => {
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>검색 - 창녕조씨 강릉파보</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
        
        <style>
        .search-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .search-form {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-results {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .result-table th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .result-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .result-table tr:hover {
            background: #f8fafc;
        }
        
        .detail-btn {
            background: #22c55e;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }
        
        .detail-btn:hover {
            background: #16a34a;
        }
        
        .navigation-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            z-index: 1000;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            font-size: 0.75rem;
            padding: 5px 10px;
        }
        
        .nav-item.active {
            color: #22c55e;
        }
        
        .nav-item i {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        
        body {
            padding-bottom: 80px;
            background: #f8fafc;
        }
        </style>
    </head>
    <body>
        <!-- 헤더 -->
        <div class="bg-white border-b border-gray-200 p-4">
            <div class="search-container">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-search mr-2 text-green-600"></i>
                    족보 검색
                </h1>
            </div>
        </div>

        <div class="search-container">
            <!-- 검색 폼 -->
            <div class="search-form">
                <div class="flex gap-3 mb-4">
                    <select id="searchType" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="all">전체</option>
                        <option value="name">이름</option>
                        <option value="phone">전화번호</option>
                        <option value="address">주소</option>
                    </select>
                    <input type="text" id="searchQuery" placeholder="검색어를 입력하세요" 
                           class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <button onclick="performSearch()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-search mr-1"></i>
                        검색
                    </button>
                </div>
                
                <div class="flex gap-3">
                    <select id="generationFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="all">전체 세대</option>
                        <option value="30">30세대</option>
                        <option value="31">31세대</option>
                        <option value="32">32세대</option>
                        <option value="33">33세대</option>
                        <option value="34">34세대</option>
                        <option value="35">35세대</option>
                        <option value="36">36세대</option>
                        <option value="37">37세대</option>
                        <option value="38">38세대</option>
                        <option value="39">39세대</option>
                        <option value="40">40세대</option>
                        <option value="41">41세대</option>
                        <option value="42">42세대</option>
                        <option value="43">43세대</option>
                        <option value="44">44세대</option>
                        <option value="45">45세대</option>
                    </select>
                    <button onclick="loadGenerationView()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-list mr-1"></i>
                        세대별 보기
                    </button>
                </div>
            </div>

            <!-- 로딩 표시 -->
            <div id="loadingSection" class="hidden">
                <div class="search-results p-8 text-center">
                    <i class="fas fa-spinner fa-spin text-2xl text-green-600 mb-4"></i>
                    <p class="text-gray-600">검색 중...</p>
                </div>
            </div>

            <!-- 검색 결과 -->
            <div id="searchResults" class="search-results hidden">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">
                        <span id="resultTitle">검색 결과</span>
                        <span id="resultCount" class="ml-2 text-sm text-gray-500"></span>
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="result-table">
                        <thead>
                            <tr>
                                <th>세대</th>
                                <th>이름(한자)</th>
                                <th>부모(한자)</th>
                                <th>전화번호</th>
                                <th>상세보기</th>
                            </tr>
                        </thead>
                        <tbody id="resultTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 빈 결과 -->
            <div id="emptyResults" class="search-results hidden">
                <div class="p-8 text-center">
                    <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">검색 결과가 없습니다</h3>
                    <p class="text-gray-600">다른 검색어를 입력해주세요.</p>
                </div>
            </div>

            <!-- 에러 표시 -->
            <div id="errorSection" class="search-results hidden">
                <div class="p-8 text-center">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">오류가 발생했습니다</h3>
                    <p id="errorMessage" class="text-gray-600"></p>
                </div>
            </div>
        </div>

        <!-- 하단 네비게이션 -->
        <nav class="navigation-bar">
            <a href="/" class="nav-item">
                <i class="fas fa-home"></i>
                <span>홈</span>
            </a>
            <a href="/lineage" class="nav-item">
                <i class="fas fa-project-diagram"></i>
                <span>계통도</span>
            </a>
            <a href="/direct-lineage" class="nav-item">
                <i class="fas fa-sitemap"></i>
                <span>직계혈통</span>
            </a>
            <a href="/search?generation=all" class="nav-item">
                <i class="fas fa-users"></i>
                <span>세대별</span>
            </a>
            <a href="/search" class="nav-item active">
                <i class="fas fa-search"></i>
                <span>검색</span>
            </a>
            <a href="/announcements" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>설정</span>
            </a>
        </nav>

        <script src="/static/search.js"></script>
    </body>
    </html>
  `)
})

// 상세보기 페이지
app.get('/person/:person_code', (c) => {
  const { person_code } = c.req.param()
  
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>인물 상세정보 - 창녕조씨 강릉파보</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
        
        <style>
        .detail-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #6b7280;
        }
        
        .navigation-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .nav-btn {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .nav-btn.primary {
            background: #3b82f6;
            color: white;
        }
        
        .nav-btn.primary:hover {
            background: #2563eb;
        }
        
        .nav-btn.secondary {
            background: #10b981;
            color: white;
        }
        
        .nav-btn.secondary:hover {
            background: #059669;
        }
        
        .nav-btn.outline {
            background: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }
        
        .nav-btn.outline:hover {
            background: #f9fafb;
        }
        
        .family-list {
            display: grid;
            gap: 10px;
        }
        
        .family-member {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-weight: 600;
            color: #374151;
        }
        
        .member-details {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 2px;
        }
        
        .navigation-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            z-index: 1000;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            font-size: 0.75rem;
            padding: 5px 10px;
        }
        
        .nav-item i {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        
        body {
            padding-bottom: 80px;
            background: #f8fafc;
        }
        </style>
    </head>
    <body>
        <!-- 헤더 -->
        <div class="bg-white border-b border-gray-200 p-4">
            <div class="detail-container">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-user mr-2 text-blue-600"></i>
                        <span id="personTitle">인물 상세정보</span>
                    </h1>
                    <a href="/search" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="detail-container">
            <!-- 로딩 표시 -->
            <div id="loadingSection">
                <div class="info-card text-center">
                    <i class="fas fa-spinner fa-spin text-2xl text-blue-600 mb-4"></i>
                    <p class="text-gray-600">정보를 불러오는 중...</p>
                </div>
            </div>

            <!-- 상세 정보 -->
            <div id="detailContent" class="hidden">
                <!-- 네비게이션 버튼들 -->
                <div class="navigation-buttons">
                    <a id="grandparentBtn" href="#" class="nav-btn primary hidden">
                        <i class="fas fa-arrow-up"></i>
                        조부님
                    </a>
                    <a id="parentBtn" href="#" class="nav-btn primary hidden">
                        <i class="fas fa-arrow-up"></i>
                        부친
                    </a>
                    <button id="childrenBtn" onclick="toggleChildren()" class="nav-btn secondary hidden">
                        <i class="fas fa-arrow-down"></i>
                        자식보기
                    </button>
                    <button id="relationshipBtn" onclick="showRelationship()" class="nav-btn outline">
                        <i class="fas fa-heart"></i>
                        나와의 관계
                    </button>
                </div>

                <!-- 기본 정보 -->
                <div class="info-card">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                        기본 정보
                    </h2>
                    <div class="info-grid" id="basicInfo">
                        <!-- 동적으로 생성 -->
                    </div>
                </div>

                <!-- 가족 관계 -->
                <div class="info-card">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">
                        <i class="fas fa-users mr-2 text-green-600"></i>
                        가족 관계
                    </h2>
                    
                    <!-- 형제자매 -->
                    <div id="siblingsSection" class="hidden">
                        <h3 class="text-lg font-medium text-gray-700 mb-3">형제자매</h3>
                        <div class="family-list" id="siblingsList">
                            <!-- 동적으로 생성 -->
                        </div>
                    </div>
                </div>

                <!-- 자식들 (토글 가능) -->
                <div id="childrenSection" class="info-card hidden">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">
                        <i class="fas fa-child mr-2 text-purple-600"></i>
                        자식들
                    </h2>
                    <div class="family-list" id="childrenList">
                        <!-- 동적으로 생성 -->
                    </div>
                </div>

                <!-- 나와의 관계 -->
                <div id="relationshipSection" class="info-card hidden">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">
                        <i class="fas fa-heart mr-2 text-red-600"></i>
                        나와의 관계
                    </h2>
                    <div id="relationshipInfo">
                        <!-- 동적으로 생성 -->
                    </div>
                </div>
            </div>

            <!-- 에러 표시 -->
            <div id="errorSection" class="info-card hidden">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">오류가 발생했습니다</h3>
                    <p id="errorMessage" class="text-gray-600 mb-4"></p>
                    <a href="/search" class="nav-btn primary">
                        <i class="fas fa-arrow-left mr-1"></i>
                        검색으로 돌아가기
                    </a>
                </div>
            </div>
        </div>

        <!-- 하단 네비게이션 -->
        <nav class="navigation-bar">
            <a href="/" class="nav-item">
                <i class="fas fa-home"></i>
                <span>홈</span>
            </a>
            <a href="/lineage" class="nav-item">
                <i class="fas fa-project-diagram"></i>
                <span>계통도</span>
            </a>
            <a href="/direct-lineage" class="nav-item">
                <i class="fas fa-sitemap"></i>
                <span>직계혈통</span>
            </a>
            <a href="/search?generation=all" class="nav-item">
                <i class="fas fa-users"></i>
                <span>세대별</span>
            </a>
            <a href="/search" class="nav-item">
                <i class="fas fa-search"></i>
                <span>검색</span>
            </a>
            <a href="/announcements" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>설정</span>
            </a>
        </nav>

        <script>
            // 페이지 로드 시 상세 정보 불러오기
            const personCode = '${person_code}';
            let currentPersonData = null;
            
            document.addEventListener('DOMContentLoaded', function() {
                loadPersonDetail();
            });
        </script>
        <script src="/static/person-detail.js"></script>
    </body>
    </html>
  `)
})

// 404 핸들러 (Context finalization 확실히 보장)
app.notFound((c) => {
  // 명시적으로 Response 객체 반환
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>페이지를 찾을 수 없습니다 - 창녕조씨 족보</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="text-center bg-white rounded-lg p-8 shadow-lg max-w-md w-full">
                <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800 mb-4">페이지를 찾을 수 없습니다</h1>
                <p class="text-gray-600 mb-6">요청하신 페이지가 존재하지 않습니다.</p>
                <a href="/" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors inline-block">
                    <i class="fas fa-home mr-2"></i>
                    홈으로 돌아가기
                </a>
            </div>
        </div>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    </body>
    </html>
  `, 404)
})

// 에러 핸들러 (Context finalization 확실히 보장)
app.onError((err, c) => {
  console.error('Application error:', err)
  
  // 에러 타입에 따른 적절한 응답
  if (err.message.includes('Context is not finalized')) {
    console.error('Context finalization error - returning proper response')
    return c.json({ error: 'Internal server error' }, 500)
  }
  
  return c.json({ 
    error: 'Internal server error',
    message: err.message 
  }, 500)
})

export default app