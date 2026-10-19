---
title: Controladores delgados y acciones
summary: Dónde poner la lógica de negocio, con ejemplos reales de esta misma app, y cuándo una abstracción es de más.
minutes: 8
---

La pregunta de arquitectura más frecuente en Laravel es muy simple: ¿dónde pongo esta lógica? La respuesta corta es: **no en el controlador**.

## El controlador solo traduce

Un controlador traduce entre HTTP y tu aplicación. Recibe la request, llama a quien sabe hacer el trabajo y devuelve una respuesta. Este es el de esta app para registrar la respuesta a una tarjeta, en `app/Http/Controllers/Study/FlashcardController.php`:

```php
public function review(ReviewFlashcardRequest $request, Flashcard $flashcard, ReviewFlashcard $action): RedirectResponse
{
    $action->handle($request->user(), $flashcard, $request->boolean('knew'));

    return back();
}
```

Hace tres cosas: valida (el form request), delega (la acción) y responde. No hay reglas de negocio.

## La acción hace una sola cosa

```php
class ReviewFlashcard
{
    public function __construct(private RecordStudyDay $recordStudyDay) {}

    public function handle(User $user, Flashcard $flashcard, bool $knew): FlashcardReview
    {
        // calcula la caja, guarda el repaso y registra el día de estudio
    }
}
```

Una clase de acción tiene un nombre que es un verbo, un método público (`handle`) y ninguna noción de HTTP. Por eso se puede:

- **reutilizar** desde un controlador, un comando de Artisan o un job;
- **probar** sin simular una petición web;
- **leer**: abrir `ReviewFlashcard` te dice exactamente qué pasa al responder una tarjeta.

Las acciones de esta app están en `app/Actions/Study/`. Una acción puede usar otra, como `ReviewFlashcard` usa `RecordStudyDay`.

## Un objeto en lugar de un arreglo

Cuando un grupo de datos viaja junto, dale forma:

```php
readonly class ModuleProgress
{
    public function __construct(
        public int $id,
        public string $title,
        public int $done,
        public int $total,
    ) {}
}
```

Con `$progress->done` el editor autocompleta y el análisis estático detecta un campo mal escrito. Con `$progress['done']` no. Mira el real en `app/Services/ModuleProgress.php`: cuando Larastan se quejó de la forma de un arreglo anidado, convertirlo en un objeto fue la solución correcta.

## Servicios: lógica que consulta

Una acción hace algo; un **servicio** responde preguntas. `app/Services/ProgressReport.php` calcula el progreso y la racha, y los controladores solo lo usan.

## SOLID, sin dogma

- **Una responsabilidad por clase**: si para describirla necesitas «y», probablemente sean dos.
- **Depende de abstracciones**: pide `ExchangeRates` (una interfaz), no `StripeExchangeRates`, y el container decidirá cuál entregar.
- **Sustituible**: una implementación nueva no debería obligar a cambiar a quien la usa.

## Cuándo no abstraer

Una abstracción cuesta: más archivos, más saltos para entender el flujo. Antes de crear una, pregúntate qué problema real resuelve.

- Un **repositorio** sobre Eloquent rara vez aporta: Eloquent ya es la capa de datos y envolverlo duplica sus métodos.
- Una **interfaz** con una sola implementación y sin necesidad de un fake en los tests es burocracia.
- Extrae cuando duela: cuando el controlador crezca, cuando repitas la lógica en un segundo sitio, cuando necesites sustituir algo en un test.

## Eventos para los efectos secundarios

Si al pagar un pedido hay que enviar un email, avisar a almacén y registrar una auditoría, la acción de pago no debería saber de todo eso. Dispara un evento `OrderPaid` y que cada interesado tenga su listener.

## Errores comunes

- Un controlador de 80 líneas «porque es rápido de escribir».
- Una clase `OrderService` con veinte métodos que no se relacionan entre sí.
- Interfaces y repositorios «por si algún día cambiamos de base de datos».

## Practica

Haz el reto de este módulo: refactoriza un controlador largo extrayendo una acción por operación, un objeto de datos para la entrada y un evento de dominio para los efectos secundarios, y comprueba con los tests que nada cambió.
