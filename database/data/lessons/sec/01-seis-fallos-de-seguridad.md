---
title: Los seis fallos de seguridad que más se repiten
summary: Autorización, inyección SQL, XSS, asignación masiva, secretos y fuerza bruta, con el arreglo de cada uno en Laravel.
minutes: 9
---

Casi todas las brechas en aplicaciones web vienen de un puñado de errores que se repiten. Laravel te protege de varios por defecto, pero ninguno se arregla solo si lo esquivas.

## 1. Autorización: el fallo número uno

```php
// MAL: cualquiera que esté autenticado puede ver cualquier factura
public function show(Invoice $invoice)
{
    return $invoice;
}

// BIEN: la policy comprueba que la factura es suya
public function show(Invoice $invoice)
{
    Gate::authorize('view', $invoice);

    return $invoice;
}
```

Cambiar `/invoices/10` por `/invoices/11` y ver una factura ajena se llama **IDOR**. Lo evita la comprobación en el servidor, no esconder el enlace.

## 2. Inyección SQL

```php
// MAL: la entrada del usuario entra al texto SQL
DB::select("select * from users where email = '$email'");

// BIEN: parámetro enlazado
DB::select('select * from users where email = ?', [$email]);
User::where('email', $email)->first();
```

Eloquent y el query builder ya enlazan los parámetros. El riesgo está en `DB::raw()`, `whereRaw()` y en cualquier texto SQL que armes concatenando.

## 3. XSS: HTML que no escribiste tú

En Blade, `{{ $texto }}` escapa el HTML y es seguro. `{!! $texto !!}` imprime tal cual, así que un comentario con `<script>` se ejecutaría en el navegador de quien lo lea. Úsalo solo con contenido que genera tu propio código. En Vue y React, `v-html` y `dangerouslySetInnerHTML` tienen el mismo peligro.

## 4. Asignación masiva

```php
// MAL: el usuario puede enviar is_admin=1 y se guarda
$user->update($request->all());

// BIEN: solo los campos validados, y el modelo declara cuáles son rellenables
$user->update($request->validated());
```

Define qué campos puede rellenar un `create()` o un `update()`, con `#[Fillable]` como en `app/Models/Concept.php` o con la propiedad `$fillable`, y usa `$request->validated()` en lugar de `$request->all()`.

## 5. Secretos y modo debug

- `APP_DEBUG=false` en producción: con `true`, una excepción muestra rutas, consultas y a veces variables de entorno.
- Las credenciales van en variables de entorno, nunca en el repositorio. `php artisan env:encrypt` cifra tu `.env` para poder guardarlo con seguridad.
- Revisa tus dependencias con `composer audit`, que avisa de las que tienen vulnerabilidades conocidas.

## 6. Fuerza bruta y subidas de archivos

El starter kit de esta app limita el login a 5 intentos por minuto, en `app/Providers/FortifyServiceProvider.php`. Si escribes un endpoint propio, protégelo:

```php
Route::post('/login', LoginController::class)->middleware('throttle:5,1');
```

Y valida las subidas por tipo y tamaño, no por la extensión que dice el usuario:

```php
$request->validate([
    'avatar' => ['required', File::types(['jpg', 'png'])->max(2 * 1024)],
]);
```

## CSRF, en una línea

Laravel valida un token CSRF en toda petición que cambia estado, con el middleware `PreventRequestForgery`, incluido en el grupo `web`. Los formularios Blade lo llevan con `@csrf`, y Inertia lo envía por ti.

## Errores comunes

- Comprobar permisos solo en la interfaz.
- `$request->all()` en un `create()`.
- Dejar `APP_DEBUG=true` «un momento» en producción.

## Practica

Haz el reto de este módulo: audita una app tuya buscando `DB::raw`, `{!! !!}`, modelos sin campos rellenables definidos y rutas sin policy, corrige cada hallazgo y escribe un test que pruebe que un usuario recibe 403 al tocar el recurso de otro.
