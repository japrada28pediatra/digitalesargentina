# Quiniela en PHP

Requisitos: PHP 8.1+, cURL, mbstring y permisos de escritura en `data/`.

1. Subir la carpeta al hosting.
2. Ejecutar una vez: `php update.php`.
3. Crear un Cron Job cada cinco minutos:

```cron
*/5 * * * * /usr/bin/php /ruta/completa/update.php >/dev/null 2>&1
```

4. Abrir `index.php` desde el dominio.

`update.php` conserva el último JSON válido si Notitimba falla. También puede ejecutarse mediante URL:

```text
https://dominio.com/quiniela/update.php
```
