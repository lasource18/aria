# Agentic App Pipeline Starter (Cursor + Claude Code + Copilot Workspace + CI/CD)

This starter bundles:

* **Agents**: Claude Code subagents (planner/architect/implementer/reviewer/qa) + LangGraph fallback orchestrator.
* **IDE Agent**: Cursor with repo rules for safe edits.
* **Plan→PR**: GitHub Copilot Workspace flow from labeled issues.
* **CI/CD**: Lint, tests, static analysis, preview builds (EAS), release (fastlane/EAS) to TestFlight & Play Console.
* **Policy Guardrails**: Mobile permission/privacy lint checks.

> Copy this into a repo and follow the **Setup** steps at the bottom.

---

## Repository Tree

```
agentic-app-pipeline-starter/
├─ apps/
│  ├─ mobile/                     # Expo RN minimal scaffold (iOS/Android)
│  └─ web/                        # NEW: Next.js web app for Cloudflare Pages
├─ packages/                      # (optional) shared ui/utils/types
│  └─ ui/
│     ├─ Button.tsx
│     └─ index.ts
├─ docs/
│  ├─ product_specs/
│  │  └─ SAMPLE_SPEC.md
│  └─ adr/
│     └─ 0001-use-expo-and-eas.md
├─ infra/
│  ├─ prompts/
│  │  ├─ planner.md
│  │  ├─ architect.md
│  │  ├─ implementer.md
│  │  ├─ reviewer_checklist.md
│  │  └─ qa_checklist.md
│  ├─ policies/
│  │  ├─ ios_policy_lints.yml
│  │  └─ android_policy_lints.yml
│  ├─ agents/
│  │  ├─ langgraph/
│  │  │  ├─ main.py
│  │  │  └─ nodes/
│  │  │     ├─ planner.py
│  │  │     ├─ architect.py
│  │  │     ├─ implementer.py
│  │  │     ├─ reviewer.py
│  │  │     └─ qa.py
│  │  └─ post-preview-links.js
│  └─ templates/
│     ├─ ISSUE_IDEA_TEMPLATE.md
│     └─ PR_TEMPLATE.md
├─ .github/
│  ├─ ISSUE_TEMPLATE/
│  │  └─ idea.yml
│  └─ workflows/
│     ├─ ci.yml
│     ├─ preview.yml
│     ├─ release.yml
│     └─ web-deploy.yml           # NEW: Cloudflare Pages deploy workflow
├─ fastlane/
│  ├─ Fastfile
│  └─ Appfile
├─ .cursorrules
├─ .editorconfig
├─ .gitignore
├─ eas.json
├─ package.json                   # NEW: workspaces + scripts for web/mobile
├─ bun.lockb
├─ README.md
└─ SECURITY.md
```

---

## File: README.md

```md
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
```

````

---

## File: apps/mobile/package.json
```json
{
  "name": "agentic-mobile",
  "version": "0.1.0",
  "private": true,
  "main": "expo-router/entry",
  "scripts": {
    "start": "expo start",
    "android": "expo run:android",
    "ios": "expo run:ios",
    "web": "expo start --web",
    "lint": "eslint .",
    "typecheck": "tsc --noEmit",
    "test": "jest"
  },
  "dependencies": {
    "expo": "~52.0.0",
    "expo-router": "^4.0.0",
    "react": "18.3.1",
    "react-native": "0.77.0"
  },
  "devDependencies": {
    "@types/jest": "^29.5.12",
    "@types/react": "^18.2.66",
    "@types/react-native": "^0.77.0",
    "eslint": "^9.11.1",
    "jest": "^29.7.0",
    "typescript": "^5.6.3"
  }
}
````

## File: apps/mobile/app.json

```json
{
  "expo": {
    "name": "Agentic Mobile",
    "slug": "agentic-mobile",
    "scheme": "agentic",
    "owner": "YOUR_EXPO_OWNER",
    "android": { "package": "com.yourco.agentic" },
    "ios": { "bundleIdentifier": "com.yourco.agentic" }
  }
}
```

## File: apps/mobile/App.tsx

```tsx
import { StatusBar } from 'expo-status-bar';
import { View, Text } from 'react-native';
export default function App() {
  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
      <Text>Agentic Pipeline Starter</Text>
      <StatusBar style="auto" />
    </View>
  );
}
```

## File: apps/mobile/tsconfig.json

```json
{
  "compilerOptions": {
    "target": "ES2021",
    "module": "ESNext",
    "jsx": "react-jsx",
    "strict": true,
    "moduleResolution": "Bundler",
    "resolveJsonModule": true,
    "types": ["jest", "react", "react-native"]
  },
  "include": ["**/*.ts", "**/*.tsx"]
}
```

---

## File: eas.json

```json
{
  "cli": { "appVersionSource": "remote" },
  "build": {
    "preview": { "developmentClient": false, "distribution": "internal" },
    "production": { "developmentClient": false, "autoIncrement": true }
  },
  "submit": {
    "production": {
      "ios": { "appleId": "your@appleid", "ascAppId": "YOUR_ASC_APP_ID" },
      "android": { "serviceAccountKeyPath": "./play-service-account.json" }
    }
  }
}
```

---

## File: .github/workflows/ci.yml

```yaml
name: CI
on:
  pull_request:
    paths-ignore: ["**/*.md"]
jobs:
  ci:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci --prefix apps/mobile
      - run: npm run lint --prefix apps/mobile
      - run: npm run typecheck --prefix apps/mobile
      - run: npm test --prefix apps/mobile -- --ci
      - name: Semgrep SAST
        uses: returntocorp/semgrep-action@v1
      - name: Generate SBOM
        uses: CycloneDX/gh-node-module-generatebom@v2
```

## File: .github/workflows/preview.yml

```yaml
name: Preview Build (EAS)
on:
  pull_request:
    branches: [ main ]
jobs:
  preview:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - uses: expo/expo-github-action@v8
        with:
          eas-version: latest
          token: ${{ secrets.EXPO_TOKEN }}
      - run: npm ci --prefix apps/mobile
      - run: npx expo prebuild --non-interactive --working-dir apps/mobile || true
      - run: npx eas build --platform all --profile preview --non-interactive --local-timezone --input ./apps/mobile
      - name: Post Preview Links
        run: node infra/agents/post-preview-links.js
```

## File: .github/workflows/release.yml

```yaml
name: Release
on:
  push:
    tags: [ 'v*' ]
jobs:
  release:
    runs-on: ubuntu-latest
    environment: production
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - uses: expo/expo-github-action@v8
        with:
          eas-version: latest
          token: ${{ secrets.EXPO_TOKEN }}
      - run: npm ci --prefix apps/mobile
      - run: npx eas build --platform all --profile production --non-interactive --input ./apps/mobile
      - run: npx eas submit --platform ios --profile production --non-interactive --input ./apps/mobile || true
      - run: npx eas submit --platform android --profile production --non-interactive --input ./apps/mobile || true
```

---

## File: fastlane/Fastfile

```ruby
default_platform(:ios)

platform :ios do
  desc "Beta to TestFlight"
  lane :beta do
    build_app(scheme: "Agentic Mobile")
    upload_to_testflight(skip_waiting_for_build_processing: true)
  end

  desc "Submit to App Store Connect"
  lane :release do
    deliver(submit_for_review: true, automatic_release: false)
  end
end

platform :android do
  lane :beta do
    gradle(task: "bundle", build_type: "Release")
    upload_to_play_store(track: "internal")
  end
  lane :release do
    upload_to_play_store(track: "production", rollout: 0.1)
  end
end
```

## File: fastlane/Appfile

```ruby
iitc_team_id("YOUR_TEAM_ID")
app_identifier("com.yourco.agentic")
apple_dev_portal_id("your@appleid")
```

---

## File: .cursorrules

```txt
# Cursor Repo Rules
- Use TypeScript strict and ensure no implicit any.
- Never commit secrets or API keys; load from env.
- All user-visible strings must be centralized for future i18n.
- Mobile: Explain permission rationale and guard features behind flags.
- Architecture: keep screens/components/services separated; use React Query for data.
- Write/update tests with any feature change; keep >80% changed lines covered.
- Follow infra/prompts/reviewer_checklist.md before marking tasks done.
```

## File: .editorconfig

```ini
root = true
[*]
charset = utf-8
end_of_line = lf
indent_style = space
indent_size = 2
insert_final_newline = true
trim_trailing_whitespace = true
```

## File: .gitignore

```
node_modules
.expo
.expo-shared
.DS_Store
build
*.keystore
*.jks
*.p8
*.p12
play-service-account.json
.env
```

---

## File: docs/product_specs/SAMPLE_SPEC.md

```md
# SAMPLE APP SPEC: MealMuse (MVP)
- **Goal**: Browse recipes, filter by diet, save offline.
- **Target**: iOS/Android, Expo RN.

## Stories & AC
1. As a user I can search recipes by keyword.
   - AC: Query input, paginated results, loading/empty states.
2. Filter by diet (vegan, keto, etc.).
   - AC: Multi-select filters; applied to search.
3. Save a recipe offline.
   - AC: Tap to save; available offline with image + steps.

## Non-goals
- Auth, payments.

## Telemetry
- Screen views, searches, saves (privacy-friendly, no PII).
```

## File: docs/adr/0001-use-expo-and-eas.md

```md
# 0001: Use Expo + EAS for Mobile CI/CD
- Decision: Expo RN + EAS for preview and production submissions.
- Alternatives: Native + fastlane only (kept as fallback).
- Consequences: Faster previews; lock to Expo SDK cadence.
```

---

## File: infra/prompts/planner.md

```md
# Role: Product Planner
Transform an Idea issue into: product spec updates, labeled issues, milestones. Output small, atomic tasks labeled `workspace-ready` for Copilot Workspace.
Checklist:
- Confirm MVP scope & non-goals.
- Create user stories with AC.
- Ensure each task has acceptance tests and test plan notes.
```

## File: infra/prompts/architect.md

```md
# Role: Architect
Choose scaffold, update ADRs, data models, and tech tasks. Ensure mobile policy compliance.
Checklist: layering, error handling, permissions, privacy, performance.
```

## File: infra/prompts/implementer.md

```md
# Role: Implementer
Implement tasks via PRs with: What/Why, screenshots, test plan, and passing CI.
```

## File: infra/prompts/reviewer_checklist.md

```md
# Reviewer Checklist
- Lint/typecheck/tests pass; changed lines covered.
- No secrets; env vars only.
- iOS/Android permissions justified; ATT/Privacy strings present when needed.
- Accessibility labels; text scales; list virtualization.
- Performance: no blocking network on render; images cached.
```

## File: infra/prompts/qa_checklist.md

```md
# QA Checklist
- App installs and launches.
- Critical path per story passes on both platforms.
- Offline flows validated.
- No crashes in basic navigation; logs clean.
```

---

## File: infra/policies/ios_policy_lints.yml

```yaml
required_info_plist_keys:
  - NSCameraUsageDescription
  - NSPhotoLibraryUsageDescription
att_usage:
  required_if_tracking: true
```

## File: infra/policies/android_policy_lints.yml

```yaml
min_sdk: 24
forbid_permissions:
  - android.permission.READ_SMS
  - android.permission.CALL_PHONE
```

---

## File: infra/agents/post-preview-links.js

```js
// Placeholder: read EAS build outputs and post links as a PR comment.
// In real use, parse artifacts or call EAS GraphQL, then GitHub REST API.
console.log("(stub) Posted preview links to PR comments.");
```

---

## File: infra/agents/langgraph/main.py

```python
"""Minimal LangGraph-ish stub: orchestrates role prompts and writes files/issues.
Replace with real LangGraph or framework of choice.
"""
from pathlib import Path
from nodes.planner import plan
from nodes.architect import architect
from nodes.implementer import implement
from nodes.reviewer import review
from nodes.qa import qa

ROOT = Path(__file__).resolve().parents[3]

def run_idea_to_tasks(idea_text: str):
    spec, tasks = plan(idea_text)
    (ROOT/"docs/product_specs/GENERATED_FROM_IDEA.md").write_text(spec)
    # TODO: Create GitHub issues via API
    return tasks

def run_pr_cycle(diff: str):
    suggestions = review(diff)
    qa_report = qa()
    return suggestions, qa_report

if __name__ == "__main__":
    tasks = run_idea_to_tasks("Build MealMuse MVP")
    print("Planned tasks:", tasks)
```

## Files: infra/agents/langgraph/nodes/*.py

```python
# planner.py
from typing import List, Tuple

def plan(idea: str) -> Tuple[str, List[str]]:
    spec = f"# Generated Spec\nIdea: {idea}\nStories: ..."
    tasks = ["Scaffold app", "Search screen", "Offline save"]
    return spec, tasks
```

```python
# architect.py
def architect(spec: str) -> str:
    return spec + "\nArchitecture: Expo + React Query"
```

```python
# implementer.py
def implement(task: str) -> str:
    return f"Implemented: {task}"
```

```python
# reviewer.py
def review(diff: str) -> str:
    return "LGTM if tests pass and privacy strings set."
```

```python
# qa.py
def qa() -> str:
    return "QA passed on smoke tests."
```

---

## File: .github/ISSUE_TEMPLATE/idea.yml

```yaml
name: App Idea
description: Kick off a new app or feature idea
labels: [idea, workspace-ready]
body:
  - type: textarea
    id: idea
    attributes:
      label: One-liner & users
  - type: textarea
    id: scope
    attributes:
      label: MVP scope & non-goals
  - type: textarea
    id: stories
    attributes:
      label: Key user stories & acceptance criteria
```

## File: infra/templates/ISSUE_IDEA_TEMPLATE.md

```md
### App idea

### Constraints

### Success criteria
```

## File: infra/templates/PR_TEMPLATE.md

```md
## What

## Why

## Screenshots

## Test Plan
- [ ] Unit
- [ ] e2e

## Checklist
- [ ] Lint/typecheck pass
- [ ] No secrets committed
- [ ] Permissions & privacy strings updated
```

---

## File: SECURITY.md

```md
# Security
- Secrets via GitHub OIDC + env. No secrets in repo.
- Dependency & SAST scanning enforced in CI.
- Release jobs require protected environment approvals.
```

---

## Setup Steps

1. **Install Expo CLI** and log in; create `EXPO_TOKEN` and add as GitHub secret.
2. Create Apple API key / Play Service Account and add as secrets.
3. `npm i` in `apps/mobile`, then `npm run start` to verify locally.
4. Push to GitHub; open a PR → CI runs; preview builds posted.
5. Create an **Idea** issue using the template; triage with Claude Code subagents and Copilot Workspace.
6. Tag a release `v0.1.0` to trigger store submissions.

````



---

# NEW: Web app (Next.js) + Cloudflare Pages

## File: package.json (root workspaces)
```json
{
  "private": true,
  "workspaces": ["apps/*", "packages/*"],
  "scripts": {
    "dev:web": "bun run dev --cwd apps/web",
    "dev:mobile": "bunx expo start --cwd apps/mobile",
    "build:web": "bun run build --cwd apps/web"
  },
  "devDependencies": {
    "typescript": "^5.6.3",
    "eslint": "^9.11.1"
  }
}
````

## File: apps/web/package.json (Next.js)

```json
{
  "name": "agentic-web",
  "private": true,
  "version": "0.1.0",
  "scripts": {
    "dev": "next dev",
    "build": "next build",
    "start": "next start",
    "lint": "next lint"
  },
  "dependencies": {
    "next": "14.2.5",
    "react": "18.3.1",
    "react-dom": "18.3.1"
  },
  "devDependencies": {
    "@types/node": "^20.12.12",
    "@types/react": "^18.2.66",
    "@types/react-dom": "^18.2.22",
    "typescript": "^5.6.3"
  }
}
```

## File: apps/web/next.config.mjs

```js
/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'standalone'
};
export default nextConfig;
```

## File: apps/web/src/pages/index.tsx

```tsx
export default function Home() {
  return (
    <main style={{ padding: 32, fontFamily: 'system-ui' }}>
      <h1>Agentic Web (Cloudflare Pages)</h1>
      <p>Deployed from monorepo via GitHub Actions → Cloudflare Pages.</p>
    </main>
  );
}
```

## File: packages/ui/index.ts

```ts
export * from './Button';
```

## File: packages/ui/Button.tsx

```tsx
import React from 'react';
export const Button: React.FC<React.ButtonHTMLAttributes<HTMLButtonElement>> = (p) => (
  <button {...p} style={{ padding: '8px 12px', borderRadius: 8 }} />
);
```

---

## File: .github/workflows/web-deploy.yml (Cloudflare Pages)

```yaml
name: Deploy Web (Cloudflare Pages)
on:
  push:
    branches: [main]
    paths:
      - 'apps/web/**'
      - 'packages/**'
  workflow_dispatch:

permissions:
  contents: read
  deployments: write

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: oven-sh/setup-bun@v1
        with: { bun-version: '1.x' }

      - name: Install deps
        run: bun install

      - name: Build web
        run: bun run build --cwd apps/web

      - name: Deploy to Cloudflare Pages
        uses: cloudflare/pages-action@v1
        with:
          apiToken: ${{ secrets.CLOUDFLARE_API_TOKEN }}
          accountId: ${{ secrets.CLOUDFLARE_ACCOUNT_ID }}
          projectName: ${{ secrets.CLOUDFLARE_PROJECT_NAME }}
          directory: apps/web/.next
          gitHubToken: ${{ secrets.GITHUB_TOKEN }}
```

### Required GitHub Secrets

* `CLOUDFLARE_API_TOKEN` (Pages: Edit Deployments)
* `CLOUDFLARE_ACCOUNT_ID`
* `CLOUDFLARE_PROJECT_NAME`

---

## Update: infra/prompts/planner.md (append platform awareness)

```md
ADDENDUM — Platform Awareness
If the app includes both mobile and web, split tasks per platform with labels `mobile` and `web`. Keep cross-cutting items under `shared`. Ensure each Issue’s AC/test plan specify the target platform(s).
```

## Update: infra/prompts/architect.md (append mono-repo guidance)

```md
ADDENDUM — Monorepo & Cloudflare
For dual-surface apps, define shared packages (e.g., packages/ui, packages/utils) and platform adapters. Ensure web uses Next.js (edge-safe APIs) and is deployable on Cloudflare Pages. Document these as ADRs.
```

## Update: infra/prompts/implementer.md (append commands)

```md
ADDENDUM — Commands
Web tasks:
- bun run dev:web (local dev)
- bun run build:web (CI build)
Mobile tasks:
- bunx expo start (local dev)
- EAS build on PR via preview workflow
```

---

## Update: scripts/bootstrap-labels.sh (add platform labels)

```bash
mk "web"              "1d76db" "Web app feature"
mk "mobile"           "fbca04" "Mobile app feature"
mk "shared"           "cccccc" "Shared code (packages)"
```

---

## Replace: .github/workflows/issue-triage.yml (platform-aware)

```yaml
name: Issue Triage (Route to Agents)
on:
  issues:
    types: [opened, edited, labeled]
permissions:
  issues: write

jobs:
  route:
    runs-on: ubuntu-latest
    steps:
      - name: Determine route
        id: route
        uses: actions/github-script@v7
        with:
          script: |
            const issue = context.payload.issue;
            const labels = issue.labels.map(l => l.name.toLowerCase());

            const isIdea = labels.includes('idea') || /app idea/i.test(issue.title + ' ' + issue.body);
            const hasArch = labels.includes('arch');
            const isWorkspaceReady = labels.includes('workspace-ready');

            async function comment(body){
              await github.rest.issues.createComment({ ...context.repo, issue_number: issue.number, body });
            }
            async function addLabels(newLabels){
              await github.rest.issues.addLabels({ ...context.repo, issue_number: issue.number, labels: newLabels });
            }

            // Auto-detect platform
            const text = (issue.title + ' ' + issue.body).toLowerCase();
            if (/web|next|cloudflare/.test(text)) await addLabels(['web']);
            if (/mobile|expo|ios|android/.test(text)) await addLabels(['mobile']);

            if (isIdea) {
              await comment(`Summoning **Planner**: @your-user-planner`);
              await addLabels(['mvp']);
              return;
            }
            if (hasArch) {
              await comment(`Summoning **Architect**: @your-user-architect`);
              return;
            }
            if (isWorkspaceReady) {
              await comment(`Summoning **Implementer**: @your-user-implementer`);
              return;
            }
```

---

## README additions (deployment notes)

```md
## Web (Cloudflare Pages)
- Connect repo in Cloudflare Pages and set secrets in GitHub: CLOUDFLARE_API_TOKEN, CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_PROJECT_NAME.
- Deploys on push to main affecting apps/web or packages/.

## Local Commands
- Web: bun run dev:web
- Mobile: bunx expo start --cwd apps/mobile
```
