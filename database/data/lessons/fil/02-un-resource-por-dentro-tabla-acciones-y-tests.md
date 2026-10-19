---
title: Un resource de Filament por dentro: tabla, acciones y tests
summary: Qué genera Filament, cómo definir columnas, filtros y acciones, por qué una acción propia necesita su propia autorización y cómo probar un panel con Pest.
minutes: 9
---

En la lección anterior montaste un panel y viste que el acceso lo deciden tu modelo y tus policies. Ahora abrimos un **resource** para ver cómo está hecho, dónde se ajusta cada cosa y qué hay que vigilar.

## Qué genera `make:filament-resource`

```bash
php artisan make:filament-resource Customer
```

En Filament 5 crea esta estructura dentro de `app/Filament/Resources/Customers`:

```text
CustomerResource.php
Pages/        CreateCustomer.php, EditCustomer.php, ListCustomers.php
Schemas/      CustomerForm.php
Tables/       CustomersTable.php
```

La clase del resource lo une todo. Las clases de `Pages` son componentes Livewire de página completa que puedes personalizar. El formulario vive en `Schemas` y la tabla en `Tables`, cada uno en su archivo.

Algunas opciones útiles al generar: `--generate` crea el formulario y la tabla a partir de las columnas de la base de datos, `--simple` gestiona los registros en una sola página con modales, `--soft-deletes` añade restaurar y filtrar eliminados, y `--view` añade una página de solo lectura.

Si creas un resource y no aparece en el menú, la documentación señala la causa más frecuente: si tienes una policy del modelo, su método `viewAny()` debe devolver `true`.

## La tabla

Las columnas, el buscador y el orden se definen con métodos encadenables:

```php
TextColumn::make('title')->searchable()->sortable(),
TextColumn::make('author.name'),
```

`searchable()` añade el buscador de la tabla, y se puede poner en varias columnas a la vez. La segunda columna usa **notación de puntos**: Filament busca la relación `author` del modelo y muestra su `name`, y la carga por adelantado (*eager loading*) para evitar el problema N+1.

Los filtros se declaran con `filters()`:

```php
->filters([
    Filter::make('is_featured')
        ->query(fn (Builder $query) => $query->where('is_featured', true)),

    SelectFilter::make('status')->options([
        'draft' => 'Borrador',
        'reviewing' => 'En revisión',
        'published' => 'Publicado',
    ]),
])
```

El primero aparece como casilla; el segundo, como desplegable.

## Acciones, y la trampa de seguridad

Las acciones son botones en cada fila (`recordActions`) o sobre las filas seleccionadas (`toolbarActions`):

```php
->recordActions([
    Action::make('feature')
        ->action(function (Post $record) {
            $record->is_featured = true;
            $record->save();
        })
        ->hidden(fn (Post $record): bool => $record->is_featured),
])
->toolbarActions([
    BulkActionGroup::make([DeleteBulkAction::make()]),
])
```

Aquí hay un detalle que la propia documentación subraya: **las acciones propias necesitan su autorización explícita**. Ocultar un botón con `hidden()` no es autorizar nada. Se autoriza con una policy:

```php
Action::make('feature')
    ->action(fn (Post $record) => $record->update(['is_featured' => true]))
    ->authorize('update')
```

Y conviene saber qué hace Filament por ti y qué no, según su documentación:

- Las acciones integradas de crear, editar y borrar usan la policy del modelo del resource.
- **Si falta la policy, o falta ese método, el acceso se permite.** Por eso escribir las policies es tu trabajo.
- El borrado masivo, la eliminación definitiva y la restauración comprueban las habilidades `*Any()` (`deleteAny()`…), no la policy de cada registro, salvo que actives la autorización por registro.
- Nada de esto se aplica a las acciones propias ni a componentes Livewire sueltos.
- Asociar y desasociar relaciones también necesita autorización explícita.

Si tu policy devuelve un mensaje de denegación, `authorizationTooltip()` deja el botón deshabilitado con ese texto; si devuelve un simple `false`, el botón se oculta.

## Qué ve cada persona: `getEloquentQuery()`

La tabla, la edición y la búsqueda global del resource empiezan por el método `getEloquentQuery()`, y puedes ajustarlo para acotar qué registros existen para ese panel:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->where('is_active', true);
}
```

Por defecto, Filament respeta los *global scopes* de tu modelo. Para ver registros eliminados tendrías que quitarlos aquí con `withoutGlobalScopes()`.

## Probar un panel con Pest

Un resource es un conjunto de componentes Livewire, y se prueba como tales con el helper `livewire()`. Autentica a alguien antes de cada prueba (con `actingAs`):

```php
use function Pest\Livewire\livewire;

it('no muestra los posts eliminados', function () {
    $posts = Post::factory()->count(4)->create();
    $trashed = Post::factory()->trashed()->count(6)->create();

    livewire(ListPosts::class)
        ->assertCanSeeTableRecords($posts)
        ->assertCanNotSeeTableRecords($trashed)
        ->assertCountTableRecords(4);
});
```

Con la misma idea compruebas el buscador, los filtros y el orden: `searchTable('texto')`, `filterTable('status', 'published')`, `sortTable('title', 'desc')`. Para el formulario de creación, `fillForm([...])` seguido de `call('create')`, y `assertHasFormErrors` para los errores de validación. Y para las acciones, lo que más importa en seguridad:

```php
livewire(ListInvoices::class)
    ->assertActionVisible(TestAction::make('send')->table($invoice))
    ->assertActionHidden(TestAction::make('archive')->table($invoice));
```

Escribe una prueba así por cada acción propia, con un usuario que **no** debería poder usarla.

## Errores comunes

- Creer que `hidden()` protege una acción: solo la esconde.
- No escribir policies porque «Filament ya las gestiona»: si faltan, se permite el acceso.
- Olvidar que el borrado masivo comprueba `deleteAny()` y no cada registro.
- Un resource que no aparece en el menú porque `viewAny()` devuelve `false`.
- Probar solo el camino feliz del administrador y nunca con un usuario sin permiso.

## Practica

Genera un resource con `--generate` para un modelo tuyo. Añade una acción propia `archive` con `->authorize('update')`, una policy cuyo `update()` solo permita a administradores, y dos pruebas con `livewire()`: una que compruebe que un administrador ve la acción y otra que compruebe que un usuario normal no. Después borra el método `update()` de la policy y observa qué ocurre.
