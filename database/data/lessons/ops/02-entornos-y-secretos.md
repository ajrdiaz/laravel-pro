---
title: Entornos, configuración y secretos
summary: Qué va en el .env, por qué env() solo se usa en config/, cómo cifrar el archivo y cómo rotar la clave de la aplicación.
minutes: 7
---

Casi todo lo que cambia entre tu máquina y producción (la base de datos, las claves de Stripe, el modo debug) debe vivir **fuera del código**. Laravel lo resuelve con el archivo `.env` y la carpeta `config/`, y la mayoría de los accidentes vienen de mezclarlos.

## El patrón: .env → config → tu código

```php
// config/services.php
'stripe' => [
    'secret' => env('STRIPE_SECRET'),
],

// en tu código
$secret = config('services.stripe.secret');
```

El `.env` guarda los valores de **este** entorno. El archivo de `config/` los lee con `env()` y los expone con un nombre estable. Tu código usa siempre `config()`.

La regla importante: **`env()` solo se llama dentro de los archivos de `config/`**. En producción ejecutas `php artisan config:cache`, que junta toda la configuración en un solo archivo; a partir de ahí Laravel **no carga el `.env`**, así que cualquier `env('STRIPE_SECRET')` fuera de `config/` devuelve `null`. Es un fallo clásico: funciona en local, falla en producción y no hay ningún error claro.

## Qué cambia por entorno

| Variable | Local | Producción |
| --- | --- | --- |
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `APP_URL` | `http://localhost` | tu dominio con HTTPS |
| `DB_*` | SQLite o MySQL local | la base de datos real |
| `MAIL_*`, `STRIPE_*` | claves de prueba | claves reales |

Un `.env.example` con **los nombres y valores seguros** sí va al repositorio, para que cualquiera sepa qué variables necesita. El `.env` real nunca.

## Los secretos no van en el repositorio

Si una credencial entra en el historial de Git, **ya está comprometida**, aunque borres el commit después: cualquiera que clonó o hizo un fork la tiene. Hay que **cambiarla** en el servicio que la emitió.

Formas de llevar los secretos a producción:

- Variables de entorno de la plataforma (Forge, Laravel Cloud, tu panel de hosting o los secrets de GitHub Actions).
- El `.env` del servidor, que nunca sale de allí.
- Laravel permite **cifrar el `.env`** para poder guardarlo en el repositorio con seguridad:

```bash
php artisan env:encrypt                 # crea .env.encrypted y muestra la clave
php artisan env:decrypt --key=...       # lo descifra en el servidor o en el CI
```

Guarda la clave en un gestor de contraseñas o en un secreto del CI, nunca junto al archivo cifrado.

## La APP_KEY

`APP_KEY` cifra las cookies de sesión y todo lo que cifres con la clase `Crypt`. Se genera con `php artisan key:generate`.

- **No la cambies a la ligera**: invalida las sesiones y deja ilegible lo que ya cifraste.
- Si tienes que rotarla, pon la antigua en `APP_PREVIOUS_KEYS` (una lista separada por comas). Laravel cifra con la nueva y sigue pudiendo **descifrar** con las anteriores, así que nada se rompe.
- Nunca la compartas entre entornos.

## Comprobar antes de desplegar

Una lista corta que evita casi todos los sustos:

- ¿`APP_DEBUG=false` y `APP_ENV=production`?
- ¿Todas las variables de `.env.example` existen en el servidor?
- ¿Ejecuté `php artisan config:cache` y la app sigue funcionando?
- ¿Hay algún `env()` fuera de `config/`? Búscalo con `grep -r "env(" app/ routes/`.

## Errores comunes

- `env()` en un controlador o un modelo.
- Subir el `.env` «solo un momento».
- Usar las mismas claves de Stripe en local y en producción.
- Cambiar `APP_KEY` y descubrir que nadie puede iniciar sesión.

## Practica

Haz `php artisan config:cache` en tu proyecto y comprueba que todo sigue funcionando. Si algo falla, busca los `env()` que están fuera de `config/` y muévelos.
