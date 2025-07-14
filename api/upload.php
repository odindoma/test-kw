<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once '../config/database.php';
require_once '../includes/CampaignAnalyzer.php';

/**
 * API для загрузки CSV файлов с данными рекламных кампаний
 */

try {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }
    
    // Проверяем наличие файла
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Файл не был загружен или произошла ошибка');
    }
    
    $file = $_FILES['csv_file'];
    
    // Проверяем размер файла
    if ($file['size'] > DatabaseConfig::MAX_FILE_SIZE) {
        throw new Exception('Размер файла превышает максимально допустимый (' . formatFileSize(DatabaseConfig::MAX_FILE_SIZE) . ')');
    }
    
    // Проверяем расширение файла
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, DatabaseConfig::ALLOWED_EXTENSIONS)) {
        throw new Exception('Неподдерживаемый тип файла. Разрешены только: ' . implode(', ', DatabaseConfig::ALLOWED_EXTENSIONS));
    }
    
    // Создаем директорию для загрузок если не существует
    $uploadDir = DatabaseConfig::UPLOAD_DIR;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Не удалось создать директорию для загрузок');
        }
    }
    
    // Генерируем уникальное имя файла
    $fileName = uniqid('upload_') . '_' . time() . '.csv';
    $filePath = $uploadDir . $fileName;
    
    // Перемещаем загруженный файл
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Не удалось сохранить загруженный файл');
    }
    
    // Проверяем, что файл действительно CSV с табуляцией
    if (!validateCsvFile($filePath)) {
        unlink($filePath); // Удаляем файл при ошибке
        throw new Exception('Файл не является корректным CSV с разделением табуляцией');
    }
    
    // Импортируем данные
    $analyzer = new CampaignAnalyzer();
    $result = $analyzer->importCsvFile($filePath);
    
    // Удаляем временный файл после импорта
    unlink($filePath);
    
    // Сохраняем сообщение в сессию для отображения на странице
    $_SESSION['message'] = sprintf(
        'Файл успешно загружен! Импортировано записей: %d. Ошибок: %d',
        $result['imported'],
        $result['errors']
    );
    $_SESSION['message_type'] = $result['errors'] > 0 ? 'warning' : 'success';
    
    // Возвращаем результат
    echo json_encode([
        'success' => true,
        'message' => 'Файл успешно загружен',
        'imported' => $result['imported'],
        'errors' => $result['errors'],
        'error_details' => $result['error_details']
    ]);
    
} catch (Exception $e) {
    // Логируем ошибку
    error_log('Upload error: ' . $e->getMessage());
    
    // Сохраняем сообщение об ошибке в сессию
    $_SESSION['message'] = 'Ошибка загрузки: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    
    // Возвращаем ошибку
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Проверка корректности CSV файла
 */
function validateCsvFile($filePath) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return false;
    }
    
    // Читаем первую строку (заголовки)
    $headers = fgetcsv($handle, 0, "\t");
    fclose($handle);
    
    if (!$headers || count($headers) < 5) {
        return false;
    }
    
    // Проверяем наличие обязательных колонок
    $requiredColumns = [
        'Advertiser',
        'Resource ID',
        'Region',
        'Campaign',
        'Ad Title'
    ];
    
    foreach ($requiredColumns as $column) {
        if (!in_array($column, $headers)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Форматирование размера файла
 */
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>

