#!/usr/bin/env bash
#MISE description="Run composer commands in the app container"

/usr/bin/env docker compose run --rm app composer "$@"
