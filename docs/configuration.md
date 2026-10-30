# Configuración desde WooCommerce

1. Instala y activa el ZIP.
2. Abre **WooCommerce → Ajustes → Pagos → LAFISE — Y2KWebs**.
3. Introduce **Username, Password, TerminalID, MerchantID y API KEY**, en el mismo orden y con las mismas etiquetas del correo de aprobación de LAFISE.
4. Selecciona CRC o USD, coincidiendo con la moneda de la tienda y el terminal. `Typerequest` usa `Production` por defecto, como la integración de referencia.
5. Guarda y pulsa **Comprobar conexión (sin cobro)**. Esta acción autentica y comprueba la llave pública; no realiza una venta.
6. Activa el método de pago. En modo **Normal** estará disponible para los compradores, incluidos invitados.

No se solicitan certificados, códigos de aprobación ni archivos de configuración privados para usar el plugin. Cada comercio utiliza sus propias credenciales y acuerda con LAFISE las condiciones de su servicio. La versión 0.2.0 es preliminar; consulta la [validación](validation.md) antes de realizar pruebas bancarias.

Si WooCommerce indica que no hay métodos de pago, comprueba que la pasarela esté activada, que las credenciales estén completas, que la moneda del terminal coincida con la tienda y que WordPress detecte HTTPS. En modo Pruebas, accede con una cuenta autorizada. Si `Typerequest` no se ha guardado, el plugin utiliza `Production`; un valor guardado explícitamente vacío sigue siendo inválido.

## Claves guardadas

Password y API KEY se introducen en campos protegidos del administrador y se guardan cifrados en una opción separada, no precargada. Los formularios y respuestas API nunca devuelven sus valores. Dejar un campo vacío conserva el secreto existente; escribir otro lo sustituye. La opción **Eliminar credenciales guardadas** borra ambos secretos y desactiva la pasarela.

Las etiquetas coinciden con el correo; los identificadores internos de configuración se conservan para mantener las instalaciones existentes:

| Correo de LAFISE y administrador | Identificador interno |
|---|---|
| Username | `username` |
| Password | `password` |
| TerminalID | `terminal_id` |
| MerchantID | `merchant_id` |
| API KEY | `merchant_pay_id` |

API KEY es la llave de firma que el protocolo denomina Merchant Pay ID. MerchantID identifica al comercio y es un dato distinto.

La configuración `Y2K_LAFISE_PRIVATE` sigue disponible como alternativa opcional para instalaciones gestionadas. Sus valores prevalecen sobre el administrador; si existe esa constante, retirarla o actualizarla antes de cambiar de comercio. No es necesaria para la instalación normal.

## Pruebas opcionales

El modo **Pruebas** muestra la pasarela solamente a administradores/gestores de WooCommerce y usuarios con la capacidad `y2k_lafise_test`. Incluye un máximo por operación editable en la moneda de la tienda. Los cobros siguen siendo reales; no representa un sandbox bancario. El modo Normal no aplica ese límite.

## Servidor

HTTPS, enlaces permanentes, OpenSSL, DOM y MySQL/MariaDB con GET_LOCK. Configurar cron fiable. Excluir checkout, pagar pedido, admin-ajax y `/y2k-lafise/*` de caché y captura de cuerpos HTTP. Los callbacks POST del banco deben llegar sin challenges de CDN ni login global; su autorización usa un token aleatorio y la correlación de la sesión 3DS.

Cada instalación cobra en una moneda y un terminal a la vez, sin conversión automática. No cambiar de cuenta mientras haya pagos pendientes: la conciliación comprueba los identificadores originales.

## API administrativa

`GET/POST /wp-json/y2k-lafise/v1/settings` permite gestionar los mismos ajustes con permiso `manage_woocommerce`, usando la autenticación REST estándar de WordPress (incluida Application Password sobre HTTPS). GET devuelve indicadores `password_configured` y `merchant_pay_id_configured`, nunca secretos. POST acepta los identificadores de campos del formulario, con `enabled` y `clear_secrets` como `yes/no` y `mode` como `live/test`.

`GET /wp-json/y2k-lafise/v1/attempts/{reference}` permite al mismo personal autorizado consultar el estado, motivo y diagnóstico mínimo de un intento, junto con indicadores de disponibilidad de recibo y referencias bancarias. No devuelve tokens de acceso, datos de tarjeta ni recibos completos. Los intentos nuevos con resultado incierto conservan etapa, código HTTP, código bancario de formato reconocido y categoría de fallo; los intentos anteriores pueden carecer de diagnóstico. Esta consulta lee datos locales: no repite ventas ni consulta al banco.
