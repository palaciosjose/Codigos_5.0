<?php
session_start();
require_once 'funciones.php';

if (!is_installed()) {
    header("Location: instalacion/instalador.php");
    exit();
}

require_once 'security/auth.php';
// No verificar login requerido para el manual - debe ser accesible a usuarios ya logueados
if (isset($_SESSION['user_id'])) {
    // Si hay sesión activa, actualizar actividad
    update_session_activity();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual de Usuario - Web Codigos 5.0</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --neon-primary: #00f2fe;
            --neon-secondary: #a162f7;
            --neon-success: #32FFB5;
            --neon-danger: #ff4d4d;
            --neon-warning: #f59e0b;
            --neon-cyan: #00ffff;
            --neon-purple: #8a2be2;
            --neon-pink: #ff1493;
            --bg-dark: #0f172a;
            --card-bg: rgba(26, 18, 53, 0.8);
            --input-bg: rgba(0, 0, 0, 0.3);
            --text-light: #FFFFFF;
            --text-muted: #bcaee5;
            --border-color: rgba(0, 242, 254, 0.25);
            --glow-color: rgba(0, 242, 254, 0.2);
            --glow-strong: 0 0 25px var(--glow-color);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Fondo animado con partículas */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(0, 242, 254, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(161, 98, 247, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(50, 255, 181, 0.05) 0%, transparent 50%);
            animation: backgroundPulse 10s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes backgroundPulse {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.7; }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header con efecto cyber */
        .header {
            text-align: center;
            padding: 50px 0;
            background: linear-gradient(135deg, var(--card-bg), rgba(161, 98, 247, 0.2));
            border-radius: 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(0, 242, 254, 0.1), transparent);
            animation: headerRotate 6s linear infinite;
        }

        @keyframes headerRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .header h1 {
            font-size: 3.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--neon-cyan), var(--neon-pink), var(--neon-success));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.3rem;
            color: var(--text-muted);
            position: relative;
            z-index: 1;
        }

        /* Secciones principales */
        .section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
        }

        .section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--neon-primary), var(--neon-secondary), var(--neon-success));
            border-radius: 15px 15px 0 0;
        }

        .section:hover {
            border-color: var(--neon-primary);
            box-shadow: var(--glow-strong);
            transform: translateY(-5px);
        }

        .section-title {
            font-size: 2.2rem;
            font-weight: 600;
            color: var(--neon-primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title i {
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--neon-primary), var(--neon-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 0 10px var(--glow-color));
        }

        /* Pasos del tutorial */
        .step {
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            border-left: 4px solid var(--neon-success);
            position: relative;
        }

        .step-number {
            position: absolute;
            top: -10px;
            left: 20px;
            background: linear-gradient(135deg, var(--neon-success), var(--neon-cyan));
            color: var(--bg-dark);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }

        .step h3 {
            color: var(--neon-success);
            font-size: 1.4rem;
            margin-bottom: 15px;
            margin-left: 20px;
            font-weight: 600;
        }

        .step p {
            color: var(--text-light);
            margin-bottom: 10px;
        }

        /* Ejemplos y demostraciones */
        .demo-box {
            background: rgba(0, 0, 0, 0.4);
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            position: relative;
        }

        .demo-label {
            position: absolute;
            top: -12px;
            left: 20px;
            background: var(--bg-dark);
            color: var(--neon-cyan);
            padding: 0 10px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .search-form-demo {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 400px;
        }

        .input-demo {
            padding: 12px 15px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-light);
            font-size: 1rem;
        }

        .input-demo:focus {
            outline: none;
            border-color: var(--neon-primary);
            box-shadow: 0 0 0 3px var(--glow-color);
        }

        .btn-demo {
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--neon-primary), var(--neon-secondary));
            border: none;
            border-radius: 8px;
            color: var(--bg-dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-demo:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--glow-color);
        }

        /* Tipos de errores */
        .error-type {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid var(--neon-danger);
            background: rgba(255, 77, 77, 0.1);
        }

        .error-title {
            color: var(--neon-danger);
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-description {
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .error-solution {
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 5px;
            color: var(--neon-success);
            font-size: 0.9rem;
        }

        /* Consejos y tips */
        .tip {
            background: linear-gradient(135deg, rgba(50, 255, 181, 0.1), rgba(0, 242, 254, 0.1));
            border: 1px solid rgba(50, 255, 181, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .tip-icon {
            color: var(--neon-success);
            font-size: 1.5rem;
            margin-top: 2px;
        }

        .tip-content {
            flex: 1;
        }

        .tip-title {
            color: var(--neon-success);
            font-weight: 600;
            margin-bottom: 5px;
        }

        /* Navegación flotante */
        .float-nav {
            position: fixed;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav-item {
            width: 50px;
            height: 50px;
            background: var(--card-bg);
            border: 2px solid var(--neon-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--neon-primary);
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .nav-item:hover {
            background: var(--neon-primary);
            color: var(--bg-dark);
            box-shadow: var(--glow-strong);
            transform: scale(1.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.5rem;
            }
            
            .float-nav {
                display: none;
            }
            
            .container {
                padding: 10px;
            }

            .section-title {
                font-size: 1.8rem;
            }
        }

        /* Animaciones de entrada */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Efectos de carga */
        .loading-demo {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--neon-cyan);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--neon-cyan);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Botón de regreso -->
        <div class="mb-4">
            <a href="inicio.php" class="btn btn-outline-primary" style="
                padding: 12px 24px;
                border: 2px solid var(--neon-primary);
                background: rgba(0, 242, 254, 0.1);
                color: var(--neon-primary);
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                backdrop-filter: blur(10px);
            " onmouseover="this.style.background='var(--neon-primary)'; this.style.color='var(--bg-dark)'; this.style.boxShadow='0 5px 15px var(--glow-color)'" 
               onmouseout="this.style.background='rgba(0, 242, 254, 0.1)'; this.style.color='var(--neon-primary)'; this.style.boxShadow='none'">
                <i class="bi bi-arrow-left"></i>
                Volver al Inicio
            </a>
        </div>

        <!-- Header Principal -->
        <div class="header fade-in">
            <h1><i class="bi bi-search"></i> Manual de Usuario</h1>
            <p>Aprende a realizar consultas y usar el sistema Web Codigos 5.0</p>
        </div>

        <!-- Sección: Primeros Pasos -->
        <div class="section fade-in" id="primeros-pasos">
            <h2 class="section-title">
                <i class="bi bi-play-circle-fill"></i>
                Primeros Pasos
            </h2>
            
            <div class="step">
                <div class="step-number">1</div>
                <h3>Acceder al Sistema</h3>
                <p>Ingresa tu nombre de usuario y contraseña en la página de inicio. Si es tu primera vez, contacta al administrador para obtener tus credenciales.</p>
                
                <div class="tip">
                    <i class="bi bi-lightbulb-fill tip-icon"></i>
                    <div class="tip-content">
                        <div class="tip-title">Consejo de Seguridad</div>
                        <p>Nunca compartas tus credenciales de acceso con otras personas. Cada usuario debe tener su propia cuenta.</p>
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-number">2</div>
                <h3>Navegar por la Interfaz</h3>
                <p>Una vez dentro, verás la barra de navegación superior con enlaces útiles:</p>
                <ul style="margin-left: 40px; margin-top: 10px;">
                    <li><strong>Página Web:</strong> Enlace a la web principal</li>
                    <li><strong>Telegram:</strong> Canal o grupo de soporte</li>
                    <li><strong>Manual:</strong> Esta guía de ayuda</li>
                    <li><strong>WhatsApp:</strong> Contacto directo</li>
                </ul>
            </div>

            <div class="step">
                <div class="step-number">3</div>
                <h3>Preparar tu Primera Consulta</h3>
                <p>Antes de buscar, asegúrate de tener:</p>
                <ul style="margin-left: 40px; margin-top: 10px;">
                    <li>El correo electrónico que deseas consultar</li>
                    <li>Conocimiento de qué opción de búsqueda usar</li>
                    <li>Permisos para consultar ese correo específico</li>
                </ul>
            </div>
        </div>

        <!-- Sección: Realizar Consultas -->
        <div class="section fade-in" id="consultas">
            <h2 class="section-title">
                <i class="bi bi-search-heart"></i>
                Cómo Realizar Consultas
            </h2>

            <div class="step">
                <div class="step-number">1</div>
                <h3>Introducir el Correo Electrónico</h3>
                <p>En el campo "Correo electrónico", escribe la dirección completa que deseas consultar. Asegúrate de escribir el correo exactamente como aparece en la base de datos.</p>
                
                <div class="demo-box">
                    <div class="demo-label">Ejemplo de Búsqueda</div>
                    <div class="search-form-demo">
                        <input type="email" class="input-demo" placeholder="usuario@dominio.com" readonly>
                        <select class="input-demo">
                            <option>Opción de Búsqueda 1</option>
                        </select>
                        <button class="btn-demo">
                            <i class="bi bi-search"></i> Buscar Códigos
                        </button>
                    </div>
                </div>

                <div class="tip">
                    <i class="bi bi-info-circle-fill tip-icon"></i>
                    <div class="tip-content">
                        <div class="tip-title">Formato Correcto</div>
                        <p>Asegúrate de escribir el correo completo: usuario@dominio.com sin espacios ni caracteres especiales.</p>
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-number">2</div>
                <h3>Seleccionar la Opción de Búsqueda</h3>
                <p>Elige la opción de búsqueda apropiada del menú desplegable. Estas opciones han sido configuradas por el administrador y cada una puede tener criterios específicos de búsqueda:</p>
                <ul style="margin-left: 40px; margin-top: 10px;">
                    <li><strong>Diferentes plataformas:</strong> Cada opción puede buscar en servicios específicos</li>
                    <li><strong>Filtros personalizados:</strong> Algunas opciones buscan tipos específicos de correos</li>
                    <li><strong>Criterios de asunto:</strong> Pueden filtrar por palabras clave en el asunto del correo</li>
                </ul>
            </div>

            <div class="step">
                <div class="step-number">3</div>
                <h3>Iniciar la Búsqueda</h3>
                <p>Haz clic en "Buscar Códigos" y espera a que el sistema se conecte a los servidores y realice la consulta.</p>
                
                <div class="demo-box">
                    <div class="demo-label">Proceso de Carga</div>
                    <div class="loading-demo">
                        <div class="spinner"></div>
                        <span>Conectando a servidores...</span>
                    </div>
                    <p style="margin-top: 15px; color: var(--text-muted); font-size: 0.9rem;">
                        El proceso puede tardar entre 5-30 segundos dependiendo de la conexión.
                    </p>
                </div>
            </div>

            <div class="step">
                <div class="step-number">4</div>
                <h3>Interpretar los Resultados</h3>
                <p>Una vez completada la búsqueda, verás uno de estos resultados:</p>
                
                <div style="margin-top: 20px;">
                    <div class="tip" style="border-color: rgba(50, 255, 181, 0.3);">
                        <i class="bi bi-check-circle-fill" style="color: var(--neon-success);"></i>
                        <div class="tip-content">
                            <div class="tip-title" style="color: var(--neon-success);">Búsqueda Exitosa</div>
                            <p>Se mostrará el cuerpo del correo encontrado según los criterios de búsqueda configurados. Este contenido incluirá el texto completo del mensaje que coincida con los filtros establecidos.</p>
                        </div>
                    </div>

                    <div class="tip" style="border-color: rgba(245, 158, 11, 0.3); background: rgba(245, 158, 11, 0.1);">
                        <i class="bi bi-exclamation-triangle-fill" style="color: var(--neon-warning);"></i>
                        <div class="tip-content">
                            <div class="tip-title" style="color: var(--neon-warning);">Sin Resultados</div>
                            <p>No se encontraron correos que coincidan con los criterios de búsqueda especificados para esa dirección.</p>
                        </div>
                    </div>

                    <div class="tip" style="border-color: rgba(255, 77, 77, 0.3); background: rgba(255, 77, 77, 0.1);">
                        <i class="bi bi-x-circle-fill" style="color: var(--neon-danger);"></i>
                        <div class="tip-content">
                            <div class="tip-title" style="color: var(--neon-danger);">Error de Búsqueda</div>
                            <p>Hubo un problema con la consulta. Revisa la sección de errores más abajo para soluciones.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección: Tipos de Errores -->
        <div class="section fade-in" id="errores">
            <h2 class="section-title">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Errores Comunes y Soluciones
            </h2>

            <div class="error-type">
                <div class="error-title">
                    <i class="bi bi-wifi-off"></i>
                    Error de Conexión al Servidor
                </div>
                <div class="error-description">
                    No se puede conectar al servidor IMAP configurado para la opción de búsqueda seleccionada.
                </div>
                <div class="error-solution">
                    <strong>Soluciones:</strong><br>
                    • Verifica tu conexión a internet<br>
                    • Intenta de nuevo en unos minutos<br>
                    • Prueba con otra opción de búsqueda<br>
                    • Contacta al administrador si persiste
                </div>
            </div>

            <div class="error-type">
                <div class="error-title">
                    <i class="bi bi-envelope-x"></i>
                    Correo Electrónico Inválido
                </div>
                <div class="error-description">
                    El formato del correo ingresado no es válido o contiene caracteres no permitidos.
                </div>
                <div class="error-solution">
                    <strong>Soluciones:</strong><br>
                    • Verifica que incluya @ y un dominio válido<br>
                    • Ejemplo correcto: usuario@dominio.com<br>
                    • No uses espacios ni caracteres especiales<br>
                    • Copia y pega si tienes dudas sobre el formato
                </div>
            </div>

            <div class="error-type">
                <div class="error-title">
                    <i class="bi bi-lock-fill"></i>
                    Acceso Denegado
                </div>
                <div class="error-description">
                    No tienes permisos para consultar este correo específico o la dirección no está autorizada.
                </div>
                <div class="error-solution">
                    <strong>Soluciones:</strong><br>
                    • Verifica que el correo esté en tu lista autorizada<br>
                    • Contacta al administrador para solicitar acceso<br>
                    • Intenta con otro correo autorizado<br>
                    • Confirma que escribiste la dirección correctamente
                </div>
            </div>

            <div class="error-type">
                <div class="error-title">
                    <i class="bi bi-clock"></i>
                    Tiempo de Espera Agotado
                </div>
                <div class="error-description">
                    La consulta tardó demasiado tiempo en completarse y se canceló automáticamente.
                </div>
                <div class="error-solution">
                    <strong>Soluciones:</strong><br>
                    • Intenta nuevamente con mejor conexión<br>
                    • Verifica que la opción de búsqueda sea correcta<br>
                    • Espera unos minutos antes de intentar de nuevo<br>
                    • Reporta al administrador si es recurrente
                </div>
            </div>

            <div class="error-type">
                <div class="error-title">
                    <i class="bi bi-server"></i>
                    Error de Configuración del Servidor
                </div>
                <div class="error-description">
                    Problema con la configuración del servidor asociado a la opción de búsqueda seleccionada.
                </div>
                <div class="error-solution">
                    <strong>Soluciones:</strong><br>
                    • Intenta con otra opción de búsqueda disponible<br>
                    • Reporta inmediatamente al administrador<br>
                    • Este es un error que debe resolver el administrador<br>
                    • Anota el mensaje de error exacto para reportar
                </div>
            </div>
        </div>

        <!-- Sección: Mejores Prácticas -->
        <div class="section fade-in" id="mejores-practicas">
            <h2 class="section-title">
                <i class="bi bi-star-fill"></i>
                Mejores Prácticas y Consejos
            </h2>

            <div class="tip">
                <i class="bi bi-lightning-charge-fill tip-icon"></i>
                <div class="tip-content">
                    <div class="tip-title">Optimiza tus Búsquedas</div>
                    <p>Selecciona siempre la opción de búsqueda más apropiada para obtener resultados más rápidos y precisos según el tipo de correo que buscas.</p>
                </div>
            </div>

            <div class="tip">
                <i class="bi bi-shield-check-fill tip-icon"></i>
                <div class="tip-content">
                    <div class="tip-title">Respeta los Permisos</div>
                    <p>Solo consulta correos para los que tienes autorización. El sistema registra todas las consultas realizadas por cada usuario.</p>
                </div>
            </div>

            <div class="tip">
                <i class="bi bi-clock-history tip-icon"></i>
                <div class="tip-content">
                    <div class="tip-title">Sé Paciente</div>
                    <p>Las consultas pueden tardar hasta 30 segundos. No hagas múltiples búsquedas simultáneas ya que esto puede sobrecargar el sistema.</p>
                </div>
            </div>

            <div class="tip">
                <i class="bi bi-pencil-square tip-icon"></i>
                <div class="tip-content">
                    <div class="tip-title">Verifica la Escritura</div>
                    <p>Asegúrate de escribir correctamente la dirección de correo. Un error tipográfico puede resultar en "sin resultados" cuando sí existen datos.</p>
                </div>
            </div>

            <div class="tip">
                <i class="bi bi-question-circle-fill tip-icon"></i>
                <div class="tip-content">
                    <div class="tip-title">Pide Ayuda Cuando la Necesites</div>
                    <p>Si tienes dudas, usa los enlaces de contacto en la barra de navegación o consulta este manual para resolver problemas comunes.</p>
                </div>
            </div>

            <div class="step">
                <div class="step-number">💡</div>
                <h3>Función Adicional Disponible</h3>
                <p>Después de realizar una búsqueda, encontrarás la siguiente función:</p>
                <ul style="margin-left: 40px; margin-top: 10px;">
                    <li><strong>Nueva Búsqueda:</strong> Botón que limpia los campos y te permite realizar otra consulta rápidamente sin recargar la página</li>
                </ul>
            </div>
        </div>

        <!-- Sección: Contacto y Soporte -->
        <div class="section fade-in" id="soporte">
            <h2 class="section-title">
                <i class="bi bi-headset"></i>
                Contacto y Soporte
            </h2>

            <div class="step">
                <div class="step-number">📞</div>
                <h3>Canales de Comunicación</h3>
                <p>Usa los enlaces en la barra de navegación para:</p>
                <ul style="margin-left: 40px; margin-top: 10px;">
                    <li><strong>WhatsApp:</strong> Soporte inmediato y consultas urgentes</li>
                    <li><strong>Telegram:</strong> Comunidad de usuarios y anuncios del sistema</li>
                    <li><strong>Página Web:</strong> Información general y actualizaciones</li>
                </ul>
            </div>

            <div class="tip">
                <i class="bi bi-chat-left-text-fill tip-icon"></i>
                <div class="tip-content">
                    <div class="tip-title">Información Útil para Soporte</div>
                    <p>Cuando contactes soporte, incluye: tu nombre de usuario, el correo que intentabas consultar, la opción de búsqueda seleccionada y el mensaje de error exacto (si aplica).</p>
                </div>
            </div>

            <div class="demo-box">
                <div class="demo-label">Ejemplo de Reporte de Error</div>
                <p style="font-family: monospace; color: var(--neon-cyan);">
                    <strong>Usuario:</strong> juan.perez<br>
                    <strong>Correo consultado:</strong> test@ejemplo.com<br>
                    <strong>Opción de búsqueda:</strong> Opción 1<br>
                    <strong>Error:</strong> "Tiempo de espera agotado"<br>
                    <strong>Hora:</strong> 14:30 - 25/03/2024
                </p>
            </div>
        </div>

        <!-- Navegación flotante -->
        <div class="float-nav">
            <a href="#primeros-pasos" class="nav-item" title="Primeros Pasos">
                <i class="bi bi-play-circle-fill"></i>
            </a>
            <a href="#consultas" class="nav-item" title="Consultas">
                <i class="bi bi-search-heart"></i>
            </a>
            <a href="#errores" class="nav-item" title="Errores">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </a>
            <a href="#mejores-practicas" class="nav-item" title="Mejores Prácticas">
                <i class="bi bi-star-fill"></i>
            </a>
            <a href="#soporte" class="nav-item" title="Soporte">
                <i class="bi bi-headset"></i>
            </a>
        </div>
    </div>

    <script>
        // Smooth scroll para navegación
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animaciones al hacer scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    entry.target.style.animationDelay = `${index * 0.1}s`;
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.section').forEach(section => {
            observer.observe(section);
        });

        // Efecto de escritura en el header
        function typeEffect(element, text, speed = 100) {
            let i = 0;
            element.innerHTML = '';
            const timer = setInterval(() => {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                } else {
                    clearInterval(timer);
                }
            }, speed);
        }

        // Activar efectos cuando la página esté cargada
        window.addEventListener('load', () => {
            const headerText = document.querySelector('.header h1');
            if (headerText) {
                const originalText = headerText.textContent;
                typeEffect(headerText, originalText, 80);
            }
        });
    </script>
</body>
</html>