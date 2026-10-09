# Despliegue: lista de verificación

Lo que hay que hacer antes de abrir la tienda. Marca cada punto al terminarlo.

## Servidor
- [ ] PHP 8.5 con `pdo_pgsql`, `bcmath`, `intl`, `gd` y **opcache activado**; PostgreSQL 18 con la extensión `unaccent`; `pg_dump` y `pg_restore` instalados (respaldos).
- [ ] HTTPS con certificado válido y `APP_URL` con la dirección pública `https://…`.
- [ ] Cron de Laravel: `* * * * * cd /ruta && php artisan schedule:run` (reembolsos pendientes cada hora y respaldo nocturno a las 3:00).
- [ ] `php artisan storage:link` (portadas subidas desde el panel).
- [ ] Permisos de escritura en `storage/` y `bootstrap/cache/`.

## `.env` de producción
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generado, `APP_DISPLAY_TIMEZONE=America/Lima`.
- [ ] Base de datos con un usuario propio y contraseña fuerte (no `sail`/`password`).
- [ ] Correo: `MAIL_MAILER=smtp` con el servidor real, `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME` del negocio. Con `log` los correos no salen.
- [ ] `SESSION_SECURE_COOKIE=true`; `LOG_LEVEL=warning`.
- [ ] Pagos: `PAYMENT_GATEWAY=mercadopago`, `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_PUBLIC_KEY`, `MERCADOPAGO_WEBHOOK_SECRET`. La pasarela `fake` se rechaza en producción.
- [ ] Transferencia: `SHOP_BANK_NAME`, `SHOP_BANK_HOLDER`, `SHOP_BANK_ACCOUNT` y `SHOP_BANK_CCI` con la cuenta real del negocio (sin cuenta, la opción no aparece). Los comprobantes quedan en `storage/app/private/transfer-proofs`: incluirlos en los respaldos de archivos.
- [ ] En Mercado Pago, registrar la dirección del webhook que muestra `/super/pasarelas` y hacer una compra de prueba con reembolso (lista en «Conectar Mercado Pago de verdad»).

## Primer arranque
```
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=CategorySeeder   # solo categorías; las cuentas de desarrollo no se crean en producción
php artisan products:import inventario.csv --covers=portadas/
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```
- [ ] Crear la primera cuenta de super administrador (`php artisan tinker`) y **cambiar o borrar** cualquier cuenta de prueba.
- [ ] Cola de trabajos: hoy los correos salen en la misma petición. Si se vuelve lento, pasar a `QUEUE_CONNECTION=database` y un worker.

## Antes de abrir
- [ ] Cada administrador activa la verificación en dos pasos en `/seguridad` (hecha; en producción es obligatoria para entrar al panel, `SHOP_REQUIRE_2FA`). Guardar los códigos de recuperación. Si alguien pierde ambos, el super administrador la restablece en `/super/usuarios`.
- [ ] Verificación de correo de clientes: decidir si se activa (`MustVerifyEmail`).
- [ ] Textos reales de envíos, devoluciones, logo y colores (siguen siendo de ejemplo).
- [ ] Probar un respaldo y su restauración en otra máquina.
- [ ] Guardar los respaldos fuera del servidor: contienen datos de clientes.

## Rendimiento
- Meta del informe: respuesta en menos de 3 s con 50 a 100 usuarios a la vez.
- `scripts/load-test.sh [url] [peticiones] [concurrencia]` mide mediana y percentil 95 con solo `curl`. En el contenedor de desarrollo, con 50 a la vez: portada p95 1,2 s; catálogo, búsqueda, carrito y seguimiento p95 menos de 0,8 s; 0 errores. En producción (opcache, `APP_DEBUG=false`) debe salir mejor; hay que repetirlo allí.
- `tests/Feature/Performance/QueryCountTest.php` avisa si portada o catálogo empiezan a hacer una consulta por producto.
- La búsqueda usa `ilike` con `unaccent` sobre ~100 productos; si el catálogo pasa de unos miles, añadir un índice trigram.
- El resumen de ventas agrupa en PHP los pedidos del periodo; con decenas de miles de pedidos al mes conviene pasarlo a SQL.

## Monitoreo (disponibilidad 99 %)
- [ ] Un monitor externo (por ejemplo UptimeRobot) sobre la portada y `/catalogo`.
- [ ] Alerta si `storage/logs` registra errores, y espacio en disco vigilado (respaldos y portadas).
