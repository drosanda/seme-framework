#!/bin/bash
sonar-scanner -Dsonar.organization=drosanda -Dsonar.projectKey=drosanda_seme-framework -Dsonar.sources=. -Dsonar.host.url=https://sonarcloud.io
