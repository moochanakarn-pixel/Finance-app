# Finance App — Claude Context

## Project Overview
Personal finance tracker (PHP + MySQLi). Multi-user, Thai UI, Buddhist Era (BE) dates.

## Stack
- PHP 7/8, MySQLi (no PDO, no ORM)
- MySQL on port **3307** (not default 3306)
- Bootstrap 5 + Bootstrap Icons (CDN)
- Vanilla JS (no framework)
- No Composer, no package manager

## Database Schema

**MySQL 5.1.57-community (Win64)** — utf8mb4 NOT supported (added in MySQL 5.5.3); connection always falls back to utf8.  
All tables: `ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci`

### `categories`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| name | VARCHAR(150) utf8_unicode_ci | |
| type | ENUM('income','expense','saving') | |
| sort_order | INT DEFAULT 0 | |
| is_active | TINYINT(1) DEFAULT 1 | 0 = soft-deleted |
| created_at | DATETIME | |
| updated_at | DATETIME NULL | |
| import_alias | VARCHAR(255) NULL | |
| user_id | INT NULL | FK (no constraint) |
| budget_amount | DECIMAL(12,2) DEFAULT 0.00 | added via migration M1 |

Indexes: `idx_categories_user_id`, `idx_categories_user_active_type_sort(user_id, is_active, type, sort_order)`

### `entries`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| category_id | INT NOT NULL | FK → categories(id) ON UPDATE CASCADE |
| entry_date | DATE NOT NULL | stored as AD |
| amount | DECIMAL(12,2) DEFAULT 0.00 | |
| note | TEXT utf8_unicode_ci NULL | |
| created_at | DATETIME NOT NULL | |
| updated_at | DATETIME NULL | |
| user_id | INT NULL | |

Indexes: `idx_category_id`, `idx_entry_date`, `idx_entries_user_id`, `idx_entries_user_date_category`, `idx_entries_user_category_date`

### `users`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| username | VARCHAR(100) CHARACTER SET utf8 | used as email |
| password_hash | VARCHAR(255) CHARACTER SET utf8 | bcrypt |
| full_name | VARCHAR(150) CHARACTER SET utf8 NULL | |
| role | ENUM('admin','user') DEFAULT 'user' | |
| is_active | TINYINT(1) DEFAULT 1 | |
| created_at | DATETIME NOT NULL | |
| updated_at | DATETIME NULL | |

Unique: `uniq_username`

### `_dbver` (migration tracker)
| Column | Type |
|--------|------|
| k | VARCHAR(60) PK |

Applied migrations: `budget_amount_col`, `all_tables_utf8`

## Key Rules

### Database
- **MySQL 5.1.57** — no utf8mb4; connection charset is always `utf8` (3-byte, max U+FFFF)
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

- **Gitea** (local container): `http://127.0.0.1:41729` — use `git push -u origin <branch>`
- **GitHub** (Windows server pulls from here): use `mcp__github__push_files` MCP tool
- After every GitHub MCP push, run `git fetch origin <branch> && git rebase origin/<branch> && git push` to keep Gitea in sync
- Active branch: `claude/gifted-meitner-G3eI2`
- Repo: `moochanakarn-pixel/finance-app`

## Token-saving Tips
- `index.php` is 2,010 lines — always use `offset`/`limit` when reading, target only the relevant section
- Prefer `Edit` tool over full `Write` rewrites
- Batch all changes to a file before pushing; don't push after each small edit
- `debug2.php` and `debug_charset.php` are temporary debug files — can be deleted

## Working Memory

@CLAUDE-WORK.md
