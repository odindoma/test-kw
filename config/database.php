<?php
/**
 * Конфигурация базы данных для анализа рекламных кампаний
 */

class DatabaseConfig {
    // Настройки подключения к базе данных
    const DB_HOST = 'localhost';
    const DB_NAME = 'ad_campaigns_analysis';
    const DB_USER = 'root';
    const DB_PASS = '';
    const DB_CHARSET = 'utf8mb4';
    
    // Настройки для загрузки файлов
    const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    const ALLOWED_EXTENSIONS = ['csv'];
    const UPLOAD_DIR = __DIR__ . '/../uploads/';
    
    // Настройки пагинации
    const ITEMS_PER_PAGE = 50;
    const MAX_ITEMS_PER_PAGE = 500;
    
    // Настройки кэширования
    const CACHE_ENABLED = true;
    const CACHE_DURATION = 3600; // 1 час
}

/**
 * Класс для работы с базой данных
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DatabaseConfig::DB_HOST,
                DatabaseConfig::DB_NAME,
                DatabaseConfig::DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DatabaseConfig::DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DatabaseConfig::DB_USER, DatabaseConfig::DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception('Ошибка подключения к базе данных: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Выполнить запрос с параметрами
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Ошибка выполнения запроса: ' . $e->getMessage());
        }
    }
    
    /**
     * Получить все записи
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Получить одну запись
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Получить количество записей
     */
    public function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Вставить запись и вернуть ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }
    
    /**
     * Начать транзакцию
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Подтвердить транзакцию
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Откатить транзакцию
     */
    public function rollback() {
        return $this->connection->rollback();
    }
}
?>

