# Agentic App Pipeline Starter

End-to-end loop: Idea → Spec → Tasks → PRs → Preview → QA → Release (TestFlight/Play) using Cursor, Claude Code subagents, Copilot Workspace, and GitHub Actions with EAS/fastlane.

## Quick Start
1. **Create repo** and push this tree.
2. **Secrets** (GitHub → Settings → Secrets and variables → Actions):
   - `EXPO_TOKEN` (Expo access token)
   - `APPLE_API_KEY_ID`, `APPLE_ISSUER_ID`, `APPLE_API_KEY_CONTENT` (or use fastlane match)
   - `PLAY_SERVICE_ACCOUNT_JSON` (base64 or JSON for Play API)
3. **Enable Copilot Workspace** for your org/repo.
4. **Install Cursor** locally; it will read `.cursorrules`.
5. **Create first idea issue** via template. Label it `workspace-ready` to prime Workspace.

### Flow
- Planner subagent (Claude Code) expands the idea into a spec + issues.
- You open a task in Copilot Workspace → produces a PR.
- Cursor helps apply repo-wide edits/refactors.
- CI runs tests, static analysis, and EAS preview builds (links posted in PR).
- Tag `v0.1.0` or comment `/release v0.1.0` → release job submits to TestFlight/Play Internal.

## Monorepo Notes
We start with a single Expo app. Add backend/web as needed under `apps/`.

## Web (Cloudflare Pages)
- Connect repo in Cloudflare Pages and set secrets in GitHub: CLOUDFLARE_API_TOKEN, CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_PROJECT_NAME.
- Deploys on push to main affecting apps/web or packages/.

## Local Commands
- Web: bun run dev:web
- Mobile: bunx expo start --cwd apps/mobile
