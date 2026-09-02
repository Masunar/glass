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
	make app-install
	make dev



######### Common utilities
# Get into running app container shell
app-shell:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash



######### Application utilities
# Run npm install
app-install:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "npm install --ignore-scripts"
# Compile application for production build
app-build:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "npm run build"
# Run production build server
app-start:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "npm run start"
# Compile application and run production build server
app-deploy:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "npm run build && npm run start"
# Run dev server
dev:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "npm run dev"
# Run prettier
pretty:
	docker compose -f compose.yml --env-file docker/.docker.env exec -it app bash -c "npm run pretty"

gr:
	git clean -f -d && git reset --hard

gc:
	git add .
	git commit -m "draft"
	git push
