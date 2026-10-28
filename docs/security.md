# Tahfeez — Security Model

Status: **Draft for review** · Phase 0
Implements §20. Every control here is server-side unless explicitly marked client.

---

## 1. Threat model (MVP)

| # | Threat | Impact | Control |
|---|---|---|---|
| T1 | Teacher reads another teacher's students | Privacy breach (minors' data) | Assignment scope + 404 on out-of-scope ids |
| T2 | Cross-center data access | Tenant breach | `center_id` from token only; composite indexes; policy check |
| T3 | Client pushes a fabricated grade | Corrupted reporting | Server recomputes every derived value; approved evaluations are server-only |
| T4 | Stolen device | Account takeover | Device-bound revocable tokens, short access TTL, remote revoke |
| T5 | Offline tampering with local DB | Local data fraud | Server re-validation on push; local DB encrypted |
| T6 | Mass-assignment / IDOR | Unauthorized writes | Explicit `$fillable`, FormRequests, policy per record |
| T7 | Enumeration of student ids | Privacy | 404 instead of 403; rate limiting |
| T8 | Injection | Data loss | Eloquent bindings, no raw SQL with input, no `eval` in rule engine |
| T9 | Export leakage | Bulk data exfiltration | Signed short-lived URLs, permission + scope check, audit on every export |
| T10 | Secret in repo | Full compromise | `.env` git-ignored, `.env.example` only, secret scanning in CI |

---

## 2. Authentication

- Email + password, Argon2id hashing (Laravel default driver config).
- Password policy: min 10 chars, checked against a common-password list, no forced rotation.
- Sanctum personal access tokens, **device-scoped**: token ↔ `devices.id`; revoking a device revokes its tokens.
- Access token TTL: 30 days for mobile, 12 hours for web **[OD-013]**; refresh via `/auth/refresh` with rotation.
- Tokens stored hashed (`personal_access_tokens.token` is a SHA-256 hash); plaintext exists only on the device, in the platform keychain.
- Login throttling: 10/min per IP+email, exponential lockout after repeated failures.
- Logout revokes the current device token; "logout all devices" revokes the user's tokens.
- 2FA: out of MVP scope, noted as a Future Feature.

---

## 3. Authorization

Three-layer enforcement is described in `permissions.md` §5. Security-relevant invariants:

1. `center_id` is **never** read from the request body or query string.
2. Policies are the gate; middleware role checks alone are insufficient.
3. Out-of-scope resources return `404`, so existence is not leaked.
4. The `Action` re-asserts scope inside the transaction (closes the check-then-write race).
5. Permission changes are audited with before/after.

---

## 4. Input validation

- Every write goes through a `FormRequest` with explicit rules; `$request->all()` is banned.
- Server re-validates everything the client "already validated" (BR-SEC-02).
- Numeric bounds: `amount_pages > 0 && <= 604`; `weight` in `(0,100]`; `late_minutes` in `[0, 600]`; ayah ids within `1…6236`.
- Enum values are validated against the closed set, not cast blindly.
- Text fields have length caps and are stored raw (escaped at render) — no HTML sanitising that would corrupt Arabic text.
- Dates: `session_date` cannot be in the future beyond a 1-day clock-skew tolerance, cannot precede enrollment.
- File uploads (future avatars) are out of MVP scope; the export pipeline writes server-generated files only.

---

## 5. Transport & headers

- HTTPS only; HSTS; TLS 1.2+.
- CORS restricted to known web origins; credentials allowed only for those origins.
- Security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, CSP on the web build.
- No secrets, tokens or full payloads in logs; `request_id` correlates without exposing content.

---

## 6. Data protection at rest

| Data | Protection |
|---|---|
| Passwords | Argon2id |
| Tokens | SHA-256 hash server-side; keychain on device |
| Student personal data | Encrypted volume / managed Postgres at rest; column encryption is **not** in MVP **[OD-017]** |
| Local mobile DB | SQLCipher with key in Keychain/Keystore **[OD-012]** |
| Backups | Encrypted, access-logged, restore tested quarterly |
| Exports | Private storage, signed URL, TTL 24h |

**Minors' data.** The system stores children's names, ages, guardians' phone numbers and religious-study records. This is sensitive personal data. Consequences: minimum necessary collection, no analytics SDK on student screens, no third-party crash reporting with payloads, retention policy documented, and exports restricted to `reports.export` holders. See §9.

---

## 7. Audit trail

- Append-only `audit_logs`; the application DB role has `INSERT` only on that table.
- Written **in the same transaction** as the change — a failed audit write rolls back the business write.
- Captures: actor, device, IP, request id, `op_id` (for sync), entity, before/after (sensitive fields redacted), action.
- Audited events: login/logout/failed login, permission change, student create/update/delete/assign, record create/update/delete, evaluation compute/approve/recompute, template publish, export request/download, sync conflict resolution.
- Read access limited to `audit.view` (manager by default).

---

## 8. Secrets & configuration

- `.env` never committed; `.env.example` documents every key with a placeholder.
- CI runs a secret scanner; a detected credential fails the build.
- Production secrets injected via environment/secret manager, rotated on personnel change.
- API keys and tokens must never appear in Dart source, assets, or git history.
- `APP_DEBUG=false` in production; stack traces never returned to clients.

---

## 9. Privacy & retention

| Data | Retention |
|---|---|
| Student records | Life of the center account + 1 year |
| Audit logs | 3 years (append-only) |
| Exports | 24h TTL |
| Deleted students | Soft-deleted, purged by a scheduled job after the retention window |
| Backups | 30 days rolling |

Right-to-erasure requests are handled by an audited administrative purge that keeps aggregate, non-identifying statistics only. **[OD-020]** — the exact legal basis is a policy decision for the deploying organization, not something the code can assume.

---

## 10. Secure development rules

- Dependency audit (`composer audit`, `dart pub outdated` + OSV scan) in CI.
- No `eval`, no dynamic code execution anywhere — including the rule engine (whitelisted operators only).
- Raw SQL requires a review note explaining why the query builder is insufficient.
- Every PR touching auth, policies or sync requires a second reviewer.
- Penetration checklist before release: IDOR sweep across all `{id}` routes, tenant-boundary sweep, token revocation, export URL expiry.

---

## 11. Incident readiness

| Scenario | Response |
|---|---|
| Token leak | Revoke device tokens, force re-login, audit review |
| Data corruption from bad migration | Restore from PITR, replay `change_log`, notify affected centers |
| Cross-tenant exposure | Immediate hotfix, audit query, disclosure to affected centers |
| Ransomware / host loss | Restore from encrypted backup, rotate secrets |

Backups are useless until restored. A restore drill is part of the release checklist, not an afterthought.
