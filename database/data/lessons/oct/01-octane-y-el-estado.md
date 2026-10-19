---
title: Octane, más rápido si no guardas estado
summary: Qué cambia al mantener la app en memoria, los errores que provoca y cómo medir si de verdad te conviene.
minutes: 8
---

Con PHP-FPM, cada petición arranca el framework desde cero, atiende y se olvida de todo. Es lento de arrancar pero **a prueba de errores de estado**: nada sobrevive entre peticiones. Octane cambia ese pacto.

## Qué hace Octane

Octane arranca la aplicación **una vez**, la mantiene en memoria y le va entregando peticiones. Te ahorras el coste de arrancar el framework en cada una. Funciona sobre servidores de aplicación como FrankenPHP, RoadRunner, Swoole y Open Swoole.

```bash
composer require laravel/octane
php artisan octane:install        # eliges el servidor; con FrankenPHP descarga el binario
php artisan octane:start
```

Por defecto levanta un worker por núcleo de CPU. Puedes ajustarlo con `--workers=4`.

## El precio: el estado sobrevive

`register()` y `boot()` de tus providers corren **una sola vez por worker**. Todo lo que guardes en un objeto que viva más que una petición sigue ahí en la siguiente, y eso causa los bugs típicos.

**El singleton con la request dentro:**

```php
// MAL: el singleton se crea una vez y se queda con la request de la primera petición
$this->app->singleton(Service::class, function (Application $app) {
    return new Service($app['request']);
});
```

Las peticiones siguientes verían las cabeceras y la entrada de la primera: datos de otra persona. Arréglalo pasando solo lo necesario como argumento de cada método:

```php
$service->handle($request->input('name'));
```

La misma regla vale para el container y para el repositorio de configuración: no los inyectes en un singleton.

**La propiedad estática que crece:**

```php
// MAL: cada petición añade un elemento y la memoria nunca se libera
Service::$data[] = Str::random(10);
```

Con PHP-FPM el arreglo moría al terminar la petición. Con Octane persiste y se convierte en una fuga de memoria.

## singleton o scoped

`singleton` vive toda la vida del worker. `scoped` se reinicia al terminar cada petición. Para un servicio que guarde datos de la petición actual, usa `scoped`.

## Operarlo

```bash
php artisan octane:reload        # tras cada deploy: los workers tienen el código viejo en memoria
php artisan octane:start --max-requests=250
```

Octane reinicia cada worker tras 500 peticiones por defecto, para contener fugas pequeñas. Eso es una red de seguridad, no una excusa para dejar fugas. Detrás del servidor pon Nginx, que sirve los estáticos, y un proceso como Supervisor que lo mantenga vivo.

## Mide antes de activarlo

Octane ayuda cuando el coste de **arrancar el framework** es una parte importante del tiempo de cada petición. Si lo lento es una consulta de 800 ms, Octane no la acelera. Una prueba de carga simple con k6:

```js
import http from 'k6/http';

export const options = { vus: 20, duration: '30s' };

export default function () {
    http.get('http://localhost:8000/up');
}
```

```bash
k6 run script.js
```

Compara las peticiones por segundo y la latencia con y sin Octane, en las rutas que de verdad importan. Si la mejora no compensa la nueva clase de bugs, déjalo.

## Errores comunes

- Activar Octane sin medir.
- Guardar la request, el container o la configuración en un singleton.
- Propiedades estáticas que acumulan datos.
- Olvidar `octane:reload` tras un deploy y seguir ejecutando código viejo.

## Practica

Haz el reto de este módulo: instala Octane con FrankenPHP, mide con k6 antes y después, y encuentra y corrige un singleton que guarde datos de la request.
