# Imagen del plugin en WordPress.org

Familia preparada el 2 de noviembre de 2026 siguiendo `y2k-visual-standard`: logo Y2K oficial conservado sin alteraciones, paleta azul aprobada y tipografías Sora e Inter. La composición y los textos son deterministas.

## Archivos finales

| Destino en assets de SVN | Exportación |
|---|---|
| `banner-772x250.png` | [Cabecera normal](exports/banner-772x250.png) |
| `banner-1544x500.png` | [Cabecera de alta densidad](exports/banner-1544x500.png) |
| `icon-128x128.png` | [Icono normal](exports/icon-128x128.png) |
| `icon-256x256.png` | [Icono de alta densidad](exports/icon-256x256.png) |

La [fuente HTML editable](source.html) es autónoma: incorpora los archivos originales del logo SVG y las fuentes WOFF2 del estándar Y2K como datos embebidos, sin servicios externos. Las fuentes mantienen sus licencias [Sora OFL](OFL-Sora.txt) e [Inter OFL](OFL-Inter.txt). El resto de la composición y los PNG se distribuyen bajo la licencia GPL-2.0-or-later del proyecto.

Se utiliza la palabra LAFISE en tipografía Sora, como referencia a la integración. No se usa el logotipo del banco: la [FAQ del directorio](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#can-i-use-official-logos-in-my-plugin-bannericons) indica que no se deben usar logos oficiales de terceros en iconos y banners. La cabecera identifica la integración como independiente de Y2KWebs.

## Reproducir las exportaciones

Requiere Node.js, los módulos `playwright` y `sharp` y Google Chrome. Con esos módulos disponibles para Node:

```text
node scripts/render_wordpress_artwork.cjs
```

Si los módulos están en un runtime compartido, definir `PLAYWRIGHT_MODULE_DIR` con su carpeta `node_modules`. El render espera a que carguen las fuentes e imágenes y exporta únicamente los cuatro archivos indicados. Se inspeccionaron la cabecera y los iconos, también a 128 px.

## Publicación

Los cuatro PNG se publicaron el 2 de noviembre de 2026 en `assets/` de SVN, mediante la [revisión 3780418](https://plugins.trac.wordpress.org/changeset/3780418/y2kwebs-lafise). Los cuatro archivos están disponibles en la CDN con HTTP 200 y sus hashes coinciden con las exportaciones locales. La versión del plugin continúa siendo 0.2.1: no se cambió el contenido de `trunk` ni de `tags/0.2.1`. Los archivos de diseño, fuentes y herramientas permanecen fuera del paquete instalable.

En la comprobación inmediatamente posterior, la ficha y la API aún devolvían el icono generado anterior y no referenciaban el banner. La subida SVN y los archivos en CDN están verificados; queda pendiente que la ficha refleje la actualización de metadatos/caché. No repetir el commit de los mismos archivos para intentar acelerar esa propagación.
