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
        .upload-area {
            border: 2px dashed #cbd5e0;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #4299e1;
            background-color: #f7fafc;
        }
    </style>
</head>
<body class="bg-gray-100">

<?php
// Database configuration
$host = 'localhost';
$dbname = 'test_ad_campaigns';
$username = 'homestead';
$password = 'secret';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
function getDBConnection() {
    global $host, $dbname, $username, $password;
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Create tables if they don't exist
function createTables() {
    $pdo = getDBConnection();
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
    )";
    
    $pdo->exec($sql);
}

// Safe round function for PHP 8.1
function safeRound($value, $precision = 0) {
    if ($value === null || $value === '') {
        return 0;
    }
    return round(floatval($value), $precision);
}

// Safe division function
function safeDivision($numerator, $denominator) {
    if ($denominator === null || $denominator === 0) {
        return 0;
    }
    return $numerator / $denominator;
}

// Upload CSV file
function uploadCSV() {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        return "Error uploading file";
    }
    
    $file = $_FILES['csv_file']['tmp_name'];
    $pdo = getDBConnection();
    
    // Clear existing data
    $pdo->exec("DELETE FROM campaigns");
    
    $handle = fopen($file, 'r');
    if ($handle === false) {
        return "Error opening file";
    }
    
    // Skip header row
    $header = fgetcsv($handle);
    
    $count = 0;
    $stmt = $pdo->prepare("INSERT INTO campaigns (advertiser, resource_id, region, campaign_name, ad_title, ad_description, ad_media_type, ad_media_hash, target_url, first_shown_at, last_shown_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 11) {
            // Parse dates
            $first_shown = !empty($data[9]) ? date('Y-m-d', strtotime($data[9])) : null;
            $last_shown = !empty($data[10]) ? date('Y-m-d', strtotime($data[10])) : null;
            
            $stmt->execute([
                $data[0], // advertiser
                $data[1], // resource_id
                $data[2], // region
                $data[3], // campaign_name
                $data[4], // ad_title
                $data[5], // ad_description
                $data[6], // ad_media_type
                $data[7], // ad_media_hash
                $data[8], // target_url
                $first_shown,
                $last_shown
            ]);
            $count++;
        }
    }
    
    fclose($handle);
    return "Successfully uploaded $count records";
}

// Get campaigns data with pagination
function getCampaignsData($limit = 50, $offset = 0, $search = '') {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM campaigns";
    
    if ($search) {
        $sql .= " WHERE advertiser LIKE ? OR ad_title LIKE ? OR region LIKE ?";
        $searchParam = "%$search%";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    
    $stmt = $pdo->prepare($sql);
    
    if ($search) {
        $stmt->execute([$searchParam, $searchParam, $searchParam]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
function getStatistics() {
    $pdo = getDBConnection();
    
    $stats = [];
    
    // Total campaigns
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM campaigns");
    $stats['total_campaigns'] = $stmt->fetchColumn();
    
    // Unique advertisers
    $stmt = $pdo->query("SELECT COUNT(DISTINCT advertiser) as unique_advertisers FROM campaigns");
    $stats['unique_advertisers'] = $stmt->fetchColumn();
    
    // Unique regions
    $stmt = $pdo->query("SELECT COUNT(DISTINCT region) as unique_regions FROM campaigns");
    $stats['unique_regions'] = $stmt->fetchColumn();
    
    // Average campaign duration
    $stmt = $pdo->query("SELECT AVG(DATEDIFF(last_shown_at, first_shown_at)) as avg_duration FROM campaigns WHERE first_shown_at IS NOT NULL AND last_shown_at IS NOT NULL");
    $avg_duration = $stmt->fetchColumn();
    $stats['avg_duration'] = safeRound($avg_duration, 1);
    
    return $stats;
}

// Get clone analysis
function getCloneAnalysis() {
    $pdo = getDBConnection();
    
    $sql = "SELECT ad_media_hash, COUNT(*) as clone_count, GROUP_CONCAT(DISTINCT advertiser) as advertisers
            FROM campaigns 
            WHERE ad_media_hash IS NOT NULL AND ad_media_hash != ''
            GROUP BY ad_media_hash 
            HAVING clone_count > 1
            ORDER BY clone_count DESC
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get campaign growth data
function getCampaignGrowthData() {
    $pdo = getDBConnection();
    
    $sql = "SELECT DATE(first_shown_at) as date, COUNT(*) as count
            FROM campaigns 
            WHERE first_shown_at IS NOT NULL
            GROUP BY DATE(first_shown_at)
            ORDER BY date";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get region distribution
function getRegionDistribution() {
    $pdo = getDBConnection();
    
    $sql = "SELECT region, COUNT(*) as count
            FROM campaigns 
            WHERE region IS NOT NULL AND region != ''
            GROUP BY region
            ORDER BY count DESC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get advertiser activity
function getAdvertiserActivity() {
    $pdo = getDBConnection();
    
    $sql = "SELECT advertiser, COUNT(*) as campaign_count,
            MIN(first_shown_at) as first_campaign,
            MAX(last_shown_at) as last_campaign,
            COUNT(DISTINCT ad_media_hash) as unique_creatives
            FROM campaigns 
            WHERE advertiser IS NOT NULL AND advertiser != ''
            GROUP BY advertiser
            ORDER BY campaign_count DESC
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Initialize database
createTables();

// Handle file upload
$upload_message = '';
if (isset($_POST['upload_csv'])) {
    $upload_message = uploadCSV();
}

// Get current data
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';

$campaigns = getCampaignsData($limit, $offset, $search);
$stats = getStatistics();
$clone_analysis = getCloneAnalysis();
$growth_data = getCampaignGrowthData();
$region_data = getRegionDistribution();
$advertiser_activity = getAdvertiserActivity();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-4xl font-bold text-gray-800 mb-8">
        <i class="fas fa-chart-line mr-3"></i>
        Анализатор рекламных кампаний
    </h1>
    
    <!-- Upload Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold mb-4">
            <i class="fas fa-upload mr-2"></i>
            Загрузка данных
        </h2>
        
        <form method="post" enctype="multipart/form-data">
            <div class="upload-area p-8 rounded-lg text-center">
                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                <p class="text-lg text-gray-600 mb-4">Загрузите CSV файл с данными рекламных кампаний</p>
                <input type="file" name="csv_file" accept=".csv" class="mb-4" required>
                <div>
                    <button type="submit" name="upload_csv" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                        <i class="fas fa-upload mr-2"></i>
                        Загрузить
                    </button>
                </div>
            </div>
        </form>
        
        <?php if ($upload_message): ?>
            <div class="mt-4 p-4 bg-green-100 text-green-800 rounded-lg">
                <?php echo htmlspecialchars($upload_message); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="fas fa-bullhorn text-blue-500 text-2xl mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold">Всего кампаний</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo number_format($stats['total_campaigns']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="fas fa-users text-green-500 text-2xl mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold">Рекламодателей</h3>
                    <p class="text-3xl font-bold text-green-600"><?php echo number_format($stats['unique_advertisers']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="fas fa-globe text-purple-500 text-2xl mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold">Регионов</h3>
                    <p class="text-3xl font-bold text-purple-600"><?php echo number_format($stats['unique_regions']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="fas fa-clock text-orange-500 text-2xl mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold">Средняя длительность</h3>
                    <p class="text-3xl font-bold text-orange-600"><?php echo $stats['avg_duration']; ?> дней</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Campaign Growth Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-chart-line mr-2"></i>
                Рост кампаний по времени
            </h3>
            <div class="chart-container">
                <canvas id="growthChart"></canvas>
            </div>
        </div>
        
        <!-- Region Distribution Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-chart-pie mr-2"></i>
                Распределение по регионам
            </h3>
            <div class="chart-container">
                <canvas id="regionChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Clone Analysis -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold mb-4">
            <i class="fas fa-copy mr-2"></i>
            Анализ клонирования
        </h2>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Хеш медиа</th>
                        <th class="px-4 py-2 text-left">Количество клонов</th>
                        <th class="px-4 py-2 text-left">Рекламодатели</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clone_analysis as $clone): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2 font-mono text-sm"><?php echo substr(htmlspecialchars($clone['ad_media_hash']), 0, 20) . '...'; ?></td>
                            <td class="px-4 py-2">
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded">
                                    <?php echo $clone['clone_count']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($clone['advertisers']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Advertiser Activity -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-bold mb-4">
            <i class="fas fa-chart-bar mr-2"></i>
            Активность рекламодателей
        </h2>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Рекламодатель</th>
                        <th class="px-4 py-2 text-left">Кампаний</th>
                        <th class="px-4 py-2 text-left">Уникальных креативов</th>
                        <th class="px-4 py-2 text-left">Первая кампания</th>
                        <th class="px-4 py-2 text-left">Последняя кампания</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advertiser_activity as $advertiser): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2 font-semibold"><?php echo htmlspecialchars($advertiser['advertiser']); ?></td>
                            <td class="px-4 py-2"><?php echo $advertiser['campaign_count']; ?></td>
                            <td class="px-4 py-2"><?php echo $advertiser['unique_creatives']; ?></td>
                            <td class="px-4 py-2"><?php echo $advertiser['first_campaign']; ?></td>
                            <td class="px-4 py-2"><?php echo $advertiser['last_campaign']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Campaign List -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold mb-4">
            <i class="fas fa-list mr-2"></i>
            Список кампаний
        </h2>
        
        <!-- Search -->
        <form method="get" class="mb-4">
            <div class="flex gap-2">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Поиск по рекламодателю, заголовку или региону" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Рекламодатель</th>
                        <th class="px-4 py-2 text-left">Регион</th>
                        <th class="px-4 py-2 text-left">Заголовок</th>
                        <th class="px-4 py-2 text-left">Тип медиа</th>
                        <th class="px-4 py-2 text-left">Период показа</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?php echo htmlspecialchars($campaign['advertiser']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($campaign['region']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars(substr($campaign['ad_title'], 0, 50)) . '...'; ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($campaign['ad_media_type']); ?></td>
                            <td class="px-4 py-2"><?php echo $campaign['first_shown_at'] . ' - ' . $campaign['last_shown_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Campaign Growth Chart
const growthCtx = document.getElementById('growthChart').getContext('2d');
const growthData = <?php echo json_encode($growth_data); ?>;

new Chart(growthCtx, {
    type: 'line',
    data: {
        labels: growthData.map(item => item.date),
        datasets: [{
            label: 'Новые кампании',
            data: growthData.map(item => item.count),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Region Distribution Chart
const regionCtx = document.getElementById('regionChart').getContext('2d');
const regionData = <?php echo json_encode($region_data); ?>;

new Chart(regionCtx, {
    type: 'doughnut',
    data: {
        labels: regionData.map(item => item.region),
        datasets: [{
            data: regionData.map(item => item.count),
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
                '#FF9F40',
                '#FF6384',
                '#C9CBCF',
                '#4BC0C0',
                '#FF6384'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<script defer src="https://static.cloudflareinsights.com/beacon.min.js/vcd15cbe7772f49c399c6a5babf22c1241717689176015" integrity="sha512-ZpsOmlRQV6y907TI0dKBHq9Md29nnaEIPlkf84rnaERnq6zvWvPUqr2ft8M1aS28oN72PdrCzSjY4U6VaAw1EQ==" data-cf-beacon='{"rayId":"95f129bf4b04363e","serverTiming":{"name":{"cfExtPri":true,"cfEdge":true,"cfOrigin":true,"cfL4":true,"cfSpeedBrain":true,"cfCacheStatus":true}},"version":"2025.6.2","token":"4edd5f8ec12a48cfa682ab8261b80a79"}' crossorigin="anonymous"></script>
</body>
</html>