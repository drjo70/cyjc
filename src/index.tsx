import { Hono } from 'hono'
import { cors } from 'hono/cors'
import { serveStatic } from 'hono/cloudflare-workers'

// 환경 변수 타입 정의
type Bindings = {
  DB_HOST?: string
  DB_USER?: string
  DB_PASSWORD?: string
  DB_NAME?: string
}

const app = new Hono<{ Bindings: Bindings }>()

// CORS 설정
app.use('/api/*', cors())

// 정적 파일 서빙
app.use('/static/*', serveStatic({ root: './public' }))

// 족보 데이터 구조 정의
interface FamilyMember {
  id: number
  person_code: string
  parent_code?: string
  name: string
  name_hanja?: string
  gender: number
  generation: number
  sibling_order: number
  child_count: number
  birth_date?: string
  death_date?: string
  is_deceased: boolean
  phone_number?: string
  email?: string
  home_address?: any
  work_address?: any
  biography?: string
  biography_hanja?: string
  is_adopted: boolean
  access_level: number
  created_at: string
  updated_at: string
}

// 임시 족보 데이터 (실제 운영에서는 MySQL DB 연결)
const sampleFamilyData: FamilyMember[] = [
  {
    id: 1,
    person_code: '1',
    parent_code: '-1',
    name: '조계룡',
    name_hanja: '曺繼龍',
    gender: 1,
    generation: 1,
    sibling_order: 1,
    child_count: 1,
    birth_date: '0000-00',
    death_date: '0000-00',
    is_deceased: true,
    biography: '신라진평왕여서봉창성부원군관지태사',
    biography_hanja: '新羅眞平王女壻封昌城府院君官至太師',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:24:26',
    updated_at: '2025-09-13 12:24:26'
  },
  {
    id: 5,
    person_code: '441258',
    parent_code: '431997',
    name: '조영국',
    name_hanja: '永國',
    gender: 1,
    generation: 44,
    sibling_order: 1,
    child_count: 0,
    birth_date: '1970-03-04',
    is_deceased: false,
    phone_number: '010-9272-9081',
    email: 'jo@jou.kr',
    home_address: {
      address: '강원도 강릉시 화부산로99번길 12 (교동,강릉 교동 롯데캐슬 1단지)',
      detail: '102동 403호'
    },
    work_address: {
      address: '강원도 강릉시 사임당로 641-22 (대전동)',
      detail: '212호'
    },
    biography: '컴퓨터 IT 박사, 컨설팅 전문가, 프로그램 개발자, (주)조유 대표이사',
    biography_hanja: '錫濂五代孫泰鉉子 庚戌西紀一九七〇年三月四日生',
    is_adopted: false,
    access_level: 1,
    created_at: '2025-09-13 12:13:54',
    updated_at: '2025-09-13 12:13:54'
  },
  {
    id: 9,
    person_code: '441264',
    parent_code: '441260',
    name: '박수현',
    name_hanja: '朴秀賢',
    gender: 2,
    generation: 45,
    sibling_order: 1,
    child_count: 0,
    birth_date: '1995-01-01',
    is_deceased: false,
    phone_number: '010-3333-3333',
    biography: '錫濂六代孫',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:19:34',
    updated_at: '2025-09-13 12:19:34'
  },
  {
    id: 10,
    person_code: '441265',
    parent_code: '441260',
    name: '박도현',
    name_hanja: '朴道賢',
    gender: 1,
    generation: 45,
    sibling_order: 2,
    child_count: 0,
    birth_date: '1997-01-01',
    is_deceased: false,
    phone_number: '010-4444-4444',
    biography: '錫濂六代孫',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:19:40',
    updated_at: '2025-09-13 12:19:40'
  }
]

// API 라우트들

// 전체 족보 목록 조회
app.get('/api/family', (c) => {
  return c.json({
    success: true,
    data: sampleFamilyData,
    total: sampleFamilyData.length
  })
})

// 세대별 조회
app.get('/api/family/generation/:gen', (c) => {
  const generation = parseInt(c.req.param('gen'))
  const filtered = sampleFamilyData.filter(member => member.generation === generation)
  
  return c.json({
    success: true,
    data: filtered,
    total: filtered.length,
    generation: generation
  })
})

// 개별 인물 조회
app.get('/api/family/:personCode', (c) => {
  const personCode = c.req.param('personCode')
  const member = sampleFamilyData.find(m => m.person_code === personCode)
  
  if (!member) {
    return c.json({ success: false, message: '해당 인물을 찾을 수 없습니다.' }, 404)
  }
  
  return c.json({
    success: true,
    data: member
  })
})

// 이름으로 검색
app.get('/api/family/search/:name', (c) => {
  const name = c.req.param('name')
  const filtered = sampleFamilyData.filter(member => 
    member.name.includes(name) || (member.name_hanja && member.name_hanja.includes(name))
  )
  
  return c.json({
    success: true,
    data: filtered,
    total: filtered.length,
    search_term: name
  })
})

// 족보 통계 API
app.get('/api/stats', (c) => {
  const stats = {
    total_members: sampleFamilyData.length,
    generations: Math.max(...sampleFamilyData.map(m => m.generation)),
    living_members: sampleFamilyData.filter(m => !m.is_deceased).length,
    deceased_members: sampleFamilyData.filter(m => m.is_deceased).length,
    males: sampleFamilyData.filter(m => m.gender === 1).length,
    females: sampleFamilyData.filter(m => m.gender === 2).length
  }
  
  return c.json({
    success: true,
    data: stats
  })
})

// 계보 트리 구조 API
app.get('/api/tree/:personCode?', (c) => {
  const rootPersonCode = c.req.param('personCode') || '1'
  
  // 간단한 트리 구조 생성 (실제로는 재귀적으로 구성)
  const buildTree = (personCode: string): any => {
    const person = sampleFamilyData.find(m => m.person_code === personCode)
    if (!person) return null
    
    const children = sampleFamilyData.filter(m => m.parent_code === personCode)
    
    return {
      ...person,
      children: children.map(child => buildTree(child.person_code))
    }
  }
  
  const tree = buildTree(rootPersonCode)
  
  return c.json({
    success: true,
    data: tree
  })
})

// 메인 페이지
app.get('/', (c) => {
  return c.html(`
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>창녕조씨 족보 시스템</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
        <script>
          tailwind.config = {
            theme: {
              extend: {
                colors: {
                  korean: {
                    50: '#fef7f0',
                    100: '#fdeee1', 
                    500: '#d97706',
                    700: '#92400e'
                  }
                }
              }
            }
          }
        </script>
    </head>
    <body class="bg-gray-50 min-h-screen">
        <!-- 헤더 -->
        <header class="bg-korean-700 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-sitemap text-2xl"></i>
                        <div>
                            <h1 class="text-3xl font-bold">창녕조씨 족보</h1>
                            <p class="text-korean-100">昌寧曺氏 家譜 시스템</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm">버전 1.0.0</p>
                        <p class="text-xs text-korean-100">카페24 호스팅</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- 메인 컨텐츠 -->
        <main class="max-w-7xl mx-auto px-4 py-8">
            <!-- 통계 대시보드 -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <i class="fas fa-users text-blue-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">총 인원</p>
                            <p class="text-2xl font-bold text-gray-900" id="total-members">-</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <i class="fas fa-layer-group text-green-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">세대 수</p>
                            <p class="text-2xl font-bold text-gray-900" id="generations">-</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <i class="fas fa-heart text-red-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">생존 인원</p>
                            <p class="text-2xl font-bold text-gray-900" id="living-members">-</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <i class="fas fa-search text-purple-500 text-2xl mr-3"></i>
                        <div>
                            <p class="text-sm text-gray-500">검색 기능</p>
                            <p class="text-sm font-semibold text-gray-900">이름/한자</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 검색 섹션 -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">
                        <i class="fas fa-search mr-2"></i>족보 검색
                    </h2>
                </div>
                <div class="p-6">
                    <div class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-64">
                            <input type="text" 
                                   id="search-input" 
                                   placeholder="성명 또는 한자명으로 검색..." 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-korean-500 focus:border-transparent">
                        </div>
                        <button onclick="searchFamily()" 
                                class="px-6 py-2 bg-korean-600 text-white rounded-lg hover:bg-korean-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>검색
                        </button>
                        <button onclick="showAllGenerations()" 
                                class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-list mr-2"></i>전체보기
                        </button>
                    </div>
                </div>
            </div>

            <!-- 세대별 네비게이션 -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">
                        <i class="fas fa-sitemap mr-2"></i>세대별 조회
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2">
                        <button onclick="showGeneration(1)" class="p-2 text-sm bg-korean-100 hover:bg-korean-200 rounded transition-colors">
                            1세대 (시조)
                        </button>
                        <button onclick="showGeneration(44)" class="p-2 text-sm bg-korean-100 hover:bg-korean-200 rounded transition-colors">
                            44세대
                        </button>
                        <button onclick="showGeneration(45)" class="p-2 text-sm bg-korean-100 hover:bg-korean-200 rounded transition-colors">
                            45세대
                        </button>
                    </div>
                </div>
            </div>

            <!-- 결과 표시 영역 -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900" id="results-title">
                        <i class="fas fa-users mr-2"></i>족보 정보
                    </h2>
                </div>
                <div class="p-6">
                    <div id="results-container">
                        <p class="text-gray-500 text-center py-8">
                            <i class="fas fa-info-circle mr-2"></i>
                            검색하거나 세대를 선택해주세요.
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <!-- 푸터 -->
        <footer class="bg-gray-800 text-white py-6 mt-12">
            <div class="max-w-7xl mx-auto px-4 text-center">
                <p>&copy; 2025 창녕조씨 족보 시스템. Powered by 카페24</p>
                <p class="text-sm text-gray-400 mt-2">
                    문의: <a href="mailto:jo@jou.kr" class="text-korean-300 hover:underline">jo@jou.kr</a>
                </p>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js"></script>
        <script src="/static/app.js"></script>
    </body>
    </html>
  `)
})

export default app