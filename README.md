# Sistema NFC — Gestión de puntos por asignatura y curso

Sistema con login de docentes/administrador, asignación de puntos vía NFC o QR,
canje por puntos base, histórico de movimientos y reporte por curso/asignatura.
Pensado para PHP + MySQL (Aiven), desplegado en Vercel (runtime `vercel-php`).

## Arranque rápido
1. Ejecuta `schema.sql` en tu base de Aiven.
2. Configura las variables de entorno en Vercel (ver `INSTRUCCIONES_AIVEN_VERCEL.md`).
3. Sube `certs/ca.pem` al repo (es un certificado público, no una credencial).
4. Despliega y visita `/instalar.php` una vez para crear el admin. Luego bórralo.

## ⚠️ Seguridad
Este proyecto tuvo, en una versión anterior, la contraseña real de la base de
datos escrita directamente en un archivo (`conexion copy.php`), que ya fue
eliminado de esta versión. **Rota la contraseña en el panel de Aiven** si
todavía no lo has hecho: cualquiera con acceso a ese commit del repositorio
pudo verla.

Ver `INSTRUCCIONES_AIVEN_VERCEL.md` para el detalle completo.

## Cambios de la versión de modificaciones (los más recientes; prevalecen sobre lo descrito más abajo)
- **Asignatura = atributo del maestro.** El mantenedor de asignaturas se movió de *Contenido* a *Maestros* (solo administrador). Al crear un maestro se guardan nombre, apellido, usuario, contraseña y asignatura (obligatoria para docentes, opcional para administradores). Un maestro tiene una asignatura.
- **Cursos independientes de la asignatura y compartidos por todos los docentes.** *Contenido* ya no tiene asignaturas: se crea el curso solo con su nombre y el alumno se registra asociado solo al curso. Eliminar un curso queda reservado al administrador (borra alumnos y puntos de todos).
- **Histórico:** nueva columna *Profesor* (nombre y apellido de quien asignó o canjeó los puntos), la columna *Asignatura* ahora es la del profesor que registró el movimiento, y hay un filtro por asignatura. Cada movimiento nuevo guarda `maestro_id` y `asignatura_id`.
- **Metas:** asociadas solo al curso. Cada meta muestra un label «👤 Profesor: …» junto a la descripción; cada profesor puede tener su propia meta por curso y semana, y solo el autor (o el administrador) puede eliminarla.
- **NFC:** en «Asignar puntaje a todo el curso» el combo muestra solo el curso, y al asignar se reproduce un sonido de monedas (`sonidos/monedas.mp3`; puedes reemplazar ese archivo por el audio que prefieras).
- **Reporte:** metas, tablas y gráfico por curso sin asignatura; el gráfico «por asignatura» suma los puntos registrados por los profesores de cada asignatura.
- **Carga masiva y tarjetas:** el CSV ahora es `curso, alumno, nfc_uid` (las columnas `docente_usuario` y `asignatura` se ignoran); los filtros de asignatura/profesor de *Tarjetas* se eliminaron.

**Base de datos:** instalación nueva → `schema.sql`. Base existente → respaldar y ejecutar **una vez** `migracion_v2026_modificaciones.sql`; luego revisar cursos con el mismo nombre que antes estaban repetidos por asignatura (query al final de la migración).

## Novedades de esta versión
- **Identidad Los Alerces**: logo de la escuela en el login (`assets/logo_alerces.png`) y paleta de colores en tonos pastel de bosque.
- **Footer**: "© 2026 by KombaJah" con enlaces a Instagram y WhatsApp. Los enlaces en `footer.php` son un placeholder (`instagram.com/kombajah`, `wa.me/56900000000`): reemplázalos por tu usuario real de Instagram y tu número de WhatsApp.
- **Carga masiva de alumnos** (`carga_masiva.php`): sube un `.csv` con columnas `curso, alumno, nfc_uid` (ver cambios de la versión de modificaciones). Crea automáticamente el curso si no existe. Plantilla descargable en `plantilla_alumnos.php`. No procesa `.xlsx` directamente: hay que guardarlo como CSV desde Excel primero.
- **Tarjetas de alumnos con QR** (`tarjetas.php` + `tarjetas_imprimir.php`): filtra por curso y nombre de alumno. El botón "Imprimir / descargar" abre una hoja lista para Ctrl+P → Guardar como PDF.

## Estructura de despliegue en Vercel (plan Hobby)
El plan Hobby permite máximo 12 Funciones Serverless por deployment. Como `vercel-php`
trata cada `.php` declarado como una función independiente, este proyecto ahora usa
**una sola función** (`index.php`) que actúa como enrutador interno:

- `index.php` (raíz): único punto de entrada. Según la URL solicitada, incluye por
  dentro (`require`, no redirección) el archivo correspondiente desde `/paginas`.
- `/paginas/*.php`: la lógica real de cada página (reporte, nfc, canje, contenido, etc.).
  No son accesibles directamente por URL — `vercel.json` bloquea `/paginas/*` con 404
  para que nadie pueda descargar ese código fuente; solo se leen internamente vía `require`.
- `assets/` y `certs/` siguen siendo estáticos y públicos (logo e certificado CA).

Si en el futuro agregas una página nueva: crea el archivo en `/paginas/tu_pagina.php`
y agrega `'tu_pagina.php'` al arreglo `$permitidas` dentro de `index.php`. No necesitas
tocar `vercel.json` ni te acercas de nuevo al límite de 12 funciones.



##Explicación de la Arquitectura
Entorno Local (Desarrollo): Escribes y editas el código PHP/JS en Visual Studio Code dentro de tu laptop.

Control de Versiones (GitHub): Realizas git push hacia el repositorio kombajah/sistema_puntos_alerces.

Despliegue Continuo (Vercel): Vercel detecta automáticamente cada cambio subido a GitHub y realiza el build/despliegue de la aplicación web.

Persistencia de Datos (Aiven.io): La aplicación desplegada en Vercel se conecta mediante las credenciales de base de datos al servicio MySQL administrado en Aiven.io (mysql-sistema-nfc).

Autenticación Unificada: Todas las plataformas cloud (GitHub, Vercel y Aiven) están centralizadas e integradas bajo la cuenta de correo alexgaratenecunir@gmail.com.
## Novedades de esta versión
- **Gráficos de barras en Reporte**: puntos totales por curso y por asignatura (barras estilo bosque pastel, sin librerías externas).
- **Metas semanales por curso** (`metas.php`): crea una meta (curso + semana + puntaje objetivo), con barra de avance calculada en base a los puntos "ganado" registrados esa semana. `reporte.php` muestra un resumen de las metas de la semana en curso.
- **Opciones de canje** (en `canje.php`): además del canje por puntos base existente, ahora se pueden crear premios/ítems propios (nombre + costo en puntos virtuales), activarlos/desactivarlos o eliminarlos, y canjearlos por alumno. El histórico ahora distingue "Canje por: {ítem}" de "Canje por N pt base".
- **Corrección de seguridad**: en `carga_masiva.php` ya no es posible que un docente (no administrador) registre alumnos a nombre de otro docente escribiendo su usuario en la columna `docente_usuario` del CSV — para no-admins esa columna ahora se ignora y siempre se usa su propio usuario.
- **Corrección menor**: el resaltado del menú activo usaba `PHP_SELF`, que con el enrutador de un solo archivo (`api/index.php`) siempre apuntaba a la misma página; ahora usa la URL real solicitada.
- Se agregó `qr_apoderado` a `schema.sql` (columna que ya usa `reporte_apoderado.php` en la base de datos en producción, pero que no estaba en el script de creación).

Migración para bases ya existentes: `migracion_metas_opciones.sql`.

## Novedades de esta versión
- **Descripción de metas**: al crear una meta semanal ahora se puede escribir qué se espera lograr (ej. "Terminar la unidad 3 con buena participación"). Se muestra tanto en `metas.php` como en el resumen de "Metas de esta semana" de `reporte.php`.
- **Tarjetas de apoderado**: en `tarjetas.php` hay un segundo botón "👪 Imprimir tarjetas de apoderado", que abre una hoja con el mismo diseño de las tarjetas de alumno pero marcada "APODERADO", con el nombre del alumno y el QR del apoderado (el mismo que ya genera Contenido, que al escanearlo abre `reporte_apoderado.php`). Los alumnos sin QR de apoderado generado quedan fuera de ese listado.
- **Asignación masiva de puntos por curso**: en `nfc.php` se agregó una tarjeta "Asignar puntaje a todo el curso" — eliges curso, motivo y puntaje (1 a 3), y se registra ese puntaje para todos los alumnos del curso en una sola acción, con confirmación previa.
- Se documentó en `schema.sql` la tabla `frases_refuerzo` (usada por `nfc.php` para las frases de refuerzo positivo), que ya existía en la base de datos en producción pero no estaba en el script de creación.

Migración para bases ya existentes: `migracion_metas_desc_frases.sql`.
