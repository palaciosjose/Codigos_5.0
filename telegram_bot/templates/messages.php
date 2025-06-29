<?php
// telegram_bot/templates/messages.php
// Todos los mensajes de texto del bot

return [
    'welcome' => "🤖 *¡Bienvenido al Bot de Códigos\\!*\n\n" .
                 "Soy tu asistente para buscar códigos de verificación\\.\n" .
                 "Puedes usarme para consultar códigos de diferentes plataformas\\.\n\n" .
                 "🔹 Usa /buscar <email> <plataforma> para buscar\n" .
                 "🔹 Usa /codigo <id> para obtener un código específico\n" .
                 "🔹 Usa /ayuda para ver todos los comandos\n\n" .
                 "¡Empecemos\\! 🚀",

    'help' => "📚 *Manual de Comandos*\n\n" .
              "*Comandos Principales:*\n" .
              "• `/start` \\- Iniciar el bot\n" .
              "• `/buscar <email> <plataforma>` \\- Buscar códigos\n" .
              "• `/codigo <id>` \\- Obtener código por ID\n" .
              "• `/ayuda` \\- Mostrar esta ayuda\n" .
              "• `/config` \\- Ver tu configuración\n\n" .
              "*Comandos de Admin:*\n" .
              "• `/stats` \\- Ver estadísticas del sistema\n\n" .
              "*Ejemplos de uso:*\n" .
              "• `/buscar usuario@gmail\\.com Netflix`\n" .
              "• `/codigo 12345`\n\n" .
              "💡 *Tip:* Usa los botones del menú para una navegación más fácil\\!",

    'unauthorized' => "🚫 *Acceso Denegado*\n\n" .
                      "Lo siento, no estás autorizado para usar este bot\\.\n\n" .
                      "Si crees que esto es un error, contacta al administrador del sistema\\.",

    'search_instructions' => "🔍 *Cómo buscar códigos:*\n\n" .
                            "*Formato:* `/buscar <email> <plataforma>`\n\n" .
                            "*Ejemplos:*\n" .
                            "• `/buscar juan@gmail\\.com Netflix`\n" .
                            "• `/buscar maria@hotmail\\.com Amazon`\n" .
                            "• `/buscar carlos@yahoo\\.com PayPal`\n\n" .
                            "*Plataformas disponibles:*\n" .
                            "Netflix, Amazon, PayPal, Steam, Epic Games, Spotify, y más\\.\n\n" .
                            "💡 *Tip:* El email debe ser exacto y la plataforma sin espacios\\.",

    'code_instructions' => "🆔 *Obtener código por ID:*\n\n" .
                          "*Formato:* `/codigo <numero_id>`\n\n" .
                          "*Ejemplo:*\n" .
                          "• `/codigo 12345`\n\n" .
                          "El ID lo obtienes cuando realizas una búsqueda exitosa\\.",

    'invalid_format' => "❌ *Formato incorrecto*\n\n" .
                       "Por favor verifica el formato de tu comando\\.\n" .
                       "Usa /ayuda para ver los ejemplos correctos\\.",

    'searching' => "🔍 *Buscando\\.\\.\\.*\n\n" .
                   "Consultando en los servidores\\.\\.\\.\n" .
                   "Esto puede tardar unos segundos\\.",

    'no_results' => "😔 *Sin resultados*\n\n" .
                    "No se encontraron códigos para tu búsqueda\\.\n\n" .
                    "🔹 Verifica que el email sea correcto\n" .
                    "🔹 Asegúrate de que la plataforma esté bien escrita\n" .
                    "🔹 Revisa que tengas permisos para este email",

    'error_generic' => "⚠️ *Error del sistema*\n\n" .
                      "Ha ocurrido un error interno\\.\n" .
                      "Por favor intenta nuevamente en unos minutos\\.\n\n" .
                      "Si el problema persiste, contacta al administrador\\.",

    'rate_limit' => "⏰ *Demasiadas solicitudes*\n\n" .
                   "Has realizado muchas consultas muy rápido\\.\n" .
                   "Por favor espera un momento antes de intentar nuevamente\\.",

    'admin_only' => "👨‍💼 *Solo Administradores*\n\n" .
                   "Este comando está disponible únicamente para administradores del sistema\\.",

    'config_info' => "⚙️ *Tu Configuración*\n\n" .
                    "Aquí puedes ver tu información personal y permisos\\.\n" .
                    "Si necesitas cambios, contacta al administrador\\.",

    'stats_info' => "📊 *Estadísticas del Sistema*\n\n" .
                   "Vista general del uso del bot y actividad de usuarios\\.",

    'maintenance' => "🔧 *Mantenimiento*\n\n" .
                    "El sistema está en mantenimiento\\.\n" .
                    "Por favor intenta más tarde\\.",

    'success_prefix' => "✅ *¡Éxito\\!*\n\n",
    
    'error_prefix' => "❌ *Error:*\n\n",
    
    'info_prefix' => "ℹ️ *Información:*\n\n",
    
    'warning_prefix' => "⚠️ *Advertencia:*\n\n"
];