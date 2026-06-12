<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HistoryService;
use App\Services\StorageService;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug Condition Exploration Tests — Day 1 Fixes
 *
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5, 1.6**
 *
 * Property 1: Bug Condition — Six Day 1 Defects Present in Unfixed Code
 *
 * CRITICAL: These tests MUST FAIL on unfixed code — failure confirms each bug exists.
 * DO NOT attempt to fix the tests or the code when they fail.
 *
 * These tests encode the EXPECTED (post-fix) behavior. When they pass after the
 * fixes are implemented (Tasks 3–8), they confirm each bug is resolved.
 *
 * GOAL: Surface counterexamples that demonstrate each bug exists.
 *
 * Expected counterexamples on unfixed code:
 *   1.1 — <th>Confidence</th> found at column 5; <td>—</td> present in every row
 *   1.2 — <tr> elements have no onclick attribute and no cursor:pointer style
 *   1.3 — "Browse Corpus" <a> element present in Quick Actions panel
 *   1.4 — "Cebuano → English" direction label absent from My Documents card
 *   1.5 — original_storage_path is null in translation_history row after upload
 *   1.6 — cards remain in newest-first order after selecting "Oldest First"
 */
class Day1BugConditionExplorationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Build a fake document-type translation_history record. */
    private function makeDocumentRecord(
        string $sourceLang = 'English',
        string $targetLang = 'Cebuano',
        string $createdAt = null,
        int $id = null
    ): array {
        return [
            'id'                    => $id ?? random_int(1, 99999),
            'user_id'               => 1,
            'translation_type'      => 'document',
            'original_filename'     => 'test-doc.docx',
            'translated_filename'   => 'test-doc-translated.docx',
            'source_language'       => $sourceLang,
            'target_language'       => $targetLang,
            'created_at'            => $createdAt ?? now()->toIso8601String(),
            'storage_path'          => 'documents/test-doc-translated.docx',
            'signed_url_expires_at' => now()->addHour()->toIso8601String(),
            'source_text'           => null,
            'translated_text'       => null,
        ];
    }

    // ─── Bug 1.1 — Confidence Column Present ─────────────────────────────────

    /**
     * Bug 1.1 — Confidence column IS present in unfixed dashboard
     *
     * Render dashboard.blade.php with ≥1 mock record; assert <th>Confidence</th>
     * IS present (confirms bug) and assert <td>—</td> fifth-column cell IS present.
     *
     * Bug Condition: isBugCondition_1_1(view) — view CONTAINS <th>Confidence</th>
     *   OR <td>—</td> in fifth column.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: FAIL
     * Counterexample: <th>Confidence</th> found at column 5; <td>—</td> present
     *   in every data row. The fix must remove both.
     */
    public function test_bug_1_1_confidence_column_is_present_on_unfixed_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $records = [
            $this->makeDocumentRecord('English', 'Cebuano', now()->subDays(1)->toIso8601String(), 1),
            $this->makeDocumentRecord('English', 'Filipino', now()->subDays(2)->toIso8601String(), 2),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $content = $response->getContent();

        // On FIXED code: <th>Confidence</th> is ABSENT — this assertion FAILS on unfixed code
        $this->assertStringNotContainsString(
            '<th>Confidence</th>',
            $content,
            'BUG 1.1 CONFIRMED: <th>Confidence</th> is present in the dashboard table header. ' .
            'Counterexample: Confidence <th> found at column 5. The fix must remove this header.'
        );

        // On FIXED code: <td>—</td> placeholder is ABSENT — this assertion FAILS on unfixed code
        $this->assertStringNotContainsString(
            '<td>—</td>',
            $content,
            'BUG 1.1 CONFIRMED: <td>—</td> placeholder cell is present in every data row. ' .
            'Counterexample: fifth <td> column contains "—" for every record. The fix must remove it.'
        );
    }

    // ─── Bug 1.2 — Non-Clickable Table Rows ──────────────────────────────────

    /**
     * Bug 1.2 — <tr> elements have NO onclick and NO cursor:pointer on unfixed dashboard
     *
     * Render dashboard.blade.php; assert <tr> elements inside the @forelse loop
     * have NO onclick attribute and NO cursor:pointer style (confirms bug).
     *
     * Bug Condition: isBugCondition_1_2(row) — row HAS NO onclick attribute
     *   AND row HAS NO style containing 'cursor:pointer'.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: FAIL
     * Counterexample: <tr class="border-top"> has no onclick and no cursor:pointer.
     *   The fix must add onclick="window.location='...'" and style="cursor:pointer".
     */
    public function test_bug_1_2_table_rows_have_no_onclick_on_unfixed_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $records = [
            $this->makeDocumentRecord('English', 'Cebuano', now()->subDays(1)->toIso8601String(), 1),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $content = $response->getContent();

        // On FIXED code: onclick IS present on <tr> — this assertion FAILS on unfixed code
        $this->assertStringContainsString(
            'onclick',
            $content,
            'BUG 1.2 CONFIRMED: <tr> elements in the Recent Translations table have no onclick ' .
            'attribute. Counterexample: <tr class="border-top"> has no onclick handler. ' .
            'The fix must add onclick="window.location=\'/history\'" to each <tr>.'
        );

        // On FIXED code: cursor:pointer IS present on <tr> — this assertion FAILS on unfixed code
        $this->assertStringContainsString(
            'cursor:pointer',
            $content,
            'BUG 1.2 CONFIRMED: <tr> elements have no cursor:pointer style. ' .
            'Counterexample: <tr class="border-top"> has no style="cursor:pointer". ' .
            'The fix must add style="cursor:pointer" to each <tr>.'
        );
    }

    // ─── Bug 1.3 — Browse Corpus Present ─────────────────────────────────────

    /**
     * Bug 1.3 — "Browse Corpus" IS present in unfixed dashboard Quick Actions
     *
     * Render dashboard.blade.php; assert an element with text "Browse Corpus"
     * IS present in the Quick Actions panel (confirms bug).
     *
     * Bug Condition: isBugCondition_1_3(view) — view CONTAINS 'Browse Corpus'.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: FAIL
     * Counterexample: "Browse Corpus" <a> element present with href="#" in Quick Actions.
     *   The fix must remove the entire Browse Corpus card from the markup.
     */
    public function test_bug_1_3_browse_corpus_is_present_on_unfixed_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mock(HistoryService::class, function ($mock) {
            $mock->shouldReceive('getHistory')->andReturn([]);
        });

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $content = $response->getContent();

        // On FIXED code: "Browse Corpus" is ABSENT — this assertion FAILS on unfixed code
        $this->assertStringNotContainsString(
            'Browse Corpus',
            $content,
            'BUG 1.3 CONFIRMED: "Browse Corpus" element is present in the Quick Actions panel. ' .
            'Counterexample: <a href="#" class="quick-action-card"> with label "Browse Corpus" found. ' .
            'The fix must remove the entire Browse Corpus card from the markup.'
        );
    }

    // ─── Bug 1.4 — Missing Language Direction Label ───────────────────────────

    /**
     * Bug 1.4 — Direction label "source → target" is ABSENT from unfixed My Documents cards
     *
     * For each (source, target) pair in {English, Cebuano, Filipino}² where source ≠ target,
     * render a my-documents.blade.php card; assert the string "source → target" is ABSENT
     * from the card HTML (confirms bug).
     *
     * Bug Condition: isBugCondition_1_4(card, sourceLang, targetLang) — card DOES NOT
     *   CONTAIN sourceLang + ' → ' + targetLang.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: FAIL
     * Counterexample: Card for Cebuano→English shows badge "Cebuano" and tag "English"
     *   separately; "Cebuano → English" direction label is absent.
     *   The fix must add a combined "S → T" label to every card.
     *
     * @dataProvider languagePairProvider
     */
    public function test_bug_1_4_direction_label_absent_from_unfixed_my_documents_card(
        string $sourceLang,
        string $targetLang
    ): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $record = $this->makeDocumentRecord($sourceLang, $targetLang, now()->toIso8601String(), 1);

        $this->mock(HistoryService::class, function ($mock) use ($record) {
            $mock->shouldReceive('getHistory')->andReturn([$record]);
        });

        $response = $this->get('/documents');
        $response->assertStatus(200);
        $content = $response->getContent();

        $expectedLabel = $sourceLang . ' → ' . $targetLang;

        // On FIXED code: direction label IS present — this assertion FAILS on unfixed code
        $this->assertStringContainsString(
            $expectedLabel,
            $content,
            "BUG 1.4 CONFIRMED: Direction label \"{$expectedLabel}\" is absent from the My Documents card. " .
            "Counterexample: Card shows source badge \"{$sourceLang}\" and target tag \"{$targetLang}\" " .
            "separately, but the combined \"{$expectedLabel}\" label is missing. " .
            'The fix must add a single "S → T" direction label to every card.'
        );
    }

    /** All (source, target) pairs in {English, Cebuano, Filipino}² where source ≠ target. */
    public static function languagePairProvider(): array
    {
        $languages = ['English', 'Cebuano', 'Filipino'];
        $pairs = [];
        foreach ($languages as $source) {
            foreach ($languages as $target) {
                if ($source !== $target) {
                    $pairs["{$source} → {$target}"] = [$source, $target];
                }
            }
        }
        return $pairs;
    }

    // ─── Bug 1.5 — Original File Not Stored ──────────────────────────────────

    /**
     * Bug 1.5 — original_storage_path IS NULL after document upload on unfixed code
     *
     * POST /translate with a document file on unfixed code; capture the payload
     * passed to HistoryService::insertRecord; assert original_storage_path IS NULL
     * (confirms bug).
     *
     * Bug Condition: isBugCondition_1_5(record) — record['original_storage_path'] IS NULL.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: FAIL
     * Counterexample: insertRecord is called without 'original_storage_path' key,
     *   so the column is never populated. The fix must upload the original file and
     *   pass 'original_storage_path' to insertRecord.
     */
    public function test_bug_1_5_original_storage_path_is_null_after_upload_on_unfixed_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $fakeOutputPath = tempnam(sys_get_temp_dir(), 'translated_') . '.docx';
        file_put_contents($fakeOutputPath, 'fake translated content');

        $this->mock(TranslationService::class, function ($mock) use ($fakeOutputPath) {
            $mock->shouldReceive('translateDocument')->once()->andReturn($fakeOutputPath);
            $mock->shouldReceive('getOriginalOutputName')->once()->andReturn('report_translated.docx');
        });

        $this->mock(StorageService::class, function ($mock) {
            // First call: original file upload
            $mock->shouldReceive('uploadFile')
                 ->once()
                 ->andReturn([
                     'storage_path'          => '1/originals/report_original.docx',
                     'signed_url'            => 'https://storage.example.com/signed-url-original',
                     'signed_url_expires_at' => now()->addHour()->toIso8601String(),
                 ]);
            // Second call: translated file upload
            $mock->shouldReceive('uploadFile')
                 ->once()
                 ->andReturn([
                     'storage_path'          => '1/report_translated.docx',
                     'signed_url'            => 'https://storage.example.com/signed-url',
                     'signed_url_expires_at' => now()->addHour()->toIso8601String(),
                 ]);
        });

        // Capture the payload passed to insertRecord
        $capturedPayload = null;
        $this->mock(HistoryService::class, function ($mock) use (&$capturedPayload) {
            $mock->shouldReceive('insertRecord')
                 ->once()
                 ->andReturnUsing(function (array $data) use (&$capturedPayload) {
                     $capturedPayload = $data;
                 });
        });

        $file = \Illuminate\Http\UploadedFile::fake()->create(
            'report.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $response = $this->post('/translate', [
            'source_lang' => 'English',
            'target_lang' => 'Cebuano',
            'document'    => $file,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);

        // On FIXED code: original_storage_path IS non-null — this assertion FAILS on unfixed code
        $this->assertNotNull(
            $capturedPayload['original_storage_path'] ?? null,
            'BUG 1.5 CONFIRMED: original_storage_path is NULL in the insertRecord payload. ' .
            'Counterexample: TranslationController::translate() never calls StorageService::uploadFile() ' .
            'for the original file, so original_storage_path is never set in translation_history. ' .
            'The fix must upload the original file and pass original_storage_path to insertRecord.'
        );

        if (file_exists($fakeOutputPath)) {
            @unlink($fakeOutputPath);
        }
    }

    // ─── Bug 1.6 — Sort Order Incorrect ──────────────────────────────────────

    /**
     * Bug 1.6 — Sort By dropdown does not reorder cards globally on unfixed history page
     *
     * Render history.blade.php with cards in a known order across ≥2 language groups;
     * assert the applySort JS function operates per-group only (not globally), which
     * means "Oldest First" does NOT produce a globally ascending date order and
     * "Language A–Z" does NOT reorder group sections.
     *
     * We verify the bug by inspecting the rendered HTML structure:
     * - Cards within each group share the same data-lang value, so lang-az sort
     *   within a group produces no visible change.
     * - The applySort function iterates over .history-group sections, not all cards.
     *
     * Bug Condition: isBugCondition_1_6(cards, sortOrder) — actualOrder ≠ expectedOrder
     *   after applySort(sortOrder) because sort is per-group, not global.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: FAIL
     * Counterexample: applySort iterates getAllGroups() and sorts cards within each
     *   .history-cards container independently. When grouping is active, all cards
     *   in a group share the same data-lang, so "Language A–Z" produces no change.
     *   The fix must collect all cards globally and sort them across all groups.
     */
    public function test_bug_1_6_sort_function_operates_per_group_not_globally_on_unfixed_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create records across 2 language groups with interleaved dates
        // Group 1: English → Cebuano (newer records)
        // Group 2: English → Filipino (older records)
        // If sort were global (oldest first), Filipino records should appear before Cebuano.
        // But per-group sort keeps them in their respective groups regardless.
        $records = [
            $this->makeDocumentRecord('English', 'Cebuano',  now()->subDays(1)->toIso8601String(), 1),
            $this->makeDocumentRecord('English', 'Cebuano',  now()->subDays(2)->toIso8601String(), 2),
            $this->makeDocumentRecord('English', 'Filipino', now()->subDays(5)->toIso8601String(), 3),
            $this->makeDocumentRecord('English', 'Filipino', now()->subDays(6)->toIso8601String(), 4),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/history');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Verify the page renders with grouped sections (prerequisite for the bug)
        $this->assertStringContainsString('history-group', $content,
            'History page must render grouped sections for Bug 1.6 to be testable.');

        // On FIXED code: applySort uses a global card collection — the specific buggy pattern
        // "function applySort" followed by "getAllGroups().forEach" is ABSENT.
        // On UNFIXED code: applySort iterates getAllGroups() per-group — this assertion FAILS on unfixed code.
        // NOTE: getAllGroups().forEach legitimately appears in applySearch/applyGroupBy; we check the
        // applySort-specific buggy pattern by asserting the global sort approach IS present instead.
        $this->assertStringContainsString(
            'var allCards = Array.from(contentEl.querySelectorAll(\'.history-card\'))',
            $content,
            'BUG 1.6 CONFIRMED: applySort() does not collect all cards globally. ' .
            'Counterexample: applySort iterates getAllGroups() per-group — sort is per-group, not global. ' .
            'When grouping is active, all cards in a group share the same data-lang value, ' .
            'so "Language A–Z" produces no visible change across groups. ' .
            'The fix must replace per-group sort with a global sort across all .history-card elements.'
        );

        // On FIXED code: applyAll() IS wrapped in requestAnimationFrame
        // On UNFIXED code: requestAnimationFrame deferral is ABSENT — this assertion FAILS on unfixed code
        $this->assertStringContainsString(
            'requestAnimationFrame(function () { applyAll(); })',
            $content,
            'BUG 1.6 CONFIRMED: applyAll() is called synchronously (not deferred with requestAnimationFrame). ' .
            'Counterexample: The bare applyAll() call at the bottom of the IIFE runs before the DOM ' .
            'is fully settled, causing the initial sort to be overridden by subsequent layout passes. ' .
            'The fix must wrap the initial call in requestAnimationFrame(function () { applyAll(); }).'
        );
    }

    // ─── Summary: All six bug conditions documented ───────────────────────────

    /**
     * Summary test — All six Day 1 bug conditions documented in a single run
     *
     * This test runs all six checks together and documents the full set of
     * counterexamples found on unfixed code.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: FAIL (multiple assertions fail)
     * This is the SUCCESS case for the exploration test — it proves all bugs exist.
     *
     * Counterexamples documented:
     *   1.1 — <th>Confidence</th> found at column 5; <td>—</td> in every data row
     *   1.2 — <tr class="border-top"> has no onclick and no cursor:pointer
     *   1.3 — <a href="#" class="quick-action-card"> with "Browse Corpus" label present
     *   1.4 — "Cebuano → English" direction label absent from My Documents card
     *   1.5 — insertRecord called without original_storage_path key (null)
     *   1.6 — applySort iterates getAllGroups() per-group; applyAll() not deferred
     */
    public function test_summary_all_six_day1_bug_conditions_documented(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $dashRecords = [
            $this->makeDocumentRecord('English', 'Cebuano', now()->subDays(1)->toIso8601String(), 1),
            $this->makeDocumentRecord('English', 'Filipino', now()->subDays(2)->toIso8601String(), 2),
        ];

        // ── Bugs 1.1, 1.2, 1.3: Dashboard ───────────────────────────────────
        $this->mock(HistoryService::class, function ($mock) use ($dashRecords) {
            $mock->shouldReceive('getHistory')->andReturn($dashRecords);
        });

        $dashResponse = $this->get('/dashboard');
        $dashResponse->assertStatus(200);
        $dashContent = $dashResponse->getContent();

        // Bug 1.1: Confidence column absent (FAILS on unfixed — column IS present)
        $this->assertStringNotContainsString('<th>Confidence</th>', $dashContent,
            'BUG 1.1: <th>Confidence</th> present at column 5. Fix: remove <th> and <td>—</td>.');
        $this->assertStringNotContainsString('<td>—</td>', $dashContent,
            'BUG 1.1: <td>—</td> placeholder present in every data row. Fix: remove fifth <td>.');

        // Bug 1.2: Clickable rows absent (FAILS on unfixed — no onclick/cursor:pointer)
        $this->assertStringContainsString('onclick', $dashContent,
            'BUG 1.2: <tr> has no onclick. Fix: add onclick="window.location=\'/history\'".');
        $this->assertStringContainsString('cursor:pointer', $dashContent,
            'BUG 1.2: <tr> has no cursor:pointer. Fix: add style="cursor:pointer".');

        // Bug 1.3: Browse Corpus absent (FAILS on unfixed — card IS present)
        $this->assertStringNotContainsString('Browse Corpus', $dashContent,
            'BUG 1.3: "Browse Corpus" card present with href="#". Fix: remove entire card block.');

        // ── Bug 1.4: My Documents direction label ─────────────────────────────
        $docRecord = $this->makeDocumentRecord('Cebuano', 'English', now()->toIso8601String(), 3);
        $this->mock(HistoryService::class, function ($mock) use ($docRecord) {
            $mock->shouldReceive('getHistory')->andReturn([$docRecord]);
        });

        $docsResponse = $this->get('/documents');
        $docsResponse->assertStatus(200);
        $docsContent = $docsResponse->getContent();

        // Bug 1.4: Direction label absent (FAILS on unfixed — only source badge shown)
        $this->assertStringContainsString('Cebuano → English', $docsContent,
            'BUG 1.4: "Cebuano → English" direction label absent. Fix: add combined S → T label.');

        // ── Bug 1.5: Original storage path null ───────────────────────────────
        $fakeOutputPath = tempnam(sys_get_temp_dir(), 'translated_') . '.docx';
        file_put_contents($fakeOutputPath, 'fake translated content');

        $this->mock(TranslationService::class, function ($mock) use ($fakeOutputPath) {
            $mock->shouldReceive('translateDocument')->once()->andReturn($fakeOutputPath);
            $mock->shouldReceive('getOriginalOutputName')->once()->andReturn('report_translated.docx');
        });

        $this->mock(StorageService::class, function ($mock) {
            // First call: original file upload
            $mock->shouldReceive('uploadFile')
                 ->once()
                 ->andReturn([
                     'storage_path'          => '1/originals/report_original.docx',
                     'signed_url'            => 'https://storage.example.com/signed-url-original',
                     'signed_url_expires_at' => now()->addHour()->toIso8601String(),
                 ]);
            // Second call: translated file upload
            $mock->shouldReceive('uploadFile')
                 ->once()
                 ->andReturn([
                     'storage_path'          => '1/report_translated.docx',
                     'signed_url'            => 'https://storage.example.com/signed-url',
                     'signed_url_expires_at' => now()->addHour()->toIso8601String(),
                 ]);
        });

        $capturedPayload = null;
        $this->mock(HistoryService::class, function ($mock) use (&$capturedPayload) {
            $mock->shouldReceive('insertRecord')
                 ->once()
                 ->andReturnUsing(function (array $data) use (&$capturedPayload) {
                     $capturedPayload = $data;
                 });
        });

        $file = \Illuminate\Http\UploadedFile::fake()->create(
            'report.docx', 100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $translateResponse = $this->post('/translate', [
            'source_lang' => 'English',
            'target_lang' => 'Cebuano',
            'document'    => $file,
        ], ['Accept' => 'application/json']);

        $translateResponse->assertStatus(200);

        // Bug 1.5: original_storage_path null (FAILS on unfixed — key not in payload)
        $this->assertNotNull(
            $capturedPayload['original_storage_path'] ?? null,
            'BUG 1.5: original_storage_path is NULL in insertRecord payload. ' .
            'Fix: upload original file and pass original_storage_path to insertRecord.'
        );

        if (file_exists($fakeOutputPath)) {
            @unlink($fakeOutputPath);
        }

        // ── Bug 1.6: Sort per-group not global ────────────────────────────────
        $historyRecords = [
            $this->makeDocumentRecord('English', 'Cebuano',  now()->subDays(1)->toIso8601String(), 10),
            $this->makeDocumentRecord('English', 'Filipino', now()->subDays(5)->toIso8601String(), 11),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($historyRecords) {
            $mock->shouldReceive('getHistory')->andReturn($historyRecords);
        });

        $historyResponse = $this->get('/history');
        $historyResponse->assertStatus(200);
        $historyContent = $historyResponse->getContent();

        // Bug 1.6: per-group sort absent (FAILS on unfixed — global sort approach IS absent)
        // NOTE: getAllGroups().forEach legitimately appears in applySearch/applyGroupBy.
        // We check the fix by asserting the global sort approach IS present in applySort.
        $this->assertStringContainsString(
            'var allCards = Array.from(contentEl.querySelectorAll(\'.history-card\'))',
            $historyContent,
            'BUG 1.6 CONFIRMED: applySort() does not collect all cards globally. ' .
            'Counterexample: applySort iterates getAllGroups() per-group — sort is per-group, not global. ' .
            'Fix: replace with global sort across all .history-card elements.');

        // Bug 1.6: requestAnimationFrame deferral present (FAILS on unfixed — bare applyAll() used)
        $this->assertStringContainsString(
            'requestAnimationFrame(function () { applyAll(); })',
            $historyContent,
            'BUG 1.6 CONFIRMED: applyAll() not deferred with requestAnimationFrame. ' .
            'Counterexample: bare applyAll() call at bottom of IIFE runs before DOM is settled. ' .
            'Fix: wrap initial applyAll() call in requestAnimationFrame(function () { applyAll(); }).'
        );
    }
}
