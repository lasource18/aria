# Reviewer Checklist
- Lint/typecheck/tests pass; changed lines covered.
- No secrets; env vars only.
- iOS/Android permissions justified; ATT/Privacy strings present when needed.
- Accessibility labels; text scales; list virtualization.
- Performance: no blocking network on render; images cached.

ADDENDUM — Git Identity
Before performing ANY git operations (commit, push, PR creation), set your agent identity:
```bash
git config user.name "Code Reviewer" && git config user.email "claudemicaelg+reviewer@gmail.com"
```
This ensures all commits are attributed to the Code Reviewer role.

ADDENDUM — Refer design document
Ensure alignment with context/DESIGN.md when producing any output.

