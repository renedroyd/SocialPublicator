# Destinos sociales asociados a cuentas y publicaciones

## Modelo de propiedad

Cada grupo de WhatsApp o Facebook pertenece a una cuenta social del mismo workspace
mediante `social_account_id`. Una publicación puede seleccionar destinos de varias
cuentas. La asociación vive en SocialPublicator; Dubai Shop no recibe ni almacena
credenciales o IDs de sesión social.

Cuando un workspace tiene cuentas sociales configuradas, los destinos sin cuenta
asociada dejan de ser seleccionables y no se incorporan a publicaciones ni campañas.
La compatibilidad con destinos heredados sin `social_account_id` se conserva solo
para workspaces que todavía no tienen cuentas sociales configuradas.

## Permisos de selección

El formulario de publicaciones y el de campañas aplican las mismas reglas:

1. El destino debe estar activo y pertenecer al workspace actual.
2. Los grupos de WhatsApp deben tener administración verificada (`is_admin=true`).
3. El miembro debe tener permiso de publicación para el destino.
4. El miembro debe tener permiso de publicación para la cuenta asociada, salvo
   propietario o rol con gestión global de cuentas.
5. Los grupos sin cuenta no se mezclan con permisos de cuentas específicas.

La comprobación de permisos también se repite en el servidor al crear una publicación
desde una campaña; la seguridad no depende únicamente del filtro visual de Filament.

## Entrega a múltiples instancias WBot

Una publicación puede asociar varios grupos de WhatsApp y Facebook, incluso si pertenecen
a cuentas diferentes. El endpoint de publicaciones filtra destinos por las cuentas
autorizadas para el BotAgent que realiza la petición:

- El worker A recibe solo los grupos asociados a las cuentas asignadas a A.
- El worker B recibe solo los grupos asociados a las cuentas asignadas a B.
- Los grupos de WhatsApp solo se entregan a agentes cuando su administración está
  verificada y la cuenta asociada es de tipo WhatsApp. Los grupos de Facebook deben
  pertenecer a una cuenta de perfil Facebook con conexión de navegador; las páginas
  oficiales siguen el flujo Meta OAuth/API.
- Cada grupo conserva su clave de destino y su ejecución idempotente independiente.

Así, una campaña puede publicar el mismo contenido a destinos de varias cuentas sin
compartir sesiones, tokens ni permisos entre workers.

## Integración con Dubai-Shop

Dubai-Shop sigue enviando catálogo, precios, disponibilidad y eventos comerciales a
SocialPublicator. La campaña de SocialPublicator contiene la selección de grupos por
cuenta; el dispatcher valida la asociación de cada grupo y crea una Publication con
esas relaciones. Los workers WBot reciben después únicamente los destinos de sus
cuentas autorizadas.

Dubai-Shop no publica directamente en WhatsApp/Facebook y no accede a credenciales
sociales. SocialPublicator mantiene el flujo de campañas y permisos; WBot mantiene la
ejecución de sesiones y entregas.
