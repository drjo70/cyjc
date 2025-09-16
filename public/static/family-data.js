// 창녕조씨 족보 데이터
// 실제 운영에서는 서버 API나 데이터베이스에서 가져올 데이터

const familyData = [
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
    id: 2,
    person_code: '2',
    parent_code: '1',
    name: '응신',
    name_hanja: '應神',
    gender: 1,
    generation: 2,
    sibling_order: 1,
    child_count: 3,
    birth_date: '0000-00',
    death_date: '0000-00',
    is_deceased: true,
    biography: '거경후손명교출재북오십리허초제유상갈가징별설단입석이동복거민지십여호면개기지십이삼대운자몽일아시조정승여배장자칙필뇌회전풍우총운신라선덕녀왕등',
    biography_hanja: '居京後孫命敎出宰北五十里許草堤有上碣可徵別設壇立石以洞卜居民至十餘戶面皆旣至十二三代云自夢曰我是曺政丞汝輩葬者則必大雷電風雨塚云新羅善德女王登',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:25:00',
    updated_at: '2025-09-13 12:25:00'
  },
  {
    id: 12,
    person_code: '410854',
    parent_code: '400512',
    name: '갑환',
    name_hanja: '甲煥',
    gender: 1,
    generation: 41,
    sibling_order: 1,
    child_count: 3,
    birth_date: '1878-10-01',
    death_date: '1950-08-20',
    is_deceased: true,
    biography: '錫濂三代孫',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:19:54',
    updated_at: '2025-09-13 12:19:54'
  },
  {
    id: 11,
    person_code: '421453',
    parent_code: '410854',
    name: '규서',
    name_hanja: '圭瑞',
    gender: 1,
    generation: 42,
    sibling_order: 1,
    child_count: 2,
    birth_date: '1898-05-25',
    death_date: '1970-12-15',
    is_deceased: true,
    biography: '錫濂四代孫',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:19:48',
    updated_at: '2025-09-13 12:19:48'
  },
  {
    id: 6,
    person_code: '431997',
    parent_code: '421453',
    name: '태현',
    name_hanja: '泰鉉',
    gender: 1,
    generation: 43,
    sibling_order: 2,
    child_count: 3,
    birth_date: '1941-05-06',
    is_deceased: false,
    biography: '錫濂五代孫',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:19:13',
    updated_at: '2025-09-13 12:19:13'
  },
  {
    id: 1,
    person_code: '431998',
    parent_code: '421453',
    name: '광현',
    name_hanja: '光鉉',
    gender: 1,
    generation: 43,
    sibling_order: 3,
    child_count: 3,
    birth_date: '1943-12-04',
    is_deceased: true,
    biography: '錫濂五代孫',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:13:12',
    updated_at: '2025-09-13 12:13:12'
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
    id: 7,
    person_code: '441259',
    parent_code: '431997',
    name: '영순',
    name_hanja: '永順',
    gender: 2,
    generation: 44,
    sibling_order: 2,
    child_count: 0,
    birth_date: '1974-01-12',
    is_deceased: false,
    biography: '錫濂五代孫泰鉉女',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:19:20',
    updated_at: '2025-09-13 12:19:20'
  },
  {
    id: 8,
    person_code: '441260',
    parent_code: '431997',
    name: '은영',
    name_hanja: '恩永',
    gender: 2,
    generation: 44,
    sibling_order: 3,
    child_count: 2,
    birth_date: '1980-01-15',
    is_deceased: false,
    biography: '錫濂五代孫泰鉉女',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:19:27',
    updated_at: '2025-09-13 12:19:27'
  },
  {
    id: 2,
    person_code: '441261',
    parent_code: '431998',
    name: '영석',
    name_hanja: '永鍚',
    gender: 1,
    generation: 44,
    sibling_order: 1,
    child_count: 3,
    birth_date: '1974-03-18',
    is_deceased: true,
    biography: '錫濂五代孫光鉉子 甲寅西紀一九七四年三月十八日生',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:13:20',
    updated_at: '2025-09-13 12:13:20'
  },
  {
    id: 3,
    person_code: '441262',
    parent_code: '431998',
    name: '영미',
    name_hanja: '永美',
    gender: 2,
    generation: 44,
    sibling_order: 2,
    child_count: 0,
    birth_date: '1977-12-07',
    is_deceased: true,
    biography: '丁巳西紀一九七七年十二月七日生',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:13:26',
    updated_at: '2025-09-13 12:13:26'
  },
  {
    id: 4,
    person_code: '441263',
    parent_code: '431998',
    name: '영애',
    name_hanja: '永愛',
    gender: 2,
    generation: 44,
    sibling_order: 3,
    child_count: 0,
    birth_date: '1979-09-30',
    is_deceased: true,
    biography: '己未西紀一九七九년九월三十日生',
    is_adopted: false,
    access_level: 3,
    created_at: '2025-09-13 12:13:33',
    updated_at: '2025-09-13 12:13:33'
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
];

// 통계 데이터 계산 함수
function calculateStats() {
  return {
    total_members: familyData.length,
    generations: Math.max(...familyData.map(m => m.generation)),
    living_members: familyData.filter(m => !m.is_deceased).length,
    deceased_members: familyData.filter(m => m.is_deceased).length,
    males: familyData.filter(m => m.gender === 1).length,
    females: familyData.filter(m => m.gender === 2).length
  };
}

// API 시뮬레이션 함수들
const API = {
  // 전체 족보 데이터 조회
  getFamily: () => {
    return {
      success: true,
      data: familyData,
      total: familyData.length
    };
  },

  // 세대별 조회
  getFamilyByGeneration: (generation) => {
    const filtered = familyData.filter(member => member.generation === generation);
    return {
      success: true,
      data: filtered,
      total: filtered.length,
      generation: generation
    };
  },

  // 개별 인물 조회
  getFamilyMember: (personCode) => {
    const member = familyData.find(m => m.person_code === personCode);
    if (!member) {
      return { success: false, message: '해당 인물을 찾을 수 없습니다.' };
    }
    return {
      success: true,
      data: member
    };
  },

  // 이름으로 검색
  searchFamily: (name) => {
    const filtered = familyData.filter(member => 
      member.name.includes(name) || (member.name_hanja && member.name_hanja.includes(name))
    );
    return {
      success: true,
      data: filtered,
      total: filtered.length,
      search_term: name
    };
  },

  // 통계 조회
  getStats: () => {
    return {
      success: true,
      data: calculateStats()
    };
  },

  // 계보 트리 구조
  getTree: (rootPersonCode = '1') => {
    const buildTree = (personCode) => {
      const person = familyData.find(m => m.person_code === personCode);
      if (!person) return null;
      
      const children = familyData.filter(m => m.parent_code === personCode);
      
      return {
        ...person,
        children: children.map(child => buildTree(child.person_code))
      };
    };
    
    const tree = buildTree(rootPersonCode);
    
    return {
      success: true,
      data: tree
    };
  }
};