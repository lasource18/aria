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
