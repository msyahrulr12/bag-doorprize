# Makefile Quick Reference Guide

## Overview

The Makefile provides convenient commands for building, deploying, and managing the Bag Doorprize application. All commands support dynamic configuration through environment variables.

## Quick Start

```bash
# View all available commands
make help

# Create super admin user
make super-admin

# Start application server
make octane

# Start queue worker
make queue

# Backup database
make db-backup

# Restore database
make db-restore
```

## Configuration Variables

Override these variables when running commands:

| Variable           | Default                                 | Description                                         |
| ------------------ | --------------------------------------- | --------------------------------------------------- |
| `OCTANE_SERVER`    | `frankenphp`                            | Octane server type (frankenphp, swoole, roadrunner) |
| `OCTANE_HOST`      | `0.0.0.0`                               | Server host address                                 |
| `OCTANE_PORT`      | `8000`                                  | Server port number                                  |
| `OCTANE_WORKERS`   | `4`                                     | Number of worker processes                          |
| `QUEUE_CONNECTION` | `database`                              | Queue connection driver                             |
| `QUEUE_NAMES`      | `tickets,imports,draws,reports,default` | Comma-separated queue names                         |

## Commands Reference

### Local Development & Packaging

#### `make build`

Builds the application for production:

- Installs production dependencies
- Builds frontend assets
- Cleans up node_modules

```bash
make build
```

#### `make package`

Creates a deployment tarball:

- Runs `make build`
- Creates `bag-doorprize-deploy.tar.gz`
- Excludes unnecessary files (.git, tests, etc.)

```bash
make package
```

#### `make clean`

Removes the deployment package:

```bash
make clean
```

### Server Deployment

#### `make deploy`

Deploys the application on the server:

- Checks for `.env` file
- Creates necessary directories
- Sets permissions (including `sudo chown` and `chmod g+s` for storage/cache)
- Generates app key
- Caches configuration
- Runs migrations
- Links storage

**Note**: This command requires `sudo` privileges to set directory ownership and the setgid bit.

**Requirements**: `.env` file must exist

```bash
make deploy
```

#### `make cron`

Sets up Laravel scheduler in crontab:

- Adds cron job to run `schedule:run` every minute
- Preserves existing crontab entries

```bash
make cron
```

### Application Management

#### `make octane`

Starts Laravel Octane server:

```bash
# Use defaults (frankenphp, 0.0.0.0:8000, 4 workers)
make octane

# Custom configuration
OCTANE_PORT=8080 OCTANE_WORKERS=8 make octane

# Use Swoole instead of FrankenPHP
OCTANE_SERVER=swoole OCTANE_PORT=9000 make octane
```

#### `make queue`

Starts queue worker:

```bash
# Use defaults (database connection, tickets,imports,draws queues)
make queue

# Custom queues
QUEUE_NAMES=default,emails make queue
```

#### `make super-admin`

Creates a super admin user interactively:

- Prompts for name, email, password
- Creates user with `super_admin` role
- Grants all permissions

```bash
make super-admin
```

#### `make optimize`

Optimizes Laravel caches:

- Config cache
- Route cache
- View cache
- Event cache

```bash
make optimize
```

#### `make clear-cache`

Clears all Laravel caches:

- Config cache
- Route cache
- View cache
- Event cache
- Application cache

```bash
make clear-cache
```

### Database Management

#### `make db-backup`

Backs up the application database (PostgreSQL):

- Uses `pg_dump` with `--clean` and `--if-exists`
- Saves to `storage/app/backups/backup-YYYY-MM-DD-HHmmss.sql.gz`
- Automatically ignores Core T24 databases

```bash
make db-backup
```

#### `make db-restore`

Restores the application database from a backup file:

- Provides an interactive list of available backups
- Asks for confirmation before overwriting data
- Uses `psql` for restoration

```bash
# Interactive restore
make db-restore

# Restore specific file
php artisan app:database-restore backup-2026-04-28-120000.sql.gz
```

## Common Workflows

### First-Time Deployment

```bash
# 1. Extract package on server
tar -xzf bag-doorprize-deploy.tar.gz -C /var/www/html

# 2. Navigate to directory
cd /var/www/html/bag-doorprize

# 3. Create .env file
cp .env.example .env
nano .env  # Configure database, etc.

# 4. Deploy
make deploy

# 5. Setup cron
make cron

# 6. Create super admin
make super-admin

# 7. Start services
make octane  # In one terminal
make queue   # In another terminal
```

### Development Workflow

```bash
# Clear caches during development
make clear-cache

# Optimize for testing
make optimize

# Restart Octane after code changes
# (Octane auto-reloads, but you can force restart)
# Ctrl+C to stop, then:
make octane
```

### Production Deployment

```bash
# On local machine (with internet)
make package

# Transfer to server
scp bag-doorprize-deploy.tar.gz user@server:/tmp/

# On server (offline)
cd /var/www/html
tar -xzf /tmp/bag-doorprize-deploy.tar.gz
make deploy
make optimize

# Start with production settings
OCTANE_WORKERS=8 make octane
```

### Running Multiple Instances

```bash
# Instance 1 (port 8000)
OCTANE_PORT=8000 make octane

# Instance 2 (port 8001)
OCTANE_PORT=8001 make octane

# Instance 3 (port 8002)
OCTANE_PORT=8002 make octane
```

### Queue Worker Management

```bash
# Start queue worker for specific queues
QUEUE_NAMES=tickets make queue

# Start multiple workers for different queues
QUEUE_NAMES=tickets make queue &
QUEUE_NAMES=imports make queue &
QUEUE_NAMES=draws make queue &
```

## Process Management with systemd

For production, create systemd services:

### Octane Service

Create `/etc/systemd/system/bag-octane.service`:

```ini
[Unit]
Description=Bag Doorprize Octane Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/bag-doorprize
ExecStart=/usr/bin/make octane
Restart=always
Environment="OCTANE_WORKERS=8"
Environment="OCTANE_PORT=8000"

[Install]
WantedBy=multi-user.target
```

### Queue Worker Service

Create `/etc/systemd/system/bag-queue.service`:

```ini
[Unit]
Description=Bag Doorprize Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/bag-doorprize
ExecStart=/usr/bin/make queue
Restart=always

[Install]
WantedBy=multi-user.target
```

### Enable and Start Services

```bash
sudo systemctl daemon-reload
sudo systemctl enable bag-octane bag-queue
sudo systemctl start bag-octane bag-queue
sudo systemctl status bag-octane bag-queue
```

## Troubleshooting

### Port Already in Use

```bash
# Check what's using the port
lsof -i :8000

# Use a different port
OCTANE_PORT=8001 make octane
```

### Permission Errors

```bash
# Fix permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Queue Not Processing

```bash
# Check queue worker is running
ps aux | grep "queue:work"

# Restart queue worker
# Ctrl+C to stop, then:
make queue
```

### Cache Issues

```bash
# Clear all caches
make clear-cache

# Rebuild caches
make optimize
```

## Tips & Best Practices

1. **Always use environment variables** for configuration instead of editing the Makefile
2. **Run `make optimize`** after deployment for better performance
3. **Use systemd services** in production for automatic restarts
4. **Monitor logs** in `storage/logs/laravel.log`
5. **Keep queue workers running** for bulk drawing functionality
6. **Use multiple workers** for high-traffic scenarios
7. **Test in staging** before deploying to production

## See Also

- `README_DEPLOY.md` - Full deployment guide
- `DEPLOYMENT_CHECKLIST.md` - Pre-deployment checklist
- `AUDITABLE_IMPLEMENTATION.md` - Audit logging documentation
