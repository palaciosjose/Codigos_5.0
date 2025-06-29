<?php
// shared/UnifiedQueryEngine.php
namespace Shared;

/**
 * Motor unificado de búsqueda de emails
 * Integra la funcionalidad del EmailSearchEngine principal
 */
class UnifiedQueryEngine
{
    private \mysqli $db;
    private array $settings;
    private int $lastLogId = 0;
    
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->loadSettings();
    }
    
    private function loadSettings(): void
    {
        $this->settings = [];
        $query = "SELECT name, value FROM settings";
        $result = $this->db->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->settings[$row['name']] = $row['value'];
            }
        }
    }
    
    /**
     * Busca emails para un correo y plataforma específicos
     */
    public function searchEmails(string $email, string $platform, int $userId): array
    {
        try {
            // Validar parámetros
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $platform = trim($platform);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->createErrorResponse('Email inválido');
            }
            
            if (empty($platform)) {
                return $this->createErrorResponse('Plataforma requerida');
            }
            
            // Obtener asuntos para la plataforma
            $subjects = $this->getSubjectsForPlatform($platform);
            if (empty($subjects)) {
                return $this->createErrorResponse('Plataforma no encontrada o sin asuntos configurados');
            }
            
            // Obtener servidores habilitados
            $servers = $this->getEnabledServers();
            if (empty($servers)) {
                return $this->createErrorResponse('No hay servidores configurados');
            }
            
            // Registrar el intento de búsqueda
            $this->logSearchAttempt($userId, $email, $platform);
            
            // Realizar búsqueda
            $result = $this->performSearch($email, $subjects, $servers, $userId);
            
            // Actualizar log con resultado
            $this->updateSearchLog($this->lastLogId, $result);
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Error en UnifiedQueryEngine::searchEmails: " . $e->getMessage());
            return $this->createErrorResponse('Error interno del servidor');
        }
    }
    
    private function getSubjectsForPlatform(string $platform): array
    {
        $stmt = $this->db->prepare("
            SELECT ps.subject 
            FROM platforms p 
            JOIN platform_subjects ps ON p.id = ps.platform_id 
            WHERE p.name = ?
        ");
        $stmt->bind_param('s', $platform);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row['subject'];
        }
        $stmt->close();
        
        return $subjects;
    }
    
    private function getEnabledServers(): array
    {
        $query = "SELECT * FROM servers WHERE status = 1 ORDER BY priority ASC";
        $result = $this->db->query($query);
        
        $servers = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $servers[] = $row;
            }
        }
        
        return $servers;
    }
    
    private function performSearch(string $email, array $subjects, array $servers, int $userId): array
    {
        $earlyStop = ($this->settings['EARLY_SEARCH_STOP'] ?? '1') === '1';
        $serversChecked = 0;
        
        foreach ($servers as $server) {
            $serversChecked++;
            $result = $this->searchInSingleServer($email, $subjects, $server);
            
            if ($result['found']) {
                $result['servers_checked'] = $serversChecked;
                return $result;
            }
            
            if ($earlyStop && isset($result['emails_found_count']) && $result['emails_found_count'] > 0) {
                // Si encontró emails pero no pudo procesarlos, continuar con el siguiente servidor
                continue;
            }
        }
        
        return $this->createNotFoundResponse($serversChecked);
    }
    
    private function searchInSingleServer(string $email, array $subjects, array $server): array
    {
        try {
            // Configurar conexión IMAP
            $imapConfig = [
                'host' => $server['host'],
                'port' => $server['port'],
                'username' => $server['username'],
                'password' => $server['password'],
                'protocol' => $server['protocol'] ?? 'imap',
                'encryption' => $server['encryption'] ?? 'ssl'
            ];
            
            $connection = $this->openImapConnection($imapConfig);
            if (!$connection) {
                return $this->createErrorResponse('No se pudo conectar al servidor ' . $server['name']);
            }
            
            $result = $this->searchEmailsInServer($connection, $email, $subjects);
            
            imap_close($connection);
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Error buscando en servidor {$server['name']}: " . $e->getMessage());
            return $this->createErrorResponse('Error en servidor ' . $server['name']);
        }
    }
    
    private function openImapConnection(array $config): mixed
    {
        $connectionString = sprintf(
            '{%s:%d/%s/%s}INBOX',
            $config['host'],
            $config['port'],
            $config['protocol'],
            $config['encryption']
        );
        
        $timeout = (int)($this->settings['IMAP_CONNECTION_TIMEOUT'] ?? 8);
        imap_timeout(IMAP_OPENTIMEOUT, $timeout);
        
        return @imap_open($connectionString, $config['username'], $config['password']);
    }
    
    private function searchEmailsInServer($connection, string $email, array $subjects): array
    {
        $emailsFound = 0;
        $codes = [];
        
        // Calcular rango de fechas para búsqueda
        $timeLimit = (int)($this->settings['EMAIL_QUERY_TIME_LIMIT_MINUTES'] ?? 30);
        $debugHours = (int)($this->settings['TIMEZONE_DEBUG_HOURS'] ?? 48);
        
        $endTime = time();
        $startTime = $endTime - ($timeLimit * 60) - ($debugHours * 3600);
        
        $searchDate = date('d-M-Y', $startTime);
        
        foreach ($subjects as $subject) {
            try {
                // Buscar emails por asunto y fecha
                $searchCriteria = sprintf(
                    'FROM "%s" SUBJECT "%s" SINCE "%s"',
                    $email,
                    $subject,
                    $searchDate
                );
                
                $messages = imap_search($connection, $searchCriteria);
                
                if ($messages) {
                    $emailsFound += count($messages);
                    
                    // Procesar los mensajes encontrados
                    foreach ($messages as $messageId) {
                        $code = $this->extractCodeFromMessage($connection, $messageId, $email, $subject);
                        if ($code) {
                            $codes[] = $code;
                            
                            // Si encontramos un código y early stop está activo, parar
                            if (($this->settings['EARLY_SEARCH_STOP'] ?? '1') === '1') {
                                return $this->createSuccessResponse($codes[0], $emailsFound);
                            }
                        }
                    }
                }
                
            } catch (\Exception $e) {
                error_log("Error procesando asunto '$subject': " . $e->getMessage());
                continue;
            }
        }
        
        if (!empty($codes)) {
            return $this->createSuccessResponse($codes[0], $emailsFound);
        }
        
        return $this->createNotFoundResponse(1, $emailsFound);
    }
    
    private function extractCodeFromMessage($connection, int $messageId, string $email, string $subject): ?array
    {
        try {
            $header = imap_headerinfo($connection, $messageId);
            $body = imap_body($connection, $messageId);
            
            // Verificar que el email coincida exactamente
            if (strcasecmp($header->from[0]->mailbox . '@' . $header->from[0]->host, $email) !== 0) {
                return null;
            }
            
            // Validar tiempo
            $messageTime = strtotime($header->date);
            $timeLimit = (int)($this->settings['EMAIL_QUERY_TIME_LIMIT_MINUTES'] ?? 30);
            $cutoffTime = time() - ($timeLimit * 60);
            
            if ($messageTime < $cutoffTime) {
                return null;
            }
            
            // Extraer código del cuerpo del mensaje
            $extractedCode = $this->extractVerificationCode($body);
            
            if ($extractedCode) {
                return [
                    'code' => $extractedCode,
                    'platform' => $subject,
                    'email' => $email,
                    'received_at' => date('Y-m-d H:i:s', $messageTime),
                    'message_id' => $messageId,
                    'raw_body' => $body
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            error_log("Error extrayendo código del mensaje: " . $e->getMessage());
            return null;
        }
    }
    
    private function extractVerificationCode(string $body): ?string
    {
        // Patrones para extraer códigos de verificación
        $patterns = [
            '/\b(\d{6})\b/',           // 6 dígitos
            '/\b(\d{4})\b/',           // 4 dígitos
            '/\b(\d{8})\b/',           // 8 dígitos
            '/código[:\s]*(\d+)/i',    // "código: 123456"
            '/verification[:\s]*(\d+)/i', // "verification: 123456"
            '/your code[:\s]*(\d+)/i',   // "your code: 123456"
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $code = $matches[1];
                // Validar que el código tenga un tamaño razonable
                if (strlen($code) >= 4 && strlen($code) <= 8) {
                    return $code;
                }
            }
        }
        
        return null;
    }
    
    private function logSearchAttempt(int $userId, string $email, string $platform): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO search_logs (user_id, email, platform, created_at, status) 
                VALUES (?, ?, ?, NOW(), 'searching')
            ");
            $stmt->bind_param('iss', $userId, $email, $platform);
            $stmt->execute();
            $this->lastLogId = $this->db->insert_id;
            $stmt->close();
        } catch (\Exception $e) {
            error_log("Error logging search attempt: " . $e->getMessage());
        }
    }
    
    private function updateSearchLog(int $logId, array $result): void
    {
        if ($logId <= 0) return;
        
        try {
            $status = $result['found'] ? 'found' : 'not_found';
            $details = json_encode($result);
            
            $stmt = $this->db->prepare("
                UPDATE search_logs 
                SET status = ?, result_details = ?, completed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param('ssi', $status, $details, $logId);
            $stmt->execute();
            $stmt->close();
        } catch (\Exception $e) {
            error_log("Error updating search log: " . $e->getMessage());
        }
    }
    
    private function createSuccessResponse(array $code, int $emailsFound = 0): array
    {
        return [
            'found' => true,
            'status' => 'success',
            'content' => $code['code'],
            'details' => $code,
            'message' => "Código encontrado: {$code['code']}",
            'emails_found_count' => $emailsFound
        ];
    }
    
    private function createNotFoundResponse(int $serversChecked = 1, int $emailsFound = 0): array
    {
        return [
            'found' => false,
            'status' => 'not_found',
            'message' => 'No se encontraron códigos de verificación',
            'servers_checked' => $serversChecked,
            'emails_found_count' => $emailsFound
        ];
    }
    
    private function createErrorResponse(string $message): array
    {
        return [
            'found' => false,
            'status' => 'error',
            'message' => $message,
            'error' => $message
        ];
    }
    
    /**
     * Obtiene el ID del último log de búsqueda para integración con Telegram
     */
    public function getLastLogId(): int
    {
        return $this->lastLogId;
    }
}