# Application Configuration
APP_NAME=bag-doorprize
PACKAGE_NAME=$(APP_NAME)-deploy.tar.gz

PHP_INI_DIR?=$(shell php -r 'echo dirname(php_ini_loaded_file());')

# Octane Configuration (can be overridden via environment variables)
OCTANE_SERVER?=frankenphp
OCTANE_HOST?=0.0.0.0
OCTANE_PORT?=8000
OCTANE_WORKERS?=4

# Queue Configuration
QUEUE_CONNECTION?=database
QUEUE_NAMES?=tickets,imports,draws,reports,default

.PHONY: help build package clean setup install deploy octane queue super-admin create-user-view supervisor db-backup db-restore

help:
	@echo "Offline Deployment Tool"
	@echo "-----------------------"
	@echo "Usage (Local Preparation):"
	@echo "  make build-assets  - Build assets for production (Vite)"
	@echo "  make build         - Build application for production (dependencies & assets)"
	@echo "  make package       - Build and package the application into a tarball"
	@echo "  make restore-dev   - Restore development dependencies (Composer & NPM)"
	@echo ""
	@echo "Usage (Server Deployment):"
	@echo "  make deploy        - Setup the application on the target server (requires .env)"
	@echo "  make cron          - Setup crontab to run Laravel scheduler every minute"
	@echo ""
	@echo "Usage (Application Management):"
	@echo "  make octane        - Start Laravel Octane server"
	@echo "  make queue         - Start queue worker"
	@echo "  make super-admin      - Create a super admin user"
	@echo "  make create-user-view - Create PostgreSQL user for report_points_view"
	@echo "  make optimize         - Optimize Laravel caches"
	@echo "  make clear-cache   - Clear all Laravel caches"
	@echo "  make db-backup     - Backup the application database"
	@echo "  make db-restore    - Restore the application database from a backup"
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

build-assets:
	@echo ">>> Building assets for production..."
	rm -f public/hot
	npm install
	npm run build

build:
	@echo ">>> Building application for production..."
	composer install --no-dev --optimize-autoloader
	$(MAKE) build-assets
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
	@echo ">>> Cleaning up production build dependencies..."
	rm -rf node_modules
	@echo ">>> Package created successfully: $(PACKAGE_NAME)"
	@echo ">>> You can now move this file to your offline server."
	@echo ">>> Note: node_modules has been removed. Run 'make restore-dev' to restore development environment."

restore-dev:
	@echo ">>> Restoring development dependencies..."
	composer install
	npm install
	@echo ">>> Development environment restored."

clean-assets:
	@echo ">>> Cleaning assets..."
	rm -rf public/build
	rm -f public/hot
	@echo ">>> Assets cleaned."

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
	mkdir -p storage/app/private
	mkdir -p storage/logs
	@echo ">>> Setting permissions..."
	chmod +x frankenphp
	sudo chown -R sysadmin:www-data storage bootstrap/cache
	sudo chmod -R g+s storage bootstrap/cache
	sudo chmod -R 775 storage bootstrap/cache
	@echo ">>> Optimizing Laravel..."
	php artisan key:generate --force --no-interaction
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	php artisan event:cache
	rm -f zstorage/app/public/bank-statements/term_conditions_temp.pdf
	@echo ">>> Running Database Migrations..."
	php artisan migrate --force --no-interaction
	@echo ">>> Linking Storage..."
	php artisan storage:link
	@echo ">>> Deployment complete! App is ready."

# --- APPLICATION MANAGEMENT ---

octane:
	@echo ">>> Starting Laravel Octane with $(OCTANE_SERVER)..."
	@echo ">>> Host: $(OCTANE_HOST) | Port: $(OCTANE_PORT) | Workers: $(OCTANE_WORKERS)"
	PHPRC="$(PHP_INI_DIR)" php artisan octane:start --server=$(OCTANE_SERVER) --host=$(OCTANE_HOST) --port=$(OCTANE_PORT) --workers=$(OCTANE_WORKERS)

queue:
	@echo ">>> Starting queue worker..."
	@echo ">>> Connection: $(QUEUE_CONNECTION) | Queues: $(QUEUE_NAMES)"
	php artisan queue:work --queue=$(QUEUE_NAMES) --tries=3 --timeout=300

super-admin:
	@echo ">>> Creating super admin user..."
	php artisan shield:super-admin

create-user-view:
	@echo ">>> Creating PostgreSQL user with restricted access to report_points_view..."
	@if [ ! -f .env ]; then echo "!!! ERROR: .env file not found. !!!"; exit 1; fi
	@V_DB=$$(grep DB_DATABASE .env | cut -d'=' -f2 | sed -e 's/^"//' -e 's/"$$//'); \
	V_USER=$$(grep DB_USERNAME .env | cut -d'=' -f2 | sed -e 's/^"//' -e 's/"$$//'); \
	V_PASS=$$(grep DB_PASSWORD .env | cut -d'=' -f2 | sed -e 's/^"//' -e 's/"$$//'); \
	V_HOST=$$(grep DB_HOST .env | cut -d'=' -f2 | sed -e 's/^"//' -e 's/"$$//'); \
	V_PORT=$$(grep DB_PORT .env | cut -d'=' -f2 | sed -e 's/^"//' -e 's/"$$//'); \
	read -p "Enter new username for view: " NEW_USER; \
	read -sp "Enter password for view: " NEW_PASS; echo; \
	echo ">>> Connecting to $$V_DB on $$V_HOST:$$V_PORT as $$V_USER..."; \
	PGPASSWORD="$$V_PASS" psql -h "$$V_HOST" -p "$$V_PORT" -U "$$V_USER" -d "$$V_DB" \
	-c "CREATE USER $$NEW_USER WITH PASSWORD '$$NEW_PASS';" \
	-c "GRANT CONNECT ON DATABASE $$V_DB TO $$NEW_USER;" \
	-c "GRANT USAGE ON SCHEMA public TO $$NEW_USER;" \
	-c "GRANT SELECT ON report_points_view TO $$NEW_USER;"
	@echo ">>> User '$$NEW_USER' created successfully with access to 'report_points_view'."

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
	rm -f storage/app/public/bank-statements/term_conditions_temp.pdf
	@echo ">>> Cache cleared."

cron:
	@echo ">>> Setting up crontab for Laravel scheduler..."
	@(crontab -l 2>/dev/null | grep -v "artisan schedule:run"; echo "* * * * * cd $(PWD) && php artisan schedule:run >> /dev/null 2>&1") | crontab -
	@echo ">>> Crontab updated successfully. Scheduler will run every minute."

db-backup:
	@echo ">>> Backing up application database..."
	php artisan app:database-backup

db-restore:
	@echo ">>> Restoring application database..."
	php artisan app:database-restore

clean:
	rm -f $(PACKAGE_NAME)
	@echo ">>> Cleaned up package files."

supervisor:
	@echo "Creating Supervisor configuration files..."
	
	@# Create laravel-octane.conf
	@echo "[program:laravel-octane]\n\
	process_name=%(program_name)s\n\
	directory=$(PROJECT_DIR)\n\
	command=env PHPRC=\"$(PHP_INI_DIR)\" php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000 --workers=4\n\
	autostart=true\n\
	autorestart=true\n\
	user=$(USER)\n\
	redirect_stderr=true\n\
	stdout_logfile=$(PROJECT_DIR)/storage/logs/octane.log" | sudo tee $(SUPERVISOR_CONF_PATH)/laravel-octane.conf > /dev/null

	@# Create laravel-worker.conf
	@echo "[program:laravel-worker]\n\
	process_name=%(program_name)s_%(process_num)02d\n\
	directory=$(PROJECT_DIR)\n\
	command=php artisan queue:work --queue=tickets,imports,draws,reports,default --daemon --sleep=3 --tries=3 --max-time=3600\n\
	autostart=true\n\
	autorestart=true\n\
	stopasgroup=true\n\
	killasgroup=true\n\
	user=$(USER)\n\
	numprocs=8\n\
	redirect_stderr=true\n\
	stdout_logfile=$(PROJECT_DIR)/storage/logs/workers.log\n\
	stopwaitsecs=3600" | sudo tee $(SUPERVISOR_CONF_PATH)/laravel-worker.conf > /dev/null

	@echo "Rereading and updating Supervisor..."
	sudo supervisorctl reread
	sudo supervisorctl update
	@echo "Supervisor configuration applied successfully."