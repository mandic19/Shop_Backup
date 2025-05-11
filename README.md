# Shop Backup Application

This repository contains a Laravel-based Shop Backup application designed to create daily backups of shop data. The application runs in Docker containers with PHP 8.4 and the latest Laravel framework version.

## System Requirements

- Docker and Docker Compose (all other dependencies are handled within containers)

## Project Setup

### 1. Clone the Repository

```bash
git clone https://github.com/mandic19/Shop_Backup.git
cd shop_backup
```

### 2. Environment Configuration

Create a `.env` file based on the provided example:

```bash
cp .env.example .env
```

Make any necessary adjustments to the environment variables in the `.env` file before proceeding. Be sure to configure the database connection and API credentials properly.

### 3. Start Docker Containers

```bash
docker-compose up -d
```

### 4. Install Dependencies

Access the PHP-FPM container and install Composer dependencies:

```bash
docker exec -it shop-backup-php-fpm bash
composer install
```

### 5. Run Migrations

Set up the database tables:

```bash
docker exec -it shop-backup-php-fpm bash
php artisan migrate
```

## Backup Process

The Shop Backup application provides two ways to execute the backup process:

### 1. Manual Execution (Command)

To manually trigger a backup, run:

```bash
docker exec -it shop-backup-php-fpm bash
php artisan shop:backup
```

### 2. Automated Execution (Job)

The backup job is scheduled to run daily via the Laravel scheduler and cron. The scheduler configuration can be found in `bootstrap/app.php`.

## Rate Limit Handling

The application includes a rate limit handler that ensures API requests do not exceed the limit of 3 requests per minute. This handler manages the request flow to prevent API throttling while optimizing the backup process.

Rate limit settings can be configured through the .env file:

SHOP_API_RATE_LIMIT: Maximum number of requests allowed
SHOP_API_TIME_WINDOW: Time window in seconds for the rate limit
SHOP_API_RATE_AFTER_HEADER: Header that contains retry-after information

## Logging

All backup operations are logged using Laravel's logging system. Logs can be found in the `storage/logs/laravel.log` file. The application uses the stack channel as configured in the `config/logging.php` file.

To view logs:

```bash
docker exec -it shop-backup-php-fpm bash
tail -f storage/logs/laravel.log
```

## Configuration

The application's behavior can be customized through various configuration files:

- **config/services.php**: Contains Shop API configuration settings.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
