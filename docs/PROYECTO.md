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

## Siguiente

1. **Checkout**: dirección, selector de distrito con el costo de envío (`ShippingService`), correo de contacto, creación del pedido con sus copias. No reserva stock.
2. **Pagos** (Stripe y Mercado Pago, evaluar Yape y Plin): al confirmarse el pago, confirmar el pedido y descontar el stock; decidir qué hacer si `InsufficientStock`; devolver el dinero de las cancelaciones.
3. **Cuenta del cliente**: «Mis pedidos» con la línea de tiempo y el botón de cancelar; direcciones.
4. **Panel de administración**: productos y categorías (agregar, editar, ocultar), pedidos con cambio de estado, distritos y tarifas de envío, exportación del catálogo, resumen de ventas.
5. **Panel de super admin**: usuarios y roles, pasarelas, respaldos, registro de actividad.
6. Correos, accesibilidad, pruebas de carga y despliegue.

## Decisiones abiertas

- **Alcance del envío**: AxisLab solo enviaba a Lima. El prototipo de Hardcover decía «todo el Perú». Hoy solo hay zonas de Lima; si se enviará a provincias hay que definir las zonas y sus tarifas.
- **WhatsApp**: pospuesto. Al retomarlo falta el número del negocio y decidir dónde se muestra.
- Verificación de correo: `User` no implementa `MustVerifyEmail`, así que el middleware `verified` hoy no bloquea a nadie. Activarla afecta a todos los clientes.
- Logo y colores reales de la marca (hoy el logo es solo texto).
- Políticas reales de envío y devoluciones: la barra superior («Despachamos en 24 horas») y la franja de confianza («Recojo en tienda», «Envoltorio de regalo») siguen siendo texto de ejemplo del prototipo.
