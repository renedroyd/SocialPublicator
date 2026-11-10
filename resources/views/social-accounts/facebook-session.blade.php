<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sesión de Facebook</title>
    <style>
        body{font-family:Inter,system-ui,sans-serif;background:#0b0b0b;color:#f4f0e6;margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}
        main{width:min(100%,620px);background:#171717;border:1px solid #4c4020;border-radius:18px;padding:28px}
        h1{font-family:Georgia,serif;color:#e5c76b;font-weight:500;text-align:center}
        p{color:#c9c4b7;line-height:1.55}
        #status{font-weight:600;color:#e5c76b;text-align:center}
        a{color:#e5c76b}
        li{margin:10px 0;color:#c9c4b7}
    </style>
</head>
<body>
<main>
    <h1>Sesión de Facebook para grupos</h1>
    <p>Cuenta: <strong>{{ $account->display_name }}</strong></p>
    <p id="status">Esperando al agente WBot…</p>
    <p>La autenticación de grupos se realiza en el navegador persistente del equipo donde se ejecuta WBot.</p>
    <ol>
        <li>Comprueba que WBot esté iniciado y que FACEBOOK_ENABLED=true.</li>
        <li>Si Facebook solicita iniciar sesión, completa manualmente el acceso en la ventana del navegador del equipo de WBot.</li>
        <li>Cuando WBot detecte la sesión activa, el estado cambiará aquí.</li>
    </ol>
    <p>SocialPublicator no recibe ni almacena cookies, contraseñas o perfiles Chromium. No cierres ni elimines la sesión local.</p>
    <p id="detail"></p>
    <p><a href="{{ route('filament.admin.resources.social-accounts.index') }}">Volver a cuentas sociales</a></p>
</main>
<script>
(() => {
    const endpoint = @json($statusUrl);
    const status = document.getElementById('status');
    const detail = document.getElementById('detail');
    let stopped = false;

    async function refresh() {
        if (stopped) return;
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('No se pudo consultar el estado de la sesión.');
            const data = await response.json();

            const labels = {
                connected: 'Sesión de Facebook conectada.',
                pending: 'Esperando autenticación en el navegador de WBot.',
                disconnected: 'Sesión de Facebook desconectada.',
                error: 'WBot informó un error de sesión.',
            };
            status.textContent = labels[data.status] || 'Estado pendiente.';
            detail.textContent = data.last_error_message || (data.last_verified_at
                ? 'Última verificación: ' + data.last_verified_at
                : 'El estado se actualiza automáticamente.');
        } catch (error) {
            status.textContent = error.message;
        }
        window.setTimeout(refresh, 5000);
    }

    refresh();
})();
</script>
</body>
</html>
