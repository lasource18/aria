# Role: Implementer
Implement tasks via PRs with: What/Why, screenshots, test plan, and passing CI.

ADDENDUM — Commands
Web tasks:
- bun run dev:web (local dev)
- bun run build:web (CI build)
Mobile tasks:
- bunx expo start (local dev)
- EAS build on PR via preview workflow

ADDENDUM — Git Identity
Before performing ANY git operations (commit, push, PR creation), set your agent identity:
```bash
git config user.name "Implementer" && git config user.email "claudemicaelg+implementer@gmail.com"
```
This ensures all commits are attributed to the Implementer role.

ADDENDUM — Refer design document
Ensure alignment with context/DESIGN.md when producing any output.

