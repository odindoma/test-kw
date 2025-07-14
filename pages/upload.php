<?php
session_start();
require_once '../config/database.php';

// Обработка сообщений
$message = '';
$messageType = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка данных - Анализ рекламных кампаний</title>
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
                        <a class="nav-link active" href="upload.php"><i class="fas fa-upload me-1"></i>Загрузка данных</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php"><i class="fas fa-chart-bar me-1"></i>Аналитика</a>
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
        <div id="alertContainer">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-upload text-primary me-2"></i>
                    Загрузка данных рекламных кампаний
                </h1>
                <p class="lead">
                    Загрузите CSV файл с данными рекламных кампаний Facebook для анализа.
                </p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-csv text-success me-2"></i>
                            Выберите CSV файл
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="upload-area" id="uploadArea">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h4>Перетащите файл сюда или нажмите для выбора</h4>
                                <p class="text-muted">
                                    Поддерживаются CSV файлы с разделением табуляцией<br>
                                    Максимальный размер файла: 50 МБ
                                </p>
                                <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display: none;">
                            </div>
                            
                            <div id="fileInfo" style="display: none;" class="mt-3">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-file me-2"></i>Выбранный файл:</h6>
                                    <p class="mb-1"><strong>Имя:</strong> <span id="fileName"></span></p>
                                    <p class="mb-0"><strong>Размер:</strong> <span id="fileSize"></span></p>
                                </div>
                            </div>
                            
                            <div id="progressContainer" style="display: none;" class="mt-3">
                                <label class="form-label">Прогресс загрузки:</label>
                                <div class="progress">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" id="uploadBtn" class="btn btn-primary" disabled>
                                    <i class="fas fa-upload me-1"></i>
                                    Загрузить файл
                                </button>
                                <button type="button" class="btn btn-secondary ms-2" onclick="location.reload()">
                                    <i class="fas fa-redo me-1"></i>
                                    Сбросить
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Требования к файлу
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6>Формат файла:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>CSV с разделением табуляцией</li>
                            <li><i class="fas fa-check text-success me-2"></i>Кодировка UTF-8</li>
                            <li><i class="fas fa-check text-success me-2"></i>Первая строка - заголовки</li>
                        </ul>
                        
                        <h6 class="mt-3">Обязательные колонки:</h6>
                        <ul class="list-unstyled small">
                            <li><code>Advertiser</code> - Рекламодатель</li>
                            <li><code>Resource ID</code> - ID ресурса (Page ID/Ad ID)</li>
                            <li><code>Region</code> - Регион</li>
                            <li><code>Campaign</code> - Название кампании</li>
                            <li><code>Ad Title</code> - Заголовок объявления</li>
                            <li><code>Ad Description</code> - Описание</li>
                            <li><code>Ad Media Type</code> - Тип медиа</li>
                            <li><code>Ad Media Hash</code> - Хеш медиа</li>
                            <li><code>Target URL</code> - Целевая ссылка</li>
                            <li><code>First Shown At</code> - Первый показ</li>
                            <li><code>Last Shown At</code> - Последний показ</li>
                        </ul>
                        
                        <div class="alert alert-warning mt-3">
                            <small>
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Важно:</strong> Resource ID должен содержать Page ID и Ad ID, 
                                разделенные символом "/" (например: 123456789/987654321)
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history text-warning me-2"></i>
                            Последние загрузки
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $db = Database::getInstance();
                            $recentUploads = $db->fetchAll(
                                "SELECT 
                                    COUNT(*) as count,
                                    advertiser,
                                    MAX(created_at) as last_upload
                                FROM campaigns 
                                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                GROUP BY advertiser 
                                ORDER BY last_upload DESC 
                                LIMIT 5"
                            );
                            
                            if (!empty($recentUploads)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentUploads as $upload): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($upload['advertiser']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo number_format($upload['count']); ?> записей
                                                    </small>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('d.m.Y H:i', strtotime($upload['last_upload'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Нет недавних загрузок
                                </p>
                            <?php endif;
                        } catch (Exception $e) {
                            echo '<p class="text-danger mb-0">Ошибка загрузки истории</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>

