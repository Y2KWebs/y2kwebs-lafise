# Validación 0.2.1 — versión preliminar preparada para revisión

Registro actualizado el **31 de octubre de 2026**. La versión 0.2.1 incorpora la firma del desafío 3DS directo y la clasificación segura del rechazo `59` de Process3DS. El mantenedor la ha instalado y completado un pago real con desafío 3DS manual; pedido pagado e intento aprobado se comprobaron por API autenticada. La actualización sigue pendiente de publicación. Las pruebas reales y las suites automatizadas con banco simulado se distinguen a continuación.

## Antecedentes de uso real

El plugin ha procesado pagos reales en WooCommerce. Los pendientes corresponden a escenarios concretos y a la ampliación del registro de cobertura; las pruebas ejecutadas no constituyen una certificación bancaria.

El 29 de octubre de 2026 el mantenedor completó manualmente un pago real de **USD 1** con la versión pública 0.2.0 en la instalación de demostración. Comunicó el cargo real y una comprobación administrativa de solo lectura confirmó el pedido completado, fecha de pago e identificador de transacción guardados, junto con un intento aprobado y disponibilidad de recibo y referencias bancarias. Las herramientas no enviaron ni repitieron el pago. Los datos del comercio y las evidencias identificativas se mantienen en privado.

Esta comprobación acredita ese pago completo con la configuración corregida del comercio; no acredita todas las ramas 3DS, la aprobación directa de Sale, otras monedas o combinaciones de impuestos. Los intentos anteriores pendientes conservan su historial y no se dan por conciliados a partir del éxito del nuevo pedido.

El **30 de octubre de 2026**, el mantenedor eliminó WordPress y lo instaló de nuevo en el mismo subdominio, instaló y configuró el plugin y comunicó otra prueba real satisfactoria con 3DS. La comprobación posterior por API autenticada confirmó **WordPress 7.1.4, WooCommerce 11.2.1, plugin 0.2.0 activo**, un pedido de USD 1 pagado en estado `processing`, referencia de transacción guardada e intento aprobado con recibo y referencias disponibles. La reinstalación y la interacción 3DS fueron realizadas y comunicadas por el mantenedor; las herramientas solo consultaron el resultado. No se publican identificadores del comercio, compradores ni intentos.

## Pago real con desafío manual en 0.2.1

El **31 de octubre de 2026**, el mantenedor instaló 0.2.1 y comunicó un pago satisfactorio en el que completó un desafío manual de su banco. Una consulta posterior de solo lectura confirmó **plugin 0.2.1 activo**, pedido de **USD 1 pagado** en estado `processing`, identificador de transacción guardado e intento `approved`, con recibo y referencias bancarias disponibles. La instalación, los datos de tarjeta y la interacción con el banco fueron realizados por el mantenedor; las herramientas únicamente comprobaron el resultado.

Esta prueba acredita un pago completo con desafío 3DS manual en 0.2.1. La API administrativa consultada no expone la secuencia inicial de autenticación; no permite distinguir si el desafío llegó directamente desde Sale o después de un fingerprint. Esa distinción queda pendiente en la cobertura por ramas. No se publican datos del comprador, identificadores de la operación ni URLs con tokens.

## Evidencia técnica del protocolo

La regla específica de Sale `3DS-PRC-002` se contrastó con una firma bancaria real: `SHA256(MerchantPayID + Ref1_bancario + ResponseCode + Amount_original + Merchant_Id + Tax_solicitud + Currency_code)`, normalizando la referencia bancaria nula como cadena vacía y conservando las unidades menores. El rechazo Process3DS `59` se apoya en evidencia bancaria revisada con firma y datos financieros coincidentes, autorización vacía y rechazo confirmado. Estas comprobaciones respaldan el contrato y se distinguen de los pagos completos ejecutados con este paquete; no autorizan reconstruir desafíos antiguos ni clasificar resultados financieros directos de Sale.

Los fixtures contienen exclusivamente datos ficticios. Las respuestas reales y la documentación identificativa se mantienen en privado. Las herramientas no han enviado Sale, Process3DS ni nuevos cobros reales durante la preparación o revisión.

## Resultados locales de 0.2.1

Todas las comprobaciones siguientes se ejecutaron el 31/10/2026 con datos ficticios y sin llamadas bancarias reales:

| Comprobación | Resultado |
|---|---|
| Sintaxis PHP 8.4.0 | 24 archivos PHP sin errores, incluida la nueva suite de seguridad |
| Protocolo, PHP 8.2.35 y 8.4.0 | 53 aserciones superadas en cada versión |
| Transporte y diagnóstico, PHP 8.2.35 y 8.4.0 | 40 aserciones superadas en cada versión; contratos inválidos bloquean todo HTTP y el conflicto de autorización usa un diagnóstico fijo |
| WebCrypto / JWE, Node.js 24.15.0 | 2 pruebas superadas |
| WordPress, almacenamiento clásico | 65 aserciones de integración superadas; almacenamiento `no` comprobado |
| WordPress, HPOS | 65 aserciones de integración superadas; almacenamiento `yes` comprobado |
| Ajustes nativos y API administrativa | 17 aserciones superadas |
| Entradas, HTML y callbacks HTTP locales | 30 aserciones de seguridad superadas; conservación de bytes, iframe y campos, recursos registrados, cabeceras y rechazo/deduplicación de callbacks |
| Plugin Check 2.1.0 sobre el paquete instalable | Comprobaciones estáticas y de ejecución sin errores ni avisos reportados; sin excluir categorías ni códigos por opciones de CLI |
| Empaquetado, Python 3.11.4 | 5 pruebas superadas, incluida reproducibilidad y exclusión de material privado ficticio |
| Copia extraída del ZIP 0.2.1 | Activación y 10 aserciones superadas: versión, exclusión de herramientas/tests, HTML de ajustes, permisos y API sin secretos; todo HTTP externo bloqueado |
| Revisión del repositorio y del paquete | 57 candidatos públicos y 36 archivos del ZIP revisados por separado; sin referencias identificativas a comercios o compradores; enlaces Markdown locales válidos |
| Workflow y artefactos | actionlint sin errores; ZIP reproducible con SHA-256; los artefactos locales 0.2.0 conservan sus hashes |

La integración añade desafío directo → callback → aprobación financiera, callbacks manipulados y repetidos, confirmación única y stock reducido una vez. También verifica `59` válido, firma inválida, autorización contradictoria y código no reconocido: solo el rechazo validado habilita un nuevo intento solicitado por el comprador. Un CRes con `transStatus=Y` no demuestra por sí solo un cobro aprobado. Las pruebas de contrato rechazan variantes sin campos obligatorios, códigos financieros/desconocidos, otra operación, ausencia de bearer, solapamiento financiero y algoritmos no admitidos.

El entorno local conserva WordPress 6.9.4, WooCommerce 10.3.0 y PHP 8.2.35 en WP-CLI. Se restauró el almacenamiento clásico inicial tras la integración. Estos resultados no se atribuyen a una ejecución nueva de GitHub Actions ni a una prueba real en la demo. No se han reabierto ni revalidado intentos antiguos del comercio.

La comprobación del paquete usa exclusivamente el WordPress local existente y monta la copia extraída del ZIP como plugin. Verifica activación y acceso a ajustes; no acredita una instalación nueva en la demo ni una nueva prueba visual de navegador. Las capturas y comprobaciones de navegador siguientes corresponden a 0.2.0.

## Resultados de la publicación 0.2.0

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

- Cobertura real diferenciada del desafío directo desde Sale y del recorrido con fingerprint previo. El pago manual de 0.2.1 ya está comprobado; falta identificar su rama inicial y ampliar el registro por recorridos.
- Estructura, firma y tratamiento de aprobación y rechazo financieros directos de Sale, y códigos Process3DS distintos de `00`/`59`.
- Ampliación de la evidencia por moneda (CRC/USD) e impuestos. Se ha comprobado un pago real de USD 1, pero no consta una matriz completa de esos casos en este repositorio.
- Matriz completa de navegadores y móviles, incluido Safari; la cobertura publicada no comprende todas las combinaciones.

Las devoluciones automáticas mediante Reversed son una funcionalidad aún no implementada, no simplemente una prueba pendiente. Mientras tanto, se gestionan en el banco y se registran manualmente en WooCommerce.

## Revisión de Plugin Check

El informe recibido el 31/10 contenía 7 errores y 35 avisos. Se corrigieron el escape de HTML y mensajes, la lectura de entradas y variables de servidor, el registro de CSS/JavaScript en las páginas independientes, el uso de `wp_parse_url`, los identificadores de tabla preparados con `%i` y la cabecera `Tested up to: 7.1`, basada en la instalación previamente comprobada. Las listas de HTML permitidas conservan los campos sin `name`, los selectores de JavaScript y el iframe 3DS con su sandbox.

Los avisos de nonce, constante de caché y SQL directo que corresponden a mecanismos deliberados se justifican en líneas concretas del código: autorización CSRF o token/correlación bancaria, interoperabilidad con cachés y lectura/escritura de intentos cifrados bajo bloqueo sin caché de estados financieros. La comparación con los bytes originales rechaza cualquier cambio de la sanitización. No hay exclusiones globales de reglas.

Plugin Check 2.1.0 se ejecutó sobre la copia extraída del ZIP, también con su bootstrap de ejecución: no reportó errores ni avisos. Se repitieron las 65 aserciones de integración por almacenamiento, las 17 de ajustes, las suites de protocolo/transporte, WebCrypto y empaquetado, y se añadieron 30 aserciones de seguridad con HTTP entre contenedores. El pago real documentado arriba se realizó antes de estos ajustes de Plugin Check; las comprobaciones posteriores son automatizadas con banco simulado y no se ha efectuado otro cargo real desde las herramientas.

No se dan por superados los casos pendientes. Las respuestas no reconocidas se conservan para consulta/revisión y no habilitan reintentos automáticos. La comprobación de conexión solo acredita autenticación y formato de llave pública; no sustituye un pago completo.

La matriz de [pruebas bancarias controladas](pilot.md) permite repetir y ampliar la verificación. La preparación pública no constituye una aprobación de LAFISE.
