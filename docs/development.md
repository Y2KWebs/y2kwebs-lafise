# Desarrollo y pruebas locales

Requiere PHP con OpenSSL y DOM, Node.js con WebCrypto, Python 3.9 o posterior y Docker Compose para la integración. Todos los accesos publicados en los fixtures son ficticios y solo sirven para el entorno local en loopback. Los identificadores numéricos y respuestas de los tests son sintéticos.

## Pruebas sin WordPress

```text
php tests/unit.php
php tests/bank.php
node --test tests/jwe.test.mjs
python -m unittest discover -s tests -p "test_*.py"
```

Analiza también la sintaxis de todos los archivos PHP públicos con `php -l`.

## Integración aislada

Usa exclusivamente el proyecto Compose suministrado. Las suites modifican pedidos y ajustes locales; nunca deben ejecutarse en una tienda real. Las solicitudes bancarias se interceptan con respuestas sintéticas.

```text
docker compose -f tests/compose.yml up -d wordpress db
docker compose -f tests/compose.yml run --rm cli wp core install --url=http://localhost:8087 --title=Local --admin_user=localtester --admin_password=local-only-test-password --admin_email=test@example.invalid --skip-email
docker compose -f tests/compose.yml run --rm cli wp plugin install woocommerce --version=10.3.0 --activate
docker compose -f tests/compose.yml run --rm cli wp plugin activate y2kwebs-lafise
docker compose -f tests/compose.yml run --rm cli wp wc hpos sync
docker compose -f tests/compose.yml run --rm cli wp option update woocommerce_custom_orders_table_enabled no
docker compose -f tests/compose.yml run --rm cli wp eval-file wp-content/plugins/y2kwebs-lafise/tests/integration.php
docker compose -f tests/compose.yml run --rm cli wp wc hpos sync
docker compose -f tests/compose.yml run --rm cli wp option update woocommerce_custom_orders_table_enabled yes
docker compose -f tests/compose.yml run --rm cli wp eval-file wp-content/plugins/y2kwebs-lafise/tests/integration.php
docker compose -f tests/compose.yml run --rm cli wp eval-file wp-content/plugins/y2kwebs-lafise/tests/settings.php
docker compose -f tests/compose.yml stop
```

Ejecuta `wp core install` únicamente en un entorno nuevo. Los datos y ajustes deben ser descartables. Para la comprobación visual opcional, `tests/setup-ui.php` crea catálogo y páginas ficticias; el mu-plugin `tests/ui-fixture.php` simula el banco y no debe distribuirse en el ZIP.

Para verificar el paquete, extrae el ZIP generado en una carpeta descartable. Con `docker compose run --volume`, monta el directorio extraído `y2kwebs-lafise` sobre `/var/www/html/wp-content/plugins/y2kwebs-lafise:ro` y `tests/release-smoke.php` sobre `/tmp/y2k-release-smoke.php:ro` en el servicio `cli`. Ejecuta `wp plugin deactivate y2kwebs-lafise`, `wp plugin activate y2kwebs-lafise` y `wp eval-file /tmp/y2k-release-smoke.php` usando esos mismos montajes. Así se prueba el contenido real del ZIP; el script rechaza un directorio que contenga los tests y bloquea las solicitudes externas. Ejecutarlo requiere el WordPress local instalado y una cuenta administradora con ID 1.

## Contenido público y paquete

```text
python scripts/check_public.py
python scripts/package.py
```

El primer comando revisa los archivos que Git incluiría, también antes del primer commit, y los enlaces Markdown locales. Es una comprobación de patrones conocidos, complementaria a la revisión manual; no certifica la ausencia de cualquier secreto posible. El empaquetado aplica una lista explícita de archivos y genera un SHA-256 en `dist/`.

GitHub Actions ejecuta las comprobaciones de sintaxis, protocolo, transporte, JWE y empaquetado con PHP 8.2/8.4, Node.js 24 y Python 3.11. Una segunda fase levanta el entorno Docker, instala WooCommerce 10.3.0, ejecuta integración clásica/HPOS y ajustes y verifica la copia extraída del ZIP. La configuración del banco es ficticia; no hay secretos de comercio en CI. La compatibilidad adicional con WooCommerce 11.2.1 está documentada como prueba manual en [validación](validation.md).

La preparación local de 0.2.1 actualiza también las referencias del workflow al nuevo paquete, pero no ejecuta CI remoto. Las regresiones nuevas cubren firma por código sin alternativas, rechazo de contratos inseguros antes de HTTP, desafío directo con confirmación única y rechazo `59` con bloqueo de resultados contradictorios. Los datos bancarios son sintéticos; no copies las respuestas originales a los fixtures.

Mantén propuestas comerciales, documentos del banco, credenciales, registros y capturas reales fuera del repositorio. Revisa `git status --short` antes de cada commit: `.gitignore` no elimina archivos ya versionados.
