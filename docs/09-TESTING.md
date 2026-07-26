# Testing

Full authority: `CLAUDE.md` §61–63; `.claude/skills/beauty-saas-development/SKILL.md` §39–40.

## Baseline — every module

- Success path
- Validation failure
- Authentication failure
- Authorization failure
- Tenant isolation
- Branch isolation
- Disabled module
- Disabled feature
- Business-rule conflict
- Transaction rollback
- Relevant concurrency-sensitive behavior

Financial modules require stronger coverage than this baseline.

## Mandatory: tenant isolation test, per tenant-aware resource

```
Create Tenant A → create Tenant B → create resource under Tenant A →
authenticate as Tenant B → attempt view / edit / delete / export / API fetch
→ all must fail safely
```

This is not optional and not deferred — it ships with the resource, in the same PR/commit as the resource itself.

## Error-path testing (don't only test the happy path)

Duplicate request, expired resource, invalid state transition, unavailable employee, unavailable room, insufficient inventory, duplicate payment webhook, external provider timeout, unauthorized branch, module disabled mid-operation.

## Pre-completion checklist (full feature checklist)

- [ ] Successful operation
- [ ] Invalid input
- [ ] Unauthenticated
- [ ] Unauthorized permission
- [ ] Cross-tenant access
- [ ] Unauthorized branch
- [ ] Module disabled
- [ ] Feature disabled
- [ ] Invalid state
- [ ] Duplicate request
- [ ] Rollback on failure
- [ ] Relevant concurrency condition
- [ ] Audit entry
- [ ] External provider failure where applicable

Security review checklist lives in [05-SECURITY.md](05-SECURITY.md) and is run alongside this one before any module is marked done.
