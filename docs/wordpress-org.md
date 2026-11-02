# Publicación en WordPress.org

El plugin está publicado en [WordPress.org](https://wordpress.org/plugins/y2kwebs-lafise/) con el slug `y2kwebs-lafise`, bajo la cuenta `y2kwebs`. La versión publicada es **0.2.1**. En GitHub también existe la [release v0.2.1](https://github.com/Y2KWebs/y2kwebs-lafise/releases/tag/v0.2.1), publicada el 31 de octubre de 2026.

Los comercios pueden instalarlo desde el directorio de WordPress o descargar el ZIP de su ficha. Los requisitos, el servicio bancario externo y las limitaciones están en [readme.txt](../readme.txt); las pruebas reales y simuladas se distinguen en [validación](validation.md).

## Registro de inclusión y publicación

| Fecha | Hito |
|---|---|
| 31 de octubre de 2026 | Solicitud de inclusión enviada por el mantenedor. El slug generado inicialmente se cambió a `y2kwebs-lafise`, coincidiendo con la carpeta instalada y el dominio de traducción. |
| 2 de noviembre de 2026 | Inclusión aprobada por el equipo de plugins; repositorio SVN asignado: https://plugins.svn.wordpress.org/y2kwebs-lafise/. |
| 2 de noviembre de 2026 | Primera publicación, revisión [3780395](https://plugins.trac.wordpress.org/changeset/3780395/y2kwebs-lafise): contenido 0.2.1 en trunk y tag, más dos capturas con datos ficticios. |
| 2 de noviembre de 2026 | Iconos y cabeceras publicados en assets mediante la revisión [3780418](https://plugins.trac.wordpress.org/changeset/3780418/y2kwebs-lafise). |

La aprobación del directorio corresponde al plugin independiente de Y2KWebs; no implica certificación, alianza ni respaldo de LAFISE.

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

## Referencias oficiales

- [Proceso de envío y requisitos](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Normas del directorio](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Formato del readme](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Capturas y otros assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [Publicación con SVN](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
