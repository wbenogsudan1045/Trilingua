# Implementation Plan

## Overview

Six Day 1 bugs across three Blade views are fixed using the exploratory bugfix workflow:
write bug condition tests first (they fail on unfixed code), write preservation tests
(they pass on unfixed code), apply each fix, then verify both test sets pass.

## Task Dependency Graph

```json
{
  "waves": [
    { "wave": 1, "tasks": ["1", "2"] },
    { "wave": 2, "tasks": ["3", "4", "5", "6", "7", "8"] },
    { "wave": 3, "tasks": ["9"] }
  ]
}
```

## Tasks

- [x] 1. Write bug condition exploration tests (BEFORE implementing any fix)
  - **Property 1: Bug Condition** - Six Day 1 Defects Present in Unfixed Code
  - **CRITICAL**: These tests MUST FAIL on unfixed code — failure confirms each bug exists
  - **DO NOT attempt to fix the tests or the code when they fail**
  - **NOTE**: These tests encode the expected behavior — they will validate the fixes when they pass after implementation
  - **GOAL**: Surface counterexamples that demonstrate each bug exists
  - **Scoped PBT Approach**: For deterministic markup bugs (1.1, 1.2, 1.3, 1.4), scope to concrete failing cases; for property bugs (1.4, 1.6), generate inputs from the relevant domain
  - **Bug 1.1 — Confidence Column**: Render `dashboard.blade.php` with ≥1 mock record; assert `<th>Confidence</th>` IS present (confirms bug) and assert `<td>—</td>` fifth-column cell IS present
  - **Bug 1.2 — Non-Clickable Rows**: Render `dashboard.blade.php`; assert `<tr>` elements inside the `@forelse` loop have NO `onclick` attribute and NO `cursor:pointer` style (confirms bug)
  - **Bug 1.3 — Browse Corpus**: Render `dashboard.blade.php`; assert an element with text "Browse Corpus" IS present in the Quick Actions panel (confirms bug)
  - **Bug 1.4 — Missing Direction Label**: For each (source, target) pair in {English, Cebuano, Filipino}² where source ≠ target, render a `my-documents.blade.php` card; assert the string "source → target" is ABSENT from the card HTML (confirms bug)
  - **Bug 1.5 — Original File Not Stored**: POST `/translate` with a document file on unfixed code; fetch the resulting `translation_history` row; assert `original_storage_path IS NULL` (confirms bug)
  - **Bug 1.6 — Sort Order Incorrect**: Render `history.blade.php` with cards in a known order across ≥2 language groups; trigger "Oldest First" via the dropdown; assert the DOM order does NOT match ascending date order (confirms bug); also trigger "Language A–Z" and assert group sections are NOT reordered
  - Run all tests on UNFIXED code
  - **EXPECTED OUTCOME**: All tests FAIL (this is correct — it proves each bug exists)
  - Document counterexamples found (e.g., "Confidence `<th>` found at column 5", "`<tr>` has no onclick", "Browse Corpus `<a>` present", "Cebuano → English label absent", "`original_storage_path` is null", "cards remain in newest-first order after selecting Oldest First")
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

- [x] 2. Write preservation property tests (BEFORE implementing any fix)
  - **Property 2: Preservation** - Existing Dashboard, My Documents, and Saved Translations Behavior Unchanged
  - **IMPORTANT**: Follow observation-first methodology — run UNFIXED code with non-buggy inputs, observe outputs, then encode those outputs as property-based tests
  - **Observe on UNFIXED code**:
    - Render Dashboard → stat cards (Total Documents, Translations This Month, Words Translated) render correctly; "New Translation" and "Upload Document" quick-action cards are present with correct `href` values; Document, Languages, Date, Status columns render with existing data
    - Render My Documents → search, language filter, status filter, tab switching (All / Recent / Shared / Archived), and grid/list view toggle all function correctly; "Open" redownload button calls `/history/redownload/{id}` and returns a signed URL
    - Render Saved Translations → search filtering, group-by toggle, copy, download, share, and bookmark actions all function correctly
    - POST `/translate` with a document → translated file is uploaded to Supabase and a signed URL is returned
  - **Write property-based tests capturing observed behavior**:
    - Property: for any Dashboard render, stat cards and the two remaining quick-action cards are always present (from Preservation Requirements 3.1)
    - Property: for any Dashboard render with records, Document / Languages / Date / Status columns always render with correct data (from Preservation Requirements 3.2)
    - Property: for any My Documents state, all filter and toggle interactions produce the correct card visibility (from Preservation Requirements 3.3)
    - Property: for any translated document id, `/history/redownload/{id}` always returns a signed URL redirect (from Preservation Requirements 3.4)
    - Property: for any Saved Translations state, copy/download/share/bookmark/group-by/search actions always behave identically to pre-fix behavior (from Preservation Requirements 3.5)
    - Property: for any valid document upload, `TranslationController::translate()` always returns a `download_url` regardless of whether the original-file upload succeeds or fails (from Preservation Requirements 3.6)
  - Run all preservation tests on UNFIXED code
  - **EXPECTED OUTCOME**: All tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 3. Fix Bug 1.1 — Remove Confidence Column from Dashboard

  - [x] 3.1 Remove Confidence column markup from `resources/views/dashboard.blade.php`
    - Delete the `<th>Confidence</th>` line from the `<thead>` row
    - Delete the `<td>—</td>` data cell from every `<tr>` inside the `@forelse ($recentRecords as $record)` loop
    - Change `colspan="5"` to `colspan="4"` on the empty-state `<td>` so it spans the correct number of columns
    - No PHP, CSS, or route changes required
    - _Bug_Condition: isBugCondition_1_1(view) — view CONTAINS `<th>Confidence</th>` OR `<td>—</td>` in fifth column_
    - _Expected_Behavior: rendered Dashboard table has no Confidence `<th>` and no fifth `<td>` column; empty-state colspan is 4_
    - _Preservation: Document, Languages, Date, Status columns continue to render with existing data and formatting (3.2)_
    - _Requirements: 2.1, 3.2_

  - [x] 3.2 Verify Bug 1.1 exploration test now passes
    - **Property 1: Expected Behavior** - Confidence Column Absent
    - **IMPORTANT**: Re-run the SAME test from task 1 for Bug 1.1 — do NOT write a new test
    - The test from task 1 encodes the expected behavior (no `<th>Confidence</th>`, no fifth `<td>`)
    - Run bug condition exploration test for 1.1 on FIXED code
    - **EXPECTED OUTCOME**: Test PASSES (confirms Confidence column is removed)
    - _Requirements: 2.1_

  - [x] 3.3 Verify preservation tests still pass after Bug 1.1 fix
    - **Property 2: Preservation** - Dashboard Columns and Quick Actions Unchanged
    - **IMPORTANT**: Re-run the SAME preservation tests from task 2 — do NOT write new tests
    - Confirm Document / Languages / Date / Status columns still render correctly
    - Confirm stat cards and remaining quick-action cards are still present
    - **EXPECTED OUTCOME**: Preservation tests PASS (no regressions)
    - _Requirements: 3.1, 3.2_

- [x] 4. Fix Bug 1.2 — Make Recent Translations Table Rows Clickable

  - [x] 4.1 Add `onclick` and `cursor:pointer` to `<tr>` elements in `resources/views/dashboard.blade.php`
    - Replace the plain `<tr class="border-top">` inside the `@forelse` loop with:
      `<tr class="border-top" style="cursor:pointer" onclick="window.location='{{ route('history.index') }}'">` (use named route for robustness)
    - No PHP, CSS file, or route changes required — `/history` already exists as `history.index`
    - _Bug_Condition: isBugCondition_1_2(row) — row HAS NO onclick attribute AND HAS NO style containing 'cursor:pointer'_
    - _Expected_Behavior: clicking any `<tr>` in the Recent Translations table navigates the browser to `/history`_
    - _Preservation: Document, Languages, Date, Status column data and formatting unchanged (3.2); stat cards and other quick-action cards unchanged (3.1)_
    - _Requirements: 2.2, 3.1, 3.2_

  - [x] 4.2 Verify Bug 1.2 exploration test now passes
    - **Property 1: Expected Behavior** - Table Rows Are Clickable
    - **IMPORTANT**: Re-run the SAME test from task 1 for Bug 1.2 — do NOT write a new test
    - Run bug condition exploration test for 1.2 on FIXED code
    - **EXPECTED OUTCOME**: Test PASSES (confirms `<tr>` elements have `onclick` and `cursor:pointer`)
    - _Requirements: 2.2_

  - [x] 4.3 Verify preservation tests still pass after Bug 1.2 fix
    - **Property 2: Preservation** - Dashboard Columns and Quick Actions Unchanged
    - **IMPORTANT**: Re-run the SAME preservation tests from task 2 — do NOT write new tests
    - **EXPECTED OUTCOME**: Preservation tests PASS (no regressions)
    - _Requirements: 3.1, 3.2_

- [x] 5. Fix Bug 1.3 — Remove Browse Corpus Quick-Action Card

  - [x] 5.1 Delete the Browse Corpus `<a>` block from `resources/views/dashboard.blade.php`
    - Remove the entire `<a href="#" class="quick-action-card">` block containing the "Browse Corpus" label from the Quick Actions panel
    - No PHP, CSS, or route changes required
    - _Bug_Condition: isBugCondition_1_3(view) — view CONTAINS 'Browse Corpus'_
    - _Expected_Behavior: rendered Dashboard Quick Actions panel contains no element with text "Browse Corpus"_
    - _Preservation: "New Translation" and "Upload Document" quick-action cards remain present with correct `href` values (3.1)_
    - _Requirements: 2.3, 3.1_

  - [x] 5.2 Verify Bug 1.3 exploration test now passes
    - **Property 1: Expected Behavior** - Browse Corpus Card Absent
    - **IMPORTANT**: Re-run the SAME test from task 1 for Bug 1.3 — do NOT write a new test
    - Run bug condition exploration test for 1.3 on FIXED code
    - **EXPECTED OUTCOME**: Test PASSES (confirms "Browse Corpus" element is absent)
    - _Requirements: 2.3_

  - [x] 5.3 Verify preservation tests still pass after Bug 1.3 fix
    - **Property 2: Preservation** - Remaining Quick Actions Unchanged
    - **IMPORTANT**: Re-run the SAME preservation tests from task 2 — do NOT write new tests
    - Confirm "New Translation" and "Upload Document" cards are still present
    - **EXPECTED OUTCOME**: Preservation tests PASS (no regressions)
    - _Requirements: 3.1_

- [x] 6. Fix Bug 1.4 — Display Full Language Direction Label in My Documents

  - [x] 6.1 Add direction label to `resources/views/my-documents.blade.php`
    - In the `@php` block inside the `@foreach` loop, add: `$directionLabel = ($doc['source_language'] ?? '—') . ' → ' . ($doc['target_language'] ?? '—');`
    - Replace the contents of `<div class="doc-card__meta-row">` with a single badge showing `$directionLabel`: `<span class="lang-badge {{ $langClass }}">{{ $directionLabel }}</span>`
    - Remove the separate `<div class="doc-card__targets">` block that previously showed "Target: English" independently
    - No PHP controller or CSS changes required (existing badge colour classes still apply based on source language)
    - _Bug_Condition: isBugCondition_1_4(card, sourceLang, targetLang) — card DOES NOT CONTAIN sourceLang + ' → ' + targetLang_
    - _Expected_Behavior: for any document card with source S and target T, the card contains the string "S → T" as a visible label_
    - _Preservation: search, language filter, status filter, tab switching, grid/list toggle, and "Open" redownload button continue to function (3.3, 3.4)_
    - _Requirements: 2.4, 3.3, 3.4_

  - [x] 6.2 Verify Bug 1.4 exploration test now passes
    - **Property 1: Expected Behavior** - Language Direction Label Present
    - **IMPORTANT**: Re-run the SAME test from task 1 for Bug 1.4 — do NOT write a new test
    - For each (source, target) pair in {English, Cebuano, Filipino}² where source ≠ target, assert the card now CONTAINS "source → target"
    - Run bug condition exploration test for 1.4 on FIXED code
    - **EXPECTED OUTCOME**: Test PASSES for all language-pair combinations
    - _Requirements: 2.4_

  - [x] 6.3 Verify preservation tests still pass after Bug 1.4 fix
    - **Property 2: Preservation** - My Documents Filters and Actions Unchanged
    - **IMPORTANT**: Re-run the SAME preservation tests from task 2 — do NOT write new tests
    - **EXPECTED OUTCOME**: Preservation tests PASS (no regressions)
    - _Requirements: 3.3, 3.4_

- [x] 7. Fix Bug 1.5 — Store and Display Original Uploaded File

  - [x] 7.1 Create database migration adding `original_storage_path` and `original_signed_url_expires_at` columns
    - Create `database/migrations/YYYY_MM_DD_000000_add_original_storage_to_translation_history.php`
    - Add `$table->text('original_storage_path')->nullable()->after('storage_path');`
    - Add `$table->timestampTz('original_signed_url_expires_at')->nullable()->after('original_storage_path');`
    - Also apply the equivalent `ALTER TABLE` directly in the Supabase SQL editor (Supabase manages the live schema independently)
    - Run `php artisan migrate` to apply locally
    - _Requirements: 2.5_

  - [x] 7.2 Update `TranslationController::translate()` to upload the original file
    - **File**: `app/Http/Controllers/TranslationController.php`
    - Capture `$uploadedFile->getRealPath()` and `$uploadedFile->getClientOriginalName()` BEFORE calling `translateDocument()` (the service may consume the temp file)
    - Construct `$originalStoragePath` as `Auth::id() . '/originals/' . basename($originalTempPath) . '_original.' . $uploadedFile->getClientOriginalExtension()`
    - Wrap the `$this->storage->uploadFile($originalTempPath, $originalStoragePath)` call in a try/catch so a failure does NOT abort the translation response; log a warning on failure
    - Pass `'original_storage_path' => $originalStorageResult['storage_path'] ?? null` and `'original_signed_url_expires_at' => $originalStorageResult['signed_url_expires_at'] ?? null` to the `insertRecord` call
    - _Bug_Condition: isBugCondition_1_5(record) — record['original_storage_path'] IS NULL_
    - _Expected_Behavior: after a successful document upload, the `translation_history` row has a non-null `original_storage_path`_
    - _Preservation: translated file is still uploaded and a signed URL is still returned even if the original-file upload fails (3.6)_
    - _Requirements: 2.5, 3.6_

  - [x] 7.3 Update `HistoryService` to persist and retrieve the new columns
    - **File**: `app/Services/HistoryService.php`
    - In `insertRecord(array $data)`, add `'original_storage_path' => $data['original_storage_path'] ?? null` and `'original_signed_url_expires_at' => $data['original_signed_url_expires_at'] ?? null` to the payload
    - In `getHistory()`, add `original_storage_path` and `original_signed_url_expires_at` to the `select` query string
    - _Requirements: 2.5_

  - [x] 7.4 Add "Download Original" button to `resources/views/my-documents.blade.php`
    - Inside `doc-card__actions`, add a conditional button: `@if (!empty($doc['original_storage_path'])) <button class="doc-action-link redownload-btn redownload-btn--original" data-id="{{ $doc['id'] }}" data-type="original" data-filename="{{ $doc['original_filename'] ?? 'original' }}">Original</button> @endif`
    - _Requirements: 2.5_

  - [x] 7.5 Extend `HistoryController::redownload()` to support original file download
    - **File**: `app/Http/Controllers/HistoryController.php`
    - Accept an optional `type` query parameter (`translated` or `original`); when `type=original`, generate a signed URL from `original_storage_path` instead of `storage_path`
    - _Preservation: existing "Open" button behavior for translated files is unchanged (3.4)_
    - _Requirements: 2.5, 3.4_

  - [x] 7.6 Verify Bug 1.5 exploration test now passes
    - **Property 1: Expected Behavior** - Original File Stored and Displayed
    - **IMPORTANT**: Re-run the SAME test from task 1 for Bug 1.5 — do NOT write a new test
    - POST `/translate` with a document file on FIXED code; assert `original_storage_path` is non-null in the DB row; assert the My Documents card renders a "Download Original" button
    - Run bug condition exploration test for 1.5 on FIXED code
    - **EXPECTED OUTCOME**: Test PASSES (confirms original file is stored and displayed)
    - _Requirements: 2.5_

  - [x] 7.7 Verify preservation tests still pass after Bug 1.5 fix
    - **Property 2: Preservation** - Translation Pipeline and Redownload Endpoint Unchanged
    - **IMPORTANT**: Re-run the SAME preservation tests from task 2 — do NOT write new tests
    - Simulate Supabase Storage failure for the original upload only; assert translated-file response is still returned and `insertRecord` is called with `original_storage_path = null`
    - Confirm `/history/redownload/{id}` for translated documents still returns a signed URL
    - **EXPECTED OUTCOME**: Preservation tests PASS (no regressions)
    - _Requirements: 3.4, 3.6_

- [x] 8. Fix Bug 1.6 — Fix Sort By Dropdown in Saved Translations

  - [x] 8.1 Rewrite `applySort()` to sort all cards globally in `resources/views/history.blade.php`
    - Replace the current per-group `applySort` implementation with a global one:
      - Gather all `.history-card` elements from `#history-content` regardless of group using `contentEl.querySelectorAll('.history-card')`
      - Sort them by the selected comparator (newest: `db - da`; oldest: `da - db`; lang-az: locale string compare on `data-lang`)
      - Re-append each card to its own `card.parentNode` in sorted order (preserves group membership)
      - For `lang-az`, additionally sort and re-append the `.history-group` section elements themselves by their `data-lang-pair` attribute
    - _Bug_Condition: isBugCondition_1_6(cards, sortOrder) — actualOrder ≠ expectedOrder after applySort(sortOrder)_
    - _Expected_Behavior: for any list of history cards C and any sort order O ∈ {newest, oldest, lang-az}, DOM order of all visible cards equals comparator(O) applied globally_
    - _Preservation: search filtering, group-by toggle, copy, download, share, and bookmark actions continue to function (3.5)_
    - _Requirements: 2.6, 3.5_

  - [x] 8.2 Defer the initial `applyAll()` call with `requestAnimationFrame`
    - Replace the bare `applyAll()` call at the bottom of the IIFE with `requestAnimationFrame(function () { applyAll(); });`
    - This ensures the DOM is fully settled before the initial sort is applied, preventing the premature-call timing bug
    - No PHP, CSS, or route changes required
    - _Requirements: 2.6_

  - [x] 8.3 Verify Bug 1.6 exploration test now passes
    - **Property 1: Expected Behavior** - Sort Order Correctness
    - **IMPORTANT**: Re-run the SAME test from task 1 for Bug 1.6 — do NOT write a new test
    - Render Saved Translations with cards in a known order across ≥2 language groups; trigger each sort option; assert the resulting DOM order matches the expected comparator output globally
    - Run bug condition exploration test for 1.6 on FIXED code
    - **EXPECTED OUTCOME**: Test PASSES for all three sort options (newest, oldest, lang-az)
    - _Requirements: 2.6_

  - [x] 8.4 Verify preservation tests still pass after Bug 1.6 fix
    - **Property 2: Preservation** - Saved Translations Actions Unchanged
    - **IMPORTANT**: Re-run the SAME preservation tests from task 2 — do NOT write new tests
    - Exercise search, group-by toggle, copy, download, share, and bookmark actions after the fix
    - **EXPECTED OUTCOME**: Preservation tests PASS (no regressions)
    - _Requirements: 3.5_

- [x] 9. Checkpoint — Ensure all tests pass
  - Re-run the full test suite (unit tests, property-based tests, and integration tests)
  - Confirm all six bug condition exploration tests now PASS (bugs are fixed)
  - Confirm all preservation property tests still PASS (no regressions introduced)
  - Confirm the translation pipeline integration test passes (original-file upload is additive and does not break the existing flow)
  - Ensure all tests pass; ask the user if any questions arise

## Notes

- Tasks 1 and 2 (exploration and preservation tests) MUST be written and run on unfixed code before any fix is applied.
- Fixes in tasks 3–8 are independent of each other and can be applied in any order after tasks 1 and 2 are complete.
- Bug 1.5 (task 7) is the most invasive fix — it touches the DB schema, controller, service, and view. Run `php artisan migrate` after creating the migration.
- For Bug 1.5, the original-file upload is wrapped in try/catch so a Supabase Storage failure never aborts the translation response.
- For Bug 1.6, the `requestAnimationFrame` deferral (task 8.2) addresses the timing issue; the global sort rewrite (task 8.1) addresses the per-group scope issue — both sub-tasks are required.
- All property-based tests use the **Property N:** format to enable hover status in the task list.
