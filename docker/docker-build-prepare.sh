#!/bin/bash
docker buildx inspect --bootstrap
docker buildx create --use --name multi-arch-builder