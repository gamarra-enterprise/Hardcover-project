# Estado del proyecto (pausado el 2026-10-09)

Punto de partida para retomar. Las reglas de negocio y el detalle técnico están en `PROYECTO.md`; la lista para salir a producción, en `DESPLIEGUE.md`.

## Qué está hecho
Los 12 pasos del plan, salvo lo que exige un servidor real.

- **Tienda:** inicio con portada y carruseles, menú con submenú, catálogo con filtros (tipo, género principal y «Más géneros», precio con histograma, oferta, stock), listados de novedades, más vendidos, ofertas y regalos, ficha con acordeones, vista rápida, carrito, checkout, seguimiento sin cuenta, cuenta del cliente (pedidos, direcciones, perfil), Club de lectores.
- **Pagos:** Mercado Pago (Checkout Pro y tarjeta propia), pasarela de prueba `fake`, transferencia bancaria manual con comprobante, reembolsos y reintentos.
- **Panel de administración:** resumen, ventas, pedidos con cambio de estado, productos, inventario, categorías (principal o «Más»), envíos y monto de envío gratis, club, exportación del catálogo.
- **Panel de super administración:** usuarios y roles, actividad, respaldos (restauración solo por consola) y estado de pasarelas.
- **Seguridad:** roles y políticas, verificación en dos pasos para el personal, enlaces firmados, comprobantes privados.
- **Calidad:** unos 410 tests, guarda contra consultas por producto, prueba de carga con `curl`.

## Qué falta
**De tu parte:** credenciales de Mercado Pago · datos reales de la cuenta bancaria (`SHOP_BANK_*`) · textos reales de envíos, devoluciones y recojo · logo y colores · portadas reales · decidir si se activa la verificación de correo de clientes.

**Del servidor:** SMTP y remitente reales · dominio con HTTPS · repetir la prueba de carga con opcache · monitoreo.

**Sin hacer (ordenado por importancia):**
1. Revisión visual en navegador, celular y escritorio. El rediseño y los efectos de scroll solo se validaron con tests.
2. Páginas legales y libro de reclamaciones; comprobantes electrónicos (boleta o factura). Confirmar requisitos con un profesional.
3. SEO: metaetiquetas por producto, imagen para redes, mapa del sitio, datos estructurados.
4. Páginas de error propias (404 y 500) y aviso de cookies.
5. Probar un cobro real con Mercado Pago.
6. Integración continua y pruebas de navegador.
7. Atrapar el foco dentro del carrito con Tab; optimizar las imágenes de portada al subirlas.

**Dejado fuera a propósito:** valoraciones con estrellas, Express y recojo en tienda, cupones. El envío es siempre por distrito.

## Cómo retomar
```
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan storage:link
./vendor/bin/sail npm run dev        # o: npm run build
./vendor/bin/sail artisan test       # ~410 tests
```
- Tienda en http://localhost. Cuentas de desarrollo (contraseña `password`): `cliente@`, `admin@` y `superadmin@hardcover.test`.
- Pago local: `PAYMENT_GATEWAY=fake`. Transferencia: poner `SHOP_BANK_ACCOUNT` en `.env`. Correos: `MAIL_MAILER=log`, se leen en `storage/logs/laravel.log`.
- Cada vez que cambies CSS o JS, `npm run build` (los tests leen el manifiesto).
- Git: push con `gh auth switch -u gamarra-enterprise` y devolver a `ErickGamarra` después; sin línea de coautoría.

## Estado de git
Rama `main`. Último push: `a6ce892`. Quedan **2 commits locales sin subir**: portadas con proporción real y géneros principales (`e55eb8c`), y menú del panel agrupado (`20c3176`), más el de esta documentación.

## Sugerencia de siguiente sesión
Recorrer la tienda y el panel en navegador y anotar lo que se vea mal; cargar portadas reales; añadir las páginas legales y de error con textos marcados como ejemplo; hacer un cobro de prueba cuando haya credenciales.
