# Bugfix Requirements Document

## Introduction

This document captures six Day 1 bugs and UI improvements for the Trilingua Laravel/PHP translation application. The issues span three views: the Dashboard (`dashboard.blade.php`), My Documents (`my-documents.blade.php`), and Saved Translations / History (`history.blade.php`). Together they affect usability, data visibility, and correct sorting behaviour.

---

## Bug Analysis

### Current Behavior (Defect)

**Dashboard – Confidence Level column**

1.1 WHEN a user views the Recent Translations table on the Dashboard THEN the system displays a "Confidence" column header and a "—" placeholder value for every row, providing no useful information.

**Dashboard – Non-clickable rows**

1.2 WHEN a user clicks on a row in the Recent Translations table THEN the system does nothing, preventing navigation to the translation's detail page.

**Dashboard – Browse Corpus button**

1.3 WHEN a user views the Quick Actions panel on the Dashboard THEN the system displays a "Browse Corpus" button that links to `#` and has no functional destination.

**My Documents – Missing language direction**

1.4 WHEN a user views a document card in My Documents THEN the system shows only the source language badge and a separate "Target:" tag, but does not display the full language direction (e.g., "Cebuano → English") as a single readable label.

**My Documents – Original documents not stored/displayed**

1.5 WHEN a user uploads a document for translation THEN the system stores only the translated output filename; the original uploaded document is not stored or made accessible from the My Documents view.

**Saved Translations – Sort By not working**

1.6 WHEN a user selects a sort option ("Newest First", "Oldest First", or "Language A–Z") from the Sort By dropdown in Saved Translations THEN the system does not reorder the displayed cards correctly because the initial `applyAll()` call runs before the DOM is fully settled and the sort logic operates on grouped sections independently, causing the overall list order to remain unchanged when switching between sort options after the page loads.

---

### Expected Behavior (Correct)

**Dashboard – Confidence Level column**

2.1 WHEN a user views the Recent Translations table on the Dashboard THEN the system SHALL display the table without a "Confidence" column — the `<th>Confidence</th>` header and the corresponding `<td>—</td>` data cell SHALL be removed from every row.

**Dashboard – Clickable rows**

2.2 WHEN a user clicks on a row in the Recent Translations table THEN the system SHALL navigate the user to the Saved Translations page (`/history`) filtered or scrolled to that specific record, OR to a dedicated translation detail route if one exists; at minimum, each `<tr>` SHALL be styled as clickable (cursor pointer) and SHALL redirect to `/history`.

**Dashboard – Browse Corpus button**

2.3 WHEN a user views the Quick Actions panel on the Dashboard THEN the system SHALL NOT display the "Browse Corpus" quick-action card; the card SHALL be removed entirely from the markup.

**My Documents – Language direction**

2.4 WHEN a user views a document card in My Documents THEN the system SHALL display the full language direction in the format "SourceLanguage → TargetLanguage" (e.g., "Cebuano → English") as a clearly visible label on the card, replacing or supplementing the separate source badge and target tag.

**My Documents – Original documents stored and displayed**

2.5 WHEN a user uploads a document for translation THEN the system SHALL store the original uploaded file in Supabase storage alongside the translated file, record its storage path and filename in the `translation_history` row, and display a download link or "Open" button for the original document on the My Documents card.

**Saved Translations – Sort By working correctly**

2.6 WHEN a user selects any sort option from the Sort By dropdown in Saved Translations THEN the system SHALL reorder all visible history cards consistently across the entire list according to the selected criterion (date descending, date ascending, or language pair A–Z), and the sort SHALL apply correctly on both initial page load and on every subsequent dropdown change.

---

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user views the Dashboard THEN the system SHALL CONTINUE TO display the stat cards (Total Documents, Translations This Month, Words Translated) and the Quick Actions for "New Translation" and "Upload Document" without change.

3.2 WHEN a user views the Recent Translations table on the Dashboard THEN the system SHALL CONTINUE TO display the Document, Languages, Date, and Status columns with their existing data and formatting.

3.3 WHEN a user views My Documents THEN the system SHALL CONTINUE TO support search, language filter, status filter, tab switching (All / Recent / Shared / Archived), and grid/list view toggle without regression.

3.4 WHEN a user clicks the "Open" button on a translated document card in My Documents THEN the system SHALL CONTINUE TO generate a signed download URL via the `/history/redownload/{id}` endpoint and redirect the user to that URL.

3.5 WHEN a user views Saved Translations THEN the system SHALL CONTINUE TO support search filtering, group-by language pair toggle, copy, download, share, and bookmark actions on individual cards without regression.

3.6 WHEN a user uploads a document for translation THEN the system SHALL CONTINUE TO translate the document, store the translated file in Supabase, and return a signed download URL to the user on the translation page.
