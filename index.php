<?php
// Iniciar la sesión
session_start();

// Incluir el archivo de funciones centralizadas
require_once 'funciones.php';

// Verificar si el sistema está instalado
if (!is_installed()) {
    header("Location: instalacion/instalador.php");
    exit();
}

// Incluir archivos de configuración
if (file_exists('instalacion/basededatos.php')) {
    require_once 'instalacion/basededatos.php';
}

// Evitar que usuarios ya logueados vean esta página
if (isset($_SESSION['user_id'])) {
    header("Location: inicio.php");
    exit();
}

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Lógica de login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    // Prioridad para administradores
    $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['last_activity'] = time();
            header("Location: inicio.php");
            exit();
        }
    }

    // Si no es admin, buscar en usuarios regulares
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? AND status = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = 'usuario';
            $_SESSION['last_activity'] = time();
            header("Location: inicio.php");
            exit();
        }
    }
    
    $login_error = "Usuario o contraseña incorrectos.";
    $stmt->close();
}

// Obtener todas las configuraciones para usar el logo
$settings = SimpleCache::get_settings($conn);
$page_title = $settings['PAGE_TITLE'] ?? 'Sistema de Códigos';
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Iniciar Sesión</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- VARIABLES DE COLOR --- */
        :root {
            --bg-purple-dark: #1A1235;
            --card-purple: #2A1F4D;
            --input-dark: rgba(0, 0, 0, 0.3);
            --text-primary: #FFFFFF;
            --text-secondary: #BCAEE5;
            --accent-green: #32FFB5;
            --glow-green: rgba(50, 255, 181, 0.15);
            --glow-border: rgba(50, 255, 181, 0.4);
            --error-color: #ff4d4d;
        }

        /* --- ESTILOS GENERALES Y FONDO --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background-color: var(--bg-purple-dark);
            color: var(--text-primary);
            font-family: 'Poppins', sans-serif;
            position: relative;
            overflow-x: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('/images/fondo/fondo.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            filter: brightness(0.5) saturate(1.1) blur(2px);
            z-index: -2;
            animation: kenburns-effect 40s ease-in-out infinite alternate;
        }

        @keyframes kenburns-effect {
            from { transform: scale(1) translate(0, 0); }
            to { transform: scale(1.1) translate(2%, -2%); }
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(26, 18, 53, 0.6), rgba(26, 18, 53, 0.6));
            z-index: -1;
        }

        /* --- TARJETA DE LOGIN --- */
        .login-card {
            background: var(--card-purple);
            border: 1px solid var(--glow-border);
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 450px;
            width: 90%;
            box-shadow: inset 0 0 15px rgba(50, 255, 181, 0.1), 0 10px 30px rgba(0,0,0,0.4);
            animation: fadeIn 1s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        /* --- ESTILOS PARA EL LOGO DIRECTO (SIN CÍRCULO) --- */
        .login-logo {
            margin: 0 auto 1.5rem; /* Margen inferior para separar del título */
        }

        .login-logo img {
            max-width: 150px; /* Ancho máximo del logo */
            height: auto;
        }
        
        .login-title {
            font-weight: 600;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* --- FORMULARIO --- */
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            background-color: var(--input-dark);
            border: 1px solid var(--text-secondary);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 4px var(--glow-green);
        }
        
        .form-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .form-input:focus + .form-icon {
            color: var(--accent-green);
        }
        
        /* --- BOTÓN --- */
        .login-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            color: var(--bg-purple-dark);
            background: var(--accent-green);
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 0 20px var(--glow-green);
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 30px 5px var(--glow-green);
        }
        
        /* --- ALERTA DE ERROR --- */
        .alert-error {
            background-color: rgba(255, 77, 77, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">
                <img src="/images/logo/<?= htmlspecialchars($settings['LOGO'] ?? 'logo.png'); ?>" alt="Logo del Sistema">
            </div>
            <h1 class="login-title">Iniciar Sesión</h1>
            <p class="login-subtitle">Accede a tu sistema de códigos</p>
        </div>

        <?php if (!empty($login_error)): ?>
            <div class="alert-error">
                <span><?= htmlspecialchars($login_error) ?></span>
            </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="index.php">
            <div class="form-group">
                <input type="text" class="form-input" name="username" placeholder="Usuario" required autofocus autocomplete="username">
                <i class="fas fa-user form-icon"></i>
            </div>

            <div class="form-group">
                <input type="password" class="form-input" name="password" placeholder="Contraseña" required autocomplete="current-password">
                <i class="fas fa-lock form-icon"></i>
            </div>

            <button type="submit" name="login" class="login-btn">
                <span>Ingresar</span>
            </button>
        </form>
    </div>
</body>
</html>
