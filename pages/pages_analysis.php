<?php
session_start();
require_once '../config/database.php';
require_once '../includes/CampaignAnalyzer.php';

// Получаем фильтры из параметров запроса
$filters = [
    'advertiser' => $_GET['advertiser'] ?? '',
    'activity_status' => $_GET['activity_status'] ?? '',
    'min_campaigns' => intval($_GET['min_campaigns'] ?? 0),
    'sort' => $_GET['sort'] ?? 'total_campaigns',
    'order' => $_GET['order'] ?? 'DESC'
];

try {
    $analyzer = new CampaignAnalyzer();
    $db = Database::getInstance();
    
    // Получаем список рекламодателей для фильтра
    $advertisers = $db->fetchAll("SELECT DISTINCT advertiser FROM campaigns ORDER BY advertiser");
    
    // Получаем статистику активности страниц
    $pageActivityStats = $analyzer->getPageActivityStats(array_filter($filters));
    
    // Фильтруем по минимальному количеству кампаний
    if ($filters['min_campaigns'] > 0) {
        $pageActivityStats = array_filter($pageActivityStats, function($stat) use ($filters) {
            return $stat['total_campaigns'] >= $filters['min_campaigns'];
        });
    }
    
    // Сортировка
    $allowedSortFields = ['total_campaigns', 'days_active', 'first_campaign_date', 'last_campaign_date', 'advertiser'];
    $sortField = in_array($filters['sort'], $allowedSortFields) ? $filters['sort'] : 'total_campaigns';
    $sortOrder = strtoupper($filters['order']) === 'ASC' ? SORT_ASC : SORT_DESC;
    
    if (!empty($pageActivityStats)) {
        $sortColumn = array_column($pageActivityStats, $sortField);
        array_multisort($sortColumn, $sortOrder, $pageActivityStats);
    }
    
    // Получаем общую статистику
    $totalPages = count($pageActivityStats);
    $activePages = count(array_filter($pageActivityStats, function($stat) {
        return $stat['activity_status'] === 'Active';
    }));
    $recentlyActivePages = count(array_filter($pageActivityStats, function($stat) {
        return $stat['activity_status'] === 'Recently Active';
    }));
    $inactivePages = $totalPages - $activePages - $recentlyActivePages;
    
    // Получаем топ страниц по клонированию (страницы с наибольшим количеством одинаковых креативов)
    $topCloningPages = $db->fetchAll(
        "SELECT 
            page_id,
            advertiser,
            ad_media_hash,
            COUNT(*) as clone_count,
            COUNT(DISTINCT ad_title) as unique_titles,
            MIN(first_shown_at) as first_used,
            MAX(last_shown_at) as last_used
         FROM campaigns 
         WHERE ad_media_hash IS NOT NULL AND ad_media_hash != ''
         GROUP BY page_id, advertiser, ad_media_hash
         HAVING clone_count > 1
         ORDER BY clone_count DESC
         LIMIT 20"
    );
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $pageActivityStats = $topCloningPages = [];
    $totalPages = $activePages = $recentlyActivePages = $inactivePages = 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анализ страниц - Анализ рекламных кампаний</title>
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
                        <a class="nav-link" href="analytics.php"><i class="fas fa-chart-bar me-1"></i>Аналитика</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="campaigns.php"><i class="fas fa-list me-1"></i>Кампании</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="pages_analysis.php"><i class="fas fa-facebook me-1"></i>Анализ страниц</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-facebook text-primary me-2"></i>
                    Анализ страниц Facebook
                </h1>
                <p class="lead">
                    Анализ использования страниц Facebook в рекламных кампаниях, 
                    их активности и стратегий клонирования.
                </p>
            </div>
        </div>

        <!-- Статистика активности страниц -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo number_format($totalPages); ?></h4>
                                <p class="card-text">Всего страниц</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-facebook fa-2x"></i>
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
                                <h4 class="card-title"><?php echo number_format($activePages); ?></h4>
                                <p class="card-text">Активных</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
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
                                <h4 class="card-title"><?php echo number_format($recentlyActivePages); ?></h4>
                                <p class="card-text">Недавно активных</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo number_format($inactivePages); ?></h4>
                                <p class="card-text">Неактивных</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-pause-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="filter-section">
            <h5><i class="fas fa-filter me-2"></i>Фильтры</h5>
            <form method="GET" id="filterForm">
                <div class="row">
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
                        <label class="form-label">Статус активности</label>
                        <select name="activity_status" class="form-select">
                            <option value="">Все статусы</option>
                            <option value="Active" <?php echo $filters['activity_status'] === 'Active' ? 'selected' : ''; ?>>Активные</option>
                            <option value="Recently Active" <?php echo $filters['activity_status'] === 'Recently Active' ? 'selected' : ''; ?>>Недавно активные</option>
                            <option value="Inactive" <?php echo $filters['activity_status'] === 'Inactive' ? 'selected' : ''; ?>>Неактивные</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Минимум кампаний</label>
                        <input type="number" name="min_campaigns" class="form-control" 
                               placeholder="0" min="0" value="<?php echo $filters['min_campaigns']; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Сортировка</label>
                        <select name="sort" class="form-select">
                            <option value="total_campaigns" <?php echo $filters['sort'] === 'total_campaigns' ? 'selected' : ''; ?>>По количеству кампаний</option>
                            <option value="days_active" <?php echo $filters['sort'] === 'days_active' ? 'selected' : ''; ?>>По дням активности</option>
                            <option value="first_campaign_date" <?php echo $filters['sort'] === 'first_campaign_date' ? 'selected' : ''; ?>>По дате первой кампании</option>
                            <option value="last_campaign_date" <?php echo $filters['sort'] === 'last_campaign_date' ? 'selected' : ''; ?>>По дате последней кампании</option>
                            <option value="advertiser" <?php echo $filters['sort'] === 'advertiser' ? 'selected' : ''; ?>>По рекламодателю</option>
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
                            <select name="order" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                                <option value="DESC" <?php echo $filters['order'] === 'DESC' ? 'selected' : ''; ?>>По убыванию</option>
                                <option value="ASC" <?php echo $filters['order'] === 'ASC' ? 'selected' : ''; ?>>По возрастанию</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- График активности страниц -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="chart-title">Распределение по статусу активности</h5>
                    <canvas id="activityStatusChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="chart-title">Топ рекламодателей по количеству страниц</h5>
                    <canvas id="advertiserPagesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Таблица активности страниц -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-table text-primary me-2"></i>
                            Активность страниц Facebook
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pageActivityStats)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Page ID</th>
                                            <th>Рекламодатель</th>
                                            <th>Всего кампаний</th>
                                            <th>Дней активности</th>
                                            <th>Статус</th>
                                            <th>Первая кампания</th>
                                            <th>Последняя кампания</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($pageActivityStats, 0, 50) as $stat): ?>
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
                                                <td>
                                                    <a href="campaigns.php?page_id=<?php echo urlencode($stat['page_id']); ?>&advertiser=<?php echo urlencode($stat['advertiser']); ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       data-bs-toggle="tooltip" title="Посмотреть кампании">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($pageActivityStats) > 50): ?>
                                <div class="text-center mt-3">
                                    <p class="text-muted">Показано 50 из <?php echo count($pageActivityStats); ?> записей</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Нет данных для отображения</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Анализ клонирования -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-copy text-warning me-2"></i>
                            Топ страниц по клонированию креативов
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Страницы, которые чаще всего используют одинаковые креативы (по хешу медиа)
                        </p>
                        <?php if (!empty($topCloningPages)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Page ID</th>
                                            <th>Рекламодатель</th>
                                            <th>Хеш медиа</th>
                                            <th>Количество клонов</th>
                                            <th>Уникальных заголовков</th>
                                            <th>Первое использование</th>
                                            <th>Последнее использование</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topCloningPages as $clone): ?>
                                            <tr>
                                                <td>
                                                    <code class="small"><?php echo htmlspecialchars(substr($clone['page_id'], 0, 15)); ?>...</code>
                                                </td>
                                                <td><?php echo htmlspecialchars($clone['advertiser']); ?></td>
                                                <td>
                                                    <code class="small"><?php echo htmlspecialchars(substr($clone['ad_media_hash'], 0, 12)); ?>...</code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger"><?php echo $clone['clone_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $clone['unique_titles']; ?></span>
                                                </td>
                                                <td class="small"><?php echo date('d.m.Y', strtotime($clone['first_used'])); ?></td>
                                                <td class="small"><?php echo date('d.m.Y', strtotime($clone['last_used'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Нет данных о клонировании креативов</p>
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
            initPageAnalysisCharts();
        });

        function initPageAnalysisCharts() {
            // График статуса активности
            const activityData = {
                'Active': <?php echo $activePages; ?>,
                'Recently Active': <?php echo $recentlyActivePages; ?>,
                'Inactive': <?php echo $inactivePages; ?>
            };
            
            renderActivityStatusChart(activityData);
            
            // График топ рекламодателей
            fetch('../api/chart_data.php?type=advertiser_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        renderAdvertiserPagesChart(data.data);
                    }
                })
                .catch(error => console.error('Ошибка загрузки данных рекламодателей:', error));
        }

        function renderActivityStatusChart(data) {
            const ctx = document.getElementById('activityStatusChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        data: Object.values(data),
                        backgroundColor: ['#28a745', '#ffc107', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function renderAdvertiserPagesChart(data) {
            const ctx = document.getElementById('advertiserPagesChart').getContext('2d');
            
            // Берем топ-8 рекламодателей
            const topAdvertisers = data.slice(0, 8);
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: topAdvertisers.map(item => item.advertiser),
                    datasets: [{
                        label: 'Количество страниц',
                        data: topAdvertisers.map(item => parseInt(item.unique_pages)),
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>

