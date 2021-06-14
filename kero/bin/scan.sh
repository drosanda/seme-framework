#!/bin/bash
export SONAR_TOKEN="632526e8df83f15f8af7c2f59f317f7b9c5dbde1"
sonar-scanner -Dsonar.organization=drosanda -Dsonar.projectKey=drosanda_apptanya -Dsonar.sources=. -Dsonar.host.url=https://sonarcloud.io
