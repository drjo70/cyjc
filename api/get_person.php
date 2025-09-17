<?php
/**
 * 인물 정보 조회 API
 * 
 * @author 닥터조 (주)조유
 * @version 1.0
 * @date 2024-09-17
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../models/Person.php';

try {
    $person_id = $_GET['id'] ?? '';
    
    if (empty($person_id)) {
        throw new Exception('인물 ID가 필요합니다.');
    }
    
    $person = new Person();
    $result = $person->getPersonById($person_id);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'data' => $result['data']
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>