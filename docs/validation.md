# Validación 0.2.0 — versión preliminar

Registro actualizado el **30 de octubre de 2026**. Tras la preparación inicial se corrigió la disponibilidad del checkout cuando faltaba guardar el tipo de solicitud bancario y se añadió diagnóstico mínimo de resultados inciertos. Las suites automatizadas usan datos ficticios y transporte bancario simulado; las pruebas bancarias realizadas por el mantenedor se distinguen a continuación.

## Antecedentes de uso real

La integración de origen se ha utilizado con pagos reales, según la experiencia operativa comunicada por el mantenedor. La pasarela es funcional; los pendientes siguientes corresponden a casos concretos y a la ampliación del registro de cobertura. Los antecedentes de uso real no son una certificación bancaria ni implican que todos los escenarios hayan sido comprobados en el paquete público 0.2.0.

El 29 de octubre de 2026 el mantenedor completó manualmente un pago real de **USD 1** con la versión pública 0.2.0 en la instalación de demostración. Comunicó el cargo real y una comprobación administrativa de solo lectura confirmó el pedido completado, fecha de pago e identificador de transacción guardados, junto con un intento aprobado y disponibilidad de recibo y referencias bancarias. Las herramientas no enviaron ni repitieron el pago. Los datos del comercio y las evidencias identificativas se mantienen en privado.

Esta comprobación acredita ese pago completo con la configuración corregida del comercio; no acredita todas las ramas 3DS, la aprobación directa de Sale, otras monedas o combinaciones de impuestos. Los intentos anteriores pendientes conservan su historial y no se dan por conciliados a partir del éxito del nuevo pedido.

El **30 de octubre de 2026**, el mantenedor eliminó WordPress y lo instaló de nuevo en el mismo subdominio, instaló y configuró el plugin y comunicó otra prueba real satisfactoria con 3DS. La comprobación posterior por API autenticada confirmó **WordPress 7.1.4, WooCommerce 11.2.1, plugin 0.2.0 activo**, un pedido de USD 1 pagado en estado `processing`, referencia de transacción guardada e intento aprobado con recibo y referencias disponibles. La reinstalación y la interacción 3DS fueron realizadas y comunicadas por el mantenedor; las herramientas solo consultaron el resultado. No se publican identificadores del comercio, compradores ni intentos.

## Resultados ejecutados

| Comprobación | Resultado |
|---|---|
| Sintaxis PHP 8.4.0 | 23 archivos PHP, sin errores |
| Protocolo y utilidades | 41 aserciones superadas |
| WebCrypto / JWE, Node.js 24.15.0 | 2 pruebas superadas: descifrado independiente, integridad del encabezado, aleatoriedad y claves incompatibles |
| Transporte y diagnóstico bancario | 18 aserciones superadas: errores de conexión, HTTP, formato, campos, firma, preparación sin envío y exclusión de contenido privado |
| WordPress, almacenamiento clásico | 36 aserciones de integración superadas |
| WordPress, HPOS | 36 aserciones de integración superadas |
| Ajustes nativos y API administrativa | 17 aserciones superadas, incluidas cuatro sobre el tipo de solicitud ausente, vacío o explícito |
| Empaquetado, Python 3.11.4 | 5 pruebas superadas: lista permitida, exclusión de material adicional, SHA-256/reproducibilidad, secretos, archivos ausentes, versión y formato de capturas públicas |
| Copia extraída del ZIP instalable | Activación y 10 aserciones superadas: versión, exclusión de tests/herramientas, HTML de ajustes, permisos y API sin secretos; solicitudes externas bloqueadas |
| Checkout de bloques en navegador Chrome | Pasarela visible y seleccionable, cuatro campos de tarjeta presentes, sin errores de consola observados; no se enviaron datos de tarjeta ni pagos |
| Prueba real completada por el mantenedor | USD 1; pedido completado y pagado; intento aprobado, con recibo y referencias disponibles; comprobación posterior de solo lectura |
| Reinstalación limpia y nueva prueba real con 3DS | Realizadas por el mantenedor el 30/10; WordPress 7.1.4 / WooCommerce 11.2.1; pago de USD 1 y pedido pagado comprobados por API |

Entorno de integración: WordPress 6.9.4, WooCommerce 10.3.0, PHP 8.2.35 en WP-CLI y MariaDB 11.4.13. El servidor web del contenedor usa PHP 8.2.31. Se utilizó exclusivamente el entorno local en loopback, sin credenciales reales ni cobros. El modo de almacenamiento inicial se restauró al finalizar.

La integración comprueba registro de bloques, CSRF, límites de prueba, deduplicación, callbacks manipulados/repetidos, stock, respuestas inciertas, bloqueo concurrente y reintentos tras autenticación caducada. La suite de ajustes comprueba cifrado, caracteres especiales, conservación de secretos al dejar campos vacíos, denegación a invitados, modos Normal/Pruebas y borrado de credenciales.

La comprobación automatizada del ZIP se ejecutó montando su copia extraída como directorio del plugin en el WordPress local existente. Se verificaron la activación, el HTML de ajustes y el acceso administrativo a la API. La instalación nueva y la prueba real posterior fueron realizadas por el mantenedor, según el registro anterior. Las capturas públicas proceden de una tienda local distinta, con catálogo y credenciales ficticios.

## Corrección de disponibilidad del checkout

Los ajustes guardados parcialmente podían omitir `type_request`. El administrador mostraba `Production` como valor predeterminado, pero la comprobación de disponibilidad lo consideraba ausente y ocultaba la pasarela. Ahora se aplica el mismo valor predeterminado al leer los ajustes. Se conservan los valores explícitos del comercio; un valor guardado vacío sigue siendo inválido. El test nuevo reprodujo el fallo antes de la corrección y las 17 aserciones de ajustes pasaron después; se repitieron también las 41 aserciones unitarias y las 32 de integración en cada almacenamiento.

## Diagnóstico de resultados inciertos

Los intentos nuevos distinguen preparación, transporte, decodificación y validación mediante datos mínimos cifrados. Se verificó la consulta administrativa autenticada y la denegación a invitados, tanto en integración local como en la instalación de demostración. No se reenvió ninguna venta durante esa comprobación. Un fallo local al construir la solicitud se clasifica como no enviado; un error después de iniciar el transporte sigue siendo incierto.

Los intentos antiguos que solo conservaron `bank_result_unavailable` no permiten reconstruir el código HTTP, el código bancario ni el punto concreto de fallo. Si carecen de recibo y referencias bancarias, requieren comprobación del comercio antes de volver a pagar. Añadir diagnóstico no confirma ni descarta un cargo anterior y no constituye una prueba financiera superada. Tras este cambio pasaron las 18 aserciones de transporte y las 36 de integración por cada almacenamiento.

## Revisión de publicación

- Revisión de los archivos candidatos al primer commit y de sus URLs, credenciales de ejemplo e identificadores de fixtures. Las respuestas de tests y los accesos Docker publicados son sintéticos.
- Comprobación de patrones conocidos de secretos, rutas excluidas y enlaces Markdown locales mediante `python scripts/check_public.py`. Esta comprobación complementa la revisión manual; no identifica todos los secretos posibles.
- ZIP generado desde 36 archivos seleccionados explícitamente, incluidas dos capturas locales con datos ficticios y la guía de contribución. Tests, scripts, diagnósticos, borradores comerciales y configuraciones locales quedan fuera del paquete. El empaquetado genera un SHA-256.
- Propuestas comerciales y artefactos antiguos apartados fuera del repositorio. Documentación pública sin referencias identificativas al comercio utilizado durante el desarrollo.
- La release `v0.2.0` se distribuye como **pre-release**, según la [guía de publicación](releasing.md). El código conserva las pruebas y las plantillas de incidencias; GitHub Actions comprueba sintaxis, protocolo, transporte, JWE, publicación, empaquetado e integración clásica/HPOS sin utilizar credenciales bancarias reales.

Los comandos para reproducir las suites están en [desarrollo](development.md). El historial financiero local se conserva; no se eliminan las tablas del plugin al desactivarlo.

## Casos pendientes y ampliación de cobertura

- Estructura, firma y tratamiento de aprobación directa de Sale y códigos de rechazo definitivo.
- Ampliación de la evidencia por moneda (CRC/USD) e impuestos. Se ha comprobado un pago real de USD 1, pero no consta una matriz completa de esos casos en este repositorio.
- Matriz completa de navegadores y móviles, incluido Safari; la cobertura publicada no comprende todas las combinaciones.

Las devoluciones automáticas mediante Reversed son una funcionalidad aún no implementada, no simplemente una prueba pendiente. Mientras tanto, se gestionan en el banco y se registran manualmente en WooCommerce.

No se dan por superados los casos pendientes. Las respuestas no reconocidas se conservan para consulta/revisión y no habilitan reintentos automáticos. La comprobación de conexión solo acredita autenticación y formato de llave pública; no sustituye un pago completo.

La matriz de [pruebas bancarias controladas](pilot.md) permite repetir y ampliar la verificación. La preparación pública no constituye una aprobación de LAFISE.
