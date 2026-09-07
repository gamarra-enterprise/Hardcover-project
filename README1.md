# Hardcover - E-commerce Platform for Physical Books

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
| **Laravel** | 11.x | Backend framework |
| **Laravel Sail** | 1.67.x | Docker development environment |
| **PostgreSQL** | 16+ | Primary database |
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
- **Make** (optional, for convenience commands)

### 1. Clone and Navigate

```bash
cd ~/hardcover
```

### 2. Start Docker Containers with Sail

```bash
# Start all services in detached mode
./vendor/bin/sail up -d

# Verify containers are running
./vendor/bin/sail ps
```

Expected services:
- `laravel.test` - PHP 8.2+ application container
- `pgsql` - PostgreSQL 16 database
- `redis` - Redis for cache/sessions/queues
- `mailpit` - Local email testing
- `selenium` - Browser testing (optional)

### 3. Configure Environment

The `.env` file is pre-configured for Sail with PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

**Add your payment gateway credentials:**

```env
# Stripe
STRIPE_KEY=pk_test_xxxxx
STRIPE_SECRET=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx

# Mercado Pago
MERCADOPAGO_ACCESS_TOKEN=APP_USR-xxxxx
MERCADOPAGO_PUBLIC_KEY=APP_USR-xxxxx
MERCADOPAGO_WEBHOOK_SECRET=xxxxx
```

### 4. Run Database Migrations

```bash
# Run all migrations
./vendor/bin/sail artisan migrate

# Optional: Seed with sample data
./vendor/bin/sail artisan db:seed
```

### 5. Compile Frontend Assets

```bash
# Development (with hot reload)
./vendor/bin/sail npm run dev

# Production build
./vendor/bin/sail npm run build
```

### 6. Verify Installation

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

## Next Steps

1. **Define Core Models**: `Book`, `Category`, `Author`, `Publisher`, `Order`, `OrderItem`, `Shipment`
2. **Implement Payment Gateways**: Create `StripeGateway` and `MercadoPagoGateway` implementing `PaymentGatewayInterface`
3. **Build Catalog UI**: Product listing with filters, search, pagination
4. **Cart Persistence**: Database-backed cart with session fallback
5. **Checkout Flow**: Multi-step form with validation, address autocomplete
6. **Admin Panel**: Filament or custom Livewire admin for catalog/orders management

---

## Troubleshooting

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