# Finance App — Claude Context

## Project Overview
Personal finance tracker (PHP + MySQLi). Multi-user, Thai UI, Buddhist Era (BE) dates.

## Stack
- PHP 7/8, MySQLi (no PDO, no ORM)
- MySQL on port **3307** (not default 3306)
- Bootstrap 5 + Bootstrap Icons (CDN)
- Vanilla JS (no framework)
- No Composer, no package manager

## Key Rules

### Database
- Server may **not** support `utf8mb4` → `config/db.php` falls back to `utf8` automatically
- **Never** call `mysqli_set_charset()` inside individual page files — already handled in `config/db.php`
- `error_reporting(0)` and `display_errors = 0` on all pages (production mode)

### Dates
- DB stores **AD (ค.ศ.)** — all `entry_date` columns are AD
- UI displays **BE (พ.ศ.)** = AD + 543
- Pattern: `date('d/m/', $ts) . ((int)date('Y', $ts) + 543)`

### Security
- All user input cast to `(int)` or escaped before SQL — no prepared statements on older files
- `$userId = (int)$_SESSION['user_id']` guard on every page
- `h()` helper = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`

## File Map

| File | Purpose |
|------|---------|
| `index.php` | Main dashboard — budget table, charts, modal detail (2,010 lines — **read in chunks**) |
| `get_detail.php` | AJAX endpoint for detail modal (entry list + add/edit/delete forms) |
| `save_entry.php` | Add / update entries |
| `save_category.php` | Add / update categories |
| `delete_entry.php` | Delete single entry |
| `delete_category.php` | Soft-delete (set `is_active=0`) if category has entries; hard-delete otherwise |
| `entries.php` | Full entry list with filters |
| `categories.php` | Category management |
| `edit.php` | Edit form for entry or category (param: `?entry_id=` or `?category_id=`) |
| `add.php` / `add_mobile.php` | Add entry forms |
| `report.php` | Charts / reports |
| `notes.php` | Personal notes |
| `vocab.php` | Vocabulary notebook |
| `config/db.php` | DB connection + charset fallback + schema migrations |
| `config/functions.php` | Shared helpers |
| `auth.php` | Session guard (redirects to login if not authenticated) |

## Detail Modal (index.php)

The modal loads content from `get_detail.php` via AJAX and **must not reload the page**.

Key JS patterns in `index.php`:
- `pageNeedsRefresh` flag — set to `true` after any mutation; triggers `location.reload()` on modal close
- `modalBody.addEventListener('submit', ...)` — intercepts all forms inside modal, uses `fetch()` instead of normal submit
- `data-confirm="..."` attribute on delete forms — checked **before** `e.preventDefault()` so Cancel actually aborts
- `sessionStorage` caches modal HTML per `detail_{categoryId}_{month}_{year}` key; cleared on mutation

## Git

- **Gitea** (local container): `http://127.0.0.1:44143` — use `git push -u origin <branch>`
- **GitHub** (Windows server pulls from here): use `mcp__github__push_files` MCP tool
- After every GitHub MCP push, run `git fetch origin <branch> && git rebase origin/<branch> && git push` to keep Gitea in sync
- Active branch: `claude/gifted-meitner-G3eI2`
- Repo: `moochanakarn-pixel/finance-app`

## Token-saving Tips
- `index.php` is 2,010 lines — always use `offset`/`limit` when reading, target only the relevant section
- Prefer `Edit` tool over full `Write` rewrites
- Batch all changes to a file before pushing; don't push after each small edit
- `debug2.php` and `debug_charset.php` are temporary debug files — can be deleted
