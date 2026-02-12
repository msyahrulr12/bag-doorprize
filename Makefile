APP_NAME=bag-doorprize
PACKAGE_NAME=$(APP_NAME)-deploy.tar.gz

.PHONY: help build package clean setup install deploy

help:
	@echo "Offline Deployment Tool"
	@echo "-----------------------"
	@echo "Usage (Local Preparation):"
	@echo "  make package       - Build and package the application into a tarball"
	@echo ""
	@echo "Usage (Server Deployment):"
	@echo "  make deploy        - Setup the application on the target server (requires .env)"
	@echo "  make cron          - Setup crontab to run Laravel scheduler every minute"

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
	@echo ">>> Setting permissions..."
	chmod +x frankenphp
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

octane:
	@echo ">>> Starting Laravel Octane with FrankenPHP..."
	php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000 --workers=4

cron:
	@echo ">>> Setting up crontab for Laravel scheduler..."
	@(crontab -l 2>/dev/null | grep -v "artisan schedule:run"; echo "* * * * * cd $(PWD) && php artisan schedule:run >> /dev/null 2>&1") | crontab -
	@echo ">>> Crontab updated successfully. Scheduler will run every minute."

clean:
	rm -f $(PACKAGE_NAME)
	@echo ">>> Cleaned up package files."
