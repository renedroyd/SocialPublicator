# Seguridad de agentes y conexiones sociales

## Identidad de los agentes WBot

Cada agente se identifica por un workspace y un instance_id estable. El token se genera con:

~~~bash
php artisan bot-agent:issue-token --workspace_id=<ID_WORKSPACE> --instance_id=<INSTANCE_ID>
~~~

El valor se muestra una sola vez. SocialPublicator guarda únicamente su hash SHA-256. Volver a emitir un token reemplaza el hash anterior; desactivar el agente revoca el acceso inmediatamente.

Un token de agente queda vinculado a:
- Un workspace.
- Una instancia WBot.
- Una lista explícita de cuentas sociales autorizadas.

El middleware vuelve a comprobar que las cuentas asignadas pertenecen al workspace del agente. La identidad efectiva se obtiene del token autenticado; no se confía en un instance_id alternativo enviado por el cliente.

El token legacy BOT_API_TOKEN se conserva temporalmente para instalaciones antiguas. Solo representa la instancia configurada como BOT_COMMAND_BOT_NAME (por defecto WBot) y no puede impersonar agentes individuales. En instalaciones multi-workspace debe sustituirse por tokens individuales.

## Autorización de publicaciones, ejecuciones y comandos

- El feed de publicaciones filtra destinos por las cuentas asignadas al agente.
- La sincronización de grupos exige social_account_id y comprueba que pertenece al token, también en las rutas legacy.
- Al iniciar una ejecución, se verifica que el destino está vinculado a una cuenta permitida y que la conexión activa pertenece a esa instancia.
- Para completar o fallar una ejecución se vuelve a comprobar workspace, instance_id y cuenta autorizada del destino.
- La reclamación de comandos filtra por instancia, cuenta autorizada y conexión activa actual.
- Un agente no puede completar comandos legacy sin asignación explícita ni reclamar comandos de otra instancia.
- El nombre de bot usado en el protocolo de comandos debe coincidir con el configurado en el servidor.

Las cuentas y destinos deben estar vinculados en el workspace antes de habilitar el agente. Si se revoca una cuenta del agente, los nuevos feeds, ejecuciones y comandos dejan de estar disponibles para ese token.

## Límites de solicitudes

| Superficie | Límite |
| --- | ---: |
| API bot por IP de origen, antes de autenticar | 600 solicitudes/min |
| API bot por workspace e instancia autenticada | 300 solicitudes/min |
| Estado y QR de cuentas sociales por usuario | 120 solicitudes/min |
| Inicio y callback OAuth de Meta por usuario | 10 solicitudes/min |
| Webhooks Dubai Shop por IP de origen | 120 solicitudes/min |

El agente debe reutilizar conexiones HTTP, aplicar backoff ante HTTP 429 y evitar polling agresivo. Los límites se pueden ajustar en AppServiceProvider y en el middleware de rutas.

## Sesiones de WhatsApp y Facebook

- Los tokens de API de agentes no contienen cookies ni perfiles de navegador.
- Los perfiles de Puppeteer y tokens de WPPConnect permanecen en el almacenamiento protegido de WBot.
- SocialPublicator almacena referencias opacas, proveedor, instancia y estado.
- El QR de WhatsApp se conserva temporalmente en caché durante 60 segundos; no se persiste en tablas ni logs.
- Los endpoints de estado y QR requieren sesión autenticada, workspace activo y permiso de administración.
- Las sesiones existentes no se recrean ni eliminan por rotar tokens de API o modificar permisos.

## OAuth de Meta

- El estado OAuth es aleatorio, de un solo uso, ligado a usuario/workspace/cuenta y expira en diez minutos.
- El callback exige sesión autenticada y permiso vigente sobre la cuenta.
- Los tokens de perfil y página se cifran en la base de datos y no se incluyen en serialización.
- Las solicitudes Graph API utilizan appsecret_proof.
- El vencimiento del token largo de usuario se aplica también como límite conservador a los tokens de página.
- Los errores se registran con ID de cuenta y clase de excepción, sin registrar tokens, códigos OAuth ni secretos.

La disponibilidad de permisos y publicación depende de la configuración, revisión y acceso concedido a la aplicación Meta.

## Webhooks Dubai Shop

- Cada workspace tiene un identificador de clave HMAC único y su secreto cifrado.
- La clave identifica el workspace antes de resolver el contexto multi-tenant.
- Se valida HMAC-SHA256, marca temporal y tolerancia configurada.
- Las claves de integraciones deshabilitadas se rechazan.
- La bandeja de eventos y el event_id mantienen idempotencia frente a reintentos.
- La compatibilidad de secretos por variables de entorno se limita a instalaciones legacy de un solo workspace.

Actualmente se mantiene una sola clave HMAC activa por workspace. Para rotarla, coordina el cambio en el receptor y el emisor en una ventana breve; los webhooks en tránsito pueden recibir 401 y deberán reintentarse desde Dubai Shop. No se admite solapamiento de dos claves durante la rotación.

## Procedimiento operativo

1. Crear o seleccionar el workspace correcto.
2. Crear un agente con un instance_id estable.
3. Asignar solo las cuentas que ese agente debe utilizar.
4. Emitir el token y copiarlo directamente al entorno seguro de WBot.
5. Confirmar estado online y última actividad del agente.
6. Verificar que los destinos están asociados a la cuenta y al agente correctos.
7. Probar una publicación controlada y revisar la ejecución.
8. Ante pérdida o sospecha de compromiso, desactivar el agente o emitir un token nuevo; no borrar perfiles de navegador ni sesiones WhatsApp.
9. Revisar logs y auditoría sin copiar secretos a tickets o chats.

## Configuración de producción

- HTTPS obligatorio para integraciones externas.
- APP_KEY estable, respaldada y protegida; cambiarla invalida credenciales cifradas.
- Cache compartida y persistente para OAuth state y QR temporal cuando hay múltiples instancias web.
- Rate limiting habilitado y workers/scheduler supervisados.
- Tokens y secretos fuera de Git, logs, respuestas API y herramientas de observabilidad.
- Copias de seguridad cifradas y acceso restringido a los perfiles de navegador de WBot.
