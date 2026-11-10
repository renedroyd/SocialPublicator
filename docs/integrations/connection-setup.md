# Conectar SocialPublicator con Dubai Shop

Guía operativa para configurar la integración con Dubai Shop (Clásico Dubai), validar el catálogo externo y habilitar campañas sociales gradualmente.

> **Seguridad:** no guardes tokens, secretos HMAC ni credenciales de bots en Git, issues, logs compartidos o chats. Usa el gestor de secretos del despliegue.

## 1. Arquitectura

Dubai Shop es la fuente de verdad del catálogo publicado, variantes, precios de venta, almacenes y disponibilidad. SocialPublicator conserva el espejo remoto en external_catalog_items y external_catalog_warehouses. No copia productos externos a su tabla local products, no altera inventario local y no recibe costos de adquisición.

La consulta del catálogo usa HTTPS y un Bearer token con catalog:read. Los cambios se notifican mediante webhooks HMAC-SHA256. SocialPublicator registra los eventos en integration_event_inbox y evita duplicados por event_id. Las publicaciones utilizan el modelo Publication, destinos, bots y seguimiento de ejecuciones existentes.

## 2. Requisitos

- Ambas aplicaciones desplegadas y accesibles por HTTPS público.
- Migraciones aplicadas y acceso a los gestores de secretos.
- Scheduler y workers persistentes en ambas aplicaciones.
- Bots y destinos sociales configurados y probados independientemente.
- Con Cloudflare Tunnel, usar hostnames HTTPS públicos; no localhost, IP privada ni servidor de desarrollo.

## 3. Emitir token de catálogo

En el servidor de Dubai Shop:

~~~bash
php artisan social-publicator:token --name="SocialPublicator" --expires=365
~~~

Copia el token mostrado una sola vez al gestor de secretos de SocialPublicator como DUBAI_SHOP_API_TOKEN. Debe estar limitado a catalog:read. No reutilices credenciales de usuario o bot. Si se pierde o compromete, emite otro y revoca el anterior.

## 4. Variables de SocialPublicator

~~~dotenv
DUBAI_SHOP_API_BASE_URL=https://tu-dominio-publico-dubai-shop
DUBAI_SHOP_API_TOKEN=<TOKEN_DE_CATALOGO_EMITIDO_POR_DUBAI_SHOP>
DUBAI_SHOP_WEBHOOK_KEYS='{"key-2026-01":"<SECRETO_HMAC_ALEATORIO_LARGO>"}'
DUBAI_SHOP_WEBHOOK_TOLERANCE_SECONDS=300
DUBAI_SHOP_TIMEOUT=15
DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=false
~~~

La URL es el origen de Dubai Shop, sin /api al final. DUBAI_SHOP_WEBHOOK_KEYS asocia el key ID con el secreto que valida firmas entrantes. El ID y secreto deben coincidir exactamente con SOCIAL_PUBLICATOR_WEBHOOK_KEY_ID y SOCIAL_PUBLICATOR_WEBHOOK_SECRET de Dubai Shop. Mantén el despacho de campañas deshabilitado durante la instalación.

## 5. Variables de Dubai Shop

~~~dotenv
SOCIAL_PUBLICATOR_WEBHOOK_URL=https://tu-dominio-publico-socialpublicator/api/integrations/dubai-shop/v1/events
SOCIAL_PUBLICATOR_WEBHOOK_KEY_ID=key-2026-01
SOCIAL_PUBLICATOR_WEBHOOK_SECRET=<EL_MISMO_SECRETO_HMAC_DE_SOCIALPUBLICATOR>
SOCIAL_PUBLICATOR_WEBHOOK_TIMEOUT=15
SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=false
~~~

El receptor es POST /api/integrations/dubai-shop/v1/events. El emisor firma {timestamp}.{raw-request-body} con HMAC-SHA256. El receptor valida X-DubaiShop-Key-Id, X-DubaiShop-Timestamp y X-DubaiShop-Signature (sha256={hex-hmac}). No transformes el cuerpo JSON en un proxy intermedio.

## 6. Aplicar configuración

Después de guardar las variables en ambas aplicaciones:

~~~bash
php artisan config:clear
php artisan config:cache
php artisan queue:restart
~~~

Reinicia los procesos persistentes según el gestor de procesos. No imprimas secretos al comprobar variables.

## 7. Sincronización inicial

En SocialPublicator:

~~~bash
php artisan social-publicator:sync-dubai-catalog
~~~

Comprueba en Filament/base de datos los productos publicados y visibles, variantes activas, precios y monedas, imágenes, almacenes activos habilitados para fulfillment online y productos remotos retirados marcados inactivos. Esta operación no debe cambiar productos ni inventario locales. Si falla, revisa URL, token, capacidad catalog:read, HTTPS y logs.

## 8. Scheduler y workers

En ambos servidores, configura scheduler de Laravel. Con cron:

~~~cron
* * * * * cd /ruta/a/la-aplicacion && php artisan schedule:run >> /dev/null 2>&1
~~~

Ejecuta workers persistentes bajo Supervisor, systemd u otro gestor:

~~~bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
~~~

Adapta parámetros al driver y duración real de los trabajos. El worker debe reiniciarse automáticamente y no depender de una sesión SSH. SocialPublicator programa reconciliación del catálogo cada hora cuando la URL y el token están configurados.

## 9. Campaña de prueba

Desde Filament, abre **Integraciones → Campañas sociales**:

1. Crea una campaña y déjala inactiva.
2. Selecciona pocos productos o variantes externos.
3. Selecciona un único almacén elegible.
4. Configura formato, límites, intervalo y ventana horaria.
5. Define preferencias de agotado, reposición y cambios de precio.
6. Selecciona un único grupo/destino de prueba que controles.

## 10. Activación por etapas

**Etapa A — catálogo:** ambos flags siguen en false. Ejecuta y valida la sincronización.

**Etapa B — eventos:** después de validar catálogo y conectividad, habilita en Dubai Shop:

~~~dotenv
SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=true
~~~

Confirma que los eventos aparecen en integration_event_inbox, las firmas se validan y cada event_id se procesa una sola vez. HTTP 202 sólo confirma aceptación en el inbox, no publicación en redes sociales.

**Etapa C — campaña:** sólo después de validar eventos, habilita en SocialPublicator:

~~~dotenv
DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=true
~~~

Activa una sola campaña de prueba. Revisa la revalidación de producto/precio/disponibilidad en Dubai Shop, la publicación creada y la ejecución del bot por destino. Amplía gradualmente después de confirmar el resultado real.

## 11. Monitoreo

Revisa integration_event_inbox, external_catalog_items, external_catalog_warehouses, triggers, grupos de selección, publicaciones, ejecuciones de bots, logs, failed jobs, scheduler y workers. Un trigger o selección no demuestra que se haya publicado: confirma el estado del bot y el identificador externo cuando exista.

En Dubai Shop se puede inspeccionar integration_event_outbox. Después de corregir la causa de un fallo terminal, el evento se puede reencolar desde ese servidor:

~~~bash
php artisan social-publicator:requeue-outbox <event-uuid>
php artisan social-publicator:dispatch-outbox
~~~

## 12. Pausa de emergencia

Para detener campañas, configura en SocialPublicator:

~~~dotenv
DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=false
~~~

Si ya hay una ejecución, detén también el worker/bot correspondiente. Para detener nuevos webhooks, configura en Dubai Shop:

~~~dotenv
SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=false
~~~

Aplica los cambios y limpia la caché. Los flags no eliminan eventos, triggers, selecciones o publicaciones pendientes ni cancelan necesariamente entregas iniciadas.

## 13. Rotación de credenciales

**HMAC:** agrega primero un nuevo key ID/secreto a DUBAI_SHOP_WEBHOOK_KEYS, conserva temporalmente la clave anterior, configura el nuevo ID/secreto en Dubai Shop, verifica y retira la clave anterior cuando ya no se use.

**Token API:** genera otro token en Dubai Shop, actualiza DUBAI_SHOP_API_TOKEN, ejecuta una sincronización de prueba y revoca el anterior.

## 14. Checklist

- [ ] URLs públicas HTTPS válidas.
- [ ] Token dedicado catalog:read configurado.
- [ ] Key ID y secreto HMAC coincidentes.
- [ ] Flags inicialmente desactivados.
- [ ] Configuración cacheada y workers reiniciados.
- [ ] Sincronización de catálogo revisada.
- [ ] Scheduler y workers persistentes en ambos servidores.
- [ ] Campaña inactiva con almacén y destino controlados.
- [ ] Webhooks recibidos y procesados idempotentemente.
- [ ] Publicación y ejecución del bot de prueba verificadas.
- [ ] Procedimiento de pausa conocido.
- [ ] Secretos fuera de Git y logs.

## Referencias

- [Contrato técnico Dubai Shop](https://github.com/renedroyd/dubai-shop/blob/main/docs/integrations/social-publicator-api-v1.md)
- [Guía operativa existente](./dubai-shop-operations.md)
