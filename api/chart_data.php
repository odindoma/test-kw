<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/CampaignAnalyzer.php';

/**
 * API для получения данных для графиков и диаграмм
 */

try {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Метод не поддерживается');
    }
    
    $type = $_GET['type'] ?? '';
    $analyzer = new CampaignAnalyzer();
    
    // Получаем фильтры из параметров запроса
    $filters = [
        'advertiser' => $_GET['advertiser'] ?? '',
        'page_id' => $_GET['page_id'] ?? '',
        'target_url_domain' => $_GET['target_url_domain'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'activity_status' => $_GET['activity_status'] ?? ''
    ];
    
    // Удаляем пустые фильтры
    $filters = array_filter($filters);
    
    switch ($type) {
        case 'campaign_growth':
            $data = $analyzer->getCampaignGrowthData($filters);
            break;
            
        case 'creative_uniqueness':
            $data = $analyzer->getCreativeUniquenessStats($filters);
            break;
            
        case 'page_activity':
            $data = $analyzer->getPageActivityStats($filters);
            break;
            
        case 'advertiser_stats':
            $data = getAdvertiserStats($filters);
            break;
            
        case 'daily_summary':
            $data = getDailySummary($filters);
            break;
            
        case 'top_pages':
            $data = getTopPages($filters);
            break;
            
        case 'media_hash_duplicates':
            $data = getMediaHashDuplicates($filters);
            break;
            
        default:
            throw new Exception('Неизвестный тип данных: ' . $type);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'filters_applied' => $filters
    ]);
    
} catch (Exception $e) {
    error_log('Chart data error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Получить статистику по рекламодателям
 */
function getAdvertiserStats($filters = []) {
    $db = Database::getInstance();
    
    $where = [];
    $params = [];
    
    if (!empty($filters['date_from'])) {
        $where[] = "first_shown_at >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "last_shown_at <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT 
                advertiser,
                COUNT(*) as total_campaigns,
                COUNT(DISTINCT page_id) as unique_pages,
                COUNT(DISTINCT ad_id) as unique_ads,
                COUNT(DISTINCT ad_media_hash) as unique_creatives,
                MIN(first_shown_at) as first_campaign,
                MAX(last_shown_at) as last_campaign
            FROM campaigns 
            $whereClause
            GROUP BY advertiser 
            ORDER BY total_campaigns DESC";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Получить ежедневную сводку
 */
function getDailySummary($filters = []) {
    $db = Database::getInstance();
    
    $where = [];
    $params = [];
    
    if (!empty($filters['advertiser'])) {
        $where[] = "advertiser = :advertiser";
        $params['advertiser'] = $filters['advertiser'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "date >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "date <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT 
                date,
                SUM(new_campaigns) as total_new_campaigns,
                SUM(total_active_campaigns) as total_active_campaigns,
                SUM(unique_creatives) as total_unique_creatives,
                COUNT(DISTINCT advertiser) as active_advertisers
            FROM daily_campaign_stats 
            $whereClause
            GROUP BY date 
            ORDER BY date DESC 
            LIMIT 30";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Получить топ страниц по активности
 */
function getTopPages($filters = []) {
    $db = Database::getInstance();
    
    $where = [];
    $params = [];
    
    if (!empty($filters['advertiser'])) {
        $where[] = "advertiser = :advertiser";
        $params['advertiser'] = $filters['advertiser'];
    }
    
    if (!empty($filters['activity_status'])) {
        $where[] = "activity_status = :activity_status";
        $params['activity_status'] = $filters['activity_status'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT 
                page_id,
                advertiser,
                total_campaigns,
                days_active,
                activity_status,
                first_campaign_date,
                last_campaign_date
            FROM page_activity 
            $whereClause
            ORDER BY total_campaigns DESC 
            LIMIT 20";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Получить дубликаты по хешу медиа
 */
function getMediaHashDuplicates($filters = []) {
    $db = Database::getInstance();
    
    $where = ['ad_media_hash IS NOT NULL', "ad_media_hash != ''"];
    $params = [];
    
    if (!empty($filters['advertiser'])) {
        $where[] = "advertiser = :advertiser";
        $params['advertiser'] = $filters['advertiser'];
    }
    
    if (!empty($filters['page_id'])) {
        $where[] = "page_id = :page_id";
        $params['page_id'] = $filters['page_id'];
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    $sql = "SELECT 
                ad_media_hash,
                COUNT(*) as usage_count,
                COUNT(DISTINCT advertiser) as advertisers_count,
                COUNT(DISTINCT page_id) as pages_count,
                COUNT(DISTINCT ad_title) as unique_titles,
                MIN(first_shown_at) as first_used,
                MAX(last_shown_at) as last_used,
                GROUP_CONCAT(DISTINCT advertiser LIMIT 5) as sample_advertisers
            FROM campaigns 
            $whereClause
            GROUP BY ad_media_hash 
            HAVING usage_count > 1
            ORDER BY usage_count DESC 
            LIMIT 50";
    
    return $db->fetchAll($sql, $params);
}
?>

