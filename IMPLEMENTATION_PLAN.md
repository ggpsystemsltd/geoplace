# GeoPlace Modernisation: Implementation Plan

> **Status:** Awaiting development environment (PHP 8.2+, Composer).
> **Target PHP:** 8.2+
> **Goal:** Refactor a single PHP 5 procedural script into a Composer-managed, PSR-4 autoloaded, fully tested, statically-analysed modern application.

---

## 1. Current State

- Single file: `geoplace.php`
- Relies on `pecl_http` extension (`HttpRequest`) — abandoned, unavailable in PHP 8.
- Hardcoded credentials (`$usr_name`, `$usr_pwd`, `$authcode`) in source.
- No namespaces, classes, or autoloading.
- Weak input validation (`filter_input_array` with empty rules).
- Buggy date format: `Y-m-d\TH:I:s.000\Z` uses `I` (DST flag) instead of `i` (minutes).
- Echoes errors as HTML strings with no HTTP status codes.
- Writes API response to a temp file on disk, then re-reads it to stream to the browser.
- Generated filename contains colons (`:`), which are invalid on Windows and messy on Unix.

---

## 2. Target State

A standalone Composer project with:
- **PHP 8.2+** strict typing.
- **PSR-4 autoloading**.
- **Guzzle** as the HTTP client.
- **Symfony HttpFoundation** (`Request` / `Response` / `StreamedResponse`) for request/response handling.
- **vlucas/phpdotenv** for environment-based configuration.
- **PHPUnit** for testing.
- **PHPStan** at **Level 8**.
- **PHP-CS-Fixer** with PER-2 rules.
- Direct streaming of the API response to the browser (no temp files).
- Proper HTTP status codes for all error paths.
- Strong input validation with clear error messages.

---

## 3. Files to Create / Modify

### 3.1 Root Configuration Files

| File | Action | Notes |
|------|--------|-------|
| `composer.json` | Create | PSR-4 autoloading, PHP 8.2+, dependency constraints. |
| `.env.example` | Create | Template for `GEOPLACE_USR_NAME`, `GEOPLACE_USR_PWD`, `GEOPLACE_AUTHCODE`, `GEOPLACE_API_URL`. |
| `.env` | Create (gitignored) | Real secrets. Must never be committed. |
| `phpunit.xml` | Create | Bootstrap `vendor/autoload.php`, directory `tests`. |
| `phpstan.neon` | Create | Level 8, paths: `src/`, `public/`. |
| `.php-cs-fixer.dist.php` | Create | PER-2 ruleset, target PHP 8.2. |
| `.gitignore` | Modify | Add `.env`, `/vendor/`, `*.cache`, `.php-cs-fixer.php`, `.phpunit.result.cache`, `composer.lock` (if desired). |
| `README.md` | Modify | Rewrite with modern setup instructions. |
| `geoplace.php` | Delete | All logic moves to `src/` and `public/index.php`. |

### 3.2 Source Code (`src/`)

| File | Responsibility |
|------|----------------|
| `src/Config/GeoPlaceConfig.php` | Immutable value object. Reads from `$_ENV` (loaded by `vlucas/phpdotenv`). Validates that required env vars are present. |
| `src/Service/DateRangeValidator.php` | Validates `date_from` and `date_to` query parameters. Must be `Y-m-d`. Returns a validated `DateTimeImmutable` pair or throws a `ValidationException`. |
| `src/Exception/ValidationException.php` | Extends `\RuntimeException`. Carries a user-safe message and an HTTP status code (e.g. 400). |
| `src/Exception/ApiException.php` | Extends `\RuntimeException`. Carries an HTTP status code (e.g. 502 for upstream failure, 500 for unexpected). |
| `src/Service/GeoPlaceClient.php` | Wraps Guzzle. Accepts `GeoPlaceConfig` and validated date range. Sends GET request to the API with proper headers and query params. Returns a `Psr7\StreamInterface` on success. Throws `ApiException` on failure. |
| `src/Controller/DownloadController.php` | Orchestrator. Injected with `GeoPlaceClient` and `DateRangeValidator`. Builds the Symfony `Request`, validates input, calls the client, and returns a Symfony `StreamedResponse` with correct `Content-Type`, `Content-Disposition`, and `Content-Length` headers. Catches exceptions and returns a JSON `Response` with the correct HTTP status code. |

### 3.3 Public Entry Point

| File | Responsibility |
|------|----------------|
| `public/index.php` | Bootstraps the app: loads `vendor/autoload.php`, loads `.env` via `Dotenv::createImmutable(__DIR__ . '/../')->load()`, creates `GeoPlaceConfig`, creates `GeoPlaceClient` (injected with Guzzle client), creates `DownloadController`, calls `handle()`, sends the response. Must wrap the entire bootstrap in a try/catch to return a `500` JSON error for truly unexpected failures. |

### 3.4 Tests (`tests/`)

| File | Responsibility |
|------|----------------|
| `tests/bootstrap.php` | Loads `vendor/autoload.php`. |
| `tests/Unit/DateRangeValidatorTest.php` | Test valid dates, invalid formats, reversed ranges (`date_from > date_to`), missing params (defaults). |
| `tests/Unit/GeoPlaceConfigTest.php` | Test that missing env vars throw `\RuntimeException` (or similar). Test that valid env vars produce correct values. |
| `tests/Unit/GeoPlaceClientTest.php` | Mock Guzzle `ClientInterface`. Test that correct headers and query params are sent. Test that non-2xx responses throw `ApiException`. Test that success returns a stream. |
| `tests/Unit/DownloadControllerTest.php` | Mock the services. Test that valid input produces a `StreamedResponse` with correct headers. Test that invalid input produces a `JsonResponse` with 400. Test that API failure produces a `JsonResponse` with 502. |

---

## 4. Dependency List (`composer.json`)

### Production
- `php: ^8.2`
- `guzzlehttp/guzzle: ^7.9`
- `symfony/http-foundation: ^7.1`
- `vlucas/phpdotenv: ^5.6`

### Development
- `phpunit/phpunit: ^11.0`
- `phpstan/phpstan: ^1.11`
- `friendsofphp/php-cs-fixer: ^3.59`

> **Note:** Use **caret** (`^`) constraints for forward compatibility. Do not pin exact patch versions unless a security issue requires it.

---

## 5. Detailed Behaviour Specification

### 5.1 Configuration (`GeoPlaceConfig`)

Required environment variables:
- `GEOPLACE_API_URL` — defaults to `https://api.geoplace.co.uk/v1.0/cou`.
- `GEOPLACE_USR_NAME`
- `GEOPLACE_USR_PWD`
- `GEOPLACE_AUTHCODE`

If any required variable is missing, the constructor must throw `\RuntimeException` immediately on bootstrap.

### 5.2 Date Range Validation (`DateRangeValidator`)

- Read `date_from` and `date_to` from the query string.
- If both are absent, default to:
  - `date_from` = today − 60 days at `00:00:00.000Z`
  - `date_to` = today at `23:59:59.999Z` (or `00:00:00.000Z` if matching original behaviour exactly)
- Format must be exactly `Y-m-d`.
- Parse with `DateTimeImmutable::createFromFormat('!Y-m-d', ...)` (note the `!` to zero out time parts).
- If parsing fails or `getLastErrors()` is non-empty, throw `ValidationException('Invalid date format. Use Y-m-d.', 400)`.
- If `date_from > date_to`, throw `ValidationException('date_from must be before or equal to date_to.', 400)`.
- Return a tuple/array/DTO of `DateTimeImmutable` objects.

### 5.3 API Client (`GeoPlaceClient`)

- Accept `GeoPlaceConfig` in constructor.
- Method signature:
  ```php
  public function fetchCouXml(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): StreamInterface;
  ```
- Build Guzzle request:
  - **Method:** `GET`
  - **URL:** `GEOPLACE_API_URL`
  - **Headers:** `usr_name`, `usr_pwd`
  - **Query params:** `format=xml`, `authcode`, `date_from`, `date_to` formatted as `Y-m-d\TH:i:s.000\Z`.
- Request **must** use Guzzle’s `stream => true` option so the response body is a `StreamInterface`.
- On `RequestException` or non-2xx status, throw `ApiException` with message and HTTP code mapped appropriately:
  - `4xx` from upstream → `502 Bad Gateway`
  - Network/connect errors → `502 Bad Gateway`
  - Unexpected errors → `500 Internal Server Error`

### 5.4 Controller (`DownloadController`)

- Method signature:
  ```php
  public function handle(Request $request): Response;
  ```
- Steps:
  1. Try to validate dates via `DateRangeValidator`.
  2. Call `GeoPlaceClient::fetchCouXml()`.
  3. Read `Content-Length` from the Guzzle response headers (if present). If absent, omit the header.
  4. Return a `StreamedResponse` that writes the Guzzle stream to the output buffer.
  5. Set headers on the response:
     - `Content-Type: text/xml`
     - `Content-Disposition: attachment; filename="<filename>"`
  6. Generate filename safely: `geoplace_{date_to}.xml` with colons replaced by dashes, e.g. `geoplace_2024-06-09T12-30-00.000Z.xml`.
- Catch `ValidationException` → return `JsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 400)`.
- Catch `ApiException` → return `JsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 502)`.
- Catch `\Throwable` → return `JsonResponse(['error' => 'An unexpected error occurred.'], 500)`.

### 5.5 Front Controller (`public/index.php`)

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

// 1. Load environment
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Environment configuration error.']);
    exit;
}

// 2. Create Request
$request = Request::createFromGlobals();

// 3. Bootstrap services
//    (try/catch around everything to guarantee a JSON 500 on total failure)
try {
    $config = new GeoPlaceConfig($_ENV);
    $guzzle = new GuzzleHttp\Client();
    $client = new GeoPlaceClient($config, $guzzle);
    $validator = new DateRangeValidator();
    $controller = new DownloadController($client, $validator);

    $response = $controller->handle($request);
    $response->send();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An unexpected server error occurred.']);
    exit;
}
```

---

## 6. Testing Strategy

### 6.1 Unit Tests
- **GeoPlaceConfigTest:** Assert missing env var throws. Assert all getters return expected strings.
- **DateRangeValidatorTest:**
  - Valid `Y-m-d` pair → returns correct `DateTimeImmutable` objects.
  - Missing params → returns default range (today − 60 days … today).
  - Invalid format (`99-99-99`) → throws `ValidationException` with 400.
  - Reversed range → throws `ValidationException` with 400.
- **GeoPlaceClientTest:**
  - Mock Guzzle handler returning 200 + XML body stream → `fetchCouXml()` returns a stream whose contents match.
  - Mock Guzzle handler returning 404 → throws `ApiException` with 502.
  - Mock Guzzle handler throwing `ConnectException` → throws `ApiException` with 502.

### 6.2 Integration / Smoke Test
- A single test that boots `public/index.php` with missing `.env` and asserts a 500 JSON response. (Optional, but nice.)

### 6.3 Static Analysis
- Run `vendor/bin/phpstan analyse`.
- Target **Level 8**.
- Zero errors before considering the task complete.

### 6.4 Code Style
- Run `vendor/bin/php-cs-fixer fix --dry-run --diff`.
- If any changes are reported, apply them (`--dry-run` removed).

---

## 7. Environment Prerequisites Checklist

Before executing this plan, ensure the following are installed and accessible in `$PATH`:

- [ ] **PHP 8.2+** (`php -v`)
- [ ] **Composer 2.x+** (`composer --version`)
- [ ] Git repository is clean (or at least the working tree is known).

Once the above are confirmed, proceed to **Step 8**.

---

## 8. Step-by-Step Implementation Commands

> **Copy-pasteable commands to run in the project root once the environment is ready.**

1. **Initialise Composer & install production dependencies**
   ```bash
   composer init --name="ggpsystems/geoplace" --description="GeoPlace API download proxy" --author="Murray Crane <murray.crane@ggpsystems.co.uk>" --no-interaction
   composer require php:^8.2 guzzlehttp/guzzle symfony/http-foundation vlucas/phpdotenv
   composer require --dev phpunit/phpunit phpstan/phpstan friendsofphp/php-cs-fixer
   ```

2. **Create directories**
   ```bash
   mkdir -p src/Config src/Service src/Exception src/Controller public tests/Unit
   ```

3. **Write all source files** (see Section 3.2 for contents/spec).

4. **Write configuration files** (see Section 3.1 for contents/spec).

5. **Write tests** (see Section 3.4 for contents/spec).

6. **Run tests & quality gates**
   ```bash
   vendor/bin/phpunit
   vendor/bin/phpstan analyse
   vendor/bin/php-cs-fixer fix --dry-run --diff
   ```

7. **Fix any failures, repeat Step 6.**

8. **Clean up legacy file**
   ```bash
   rm geoplace.php
   ```

9. **Update `.gitignore` and commit.**

---

## 9. README Rewrite (Brief)

The new `README.md` should contain:
1. One-sentence description.
2. Requirements: PHP 8.2+, Composer 2.x.
3. Installation:
   ```bash
   composer install
   cp .env.example .env
   # edit .env with your credentials
   ```
4. Usage: point a web server at `public/` or use the built-in PHP server:
   ```bash
   php -S localhost:8000 -t public/
   ```
5. Testing & quality commands (PHPUnit, PHPStan, CS-Fixer).
6. License (retain BSD-3-clause).

---

## 10. Risk Log

| Risk | Mitigation |
|------|------------|
| API URL or auth scheme changed since 2015. | The `.env.example` documents the original URL; user can adjust. The Guzzle wrapper is generic enough to adapt. |
| Colon-safe filename might break an existing downstream consumer expecting the old format. | Document the change in README. Old format was buggy on Windows anyway. |
| `Content-Length` may not be returned by the GeoPlace API. | Omit the header if absent; browsers handle chunked downloads fine. |

---

## 11. Definition of Done

- [ ] `geoplace.php` deleted.
- [ ] `composer.json`, `composer.lock`, `vendor/` present and valid.
- [ ] `public/index.php` boots cleanly and proxies the API.
- [ ] All env vars are read from `.env`; `.env.example` is committed.
- [ ] PHPUnit suite passes (100%).
- [ ] PHPStan Level 8 passes with 0 errors.
- [ ] PHP-CS-Fixer reports no changes needed.
- [ ] README.md rewritten with modern instructions.
- [ ] Git history is clean (single logical commit for the refactor).

---

*Plan generated by OpenCode. Ready for implementation once the development environment is provisioned.*
