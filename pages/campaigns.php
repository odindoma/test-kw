<?php
session_start();
require_once '../config/database.php';

// Получаем параметры фильтрации и пагинации
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(DatabaseConfig::MAX_ITEMS_PER_PAGE, max(10, intval($_GET['limit'] ?? DatabaseConfig::ITEMS_PER_PAGE)));
$offset = ($page - 1) * $limit;

$filters = [
    'advertiser' => $_GET['advertiser'] ?? '',
    'page_id' => $_GET['page_id'] ?? '',
    'region' => $_GET['region'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'media_type' => $_GET['media_type'] ?? '',
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'DESC'
];

try {
    $db = Database::getInstance();
    
    // Получаем списки для фильтров
    $advertisers = $db->fetchAll("SELECT DISTINCT advertiser FROM campaigns ORDER BY advertiser");
    $regions = $db->fetchAll("SELECT DISTINCT region FROM campaigns WHERE region != '' ORDER BY region");
    $mediaTypes = $db->fetchAll("SELECT DISTINCT ad_media_type FROM campaigns WHERE ad_media_type != '' ORDER BY ad_media_type");
    
    // Строим запрос с фильтрами
    $whereConditions = [];
    $params = [];
    
    if (!empty($filters['advertiser'])) {
        $whereConditions[] = "advertiser = :advertiser";
        $params['advertiser'] = $filters['advertiser'];
    }
    
    if (!empty($filters['page_id'])) {
        $whereConditions[] = "page_id = :page_id";
        $params['page_id'] = $filters['page_id'];
    }
    
    if (!empty($filters['region'])) {
        $whereConditions[] = "region = :region";
        $params['region'] = $filters['region'];
    }
    
    if (!empty($filters['media_type'])) {
        $whereConditions[] = "ad_media_type = :media_type";
        $params['media_type'] = $filters['media_type'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = "(ad_title LIKE :search OR ad_description LIKE :search OR campaign_name LIKE :search)";
        $params['search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = "first_shown_at >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = "last_shown_at <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Валидация сортировки
    $allowedSortFields = ['created_at', 'first_shown_at', 'last_shown_at', 'advertiser', 'campaign_name'];
    $sortField = in_array($filters['sort'], $allowedSortFields) ? $filters['sort'] : 'created_at';
    $sortOrder = strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Получаем общее количество записей
    $totalCount = $db->count("SELECT COUNT(*) FROM campaigns $whereClause", $params);
    
    // Получаем данные кампаний
    $sql = "SELECT 
                id, advertiser, page_id, ad_id, region, campaign_name,
                ad_title, ad_description, ad_media_type, ad_media_hash,
                target_url, first_shown_at, last_shown_at, created_at
            FROM campaigns 
            $whereClause 
            ORDER BY $sortField $sortOrder 
            LIMIT $limit OFFSET $offset";
    
    $campaigns = $db->fetchAll($sql, $params);
    
    // Вычисляем пагинацию
    $totalPages = ceil($totalCount / $limit);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $campaigns = [];
    $totalCount = $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кампании - Анализ рекламных кампаний</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-chart-line me-2"></i>
                Анализ рекламных кампаний
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload.php"><i class="fas fa-upload me-1"></i>Загрузка данных</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php"><i class="fas fa-chart-bar me-1"></i>Аналитика</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="campaigns.php"><i class="fas fa-list me-1"></i>Кампании</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages_analysis.php"><i class="fas fa-facebook me-1"></i>Анализ страниц</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-list text-primary me-2"></i>
                    Рекламные кампании
                </h1>
                <p class="text-muted">
                    Найдено записей: <strong><?php echo number_format($totalCount); ?></strong>
                </p>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="filter-section">
            <h5><i class="fas fa-filter me-2"></i>Фильтры и поиск</h5>
            <form method="GET" id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Поиск по тексту</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Поиск в заголовках, описаниях, кампаниях..."
                               value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Рекламодатель</label>
                        <select name="advertiser" class="form-select">
                            <option value="">Все рекламодатели</option>
                            <?php foreach ($advertisers as $advertiser): ?>
                                <option value="<?php echo htmlspecialchars($advertiser['advertiser']); ?>" 
                                        <?php echo $filters['advertiser'] === $advertiser['advertiser'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($advertiser['advertiser']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Регион</label>
                        <select name="region" class="form-select">
                            <option value="">Все регионы</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?php echo htmlspecialchars($region['region']); ?>" 
                                        <?php echo $filters['region'] === $region['region'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($region['region']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Тип медиа</label>
                        <select name="media_type" class="form-select">
                            <option value="">Все типы</option>
                            <?php foreach ($mediaTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['ad_media_type']); ?>" 
                                        <?php echo $filters['media_type'] === $type['ad_media_type'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['ad_media_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-3">
                        <label class="form-label">Дата от</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Дата до</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Сортировка</label>
                        <select name="sort" class="form-select">
                            <option value="created_at" <?php echo $filters['sort'] === 'created_at' ? 'selected' : ''; ?>>Дата добавления</option>
                            <option value="first_shown_at" <?php echo $filters['sort'] === 'first_shown_at' ? 'selected' : ''; ?>>Первый показ</option>
                            <option value="last_shown_at" <?php echo $filters['sort'] === 'last_shown_at' ? 'selected' : ''; ?>>Последний показ</option>
                            <option value="advertiser" <?php echo $filters['sort'] === 'advertiser' ? 'selected' : ''; ?>>Рекламодатель</option>
                            <option value="campaign_name" <?php echo $filters['sort'] === 'campaign_name' ? 'selected' : ''; ?>>Название кампании</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Порядок</label>
                        <select name="order" class="form-select">
                            <option value="DESC" <?php echo $filters['order'] === 'DESC' ? 'selected' : ''; ?>>По убыванию</option>
                            <option value="ASC" <?php echo $filters['order'] === 'ASC' ? 'selected' : ''; ?>>По возрастанию</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Применить фильтры
                        </button>
                        <button type="button" class="btn btn-secondary ms-2" id="resetFilters">
                            <i class="fas fa-undo me-1"></i>Сбросить
                        </button>
                        <div class="float-end">
                            <label class="form-label me-2">Записей на странице:</label>
                            <select name="limit" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                                <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?php echo $limit === 200 ? 'selected' : ''; ?>>200</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Таблица кампаний -->
        <div class="card">
            <div class="card-body">
                <?php if (!empty($campaigns)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Рекламодатель</th>
                                    <th>Page ID</th>
                                    <th>Ad ID</th>
                                    <th>Регион</th>
                                    <th>Кампания</th>
                                    <th>Заголовок</th>
                                    <th>Тип медиа</th>
                                    <th>Первый показ</th>
                                    <th>Последний показ</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($campaign['advertiser']); ?></strong>
                                        </td>
                                        <td>
                                            <code class="small"><?php echo htmlspecialchars(substr($campaign['page_id'], 0, 12)); ?>...</code>
                                        </td>
                                        <td>
                                            <code class="small"><?php echo htmlspecialchars(substr($campaign['ad_id'], 0, 12)); ?>...</code>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($campaign['region']); ?></span>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" 
                                                 title="<?php echo htmlspecialchars($campaign['campaign_name']); ?>">
                                                <?php echo htmlspecialchars($campaign['campaign_name']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 250px;" 
                                                 title="<?php echo htmlspecialchars($campaign['ad_title']); ?>">
                                                <?php echo htmlspecialchars($campaign['ad_title']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($campaign['ad_media_type'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($campaign['ad_media_type']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <?php echo $campaign['first_shown_at'] ? date('d.m.Y H:i', strtotime($campaign['first_shown_at'])) : '-'; ?>
                                        </td>
                                        <td class="small">
                                            <?php echo $campaign['last_shown_at'] ? date('d.m.Y H:i', strtotime($campaign['last_shown_at'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="showCampaignDetails(<?php echo $campaign['id']; ?>)"
                                                    data-bs-toggle="tooltip" title="Подробности">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success ms-1" 
                                                    onclick="findSimilarCampaigns(<?php echo $campaign['id']; ?>)"
                                                    data-bs-toggle="tooltip" title="Найти похожие">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Пагинация -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Навигация по страницам">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Кампании не найдены</h5>
                        <p class="text-muted">Попробуйте изменить параметры фильтрации</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Модальное окно для деталей кампании -->
    <div class="modal fade" id="campaignDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Детали кампании</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="campaignDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function showCampaignDetails(campaignId) {
            const modal = new bootstrap.Modal(document.getElementById('campaignDetailsModal'));
            const content = document.getElementById('campaignDetailsContent');
            
            // Показываем модальное окно
            modal.show();
            
            // Загружаем детали кампании
            fetch(`../api/campaign_details.php?id=${campaignId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        content.innerHTML = renderCampaignDetails(data.campaign);
                    } else {
                        content.innerHTML = '<div class="alert alert-danger">Ошибка загрузки данных</div>';
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    content.innerHTML = '<div class="alert alert-danger">Произошла ошибка при загрузке</div>';
                });
        }

        function renderCampaignDetails(campaign) {
            return `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Основная информация</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Рекламодатель:</strong></td><td>${campaign.advertiser}</td></tr>
                            <tr><td><strong>Page ID:</strong></td><td><code>${campaign.page_id}</code></td></tr>
                            <tr><td><strong>Ad ID:</strong></td><td><code>${campaign.ad_id}</code></td></tr>
                            <tr><td><strong>Регион:</strong></td><td>${campaign.region}</td></tr>
                            <tr><td><strong>Тип медиа:</strong></td><td>${campaign.ad_media_type || '-'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Временные рамки</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Первый показ:</strong></td><td>${campaign.first_shown_at || '-'}</td></tr>
                            <tr><td><strong>Последний показ:</strong></td><td>${campaign.last_shown_at || '-'}</td></tr>
                            <tr><td><strong>Добавлено:</strong></td><td>${campaign.created_at}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Название кампании</h6>
                        <p class="border p-2 bg-light">${campaign.campaign_name}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <h6>Заголовок объявления</h6>
                        <p class="border p-2 bg-light">${campaign.ad_title}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <h6>Описание объявления</h6>
                        <p class="border p-2 bg-light">${campaign.ad_description || 'Нет описания'}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <h6>Целевая ссылка</h6>
                        <p class="border p-2 bg-light">
                            <a href="${campaign.target_url}" target="_blank" class="text-break">${campaign.target_url}</a>
                        </p>
                    </div>
                </div>
                ${campaign.ad_media_hash ? `
                <div class="row">
                    <div class="col-12">
                        <h6>Хеш медиа</h6>
                        <p class="border p-2 bg-light"><code>${campaign.ad_media_hash}</code></p>
                    </div>
                </div>
                ` : ''}
            `;
        }

        function findSimilarCampaigns(campaignId) {
            // Открываем новую вкладку с поиском похожих кампаний
            window.open(`campaigns.php?similar_to=${campaignId}`, '_blank');
        }
    </script>
</body>
</html>

