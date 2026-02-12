# Application Configuration
APP_NAME=bag-doorprize
PACKAGE_NAME=$(APP_NAME)-deploy.tar.gz

# Octane Configuration (can be overridden via environment variables)
OCTANE_SERVER?=frankenphp
OCTANE_HOST?=0.0.0.0
OCTANE_PORT?=8000
OCTANE_WORKERS?=4

# Queue Configuration
QUEUE_CONNECTION?=database
QUEUE_NAMES?=tickets,imports,draws

.PHONY: help build package clean setup install deploy octane queue super-admin

help:
	@echo "Offline Deployment Tool"
	@echo "-----------------------"
	@echo "Usage (Local Preparation):"
	@echo "  make package       - Build and package the application into a tarball"
	@echo ""
	@echo "Usage (Server Deployment):"
	@echo "  make deploy        - Setup the application on the target server (requires .env)"
	@echo "  make cron          - Setup crontab to run Laravel scheduler every minute"
	@echo ""
	@echo "Usage (Application Management):"
	@echo "  make octane        - Start Laravel Octane server"
	@echo "  make queue         - Start queue worker"
	@echo "  make super-admin   - Create a super admin user"
	@echo "  make optimize      - Optimize Laravel caches"
	@echo "  make clear-cache   - Clear all Laravel caches"
	@echo ""
	@echo "Configuration (override with environment variables):"
	@echo "  OCTANE_SERVER      - Octane server type (default: frankenphp)"
	@echo "  OCTANE_HOST        - Octane host (default: 0.0.0.0)"
	@echo "  OCTANE_PORT        - Octane port (default: 8000)"
	@echo "  OCTANE_WORKERS     - Number of workers (default: 4)"
	@echo "  QUEUE_NAMES        - Queue names (default: tickets,imports,draws)"
	@echo ""
	@echo "Example:"
	@echo "  OCTANE_PORT=8080 OCTANE_WORKERS=8 make octane"

# --- LOCAL PREPARATION ---

build:
	@echo ">>> Building application for production..."
	composer install --no-dev --optimize-autoloader
	npm install
	npm run build
	@echo ">>> Cleaning up build dependencies..."
	rm -rf node_modules
	@echo ">>> Application build complete."

package: build
	@echo ">>> Packaging application into $(PACKAGE_NAME)..."
	tar -czf $(PACKAGE_NAME) \
		--exclude='.git' \
		--exclude='.github' \
		--exclude='.env' \
		--exclude='.env.example' \
		--exclude='storage/logs/*.log' \
		--exclude='storage/framework/cache/data/*' \
		--exclude='storage/framework/sessions/*' \
		--exclude='storage/framework/views/*.php' \
		--exclude='tests' \
		--exclude='$(PACKAGE_NAME)' \
		--exclude='node_modules' \
		.
	@echo ">>> Package created successfully: $(PACKAGE_NAME)"
	@echo ">>> You can now move this file to your offline server."

# --- SERVER DEPLOYMENT ---

deploy:
	@echo ">>> Starting deployment process..."
	@if [ ! -f .env ]; then \
		echo "!!! ERROR: .env file not found. !!!"; \
		echo "Please create a .env file with production settings before running deploy."; \
		exit 1; \
	fi
	@echo ">>> Syncing folders..."
	mkdir -p storage/framework/cache/data
	mkdir -p storage/framework/sessions
	mkdir -p storage/framework/views
	mkdir -p storage/app/public
	mkdir -p storage/logs
	@echo ">>> Setting permissions..."
	chmod +x frankenphp
	chmod -R 775 storage bootstrap/cache
	@echo ">>> Optimizing Laravel..."
	php artisan key:generate --force --no-interaction
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	php artisan event:cache
	@echo ">>> Running Database Migrations..."
	php artisan migrate --force --no-interaction
	@echo ">>> Linking Storage..."
	php artisan storage:link
	@echo ">>> Deployment complete! App is ready."

# --- APPLICATION MANAGEMENT ---

octane:
	@echo ">>> Starting Laravel Octane with $(OCTANE_SERVER)..."
	@echo ">>> Host: $(OCTANE_HOST) | Port: $(OCTANE_PORT) | Workers: $(OCTANE_WORKERS)"
	php artisan octane:start --server=$(OCTANE_SERVER) --host=$(OCTANE_HOST) --port=$(OCTANE_PORT) --workers=$(OCTANE_WORKERS)

queue:
	@echo ">>> Starting queue worker..."
	@echo ">>> Connection: $(QUEUE_CONNECTION) | Queues: $(QUEUE_NAMES)"
	php artisan queue:work --queue=$(QUEUE_NAMES) --tries=3 --timeout=300

super-admin:
	@echo ">>> Creating super admin user..."
	php artisan shield:super-admin

optimize:
	@echo ">>> Optimizing Laravel..."
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	php artisan event:cache
	@echo ">>> Optimization complete."

clear-cache:
	@echo ">>> Clearing all caches..."
	php artisan config:clear
	php artisan route:clear
	php artisan view:clear
	php artisan event:clear
	php artisan cache:clear
	@echo ">>> Cache cleared."

cron:
	@echo ">>> Setting up crontab for Laravel scheduler..."
	@(crontab -l 2>/dev/null | grep -v "artisan schedule:run"; echo "* * * * * cd $(PWD) && php artisan schedule:run >> /dev/null 2>&1") | crontab -
	@echo ">>> Crontab updated successfully. Scheduler will run every minute."

clean:
	rm -f $(PACKAGE_NAME)
	@echo ">>> Cleaned up package files."
