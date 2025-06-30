<?php
/**
 * Cliente de Licencias - VERSIÓN CORREGIDA
 * Solución al problema de rutas relativas
 */

class ClientLicense {
    private $license_server;
    private $license_dir;
    private $license_file;
    
    public function __construct($license_server = null) {
        $this->license_server = $license_server ?: 'https://scode.warsup.shop/api.php';
        
        // ==========================================
        // SOLUCIÓN AL PROBLEMA DE RUTAS
        // ==========================================
        
        // Verificar si las constantes ya están definidas (desde el instalador)
        if (defined('LICENSE_DIR') && defined('LICENSE_FILE')) {
            // Usar las constantes definidas por el instalador
            $this->license_dir = LICENSE_DIR;
            $this->license_file = LICENSE_FILE;
        } else {
            // Definir rutas basadas en la raíz del proyecto
            $this->license_dir = $this->getProjectRoot() . '/license';
            $this->license_file = $this->license_dir . '/license.dat';
            
            // Definir constantes para uso futuro
            if (!defined('LICENSE_DIR')) {
                define('LICENSE_DIR', $this->license_dir);
            }
            if (!defined('LICENSE_FILE')) {
                define('LICENSE_FILE', $this->license_file);
            }
        }
        
        // Asegurar que el directorio existe
        $this->ensureLicenseDirectoryExists();
    }
    
    /**
     * Obtener la ruta raíz del proyecto independientemente de dónde se ejecute
     */
    private function getProjectRoot() {
        // Si está definida la constante PROJECT_ROOT, usarla
        if (defined('PROJECT_ROOT')) {
            return PROJECT_ROOT;
        }
        
        // Detectar la raíz del proyecto buscando archivos característicos
        $current_dir = __DIR__;
        $max_levels = 5; // Máximo 5 niveles hacia arriba
        $level = 0;
        
        while ($level < $max_levels) {
            // Buscar archivos que indiquen la raíz del proyecto
            $markers = [
                'index.php',
                'inicio.php', 
                'config/config.php',
                'instalacion/basededatos.php'
            ];
            
            foreach ($markers as $marker) {
                if (file_exists($current_dir . '/' . $marker)) {
                    return $current_dir;
                }
            }
            
            // Subir un nivel
            $parent_dir = dirname($current_dir);
            if ($parent_dir === $current_dir) {
                // Llegamos a la raíz del sistema, parar
                break;
            }
            $current_dir = $parent_dir;
            $level++;
        }
        
        // Si no se encuentra, usar el directorio actual como fallback
        return __DIR__;
    }
    
    /**
     * Asegurar que el directorio de licencias existe
     */
    private function ensureLicenseDirectoryExists() {
        if (!file_exists($this->license_dir)) {
            if (!mkdir($this->license_dir, 0755, true)) {
                error_log("Error: No se pudo crear el directorio de licencias: " . $this->license_dir);
                return false;
            }
            
            // Crear .htaccess para proteger el directorio
            $htaccess_content = "Deny from all\n<Files \"*.dat\">\nDeny from all\n</Files>";
            file_put_contents($this->license_dir . '/.htaccess', $htaccess_content);
        }
        
        return true;
    }
    /**
     * Verificar si existe archivo de licencia
     */
    public function hasLicense() {
        return file_exists($this->license_file) && is_readable($this->license_file);
    }

    
    /**
     * Activar licencia
     */
    public function activateLicense($license_key) {
        try {
            $domain = $_SERVER['HTTP_HOST'];
            $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            
            $data = [
                'action' => 'activate',
                'license_key' => $license_key,
                'domain' => $domain,
                'ip' => $ip,
                'software' => 'Sistema de Códigos',
                'version' => '2.0'
            ];
            
            $response = $this->makeRequest($data);
            
            if ($response && $response['success']) {
                // Guardar la licencia en el archivo correcto
                $license_data = [
                    'license_key' => $license_key,
                    'domain' => $domain,
                    'activated_at' => date('Y-m-d H:i:s'),
                    'last_check' => time(),
                    'status' => 'active',
                    'server_response' => $response
                ];
                
                if ($this->saveLicenseData($license_data)) {
                    error_log("Licencia guardada exitosamente en: " . $this->license_file);
                    return ['success' => true, 'message' => 'Licencia activada correctamente'];
                } else {
                    error_log("Error guardando licencia en: " . $this->license_file);
                    return ['success' => false, 'message' => 'Error guardando la licencia'];
                }
            } else {
                $error_msg = $response['message'] ?? 'Error desconocido del servidor';
                return ['success' => false, 'message' => $error_msg];
            }
            
        } catch (Exception $e) {
            error_log("Error activando licencia: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verificar si la licencia es válida
     */
    public function isLicenseValid() {
        // Verificar en modo instalador
        if (defined('INSTALLER_MODE') && INSTALLER_MODE) {
            return $this->hasLicense();
        }
        
        if (!$this->hasLicense()) {
            return false;
        }
        
        $license_data = $this->getLicenseData();
        if (!$license_data) {
            return false;
        }
        
        // Verificar si necesita validación remota (cada 24 horas)
        $last_check = $license_data['last_check'] ?? 0;
        if ((time() - $last_check) > 86400) { // 24 horas
            return $this->validateWithServer($license_data);
        }
        
        return $license_data['status'] === 'active';
    }
    
    
    /**
     * Obtener información de la licencia
     */
    public function getLicenseInfo() {
        if (!$this->hasLicense()) {
            return null;
        }
        
        $license_data = $this->getLicenseData();
        if (!$license_data) {
            return null;
        }
        
        return [
            'domain' => $license_data['domain'] ?? '',
            'activated_at' => $license_data['activated_at'] ?? '',
            'status' => $license_data['status'] ?? 'unknown',
            'last_check' => date('Y-m-d H:i:s', $license_data['last_check'] ?? 0),
            'file_path' => $this->license_file
        ];
    }
    
    /**
     * Guardar datos de licencia en archivo
     */
    private function saveLicenseData($data) {
        try {
            $encoded = base64_encode(serialize($data));
            $result = file_put_contents($this->license_file, $encoded, LOCK_EX);
            
            if ($result !== false) {
                chmod($this->license_file, 0644);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error guardando licencia: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Leer datos de licencia desde archivo
     */
    private function getLicenseData() {
        try {
            if (!$this->hasLicense()) {
                return null;
            }
            
            $content = file_get_contents($this->license_file);
            if ($content === false) {
                return null;
            }
            
            $decoded = base64_decode($content);
            if ($decoded === false) {
                return null;
            }
            
            $data = unserialize($decoded);
            if ($data === false) {
                return null;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error leyendo licencia: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validar licencia con el servidor remoto
     */
    private function validateWithServer($license_data) {
        try {
            $data = [
                'action' => 'validate',
                'license_key' => $license_data['license_key'] ?? '',
                'domain' => $_SERVER['HTTP_HOST'],
                'current_domain' => $license_data['domain'] ?? ''
            ];
            
            $response = $this->makeRequest($data);
            
            if ($response && $response['success']) {
                // Actualizar datos de licencia
                $license_data['last_check'] = time();
                $license_data['status'] = 'active';
                $this->saveLicenseData($license_data);
                return true;
            } else {
                // Marcar como inválida pero conservar archivo para debugging
                $license_data['last_check'] = time();
                $license_data['status'] = 'invalid';
                $this->saveLicenseData($license_data);
                return false;
            }
        } catch (Exception $e) {
            error_log("Error validando licencia: " . $e->getMessage());
            // En caso de error de red, mantener válida si no ha expirado hace mucho
            $grace_period = 7 * 24 * 3600; // 7 días
            return (time() - ($license_data['last_check'] ?? 0)) < $grace_period;
        }
    }
    
    /**
     * Realizar petición HTTP al servidor de licencias
     */
    private function makeRequest($data, $timeout = 10) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL no está disponible');
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->license_server,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'License-Client/2.0'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($error)) {
            throw new Exception("Error cURL: " . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception("HTTP Error: " . $http_code);
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error decodificando respuesta JSON");
        }
        
        return $decoded;
    }
    
    /**
     * Obtener información de diagnóstico
     */
    public function getDiagnosticInfo() {
        return [
            'license_dir' => $this->license_dir,
            'license_file' => $this->license_file,
            'directory_exists' => file_exists($this->license_dir),
            'directory_writable' => is_writable($this->license_dir),
            'file_exists' => file_exists($this->license_file),
            'file_readable' => is_readable($this->license_file),
            'file_writable' => is_writable($this->license_file),
            'project_root' => $this->getProjectRoot(),
            'current_dir' => getcwd(),
            'script_dir' => __DIR__,
            'constants_defined' => [
                'LICENSE_DIR' => defined('LICENSE_DIR'),
                'LICENSE_FILE' => defined('LICENSE_FILE'),
                'PROJECT_ROOT' => defined('PROJECT_ROOT'),
                'INSTALLER_MODE' => defined('INSTALLER_MODE')
            ]
        ];
    }
}

// ==========================================
// VERIFICACIÓN AUTOMÁTICA (SOLO SI NO ES INSTALADOR)
// ==========================================
if (!defined('INSTALLER_MODE') || !INSTALLER_MODE) {
    $license_client = new ClientLicense();
    
    // Verificar licencia en páginas públicas
    $exempt_files = ['index.php', 'inicio.php'];
    $current_file = basename($_SERVER['SCRIPT_NAME']);
    
    if (!in_array($current_file, $exempt_files)) {
        if (!$license_client->isLicenseValid()) {
            // Redirigir a página de error de licencia o bloquear acceso
            header('HTTP/1.1 403 Forbidden');
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Licencia Requerida</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
                    .error-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; margin: 20px auto; width: 50%; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class="error-box">
                    <h1>Licencia Requerida</h1>
                    <p>Este software requiere una licencia válida para funcionar.</p>
                    <p>Contacte al administrador del sistema.</p>
                </div>
            </body>
            </html>';
            exit;
        }
    }
}
?>
