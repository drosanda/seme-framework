#!/bin/bash
sonar-scanner -Dsonar.organization=drosanda -Dsonar.projectKey=drosanda_apptanya -Dsonar.sources=. -Dsonar.host.url=https://sonarcloud.io
