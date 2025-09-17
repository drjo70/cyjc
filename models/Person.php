<?php
/**
 * 창녕조씨 족보 시스템 - 인물 모델 클래스
 * 실제 DB 테이블 구조에 맞춘 버전
 * 
 * @author 닥터조 (주)조유
 * @version 2.0 
 * @date 2024-09-17
 */

require_once 'config/database.php';

class Person {
    
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * 모든 인물 목록 조회 (페이징 지원)
     */
    public function getAllPersons($page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            
            $stmt = $this->db->prepare("
                SELECT 
                    id, person_code, parent_code, name, name_hanja, 
                    gender, generation, sibling_order, child_count,
                    birth_date, death_date
                FROM family_members 
                ORDER BY generation ASC, sibling_order ASC, id ASC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $persons = $stmt->fetchAll();
            
            // 전체 개수도 함께 반환
            $countStmt = $this->db->query("SELECT COUNT(*) as total FROM family_members");
            $total = $countStmt->fetch()['total'];
            
            return [
                'success' => true,
                'data' => $persons,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '인물 목록 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 특정 인물 상세 정보 조회 (ID로)
     */
    public function getPersonById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM family_members WHERE id = ?
            ");
            $stmt->execute([$id]);
            $person = $stmt->fetch();
            
            if (!$person) {
                return [
                    'success' => false,
                    'message' => '해당 인물을 찾을 수 없습니다.'
                ];
            }
            
            // 가족 관계 정보도 함께 조회
            $family = $this->getFamilyRelations($person['person_code']);
            
            return [
                'success' => true,
                'data' => [
                    'person' => $person,
                    'family' => $family
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '인물 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 인물 코드로 검색
     */
    public function getPersonByCode($person_code) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM family_members WHERE person_code = ?
            ");
            $stmt->execute([$person_code]);
            $person = $stmt->fetch();
            
            if (!$person) {
                return [
                    'success' => false,
                    'message' => '해당 인물을 찾을 수 없습니다.'
                ];
            }
            
            return [
                'success' => true,
                'data' => $person
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '인물 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 이름으로 인물 검색
     */
    public function searchPersonsByName($name, $limit = 10) {
        try {
            $searchTerm = "%$name%";
            
            $stmt = $this->db->prepare("
                SELECT 
                    id, person_code, parent_code, name, name_hanja, 
                    gender, generation, birth_date, death_date, child_count
                FROM family_members 
                WHERE name LIKE ? OR name_hanja LIKE ?
                ORDER BY generation ASC, name ASC
                LIMIT ?
            ");
            
            $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '검색 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 세대별 인물 조회
     */
    public function getPersonsByGeneration($generation) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id, person_code, parent_code, name, name_hanja, 
                    gender, sibling_order, child_count, birth_date, death_date
                FROM family_members 
                WHERE generation = ?
                ORDER BY sibling_order ASC, id ASC
            ");
            $stmt->execute([$generation]);
            
            $persons = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $persons,
                'count' => count($persons)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '세대별 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 가족 관계 정보 조회
     */
    private function getFamilyRelations($person_code) {
        try {
            $relations = [];
            
            // 현재 인물 정보
            $currentStmt = $this->db->prepare("SELECT * FROM family_members WHERE person_code = ?");
            $currentStmt->execute([$person_code]);
            $current = $currentStmt->fetch();
            
            if ($current && $current['parent_code']) {
                // 부모 정보
                $parentStmt = $this->db->prepare("
                    SELECT name, name_hanja, generation FROM family_members 
                    WHERE person_code = ?
                ");
                $parentStmt->execute([$current['parent_code']]);
                $relations['parent'] = $parentStmt->fetch();
                
                // 형제자매들 (같은 부모를 가진 사람들)
                $siblingsStmt = $this->db->prepare("
                    SELECT person_code, name, name_hanja, gender, sibling_order, birth_date 
                    FROM family_members 
                    WHERE parent_code = ? AND person_code != ?
                    ORDER BY sibling_order ASC
                ");
                $siblingsStmt->execute([$current['parent_code'], $person_code]);
                $relations['siblings'] = $siblingsStmt->fetchAll();
            }
            
            // 자녀들 (이 사람을 부모로 하는 사람들)
            $childrenStmt = $this->db->prepare("
                SELECT person_code, name, name_hanja, gender, generation, birth_date
                FROM family_members 
                WHERE parent_code = ?
                ORDER BY sibling_order ASC
            ");
            $childrenStmt->execute([$person_code]);
            $relations['children'] = $childrenStmt->fetchAll();
            
            return $relations;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * 족보 통계 정보
     */
    public function getGenealogyStats() {
        try {
            $stats = [];
            
            // 전체 인물 수
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM family_members");
            $stats['total_persons'] = $stmt->fetch()['total'];
            
            // 생존 인물 수 (death_date가 NULL이거나 비어있는 경우)
            $stmt = $this->db->query("
                SELECT COUNT(*) as alive 
                FROM family_members 
                WHERE death_date IS NULL OR death_date = '' OR death_date = '0000-00'
            ");
            $stats['alive_persons'] = $stmt->fetch()['alive'];
            
            // 성별 통계
            $stmt = $this->db->query("
                SELECT gender, COUNT(*) as count 
                FROM family_members 
                GROUP BY gender
            ");
            $stats['by_gender'] = $stmt->fetchAll();
            
            // 세대별 통계
            $stmt = $this->db->query("
                SELECT generation, COUNT(*) as count 
                FROM family_members 
                GROUP BY generation 
                ORDER BY generation
            ");
            $stats['by_generation'] = $stmt->fetchAll();
            
            // 최신 등록 인물 (ID 기준 최근 5명)
            $stmt = $this->db->query("
                SELECT name, name_hanja, generation 
                FROM family_members 
                ORDER BY id DESC 
                LIMIT 5
            ");
            $stats['recent_persons'] = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '통계 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 시조 조회 (1세대)
     */
    public function getFounder() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM family_members 
                WHERE generation = 1 
                ORDER BY id ASC 
                LIMIT 1
            ");
            $stmt->execute();
            
            return [
                'success' => true,
                'data' => $stmt->fetch()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '시조 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 특정 세대의 전체 인구 수 조회
     */
    public function getGenerationCount($generation) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM family_members 
                WHERE generation = ?
            ");
            $stmt->execute([$generation]);
            
            return $stmt->fetch()['count'];
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * 활성 세대 범위 조회 (최소~최대 세대)
     */
    public function getGenerationRange() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    MIN(generation) as min_gen, 
                    MAX(generation) as max_gen 
                FROM family_members
            ");
            
            return [
                'success' => true,
                'data' => $stmt->fetch()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '세대 범위 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 고급 복합 검색
     */
    public function advancedSearch($params = []) {
        try {
            $conditions = [];
            $bindings = [];
            
            // 검색어 조건
            if (!empty($params['query'])) {
                $searchTerm = "%" . $params['query'] . "%";
                
                switch ($params['search_type'] ?? 'all') {
                    case 'name':
                        $conditions[] = "(name LIKE ? OR name_hanja LIKE ?)";
                        $bindings[] = $searchTerm;
                        $bindings[] = $searchTerm;
                        break;
                        
                    case 'hanja_only':
                        $conditions[] = "name_hanja LIKE ?";
                        $bindings[] = $searchTerm;
                        break;
                        
                    case 'korean_only':
                        $conditions[] = "name LIKE ?";
                        $bindings[] = $searchTerm;
                        break;
                        
                    case 'person_code':
                        $conditions[] = "person_code LIKE ?";
                        $bindings[] = $searchTerm;
                        break;
                        
                    default: // 'all'
                        $conditions[] = "(name LIKE ? OR name_hanja LIKE ? OR person_code LIKE ?)";
                        $bindings[] = $searchTerm;
                        $bindings[] = $searchTerm;
                        $bindings[] = $searchTerm;
                        break;
                }
            }
            
            // 세대 필터
            if (!empty($params['generation'])) {
                $conditions[] = "generation = ?";
                $bindings[] = $params['generation'];
            }
            
            // 성별 필터
            if (!empty($params['gender'])) {
                $conditions[] = "gender = ?";
                $bindings[] = $params['gender'];
            }
            
            // 생존 상태 필터
            if (!empty($params['alive_status'])) {
                if ($params['alive_status'] === 'alive') {
                    $conditions[] = "(death_date IS NULL OR death_date = '' OR death_date = '0000-00-00')";
                } else if ($params['alive_status'] === 'deceased') {
                    $conditions[] = "(death_date IS NOT NULL AND death_date != '' AND death_date != '0000-00-00')";
                }
            }
            
            // 자녀 수 조건
            if (!empty($params['has_children'])) {
                if ($params['has_children'] === 'yes') {
                    $conditions[] = "child_count > 0";
                } else if ($params['has_children'] === 'no') {
                    $conditions[] = "(child_count = 0 OR child_count IS NULL)";
                }
            }
            
            // WHERE 절 구성
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // 정렬 옵션
            $orderBy = "ORDER BY generation ASC, sibling_order ASC, name ASC";
            if (!empty($params['sort_by'])) {
                switch ($params['sort_by']) {
                    case 'name':
                        $orderBy = "ORDER BY name ASC, generation ASC";
                        break;
                    case 'generation_desc':
                        $orderBy = "ORDER BY generation DESC, sibling_order ASC";
                        break;
                    case 'birth_date':
                        $orderBy = "ORDER BY birth_date ASC, generation ASC";
                        break;
                }
            }
            
            // 제한 설정
            $limit = intval($params['limit'] ?? 100);
            $limit = min($limit, 500); // 최대 500개로 제한
            
            $sql = "
                SELECT 
                    id, person_code, parent_code, name, name_hanja, 
                    gender, generation, sibling_order, child_count,
                    birth_date, death_date
                FROM family_members 
                $whereClause
                $orderBy
                LIMIT $limit
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            
            $results = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $results,
                'count' => count($results),
                'query_info' => $params
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '고급 검색 실패: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * 실시간 검색 제안 (자동완성용)
     */
    public function getSearchSuggestions($query, $limit = 10) {
        try {
            if (empty($query) || strlen($query) < 2) {
                return ['success' => true, 'data' => []];
            }
            
            $searchTerm = "%" . $query . "%";
            
            $stmt = $this->db->prepare("
                SELECT DISTINCT
                    name, name_hanja, generation, 
                    COUNT(*) as name_count
                FROM family_members 
                WHERE name LIKE ? OR name_hanja LIKE ?
                GROUP BY name, name_hanja, generation
                ORDER BY name_count DESC, generation ASC
                LIMIT ?
            ");
            
            $stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(2, $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $suggestions = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $suggestions
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '검색 제안 실패: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * 검색 통계 (인기 검색어 등)
     */
    public function getSearchStats() {
        try {
            $stats = [];
            
            // 가장 많이 나타나는 이름들
            $nameStmt = $this->db->query("
                SELECT name, COUNT(*) as count 
                FROM family_members 
                WHERE name IS NOT NULL AND name != ''
                GROUP BY name 
                ORDER BY count DESC 
                LIMIT 10
            ");
            $stats['popular_names'] = $nameStmt->fetchAll();
            
            // 세대별 인구 밀도
            $densityStmt = $this->db->query("
                SELECT generation, COUNT(*) as count
                FROM family_members 
                GROUP BY generation 
                ORDER BY count DESC 
                LIMIT 5
            ");
            $stats['dense_generations'] = $densityStmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '검색 통계 실패: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }
}
?>