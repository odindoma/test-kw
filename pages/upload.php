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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/upload.css" rel="stylesheet">
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
                        <a class="nav-link active" href="upload.php"><i class="fas fa-upload me-1"></i>Загрузка</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php"><i class="fas fa-chart-bar me-1"></i>Аналитика</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="campaigns.php"><i class="fas fa-bullhorn me-1"></i>Кампании</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages_analysis.php"><i class="fas fa-facebook me-1"></i>Страницы</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Сообщения -->
        <div id="alertContainer">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="upload-container">
                    <div class="text-center mb-4">
                        <h2 class="mb-3">
                            <i class="fas fa-upload text-primary me-2"></i>
                            Загрузка CSV файла
                        </h2>
                        <p class="text-muted">
                            Выберите CSV файл с данными рекламных кампаний Facebook для анализа
                        </p>
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data" method="post">
                        <div class="file-input-wrapper">
                            <input type="file" id="csvFile" name="csv_file" accept=".csv" required>
                            <label for="csvFile" class="file-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span class="label-text">Выбрать CSV файл</span>
                                <span class="file-name"></span>
                            </label>
                        </div>

                        <button type="submit" class="btn-upload" id="uploadBtn">
                            <i class="fas fa-upload"></i>
                            Загрузить файл
                        </button>

                        <!-- Статус загрузки -->
                        <div id="uploadStatus" class="upload-status" style="display: none;">
                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                            Обработка файла...
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="info-card">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle text-info me-2"></i>
                        Требования к файлу
                    </h5>
                    <ul class="requirements-list">
                        <li><i class="fas fa-check text-success me-2"></i>Формат: CSV</li>
                        <li><i class="fas fa-check text-success me-2"></i>Разделитель: табуляция</li>
                        <li><i class="fas fa-check text-success me-2"></i>Кодировка: UTF-8</li>
                        <li><i class="fas fa-check text-success me-2"></i>Размер: до 50 МБ</li>
                    </ul>

                    <div class="mt-3">
                        <h6>Обязательные колонки:</h6>
                        <div class="columns-list">
                            <code>Advertiser</code>, <code>Resource ID</code>, <code>Region</code>, 
                            <code>Campaign</code>, <code>Ad Title</code>, <code>Ad Description</code>, 
                            <code>Ad Media Type</code>, <code>Ad Media Hash</code>, <code>Target URL</code>, 
                            <code>First Shown At</code>, <code>Last Shown At</code>
                        </div>
                    </div>

                    <div class="mt-3">
                        <a href="analytics.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-chart-bar me-1"></i>
                            Перейти к аналитике
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/upload.js"></script>
</body>
</html>
