# Hardcover Bookery: guía breve

Tienda en línea de libros y productos afines (separadores, llaveros, figuras). Instagram: @hardcoverbookery. Estado al 2026-10-08.

## Perfiles

| Perfil | Rol | Puede |
|---|---|---|
| Cliente | `customer` | Navegar el sitio, manejar su carrito, comprar y ver solo sus pedidos y direcciones |
| Administrador | `admin` | Gestionar productos (alta, edición, precios, stock), categorías y pedidos, y ver reportes. No ve usuarios ni roles |
| Super administrador | `super_admin` | Control total: todo lo anterior, más las cuentas y roles de todos los usuarios, pasarelas, tarifas de envío y actividad |

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

## Diseño

Prototipo navegable en [`prototype/`](../prototype/README.md): abre `prototype/index.html`. Es la referencia visual y de comportamiento; usa datos de ejemplo.

- Blanco, negro y verde neón suave; fondo siempre claro. Las portadas aportan el color.
- Estilo limpio, con carátula de marca, despliegues animados, filtros dinámicos y vista rápida del libro.
- Los libros se expanden desde el centro al pasar el mouse.

## Git

- Este repo: `user.name = gamarra-enterprise`, `user.email = gamarra.code@gmail.com`, push con la cuenta de GitHub `gamarra-enterprise` (por `gh`, sin tokens en la URL).
- Otros proyectos: `ErickGamarra` con el correo institucional.
- Commits pequeños y por tema. Nunca incluir `.env` ni credenciales.

## Siguiente

1. Pasar el prototipo a Blade y Livewire por partes: layout, inicio, catálogo, ficha, carrito, checkout y paneles.
2. Pasarelas Stripe y Mercado Pago.

## Decisiones abiertas

- Verificación de correo: `User` no implementa `MustVerifyEmail`, así que el middleware `verified` hoy no bloquea a nadie. Activarla afecta a todos los clientes.
- Logo y colores reales de la marca (hoy el logo es solo texto).
- Políticas reales de envío, devoluciones y preguntas frecuentes (hoy son de ejemplo).
