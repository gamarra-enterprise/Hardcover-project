# Hardcover Bookery: guía breve

Tienda en línea de libros y productos afines (separadores, llaveros, figuras). Instagram: @hardcoverbookery. Estado al 2026-10-08.

## Perfiles

| Perfil | Rol | Puede |
|---|---|---|
| Cliente | `customer` | Navegar el sitio, manejar su carrito, comprar y ver solo sus pedidos y direcciones |
| Administrador | `admin` | Gestionar productos (alta, edición, precios, stock), categorías y pedidos, **distritos y tarifas de envío**, y ver reportes. No ve usuarios ni roles |
| Super administrador | `super_admin` | Control total: todo lo anterior, más las cuentas y roles de todos los usuarios, pasarelas de pago y actividad |

El prototipo muestra las tarifas de envío dentro del super admin; la decisión vigente es que las maneja el administrador.

Acceso: middleware `role:` (`admin` o `super_admin`; el super admin siempre pasa), `Gate::before` que da todos los permisos al super admin, y policies en `app/Policies`. Paneles en `/admin` y `/super`; `/dashboard` redirige según el rol.

## Stack

Laravel 13 (PHP 8.5 en Sail) · PostgreSQL 18 · Livewire 3 + Volt · Breeze · Tailwind · Vite · Docker/Sail sobre Fedora con SELinux.

## Levantar el entorno

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
```

- Vite sale por el puerto **5174** (`VITE_PORT` en `.env`) para no chocar con otros proyectos locales.
- El volumen de `compose.yaml` lleva `:z` por SELinux. Usa siempre `sail`, no `docker compose` directo.
- Consulta rápida a la BD: `docker exec hardcover-pgsql-1 psql -U sail -d laravel -c "..."`.

## Datos

19 tablas. Las del dominio: `addresses`, `categories`, `products`, `book_details`, `category_product`, `carts`, `cart_items`, `orders`, `order_items`, `payments`, y la columna `users.role`.

- Peso y medidas (`weight_grams`, `width_mm`, `height_mm`, `depth_mm`) son opcionales: el inventario real no trae peso ni profundidad, así que el cálculo de envío debe usar un valor por defecto cuando falten. `products.cost_price` (columna PDC) es el costo de compra: solo para el personal, oculto en la serialización. `book_details.genres` guarda el texto completo de géneros y `book_details.author_bio` la reseña del autor.
- Estado del producto (`products.status`, enum `ProductStatus`): `active`, `hidden` (lo fija el personal) y `out_of_stock`, que el modelo calcula solo a partir del stock al guardar. Los productos sin stock siguen visibles, marcados "Sin stock"; los ocultos no se muestran. Hay que cambiar el stock con `save()` o `update()`, nunca con `decrement()` ni SQL directo, o el estado no se actualiza.
- `products` es la tabla única de productos (libros, separadores, llaveros, figuras). `book_details` (1:1, clave `product_id`) guarda ISBN, autor, editorial, año, páginas y formato solo de los libros.

- Dinero en `decimal(10,2)`, moneda `PEN`, precios con IGV (18 %) incluido.
- `orders.shipping_address`, `order_items.product_snapshot` y `payments.payload` son **JSONB**: guardan una copia inmutable para que cambios posteriores no alteren pedidos pasados.
- `orders.user_id` y `order_items.product_id` son nulables: permiten comprar sin cuenta y conservan el historial si se borra un producto.
- El envío se calcula por peso (`weight_grams`) y dimensiones en mm.

## Código

- `app/Enums`: `OrderStatus`, `PaymentStatus`, `ShippingStatus`, `UserRole`. Enums con valor `string`.
- `app/Services`: `InventoryService`, `PaymentService`, `ShippingService` (esqueletos). Sin estado, con inyección de dependencias.
- `app/Livewire`: `Shop`, `Cart`, `Checkout` (esqueletos).
- Modelos: `User`, `Product`, `BookDetail`, `Category`, `Address`, `Cart`, `CartItem`, `Order`, `OrderItem`, `Payment`, todos con factory.
- Seeders (`sail artisan db:seed`): 10 categorías, los 16 productos del prototipo y tres cuentas de desarrollo (`superadmin@`, `admin@` y `cliente@hardcover.test`, contraseña `password`; no se crean en producción).
- Pedidos: `OrderItem::valuesFor($producto, $cantidad)` arma la copia del producto; el `subtotal` lleva IGV incluido y `Order::totalsFor()` calcula `tax` (parte de IGV del total) y `total`. Código de seguimiento: `HB-aammdd-NNNN`. El estado del envío usa `OrderStatus`, no hay tabla aparte.

## Catálogo y ficha

- `/catalogo`: componente Livewire `App\Livewire\Shop\ProductList`. Filtros por género (las categorías, con su conteo), búsqueda, precio máximo, solo oferta y solo con stock; orden; 12 por página. Todo vive en la URL (`q`, `genero`, `precio`, `oferta`, `stock`, `orden`, `page`), así que los enlaces se pueden compartir. La cabecera tiene un buscador que envía `q`.
- La búsqueda ignora tildes y mayúsculas (extensión `unaccent` de PostgreSQL, creada por una migración) y busca en título, SKU, autor, editorial, géneros e ISBN.
- `/producto/{slug}`: ficha del libro (`ProductController`). Un producto oculto responde 404 al público y se abre para el personal. El botón de compra está deshabilitado hasta el paso del carrito.
- Pendiente del prototipo: vista rápida de la ficha en una ventana sobre el catálogo.

## Carrito y stock

Reglas de negocio:

- Guardar un producto en el carrito **no baja el stock ni lo reserva**. Varias personas pueden tener la última unidad en su carrito a la vez; nadie queda bloqueado.
- El stock baja **solo cuando el pago se confirma** (paso de pagos). Esa deducción tiene que ser atómica y con bloqueo de fila, y prever que la última unidad ya se haya vendido a otra persona entre el carrito y el pago.
- Una persona no puede llevar más unidades de las que hay: la cantidad de una línea se limita al stock actual. El stock de otros carritos nunca cuenta.
- Si después el stock baja, el producto se oculta o se agota, el carrito **no cambia solo**: la línea muestra un aviso («Solo quedan 2 unidades», «Este producto se quedó sin stock») con la opción de ajustar o quitar, y el subtotal no cuenta las líneas con aviso. El checkout deberá exigir que no haya avisos.

Implementación: `CartService` (carrito del usuario en BD; el de invitado, ligado a un token en la sesión que sobrevive al cambio de sesión del login), `InventoryService` (disponibilidad y avisos), el listener `MergeGuestCart` (al iniciar sesión se suman las cantidades sin pasar del stock y se borra el carrito de invitado) y los componentes Livewire de `app/Livewire/Cart` (añadir desde la ficha, contador de la cabecera, panel lateral y `/carrito`). Las acciones del carrito solo ven las líneas del carrito del visitante, aunque el navegador envíe otro id. Un usuario tiene un solo carrito (índice único en `carts.user_id`). Pendiente: limpiar carritos de invitado antiguos con una tarea programada. El envío gratis desde `config/shop.php` (`SHOP_FREE_SHIPPING_FROM`).

## Idioma

La aplicación corre en español (`APP_LOCALE=es`, faker `es_PE`). Las traducciones están en `lang/es/` (validación, autenticación, contraseñas, paginación) y `lang/es.json` (textos de Breeze y correos del framework). `lang/es/validation.php` incluye los nombres en español de los campos (`attributes`); al añadir un campo nuevo a un formulario, agrégalo ahí. El idioma de reserva es `en`. La zona horaria sigue en UTC.

## Importar el inventario

`sail artisan products:import storage/imports/inventario.csv` (opciones: `--dry-run`, `--covers=carpeta`, `--min-genre-count=8`). Es repetible: actualiza por SKU. El SKU es `HB-` más el ISBN. `TAMAÑO` es alto x ancho en cm (sin profundidad) y el peso queda vacío. Los géneros que aparecen en 8 libros o más son categorías (filtros); el texto completo va en `book_details.genres`. Las portadas se enlazan por ISBN (`{isbn}.jpg|png|webp` dentro de `--covers`). La carpeta `storage/imports/` está fuera de git porque contiene datos del negocio. Si `AUTOR` está en blanco (la hoja combina celdas cuando varios libros seguidos son del mismo autor), el libro toma el autor y su reseña del libro anterior. Una fila sin título justo después de un libro se toma como otro tono del mismo libro (por ejemplo `Anagrama (azul)` tras `Anagrama (gris)`): se informa como variante y no cambia nada. Más adelante se puede modelar como variante con su propio ISBN y stock. La columna `WEB` es el estado del producto (`activo`, `oculto`, `sin stock`); cualquier otro valor (hoy dice `no` en todos los libros) se ignora con un aviso, para no ocultar la tienda por error.

## Diseño

Layout público en `resources/views/components/shop-layout.blade.php`, estilos en `resources/css/shop.css` (acotados bajo `body.shop`) y el script de la carátula en `resources/js/shop.js`. Componentes en `components/shop/` (`cover`, `product-card`, `icon`). Tras cambiar CSS o JS hay que compilar (`sail npm run build`), porque las vistas leen el manifiesto de Vite. Las portadas usan `Product::imageUrl()` (disco `public`, ruta en `image_path`) y, sin imagen, un marcador tipográfico neutro.

Prototipo navegable en [`prototype/`](../prototype/README.md): abre `prototype/index.html`. Es la referencia visual y de comportamiento; usa datos de ejemplo.

- Blanco, negro y verde neón suave; fondo siempre claro. Las portadas aportan el color.
- Estilo limpio, con carátula de marca, despliegues animados, filtros dinámicos y vista rápida del libro.
- Los libros se expanden desde el centro al pasar el mouse.

## Git

- Este repo: `user.name = gamarra-enterprise`, `user.email = gamarra.code@gmail.com`, push con la cuenta de GitHub `gamarra-enterprise` (por `gh`, sin tokens en la URL).
- Otros proyectos: `ErickGamarra` con el correo institucional.
- Commits pequeños y por tema. Nunca incluir `.env` ni credenciales.

## Envío, pedidos y reembolsos (rescatado de AxisLab)

Origen: el informe de requerimientos de AxisLab (venta de productos impresos en 3D), un proyecto anterior del equipo. Se rescataron las funciones que corresponden a un módulo de negocio real y se adaptaron a una librería.

**Envío por distrito** (`ShippingZone`, `ShippingDistrict`, `ShippingService`). Las librerías del CSV no tienen peso, así que el envío no se calcula por peso sino por distrito de destino, como en AxisLab. Hay zonas con un costo mínimo (Lima Metropolitana S/ 10, Lima Provincia S/ 15) y distritos con su costo. El costo de un distrito se puede subir pero nunca bajar del mínimo de su zona: la regla está en el modelo (`ShippingCostBelowMinimum`), así que ninguna pantalla ni importación puede saltársela; y si se sube el mínimo de una zona, los distritos que quedaban por debajo suben con ella. Una zona con distritos no se puede borrar. El envío es gratis desde `SHOP_FREE_SHIPPING_FROM`. El seeder carga los 43 distritos de Lima Metropolitana (con su ubigeo); los de Lima Provincia los agrega el administrador desde el panel. Las zonas, los distritos y sus tarifas los gestiona el administrador (`ShippingZonePolicy` y `ShippingDistrictPolicy`).

**Estados del pedido con historial** (`OrderStatus`, `Order::transitionTo()`, `OrderStatusHistory`). Un pedido solo avanza por las transiciones permitidas (pendiente → confirmado → en preparación → enviado → entregado; cancelar solo hasta la preparación; reembolsar al final). Cada cambio guarda quién, cuándo y por qué en una tabla de solo-agregar, y se bloquea la fila para que dos personas no cambien el mismo pedido a la vez. El cliente recibe un correo en cada cambio (`OrderStatusChanged`, en español).

**Stock solo al pagar** (`InventoryService::deductForOrder()` y `restoreForOrder()`). Al confirmarse el pago se descuenta el stock de todo el pedido: bloquea las filas de los productos, es todo o nada y se puede repetir sin descontar dos veces. Si otra persona ya compró la última unidad lanza `InsufficientStock` con el detalle, sin tocar nada, y el paso de pagos decide qué hacer con ese pedido. Al cancelar, el stock vuelve.

**Cancelación con reembolso total** (`OrderService::cancel()`, `OrderPolicy::cancel()`). El cliente cancela mientras el estado lo permita (hasta antes de enviarse). Se le devuelve todo lo que pagó: el servicio deja el monto en `orders.refund_amount` y devuelve el stock. Devolver el dinero por la pasarela y pasar el pedido a «reembolsado» es del paso de pagos. No hay porcentajes de reembolso: se decidió que no hacían falta en este sistema.

**Contacto por WhatsApp**: pospuesto para después (en AxisLab servía para consultas y diseños personalizados; aquí serviría para consultar un libro y encargar un título que no está en el catálogo). No hay ningún enlace ni configuración en el código.

**Páginas** `/nosotros` y `/preguntas-frecuentes`. La FAQ lee de la base de datos los costos de envío, así que no puede contradecir las tarifas reales.

### Qué se tomó y qué no del informe

| Requisito de AxisLab | Estado en Hardcover |
|---|---|
| Registro, inicio de sesión y roles cliente/administrador | Hecho, con un tercer rol (super admin) |
| Catálogo, detalle, búsqueda y filtros avanzados | Hecho (más completo: sin tildes, por género, precio, oferta y stock) |
| Carrito, agregar y eliminar, aviso si cambia el stock | Hecho |
| Costo de envío por distrito, mínimos y edición de tarifas | Modelo, reglas, permisos del administrador y cálculo hechos; falta la pantalla de administración y el selector del checkout |
| Estados del pedido y trazabilidad | Hecho el flujo, el historial y el correo; falta la pantalla del administrador y «Mis pedidos» |
| Cancelar pedido y reembolso, stock al cancelar | Hecho (reembolso total, sin porcentajes) y el stock; falta el botón del cliente y el reembolso real |
| Stock baja al confirmar la compra | Hecho (al confirmarse el pago) |
| Enlace a WhatsApp | Pospuesto para después |
| Sobre nosotros y FAQ | Hecho |
| Gestión del catálogo por el administrador, con «respaldo» del catálogo | Paso 9. El respaldo del catálogo será una exportación a CSV con el mismo formato que la importación |
| Respaldos de la base de datos (ver, descargar, cargar) | Paso 10, solo super admin. Crear y descargar desde el panel; **restaurar solo desde la consola**, porque un botón web que reemplaza toda la base es demasiado peligroso |
| Reportes y métricas (recomendación) | Paso 9, en el resumen de ventas |
| Yape y Plin (recomendación) | Paso 7: evaluar qué admite la pasarela elegida |
| Autenticación en dos pasos para administradores (recomendación) | Antes de salir a producción |
| Rendimiento: respuesta en menos de 3 s, 50 a 100 usuarios a la vez, 99 % de disponibilidad | Paso 12: pruebas de carga y monitoreo |
| Almacenamiento en la nube (actor del informe) | En producción, imágenes y respaldos en un disco S3 compatible |
| Fases «preparación de materiales, en impresión, impreso» y atributos 3D (material, colores, acabado, resistencia) | Descartado: son de impresión 3D. Se adaptó a confirmado, en preparación, enviado y entregado |
| «Diseños personalizados» | Pospuesto junto con WhatsApp (encargos de libros) |

## Checkout y pedidos

`/checkout` (`App\Livewire\Checkout\Checkout`, `CheckoutService`). Una sola pantalla con tres bloques: datos del cliente, entrega y resumen con el costo de envío que cambia al elegir el distrito. Se puede comprar sin cuenta. El cliente con sesión ve sus datos y su última dirección ya llenos y puede guardar la nueva en su cuenta.

- **Alcance del envío**: por ahora solo Lima Metropolitana y Lima Provincia. Las demás regiones, y las zonas que aún no tienen ningún distrito activo, aparecen como «Próximamente» en el selector y en las preguntas frecuentes. El seeder carga los 128 distritos de Lima Provincia pero activa solo los de la costa (Barranca, Cañete, Huaral y Huaura); los de sierra quedan cargados e inactivos para que el administrador los active. Los códigos ubigeo de Lima Provincia los puse según mi recuerdo de la lista del INEI y conviene cotejarlos una vez con el archivo oficial.
- **Qué hace «Confirmar pedido»**: crea el pedido en estado «Pendiente de pago» con sus productos, precios y dirección copiados, y vacía el carrito. **No reserva ni descuenta stock**: eso ocurre al confirmarse el pago. Si entre llenar el formulario y confirmar cambió el stock, no se crea nada y se vuelve al carrito con el aviso. Pulsar dos veces no duplica el pedido, y hay un límite de 10 pedidos por hora por cliente o IP.
- **Celular**: se aceptan espacios, guiones y el prefijo +51; debe quedar en 9 dígitos y empezar con 9.
- **Ver el pedido** (`/pedido/{código}`): es privado, porque muestra nombre y dirección. Se abre con el enlace firmado que se entrega al confirmar el pedido (vale 14 días), o con la sesión del dueño o del personal. El código solo no basta y cualquier otro intento responde 404, para que no se puedan adivinar pedidos.
- **Seguimiento sin cuenta** (`/seguimiento`): código + correo del pedido; si cualquiera de los dos falla, la respuesta es la misma, con un límite de 6 intentos por minuto.
- **Horas**: se guardan en UTC y se muestran en hora de Lima (`APP_DISPLAY_TIMEZONE`).
- **Pago**: la página del pedido pendiente trae el botón «Pagar» (ver «Pagos»).

## Pagos

Pasarela principal: **Mercado Pago** (Checkout Pro). El cliente paga en una página de Mercado Pago, donde aparecen los medios que estén activados en la cuenta del negocio (tarjetas Visa y otras y, si la cuenta lo tiene habilitado, Yape), y vuelve a la tienda. Stripe queda fuera del plan por ahora. Código en `app/Payments` (contrato `PaymentGateway`, `MercadoPagoGateway`, `FakeGateway`) y `PaymentService`.

**Flujo.** Pedido «Pendiente de pago» → botón «Pagar» (`POST /pedido/{código}/pagar`, con dirección firmada, así que solo lo usa quien tiene el enlace del pedido o su dueño) → página de la pasarela → vuelta a `/pago/retorno/…` → página del pedido. Antes de abrir el pago se comprueba que los productos sigan disponibles, para no cobrar algo que ya no hay.

**La pasarela es la fuente de verdad.** Un pago se procesa con lo que responde la API de Mercado Pago al consultarla, nunca con lo que dice una notificación o el navegador. La notificación (`POST /webhooks/mercadopago`, sin CSRF) solo avisa «el pago X cambió»; se verifica su firma `x-signature` con el secreto del webhook (sin secreto configurado no se acepta ninguna) y luego se consulta el pago. Al volver el cliente se consulta también, así que el flujo funciona aunque la notificación no llegue. Procesar dos veces el mismo pago no cambia nada.

**Reglas al confirmarse un pago aprobado**
- El pedido pasa a «Confirmado» y **recién ahí se descuenta el stock**.
- **Última unidad: se la lleva quien pague primero.** Si al confirmar ya no alcanza el stock (otra persona pagó antes), el pedido se cancela con el motivo «Sin stock al confirmar el pago», se le devuelve todo lo pagado y se le avisa por correo. No importa quién hizo el pedido primero.
- Un segundo pago del mismo pedido, un pago que llega con el pedido ya cancelado, o un pago por un monto distinto al del pedido se reembolsan automáticamente.
- Un pago rechazado deja el pedido pendiente y el cliente puede intentarlo otra vez.

**Reembolsos.** Cancelar un pedido pagado devuelve el dinero por la pasarela y el pedido pasa a «Reembolsado». Si la pasarela falla, el pedido queda cancelado con el reembolso pendiente y `payments:retry-refunds` (programado cada hora) lo reintenta; para que corra, el servidor necesita el cron de Laravel (`* * * * * php artisan schedule:run`). La petición de reembolso lleva una llave de idempotencia, así que repetirla no devuelve dos veces.

**Pagar con tarjeta dentro de la tienda** (`/pedido/{código}/tarjeta`). El cliente ve un formulario como el de cualquier portal peruano: número de la tarjeta, vencimiento, código de seguridad (CVV), nombres y apellidos del titular, DNI o carné de extranjería, y correo. La dirección y los datos de contacto ya se pidieron en el checkout, y la dirección de entrega no se vuelve a pedir porque Mercado Pago Perú no la usa para validar la tarjeta.

La decisión importante es que **el número de la tarjeta y el CVV nunca llegan a nuestro servidor**. Recibir, procesar o guardar esos datos pone al negocio bajo la norma PCI DSS (una certificación bancaria costosa) y Mercado Pago lo prohíbe sin ella. Por eso los tres campos de la tarjeta los sirve Mercado Pago dentro de nuestra página (campos seguros, mismo aspecto que el resto del formulario), el navegador los convierte en un **token de un solo uso** y a la tienda solo llega ese token más quién paga. Con el token la tienda pide el cobro a Mercado Pago (`POST /v1/payments`, con llave de idempotencia y `binary_mode`, para que el pago se apruebe o rechace en el acto).

Defensas del lado del servidor:
- Los campos de la tarjeta en el HTML no tienen atributo `name`: aunque algo fallara, el navegador no podría enviarlos.
- Si una petición trae algo que parezca un número de tarjeta (13 a 19 dígitos que pasan la verificación de Luhn, aunque estén dentro de otro campo) o un campo `card_number`, `cvv` y similares, se rechaza sin guardar el valor.
- Un solo cobro por pedido a la vez (bloqueo): un doble clic no cobra dos veces. El pedido ya pagado no se puede volver a cobrar.
- Máximo 5 intentos por pedido y dirección cada 15 minutos, contra el «card testing».
- Si Mercado Pago rechaza la tarjeta se explica el motivo en español («no tiene fondos», «revisa el CVV», etc.) y el cliente puede intentar de nuevo. Si no responde, se le dice que no vuelva a pagar todavía: si el cobro sí se hizo, la notificación lo confirmará sobre el mismo intento.
- La última unidad también aquí: gana quien paga primero; antes de cobrar se comprueba que sigan los productos.

Pruebas del navegador: `npm run test:js` ejecuta el `card-form.js` real en un DOM simulado y comprueba, entre otras cosas, que lo que sale hacia la tienda es el token y no el número ni el CVV, y que la tarjeta escrita se borra del formulario. Con `PAYMENT_GATEWAY=fake` el formulario usa campos simples cuyo contenido tampoco sale del navegador; la tarjeta 4111 1111 1111 1111 aprueba y la 4000 0000 0000 0002 rechaza.

**Lo que falta para tarjetas con Mercado Pago de verdad** (el código del navegador contra su SDK no se pudo probar sin credenciales): poner `MERCADOPAGO_PUBLIC_KEY`, comprobar que los campos seguros se monten, que `getPaymentMethods` reconozca la marca, que el token se cree y el cobro se apruebe con una tarjeta de prueba, y que aparezca la marca y el banco emisor. No están hechos: **3D Secure** (si el banco pide verificación el pago queda «en proceso» y se confirma por notificación, pero no hay pantalla de desafío), **cuotas** (siempre 1) y una política **CSP** que limite los scripts de la página de pago.

**Pasarela de prueba local.** Sin credenciales, `PAYMENT_GATEWAY=fake` (el valor del `.env` de desarrollo) manda al cliente a una página local donde se aprueba o rechaza el pago. Sirve para recorrer todo el flujo. Con `PAYMENT_GATEWAY=fake` la aplicación se niega a arrancar en producción.

**Conectar Mercado Pago de verdad** (pendiente; el código se probó con respuestas simuladas, no contra el servicio real):
1. En Mercado Pago Developers, crear una aplicación y copiar el Access Token (para pruebas, el que empieza con `TEST-`) en `MERCADOPAGO_ACCESS_TOKEN` y la Public Key en `MERCADOPAGO_PUBLIC_KEY`.
2. Configurar el webhook de pagos hacia `https://tu-dominio/webhooks/mercadopago` y copiar su clave secreta en `MERCADOPAGO_WEBHOOK_SECRET`.
3. Poner `PAYMENT_GATEWAY=mercadopago` y `APP_URL` con la dirección pública y https. En desarrollo hace falta un túnel (por ejemplo ngrok o cloudflared) para que Mercado Pago alcance el webhook y las direcciones de retorno.
4. Verificar con una compra de prueba: que la preferencia se cree, que vuelva a la tienda, que la notificación llegue y se acepte su firma, que Yape aparezca si se espera, y que un reembolso funcione.

**Transferencia bancaria** (hecha): si `SHOP_BANK_ACCOUNT` (y opcionalmente `SHOP_BANK_NAME`, `SHOP_BANK_HOLDER`, `SHOP_BANK_CCI`) está en `.env`, la página del pedido pendiente muestra los datos de la cuenta y un formulario para subir el comprobante (imagen o PDF, 5 MB; se guarda en disco privado). El pago queda «en proceso» y el pedido pendiente; en el detalle del pedido del panel el personal ve el comprobante y **confirma** (pedido confirmado, stock descontado, correo al cliente) o **rechaza** con motivo (el cliente puede enviar otro). Si al confirmar ya no hay stock, el pedido se cancela y el reembolso queda pendiente. Una transferencia no se reembolsa por la pasarela: al cancelar, el pedido espera a que el personal devuelva el dinero y pulse «Ya devolví el dinero» (entonces pasa a «Reembolsado»). El resumen del panel cuenta los comprobantes por revisar.

## Siguiente

1. **Cuenta del cliente**: «Mis pedidos» hecho (`/cuenta/pedidos`, lista, detalle con línea de tiempo y botón «Cancelar pedido»; `/dashboard` redirige ahí a los clientes). Direcciones hecho (`/cuenta/direcciones`: agregar, editar, eliminar y elegir la principal; el checkout ya usa la principal).
2. **Panel de administración**: pedidos hecho (`/admin/pedidos`: lista con filtro por estado y búsqueda por código o correo, detalle con productos, pagos e historial, y cambio de estado con nota; cancelar usa `OrderService::cancel`, que devuelve stock y dinero; «Confirmado» y «Reembolsado» no se fuerzan a mano porque son del flujo de pagos). El panel tiene su propio diseño (`panel-layout`, `resources/css/panel.css`, menú lateral, resumen con lo pendiente del día) y la ruta `POST /salir`. Productos y categorías hecho (`/admin/productos`, `/admin/categorias`: lista con búsqueda y filtro, crear y editar con datos del libro, categorías y portada; **no se borran productos, se ocultan**; el slug no cambia al editar; una categoría con productos no se elimina). Las portadas subidas necesitan `php artisan storage:link` en el servidor. Envíos hecho (`/admin/envios`: mínimo y estado de cada zona, costo y estado de cada distrito, alta de distritos; el costo nunca baja del mínimo de la zona). Exportación hecha (botón «Exportar catálogo (CSV)» en `/admin/productos`: solo libros, mismas columnas que la importación, celdas con fórmulas neutralizadas; el importador reconoce los productos por ISBN aunque su SKU no sea `HB-ISBN`). Ventas hecho (`/admin/ventas`: hoy, semana y mes, gráfico diario de 7, 30 o 90 días, más vendidos y pedidos por estado; cuenta como venta el pedido con pago confirmado que no se canceló ni reembolsó, fechado al confirmarse el pago, en hora de Lima). Falta: confirmación de pagos por transferencia (hecha, ver «Pagos»).
3. **Panel de super admin**: usuarios y roles hecho (`/super/usuarios`: buscar, filtrar y cambiar el rol; nadie cambia su propio rol, así que siempre queda un super administrador) y actividad hecha (`/super/actividad`; tabla `activity_logs` con `ActivityLog::record()`, que ya registran productos, categorías, envíos, cambios de estado de pedidos, exportación y cambios de rol). Respaldos hecho (`/super/respaldos`: crear, descargar y eliminar; `pg_dump` en formato custom en `storage/app/private/backups`; `db:backup` se programa cada noche a las 3:00 y conserva los últimos 14, así que el cron de Laravel debe estar activo; **restaurar solo por consola**: `php artisan db:restore <archivo>`, probado). Los respaldos tienen datos de clientes: guardarlos fuera del servidor. Pasarelas hecho (`/super/pasarelas`, solo lectura: pasarela activa, qué credenciales faltan sin mostrar nunca sus valores, dirección del webhook y últimos pagos; las claves siguen en `.env`). **El paso 10 está completo.**
4. Correos, accesibilidad, pruebas de carga y despliegue (lista de verificación y cifras de carga en `docs/DESPLIEGUE.md`; `scripts/load-test.sh` para repetir la prueba). Ya con el diseño de la tienda: ingreso, registro, recuperar contraseña, verificación y perfil (los componentes de formulario de Breeze usan las clases `.btn` e `.input` de la tienda); los correos de estado llevan el botón «Ver mi pedido» (enlace firmado) y al hacer el pedido se envía «Recibimos tu pedido» con productos, total y enlace; en checkout los errores se enlazan al campo (`aria-invalid`, `aria-describedby`), los botones del carrito dicen de qué producto hablan y el panel del carrito recibe el foco al abrirse (falta atrapar el foco dentro); el anillo de foco usa el verde oscuro (el verde claro no se veía sobre blanco); el botón «Salir» está en la cabecera de la tienda y en el panel.

## Rediseño de la tienda (según el artifact aprobado)

Hecho: inicio con portada, destacado rotativo, géneros como chips, paneles «¿Qué te apetece leer hoy?» (por género real), carruseles de Novedades y Más vendidos (con arrastre y flechas), «Calcula tu ritmo», papelería y regalos, estantería, pasos, preguntas frecuentes con datos reales y Club de lectores; menú con submenú (géneros, tipos, descubre); listados `/novedades`, `/mas-vendidos`, `/ofertas`, `/regalos`; tipo de producto (libro, papelería, accesorio, figura) con selector y conteos en el catálogo, histograma de precios; ventana de vista rápida sobre cualquier listado; ficha con acordeones. **Decidido dejar fuera**: valoraciones con estrellas, métodos de envío Express y recojo, cupones (el Club es solo suscripción: `/club`, lista y CSV en `/admin/club`). Panel: inventario con stock editable en la lista (`/admin/inventario`), resumen con ventas de hoy, ticket promedio, gráfico de 7 días y «Reponer pronto», y el monto de envío gratis editable en `/admin/envios` (tabla `settings`; pisa `SHOP_FREE_SHIPPING_FROM`). Cuenta: pestañas Mis pedidos / Direcciones / Perfil. Los estilos nuevos están en `resources/css/shop-extra.css`. Más vendidos se calcula con los pedidos pagados de los últimos 90 días.

## Decisiones abiertas

- **Alcance del envío**: decidido, por ahora solo Lima Metropolitana y Lima Provincia; las otras regiones salen como «Próximamente». Cuando se abran más, hay que definir zonas, tarifas y un selector que no se limite a Lima (hoy el estado guardado en la dirección es siempre «Lima»).
- **Ubigeos de Lima Provincia**: cotejarlos con el archivo oficial del INEI.
- **Credenciales de Mercado Pago**: sin ellas la integración real no se ha podido probar. Hay que crear la aplicación, obtener el token de prueba y el secreto del webhook, y recorrer la lista de «Conectar Mercado Pago de verdad».
- **Transferencia bancaria manual**: decidida y hecha (ver «Pagos»). Falta poner los datos reales de la cuenta en `.env`.
- **WhatsApp**: pospuesto. Al retomarlo falta el número del negocio y decidir dónde se muestra.
- **Verificación en dos pasos** (hecha, solo personal): `/seguridad`, códigos TOTP de una app de autenticación (`pragmarx/google2fa`), QR con `chillerlan/php-qrcode`, 8 códigos de recuperación de un solo uso, un código no se reutiliza, 5 intentos por minuto, secreto cifrado. `SHOP_REQUIRE_2FA` (activo por defecto en producción) obliga al personal a activarla; quien la tiene activa siempre pasa el desafío. El super administrador puede restablecerla a otra persona.
- Verificación de correo: `User` no implementa `MustVerifyEmail`, así que el middleware `verified` hoy no bloquea a nadie. Activarla afecta a todos los clientes.
- Logo y colores reales de la marca (hoy el logo es solo texto).
- Políticas reales de envío y devoluciones: la barra superior («Despachamos en 24 horas») y la franja de confianza («Recojo en tienda», «Envoltorio de regalo») siguen siendo texto de ejemplo del prototipo.
