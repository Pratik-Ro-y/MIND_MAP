
<?php
// includes/Database.php
class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $pdo;
    private $error;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
        
        $this->connect();
    }
    
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            if (DEBUG_MODE) {
                error_log("Database Connection Error: " . $this->error);
                throw new Exception("Database connection failed: " . $this->error);
            } else {
                throw new Exception("Database connection failed");
            }
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // Utility method for pagination
    public function paginate($baseQuery, $params = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM ({$baseQuery}) as count_table";
        $totalStmt = $this->query($countQuery, $params);
        $total = $totalStmt->fetch()['total'];
        
        // Get paginated results
        $paginatedQuery = $baseQuery . " LIMIT {$limit} OFFSET {$offset}";
        $results = $this->fetchAll($paginatedQuery, $params);
        
        return [
            'data' => $results,
            'pagination' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'pages' => (int)ceil($total / $limit),
                'has_next' => $page < ceil($total / $limit),
                'has_prev' => $page > 1
            ]
        ];
    }
}
?>

