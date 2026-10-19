---
title: Jobs que se pueden reintentar sin romper nada
summary: Sacar el trabajo lento de la request y escribir jobs idempotentes, con reintentos, lotes y workers bien desplegados.
minutes: 8
---

Un job es una clase que describe trabajo que se hará fuera de la request: enviar un email, procesar un archivo, llamar a una API externa. El usuario recibe su respuesta al instante y un **worker** hace el trabajo después.

## Un job con reintentos

```php
class ImportProducts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Segundos de espera antes de cada reintento. */
    public array $backoff = [10, 60];

    public function __construct(public string $path) {}

    public function handle(): void
    {
        // leer el archivo y crear los productos
    }

    public function failed(Throwable $exception): void
    {
        // avisar a alguien o limpiar lo que quedó a medias
    }
}

ImportProducts::dispatch($path);
```

En Laravel 13 también puedes declarar los reintentos con atributos como `#[Tries]`, `#[Backoff]` y `#[Timeout]`, directamente sobre la clase.

## Hazlo idempotente

Un job puede ejecutarse dos veces: falló a mitad y se reintentó, o el worker murió justo después de terminar. Escríbelo para que el segundo intento no duplique nada:

```php
public function handle(): void
{
    if (Payment::where('external_id', $this->externalId)->exists()) {
        return; // un intento anterior ya lo registró
    }

    Payment::create([
        'external_id' => $this->externalId,
        'amount' => $this->amount,
    ]);
}
```

Lo ideal es respaldarlo con una restricción `unique` en la base de datos, para que ni dos workers a la vez puedan duplicarlo.

## Cadenas y lotes

```php
Bus::chain([
    new ExtractData($file),
    new TransformData($file),
    new LoadData($file),
])->dispatch();
```

Una **chain** ejecuta los jobs uno tras otro, y si uno falla los siguientes no corren. Un **batch** (`Bus::batch([...])`) los ejecuta en paralelo y te deja reaccionar con `then`, `catch` y `finally`, además de consultar el porcentaje de avance.

## Workers y despliegue

```bash
php artisan queue:work         # el proceso que consume la cola
php artisan queue:restart      # tras cada deploy: el worker tiene el código viejo en memoria
php artisan queue:failed       # lista los jobs que fallaron
php artisan queue:retry all    # los vuelve a encolar
```

El worker es un proceso de larga vida. En producción lo mantiene vivo Supervisor, y con Redis puedes añadir Horizon para tener un panel y balanceo entre colas. Lo verás en el módulo de despliegue.

## Probarlo

```php
Queue::fake();

$this->post('/imports', ['path' => 'products.csv']);

Queue::assertPushed(ImportProducts::class);
```

`Queue::fake()` evita que el job se ejecute y te deja comprobar que se despachó. La lógica de `handle()` se prueba aparte, llamándola directamente.

## Errores comunes

- Olvidar `queue:restart` tras un deploy y preguntarte por qué sigue corriendo el código anterior.
- Un job que depende del orden de otros sin estar en una chain.
- Suponer que un modelo pasado al job conserva sus datos de cuando lo despachaste. Los jobs guardan el modelo por su id y lo consultan de nuevo al ejecutarse, así que ves los datos actuales.

## Practica

Haz el reto de este módulo: procesa un CSV con un batch de jobs, muestra el porcentaje de avance y registra las filas con error sin detener el resto.
