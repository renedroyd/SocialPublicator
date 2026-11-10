# Workspaces y control de acceso

## Objetivo

SocialPublicator funciona de forma independiente de Dubai-Shop y permite que varios usuarios compartan un espacio de trabajo. Las cuentas sociales, destinos, publicaciones, campañas y datos operativos quedan asociados al workspace correspondiente. La integración comercial con Dubai-Shop continúa siendo opcional y se configura por workspace.

## Modelo de datos

- `workspaces`: espacio de trabajo, creador y configuración extensible.
- `workspace_memberships`: relación usuario/workspace, rol, permisos explícitos y estado.
- `workspace_invitations`: invitaciones de un solo uso, con hash del token, vencimiento, aceptación y revocación.
- `workspace_audit_logs`: actor, evento, sujeto, metadatos, IP y agente de usuario.
- Las tablas operativas incorporan `workspace_id`, incluyendo catálogo propio y externo, publicaciones, destinos, grupos, ejecuciones, campañas, comandos y estados del bot.

Las invitaciones almacenan únicamente el hash SHA-256 del token. El enlace se muestra al administrador que invita, vence a los siete días y solo se acepta si la sesión corresponde al correo invitado.

## Roles y permisos

| Rol | Capacidades predeterminadas |
| --- | --- |
| Propietario | Control total del workspace y del equipo. |
| Administrador | Administración del equipo y acceso a las capacidades operativas. |
| Editor | Crear, editar y publicar contenido; gestionar campañas editoriales y consultar catálogo/destinos. |
| Colaborador | Consultar publicaciones, ejecuciones y analítica autorizada. |

Un arreglo de permisos explícitos en la membresía reemplaza los permisos predeterminados del rol. Esto permite definir combinaciones personalizadas sin crear un rol rígido por cada caso. Una membresía inactiva nunca concede permisos, ni siquiera con el rol de propietario. Solo el propietario puede asignar el rol de administrador.

La autorización se aplica en los recursos Filament y en las políticas de productos, publicaciones y campañas. Las categorías comparten el permiso del catálogo de productos. Los recursos de miembros e invitaciones requieren el permiso de administración del equipo.

**Permisos por cuenta social o por destino:** implementados en la Fase 2. Editores y colaboradores necesitan concesiones explícitas por cuenta/destino; para publicar en un destino vinculado se requieren ambas concesiones. Los roles globales no se convierten en acceso universal salvo propietario o permisos administrativos explícitos.

## Experiencia de administración

- Cada usuario puede crear espacios de trabajo y queda como propietario del que crea.
- La sección **Mis espacios** muestra los workspaces a los que pertenece y permite editar el nombre e identificador si es propietario.
- El selector de la barra superior permite cambiar el workspace activo.
- Propietarios y administradores pueden añadir miembros existentes, cambiar roles/estado y revocar membresías.
- Propietarios y administradores pueden emitir invitaciones; el enlace puede compartirse con el invitado.
- Un invitado puede iniciar sesión o registrarse con el correo invitado. La aceptación se completa al entrar al panel.
- No se permite eliminar un workspace desde el panel, evitando borrados accidentales de datos operativos.

## Aislamiento de datos

El aislamiento se aplica en varias capas:

1. Los modelos operativos usan un scope global por `workspace_id`; sin contexto válido, las consultas no devuelven registros de workspaces.
2. La creación de registros asigna el workspace activo y falla si no existe contexto.
3. Las relaciones muchos-a-muchos incluyen el workspace en el pivote.
4. La migración añade claves foráneas compuestas para impedir que un pivote o una relación obligatoria conecte registros de workspaces distintos.
5. Las relaciones opcionales que conservan semántica de borrado `SET NULL` se validan al guardar el modelo para impedir referencias cruzadas.
6. Los identificadores de negocio que antes eran globalmente únicos pasan a ser únicos dentro del workspace cuando corresponde.

También se corrigieron referencias de claves foráneas heredadas en las migraciones de productos, movimientos de inventario y pivote de productos/publicaciones.

## Compatibilidad y migración

La migración crea el workspace principal y asigna los usuarios existentes: el primer usuario queda como propietario y los restantes como administradores, manteniendo el acceso previo. Los registros operativos existentes se asignan al workspace principal. No se eliminan publicaciones, productos, grupos, ejecuciones, comandos ni sesiones de bots.

Cuando hay un solo workspace, los procesos heredados pueden resolverlo como compatibilidad. Cuando existen varios, las integraciones de bot y Dubai-Shop deben recibir/configurar explícitamente el workspace correspondiente; no se selecciona uno arbitrariamente.

La migración de aislamiento bloquea explícitamente `down()` en SQLite porque el schema builder de Laravel no permite retirar esas claves foráneas por nombre de forma segura. El rollback lanza un error y conserva el registro de migración; para revertir en SQLite se requiere reconstrucción manual de tablas o recrear la base de pruebas. En motores como MySQL, el método de reversión elimina las restricciones y columnas añadidas; los índices globales antiguos no se recrean automáticamente porque podrían existir valores duplicados entre workspaces.

## Auditoría

Se registran la creación, actualización y eliminación de membresías; creación, aceptación y revocación de invitaciones; y cambios de workspace activo. La creación del workspace queda reflejada además por la membresía inicial de propietario. Las operaciones sensibles de cuentas sociales se incorporarán cuando se implemente su administración.

La administración de cuentas, conexiones y permisos granulares se describe en [Cuentas sociales y conexiones seguras](./social-accounts.md).

## Validación de la Fase 1

Última ejecución CI: [GitHub Actions #42891446569](https://github.com/renedroyd/SocialPublicator/actions/runs/42891446569).

- Migraciones: correctas.
- PHPUnit: **78 pruebas y 263 aserciones aprobadas**.
- Laravel Pint: **61 archivos aprobados**.
- Incluye pruebas de acceso, permisos, invitaciones, aislamiento por workspace y rechazo de relaciones cruzadas entre productos y publicaciones.
