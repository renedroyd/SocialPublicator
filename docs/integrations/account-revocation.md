# Revocación y reactivación de cuentas sociales

## Alcance de la desconexión

Las acciones del panel revocan el acceso de SocialPublicator a una conexión. Son reversibles y no borran ni recrean sesiones locales:

- WhatsApp: no elimina el directorio WPPConnect ni los tokens locales de WBot.
- Facebook Groups: no elimina el perfil Chromium, cookies ni almacenamiento de Puppeteer.
- Meta OAuth: marca como revocados los tokens cifrados en SocialPublicator y desactiva las páginas hijas como destinos. No realiza un logout del usuario en Facebook ni borra los tokens cifrados de auditoría.

Por tanto, revocar desde el panel bloquea el uso de la cuenta por SocialPublicator, pero no equivale a cerrar sesión en la aplicación externa. Si se requiere logout externo, debe hacerse en el proveedor correspondiente de forma explícita.

## Panel administrativo

En **Cuentas sociales → Cuentas**, un miembro con `accounts.manage` puede:

- Desconectar WhatsApp.
- Reactivar WhatsApp.
- Desconectar o reactivar la sesión de navegador de Facebook.
- Desconectar Meta OAuth. Para recuperarlo se utiliza nuevamente **Conectar con Meta**, no una reactivación local.

La revocación se registra con código administrativo y fecha. Los agentes WBot reciben 423 si intentan informar estado para una conexión revocada. El endpoint de preflight también detecta el código de revocación y detiene el arranque de la instancia afectada.

## Reactivación

1. Reactiva la conexión desde el panel.
2. Abre el flujo QR de WhatsApp o la sesión de Facebook.
3. Si el agente no informa un estado nuevo, reinicia esa instancia WBot.
4. Comprueba que la cuenta vuelva a estado conectado y que los destinos estén autorizados.

La reactivación cambia la conexión a `pending` y elimina únicamente la marca administrativa de revocación. No modifica los archivos de sesión.

## Aislamiento de conexiones

Un perfil de Facebook puede tener dos conexiones distintas: OAuth oficial para páginas y sesión de navegador para grupos. Revocar una de ellas no revoca la otra. Al revocar Meta OAuth, sus páginas hijas y destinos API se desactivan; la sesión de navegador puede seguir autorizada.

Una desconexión natural informada por WBot no se confunde con una revocación administrativa. Solo el código de revocación emitido desde el panel bloquea el preflight hasta que un administrador reactive la conexión.
