# Hardcover - E-commerce Platform for Physical Books

> Resumen breve en español: [docs/PROYECTO.md](docs/PROYECTO.md)

## Purpose and Functional Scope

Hardcover is a Laravel-based e-commerce platform specialized in the sale of physical books. The system provides a complete solution for managing a categorized book catalog, physical inventory control, shipping calculation and dispatch, and transactional processing through payment gateways (Stripe / Mercado Pago).

### Core Functional Modules

- **Catalog Management**: Hierarchical categorization of books (genres, authors, publishers, collections)
- **Inventory Control**: Real-time stock tracking, reservations, low-stock alerts, physical warehouse management
- **Shopping Cart & Checkout**: Persistent cart, guest checkout, shipping address management, order summary
- **Payment Processing**: Integration with Stripe and Mercado Pago, webhook handling, refunds, partial refunds
- **Shipping & Logistics**: Multi-carrier shipping calculation, label generation, tracking, pickup scheduling
- **Order Management**: Complete order lifecycle (pending → confirmed → processing → shipped → delivered)
- **User Authentication**: Registration, login, password reset, email verification via Laravel Breeze

---

## Technology Stack

| Component | Version | Purpose |
|-----------|---------|---------|
| **PHP** | 8.2+ | Core language |
| **Laravel** | 13.x | Backend framework |
| **Laravel Sail** | 1.67.x | Docker development environment |
| **PostgreSQL** | 18 | Primary database |
| **Laravel Breeze** | 2.4.x | Authentication scaffolding |
| **Livewire** | 3.8.x | Reactive frontend components |
| **Alpine.js** | 3.x | Lightweight JavaScript interactions |
| **Tailwind CSS** | 3.x | Utility-first CSS framework |
| **Vite** | 5.x | Asset bundling and dev server |
| **Composer** | 2.x | PHP dependency management |
| **Node.js** | 20+ | Frontend tooling |

---

## Directory Structure Map

```
app/
├── Enums/
│   ├── OrderStatus.php       # Order lifecycle states
│   ├── PaymentStatus.php     # Payment transaction states
│   └── ShippingStatus.php    # Shipment tracking states
├── Services/
│   ├── PaymentService.php    # Payment gateway abstraction (Stripe/MP)
│   ├── InventoryService.php  # Stock management & reservations
│   └── ShippingService.php   # Carrier integration & logistics
├── Livewire/
│   ├── Shop/
│   │   ├── ProductList.php   # Catalog browsing & filtering
│   │   └── ProductDetail.php # Individual book view
│   ├── Cart/
│   │   ├── CartDrawer.php    # Slide-out cart summary
│   │   └── CartPage.php      # Full cart management page
│   └── Checkout/
│       ├── Checkout.php      # Multi-step checkout orchestrator
│       └── ShippingInformation.php # Address & shipping selection
├── Models/                   # Eloquent models (User, Product, Order, etc.)
├── Http/
│   ├── Controllers/          # API & Web controllers
│   └── Requests/             # Form request validation
└── Policies/                 # Authorization policies
```

---

## Getting Started Guide

### Prerequisites

- **Docker** and **Docker Compose** (v2.x)
- **Git** (for version control)
- **PHP** 8.2+ and **Composer** 2.x (for initial project creation)
- **Node.js** 20+ and **npm** (for frontend assets)

### 1. Create Laravel Project with Sail (PostgreSQL)

```bash
# Create project via Composer (alternative to laravel.new installer)
composer create-project laravel/laravel ~/hardcover --prefer-dist

cd ~/hardcover

# Add Laravel Sail with PostgreSQL support
composer require laravel/sail --dev
php artisan sail:install --with=pgsql
```

### 2. Install Authentication & Frontend Stack (Breeze + Livewire)

```bash
# Install Laravel Breeze with Livewire/Blade stack
composer require laravel/breeze --dev
php artisan breeze:install livewire

# This installs: Livewire 3, Alpine.js, Tailwind CSS, Vite
# And compiles initial assets automatically
```

### 3. Create Custom Architecture Directories

```bash
# Business logic directories (not created by Laravel by default)
mkdir -p app/Enums app/Services app/Livewire/Shop app/Livewire/Cart app/Livewire/Checkout

# Create base Enums (OrderStatus, PaymentStatus, ShippingStatus)
# Create Service skeletons (PaymentService, InventoryService, ShippingService)
# Create Livewire component skeletons per domain
```

### 4. Configure Environment Variables

Copy and configure the `.env` file with PostgreSQL (Sail defaults) and payment gateway placeholders:

```env
# Database (pre-configured by sail:install --with=pgsql)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# Payment Gateways (add your credentials)
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

MERCADOPAGO_PUBLIC_KEY=
MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_WEBHOOK_SECRET=

# Shipping & Image Storage settings
SHIPPING_DEFAULT_ORIGIN_ADDRESS=
COVER_IMAGE_MAX_SIZE=2048
COVER_IMAGE_ALLOWED_TYPES=jpg,jpeg,png,webp
COVER_IMAGE_STORAGE_PATH=covers
```

### 5. Start Docker Containers with Sail

```bash
# Ensure Docker daemon is running (see Docker Troubleshooting if needed)
sudo systemctl start docker

# Refresh group membership if "permission denied" on docker.sock
newgrp docker

# Start all services in detached mode
./vendor/bin/sail up -d

# Verify containers are running
./vendor/bin/sail ps
```

Expected services (see `compose.yaml`):
- `laravel.test` - PHP application container (Sail)
- `pgsql` - PostgreSQL 18 database

Redis, Mailpit and Selenium are not enabled yet. Add them with `sail:add` if needed.

### 6. Run Database Migrations

```bash
# Run all migrations
./vendor/bin/sail artisan migrate

# Optional: Seed with sample data
./vendor/bin/sail artisan db:seed
```

### 7. Compile Frontend Assets

```bash
# Development (with hot reload)
./vendor/bin/sail npm run dev

# Production build
./vendor/bin/sail npm run build
```

### 8. Verify Installation

1. **Application**: Open http://localhost in your browser
2. **Database Connection**: Verify via Sail
   ```bash
   ./vendor/bin/sail artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected!';"
   ```
3. **PostgreSQL Direct Access**:
   ```bash
   ./vendor/bin/sail psql -U sail -d laravel
   ```
4. **Mailpit**: Open http://localhost:8025 for email testing

---

## Development Workflow

### Common Sail Commands

```bash
# Artisan commands
./vendor/bin/sail artisan <command>

# Composer commands
./vendor/bin/sail composer <command>

# NPM commands
./vendor/bin/sail npm <command>

# Database shell
./vendor/bin/sail psql

# Redis CLI
./vendor/bin/sail redis-cli

# Run tests
./vendor/bin/sail test

# Lint code (Pint)
./vendor/bin/sail pint

# Static analysis (if configured)
./vendor/bin/sail phpstan analyse
```

### Creating New Components

```bash
# New Livewire component
./vendor/bin/sail artisan make:livewire Shop/CategoryFilter

# New Service
./vendor/bin/sail artisan make:class Services/OrderService

# New Enum
./vendor/bin/sail artisan make:enum Order/ReturnStatus

# New Model with migration
./vendor/bin/sail artisan make:model Book -m
```

---

## Project Conventions

### Enums
All enums use **BackedEnum** with `string` backing type for database persistence.

```php
enum OrderStatus: string {
    case PENDING = 'pending';
    // ...
}
```

### Services
Services are **stateless** classes with dependency injection. Use interfaces for swappable implementations (e.g., `PaymentGatewayInterface` with `StripeGateway`, `MercadoPagoGateway`).

### Livewire Components
- **Naming**: PascalCase, domain-prefixed (`Shop/`, `Cart/`, `Checkout/`)
- **Views**: `resources/views/livewire/{domain}/{component}.blade.php`
- **Properties**: Public reactive properties with `#[On('event')]` listeners

### Database
- **Migrations**: Snake_case, descriptive names (`create_books_table`, `add_isbn_to_books_table`)
- **Models**: Singular PascalCase, `$fillable` defined, relationships typed
- **Indexes**: Explicit on foreign keys and frequently queried columns

---

## Current Status

Last updated: 2026-10-08.

**Done**
- Docker/Sail environment on Fedora with PostgreSQL 18.
- `UserRole` enum and the 10 domain migrations are applied: `users.role`, `addresses`, `categories`, `products`, `book_details`, `category_product`, `carts`, `cart_items`, `orders`, `order_items`, `payments`. Order and payment snapshots use JSONB.
- `UserRole` now has `super_admin`, `admin` and `customer` (the old `logistics` role was removed; its users become `admin`).
- All Eloquent models, factories and seeders (16 prototype products, 10 categories, one dev account per role).
- Role-based access: `role` middleware, `Gate::before` for the super admin, policies, and `/admin` and `/super` placeholder panels.
- Public storefront layout and home page (brand cover, header, footer, new-arrivals grid) with placeholder covers.
- Spanish locale (`lang/es`, `lang/es.json`) and the `products:import` command that loads the books inventory CSV.
- Catalog with genre, search, price, sale and stock filters, sorting and pagination (`/catalogo`), and the product page (`/producto/{slug}`).
- Cart (guest and logged-in, merged on login, drawer and `/carrito`) where adding to the cart never changes stock; stock will only go down when a payment completes.
- Requirements taken from the AxisLab report: shipping priced by district with zone minimums, order status workflow with history and e-mail notices, stock deducted only on payment and given back on cancellation, full refund on cancellation, shipping rates managed by the administrator, About and FAQ pages.
- Checkout (`/checkout`): guest or logged-in, shipping priced by district with "próximamente" for regions not served yet, creates an order pending payment without touching stock; private order page with a signed link and tracking by code and e-mail.
- Payments through Mercado Pago (Checkout Pro) with signed notifications, the gateway as the source of truth, stock deducted when the payment is confirmed, the first payer keeps the last unit and the other is refunded, automatic refunds on cancellation with an hourly retry, a card form in the shop's own page that works with one-time tokens, so the card number and CVV never reach the server, and a local stand-in gateway for development. Tested with simulated answers; not yet run against the real service.
- Clickable UI prototype in [`prototype/`](prototype/README.md) (sample data, three profiles).

**Pending**
- Remaining Eloquent models, factories and seeders (`Product`, `BookDetail` and `Category` exist). The seeders can reuse the prototype's sample products.
- Role-based access, then the Livewire/Blade views based on the prototype, then payment gateways.

## Next Steps

1. **Define Core Models**: `Book`, `Category`, `Author`, `Publisher`, `Order`, `OrderItem`, `Shipment`
2. **Implement Payment Gateways**: Create `StripeGateway` and `MercadoPagoGateway` implementing `PaymentGatewayInterface`
3. **Build Catalog UI**: Product listing with filters, search, pagination
4. **Cart Persistence**: Database-backed cart with session fallback
5. **Checkout Flow**: Multi-step form with validation, address autocomplete
6. **Admin Panel**: Filament or custom Livewire admin for catalog/orders management

---

## Troubleshooting

### SELinux (Fedora): `php entered FATAL state` / `Could not open input file: artisan`

SELinux blocks the container from reading the project folder. The volume in `compose.yaml` must carry the `:z` flag:

```yaml
volumes:
    - '.:/var/www/html:z'
```

If it still fails, relabel once with `chcon -R -t container_file_t ~/hardcover`. Always start the stack through `./vendor/bin/sail`, not `docker compose` directly, so `WWWUSER` and `WWWGROUP` are set.

### Port 5173 already in use

Another local project may already be running Vite on 5173. This project forwards Vite on **5174** through `VITE_PORT` in `.env` (documented in `.env.example`). Change it there if 5174 is also taken.

### Containers won't start
```bash
# Check Docker daemon
docker info

# Rebuild containers
./vendor/bin/sail down -v && ./vendor/bin/sail up -d --build
```

### Database connection refused
```bash
# Wait for PostgreSQL to be ready
./vendor/bin/sail psql -c "SELECT 1;"

# Check logs
./vendor/bin/sail logs pgsql
```

### Frontend assets not loading
```bash
# Ensure Vite dev server is running
./vendor/bin/sail npm run dev

# Check manifest exists
ls -la public/build/
```

### Permission issues
```bash
# Fix storage/cache permissions
./vendor/bin/sail artisan storage:link
./vendor/bin/sail root-shell chown -R sail:sail storage bootstrap/cache
```

---

## Docker Troubleshooting Guide (Fedora Linux)

This section documents Docker-specific issues encountered during setup and their solutions.

### Issue 1: "Docker is not running" / "Docker or Podman is not running"

**Symptoms:**
- `docker info` shows "Server: Docker needs to be started"
- `./vendor/bin/sail up -d` fails with "Docker or Podman is not running"

**Root Cause:** Docker daemon (dockerd) is not running.

**Solution:**
```bash
# Start Docker service (requires sudo)
sudo systemctl start docker

# Verify it's running
docker info

# Enable auto-start on boot (optional)
sudo systemctl enable docker
```

**Verification:**
```bash
systemctl is-active docker
# Should output: active
```

---

### Issue 2: "permission denied while trying to connect to the docker API at unix:///var/run/docker.sock"

**Symptoms:**
- User is in `docker` group (`groups $USER` shows `docker`)
- But `docker ps` or Sail commands fail with permission denied

**Root Cause:** Group membership changes require a new login session to take effect.

**Solutions (in order of preference):**

**Option A: Refresh group membership in current session (immediate)**
```bash
newgrp docker
# Now docker commands work
./vendor/bin/sail up -d
```

**Option B: Log out and log back in**
- Log out of your desktop session / terminal
- Log back in
- Group membership will be active

**Option C: Use sudo temporarily (not recommended for regular use)**
```bash
sudo ./vendor/bin/sail up -d
```

**Option D: Start new shell with group**
```bash
sg docker -c "./vendor/bin/sail up -d"
```

**Verification after fix:**
```bash
docker ps
# Should show running containers without permission error
```

---

### Issue 3: Docker daemon fails to start

**Symptoms:**
- `sudo systemctl start docker` fails or hangs
- `systemctl status docker` shows failed state

**Diagnostics:**
```bash
# Check detailed status
systemctl status docker

# View logs
journalctl -u docker -f

# Check for port conflicts (common on Fedora with Podman)
ss -tlnp | grep :2375
ss -tlnp | grep :2376
```

**Common Fixes:**

**A. Conflict with Podman (Fedora default)**
```bash
# Stop Podman socket if running
sudo systemctl stop podman.socket
sudo systemctl disable podman.socket

# Then start Docker
sudo systemctl start docker
```

**B. Corrupted Docker data**
```bash
# WARNING: This removes all containers, images, volumes
sudo systemctl stop docker
sudo rm -rf /var/lib/docker
sudo systemctl start docker
```

**C. SELinux issues (Fedora)**
```bash
# Check SELinux status
sestatus

# If enforcing, check audit logs
sudo ausearch -m avc -ts recent | grep docker

# Temporary workaround (not for production)
sudo setenforce 0
```

---

### Issue 4: Sail containers exit immediately / crash loop

**Symptoms:**
- `./vendor/bin/sail ps` shows containers with "Exit 1" or "Restarting"
- Logs show errors on startup

**Diagnostics:**
```bash
# View all container logs
./vendor/bin/sail logs

# View specific service logs
./vendor/bin/sail logs laravel.test
./vendor/bin/sail logs pgsql
./vendor/bin/sail logs redis
```

**Common Fixes:**

**A. Port already in use**
```bash
# Check what's using ports 80, 3306, 5432, 6379
sudo ss -tlnp | grep -E ':80|:3306|:5432|:6379'

# Kill conflicting process or change ports in compose.yaml
```

**B. Volume permission issues**
```bash
# Reset and fix permissions
./vendor/bin/sail down -v
sudo chown -R $USER:$USER ~/hardcover
./vendor/bin/sail up -d --build
```

**C. Missing .env or misconfigured database**
```bash
# Ensure .env exists and has correct DB settings
cp .env.example .env
# Edit .env with correct DB_HOST=pgsql (not localhost)
./vendor/bin/sail artisan key:generate
./vendor/bin/sail up -d
```

---

### Issue 5: Composer/NPM commands fail inside Sail

**Symptoms:**
- `./vendor/bin/sail composer install` fails
- `./vendor/bin/sail npm install` fails with permission errors

**Solution:**
```bash
# Run as root inside container for permission fixes
./vendor/bin/sail root-shell chown -R sail:sail /var/www/html

# Or rebuild with fresh dependencies
./vendor/bin/sail down -v
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail npm install
```

---

### Quick Reference: Docker Service Management (Fedora)

```bash
# Start Docker
sudo systemctl start docker

# Stop Docker
sudo systemctl stop docker

# Restart Docker
sudo systemctl restart docker

# Check status
systemctl status docker

# Enable on boot
sudo systemctl enable docker

# Disable on boot
sudo systemctl disable docker

# View logs
journalctl -u docker -f

# Check Docker version
docker --version
docker compose version
```

---

### Quick Reference: Sail Commands After Docker Fix

```bash
# Start containers
./vendor/bin/sail up -d

# Stop containers
./vendor/bin/sail down

# Stop and remove volumes (clean slate)
./vendor/bin/sail down -v

# View running containers
./vendor/bin/sail ps

# View logs
./vendor/bin/sail logs -f

# Run artisan
./vendor/bin/sail artisan migrate

# Run tests
./vendor/bin/sail test

# Access database
./vendor/bin/sail psql -U sail -d laravel

# Access Redis
./vendor/bin/sail redis-cli

# Shell into app container
./vendor/bin/sail shell

# Root shell (for permissions)
./vendor/bin/sail root-shell
```

---

## License

Proprietary - Hardcover Project Team