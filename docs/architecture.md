# Arquitectura y flujo de pago

El plugin conecta directamente la tienda con la API REST de LAFISE Costa Rica. Y2KWebs no opera un intermediario de pagos. La versión 0.2.0 es preliminar; consulta la [validación](validation.md).

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

## Límites y datos

No se persisten tarjeta, CVV ni JWE. El historial financiero se conserva al desactivar o desinstalar. Los salts de WordPress forman parte del material de cifrado; su rotación requiere preparar la conciliación y las copias correspondientes. Véase [seguridad](../SECURITY.md).

El modo Pruebas limita usuarios e importe, pero no es un sandbox bancario. Las devoluciones bancarias automáticas no están implementadas. La configuración del protocolo contiene endpoints y marcadores de campos, nunca credenciales de un comercio.
