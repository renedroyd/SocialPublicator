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

## Alcance de esta fase

Esta fase entrega el registro de cuentas, los vínculos con destinos, el almacenamiento seguro y la administración de permisos. Todavía no inicia OAuth, genera QR ni controla perfiles WBot desde el panel. Esos flujos se implementan en la fase de autenticación, utilizando estos modelos y sin mover las sesiones actuales de WBot.

## Operación y compatibilidad

- Los grupos/destinos existentes se conservan y quedan con social_account_id = null hasta asociarlos.
- No se recrean ni eliminan perfiles de navegador, tokens de WPPConnect o sesiones existentes.
- No se permite eliminar una cuenta mientras tenga destinos vinculados; debe desconectarse o desasociarse de forma explícita.
- La migración de esta fase bloquea down() en SQLite porque retirar claves foráneas mediante el schema builder no es seguro; el rollback requiere reconstrucción manual de tablas. En MySQL se puede revertir la migración.
