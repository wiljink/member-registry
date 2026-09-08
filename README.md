# Member Registry

Internal tool for ORO Integrated Cooperative to build the CDA **Registry of Members**
report from CIC SQL data plus hand-keyed information the core system does not hold.

```
SSMS: run cic_merged_registry_extract.sql
      ├─ result 1 "Members" ─► members.xlsx ─┐
      └─ result 2 "Loans"   ─► loans.xlsx  ──┤
                                             ▼
                     this app  ──  Imports ► fill gaps ► Export ► REGISTRY OF MEMBERS .xlsx
```

## Stack

Laravel 13 · Breeze (Blade) · MySQL · `maatwebsite/excel`. Matches the conventions of
`member_concern-redesign-new`.

## Setup (Laragon / Windows)

```bash
cd C:/laragon/www/member-registry
composer install
npm install && npm run build
cp .env.example .env          # already points at DB "member_registry"
php artisan key:generate
```

Create the database, then:

```bash
php artisan migrate --seed
```

Seeded login: **ict@orointegrated.coop / password** — change it after first login
(`php artisan app:make-user "Name" you@oro.coop`).

Serve at `http://member-registry.test` (Laragon) or `php artisan serve`.

## 1. Extract the data

`database/sql/cic_merged_registry_extract.sql` merges the two production scripts
(`SQL query for CIC ID FINAL.sql` + `SQLQuery8 query for CIC CI FINAL.sql`) and adds a
per-member loan roll-up. Run it in SSMS against the core-banking DB. It returns **two
result sets**:

| Result set | Grain | Export to | Import as |
|---|---|---|---|
| 1 — Members | one row per member | `members.xlsx` | **Members** |
| 2 — Loans | one row per loan account | `loans.xlsx` | **Loans** |

(Headers match the old `SQL CIC output.xlsx` / `SQL ID output.xlsx`, so those still work.)

## 2. Import

First add your branches on the **Branches** page (or `php artisan app:make-branch "Name"`).

**Imports** page → **pick the branch this file belongs to**, then upload the Members file,
then the Loans file. Repeat per branch.

- The selected branch is stamped on every imported member and loan, **overriding** the
  file's own Branch Code column.
- Members are upserted by **CID**.
- Every import **rewrites the CIC source columns** (name, gender, DOB, address, …).
- **Hand-keyed columns are never touched.** A few are *seeded once* while still blank:
  **TIN** (from the CIC NID field — ORO stores the TIN there), present address, sex, gender,
  civil status, contact number, email, and `date_accepted` (parsed from the `(D/A m/d/yy)`
  note inside the address — **best-effort, verify it**).
- After a Loans import the loan-portfolio roll-up on each member is recomputed.

## 3. Fill the gaps

**Members** page → filter by completion status → open a member. The form is grouped to
match the registry; the sidebar checklist shows what is still required. Dropdown choices
live in `config/registry.php`.

A member becomes **complete** once every field in `config('registry.required_for_complete')`
is filled.

## 4. Export

**Export Registry** (whole co-op, or pick a branch on the Dashboard / `?branch=` on the URL)
produces a workbook with four sheets:

1. **REGISTRY OF MEMBERS** — the exact CDA template (3-tier header, column order A→AS).
   `AGE` is a live formula against the as-of date on the Notes sheet.
2. **CI Contracts** — the imported loan rows.
3. **GAD REQUIRED REPORT** — members' contribution grid by sex.
4. **Notes** — field mapping, decode legend, assumptions, and the editable **as-of date**
   (`Notes!B3`) that drives every AGE.

Set a fixed as-of date with `REGISTRY_AS_OF_DATE=2026-09-30` in `.env` (otherwise "today").
The CIC provider code (`CO014030`) shows on the title block and Notes sheet; override with
`REGISTRY_PROVIDER_CODE=` in `.env`. The merged SQL script hard-codes the same value into the
`Provider Code` column of both result sets.

## Column mapping

See the **Notes** sheet of any export, or `app/Support/Registry.php` (`columns()`),
which is the single source of truth for the layout shared by the importer and the export.
