<?php
session_start();
require_once '../config/database.php';
require_once '../includes/CampaignAnalyzer.php';

// Получаем фильтры из параметров запроса
$filters = [
    'advertiser' => $_GET['advertiser'] ?? '',
    'page_id' => $_GET['page_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

try {
    $analyzer = new CampaignAnalyzer();
    $db = Database::getInstance();
    
    // Получаем список рекламодателей для фильтра
    $advertisers = $db->fetchAll("SELECT DISTINCT advertiser FROM campaigns ORDER BY advertiser");
    
    // Получаем список страниц для фильтра
    $pages = $db->fetchAll("SELECT DISTINCT page_id FROM campaigns WHERE page_id != '' ORDER BY page_id LIMIT 100");
    
    // Получаем статистику уникальности креативов
    $creativityStats = $analyzer->getCreativeUniquenessStats(array_filter($filters));
    
    // Получаем статистику активности страниц
    $pageActivityStats = $analyzer->getPageActivityStats(array_filter($filters));
    
    // Получаем общую статистику
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
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = "first_shown_at >= :date_from";
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = "last_shown_at <= :date_to";
        $params['date_to'] = $filters['date_to'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $totalCampaigns = $db->count("SELECT COUNT(*) FROM campaigns $whereClause", $params);
    $uniqueCreatives = $db->count("SELECT COUNT(DISTINCT ad_media_hash) FROM campaigns $whereClause AND ad_media_hash != ''", $params);
    $uniqueTexts = $db->count("SELECT COUNT(DISTINCT MD5(CONCAT(COALESCE(ad_title, ''), COALESCE(ad_description, '')))) FROM campaigns $whereClause", $params);
    $activePagesCount = $db->count("SELECT COUNT(DISTINCT page_id) FROM campaigns $whereClause AND page_id != ''", $params);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $creativityStats = $pageActivityStats = [];
    $totalCampaigns = $uniqueCreatives = $uniqueTexts = $activePagesCount = 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика - Анализ рекламных кампаний</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a class="nav-link active" href="analytics.php"><i class="fas fa-chart-bar me-1"></i>Аналитика</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="campaigns.php"><i class="fas fa-list me-1"></i>Кампании</a>
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
                    <i class="fas fa-chart-bar text-primary me-2"></i>
                    Аналитика рекламных кампаний
                </h1>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="filter-section">
            <h5><i class="fas fa-filter me-2"></i>Фильтры</h5>
            <form method="GET" id="filterForm">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Рекламодатель</label>
                        <select name="advertiser" class="form-select auto-filter">
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
                        <label class="form-label">Страница Facebook</label>
                        <select name="page_id" class="form-select auto-filter">
                            <option value="">Все страницы</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo htmlspecialchars($page['page_id']); ?>" 
                                        <?php echo $filters['page_id'] === $page['page_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($page['page_id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Дата от</label>
                        <input type="date" name="date_from" class="form-control auto-filter" 
                               value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Дата до</label>
                        <input type="date" name="date_to" class="form-control auto-filter" 
                               value="<?php echo htmlspecialchars($filters['date_to']); ?>">
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
                    </div>
                </div>
            </form>
        </div>

        <!-- Общая статистика -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo number_format($totalCampaigns); ?></h4>
                                <p class="card-text">Всего кампаний</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-bullhorn fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo number_format($uniqueCreatives); ?></h4>
                                <p class="card-text">Уникальных креативов</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-images fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo number_format($uniqueTexts); ?></h4>
                                <p class="card-text">Уникальных текстов</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-font fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo number_format($activePagesCount); ?></h4>
                                <p class="card-text">Активных страниц</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-facebook fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Графики -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="chart-title">Рост кампаний по дням</h5>
                    <canvas id="campaignGrowthChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="chart-title">Распределение по рекламодателям</h5>
                    <canvas id="advertiserDistributionChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Анализ уникальности креативов -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-images text-primary me-2"></i>
                            Анализ уникальности креативов
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($creativityStats)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Рекламодатель</th>
                                            <th>Страница</th>
                                            <th>Хеш медиа</th>
                                            <th>Использований</th>
                                            <th>Уникальных заголовков</th>
                                            <th>Уникальных описаний</th>
                                            <th>Первое использование</th>
                                            <th>Последнее использование</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($creativityStats, 0, 20) as $stat): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($stat['advertiser']); ?></td>
                                                <td>
                                                    <code class="small"><?php echo htmlspecialchars(substr($stat['page_id'], 0, 15)); ?>...</code>
                                                </td>
                                                <td>
                                                    <code class="small"><?php echo htmlspecialchars(substr($stat['ad_media_hash'], 0, 12)); ?>...</code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $stat['usage_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $stat['unique_titles']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $stat['unique_descriptions']; ?></span>
                                                </td>
                                                <td class="small"><?php echo date('d.m.Y', strtotime($stat['first_used'])); ?></td>
                                                <td class="small"><?php echo date('d.m.Y', strtotime($stat['last_used'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($creativityStats) > 20): ?>
                                <div class="text-center mt-3">
                                    <p class="text-muted">Показано 20 из <?php echo count($creativityStats); ?> записей</p>
                                    <a href="campaigns.php?view=creative_analysis" class="btn btn-outline-primary">
                                        Посмотреть все
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Нет данных для анализа уникальности креативов</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Анализ активности страниц -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-facebook text-primary me-2"></i>
                            Анализ активности страниц Facebook
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pageActivityStats)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Страница ID</th>
                                            <th>Рекламодатель</th>
                                            <th>Всего кампаний</th>
                                            <th>Дней активности</th>
                                            <th>Статус</th>
                                            <th>Первая кампания</th>
                                            <th>Последняя кампания</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($pageActivityStats, 0, 15) as $stat): ?>
                                            <tr>
                                                <td>
                                                    <code class="small"><?php echo htmlspecialchars(substr($stat['page_id'], 0, 15)); ?>...</code>
                                                </td>
                                                <td><?php echo htmlspecialchars($stat['advertiser']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $stat['total_campaigns']; ?></span>
                                                </td>
                                                <td><?php echo $stat['days_active']; ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($stat['activity_status']) {
                                                        case 'Active':
                                                            $statusClass = 'bg-success';
                                                            break;
                                                        case 'Recently Active':
                                                            $statusClass = 'bg-warning';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo $stat['activity_status']; ?>
                                                    </span>
                                                </td>
                                                <td class="small"><?php echo date('d.m.Y', strtotime($stat['first_campaign_date'])); ?></td>
                                                <td class="small"><?php echo date('d.m.Y', strtotime($stat['last_campaign_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($pageActivityStats) > 15): ?>
                                <div class="text-center mt-3">
                                    <p class="text-muted">Показано 15 из <?php echo count($pageActivityStats); ?> записей</p>
                                    <a href="pages_analysis.php" class="btn btn-outline-primary">
                                        Посмотреть все
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Нет данных для анализа активности страниц</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Инициализация графиков при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            initAnalyticsCharts();
        });

        function initAnalyticsCharts() {
            // Получаем параметры фильтров для API
            const urlParams = new URLSearchParams(window.location.search);
            const filterParams = urlParams.toString();
            
            // График роста кампаний
            fetch(`../api/chart_data.php?type=campaign_growth&${filterParams}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        renderCampaignGrowthChart(data.data);
                    }
                })
                .catch(error => console.error('Ошибка загрузки данных графика роста:', error));
            
            // График распределения по рекламодателям
            fetch(`../api/chart_data.php?type=advertiser_stats&${filterParams}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        renderAdvertiserDistributionChart(data.data);
                    }
                })
                .catch(error => console.error('Ошибка загрузки данных распределения:', error));
        }

        function renderCampaignGrowthChart(data) {
            const ctx = document.getElementById('campaignGrowthChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(item => new Date(item.date).toLocaleDateString('ru-RU')),
                    datasets: [{
                        label: 'Новые кампании',
                        data: data.map(item => parseInt(item.daily_new_campaigns)),
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function renderAdvertiserDistributionChart(data) {
            const ctx = document.getElementById('advertiserDistributionChart').getContext('2d');
            
            // Берем топ-10 рекламодателей
            const topAdvertisers = data.slice(0, 10);
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: topAdvertisers.map(item => item.advertiser),
                    datasets: [{
                        data: topAdvertisers.map(item => parseInt(item.total_campaigns)),
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF',
                            '#4BC0C0', '#FF6384'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'right'
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>

