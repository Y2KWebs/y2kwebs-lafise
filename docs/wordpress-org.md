# Publicación en WordPress.org

El plugin se distribuye en [WordPress.org](https://wordpress.org/plugins/y2kwebs-lafise/) con el slug `y2kwebs-lafise`, bajo la cuenta `y2kwebs`. Este árbol corresponde a **0.2.3**. Su publicación usa el ZIP del CI aprobado y actualiza únicamente `trunk` y el nuevo tag, conservando las versiones anteriores y los assets. La revisión SVN, hashes y comprobaciones públicas finales se registran en las notas de la [release v0.2.3](https://github.com/Y2KWebs/y2kwebs-lafise/releases/tag/v0.2.3), sin modificar el paquete ya publicado. Los registros históricos siguientes corresponden a publicaciones previamente verificadas.

Los comercios pueden instalarlo desde el directorio de WordPress o descargar el ZIP de su ficha. Los requisitos, el servicio bancario externo y las limitaciones están en [readme.txt](../readme.txt); las pruebas reales y simuladas se distinguen en [validación](validation.md).

## Registro de inclusión y publicación

| Fecha | Hito |
|---|---|
| 31 de octubre de 2026 | Solicitud de inclusión enviada por el mantenedor. El slug generado inicialmente se cambió a `y2kwebs-lafise`, coincidiendo con la carpeta instalada y el dominio de traducción. |
| 2 de noviembre de 2026 | Inclusión aprobada por el equipo de plugins; repositorio SVN asignado: https://plugins.svn.wordpress.org/y2kwebs-lafise/. |
| 2 de noviembre de 2026 | Primera publicación, revisión [3780395](https://plugins.trac.wordpress.org/changeset/3780395/y2kwebs-lafise): contenido 0.2.1 en trunk y tag, más dos capturas con datos ficticios. |
| 2 de noviembre de 2026 | Iconos y cabeceras publicados en assets mediante la revisión [3780418](https://plugins.trac.wordpress.org/changeset/3780418/y2kwebs-lafise). |
| 2 de noviembre de 2026 | Publicación bilingüe 0.2.2 en trunk y tag, revisión [3780516](https://plugins.trac.wordpress.org/changeset/3780516/y2kwebs-lafise), tras integrar el [PR #3](https://github.com/Y2KWebs/y2kwebs-lafise/pull/3) con CI satisfactorio. |

La aprobación del directorio corresponde al plugin independiente de Y2KWebs; no implica certificación, alianza ni respaldo de LAFISE.

## Comprobaciones de la publicación 0.2.2

CI pasó en PHP 8.2/8.4, integración WordPress clásica/HPOS, matriz de seis idiomas, ZIP instalable y Plugin Check. La API del directorio identifica 0.2.2 y la [descarga pública](https://downloads.wordpress.org/plugin/y2kwebs-lafise.0.2.2.zip) contiene los mismos 40 archivos del ZIP verificado, byte por byte, incluidos POT/PO/MO. Se comprobaron trunk y tag contra ese ZIP antes del envío y se conservaron los 36 archivos del tag 0.2.1.

SHA-256 del ZIP verificado y adjunto a GitHub: `07560f0901c4b020cfd79050b56bec7a36550fba8a739135d3a39b10d049253f`.

SHA-256 del ZIP generado por WordPress.org: `51c86990c0121d5ed54e65d7cc15136e2e6d02e864a49d3caddfb8cd95b6f7d0`.

Las diferencias de compresión no afectan a la igualdad del contenido. Los registros de publicación posteriores se mantienen mediante PR de documentación; no se reenvían al tag publicado ni generan otra release.

## Comprobaciones de la publicación 0.2.1

Antes de subir se verificaron el ZIP revisado y los hashes de los 74 archivos preparados para SVN: 36 en trunk, otros 36 en el tag y dos capturas. Las comprobaciones del candidato incluyeron 5 pruebas de empaquetado, 10 aserciones sobre la copia instalada del ZIP y Plugin Check 2.1.0 sin errores ni avisos reportados.

Después del commit se comprobaron:

- Ficha pública con HTTP 200 y API del directorio identificando versión `0.2.1`.
- [Descarga del directorio](https://downloads.wordpress.org/plugin/y2kwebs-lafise.0.2.1.zip): los mismos 36 archivos del ZIP revisado, byte por byte.
- Dos capturas presentes en la API y accesibles como JPEG con HTTP 200.
- Cuatro PNG de iconos/cabeceras disponibles en la CDN, con hashes coincidentes con las [exportaciones del repositorio](../design/wordpress-org/README.md).

SHA-256 del ZIP original revisado: `29377115464350d7072cd323ae554808afae1742863b3d16abd59a46d619cd9b`.

SHA-256 del ZIP generado por WordPress.org: `8062af7c9b95d064767bc25570e4a4961fbb9388b3d63fe6bd8dfae98fbb3844`.

Los hashes de los contenedores ZIP difieren porque el directorio genera su propio archivo comprimido; se verificó la igualdad del contenido descomprimido. La documentación del árbol de desarrollo se actualizó después de publicar y no se ha reenviado al tag 0.2.1.

En la comprobación inmediata de las imágenes, la CDN ya servía los cuatro PNG pero la ficha y la API todavía devolvían el icono anterior. Es un estado de aquella comprobación, no una verificación permanente del estado de caché.

## Organización de SVN

```text
assets/
  screenshot-1.jpg
  screenshot-2.jpg
  banner-772x250.png
  banner-1544x500.png
  icon-128x128.png
  icon-256x256.png
trunk/
  y2kwebs-lafise.php
  readme.txt
  ...
tags/
  0.2.1/
    y2kwebs-lafise.php
    readme.txt
    ...
```

`assets/` contiene los gráficos de la ficha. El código instalable vive directamente en trunk y en cada tag, sin otra carpeta envolvente. Los tests, las herramientas y las fuentes de diseño permanecen en GitHub, fuera del ZIP instalable.

## Mantenimiento del directorio

1. Desarrollar y revisar los cambios mediante ramas y PR de GitHub, según [CONTRIBUTING.md](../CONTRIBUTING.md).
2. Documentación de GitHub e imágenes del directorio pueden actualizarse sin crear una nueva release ni incrementar la versión del plugin.
3. Para cambios funcionales, preparar una versión nueva y ejecutar las verificaciones de [desarrollo](development.md). Publicar la misma versión funcional en los canales de distribución previstos.
4. Usar `scripts/package.py` y `scripts/prepare_wordpress_org.py` para preparar candidatos. Esos comandos generan archivos locales; no publican nada. No regenerar el ZIP 0.2.1 del árbol modificado para reemplazar el artefacto ya publicado.
5. Actualizar el checkout del repositorio SVN aprobado. Revisar los archivos y el diff; publicar únicamente los destinos previstos. Los cambios de icono/cabecera se limitan a assets; los cambios de código requieren el número de versión y el tag correspondiente.
6. Verificar después la ficha, API, capturas y descarga. Evitar commits repetidos de los mismos archivos para intentar acelerar la caché.

La autenticación SVN usa el usuario `y2kwebs` y su contraseña SVN específica. Mantener esa contraseña fuera del repositorio y de los argumentos de línea de comandos. Al pasarla desde Python en Windows a un cliente Linux, enviar bytes UTF-8 con LF; el modo texto puede añadir un retorno de carro.

La integración original utiliza la política de privacidad pública de LAFISE Costa Rica. Las condiciones y tarifas del servicio de pago corresponden al contrato de cada comercio; no se han sustituido por términos generales de otro servicio bancario.

## Traducciones oficiales

0.2.2 mantiene el readme en inglés y añade inglés fuente y español incluido al software. Los catálogos del directorio se presentan por separado en los proyectos Stable, Development, Stable Readme y Development Readme para es_ES y es_CR. La cuenta `y2kwebs` puede importar propuestas con estado Waiting; ese permiso no permite aprobarlas.

El español incluido funciona antes de la validación. El aviso «Este plugin aún no ha sido traducido…» depende de los paquetes oficiales de WordPress.org, no del MO incluido en el ZIP. Solo se dará por completada esa parte cuando se aprueben las traducciones, se generen los paquetes regionales y se comprueben las fichas localizadas. La solicitud de revisión seguirá el [procedimiento de Polyglots](https://make.wordpress.org/polyglots/handbook/plugin-theme-authors-guide/pte-request/); no se atribuye aprobación a una importación en Waiting.

En la comprobación posterior a la publicación, Development ya exponía las 70 cadenas inglesas. Stable todavía exponía las 57 anteriores y ambos readmes las 56 anteriores; los catálogos nuevos contienen 70 entradas de software y 61 de ficha por locale. Se prepararon los cuatro PO y las instrucciones de ocho importaciones para el mantenedor. La carga mediante navegador no llegó a enviarse porque la extensión carecía de acceso a archivos locales; el mantenedor se ofreció a importarlos manualmente. No hay propuestas importadas ni traducciones aprobadas verificadas en esta entrega, y la API no devuelve paquetes oficiales para 0.2.2.

Una comprobación posterior confirmó la actualización de los ocho destinos: 70 fuentes de software y 61 de ficha por locale, con catálogos preparados que coinciden con las fuentes oficiales. La importación manual ya puede cubrirlas completas; la aprobación y la generación de paquetes siguen pendientes.

## Referencias oficiales

- [Proceso de envío y requisitos](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Normas del directorio](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Formato del readme](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Capturas y otros assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [Publicación con SVN](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
