<?php
/**
 * Simple Composer Installer - Versión Simplificada
 * Sube este archivo a la raíz del proyecto y ábrelo en el navegador
 */

// Configuración básica
set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalador Simple de Composer</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .status { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 Instalador Simple de Composer</h1>
        
        <?php
        
        // Función para mostrar mensajes
        function showMessage($message, $type = 'info') {
            echo "<div class='step $type'>$message</div>";
        }
        
        // Función para verificar el estado actual
        function checkStatus() {
            $status = [];
            $status['vendor_exists'] = file_exists('vendor/autoload.php');
            $status['composer_phar'] = file_exists('composer.phar');
            $status['composer_json'] = file_exists('composer.json');
            $status['writable'] = is_writable('.');
            $status['exec_available'] = function_exists('exec');
            $status['curl_available'] = function_exists('curl_init');
            $status['allow_url_fopen'] = ini_get('allow_url_fopen');
            
            return $status;
        }
        
        // Función para crear autoloader básico
        function createSimpleAutoloader() {
            // Crear directorio vendor
            if (!is_dir('vendor')) {
                if (!mkdir('vendor', 0755, true)) {
                    return false;
                }
            }
            
            // Contenido del autoloader
            $autoloader = '<?php
// Autoloader simple para telegram bot
spl_autoload_register(function ($class) {
    $class = str_replace("\\\\", "/", $class);
    $files = [
        __DIR__ . "/../telegram_bot/" . $class . ".php",
        __DIR__ . "/../config/" . $class . ".php",
        __DIR__ . "/../" . $class . ".php"
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});

// Definir constante
define("AUTOLOADER_LOADED", true);
';
            
            return file_put_contents('vendor/autoload.php', $autoloader);
        }
        
        // Procesar acciones
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            
            if ($action === 'download_composer') {
                showMessage('🔄 Descargando Composer...', 'info');
                
                // Descargar composer.phar
                $composer_url = 'https://getcomposer.org/composer.phar';
                $composer_data = @file_get_contents($composer_url);
                
                if ($composer_data && file_put_contents('composer.phar', $composer_data)) {
                    chmod('composer.phar', 0755);
                    showMessage('✅ Composer descargado exitosamente', 'success');
                } else {
                    showMessage('❌ Error descargando Composer', 'error');
                }
            }
            
            elseif ($action === 'install_deps') {
                showMessage('🔄 Instalando dependencias...', 'info');
                
                if (!file_exists('composer.phar')) {
                    showMessage('❌ composer.phar no encontrado', 'error');
                } elseif (!file_exists('composer.json')) {
                    showMessage('❌ composer.json no encontrado', 'error');
                } else {
                    if (function_exists('exec')) {
                        // Crear directorio temporal para Composer
                        $tempDir = getcwd() . '/temp_composer';
                        if (!is_dir($tempDir)) {
                            mkdir($tempDir, 0755, true);
                        }
                        
                        // Configurar variables de entorno para Composer
                        $env_vars = [
                            'COMPOSER_HOME=' . $tempDir,
                            'HOME=' . $tempDir,
                            'COMPOSER_CACHE_DIR=' . $tempDir . '/cache'
                        ];
                        
                        $output = [];
                        $return_var = 0;
                        
                        // Comando con variables de entorno
                        $cmd = implode(' ', $env_vars) . ' php composer.phar install --no-dev --no-interaction --no-cache 2>&1';
                        
                        showMessage('🔧 Ejecutando: ' . $cmd, 'info');
                        
                        exec($cmd, $output, $return_var);
                        
                        if ($return_var === 0) {
                            showMessage('✅ Dependencias instaladas exitosamente', 'success');
                            echo '<pre>' . implode("\n", array_slice($output, -10)) . '</pre>';
                            
                            // Limpiar directorio temporal
                            if (is_dir($tempDir)) {
                                exec('rm -rf ' . $tempDir);
                            }
                        } else {
                            showMessage('❌ Error instalando dependencias', 'error');
                            echo '<pre>' . implode("\n", $output) . '</pre>';
                            
                            showMessage('💡 Intentando método alternativo...', 'warning');
                            
                            // Método alternativo: sin cache y con directorio home específico
                            $cmd2 = 'COMPOSER_HOME=' . $tempDir . ' php composer.phar install --no-dev --no-interaction --no-cache --no-plugins 2>&1';
                            
                            $output2 = [];
                            $return_var2 = 0;
                            exec($cmd2, $output2, $return_var2);
                            
                            if ($return_var2 === 0) {
                                showMessage('✅ Instalación exitosa con método alternativo', 'success');
                                echo '<pre>' . implode("\n", array_slice($output2, -10)) . '</pre>';
                            } else {
                                showMessage('❌ Ambos métodos fallaron. Creando autoloader básico...', 'error');
                                echo '<pre>' . implode("\n", $output2) . '</pre>';
                                
                                if (createSimpleAutoloader()) {
                                    showMessage('✅ Autoloader básico creado como alternativa', 'success');
                                } else {
                                    showMessage('❌ Error creando autoloader', 'error');
                                }
                            }
                            
                            // Limpiar directorio temporal
                            if (is_dir($tempDir)) {
                                exec('rm -rf ' . $tempDir);
                            }
                        }
                    } else {
                        showMessage('❌ La función exec() no está disponible', 'error');
                        showMessage('💡 Creando autoloader básico como alternativa...', 'warning');
                        
                        if (createSimpleAutoloader()) {
                            showMessage('✅ Autoloader básico creado', 'success');
                        } else {
                            showMessage('❌ Error creando autoloader', 'error');
                        }
                    }
                }
            }
            
            elseif ($action === 'create_basic') {
                showMessage('🔄 Creando autoloader básico...', 'info');
                
                if (createSimpleAutoloader()) {
                    showMessage('✅ Autoloader básico creado exitosamente', 'success');
                    showMessage('ℹ️ Esto debería eliminar el error de autoload', 'info');
                } else {
                    showMessage('❌ Error creando autoloader básico', 'error');
                }
            }
            
            elseif ($action === 'delete') {
                if (unlink(__FILE__)) {
                    echo '<div class="step success">✅ Archivo eliminado</div>';
                    echo '<a href="admin/telegram_management.php" class="btn">Ir al Panel</a>';
                    exit;
                } else {
                    showMessage('❌ Error eliminando archivo', 'error');
                }
            }
        }
        
        // Mostrar estado actual
        $status = checkStatus();
        
        echo '<h2>📋 Estado del Sistema</h2>';
        echo '<div class="step info">';
        echo '<strong>PHP:</strong> ' . PHP_VERSION . '<br>';
        echo '<strong>Directorio:</strong> ' . getcwd() . '<br>';
        echo '<strong>Vendor/autoload.php:</strong> ' . ($status['vendor_exists'] ? '✅ Existe' : '❌ No existe') . '<br>';
        echo '<strong>composer.phar:</strong> ' . ($status['composer_phar'] ? '✅ Existe' : '❌ No existe') . '<br>';
        echo '<strong>composer.json:</strong> ' . ($status['composer_json'] ? '✅ Existe' : '❌ No existe') . '<br>';
        echo '<strong>Permisos escritura:</strong> ' . ($status['writable'] ? '✅ OK' : '❌ Sin permisos') . '<br>';
        echo '<strong>Función exec():</strong> ' . ($status['exec_available'] ? '✅ Disponible' : '❌ No disponible') . '<br>';
        echo '<strong>cURL:</strong> ' . ($status['curl_available'] ? '✅ Disponible' : '❌ No disponible') . '<br>';
        echo '<strong>allow_url_fopen:</strong> ' . ($status['allow_url_fopen'] ? '✅ Habilitado' : '❌ Deshabilitado') . '<br>';
        echo '</div>';
        
        // Mostrar opciones basadas en el estado
        if ($status['vendor_exists']) {
            showMessage('🎉 ¡Composer ya está instalado! El autoloader existe.', 'success');
            echo '<a href="admin/telegram_management.php" class="btn btn-success">🔙 Ir al Panel de Telegram</a>';
            echo '<a href="?action=delete" class="btn btn-danger">🗑️ Eliminar este archivo</a>';
        } else {
            echo '<h2>🛠️ Opciones de Instalación</h2>';
            
            if (!$status['composer_json']) {
                showMessage('❌ No se encuentra composer.json. Verifica que estés en el directorio correcto.', 'error');
            } elseif (!$status['writable']) {
                showMessage('❌ Sin permisos de escritura. Cambia los permisos del directorio a 755.', 'error');
            } else {
                echo '<div class="step">';
                echo '<h3>Método 1: Instalación Completa (Recomendado)</h3>';
                echo '<p>Descarga composer.phar e instala todas las dependencias</p>';
                
                if (!$status['composer_phar']) {
                    echo '<a href="?action=download_composer" class="btn">📥 1. Descargar Composer</a>';
                } else {
                    echo '<span class="status">✅ Composer descargado</span><br>';
                    echo '<a href="?action=install_deps" class="btn">📦 2. Instalar Dependencias</a>';
                }
                echo '</div>';
                
                echo '<div class="step">';
                echo '<h3>Método 2: Autoloader Básico (Alternativo)</h3>';
                echo '<p>Crea un autoloader simple que elimina el error pero con funcionalidad limitada</p>';
                echo '<a href="?action=create_basic" class="btn btn-success">🔧 Crear Autoloader Básico</a>';
                echo '</div>';
            }
        }
        
        echo '<div class="step warning">';
        echo '<h3>⚠️ Notas Importantes</h3>';
        echo '<ul>';
        echo '<li>Elimina este archivo después de usarlo</li>';
        echo '<li>Si exec() no está disponible, solo funcionará el autoloader básico</li>';
        echo '<li>El autoloader básico puede tener limitaciones</li>';
        echo '</ul>';
        echo '</div>';
        
        ?>
    </div>
</body>
</html>