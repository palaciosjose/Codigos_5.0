<?php
/**
 * Renovar Licencia - Sistema de Códigos
 * Permite renovar la licencia sin reinstalar el sistema
 */

// Evitar bucle de verificación de licencia
define('INSTALLER_MODE', true);

session_start();

// Incluir archivos necesarios
require_once __DIR__ . '/license_client.php';

// Verificar que el sistema esté instalado
if (!file_exists('config/db_credentials.php')) {
    header('Location: instalacion/instalador.php');
    exit();
}

$license_client = new ClientLicense();

// Variables para mensajes
$license_success = '';
$license_error = '';
$license_warning = '';
$renewal_successful = false;

// Procesar renovación de licencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_license'])) {
    $license_key = trim($_POST['license_key'] ?? '');
    
    if (empty($license_key)) {
        $license_error = 'Por favor, ingrese una clave de licencia válida.';
    } else {
        try {
            $activation_result = $license_client->activateLicense($license_key);
            
            if ($activation_result['success']) {
                $verification_attempts = 0;
                $max_attempts = 3;
                $license_verified = false;
                
                while ($verification_attempts < $max_attempts && !$license_verified) {
                    sleep(1);
                    $license_verified = $license_client->isLicenseValid();
                    $verification_attempts++;
                }
                
                if ($license_verified) {
                    $license_success = 'Licencia renovada y verificada exitosamente. El sistema ya está disponible.';
                    $renewal_successful = true;
                } else {
                    $license_warning = 'Licencia renovada exitosamente, pero la verificación tardó más de lo esperado. Intente acceder al sistema en unos momentos.';
                }
            } else {
                $license_error = $activation_result['message'];
            }
        } catch (Exception $e) {
            $license_error = 'Error durante la renovación: ' . $e->getMessage();
            error_log('Error renovación licencia: ' . $e->getMessage());
        }
    }
}

// Obtener información actual de la licencia
$current_license_info = $license_client->getLicenseInfo();
$diagnostic_info = $license_client->getDiagnosticInfo();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renovar Licencia - Sistema de Códigos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .renewal-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .header-icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .license-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .diagnostic-info {
            background: #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
        
        .btn-renew {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-renew:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .current-license {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="renewal-container">
            <div class="text-center">
                <div class="header-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="h3 mb-3">Renovar Licencia del Sistema</h1>
                <p class="text-muted mb-4">
                    Actualice su licencia para continuar usando el sistema sin interrupciones.
                </p>
            </div>

            <?php if (!empty($license_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>¡Excelente!</strong> <?= htmlspecialchars($license_success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-home me-2"></i>Ir al Sistema
                    </a>
                </div>
                
            <?php elseif (!empty($license_warning)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Atención:</strong> <?= htmlspecialchars($license_warning) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                
            <?php elseif (!empty($license_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-times-circle me-2"></i>
                    <strong>Error:</strong> <?= htmlspecialchars($license_error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($current_license_info): ?>
                <div class="current-license">
                    <h6><i class="fas fa-info-circle me-2"></i>Información de Licencia Actual</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Dominio:</strong> <?= htmlspecialchars($current_license_info['domain']) ?><br>
                            <strong>Estado:</strong> <?= htmlspecialchars($current_license_info['status']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Activada:</strong> <?= htmlspecialchars($current_license_info['activated_at']) ?><br>
                            <strong>Última verificación:</strong> <?= htmlspecialchars($current_license_info['last_check']) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$renewal_successful): ?>
                <form method="POST" class="license-form">
                    <h5 class="mb-3">
                        <i class="fas fa-key me-2"></i>Ingrese su Nueva Clave de Licencia
                    </h5>
                    
                    <div class="mb-3">
                        <label for="license_key" class="form-label">Clave de Licencia</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-shield-alt"></i>
                            </span>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="license_key" 
                                   name="license_key" 
                                   placeholder="Ingrese su clave de licencia"
                                   value="<?= htmlspecialchars($_POST['license_key'] ?? '') ?>"
                                   required>
                        </div>
                        <div class="form-text">
                            Ingrese la nueva clave de licencia proporcionada por su proveedor.
                        </div>
                    </div>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Información importante:</strong><br>
                        • La licencia se activará para el dominio: <strong><?= htmlspecialchars($_SERVER['HTTP_HOST']) ?></strong><br>
                        • Se verificará automáticamente la validez con el servidor de licencias<br>
                        • La renovación no afectará sus datos ni configuraciones existentes<br>
                        • Se requiere conexión a internet para la activación
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <button type="submit" name="renew_license" class="btn btn-renew btn-lg me-md-2">
                            <i class="fas fa-sync-alt me-2"></i>Renovar Licencia
                        </button>
                        <a href="mailto:soporte@tudominio.com" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-envelope me-2"></i>Contactar Soporte
                        </a>
                    </div>
                </form>

                <div class="diagnostic-info">
                    <h6><i class="fas fa-wrench me-2"></i>Información del Sistema</h6>
                    <strong>Directorio de licencias existe:</strong> <?= $diagnostic_info['directory_exists'] ? 'Sí' : 'No' ?><br>
                    <strong>Archivo de licencia existe:</strong> <?= $diagnostic_info['file_exists'] ? 'Sí' : 'No' ?><br>
                    <strong>Permisos de escritura:</strong> <?= $diagnostic_info['directory_writable'] ? 'Sí' : 'No' ?><br>
                    <strong>Dominio actual:</strong> <?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <hr>
                <p class="text-muted">
                    <small>
                        <i class="fas fa-question-circle me-1"></i>
                        ¿Problemas con la renovación? 
                        <a href="mailto:soporte@tudominio.com">Contacte al soporte técnico</a>
                    </small>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($renewal_successful): ?>
    <script>
        // Auto-redirigir después de 5 segundos si la renovación fue exitosa
        setTimeout(function() {
            if (confirm('Licencia renovada exitosamente. ¿Desea ir al sistema ahora?')) {
                window.location.href = 'index.php';
            }
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>