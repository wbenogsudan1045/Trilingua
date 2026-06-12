# Day 1 Fixes — Bugfix Design

## Overview

This document covers six Day 1 bugs and UI improvements for the Trilingua Laravel/PHP
translation application. The issues span three Blade views:

| # | View | Bug |
|---|------|-----|
| 1.1 | `dashboard.blade.php` | Useless "Confidence" column in Recent Translations table |
| 1.2 | `dashboard.blade.php` | Table rows are not clickable |
| 1.3 | `dashboard.blade.php` | "Browse Corpus" quick-action card links to `#` |
| 1.4 | `my-documents.blade.php` | Language direction not shown as a single label |
| 1.5 | `my-documents.blade.php` | Original uploaded file not stored or displayed |
| 1.6 | `history.blade.php` | Sort By dropdown does not reorder cards correctly |

The fix strategy is minimal and targeted: remove dead markup (1.1, 1.3), add missing
interactivity (1.2), improve a display label (1.4), extend the storage pipeline (1.5),
and correct a JavaScript timing/scope bug (1.6). No existing behaviour is altered beyond
the six defects described.

---

## Glossary

- **Bug_Condition (C)**: The set of inputs or states that trigger a defect.
- **Property (P)**: The correct observable behaviour that must hold after the fix.
- **Preservation**: Existing behaviour that must remain unchanged by the fix.
- **`applyAll()`**: The JS function in `history.blade.php` that applies search, group-by,
  and sort simultaneously.
- **`applySort(order)`**: The JS function that reorders `.history-card` elements within
  each `.history-group` section.
- **`translation_history`**: The Supabase/PostgreSQL table that records every translation
  job for a user.
- **`StorageService`**: `app/Services/StorageService.php` — wraps Supabase Storage upload
  and signed-URL generation.
- **`HistoryService`**: `app/Services/HistoryService.php` — wraps Supabase REST API for
  `translation_history` CRUD.
- **`TranslationController`**: `app/Http/Controllers/TranslationController.php` — handles
  `POST /translate` for both text and document modes.

---

## Bug Details

### Bug 1.1 — Confidence Column (Dashboard)

#### Bug Condition

The "Confidence" column is rendered unconditionally in the Recent Translations table even
though no confidence data is ever computed or stored.

```
FUNCTION isBugCondition_1_1(view)
  INPUT: rendered HTML of dashboard Recent Translations table
  OUTPUT: boolean

  RETURN view CONTAINS '<th>Confidence</th>'
         OR view CONTAINS '<td>—</td>'   // the placeholder cell
END FUNCTION
```

#### Examples

- User opens Dashboard → table header row shows: Document | Languages | Date | Status | **Confidence**
- Every data row ends with `<td>—</td>` — no real value is ever shown.
- Empty-state row uses `colspan="5"` — must be reduced to `colspan="4"` after fix.

---

### Bug 1.2 — Non-Clickable Table Rows (Dashboard)

#### Bug Condition

`<tr>` elements in the Recent Translations table have no `onclick` handler and no
`cursor: pointer` style, so clicking a row does nothing.

```
FUNCTION isBugCondition_1_2(row)
  INPUT: a <tr> element in the Recent Translations table
  OUTPUT: boolean

  RETURN row HAS NO onclick attribute
         AND row HAS NO style containing 'cursor:pointer'
END FUNCTION
```

#### Examples

- User clicks any row → browser does nothing; no navigation occurs.
- Expected: clicking a row navigates to `/history`.

---

### Bug 1.3 — Browse Corpus Button (Dashboard)

#### Bug Condition

The "Browse Corpus" quick-action card exists in the markup with `href="#"`, providing no
functional destination and cluttering the Quick Actions panel.

```
FUNCTION isBugCondition_1_3(view)
  INPUT: rendered HTML of dashboard Quick Actions panel
  OUTPUT: boolean

  RETURN view CONTAINS 'Browse Corpus'
END FUNCTION
```

#### Examples

- User clicks "Browse Corpus" → page scrolls to top (anchor `#`), no navigation.
- Expected: the card is absent entirely.

---

### Bug 1.4 — Missing Language Direction Label (My Documents)

#### Bug Condition

Document cards show only the source language badge and a separate "Target:" tag instead
of a combined "SourceLang → TargetLang" direction label.

```
FUNCTION isBugCondition_1_4(card, sourceLang, targetLang)
  INPUT: rendered doc-card HTML, source language string, target language string
  OUTPUT: boolean

  expectedLabel = sourceLang + ' → ' + targetLang
  RETURN card DOES NOT CONTAIN expectedLabel
END FUNCTION
```

#### Examples

- Card for a Cebuano→English document shows badge "Cebuano" and tag "English" separately.
- Expected: a single label "Cebuano → English" is visible on the card.

---

### Bug 1.5 — Original File Not Stored or Displayed (My Documents)

#### Bug Condition

When a document is uploaded for translation, only the translated output is uploaded to
Supabase Storage. The original file is discarded after translation and its path is never
recorded in `translation_history`.

```
FUNCTION isBugCondition_1_5(record)
  INPUT: a translation_history row for a document translation
  OUTPUT: boolean

  RETURN record['original_storage_path'] IS NULL
         OR record['original_storage_path'] = ''
END FUNCTION
```

#### Examples

- User uploads `report.docx` → translation succeeds → My Documents card shows only
  "Open" (translated file); no link to the original `report.docx`.
- `translation_history` row: `original_storage_path = null`, `original_signed_url_expires_at = null`.
- Expected: both the original and translated files are stored; the card shows a
  "Download Original" link.

---

### Bug 1.6 — Sort By Not Working (Saved Translations)

#### Bug Condition

`applySort()` iterates over each `.history-group` section and reorders cards **within**
that section only. When grouping is active (the default), all cards in a language-pair
group already share the same `data-lang` value, so "Language A–Z" produces no visible
change. Additionally, `applyAll()` is called synchronously at the end of the script
before the browser has finished painting, which can cause the initial sort to be
overridden by subsequent layout passes.

```
FUNCTION isBugCondition_1_6(cards, sortOrder)
  INPUT: array of .history-card elements, selected sort order string
  OUTPUT: boolean

  actualOrder   = cards.map(c => c.dataset.date OR c.dataset.lang)
  expectedOrder = sort(cards, comparatorFor(sortOrder))
                    .map(c => c.dataset.date OR c.dataset.lang)

  RETURN actualOrder ≠ expectedOrder
END FUNCTION
```

#### Examples

- Page loads with 6 cards across 2 language groups, default "Newest First".
  User selects "Oldest First" → cards remain in newest-first order.
- User selects "Language A–Z" → cards within each group are already the same language,
  so no reordering occurs across groups.
- Expected: selecting any sort option reorders **all** visible cards globally and
  consistently.

---

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**

- 3.1 Stat cards (Total Documents, Translations This Month, Words Translated) and the
  "New Translation" and "Upload Document" quick-action cards on the Dashboard must
  continue to render and function exactly as before.
- 3.2 The Document, Languages, Date, and Status columns in the Recent Translations table
  must continue to display their existing data and formatting.
- 3.3 My Documents search, language filter, status filter, tab switching, and grid/list
  view toggle must continue to work without regression.
- 3.4 The "Open" button on translated document cards must continue to call
  `/history/redownload/{id}` and redirect to the signed URL.
- 3.5 Saved Translations search filtering, group-by toggle, copy, download, share, and
  bookmark actions must continue to work without regression.
- 3.6 The document translation pipeline (translate → upload translated file to Supabase →
  return signed URL) must continue to work; the original-file upload is additive and
  must not break the existing flow if it fails.

**Scope:**
All inputs that do NOT involve the six bug conditions above must be completely unaffected
by this fix. This includes all mouse interactions, non-document translation flows, and
all other views (Settings, Translation page, Auth pages).

---

## Hypothesized Root Cause

### Bug 1.1 — Confidence Column

The column was added as a placeholder during initial scaffolding with the intention of
computing a confidence score from the translation API. The API integration never returned
a confidence value, so the column was never populated. The `<th>` and `<td>` were left
in the template without a guard condition.

### Bug 1.2 — Non-Clickable Rows

The `<tr>` elements were never given an `onclick` attribute or an event listener. The
table was built as a read-only display component; the navigation requirement was not
implemented. No CSS `cursor: pointer` rule exists for table rows.

### Bug 1.3 — Browse Corpus Button

The card was added as a future feature placeholder with `href="#"`. The corpus browsing
feature was never built, and the placeholder was not removed before launch.

### Bug 1.4 — Missing Language Direction Label

The Blade template computes `$langLabel` from `$doc['source_language']` only and renders
it as a badge. The target language is rendered separately in a `<div class="doc-card__targets">` block. No combined "Source → Target" label was ever constructed in the template.

### Bug 1.5 — Original File Not Stored

In `TranslationController::translate()`, the uploaded file (`$request->file('document')`)
is passed to `TranslationService::translateDocument()` which reads it and writes a
translated output file. The original file object is available via `$request->file()`
throughout the request lifecycle, but the controller never calls `StorageService::uploadFile()`
for the original — only for the translated output. Consequently:

1. The original file bytes are never uploaded to Supabase Storage.
2. No `original_storage_path` column exists in `translation_history`.
3. The My Documents view has no data to render a download link for the original.

The fix requires: (a) a new migration adding `original_storage_path` and
`original_signed_url_expires_at` columns, (b) uploading the original file in the
controller before it is consumed by the translation service, and (c) rendering the link
in the Blade template.

### Bug 1.6 — Sort By Not Working

Two compounding issues:

1. **Per-group sort scope**: `applySort()` calls `group.querySelector('.history-cards')`
   and sorts cards within that container. When the default "Group by Language Pair" mode
   is active, each group contains only cards with the same `data-lang` value. Sorting by
   "Language A–Z" within a group that is already homogeneous produces no visible change.
   For date sorts, cards within a single language-pair group may already be in the correct
   order by coincidence, masking the bug until a user has many records across one pair.

2. **Premature `applyAll()` call**: `applyAll()` is invoked synchronously at the bottom
   of the IIFE, before `DOMContentLoaded` has fully settled in some browser rendering
   paths. The more reliable fix is to wrap the initial call in
   `requestAnimationFrame(() => applyAll())` or move it inside a `DOMContentLoaded`
   listener, ensuring the DOM is stable before manipulation.

   The correct fix for the sort scope issue is to collect **all** cards across all groups,
   sort them globally, then re-insert them — either by flattening into a single container
   or by re-distributing them back into their groups in sorted order.

---

## Correctness Properties

Property 1: Bug Condition — Confidence Column Absent

_For any_ rendered Dashboard page, the Recent Translations table SHALL NOT contain a
`<th>` with text "Confidence" and SHALL NOT contain a `<td>` with the placeholder value
"—" in the fifth column position.

**Validates: Requirements 2.1**

---

Property 2: Bug Condition — Table Rows Are Clickable

_For any_ `<tr>` element in the Recent Translations table that corresponds to a
translation record, clicking that row SHALL navigate the browser to `/history`.

**Validates: Requirements 2.2**

---

Property 3: Bug Condition — Browse Corpus Card Absent

_For any_ rendered Dashboard page, the Quick Actions panel SHALL NOT contain any element
with the text "Browse Corpus".

**Validates: Requirements 2.3**

---

Property 4: Bug Condition — Language Direction Label

_For any_ document card rendered in My Documents with source language S and target
language T, the card SHALL contain the string "S → T" as a visible label.

**Validates: Requirements 2.4**

---

Property 5: Bug Condition — Original File Stored and Displayed

_For any_ document upload where the translation succeeds, the resulting
`translation_history` row SHALL have a non-null `original_storage_path`, and the
corresponding My Documents card SHALL display a download link for the original file.

**Validates: Requirements 2.5**

---

Property 6: Bug Condition — Sort Order Correctness

_For any_ list of history cards C and any sort order O ∈ {newest, oldest, lang-az},
after `applySort(O)` is called, the DOM order of all visible cards SHALL equal the
order produced by applying comparator(O) to C globally (not per-group).

**Validates: Requirements 2.6**

---

Property 7: Preservation — Existing Dashboard Elements Unchanged

_For any_ rendered Dashboard page after the fix, the stat cards, "New Translation"
quick-action, "Upload Document" quick-action, and the Document / Languages / Date /
Status columns SHALL continue to render with their existing data and formatting.

**Validates: Requirements 3.1, 3.2**

---

Property 8: Preservation — My Documents Filters and Actions Unchanged

_For any_ My Documents page state after the fix, search, language filter, status filter,
tab switching, grid/list toggle, and the "Open" redownload button SHALL continue to
function identically to their pre-fix behaviour.

**Validates: Requirements 3.3, 3.4**

---

Property 9: Preservation — Saved Translations Actions and Translation Pipeline Unchanged

_For any_ Saved Translations page state after the fix, search, group-by toggle, copy,
download, share, and bookmark actions SHALL continue to function identically. The
document translation pipeline (translate → upload translated file → return signed URL)
SHALL continue to succeed independently of whether the original-file upload succeeds.

**Validates: Requirements 3.5, 3.6**

---

## Fix Implementation

### Bug 1.1 — Remove Confidence Column

**File**: `resources/views/dashboard.blade.php`

**Specific Changes**:

1. **Remove `<th>` header**: Delete the line `<th>Confidence</th>` from the `<thead>` row.
2. **Remove `<td>` data cell**: Delete the line `<td>—</td>` from every `<tr>` inside
   `@forelse ($recentRecords as $record)`.
3. **Fix empty-state colspan**: Change `colspan="5"` to `colspan="4"` on the empty-state
   `<td>` so it still spans the correct number of columns.

No PHP or CSS changes required.

---

### Bug 1.2 — Make Table Rows Clickable

**File**: `resources/views/dashboard.blade.php`

**Specific Changes**:

1. **Add `onclick` and `style` to each `<tr>`**: Replace the plain `<tr class="border-top">`
   inside the `@forelse` loop with:
   ```html
   <tr class="border-top"
       style="cursor:pointer"
       onclick="window.location='/history'">
   ```
2. No PHP, CSS, or route changes required — `/history` already exists as a named route
   (`history.index`). Optionally use `{{ route('history.index') }}` for robustness.

---

### Bug 1.3 — Remove Browse Corpus Card

**File**: `resources/views/dashboard.blade.php`

**Specific Changes**:

1. **Delete the entire Browse Corpus `<a>` block** from the Quick Actions panel:
   ```html
   {{-- DELETE this block --}}
   <a href="#" class="quick-action-card">
       <span class="quick-action-card__icon">&#128218;</span>
       <span class="quick-action-card__label">Browse Corpus</span>
   </a>
   ```

No PHP or CSS changes required.

---

### Bug 1.4 — Language Direction Label

**File**: `resources/views/my-documents.blade.php`

**Specific Changes**:

1. **Add a direction label variable** in the `@php` block inside the `@foreach`:
   ```php
   $directionLabel = ($doc['source_language'] ?? '—') . ' → ' . ($doc['target_language'] ?? '—');
   ```
2. **Replace the separate badge + target block** with a single direction label. The
   existing `<div class="doc-card__meta-row">` currently shows only the source badge.
   Replace the contents of that row (and remove the separate `doc-card__targets` div) with:
   ```html
   <div class="doc-card__meta-row">
       <span class="lang-badge {{ $langClass }}">{{ $directionLabel }}</span>
   </div>
   ```
   Alternatively, keep the source badge and add the direction label as a second element
   in the same row — either approach satisfies requirement 2.4. The simpler approach
   (single badge showing the full direction) is preferred.
3. **Remove the `doc-card__targets` div** that previously showed "Target: English"
   separately, since the information is now in the direction label.

No PHP controller or CSS changes required (the existing badge colour classes still apply
based on source language).

---

### Bug 1.5 — Store and Display Original File

This is the most invasive fix. It touches the database schema, the storage pipeline, the
history service, the translation controller, and the My Documents view.

#### 1.5-A — Database Migration

**File**: `database/migrations/YYYY_MM_DD_000000_add_original_storage_to_translation_history.php`

Add two nullable columns to `translation_history`:

```php
Schema::table('translation_history', function (Blueprint $table) {
    $table->text('original_storage_path')->nullable()->after('storage_path');
    $table->timestampTz('original_signed_url_expires_at')->nullable()
          ->after('original_storage_path');
});
```

Also apply the equivalent `ALTER TABLE` directly in Supabase SQL editor (since Supabase
manages the live schema independently of Laravel migrations in this project).

#### 1.5-B — TranslationController

**File**: `app/Http/Controllers/TranslationController.php`

**Function**: `translate(Request $request)` — document branch

**Specific Changes**:

1. **Save the original file to a temp path before translation** (the `UploadedFile` object
   is consumed by `translateDocument`; capture the bytes first):
   ```php
   $uploadedFile        = $request->file('document');
   $originalTempPath    = $uploadedFile->getRealPath();
   $originalClientName  = $uploadedFile->getClientOriginalName();
   $originalStoragePath = Auth::id() . '/originals/' . basename($originalTempPath)
                          . '_original.' . $uploadedFile->getClientOriginalExtension();
   ```
2. **Upload the original file to Supabase Storage** (non-blocking — wrap in try/catch so
   a failure here does not abort the translation response):
   ```php
   $originalStorageResult = null;
   try {
       $originalStorageResult = $this->storage->uploadFile(
           $originalTempPath,
           $originalStoragePath
       );
   } catch (\Throwable $e) {
       Log::warning('Original file upload to Supabase failed', [
           'exception'    => $e->getMessage(),
           'storage_path' => $originalStoragePath,
       ]);
   }
   ```
   Note: `$originalTempPath` must be read **before** `translateDocument()` is called,
   because the service may move or consume the temp file. Alternatively, copy the file
   to a separate temp location first.

3. **Include original storage fields in the `insertRecord` call**:
   ```php
   $this->history->insertRecord([
       // ... existing fields ...
       'original_storage_path'          => $originalStorageResult['storage_path'] ?? null,
       'original_signed_url_expires_at' => $originalStorageResult['signed_url_expires_at'] ?? null,
   ]);
   ```

#### 1.5-C — HistoryService

**File**: `app/Services/HistoryService.php`

**Function**: `insertRecord(array $data)`

1. **Add the two new fields to the payload filter**:
   ```php
   'original_storage_path'          => $data['original_storage_path'] ?? null,
   'original_signed_url_expires_at' => $data['original_signed_url_expires_at'] ?? null,
   ```
2. **Add the two new fields to the `select` query string** in `getHistory()`:
   ```
   'select' => '...,original_storage_path,original_signed_url_expires_at',
   ```

#### 1.5-D — My Documents View

**File**: `resources/views/my-documents.blade.php`

1. **Add a "Download Original" button** in the `doc-card__actions` footer, conditional
   on `$doc['original_storage_path']` being non-empty:
   ```html
   @if (!empty($doc['original_storage_path']))
   <button class="doc-action-link redownload-btn redownload-btn--original"
           data-id="{{ $doc['id'] }}"
           data-type="original"
           data-filename="{{ $doc['original_filename'] ?? 'original' }}">
       Original
   </button>
   @endif
   ```
2. **Extend the JS redownload handler** to pass `data-type` to the endpoint, or add a
   separate endpoint `POST /history/redownload/{id}/original` that generates a signed URL
   for `original_storage_path`. The simpler approach is to add a `type` query parameter
   to the existing `HistoryController::redownload()` method.

#### 1.5-E — HistoryController (redownload endpoint)

**File**: `app/Http/Controllers/HistoryController.php`

Extend `redownload(int $id)` to accept an optional `type` parameter (`translated` or
`original`) and use `original_storage_path` when `type=original`.

---

### Bug 1.6 — Fix Sort By

**File**: `resources/views/history.blade.php` — inline `<script>` block

**Specific Changes**:

1. **Collect all cards globally** instead of per-group. Replace the current `applySort`
   implementation with one that:
   a. Gathers all `.history-card` elements from `#history-content` regardless of group.
   b. Sorts them by the selected comparator.
   c. Re-appends them to their respective `.history-cards` containers in sorted order
      (preserving group membership) — or, when "No Grouping" is active, appends them all
      to a single flat container.

   Simplified pseudocode for the new `applySort`:
   ```javascript
   function applySort(order) {
       var allCards = Array.from(contentEl.querySelectorAll('.history-card'));

       allCards.sort(function (a, b) {
           if (order === 'newest' || order === 'oldest') {
               var da = new Date(a.getAttribute('data-date') || 0);
               var db = new Date(b.getAttribute('data-date') || 0);
               return order === 'newest' ? db - da : da - db;
           }
           if (order === 'lang-az') {
               var la = (a.getAttribute('data-lang') || '').toLowerCase();
               var lb = (b.getAttribute('data-lang') || '').toLowerCase();
               return la < lb ? -1 : la > lb ? 1 : 0;
           }
           return 0;
       });

       // Re-insert in sorted order; each card's parentNode is its .history-cards grid
       allCards.forEach(function (card) {
           card.parentNode.appendChild(card);
       });

       // For lang-az, also reorder the group sections themselves
       if (order === 'lang-az') {
           var groups = Array.from(contentEl.querySelectorAll('.history-group'));
           groups.sort(function (a, b) {
               var la = (a.getAttribute('data-lang-pair') || '').toLowerCase();
               var lb = (b.getAttribute('data-lang-pair') || '').toLowerCase();
               return la < lb ? -1 : la > lb ? 1 : 0;
           });
           groups.forEach(function (g) { contentEl.appendChild(g); });
       }
   }
   ```

2. **Defer the initial `applyAll()` call** to ensure the DOM is fully settled:
   ```javascript
   // Replace the bare applyAll() call at the bottom of the IIFE with:
   requestAnimationFrame(function () { applyAll(); });
   ```

No PHP, CSS, or route changes required for this bug.

---

## Testing Strategy

### Validation Approach

The testing strategy follows the two-phase bug condition methodology:

1. **Exploratory phase** — write tests against the _unfixed_ code to confirm the bug
   manifests as described and to validate the root cause hypothesis.
2. **Fix + Preservation phase** — after applying the fix, run the same tests to confirm
   the bug is resolved, then run preservation tests to confirm no regressions.

Bugs 1.1, 1.2, 1.3 are pure markup changes verified by example-based tests.
Bug 1.4 is verified by a property over all language-pair combinations.
Bug 1.5 is verified by an integration test covering the full upload pipeline.
Bug 1.6 is verified by a property-based test over arbitrary card orderings.

---

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate each bug on unfixed code.

**Test Cases**:

1. **1.1 — Confidence header present** (will fail on unfixed code):
   Render the Dashboard with at least one record → assert `<th>Confidence</th>` exists.

2. **1.2 — Row click does nothing** (will fail on unfixed code):
   Render the Dashboard → simulate a click on a `<tr>` → assert no navigation occurred.

3. **1.3 — Browse Corpus present** (will fail on unfixed code):
   Render the Dashboard → assert an element with text "Browse Corpus" exists.

4. **1.4 — Direction label absent** (will fail on unfixed code):
   Render My Documents with a Cebuano→English record → assert "Cebuano → English" is
   absent from the card HTML.

5. **1.5 — original_storage_path null after upload** (will fail on unfixed code):
   POST `/translate` with a document file → fetch the resulting `translation_history`
   row → assert `original_storage_path IS NULL`.

6. **1.6 — Sort order incorrect** (will fail on unfixed code):
   Render Saved Translations with cards in a known order → trigger "Oldest First" →
   assert the DOM order does not match ascending date order.

**Expected Counterexamples**:
- Tests 1–4 and 6 fail because the markup/JS is unchanged.
- Test 5 fails because the controller never uploads the original file.

---

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed code
produces the expected behaviour.

**Pseudocode (generalised)**:
```
FOR ALL input WHERE isBugCondition_N(input) DO
  result := fixedCode(input)
  ASSERT property_N(result)
END FOR
```

**Test Cases** (run on fixed code):

1. **1.1** — Render Dashboard → assert no `<th>Confidence</th>` and no fifth `<td>` column.
2. **1.2** — Render Dashboard → click a `<tr>` → assert navigation to `/history`.
3. **1.3** — Render Dashboard → assert "Browse Corpus" element is absent.
4. **1.4** — For each (source, target) pair in {English, Cebuano, Filipino}² where
   source ≠ target, render a My Documents card → assert the card contains
   "source → target".
5. **1.5** — POST `/translate` with a document → assert `original_storage_path` is
   non-null in the DB row and the My Documents card renders a "Download Original" button.
6. **1.6** — For any permutation of N cards with distinct dates, trigger each sort option
   → assert the resulting DOM order matches the expected comparator output.

---

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed
code produces the same result as the original code.

**Pseudocode**:
```
FOR ALL input WHERE NOT isBugCondition_N(input) DO
  ASSERT originalCode(input) = fixedCode(input)
END FOR
```

**Test Cases**:

1. **Stat cards preserved** — Render Dashboard → assert Total Documents, Translations
   This Month, Words Translated cards are present with correct values.
2. **Remaining table columns preserved** — Render Dashboard with records → assert
   Document, Languages, Date, Status columns render correctly.
3. **"New Translation" and "Upload Document" cards preserved** — Render Dashboard →
   assert both quick-action cards are present with correct `href` values.
4. **My Documents filters preserved** — Render My Documents → exercise search, language
   filter, status filter, tab switching, grid/list toggle → assert each filter hides/shows
   the correct cards.
5. **Redownload endpoint preserved** — POST `/history/redownload/{id}` for a translated
   document → assert a signed URL is returned.
6. **Saved Translations actions preserved** — Render Saved Translations → exercise copy,
   download, share, bookmark, group-by toggle, search → assert each action behaves as
   before.
7. **Translation pipeline preserved** — POST `/translate` with a document → assert the
   translated file is still uploaded and a signed URL is returned even if the original
   file upload fails (simulate Supabase Storage failure for the original upload only).

---

### Unit Tests

- Render `dashboard.blade.php` with mock records; assert Confidence column absent (1.1).
- Render `dashboard.blade.php`; assert `<tr>` elements have `onclick` and `cursor:pointer` (1.2).
- Render `dashboard.blade.php`; assert Browse Corpus `<a>` absent (1.3).
- Render `my-documents.blade.php` with various language pairs; assert direction label present (1.4).
- Unit-test `applySort` JS function with a fixed array of mock card objects; assert
  output order for each of the three sort options (1.6).
- Unit-test `TranslationController::translate()` with a mocked `StorageService` that
  succeeds for both uploads; assert `insertRecord` is called with non-null
  `original_storage_path` (1.5).
- Unit-test `TranslationController::translate()` with a mocked `StorageService` that
  throws on the original upload; assert the translated-file response is still returned
  and `insertRecord` is called with `original_storage_path = null` (1.5 preservation).

---

### Property-Based Tests

- **Property 4 (Language Direction)**: Generate random pairs (source, target) from the
  supported language set; render the card template; assert the direction label equals
  `"$source → $target"` for all pairs.
- **Property 6 (Sort Correctness)**: Generate random arrays of N cards (N ∈ [1, 50])
  with random `data-date` and `data-lang` attributes; apply each sort option; assert the
  resulting order matches the reference sort for all generated inputs.
- **Property 9 (Pipeline Preservation)**: Generate random valid document filenames and
  language pairs; assert that `TranslationController::translate()` always returns a
  `download_url` regardless of whether the original-file upload succeeds or fails.

---

### Integration Tests

- **Full document upload flow (1.5)**: Upload a real `.docx` file via `POST /translate`
  in a test environment with Supabase Storage mocked; assert the DB row has both
  `storage_path` (translated) and `original_storage_path` (original) set; assert the
  My Documents page renders both "Open" and "Download Original" buttons.
- **Sort across page load (1.6)**: Load `/history` in a browser test (Dusk or similar);
  assert default order is newest-first; change dropdown to "Oldest First"; assert cards
  reorder globally; change to "Language A–Z"; assert group sections reorder.
- **Dashboard navigation (1.2)**: Load `/` in a browser test; click a Recent Translations
  row; assert the browser navigates to `/history`.
