<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require_once '../config/database.php';
require_once '../includes/CampaignAnalyzer.php';

// Функция для отправки JSON ответа
function sendResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Функция для логирования ошибок
function logError($message, $context = []) {
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logMessage .= ' - Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    
    // Создаем папку для логов, если её нет
    $logDir = '../logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    error_log($logMessage . PHP_EOL, 3, $logDir . 'upload_errors.log');
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Метод не поддерживается');
}

// Проверяем загрузку файла
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Файл превышает максимальный размер',
        UPLOAD_ERR_FORM_SIZE => 'Файл превышает максимальный размер формы',
        UPLOAD_ERR_PARTIAL => 'Файл загружен частично',
        UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл',
        UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением'
    ];
    
    $message = $errorMessages[$errorCode] ?? 'Неизвестная ошибка загрузки';
    logError('Ошибка загрузки файла', ['error_code' => $errorCode]);
    sendResponse(false, $message);
}

$file = $_FILES['csv_file'];

// Проверка типа файла
if (!str_ends_with(strtolower($file['name']), '.csv')) {
    logError('Неверный тип файла', ['file_name' => $file['name']]);
    sendResponse(false, 'Пожалуйста, загрузите CSV файл');
}

// Проверка размера файла (50MB)
$maxSize = 50 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    logError('Файл слишком большой', ['size' => $file['size']]);
    sendResponse(false, 'Файл слишком большой. Максимальный размер: 50MB');
}

// Создаем папку для загрузок
$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        logError('Не удалось создать папку для загрузок');
        sendResponse(false, 'Ошибка создания папки для загрузок');
    }
}

// Генерируем уникальное имя файла
$uploadPath = $uploadDir . uniqid('csv_', true) . '.csv';

// Перемещаем файл
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    logError('Не удалось переместить файл', ['tmp_name' => $file['tmp_name']]);
    sendResponse(false, 'Ошибка при сохранении файла');
}

try {
    // Обрабатываем CSV файл
    $analyzer = new CampaignAnalyzer();
    $result = $analyzer->importCsvFile($uploadPath);
    
    // Удаляем временный файл
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    if ($result['success']) {
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = 'success';
        
        sendResponse(true, $result['message'], [
            'imported_count' => $result['imported_count'] ?? 0,
            'total_count' => $result['total_count'] ?? 0
        ]);
    } else {
        logError('Ошибка импорта CSV', ['error' => $result['message']]);
        sendResponse(false, $result['message']);
    }
    
} catch (Exception $e) {
    // Удаляем файл в случае ошибки
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    logError('Исключение при обработке файла', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    sendResponse(false, 'Ошибка при обработке файла: ' . $e->getMessage());
}
?>
