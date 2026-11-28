# Configuración local y producción

El sitio usa archivos HTML estáticos y endpoints PHP bajo `api/`. Para que los formularios, el contador y el panel funcionen, abre el proyecto a través de Apache con PHP y MySQL; abrir `index.html` directamente como archivo no ejecuta PHP.

## XAMPP en Windows

1. Copia la carpeta del proyecto dentro de `C:\xampp\htdocs\` (por ejemplo, `C:\xampp\htdocs\tierrayuc`).
2. En el panel de XAMPP inicia Apache y MySQL.
3. Abre `http://localhost/phpmyadmin`, crea una base llamada `tierrayuc` con cotejamiento `utf8mb4_unicode_ci`.
4. Copia `api/config.example.php` como `api/config.local.php`. Para la instalación estándar de XAMPP, deja `DB_HOST=localhost`, `DB_NAME=tierrayuc`, `DB_USER=root`, `DB_PASSWORD=` y `APP_ENV=development`.
5. Visita `http://localhost/tierrayuc/`. Apache creará las tablas de prospectos, estadísticas y administración al usar esas funciones. Para cuentas de clientes, importa `api/customer-auth-schema.sql` en phpMyAdmin.

No uses `file://` ni el servidor de Live Server para PHP: ese servidor solo sirve HTML y JavaScript. En la vista local, la verificación WhatsApp de clientes queda desactivada hasta configurar las credenciales de Meta; el formulario conserva el envío manual. La API de WhatsApp requiere HTTPS fuera de localhost.

## Hosting real

En el hosting crea una base MySQL y un usuario desde el panel del proveedor y asigna todos los permisos de esa base. Configura en el entorno de PHP `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD`; si el hosting solo permite archivos de configuración, sube `api/config.local.php` con esos mismos campos. No subas el archivo de ejemplo como configuración y no publiques credenciales.

Para activar la verificación de WhatsApp configura además `APP_OTP_PEPPER` (secreto aleatorio de al menos 32 caracteres), `WHATSAPP_GRAPH_API_VERSION`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_OTP_TEMPLATE_NAME`, `WHATSAPP_OTP_TEMPLATE_LANGUAGE` y `APP_ALLOWED_ORIGINS` con los orígenes HTTPS exactos del sitio, separados por coma. El hosting debe tener PDO MySQL y cURL activos. `APP_ENV` debe ser `production`.

Sube los archivos del proyecto conservando la carpeta `api/`. El archivo `api/config.local.php` se excluye de Git y Apache tiene reglas para proteger los esquemas SQL y datos internos. Verifica en tu hosting que Apache permita `.htaccess`; si usa Nginx, configura allí el bloqueo equivalente para `api/data` y archivos `.sql`.

## Panel de administración

Abre `/admin.html`. En la primera instalación, el endpoint crea las tablas y una cuenta inicial: usuario `admin`, contraseña `admin`. Cambia la contraseña en ese primer acceso por una de 8 a 13 caracteres, con al menos una mayúscula, un número y un símbolo. La sesión administrativa requiere HTTPS en producción. Las contraseñas nuevas se guardan como hashes.

Si las tablas de clientes no se crean automáticamente, importa `api/customer-auth-schema.sql`. Las visitas se cuentan desde que el contador queda activo; las anteriores no pueden reconstruirse desde la base de datos.
