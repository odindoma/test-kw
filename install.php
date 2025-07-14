<?php
/**
 * Скрипт установки для системы анализа рекламных кампаний
 * Автоматически создает базу данных и таблицы
 */

// Проверяем, что скрипт запущен из командной строки или локально
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']) && php_sapi_name() !== 'cli') {
    die('Доступ запрещен. Запустите скрипт локально или из командной строки.');
}

echo "=== Установка системы анализа рекламных кампаний ===\n\n";

// Настройки подключения к базе данных
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'ad_campaigns_analysis'
];

// Если запущен из веб-интерфейса, получаем параметры из POST
if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['host'] = $_POST['host'] ?? $config['host'];
    $config['username'] = $_POST['username'] ?? $config['username'];
    $config['password'] = $_POST['password'] ?? $config['password'];
    $config['database'] = $_POST['database'] ?? $config['database'];
}

try {
    echo "1. Подключение к MySQL серверу...\n";
    
    // Подключаемся к MySQL без указания базы данных
    $pdo = new PDO(
        "mysql:host={$config['host']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✓ Подключение к MySQL успешно\n\n";
    
    echo "2. Создание базы данных '{$config['database']}'...\n";
    
    // Создаем базу данных
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$config['database']}`");
    
    echo "✓ База данных создана\n\n";
    
    echo "3. Создание таблиц...\n";
    
    // Читаем и выполняем SQL схему
    $sqlFile = __DIR__ . '/database_schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Файл схемы базы данных не найден: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Удаляем команды создания базы данных из SQL файла
    $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
    $sql = preg_replace('/USE.*?;/i', '', $sql);
    
    // Разделяем на отдельные запросы
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    echo "✓ Таблицы созданы\n\n";
    
    echo "4. Обновление конфигурационного файла...\n";
    
    // Обновляем конфигурационный файл
    $configFile = __DIR__ . '/config/database.php';
    $configContent = file_get_contents($configFile);
    
    $configContent = str_replace("const DB_HOST = 'localhost';", "const DB_HOST = '{$config['host']}';", $configContent);
    $configContent = str_replace("const DB_NAME = 'ad_campaigns_analysis';", "const DB_NAME = '{$config['database']}';", $configContent);
    $configContent = str_replace("const DB_USER = 'root';", "const DB_USER = '{$config['username']}';", $configContent);
    $configContent = str_replace("const DB_PASS = '';", "const DB_PASS = '{$config['password']}';", $configContent);
    
    file_put_contents($configFile, $configContent);
    
    echo "✓ Конфигурация обновлена\n\n";
    
    echo "5. Создание директорий...\n";
    
    // Создаем необходимые директории
    $directories = [
        __DIR__ . '/uploads',
        __DIR__ . '/logs'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✓ Создана директория: $dir\n";
        }
    }
    
    echo "\n=== УСТАНОВКА ЗАВЕРШЕНА УСПЕШНО ===\n\n";
    echo "Система готова к использованию!\n";
    echo "Откройте в браузере: http://localhost/ad_campaigns_analyzer/\n\n";
    echo "Для загрузки тестовых данных запустите:\n";
    echo "php create_test_data.php\n\n";
    
    // Удаляем файл установки для безопасности
    if (php_sapi_name() !== 'cli') {
        echo "Файл установки будет удален для безопасности.\n";
        unlink(__FILE__);
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n\n";
    echo "Проверьте:\n";
    echo "- Правильность настроек подключения к MySQL\n";
    echo "- Права доступа пользователя MySQL\n";
    echo "- Доступность MySQL сервера\n";
    exit(1);
}

// Веб-интерфейс для установки
if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка - Анализ рекламных кампаний</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Установка системы анализа рекламных кампаний</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Хост MySQL</label>
                                <input type="text" name="host" class="form-control" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Имя пользователя</label>
                                <input type="text" name="username" class="form-control" value="root" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Пароль</label>
                                <input type="password" name="password" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Имя базы данных</label>
                                <input type="text" name="database" class="form-control" value="ad_campaigns_analysis" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Установить</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php endif; ?>

