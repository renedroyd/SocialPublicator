# Cuentas sociales y conexiones seguras

## Objetivo

Las cuentas sociales pertenecen al workspace, no al usuario que las conectó. Los miembros autorizados comparten la cuenta sin compartir credenciales personales. Retirar un miembro no elimina ni desconecta las cuentas o sesiones del workspace.

## Modelo

- social_accounts: identidad de WhatsApp, perfil de Facebook o página de Facebook, asociada a un workspace.
- social_account_connections: una o varias vías de conexión por cuenta, con proveedor, estado, agente, referencia de sesión, expiración y metadatos.
- workspace_membership_social_account_permissions: permisos de consulta/publicación de cada miembro sobre una cuenta.
- workspace_membership_destination_permissions: permisos de consulta/publicación de cada miembro sobre un grupo WhatsApp, grupo Facebook o destino/página.
- social_account_id en grupos y destinos vincula cada destino con la cuenta que lo administra. Es nullable para preservar destinos existentes hasta que se asocien durante la conexión/sincronización.

Una página de Facebook puede ser una cuenta hija de un perfil de Facebook del mismo workspace. Las cuentas de WhatsApp son identidades independientes.

## Métodos de conexión previstos

| Plataforma/tipo | Método | Dónde residen los secretos/sesiones |
| --- | --- | --- |
| Facebook Page | OAuth oficial de Meta / Graph API | Tokens cifrados en la conexión de la cuenta |
| Facebook Profile / grupos | Sesión persistente de navegador mediante Puppeteer/WBot | Perfil y cookies permanecen en el agente WBot; Laravel solo conserva referencia opaca |
| WhatsApp | QR de WPPConnect | Sesión permanece en el agente WBot; Laravel solo conserva referencia opaca |

Una misma identidad de Facebook puede tener más de una conexión: OAuth oficial para páginas y sesión de navegador para automatización de grupos.

## Protección de credenciales

- El campo credentials usa el cast Laravel encrypted:array; los tokens OAuth se cifran con la clave de aplicación.
- credentials y session_reference están ocultos en la serialización del modelo.
- Las conexiones de navegador y WPPConnect exigen agent_instance_id y una referencia opaca; rechazan credenciales/cookies copiadas a SocialPublicator.
- Los perfiles de navegador, cookies y tokens de sesión de WhatsApp no se guardan en la base de datos Laravel.
- El QR es información transitoria del flujo de conexión y no debe persistirse en tablas ni logs.
- Los eventos de auditoría registran cuenta, proveedor, método y estado, nunca el contenido de credenciales.

## Permisos por cuenta y destino

Los permisos globales del rol son la primera condición. Para editores y colaboradores se requiere además una concesión específica:

- **Cuenta:** view para consultar la cuenta; publish permite publicar y añade implícitamente view.
- **Destino:** view para consultar el destino; publish permite publicar y añade implícitamente view.
- Para publicar en un destino vinculado a una cuenta, el miembro necesita permiso publish tanto sobre el destino como sobre la cuenta asociada.
- El propietario y los miembros con permisos globales accounts.manage / destinations.manage conservan el acceso administrativo correspondiente.
- El colaborador no obtiene permiso de publicación por recibir una concesión; también debe tener el permiso global publications.publish.
- Las opciones de grupos de WhatsApp/Facebook en los formularios de publicaciones y campañas se filtran por estas concesiones.
- Las publicaciones y campañas nuevas registran a su creador. El endpoint de WBot vuelve a validar la membresía y las concesiones al entregar cada publicación; si se retira al creador o se revoca un permiso, el destino deja de entregarse al agente.
- Los registros históricos sin creador conservan el comportamiento anterior para no interrumpir publicaciones ya existentes.

Las concesiones se administran desde **Cuentas sociales → Permisos por cuenta** y **Permisos por destino**. El modelo impide asignar destinos de otro workspace; las claves foráneas compuestas protegen la relación de cuenta y membresía.


## Autenticación oficial de Meta

La conexión de perfiles y páginas de Facebook utiliza el flujo OAuth Authorization Code del lado del servidor:

- El propietario o un miembro con permiso de administración inicia la conexión desde la fila del perfil en Filament.
- Se genera un estado aleatorio de un solo uso, ligado a usuario, workspace y cuenta, con expiración de diez minutos.
- El callback valida el estado, usuario y workspace antes de intercambiar el código.
- Laravel intercambia el código y solicita un token de usuario de larga duración; los secretos de Meta nunca se envían al navegador.
- Se consulta la identidad del perfil y sus páginas autorizadas. Cada página se registra como cuenta hija y su Page access token se cifra en su propia conexión.
- Las credenciales se excluyen de serialización y no se escriben en logs ni auditoría.
- Variables requeridas: META_APP_ID y META_APP_SECRET. META_REDIRECT_URI y META_GRAPH_VERSION son opcionales. El callback configurado en Meta debe coincidir exactamente con el URI generado por la aplicación.
- Permisos solicitados: pages_show_list, pages_read_engagement y pages_manage_posts. La disponibilidad depende del modo de la aplicación, los roles de prueba y la revisión de Meta.

El callback de conexión requiere una sesión autenticada y una membresía activa con capacidad de administrar cuentas. La revocación de permisos o tokens en Meta exige reconectar; el estado de conexión se conserva para diagnóstico.

La conexión QR de WhatsApp y la asociación de sesiones persistentes de Facebook se coordinan con WBot en la etapa siguiente. No se recrean ni migran perfiles existentes durante este flujo.

## Alcance de esta fase

Esta fase entrega el registro de cuentas, los vínculos con destinos, el almacenamiento seguro y la administración de permisos. Todavía no inicia OAuth, genera QR ni controla perfiles WBot desde el panel. Esos flujos se implementan en la fase de autenticación, utilizando estos modelos y sin mover las sesiones actuales de WBot.

## Operación y compatibilidad

- Los grupos/destinos existentes se conservan y quedan con social_account_id = null hasta asociarlos.
- No se recrean ni eliminan perfiles de navegador, tokens de WPPConnect o sesiones existentes.
- No se permite eliminar una cuenta mientras tenga destinos vinculados; debe desconectarse o desasociarse de forma explícita.
- La migración de esta fase bloquea down() en SQLite porque retirar claves foráneas mediante el schema builder no es seguro; el rollback requiere reconstrucción manual de tablas. En MySQL se puede revertir la migración.


## Conexión WhatsApp mediante WBot

- WBot conserva la sesión WPPConnect en su almacenamiento local actual. SocialPublicator no recibe ni guarda cookies, tokens de sesión ni rutas locales.
- El agente informa el QR a un endpoint autenticado mediante el token de integración. El QR se conserva únicamente en Cache durante 60 segundos y se sirve solo a miembros con permiso de administración sobre esa cuenta.
- La pantalla de conexión consulta el estado de forma periódica y deja de consultar al confirmar la conexión.
- Los eventos de conexión actualizan el estado de la cuenta y una conexión WPPConnect con referencia opaca del agente.
- La sincronización de grupos acepta el identificador de cuenta y vincula cada grupo a la cuenta WhatsApp que lo administra.
- La tabla de grupos permite el mismo identificador externo en cuentas diferentes dentro del workspace; los grupos heredados sin cuenta siguen siendo compatibles.
- Configuración del agente: SOCIAL_ACCOUNT_ID y BOT_INSTANCE_ID. SOCIAL_ACCOUNT_ID vacío mantiene el modo independiente/legacy de WBot.
- La conexión actual no mueve, borra ni recrea el directorio de tokens ni el perfil de navegador. La asociación se realiza sobre la sesión WPPConnect existente.
- El QR es temporal y no se registra en logs ni en tablas persistentes. Se elimina del caché al conectar, desconectar o informar un error.


## Sesión persistente de Facebook para grupos

- El agente WBot informa a SocialPublicator el estado pending/connected/disconnected/error de la sesión de navegador asociada al perfil de Facebook del workspace.
- El perfil Chromium, las cookies y el almacenamiento local permanecen exclusivamente en el equipo del agente. Laravel conserva solo una referencia opaca y el identificador de instancia.
- La autenticación se completa manualmente en la ventana de navegador del equipo WBot cuando Facebook la solicita. No se automatizan CAPTCHA ni verificaciones de seguridad.
- La pantalla de Filament muestra el estado de la sesión sin exponer datos del navegador.
- La conexión de navegador es independiente del OAuth oficial del perfil/páginas; ambas vías pueden coexistir para la misma identidad de Facebook.
- Configuración del agente: FACEBOOK_SOCIAL_ACCOUNT_ID. Si está vacío, WBot mantiene su operación actual sin sincronizar el estado de sesión de Facebook con un registro de SocialPublicator.
- No se copian ni se migran cookies, tokens o perfiles Chromium al servidor, ni se recrean sesiones existentes.


## Identidad de agentes y despliegue multinstancia

- Cada proceso WBot informa un instance_id estable en el heartbeat. SocialPublicator mantiene una fila independiente por instancia dentro del workspace, aunque varias compartan el nombre visible WBot.
- Los heartbeats antiguos sin instance_id usan name como identidad, manteniendo compatibilidad y actualizando el mismo registro.
- WBot permite configurar por proceso el nombre de sesión WPPConnect, directorio de sesión, directorio de tokens, perfil Chromium, directorio de sesión Facebook y archivo de bloqueo.
- Los valores predeterminados conservan exactamente las rutas y el nombre de sesión existentes. No se cambia la sesión actual al desplegar esta capacidad.
- Para añadir una instancia, debe asignarse un BOT_INSTANCE_ID único y directorios/nombre de sesión nuevos. No se debe apuntar dos instancias al mismo perfil Chromium o directorio de tokens.
- Cada conexión social conserva el agent_instance_id y una referencia opaca de su sesión. El servidor no almacena archivos de perfil ni credenciales locales.
- La asignación de comandos y publicaciones a agentes específicos se completará en el siguiente bloque de la fase multinstancia; esta entrega prepara la identidad y el aislamiento del almacenamiento.


## Enrutamiento de comandos por agente

- Los comandos de reintento se asignan al agent_instance_id de la conexión activa que administra el grupo/destino.
- WhatsApp resuelve el agente a partir del grupo y su cuenta vinculada; Facebook resuelve el agente a partir del grupo y la sesión de navegador de su perfil.
- El endpoint claim filtra por bot_name e instance_id. Una instancia no puede reclamar comandos asignados a otra.
- Las confirmaciones y fallos verifican también la identidad de la instancia que reclamó el comando.
- Los comandos históricos sin agente asignado siguen disponibles para la instancia legacy cuyo instance_id coincide con el nombre del bot (por defecto WBot). Las nuevas instancias no consumen esos comandos sin asignación.
- Si un destino no está asociado a una cuenta conectada, el comando permanece sin asignación y conserva el comportamiento legacy.
