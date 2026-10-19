---
title: El PHP moderno que verás en cada archivo de Laravel
summary: Tipos, readonly, enums, match, nullsafe y atributos, lo justo de PHP 8 para leer y escribir Laravel con soltura.
minutes: 8
---

Laravel 13 exige PHP 8.3 o superior y su propio código aprovecha el lenguaje en todas partes. Si lees un archivo del framework, o de esta app, y algo te parece raro, probablemente sea una de estas ideas.

## Tipos: que falle pronto

```php
function applyDiscount(int $cents, float $rate): int
{
    return (int) round($cents * (1 - $rate));
}
```

Los tipos de parámetros y de retorno convierten un error silencioso («sumé un texto») en un error inmediato con un mensaje claro. Con `declare(strict_types=1);` al inicio del archivo, PHP además deja de convertir un `"5"` en `5` por su cuenta.

## Objetos de datos inmutables

```php
final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency = 'PEN',
    ) {}

    public function plus(Money $other): self
    {
        return new self($this->cents + $other->cents, $this->currency);
    }
}
```

- **Propiedades promovidas**: declarar la propiedad en el parámetro del constructor la crea y la asigna, sin repetir código.
- **`readonly`**: una propiedad solo se asigna una vez. Sumar dos cantidades de dinero produce un objeto nuevo y nunca modifica el anterior. En esta app, `app/Services/ModuleProgress.php` es un ejemplo real.

## Enums en lugar de cadenas sueltas

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Pagado',
            self::Shipped => 'Enviado',
        };
    }
}

OrderStatus::from('paid');      // OrderStatus::Paid
OrderStatus::tryFrom('nope');   // null
OrderStatus::from('nope');      // lanza ValueError
```

`match` es una expresión estricta: devuelve un valor y, si olvidas un caso, lanza `UnhandledMatchError` en lugar de seguir en silencio como haría un `switch`.

En Eloquent, el enum se usa como cast. Basta declararlo en el método `casts()` del modelo y `$order->status` pasa a ser un `OrderStatus`:

```php
protected function casts(): array
{
    return [
        'status' => OrderStatus::class,
    ];
}
```

Esta app lo hace con `ResourceKind` en `app/Models/LearningResource.php`.

## Código más corto, igual de claro

```php
$city = $user?->profile?->address?->city;   // null si algo falta, sin error

$total = collect($items)->sum(fn (array $item) => $item['price'] * $item['qty']);

applyDiscount(rate: 0.1, cents: 1000);      // argumentos con nombre
```

Una advertencia: Laravel puede renombrar los parámetros de sus métodos y los nombres de argumentos no entran en su garantía de compatibilidad. Úsalos con tus propias funciones y con cuidado al llamar al framework.

## Atributos

```php
#[Fillable(['name', 'email', 'password'])]
class User extends Authenticatable
```

Un atributo es un metadato que el framework lee. En Laravel 13 aparecen en los modelos (`#[Fillable]`), los controladores (`#[Middleware]`, `#[Authorize]`) y los jobs (`#[Tries]`, `#[Backoff]`). Mira `app/Models/Concept.php`.

## Errores comunes

- Usar `array` para todo. Un objeto de datos o un enum dice más y se equivoca menos.
- Comparar con `==` cuando querías `===`: `'1' == '01'` es `true`.
- Olvidar un caso en un `match` y descubrirlo en producción como `UnhandledMatchError`.

## Practica

Haz el reto de este módulo: modela los estados de un pedido con un enum, úsalo como cast en un modelo y escribe un test que compruebe que un valor inválido lanza `ValueError`.
