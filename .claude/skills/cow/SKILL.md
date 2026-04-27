---
name: cow
description: Manage APFS copy-on-write git clones with Laravel Valet integration. Use when the user wants to create, list, activate, delete, or open clones of a local development project. Use when user asks to "switch to a PR", "create a clone", "activate a branch", or anything related to managing local project clones.
---

# cow — Clone Manager Skill

`cow` is a CLI tool for managing APFS copy-on-write git clones tied to Laravel Valet.
All commands support `--json` for structured output. Always use `--json` when acting as an agent.

Arguments: $ARGUMENTS

---

## Workflow

### 1. Discover projects and current state

```bash
cow cow:projects --json
# → [{"name":"My API","path":"/...","domain":"myapi.test","slug":"my-api"}, ...]

cow cow:list "My API" --json
# → [
#     {"name":"main","branch":"main","path":"/...","active":false},
#     {"name":"pr-1234-add-feature","branch":"feat/add-feature","path":"/...","active":true}
#   ]
```

`active: true` means valet is currently serving that clone.

### 2. Create a clone

From a PR number (auto-fetches title via `gh`, names clone `pr-{n}-{title-slug}`):
```bash
cow cow:create "My API" --pr=1234 --json
# → {"name":"pr-1234-add-feature","branch":"feat/add-feature","path":"/..."}
```

From a branch name (clone named after last path segment):
```bash
cow cow:create "My API" --branch="feat/my-feature" --json
# → {"name":"my-feature","branch":"feat/my-feature","path":"/..."}
```

### 3. Activate a clone (relinks valet + restarts PHP-FPM)

```bash
cow cow:activate "My API" pr-1234-add-feature --json
# → {"activated":true,"path":"/...","services_restarted":["php","php@8.4"]}
```

Use `main` to activate the source repo:
```bash
cow cow:activate "My API" main --json
```

### 4. Delete a clone

Always pass `--force` non-interactively:
```bash
cow cow:delete "My API" pr-1234-add-feature --force --json
# → {"deleted":true,"name":"pr-1234-add-feature","path":"/..."}
```

Cannot delete the currently active clone — activate another first.
Cannot delete `main`.

### 5. Open in IDE

```bash
cow cow:open "My API" pr-1234-add-feature --json
# → {"opened":true,"path":"/...","ide":"phpstorm"}
```

`clone` defaults to `main` if omitted.

### 6. Add a project

```bash
cow cow:project-add "My API" /path/to/repo --domain=myapi.test --json
# → {"name":"My API","path":"/...","domain":"myapi.test","slug":"my-api"}
```

`--domain` is optional — cow will auto-detect from valet if omitted.

---

## Self-update

```bash
cow self-update
```

Downloads and replaces the binary with the latest release from GitHub.

---

## Error handling

All commands return `{"error":"..."}` with exit code 1 on failure:

```bash
cow cow:activate "My API" nonexistent --json
# → {"error":"Clone 'nonexistent' not found in project 'My API'"}; exit 1
```

Always check exit code or the presence of `error` key in the JSON response.

---

## Common agent patterns

**Switch to a PR and verify:**
```bash
cow cow:create "My API" --pr=1234 --json
# get clone name from response, then:
cow cow:activate "My API" <clone-name> --json
cow cow:list "My API" --json  # confirm active:true on new clone
```

**Clean up after reviewing a PR:**
```bash
cow cow:activate "My API" main --json  # switch back first
cow cow:delete "My API" pr-1234-some-feature --force --json
```

**Check what's currently active:**
```bash
cow cow:list "My API" --json | jq '.[] | select(.active)'
```
