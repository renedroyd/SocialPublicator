# Roadmap — VIRALYX / SocialPublicator

## Objetivo

Evolucionar SocialPublicator desde un gestor de publicaciones y bots hacia una plataforma profesional de gestión, programación, distribución y observabilidad de contenido para múltiples redes sociales.

La arquitectura debe mantener una única fuente de verdad para contenido y agenda, desacoplando la lógica de negocio de los canales de publicación.

## Estado actual

### Ya disponible
- Laravel 13 + Filament 5.
- Panel administrativo VIRALYX.
- Gestión de publicaciones.
- Programación por fecha y hora.
- Recurrencia por intervalos.
- Productos, categorías e inventario.
- Destinos sociales modelados como entidades independientes.
- Grupos de Facebook sincronizados desde el bot.
- Registro de ejecuciones por publicación, ocurrencia, canal y destino.
- Estados de ejecución: pending, running, succeeded, failed.
- Protección de la API mediante token de bot.
- Comandos remotos para reclamar, completar y fallar tareas.
- Estado operativo de bots.
- Dashboard operativo en Filament.
- Suite de pruebas y CI con build de assets frontend.
- Landing comercial y login con identidad VIRALYX.

## Principios de arquitectura
1. Contenido independiente del canal.
2. Adaptadores por canal mediante un contrato común.
3. Idempotencia obligatoria para cada ocurrencia y destino.
4. La agenda debe ser un dominio, no lógica de las vistas ni del bot.
5. La ejecución debe ser asíncrona.
6. Toda ejecución debe ser observable y trazable.
7. Compatibilidad progresiva con WBot.
8. Seguridad por defecto: secretos fuera del repositorio y acciones remotas autenticadas.

# Fase 1 — Núcleo editorial profesional
Objetivo: convertir la gestión de publicaciones en un flujo editorial completo.
- [ ] Estados editoriales: borrador, programada, en proceso, publicada, fallida, cancelada.
- [ ] Separar estado editorial de estado de ejecución.
- [ ] Calendario editorial mensual/semanal/diario.
- [ ] Vista de detalle con historial completo.
- [ ] Duplicar publicación.
- [ ] Reprogramar publicación.
- [ ] Cancelar una ocurrencia sin eliminar la publicación.
- [ ] Previsualización por canal.
- [ ] Biblioteca multimedia básica.
- [ ] Validaciones de contenido antes de programar.
Salida: el usuario puede crear, programar, modificar, cancelar y revisar publicaciones sin conocer la API ni el bot.

# Fase 2 — Arquitectura multi-red
Objetivo: incorporar nuevas redes sin duplicar la lógica del dominio.
- [ ] Contrato SocialChannelDriver.
- [ ] Registro central de drivers.
- [ ] Capacidades por canal: texto, imagen, vídeo, enlaces, límites y programación.
- [ ] PublicationDestination como destino normalizado.
- [ ] Separación entre plataforma, cuenta/conexión y destino.
- [ ] Credenciales almacenadas de forma segura.
- [ ] Health check por conexión.
- [ ] Facebook como primer driver de referencia.
- [ ] WhatsApp/WBot como driver de compatibilidad.
- [ ] Preparación para Instagram, Telegram, X y otros canales.
Salida: añadir un canal requiere principalmente un nuevo driver y sus pruebas.

# Fase 3 — Motor de programación y colas
Objetivo: ejecutar publicaciones de forma fiable y escalable.
- [ ] Jobs de Laravel para materializar/encolar ocurrencias.
- [ ] Cola de ejecución por canal/destino.
- [ ] Backoff y reintentos configurables.
- [ ] Control de concurrencia.
- [ ] Idempotency keys.
- [ ] Dead-letter/fallos permanentes.
- [ ] Expiración de trabajos atrasados.
- [ ] Scheduler de Laravel.
- [ ] Métricas de latencia y tasa de éxito.
- [ ] Herramientas administrativas para reintentar/cancelar.

# Fase 4 — Calendario y planificación avanzada
- [ ] Calendario visual.
- [ ] Arrastrar y soltar para reprogramar.
- [ ] Filtros por canal, estado, campaña y destino.
- [ ] Vista semanal y mensual.
- [ ] Agenda de próximas publicaciones.
- [ ] Bloqueos y ventanas horarias.
- [ ] Recurrencias avanzadas por días de semana.
- [ ] Fechas de finalización y máximo de ocurrencias.
- [ ] Zona horaria explícita por cuenta/espacio de trabajo.

# Fase 5 — Campañas, equipos y permisos
- [ ] Espacios de trabajo/organizaciones.
- [ ] Roles y permisos.
- [ ] Propietario, administrador, editor y operador.
- [ ] Campañas y etiquetas.
- [ ] Flujos de aprobación.
- [ ] Comentarios internos.
- [ ] Historial de cambios.
- [ ] Auditoría de acciones sensibles.

# Fase 6 — Analítica y observabilidad
- [ ] Métricas de publicaciones por canal.
- [ ] Tasa de éxito/fallo.
- [ ] Intentos y latencia.
- [ ] Rendimiento por canal y campaña.
- [ ] Panel de salud de integraciones.
- [ ] Alertas operativas.
- [ ] Exportación de reportes.
- [ ] Retención configurable de logs.

# Fase 7 — Seguridad y producción
- [ ] Gestión segura y rotación de secretos.
- [ ] Rate limiting.
- [ ] Políticas de autorización.
- [ ] Protección de endpoints internos.
- [ ] Auditoría.
- [ ] Backups y recuperación.
- [ ] Health checks y monitoring.
- [ ] Logs estructurados.
- [ ] CI/CD con staging y producción.
- [ ] Migraciones verificadas en CI.

# Fase 8 — Producto SaaS
- [ ] Multi-tenancy.
- [ ] Suscripciones y facturación.
- [ ] Límites por plan.
- [ ] Onboarding.
- [ ] Gestión de miembros.
- [ ] Configuración de marca.
- [ ] Medición de uso por organización.
- [ ] Centro de ayuda.

## Orden de implementación recomendado
1. Núcleo editorial.
2. Drivers multi-red.
3. Motor de colas.
4. Calendario avanzado.
5. Equipos y campañas.
6. Analítica.
7. Producción.
8. SaaS.

## Definición de profesional
- Contenido y programación desacoplados de los bots.
- Contrato común para integrar canales.
- Publicaciones idempotentes.
- Trazabilidad completa de ejecuciones.
- Calendario visual.
- Fallos visibles y reintentables.
- Acciones administrativas protegidas y auditadas.
- CI validando PHP, tests y assets.
- Nuevos canales añadibles sin reescribir el dominio.

## Próximo incremento
El siguiente incremento de código debe centrarse en Fase 1: separar explícitamente el ciclo editorial de la ejecución técnica.

Una publicación debe tener un estado de negocio independiente, mientras cada entrega a Facebook, WhatsApp u otro canal conserva su propio estado operativo. Este desacoplamiento es la base del calendario, los reintentos, las aprobaciones y la integración multi-red.