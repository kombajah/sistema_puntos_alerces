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

## Novedades de esta versión
- **Identidad Los Alerces**: logo de la escuela en el login (`assets/logo_alerces.png`) y paleta de colores en tonos pastel de bosque.
- **Footer**: "© 2026 by KombaJah" con enlaces a Instagram y WhatsApp. Los enlaces en `footer.php` son un placeholder (`instagram.com/kombajah`, `wa.me/56900000000`): reemplázalos por tu usuario real de Instagram y tu número de WhatsApp.
- **Carga masiva de alumnos** (`carga_masiva.php`, solo admin): sube un `.csv` con columnas `docente_usuario, asignatura, curso, alumno, nfc_uid`. Crea automáticamente la asignatura/curso si no existen para ese docente. Plantilla descargable en `plantilla_alumnos.php`. No procesa `.xlsx` directamente: hay que guardarlo como CSV desde Excel primero.
- **Tarjetas de alumnos con QR** (`tarjetas.php` + `tarjetas_imprimir.php`): filtra por curso, asignatura y nombre de alumno (docente ve solo lo suyo); el admin además filtra por profesor. El botón "Imprimir / descargar" abre una hoja lista para Ctrl+P → Guardar como PDF.

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
