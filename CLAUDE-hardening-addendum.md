# CLAUDE.md Addendum: Hardening Build Constraints

This addendum governs the hardening and feature work described in WMS-hardening-implementation-plan.md. It sits alongside the original CLAUDE.md. Where the two ever appear to conflict, this addendum wins for the hardening build. Read this before starting any phase.

## Non-negotiable constraints

1. **No technology stack changes.** PHP procedural, mysqli with prepared statements only, Bootstrap 5, jQuery, DataTables, Parsley.js, DOMPurify, SweetAlert2, PHPMailer, Font Awesome, Comfortaa. No frameworks, ORMs, routers, npm, or build steps. New capabilities (for example a TOTP library in Phase 7) may be added only as a Composer PHP package and only if the user opts in; they still count as "PHP", not a stack change.

2. **Schema is preserved.** Do not rewrite or restructure the core tables in database/schema.sql. All new tables and columns go in numbered migration files under database/migrations/ (001_..., 002_..., and so on) so history stays traceable and re-runnable.

3. **No em dashes.** Not in code comments, UI strings, commit messages, migration comments, or any documentation. This is a hard rule everywhere.

4. **Preserve every working control.** The diagnosis in the plan lists what is already correct (prepared statements, per-processor auth_check, session-scoped employee_id, generic login errors, MIME-validated uploads, manager-scoped leave approval). Do not remove, weaken, or "simplify" any of these while adding new code.

## Dual-theme requirement (important, do not treat this app as dark-only)

The app already ships a working dark/light theme switch. Any reference to the "house style" means the dual-theme system below, NOT dark-only. New pages, nav entries, sidebar links, dashboard widgets, modals, tables, and the entire module-builder UI must render correctly in BOTH themes.

How the existing theme system works (match it, do not reinvent it):

- Theme is driven by Bootstrap's native `data-bs-theme` attribute on the `<html>` element, set to `"dark"` or `"light"`.
- The default is dark. Light styling is applied through CSS overrides in src/css/style.css scoped under `html[data-bs-theme="light"]` (for example `html[data-bs-theme="light"] .bg-111 { ... }`).
- The custom utility classes are `.bg-000`, `.bg-111`, `.bg-111-custom`, `.bg-222`, `.bg-333`, `.text-light`, `.text-white-50`, plus `.brand-title`, `.link`, and DataTables overrides, all of which already have light-theme counterparts.
- Persistence is client-side in `localStorage` under the key `workdesk_theme`. There is no server-side theme storage and none should be added; the Phase 4 session and SameSite hardening does not touch localStorage, so there is no conflict.
- The toggle is `toggle_theme()` in src/script.js, wired to a button in includes/nav.php with icon id `theme_toggle_icon` (fa-moon in dark, fa-sun in light).
- A small inline script at the top of includes/nav.php reads localStorage and sets `data-bs-theme` before render to prevent a flash of the wrong theme. Any new full-page template must include this same early-set script, or include nav.php early enough that it runs.

Rules for all new UI:

- Style new elements with the existing utility classes (`.bg-222`, `.bg-333`, `.text-light`, and so on) so the light-theme overrides apply automatically. Do NOT hardcode dark-only colors (no raw `#111`/`#222` hex, no Bootstrap `bg-dark`/`text-white` fixed classes) that would look correct in dark and broken in light.
- If a genuinely new color surface is needed, add BOTH the dark rule and its `html[data-bs-theme="light"]` override in style.css in the same change. Never add a dark rule without its light counterpart.
- SweetAlert2 dialogs render outside the normal DOM and can ignore `data-bs-theme`. For any new Swal dialog, confirm it reads acceptably in both themes; if it does not, pass theme-aware `customClass` or `background`/`color` options based on the current `document.documentElement.getAttribute('data-bs-theme')`.
- DataTables added to new pages must pick up the existing `html[data-bs-theme="light"] table.dataTable` overrides. Do not apply inline table colors that would defeat them.

Verification for every new page or widget: load it, toggle the theme, confirm both states are legible and consistent with the rest of the app. This check is part of "done" for Phases 6 and 7, not an afterthought.

## Module builder must respect theming and the seed-parity rule

- The module_builder admin pages, the role-defaults matrix, the per-user override view, and the dashboard widget cards must all use the existing utility classes so they theme correctly.
- The migration seed for modules, role_module_defaults, and dashboard widgets must reproduce the CURRENT visibility behavior exactly, so nothing visibly changes on first deploy until an Admin edits something. This "no visible change on day one" property is a correctness requirement, not a nicety.
- Nav and sidebar become data-driven from get_visible_modules(), but the generated markup, classes, active-state highlighting via $_SESSION['page_name'], the theme toggle button, and the mobile offcanvas behavior must be byte-for-byte equivalent to what renders today. The only change is that the link list is looped from the resolved module set instead of hardcoded.

## Phasing and verification

Work the phases in the plan's order (exposure, brute-force, CSRF, sessions, logic, module builder, then optional features). Do not start a phase before the previous one's verification steps pass. Report what you changed per phase and pause for the user to test, especially before the module-builder refactor of nav.php and sidebar.php, since those touch every page.
