## 4. SESSION_SECURE_COOKIE Blocks Fresh Context Login Over HTTP

**Files:** `tests/e2e/flows/milestone-progress.spec.ts`, `tests/e2e/flows/register-and-profile.spec.ts`, `tests/e2e/logbook/empty-state.spec.ts`, `tests/e2e/export/user-data-csv.spec.ts`
**Issue:** The `.env` has `SESSION_SECURE_COOKIE=true`, which sets the `Secure` flag on session cookies.
The E2E test server runs on HTTP (port 8001), so browsers in fresh `browser.newContext()` contexts silently drop the session cookie after login — the POST succeeds but no session is established for subsequent requests.
Tests that need to log in as a different user (tiered users, fresh user) in a fresh context are blocked by this.
**Workaround:** Tests that need different users should pre-cache their storageState in `auth.setup.ts`, or the test environment should set `SESSION_SECURE_COOKIE=false`.
**Status:** `test.fixme()` for affected tests
