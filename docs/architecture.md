# Arquitectura y flujo de pago

El plugin conecta directamente la tienda con la API REST de LAFISE Costa Rica. Y2KWebs no opera un intermediario de pagos. La versión 0.2.2 es preliminar; consulta la [validación](validation.md).

## Componentes

- El archivo principal carga las clases y registra activación, desactivación y compatibilidad HPOS.
- `Gateway`, `Blocks` y los recursos del navegador integran los formularios en los dos checkouts de WooCommerce.
- `Settings`, `Config` y `Secrets` gestionan ajustes y secretos cifrados. La API administrativa aplica el permiso `manage_woocommerce`.
- `Bank`, `Protocol`, `Money` y `ThreeDS` gestionan transporte, contrato bancario, importes, firmas y correlación 3DS.
- `Payments`, `Store` y `Routes` coordinan intentos, estados, callbacks y conciliación. Los pedidos se gestionan mediante las APIs de WooCommerce.

## Flujo

1. La tienda obtiene del banco la llave pública de transporte. El navegador cifra los datos de tarjeta con JWE antes de enviarlos a WordPress; las credenciales bancarias del servidor no llegan al navegador.
2. El servidor valida acceso, importe y correlación del intento, reserva la operación y solicita la venta al banco.
3. Si el banco solicita fingerprint o desafío 3DS, el plugin prepara la acción y valida su correlación antes de procesar el resultado.
4. Una respuesta financiera reconocida debe superar las comprobaciones de firma, importe, moneda y comercio. Una respuesta incierta conserva el pedido en espera para consulta o revisión.
5. La consulta de estado no repite la venta. El almacenamiento cifrado de intentos y los bloqueos de concurrencia apoyan la deduplicación.

## Firma de desafío directo y rechazo validado

El contrato interno admite `signature_by_code` exclusivamente para continuaciones 3DS de Sale con transporte bearer. La variante de `3DS-PRC-002` firma `merchant_pay_id + bank_reference + code + amount + merchant + tax + currency` mediante SHA-256; el importe conserva las unidades menores de Sale y una referencia bancaria nula se convierte en cadena vacía. La selección usa una sola regla por código, sin alternativas por descarte. Fingerprint conserva la firma sin código y Process3DS conserva su contrato anterior. Las variantes deben incluir código, secreto, referencia, importe, comercio y moneda, y no pueden clasificar aprobaciones ni rechazos financieros. El contrato se valida antes de HTTP y al validar las respuestas.

Process3DS clasifica `59` como rechazo solo con firma y datos financieros válidos y autorización vacía. Una autorización contradictoria produce un resultado incierto con diagnóstico fijo `decline_authorization_conflict`; no habilita otro cobro. Las listas de aprobación y rechazo financieros de Sale siguen vacías. El desafío y el CRes por sí solos no completan el pedido: se requiere un resultado financiero validado. No cambian los endpoints públicos, ajustes ni esquema de almacenamiento.

## Límites y datos

Las continuaciones 3DS de Sale y del JSON de Process3DS admiten mayúsculas distintas únicamente en el último segmento de sus rutas (`Action`, `Creq`, `ThreeDSSessionData`, `threeDSMethodURL`, `threeDSMethodData`). El contenedor, los campos financieros y los callbacks POST mantienen sus nombres exactos. Dos claves equivalentes se rechazan, aunque sus valores coincidan. La adaptación conserva los valores originales y no cambia destinos permitidos, firmas, importes ni UUID.

Un fallo 3DS guarda un submotivo fijo en `diagnostic.three_ds_failure`, sin cuerpos bancarios ni textos arbitrarios. Las consultas inconclusas conservan ese diagnóstico principal y su motivo; una aprobación posterior validada retira el fallo activo. Un fallo inicial de preparación o un rechazo de autenticación demostrado permite un nuevo intento solicitado por el comprador; un envío con resultado financiero incierto permanece bloqueado. No se añaden tablas ni migraciones.

No se persisten tarjeta, CVV ni JWE. El historial financiero se conserva al desactivar o desinstalar. Los salts de WordPress forman parte del material de cifrado; su rotación requiere preparar la conciliación y las copias correspondientes. Véase [seguridad](../SECURITY.md).

El modo Pruebas limita usuarios e importe, pero no es un sandbox bancario. Las devoluciones bancarias automáticas no están implementadas. La configuración del protocolo contiene endpoints y marcadores de campos, nunca credenciales de un comercio.

## Recuperación de autenticación 0.2.4

Cada etapa validada conserva contexto cifrado con etapa, UUID, referencia local, código de procedencia y hash del recibo original validado. El callback se valida y deduplica bajo el bloqueo del pedido; se persiste el marcador de envío antes de Process3DS. Una continuación validada abre una nueva etapa pendiente de callback, sin reemplazar el recibo financiero original.

Solo una respuesta autenticada de Process3DS con `ResponseCode: 3DS-RES-003`, vinculada a la continuación enviada de un pedido sin pagar y al recibo, importe, comercio, terminal y moneda originales, produce `declined` con `authentication_failed`. No es un rechazo financiero genérico ni una excepción a sus firmas. Autorizaciones, referencias financieras, firmas o datos contradictorios en nombres alternativos o campos anidados mantienen el resultado incierto. Se conservan únicamente el motivo y diagnóstico controlados, nunca cuerpos bancarios o mensajes del emisor.

Los nuevos fingerprints esperan 60 segundos y los desafíos manuales 600, sin superar el vencimiento global. El vencimiento se comprueba al abrir la confirmación, consultar estado, recibir callbacks, iniciar otro intento y conciliar. La programación de cada etapa sustituye el evento inicial para evitar que la deduplicación de cron suprima el plazo más corto. El cron de WordPress depende de su ejecución habitual; las consultas y callbacks también aplican el límite.

Solo una etapa pendiente de callback, sin evidencia financiera y con pedido sin pagar, vence como `authentication_expired`; registra `fingerprint_callback_timeout` o `challenge_callback_timeout` en `diagnostic.three_ds_failure`. Si hay evidencia financiera o un envío incierto, se retira la acción caducada y queda `unknown`, sin permiso de reintento. Los vencimientos ya persistidos se conservan y los intentos sin contexto suficiente no reciben la nueva clasificación de rechazo. No hay migraciones ni modificaciones masivas.
