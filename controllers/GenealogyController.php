<?php
/**
 * 창녕조씨 족보 시스템 - 메인 컨트롤러
 * 
 * @author 닥터조 (주)조유
 * @version 1.0
 * @date 2024-09-17
 */

require_once 'models/Person.php';
require_once 'models/Family.php';

class GenealogyController {
    
    private $personModel;
    private $familyModel;
    
    public function __construct() {
        $this->personModel = new Person();
        $this->familyModel = new Family();
    }
    
    /**
     * 메인 대시보드 페이지
     */
    public function dashboard() {
        try {
            $stats = $this->personModel->getGenealogyStats();
            
            $data = [
                'page_title' => '창녕조씨 족보 시스템',
                'stats' => $stats['data'] ?? [],
                'error' => !$stats['success'] ? $stats['message'] : null
            ];
            
            $this->render('dashboard', $data);
            
        } catch (Exception $e) {
            $this->handleError('대시보드 로딩 실패: ' . $e->getMessage());
        }
    }
    
    /**
     * 인물 목록 페이지
     */
    public function personList() {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $search = $_GET['search'] ?? '';
            $generation = $_GET['generation'] ?? '';
            
            if (!empty($search)) {
                $result = $this->personModel->searchPersonsByName($search, 50);
                $data = [
                    'page_title' => '인물 검색 결과',
                    'persons' => $result['data'] ?? [],
                    'search_term' => $search,
                    'is_search' => true,
                    'error' => !$result['success'] ? $result['message'] : null
                ];
            } else if (!empty($generation)) {
                $result = $this->personModel->getPersonsByGeneration($generation);
                $data = [
                    'page_title' => $generation . '세대 인물 목록',
                    'persons' => $result['data'] ?? [],
                    'generation' => $generation,
                    'is_generation' => true,
                    'error' => !$result['success'] ? $result['message'] : null
                ];
            } else {
                $result = $this->personModel->getAllPersons($page, $limit);
                $data = [
                    'page_title' => '전체 인물 목록',
                    'persons' => $result['data'] ?? [],
                    'pagination' => $result['pagination'] ?? [],
                    'is_list' => true,
                    'error' => !$result['success'] ? $result['message'] : null
                ];
            }
            
            $this->render('person_list', $data);
            
        } catch (Exception $e) {
            $this->handleError('인물 목록 로딩 실패: ' . $e->getMessage());
        }
    }
    
    /**
     * 인물 상세 페이지
     */
    public function personDetail() {
        try {
            $id = $_GET['id'] ?? '';
            $person_code = $_GET['code'] ?? '';
            
            if (empty($id) && empty($person_code)) {
                throw new Exception('인물 정보가 필요합니다.');
            }
            
            if (!empty($id)) {
                $result = $this->personModel->getPersonById($id);
            } else {
                $result = $this->personModel->getPersonByCode($person_code);
                if ($result['success']) {
                    // 가족 관계도도 함께 가져오기
                    $familyTree = $this->familyModel->getFamilyTree($person_code);
                    $result['data'] = [
                        'person' => $result['data'],
                        'family' => $familyTree['data'] ?? []
                    ];
                }
            }
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $data = [
                'page_title' => $result['data']['person']['name'] . ' 상세 정보',
                'person' => $result['data']['person'],
                'family' => $result['data']['family'] ?? [],
                'error' => null
            ];
            
            $this->render('person_detail', $data);
            
        } catch (Exception $e) {
            $this->handleError('인물 정보 로딩 실패: ' . $e->getMessage());
        }
    }
    
    /**
     * 가족 관계도 페이지
     */
    public function familyTree() {
        try {
            $person_code = $_GET['code'] ?? '';
            $depth = $_GET['depth'] ?? 2;
            
            if (empty($person_code)) {
                throw new Exception('인물 코드가 필요합니다.');
            }
            
            $result = $this->familyModel->getFamilyTree($person_code, $depth);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $data = [
                'page_title' => $result['data']['self']['name'] . ' 가족관계도',
                'tree' => $result['data'],
                'person_code' => $person_code,
                'depth' => $depth,
                'error' => null
            ];
            
            $this->render('family_tree', $data);
            
        } catch (Exception $e) {
            $this->handleError('가족관계도 로딩 실패: ' . $e->getMessage());
        }
    }
    
    /**
     * 세대별 족보 페이지
     */
    public function generationView() {
        try {
            $start_gen = $_GET['start'] ?? 1;
            $end_gen = $_GET['end'] ?? 10;
            
            // 최대 10세대까지만 한 번에 보여주기
            if (($end_gen - $start_gen) > 10) {
                $end_gen = $start_gen + 10;
            }
            
            $result = $this->familyModel->getGenerationStructure($start_gen, $end_gen);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $data = [
                'page_title' => $start_gen . '~' . $end_gen . '세대 족보',
                'generations' => $result['data'],
                'start_generation' => $start_gen,
                'end_generation' => $end_gen,
                'error' => null
            ];
            
            $this->render('generation_view', $data);
            
        } catch (Exception $e) {
            $this->handleError('세대별 족보 로딩 실패: ' . $e->getMessage());
        }
    }
    
    /**
     * 혈통 추적 페이지
     */
    public function lineage() {
        try {
            $person_code = $_GET['code'] ?? '';
            
            if (empty($person_code)) {
                throw new Exception('인물 코드가 필요합니다.');
            }
            
            $result = $this->familyModel->getLineage($person_code);
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $data = [
                'page_title' => '혈통 추적 - ' . end($result['data'])['name'],
                'lineage' => $result['data'],
                'depth' => $result['depth'],
                'person_code' => $person_code,
                'error' => null
            ];
            
            $this->render('lineage', $data);
            
        } catch (Exception $e) {
            $this->handleError('혈통 추적 실패: ' . $e->getMessage());
        }
    }
    
    /**
     * API 응답 (JSON)
     */
    public function api() {
        try {
            $action = $_GET['action'] ?? '';
            $result = ['success' => false, 'message' => '잘못된 요청입니다.'];
            
            switch ($action) {
                case 'search':
                    $name = $_GET['name'] ?? '';
                    if (!empty($name)) {
                        $result = $this->personModel->searchPersonsByName($name, 10);
                    }
                    break;
                    
                case 'stats':
                    $result = $this->personModel->getGenealogyStats();
                    break;
                    
                case 'family':
                    $person_code = $_GET['code'] ?? '';
                    if (!empty($person_code)) {
                        $result = $this->familyModel->getFamilyTree($person_code);
                    }
                    break;
                    
                case 'relationship':
                    $code1 = $_GET['code1'] ?? '';
                    $code2 = $_GET['code2'] ?? '';
                    if (!empty($code1) && !empty($code2)) {
                        $result = $this->familyModel->getRelationship($code1, $code2);
                    }
                    break;
                    
                default:
                    $result['message'] = '지원하지 않는 API 액션입니다.';
            }
            
            $this->jsonResponse($result);
            
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'API 오류: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 뷰 렌더링
     */
    private function render($view, $data = []) {
        // 데이터를 변수로 추출
        extract($data);
        
        // 뷰 파일 경로
        $viewFile = 'views/' . $view . '.php';
        
        if (!file_exists($viewFile)) {
            throw new Exception('뷰 파일을 찾을 수 없습니다: ' . $view);
        }
        
        // 헤더 포함
        include 'views/header.php';
        
        // 메인 뷰 포함
        include $viewFile;
        
        // 푸터 포함
        include 'views/footer.php';
    }
    
    /**
     * JSON 응답
     */
    private function jsonResponse($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 오류 처리
     */
    private function handleError($message) {
        $data = [
            'page_title' => '오류 발생',
            'error_message' => $message
        ];
        
        $this->render('error', $data);
    }
}
?>