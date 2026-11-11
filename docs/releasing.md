# Versiones y canales de distribución

Este árbol corresponde a **0.2.4**, con recuperación segura de fallos de autenticación y vencimiento diferenciado de etapas 3DS. La publicación se realiza tras integrar su PR con CI satisfactorio. El ZIP, checksum y registro final de publicación de esta versión se recogen en la [release v0.2.4](https://github.com/Y2KWebs/y2kwebs-lafise/releases/tag/v0.2.4); la versión del directorio puede comprobarse en [WordPress.org](https://wordpress.org/plugins/y2kwebs-lafise/). No se sustituyen artefactos publicados para añadir registros posteriores.

La versión **0.2.2** se publicó el 2 de noviembre de 2026 tras integrar el PR #3 con CI satisfactorio; revisión SVN `3780516`. Su [release preliminar](https://github.com/Y2KWebs/y2kwebs-lafise/releases/tag/v0.2.2) y los artefactos anteriores se conservan intactos. WordPress.org aprobó la inclusión y recibió la primera publicación el 2 de noviembre, revisión SVN `3780395`; los iconos y las cabeceras se añadieron en `3780418`.

La [documentación de WordPress.org](https://github.com/Y2KWebs/y2kwebs-lafise/blob/main/docs/wordpress-org.md) conserva los destinos, verificaciones y hashes del paquete publicado. Los pagos reales y las pruebas con banco simulado se distinguen en [validación](validation.md).

La actualización 0.2.2 añade inglés fuente y español incluido. Su publicación funcional es independiente de la aprobación comunitaria de las traducciones oficiales es_ES/es_CR. Conservar 0.2.1 y no dar por concluida la localización del directorio mientras existan propuestas pendientes o no se hayan generado sus paquetes oficiales.

## Cambios que no requieren una release

La documentación del repositorio, los enlaces de instalación, los registros de publicación y las fuentes de las imágenes se mantienen con commits y PR normales. El icono y la cabecera del directorio se publican en assets de SVN. Estos cambios no requieren una nueva etiqueta de GitHub, una Release ni un incremento de versión del plugin.

Conservar los ZIP y tags publicados. Una compilación de comprobación del árbol de desarrollo puede incluir documentación posterior a la publicación; no debe reemplazar silenciosamente los artefactos de una versión existente.

## Preparación de una nueva versión funcional

1. Actualizar la versión y las referencias correspondientes en cabecera, metadatos, pruebas y herramientas de empaquetado.
2. Ejecutar las comprobaciones de [desarrollo](development.md), registrar los resultados reales en [validación](validation.md) y revisar el diff completo. No copiar documentos ni evidencias bancarias privadas.
3. Ejecutar `python scripts/check_public.py` y las pruebas de empaquetado. Revisar por separado los archivos del repositorio y los seleccionados para el ZIP.
   Regenerar los catálogos con `python scripts/build_translations.py` y verificar `--check` antes de empaquetar. `scripts/prepare_wordpress_org.py` prepara cada versión por separado en `dist/wordpress-org/<versión>/`, sin sobrescribir candidatos anteriores.
4. Ejecutar `python scripts/package.py`. Comprobar instalación, activación, acceso a ajustes y Plugin Check sobre la copia extraída en el entorno local aislado. Si cambia un archivo empaquetado, regenerar el candidato y repetir las comprobaciones afectadas.
5. Integrar el PR cuando pasen las verificaciones y se resuelvan los comentarios de revisión. Crear el tag y la Release solo cuando corresponda publicar esa versión, siguiendo [CONTRIBUTING.md](../CONTRIBUTING.md).
6. Adjuntar el ZIP instalable verificado y su SHA-256. Marcar como pre-release las versiones preliminares. Conservar las versiones anteriores.
7. Para WordPress.org, actualizar trunk y crear el tag de la nueva versión en el repositorio SVN asignado. Mantener alineados cabecera, Stable tag y contenido de ambos destinos.
8. Verificar los resultados públicos de los canales utilizados; publicar código o generar un ZIP local no crea por sí solo una Release ni una versión en el directorio.

Las notas deben destacar las funciones realmente verificadas y sus límites. La prueba real de 0.2.1 acredita un desafío manual y un resultado financiero aprobado; no identifica si hubo fingerprint previo. No atribuir a 0.2.1 pruebas realizadas con 0.2.0 ni presentar pruebas simuladas como pagos reales. Enlazar el soporte profesional opcional de Y2KWebs.

No anunciar certificación, alianza o respaldo de LAFISE ni exclusividad en el mercado sin evidencia. Cualquier caso de éxito identificado requiere autorización del cliente. Los informes operativos y evidencias bancarias reales se conservan en privado.
