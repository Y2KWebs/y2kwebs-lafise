# Preparación de la versión preliminar 0.2.1

La versión 0.2.1 está preparada para revisión y todavía no se ha publicado. La release pública 0.2.0 y sus artefactos se conservan. El mantenedor ha instalado 0.2.1 y completado un pago real con desafío 3DS manual; pedido pagado e intento aprobado se comprobaron por API. Las notas deben distinguir los pagos reales verificados de las pruebas simuladas.

## Preparación local

1. Ejecutar las comprobaciones de [desarrollo](development.md), registrar los resultados reales en [validación](validation.md) y revisar el diff completo. No copiar documentos ni evidencias bancarias privadas.
2. Ejecutar `python scripts/check_public.py` y las pruebas de empaquetado. Revisar por separado los archivos candidatos del repositorio y los 36 archivos seleccionados para el ZIP.
3. Ejecutar `python scripts/package.py`. Comprobar instalación, activación y acceso a ajustes montando la copia extraída del paquete en el entorno local aislado. Regenerar el paquete y repetir el smoke si después cambia cualquiera de sus archivos.
4. Entregar `dist/y2kwebs-lafise-0.2.1.zip` y `dist/y2kwebs-lafise-0.2.1.zip.sha256` para revisión. No crear tags ni releases, hacer push o instalar en tiendas como parte de esta preparación.

## Publicación posterior

Solo cuando se solicite publicar, revisar de nuevo el destino remoto y las comprobaciones de CI sobre el commit definitivo. La futura release usará tag `v0.2.1`, título `0.2.1 — versión preliminar` y la opción **Set as a pre-release**. Se adjuntarán el ZIP verificado y su SHA-256, conservando 0.2.0. La preparación actual no ejecuta este paso.

Las notas deberán destacar los pagos reales comprobados en WooCommerce, la nueva firma del desafío directo y el rechazo Process3DS `59`, además de la ausencia de devoluciones automáticas y los casos pendientes. La prueba real de 0.2.1 acredita el desafío manual y el resultado financiero aprobado; todavía no se ha identificado si hubo fingerprint previo. No atribuir a 0.2.1 pruebas realizadas con 0.2.0. Enlazar el soporte profesional opcional de Y2KWebs.

No anunciar certificación, alianza o respaldo de LAFISE ni exclusividad en el mercado sin evidencia. Cualquier caso de éxito identificado requiere autorización del cliente. Los informes operativos y evidencias bancarias reales se conservan en privado.
