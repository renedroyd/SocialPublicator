# Conectar SocialPublicator con Dubai Shop

Guía para habilitar la integración opcional por espacio de trabajo, validar el catálogo externo y activar campañas sociales gradualmente.

> **Seguridad:** nunca guardes tokens, secretos HMAC ni credenciales de bots en Git, issues, logs compartidos o chats. Los secretos de la conexión se cifran en la base de datos de SocialPublicator.

## 1. Modos de funcionamiento

Cada workspace puede funcionar en uno de estos modos:

- **Independiente:** no se configura ni habilita la conexión con Dubai Shop. Se mantienen disponibles el catálogo local, los productos, las publicaciones y los destinos propios de SocialPublicator.
- **Integrado:** el workspace habilita su conexión privada con Dubai Shop. Su catálogo externo, almacenes, eventos y campañas quedan aislados de los demás workspaces.

No se comparten tokens, secretos HMAC, catálogos ni configuraciones entre workspaces. La integración no modifica el catálogo ni el inventario local de SocialPublicator.

## 2. Requisitos

- Ambas aplicaciones desplegadas y accesibles por HTTPS público.
- Migraciones aplicadas en SocialPublicator.
- Scheduler y workers persistentes en ambos servidores.
- Bots y destinos sociales configurados y probados independientemente.
- Con Cloudflare Tunnel, usar hostnames HTTPS públicos; no localhost, IP privada ni servidor de desarrollo.

## 3. Emitir el token de catálogo

En el servidor de Dubai Shop:

~~~bash
php artisan social-publicator:token --name="SocialPublicator" --expires=365
~~~

Copia el token una sola vez. Debe estar limitado al permiso catalog:read. No reutilices credenciales de usuario o bot. Si se pierde o compromete, emite otro y revoca el anterior.

## 4. Configurar el workspace en SocialPublicator

Entra al workspace que utilizará la integración y abre **Integraciones → Dubai Shop**.

1. Selecciona **Configurar conexión**.
2. Introduce la URL pública HTTPS de Dubai Shop, sin /api al final.
3. Pega el token de catálogo emitido en el paso anterior.
4. Deja desactivada la opción **Permitir campañas automáticas**.
5. Activa **Integración habilitada** y guarda.
6. Utiliza **Probar conexión** para verificar que el servidor responde y devuelve sus almacenes.

El token API se cifra al guardarse. Si editas la conexión y dejas vacío el campo de token, se conserva el secreto almacenado; no se muestra de nuevo en pantalla.

La integración habilitada permite sincronizar el catálogo de ese workspace. La publicación automática continúa desactivada hasta que la actives expresamente.

## 5. Configurar eventos HMAC

Para recibir cambios de inventario, precios y catálogo en tiempo real, configura en **Eventos firmados**:

- **Identificador de clave HMAC:** valor único para identificar esta conexión, por ejemplo socialpublicator-workspace-01.
- **Secreto HMAC:** secreto aleatorio largo compartido con el emisor Dubai Shop.

El identificador debe ser único entre los workspaces de esta instalación. El secreto se cifra en la base de datos. El receptor obtiene el workspace desde ese identificador, valida la firma y aplica la tolerancia temporal configurada. Una clave deshabilitada no puede enviar eventos.

En Dubai Shop configura:

~~~dotenv
SOCIAL_PUBLICATOR_WEBHOOK_URL=https://tu-dominio-publico-socialpublicator/api/integrations/dubai-shop/v1/events
SOCIAL_PUBLICATOR_WEBHOOK_KEY_ID=<EL_IDENTIFICADOR_CONFIGURADO_EN_SOCIALPUBLICATOR>
SOCIAL_PUBLICATOR_WEBHOOK_SECRET=<EL_MISMO_SECRETO_HMAC>
SOCIAL_PUBLICATOR_WEBHOOK_TIMEOUT=15
SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=false
~~~

El emisor firma {timestamp}.{raw-request-body} con HMAC-SHA256. El receptor valida X-DubaiShop-Key-Id, X-DubaiShop-Timestamp y X-DubaiShop-Signature (sha256={hex-hmac}). No transformes el cuerpo JSON en un proxy intermedio.

## 6. Sincronización inicial

En SocialPublicator:

~~~bash
php artisan social-publicator:sync-dubai-catalog
~~~

El comando procesa cada workspace con una integración habilitada y mantiene su contexto separado. Si no hay conexiones activas, finaliza sin afectar el modo independiente.

Comprueba los artículos publicados y visibles, variantes activas, precios y monedas, imágenes, almacenes elegibles y productos remotos retirados marcados inactivos. La sincronización no debe cambiar productos ni inventario locales.

## 7. Scheduler y workers

En ambos servidores configura el scheduler de Laravel. Con cron:

~~~cron
* * * * * cd /ruta/a/la-aplicacion && php artisan schedule:run >> /dev/null 2>&1
~~~

Ejecuta workers persistentes bajo Supervisor, systemd u otro gestor:

~~~bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
~~~

El worker debe reiniciarse automáticamente y no depender de una sesión SSH. SocialPublicator programa reconciliación horaria por cada workspace conectado.

## 8. Campaña de prueba

Una vez sincronizado el catálogo, abre **Integraciones → Campañas sociales** en el mismo workspace:

1. Crea una campaña y déjala inactiva.
2. Selecciona pocos productos o variantes externos.
3. Selecciona un único almacén elegible.
4. Configura formato, límites, intervalo y ventana horaria.
5. Define preferencias de agotado, reposición y cambios de precio.
6. Selecciona un único grupo/destino de prueba que controles.

La sección de campañas externas solo está disponible cuando la integración Dubai Shop del workspace está habilitada. Las publicaciones y el catálogo local continúan disponibles aunque no exista integración.

## 9. Activación por etapas

**Etapa A — catálogo:** activa la conexión, mantén deshabilitadas las campañas automáticas y valida la sincronización.

**Etapa B — eventos:** después de validar catálogo y conectividad, habilita en Dubai Shop:

~~~dotenv
SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=true
~~~

Confirma que los eventos aparecen en integration_event_inbox, que las firmas se validan y que cada event_id se procesa una sola vez. HTTP 202 confirma la aceptación en el inbox, no que se haya publicado en redes sociales.

**Etapa C — campañas:** cuando catálogo, eventos, almacenes y destinos estén validados, activa **Permitir campañas automáticas** en el workspace correspondiente. Esta opción es independiente de las demás conexiones y no habilita campañas en otros workspaces.

Activa una sola campaña de prueba. Revisa la revalidación del producto, precio y disponibilidad en Dubai Shop, la publicación creada y la ejecución del bot por destino. Amplía gradualmente después de confirmar el resultado real.

## 10. Monitoreo y pausa

Revisa integration_event_inbox, external_catalog_items, external_catalog_warehouses, triggers, grupos de selección, publicaciones, ejecuciones de bots, logs, failed jobs, scheduler y workers. Un trigger o una selección no demuestran que se haya publicado: confirma el estado del bot y el identificador externo cuando exista.

Para pausar un workspace, desactiva **Permitir campañas automáticas** y, si deseas detener también la sincronización y los webhooks, desactiva **Integración habilitada** en su conexión. Esto no elimina eventos, triggers, selecciones ni publicaciones ya creadas, y no cancela necesariamente entregas iniciadas. Para detener webhooks en origen, desactiva también SOCIAL_PUBLICATOR_WEBHOOK_ENABLED en Dubai Shop.

## 11. Rotación de credenciales

**Token API:** emite otro token en Dubai Shop, actualízalo en la conexión del workspace, prueba la conexión y revoca el anterior.

**HMAC:** actualmente hay una sola clave activa por workspace. Planifica una ventana breve: pausa los webhooks en origen, actualiza el ID y secreto en SocialPublicator, actualiza el mismo par en Dubai Shop y reactiva los webhooks. Los eventos en tránsito pueden recibir 401 y deben reintentarse desde el emisor; no hay solapamiento de claves durante la rotación.

## 12. Compatibilidad heredada

Las variables DUBAI_SHOP_API_BASE_URL, DUBAI_SHOP_API_TOKEN, DUBAI_SHOP_WEBHOOK_KEYS y DUBAI_SHOP_WORKSPACE_ID se conservan temporalmente para instalaciones antiguas de un solo workspace. No se recomienda utilizarlas en despliegues multi-workspace. Cuando exista una conexión guardada para un workspace, esa configuración tiene prioridad y una conexión deshabilitada no puede recurrir a los secretos globales.

## 13. Límites de solicitudes

La API de WBot aplica 600 solicitudes/min por IP y 300/min por workspace e instancia autenticada. Los endpoints de estado/QR limitan a 120/min por usuario; OAuth de Meta, a 10/min por usuario; los webhooks, a 120/min por IP. WBot debe aplicar backoff ante HTTP 429 y evitar polling agresivo. Consulta [Seguridad de agentes](../architecture/agent-security.md) para el detalle.

## 14. Checklist

- [ ] Workspace correcto seleccionado.
- [ ] URL pública HTTPS y token catalog:read configurados.
- [ ] Prueba de conexión correcta.
- [ ] HMAC configurado si se utilizarán webhooks.
- [ ] Campañas automáticas inicialmente desactivadas.
- [ ] Catálogo y almacenes revisados.
- [ ] Scheduler y workers persistentes.
- [ ] Campaña inactiva con almacén y destino controlados.
- [ ] Webhooks recibidos y procesados idempotentemente.
- [ ] Publicación y ejecución del bot de prueba verificadas.
- [ ] Procedimiento de pausa conocido.
- [ ] Secretos fuera de Git y logs.

## Referencias

- [Contrato técnico Dubai Shop](https://github.com/renedroyd/dubai-shop/blob/main/docs/integrations/social-publicator-api-v1.md)
- [Guía operativa existente](./dubai-shop-operations.md)
