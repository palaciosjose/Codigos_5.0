# Manual del Bot de Telegram

## Configuración
1. Copiar `.env.example` a `.env` dentro de `telegram_bot`.
2. Editar el token del bot y la URL del webhook.
3. Ejecutar `php telegram_bot/setup.php` para registrar el webhook.

## Comandos Disponibles
- `/start` - Iniciar bot
- `/buscar <email> <plataforma>` - Buscar códigos
- `/codigo <id>` - Obtener código por ID
- `/ayuda` - Mostrar ayuda
- `/stats` - Estadísticas (solo admin)
- `/config` - Ver configuración personal

## Troubleshooting
### Error de webhook
- Verificar URL accesible
- Revisar certificado SSL
- Comprobar token del bot

### Integración con Panel Admin
Desde la pestaña **Bot Telegram** del panel de administración puedes actualizar el token, configurar el webhook y revisar estadísticas sin modificar archivos.
