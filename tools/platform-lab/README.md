# Isolated Platform Lab

Reproduces the tested PHP 8.4/8.5 runtime without changing host PHP or the
existing `wp-local/site` / `wp-local/mysql`.

The exact CI version is in `.github/workflows/ci.yml`. This lab is the local
equivalent for hosts with rootless Podman.

## Build PHP images

```bash
podman build --build-arg PHP_VERSION=8.5.12 \
  -f tools/platform-lab/Containerfile -t parsian-php:8.5.12 tools/platform-lab
podman build --build-arg PHP_VERSION=8.4.27 \
  -f tools/platform-lab/Containerfile -t parsian-php:8.4.27 tools/platform-lab
```

## Start isolated databases

Set disposable lab-only values in your shell first; never reuse production
credentials and never commit them.

```bash
export LAB_DB_PASSWORD='choose-a-disposable-lab-password'
export LAB_ROOT_PASSWORD='choose-a-disposable-root-password'

podman run -d --name parsian-mysql-8413 \
  -e MYSQL_DATABASE=parsian_wp -e MYSQL_USER=parsian \
  -e MYSQL_PASSWORD="$LAB_DB_PASSWORD" -e MYSQL_ROOT_PASSWORD="$LAB_ROOT_PASSWORD" \
  -p 127.0.0.1:3308:3306 docker.io/library/mysql:8.4.13

podman run -d --name parsian-mariadb-1233 \
  -e MARIADB_DATABASE=parsian_wp -e MARIADB_USER=parsian \
  -e MARIADB_PASSWORD="$LAB_DB_PASSWORD" -e MARIADB_ROOT_PASSWORD="$LAB_ROOT_PASSWORD" \
  -p 127.0.0.1:3310:3306 docker.io/library/mariadb:12.3.3

# Optional backward-compatibility LTS:
podman run -d --name parsian-mariadb-1189 \
  -e MARIADB_DATABASE=parsian_wp -e MARIADB_USER=parsian \
  -e MARIADB_PASSWORD="$LAB_DB_PASSWORD" -e MARIADB_ROOT_PASSWORD="$LAB_ROOT_PASSWORD" \
  -p 127.0.0.1:3309:3306 docker.io/library/mariadb:11.8.9
```

## Run the repository tests

Create a fresh WordPress 7.1.4 tree, copy the theme/plugin (or symlink them),
point `wp-config.php` at the lab DB, then run:

```bash
export PA_PHP_BIN=/usr/bin/php8.5   # any isolated 8.5.12 binary
cd wp-local
for t in pa-platform-test pa-migration-test pa-assistant-test pa-bale-test pa-security-test \
         pa-durability-test pa-webhook-test pm-1.6.0-smoke pa-theme-compat; do
  ./wp eval-file "tests/$t.php"
done
```

The `./wp` wrapper honors `PA_PHP_BIN`; it defaults to `/usr/bin/php8.5`.
Never point it at the personal local site while testing a different PHP patch
unless that is an intentional, approved upgrade.
