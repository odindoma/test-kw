<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анализатор рекламных кампаний с MySQL</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .upload-area {
            border: 2px dashed #cbd5e0;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #4299e1;
            background-color: #f7fafc;
        }
        .loading {
            display: none;
        }
        .progress-bar {
            height: 4px;
            background: #4299e1;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<?php
// Database configuration
$db_config = [
    'host' => 'localhost',
    'username' => 'homestead',
    'password' => 'secret',
    'database' => 'test_ad_campaigns'
];

// Database connection
function getConnection() {
    global $db_config;
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['database']};charset=utf8",
            $db_config['username'],
            $db_config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        return false;
    }
}

// Create database and tables if they don't exist
function initializeDatabase() {
    global $db_config;
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};charset=utf8",
            $db_config['username'],
            $db_config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$db_config['database']} CHARACTER SET utf8 COLLATE utf8_general_ci");
        $pdo->exec("USE {$db_config['database']}");
        
        // Create campaigns table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                advertiser VARCHAR(255) NOT NULL,
                resource_id VARCHAR(255) NOT NULL,
                region VARCHAR(100) NOT NULL,
                campaign_name TEXT NOT NULL,
                ad_title TEXT,
                ad_description TEXT,
                ad_media_type VARCHAR(50),
                ad_media_hash VARCHAR(255),
                target_url TEXT,
                first_shown_at DATE,
                last_shown_at DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_advertiser (advertiser),
                INDEX idx_region (region),
                INDEX idx_dates (first_shown_at, last_shown_at),
                INDEX idx_media_hash (ad_media_hash)
            )
        ");
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $response = ['success' => false, 'message' => '', 'records' => 0];
    
    if (!initializeDatabase()) {
        $response['message'] = 'Ошибка инициализации базы данных';
        echo json_encode($response);
        exit;
    }
    
    $pdo = getConnection();
    if (!$pdo) {
        $response['message'] = 'Ошибка подключения к базе данных';
        echo json_encode($response);
        exit;
    }
    
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmp_name, 'r');
        
        if ($handle !== false) {
            // Skip header row
            $header = fgetcsv($handle);
            
            $stmt = $pdo->prepare("
                INSERT INTO campaigns (
                    advertiser, resource_id, region, campaign_name, ad_title, 
                    ad_description, ad_media_type, ad_media_hash, target_url, 
                    first_shown_at, last_shown_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $records = 0;
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 11) {
                    $first_shown = !empty($data[9]) ? date('Y-m-d', strtotime($data[9])) : null;
                    $last_shown = !empty($data[10]) ? date('Y-m-d', strtotime($data[10])) : null;
                    
                    $stmt->execute([
                        $data[0], $data[1], $data[2], $data[3], $data[4],
                        $data[5], $data[6], $data[7], $data[8], 
                        $first_shown, $last_shown
                    ]);
                    $records++;
                }
            }
            
            fclose($handle);
            $response['success'] = true;
            $response['records'] = $records;
            $response['message'] = "Загружено {$records} записей";
        } else {
            $response['message'] = 'Ошибка чтения файла';
        }
    } else {
        $response['message'] = 'Ошибка загрузки файла';
    }
    
    echo json_encode($response);
    exit;
}

// Get statistics
function getStatistics() {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $stats = [];
    
    // Total campaigns
    $stmt = $pdo->query("SELECT COUNT(*) FROM campaigns");
    $stats['total_campaigns'] = $stmt->fetchColumn();
    
    // Unique advertisers
    $stmt = $pdo->query("SELECT COUNT(DISTINCT advertiser) FROM campaigns");
    $stats['unique_advertisers'] = $stmt->fetchColumn();
    
    // Active regions
    $stmt = $pdo->query("SELECT COUNT(DISTINCT region) FROM campaigns");
    $stats['active_regions'] = $stmt->fetchColumn();
    
    // Average campaign duration
    $stmt = $pdo->query("
        SELECT AVG(DATEDIFF(last_shown_at, first_shown_at)) as avg_duration 
        FROM campaigns 
        WHERE first_shown_at IS NOT NULL AND last_shown_at IS NOT NULL
    ");
    $stats['avg_duration'] = round($stmt->fetchColumn() ?: 0, 1);
    
    return $stats;
}

// Get campaigns data for analysis
function getCampaignsData($limit = 100, $offset = 0, $search = '') {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $where = '';
    $params = [];
    
    if (!empty($search)) {
        $where = "WHERE advertiser LIKE ? OR campaign_name LIKE ? OR ad_title LIKE ?";
        $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM campaigns 
        {$where}
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get cloning analysis
function getCloningAnalysis() {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    // Find campaigns with same media hash (potential clones)
    $stmt = $pdo->query("
        SELECT ad_media_hash, COUNT(*) as clone_count, 
               GROUP_CONCAT(DISTINCT advertiser) as advertisers,
               GROUP_CONCAT(DISTINCT region) as regions
        FROM campaigns 
        WHERE ad_media_hash IS NOT NULL AND ad_media_hash != ''
        GROUP BY ad_media_hash 
        HAVING COUNT(*) > 1
        ORDER BY clone_count DESC
        LIMIT 20
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get campaign trends
function getCampaignTrends() {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $stmt = $pdo->query("
        SELECT DATE(first_shown_at) as date, COUNT(*) as campaigns_count
        FROM campaigns 
        WHERE first_shown_at IS NOT NULL
        GROUP BY DATE(first_shown_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get top advertisers
function getTopAdvertisers() {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $stmt = $pdo->query("
        SELECT advertiser, COUNT(*) as campaigns_count,
               COUNT(DISTINCT region) as regions_count,
               MIN(first_shown_at) as first_campaign,
               MAX(last_shown_at) as last_campaign
        FROM campaigns 
        GROUP BY advertiser
        ORDER BY campaigns_count DESC
        LIMIT 10
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Initialize database
initializeDatabase();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-800 mb-2">
            <i class="fas fa-chart-line mr-3"></i>
            Анализатор рекламных кампаний
        </h1>
        <p class="text-gray-600">Анализ рекламных данных с MySQL базой данных</p>
    </div>

    <!-- Upload Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-2xl font-semibold mb-4">
            <i class="fas fa-upload mr-2"></i>
            Загрузка данных
        </h2>
        
        <div class="upload-area rounded-lg p-8 text-center" id="uploadArea">
            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-600 mb-4">Перетащите CSV файл сюда или нажмите для выбора</p>
            <input type="file" id="csvFile" accept=".csv" class="hidden">
            <button type="button" id="selectFile" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                Выбрать файл
            </button>
        </div>
        
        <div class="loading mt-4" id="loadingDiv">
            <div class="bg-gray-200 rounded-full h-2 mb-2">
                <div class="progress-bar rounded-full h-2" id="progressBar" style="width: 0%"></div>
            </div>
            <p class="text-sm text-gray-600">Загрузка данных...</p>
        </div>
        
        <div id="uploadResult" class="mt-4"></div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <?php
        $stats = getStatistics();
        if ($stats):
        ?>
        <div class="stats-card rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-75">Всего кампаний</p>
                    <p class="text-2xl font-bold"><?= number_format($stats['total_campaigns']) ?></p>
                </div>
                <i class="fas fa-bullhorn text-3xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-75">Рекламодателей</p>
                    <p class="text-2xl font-bold"><?= number_format($stats['unique_advertisers']) ?></p>
                </div>
                <i class="fas fa-building text-3xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-75">Активных регионов</p>
                    <p class="text-2xl font-bold"><?= number_format($stats['active_regions']) ?></p>
                </div>
                <i class="fas fa-globe text-3xl opacity-75"></i>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-75">Средняя длительность</p>
                    <p class="text-2xl font-bold"><?= $stats['avg_duration'] ?> дней</p>
                </div>
                <i class="fas fa-clock text-3xl opacity-75"></i>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Campaign Trends -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold mb-4">
                <i class="fas fa-chart-line mr-2"></i>
                Тренды кампаний
            </h3>
            <div class="chart-container">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>

        <!-- Top Advertisers -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold mb-4">
                <i class="fas fa-trophy mr-2"></i>
                Топ рекламодателей
            </h3>
            <div class="chart-container">
                <canvas id="advertisersChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Cloning Analysis -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-semibold mb-4">
            <i class="fas fa-copy mr-2"></i>
            Анализ клонирования
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Хеш медиа</th>
                        <th class="px-4 py-2 text-left">Количество клонов</th>
                        <th class="px-4 py-2 text-left">Рекламодатели</th>
                        <th class="px-4 py-2 text-left">Регионы</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cloning_data = getCloningAnalysis();
                    if ($cloning_data):
                        foreach ($cloning_data as $row):
                    ?>
                    <tr class="border-b">
                        <td class="px-4 py-2 font-mono text-xs"><?= substr($row['ad_media_hash'], 0, 20) ?>...</td>
                        <td class="px-4 py-2">
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded"><?= $row['clone_count'] ?></span>
                        </td>
                        <td class="px-4 py-2"><?= substr($row['advertisers'], 0, 50) ?><?= strlen($row['advertisers']) > 50 ? '...' : '' ?></td>
                        <td class="px-4 py-2"><?= $row['regions'] ?></td>
                    </tr>
                    <?php 
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">
                <i class="fas fa-table mr-2"></i>
                Данные кампаний
            </h3>
            <div class="flex items-center space-x-4">
                <input type="text" id="searchInput" placeholder="Поиск..." 
                       class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button id="searchBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Рекламодатель</th>
                        <th class="px-4 py-2 text-left">Регион</th>
                        <th class="px-4 py-2 text-left">Кампания</th>
                        <th class="px-4 py-2 text-left">Заголовок</th>
                        <th class="px-4 py-2 text-left">Тип медиа</th>
                        <th class="px-4 py-2 text-left">Период</th>
                    </tr>
                </thead>
                <tbody id="campaignsTableBody">
                    <?php
                    $campaigns = getCampaignsData(50, 0);
                    if ($campaigns):
                        foreach ($campaigns as $campaign):
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium"><?= htmlspecialchars($campaign['advertiser']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($campaign['region']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars(substr($campaign['campaign_name'], 0, 40)) ?>...</td>
                        <td class="px-4 py-2"><?= htmlspecialchars(substr($campaign['ad_title'], 0, 30)) ?><?= strlen($campaign['ad_title']) > 30 ? '...' : '' ?></td>
                        <td class="px-4 py-2">
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                <?= htmlspecialchars($campaign['ad_media_type']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs">
                            <?= $campaign['first_shown_at'] ? date('d.m.Y', strtotime($campaign['first_shown_at'])) : '-' ?>
                            <br>
                            <?= $campaign['last_shown_at'] ? date('d.m.Y', strtotime($campaign['last_shown_at'])) : '-' ?>
                        </td>
                    </tr>
                    <?php 
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// File upload functionality
document.getElementById('selectFile').addEventListener('click', function() {
    document.getElementById('csvFile').click();
});

document.getElementById('csvFile').addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
        uploadFile(e.target.files[0]);
    }
});

// Drag and drop functionality
const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadArea.style.borderColor = '#4299e1';
    uploadArea.style.backgroundColor = '#f7fafc';
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    uploadArea.style.borderColor = '#cbd5e0';
    uploadArea.style.backgroundColor = 'transparent';
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadArea.style.borderColor = '#cbd5e0';
    uploadArea.style.backgroundColor = 'transparent';
    
    const files = e.dataTransfer.files;
    if (files.length > 0 && files[0].type === 'text/csv') {
        uploadFile(files[0]);
    }
});

function uploadFile(file) {
    const formData = new FormData();
    formData.append('csv_file', file);
    
    const loadingDiv = document.getElementById('loadingDiv');
    const progressBar = document.getElementById('progressBar');
    const resultDiv = document.getElementById('uploadResult');
    
    loadingDiv.style.display = 'block';
    progressBar.style.width = '0%';
    
    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += 10;
        progressBar.style.width = progress + '%';
        if (progress >= 90) {
            clearInterval(progressInterval);
        }
    }, 100);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        
        setTimeout(() => {
            loadingDiv.style.display = 'none';
            
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        <i class="fas fa-check-circle mr-2"></i>
                        ${data.message}
                    </div>
                `;
                // Refresh page after successful upload
                setTimeout(() => location.reload(), 2000);
            } else {
                resultDiv.innerHTML = `
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        ${data.message}
                    </div>
                `;
            }
        }, 500);
    })
    .catch(error => {
        clearInterval(progressInterval);
        loadingDiv.style.display = 'none';
        resultDiv.innerHTML = `
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i>
                Ошибка загрузки файла
            </div>
        `;
    });
}

// Initialize charts
<?php if ($stats && $stats['total_campaigns'] > 0): ?>
// Trends chart
const trendsData = <?= json_encode(array_reverse(getCampaignTrends() ?: [])) ?>;
const trendsChart = new Chart(document.getElementById('trendsChart'), {
    type: 'line',
    data: {
        labels: trendsData.map(item => item.date),
        datasets: [{
            label: 'Количество кампаний',
            data: trendsData.map(item => item.campaigns_count),
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66, 153, 225, 0.1)',
            tension: 0.4
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

// Advertisers chart
const advertisersData = <?= json_encode(getTopAdvertisers() ?: []) ?>;
const advertisersChart = new Chart(document.getElementById('advertisersChart'), {
    type: 'bar',
    data: {
        labels: advertisersData.map(item => item.advertiser.length > 15 ? item.advertiser.substring(0, 15) + '...' : item.advertiser),
        datasets: [{
            label: 'Количество кампаний',
            data: advertisersData.map(item => item.campaigns_count),
            backgroundColor: [
                '#4299e1', '#48bb78', '#ed8936', '#9f7aea', '#f56565',
                '#38b2ac', '#d69e2e', '#667eea', '#f093fb', '#4fd1c7'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>

// Search functionality
document.getElementById('searchBtn').addEventListener('click', function() {
    const searchTerm = document.getElementById('searchInput').value;
    // Here you would typically make an AJAX request to search
    // For now, we'll just reload the page
    if (searchTerm) {
        window.location.href = `?search=${encodeURIComponent(searchTerm)}`;
    }
});

document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('searchBtn').click();
    }
});
</script>

<script defer src="https://static.cloudflareinsights.com/beacon.min.js/vcd15cbe7772f49c399c6a5babf22c1241717689176015" integrity="sha512-ZpsOmlRQV6y907TI0dKBHq9Md29nnaEIPlkf84rnaERnq6zvWvPUqr2ft8M1aS28oN72PdrCzSjY4U6VaAw1EQ==" data-cf-beacon='{"rayId":"95ea61635a534d82","serverTiming":{"name":{"cfExtPri":true,"cfEdge":true,"cfOrigin":true,"cfL4":true,"cfSpeedBrain":true,"cfCacheStatus":true}},"version":"2025.6.2","token":"4edd5f8ec12a48cfa682ab8261b80a79"}' crossorigin="anonymous"></script>
</body>
</html>
    <script id="html_badge_script1">
        window.__genspark_remove_badge_link = "https://www.genspark.ai/api/html_badge/" +
            "remove_badge?token=To%2FBnjzloZ3UfQdcSaYfDszAxL%2FwKGwhLQIHvhIvq6YXggNNZTC73GBFTd%2Fp1d4HdfUBsAdCrLBgvOKhje13NS4r04vO04G2ukkLUXUhtVqxOCpf%2FBO90qDsOXAOXRNcLrEz3nVautOizNCwLGju%2BVlVsUH251L8I8uA6AYDgbrplgkyWdHrp7sa2YojkaWmXa5FAXfdsvEamq8xGR2t6bQGm9wN6voJndVSF868jeAmZVZoMuWlKNp%2F1SMVeBp%2Bq6gy%2FKGg7WA9zoJPRiWr70UZtZ3LsPIHXSwB3xjHrsgP2Be4d4LHncAtntGo9WOgM68kDzjIH2w0GlRgC3ANOMCKs7Kuk3t%2F6STpwJYkDcB1%2Fsv6ibztAwAFhq2YHtgdaPX8RAsneqjy%2BI2IwXBh18xQjPRnBUYI7pnsgVDwAR%2FzFM%2FHzq2t8sUqxFOPApfsEzDbOhfBLD26tWPLJgc3N2jddfAxA5q9Si41%2FCj5fjokSMheKnpsHSB8Lz5zMUHBhXUbjDI%2Ffu9UF5ALxeptJpKksAVrrjcsboi3BsWo2o0%3D";
        window.__genspark_locale = "en-US";
        window.__genspark_token = "To/BnjzloZ3UfQdcSaYfDszAxL/wKGwhLQIHvhIvq6YXggNNZTC73GBFTd/p1d4HdfUBsAdCrLBgvOKhje13NS4r04vO04G2ukkLUXUhtVqxOCpf/BO90qDsOXAOXRNcLrEz3nVautOizNCwLGju+VlVsUH251L8I8uA6AYDgbrplgkyWdHrp7sa2YojkaWmXa5FAXfdsvEamq8xGR2t6bQGm9wN6voJndVSF868jeAmZVZoMuWlKNp/1SMVeBp+q6gy/KGg7WA9zoJPRiWr70UZtZ3LsPIHXSwB3xjHrsgP2Be4d4LHncAtntGo9WOgM68kDzjIH2w0GlRgC3ANOMCKs7Kuk3t/6STpwJYkDcB1/sv6ibztAwAFhq2YHtgdaPX8RAsneqjy+I2IwXBh18xQjPRnBUYI7pnsgVDwAR/zFM/Hzq2t8sUqxFOPApfsEzDbOhfBLD26tWPLJgc3N2jddfAxA5q9Si41/Cj5fjokSMheKnpsHSB8Lz5zMUHBhXUbjDI/fu9UF5ALxeptJpKksAVrrjcsboi3BsWo2o0=";
    </script>
    
    <script id="html_notice_dialog_script" src="https://www.genspark.ai/notice_dialog.js"></script>
    