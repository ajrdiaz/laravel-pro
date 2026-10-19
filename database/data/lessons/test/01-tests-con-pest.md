---
title: Tests con Pest que de verdad protegen
summary: Feature tests, datasets, fakes y factories, y la lista corta de casos que siempre debes cubrir.
minutes: 7
---

Un test que falla cuando rompes algo vale más que cien que siempre pasan. Esto es lo mínimo que de verdad protege.

## Un feature test completo

```php
use App\Models\User;

it('permite a un usuario crear una tarea', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/tasks', ['title' => 'Aprender Pest'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Aprender Pest');

    $this->assertDatabaseHas('tasks', [
        'title' => 'Aprender Pest',
        'user_id' => $user->id,
    ]);
});
```

Hace una request real, comprueba la respuesta **y** comprueba el efecto en la base de datos. Esta app mezcla dos estilos: los tests que trae el starter kit son clases con métodos `test_...`, y `tests/Pest.php` deja escribir también tests con `it()` en `tests/Feature`.

## Un test, muchos casos: datasets

```php
it('rechaza tareas inválidas', function (array $payload, string $field) {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/tasks', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'sin título' => [[], 'title'],
    'título demasiado largo' => [['title' => str_repeat('a', 121)], 'title'],
]);
```

Cada entrada del dataset corre como un test independiente y aparece con su nombre en el resultado.

## Lo que siempre debes probar

1. El caso feliz.
2. La validación: que un dato inválido responde 422.
3. Sin sesión: 401.
4. Sin permiso: 403.
5. Que un usuario **no** puede ver ni tocar lo de otro.

El tercero y el cuarto son los que más se olvidan, y los que más daño hacen cuando fallan.

## Fakes: probar sin efectos reales

```php
Queue::fake();
Mail::fake();
Storage::fake('public');
Http::fake([
    'api.example.test/*' => Http::response(['rate' => 3.75]),
]);
```

Después compruebas con `Queue::assertPushed(...)`, `Mail::assertSent(...)` o `Http::assertSent(...)`. Nada sale a internet y nada se envía.

## Base de datos limpia en cada test

Los feature tests que tocan la base de datos usan el trait `RefreshDatabase`. En esta app ya está activado para todo `tests/Feature` en `tests/Pest.php`.

## Factories con estados

```php
Order::factory()->paid()->create();

// en OrderFactory
public function paid(): static
{
    return $this->state(['status' => 'paid']);
}
```

Un estado le da nombre a una variación de los datos y deja el test legible.

## Errores comunes

- Comprobar solo `assertOk()`. Pasa aunque la respuesta esté vacía o mal formada.
- Atar el test al HTML exacto en lugar de a los datos.
- No probar nunca el 403.

## Practica

Haz el reto de este módulo: escribe los tests de tu API de tareas (caso feliz, 422, 401, 403) y uno que compruebe que se despacha un job al completar una tarea.
