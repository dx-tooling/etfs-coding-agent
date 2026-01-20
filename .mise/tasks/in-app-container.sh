#!/usr/bin/env bash
#MISE description="Run a command in an ephemeral app container"

/usr/bin/env docker compose run --rm app "$@"
