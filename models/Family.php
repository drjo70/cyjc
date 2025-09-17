<?php
/**
 * 창녕조씨 족보 시스템 - 가족 관계 모델 클래스
 * 
 * @author 닥터조 (주)조유
 * @version 1.0
 * @date 2024-09-17
 */

require_once 'config/database.php';

class Family {
    
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * 특정 인물의 가족 관계도 조회
     */
    public function getFamilyTree($person_code, $depth = 2) {
        try {
            $tree = [];
            
            // 본인 정보
            $personStmt = $this->db->prepare("SELECT * FROM family_members WHERE person_code = ?");
            $personStmt->execute([$person_code]);
            $tree['self'] = $personStmt->fetch();
            
            if (!$tree['self']) {
                return [
                    'success' => false,
                    'message' => '해당 인물을 찾을 수 없습니다.'
                ];
            }
            
            // 부모 정보
            if ($tree['self']['father_code']) {
                $tree['father'] = $this->getPersonByCode($tree['self']['father_code']);
                
                // 할아버지 정보 (depth > 1일 때)
                if ($depth > 1 && $tree['father']['father_code']) {
                    $tree['grandfather'] = $this->getPersonByCode($tree['father']['father_code']);
                }
            }
            
            // 배우자들
            $tree['spouses'] = $this->getSpouses($person_code);
            
            // 자녀들
            $tree['children'] = $this->getChildren($person_code);
            
            // 형제자매들 (같은 아버지를 가진 사람들)
            if ($tree['self']['father_code']) {
                $tree['siblings'] = $this->getSiblings($person_code, $tree['self']['father_code']);
            }
            
            return [
                'success' => true,
                'data' => $tree
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '가족관계도 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 세대별 족보 구조 조회
     */
    public function getGenerationStructure($start_generation = 1, $end_generation = 45) {
        try {
            $structure = [];
            
            for ($gen = $start_generation; $gen <= $end_generation; $gen++) {
                $stmt = $this->db->prepare("
                    SELECT 
                        person_code, name, name_hanja, 
                        birth_date, death_date, father_code, is_alive
                    FROM family_members 
                    WHERE generation = ?
                    ORDER BY person_code ASC
                ");
                $stmt->execute([$gen]);
                $family_members = $stmt->fetchAll();
                
                if (!empty($family_members)) {
                    $structure[$gen] = $family_members;
                }
            }
            
            return [
                'success' => true,
                'data' => $structure,
                'generation_range' => [$start_generation, $end_generation]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '세대별 구조 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 혈통 추적 (특정 인물부터 시조까지)
     */
    public function getLineage($person_code) {
        try {
            $lineage = [];
            $current_code = $person_code;
            $max_depth = 50; // 무한루프 방지
            $depth = 0;
            
            while ($current_code && $depth < $max_depth) {
                $person = $this->getPersonByCode($current_code);
                
                if (!$person) break;
                
                $lineage[] = [
                    'generation' => $person['generation'],
                    'person_code' => $person['person_code'],
                    'name' => $person['name'],
                    'name_hanja' => $person['name_hanja'],
                    'birth_date' => $person['birth_date'],
                    'death_date' => $person['death_date']
                ];
                
                $current_code = $person['father_code'];
                $depth++;
            }
            
            // 시조부터 본인까지 순서로 정렬
            $lineage = array_reverse($lineage);
            
            return [
                'success' => true,
                'data' => $lineage,
                'depth' => count($lineage)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '혈통 추적 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 후손 조회 (특정 인물의 모든 후손들)
     */
    public function getDescendants($person_code, $max_depth = 5) {
        try {
            $descendants = [];
            $this->findDescendantsRecursive($person_code, $descendants, 1, $max_depth);
            
            return [
                'success' => true,
                'data' => $descendants,
                'total_descendants' => count($descendants)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '후손 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 재귀적으로 후손 찾기 (내부 함수)
     */
    private function findDescendantsRecursive($person_code, &$descendants, $current_depth, $max_depth) {
        if ($current_depth > $max_depth) return;
        
        $stmt = $this->db->prepare("
            SELECT * FROM family_members WHERE father_code = ?
        ");
        $stmt->execute([$person_code]);
        $children = $stmt->fetchAll();
        
        foreach ($children as $child) {
            $child['depth'] = $current_depth;
            $descendants[] = $child;
            
            // 재귀적으로 그 자녀의 후손들도 찾기
            $this->findDescendantsRecursive($child['person_code'], $descendants, $current_depth + 1, $max_depth);
        }
    }
    
    /**
     * 배우자 정보 조회
     */
    private function getSpouses($person_code) {
        $stmt = $this->db->prepare("SELECT * FROM spouses WHERE person_code = ? ORDER BY marriage_order ASC");
        $stmt->execute([$person_code]);
        return $stmt->fetchAll();
    }
    
    /**
     * 자녀 정보 조회
     */
    private function getChildren($person_code) {
        $stmt = $this->db->prepare("
            SELECT c.*, p.name, p.name_hanja, p.generation 
            FROM children c
            LEFT JOIN family_members p ON c.child_code = p.person_code
            WHERE c.parent_code = ?
            ORDER BY c.birth_order ASC
        ");
        $stmt->execute([$person_code]);
        return $stmt->fetchAll();
    }
    
    /**
     * 형제자매 정보 조회
     */
    private function getSiblings($person_code, $father_code) {
        $stmt = $this->db->prepare("
            SELECT * FROM family_members 
            WHERE father_code = ? AND person_code != ?
            ORDER BY person_code ASC
        ");
        $stmt->execute([$father_code, $person_code]);
        return $stmt->fetchAll();
    }
    
    /**
     * 인물 코드로 기본 정보 조회
     */
    private function getPersonByCode($person_code) {
        $stmt = $this->db->prepare("SELECT * FROM family_members WHERE person_code = ?");
        $stmt->execute([$person_code]);
        return $stmt->fetch();
    }
    
    /**
     * 족보 관계 검증 (두 인물 간의 관계)
     */
    public function getRelationship($person_code1, $person_code2) {
        try {
            // 두 인물의 혈통을 각각 추적
            $lineage1 = $this->getLineage($person_code1);
            $lineage2 = $this->getLineage($person_code2);
            
            if (!$lineage1['success'] || !$lineage2['success']) {
                return [
                    'success' => false,
                    'message' => '혈통 정보를 찾을 수 없습니다.'
                ];
            }
            
            $line1 = $lineage1['data'];
            $line2 = $lineage2['data'];
            
            // 공통 조상 찾기
            $common_ancestor = null;
            foreach ($line1 as $ancestor1) {
                foreach ($line2 as $ancestor2) {
                    if ($ancestor1['person_code'] === $ancestor2['person_code']) {
                        $common_ancestor = $ancestor1;
                        break 2;
                    }
                }
            }
            
            if (!$common_ancestor) {
                return [
                    'success' => true,
                    'relationship' => '혈족 관계 없음',
                    'common_ancestor' => null
                ];
            }
            
            // 세대 차이 계산
            $person1 = end($line1);
            $person2 = end($line2);
            $gen_diff = abs($person1['generation'] - $person2['generation']);
            
            // 관계 결정
            $relationship = $this->determineRelationship($person1, $person2, $common_ancestor, $gen_diff);
            
            return [
                'success' => true,
                'relationship' => $relationship,
                'common_ancestor' => $common_ancestor,
                'generation_difference' => $gen_diff
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '관계 분석 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 관계 결정 로직
     */
    private function determineRelationship($person1, $person2, $common_ancestor, $gen_diff) {
        // 동일 인물
        if ($person1['person_code'] === $person2['person_code']) {
            return '본인';
        }
        
        // 직계 관계 (부자관계)
        if ($gen_diff === 1) {
            return ($person1['generation'] > $person2['generation']) ? '부자' : '자부';
        }
        
        // 조손 관계
        if ($gen_diff === 2) {
            return ($person1['generation'] > $person2['generation']) ? '조손' : '손조';
        }
        
        // 같은 세대 (형제, 사촌 등)
        if ($gen_diff === 0) {
            if ($person1['father_code'] === $person2['father_code']) {
                return '형제';
            }
            return '사촌';
        }
        
        // 기타
        return $gen_diff . '세대 차이';
    }
}
?>