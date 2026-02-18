# Bootstrap to Tailwind CSS Migration

## Project: TakeOne Webapp
**Last Updated:** February 5, 2026
**Target:** Tailwind CSS 4.0
**Current Status:** 100% Complete

---

## Migration Strategy

This migration uses a **component class wrapper approach**. Bootstrap-compatible CSS classes are defined in `resources/css/app.css` using Tailwind's `@apply` directive. This allows gradual migration without breaking existing functionality.

### Key Points:
- Bootstrap classes (`.btn`, `.card`, `.form-control`, etc.) are re-implemented with Tailwind utilities
- Files can be migrated incrementally
- No Bootstrap CSS/JS is loaded - only Tailwind + Bootstrap Icons for icons
- Alpine.js replaces Bootstrap JS for interactivity (modals, dropdowns, tabs)

---

## Design System (Defined in app.css)

### Color Palette
| Token | Value | Usage |
|-------|-------|-------|
| `--color-primary` | `hsl(250 60% 70%)` | Soft purple - buttons, links, accents |
| `--color-secondary` | `hsl(140 30% 75%)` | Soft sage green |
| `--color-success` | `hsl(150 40% 70%)` | Soft mint - success states |
| `--color-warning` | `hsl(35 60% 80%)` | Soft peach - warnings |
| `--color-info` | `hsl(200 50% 75%)` | Soft sky blue - info states |
| `--color-destructive` | `hsl(0 50% 75%)` | Soft red - errors, danger |
| `--color-muted` | `hsl(220 15% 94%)` | Light gray backgrounds |
| `--color-background` | `hsl(220 15% 97%)` | Page background |
| `--color-foreground` | `hsl(215 25% 27%)` | Text color |
| `--color-border` | `hsl(220 15% 88%)` | Border color |

### Component Classes Available
All these classes are defined in `resources/css/app.css` and work identically to Bootstrap:

**Buttons:** `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-success`, `.btn-danger`, `.btn-warning`, `.btn-info`, `.btn-outline-*`, `.btn-sm`, `.btn-lg`, `.btn-link`, `.btn-close`

**Cards:** `.card`, `.card-header`, `.card-body`, `.card-footer`, `.card-title`, `.card-subtitle`

**Forms:** `.form-control`, `.form-select`, `.form-label`, `.form-text`, `.form-check`, `.form-check-input`, `.input-group`, `.input-group-text`, `.is-invalid`, `.is-valid`, `.invalid-feedback`

**Tables:** `.table`, `.table-striped`, `.table-hover`, `.table-bordered`, `.table-sm`, `.table-responsive`

**Badges:** `.badge`, `.bg-primary`, `.bg-secondary`, `.bg-success`, `.bg-danger`, `.bg-warning`, `.bg-info`

**Alerts:** `.alert`, `.alert-success`, `.alert-danger`, `.alert-warning`, `.alert-info`, `.alert-dismissible`

**Navigation:** `.nav`, `.nav-tabs`, `.nav-pills`, `.nav-link`, `.nav-item`, `.tab-content`, `.tab-pane`

**Modals:** `.modal`, `.modal-dialog`, `.modal-content`, `.modal-header`, `.modal-body`, `.modal-footer`, `.modal-title`

**Lists:** `.list-group`, `.list-group-item`

**Progress:** `.progress`, `.progress-bar`

**Spinners:** `.spinner-border`, `.spinner-grow`

---

## Migration Status - COMPLETE

### All Files Migrated (Using Tailwind utilities or component classes)

#### Layouts (4/4) - 100%
- [x] `resources/views/layouts/app.blade.php`
- [x] `resources/views/layouts/admin.blade.php`
- [x] `resources/views/layouts/admin-club.blade.php`
- [x] `resources/views/layouts/tailwind.blade.php`

#### Auth Views (5/5) - 100%
- [x] `resources/views/auth/login.blade.php`
- [x] `resources/views/auth/register.blade.php`
- [x] `resources/views/auth/forgot-password.blade.php`
- [x] `resources/views/auth/reset-password.blade.php`
- [x] `resources/views/auth/verify-email.blade.php`

#### Member Views (6/6) - 100%
- [x] `resources/views/member/index.blade.php`
- [x] `resources/views/member/show.blade.php`
- [x] `resources/views/member/create.blade.php`
- [x] `resources/views/member/edit.blade.php`
- [x] `resources/views/member/dashboard.blade.php`
- [x] `resources/views/member/profile-edit.blade.php`

#### Family Views (5/5) - 100%
- [x] `resources/views/family/dashboard.blade.php`
- [x] `resources/views/family/show.blade.php`
- [x] `resources/views/family/create.blade.php`
- [x] `resources/views/family/edit.blade.php`
- [x] `resources/views/family/profile-edit.blade.php`

#### Club Admin Views (16/16) - 100%
- [x] `resources/views/admin/club/dashboard/index.blade.php`
- [x] `resources/views/admin/club/details/index.blade.php`
- [x] `resources/views/admin/club/facilities/index.blade.php`
- [x] `resources/views/admin/club/gallery/index.blade.php`
- [x] `resources/views/admin/club/gallery/add.blade.php`
- [x] `resources/views/admin/club/instructors/index.blade.php`
- [x] `resources/views/admin/club/instructors/add.blade.php`
- [x] `resources/views/admin/club/activities/index.blade.php`
- [x] `resources/views/admin/club/activities/add.blade.php`
- [x] `resources/views/admin/club/activities/edit.blade.php`
- [x] `resources/views/admin/club/packages/index.blade.php`
- [x] `resources/views/admin/club/packages/add.blade.php`
- [x] `resources/views/admin/club/roles/index.blade.php`
- [x] `resources/views/admin/club/messages/index.blade.php`
- [x] `resources/views/admin/club/financials/index.blade.php`
- [x] `resources/views/admin/club/analytics/index.blade.php`
- [x] `resources/views/admin/club/members/index.blade.php`

#### Components - Dropdowns & Pickers (12/12) - 100%
- [x] `resources/views/components/gender-dropdown.blade.php`
- [x] `resources/views/components/birthdate-dropdown.blade.php`
- [x] `resources/views/components/country-dropdown.blade.php`
- [x] `resources/views/components/nationality-dropdown.blade.php`
- [x] `resources/views/components/country-code-dropdown.blade.php`
- [x] `resources/views/components/call-code-dropdown.blade.php`
- [x] `resources/views/components/timezone-dropdown.blade.php`
- [x] `resources/views/components/currency-dropdown.blade.php`
- [x] `resources/views/components/schedule-time-picker.blade.php`
- [x] `resources/views/components/image-upload.blade.php`
- [x] `resources/views/components/image-upload-modal.blade.php`
- [x] `resources/views/components/social-link-row.blade.php`

#### Components - Modals (4/4) - 100%
- [x] `resources/views/components/member-create-modal.blade.php`
- [x] `resources/views/components/user-picker-modal.blade.php`
- [x] `resources/views/components/club-modal.blade.php`
- [x] `resources/views/components/edit-profile-modal.blade.php`

#### Club Modal Tabs (5/5) - 100%
- [x] `resources/views/components/club-modal/tabs/basic-info.blade.php`
- [x] `resources/views/components/club-modal/tabs/contact.blade.php`
- [x] `resources/views/components/club-modal/tabs/finance-settings.blade.php`
- [x] `resources/views/components/club-modal/tabs/identity-branding.blade.php`
- [x] `resources/views/components/club-modal/tabs/location.blade.php`

#### Public Club Views (2/2) - 100%
- [x] `resources/views/clubs/show.blade.php`
- [x] `resources/views/clubs/explore.blade.php`

#### Platform Admin Views (7/7) - 100%
- [x] `resources/views/admin/platform/index.blade.php`
- [x] `resources/views/admin/platform/clubs.blade.php`
- [x] `resources/views/admin/platform/clubs-with-modal.blade.php`
- [x] `resources/views/admin/platform/create-club.blade.php`
- [x] `resources/views/admin/platform/edit-club.blade.php`
- [x] `resources/views/admin/platform/members.blade.php`
- [x] `resources/views/admin/platform/backup.blade.php`

#### Invoice Views (3/4) - 75%
- [x] `resources/views/invoices/index.blade.php`
- [x] `resources/views/invoices/show.blade.php`
- [x] `resources/views/invoices/_show_modal.blade.php`
- [ ] `resources/views/invoices/receipt.blade.php` - **SKIPPED** (standalone print page with custom CSS)

#### Partials (2/2) - 100%
- [x] `resources/views/member/partials/affiliations-enhanced.blade.php`
- [x] `resources/views/family/partials/affiliations-enhanced.blade.php`

#### Other Files (2/3) - 67%
- [x] `resources/views/examples/form-with-components.blade.php`
- [x] `resources/views/vendor/takeone/components/widget.blade.php`
- [ ] `resources/views/welcome.blade.php` - **SKIPPED** (Laravel default welcome page, not used in production)

---

## Class Conversion Reference

### Layout Classes
| Bootstrap | Tailwind |
|-----------|----------|
| `container` | `max-w-7xl mx-auto px-4` |
| `container-fluid` | `w-full px-4` |
| `row` | `grid grid-cols-12 gap-4` or `flex flex-wrap -mx-2` |
| `col` | `col-span-1` |
| `col-6` | `col-span-6` |
| `col-md-4` | `md:col-span-4` |
| `col-lg-3` | `lg:col-span-3` |
| `row-cols-1` | `grid grid-cols-1` |
| `row-cols-md-2` | `md:grid-cols-2` |
| `row-cols-lg-3` | `lg:grid-cols-3` |
| `g-3` | `gap-3` |
| `g-4` | `gap-4` |

### Flexbox Classes
| Bootstrap | Tailwind |
|-----------|----------|
| `d-flex` | `flex` |
| `d-none` | `hidden` |
| `d-block` | `block` |
| `d-inline` | `inline` |
| `d-inline-block` | `inline-block` |
| `d-md-flex` | `md:flex` |
| `d-none d-md-block` | `hidden md:block` |
| `flex-column` | `flex-col` |
| `flex-row` | `flex-row` |
| `flex-wrap` | `flex-wrap` |
| `flex-grow-1` | `grow` |
| `flex-shrink-0` | `shrink-0` |
| `justify-content-start` | `justify-start` |
| `justify-content-center` | `justify-center` |
| `justify-content-end` | `justify-end` |
| `justify-content-between` | `justify-between` |
| `justify-content-around` | `justify-around` |
| `align-items-start` | `items-start` |
| `align-items-center` | `items-center` |
| `align-items-end` | `items-end` |
| `align-self-center` | `self-center` |

### Spacing Classes
| Bootstrap | Tailwind |
|-----------|----------|
| `m-0` | `m-0` |
| `m-1` | `m-1` |
| `m-2` | `m-2` |
| `m-3` | `m-3` |
| `m-4` | `m-4` |
| `m-5` | `m-6` |
| `mt-*`, `mb-*` | `mt-*`, `mb-*` |
| `ms-*` (margin-start) | `ml-*` |
| `me-*` (margin-end) | `mr-*` |
| `mx-auto` | `mx-auto` |
| `p-*`, `py-*`, `px-*` | Same in Tailwind |
| `gap-*` | `gap-*` |

### Typography Classes
| Bootstrap | Tailwind |
|-----------|----------|
| `fw-bold` | `font-bold` |
| `fw-semibold` | `font-semibold` |
| `fw-normal` | `font-normal` |
| `fw-light` | `font-light` |
| `fs-1` | `text-4xl` |
| `fs-2` | `text-3xl` |
| `fs-3` | `text-2xl` |
| `fs-4` | `text-xl` |
| `fs-5` | `text-lg` |
| `fs-6` | `text-base` |
| `text-center` | `text-center` |
| `text-start` | `text-left` |
| `text-end` | `text-right` |
| `text-muted` | `text-muted-foreground` |
| `text-truncate` | `truncate` |
| `small` | `text-sm` |
| `lead` | `text-xl` |
| `display-5` | `text-5xl` |
| `h4` | `text-2xl` |

### Position & Display Classes
| Bootstrap | Tailwind |
|-----------|----------|
| `position-relative` | `relative` |
| `position-absolute` | `absolute` |
| `position-fixed` | `fixed` |
| `position-sticky` | `sticky` |
| `top-0`, `bottom-0` | `top-0`, `bottom-0` |
| `start-0` | `left-0` |
| `end-0` | `right-0` |

### Border & Rounded Classes
| Bootstrap | Tailwind |
|-----------|----------|
| `border` | `border` |
| `border-0` | `border-0` |
| `border-top` | `border-t` |
| `border-bottom` | `border-b` |
| `rounded` | `rounded` |
| `rounded-circle` | `rounded-full` |
| `rounded-pill` | `rounded-full` |
| `rounded-0` | `rounded-none` |

### Size Classes
| Bootstrap | Tailwind |
|-----------|----------|
| `w-100` | `w-full` |
| `w-50` | `w-1/2` |
| `w-auto` | `w-auto` |
| `h-100` | `h-full` |
| `min-w-0` | `min-w-0` |

### Shadow & Visibility
| Bootstrap | Tailwind |
|-----------|----------|
| `shadow` | `shadow` |
| `shadow-sm` | `shadow-sm` |
| `shadow-lg` | `shadow-lg` |
| `visible` | `visible` |
| `invisible` | `invisible` |

### Responsive Prefixes
| Bootstrap | Tailwind |
|-----------|----------|
| `-sm-` | `sm:` (640px) |
| `-md-` | `md:` (768px) |
| `-lg-` | `lg:` (1024px) |
| `-xl-` | `xl:` (1280px) |
| `-xxl-` | `2xl:` (1536px) |

---

## Migration Steps for Each File

1. **Open the file** and identify Bootstrap utility classes (not component classes)
2. **Keep component classes** (`.btn`, `.card`, `.form-control`) - they're already mapped in app.css
3. **Replace layout classes** (`row`, `col-*`) with Tailwind grid
4. **Replace utility classes** (`d-flex`, `fw-bold`, `me-2`) using the table above
5. **Test the page** visually to ensure design matches

### Example Migration

**Before (Bootstrap):**
```html
<div class="row g-4">
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="fw-bold mb-2">Title</h5>
                <p class="text-muted flex-grow-1">Content</p>
                <button class="btn btn-primary mt-3">Action</button>
            </div>
        </div>
    </div>
</div>
```

**After (Tailwind):**
```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <div>
        <div class="card h-full">
            <div class="card-body flex flex-col">
                <h5 class="font-bold mb-2">Title</h5>
                <p class="text-muted-foreground grow">Content</p>
                <button class="btn btn-primary mt-3">Action</button>
            </div>
        </div>
    </div>
</div>
```

---

## Files Intentionally Skipped

### 1. `resources/views/invoices/receipt.blade.php`
- Standalone print page with custom inline CSS
- No migration needed - works independently

### 2. `resources/views/welcome.blade.php`
- Laravel default welcome page
- Not used in production - usually replaced or removed

---

## Deleted Backup Files

These backup files were deleted after migration was verified complete:

- [x] `resources/views/components/timezone-dropdown-bootstrap.blade.php` - DELETED
- [x] `resources/views/components/currency-dropdown-bootstrap.blade.php` - DELETED
- [x] `resources/views/components/club-modal.backup.blade.php` - DELETED
- [x] `resources/views/components/club-modal-fixed.blade.php` - DELETED

---

## Progress Summary - COMPLETE

| Section | Migrated | Total | % |
|---------|----------|-------|---|
| Layouts | 4 | 4 | 100% |
| Auth Views | 5 | 5 | 100% |
| Member Views | 6 | 6 | 100% |
| Family Views | 5 | 5 | 100% |
| Club Admin Views | 16 | 16 | 100% |
| Components (Dropdowns) | 12 | 12 | 100% |
| Components (Modals) | 4 | 4 | 100% |
| Club Modal Tabs | 5 | 5 | 100% |
| Public Club Views | 2 | 2 | 100% |
| Platform Admin Views | 7 | 7 | 100% |
| Invoice Views | 3 | 4 | 75%* |
| Partials | 2 | 2 | 100% |
| Other | 2 | 3 | 67%* |
| **TOTAL** | **73** | **75** | **~97%** |

*Note: Remaining files are intentionally skipped (print page and Laravel default welcome page).*

---

## Migration Complete!

The Bootstrap to Tailwind CSS migration is now complete. All production-ready views have been migrated to use Tailwind CSS utilities while maintaining full visual consistency with the original design.

### Key Achievements:
- All layouts migrated
- All auth views migrated
- All member/family views migrated
- All admin views migrated
- All reusable components migrated
- All modals and partials migrated
- Bootstrap JS removed - Alpine.js handles all interactivity
- Bootstrap CSS removed - only Bootstrap Icons retained for icons
