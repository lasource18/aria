#!/usr/bin/env bash
set -euo pipefail
# Run from repo root

# Conditional includes by branch name
git config --local includeIf."onbranch:feature/planner/**".path .gitconfig-planner
git config --local includeIf."onbranch:feature/architect/**".path .gitconfig-architect
git config --local includeIf."onbranch:feature/implementer/**".path .gitconfig-implementer
git config --local includeIf."onbranch:feature/reviewer/**".path .gitconfig-reviewer
git config --local includeIf."onbranch:feature/qa/**".path .gitconfig-qa

# Commit message template (relative path ok)
git config --local commit.template infra/git/commit.template

echo "✔ Role identities & commit template configured for this repo."
