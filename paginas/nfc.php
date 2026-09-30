<?php
require_once 'conexion.php'; 
requiere_login();

$mensaje = ''; 
$error = '';
$puntos_asignados = 0; // Variable para controlar las repeticiones del sonido en JS

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['asignar_puntos'])) {
    $alumno = (int)($_POST['alumno_id'] ?? 0);
    $cat    = (int)($_POST['categoria_id'] ?? 0);
    $pts    = (int)($_POST['puntos'] ?? 0);

    // Validar si el alumno pertenece a un curso del docente (salvo admin)
    $s = $conn->prepare("SELECT c.docente_id FROM alumnos a JOIN cursos c ON c.id=a.curso_id WHERE a.id=?");
    $s->bind_param("i", $alumno); 
    $s->execute(); 
    $r = $s->get_result()->fetch_assoc();

    if (!$r || (!es_admin() && (int)$r['docente_id'] !== docente_id())) {
        $error = "Alumno no válido.";
    } elseif (!in_array($pts, [1, 2, 3], true)) {
        $error = "Puntaje inválido.";
    } else {
        // Registrar puntos en la base de datos
        $s = $conn->prepare("INSERT INTO registro_puntos (alumno_id, categoria_id, puntos) SELECT a.id, c.id, ? FROM alumnos a, categorias c WHERE a.id=? AND c.id=?");
        $s->bind_param("iii", $pts, $alumno, $cat); 
        $s->execute();

        if ($s->affected_rows > 0) {
            $puntos_asignados = $pts; // Guardar los puntos para el audio
            
            // Consultar una frase de refuerzo positivo aleatoria de la base de datos
            $refuerzo = '';
            $s_frase = $conn->prepare("SELECT frase FROM frases_refuerzo WHERE categoria_id = ? ORDER BY RAND() LIMIT 1");
            $s_frase->bind_param("i", $cat);
            $s_frase->execute();
            $res_frase = $s_frase->get_result()->fetch_assoc();

            if ($res_frase && !empty($res_frase['frase'])) {
                $refuerzo = "<br><br>🌟 <em>\"" . h($res_frase['frase']) . "\"</em>";
            }

            $mensaje = "¡Puntos asignados correctamente!" . $refuerzo;
        } else {
            $error = "Motivo no válido.";
        }
    }
}

$puntos_masivos_alumnos = 0; // para el sonido: cuántos alumnos recibieron puntos en la asignación masiva

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['asignar_masivo'])) {
    $curso = (int)($_POST['curso_id_masivo'] ?? 0);
    $cat   = (int)($_POST['categoria_id_masivo'] ?? 0);
    $pts   = (int)($_POST['puntos_masivo'] ?? 0);

    $s = $conn->prepare("SELECT docente_id FROM cursos WHERE id=?");
    $s->bind_param("i", $curso); $s->execute();
    $c = $s->get_result()->fetch_assoc();

    if (!$c || (!es_admin() && (int)$c['docente_id'] !== docente_id())) {
        $error = "Curso no válido.";
    } elseif (!in_array($pts, [1, 2, 3], true)) {
        $error = "Puntaje inválido.";
    } else {
        $s = $conn->prepare("SELECT id FROM categorias WHERE id=?");
        $s->bind_param("i", $cat); $s->execute();
        if (!$s->get_result()->fetch_assoc()) {
            $error = "Motivo no válido.";
        } else {
            $s = $conn->prepare(
                "INSERT INTO registro_puntos (alumno_id, categoria_id, puntos)
                 SELECT a.id, ?, ? FROM alumnos a WHERE a.curso_id = ?"
            );
            $s->bind_param("iii", $cat, $pts, $curso);
            $s->execute();
            $puntos_masivos_alumnos = $s->affected_rows;
            if ($puntos_masivos_alumnos > 0) {
                $mensaje = "¡Se asignaron $pts pt(s) a los $puntos_masivos_alumnos alumnos del curso!";
            } else {
                $error = "Ese curso no tiene alumnos registrados.";
            }
        }
    }
}

// Cursos del docente (o todos, si es admin), para el formulario de asignación masiva
$types=''; $vals=[];
$sqlCursosMasivo = "SELECT c.id, c.nombre, ag.nombre asignatura, m.usuario docente,
  (SELECT COUNT(*) FROM alumnos WHERE curso_id=c.id) total_alumnos
  FROM cursos c JOIN asignaturas ag ON ag.id=c.asignatura_id JOIN maestros m ON m.id=c.docente_id WHERE 1=1";
filtro_docente($sqlCursosMasivo,$types,$vals,'c');
$s=$conn->prepare($sqlCursosMasivo." ORDER BY c.nombre"); if($vals) $s->bind_param($types,...$vals); $s->execute();
$cursosMasivo = $s->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener categorías usando MySQLi
$cats = $conn->query("SELECT * FROM categorias");
$catsMasivo = $conn->query("SELECT * FROM categorias");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Ingreso NFC</title>
    <?php include 'head.php'; ?>
</head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-2">
    <?php if($mensaje) echo "<div class='alert alert-success'>". $mensaje ."</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>". h($error) ."</div>"; ?>

    <div class="card p-3 shadow-sm text-center" id="estadoNFC">
        <h4>ACERCAR SU TARJETA</h4>
        <p class="text-muted">Presiona un botón y pide al alumno que acerque su tarjeta o muestre su QR.</p>
        <p id="estado" class="fw-bold text-primary fs-5"></p>
        <button class="btn btn-dark w-100 rounded-pill mb-2" onclick="iniciarEscaneo()">🛜 Escanear tarjeta NFC</button>
        <button class="btn btn-outline-dark w-100 rounded-pill" onclick="iniciarQR()">📷 Escanear código QR</button>
        <div id="lectorQR" class="mt-3" style="display:none; width:100%; max-width:320px; margin:auto"></div>
    </div>

    <div class="card p-4 mt-3 shadow-sm" id="formPuntos" style="display:none">
        <h5 id="nombreAlumnoDisplay" class="text-primary mb-0"></h5>
        <p id="cursoDisplay" class="text-muted mb-3"></p>
        <form method="POST" onsubmit="return validar()">
            <input type="hidden" name="alumno_id" id="alumno_id_input">
            <label class="form-label">Motivo</label>
            <select name="categoria_id" class="form-select mb-3" required>
                <option value="" disabled selected>Elige un motivo...</option>
                <?php while($r = $cats->fetch_assoc()): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= h($r['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
            <label class="form-label">Cantidad de puntos:</label>
            <div class="d-flex justify-content-between mb-4">
                <?php foreach([1,2,3] as $n): ?>
                    <button type="button" class="btn-punto" onclick="seleccionarPunto(<?= $n ?>, this)"><?= $n ?> pt<?= $n>1?'s':'' ?></button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="puntos" id="input_puntos">
            <button type="submit" name="asignar_puntos" class="btn btn-primary w-100 rounded-pill fs-5" style="background:#2e44d3">Confirmar Asignación</button>
        </form>
    </div>

    <div class="card p-4 mt-3 shadow-sm">
        <h5 class="text-primary mb-1">👥 Asignar puntaje a todo el curso</h5>
        <p class="text-muted small">Útil para actividades grupales: todos los alumnos del curso reciben el mismo puntaje por el mismo motivo, de una sola vez.</p>
        <?php if(!$cursosMasivo): ?>
          <p class="text-muted">Primero crea un curso con alumnos en Contenido.</p>
        <?php else: ?>
        <form method="POST" onsubmit="return validarMasivo()">
            <select name="curso_id_masivo" class="form-select mb-3" required>
                <option value="" disabled selected>Elige un curso...</option>
                <?php foreach($cursosMasivo as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?> · <?= h($c['asignatura']) ?><?= es_admin()?' — '.h($c['docente']):'' ?> (<?= (int)$c['total_alumnos'] ?> alumnos)</option>
                <?php endforeach; ?>
            </select>
            <select name="categoria_id_masivo" class="form-select mb-3" required>
                <option value="" disabled selected>Elige un motivo...</option>
                <?php while($r = $catsMasivo->fetch_assoc()): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= h($r['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
            <label class="form-label">Cantidad de puntos:</label>
            <div class="d-flex justify-content-between mb-4">
                <?php foreach([1,2,3] as $n): ?>
                    <button type="button" class="btn-punto" onclick="seleccionarPuntoMasivo(<?= $n ?>, this)"><?= $n ?> pt<?= $n>1?'s':'' ?></button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="puntos_masivo" id="input_puntos_masivo">
            <button type="submit" name="asignar_masivo" class="btn w-100 rounded-pill fs-5 text-white" style="background:#d99a5b" onclick="return confirm('¿Asignar este puntaje a TODOS los alumnos del curso elegido?')">Asignar a todo el curso</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
let ndef = null, html5QrcodeScanner = null, ocupado = false;

// --- SINTETIZADOR DE SONIDO MONEDA MARIO BROS ---
function reproducirSonidoMoneda(repeticiones = 1) {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();

    for (let i = 0; i < repeticiones; i++) {
        const tiempoInicio = ctx.currentTime + (i * 0.35);

        // Nota 1: B4 (987.77 Hz)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'square';
        osc1.frequency.setValueAtTime(987.77, tiempoInicio);
        gain1.gain.setValueAtTime(0.1, tiempoInicio);
        gain1.gain.exponentialRampToValueAtTime(0.01, tiempoInicio + 0.08);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(tiempoInicio);
        osc1.stop(tiempoInicio + 0.08);

        // Nota 2: E5 (1318.51 Hz)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'square';
        osc2.frequency.setValueAtTime(1318.51, tiempoInicio + 0.08);
        gain2.gain.setValueAtTime(0.1, tiempoInicio + 0.08);
        gain2.gain.exponentialRampToValueAtTime(0.001, tiempoInicio + 0.38);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(tiempoInicio + 0.08);
        osc2.stop(tiempoInicio + 0.38);
    }
}

<?php if ($puntos_asignados > 0): ?>
    document.addEventListener("DOMContentLoaded", () => {
        reproducirSonidoMoneda(<?= $puntos_asignados ?>);
    });
<?php endif; ?>
<?php if ($puntos_masivos_alumnos > 0): ?>
    document.addEventListener("DOMContentLoaded", () => {
        reproducirSonidoMoneda(1);
    });
<?php endif; ?>

function seleccionarPunto(v, el){
    el.closest('form').querySelectorAll('.btn-punto').forEach(b => b.classList.remove('active'));
    el.classList.add('active'); 
    document.getElementById('input_puntos').value = v;
}

function seleccionarPuntoMasivo(v, el){
    el.closest('form').querySelectorAll('.btn-punto').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('input_puntos_masivo').value = v;
}

function validar(){ 
    if(!document.getElementById('input_puntos').value){ 
        alert('Selecciona 1, 2 o 3 puntos'); 
        return false; 
    } 
    return true; 
}

function validarMasivo(){
    if(!document.getElementById('input_puntos_masivo').value){
        alert('Selecciona 1, 2 o 3 puntos');
        return false;
    }
    return true;
}

async function cargar(codigo){
    if (ocupado || !codigo) return false; 
    ocupado = true;
    try {
        const r = await fetch("buscar_alumno.php?uid=" + encodeURIComponent(codigo));
        const d = await r.json();
        if (d.error) { 
            document.getElementById('estado').innerText = "⚠️ " + d.error; 
            return false; 
        }
        
        await detenerQR();

        document.getElementById('estadoNFC').style.display = "none";
        document.getElementById('formPuntos').style.display = "block";
        document.getElementById('nombreAlumnoDisplay').innerText = d.nombre;
        document.getElementById('cursoDisplay').innerText = "Curso: " + d.curso + " · " + d.asignatura;
        document.getElementById('alumno_id_input').value = d.id;
        return true;
    } catch(e) {
        document.getElementById('estado').innerText = "⚠️ Error al consultar el alumno";
        return false;
    } finally { 
        ocupado = false; 
    }
}

// Extrae el UID o los datos NDEF grabados en la tarjeta
function extraerCodigoNFC(event) {
    if (event.serialNumber) {
        return event.serialNumber.replace(/:/g, '').toUpperCase();
    }
    
    if (event.message && event.message.records) {
        for (const record of event.message.records) {
            if (record.recordType === "text") {
                const textDecoder = new TextDecoder(record.encoding || "utf-8");
                return textDecoder.decode(record.data).trim();
            } else if (record.recordType === "url") {
                const textDecoder = new TextDecoder();
                return textDecoder.decode(record.data).trim();
            }
        }
    }
    return null;
}

async function iniciarEscaneo(){
    await detenerQR();
    const elemEstado = document.getElementById('estado');

    if (!("NDEFReader" in window)) { 
        elemEstado.innerText = "⚠️ Web NFC requiere Chrome en Android y HTTPS. Usa el lector QR."; 
        return; 
    }
    try {
        if (!ndef) { 
            ndef = new NDEFReader(); 
            ndef.addEventListener("reading", (event) => {
                const codigo = extraerCodigoNFC(event);
                if (codigo) {
                    elemEstado.innerText = "⏳ Procesando tarjeta...";
                    cargar(codigo);
                } else {
                    elemEstado.innerText = "⚠️ Tarjeta no reconocida o sin datos válidos.";
                }
            });
            ndef.addEventListener("readingerror", () => {
                elemEstado.innerText = "⚠️ Error al leer la tarjeta. Inténtalo de nuevo.";
            });
        }
        await ndef.scan();
        elemEstado.innerText = "🛜 Escaneando... Acerca la tarjeta ahora al teléfono.";
    } catch(e){ 
        elemEstado.innerText = "⚠️ Error al iniciar NFC: " + (e.message || e); 
    }
}

async function iniciarQR(){
    if (typeof Html5QrcodeScanner === 'undefined') { 
        document.getElementById('estado').innerText = "⚠️ Cargando librería QR..."; 
        return; 
    }

    await detenerQR();

    const box = document.getElementById('lectorQR');
    box.style.display = 'block';

    try {
        html5QrcodeScanner = new Html5QrcodeScanner(
            "lectorQR", 
            { 
                fps: 10, 
                qrbox: { width: 220, height: 220 },
                rememberLastUsedCamera: true,
                supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
            }, 
            false
        );

        html5QrcodeScanner.render(
            async (decodedText) => {
                await cargar(decodedText);
            },
            (error) => {}
        );
    } catch(e) {
        box.style.display = 'none';
        document.getElementById('estado').innerText = "⚠️ Error al iniciar escáner QR";
    }
}

async function detenerQR() {
    const box = document.getElementById('lectorQR');
    if (html5QrcodeScanner) {
        try {
            await html5QrcodeScanner.clear();
        } catch(e) {}
        html5QrcodeScanner = null;
    }
    box.style.display = 'none';
}
</script>
<?php include 'footer.php'; ?>
</body>
</html>