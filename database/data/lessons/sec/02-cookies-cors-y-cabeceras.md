---
title: Cookies, CORS y cabeceras de seguridad
summary: Qué le pides al navegador en cada respuesta: cookies que no se filtran, orígenes permitidos y cabeceras que cierran puertas.
minutes: 8
---

Una parte de la seguridad no está en tu código PHP sino en lo que le **dices al navegador** que haga (o no haga) con tu sitio. Son tres palancas: las cookies, CORS y las cabeceras.

## Cookies de sesión

Todo se configura en `config/session.php`. Estas son las opciones que importan y su valor por defecto:

| Opción | Por defecto | Qué hace |
| --- | --- | --- |
| `secure` | sin fijar | La cookie solo viaja por HTTPS |
| `http_only` | `true` | JavaScript no puede leerla (corta el robo de sesión por XSS) |
| `same_site` | `lax` | Cuándo se envía en peticiones desde otros sitios |
| `encrypt` | `false` | Cifra los datos de la sesión en el almacén |

Sobre `secure`: sin fijar, Laravel marca la cookie como segura solo cuando la petición llegó por HTTPS. Detrás de un balanceador que termina el TLS, la app puede creer que la petición es HTTP si no has configurado los proxies de confianza (`$middleware->trustProxies(at: [...])` en `bootstrap/app.php`). En producción fija `SESSION_SECURE_COOKIE=true` y no dependas de que lo adivine.

`same_site` acepta `lax`, `strict`, `none` o `null`:

- **`lax`**: la cookie viaja con tus peticiones y también cuando alguien llega a tu sitio desde otro pulsando un enlace o enviando un formulario con un método seguro como `GET`. No viaja en un `POST` ni en un `fetch` desde otro sitio, ni en imágenes o iframes.
- **`strict`**: solo viaja si la petición nace en tu propio sitio. Más seguro, pero quien llegue desde un enlace externo verá la página sin sesión la primera vez.
- **`none`**: viaja siempre, y exige que la cookie sea `Secure`.

Esta app tiene un caso real: en `bootstrap/app.php` cifra todas las cookies **excepto** `appearance` y `sidebar_state`. Esas dos las escribe el JavaScript del navegador (`document.cookie`), así que llegan sin cifrar y Laravel no podría descifrarlas. Regla: cifra todo salvo lo que escribe o lee el navegador, y nunca guardes ahí nada sensible.

## CORS: qué es y qué no es

CORS es una regla del **navegador**: el servidor declara qué orígenes pueden **leer** sus respuestas desde JavaScript. No es autenticación, y no frena a `curl` ni a otro servidor: solo a una página web de otro dominio.

Laravel responde solo a las peticiones `OPTIONS` previas (preflight) con el middleware `HandleCors`. Para cambiar la configuración publícala:

```bash
php artisan config:publish cors
```

Sin publicar, el framework usa estos valores: CORS solo en `api/*` y `sanctum/csrf-cookie`, `allowed_origins` con `*` y `supports_credentials` en `false`. Es decir, cualquier web puede llamar a tu API desde el navegador, pero sin enviar cookies.

Si tu frontend vive en otro dominio y necesita enviar cookies, pon `supports_credentials` en `true` **y lista los orígenes de uno en uno**: según MDN, una respuesta a una petición con credenciales no puede usar `*` en `Access-Control-Allow-Origin`.

```php
'allowed_origins' => ['https://app.midominio.com'],
'supports_credentials' => true,
```

Una app monolítica como esta, sin API pública, no necesita tocar nada.

## Cabeceras de seguridad

Si pides la página de login de esta app, la respuesta no trae ninguna cabecera de seguridad: ni `Strict-Transport-Security`, ni `X-Content-Type-Options`, ni `Content-Security-Policy`. Las añades tú con un middleware:

```php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
```

Y lo registras en `bootstrap/app.php`: `$middleware->web(append: [SecurityHeaders::class]);`.

- **`X-Content-Type-Options: nosniff`**: el navegador respeta el `Content-Type` que declaras en vez de adivinarlo.
- **`Referrer-Policy`**: controla cuánta URL se envía al seguir un enlace externo.
- **`Strict-Transport-Security`**: obliga al navegador a usar siempre HTTPS con tu dominio durante `max-age` segundos. Se envía solo por HTTPS: los navegadores la ignoran si llega por HTTP. Empieza con un `max-age` corto mientras compruebas que todo carga bien por HTTPS.
- **`Content-Security-Policy`**: lista de dónde puede cargar scripts, estilos e imágenes tu página, y funciona como segunda defensa contra XSS. Empieza con `Content-Security-Policy-Report-Only`: no bloquea nada, solo informa de lo que habría bloqueado. Con Vite necesitas un *nonce* para tus scripts: Laravel lo da con `Vite::useCspNonce()` en un middleware.

## Errores comunes

- Dejar la sesión sin `Secure` en producción y creer que HTTPS basta.
- Poner `allowed_origins => ['*']` junto con `supports_credentials => true`: el navegador lo rechaza.
- Pensar que CORS protege tu API: cualquiera con `curl` la llama igual.
- Activar HSTS con un `max-age` enorme antes de comprobar que todo el sitio funciona por HTTPS.
- Una CSP con `unsafe-inline` en `script-src`: anula casi toda su protección contra XSS.

## Practica

Añade el middleware `SecurityHeaders` a esta app y escribe un test que pida `/login` y compruebe con `assertHeader` que `X-Content-Type-Options` vale `nosniff`. Luego pide la página con `curl -I` y, en las herramientas del navegador, mira los flags de la cookie `laravel-session`: ¿`HttpOnly`, `SameSite=Lax`?
