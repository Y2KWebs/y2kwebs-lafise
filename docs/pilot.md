# Guía de pruebas bancarias controladas

Esta guía describe cómo validar la versión preliminar en un entorno de pruebas autorizado por el comercio. Utiliza un catálogo ficticio y configura las credenciales directamente en WooCommerce. No copies credenciales, respuestas reales ni datos de compradores al repositorio o a incidencias públicas.

La matriz siguiente sirve para repetir y ampliar la verificación bancaria de una pasarela con antecedentes de pagos reales. No es un registro de pruebas superadas para todas las combinaciones del paquete público. Las suites automatizadas usan un banco simulado. Los antecedentes, resultados y casos pendientes se distinguen en [validación](validation.md).

## Preparación

1. Instalar el ZIP y configurar WooCommerce, HTTPS, enlaces permanentes y catálogo ficticio. Mostrar un aviso de demo y desactivar indexación.
2. Introducir las credenciales desde los ajustes de LAFISE. Seleccionar **Pruebas** y un máximo bajo por operación.
3. Comprobar conexión sin cobro.
4. Coordinar tarjeta, horario, importe por operación, presupuesto total y devolución. No hacer cargos por el mero hecho de disponer de acceso.

No hay un paso de aprobación/certificación en el plugin. Las comprobaciones siguientes son pruebas del software y del flujo bancario, no formularios que cada usuario deba completar.

## Matriz

| Caso | Resultado esperado |
|---|---|
| Fingerprint y desafío | Un intento, UUID correlacionado, firma y resultado financiero válidos |
| Desafío directo desde Sale | Firma que incluye código y unidades menores; pago confirmado solo tras el resultado financiero final |
| Process3DS con rechazo 59 | Rechazo validado sin autorización permite otro intento solicitado por el comprador; contradicciones o firma inválida permanecen en revisión |
| Emisor nuevo | Destino recibido del transporte bancario autenticado; no listas mundiales de dominios |
| Autenticación caducada sin siguiente envío | Cerrar intento y permitir otro pago del mismo pedido |
| Timeout tras envío financiero | Retener pedido y consultar; nunca repetir automáticamente |
| CRC, USD e impuestos | Importes y terminal correspondientes, sin conversión de moneda |
| Recargas y doble clic | Sin ventas ni confirmaciones duplicadas |
| Navegadores y móviles | Campos utilizables, cifrado previo y desafío visible |

## Limitaciones técnicas actuales

La aprobación directa de Sale todavía requiere comprobar su estructura y firma. El plugin conserva las respuestas no reconocidas para consulta/revisión. Los códigos de rechazo definitivo aún no incorporados siguen el mismo criterio.

La devolución automática mediante Reversed no está implementada porque falta una respuesta y mecanismo de conciliación comprobados. Hasta incorporarlos, efectuar la devolución en LAFISE y registrarla manualmente en WooCommerce. Es una limitación de esta versión, no una exigencia de certificación al comercio.

## Cierre

Revisar todos los pedidos y movimientos. Usar «Consultar estado en LAFISE (sin nuevo cobro)» cuando sea necesario. Sin Ref3 y recibo correlacionable, revisar el portal; no emparejar movimientos solo por importe/fecha. Deshabilitar el pago real al terminar la sesión de demo.
