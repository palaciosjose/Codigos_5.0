<?php
session_start();
require_once '../funciones.php';

if (!is_installed()) {
    header("Location: ../instalacion/instalador.php");
    exit();
}

require_once '../security/auth.php';
check_session(true, '../index.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual de Configuración - Administradores</title>
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

        /* Fondo animado */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 25% 25%, rgba(0, 242, 254, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 75% 75%, rgba(161, 98, 247, 0.1) 0%, transparent 50%);
            animation: backgroundPulse 8s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes backgroundPulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 0.8; }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            text-align: center;
            padding: 40px 0;
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
            background: linear-gradient(45deg, transparent, rgba(0, 242, 254, 0.1), transparent);
            animation: headerGlow 3s linear infinite;
        }

        @keyframes headerGlow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--neon-primary), var(--neon-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.2rem;
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
        }

        .section:hover {
            border-color: var(--neon-primary);
            box-shadow: var(--glow-strong);
        }

        .section-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--neon-primary);
            margin-bottom: 20px;
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
        }

        .subsection {
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            border-left: 4px solid var(--neon-success);
        }

        .subsection h3 {
            color: var(--neon-success);
            font-size: 1.3rem;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .config-item {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .config-name {
            font-weight: 600;
            color: var(--neon-cyan);
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .config-description {
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .config-example {
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--neon-success);
            border-left: 3px solid var(--neon-success);
        }

        /* Alertas */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: var(--neon-warning);
        }

        .alert-info {
            background: rgba(0, 242, 254, 0.1);
            border: 1px solid rgba(0, 242, 254, 0.3);
            color: var(--neon-primary);
        }

        .alert-success {
            background: rgba(50, 255, 181, 0.1);
            border: 1px solid rgba(50, 255, 181, 0.3);
            color: var(--neon-success);
        }

        /* Botones de navegación */
        .nav-buttons {
            position: fixed;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1000;
        }

        .nav-btn {
            display: block;
            width: 35px;
            height: 35px;
            background: var(--card-bg);
            border: 2px solid var(--neon-primary);
            border-radius: 50%;
            color: var(--neon-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .nav-btn:hover {
            background: var(--neon-primary);
            color: var(--bg-dark);
            box-shadow: var(--glow-strong);
            transform: scale(1.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .nav-buttons {
                display: none;
            }
            
            .container {
                padding: 10px;
            }
        }

        /* Efectos de scroll */
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Principal -->
        <div class="header fade-in">
            <h1><i class="bi bi-gear-fill"></i> Manual de Configuración</h1>
            <p>Guía completa para administradores del sistema Web Codigos 5.0</p>
        </div>

        <!-- Sección: Dashboard -->
        <div class="section fade-in" id="dashboard">
            <h2 class="section-title">
                <i class="bi bi-speedometer2"></i>
                Dashboard del Sistema
            </h2>
            
            <div class="subsection">
                <h3>Vista General en Tiempo Real</h3>
                <p>El dashboard proporciona una vista panorámica del estado actual del sistema con actualizaciones automáticas cada 30 segundos.</p>
                
                <div class="config-item">
                    <div class="config-name">Consultas Realizadas Hoy</div>
                    <div class="config-description">
                        Contador de todas las búsquedas ejecutadas en el día actual, se resetea automáticamente a medianoche.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Usuarios Activos</div>
                    <div class="config-description">
                        Número de usuarios con sesiones activas en los últimos 15 minutos.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Servidores Operativos</div>
                    <div class="config-description">
                        Estado de conexión de todos los servidores IMAP configurados. Verde = Operativo, Rojo = Con problemas.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Correos en Base de Datos</div>
                    <div class="config-description">
                        Total de direcciones de correo autorizadas actualmente en el sistema.
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección: Opciones Principales -->
        <div class="section fade-in" id="opciones-principales">
            <h2 class="section-title">
                <i class="bi bi-toggles"></i>
                Opciones Principales
            </h2>
            
            <div class="subsection">
                <h3>Controles Fundamentales del Sistema</h3>
                
                <div class="config-item">
                    <div class="config-name">EMAIL_AUTH_ENABLED - Filtro de Correos Electrónicos</div>
                    <div class="config-description">
                        <strong>Función:</strong> Activa o desactiva el sistema de lista blanca de correos autorizados.
                        <br><strong>Activado (1):</strong> Solo los correos en la lista "Correos Autorizados" pueden ser consultados.
                        <br><strong>Desactivado (0):</strong> Cualquier correo puede ser consultado sin restricciones.
                    </div>
                    <div class="config-example">Recomendación: Activado para mayor control y seguridad</div>
                </div>

                <div class="config-item">
                    <div class="config-name">REQUIRE_LOGIN - Requerir Inicio de Sesión</div>
                    <div class="config-description">
                        <strong>Función:</strong> Determina si se necesita autenticación para usar el sistema.
                        <br><strong>Activado (1):</strong> Los usuarios deben hacer login para realizar consultas.
                        <br><strong>Desactivado (0):</strong> El sistema es de acceso público sin autenticación.
                    </div>
                    <div class="config-example">Recomendación: Activado para mantener trazabilidad y control</div>
                </div>

                <div class="config-item">
                    <div class="config-name">USER_EMAIL_RESTRICTIONS_ENABLED - Restricciones por Usuario</div>
                    <div class="config-description">
                        <strong>Función:</strong> Permite asignar correos específicos a usuarios individuales.
                        <br><strong>Activado (1):</strong> Cada usuario puede tener su propia lista de correos autorizados.
                        <br><strong>Desactivado (0):</strong> Todos los usuarios autenticados pueden consultar todos los correos autorizados.
                    </div>
                    <div class="config-example">Útil para: Equipos grandes donde cada usuario maneja correos específicos</div>
                </div>

                <div class="config-item">
                    <div class="config-name">USER_SUBJECT_RESTRICTIONS_ENABLED - Restricciones de Asuntos</div>
                    <div class="config-description">
                        <strong>Función:</strong> Limita qué asuntos puede consultar cada usuario en cada plataforma.
                        <br><strong>Activado (1):</strong> Se deben asignar asuntos específicos por usuario y plataforma.
                        <br><strong>Desactivado (0):</strong> Todos los asuntos estarán disponibles para todos los usuarios.
                    </div>
                    <div class="config-example">Recomendación: Activar para un control granular de búsquedas.</div>
                </div>

                <div class="config-item">
                    <div class="config-name">CACHE_ENABLED - Sistema de Cache</div>
                    <div class="config-description">
                        <strong>Función:</strong> Activa el almacenamiento temporal de resultados para mejorar velocidad.
                        <br><strong>Activado (1):</strong> Las consultas repetidas se cargan desde cache (más rápido).
                        <br><strong>Desactivado (0):</strong> Cada consulta se ejecuta completamente cada vez.
                    </div>
                    <div class="config-example">Recomendación: Activado para mejor rendimiento</div>
                </div>

                <div class="config-item">
                    <div class="config-name">CACHE_MEMORY_ENABLED - Cache en Memoria</div>
                    <div class="config-description">
                        <strong>Función:</strong> Utiliza la memoria RAM del servidor para cache ultra-rápido.
                        <br><strong>Activado (1):</strong> Cache se almacena en RAM (más rápido, pero consume memoria).
                        <br><strong>Desactivado (0):</strong> Cache se almacena en disco (más lento, pero menor uso de RAM).
                    </div>
                    <div class="config-example">Usar solo si tienes suficiente RAM en el servidor</div>
                </div>

                <div class="config-item">
                    <div class="config-name">MAX_SEARCH_TIME_MINUTES - Tiempo Máximo de Búsqueda</div>
                    <div class="config-description">
                        <strong>Función:</strong> Límite de tiempo máximo para que una consulta se ejecute.
                        <br><strong>Valor recomendado:</strong> 30 minutos para mejor performance.
                        <br>Si una búsqueda tarda más que este tiempo, se cancela automáticamente.
                    </div>
                    <div class="config-example">Rango: 5-60 minutos. Default: 30 minutos</div>
                </div>

                <div class="config-item">
                    <div class="config-name">CACHE_TIME_MINUTES - Tiempo de Vida del Cache</div>
                    <div class="config-description">
                        <strong>Función:</strong> Define cuánto tiempo se mantienen los datos en cache antes de actualizarse.
                        <br><strong>Valor bajo (1-5 min):</strong> Datos más actualizados, pero más consultas al servidor.
                        <br><strong>Valor alto (15-60 min):</strong> Menos carga del servidor, pero datos menos actualizados.
                    </div>
                    <div class="config-example">Recomendación: 5 minutos para balance entre velocidad y actualización</div>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <span>Estas opciones son fundamentales para el funcionamiento del sistema. Cambios aquí afectan a todos los usuarios.</span>
            </div>
        </div>

        <!-- Sección: Optimizaciones de Performance -->
        <div class="section fade-in" id="performance">
            <h2 class="section-title">
                <i class="bi bi-rocket-takeoff"></i>
                Optimizaciones de Performance
            </h2>
            
            <div class="subsection">
                <h3>Configuraciones Avanzadas para Velocidad</h3>
                
                <div class="config-item">
                    <div class="config-name">USE_PRECISE_IMAP_SEARCH - Búsquedas IMAP Precisas</div>
                    <div class="config-description">
                        <strong>Función:</strong> Usa búsquedas más específicas con fecha y hora exacta en servidores IMAP.
                        <br><strong>Activado:</strong> Búsquedas más precisas pero pueden ser más lentas.
                        <br><strong>Desactivado:</strong> Búsquedas más amplias pero más rápidas.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">EARLY_SEARCH_STOP - Parada Temprana de Búsqueda</div>
                    <div class="config-description">
                        <strong>Función:</strong> Detiene la búsqueda al encontrar el primer resultado válido.
                        <br><strong>Activado:</strong> Búsquedas más rápidas, pero puede perderse información adicional.
                        <br><strong>Desactivado:</strong> Búsqueda completa, más lenta pero más exhaustiva.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">IMAP_SEARCH_OPTIMIZATION - Optimizaciones Automáticas</div>
                    <div class="config-description">
                        <strong>Función:</strong> Activa todas las optimizaciones automáticas de búsqueda IMAP.
                        <br>Incluye técnicas avanzadas para reducir tiempo de respuesta y carga del servidor.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">TRUST_IMAP_DATE_FILTER - Confiar en Filtrado de Fechas</div>
                    <div class="config-description">
                        <strong>Función:</strong> Confía en el servidor IMAP para filtrar fechas (más rápido).
                        <br><strong>Activado:</strong> El servidor IMAP filtra fechas (más eficiente).
                        <br><strong>Desactivado:</strong> El sistema filtra fechas localmente (más seguro).
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">PERFORMANCE_LOGGING - Registro de Métricas</div>
                    <div class="config-description">
                        <strong>Función:</strong> Registra métricas de rendimiento en los logs del sistema.
                        <br>Útil para diagnosticar problemas de velocidad y optimizar configuraciones.
                    </div>
                </div>
            </div>

            <div class="subsection">
                <h3>Parámetros Numéricos de Performance</h3>
                
                <div class="config-item">
                    <div class="config-name">MAX_EMAILS_TO_CHECK - Máximo de Emails por Consulta</div>
                    <div class="config-description">
                        <strong>Función:</strong> Limita cuántos emails procesa en cada búsqueda.
                        <br><strong>Valor recomendado:</strong> 35 emails. Reducir para buzones muy grandes.
                        <br><strong>Rango:</strong> 10-100 emails.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">IMAP_CONNECTION_TIMEOUT - Timeout de Conexión</div>
                    <div class="config-description">
                        <strong>Función:</strong> Tiempo máximo para establecer conexión con servidor IMAP.
                        <br><strong>Valor recomendado:</strong> 8 segundos para servidores estables.
                        <br><strong>Rango:</strong> 5-30 segundos.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">IMAP_SEARCH_TIMEOUT - Timeout de Búsqueda</div>
                    <div class="config-description">
                        <strong>Función:</strong> Tiempo máximo para cada operación de búsqueda individual.
                        <br><strong>Valor recomendado:</strong> 30 segundos.
                        <br><strong>Rango:</strong> 10-120 segundos.
                    </div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Cambios en estas configuraciones pueden afectar significativamente el rendimiento. Prueba con pocos usuarios antes de aplicar en producción.</span>
            </div>
        </div>

        <!-- Sección: Personalización del Sitio -->
        <div class="section fade-in" id="personalizacion">
            <h2 class="section-title">
                <i class="bi bi-palette-fill"></i>
                Personalización del Sitio
            </h2>
            
            <div class="subsection">
                <h3>Configuración Visual y Enlaces</h3>
                
                <div class="config-item">
                    <div class="config-name">PAGE_TITLE - Título SEO de la Página</div>
                    <div class="config-description">
                        Define el título que aparece en la pestaña del navegador y en la barra de navegación.
                    </div>
                    <div class="config-example">Ejemplo: "Sistema de Consulta Premium"</div>
                </div>

                <div class="config-item">
                    <div class="config-name">Enlaces Globales de Navegación</div>
                    <div class="config-description">
                        <strong>enlace_global_1:</strong> URL del primer botón (ej: tu página web)<br>
                        <strong>enlace_global_1_texto:</strong> Texto que se muestra en el botón<br>
                        <strong>enlace_global_2:</strong> URL del segundo botón (ej: Telegram)<br>
                        <strong>enlace_global_2_texto:</strong> Texto del segundo botón
                    </div>
                    <div class="config-example">
                        Botón 1: "Página Web" → https://miweb.com<br>
                        Botón 2: "Telegram" → https://t.me/micanal
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Configuración de WhatsApp</div>
                    <div class="config-description">
                        <strong>enlace_global_numero_whatsapp:</strong> Número con código de país<br>
                        <strong>enlace_global_texto_whatsapp:</strong> Texto del botón de WhatsApp
                    </div>
                    <div class="config-example">Número: +1234567890<br>Texto: "Soporte WhatsApp"</div>
                </div>

                <div class="config-item">
                    <div class="config-name">ID_VENDEDOR</div>
                    <div class="config-description">
                        Identificador único del vendedor o distribuidor para propósitos de licencia y soporte.
                    </div>
                </div>
            </div>

            <div class="subsection">
                <h3>Logo del Sistema</h3>
                <div class="config-item">
                    <div class="config-name">Configuración de Logo Personalizado</div>
                    <div class="config-description">
                        <strong>Formato requerido:</strong> PNG únicamente<br>
                        <strong>Dimensiones exactas:</strong> 512px × 315px<br>
                        <strong>Ubicación de almacenamiento:</strong> Carpeta assets/<br>
                        <strong>Recomendación:</strong> Usar imágenes con fondo transparente para mejor integración visual
                    </div>
                    <div class="config-example">El logo se mostrará en la interfaz principal del sistema</div>
                </div>
            </div>
        </div>

        <!-- Sección: Configuración de Servidores -->
        <div class="section fade-in" id="servidores">
            <h2 class="section-title">
                <i class="bi bi-server"></i>
                Configuración de Servidores IMAP
            </h2>

            <div class="subsection">
                <h3>Configuración de Conexiones IMAP</h3>
                <p>El sistema maneja múltiples servidores IMAP predefinidos. Cada servidor puede ser habilitado/deshabilitado y configurado individualmente.</p>
                
                <div class="config-item">
                    <div class="config-name">Servidor IMAP</div>
                    <div class="config-description">
                        Dirección del servidor IMAP. Puede ser de proveedores públicos o dominios personalizados.
                    </div>
                    <div class="config-example">
                        Públicos: imap.gmail.com, outlook.office365.com<br>
                        Personalizados: mail.tudominio.com, imap.empresa.org
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Puerto IMAP</div>
                    <div class="config-description">
                        Puerto de conexión estándar para protocolo IMAP.
                    </div>
                    <div class="config-example">993 (SSL - Recomendado) | 143 (TLS/Sin encriptación)</div>
                </div>

                <div class="config-item">
                    <div class="config-name">Usuario IMAP</div>
                    <div class="config-description">
                        Dirección de correo electrónico que se usará para autenticarse en el servidor.
                    </div>
                    <div class="config-example">usuario@gmail.com</div>
                </div>

                <div class="config-item">
                    <div class="config-name">Contraseña IMAP</div>
                    <div class="config-description">
                        Contraseña o App Password para autenticación. Para Gmail/Outlook se recomienda usar App Passwords específicas.
                    </div>
                </div>
            </div>

            <div class="subsection">
                <h3>Función de Prueba de Conexión</h3>
                <div class="config-item">
                    <div class="config-description">
                        <strong>IMPORTANTE:</strong> Siempre utiliza el botón "PROBAR CONEXIÓN" antes de guardar configuraciones.<br><br>
                        La prueba verifica:
                        <br>• Conectividad con el servidor IMAP
                        <br>• Autenticación correcta con usuario/contraseña
                        <br>• Tiempo de respuesta del servidor
                        <br>• Configuración de puerto y encriptación
                        <br>• Posibles problemas de firewall o red
                    </div>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>NUNCA guardes configuraciones de servidor sin probar la conexión primero. Esto puede causar errores en todas las consultas.</span>
            </div>
        </div>

        <!-- Sección: Gestión de Usuarios -->
        <div class="section fade-in" id="usuarios">
            <h2 class="section-title">
                <i class="bi bi-people-fill"></i>
                Gestión de Usuarios
            </h2>

            <div class="subsection">
                <h3>Finalidad del Control de Acceso</h3>
                <p>Esta sección es fundamental para mantener la seguridad, trazabilidad y control de acceso al sistema. Permite gestionar quién puede usar el sistema y bajo qué condiciones.</p>
            </div>

            <div class="subsection">
                <h3>Operaciones Disponibles</h3>
                
                <div class="config-item">
                    <div class="config-name">Crear Usuario</div>
                    <div class="config-description">
                        Añade nuevos usuarios con credenciales únicas. Cada usuario debe tener un nombre único y contraseña segura.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Editar Usuario</div>
                    <div class="config-description">
                        Modifica información de usuarios existentes: nombre, contraseña, estado, permisos específicos.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Eliminar Usuario</div>
                    <div class="config-description">
                        Remueve permanentemente usuarios del sistema. <strong>Esta acción no se puede deshacer.</strong>
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Activar/Desactivar Usuario</div>
                    <div class="config-description">
                        <strong>Activo:</strong> Usuario puede iniciar sesión y realizar consultas normalmente.<br>
                        <strong>Inactivo:</strong> Usuario bloqueado temporalmente, mantiene sus datos pero no puede acceder.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Barra de Búsqueda</div>
                    <div class="config-description">
                        <strong>Función:</strong> Localizar usuarios específicos rápidamente por nombre.<br>
                        <strong>Útil para:</strong> Sistemas con muchos usuarios registrados (50+ usuarios).
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <span>Los administradores siempre mantienen acceso completo al sistema, independientemente de cualquier restricción configurada.</span>
            </div>
        </div>

        <!-- Sección: Registros del Sistema -->
        <div class="section fade-in" id="registros">
            <h2 class="section-title">
                <i class="bi bi-journal-text"></i>
                Registros del Sistema
            </h2>

            <div class="subsection">
                <h3>¿Qué son los Registros?</h3>
                <p>Los registros (logs) son un historial detallado de todas las actividades del sistema. Son esenciales para auditoría, diagnóstico de problemas y mantenimiento de la seguridad.</p>
            </div>

            <div class="subsection">
                <h3>Tipos de Registros Disponibles</h3>
                
                <div class="config-item">
                    <div class="config-name">Registros de Consultas</div>
                    <div class="config-description">
                        <strong>Contiene:</strong> Usuario, correo consultado, plataforma utilizada, fecha/hora, resultado obtenido, tiempo de procesamiento.
                        <br><strong>Útil para:</strong> Auditar uso del sistema y identificar patrones de consulta.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Registros de Acceso</div>
                    <div class="config-description">
                        <strong>Contiene:</strong> Logins exitosos, logouts, intentos fallidos de acceso, IP de origen, timestamp.
                        <br><strong>Útil para:</strong> Detectar accesos no autorizados y monitorear actividad de usuarios.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Registros de Errores</div>
                    <div class="config-description">
                        <strong>Contiene:</strong> Errores del sistema, fallos de conexión, problemas técnicos, stack traces para debugging.
                        <br><strong>Útil para:</strong> Diagnosticar y resolver problemas técnicos del sistema.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Registros de Performance</div>
                    <div class="config-description">
                        <strong>Contiene:</strong> Métricas de velocidad, uso de recursos, tiempos de respuesta.
                        <br><strong>Útil para:</strong> Optimizar configuraciones y identificar cuellos de botella.
                    </div>
                </div>
            </div>

            <div class="subsection">
                <h3>Cómo Revisar los Registros</h3>
                <div class="config-item">
                    <div class="config-description">
                        <strong>Filtros por fecha:</strong> Selecciona períodos específicos para análisis<br>
                        <strong>Filtros por tipo:</strong> Enfócate en el tipo de registro que necesitas<br>
                        <strong>Búsqueda por usuario:</strong> Audita actividad de usuarios específicos<br>
                        <strong>Exportación:</strong> Descarga registros para análisis externos o respaldos<br>
                        <strong>Búsqueda de texto:</strong> Localiza eventos específicos por palabras clave
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección: Correos Autorizados -->
        <div class="section fade-in" id="correos">
            <h2 class="section-title">
                <i class="bi bi-envelope-check-fill"></i>
                Correos Autorizados
            </h2>

            <div class="subsection">
                <h3>Gestión Individual de Correos</h3>
                
                <div class="config-item">
                    <div class="config-name">Agregar Correos</div>
                    <div class="config-description">
                        Añade direcciones de correo una por una usando el formulario manual. Ideal para correos específicos.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Editar Correos</div>
                    <div class="config-description">
                        Modifica direcciones existentes para corregir errores tipográficos o actualizar dominios.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Eliminar Correos</div>
                    <div class="config-description">
                        Remueve direcciones que ya no deben ser consultables en el sistema. La eliminación es permanente.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Barra de Búsqueda</div>
                    <div class="config-description">
                        <strong>Función:</strong> Localizar correos específicos rápidamente en listas extensas.<br>
                        <strong>Especialmente útil para:</strong> Bases de datos con miles de correos autorizados.
                    </div>
                </div>
            </div>

            <div class="subsection">
                <h3>Importación Masiva de Correos</h3>
                
                <div class="config-item">
                    <div class="config-name">Archivos .txt</div>
                    <div class="config-description">
                        <strong>Formato 1:</strong> Una dirección por línea<br>
                        <strong>Formato 2:</strong> Separadas por comas o punto y coma
                    </div>
                    <div class="config-example">
                        correo1@ejemplo.com<br>
                        correo2@ejemplo.com; correo3@ejemplo.com
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Archivos .csv</div>
                    <div class="config-description">
                        El sistema procesará todas las celdas del archivo. Cada celda que contenga una dirección válida será importada.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Archivos .xlsx/.xls</div>
                    <div class="config-description">
                        Se tomará automáticamente la primera hoja y la primera columna. Una dirección por fila.
                    </div>
                </div>
            </div>

            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span>El sistema valida automáticamente el formato de correos durante la importación y reporta direcciones inválidas.</span>
            </div>
        </div>

        <!-- Sección: Plataformas -->
        <div class="section fade-in" id="plataformas">
            <h2 class="section-title">
                <i class="bi bi-layers-fill"></i>
                Gestión de Plataformas
            </h2>

            <div class="subsection">
                <h3>¿Qué son las Plataformas?</h3>
                <p>Las plataformas son las opciones de búsqueda que aparecen en el dropdown para los usuarios finales. Cada plataforma puede tener configuraciones específicas y criterios de búsqueda personalizados.</p>
            </div>

            <div class="subsection">
                <h3>Operaciones con Plataformas</h3>
                
                <div class="config-item">
                    <div class="config-name">Agregar Plataforma</div>
                    <div class="config-description">
                        Crea nuevas opciones de búsqueda personalizadas. Cada plataforma puede tener criterios únicos de filtrado y búsqueda.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Editar Plataforma</div>
                    <div class="config-description">
                        Modifica configuraciones existentes: nombre, criterios de búsqueda, servidores asociados, parámetros de filtrado.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Eliminar Plataforma</div>
                    <div class="config-description">
                        Remueve plataformas obsoletas. Los usuarios ya no podrán seleccionar esta opción de búsqueda.
                    </div>
                </div>
            </div>

            <div class="subsection">
                <h3>Asignar Asuntos para Filtros de Búsqueda</h3>
                
                <div class="config-item">
                    <div class="config-name">¿Qué es un Asunto?</div>
                    <div class="config-description">
                        El asunto es el campo "Subject" de los correos electrónicos. Configurar asuntos específicos permite que el sistema busque únicamente correos con ciertos títulos o palabras clave en el asunto.
                    </div>
                    <div class="config-example">
                        Ejemplos efectivos:<br>
                        • "Código de verificación"<br>
                        • "Password reset"<br>
                        • "Confirmar cuenta"<br>
                        • "Two-factor authentication"
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Cómo Registrar Asuntos Efectivos</div>
                    <div class="config-description">
                        <strong>1. Identificación:</strong> Analiza los asuntos más comunes en los correos objetivo<br>
                        <strong>2. Especificidad:</strong> Usa palabras clave específicas, evita términos genéricos<br>
                        <strong>3. Variaciones:</strong> Considera idiomas y variaciones de texto<br>
                        <strong>4. Pruebas:</strong> Testa diferentes combinaciones para optimizar resultados
                    </div>
                    <div class="config-example">
                        ✅ Bueno: "Verification Code", "Reset Password"<br>
                        ❌ Malo: "Email", "Notification", "Message"
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección: Asignar Correos -->
        <div class="section fade-in" id="asignar-correos">
            <h2 class="section-title">
                <i class="bi bi-person-lines-fill"></i>
                Asignar Correos por Usuario
            </h2>

            <div class="subsection">
                <h3>Configuración y Modo de Uso</h3>
                <p>Esta funcionalidad avanzada permite un control granular de acceso, restringiendo qué correos específicos puede consultar cada usuario individual.</p>
            </div>

            <div class="subsection">
                <h3>Proceso de Asignación</h3>
                
                <div class="config-item">
                    <div class="config-name">Paso 1: Activar Restricciones</div>
                    <div class="config-description">
                        Primero activa "USER_EMAIL_RESTRICTIONS_ENABLED" y "USER_SUBJECT_RESTRICTIONS_ENABLED" en la sección "Opciones Principales" de Configuración.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Paso 2: Seleccionar Usuario</div>
                    <div class="config-description">
                        Elige el usuario específico al que quieres asignar correos desde la lista de usuarios registrados.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Paso 3: Definir Correos Autorizados</div>
                    <div class="config-description">
                        <strong>Con correos asignados:</strong> El usuario solo puede consultar las direcciones específicamente asignadas.<br>
                        <strong>Sin correos asignados:</strong> El usuario puede consultar todos los correos autorizados globalmente.
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Búsqueda Avanzada de Usuarios</div>
                    <div class="config-description">
                        <strong>Función crítica:</strong> Utiliza la barra de búsqueda para localizar usuarios específicos.<br>
                        <strong>Especialmente útil para:</strong> Sistemas empresariales con cientos de usuarios registrados.<br>
                        <strong>Búsqueda por:</strong> Nombre de usuario, ID, o cualquier parte del nombre.
                    </div>
                </div>
            </div>

            <div class="subsection">
                <h3>Casos de Uso Prácticos</h3>
                <div class="config-item">
                    <div class="config-description">
                        <strong>Equipos especializados:</strong> Cada equipo maneja correos de clientes específicos<br>
                        <strong>Departamentos:</strong> Ventas vs Soporte tienen acceso a correos diferentes<br>
                        <strong>Niveles de acceso:</strong> Usuarios junior vs senior con diferentes permisos<br>
                        <strong>Clientes corporativos:</strong> Cada cliente maneja solo sus propios correos
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <span>Los usuarios sin restricciones específicas mantienen acceso a todos los correos autorizados globalmente. Esta configuración no afecta a administradores.</span>
            </div>
        </div>

        <!-- Sección: Licencia -->
        <div class="section fade-in" id="licencia">
            <h2 class="section-title">
                <i class="bi bi-shield-check"></i>
                Licencia y Migración
            </h2>

            <div class="subsection">
                <h3>Verificación de Licencia</h3>
                <p>Esta sección permite verificar que tu dominio esté correctamente activado y autorizado para usar el sistema Web Codigos 5.0.</p>
                
                <div class="config-item">
                    <div class="config-description">
                        <strong>Estado de licencia:</strong> Muestra si la licencia está activa y válida<br>
                        <strong>Dominio autorizado:</strong> Verifica que el dominio actual esté permitido<br>
                        <strong>Fecha de expiración:</strong> Información sobre vigencia de la licencia<br>
                        <strong>Información del vendedor:</strong> Datos de contacto para soporte
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Si cambias de dominio o servidor, contacta al proveedor de la licencia para reactivar el sistema en la nueva ubicación.</span>
                </div>
            </div>

            <div class="subsection">
                <h3>Proceso de Migración a Otro Servidor</h3>
                
                <div class="config-item">
                    <div class="config-name">Paso 1: Respaldo Completo</div>
                    <div class="config-description">
                        • Respalda toda la base de datos MySQL<br>
                        • Copia el archivo config/db_credentials.php<br>
                        • Guarda cualquier personalización de archivos
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Paso 2: Transferencia de Archivos</div>
                    <div class="config-description">
                        • Copia todos los archivos del sistema al nuevo servidor<br>
                        • Mantén la estructura de carpetas original<br>
                        • Verifica permisos de escritura en carpetas necesarias
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Paso 3: Configuración de Base de Datos</div>
                    <div class="config-description">
                        • Crea nueva base de datos en el servidor destino<br>
                        • Importa el respaldo de la base de datos<br>
                        • Verifica que todas las tablas se hayan importado correctamente
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Paso 4: Ajuste de Configuraciones</div>
                    <div class="config-description">
                        • Actualiza config/db_credentials.php con nuevos datos<br>
                        • Ajusta configuraciones de servidor según el nuevo entorno<br>
                        • Prueba conexiones IMAP en el nuevo servidor
                    </div>
                </div>

                <div class="config-item">
                    <div class="config-name">Paso 5: Reactivación de Licencia</div>
                    <div class="config-description">
                        • Contacta al proveedor con la nueva URL<br>
                        • Proporciona información del ID_VENDEDOR<br>
                        • Espera confirmación de reactivación antes de usar en producción
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de navegación flotantes -->
        <div class="nav-buttons">
            <a href="#dashboard" class="nav-btn" title="Dashboard">
                <i class="bi bi-speedometer2"></i>
            </a>
            <a href="#opciones-principales" class="nav-btn" title="Opciones">
                <i class="bi bi-toggles"></i>
            </a>
            <a href="#performance" class="nav-btn" title="Performance">
                <i class="bi bi-rocket-takeoff"></i>
            </a>
            <a href="#personalizacion" class="nav-btn" title="Personalización">
                <i class="bi bi-palette-fill"></i>
            </a>
            <a href="#servidores" class="nav-btn" title="Servidores">
                <i class="bi bi-server"></i>
            </a>
            <a href="#usuarios" class="nav-btn" title="Usuarios">
                <i class="bi bi-people-fill"></i>
            </a>
            <a href="#registros" class="nav-btn" title="Registros">
                <i class="bi bi-journal-text"></i>
            </a>
            <a href="#correos" class="nav-btn" title="Correos">
                <i class="bi bi-envelope-check-fill"></i>
            </a>
            <a href="#plataformas" class="nav-btn" title="Plataformas">
                <i class="bi bi-layers-fill"></i>
            </a>
            <a href="#asignar-correos" class="nav-btn" title="Asignar">
                <i class="bi bi-person-lines-fill"></i>
            </a>
            <a href="#licencia" class="nav-btn" title="Licencia">
                <i class="bi bi-shield-check"></i>
            </a>
        </div>
    </div>

    <script>
        // Smooth scroll para los enlaces de navegación
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

        // Animación de fade-in al hacer scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationDelay = '0.2s';
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.section').forEach(section => {
            observer.observe(section);
        });
    </script>
</body>
</html>
