---
title: Git en el día a día, ramas cortas y pull requests pequeños
summary: El flujo que usan los equipos que despliegan a menudo, y cómo actualizar tu rama sin dejar un desastre.
minutes: 7
---

El CI solo es tan bueno como lo que le llega. Un pull request de 40 líneas se revisa en cinco minutos; uno de 2.000 se aprueba sin leerlo. Esta lección va del flujo que mantiene los cambios pequeños.

## El flujo básico

```bash
git switch main
git pull
git switch -c feature/respuestas-del-quiz     # una rama por cambio

# ... trabajas ...
git add -p                                     # revisa qué añades, trozo a trozo
git commit -m "Registra las respuestas del quiz"

git push -u origin feature/respuestas-del-quiz
# abres el pull request en GitHub
```

Una rama corta vive un día o dos. Cuanto más dura, más se aleja de `main` y más doloroso es juntarla.

## Pull requests pequeños

- **Un solo propósito**: «añade el filtro por estado», no «filtro, refactor de tests y arreglo de estilos».
- Que el CI pase **antes** de pedir revisión.
- Una descripción que diga el **por qué**, no solo el qué.
- Si un cambio es grande, pártelo en pasos que se puedan fusionar de uno en uno.

Un buen mensaje de commit explica la razón. «Arreglos varios» no le sirve a nadie cuando busques dentro de seis meses por qué se cambió algo.

## Actualizar tu rama con main

Mientras trabajas, `main` avanza. Hay dos formas de traer sus cambios:

```bash
# merge: crea un commit de unión y conserva la historia tal cual
git switch feature/respuestas-del-quiz
git merge main

# rebase: reescribe tus commits encima de la última versión de main
git switch feature/respuestas-del-quiz
git rebase main
```

| | merge | rebase |
| --- | --- | --- |
| Historia | Conserva todo, con commits de unión | Lineal y limpia |
| Reescribe commits | No | Sí |
| Seguro en ramas compartidas | Sí | **No** |

La regla de oro: **nunca hagas rebase de una rama que otras personas ya usan**, porque reescribe commits que ellas tienen. En tu rama personal, adelante.

Tras un rebase tu historia local ya no coincide con la del servidor y el `push` normal será rechazado. Usa:

```bash
git push --force-with-lease
```

`--force-with-lease` solo sobrescribe si nadie más subió algo a esa rama; es mucho más seguro que `--force` a secas.

## Cuando hay conflictos

Un conflicto no es un fallo: es Git diciendo que dos cambios tocaron las mismas líneas y no sabe cuál manda. Abre el archivo, busca las marcas `<<<<<<<`, `=======` y `>>>>>>>`, decide qué queda, y continúa:

```bash
git add archivo-resuelto.php
git rebase --continue      # o: git commit, si estabas haciendo merge
```

Si te pierdes, `git rebase --abort` vuelve al punto de partida.

## Lo que salvó a todos alguna vez

```bash
git status                  # dónde estoy
git log --oneline -10       # qué hay
git stash                   # guardo lo que tengo a medias
git switch -                # vuelvo a la rama anterior
git commit --amend          # corrijo el último commit (solo si aún no lo subí)
git reflog                  # historial de todo lo que hice: casi nada se pierde del todo
```

## Errores comunes

- Una rama que lleva tres semanas sin actualizarse.
- Un pull request que mezcla cinco cosas.
- Rebase sobre una rama compartida.
- `git push --force` sin pensar.
- Subir un `.env` o credenciales: si pasa, **cámbialas**, porque borrar el commit no basta.

## Practica

Crea una rama, haz dos commits pequeños, actualiza la rama con `main` primero con merge y luego con rebase (en otra rama de prueba), y compara los dos historiales con `git log --oneline --graph`.
