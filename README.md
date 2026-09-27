# Sastreria Royale

Aplicacion PHP para el catalogo y la administracion de sucursales.

## Instalacion local con XAMPP

1. Copia el proyecto dentro de `htdocs` e inicia Apache y MySQL.
2. En phpMyAdmin, importa `Dt_registro.SQL` y despues ejecuta una sola vez `Administrador_General_Migracion.sql`.
3. Genera un hash para la clave del administrador desde PowerShell:

   ```powershell
   php -r "echo password_hash('TU_CLAVE_SEGURA', PASSWORD_DEFAULT), PHP_EOL;"
   ```

4. En phpMyAdmin, inserta la cuenta con el hash generado:

   ```sql
   INSERT INTO usuarios (nombre, email, password, rol)
   VALUES ('Administrador General', 'admin@tudominio.com', 'PEGA_AQUI_EL_HASH', 'administrador_general');
   ```

5. Abre `http://localhost/Sisvem/index.html` e inicia sesion.

La configuracion MySQL predeterminada de los archivos PHP corresponde a XAMPP local (`localhost`, usuario `root`, contrasena vacia). Actualizala para otros entornos; no publiques credenciales reales.