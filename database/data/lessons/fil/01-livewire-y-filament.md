---
title: Livewire y Filament, dos ideas y un modelo mental
summary: Cómo funciona un componente Livewire, por qué su estado público es entrada del usuario y cómo montar un panel con Filament sin abrir una brecha.
minutes: 9
---

Livewire y Filament son la otra forma de hacer interfaces dinámicas en Laravel: en lugar de escribir componentes de Vue o React, escribes **PHP** y el framework se ocupa del JavaScript.

## Un componente Livewire

En Livewire 4, `php artisan make:livewire post.create` crea un componente de archivo único: la lógica en PHP y la vista Blade, juntas.

```php
<?php

use Livewire\Component;

new class extends Component {
    public string $title = '';

    public function save(): void
    {
        $this->validate(['title' => 'required|max:120']);

        // guardar el post...
    }
};
?>

<div>
    <input wire:model="title" type="text">
    <button wire:click="save">Guardar</button>
</div>
```

- `wire:model` enlaza el campo con la propiedad `$title`. Para sincronizar mientras el usuario escribe, usa `wire:model.live`.
- `wire:click="save"` llama al método del servidor cuando se hace clic.

Cada interacción es una **petición al servidor** que vuelve con el HTML actualizado. Para el usuario es instantáneo; para ti, no hay una API ni un cliente que mantener.

## El detalle que lo cambia todo

Las propiedades públicas del componente **viajan al navegador, y el navegador puede devolverlas modificadas**. La documentación de Livewire lo dice sin rodeos: trata las propiedades públicas como entrada del usuario, igual que una request normal.

```php
use Livewire\Attributes\Locked;

#[Locked]
public int $orderId;
```

Sin `#[Locked]`, alguien podría cambiar `orderId` desde las herramientas del navegador y ver el pedido de otra persona. Con él, Livewire rechaza esa modificación. Además, **autoriza siempre en el servidor** a qué recurso puede acceder el usuario: lo de arriba es un seguro, no la regla.

## Filament: un panel a partir de tus modelos

Filament construye paneles de administración sobre Livewire. Filament 5 requiere PHP 8.2 o superior, Laravel 11.28 o superior y Tailwind CSS 4.1 o superior.

```bash
composer require filament/filament:"^5.0"
php artisan filament:install --panels
php artisan make:filament-user
php artisan make:filament-resource Product
```

Abre `/admin`, entra con el usuario que creaste y tienes un panel. Un **resource** describe cómo administrar un modelo: su tabla, su formulario y sus páginas de listado, creación y edición.

## Quién puede entrar

Esto sorprende en el primer despliegue: en local cualquier usuario entra, pero **en otros entornos Filament exige que tu modelo `User` decida el acceso**:

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;   // tu propia regla
    }
}
```

Sin esta interfaz, un administrador que podía entrar en local recibe un 403 en producción.

## Y qué puede hacer dentro

Filament respeta las **policies** que tengas registradas: `viewAny()` oculta el recurso del menú, `create()`, `update()` y `delete()` controlan cada acción, y `deleteAny()` el borrado masivo. Escribe las policies y **pruébalas con tests**: según la documentación de Filament, si falta la policy de un modelo, o el método concreto, el acceso se **permite**. En la siguiente lección verás qué más no cubre por defecto.

## Cuándo sí y cuándo no

- **Sí**: backoffice, herramientas internas, paneles de soporte, prototipos donde lo importante es administrar datos rápido.
- **No**, o con cuidado: cuando la interfaz es el producto y necesita un diseño totalmente a medida. Personalizar mucho un panel de Filament cuesta más que construirlo a mano.
- Las versiones mayores de Filament y de Livewire traen cambios: fija las versiones y lee la guía de actualización antes de subir.

## Errores comunes

- Una propiedad pública con un id sensible, sin `#[Locked]` y sin comprobar permisos.
- No implementar `FilamentUser` y descubrirlo al desplegar.
- Dar por hecho que Filament protege cada modelo sin escribir las policies.

## Practica

Haz el reto de este módulo: un panel de Filament para un modelo, con tabla con filtros, formulario validado, una policy que limite quién edita y una acción masiva, más un test de la autorización.
