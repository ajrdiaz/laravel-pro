---
title: Una API REST que no dé vergüenza
summary: Resources, form requests, códigos HTTP correctos, paginación, versionado y límites de uso.
minutes: 8
---

Una API es un contrato con gente que no controlas. Cada decisión que tomes hoy será difícil de cambiar cuando alguien dependa de ella.

## El resource es tu contrato público

```php
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'done' => $this->done,
            'owner' => new UserResource($this->whenLoaded('owner')),
        ];
    }
}
```

Tú eliges exactamente qué campos salen, así que nunca se filtra una columna interna por accidente. `whenLoaded()` incluye la relación solo si ya estaba cargada: así el resource no dispara consultas por su cuenta.

## Valida con un form request

```php
class StoreTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
        ];
    }
}
```

Si el cliente envía `Accept: application/json`, una validación fallida responde **422** con los errores en JSON.

## El controlador queda corto

```php
public function store(StoreTaskRequest $request): JsonResponse
{
    $task = $request->user()->tasks()->create($request->validated());

    return TaskResource::make($task)->response()->setStatusCode(201);
}

public function index(Request $request): AnonymousResourceCollection
{
    return TaskResource::collection(
        $request->user()->tasks()->latest()->paginate(15)
    );
}
```

Combinar `paginate()` con un resource devuelve `meta` y `links` sin trabajo extra.

## Los códigos que debes dominar

| Código | Cuándo                                          |
| ------ | ----------------------------------------------- |
| 200    | Todo bien y devuelves algo                      |
| 201    | Creaste un recurso                              |
| 204    | Todo bien y no hay cuerpo (típico de un DELETE) |
| 401    | No estás autenticado                            |
| 403    | Estás autenticado pero no tienes permiso        |
| 404    | No existe                                       |
| 422    | Falló la validación                             |
| 429    | Demasiadas requests                             |

## Versiona y limita

```php
// routes/api.php ya añade el prefijo /api
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('tasks', TaskController::class);
});
```

Eso publica `/api/v1/tasks`. Cuando necesites un cambio incompatible, nace `v2` y `v1` sigue funcionando para los clientes antiguos.

```php
// AppServiceProvider::boot()
RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
    ->by($request->user()?->id ?: $request->ip()));
```

El límite se aplica con el middleware `throttle:api`. Se cuenta por usuario si lo hay y por IP si no.

## Documéntala

Scramble o Scribe generan documentación OpenAPI a partir de tu código. Una API sin documentación obliga a los demás a leer tu código fuente.

## Errores comunes

- Devolver el modelo completo en lugar de un resource: expones columnas que no querías.
- Responder 200 con `{ "error": ... }`. El código HTTP existe para eso.
- Listados sin paginación: un día la tabla tendrá un millón de filas.

## Practica

Haz el reto de este módulo: la API v1 de un gestor de tareas con CRUD completo, form requests, resources, paginación, filtro por estado, límite de 60 requests por minuto y documentación generada.
