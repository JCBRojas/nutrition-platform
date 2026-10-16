# Tabla Pagination Skill

Cuando generes o modifiques listados tabulares:

1. Implementa paginación obligatoria de 15 registros por página.
2. Permite al usuario cambiar de página mediante controles Anterior / Siguiente y selector de página.
3. Muestra el total de registros y el rango visible actual (ej: 1-15 de 120).
4. Conserva filtros, búsquedas y ordenamiento al cambiar de página.
5. La paginación debe realizarse en servidor cuando el volumen de datos sea alto; de lo contrario usar paginación local.
6. Utiliza parámetros estándar: `page`, `per_page=15`.
7. Mantén la paginación responsive y accesible.
8. Aplica esta regla a todas las tablas que no tengan paginacion en el proyecto.