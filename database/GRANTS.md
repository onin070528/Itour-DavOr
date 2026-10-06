# Audit log table permissions

`security_logs` and `operation_logs` are append-only (see `App\Models\Concerns\AppendOnly`,
used by both models). The Eloquent-level guard is defense-in-depth only — a
raw query-builder call (`SecurityLog::query()->where(...)->update(...)`)
bypasses model instance methods entirely. The real guarantee has to be the
database grant: the application's runtime DB user should hold **INSERT and
SELECT only** on these two tables, not UPDATE or DELETE.

**Not run automatically.** Apply these manually, as the Postgres superuser/owner,
against the production database, with `<app_user>` replaced by the actual
role name the application connects as:

```sql
REVOKE UPDATE, DELETE ON security_logs, operation_logs FROM <app_user>;
GRANT SELECT, INSERT ON security_logs, operation_logs TO <app_user>;

-- The id sequences still need nextval() for INSERT to work:
GRANT USAGE, SELECT ON SEQUENCE security_logs_id_seq TO <app_user>;
GRANT USAGE, SELECT ON SEQUENCE operation_logs_id_seq TO <app_user>;
```

If the app connects as a role with `ALL PRIVILEGES` on the schema (e.g. the
table owner), an explicit `REVOKE` is required first — granting a narrower
set alone won't remove existing broader privileges.
