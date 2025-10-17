This repository is a PHP-based school management system (mix of procedural PHP, AdminLTE-like templates, and a few libraries). The goal of these instructions is to give an AI coding agent the essential, actionable context so it can make safe, minimal, and useful edits.

Quick architecture summary
- Procedural PHP templates live at project root and under `admin/`, `teacher/`, `exam/`, etc. Most pages are single-file PHP + HTML (no PSR-4 or Composer structure).
- `config.php` sets up a PDO `$pdo` connection, defines `BASE_URL` and `ADMIN_URL`, and toggles PHP error display (use this as the primary DB connection example).
- Some legacy/CI-style files use `$this->db->...` and `$this->lang->line()` (mixed coding styles). Always inspect the file to see whether it expects CodeIgniter helpers or plain PDO.
- Reusable UI parts are under `admin/inc/` (header.php, sidebar.php, footer.php) and `teacher/inc/`.
- Print helpers are in `admin/print_common.php` and QR code utilities live in `assets/phpqrcode/` (also TCPDF bindings under `assets/bindings`).
- Static assets and uploads: `assets/`, `uploads/logo/`, `uploads/students/`, `uploads/users/`.
- SQL dumps and schema helpers are in `database/` (use these when adding/altering DB schema).

Dev workflow & run/debug hints
- Local runtime: intended to run on XAMPP / Apache + MySQL. Edit `config.php` to point DB credentials and `BASE_URL` (default: `http://localhost/ksms/`).
- Import a suitable SQL from `database/` into MySQL (phpMyAdmin or CLI) before running. Common files: `jorepuku_ksms.sql`, other `*.sql` in `database/`.
- Errors: `config.php` enables display_errors; check browser output and `apache/php_error.log`. Many pages mix template logic and DB calls — check included files (`admin/inc/*`) for shared helpers.

Patterns & project conventions (concrete)
- URL building: use `BASE_URL` and `ADMIN_URL` constants from `config.php`. Example: `<?php echo BASE_URL; ?>admin/profile.php`.
- Printing pages: include `admin/print_common.php` and use `print_header($pdo, $title)` / `print_footer()` for consistent headers/footers.
- DB access: two styles exist:
  - PDO `$pdo` prepared statements (see `index.php`, `config.php`) — preferred for new work.
  - CodeIgniter-like `$this->db->query(...)` in some existing templates — do not blindly convert these; verify the execution context before changing.
- Language / i18n: some files call `$this->lang->line('key')`. If you touch UI text, preserve the original keys or the Bengali text already present.
- Helpers: project uses custom helpers for formatting (e.g., `store_number_format`, `no_to_words`, `format_qty`). They may be defined in helper includes; search before adding duplicates.

Where to look first (key files to open for any UI/logic change)
- `config.php` — DB connection, base URLs, error reporting.
- `admin/inc/header.php`, `admin/inc/sidebar.php`, `admin/inc/footer.php` — global layout and nav patterns.
- `admin/print_common.php` — print layout helper functions.
- `exam/create_exam.php`, `exam/tabulation.php` — example of more complex features (AJAX usage, exam/tabulation logic).
- `assets/phpqrcode/` and `assets/bindings/tcpdf/` — external libs, used by certificate/QR features.
- `database/` — SQL dumps to learn schema and to apply migrations.

Editing & safety rules for AI agents
- Preserve application logic and SQL shape exactly unless the user explicitly asks for schema changes. UI/HTML/CSS improvements are okay when you leave logic untouched.
- If you must edit DB-related code, search for all usages before renaming columns, tables, or helper functions. Provide SQL ALTER statements when adding DB columns and clearly mark them as required.
- Avoid introducing frameworks or heavy refactors. This codebase expects small, conservative edits.
- When adding new files, follow existing naming and folder patterns (`admin/`, `exam/`, `teacher/`).

Examples & snippets
- Build a link to admin dashboard: `<?php echo ADMIN_URL; ?>dashboard.php`.
- PDO example (follow pattern in `index.php`):
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
- Use print header for invoices/print pages:
  include 'admin/print_common.php';
  echo print_header($pdo, 'শিরোনাম');

Integration points & external dependencies
- PHP >=7.x and MySQL. Project tested on XAMPP-style stack.
- QR generation: `assets/phpqrcode/*` (qrlib.php, phpqrcode.php).
- PDF generation (if used elsewhere): TCPDF bindings under `assets/bindings/tcpdf/`.

If something is ambiguous
- Search the code for helper or pattern before creating a new helper. Use `grep`/repository search for the function name.
- When a file uses `$this->db` or `$this->lang`, check if it is intended to run under CodeIgniter or if those are legacy calls—confirm by opening the file and its includes.

When you finish a change
- Run a quick smoke test in a browser at the configured `BASE_URL`.
- For DB changes, provide `ALTER TABLE` SQL and a short migration note.

Questions for the maintainer
- Is the application running under CodeIgniter in production or has it been migrated to plain PHP + PDO? (This matters for `$this->db` and `$this->lang` usages.)
- Any hidden helper files (e.g., `comman/code_css.php`) not in the repo root that should be preserved? If yes, point to their path.

End of instructions — ask the user for feedback and any special rules to include.
