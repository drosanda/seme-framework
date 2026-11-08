#!/bin/bash
export DOCKER_BUILDKIT=1;
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519

docker buildx build --load --ssh default="$SSH_AUTH_SOCK" --platform linux/amd64,linux/arm64 -t drosanda/seme_webstack_php:8.3 .


docker buildx build --load --ssh default="$SSH_AUTH_SOCK" -t drosanda/seme_webstack_php:8.3 .