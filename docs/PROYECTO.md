# Hardcover Bookery: guía breve

Tienda en línea de libros y productos afines (separadores, llaveros, figuras). Instagram: @hardcoverbookery. Estado al 2026-10-08.

## Perfiles

| Perfil | Rol | Puede |
|---|---|---|
| Cliente | `customer` | Comprar, ver sus pedidos y direcciones |
| Administrador (ventas) | `admin` | Lo anterior, más pedidos, productos, stock y reportes |
| Super administrador | `super_admin` | Todo, más usuarios y roles, pasarelas, tarifas de envío y actividad |

> Hoy el enum `UserRole` tiene `admin`, `customer` y `logistics`. Hay que cambiarlo a los tres de arriba.

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

18 tablas. Las del dominio: `addresses`, `categories`, `books`, `book_category`, `carts`, `cart_items`, `orders`, `order_items`, `payments`, y la columna `users.role`.

- Dinero en `decimal(10,2)`, moneda `PEN`, precios con IGV (18 %) incluido.
- `orders.shipping_address`, `order_items.book_snapshot` y `payments.payload` son **JSONB**: guardan una copia inmutable para que cambios posteriores no alteren pedidos pasados.
- `orders.user_id` y `order_items.book_id` son nulables: permiten comprar sin cuenta y conservan el historial si se borra un producto.
- El envío se calcula por peso (`weight_grams`) y dimensiones en mm.

## Código

- `app/Enums`: `OrderStatus`, `PaymentStatus`, `ShippingStatus`, `UserRole`. Enums con valor `string`.
- `app/Services`: `InventoryService`, `PaymentService`, `ShippingService` (esqueletos). Sin estado, con inyección de dependencias.
- `app/Livewire`: `Shop`, `Cart`, `Checkout` (esqueletos).
- Solo existe el modelo `User`. Faltan los demás modelos, factories y seeders.
- `InventoryService` referencia `App\Models\Product`, que aún no existe.

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

1. Roles `super_admin` / `admin` / `customer`.
2. Cambiar `books` por una tabla general `products` con una extensión para los datos del libro (ISBN, páginas, editorial, formato). Decisión recomendada, pendiente de confirmar.
3. Modelos, factories y seeders (con los 16 productos del prototipo).
4. Acceso por roles: Breeze, middleware y policies.
5. Pasar el prototipo a Blade y Livewire por partes: layout, inicio, catálogo, ficha, carrito, checkout y paneles.
6. Pasarelas Stripe y Mercado Pago.

## Decisiones abiertas

- ¿Una sola tabla de productos con extensión de libro?
- Logo y colores reales de la marca (hoy el logo es solo texto).
- Políticas reales de envío, devoluciones y preguntas frecuentes (hoy son de ejemplo).
