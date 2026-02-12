# Offline Deployment Guide

This guide explains how to deploy the application to a server with **no internet connection**.

## 1. Local Preparation (With Internet)

Run these steps on your development machine where you have internet access.

1.  Open your terminal in the project root.
2.  Execute the following command to build and package the app:
    ```bash
    make package
    ```
3.  This will:
    - Install production dependencies (`vendor/`).
    - Build frontend assets (`public/build/`).
    - Compact everything into a file named `bag-doorprize-deploy.tar.gz`.

## 2. Server Setup (Offline)

Move the generated `bag-doorprize-deploy.tar.gz` to your target server using a USB drive or local network.

1.  Extract the package:
    ```bash
    tar -xzf bag-doorprize-deploy.tar.gz -C /path/to/webroot
    ```
2.  Navigate to the project directory:
    ```bash
    cd /path/to/webroot
    ```
3.  Create and configure your `.env` file manually:
    ```bash
    cp .env.example .env
    nano .env # Adjust DB_HOST, DB_PASSWORD, etc.
    # Set OCTANE_SERVER=frankenphp
    ```
4.  Run the deployment script:
    ```bash
    make deploy
    ```
5.  Setup the automated task scheduler (Crontab):
    ```bash
    make cron
    ```

## 3. Running the Application (Octane + FrankenPHP)

This application is designed to run with **Laravel Octane** and **FrankenPHP**.

To start the application server:

```bash
make octane
```

The server will start on port `8000` by default (as defined in the Makefile).

## Notes

- **PHP & Web Server**: Ensure the server already has PHP 8.2+, a database (PostgreSQL/MySQL), and required extensions. FrankenPHP handles web requests directly, so a separate Nginx/Apache is optional.
- **Binary Status**: The `frankenphp` binary is bundled within the deployment package. The `make deploy` command automatically sets the correct execution permissions for it.
