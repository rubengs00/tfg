# MusicHub

Clon funcional inspirado en Spotify con backend Laravel 13 y frontend Angular 21.

## Estructura

- `anteproyecto/`: documento original y wireframes.
- `backend/`: API REST Laravel 13, SQLite por defecto, auth con segundo factor, playlists, favoritos, seguimiento, admin y logs.
- `frontend/`: Angular 21 standalone con routing, signals, servicios HTTP y UI responsive.

## Arranque local

Backend:

```powershell
cd backend
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Frontend:

```powershell
cd frontend
npm.cmd run start -- --host 127.0.0.1 --port 4300
```

Abre `http://127.0.0.1:4300`.

## Usuarios demo

- Usuario: `demo@musichub.local` / `password`
- Admin: `admin@musichub.local` / `password`

El login pide 2FA. En entorno local la API devuelve `debugCode`, y el frontend lo rellena automaticamente.

## Spotify API

El catalogo usa datos demo si no hay credenciales. Si configuras Spotify, `php artisan migrate:fresh --seed` intentara poblar artistas, albumes y canciones reales en SQLite, y la busqueda tambien ira sincronizando resultados locales con IDs validos para favoritos, follows y playlists.

```env
SPOTIFY_CLIENT_ID=...
SPOTIFY_CLIENT_SECRET=...
SPOTIFY_MARKET=ES
SPOTIFY_SEED_ARTISTS=Rosalia,Quevedo,Bad Bunny,Dua Lipa,The Weeknd
```

Tambien puedes refrescar el catalogo sin resetear la base:

```powershell
cd backend
php artisan spotify:sync-catalog
php artisan spotify:sync-catalog --artist="Rosalia" --artist="The Weeknd" --albums=2
```

## Verificacion

```powershell
cd backend
php artisan test

cd ..\frontend
npm.cmd run build
```
php artisan config:clear

