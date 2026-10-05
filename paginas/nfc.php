<!DOCTYPE html>
<html lang="es">
<head>
    <title>Ingreso QR</title>
    <?php include 'head.php'; ?>
    <style>
        .card-qr {
            border: none;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .btn-qr-main {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            max-width: 420px;
            height: 90px; /* Tamaño que abarca el espacio equivalente a dos botones */
            margin: 0 auto;
            border: none;
            border-radius: 18px;
            background: linear-gradient(135deg, #2e44d3 0%, #1a2ab0 100%);
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            box-shadow: 0 8px 20px rgba(46, 68, 211, 0.3);
            transition: all 0.25s ease-in-out;
            cursor: pointer;
        }

        .btn-qr-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(46, 68, 211, 0.4);
            background: linear-gradient(135deg, #374ee6 0%, #1f31c4 100%);
            color: #ffffff;
        }

        .btn-qr-main:active {
            transform: translateY(1px);
            box-shadow: 0 4px 12px rgba(46, 68, 211, 0.2);
        }

        .btn-qr-main svg {
            width: 28px;
            height: 28px;
            fill: currentColor;
        }

        #lectorQR {
            border-radius: 16px;
            overflow: hidden;
            border: 2px dashed rgba(46, 68, 211, 0.3);
            background: #f8fafc;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'menu.php'; ?>
<div class="container mt-4">
    <?php if($mensaje) echo "<div class='alert alert-success rounded-3'>". $mensaje ."</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger rounded-3'>". h($error) ."</div>"; ?>

    <div class="card card-qr p-4 text-center" id="estadoNFC">
        <h4 class="fw-bold text-dark mb-1">ESCANEAR CÓDIGO QR</h4>
        <p class="text-muted small mb-4">Presiona el botón para activar la cámara e identificar al alumno.</p>
        
        <p id="estado" class="fw-bold text-primary fs-5 mb-3"></p>

        <div class="d-flex justify-content-center">
            <button class="btn-qr-main" onclick="iniciarQR()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M3 4b1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 1v2h2V5H5zm8-1a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V4zm2 1v2h2V5h-2zM3 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1v-4zm2 1v2h2v-2H5zm10-1a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1h-1a1 1 0 01-1-1v-1zm-4 0a1 1 0 011-1h1a1 1 0 011 1v4a1 1 0 01-1 1h-1a1 1 0 01-1-1v-4zm4 4a1 1 0 011-1h4a1 1 0 011 1v1a1 1 0 01-1 1h-4a1 1 0 01-1-1v-1z"/>
                </svg>
                <span>Escanear código QR</span>
            </button>
        </div>

        <div id="lectorQR" class="mt-4" style="display:none; width:100%; max-width:360px; margin:auto"></div>
    </div>

    <div class="card card-qr p-4 mt-4" id="formPuntos" style="display:none">
        <h5 id="nombreAlumnoDisplay" class="text-primary mb-0"></h5>
        <p id="cursoDisplay" class="text-muted mb-2"></p>
        <div id="saldoBox" class="rounded-3 text-center py-2 mb-3" style="background:#e8f5e9;color:#2e7d32">
            <div class="small fw-bold">Puntos disponibles</div>
            <div id="saldoValor" class="fs-2 fw-bold lh-1">…</div>
            <div id="saldoDetalle" class="small text-muted"></div>
        </div>
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

    <div class="card card-qr p-4 mt-4 mb-5">
        <h5 class="text-primary mb-1">👥 Asignar puntaje a todo el curso</h5>
        <p class="text-muted small">Útil para actividades grupales: todos los alumnos del curso reciben el mismo puntaje por el mismo motivo, de una sola vez.</p>
        <?php if(!$cursosMasivo): ?>
          <p class="text-muted">Primero crea un curso con alumnos en Contenido.</p>
        <?php else: ?>
        <form method="POST" onsubmit="return validarMasivo()">
            <select name="curso_id_masivo" class="form-select mb-3" required>
                <option value="" disabled selected>Elige un curso...</option>
                <?php foreach($cursosMasivo as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?> (<?= (int)$c['total_alumnos'] ?> alumnos)</option>
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
let html5QrcodeScanner = null, ocupado = false;

// --- SINTETIZADOR DE SONIDO MONEDA MARIO BROS ---
function reproducirSonidoMoneda(repeticiones = 1) {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();

    for (let i = 0; i < repeticiones; i++) {
        const tiempoInicio = ctx.currentTime + (i * 0.35);

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

function reproducirSonidoMonedas() {
    const audio = new Audio('sonidos/monedas.mp3');
    audio.volume = 0.9;
    audio.play().catch(() => reproducirSonidoMoneda(3));
}

<?php if ($puntos_asignados > 0): ?>
    document.addEventListener("DOMContentLoaded", () => {
        reproducirSonidoMoneda(<?= $puntos_asignados ?>);
    });
<?php endif; ?>
<?php if ($puntos_masivos_alumnos > 0): ?>
    document.addEventListener("DOMContentLoaded", () => {
        reproducirSonidoMonedas();
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

async function cargarSaldo(id){
    const valor = document.getElementById('saldoValor');
    const detalle = document.getElementById('saldoDetalle');
    valor.innerText = '…'; detalle.innerText = '';
    try {
        const r = await fetch("nfc.php?saldo=" + encodeURIComponent(id), { credentials: 'same-origin' });
        if (!r.ok) throw new Error("HTTP " + r.status);
        const d = await r.json();
        if (d.error) { valor.innerText = '—'; detalle.innerText = d.error; return; }
        valor.innerText = d.saldo;
        detalle.innerText = "Ganados: " + d.ganados + " · Canjeados: " + d.canjeados;
    } catch(e) {
        valor.innerText = '—';
        detalle.innerText = "No se pudo consultar el saldo (" + (e.message || e) + ")";
    }
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
        document.getElementById('cursoDisplay').innerText = "Curso: " + d.curso;
        document.getElementById('alumno_id_input').value = d.id;
        cargarSaldo(d.id);
        return true;
    } catch(e) {
        document.getElementById('estado').innerText = "⚠️ Error al consultar el alumno";
        return false;
    } finally { 
        ocupado = false; 
    }
}

async function iniciarQR(){
    if (typeof Html5QrcodeScanner === 'undefined') { 
        document.getElementById('estado').innerText = "⚠️️ Cargando librería QR..."; 
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