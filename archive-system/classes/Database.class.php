<?php
/**
 * Database.class.php
 * فئة متقدمة للتعامل مع قاعدة البيانات باستخدام Singleton Pattern
 * تدعم Prepared Statements، Transactions، والتعامل مع الأخطاء
 */

class Database {
    private static $instance = null;
    private $connection;
    private $queryCount = 0;
    private $lastQuery;
    private $transactionLevel = 0;
    
    // إعدادات قاعدة البيانات
    private $config = [
        'host' => 'localhost',
        'database' => 'enterprise_archive',
        'username' => 'archive_user',
        'password' => 'StrongPassword@123',
        'charset' => 'utf8',
        'collation' => 'utf8_bin',
        'port' => 3306,
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8 COLLATE utf8_bin, 
                                           time_zone = '+03:00', 
                                           sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
        ]
    ];
    
    /**
     * Constructor خاص لمنع الإنشاء المباشر
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * منع النسخ
     */
    private function __clone() { }
    
    /**
     * الحصول على النسخة الوحيدة (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * الاتصال بقاعدة البيانات
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
            
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
            
            // إعدادات إضافية
            $this->connection->exec("SET time_zone = '+03:00'");
            $this->connection->exec("SET sql_mode = ''");
            
            $this->log('info', 'تم الاتصال بقاعدة البيانات بنجاح');
            
        } catch (PDOException $e) {
            $this->log('error', 'فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
            throw new Exception('فشل الاتصال بقاعدة البيانات. الرجاء المحاولة لاحقاً.');
        }
    }
    
    /**
     * تنفيذ استعلام مع معلمات
     */
    public function query($sql, $params = [], $returnType = 'all') {
        $this->queryCount++;
        $this->lastQuery = ['sql' => $sql, 'params' => $params];
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            if (!$stmt) {
                throw new Exception('فشل إعداد الاستعلام: ' . implode(', ', $this->connection->errorInfo()));
            }
            
            // ربط المعلمات
            foreach ($params as $key => $value) {
                $paramType = $this->getParamType($value);
                $stmt->bindValue(
                    is_int($key) ? $key + 1 : $key,
                    $value,
                    $paramType
                );
            }
            
            // تنفيذ الاستعلام
            $startTime = microtime(true);
            $executed = $stmt->execute();
            $executionTime = microtime(true) - $startTime;
            
            if (!$executed) {
                throw new Exception('فشل تنفيذ الاستعلام: ' . implode(', ', $stmt->errorInfo()));
            }
            
            $this->log('debug', sprintf(
                'تم تنفيذ الاستعلام في %.4f ثانية: %s',
                $executionTime,
                $this->formatSqlForLog($sql, $params)
            ));
            
            // إرجاع النتيجة حسب النوع المطلوب
            switch (strtolower($returnType)) {
                case 'row':
                    return $stmt->fetch();
                    
                case 'column':
                    return $stmt->fetchColumn();
                    
                case 'count':
                    return $stmt->rowCount();
                    
                case 'id':
                    return $this->connection->lastInsertId();
                    
                case 'stmt':
                    return $stmt;
                    
                case 'all':
                default:
                    return $stmt->fetchAll();
            }
            
        } catch (Exception $e) {
            $this->log('error', 'خطأ في الاستعلام: ' . $e->getMessage() . 
                      ' | SQL: ' . $this->formatSqlForLog($sql, $params));
            throw $e;
        }
    }
    
    /**
     * جلب صف واحد
     */
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params, 'row');
    }
    
    /**
     * جلب عمود واحد
     */
    public function fetchColumn($sql, $params = [], $column = 0) {
        $result = $this->query($sql, $params, 'stmt');
        return $result->fetchColumn($column);
    }
    
    /**
     * جلب جميع الصفوف
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params, 'all');
    }
    
    /**
     * إدراج سجل جديد
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);
        
        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)",
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, $values);
        return $this->connection->lastInsertId();
    }
    
    /**
     * تحديث سجل
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $setClause[] = "`$column` = ?";
            $values[] = $value;
        }
        
        $values = array_merge($values, $whereParams);
        
        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE %s",
            $table,
            implode(', ', $setClause),
            $where
        );
        
        return $this->query($sql, $values, 'count');
    }
    
    /**
     * حذف سجل
     */
    public function delete($table, $where, $params = []) {
        $sql = sprintf("DELETE FROM `%s` WHERE %s", $table, $where);
        return $this->query($sql, $params, 'count');
    }
    
    /**
     * بدء Transaction
     */
    public function beginTransaction() {
        if ($this->transactionLevel === 0) {
            $this->connection->beginTransaction();
            $this->log('debug', 'بدأ Transaction جديد');
        }
        $this->transactionLevel++;
    }
    
    /**
     * تأكيد Transaction
     */
    public function commit() {
        if ($this->transactionLevel === 1) {
            $this->connection->commit();
            $this->log('debug', 'تم تأكيد Transaction');
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }
    
    /**
     * تراجع Transaction
     */
    public function rollback() {
        if ($this->transactionLevel === 1) {
            $this->connection->rollBack();
            $this->log('debug', 'تم تراجع Transaction');
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }
    
    /**
     * تنفيذ Transaction بأمان
     */
    public function transaction(callable $callback) {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * التحقق من وجود سجل
     */
    public function exists($table, $where, $params = []) {
        $sql = "SELECT 1 FROM `{$table}` WHERE {$where} LIMIT 1";
        return (bool) $this->query($sql, $params, 'row');
    }
    
    /**
     * الحصول على عدد السجلات
     */
    public function count($table, $where = '1', $params = []) {
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
        return (int) $this->query($sql, $params, 'column');
    }
    
    /**
     * الحصول على القيمة القصوى
     */
    public function max($table, $column, $where = '1', $params = []) {
        $sql = "SELECT MAX(`{$column}`) FROM `{$table}` WHERE {$where}";
        return $this->query($sql, $params, 'column');
    }
    
    /**
     * الحصول على القيمة الدنيا
     */
    public function min($table, $column, $where = '1', $params = []) {
        $sql = "SELECT MIN(`{$column}`) FROM `{$table}` WHERE {$where}";
        return $this->query($sql, $params, 'column');
    }
    
    /**
     * الحصول على المجموع
     */
    public function sum($table, $column, $where = '1', $params = []) {
        $sql = "SELECT SUM(`{$column}`) FROM `{$table}` WHERE {$where}";
        return $this->query($sql, $params, 'column') ?: 0;
    }
    
    /**
     * الحصول على المتوسط
     */
    public function avg($table, $column, $where = '1', $params = []) {
        $sql = "SELECT AVG(`{$column}`) FROM `{$table}` WHERE {$where}";
        return $this->query($sql, $params, 'column');
    }
    
    /**
     * البحث النصي الكامل
     */
    public function fulltextSearch($table, $columns, $searchTerm, $additionalWhere = '1', $params = [], $limit = 50) {
        $searchColumns = array_map(function($col) {
            return "`$col`";
        }, (array)$columns);
        
        $searchColumns = implode(', ', $searchColumns);
        $searchTerm = preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $searchTerm);
        $searchTerm = trim($searchTerm);
        
        $sql = "SELECT *, MATCH({$searchColumns}) AGAINST(? IN BOOLEAN MODE) as relevance 
                FROM `{$table}` 
                WHERE MATCH({$searchColumns}) AGAINST(? IN BOOLEAN MODE) 
                AND {$additionalWhere}
                ORDER BY relevance DESC 
                LIMIT ?";
        
        $searchParams = array_merge([$searchTerm, $searchTerm], $params, [$limit]);
        
        return $this->query($sql, $searchParams);
    }
    
    /**
     * الحصول على الهيكل
     */
    public function getStructure($table) {
        $sql = "DESCRIBE `{$table}`";
        return $this->query($sql);
    }
    
    /**
     * الحصول على الفهارس
     */
    public function getIndexes($table) {
        $sql = "SHOW INDEX FROM `{$table}`";
        return $this->query($sql);
    }
    
    /**
     * التحقق من وجود جدول
     */
    public function tableExists($table) {
        $sql = "SELECT 1 FROM information_schema.tables 
                WHERE table_schema = ? AND table_name = ?";
        return (bool) $this->query($sql, [$this->config['database'], $table], 'row');
    }
    
    /**
     * إنشاء جدول
     */
    public function createTable($table, $columns) {
        $columnDefinitions = [];
        
        foreach ($columns as $name => $definition) {
            $columnDefinitions[] = "`{$name}` {$definition}";
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (" . 
               implode(', ', $columnDefinitions) . 
               ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin";
        
        return $this->query($sql);
    }
    
    /**
     * إضافة عمود
     */
    public function addColumn($table, $column, $definition, $after = null) {
        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
        
        if ($after) {
            $sql .= " AFTER `{$after}`";
        }
        
        return $this->query($sql);
    }
    
    /**
     * تعديل عمود
     */
    public function modifyColumn($table, $column, $newDefinition) {
        $sql = "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$newDefinition}";
        return $this->query($sql);
    }
    
    /**
     * حذف عمود
     */
    public function dropColumn($table, $column) {
        $sql = "ALTER TABLE `{$table}` DROP COLUMN `{$column}`";
        return $this->query($sql);
    }
    
    /**
     * إضافة فهرس
     */
    public function addIndex($table, $indexName, $columns, $unique = false) {
        $type = $unique ? 'UNIQUE' : 'INDEX';
        $columns = implode('`, `', (array)$columns);
        
        $sql = "ALTER TABLE `{$table}` ADD {$type} `{$indexName}` (`{$columns}`)";
        return $this->query($sql);
    }
    
    /**
     * حذف فهرس
     */
    public function dropIndex($table, $indexName) {
        $sql = "ALTER TABLE `{$table}` DROP INDEX `{$indexName}`";
        return $this->query($sql);
    }
    
    /**
     * النسخ الاحتياطي للجدول
     */
    public function backupTable($table, $backupTable = null) {
        if (!$backupTable) {
            $backupTable = $table . '_backup_' . date('Ymd_His');
        }
        
        $sql = "CREATE TABLE `{$backupTable}` LIKE `{$table}`";
        $this->query($sql);
        
        $sql = "INSERT INTO `{$backupTable}` SELECT * FROM `{$table}`";
        return $this->query($sql, [], 'count');
    }
    
    /**
     * استعادة جدول من نسخة احتياطية
     */
    public function restoreTable($backupTable, $targetTable) {
        $this->query("TRUNCATE TABLE `{$targetTable}`");
        
        $sql = "INSERT INTO `{$targetTable}` SELECT * FROM `{$backupTable}`";
        return $this->query($sql, [], 'count');
    }
    
    /**
     * إحصائيات قاعدة البيانات
     */
    public function getStats() {
        $stats = [];
        
        // حجم قاعدة البيانات
        $sql = "SELECT 
                    table_schema as 'database',
                    SUM(data_length + index_length) / 1024 / 1024 as 'size_mb',
                    COUNT(*) as 'tables'
                FROM information_schema.tables 
                WHERE table_schema = ?
                GROUP BY table_schema";
        
        $stats['database'] = $this->query($sql, [$this->config['database']], 'row');
        
        // حجم الجداول
        $sql = "SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'size_mb',
                    table_rows
                FROM information_schema.tables 
                WHERE table_schema = ?
                ORDER BY (data_length + index_length) DESC";
        
        $stats['tables'] = $this->query($sql, [$this->config['database']]);
        
        // إحصائيات الاستعلامات
        $stats['queries'] = [
            'total' => $this->queryCount,
            'last_query' => $this->lastQuery
        ];
        
        // معلومات الاتصال
        $stats['connection'] = [
            'status' => $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS),
            'server_version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'client_version' => $this->connection->getAttribute(PDO::ATTR_CLIENT_VERSION)
        ];
        
        return $stats;
    }
    
    /**
     * تنظيف البيانات القديمة
     */
    public function cleanupOldData($table, $dateColumn, $days = 365) {
        $sql = "DELETE FROM `{$table}` 
                WHERE `{$dateColumn}` < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $this->query($sql, [$days], 'count');
    }
    
    /**
     * تحسين الجداول
     */
    public function optimizeTables() {
        $sql = "SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = ? AND engine = 'InnoDB'";
        
        $tables = $this->query($sql, [$this->config['database']]);
        
        foreach ($tables as $table) {
            $this->query("OPTIMIZE TABLE `{$table['table_name']}`");
        }
        
        return count($tables);
    }
    
    /**
     * إصلاح الجداول
     */
    public function repairTables() {
        $sql = "SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = ?";
        
        $tables = $this->query($sql, [$this->config['database']]);
        
        foreach ($tables as $table) {
            $this->query("REPAIR TABLE `{$table['table_name']}`");
        }
        
        return count($tables);
    }
    
    /**
     * الحصول على نوع المعلمة لـ PDO
     */
    private function getParamType($value) {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }
    
    /**
     * تنسيق SQL للسجلات
     */
    private function formatSqlForLog($sql, $params) {
        $indexed = array_keys($params) === range(0, count($params) - 1);
        
        foreach ($params as $key => $value) {
            $value = is_string($value) ? "'" . addslashes($value) . "'" : $value;
            $value = is_null($value) ? 'NULL' : $value;
            $value = is_bool($value) ? ($value ? '1' : '0') : $value;
            
            if ($indexed) {
                $sql = preg_replace('/\?/', $value, $sql, 1);
            } else {
                $sql = str_replace(":$key", $value, $sql);
            }
        }
        
        return $sql;
    }
    
    /**
     * تسجيل الرسائل
     */
    private function log($level, $message) {
        $logFile = __DIR__ . '/../logs/database.log';
        
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        error_log($logMessage, 3, $logFile);
        
        // أيضاً تسجيل في سجل PHP إذا كان في وضع التطوير
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log($logMessage);
        }
    }
    
    /**
     * الحصول على معلومات التصحيح
     */
    public function getDebugInfo() {
        return [
            'connection' => [
                'database' => $this->config['database'],
                'host' => $this->config['host'],
                'charset' => $this->config['charset']
            ],
            'queries' => [
                'total' => $this->queryCount,
                'last_query' => $this->lastQuery
            ],
            'transaction' => [
                'level' => $this->transactionLevel,
                'in_transaction' => $this->connection->inTransaction()
            ]
        ];
    }
    
    /**
     * الحصول على اتصال PDO مباشرة (للاستخدام المتقدم)
     */
    public function getPdo() {
        return $this->connection;
    }
    
    /**
     * إغلاق الاتصال
     */
    public function close() {
        $this->connection = null;
        self::$instance = null;
        $this->log('info', 'تم إغلاق الاتصال بقاعدة البيانات');
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}

// إنشاء كائن قاعدة البيانات عالمياً
$GLOBALS['db'] = Database::getInstance();

// دوال اختصار للاستخدام السريع
if (!function_exists('db_query')) {
    function db_query($sql, $params = [], $returnType = 'all') {
        return Database::getInstance()->query($sql, $params, $returnType);
    }
}

if (!function_exists('db_fetch_one')) {
    function db_fetch_one($sql, $params = []) {
        return Database::getInstance()->fetchOne($sql, $params);
    }
}

if (!function_exists('db_fetch_all')) {
    function db_fetch_all($sql, $params = []) {
        return Database::getInstance()->fetchAll($sql, $params);
    }
}

if (!function_exists('db_insert')) {
    function db_insert($table, $data) {
        return Database::getInstance()->insert($table, $data);
    }
}

if (!function_exists('db_update')) {
    function db_update($table, $data, $where, $whereParams = []) {
        return Database::getInstance()->update($table, $data, $where, $whereParams);
    }
}

if (!function_exists('db_delete')) {
    function db_delete($table, $where, $params = []) {
        return Database::getInstance()->delete($table, $where, $params);
    }
}

if (!function_exists('db_count')) {
    function db_count($table, $where = '1', $params = []) {
        return Database::getInstance()->count($table, $where, $params);
    }
}

if (!function_exists('db_exists')) {
    function db_exists($table, $where, $params = []) {
        return Database::getInstance()->exists($table, $where, $params);
    }
}

if (!function_exists('db_transaction')) {
    function db_transaction(callable $callback) {
        return Database::getInstance()->transaction($callback);
    }
}
?>