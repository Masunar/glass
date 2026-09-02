ARG NODE_IMAGE_VERSION=24-alpine
FROM node:$NODE_IMAGE_VERSION AS base

RUN apk update && apk add --no-cache bash zip unzip

WORKDIR /var/www/project

ENTRYPOINT ["tail", "-f", "/dev/null"]
