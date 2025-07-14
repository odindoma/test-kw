<?php
session_start();
require_once 'config/database.php';
require_once 'includes/CampaignAnalyzer.php';

// Обработка сообщений
$message = '';
$messageType = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}

try {
    $analyzer = new CampaignAnalyzer();
    $db = Database::getInstance();
    
    // Получаем общую статистику
    $totalCampaigns = $db->count("SELECT COUNT(*) FROM campaigns");
    $totalAdvertisers = $db->count("SELECT COUNT(DISTINCT advertiser) FROM campaigns");
    $totalPages = $db->count("SELECT COUNT(DISTINCT page_id) FROM campaigns WHERE page_id != ''");
    $totalUniqueCreatives = $db->count("SELECT COUNT(DISTINCT ad_media_hash) FROM campaigns WHERE ad_media_hash != ''");
    
} catch (Exception $e) {
    $message = 'Ошибка подключения к базе данных: ' . $e->getMessage();
    $messageType = 'error';
    $totalCampaigns = $totalAdvertisers = $totalPages = $totalUniqueCreatives = 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анализ рекламных кампаний Facebook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i>
                Анализ рекламных кампаний
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/upload.php"><i class="fas fa-upload me-1"></i>Загрузка данных</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/analytics.php"><i class="fas fa-chart-bar me-1"></i>Аналитика</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/campaigns.php"><i class="fas fa-list me-1"></i>Кампании</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/pages_analysis.php"><i class="fas fa-facebook me-1"></i>Анализ страниц</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-chart-line text-primary me-2"></i>
                    Анализ рекламных кампаний Facebook
                </h1>
                <p class="lead">
                    Система для анализа данных рекламных кампаний, изучения уникальности креативов, 
                    клонирования кампаний и использования страниц Facebook.
                </p>
            </div>
        </div>

        <!-- Статистика -->
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
                                <h4 class="card-title"><?php echo number_format($totalAdvertisers); ?></h4>
                                <p class="card-text">Рекламодателей</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
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
                                <h4 class="card-title"><?php echo number_format($totalPages); ?></h4>
                                <p class="card-text">Страниц Facebook</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-facebook fa-2x"></i>
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
                                <h4 class="card-title"><?php echo number_format($totalUniqueCreatives); ?></h4>
                                <p class="card-text">Уникальных креативов</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-images fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Быстрые действия -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-upload text-primary me-2"></i>
                            Загрузка данных
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Загрузите CSV файл с данными рекламных кампаний для анализа. 
                            Поддерживается формат с разделением табуляцией.
                        </p>
                        <a href="pages/upload.php" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>
                            Загрузить файл
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-bar text-success me-2"></i>
                            Аналитика
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Изучите уникальность креативов, клонирование кампаний, 
                            активность страниц и другие аналитические данные.
                        </p>
                        <a href="pages/analytics.php" class="btn btn-success">
                            <i class="fas fa-chart-bar me-1"></i>
                            Перейти к аналитике
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list text-info me-2"></i>
                            Просмотр кампаний
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Просматривайте и фильтруйте данные кампаний, 
                            ищите похожие объявления и анализируйте тексты.
                        </p>
                        <a href="pages/campaigns.php" class="btn btn-info">
                            <i class="fas fa-list me-1"></i>
                            Просмотреть кампании
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-facebook text-warning me-2"></i>
                            Анализ страниц
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Анализируйте использование страниц Facebook, 
                            их активность и эффективность в рекламных кампаниях.
                        </p>
                        <a href="pages/pages_analysis.php" class="btn btn-warning">
                            <i class="fas fa-facebook me-1"></i>
                            Анализ страниц
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="text-muted mb-0">
                        &copy; 2025 Система анализа рекламных кампаний Facebook
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

