#!/usr/bin/env bash
#MISE description="Run npm commands in the app container"

/usr/bin/env docker compose run --rm app bash -c "mise trust && eval \"\$(mise activate bash)\" && npm $*"
