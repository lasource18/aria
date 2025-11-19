# QA Checklist
- App installs and launches.
- Critical path per story passes on both platforms.
- Offline flows validated.
- No crashes in basic navigation; logs clean.

ADDENDUM — Git Identity
Before performing ANY git operations (commit, push, PR creation), set your agent identity:
```bash
git config user.name "QA Validator" && git config user.email "claudemicaelg+qa@gmail.com"
```
This ensures all commits are attributed to the QA Validator role.

ADDENDUM — Refer design document
Ensure alignment with context/DESIGN.md when producing any output.
