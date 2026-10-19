---
title: Ver lo que pasa en producción
summary: Logs con contexto, Pail, Telescope, Pulse y alertas útiles, para depurar sin adivinar.
minutes: 8
---

En local puedes poner un `dd()`. En producción no. **Observabilidad** es poder responder «¿qué está pasando?» sin adivinar y sin desplegar código nuevo solo para mirar.

## Tres fuentes de verdad

- **Logs**: eventos con detalle. «El pedido 42 falló porque el pago fue rechazado».
- **Métricas**: números agregados en el tiempo. Peticiones por segundo, latencia, tamaño de la cola.
- **Trazas**: el recorrido de una petición por varios servicios.

Para una app Laravel normal, con buenos logs y unas pocas métricas ya llegas lejos.

## Logs con contexto

```php
Log::info('Pedido pagado', ['order_id' => $order->id, 'user_id' => $order->user_id]);
Log::error('Cobro rechazado', ['order_id' => $order->id, 'reason' => $exception->getMessage()]);
```

Un mensaje sin contexto («Falló») no sirve de nada a las tres de la mañana. Añade los ids con los que podrás buscar. **Nunca registres contraseñas, tokens ni datos personales**: los logs se copian, se envían a terceros y se guardan meses.

Para que todos los mensajes de una petición compartan un identificador:

```php
Log::withContext(['request_id' => (string) Str::uuid()]);
```

Lo pones una vez en un middleware y luego puedes seguir una petición completa en el log.

## Canales: a dónde va el log

`config/logging.php` define canales como `single`, `daily` (un archivo por día), `stderr`, `slack` y `stack`, que combina varios. En un contenedor se suele escribir a `stderr` y dejar que la plataforma recoja el log. Un `stack` permite que un mismo mensaje vaya a un archivo y a un servicio de alertas.

## Ver el log en vivo con Pail

```bash
php artisan pail                      # todo
php artisan pail --level=error        # solo errores
php artisan pail --filter="QueryException"
php artisan pail --user=5             # lo que hizo un usuario concreto
```

Pail es un paquete de desarrollo que muestra el log en la terminal en tiempo real. Es mucho más cómodo que abrir `storage/logs/laravel.log` y recargar.

## La caja de herramientas de Laravel

| Herramienta | Para qué |
| --- | --- |
| **Telescope** | Inspeccionar requests, consultas, jobs y correos **mientras desarrollas** |
| **Pulse** | Métricas de rendimiento en producción: consultas lentas, colas, uso |
| **Nightwatch** | Servicio de monitoreo de Laravel |
| **Sentry** | Captura excepciones con su contexto y te avisa |

Telescope guarda muchísima información. Úsalo en local, y en producción solo con acceso restringido.

## El health check

Laravel registra una ruta `/up` que responde 200 si la app arrancó. Puedes verla en `bootstrap/app.php`: `health: '/up'`. La usan los balanceadores y los monitores externos para saber si deben enviarte tráfico o reiniciarte.

## Alertas: pocas y útiles

Una alerta debe significar «alguien tiene que hacer algo ahora». Alerta sobre **síntomas que sienten los usuarios**: tasa de errores, latencia alta, la cola con trabajos esperando demasiado. No sobre cada métrica que se mueve: si todo alerta, nada se atiende.

## Errores comunes

- Mensajes de log sin ningún identificador.
- Registrar la contraseña o el token «para depurar».
- Capturar una excepción y no registrarla: el error desaparece en silencio.
- Cientos de alertas que se aprenden a ignorar.

## Practica

Haz el reto de este módulo: añade a tu API de tareas logs con contexto en las acciones críticas, un canal `stderr` para producción, captura de excepciones con Sentry o Nightwatch y una alerta cuando la cola se atrasa.
