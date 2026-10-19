---
title: De tu máquina a producción
summary: La configuración, el script de deploy, los workers con Supervisor, el cron del scheduler y los errores clásicos del primer despliegue.
minutes: 8
---

Que tu app funcione en tu máquina es la mitad del trabajo. La otra mitad es que siga funcionando cuando la ve el mundo, y casi todos los problemas del primer despliegue se repiten.

## La configuración de producción

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
```

`APP_DEBUG=true` en producción muestra trazas con rutas, consultas y a veces variables de entorno a cualquier visitante. `APP_KEY` debe estar definida y no cambiar entre despliegues. Las credenciales viven en variables de entorno, nunca en el repositorio.

## El script de despliegue

```bash
cd /var/www/app

git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

- `--no-dev` deja fuera las dependencias de desarrollo.
- `migrate --force` omite la confirmación que Laravel pide en producción, para que el script automático no se quede esperando.
- `optimize` cachea configuración, rutas, vistas y eventos.
- `queue:restart` hace que los workers carguen el código nuevo.

## Mantener vivo el worker con Supervisor

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
stopwaitsecs=3600
```

Si el worker se cae, Supervisor lo vuelve a levantar. Con `--max-time=3600` se reinicia solo cada hora, lo que ayuda con la memoria.

## El scheduler necesita un cron

Las tareas programadas no corren solas: un cron del sistema debe invocarlas cada minuto.

```bash
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

Si las tareas funcionan en local pero nunca en el servidor, casi seguro falta esta línea.

## Permisos y archivos

`storage` y `bootstrap/cache` deben poder escribirse por el usuario del servidor web. Si no, verás un error 500 con «Permission denied» en `storage/logs`.

Los archivos que suben los usuarios no deben quedarse en el disco del servidor. Guárdalos en S3 u otro almacenamiento externo: así el servidor es desechable y puedes cambiarlo o duplicarlo sin perderlos.

## Despliegues sin caída

La técnica habitual es dejar cada versión en su propia carpeta y cambiar un enlace simbólico al final. Herramientas como Forge, Envoyer o Deployer lo resuelven por ti, y Laravel Cloud elimina buena parte de esta operación.

## Errores comunes

- Dejar `APP_DEBUG=true`.
- Olvidar `queue:restart`: los workers siguen con el código viejo.
- No configurar el cron del scheduler.
- Guardar las subidas en el disco local del servidor.

## Practica

Haz el reto de este módulo: pon en producción tu API de tareas con dominio y HTTPS, MySQL, Redis para las colas, Supervisor, el cron del scheduler, archivos en S3 y despliegue automático desde GitHub Actions, y documenta cada paso en el README.
