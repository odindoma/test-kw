<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Основной класс для анализа рекламных кампаний
 */
class CampaignAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Загрузить данные из CSV файла - ИСПРАВЛЕННАЯ ВЕРСИЯ
     */
    public function importCsvFile($filePath) {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'Файл не найден: ' . $filePath
            ];
        }
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [
                'success' => false,
                'message' => 'Не удалось открыть файл: ' . $filePath
            ];
        }
        
        // Читаем заголовки
        $headers = fgetcsv($handle, 0, "\t");
        if (!$headers) {
            fclose($handle);
            return [
                'success' => false,
                'message' => 'Не удалось прочитать заголовки CSV файла'
            ];
        }
        
        $importedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $totalCount = 0;
        $errors = [];
        
        $this->db->beginTransaction();
        
        try {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                $totalCount++;
                
                try {
                    if (count($data) >= count($headers)) {
                        $row = array_combine($headers, $data);
                        
                        // Проверяем на дубликаты перед вставкой
                        if ($this->isDuplicateCampaign($row)) {
                            $skippedCount++;
                            continue;
                        }
                        
                        $this->insertCampaignData($row);
                        $importedCount++;
                    } else {
                        $skippedCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $errors[] = "Строка " . $totalCount . ": " . $e->getMessage();
                    
                    // Прерываем импорт если слишком много ошибок
                    if ($errorCount > 100) {
                        throw new Exception('Слишком много ошибок при импорте. Процесс остановлен.');
                    }
                }
            }
            
            $this->db->commit();
            
            // Обновляем статистику после импорта
            if ($importedCount > 0) {
                $this->updatePageStatistics();
                $this->updateDailyStats();
            }
            
        } catch (Exception $e) {
            $this->db->rollback();
            fclose($handle);
            return [
                'success' => false,
                'message' => 'Ошибка при импорте: ' . $e->getMessage()
            ];
        } finally {
            fclose($handle);
        }
        
        // Формируем сообщение о результате
        $message = "Импорт завершен: ";
        $message .= "добавлено {$importedCount} новых записей";
        
        if ($skippedCount > 0) {
            $message .= ", пропущено {$skippedCount} дубликатов";
        }
        
        if ($errorCount > 0) {
            $message .= ", ошибок: {$errorCount}";
        }
        
        return [
            'success' => true,
            'message' => $message,
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'error_count' => $errorCount,
            'total_count' => $totalCount,
            'errors' => $errors
        ];
    }
    
    /**
     * Проверить, является ли кампания дубликатом - НОВАЯ ФУНКЦИЯ
     */
    private function isDuplicateCampaign($row) {
        // Разделяем Resource ID на Page ID и Ad ID
        $resourceId = $row['Resource ID'] ?? '';
        $parts = explode('/', $resourceId);
        $pageId = $parts[0] ?? '';
        $adId = $parts[1] ?? '';
        
        // Получаем ключевые поля для проверки дубликатов
        $advertiser = $row['Advertiser'] ?? '';
        $campaignName = $row['Campaign'] ?? '';
        $adTitle = $row['Ad Title'] ?? '';
        $adDescription = $row['Ad Description'] ?? '';
        $adMediaHash = $row['Ad Media Hash'] ?? '';
        $targetUrl = $row['Target URL'] ?? '';
        $firstShownAt = $this->parseDateTime($row['First Shown At'] ?? '');
        
        // Проверяем по уникальной комбинации полей
        $sql = "SELECT COUNT(*) as count FROM campaigns WHERE 
                advertiser = :advertiser 
                AND resource_id = :resource_id 
                AND campaign_name = :campaign_name 
                AND ad_title = :ad_title 
                AND ad_description = :ad_description 
                AND target_url = :target_url
                AND first_shown_at = :first_shown_at";
        
        $params = [
            'advertiser' => $advertiser,
            'resource_id' => $resourceId,
            'campaign_name' => $campaignName,
            'ad_title' => $adTitle,
            'ad_description' => $adDescription,
            'target_url' => $targetUrl,
            'first_shown_at' => $firstShownAt
        ];
        
        $result = $this->db->fetchOne($sql, $params);
        
        return $result['count'] > 0;
    }
    
    /**
     * Получить количество кампаний - НОВАЯ ФУНКЦИЯ
     */
    public function getCampaignsCount() {
        $result = $this->db->fetchOne("SELECT COUNT(*) as count FROM campaigns");
        return $result['count'] ?? 0;
    }
    
    /**
     * Вставить данные кампании в базу
     */
    private function insertCampaignData($row) {
        // Разделяем Resource ID на Page ID и Ad ID
        $resourceId = $row['Resource ID'] ?? '';
        $parts = explode('/', $resourceId);
        $pageId = $parts[0] ?? '';
        $adId = $parts[1] ?? '';
        
        // Подготавливаем данные для вставки
        $data = [
            'advertiser' => $row['Advertiser'] ?? '',
            'resource_id' => $resourceId,
            'page_id' => $pageId,
            'ad_id' => $adId,
            'region' => $row['Region'] ?? '',
            'campaign_name' => $row['Campaign'] ?? '',
            'ad_title' => $row['Ad Title'] ?? '',
            'ad_description' => $row['Ad Description'] ?? '',
            'ad_media_type' => $row['Ad Media Type'] ?? '',
            'ad_media_hash' => $row['Ad Media Hash'] ?? '',
            'target_url' => $row['Target URL'] ?? '',
            'first_shown_at' => $this->parseDateTime($row['First Shown At'] ?? ''),
            'last_shown_at' => $this->parseDateTime($row['Last Shown At'] ?? '')
        ];
        
        $sql = "INSERT INTO campaigns (
            advertiser, resource_id, page_id, ad_id, region, campaign_name,
            ad_title, ad_description, ad_media_type, ad_media_hash,
            target_url, first_shown_at, last_shown_at
        ) VALUES (
            :advertiser, :resource_id, :page_id, :ad_id, :region, :campaign_name,
            :ad_title, :ad_description, :ad_media_type, :ad_media_hash,
            :target_url, :first_shown_at, :last_shown_at
        )";
        
        $campaignId = $this->db->insert($sql, $data);
        
        // Анализируем тексты для поиска дубликатов
        $this->analyzeAdTexts($campaignId, $data['ad_title'], $data['ad_description']);
        
        return $campaignId;
    }
    
    /**
     * Анализировать тексты объявлений - ИСПРАВЛЕННАЯ ВЕРСИЯ
     */
    private function analyzeAdTexts($campaignId, $title, $description) {
        if (!empty($title)) {
            $titleHash = md5(trim($title));
            
            // Проверяем, нет ли уже такого хеша
            $existingTitle = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM ad_text_analysis WHERE text_hash = :hash AND text_type = 'title'",
                ['hash' => $titleHash]
            );
            
            if ($existingTitle['count'] == 0) {
                $this->db->insert(
                    "INSERT INTO ad_text_analysis (campaign_id, text_hash, text_content, text_type) 
                     VALUES (:campaign_id, :text_hash, :text_content, :text_type)",
                    [
                        'campaign_id' => $campaignId,
                        'text_hash' => $titleHash,
                        'text_content' => $title,
                        'text_type' => 'title'
                    ]
                );
            }
        }
        
        if (!empty($description)) {
            $descHash = md5(trim($description));
            
            // Проверяем, нет ли уже такого хеша
            $existingDesc = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM ad_text_analysis WHERE text_hash = :hash AND text_type = 'description'",
                ['hash' => $descHash]
            );
            
            if ($existingDesc['count'] == 0) {
                $this->db->insert(
                    "INSERT INTO ad_text_analysis (campaign_id, text_hash, text_content, text_type) 
                     VALUES (:campaign_id, :text_hash, :text_content, :text_type)",
                    [
                        'campaign_id' => $campaignId,
                        'text_hash' => $descHash,
                        'text_content' => $description,
                        'text_type' => 'description'
                    ]
                );
            }
        }
    }
    
    /**
     * Обновить статистику по страницам
     */
    private function updatePageStatistics() {
        $sql = "INSERT INTO page_statistics (page_id, advertiser, total_campaigns, unique_ads, first_campaign_date, last_campaign_date)
                SELECT 
                    page_id,
                    advertiser,
                    COUNT(*) as total_campaigns,
                    COUNT(DISTINCT ad_id) as unique_ads,
                    MIN(first_shown_at) as first_campaign_date,
                    MAX(last_shown_at) as last_campaign_date
                FROM campaigns 
                WHERE page_id != ''
                GROUP BY page_id, advertiser
                ON DUPLICATE KEY UPDATE
                    total_campaigns = VALUES(total_campaigns),
                    unique_ads = VALUES(unique_ads),
                    first_campaign_date = VALUES(first_campaign_date),
                    last_campaign_date = VALUES(last_campaign_date),
                    updated_at = CURRENT_TIMESTAMP";
        
        $this->db->query($sql);
    }
    
    /**
     * Обновить ежедневную статистику
     */
    private function updateDailyStats() {
        $sql = "INSERT INTO daily_campaign_stats (date, advertiser, page_id, target_url_domain, new_campaigns, total_active_campaigns, unique_creatives)
                SELECT 
                    DATE(first_shown_at) as date,
                    advertiser,
                    page_id,
                    SUBSTRING_INDEX(SUBSTRING_INDEX(target_url, '/', 3), '/', -1) as target_url_domain,
                    COUNT(*) as new_campaigns,
                    COUNT(*) as total_active_campaigns,
                    COUNT(DISTINCT ad_media_hash) as unique_creatives
                FROM campaigns 
                WHERE first_shown_at IS NOT NULL
                GROUP BY DATE(first_shown_at), advertiser, page_id, target_url_domain
                ON DUPLICATE KEY UPDATE
                    new_campaigns = VALUES(new_campaigns),
                    total_active_campaigns = VALUES(total_active_campaigns),
                    unique_creatives = VALUES(unique_creatives)";
        
        $this->db->query($sql);
    }
    
    /**
     * Парсить дату и время
     */
    private function parseDateTime($dateString) {
        if (empty($dateString)) {
            return null;
        }
        
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return null;
        }
        
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Получить статистику по уникальности креативов
     */
    public function getCreativeUniquenessStats($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['advertiser'])) {
            $where[] = "advertiser = :advertiser";
            $params['advertiser'] = $filters['advertiser'];
        }
        
        if (!empty($filters['page_id'])) {
            $where[] = "page_id = :page_id";
            $params['page_id'] = $filters['page_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "first_used >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "last_used <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM creative_uniqueness $whereClause ORDER BY usage_count DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Получить статистику активности страниц
     */
    public function getPageActivityStats($filters = []) {
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
        
        $sql = "SELECT * FROM page_activity $whereClause ORDER BY last_campaign_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Получить данные для графика роста кампаний
     */
    public function getCampaignGrowthData($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['advertiser'])) {
            $where[] = "advertiser = :advertiser";
            $params['advertiser'] = $filters['advertiser'];
        }
        
        if (!empty($filters['page_id'])) {
            $where[] = "page_id = :page_id";
            $params['page_id'] = $filters['page_id'];
        }
        
        if (!empty($filters['target_url_domain'])) {
            $where[] = "target_url_domain = :target_url_domain";
            $params['target_url_domain'] = $filters['target_url_domain'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT 
                    date,
                    SUM(new_campaigns) as daily_new_campaigns,
                    SUM(total_active_campaigns) as daily_total_campaigns,
                    SUM(unique_creatives) as daily_unique_creatives
                FROM daily_campaign_stats 
                $whereClause
                GROUP BY date 
                ORDER BY date";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Найти похожие кампании
     */
    public function findSimilarCampaigns($campaignId, $threshold = 0.8) {
        $sql = "SELECT c1.*, c2.id as similar_id, c2.ad_title as similar_title, c2.ad_description as similar_description
                FROM campaigns c1
                JOIN campaigns c2 ON c1.id != c2.id
                WHERE c1.id = :campaign_id
                AND (
                    c1.ad_media_hash = c2.ad_media_hash
                    OR MATCH(c2.ad_title, c2.ad_description) AGAINST(CONCAT(c1.ad_title, ' ', c1.ad_description) IN NATURAL LANGUAGE MODE)
                )";
        
        return $this->db->fetchAll($sql, ['campaign_id' => $campaignId]);
    }
}
?>
