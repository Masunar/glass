######### Basic Docker utilities
# Use compose.yml to deploy/run containers in the background
up:
	docker compose -f compose.yml --env-file docker/.docker.env up -d
# Down containers deployed by compose.yml and .docker.env file
down:
	docker compose -f compose.yml --env-file docker/.docker.env down
# Stop containers deployed by compose.yml and .docker.env file
stop:
	docker compose -f compose.yml --env-file docker/.docker.env stop
restart:
	make stop && make up
# List running container deployed by compose.yml and .docker.env file
# Build container images used by compose.yml to provision containers
build:
	docker compose -f compose.yml --env-file docker/.docker.env build
# Destroy containers (including their volumes) deployed by compose.yml and .docker.env file
destroy:
	docker compose -f compose.yml --env-file docker/.docker.env down -v
# Restart containers deployed by compose.yml and .docker.env file
ps:
	docker compose -f compose.yml --env-file docker/.docker.env ps



######### Advanced Docker utilities
# Use compose.yml definitions and .docker.env variables to rebuild and run container in background with fresh image pull
rebuild:
	docker compose -f compose.yml --env-file docker/.docker.env build --no-cache --pull
	docker compose -f compose.yml --env-file docker/.docker.env up -d --build $(container)
# Clean all containers data
clear-container-deps:
	docker compose -f compose.yml --env-file docker/.docker.env rm -f -s -v
# Docker weird problems solver
troubleshooting:
	make destroy && make clear-container-deps && make rebuild
# Stop all working containers and run only those defined in compose.yml
failsafe-up:
	@if [ "$$(docker ps -q)" != "" ]; then \
		docker stop $$(docker ps -q); \
	fi
	make up
# Create docker env file if does not exists
docker-env-init:
	@if [ ! -f docker/.docker.env ]; then cp -n docker/.docker.env.example docker/.docker.env; fi
# Complete app installation
init:
	make docker-env-init
	make failsafe-up
	make app-init



######### Common utilities
# Get into running app container shell
app-shell:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash
# Get into running nginx container shells
nginx-shell:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it nginx sh
# Get into running mysql shell
mysql-shell:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it mariadb bash
# Get into running container shells
mysql:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it mariadb mariadb -u root -proot



######### Application utilities
# Run setup-dev command
app-init:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "composer setup-dev"
# Run setup-dev command
frontend-install:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "composer install-frontend"
# Run npm dev command
frontend-dev:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "cd frontend && npm run dev"
# Execute command in app container
app-execute:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "$(c)"
# Run composer install
backend-install:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app "composer install"
# Run composer dependency installation
backend-test:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app php artisan test
# Run PHP-CBF and Rector to reformat code
backend-reformat:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "vendor/bin/rector --clear-cache && vendor/bin/php-cs-fixer fix --allow-risky=yes"
backend-analyse:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "vendor/bin/phpstan analyse"
# Clear all possible caches
backend-cache-clear:
	make art c="cache:clear"
	make art c="view:clear"
	make art c="config:clear"
	make art c="event:clear"
	make art c="route:clear"



######### Artisan utilities
# Execute artisan command in app container
art:
	make app-execute c="./artisan $(c)"
# Run queue worker
queue:
	make art c="q:w"
# Run artisan tests
test:
	make art c="test"
# Run database migrations
migrate:
	make art c="migrate"
# Refresh database structure
migrate-fresh:
	make art c="migrate:fresh"
# Refresh database with seeders
migrate-fresh-seed:
	make art c="migrate:fresh --seed"
reload-db:
	make migrate-fresh-seed
reverb:
	make art c="reverb:start"



######### NodeJS utilities
# Execute artisan command in app container
dev:
	make failsafe-up
	make frontend-dev



######### GIT utilities
# Reset and clean branch
gr:
	git clean -f -d && git reset --hard
# Quick commit
gcp:
	@if git branch --show-current | grep main; then \
    echo "----------------------------------"; \
    echo "DO NOT RUN THIS COMMAND ON MAIN :)"; \
    echo "----------------------------------"; \
    exit 1; \
    else \
	git add .; \
	git commit -m "draft"; \
	git push; \
	fi

gc:
	@if git branch --show-current | grep main; then \
    echo "----------------------------------"; \
    echo "DO NOT RUN THIS COMMAND ON MAIN :)"; \
    echo "----------------------------------"; \
    exit 1; \
    else \
	git add .; \
	git commit -m "draft"; \
	fi

gp:
	git push
