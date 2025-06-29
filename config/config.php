<?php
header('Content-Type: text/html; charset=utf-8');

// Iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de caracteres para PHP
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Activa o desactiva la autorización de correos electrónicos
define('EMAIL_AUTH_ENABLED', false); // Cambia a true para activar la autorización

// Ruta al archivo de correos autorizados
define('AUTHORIZED_EMAILS_FILE', 'security/autorizados.txt');

// Variables de contacto para el footer
define('FOOTER_TEXTO', '¿Deseas una página y bot de códigos para tu negocio?');
define('FOOTER_CONTACTO', 'Click aquí');
define('FOOTER_NUMERO_WHATSAPP', '13177790136');
define('FOOTER_TEXTO_WHATSAPP', 'Hola, estoy interesado en una página web y en un bot para códigos.');

?>
