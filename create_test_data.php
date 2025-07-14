<?php
/**
 * Скрипт для создания тестовых данных
 * Генерирует примеры рекламных кампаний для демонстрации функционала
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/CampaignAnalyzer.php';

echo "=== Создание тестовых данных ===\n\n";

try {
    $db = Database::getInstance();
    $analyzer = new CampaignAnalyzer();
    
    echo "1. Очистка существующих данных...\n";
    
    // Очищаем таблицы
    $db->query("DELETE FROM ad_text_analysis");
    $db->query("DELETE FROM campaign_clones");
    $db->query("DELETE FROM daily_campaign_stats");
    $db->query("DELETE FROM page_statistics");
    $db->query("DELETE FROM campaigns");
    
    echo "✓ Данные очищены\n\n";
    
    echo "2. Генерация тестовых кампаний...\n";
    
    // Тестовые данные
    $advertisers = [
        'spellrock.com',
        'dating-site.com',
        'health-products.net',
        'finance-offers.org',
        'travel-deals.com'
    ];
    
    $regions = ['Austria', 'Germany', 'France', 'Italy', 'Spain', 'Poland', 'Czech Republic'];
    
    $campaignTemplates = [
        'Senior dating %s new v%d B2B %d DXR %s - Unknown WW FB DXR',
        'Health supplement %s offer v%d - %s targeting %s',
        'Travel deals %s summer v%d - %s promotion %s',
        'Finance loan %s quick v%d - %s campaign %s',
        'Dating app %s premium v%d - %s special %s'
    ];
    
    $adTitles = [
        '💖 50 के बाद प्यार की तलाश? ❤️',
        '🌟 Lose Weight Fast! 🌟',
        '✈️ Amazing Travel Deals! ✈️',
        '💰 Quick Loan Approval 💰',
        '❤️ Find Love Today ❤️',
        '🏥 Health Supplement Sale 🏥',
        '🎯 Special Offer Inside 🎯'
    ];
    
    $adDescriptions = [
        'Join thousands of singles over 50 finding love online.',
        'Revolutionary weight loss formula - see results in days!',
        'Book now and save up to 70% on your next vacation.',
        'Get approved for a loan in minutes, not days.',
        'Meet compatible singles in your area today.',
        'Natural ingredients, proven results, money-back guarantee.',
        'Limited time offer - don\'t miss out on this deal!'
    ];
    
    $mediaHashes = [
        'e7fe6eb73cb392ad79421cda7cf196a479bdad7238e0677a47d91872d8fc9503',
        'b22efa0d68787bdc16c6b83db09272ac2a470f4e812747a447237dc128f0ecd4',
        'c33f1b1e79898cec27d7c94eb1382bd3b581e5f923858b558348ed239e1f0de5',
        'a11d2c2f68676adb16b5a72ca0271ac1a360d4d712636a336126dc017d0dcb6',
        'f44e3d3g79787bec38c8d95fb2493ce4c692f6g834969c669459fe34af2e1ef7'
    ];
    
    $targetUrls = [
        'https://spellrock.com/articles/senior-speed-dating/',
        'https://dating-site.com/signup/premium',
        'https://health-products.net/weight-loss/formula',
        'https://finance-offers.org/loans/quick-approval',
        'https://travel-deals.com/summer/packages'
    ];
    
    $generatedCount = 0;
    $startDate = strtotime('-60 days');
    $endDate = time();
    
    // Генерируем кампании
    for ($i = 0; $i < 500; $i++) {
        $advertiser = $advertisers[array_rand($advertisers)];
        $region = $regions[array_rand($regions)];
        
        // Генерируем Page ID и Ad ID
        $pageId = rand(100000000000000, 999999999999999);
        $adId = rand(1000000000000000, 9999999999999999);
        $resourceId = $pageId . '/' . $adId;
        
        // Выбираем шаблон кампании
        $campaignTemplate = $campaignTemplates[array_rand($campaignTemplates)];
        $campaignName = sprintf(
            $campaignTemplate,
            date('dM', $startDate + rand(0, $endDate - $startDate)),
            rand(1, 10),
            rand(100, 999),
            substr(md5(rand()), 0, 4)
        );
        
        $adTitle = $adTitles[array_rand($adTitles)];
        $adDescription = rand(0, 1) ? $adDescriptions[array_rand($adDescriptions)] : '';
        
        // Иногда используем одинаковые хеши для демонстрации клонирования
        $mediaHash = '';
        if (rand(0, 100) < 70) { // 70% кампаний имеют медиа
            if (rand(0, 100) < 30) { // 30% используют повторяющиеся хеши
                $mediaHash = $mediaHashes[array_rand(array_slice($mediaHashes, 0, 2))];
            } else {
                $mediaHash = $mediaHashes[array_rand($mediaHashes)];
            }
        }
        
        $targetUrl = $targetUrls[array_rand($targetUrls)] . '?utm_source=Facebook&utm_campaign=' . urlencode($campaignName);
        
        // Генерируем даты
        $firstShown = $startDate + rand(0, $endDate - $startDate);
        $lastShown = $firstShown + rand(3600, 7 * 24 * 3600); // От 1 часа до 7 дней
        
        // Вставляем данные
        $sql = "INSERT INTO campaigns (
            advertiser, resource_id, page_id, ad_id, region, campaign_name,
            ad_title, ad_description, ad_media_type, ad_media_hash,
            target_url, first_shown_at, last_shown_at
        ) VALUES (
            :advertiser, :resource_id, :page_id, :ad_id, :region, :campaign_name,
            :ad_title, :ad_description, :ad_media_type, :ad_media_hash,
            :target_url, :first_shown_at, :last_shown_at
        )";
        
        $params = [
            'advertiser' => $advertiser,
            'resource_id' => $resourceId,
            'page_id' => $pageId,
            'ad_id' => $adId,
            'region' => $region,
            'campaign_name' => $campaignName,
            'ad_title' => $adTitle,
            'ad_description' => $adDescription,
            'ad_media_type' => $mediaHash ? 'Image' : '',
            'ad_media_hash' => $mediaHash,
            'target_url' => $targetUrl,
            'first_shown_at' => date('Y-m-d H:i:s', $firstShown),
            'last_shown_at' => date('Y-m-d H:i:s', $lastShown)
        ];
        
        $db->query($sql, $params);
        $generatedCount++;
        
        if ($generatedCount % 50 == 0) {
            echo "✓ Создано $generatedCount кампаний\n";
        }
    }
    
    echo "✓ Всего создано $generatedCount кампаний\n\n";
    
    echo "3. Обновление статистики...\n";
    
    // Обновляем статистику страниц
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
            GROUP BY page_id, advertiser";
    
    $db->query($sql);
    
    // Обновляем ежедневную статистику
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
            GROUP BY DATE(first_shown_at), advertiser, page_id, target_url_domain";
    
    $db->query($sql);
    
    echo "✓ Статистика обновлена\n\n";
    
    echo "4. Создание анализа текстов...\n";
    
    // Создаем анализ текстов
    $campaigns = $db->fetchAll("SELECT id, ad_title, ad_description FROM campaigns LIMIT 100");
    
    foreach ($campaigns as $campaign) {
        if (!empty($campaign['ad_title'])) {
            $titleHash = md5(trim($campaign['ad_title']));
            $db->query(
                "INSERT INTO ad_text_analysis (campaign_id, text_hash, text_content, text_type) 
                 VALUES (:campaign_id, :text_hash, :text_content, :text_type)",
                [
                    'campaign_id' => $campaign['id'],
                    'text_hash' => $titleHash,
                    'text_content' => $campaign['ad_title'],
                    'text_type' => 'title'
                ]
            );
        }
        
        if (!empty($campaign['ad_description'])) {
            $descHash = md5(trim($campaign['ad_description']));
            $db->query(
                "INSERT INTO ad_text_analysis (campaign_id, text_hash, text_content, text_type) 
                 VALUES (:campaign_id, :text_hash, :text_content, :text_type)",
                [
                    'campaign_id' => $campaign['id'],
                    'text_hash' => $descHash,
                    'text_content' => $campaign['ad_description'],
                    'text_type' => 'description'
                ]
            );
        }
    }
    
    echo "✓ Анализ текстов создан\n\n";
    
    // Статистика
    $totalCampaigns = $db->count("SELECT COUNT(*) FROM campaigns");
    $totalAdvertisers = $db->count("SELECT COUNT(DISTINCT advertiser) FROM campaigns");
    $totalPages = $db->count("SELECT COUNT(DISTINCT page_id) FROM campaigns");
    $totalCreatives = $db->count("SELECT COUNT(DISTINCT ad_media_hash) FROM campaigns WHERE ad_media_hash != ''");
    
    echo "=== ТЕСТОВЫЕ ДАННЫЕ СОЗДАНЫ ===\n\n";
    echo "Статистика:\n";
    echo "- Кампаний: $totalCampaigns\n";
    echo "- Рекламодателей: $totalAdvertisers\n";
    echo "- Страниц: $totalPages\n";
    echo "- Уникальных креативов: $totalCreatives\n\n";
    echo "Система готова для тестирования!\n";
    echo "Откройте в браузере: http://localhost/ad_campaigns_analyzer/\n\n";
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}
?>

