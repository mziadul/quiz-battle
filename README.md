# QuizFight - Laravel 11 + Vue 3 (Vite) Docker Monolith

A containerized setup for Laravel 11 and Vue 3 (Inertia/Vite) running on PHP 8.4-FPM and Nginx, connected to a local MySQL instance on the host machine.

---

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) & [Docker Compose](https://docs.docker.com/compose/install/)
- MySQL running on the Host Machine (Port `3306`)

---

## First-Time Setup (After Git Pull)

Run the following commands sequentially after cloning or pulling the repository:

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Build and start containers
docker compose --profile dev up -d --build

# 3. Install Composer dependencies
docker compose exec app composer install

# 4. Install NPM dependencies
docker compose exec app npm install

# 5. Generate Application Key
docker compose exec app php artisan key:generate

# 6. Run Database Migrations
docker compose exec app php artisan migrate

# 7. Set permissions for Storage & Cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache