# Mapa Web

## Rutas publicas

- `/`: inicio, busqueda, artistas populares, albumes populares y canciones destacadas.
- `/artists/:id`: detalle de artista y sus albumes.
- `/albums/:id`: detalle de album y canciones.
- `/login`: inicio de sesion y verificacion 2FA cuando la cuenta lo requiere.
- `/register`: registro de nueva cuenta.

## Rutas privadas

- `/favorites`: canciones favoritas del usuario.
- `/playlists`: gestion de playlists, detalle modal y edicion.
- `/profile`: perfil de usuario, favoritos y artistas seguidos.

## Rutas de administracion

- `/admin`: panel restringido a administradores.
- Pestana Stats: indicadores, actividad, errores y rankings.
- Pestana Users: listado, edicion, estado, rol, 2FA y biblioteca.
- Pestana Logs: actividad y errores registrados por la API.

## Flujo principal

1. El usuario entra en Inicio.
2. Busca o navega por artistas y albumes.
3. Reproduce previews desde las filas de canciones.
4. Guarda canciones en favoritas o en playlists.
5. Consulta su biblioteca desde Favoritas, Playlists o Perfil.
6. Si es administrador, gestiona usuarios desde Admin.
