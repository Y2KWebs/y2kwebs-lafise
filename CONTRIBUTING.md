# Contribuir

## Ramas, pull requests y versiones

- Trabaja en una rama por cambio o conjunto coherente de cambios, con nombres descriptivos como `codex/admin-credentials` o `codex/lafise-0.2.1`. Las correcciones, pruebas y documentación de una misma actualización pueden ir en un único PR.
- Sube esa rama y abre un PR hacia `main`, con el problema, el comportamiento resultante, las pruebas ejecutadas y las limitaciones. No hagas push directo a `main`.
- Integra el PR cuando hayan pasado las comprobaciones y se hayan resuelto los comentarios de revisión.
- Para publicar una versión, crea una etiqueta como `v0.2.1` sobre el commit integrado y una Release con el ZIP instalable verificado y su SHA-256. Las versiones preliminares se marcan como **pre-release**. Sigue la [guía de publicación](docs/releasing.md).

Los errores de instalación y las propuestas de mejora se gestionan mediante las plantillas de incidencias de este repositorio. El soporte comunitario no tiene SLA; [Y2KWebs](https://y2kwebs.com/) ofrece instalación y soporte profesional mediante acuerdo separado.

Para desarrollar, sigue [las instrucciones de pruebas locales](docs/development.md). Trabaja con datos ficticios y el banco simulado. Las pruebas automatizadas no necesitan credenciales de LAFISE y no realizan cobros.

En una propuesta de cambio, explica el problema, el comportamiento resultante y las pruebas ejecutadas. Los cambios de pagos deben conservar la comprobación de firma, importe y comercio, la correlación 3DS y la protección contra ventas duplicadas. No conviertas una respuesta incierta en una aprobación o en permiso para repetir el cobro.

No adjuntes claves, tarjetas, CVV, cookies, URLs de pago con tokens ni respuestas bancarias reales. Para vulnerabilidades utiliza el canal privado indicado en [SECURITY.md](SECURITY.md).

El proyecto se distribuye bajo GPL-2.0-or-later. Mantén los avisos y licencias de cualquier código que incorpores.
