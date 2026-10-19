---
title: Quién eres y qué puedes hacer
summary: Autenticación frente a autorización, Sanctum con cookies o tokens, y policies para decidir qué puede hacer cada usuario.
minutes: 8
---

Son dos preguntas distintas y se responden en sitios distintos.

- **Autenticación**: ¿quién eres? Si falla, la respuesta es **401**.
- **Autorización**: ¿tienes permiso para esto? Si falla, la respuesta es **403**.

Confundirlas es el error de entrevista más común. Quien no ha iniciado sesión recibe 401. Quien sí la inició pero intenta tocar algo ajeno recibe 403.

## Sanctum: ¿cookie o token?

| Cliente                                          | Mecanismo                     |
| ------------------------------------------------ | ----------------------------- |
| Tu SPA, en el mismo dominio principal que la API | Sesión por cookie, sin tokens |
| App móvil, scripts e integraciones               | Token personal con abilities  |

No uses tokens para tu propia SPA: la sesión por cookie con protección CSRF es más segura. Sanctum se instala con `php artisan install:api`.

```php
// Un token limitado a una sola acción
$token = $user->createToken('mi-telefono', ['tasks:read'])->plainTextToken;
```

El texto del token se muestra una sola vez. En la base de datos queda guardado con hash. Para proteger una ruta:

```php
Route::get('/tasks', [TaskController::class, 'index'])->middleware('auth:sanctum');
```

## Policies: reglas por modelo

```php
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
```

Laravel descubre solo que `App\Policies\PostPolicy` corresponde a `App\Models\Post`. Para aplicarla:

```php
Gate::authorize('update', $post);   // en el controlador: lanza 403 si no se permite

Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('can:update,post'); // o en la ruta
```

Laravel 13 añade además el atributo `#[Authorize]` para declararlo sobre el método del controlador.

Para que un administrador pueda todo, usa `before()`. Devolver `null` significa «sigue con las reglas normales»:

```php
public function before(User $user, string $ability): ?bool
{
    return $user->is_admin ? true : null;
}
```

## Probar 401 y 403

```php
$this->getJson('/api/tasks')->assertUnauthorized();          // sin token: 401

Sanctum::actingAs($otherUser);
$this->putJson("/api/tasks/{$task->id}", ['title' => 'x'])
    ->assertForbidden();                                      // otro usuario: 403
```

`Sanctum::actingAs()` te autentica en el test y te deja elegir las abilities del token.

## Errores comunes

- Ocultar el botón en la interfaz y no comprobar el permiso en el servidor. Quien conozca la URL lo salta.
- Olvidar la comprobación de propiedad. Es la causa clásica de que un usuario lea o edite datos de otro.
- Usar tokens de API para la SPA propia, perdiendo la protección CSRF.

## Practica

Haz el reto de este módulo: una API con login que devuelve un token, roles admin y editor, una policy para `Post` y tests que comprueben las respuestas 401 y 403.
