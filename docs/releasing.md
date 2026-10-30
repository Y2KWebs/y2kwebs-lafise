# Publicación de la versión preliminar 0.2.0

La primera publicación se presenta como **preliminar**: una pasarela funcional basada en una integración utilizada con pagos reales, con casos específicos pendientes de comprobar. Las notas deben distinguir esos antecedentes, las pruebas automatizadas y el pago real de USD 1 ya verificado con este paquete. El número de versión y el campo `Stable tag` identifican el paquete; la cobertura se describe en la documentación.

1. Ejecutar las comprobaciones de [desarrollo](development.md), registrar los resultados reales en [validación](validation.md) y revisar manualmente los archivos candidatos al commit.
2. Ejecutar `python scripts/check_public.py` y `python scripts/package.py`. Revisar el ZIP y su SHA-256. Conservar tests y herramientas en el repositorio; distribuir solo el paquete generado.
3. Revisar los cambios y el destino remoto antes de crear el primer commit público. No añadir archivos ignorados con `git add -f`.
4. Crear la release de GitHub con tag `v0.2.0`, título `0.2.0 — versión preliminar` y la opción **Set as a pre-release** activada. Adjuntar `y2kwebs-lafise-0.2.0.zip` y `y2kwebs-lafise-0.2.0.zip.sha256`. Publicar el código o generar el ZIP local no crea por sí solo esta release.
5. Incluir en las notas los resultados de validación, la ausencia de devoluciones automáticas y los flujos bancarios pendientes. Enlazar el soporte profesional opcional de Y2KWebs.

No anunciar certificación, alianza o respaldo de LAFISE ni exclusividad en el mercado sin evidencia. Cualquier caso de éxito identificado requiere autorización del cliente. Los informes operativos y evidencias bancarias reales se conservan en privado.
