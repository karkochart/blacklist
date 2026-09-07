# blacklist.localhost

Docker environment only — **PHP 8.4 (Apache) + MariaDB 11.4**. No application
code yet: drop your own Symfony project into this directory later. The shared
`nginx-proxy` from `~/PhpstormProjects/multisite` routes
`http://blacklist.localhost/` here via `VIRTUAL_HOST`.

## Layout

| Service           | Image             | Notes                                         |
|-------------------|-------------------|-----------------------------------------------|
| `blacklist-app`   | `php:8.4-apache`  | docroot `public/`, exposes `:80`, code mounted from `.` |
| `blacklist-mysql` | `mariadb:11.4`    | data in the `blacklist_mysql_data` volume     |

Right now `public/index.php` is a placeholder status page. When you add Symfony,
its own `public/index.php` replaces it; `bin/console` housekeeping
(`cache:clear`, `doctrine:database:create`, migrations) then runs automatically
on container boot.

## Run

```bash
cd ~/PhpstormProjects/multisite
./start.sh blacklist.localhost
./stop.sh  blacklist.localhost
```

or directly:

```bash
docker compose -p blacklist up -d --build
docker compose -p blacklist down
```

Open http://blacklist.localhost/  (direct: http://localhost:8096)

## Database

| From          | Host / DSN                                                                        |
|---------------|----------------------------------------------------------------------------------|
| containers    | `blacklist-mysql:3306` (compose injects `DATABASE_URL`)                          |
| host tooling  | `127.0.0.1:13307`                                                                |

Credentials: `blacklist` / `blacklist`, root password `root`, database `blacklist`.
All configurable in `.env`.

## Adding your Symfony project

1. Copy the project files into this directory (keep `Dockerfile`,
   `docker-compose.yml`, `docker-entrypoint.sh`, `.env`).
2. Merge the infra block from this `.env` into Symfony's `.env` (or keep this
   file and add Symfony's keys).
3. `./start.sh blacklist.localhost` — the entrypoint runs `composer install`
   automatically when `vendor/` is missing.
