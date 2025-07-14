-- Схема базы данных для анализа рекламных кампаний Facebook

CREATE DATABASE IF NOT EXISTS ad_campaigns_analysis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ad_campaigns_analysis;

-- Основная таблица с данными рекламных кампаний
CREATE TABLE campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advertiser VARCHAR(255) NOT NULL,
    resource_id VARCHAR(255) NOT NULL,
    page_id VARCHAR(255) NOT NULL,
    ad_id VARCHAR(255) NOT NULL,
    region VARCHAR(100),
    campaign_name TEXT,
    ad_title TEXT,
    ad_description TEXT,
    ad_media_type VARCHAR(50),
    ad_media_hash VARCHAR(255),
    target_url TEXT,
    first_shown_at DATETIME,
    last_shown_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Индексы для оптимизации запросов
    INDEX idx_advertiser (advertiser),
    INDEX idx_page_id (page_id),
    INDEX idx_ad_id (ad_id),
    INDEX idx_resource_id (resource_id),
    INDEX idx_media_hash (ad_media_hash),
    INDEX idx_first_shown (first_shown_at),
    INDEX idx_last_shown (last_shown_at),
    INDEX idx_campaign_name (campaign_name(100)),
    INDEX idx_region (region)
);

-- Таблица для анализа уникальности текстов объявлений
CREATE TABLE ad_text_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT,
    text_hash VARCHAR(255) NOT NULL,
    text_content TEXT,
    text_type ENUM('title', 'description') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    INDEX idx_text_hash (text_hash),
    INDEX idx_text_type (text_type)
);

-- Таблица для анализа клонирования кампаний
CREATE TABLE campaign_clones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_campaign_id INT,
    clone_campaign_id INT,
    similarity_score DECIMAL(5,2),
    clone_type ENUM('exact', 'partial', 'media_only', 'text_only') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (original_campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (clone_campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    INDEX idx_similarity (similarity_score),
    INDEX idx_clone_type (clone_type)
);

-- Таблица для статистики по страницам Facebook
CREATE TABLE page_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id VARCHAR(255) NOT NULL,
    advertiser VARCHAR(255) NOT NULL,
    total_campaigns INT DEFAULT 0,
    unique_ads INT DEFAULT 0,
    first_campaign_date DATETIME,
    last_campaign_date DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_page_advertiser (page_id, advertiser),
    INDEX idx_page_id (page_id),
    INDEX idx_advertiser (advertiser),
    INDEX idx_active (is_active)
);

-- Таблица для ежедневной статистики кампаний
CREATE TABLE daily_campaign_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    advertiser VARCHAR(255) NOT NULL,
    page_id VARCHAR(255),
    target_url_domain VARCHAR(255),
    new_campaigns INT DEFAULT 0,
    total_active_campaigns INT DEFAULT 0,
    unique_creatives INT DEFAULT 0,
    
    UNIQUE KEY unique_daily_stats (date, advertiser, page_id, target_url_domain),
    INDEX idx_date (date),
    INDEX idx_advertiser (advertiser),
    INDEX idx_page_id (page_id)
);

-- Представление для быстрого анализа уникальности креативов
CREATE VIEW creative_uniqueness AS
SELECT 
    advertiser,
    page_id,
    ad_media_hash,
    COUNT(*) as usage_count,
    COUNT(DISTINCT ad_title) as unique_titles,
    COUNT(DISTINCT ad_description) as unique_descriptions,
    MIN(first_shown_at) as first_used,
    MAX(last_shown_at) as last_used
FROM campaigns 
WHERE ad_media_hash IS NOT NULL AND ad_media_hash != ''
GROUP BY advertiser, page_id, ad_media_hash;

-- Представление для анализа активности страниц
CREATE VIEW page_activity AS
SELECT 
    p.page_id,
    p.advertiser,
    p.total_campaigns,
    p.first_campaign_date,
    p.last_campaign_date,
    DATEDIFF(p.last_campaign_date, p.first_campaign_date) as days_active,
    CASE 
        WHEN p.last_campaign_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Active'
        WHEN p.last_campaign_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Recently Active'
        ELSE 'Inactive'
    END as activity_status
FROM page_statistics p;

