<?php
// Inicia una sesión para almacenar datos temporales
session_start();

// Incluimos los archivos necesarios
require_once 'funciones.php';
require_once 'decodificador.php';

// Verificar si el sistema está instalado. Si no, redirige al instalador.
if (!is_installed()) {
    header("Location: instalacion/instalador.php");
    exit();
}

// Conexión a la base de datos
require_once 'instalacion/basededatos.php';
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    // Manejar error de conexión de forma elegante
    die("Error de conexión. Contacte al administrador.");
}

// Incluir y verificar autenticación
require_once 'security/auth.php';
check_session(false, 'index.php', true);

// Obtener configuraciones del sistema (usa el sistema de caché)
$settings = SimpleCache::get_settings($conn);
$page_title = $settings['PAGE_TITLE'] ?? 'Sistema de Consulta';

// Lógica de logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// Recuperar y limpiar mensajes de sesión
$resultado = $_SESSION['resultado'] ?? '';
$resultado_tipo = $_SESSION['resultado_tipo'] ?? '';
$resultado_info = $_SESSION['resultado_info'] ?? [];
$error_message = $_SESSION['error_message'] ?? '';
$error_tipo = $_SESSION['error_tipo'] ?? '';

// Limpiar sesión
unset($_SESSION['resultado'], $_SESSION['resultado_tipo'], $_SESSION['resultado_info'], 
      $_SESSION['error_message'], $_SESSION['error_tipo']);

$user_logged_in = isset($_SESSION['user_id']);

// Instanciar motor de búsqueda y obtener emails autorizados para el usuario
$search_engine = new EmailSearchEngine($conn);
$authorized_emails = $search_engine->getUserAuthorizedEmails($_SESSION['user_id'] ?? 0);

$has_results = !empty($resultado) || !empty($error_message);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page_title) ?></title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles/modern_inicio.css">
  
  <!-- ESTILOS MINIMALISTAS + ANIMACIÓN NEON -->
  <style>
    /* Variables CSS para colores neon - Adaptables a tu tema */
    :root {
        --neon-cyan: #00ffff;
        --neon-purple: #8a2be2;
        --neon-pink: #ff1493;
        --neon-blue: #0066ff;
        --neon-green: #00ff41;
        --dark-bg: rgba(0, 0, 0, 0.95);
        --dark-card: rgba(20, 20, 30, 0.95);
        --glow-intensity: 0 0 20px;
    }
    
    /* Contenedor principal de resultados - ENFOQUE MINIMALISTA */
    .result-content-wrapper {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        margin: 20px 0;
        
        /* PERMITIR que el contenido original se muestre tal como es */
        width: 100%;
        max-width: 100%;
        overflow: visible;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    /* NO aplicar estilos agresivos al contenido del email */
    .result-content-wrapper * {
        /* Solo asegurar que no se salga del contenedor */
        max-width: 100% !important;
        box-sizing: border-box;
    }
    
    /* Mejorar solo imágenes para que sean responsivas */
    .result-content-wrapper img {
        max-width: 100% !important;
        height: auto !important;
        border-radius: 4px;
    }
    
    /* Tablas básicamente responsivas */
    .result-content-wrapper table {
        max-width: 100% !important;
        table-layout: auto;
        border-collapse: collapse;
    }
    
    /* Enlaces seguros */
    .result-content-wrapper a {
        word-break: break-all;
    }
    
    /* =====================================================
       ANIMACIÓN DE CARGA ESTILO NEON/CYBER
       ===================================================== */
    
    /* Overlay de carga sutil */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(3px);
    }
    
    /* Contenedor de la animación con estilo cyber */
    .loading-container {
        background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
        border: 2px solid var(--neon-cyan);
        border-radius: 15px;
        padding: 40px;
        text-align: center;
        box-shadow: 
            var(--glow-intensity) var(--neon-cyan),
            0 10px 30px rgba(0, 0, 0, 0.5);
        max-width: 400px;
        width: 90%;
        position: relative;
        overflow: hidden;
    }
    
    /* Efecto de líneas de código en el fondo */
    .loading-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: 
            linear-gradient(90deg, transparent 95%, rgba(0, 255, 255, 0.1) 100%),
            linear-gradient(0deg, transparent 95%, rgba(0, 255, 255, 0.06) 100%);
        background-size: 20px 20px;
        animation: matrixMove 3s linear infinite;
        pointer-events: none;
    }
    
    @keyframes matrixMove {
        0% { transform: translate(0, 0); }
        100% { transform: translate(20px, 20px); }
    }
    
    /* Spinner principal estilo neon */
    .loading-spinner {
        width: 60px;
        height: 60px;
        border: 3px solid transparent;
        border-top: 3px solid var(--neon-cyan);
        border-right: 3px solid var(--neon-purple);
        border-radius: 50%;
        animation: neonSpin 1s linear infinite;
        margin: 0 auto 20px auto;
        box-shadow: var(--glow-intensity) var(--neon-cyan);
        position: relative;
    }
    
    .loading-spinner::before {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        border: 1px solid var(--neon-pink);
        border-radius: 50%;
        animation: neonSpin 2s linear infinite reverse;
    }
    
    @keyframes neonSpin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Puntos animados estilo cyber */
    .loading-dots {
        display: inline-flex;
        gap: 6px;
        margin-left: 8px;
    }
    
    .loading-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--neon-cyan);
        box-shadow: var(--glow-intensity) var(--neon-cyan);
        animation: neonPulse 1.4s infinite ease-in-out both;
    }
    
    .loading-dots span:nth-child(1) { 
        animation-delay: -0.32s;
        background: var(--neon-cyan);
        box-shadow: var(--glow-intensity) var(--neon-cyan);
    }
    .loading-dots span:nth-child(2) { 
        animation-delay: -0.16s;
        background: var(--neon-purple);
        box-shadow: var(--glow-intensity) var(--neon-purple);
    }
    .loading-dots span:nth-child(3) { 
        animation-delay: 0s;
        background: var(--neon-pink);
        box-shadow: var(--glow-intensity) var(--neon-pink);
    }
    
    @keyframes neonPulse {
        0%, 80%, 100% {
            transform: scale(0.6);
            opacity: 0.5;
        }
        40% {
            transform: scale(1.2);
            opacity: 1;
        }
    }
    
    /* Texto de carga estilo cyber */
    .loading-text {
        font-size: 18px;
        font-weight: 600;
        color: var(--neon-cyan);
        margin-bottom: 10px;
        text-shadow: var(--glow-intensity) var(--neon-cyan);
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
    }
    
    .loading-subtext {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 20px;
        opacity: 0.9;
    }
    
    /* Barra de progreso estilo neon */
    .loading-progress {
        width: 100%;
        height: 8px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--neon-cyan);
        border-radius: 4px;
        overflow: hidden;
        margin-top: 20px;
        box-shadow: inset 0 0 10px rgba(0, 255, 255, 0.3);
    }
    
    .loading-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--neon-cyan), var(--neon-purple), var(--neon-pink));
        border-radius: 3px;
        animation: neonProgress 3s ease-in-out infinite;
        box-shadow: var(--glow-intensity) var(--neon-cyan);
    }
    
    @keyframes neonProgress {
        0% { width: 0%; }
        50% { width: 70%; }
        100% { width: 100%; }
    }
    
    /* Ícono de búsqueda con efecto neon */
    .loading-icon {
        font-size: 48px;
        color: var(--neon-cyan);
        margin-bottom: 15px;
        animation: neonBounce 2s infinite;
        text-shadow: var(--glow-intensity) var(--neon-cyan);
        filter: drop-shadow(0 0 15px var(--neon-cyan));
    }
    
    @keyframes neonBounce {
        0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-15px);
            text-shadow: 0 0 30px var(--neon-cyan);
        }
        60% {
            transform: translateY(-8px);
        }
    }
    
    /* Botón de búsqueda - Estado de carga con efecto neon */
    .btn-search-modern.loading {
        background: linear-gradient(135deg, var(--dark-card), rgba(0, 0, 0, 0.8));
        border: 2px solid var(--neon-purple);
        color: var(--neon-purple);
        cursor: not-allowed;
        position: relative;
        overflow: hidden;
        text-shadow: 0 0 10px var(--neon-purple);
        box-shadow: var(--glow-intensity) var(--neon-purple);
    }
    
    .btn-search-modern.loading::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(0, 255, 255, 0.4), transparent);
        animation: neonShimmer 2s infinite;
    }
    
    @keyframes neonShimmer {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    
    /* Efectos de partículas en el fondo */
    .loading-container::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: 
            radial-gradient(2px 2px at 20px 30px, var(--neon-cyan), transparent),
            radial-gradient(2px 2px at 40px 70px, var(--neon-purple), transparent),
            radial-gradient(1px 1px at 90px 40px, var(--neon-pink), transparent),
            radial-gradient(1px 1px at 130px 80px, var(--neon-green), transparent);
        background-repeat: repeat;
        background-size: 150px 150px;
        animation: particleFloat 8s linear infinite;
        opacity: 0.4;
        pointer-events: none;
    }
    
    @keyframes particleFloat {
        0% { transform: translate(0, 0); }
        100% { transform: translate(-150px, -150px); }
    }
    
    /* Responsivo para móviles */
    @media (max-width: 768px) {
        .result-content-wrapper {
            padding: 15px;
            margin: 15px 5px;
        }
        
        .loading-container {
            padding: 30px 20px;
            border-width: 1px;
        }
        
        .loading-text {
            font-size: 16px;
        }
        
        .loading-subtext {
            font-size: 13px;
        }
        
        .loading-icon {
            font-size: 40px;
        }
    }
    
    /* Mejorar alertas con estilo coherente */
    .alert-modern {
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    /* Botón de nueva búsqueda coherente */
    .btn-back {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
        padding: 12px 24px;
        border-radius: 25px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(108,117,125,0.3);
    }
    
    .btn-back:hover {
        background: linear-gradient(135deg, #5a6268, #495057);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(108,117,125,0.4);
    }
    
    /* Debug info styling */
    .debug-info {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
        font-family: monospace;
        font-size: 0.9em;
        overflow-x: auto;
    }
  </style>
</head>
<body>

<!-- OVERLAY DE CARGA ESTILO NEON -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-container">
        <div class="loading-icon">
            <i class="bi bi-search"></i>
        </div>
        <div class="loading-spinner"></div>
        <div class="loading-text">
            Conectando a servidores<span class="loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </div>
        <div class="loading-subtext">
            Esto puede tardar unos segundos...
        </div>
        <div class="loading-progress">
            <div class="loading-progress-bar"></div>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-dark navbar-modern fixed-top">
  <div class="container">
    <a class="navbar-brand" href="inicio.php">
      <i class="bi bi-code-slash"></i> <?= htmlspecialchars($page_title) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarOpciones" aria-controls="navbarOpciones" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarOpciones">
      <ul class="navbar-nav ms-auto">
        <?php if (is_admin()): ?>
          <li class="nav-item">
            <a class="nav-link" href="admin/admin.php"><i class="bi bi-person-badge-fill"></i> Panel Admin</a>
          </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= htmlspecialchars($settings['enlace_global_1'] ?? '#'); ?>" target="_blank"><i class="bi bi-globe"></i> <?= htmlspecialchars($settings['enlace_global_1_texto'] ?? 'Página Web'); ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= htmlspecialchars($settings['enlace_global_2'] ?? '#'); ?>" target="_blank"><i class="bi bi-telegram"></i> <?= htmlspecialchars($settings['enlace_global_2_texto'] ?? 'Telegram'); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="manual.php"><i class="bi bi-info-circle"></i> Manual</a>
        </li>
        <li class="nav-item">
            <?php
              $whatsappNumero = $settings['enlace_global_numero_whatsapp'] ?? '';
              $whatsappTexto = $settings['enlace_global_texto_whatsapp'] ?? '';
              $whatsappLink = 'https://wa.me/' . $whatsappNumero . '?text=' . urlencode($whatsappTexto);
            ?>
            <a class="nav-link" href="<?= htmlspecialchars($whatsappLink) ?>" target="_blank"><i class="bi bi-whatsapp"></i> Contacto</a>
        </li>
        <?php if ($user_logged_in): ?>
        <li class="nav-item">
          <a class="nav-link" href="inicio.php?logout=1"><i class="bi bi-box-arrow-right"></i> Salir</a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="main-container">
  <div class="main-card <?= $has_results ? 'expanded' : '' ?>">
    <div id="search-container" class="<?= $has_results ? 'hidden' : '' ?>">
        <div class="logo-container">
          <img src="/images/logo/<?= htmlspecialchars($settings['LOGO'] ?? 'logo.png'); ?>" alt="Logo" class="logo"/>
        </div>
        <h1 class="main-title" id="typing-title"></h1>
        <form action="funciones.php" method="POST" class="search-form" id="searchForm">
          <div class="form-group-modern">
            <input type="email" id="email" name="email" class="form-input-modern" placeholder="Correo a consultar" required maxlength="50" list="authorizedEmails"/>
            <i class="bi bi-envelope form-icon"></i>
          </div>
          <datalist id="authorizedEmails">
            <?php foreach ($authorized_emails as $email_option): ?>
              <option value="<?= htmlspecialchars($email_option) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="form-group-modern">
              <div class="custom-select-wrapper">
                  <div class="custom-select">
                      <div class="custom-select__trigger">
                          <span>Selecciona una plataforma</span>
                          <div class="arrow"></div>
                      </div>
                      <div class="custom-options">
                          <?php
                            $platforms_query = "SELECT p.name FROM platforms p ORDER BY p.sort_order ASC";
                            $platforms_result = $conn->query($platforms_query);
                            if ($platforms_result && $platforms_result->num_rows > 0) {
                                while ($platform_row = $platforms_result->fetch_assoc()) {
                                    $platform_name = htmlspecialchars($platform_row['name']);
                                    echo '<span class="custom-option" data-value="' . $platform_name . '">' . $platform_name . '</span>';
                                }
                            } else {
                                echo '<span class="custom-option" style="color: #888; pointer-events: none;">No hay plataformas</span>';
                            }
                          ?>
                      </div>
                  </div>
              </div>
              <i class="bi bi-grid-3x3-gap form-icon"></i>
              <input type="hidden" name="plataforma" id="plataforma" required>
          </div>
          <?php if ($user_logged_in): ?>
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($_SESSION['user_id']) ?>">
          <?php endif; ?>
          <button type="submit" class="btn-search-modern" id="searchButton">
              <span class="button-text">Buscar Códigos</span>
          </button>
        </form>
    </div>

<div id="results-container" class="results-container <?= !$has_results ? 'hidden' : '' ?>">
  <div style="text-align: center; margin-bottom: 1rem;">
      <a href="inicio.php" class="btn-back">
        <i class="bi bi-search"></i> Nueva Búsqueda
      </a>
  </div>

  <?php if (!empty($resultado)): ?>
    <?php if ($resultado_tipo === 'success'): ?>
      <div class="alert-modern alert-success-modern">
        <i class="bi bi-check-circle"></i>
        <strong>¡Éxito!</strong> Se encontró y procesó un resultado para tu consulta.
        <?php if (!empty($resultado_info['emails_found']) && $resultado_info['emails_found'] > 1): ?>
          <small class="d-block mt-1">
            (<?= $resultado_info['emails_found'] ?> emails encontrados, procesado el más reciente)
          </small>
        <?php endif; ?>
      </div>
      
      <!-- CONTENIDO DEL EMAIL - ENFOQUE MINIMALISTA -->
      <div class="result-content-wrapper">
        <?php
        // MOSTRAR EL CONTENIDO TAL COMO VIENE, SIN PROCESAMIENTO ADICIONAL
        if (empty(trim($resultado))) {
            echo '<div style="text-align: center; padding: 40px; color: #6c757d;">
                    <i class="bi bi-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                    <h4>Contenido vacío</h4>
                    <p>El email se encontró pero no contiene contenido visible.</p>
                  </div>';
        } else {
            // MOSTRAR CONTENIDO ORIGINAL DIRECTAMENTE
            echo $resultado;
        }
        ?>
      </div>
      
    <?php elseif ($resultado_tipo === 'found_but_unprocessable'): ?>
      <div class="alert-modern alert-warning-modern">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Emails Encontrados</strong> pero sin contenido procesable.
      </div>
      <div class="alert alert-warning text-center">
        <strong><?= htmlspecialchars($resultado) ?></strong>
        <?php if (!empty($resultado_info['emails_found'])): ?>
          <hr>
          <small>
            <i class="bi bi-info-circle"></i> 
            Se encontraron <?= $resultado_info['emails_found'] ?> emails que coinciden con los criterios, 
            pero ninguno contenía datos válidos o decodificables.
          </small>
        <?php endif; ?>
        <hr>
        <small class="text-muted">
          💡 <strong>Posibles causas:</strong> 
          • Los emails están vacíos o dañados
          • Problemas de codificación en el contenido
          • Los emails no contienen el formato esperado
        </small>
      </div>
      
    <?php elseif ($resultado_tipo === 'not_found'): ?>
      <div class="alert-modern alert-info-modern">
        <i class="bi bi-info-circle"></i>
        <strong>Sin Resultados</strong>
      </div>
      <div class="alert alert-info text-center">
        <strong><?= htmlspecialchars($resultado) ?></strong>
        <hr>
        <small class="text-muted">
          💡 <strong>Sugerencias:</strong>
          • Verifica que el email sea correcto
          • Los códigos pueden tardar unos minutos en llegar
          • Revisa la carpeta de spam/correo no deseado
        </small>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($error_message)): ?>
    <div class="alert-modern alert-danger-modern">
      <i class="bi bi-exclamation-triangle"></i>
      <strong>Error</strong>
    </div>
    <div class="alert alert-danger text-center">
      <?= htmlspecialchars($error_message) ?>
    </div>
  <?php endif; ?>
</div>

<!-- Debug info para administradores -->
<?php if (is_admin() && isset($_GET['debug']) && !empty($resultado)): ?>
<div class="debug-info">
    <h5><i class="bi bi-bug"></i> Información de Debug (Solo Admin)</h5>
    <strong>Tipo de resultado:</strong> <?= htmlspecialchars($resultado_tipo) ?><br>
    <strong>Información adicional:</strong> <?= htmlspecialchars(json_encode($resultado_info)) ?><br>
    <strong>Longitud del contenido:</strong> <?= strlen($resultado) ?> caracteres<br>
    <strong>Contiene HTML:</strong> <?= preg_match('/<[^>]+>/', $resultado) ? 'Sí' : 'No' ?><br>
    <strong>Muestra de contenido (primeros 200 chars):</strong><br>
    <code style="background: white; padding: 10px; display: block; margin: 10px 0; border-radius: 4px; max-height: 100px; overflow-y: auto;">
        <?= htmlspecialchars(substr($resultado, 0, 200)) ?><?= strlen($resultado) > 200 ? '...' : '' ?>
    </code>
</div>
<?php endif; ?>

<footer class="footer-modern">
    <p>¿Interesado en un sistema similar? 
<a href="https://wa.me/573232405812" target="_blank">Contacta para más información</a>
    </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Lógica del título que se escribe solo ---
    const title = document.getElementById('typing-title');
    if (title) {
        const titleText = 'Consulta tu Código Aquí';
        let i = 0;
        title.innerHTML = '';
        function typeWriter() {
            if (i < titleText.length) {
                title.innerHTML += titleText.charAt(i);
                i++;
                setTimeout(typeWriter, 80);
            }
        }
        setTimeout(typeWriter, 500);
    }
    
    // ================================================================
    // --- LÓGICA PARA EL MENÚ DESPLEGABLE ---
    // ================================================================
    const mainCard = document.querySelector('.main-card');
    const customSelect = document.querySelector('.custom-select');
    
    if (mainCard && customSelect) {
        const trigger = customSelect.querySelector('.custom-select__trigger');
        const options = customSelect.querySelectorAll('.custom-option');
        const hiddenInput = document.getElementById('plataforma');
        const triggerSpan = trigger.querySelector('span');
        
        trigger.addEventListener('click', (e) => {
            e.stopPropagation(); 
            const isOpen = customSelect.classList.toggle('open');
            mainCard.classList.toggle('options-open', isOpen);
        });
        
        options.forEach(option => {
            option.addEventListener('click', function() {
                if (this.hasAttribute('data-value')) {
                    triggerSpan.textContent = this.textContent;
                    triggerSpan.style.color = 'var(--text-primary)';
                    hiddenInput.value = this.getAttribute('data-value');
                    
                    customSelect.classList.remove('open');
                    mainCard.classList.remove('options-open');
                }
            });
        });
        
        window.addEventListener('click', () => {
            if (customSelect.classList.contains('open')) {
                customSelect.classList.remove('open');
                mainCard.classList.remove('options-open');
            }
        });
    }
    
    // ================================================================
    // --- ANIMACIÓN DE CARGA ESTILO NEON/CYBER ---
    // ================================================================
    
    const searchForm = document.getElementById('searchForm');
    const searchButton = document.getElementById('searchButton');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    if (searchForm && searchButton && loadingOverlay) {
        
        // Interceptar el envío del formulario
        searchForm.addEventListener('submit', function(e) {
            // Validar que todos los campos estén llenos
            const email = document.getElementById('email').value.trim();
            const plataforma = document.getElementById('plataforma').value.trim();
            
            if (!email || !plataforma) {
                // Dejar que la validación nativa del navegador funcione
                return true;
            }
            
            // Mostrar animación de carga
            showNeonLoadingAnimation();
            
            // Modificar el botón
            searchButton.classList.add('loading');
            searchButton.disabled = true;
            searchButton.querySelector('.button-text').textContent = 'PROCESANDO...';
            
            // No prevenir el envío - dejar que funcione normalmente
        });
        
        // Función para mostrar la animación estilo neon
        function showNeonLoadingAnimation() {
            loadingOverlay.style.display = 'flex';
            
            // Agregar efecto de fade-in
            setTimeout(() => {
                loadingOverlay.style.opacity = '1';
            }, 10);
            
            // Textos de carga estilo cyber
            const neonLoadingTexts = [
                'CONECTANDO A SERVIDORES',
                'ACCEDIENDO A BANDEJAS IMAP',
                'ESCANEANDO MENSAJES RECIENTES',
                'DECODIFICANDO DATOS ENCRYPTED',
                'PROCESANDO ALGORITMOS',
                'FINALIZANDO BÚSQUEDA'
            ];
            
            let textIndex = 0;
            const loadingTextElement = document.querySelector('.loading-text');
            
            const textInterval = setInterval(() => {
                if (textIndex < neonLoadingTexts.length) {
                    loadingTextElement.innerHTML = neonLoadingTexts[textIndex] + '<span class="loading-dots"><span></span><span></span><span></span></span>';
                    textIndex++;
                } else {
                    clearInterval(textInterval);
                }
            }, 1200);
            
            // Timeout de seguridad
            setTimeout(() => {
                hideNeonLoadingAnimation();
            }, 30000); // 30 segundos máximo
        }
        
        // Función para ocultar la animación
        function hideNeonLoadingAnimation() {
            loadingOverlay.style.opacity = '0';
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
                
                // Restaurar botón
                searchButton.classList.remove('loading');
                searchButton.disabled = false;
                searchButton.querySelector('.button-text').textContent = 'Buscar Códigos';
            }, 300);
        }
        
        // Ocultar animación si la página se carga con resultados
        if (document.querySelector('.results-container:not(.hidden)')) {
            hideNeonLoadingAnimation();
        }
    }
    
    // ================================================================
    // --- LOG BÁSICO PARA DEBUGGING ---
    // ================================================================
    
    const contentContainer = document.querySelector('.result-content-wrapper');
    if (contentContainer) {
        console.log('📊 Contenido cargado:', {
            'Caracteres': contentContainer.innerHTML.length,
            'Elementos': contentContainer.querySelectorAll('*').length
        });
    }
});

// Ocultar loading si el usuario navega hacia atrás
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
        }
    }
});
</script>

</body>
</html>
