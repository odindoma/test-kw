<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

/**
 * API для получения деталей конкретной кампании
 */

try {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Метод не поддерживается');
    }
    
    $campaignId = intval($_GET['id'] ?? 0);
    
    if ($campaignId <= 0) {
        throw new Exception('Некорректный ID кампании');
    }
    
    $db = Database::getInstance();
    
    // Получаем детали кампании
    $campaign = $db->fetchOne(
        "SELECT * FROM campaigns WHERE id = :id",
        ['id' => $campaignId]
    );
    
    if (!$campaign) {
        throw new Exception('Кампания не найдена');
    }
    
    // Получаем похожие кампании по хешу медиа
    $similarByMedia = [];
    if (!empty($campaign['ad_media_hash'])) {
        $similarByMedia = $db->fetchAll(
            "SELECT id, advertiser, page_id, ad_title, first_shown_at 
             FROM campaigns 
             WHERE ad_media_hash = :hash AND id != :id 
             ORDER BY first_shown_at DESC 
             LIMIT 10",
            [
                'hash' => $campaign['ad_media_hash'],
                'id' => $campaignId
            ]
        );
    }
    
    // Получаем похожие кампании по тексту (простое сравнение)
    $similarByText = [];
    if (!empty($campaign['ad_title'])) {
        $titleWords = explode(' ', $campaign['ad_title']);
        if (count($titleWords) >= 3) {
            $searchPattern = '%' . implode('%', array_slice($titleWords, 0, 3)) . '%';
            $similarByText = $db->fetchAll(
                "SELECT id, advertiser, page_id, ad_title, first_shown_at 
                 FROM campaigns 
                 WHERE ad_title LIKE :pattern AND id != :id 
                 ORDER BY first_shown_at DESC 
                 LIMIT 10",
                [
                    'pattern' => $searchPattern,
                    'id' => $campaignId
                ]
            );
        }
    }
    
    // Получаем статистику по странице
    $pageStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_campaigns,
            COUNT(DISTINCT ad_id) as unique_ads,
            COUNT(DISTINCT ad_media_hash) as unique_creatives,
            MIN(first_shown_at) as first_campaign,
            MAX(last_shown_at) as last_campaign
         FROM campaigns 
         WHERE page_id = :page_id AND advertiser = :advertiser",
        [
            'page_id' => $campaign['page_id'],
            'advertiser' => $campaign['advertiser']
        ]
    );
    
    echo json_encode([
        'success' => true,
        'campaign' => $campaign,
        'similar_by_media' => $similarByMedia,
        'similar_by_text' => $similarByText,
        'page_stats' => $pageStats
    ]);
    
} catch (Exception $e) {
    error_log('Campaign details error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

