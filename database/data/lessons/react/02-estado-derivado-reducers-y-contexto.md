---
title: Estado derivado, reducers y contexto
summary: Cuándo no hace falta estado, cómo reiniciarlo con una key, cómo ordenar cambios complejos con useReducer y cómo compartirlo con un contexto sin pasar props por todos lados.
minutes: 9
---

Cuando una pantalla de React crece, los problemas dejan de ser de sintaxis y pasan a ser de **dónde vive el estado**. Hay tres decisiones que lo resuelven casi siempre.

## No guardes lo que puedes calcular

Si un valor sale de props o de otro estado, no es estado: se calcula en el render.

```tsx
const [tasks, setTasks] = useState<Task[]>([]);
const [filter, setFilter] = useState<'all' | 'pending'>('all');

const visible = filter === 'all' ? tasks : tasks.filter((task) => !task.done);
```

El error típico es guardar `visible` en otro `useState` y actualizarlo con un `useEffect`. La guía oficial lo marca como algo a evitar: es más código, y además hace un render con el valor viejo y otro inmediatamente después con el nuevo. `useMemo` solo vale la pena si el cálculo es de verdad caro, y el compilador de React puede memoizar por ti.

## Reiniciar el estado con una key

Si un componente tiene estado propio (un comentario a medio escribir) y cambia la persona que muestra, ese texto se queda. En lugar de un efecto que lo vacíe, dale una `key` distinta:

```tsx
export default function ProfilePage({ userId }: { userId: number }) {
    return <Profile userId={userId} key={userId} />;
}
```

React trata dos `Profile` con `key` diferente como dos componentes distintos que no comparten estado, así que todo el estado interno de `Profile` empieza de cero cuando cambia `userId`.

## useReducer: cuando los cambios se enredan

Con `useState`, cada manejador cambia el estado a su manera y la lógica queda repartida. `useReducer` la reúne en una función con nombre para cada cambio:

```tsx
type Action =
    | { type: 'added'; id: string; title: string }
    | { type: 'toggled'; id: string }
    | { type: 'removed'; id: string };

function tasksReducer(tasks: Task[], action: Action): Task[] {
    switch (action.type) {
        case 'added':
            return [...tasks, { id: action.id, title: action.title, done: false }];
        case 'toggled':
            return tasks.map((t) => (t.id === action.id ? { ...t, done: !t.done } : t));
        case 'removed':
            return tasks.filter((t) => t.id !== action.id);
    }
}

const [tasks, dispatch] = useReducer(tasksReducer, []);

dispatch({ type: 'added', id: crypto.randomUUID(), title });
```

Dos reglas. El reducer es una **función pura**: con el mismo estado y la misma acción devuelve lo mismo, sin generar ids ni fechas dentro. En Strict Mode, React lo llama dos veces a propósito para destapar impurezas; por eso el `id` se crea en el manejador y viaja en la acción. Y como no depende del componente, lo puedes probar sin renderizar nada: `tasksReducer([], { type: 'added', id: 'a', title: 'x' })`.

La `Action` como unión de tipos hace que TypeScript te avise si escribes `'togled'` o te olvidas de un caso.

## Contexto: compartir sin pasar props por todos lados

Si diez componentes de distintos niveles necesitan `tasks` y `dispatch`, pasarlos por props es pesado. Un contexto los deja disponibles a todo un subárbol. El patrón de la documentación usa **dos** contextos, uno para el estado y otro para `dispatch`:

```tsx
const TasksContext = createContext<Task[]>([]);
const TasksDispatchContext = createContext<Dispatch<Action>>(() => {});

export function TasksProvider({ children }: { children: ReactNode }) {
    const [tasks, dispatch] = useReducer(tasksReducer, []);

    return (
        <TasksContext value={tasks}>
            <TasksDispatchContext value={dispatch}>{children}</TasksDispatchContext>
        </TasksContext>
    );
}

export const useTasks = () => useContext(TasksContext);
export const useTasksDispatch = () => useContext(TasksDispatchContext);
```

- En React 19 el propio contexto actúa de proveedor (`<TasksContext value={...}>`); `.Provider` es la forma anterior a React 19.
- El valor por defecto de `createContext` es el último recurso, para cuando no hay proveedor encima.
- Cuando el valor cambia (se compara con `Object.is`), React vuelve a renderizar **todos** los que leen ese contexto. Pasar un objeto nuevo en cada render los renderiza siempre.
- Con dos contextos, un componente que solo hace `dispatch` no se vuelve a renderizar **por el contexto** cuando cambian las tareas: la función `dispatch` es la misma entre renders.

La propia guía advierte que el contexto es tentador y fácil de abusar. Antes de usarlo prueba a pasar props, o a extraer componentes y pasar `children`. Sus casos típicos: el tema, el usuario actual, el enrutador y el estado de una pantalla compleja.

## Con Inertia

El estado del servidor (la lista de módulos, tu progreso) ya llega por props y es la fuente de verdad. No lo copies a un `useState` «para tenerlo local»: se desincroniza en cuanto el servidor responde. Si necesitas una copia editable, es un formulario: usa `useForm` de Inertia.

## Errores comunes

- Guardar en estado algo que se deriva de props u otro estado, y sincronizarlo con un efecto.
- Vaciar un formulario con `useEffect` en lugar de cambiar la `key`.
- Generar ids o fechas dentro de un reducer.
- Un contexto único con todo dentro: cualquier cambio vuelve a renderizar a todos sus consumidores.
- Usar un contexto para pasar un valor a un hijo directo.

## Practica

Reescribe la lista de tareas de la lección anterior con `useReducer`, y comprueba el reducer con un test de tres líneas. Luego muévelo a un `TasksProvider` con dos contextos, y renderiza dos componentes: uno que lee `useTasks()` y otro que solo usa `useTasksDispatch()`. Pónlos como `children` del proveedor, añade un `console.log` a cada uno y observa cuál se vuelve a renderizar cuando añades una tarea.
