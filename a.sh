# install + init
bun install && bunx husky init
chmod +x scripts/setup-role-identities.sh && ./scripts/setup-role-identities.sh

# mobile checks
(cd apps/mobile && bun run lint && bun run typecheck && bun test -- --ci && bunx expo doctor)

# web checks
(cd apps/web && bun run build)

# labels
chmod +x scripts/bootstrap-labels.sh
./scripts/bootstrap-labels.sh lasource18/agentic-app-pipeline

# idea issue
gh issue create --title "App Idea: Hello" --body "MVP scope..." --label idea

# implementer PR (triggers CI + previews)
git checkout -b feature/implementer/smoke
echo "// smoke" >> apps/mobile/src/smoke.ts
git add -A && git commit -m "impl: smoke"
git push -u origin feature/implementer/smoke
