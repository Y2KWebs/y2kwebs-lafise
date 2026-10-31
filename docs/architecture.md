# Arquitectura y flujo de pago

El plugin conecta directamente la tienda con la API REST de LAFISE Costa Rica. Y2KWebs no opera un intermediario de pagos. La versión 0.2.1 es preliminar; consulta la [validación](validation.md).

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

No se persisten tarjeta, CVV ni JWE. El historial financiero se conserva al desactivar o desinstalar. Los salts de WordPress forman parte del material de cifrado; su rotación requiere preparar la conciliación y las copias correspondientes. Véase [seguridad](../SECURITY.md).

El modo Pruebas limita usuarios e importe, pero no es un sandbox bancario. Las devoluciones bancarias automáticas no están implementadas. La configuración del protocolo contiene endpoints y marcadores de campos, nunca credenciales de un comercio.
