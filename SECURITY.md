# Reporte privado de seguridad

No abrir una incidencia pública con detalles explotables o datos de un comercio. Utiliza [Report a vulnerability en GitHub](https://github.com/Y2KWebs/y2kwebs-lafise/security/advisories/new). Si ese canal no está disponible, contacta de forma privada con Y2KWebs mediante su sitio https://y2kwebs.com/ indicando que se trata de un reporte de seguridad del plugin. Antes de adjuntar evidencia sensible, acuerda un canal privado con el equipo.

Incluir versión, descripción, impacto y reproducción con datos ficticios. Excluir tarjetas, CVV, claves, credenciales, cookies, tokens de sesión y respuestas bancarias reales. No realizar cobros o pruebas en tiendas ajenas.

No se promete un SLA de seguridad ni un programa de recompensas. El soporte profesional puede acordarse por separado. La configuración del plugin no exige certificación bancaria; las limitaciones técnicas de la versión se documentan en el README.

## Datos y retención

Los campos de tarjeta no tienen atributo `name`; se cifran mediante JWE antes de enviarlos. El servidor no persiste tarjeta, CVV ni JWE. Los intentos y las acciones 3DS se cifran con AES-GCM usando material derivado de los salts de WordPress. El token de acceso bancario se conserva cifrado en la sesión WooCommerce por un máximo lógico de cinco minutos y se consume al reservar la venta. Las URLs de verificación son capacidades privadas: excluirlas de analítica, registros compartidos y referrers.

El cifrado de la base de datos no protege frente a una intrusión que también permita leer los salts del servidor. Rotarlos vuelve ilegibles los intentos existentes; preparar copia y conciliación antes de esa operación. APM, WAF, plugins de depuración y logs del servidor pueden capturar cuerpos HTTP: desactivar esa captura para el pago.

Desactivar o desinstalar conserva el historial financiero. No se eliminan tablas automáticamente. Definir retención y borrado controlado con el comercio después de resolver todas las operaciones pendientes.

Los resultados inciertos nuevos incluyen un diagnóstico mínimo en el registro cifrado del intento: operación, etapa, estado HTTP, código bancario de formato reconocido y categoría de fallo. No se guardan mensajes arbitrarios de excepciones ni cuerpos HTTP. La consulta administrativa de intentos exige `manage_woocommerce` y no devuelve tokens ni recibos completos; los compradores no tienen acceso a ella.
