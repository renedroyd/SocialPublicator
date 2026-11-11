# Operación de la integración opcional con Dubai-Shop

## Independencia por workspace

La integración está deshabilitada por defecto. Cada workspace puede operar con el
catálogo local de SocialPublicator sin credenciales ni conexión con Dubai-Shop.
La conexión externa se configura en el panel del workspace y sus credenciales se
cifran individualmente.

Los artículos y almacenes recibidos se guardan como `ExternalCatalogItem` y
`ExternalCatalogWarehouse`; no se convierten ni se copian como productos locales.
Deshabilitar o desconectar la integración no elimina el catálogo externo ya recibido.

## Requisitos para habilitar

Cada workspace integrado debe configurar:

- URL HTTPS de la API de Dubai-Shop.
- Token de lectura del catálogo.
- Identificador de clave HMAC.
- Secreto HMAC.
- Ventana de tolerancia de timestamp.

Los webhooks se validan por clave y workspace. Si el registro existe pero está
deshabilitado o incompleto, no se permite recurrir a credenciales globales legacy.
La compatibilidad con claves por entorno se limita a instalaciones antiguas de un
solo workspace y requiere API credentials y claves HMAC explícitas.

## Activación gradual

El orden de activación es intencional:

1. Guardar las credenciales y habilitar la integración.
2. Usar **Probar conexión** para validar la API y marcar `last_verified_at`.
3. Usar **Sincronizar catálogo** para completar artículos y almacenes y marcar
   `last_catalog_synced_at`.
4. Activar manualmente **Permitir campañas automáticas**.

El dispatcher solo procesa campañas cuando la integración está habilitada, tiene
credenciales y HMAC, fue verificada, completó al menos una sincronización integral y
el toggle de campañas está activado. Una modificación de credenciales, una
desactivación, un fallo de verificación o un fallo de sincronización reinicia el
estado de activación y pausa las campañas.

La sincronización integral es paginada e idempotente. Solo desactiva registros externos
que no aparecieron después de completar todas las páginas. Una ejecución fallida no
marca la sincronización como exitosa y desactiva temporalmente el despacho de campañas.

## Procesamiento asíncrono y scheduler

En producción deben estar activos:

- Un worker de cola Laravel para procesar `ProcessDubaiShopEvent`.
- El scheduler Laravel (`schedule:run` por cron o `schedule:work`).
- La tarea programada de sincronización de catálogo.
- La tarea programada de evaluación y despacho de campañas.

La sincronización programada solo incluye workspaces habilitados, con HMAC completo y
verificación previa. El despacho de campañas exige además sincronización completa y
activación explícita.

## Límites de responsabilidad entre aplicaciones

- **Dubai-Shop:** fuente de catálogo, precios, disponibilidad y almacenes.
- **SocialPublicator:** conexión opcional por workspace, catálogo externo, campañas,
  cuentas sociales, permisos y selección de destinos.
- **WBot:** ejecución de sesiones y entregas en los destinos asignados a cada BotAgent.

Dubai-Shop no guarda credenciales sociales ni publica directamente en WhatsApp o
Facebook. SocialPublicator puede funcionar de forma autónoma cuando la integración
está deshabilitada.
