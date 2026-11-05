<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Pruebas (tests)

El esquema base (`productos`, `clientes`, `servicios`, `citas`, `ventas`, ...) NO está
definido en migraciones: existe solo en la base MySQL real. Por eso los tests corren
contra una **base de datos de pruebas dedicada** (`beautysalon_testing`) usando
`DatabaseTransactions` (cada test se revierte al terminar; los datos reales nunca se tocan).

Crear / refrescar esa base (solo estructura) desde la base real:

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root --no-data --routines --skip-comments beautysalon --result-file="$env:TEMP\schema.sql"
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS beautysalon_testing; CREATE DATABASE beautysalon_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "`"C:\xampp\mysql\bin\mysql.exe`" -u root beautysalon_testing < $env:TEMP\schema.sql"
```

Ejecutar la suite:

```powershell
php artisan test
```

