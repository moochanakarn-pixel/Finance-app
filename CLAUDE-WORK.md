# Finance App — Working Memory

> อัปเดตทุก session ที่มีการเปลี่ยนแปลงสำคัญ  
> อ่านไฟล์นี้ก่อนเริ่มทำงานทุกครั้ง — ประหยัดเวลาแทนการอ่าน index.php ใหม่

---

## Current State

| Key | Value |
|-----|-------|
| Branch | `claude/gifted-meitner-G3eI2` |
| Last commit | `bacb3a3` — Fix modal scroll / sticky header / edit+delete (2026-07-16) |
| Gitea remote | `http://local_proxy@127.0.0.1:41729/git/moochanakarn-pixel/Finance-app` |
| GitHub repo | `moochanakarn-pixel/finance-app` |
| Gitea → GitHub | Auto-syncs on every `git push` (no manual MCP push needed) |

---

## index.php — Line Number Index

ไฟล์ 2010 บรรทัด อ่านแบบ chunk เสมอ (`offset` + `limit`)

| Section | Lines | Read Command |
|---------|-------|-------------|
| PHP: includes, session, helpers | 1–50 | offset=0 limit=50 |
| PHP: year/month selection | 51–120 | offset=50 limit=70 |
| PHP: DB queries (categories, entries, budget) | 120–265 | offset=120 limit=150 |
| HTML: page start → hero cards | 266–435 | offset=266 limit=170 |
| HTML: quick-add form | 436–460 | offset=436 limit=25 |
| HTML: budget table panel | 461–825 | offset=461 limit=365 |
| HTML: detail modal scaffold | 826–840 | offset=826 limit=15 |
| HTML: page end + `<script>` tag | 841–842 | offset=841 limit=2 |
| JS: DOM refs + utility functions | 843–940 | offset=843 limit=100 |
| JS: openModal / closeModal / backdrop | 910–960 | offset=910 limit=50 |
| JS: loadModalContent (fetch + cache) | 960–1023 | offset=960 limit=65 |
| JS: form submit delegation (modalBody) | 1024–1080 | offset=1024 limit=57 |
| JS: two-stage delete delegation | 1081–1113 | offset=1081 limit=33 |
| JS: quick-add form logic | 1114–1151 | offset=1114 limit=38 |
| JS: Chart.js setup | 1152–1203 | offset=1152 limit=52 |

**Grep shortcuts** (ใช้แทนการเดา line number):
```
grep: "function openModal"  → line 922
grep: "function closeModal" → line 933
grep: "pageNeedsRefresh"    → line 941 (declaration)
grep: "modalBody.addEventListener('submit'" → line 1024
grep: "js-delete-confirm-btn" → line 1084
```

---

## Modal System — Complete Reference

### HTML Structure (lines 826–839)

```
#detailModal  (.modal)                      ← fixed backdrop, display:none → flex
  └─ .modal-dialog                          ← white card + SCROLL CONTAINER
       ├─ .modal-header  (sticky top:0)     ← title + close button
       │    ├─ #modalTitle
       │    └─ #modalSubtitle
       └─ .modal-body  (#modalBody)         ← AJAX content from get_detail.php
```

### JS State Variables (line 910+)

| Variable | Role |
|----------|------|
| `detailModal` | `getElementById('detailModal')` |
| `modalBody` | `getElementById('modalBody')` |
| `pageNeedsRefresh` | `true` หลัง mutation → reload เมื่อปิด modal |
| `currentDetailController` | AbortController สำหรับ fetch ที่กำลังทำงาน |
| cache key | `sessionStorage`: `detail_{categoryId}_{month}_{yearBE}` |

### openModal flow (line 922)

```
openModal(detailModal)
  1. modal.classList.add('open')
  2. document.body.style.overflow = 'hidden'
  3. requestAnimationFrame → .modal-dialog.scrollTop = 0
  4. loadModalContent(categoryId, month, year)
       ├─ cache hit → inject HTML, resetModalScroll()
       └─ cache miss → fetch get_detail.php?category_id=&month=&year=
                       → inject HTML, resetModalScroll(), cache in sessionStorage
```

### closeModal flow (line 933)

```
closeModal(detailModal)
  1. modal.classList.remove('open')
  2. document.body.style.overflow = ''
  3. ถ้า pageNeedsRefresh → location.reload()
```

### Form Submit Delegation (lines 1024–1080)

- `modalBody.addEventListener('submit', ...)` จับทุก form ใน modal
- `fetch(action, { method:'POST', body: FormData, redirect:'follow' })`
- success: `pageNeedsRefresh = true` → re-fetch modal content (no page reload yet)
- Forms ต้องมี hidden fields: `action`, `category_id`, `month`, `year_be`, `return_url`

### Two-Stage Delete (lines 1081–1113)

```
click .js-delete-confirm-btn → hide .js-delete-stage1, show .js-delete-stage2
click .js-delete-cancel-btn  → hide .js-delete-stage2, show .js-delete-stage1
submit .js-delete-form       → intercepted by submit delegation above
```

> หมายเหตุ: `<script>` ที่ inject ผ่าน `innerHTML` จะไม่ถูก execute — ต้องใช้ event delegation เท่านั้น

---

## get_detail.php — Output Structure

```php
// 1. Summary row (3 boxes: entry count, total, month/year)
<div class="detail-summary"> ... </div>

// 2. Add form (top of list)
<div class="entry-card">
  <form method="post" action="save_entry.php" class="inline-form">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="category_id" value="...">
    <input type="hidden" name="month" value="...">
    <input type="hidden" name="year_be" value="...">
    <input type="hidden" name="return_url" value="index.php?year=...">
    <!-- date, amount, note inputs -->
  </form>
</div>

// 3. Each entry (edit + delete forms)
<div class="entry-card">
  <form ... class="inline-form">   // update form
  <form ... class="js-delete-form">  // delete form
    <div class="js-delete-stage1"> ... </div>
    <div class="js-delete-stage2" style="display:none"> ... </div>
  </form>
</div>
```

---

## CSS Architecture — Key Modal Selectors (dashboard.css)

| Selector | Role | Notes |
|----------|------|-------|
| `.modal` | Fixed backdrop | `overflow:hidden` — ไม่ใช่ scroll container |
| `.modal.open` | Active state | เพิ่ม `display:flex` |
| `.modal-dialog` | White card | **THE scroll container** — `overflow-y:auto` |
| `.modal-header` | Sticky header | `position:sticky; top:0; z-index:2` ภายใน `.modal-dialog` |
| `.modal-body` | Content area | AJAX target |
| `.detail-summary` | 3-col summary grid | |
| `.entry-card` | แต่ละ entry | |
| `.entry-head` | วันที่ + จำนวน | |
| `.inline-form` | Form grid | `display:grid; gap:12px` |
| `.inline-row` | Label + input | `grid-template-columns: 150px 1fr` |
| `.entry-actions` | ปุ่มต่างๆ | |
| `.js-delete-stage1` | ปุ่ม "ลบรายการนี้" | |
| `.js-delete-stage2` | ยืนยัน + ยกเลิก | `display:none` เริ่มต้น |

**Modal max-height values:**
- Desktop: `calc(100dvh - 90px)` (padding 18px×2 + margin 20px×2 + buffer)
- Mobile `@media (max-width:760px)`: `calc(100dvh - 60px)` (padding 10px×2 + margin 20px×2)

---

## Fixes Log

### 2026-07-16 — Modal scroll / sticky header / edit+delete (commit `bacb3a3`)

**อาการ:** ไม่สามารถ scroll ใน modal เมื่อมีรายการเยอะ, header ค้าง, กด edit/delete ไม่ได้

**Root cause:** `.modal` มี `display:flex + overflow-y:auto` บน `position:fixed` — iOS Safari และ browser บางตัวทำให้ scroll ทำงานผิดปกติเมื่อ `body.overflow='hidden'`

**Fix — dashboard.css:**
```css
/* BEFORE */
.modal { overflow-y: auto; -webkit-overflow-scrolling: touch; }

/* AFTER */
.modal { overflow: hidden; overscroll-behavior: contain; }
.modal-dialog {
    max-height: calc(100vh - 90px);
    max-height: calc(100dvh - 90px);   /* dvh = dynamic viewport height */
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}
.modal-header { border-radius: 22px 22px 0 0; }  /* ต้องเพิ่มเพราะ dialog clip overflow */

/* mobile */
@media (max-width:760px) {
    .modal-dialog { max-height: calc(100dvh - 60px); }
}
```

**Fix — index.php** (lines 926, 930):
```js
// scrollTop ต้อง target .modal-dialog ไม่ใช่ backdrop
var dlg = modal.querySelector('.modal-dialog');
(dlg || modal).scrollTop = 0;
```

---

## Git Workflow — Exact Commands

**ลำดับที่ถูกต้อง:**

```bash
# 1. commit locally
git add <specific-files>
git commit -m "..."

# 2. push to Gitea → auto-syncs to GitHub
git push -u origin claude/gifted-meitner-G3eI2

# 3. ตรวจสอบ GitHub (optional แต่แนะนำ)
# → mcp__github__get_file_contents เช็ค SHA ล่าสุด
```

**ถ้า push ไป GitHub ด้วย MCP ก่อน Gitea:**
```bash
git fetch origin claude/gifted-meitner-G3eI2
git rebase origin/claude/gifted-meitner-G3eI2
git push
```

**SHA mismatch error บน MCP push** = Gitea sync ไป GitHub แล้ว → ไม่ต้อง push ซ้ำ

> **Gitea URL จริง:** `http://local_proxy@127.0.0.1:41729/git/moochanakarn-pixel/Finance-app`  
> (CLAUDE.md บอก port 44143 แต่ remote จริงคือ **41729**)

---

## Common Task Recipes

### เพิ่ม field ใน entry (เช่น tags)
1. `config/db.php` → เพิ่ม migration ALTER TABLE entries ADD COLUMN ...
2. `get_detail.php` → เพิ่ม input ใน add form + update form, เพิ่มใน SELECT
3. `save_entry.php` → เพิ่มใน INSERT + UPDATE

### เพิ่ม column ในตาราง budget
1. หา `<thead>` ใน index.php (~line 600) → เพิ่ม `<th>`
2. หา PHP loop ด้านล่าง → เพิ่ม `<td>` ให้ตรงกัน
3. เพิ่ม CSS class ถ้าต้องการ width ใหม่

### เพิ่ม endpoint modal ใหม่
1. สร้างไฟล์ `get_something.php` return HTML fragments
2. ใช้ CSS classes เดิม (`.entry-card`, `.detail-summary`, `.inline-form`)
3. JS: เปลี่ยน URL ใน `loadModalContent()` ตาม param ที่ส่ง

### แก้ไข form ใน modal
- ทุก form ต้องมี: `name="action"`, `name="category_id"`, `name="month"`, `name="year_be"`, `name="return_url"`
- action ชี้ไป `save_entry.php` หรือ endpoint ที่ถูกต้อง
- JS จะ intercept ผ่าน delegation ที่ `#modalBody` อัตโนมัติ

---

## Danger Zones

| อย่าทำ | เพราะ |
|--------|-------|
| `mysqli_set_charset()` ใน page files | ทำแล้วใน `config/db.php` → double-call ทำ connection พัง |
| ใช้ utf8mb4 | MySQL 5.1.57 ไม่รองรับ — max คือ utf8 (3-byte) |
| แสดงปีโดยไม่บวก 543 | DB เก็บ AD, UI ต้องแสดง BE (AD+543) |
| ลืม `$userId` guard | ทุกหน้าต้องมี `$userId = (int)$_SESSION['user_id'];` |
| เปลี่ยน backdrop click ให้ปิด modal โดยไม่เช็ค | ต้องเป็น `if (e.target === detailModal)` เท่านั้น |
| `modal.scrollTop = 0` | ต้อง target `.modal-dialog` ไม่ใช่ backdrop |
| inject `<script>` ผ่าน innerHTML | browser ไม่ execute — ใช้ event delegation แทน |

---

## Session Update Protocol

เมื่อ session ทำงานเสร็จที่มีการเปลี่ยนแปลงสำคัญ ให้อัปเดตส่วนต่อไปนี้:
1. **Current State** → อัปเดต last commit + วันที่
2. **Fixes Log** → เพิ่ม fix ใหม่ถ้ามี
3. **index.php Line Index** → อัปเดต line numbers ถ้าไฟล์เปลี่ยน
