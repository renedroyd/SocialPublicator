<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conectar WhatsApp</title>
    <style>
        body{font-family:Inter,system-ui,sans-serif;background:#0b0b0b;color:#f4f0e6;margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}
        main{width:min(100%,560px);background:#171717;border:1px solid #4c4020;border-radius:18px;padding:28px;text-align:center}
        h1{font-family:Georgia,serif;color:#e5c76b;font-weight:500}
        p{color:#c9c4b7;line-height:1.5}
        #qr{width:min(100%,320px);aspect-ratio:1;object-fit:contain;background:white;border-radius:12px;padding:12px;display:none;margin:20px auto}
        #status{font-weight:600;color:#e5c76b}
        a{color:#e5c76b}
    </style>
</head>
<body>
<main>
    <h1>Conectar WhatsApp</h1>
    <p>Cuenta: <strong>{{ $account->display_name }}</strong></p>
    <p id="status">Esperando al agente WBot…</p>
    <img id="qr" alt="Código QR temporal para vincular WhatsApp">
    <p id="hint">Cuando WBot genere el código QR, aparecerá aquí. Escanéalo desde WhatsApp → Dispositivos vinculados.</p>
    <p><a href="{{ route('filament.admin.resources.social-accounts.index') }}">Volver a cuentas sociales</a></p>
</main>
<script>
(() => {
    const endpoint = @json($qrDataUrl);
    const status = document.getElementById('status');
    const qr = document.getElementById('qr');
    const hint = document.getElementById('hint');
    let stopped = false;

    async function refresh() {
        if (stopped) return;
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('No se pudo consultar el estado de la cuenta.');
            const data = await response.json();

            if (data.status === 'connected') {
                status.textContent = 'WhatsApp conectado correctamente.';
                qr.style.display = 'none';
                hint.textContent = data.display_name || 'La sesión está activa.';
                stopped = true;
                return;
            }

            if (data.status === 'error') {
                status.textContent = 'WBot informó un error de conexión.';
                hint.textContent = data.last_error_message || 'Revisa el agente WBot.';
            } else if (data.qr) {
                status.textContent = 'Escanea el código QR antes de que expire.';
                qr.src = data.qr.startsWith('data:') ? data.qr : 'data:image/png;base64,' + data.qr;
                qr.style.display = 'block';
                hint.textContent = 'El código se renueva automáticamente si expira.';
            } else {
                status.textContent = data.status === 'disconnected'
                    ? 'WhatsApp está desconectado.'
                    : 'Esperando al agente WBot y su código QR…';
                qr.style.display = 'none';
            }
        } catch (error) {
            status.textContent = error.message;
        }
        window.setTimeout(refresh, 2500);
    }

    refresh();
})();
</script>
</body>
</html>
