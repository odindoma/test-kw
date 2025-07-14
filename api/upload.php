<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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
    error_log($logMessage . PHP_EOL, 3, '../logs/upload_errors.log');
}

try {
    $analyzer = new CampaignAnalyzer();
    
    // Обработка GET запросов для проверки данных
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'check_data') {
            try {
                $count = $analyzer->getCampaignsCount();
                sendResponse(true, 'Данные получены', [
                    'has_data' => $count > 0,
                    'count' => $count
                ]);
            } catch (Exception $e) {
                logError('Ошибка при проверке данных', ['error' => $e->getMessage()]);
                sendResponse(false, 'Ошибка при проверке данных');
            }
        } else {
            sendResponse(false, 'Неизвестное действие');
        }
    }
    
    // Обработка POST запросов для загрузки файлов
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Проверяем, был ли загружен файл
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Файл превышает максимальный размер, установленный в PHP',
                UPLOAD_ERR_FORM_SIZE => 'Файл превышает максимальный размер формы',
                UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
                UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
                UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
                UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
                UPLOAD_ERR_EXTENSION => 'Загрузка файла остановлена расширением'
            ];
            
            $error = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $message = $errorMessages[$error] ?? 'Неизвестная ошибка загрузки файла';
            
            logError('Ошибка загрузки файла', [
                'error_code' => $error,
                'files_info' => $_FILES
            ]);
            
            sendResponse(false, $message);
        }
        
        $file = $_FILES['csv_file'];
        
        // Проверяем размер файла (максимум 50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file['size'] > $maxSize) {
            logError('Файл слишком большой', [
                'size' => $file['size'],
                'max_size' => $maxSize
            ]);
            sendResponse(false, 'Файл слишком большой. Максимальный размер: 50MB');
        }
        
        // Проверяем тип файла
        $allowedTypes = ['text/csv', 'application/csv', 'text/plain'];
        $fileType = $file['type'];
        $fileName = $file['name'];
        
        if (!in_array($fileType, $allowedTypes) && !str_ends_with(strtolower($fileName), '.csv')) {
            logError('Неверный тип файла', [
                'file_type' => $fileType,
                'file_name' => $fileName
            ]);
            sendResponse(false, 'Пожалуйста, загрузите CSV файл');
        }
        
        // Проверяем, что файл действительно существует
        if (!file_exists($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            logError('Файл недоступен для чтения', [
                'tmp_name' => $file['tmp_name'],
                'exists' => file_exists($file['tmp_name']),
                'readable' => is_readable($file['tmp_name'])
            ]);
            sendResponse(false, 'Загруженный файл недоступен для чтения');
        }
        
        // Создаем папку для загрузок, если её нет
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                logError('Не удалось создать папку для загрузок', ['upload_dir' => $uploadDir]);
                sendResponse(false, 'Ошибка создания папки для загрузок');
            }
        }
        
        // Генерируем уникальное имя файла
        $uploadPath = $uploadDir . uniqid('csv_') . '_' . time() . '.csv';
        
        // Перемещаем загруженный файл
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            logError('Не удалось переместить загруженный файл', [
                'tmp_name' => $file['tmp_name'],
                'upload_path' => $uploadPath
            ]);
            sendResponse(false, 'Ошибка при сохранении файла');
        }
        
        try {
            // Обрабатываем CSV файл
            $result = $analyzer->importCsvFile($uploadPath);
            
            // Удаляем временный файл после обработки
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            
            if ($result['success']) {
                // Сохраняем сообщение в сессию для отображения на следующей странице
                $_SESSION['message'] = $result['message'];
                $_SESSION['message_type'] = 'success';
                
                sendResponse(true, $result['message'], [
                    'imported_count' => $result['imported_count'],
                    'skipped_count' => $result['skipped_count'],
                    'total_count' => $result['total_count']
                ]);
            } else {
                // Удаляем файл в случае ошибки
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                
                logError('Ошибка при импорте CSV', [
                    'error' => $result['message'],
                    'file_path' => $uploadPath
                ]);
                
                sendResponse(false, $result['message']);
            }
            
        } catch (Exception $e) {
            // Удаляем файл в случае исключения
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            
            logError('Исключение при обработке файла', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            sendResponse(false, 'Произошла ошибка при обработке файла: ' . $e->getMessage());
        }
    }
    
    // Неподдерживаемый метод
    sendResponse(false, 'Метод не поддерживается');
    
} catch (Exception $e) {
    logError('Критическая ошибка в API', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    sendResponse(false, 'Произошла критическая ошибка сервера');
}
?>

