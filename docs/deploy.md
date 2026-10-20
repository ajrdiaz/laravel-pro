# Desplegar Ruta Laravel en un VPS

La app corre en **un solo contenedor** (FrankenPHP: PHP + el servidor web Caddy) con un volumen
para la base de datos SQLite. Caddy obtiene y renueva solo el certificado HTTPS, así que no
hace falta nginx, certbot ni nada más.

Lo que queda decidido en esta configuración:

- **Registro cerrado.** Nadie puede crear una cuenta desde la página de login; la tuya la
  creas por consola en el servidor.
- **Sin dominio propio.** Se usa un nombre gratuito basado en la IP del servidor
  (`203-0-113-10.sslip.io`). HTTPS es obligatorio para la PWA (instalarla y leer lecciones sin
  conexión), y Let's Encrypt no emite certificados para una IP suelta, por eso el nombre.

## 1. Lo que necesitas

- Un VPS con **Ubuntu 24.04**, IP pública y **2 GB de RAM** (construir la imagen es lo que más
  memoria pide; con 1 GB agrega 2 GB de swap, ver abajo).
- Acceso por SSH.
- Los puertos **80** y **443** (TCP) y **443** (UDP, HTTP/3) abiertos hacia internet. Si el
  proveedor tiene un firewall en su panel, ábrelos ahí también.

## 2. Preparar el servidor

```bash
# Firewall: SSH y la web, nada más.
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp
sudo ufw enable
```

Instala Docker Engine con el plugin de Compose siguiendo la guía oficial
(<https://docs.docker.com/engine/install/ubuntu/>) y comprueba que funciona:

```bash
docker compose version
```

Solo si el servidor tiene 1 GB de RAM:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

## 3. Instalar la app

```bash
git clone https://github.com/ajrdiaz/laravel-pro.git
cd laravel-pro
cp docker/production.env.example .env.production
chmod 600 .env.production
```

Abre `.env.production` (`nano .env.production`) y completa las dos líneas marcadas
`REQUIRED`:

1. **`APP_KEY`**: se crea una vez y se guarda para siempre (cambiarla cierra todas las
   sesiones). Genérala con:

    ```bash
    docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
    ```

    La primera vez construye la imagen (unos minutos). Copia el resultado
    (`base64:...`) después de `APP_KEY=`.

2. **`SERVER_NAME`** y **`APP_URL`**: la dirección que vas a escribir en el navegador. Para
   una IP `203.0.113.10`, cambia los puntos por guiones:

    ```
    SERVER_NAME=203-0-113-10.sslip.io
    APP_URL=https://203-0-113-10.sslip.io
    ```

Arranca:

```bash
docker compose up -d --build
docker compose ps        # espera a que diga "healthy"
```

La primera vez, el contenedor crea la base de datos, carga la ruta, las lecciones, los stacks y
los proyectos, y pide el certificado HTTPS (tarda unos segundos; si el DNS o los puertos
están mal, falla aquí, ver "Si algo falla").

Crea tu usuario (te pregunta nombre, correo y contraseña):

```bash
docker compose exec app php artisan user:create
```

Abre `https://203-0-113-10.sslip.io` (con tu IP) e inicia sesión. En el celular, el menú del
navegador ofrece **Instalar app**; las lecciones que abras quedan disponibles sin conexión.

## 4. Copias de seguridad

La base de datos es un archivo SQLite en el volumen `data`. El comando `database:backup` hace
una copia consistente (aunque la app esté en uso) en `/data/backups` dentro del volumen y
conserva las últimas 14.

Prográmalo en el servidor, todos los días a las 3:00 (`crontab -e`, con la ruta real de la
carpeta):

```
0 3 * * * cd /home/usuario/laravel-pro && docker compose exec -T app php artisan database:backup >> /home/usuario/ruta-backup.log 2>&1
```

**Esas copias están en el mismo servidor que la base de datos**: no te protegen si pierdes el
VPS. De vez en cuando baja una a tu PC (se ejecuta en tu PC, no en el servidor):

```bash
ssh usuario@IP "cd laravel-pro && docker compose exec -T app tar -C /data -cf - backups" | tar -xf -
```

### Restaurar una copia

```bash
cd laravel-pro
docker compose exec app ls /data/backups        # elige el archivo
docker compose stop app
docker compose run --rm --no-deps --user root --entrypoint sh app -c \
  "cp /data/backups/ruta-laravel-20261019-030000.sqlite /data/database.sqlite \
   && chown app:app /data/database.sqlite \
   && rm -f /data/database.sqlite-wal /data/database.sqlite-shm"
docker compose up -d
```

(Cambia el nombre por el del archivo que elegiste. Para restaurar una copia que bajaste a tu
PC, súbela primero con `docker compose cp copia.sqlite app:/data/backups/` y usa su nombre.)

## 5. Actualizar a una versión nueva

```bash
cd laravel-pro
docker compose exec -T app php artisan database:backup   # por si acaso
git pull
docker compose up -d --build
docker image prune -f                                    # borra las imágenes viejas
```

Al arrancar, el contenedor corre las migraciones y vuelve a cargar el contenido (ruta,
lecciones, stacks, proyectos), así que lo nuevo llega con la imagen. Tus notas, progreso y
resultados no se tocan. Hay un corte de unos segundos mientras se reemplaza el contenedor.

La PWA se actualiza sola: el navegador pide `sw.js` en cada visita (el servidor lo marca como
`no-cache`) y las lecciones guardadas se renuevan al volver a abrirlas.

## 6. Comandos útiles

```bash
docker compose ps                          # estado y salud
docker compose logs -f app                 # registros en vivo (también los errores de Laravel)
docker compose restart app                 # reiniciar
docker compose exec app php artisan about  # configuración que ve la app
docker compose down                        # apagar (los datos siguen en el volumen)
```

`docker compose down -v` **borra el volumen**: la base de datos y las copias. No lo uses a
menos que quieras empezar de cero.

Si olvidas la contraseña (el correo de recuperación no está configurado a propósito):

```bash
docker compose exec app php artisan tinker
> App\Models\User::where('email', 'tu@correo.com')->first()->update(['password' => 'la-nueva']);
```

## 7. Si algo falla

| Síntoma                                        | Qué mirar                                                                                                                                                                                                   |
| ---------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_KEY is empty` al arrancar                 | Falta `APP_KEY` en `.env.production`, ver paso 3.                                                                                                                                                           |
| El navegador dice que la conexión no es segura | `docker compose logs app \| grep -i -E "tls\|acme\|certificate"`. Casi siempre son los puertos 80/443 cerrados (firewall del proveedor), o `SERVER_NAME` mal escrito (debe llevar guiones, sin `https://`). |
| `docker compose ps` no llega a `healthy`       | `docker compose logs app` y busca el primer error (una migración, una variable).                                                                                                                            |
| Entras pero la sesión se cierra sola           | `APP_URL` no coincide con la dirección que usas, o `SESSION_SECURE_COOKIE=true` y entras por `http://`.                                                                                                     |
| Cambié `.env.production` y no pasa nada        | Las variables se leen al crear el contenedor: `docker compose up -d` (sin `restart`) lo vuelve a crear.                                                                                                     |

## 8. Notas

- **sslip.io es un servicio gratuito de terceros** (resuelve `203-0-113-10.sslip.io` a
  `203.0.113.10`). Si un día no está disponible o Let's Encrypt limita los certificados de ese
  dominio compartido, la salida es un dominio propio: apunta un registro A a la IP, pon ese
  nombre en `SERVER_NAME` y `APP_URL`, y `docker compose up -d`. No hay nada más que cambiar.
- Si cambias de servidor, cambia la IP: el nombre cambia y hay que actualizar `SERVER_NAME` y
  `APP_URL`. La PWA instalada con el nombre anterior hay que volver a instalarla.
- Docker publica los puertos saltándose `ufw`. Aquí solo se publican 80 y 443, que son los
  que quieres abiertos; no agregues otros servicios con `ports:` sin pensarlo.
- El contenedor corre como un usuario normal (no root) y la base de datos y los certificados
  están en `/data`.

## Probar la imagen en tu PC

`docker/smoke-test.sh` construye la imagen, la arranca con un volumen vacío y comprueba lo que
un despliegue tiene que hacer bien: que arranca y queda sano, el registro cerrado, las
cabeceras y la caché, que se puede entrar, las copias, que los datos sobreviven a reemplazar
el contenedor y que una copia se restaura. Usa un puerto libre que elige Docker, así que no
choca con otros proyectos.

```bash
bash docker/smoke-test.sh                  # construye y prueba
SKIP_BUILD=1 bash docker/smoke-test.sh     # prueba la imagen ya construida
```

GitHub Actions lo ejecuta en cada push y pull request (`.github/workflows/docker.yml`).
