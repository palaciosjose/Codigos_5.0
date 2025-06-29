<?php
// shared/DatabaseManager.php
namespace Shared;

/**
 * Gestión centralizada de la conexión a la base de datos
 */
class DatabaseManager
{
    private static ?DatabaseManager $instance = null;
    private ?\mysqli $connection = null;
    
    private function __construct()
    {
        $this->connect();
    }
    
    public static function getInstance(): DatabaseManager
    {
        if (self::$instance === null) {
            self::$instance = new DatabaseManager();
        }
        return self::$instance;
    }
    
    public function getConnection(): \mysqli
    {
        // Verificar si la conexión sigue activa
        if ($this->connection && !$this->connection->ping()) {
            $this->reconnect();
        }
        
        if (!$this->connection) {
            $this->connect();
        }
        
        return $this->connection;
    }
    
    private function connect(): void
    {
        try {
            // Cargar credenciales desde config o variables de entorno
            $config = $this->loadDatabaseConfig();
            
            $this->connection = new \mysqli(
                $config['host'],
                $config['user'], 
                $config['password'],
                $config['database']
            );
            
            if ($this->connection->connect_error) {
                throw new \Exception('Error de conexión a BD: ' . $this->connection->connect_error);
            }
            
            $this->connection->set_charset('utf8mb4');
            
            // Configurar opciones de conexión
            $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            $this->connection->options(MYSQLI_OPT_READ_TIMEOUT, 30);
            
        } catch (\Exception $e) {
            error_log("DatabaseManager connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function reconnect(): void
    {
        if ($this->connection) {
            $this->connection->close();
        }
        $this->connect();
    }
    
    private function loadDatabaseConfig(): array
    {
        // 1. Primero intentar variables de entorno
        if (getenv('DB_HOST')) {
            return [
                'host' => getenv('DB_HOST'),
                'user' => getenv('DB_USER'),
                'password' => getenv('DB_PASSWORD'),
                'database' => getenv('DB_NAME')
            ];
        }
        
        // 2. Intentar archivo .env en el directorio telegram_bot
        $envFile = dirname(__DIR__) . '/telegram_bot/.env';
        if (file_exists($envFile)) {
            $envConfig = $this->parseEnvFile($envFile);
            if (isset($envConfig['DB_HOST'])) {
                return [
                    'host' => $envConfig['DB_HOST'],
                    'user' => $envConfig['DB_USER'],
                    'password' => $envConfig['DB_PASSWORD'],
                    'database' => $envConfig['DB_NAME']
                ];
            }
        }
        
        // 3. Intentar archivo de configuración
        $configFile = dirname(__DIR__) . '/config/db_credentials.php';
        if (file_exists($configFile)) {
            include $configFile;
            return [
                'host' => $db_host ?? 'localhost',
                'user' => $db_user ?? '',
                'password' => $db_password ?? '',
                'database' => $db_name ?? ''
            ];
        }
        
        // 4. Fallback al archivo legacy
        $legacyFile = dirname(__DIR__) . '/instalacion/basededatos.php';
        if (file_exists($legacyFile)) {
            include $legacyFile;
            return [
                'host' => $db_host ?? 'localhost',
                'user' => $db_user ?? '',
                'password' => $db_password ?? '',
                'database' => $db_name ?? ''
            ];
        }
        
        throw new \Exception('No se pudo cargar la configuración de la base de datos');
    }
    
    private function parseEnvFile(string $filePath): array
    {
        $config = [];
        
        if (!file_exists($filePath)) {
            return $config;
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Ignorar comentarios
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Parsear variable=valor
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        
        return $config;
    }
    
    /**
     * Ejecuta una consulta preparada de forma segura
     */
    public function executePrepared(string $query, string $types = '', ...$params): ?\mysqli_result
    {
        try {
            $stmt = $this->connection->prepare($query);
            
            if (!$stmt) {
                throw new \Exception('Error preparando consulta: ' . $this->connection->error);
            }
            
            if (!empty($types) && !empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new \Exception('Error ejecutando consulta: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $stmt->close();
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("DatabaseManager query error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtiene una sola fila de resultado
     */
    public function fetchOne(string $query, string $types = '', ...$params): ?array
    {
        $result = $this->executePrepared($query, $types, ...$params);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    /**
     * Obtiene todas las filas de resultado
     */
    public function fetchAll(string $query, string $types = '', ...$params): array
    {
        $result = $this->executePrepared($query, $types, ...$params);
        $rows = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        
        return $rows;
    }
    
    /**
     * Obtiene el ID del último insert
     */
    public function getLastInsertId(): int
    {
        return $this->connection->insert_id;
    }
    
    /**
     * Inicia una transacción
     */
    public function beginTransaction(): bool
    {
        return $this->connection->begin_transaction();
    }
    
    /**
     * Confirma una transacción
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }
    
    /**
     * Deshace una transacción
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }
    
    /**
     * Verifica si una tabla existe
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $result = $this->executePrepared(
                "SHOW TABLES LIKE ?", 
                's', 
                $tableName
            );
            
            return $result && $result->num_rows > 0;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtiene información sobre el estado de la conexión
     */
    public function getConnectionInfo(): array
    {
        if (!$this->connection) {
            return ['status' => 'disconnected'];
        }
        
        return [
            'status' => 'connected',
            'thread_id' => $this->connection->thread_id,
            'server_info' => $this->connection->server_info,
            'client_info' => $this->connection->client_info,
            'host_info' => $this->connection->host_info,
            'protocol_version' => $this->connection->protocol_version,
            'charset' => $this->connection->character_set_name()
        ];
    }
    
    /**
     * Verifica la salud de la conexión
     */
    public function healthCheck(): bool
    {
        try {
            if (!$this->connection) {
                return false;
            }
            
            // Ping para verificar conexión
            if (!$this->connection->ping()) {
                return false;
            }
            
            // Consulta simple para verificar funcionalidad
            $result = $this->connection->query("SELECT 1");
            if (!$result) {
                return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("DatabaseManager health check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cierra la conexión explícitamente
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }
    
    /**
     * Previene clonación
     */
    private function __clone() {}
    
    /**
     * Previene deserialización
     */
    public function __wakeup() {}
    
    /**
     * Cleanup al destruir
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}