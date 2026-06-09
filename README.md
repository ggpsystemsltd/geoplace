# GeoPlace API Download Proxy

A modern PHP application that fetches an XML file from the GeoPlace API for a
given date range and streams it directly to the browser as a download.

## Requirements

- PHP 8.2 or higher
- Composer 2.x

## Installation

1. Install PHP dependencies:
   ```bash
   composer install
   ```

2. Copy the environment template and fill in your credentials:
   ```bash
   cp .env.example .env
   ```
   Edit `.env` and set:
   - `GEOPLACE_USR_NAME`
   - `GEOPLACE_USR_PWD`
   - `GEOPLACE_AUTHCODE`

## Usage

Point your web server document root to the `public/` directory, or use PHP's
built-in server for local development:

```bash
php -S localhost:8000 -t public/
```

Visit `http://localhost:8000/` in your browser. The file for the last 60 days
will be downloaded automatically.

Optional query parameters:
- `date_from` — start date in `Y-m-d` format
- `date_to` — end date in `Y-m-d` format

## Development

### Run tests
```bash
vendor/bin/phpunit
```

### Run static analysis
```bash
vendor/bin/phpstan analyse
```

### Run code style checks
```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Apply fixes:
```bash
vendor/bin/php-cs-fixer fix
```

## License

BSD 3-Clause License. See original `geoplace.php` or repository history for
full text.
