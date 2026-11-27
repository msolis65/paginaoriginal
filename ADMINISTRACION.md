# Panel de administración

Al abrir `admin.html`, el servidor prepara las tablas necesarias y crea la base de datos `u194586287_tierrayuc` si no existe y si el usuario MySQL del sitio tiene permiso para hacerlo. En algunos planes de hosting, la creación inicial de una base de datos solo se permite desde el panel del proveedor; si ocurre, el panel mostrará un error y habrá que crearla una vez allí.

El primer acceso es:

- Usuario: `admin`
- Contraseña: `admin`

En ese primer inicio de sesión, el panel obliga a cambiar la contraseña por una de al menos 12 caracteres. Después se puede iniciar sesión con la nueva contraseña.

El enlace está al pie de `index.html`; el panel requiere HTTPS. Las cuentas también se pueden crear o modificar en SQL en la tabla `site_admins`. Guarda las contraseñas como hashes de PHP (`password_hash`), nunca en texto plano. El endpoint crea las tablas automáticamente; `api/admin-schema.sql` queda disponible como esquema de referencia y está bloqueado para descarga directa.

Las visitas comienzan a contarse cuando el contador está instalado. Las visitas anteriores no pueden reconstruirse desde el servidor.
