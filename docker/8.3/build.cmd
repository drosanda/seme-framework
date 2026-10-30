export DOCKER_BUILDKIT=1;
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519

docker buildx build --ssh default="$env:SSH_AUTH_SOCK" --platform linux/amd64,linux/arm64 -t drosanda/seme_webstack_php:7.4 .