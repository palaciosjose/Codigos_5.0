# Manual de Administraci\xC3\xB3n

Este manual describe las acciones b\xC3\xA1sicas para configurar el sistema y trasladarlo a otro servidor.

## Configuraci\xC3\xB3n inicial

- Acceda al **Panel de Administraci\xC3\xB3n** con su usuario.
- Revise la pesta\xC3\xB1a **Configuraci\xC3\xB3n** para ajustar el t\xC3\xADtulo, enlaces y par\xC3\xA1metros de IMAP.
- En la secci\xC3\xB3n **Servidores** gestione los servidores de correo.
- Utilice **Usuarios** para crear o editar cuentas y roles.
- Gestione los enlaces globales (página web, Telegram y WhatsApp) desde **Configuración**.
- Recuerde pulsar **Guardar** para aplicar los cambios.

## Migraci\xC3\xB3n o actualizaci\xC3\xB3n

1. Haga una copia de seguridad de su base de datos y del archivo `config/db_credentials.php`.
2. Copie todo el c\xC3\xB3digo de la aplicaci\xC3\xB3n al nuevo servidor.
3. Importe la base de datos en el nuevo entorno.
4. Ajuste las variables de entorno o el archivo de credenciales seg\xC3\xBAn corresponda.
5. Acceda a **Licencia** para verificar que el dominio est\xC3\xA9 activado.

## Importar Correos Autorizados

En el panel de administraci\xC3\xB3n, secci\xC3\xB3n **Correos Autorizados**, use el bot\xC3\xB3n *Importar* y seleccione un archivo con alguno de los siguientes formatos:

### Archivos `.txt`

- Una direcci\xC3\xB3n por l\xC3\xADnea o separadas por comas o punto y coma.
- Ejemplo:
  ```
  correo1@ejemplo.com
  correo2@ejemplo.com; correo3@ejemplo.com
  ```

### Archivos `.csv`

- El sistema procesar\xC3\xA1 todas las celdas del archivo.
- Cada celda debe contener una direcci\xC3\xB3n v\xC3\xA1lida.
- Separador est\xC3\xA1ndar de comas.

### Archivos `.xlsx`/`.xls`

- Se tomar\xC3\xA1 la primera hoja y la primera columna.
- Cada fila debe contener una \xC3\xBAnica direcci\xC3\xB3n de correo.

Una vez seleccionado el archivo, confirme la importaci\xC3\xB3n. Se mostrar\xC3\xA1n los correos a\xC3\xB1adidos correctamente.

## Bot de Telegram

Desde la pestaña **Bot Telegram** podrás configurar el token, los usuarios autorizados y consultar estadísticas del bot sin editar archivos manualmente. Revisa también [TELEGRAM_BOT_MANUAL.md](TELEGRAM_BOT_MANUAL.md) para un detalle de comandos disponibles.
