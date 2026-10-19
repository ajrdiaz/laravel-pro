---
title: Un pipeline de GitHub Actions para Laravel
summary: Un workflow que corre estilo, análisis estático y tests en cada pull request, y despliega solo si todo pasó.
minutes: 7
---

Un pipeline de integración continua (CI) responde una pregunta en cada cambio: ¿sigue funcionando todo? Si la respuesta no es automática, depende de que alguien se acuerde de correr los tests.

## Los conceptos

- Un **workflow** es un archivo YAML en `.github/workflows/`.
- Se dispara con **eventos**: `push`, `pull_request`...
- Tiene **jobs**, y cada job corre en su propio **runner** limpio. Por defecto los jobs corren en paralelo.
- Cada job ejecuta **steps** en orden.

## El workflow de pruebas

```yaml
name: tests

on:
  pull_request:
  push:
    branches: [main]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none

      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env
      - run: php artisan key:generate

      - run: vendor/bin/pint --test        # estilo: falla si algo no está formateado
      - run: vendor/bin/phpstan analyse    # análisis estático
      - run: php artisan test
```

Cada línea `run` que falle detiene el job y marca el pull request en rojo. `setup-php` documenta cómo cachear las dependencias de Composer para que el pipeline sea más rápido.

## Desplegar solo si todo pasó

Un segundo job, dentro de `jobs:`, justo debajo de `tests`:

```yaml
  deploy:
    needs: tests
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - name: Disparar el despliegue
        run: curl --fail -X POST "${{ secrets.DEPLOY_HOOK_URL }}"
```

`needs: tests` hace que el deploy espere al job de pruebas y no se ejecute si este falla. Sin `needs`, los dos correrían a la vez. La condición `if` evita desplegar desde un pull request.

## Los secretos no van en el YAML

Todo lo que escribas en el archivo lo ve cualquiera con acceso al repositorio. Guarda tokens y URLs de despliegue en la configuración del repositorio (Settings, Secrets and variables) y léelos con `${{ secrets.NOMBRE }}`. GitHub los oculta en los logs.

## Protege la rama main

En la configuración del repositorio puedes exigir que el workflow pase y que alguien revise el pull request antes de poder fusionar. Un CI que se puede ignorar no protege nada.

## Merge o rebase

- `git rebase main` en tu rama personal deja una historia lineal y limpia.
- `git merge` para integrar ramas compartidas, sin reescribir lo que otros ya tienen.

Nunca hagas rebase de una rama que otras personas ya usan.

## Errores comunes

- Poner secretos en el YAML.
- Desplegar sin esperar a los tests.
- Probar con SQLite en el CI y usar MySQL en producción: algunas consultas se comportan distinto. Si importa, añade MySQL como `service` del job.

## Practica

Haz el reto de este módulo: un workflow que en cada pull request instale con caché y corra Pint, Larastan y los tests con MySQL como service, que despliegue al fusionar en `main` solo si todo pasó, y con `main` protegida en GitHub.
