# Publicación en páginas de Facebook mediante Meta Graph API

## Alcance

Los perfiles de Facebook se conectan mediante OAuth de Meta. Las páginas que el perfil autoriza se registran como cuentas hijas y como destinos publicables del mismo workspace.

- **Páginas:** publicación oficial del lado de Laravel mediante Meta Graph API y Page access token cifrado.
- **Grupos:** se mantienen en WBot/Puppeteer con la sesión persistente actual; no se envían por Graph API.
- **WhatsApp:** se mantiene en WBot/WPPConnect.
- Los tokens de página no se envían a WBot ni se incluyen en la respuesta del feed de publicaciones.

## Requisitos de Meta

Configurar en SocialPublicator:

```dotenv
META_APP_ID=
META_APP_SECRET=
META_REDIRECT_URI=
META_GRAPH_VERSION=v26.0
```

La aplicación solicita `pages_show_list`, `pages_read_engagement` y `pages_manage_posts`. La posibilidad de conceder estos permisos depende del modo de la app, roles de prueba, revisión y acceso aprobados por Meta. El callback debe estar registrado exactamente en Meta for Developers.

El propietario o miembro autorizado conecta un perfil desde **Cuentas sociales → Cuentas → Conectar con Meta**. Las páginas que Meta devuelve se guardan como cuentas hijas y aparecen en el selector **Páginas de Facebook (API oficial)** del formulario de publicaciones.

## Flujo de publicación

1. El usuario selecciona una o varias páginas oficiales como destino de una publicación.
2. El scheduler ejecuta `php artisan meta-pages:dispatch` cada minuto.
3. El dispatcher valida fecha/hora/recurrencia, destino activo, cuenta conectada, token vigente y permisos actuales del creador.
4. Se crea una `PublicationExecution` por ocurrencia y página, con una clave de destino única.
5. Un job de Laravel publica la imagen y el texto mediante `/{page-id}/photos`, o solo el texto mediante `/{page-id}/feed`.
6. El ID externo devuelto por Meta se conserva en la ejecución.

La publicación oficial en páginas no utiliza la sesión de navegador de WBot. Los grupos de Facebook continúan usando el flujo de navegador ya existente.

## Estados y seguridad ante duplicados

- Una respuesta aceptada con ID externo marca la ejecución como `succeeded`.
- Un rechazo explícito de Meta marca la ejecución como `failed`.
- Una desconexión o timeout durante el envío, o un error HTTP 5xx, marca la ejecución como `unknown`. No se reintenta automáticamente porque Meta podría haber recibido la publicación. Verifica la página antes de cualquier reintento manual.
- Las ejecuciones `pending`, `running`, `succeeded`, `failed` o `unknown` existentes no generan un segundo envío para la misma ocurrencia y página.
- El token de página se obtiene desde el cast cifrado de Laravel y nunca se registra en logs.
- Se envía `appsecret_proof` en cada solicitud a Graph API.

## Operación

En producción deben estar activos:

- Scheduler de Laravel (cron que ejecute `php artisan schedule:run` cada minuto).
- Un worker persistente de Laravel Queue.
- HTTPS y una URL pública accesible para las imágenes del disco `public`.
- Variables META_APP_ID, META_APP_SECRET y la versión Graph aprobada.
- Supervisión de `failed_jobs` y de ejecuciones `unknown`.

Para desactivar temporalmente esta vía, pausa las publicaciones que tengan páginas oficiales seleccionadas o retira el acceso de publicación en el workspace. No desconectes ni elimines las sesiones de WBot para pausar publicaciones de páginas.

## Pruebas

La suite `MetaPagePublicationTest` cubre la creación de ejecuciones por página, publicación de imágenes con token cifrado, persistencia del ID externo, errores explícitos y resultados de transporte desconocidos.
