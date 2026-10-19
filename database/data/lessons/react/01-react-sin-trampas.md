---
title: React sin trampas
summary: Estado inmutable, efectos con limpieza, listas con key y por qué casi nunca necesitas useEffect.
minutes: 9
---

React describe la interfaz como una función del estado. La mayoría de los errores de principiante vienen de tres ideas: mutar el estado, abusar de `useEffect` y olvidar las `key`.

## Un componente completo

```tsx
import { useState } from 'react';

type Task = { id: number; title: string; done: boolean };

export function Tasks() {
    const [tasks, setTasks] = useState<Task[]>([]);
    const [title, setTitle] = useState('');

    const pending = tasks.filter((task) => !task.done).length; // derivado: sin useEffect

    function add(event: React.FormEvent) {
        event.preventDefault();

        if (title.trim() === '') {
            return;
        }

        setTasks([...tasks, { id: Date.now(), title, done: false }]);
        setTitle('');
    }

    function toggle(id: number) {
        setTasks(
            tasks.map((task) =>
                task.id === id ? { ...task, done: !task.done } : task,
            ),
        );
    }

    return (
        <>
            <form onSubmit={add}>
                <input value={title} onChange={(e) => setTitle(e.target.value)} />
                <button>Añadir</button>
            </form>

            <p>{pending} pendientes</p>

            <ul>
                {tasks.map((task) => (
                    <li key={task.id}>
                        <label>
                            <input
                                type="checkbox"
                                checked={task.done}
                                onChange={() => toggle(task.id)}
                            />
                            {task.title}
                        </label>
                    </li>
                ))}
            </ul>
        </>
    );
}
```

## Nunca mutes el estado

`tasks.push(...)` cambia el arreglo pero no su referencia, y React compara por referencia para saber si algo cambió: no vuelve a renderizar. Siempre pasa al setter un valor **nuevo**: `[...tasks, nueva]` para añadir, `tasks.map(...)` para modificar, `tasks.filter(...)` para borrar, y `{ ...task, done: true }` para cambiar un objeto.

## Props y estado

Las props llegan del padre y el componente no las modifica. El estado es del propio componente y al cambiarlo con su setter React vuelve a renderizar. Si el hijo necesita avisar al padre, recibe una función por props y la llama.

## useEffect: solo para sincronizar con el exterior

```tsx
useEffect(() => {
    const controller = new AbortController();

    fetch(`/api/users/${id}`, { signal: controller.signal })
        .then((response) => response.json())
        .then(setUser)
        .catch(() => {});

    return () => controller.abort(); // limpieza: cancela si cambia `id` o se desmonta
}, [id]);
```

- El arreglo `[id]` dice de qué depende el efecto: corre tras el primer render y cada vez que `id` cambia. Con `[]` corre una sola vez; sin arreglo, en cada render.
- La función que devuelves limpia: cancela una petición, quita un listener, cierra una suscripción.

Pero **la mayoría de las veces no necesitas un efecto**. Para calcular un valor a partir de props o estado (`pending` arriba), hazlo durante el render. Para responder a un clic, hazlo en el manejador. La guía oficial «You Might Not Need an Effect» lo explica muy bien.

## Con Inertia

En una app Inertia casi nunca haces `fetch` a mano: el controlador de Laravel entrega las props y `useForm` o `router` mandan los cambios. El backend es el mismo que con Vue; solo cambian los componentes.

## Errores comunes

- Mutar el estado (`push`, `splice`, asignar a una propiedad) y no ver el cambio.
- Un `useEffect` que copia props a estado para «mantenerlos sincronizados».
- Olvidar una dependencia en el arreglo y trabajar con valores viejos.
- Usar el índice como `key` en una lista que se reordena.

## Practica

Haz el reto de este módulo: la lista de tareas del módulo de Vue en React, con un filtro calculado durante el render y un hook propio `useTasks()`.
