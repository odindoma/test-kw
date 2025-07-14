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
        <div id="alertContainer">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?> alert-dismissible fade show">
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
                    Загрузите CSV файл с данными рекламных кампаний Facebook для анализа уникальности креативов, клонирования и активности страниц.
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
                        <form id="uploadForm" enctype="multipart/form-data" method="post">
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
                            
                            <!-- Информация о выбранном файле -->
                            <div id="fileInfo" style="display: none;" class="mt-3">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-file me-2"></i>Выбранный файл:</h6>
                                    <p class="mb-1"><strong>Имя:</strong> <span id="fileName"></span></p>
                                    <p class="mb-0"><strong>Размер:</strong> <span id="fileSize"></span></p>
                                </div>
                            </div>

                            <!-- Прогресс загрузки -->
                            <div id="progressContainer" style="display: none;" class="mt-3">
                                <label class="form-label">Прогресс загрузки:</label>
                                <div class="progress">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        0%
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" id="uploadBtn" class="btn btn-primary btn-lg disabled" disabled>
                                    <i class="fas fa-upload me-1"></i>
                                    Загрузить файл
                                </button>
                                <button type="button" class="btn btn-secondary btn-lg ms-2" onclick="resetForm()">
                                    <i class="fas fa-times me-1"></i>
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
                            <li><i class="fas fa-check text-success me-2"></i>Максимум 50 МБ</li>
                        </ul>

                        <h6 class="mt-3">Обязательные колонки:</h6>
                        <ul class="list-unstyled small">
                            <li><code>Advertiser</code> - Рекламодатель</li>
                            <li><code>Resource ID</code> - Page ID/Ad ID</li>
                            <li><code>Region</code> - Регион показа</li>
                            <li><code>Campaign</code> - Название кампании</li>
                            <li><code>Ad Title</code> - Заголовок</li>
                            <li><code>Ad Description</code> - Описание</li>
                            <li><code>Ad Media Type</code> - Тип медиа</li>
                            <li><code>Ad Media Hash</code> - Хеш медиа</li>
                            <li><code>Target URL</code> - Целевая ссылка</li>
                            <li><code>First Shown At</code> - Дата начала</li>
                            <li><code>Last Shown At</code> - Дата окончания</li>
                        </ul>

                        <div class="alert alert-warning mt-3">
                            <small>
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Важно:</strong> Resource ID должен содержать Page ID и Ad ID, разделенные символом "/"
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-question-circle text-warning me-2"></i>
                            Помощь
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="small">
                            После успешной загрузки файла вы будете перенаправлены на страницу аналитики, 
                            где сможете изучить:
                        </p>
                        <ul class="small">
                            <li>Уникальность креативов</li>
                            <li>Клонирование кампаний</li>
                            <li>Активность страниц Facebook</li>
                            <li>Статистику по рекламодателям</li>
                        </ul>
                        
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        // Дополнительные функции для страницы загрузки
        
        function resetForm() {
            console.log('Сброс формы');
            
            const fileInput = document.getElementById('csvFile');
            const fileInfo = document.getElementById('fileInfo');
            const uploadBtn = document.getElementById('uploadBtn');
            const progressContainer = document.getElementById('progressContainer');
            
            // Очищаем input файла
            if (fileInput) {
                fileInput.value = '';
            }
            
            // Скрываем информацию о файле
            if (fileInfo) {
                fileInfo.style.display = 'none';
            }
            
            // Отключаем кнопку загрузки
            if (uploadBtn) {
                uploadBtn.disabled = true;
                uploadBtn.classList.add('disabled');
                uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i>Загрузить файл';
            }
            
            // Скрываем прогресс-бар
            if (progressContainer) {
                progressContainer.style.display = 'none';
            }
            
            // Удаляем динамические алерты
            const dynamicAlerts = document.querySelectorAll('.alert.dynamic-alert');
            dynamicAlerts.forEach(alert => alert.remove());
        }
        
        // Предотвращаем случайное закрытие страницы во время загрузки
        let uploadInProgress = false;
        
        window.addEventListener('beforeunload', function(e) {
            if (uploadInProgress) {
                e.preventDefault();
                e.returnValue = 'Загрузка файла в процессе. Вы уверены, что хотите покинуть страницу?';
                return e.returnValue;
            }
        });
        
        // Переопределяем функцию uploadFile для отслеживания состояния
        const originalUploadFile = window.uploadFile;
        window.uploadFile = function() {
            uploadInProgress = true;
            
            // Вызываем оригинальную функцию
            originalUploadFile();
            
            // Сбрасываем флаг после завершения (через 30 секунд максимум)
            setTimeout(() => {
                uploadInProgress = false;
            }, 30000);
        };
        
        // Дополнительная проверка при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Страница загрузки готова');
            
            // Проверяем, есть ли уже загруженные данные
            fetch('../api/upload.php?action=check_data')
                .then(response => response.json())
                .then(data => {
                    if (data.has_data) {
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-info alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            <i class="fas fa-info-circle me-2"></i>
                            В базе данных уже есть ${data.count} записей. 
                            Новые данные будут добавлены к существующим.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        
                        const alertContainer = document.getElementById('alertContainer');
                        if (alertContainer) {
                            alertContainer.appendChild(alertDiv);
                        }
                    }
                })
                .catch(error => {
                    console.log('Не удалось проверить существующие данные:', error);
                });
        });
    </script>
</body>
</html>

