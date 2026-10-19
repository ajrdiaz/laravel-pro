---
title: Login de API con tokens y roles
summary: Un endpoint que cambia credenciales por un token de Sanctum, cómo revocarlo y cómo repartir permisos por rol.
minutes: 8
---

Una app móvil no tiene sesión de navegador: necesita que el servidor le entregue un **token** que enviará en cada petición. Esta lección arma ese flujo completo con Sanctum.

## El endpoint de login

```php
Route::post('/sanctum/token', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Las credenciales no son correctas.'],
        ]);
    }

    return $user->createToken($request->device_name)->plainTextToken;
})->middleware('throttle:5,1');
```

Es el flujo de la documentación de Sanctum. Tres detalles importan:

- **El mismo mensaje** para «el usuario no existe» y «la contraseña es incorrecta»: así nadie puede averiguar qué emails están registrados.
- **`throttle:5,1`** limita los intentos: sin eso es un blanco perfecto para fuerza bruta.
- `device_name` es solo informativo («iPhone de Ana»), pero permite que el usuario vea y revoque sus sesiones.

El texto del token (`plainTextToken`) se devuelve **una sola vez**: en la base de datos solo queda un hash.

## Usar el token

El cliente lo envía como `Authorization: Bearer <token>` y las rutas se protegen con el guard de Sanctum:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tasks', [TaskController::class, 'index']);
});
```

## Revocar

```php
// cerrar sesión en este dispositivo
$request->user()->currentAccessToken()->delete();

// cerrar sesión en todos
$request->user()->tokens()->delete();
```

Un token que no puedes revocar es una contraseña eterna. Además puedes darle caducidad con la opción `expiration` de `config/sanctum.php` y programar `sanctum:prune-expired` para limpiar los vencidos.

## Abilities: permisos del token

```php
$token = $user->createToken('integracion', ['tasks:read'])->plainTextToken;

if ($request->user()->tokenCan('tasks:write')) {
    // ...
}
```

Una integración externa solo necesita leer: dale un token que solo pueda eso. Si se filtra, el daño es menor. Las abilities se combinan con las policies: el token dice qué **puede** intentar, la policy decide si **este usuario** puede hacerlo sobre **este recurso**.

## Roles, sin complicarte

Para dos o tres roles basta una columna con un enum:

```php
enum Role: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
```

```php
// en la policy
public function before(User $user, string $ability): ?bool
{
    return $user->role === Role::Admin ? true : null;
}

public function update(User $user, Post $post): bool
{
    return $user->role === Role::Editor && $user->id === $post->user_id;
}
```

`before()` devuelve `true` para el administrador y `null` para dejar que decidan las reglas normales. Si necesitas permisos finos y editables desde un panel, mira un paquete como `spatie/laravel-permission`; para tres roles, es más de lo que hace falta.

## Probarlo

```php
Sanctum::actingAs($editor, ['tasks:read']);

$this->getJson('/api/tasks')->assertOk();
$this->putJson("/api/tasks/{$task->id}", ['title' => 'x'])->assertForbidden();
```

`Sanctum::actingAs` te autentica en el test con las abilities que elijas.

## Errores comunes

- Mensajes distintos para «usuario inexistente» y «contraseña incorrecta».
- Tokens con todas las abilities (`*`) «por comodidad».
- No ofrecer forma de revocar.
- Comprobar el rol solo en el frontend.

## Practica

Haz el reto de este módulo: una API con login que devuelve un token, roles admin y editor, una policy para `Post` y tests que comprueben las respuestas 401 y 403.
