<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анализатор рекламных кампаний</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            height: 400px;
            margin: 20px 0;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .upload-zone {
            border: 2px dashed #cbd5e0;
            transition: all 0.3s ease;
        }
        .upload-zone:hover {
            border-color: #4299e1;
            background-color: #f7fafc;
        }
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100">

<?php
// Конфигурация базы данных
$host = 'localhost';
$dbname = 'test_ad_campaigns';
$username = 'homestead';
$password = 'secret';

// Функция подключения к базе данных
function getDbConnection() {
    global $host, $dbname, $username, $password;
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Ошибка подключения к базе данных: " . $e->getMessage());
    }
}

// Создание таблицы если не существует
function createTable() {
    $pdo = getDbConnection();
    $sql = "CREATE TABLE IF NOT EXISTS campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        advertiser VARCHAR(255),
        resource_id VARCHAR(255),
        region VARCHAR(100),
        campaign_name TEXT,
        ad_title TEXT,
        ad_description TEXT,
        ad_media_type VARCHAR(50),
        ad_media_hash VARCHAR(255),
        target_url TEXT,
        first_shown_at DATE,
        last_shown_at DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_advertiser (advertiser),
        INDEX idx_media_hash (ad_media_hash),
        INDEX idx_dates (first_shown_at, last_shown_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

// Обработка загрузки CSV файла
function handleCsvUpload() {
    if (!isset($_FILES['csv_file'])) {
        return false;
    }
    
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Ошибка загрузки файла');
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('Не удается открыть файл');
    }
    
    $pdo = getDbConnection();
    
    // Пропускаем заголовок
    $header = fgetcsv($handle);
    
    $sql = "INSERT INTO campaigns (advertiser, resource_id, region, campaign_name, ad_title, ad_description, ad_media_type, ad_media_hash, target_url, first_shown_at, last_shown_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    $count = 0;
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) >= 11) {
            $stmt->execute([
                $data[1], // advertiser
                $data[2], // resource_id
                $data[3], // region
                $data[4], // campaign_name
                $data[5], // ad_title
                $data[6], // ad_description
                $data[7], // ad_media_type
                $data[8], // ad_media_hash
                $data[9], // target_url
                $data[10], // first_shown_at
                $data[11]  // last_shown_at
            ]);
            $count++;
        }
    }
    
    fclose($handle);
    return $count;
}

// Получение данных кампаний с исправленным SQL синтаксисом
function getCampaignsData($limit = 50, $offset = 0, $search = '', $region = '', $advertiser = '') {
    $pdo = getDbConnection();
    
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (ad_title LIKE ? OR ad_description LIKE ? OR campaign_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($region)) {
        $where .= " AND region = ?";
        $params[] = $region;
    }
    
    if (!empty($advertiser)) {
        $where .= " AND advertiser = ?";
        $params[] = $advertiser;
    }
    
    // ИСПРАВЛЕННЫЙ SQL синтаксис для LIMIT и OFFSET
    $sql = "SELECT * FROM campaigns $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение статистики
function getStats() {
    $pdo = getDbConnection();
    
    $stats = [];
    
    // Общее количество кампаний
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM campaigns");
    $stats['total_campaigns'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Количество рекламодателей
    $stmt = $pdo->query("SELECT COUNT(DISTINCT advertiser) as total FROM campaigns");
    $stats['total_advertisers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Количество регионов
    $stmt = $pdo->query("SELECT COUNT(DISTINCT region) as total FROM campaigns");
    $stats['total_regions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Средняя продолжительность кампании
    $stmt = $pdo->query("SELECT AVG(DATEDIFF(last_shown_at, first_shown_at)) as avg_duration FROM campaigns WHERE first_shown_at IS NOT NULL AND last_shown_at IS NOT NULL");
    $stats['avg_duration'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_duration'], 1);
    
    return $stats;
}

// Анализ клонирования
function getCloneAnalysis() {
    $pdo = getDbConnection();
    
    $sql = "SELECT ad_media_hash, COUNT(*) as clone_count, 
            GROUP_CONCAT(DISTINCT advertiser) as advertisers,
            GROUP_CONCAT(DISTINCT region) as regions
            FROM campaigns 
            WHERE ad_media_hash IS NOT NULL AND ad_media_hash != ''
            GROUP BY ad_media_hash 
            HAVING clone_count > 1 
            ORDER BY clone_count DESC 
            LIMIT 20";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение топ рекламодателей
function getTopAdvertisers() {
    $pdo = getDbConnection();
    
    $sql = "SELECT advertiser, COUNT(*) as campaign_count 
            FROM campaigns 
            GROUP BY advertiser 
            ORDER BY campaign_count DESC 
            LIMIT 10";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение топ регионов
function getTopRegions() {
    $pdo = getDbConnection();
    
    $sql = "SELECT region, COUNT(*) as campaign_count 
            FROM campaigns 
            GROUP BY region 
            ORDER BY campaign_count DESC 
            LIMIT 10";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение данных для графика по времени
function getTimelineData() {
    $pdo = getDbConnection();
    
    $sql = "SELECT DATE(first_shown_at) as date, COUNT(*) as count 
            FROM campaigns 
            WHERE first_shown_at IS NOT NULL 
            GROUP BY DATE(first_shown_at) 
            ORDER BY date DESC 
            LIMIT 30";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Инициализация
createTable();

// Обработка POST запросов
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        try {
            $count = handleCsvUpload();
            if ($count) {
                $message = "Успешно загружено $count записей";
                $messageType = 'success';
            } else {
                $message = "Не удалось загрузить файл";
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = "Ошибка: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Получение данных для отображения
$search = $_GET['search'] ?? '';
$region = $_GET['region'] ?? '';
$advertiser = $_GET['advertiser'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$campaigns = getCampaignsData($limit, $offset, $search, $region, $advertiser);
$stats = getStats();
$cloneAnalysis = getCloneAnalysis();
$topAdvertisers = getTopAdvertisers();
$topRegions = getTopRegions();
$timelineData = getTimelineData();
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-800 mb-4">
            <i class="fas fa-chart-line text-blue-600"></i>
            Анализатор рекламных кампаний
        </h1>
        <p class="text-gray-600">Загрузите CSV файл с данными рекламных кампаний для анализа</p>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Загрузка файла -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-semibold mb-4"><i class="fas fa-upload text-blue-600"></i> Загрузка данных</h2>
        
        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="upload">
            
            <div class="upload-zone rounded-lg p-6 text-center">
                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-600 mb-4">Перетащите CSV файл сюда или нажмите для выбора</p>
                <input type="file" name="csv_file" accept=".csv" required 
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>
            
            <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-upload mr-2"></i>Загрузить данные
            </button>
        </form>
    </div>

    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stats-card rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-75">Всего кампаний</p>
                    <p class="text-3xl font-bold"><?php echo number_format($stats['total_campaigns']); ?></p>
                </div>
                <i class="fas fa-bullhorn text-3xl opacity-75"></i>
            </div>
        </div>
        
        <div class="stats-card rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-75">Рекламодателей</p>
                    <p class="text-3xl font-bold"><?php echo number_format($stats['total_advertisers']); ?></p>
                </div>
                <i class="fas fa-users text-3xl opacity-75"></i>
            </div>
        </div>
        
        <div class="stats-card rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-75">Регионов</p>
                    <p class="text-3xl font-bold"><?php echo number_format($stats['total_regions']); ?></p>
                </div>
                <i class="fas fa-globe text-3xl opacity-75"></i>
            </div>
        </div>
        
        <div class="stats-card rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-75">Средняя длительность</p>
                    <p class="text-3xl font-bold"><?php echo $stats['avg_duration']; ?> дн.</p>
                </div>
                <i class="fas fa-clock text-3xl opacity-75"></i>
            </div>
        </div>
    </div>

    <!-- Графики -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- График временной активности -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold mb-4">Активность по времени</h3>
            <div class="chart-container">
                <canvas id="timelineChart"></canvas>
            </div>
        </div>
        
        <!-- График топ рекламодателей -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold mb-4">Топ рекламодатели</h3>
            <div class="chart-container">
                <canvas id="advertisersChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Анализ клонирования -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-semibold mb-4"><i class="fas fa-copy text-yellow-600"></i> Анализ клонирования</h3>
        <?php if (!empty($cloneAnalysis)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Хэш медиа</th>
                            <th class="px-4 py-2 text-left">Количество клонов</th>
                            <th class="px-4 py-2 text-left">Рекламодатели</th>
                            <th class="px-4 py-2 text-left">Регионы</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cloneAnalysis as $clone): ?>
                            <tr class="border-b">
                                <td class="px-4 py-2 font-mono text-xs"><?php echo substr($clone['ad_media_hash'], 0, 16) . '...'; ?></td>
                                <td class="px-4 py-2">
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">
                                        <?php echo $clone['clone_count']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2"><?php echo substr($clone['advertisers'], 0, 50) . '...'; ?></td>
                                <td class="px-4 py-2"><?php echo $clone['regions']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500">Клонированные кампании не найдены</p>
        <?php endif; ?>
    </div>

    <!-- Фильтры -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-semibold mb-4"><i class="fas fa-filter text-green-600"></i> Фильтры</h3>
        
        <form method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Поиск</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Поиск по заголовку, описанию..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Регион</label>
                <select name="region" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Все регионы</option>
                    <?php foreach ($topRegions as $regionData): ?>
                        <option value="<?php echo htmlspecialchars($regionData['region']); ?>" 
                                <?php echo $region === $regionData['region'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($regionData['region']); ?> (<?php echo $regionData['campaign_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Рекламодатель</label>
                <select name="advertiser" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Все рекламодатели</option>
                    <?php foreach ($topAdvertisers as $advertiserData): ?>
                        <option value="<?php echo htmlspecialchars($advertiserData['advertiser']); ?>" 
                                <?php echo $advertiser === $advertiserData['advertiser'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($advertiserData['advertiser']); ?> (<?php echo $advertiserData['campaign_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="md:col-span-3">
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                    <i class="fas fa-search mr-2"></i>Применить фильтры
                </button>
                <a href="?" class="ml-2 bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Сбросить
                </a>
            </div>
        </form>
    </div>

    <!-- Таблица кампаний -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-xl font-semibold mb-4"><i class="fas fa-table text-purple-600"></i> Кампании</h3>
        
        <?php if (!empty($campaigns)): ?>
            <div class="table-container">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left">Рекламодатель</th>
                            <th class="px-4 py-2 text-left">Регион</th>
                            <th class="px-4 py-2 text-left">Заголовок</th>
                            <th class="px-4 py-2 text-left">Описание</th>
                            <th class="px-4 py-2 text-left">Тип медиа</th>
                            <th class="px-4 py-2 text-left">Период</th>
                            <th class="px-4 py-2 text-left">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($campaign['advertiser']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($campaign['region']); ?></td>
                                <td class="px-4 py-2 max-w-xs truncate"><?php echo htmlspecialchars($campaign['ad_title']); ?></td>
                                <td class="px-4 py-2 max-w-xs truncate"><?php echo htmlspecialchars($campaign['ad_description']); ?></td>
                                <td class="px-4 py-2">
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                        <?php echo htmlspecialchars($campaign['ad_media_type']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs">
                                    <?php echo $campaign['first_shown_at']; ?><br>
                                    <?php echo $campaign['last_shown_at']; ?>
                                </td>
                                <td class="px-4 py-2">
                                    <?php if ($campaign['target_url']): ?>
                                        <a href="<?php echo htmlspecialchars($campaign['target_url']); ?>" 
                                           target="_blank" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Пагинация -->
            <div class="mt-4 flex justify-center">
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&region=<?php echo urlencode($region); ?>&advertiser=<?php echo urlencode($advertiser); ?>" 
                           class="px-3 py-2 bg-gray-200 rounded-md hover:bg-gray-300">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <span class="px-3 py-2 bg-blue-600 text-white rounded-md">
                        Страница <?php echo $page; ?>
                    </span>
                    
                    <?php if (count($campaigns) === $limit): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&region=<?php echo urlencode($region); ?>&advertiser=<?php echo urlencode($advertiser); ?>" 
                           class="px-3 py-2 bg-gray-200 rounded-md hover:bg-gray-300">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-8">Нет данных для отображения. Загрузите CSV файл с кампаниями.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// График временной активности
const timelineData = <?php echo json_encode(array_reverse($timelineData)); ?>;
const timelineCtx = document.getElementById('timelineChart').getContext('2d');
new Chart(timelineCtx, {
    type: 'line',
    data: {
        labels: timelineData.map(item => item.date),
        datasets: [{
            label: 'Количество кампаний',
            data: timelineData.map(item => item.count),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Активность кампаний по дням'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// График топ рекламодателей
const advertisersData = <?php echo json_encode($topAdvertisers); ?>;
const advertisersCtx = document.getElementById('advertisersChart').getContext('2d');
new Chart(advertisersCtx, {
    type: 'bar',
    data: {
        labels: advertisersData.map(item => item.advertiser.substring(0, 20) + '...'),
        datasets: [{
            label: 'Количество кампаний',
            data: advertisersData.map(item => item.campaign_count),
            backgroundColor: 'rgba(34, 197, 94, 0.6)',
            borderColor: 'rgb(34, 197, 94)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Топ рекламодатели'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<script defer src="https://static.cloudflareinsights.com/beacon.min.js/vcd15cbe7772f49c399c6a5babf22c1241717689176015" integrity="sha512-ZpsOmlRQV6y907TI0dKBHq9Md29nnaEIPlkf84rnaERnq6zvWvPUqr2ft8M1aS28oN72PdrCzSjY4U6VaAw1EQ==" data-cf-beacon='{"rayId":"95f11be348d2363e","serverTiming":{"name":{"cfExtPri":true,"cfEdge":true,"cfOrigin":true,"cfL4":true,"cfSpeedBrain":true,"cfCacheStatus":true}},"version":"2025.6.2","token":"4edd5f8ec12a48cfa682ab8261b80a79"}' crossorigin="anonymous"></script>
</body>
</html>
    <script id="html_badge_script1">
        window.__genspark_remove_badge_link = "https://www.genspark.ai/api/html_badge/" +
            "remove_badge?token=To%2FBnjzloZ3UfQdcSaYfDocHnPnhTFXl03oA9YlVueFlXb9QcLbV%2Brccd1%2BmNJpHTqzC42F%2FtktPpU%2BUSBIOYJWGVKqcFJmGVHghNyUw9SKxJyVaBcEsgCHy%2Bs5wxyFyIGMGNF9k73Zn6GvaDETnk1OhHG2FP3wZSnPOM%2B4Z1Hy%2BpwYk43iE6qse%2BaoNKvah39G6j6FK9RrZbMe3J8J6vr%2B4qHRAzJoWexHTyHyP8Y0jtZTWcF01mxRJuaf%2BmEt6b3Q9nbFHFhuUKFLhjHTsGco9VPDqXN%2FbHZRKZwOeZK84DW1hqjGTWXr%2Btfd0iNTOHlxHyzO7nvmSKB2Z%2FCy%2BSrO0lgpbEliHwqnkPLzypfRO0sAsiN4LdwdyybrPjIoaGJ4Ohomdjogsrl0VQIcLc80rSovu9SCkp%2BfVzRQgdEM05p%2FKDgwFfUlUICnIVY%2B7mD7X0JaHw4%2BQ12O80ykwiOB3lTO%2BAWizzWg4huCGemHHoX5EdvTps5LiHwWO9gAJrPPtqo4jxgtATar2kBodyrLuefaBfQDhogc%2FKzBkRec%3D";
        window.__genspark_locale = "en-US";
        window.__genspark_token = "To/BnjzloZ3UfQdcSaYfDocHnPnhTFXl03oA9YlVueFlXb9QcLbV+rccd1+mNJpHTqzC42F/tktPpU+USBIOYJWGVKqcFJmGVHghNyUw9SKxJyVaBcEsgCHy+s5wxyFyIGMGNF9k73Zn6GvaDETnk1OhHG2FP3wZSnPOM+4Z1Hy+pwYk43iE6qse+aoNKvah39G6j6FK9RrZbMe3J8J6vr+4qHRAzJoWexHTyHyP8Y0jtZTWcF01mxRJuaf+mEt6b3Q9nbFHFhuUKFLhjHTsGco9VPDqXN/bHZRKZwOeZK84DW1hqjGTWXr+tfd0iNTOHlxHyzO7nvmSKB2Z/Cy+SrO0lgpbEliHwqnkPLzypfRO0sAsiN4LdwdyybrPjIoaGJ4Ohomdjogsrl0VQIcLc80rSovu9SCkp+fVzRQgdEM05p/KDgwFfUlUICnIVY+7mD7X0JaHw4+Q12O80ykwiOB3lTO+AWizzWg4huCGemHHoX5EdvTps5LiHwWO9gAJrPPtqo4jxgtATar2kBodyrLuefaBfQDhogc/KzBkRec=";
    </script>
    
    <script id="html_notice_dialog_script" src="https://www.genspark.ai/notice_dialog.js"></script>
    