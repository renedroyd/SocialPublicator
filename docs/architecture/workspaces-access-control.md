# Workspaces y control de acceso

## Objetivo

SocialPublicator incorpora una capa de espacios de trabajo para que las cuentas sociales, destinos, publicaciones y usuarios puedan pertenecer a un contexto compartido sin depender de Dubai-Shop. La integración comercial seguirá siendo opcional y se conectará al workspace que la habilite.

## Modelo de datos inicial

- `workspaces`: espacio de trabajo, propietario creador y configuración extensible.
- `workspace_memberships`: relación usuario/workspace, rol, permisos explícitos y estado.
- `workspace_invitations`: invitaciones de un solo uso representadas por un hash de token, con vencimiento, aceptación y revocación.
- `workspace_audit_logs`: actor, evento, sujeto, metadatos e información de la petición.

Las invitaciones almacenan únicamente el hash del token; nunca debe guardarse el token en texto claro.

## Roles iniciales

| Rol | Capacidades |
| --- | --- |
| Propietario | Control total del workspace. |
| Administrador | Administración del equipo y acceso a todas las capacidades operativas del workspace. |
| Editor | Crear, editar y publicar contenido; gestionar campañas editoriales y consultar catálogo/destinos. |
| Colaborador | Consultar publicaciones, ejecuciones y analítica autorizada. |

Un arreglo de permisos explícitos en la membresía reemplaza los permisos predeterminados del rol. Esto permite construir permisos personalizados sin añadir roles rígidos por cada caso. Una membresía inactiva nunca concede permisos, ni siquiera con el rol de propietario.

## Compatibilidad con instalaciones existentes

La migración crea un workspace principal cuando ya existen usuarios y conserva las cuentas. El primer usuario existente queda como propietario y los restantes como administradores para mantener el acceso operativo que tenían antes de introducir workspaces. No elimina ni modifica publicaciones, productos, destinos, sesiones de bots ni credenciales.

Los usuarios nuevos no obtienen acceso al panel Filament hasta que pertenezcan a un workspace activo.

## Alcance de esta entrega

Esta primera entrega establece el esquema, relaciones Eloquent, permisos base, auditoría y la protección de acceso al panel. No activa todavía el aislamiento de filas de publicaciones, productos, grupos, campañas y destinos por workspace, ni la interfaz de invitaciones. Esos cambios deben completarse antes de habilitar usuarios de workspaces distintos sobre datos operativos, para evitar cualquier fuga entre espacios.

## Siguientes incrementos

1. Resolver y seleccionar el workspace activo por sesión.
2. Aplicar `workspace_id`, backfill y scopes/policies a cada recurso operativo, incluyendo los datos del catálogo externo.
3. Añadir interfaz Filament para miembros, invitaciones, roles y permisos por cuenta/destino.
4. Registrar automáticamente las operaciones sensibles en el historial de auditoría.