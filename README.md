# SocialPublicator

Plataforma Laravel para la gestión, programación y distribución de publicaciones hacia múltiples canales sociales. El proyecto centraliza el contenido, la programación, los destinos y el seguimiento de ejecuciones para que los bots y futuras integraciones puedan consumir una única fuente de verdad.

## Estado actual

- **Framework:** Laravel 13
- **PHP:** ^8.3
- **Administración:** Filament 5
- **Autenticación API:** Laravel Sanctum / token de bot
- **Base de datos por defecto:** SQLite
- **Pruebas:** PHPUnit mediante `php artisan test`
- **Calidad de código:** Laravel Pint
- **CI:** GitHub Actions
- **Zona horaria de la aplicación:** `America/Havana` por defecto

El CI actual ejecuta PHP 8.4, instala las dependencias, prepara una base SQLite, ejecuta las migraciones, ejecuta toda la suite de pruebas y valida el formato PHP de los archivos modificados.

## Objetivos

SocialPublicator está evolucionando desde un gestor de publicaciones para WhatsApp hacia una plataforma profesional y extensible para:

1. Crear y administrar contenido.
2. Programar publicaciones por fecha y hora.
3. Definir publicaciones recurrentes.
4. Asociar productos e información de inventario al contenido.
5. Seleccionar uno o varios destinos sociales.
6. Gestionar grupos de Facebook asociados a una publicación.
7. Exponer publicaciones programadas a bots mediante una API protegida.
8. Registrar las ejecuciones para evitar publicaciones duplicadas y mantener trazabilidad.
9. Incorporar progresivamente nuevos canales sociales sin duplicar la lógica de negocio.

## Arquitectura funcional

### Publicaciones

El modelo `Publication` concentra la configuración principal:

- Fecha de inicio.
- Hora.
- Imagen.
- Texto.
- Destino: WhatsApp, Facebook o ambos.
- Estado activo/inactivo.
- Recurrencia por horas.
- Hora de inicio de recurrencia.
- Hora límite diaria.
- Fecha de finalización.
- Relaciones con productos, grupos de Facebook y ejecuciones.

La fecha y la hora de programación son datos explícitos de agenda. Los timestamps de auditoría (`created_at` y `updated_at`) utilizan la zona horaria configurada por `APP_TIMEZONE`.

### Recurrencia

Actualmente se soporta:

- **Sin recurrencia:** una publicación en la fecha y hora indicadas.
- **Cada X horas:** generación de ocurrencias dentro de una ventana diaria, respetando fecha inicial, fecha final, hora de inicio, hora límite e intervalo.

El API genera un identificador estable por ocurrencia:

```text
{publication_id}_{YYYY-MM-DD}_{HH-mm}
```

Esto permite que un bot trate cada ejecución programada como una unidad independiente.

### Productos e inventario

Las publicaciones pueden relacionarse con productos mediante la tabla pivote correspondiente. Los productos disponen de categorías y movimientos de inventario, permitiendo evolucionar hacia publicaciones dinámicas basadas en disponibilidad y precios.

### Facebook

La aplicación mantiene los grupos de Facebook sincronizados desde el bot mediante un endpoint protegido. Una publicación puede seleccionar uno o varios grupos activos cuando su destino incluye Facebook.

Cada grupo almacena:

- ID de Facebook.
- Nombre.
- URL.
- Estado activo.
- Fecha de última sincronización.

### Ejecuciones

`PublicationExecution` registra el ciclo de entrega de una publicación a un destino concreto.

Estados principales:

- `pending`
- `running`
- `succeeded`
- `failed`

El servicio `PublicationExecutionService` controla las transiciones y utiliza transacciones/bloqueos para evitar carreras entre procesos. La base de datos aplica una restricción de unicidad sobre la combinación de ocurrencia y destino, proporcionando una barrera adicional contra duplicados.

## API para bots

Las rutas del bot están protegidas por el middleware `VerifyBotApiToken`.

### Obtener publicaciones programadas

```http
GET /api/bot/publications
Authorization: Bearer <BOT_API_TOKEN>
```

También se mantiene temporalmente el endpoint legado:

```http
GET /api/bot/publicaciones
```

La respuesta contiene el contrato moderno en inglés y campos heredados en español para mantener compatibilidad con la versión estable actual del WBot mientras se completa la migración.

Ejemplo conceptual:

```json
{
  "data": [
    {
      "id": 1,
      "occurrence_id": "1_2026-10-08_09-00",
      "date": "2026-10-08",
      "time": "09:00",
      "image": "https://example.test/storage/publications/example.jpg",
      "text": "Contenido de la publicación",
      "destination": "both",
      "recurrence_type": "none",
      "facebook_groups": []
    }
  ]
}
```

### Sincronizar grupos de Facebook

```http
POST /api/bot/facebook/groups/sync
Authorization: Bearer <BOT_API_TOKEN>
Content-Type: application/json
```

Payload:

```json
{
  "groups": [
    {
      "facebook_id": "123456789",
      "name": "Grupo de ejemplo",
      "url": "https://www.facebook.com/groups/123456789"
    }
  ]
}
```

También existe el endpoint legado:

```http
POST /api/bot/facebook/grupos/sincronizar
```

### Reportar ejecuciones del bot

WBot informa el ciclo de vida de cada entrega mediante estos endpoints protegidos por el token de bot:

```http
POST /api/bot/executions/start
POST /api/bot/executions/{execution}/complete
POST /api/bot/executions/{execution}/fail
```

Al iniciar una ejecución, el bot envía `publication_id`, `occurrence_id`, `channel` (`whatsapp` o `facebook`) y `target_key`; para destinos de Facebook puede enviar también `facebook_group_id`. La finalización admite `external_id` para conservar el identificador de la publicación o mensaje externo. Los estados de ejecución son `pending`, `running`, `succeeded` y `failed`, y el campo `attempts` registra los intentos de entrega.

### Comandos remotos para WBot

Laravel puede encolar comandos para que el bot los reclame y ejecute:

```http
GET /api/bot/commands/claim?bot_name=WBot
POST /api/bot/commands/{command}/complete
POST /api/bot/commands/{command}/fail
```

El comando de reintento usa el tipo `retry_publication` y conserva en su payload la ejecución, ocurrencia, canal y destino originales. Las reclamaciones se identifican por bot; el bot debe enviar `bot_name` al completar o reportar el fallo. Los comandos pasan por `pending`, `claimed`, `succeeded` o `failed`. Los fallos pueden reencolarse hasta `max_attempts`, con una espera de 30 segundos entre intentos.

La configuración de Laravel incluye `BOT_API_TOKEN` y `BOT_COMMAND_BOT_NAME=WBot`; en WBot se configuran `LARAVEL_API_URL`, `LARAVEL_API_TOKEN` y opcionalmente `BOT_NAME`. Los tokens deben ser iguales en ambos extremos y nunca deben versionarse.

El panel Filament muestra el historial operativo de ejecuciones, con filtros por estado, canal y publicación, identificador de ocurrencia, número de intentos, tiempos, ID externo y mensaje de error. La acción **Reintentar** crea un comando remoto y evita duplicar comandos de reintento que sigan pendientes o reclamados para esa ejecución.

## Estructura principal

```text
app/
├── Filament/
│   └── Resources/
│       ├── Categories/
│       ├── Products/
│       └── Publications/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── BotPublicationController.php
│   │       └── FacebookGroupController.php
│   ├── Middleware/
│   └── ...
├── Models/
│   ├── Category.php
│   ├── FacebookGroup.php
│   ├── InventoryMovement.php
│   ├── Product.php
│   ├── Publication.php
│   ├── PublicationDestination.php
│   ├── PublicationExecution.php
│   └── PublicationSchedule.php
└── Services/
    └── PublicationExecutionService.php

database/
├── migrations/
└── seeders/

routes/
└── api.php

tests/
├── Feature/
└── Unit/

.github/
└── workflows/
    └── ci.yml
```

## Instalación

Requisitos:

- PHP 8.3 o superior.
- Composer.
- Node.js y npm para los assets del panel.
- Una base de datos soportada por Laravel. SQLite es la configuración de desarrollo recomendada actualmente.

Instalar dependencias:

```bash
composer install
npm install
```

Crear el entorno:

```bash
cp .env.example .env
php artisan key:generate
```

Para SQLite:

```bash
touch database/database.sqlite
php artisan migrate
```

Configurar el token utilizado por los bots y el resto de variables necesarias en `.env`.

Construir los assets:

```bash
npm run build
```

Iniciar el entorno de desarrollo:

```bash
php artisan serve
```

## Zona horaria

La aplicación utiliza:

```env
APP_TIMEZONE=America/Havana
```

y `config/app.php` obtiene la zona horaria desde esa variable:

```php
'timezone' => env('APP_TIMEZONE', 'America/Havana'),
```

Esto es especialmente importante para las publicaciones programadas y para los timestamps de auditoría. En una futura instalación multi-región, `APP_TIMEZONE` puede configurarse según la zona horaria de la instancia.

Después de cambiar variables de entorno en una instalación existente, limpiar la configuración cacheada:

```bash
php artisan config:clear
```

## Pruebas

Ejecutar toda la suite:

```bash
php artisan test
```

Validar formato:

```bash
vendor/bin/pint --test
```

Para preparar el entorno utilizado por los agentes de desarrollo, el repositorio incluye las instrucciones de Laravel Boost en `AGENTS.md`:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

## Integración continua

El workflow `.github/workflows/ci.yml` verifica:

1. Checkout del repositorio.
2. PHP 8.4.
3. Extensiones requeridas para Laravel y SQLite.
4. Instalación de Composer.
5. Creación del entorno de pruebas.
6. Migraciones.
7. Tests de Laravel.
8. Formato Pint únicamente sobre los archivos PHP modificados.

El estado de CI debe ser verde antes de integrar cambios en `main`.

## Convenciones de desarrollo

- Mantener los identificadores de dominio y código en inglés.
- Mantener compatibilidad temporal con los nombres heredados del bot cuando sea necesario.
- Preferir cambios pequeños y verificables.
- Añadir pruebas para cambios de comportamiento.
- Mantener las migraciones como historial evolutivo del esquema.
- Evitar duplicar lógica de programación en cada canal social.
- Utilizar servicios de dominio para operaciones que requieran transacciones, idempotencia o coordinación entre procesos.
- No almacenar secretos en el repositorio.

## Roadmap técnico

### Corto plazo

- Consolidar el contrato API moderno en inglés.
- Completar la migración del WBot a los nombres nuevos.
- Mejorar las pruebas de programación y zona horaria.
- Incorporar visualización del historial de ejecuciones en Filament.
- Añadir filtros y métricas de publicaciones por canal y estado.

### Medio plazo

- Arquitectura de adaptadores por red social.
- Cola de trabajos para publicación asíncrona.
- Reintentos y backoff por canal.
- Registro de respuestas externas y IDs de publicaciones.
- Calendario visual de publicaciones.
- Plantillas de contenido.
- Gestión avanzada de productos e inventario dentro del compositor.

### Largo plazo

- Nuevas redes sociales mediante conectores independientes.
- Multiusuario y roles.
- Multiempresa/multicuenta.
- Analítica de publicaciones.
- Auditoría completa.
- Gestión centralizada de credenciales OAuth.
- Programación basada en zona horaria por cuenta o destino.

## Seguridad

- Las credenciales y tokens deben mantenerse exclusivamente en `.env` o en el gestor de secretos del entorno.
- Los endpoints del bot requieren autenticación mediante token.
- Las operaciones de sincronización de Facebook se validan antes de persistirse.
- Las ejecuciones de publicaciones se controlan para evitar duplicados.
- En producción se debe usar HTTPS y una configuración de `APP_DEBUG=false`.

## Licencia

El proyecto utiliza la licencia MIT.

## Documentación oficial

- Laravel: https://laravel.com/docs
- Filament: https://filamentphp.com/docs
- Laravel Boost: https://laravel.com/docs/ai
