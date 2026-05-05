# Patrones de Diseno Web

## Aplicacion shell

La aplicacion usa una estructura persistente con sidebar, topbar, contenido central y reproductor fijo. Esto permite mantener navegacion y reproduccion sin perder contexto.

## Dashboard operativo

El panel de administrador se organiza con una navegacion lateral interna, cabecera contextual, tablas y modales. Es un patron orientado a gestion repetitiva y lectura rapida.

## Biblioteca musical

Las vistas de albumes, artistas y playlists usan tarjetas y filas densas. El objetivo es priorizar contenido real, portada, nombre, autor y acciones.

## Modales de edicion

Las acciones que editan informacion sensible se abren en modales. Asi se mantiene el contexto de la pagina y se reduce navegacion innecesaria.

## Feedback de estado

Las operaciones administrativas muestran estados como guardando, guardado y error. No se usan confirmaciones nativas del navegador.

## Mobile first correctivo

Aunque la composicion base parte de escritorio, las reglas responsive simplifican navegacion, reducen columnas y evitan solapamientos en movil.
