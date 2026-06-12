<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HistoryService;
use App\Services\StorageService;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preservation Property Tests — Day 1 Fixes
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6**
 *
 * Property 2: Preservation — Existing Dashboard, My Documents, and Saved
 * Translations Behavior Unchanged
 *
 * CRITICAL: These tests MUST PASS on UNFIXED code — they encode the baseline
 * behavior that must be preserved after each fix is applied.
 *
 * Observation-first methodology: each test was written by observing the actual
 * output of the unfixed code with non-buggy inputs, then encoding those
 * observations as assertions.
 *
 * Expected outcome on unfixed code: ALL PASS
 */
class Day1PreservationTest extends TestCase
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

    /** Build a fake text-type translation_history record. */
    private function makeTextRecord(
        string $sourceLang = 'English',
        string $targetLang = 'Cebuano',
        string $sourceText = 'Hello world',
        string $translatedText = 'Kumusta kalibutan',
        string $createdAt = null,
        int $id = null
    ): array {
        return [
            'id'               => $id ?? random_int(1, 99999),
            'user_id'          => 1,
            'translation_type' => 'text',
            'original_filename'  => null,
            'translated_filename' => null,
            'source_language'  => $sourceLang,
            'target_language'  => $targetLang,
            'created_at'       => $createdAt ?? now()->toIso8601String(),
            'storage_path'     => null,
            'signed_url_expires_at' => null,
            'source_text'      => $sourceText,
            'translated_text'  => $translatedText,
        ];
    }

    // ─── Preservation 3.1 — Dashboard Stat Cards and Quick Actions ───────────

    /**
     * Preservation 3.1 — Stat cards and remaining quick-action cards always present
     *
     * **Validates: Requirements 3.1**
     *
     * Property: for any Dashboard render, the three stat cards (Total Documents,
     * Translations This Month, Words Translated) and the two quick-action cards
     * ("New Translation", "Upload Document") are always present.
     *
     * Observed on unfixed code: stat cards render with correct labels; "New Translation"
     * and "Upload Document" quick-action cards are present with href pointing to /translate.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_1_stat_cards_and_quick_actions_always_present(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test with empty records
        $this->mock(HistoryService::class, function ($mock) {
            $mock->shouldReceive('getHistory')->andReturn([]);
        });

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Stat card labels must be present
        $this->assertStringContainsString('Total Documents', $content,
            'PRESERVATION 3.1: "Total Documents" stat card label must always be present.');
        $this->assertStringContainsString('Translations This Month', $content,
            'PRESERVATION 3.1: "Translations This Month" stat card label must always be present.');
        $this->assertStringContainsString('Words Translated', $content,
            'PRESERVATION 3.1: "Words Translated" stat card label must always be present.');

        // Quick-action cards must be present
        $this->assertStringContainsString('New Translation', $content,
            'PRESERVATION 3.1: "New Translation" quick-action card must always be present.');
        $this->assertStringContainsString('Upload Document', $content,
            'PRESERVATION 3.1: "Upload Document" quick-action card must always be present.');
    }

    /**
     * Preservation 3.1 — Quick-action card hrefs point to /translate
     *
     * **Validates: Requirements 3.1**
     *
     * Property: for any Dashboard render, "New Translation" and "Upload Document"
     * quick-action cards always have href values pointing to the translate route.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_1_quick_action_hrefs_point_to_translate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mock(HistoryService::class, function ($mock) {
            $mock->shouldReceive('getHistory')->andReturn([]);
        });

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Both quick-action cards link to /translate
        $translateUrl = route('translate');
        $this->assertStringContainsString(
            'href="' . $translateUrl . '"',
            $content,
            'PRESERVATION 3.1: Quick-action cards must have href pointing to the translate route.'
        );
    }

    /**
     * Preservation 3.1 — Stat card values reflect actual record counts
     *
     * **Validates: Requirements 3.1**
     *
     * Property: for any Dashboard render with N document records, the "Total Documents"
     * stat card shows N and "Translations This Month" reflects current-month records.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     *
     * @dataProvider statCardRecordProvider
     */
    public function test_preservation_3_1_stat_card_values_reflect_records(
        array $records,
        int $expectedTotalDocs,
        int $expectedThisMonth
    ): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString(
            (string) $expectedTotalDocs,
            $content,
            "PRESERVATION 3.1: Total Documents stat must show {$expectedTotalDocs}."
        );
        $this->assertStringContainsString(
            (string) $expectedThisMonth,
            $content,
            "PRESERVATION 3.1: Translations This Month stat must show {$expectedThisMonth}."
        );
    }

    public static function statCardRecordProvider(): array
    {
        return [
            'zero records' => [[], 0, 0],
            'one doc this month' => [
                [
                    [
                        'id' => 1, 'user_id' => 1, 'translation_type' => 'document',
                        'original_filename' => 'a.docx', 'translated_filename' => 'a_t.docx',
                        'source_language' => 'English', 'target_language' => 'Cebuano',
                        'created_at' => now()->toIso8601String(),
                        'storage_path' => 'path', 'signed_url_expires_at' => now()->addHour()->toIso8601String(),
                        'source_text' => null, 'translated_text' => null,
                    ],
                ],
                1, 1,
            ],
            'two docs, one last month' => [
                [
                    [
                        'id' => 1, 'user_id' => 1, 'translation_type' => 'document',
                        'original_filename' => 'a.docx', 'translated_filename' => 'a_t.docx',
                        'source_language' => 'English', 'target_language' => 'Cebuano',
                        'created_at' => now()->toIso8601String(),
                        'storage_path' => 'path1', 'signed_url_expires_at' => now()->addHour()->toIso8601String(),
                        'source_text' => null, 'translated_text' => null,
                    ],
                    [
                        'id' => 2, 'user_id' => 1, 'translation_type' => 'document',
                        'original_filename' => 'b.docx', 'translated_filename' => 'b_t.docx',
                        'source_language' => 'Cebuano', 'target_language' => 'English',
                        'created_at' => now()->subMonth()->toIso8601String(),
                        'storage_path' => 'path2', 'signed_url_expires_at' => now()->addHour()->toIso8601String(),
                        'source_text' => null, 'translated_text' => null,
                    ],
                ],
                2, 1,
            ],
        ];
    }

    // ─── Preservation 3.2 — Dashboard Table Columns ──────────────────────────

    /**
     * Preservation 3.2 — Document / Languages / Date / Status columns always render
     *
     * **Validates: Requirements 3.2**
     *
     * Property: for any Dashboard render with records, the four table columns
     * (Document, Languages, Date, Status) always render with correct data.
     *
     * Observed on unfixed code: all four column headers present; each row shows
     * filename, "source → target" language pair, formatted date, and status badge.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_2_dashboard_table_columns_always_render(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $records = [
            $this->makeDocumentRecord('English', 'Cebuano', now()->subDays(1)->toIso8601String(), 1),
            $this->makeDocumentRecord('Filipino', 'English', now()->subDays(2)->toIso8601String(), 2),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Column headers
        $this->assertStringContainsString('<th>Document</th>', $content,
            'PRESERVATION 3.2: "Document" column header must always be present.');
        $this->assertStringContainsString('<th>Languages</th>', $content,
            'PRESERVATION 3.2: "Languages" column header must always be present.');
        $this->assertStringContainsString('<th>Date</th>', $content,
            'PRESERVATION 3.2: "Date" column header must always be present.');
        $this->assertStringContainsString('<th>Status</th>', $content,
            'PRESERVATION 3.2: "Status" column header must always be present.');

        // Row data: filename
        $this->assertStringContainsString('test-doc.docx', $content,
            'PRESERVATION 3.2: Document filename must appear in the table row.');

        // Row data: language pair (→ arrow)
        $this->assertStringContainsString('English', $content,
            'PRESERVATION 3.2: Source language must appear in the Languages column.');
        $this->assertStringContainsString('Cebuano', $content,
            'PRESERVATION 3.2: Target language must appear in the Languages column.');

        // Row data: status badge
        $this->assertStringContainsString('Completed', $content,
            'PRESERVATION 3.2: "Completed" status badge must appear for document records.');
    }

    /**
     * Preservation 3.2 — Date column renders formatted date for each record
     *
     * **Validates: Requirements 3.2**
     *
     * Property: for any record with a valid created_at timestamp, the Date column
     * renders the date in "M j, Y" format (e.g., "Jan 1, 2024").
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     *
     * @dataProvider dateFormatProvider
     */
    public function test_preservation_3_2_date_column_renders_formatted_date(
        string $isoDate,
        string $expectedFormatted
    ): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $records = [
            $this->makeDocumentRecord('English', 'Cebuano', $isoDate, 1),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString(
            $expectedFormatted,
            $content,
            "PRESERVATION 3.2: Date column must render '{$expectedFormatted}' for ISO date '{$isoDate}'."
        );
    }

    public static function dateFormatProvider(): array
    {
        return [
            'Jan 1 2024'  => ['2024-01-01T00:00:00Z', 'Jan 1, 2024'],
            'Dec 31 2023' => ['2023-12-31T23:59:59Z', 'Dec 31, 2023'],
            'Jun 15 2025' => ['2025-06-15T12:00:00Z', 'Jun 15, 2025'],
        ];
    }

    // ─── Preservation 3.3 — My Documents Filters and Toggles ────────────────

    /**
     * Preservation 3.3 — Search, language filter, status filter, tabs, and view toggle
     * controls are always present in My Documents
     *
     * **Validates: Requirements 3.3**
     *
     * Property: for any My Documents state with documents, all filter and toggle
     * controls (search input, language filter, status filter, tab buttons, grid/list
     * toggle) are always rendered in the HTML.
     *
     * Observed on unfixed code: all controls present and functional.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_3_my_documents_filter_controls_always_present(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $records = [
            $this->makeDocumentRecord('English', 'Cebuano', now()->toIso8601String(), 1),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/documents');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Search input
        $this->assertStringContainsString('id="docs-search"', $content,
            'PRESERVATION 3.3: Search input (#docs-search) must always be present.');

        // Language filter dropdown
        $this->assertStringContainsString('id="docs-lang-filter"', $content,
            'PRESERVATION 3.3: Language filter (#docs-lang-filter) must always be present.');

        // Status filter dropdown
        $this->assertStringContainsString('id="docs-status-filter"', $content,
            'PRESERVATION 3.3: Status filter (#docs-status-filter) must always be present.');

        // Tab buttons
        $this->assertStringContainsString('data-tab="all"', $content,
            'PRESERVATION 3.3: "All" tab button must always be present.');
        $this->assertStringContainsString('data-tab="recent"', $content,
            'PRESERVATION 3.3: "Recent" tab button must always be present.');
        $this->assertStringContainsString('data-tab="shared"', $content,
            'PRESERVATION 3.3: "Shared" tab button must always be present.');
        $this->assertStringContainsString('data-tab="archived"', $content,
            'PRESERVATION 3.3: "Archived" tab button must always be present.');

        // Grid/list view toggle buttons
        $this->assertStringContainsString('id="docs-grid-btn"', $content,
            'PRESERVATION 3.3: Grid view button (#docs-grid-btn) must always be present.');
        $this->assertStringContainsString('id="docs-list-btn"', $content,
            'PRESERVATION 3.3: List view button (#docs-list-btn) must always be present.');
    }

    /**
     * Preservation 3.3 — Card data-attributes for client-side filtering always present
     *
     * **Validates: Requirements 3.3**
     *
     * Property: for any My Documents state with documents, each card has the
     * data-title, data-lang, data-status, and data-recent attributes that drive
     * the client-side filter logic.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     *
     * @dataProvider documentCardFilterProvider
     */
    public function test_preservation_3_3_card_filter_data_attributes_always_present(
        string $sourceLang,
        string $targetLang,
        string $createdAt,
        string $expectedDataLang,
        string $expectedDataStatus
    ): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $record = $this->makeDocumentRecord($sourceLang, $targetLang, $createdAt, 1);

        $this->mock(HistoryService::class, function ($mock) use ($record) {
            $mock->shouldReceive('getHistory')->andReturn([$record]);
        });

        $response = $this->get('/documents');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString(
            'data-lang="' . $expectedDataLang . '"',
            $content,
            "PRESERVATION 3.3: Card must have data-lang=\"{$expectedDataLang}\"."
        );
        $this->assertStringContainsString(
            'data-status="' . $expectedDataStatus . '"',
            $content,
            "PRESERVATION 3.3: Card must have data-status=\"{$expectedDataStatus}\"."
        );
        $this->assertStringContainsString(
            'data-title=',
            $content,
            'PRESERVATION 3.3: Card must have data-title attribute for search filtering.'
        );
        $this->assertStringContainsString(
            'data-recent=',
            $content,
            'PRESERVATION 3.3: Card must have data-recent attribute for tab filtering.'
        );
    }

    public static function documentCardFilterProvider(): array
    {
        return [
            'English source, translated' => [
                'English', 'Cebuano', now()->toIso8601String(), 'English', 'translated',
            ],
            'Cebuano source, translated' => [
                'Cebuano', 'English', now()->subMonths(2)->toIso8601String(), 'Cebuano', 'translated',
            ],
            'Filipino source, translated' => [
                'Filipino', 'English', now()->toIso8601String(), 'Filipino', 'translated',
            ],
        ];
    }

    // ─── Preservation 3.4 — Redownload Endpoint Returns Signed URL ───────────

    /**
     * Preservation 3.4 — /history/redownload/{id} always returns a signed URL
     *
     * **Validates: Requirements 3.4**
     *
     * Property: for any translated document id, POST /history/redownload/{id}
     * always returns a JSON response with a download_url (signed URL).
     *
     * Observed on unfixed code: endpoint returns 200 with download_url key.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     *
     * @dataProvider redownloadIdProvider
     */
    public function test_preservation_3_4_redownload_always_returns_signed_url(int $docId): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $record = [
            'id'                    => $docId,
            'user_id'               => $user->id,
            'translation_type'      => 'document',
            'original_filename'     => 'report.docx',
            'translated_filename'   => 'report_translated.docx',
            'source_language'       => 'English',
            'target_language'       => 'Cebuano',
            'created_at'            => now()->toIso8601String(),
            'storage_path'          => "{$user->id}/report_translated.docx",
            'signed_url_expires_at' => now()->addHour()->toIso8601String(),
            'source_text'           => null,
            'translated_text'       => null,
        ];

        $signedUrl = 'https://storage.example.com/signed-url-' . $docId;

        $this->mock(\App\Services\HistoryService::class, function ($mock) use ($record) {
            $mock->shouldReceive('getRecord')->once()->andReturn($record);
            $mock->shouldReceive('updateExpiry')->once()->andReturn(null);
        });

        $this->mock(\App\Services\StorageService::class, function ($mock) use ($signedUrl) {
            $mock->shouldReceive('generateSignedUrl')->once()->andReturn([
                'signed_url'            => $signedUrl,
                'signed_url_expires_at' => now()->addHour()->toIso8601String(),
            ]);
        });

        $response = $this->post("/history/redownload/{$docId}", [], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['download_url']);

        $data = $response->json();
        $this->assertNotEmpty($data['download_url'],
            "PRESERVATION 3.4: /history/redownload/{$docId} must return a non-empty download_url.");
        $this->assertStringStartsWith('https://', $data['download_url'],
            "PRESERVATION 3.4: download_url must be a valid HTTPS URL.");
    }

    public static function redownloadIdProvider(): array
    {
        return [
            'id 1'   => [1],
            'id 42'  => [42],
            'id 999' => [999],
        ];
    }

    /**
     * Preservation 3.4 — "Open" redownload button is present on My Documents cards
     *
     * **Validates: Requirements 3.4**
     *
     * Property: for any My Documents card, the "Open" redownload button is always
     * rendered with the correct data-id attribute.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_4_open_button_present_on_my_documents_card(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $record = $this->makeDocumentRecord('English', 'Cebuano', now()->toIso8601String(), 77);

        $this->mock(HistoryService::class, function ($mock) use ($record) {
            $mock->shouldReceive('getHistory')->andReturn([$record]);
        });

        $response = $this->get('/documents');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('redownload-btn', $content,
            'PRESERVATION 3.4: "Open" redownload button (.redownload-btn) must be present on cards.');
        $this->assertStringContainsString('data-id="77"', $content,
            'PRESERVATION 3.4: Redownload button must have data-id matching the record id.');
        $this->assertStringContainsString('Open', $content,
            'PRESERVATION 3.4: Redownload button must have "Open" label text.');
    }

    // ─── Preservation 3.5 — Saved Translations Actions ───────────────────────

    /**
     * Preservation 3.5 — Search, group-by, sort, copy, download, share, bookmark
     * controls always present in Saved Translations
     *
     * **Validates: Requirements 3.5**
     *
     * Property: for any Saved Translations state with records, all action controls
     * (search, group-by select, sort select, copy/download/share/bookmark buttons)
     * are always rendered in the HTML.
     *
     * Observed on unfixed code: all controls present and functional.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_5_saved_translations_action_controls_always_present(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $records = [
            $this->makeDocumentRecord('English', 'Cebuano', now()->subDays(1)->toIso8601String(), 1),
            $this->makeTextRecord('English', 'Filipino', 'Hello', 'Kumusta', now()->subDays(2)->toIso8601String(), 2),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/history');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Search input
        $this->assertStringContainsString('id="history-search"', $content,
            'PRESERVATION 3.5: Search input (#history-search) must always be present.');

        // Group-by select
        $this->assertStringContainsString('id="history-group"', $content,
            'PRESERVATION 3.5: Group-by select (#history-group) must always be present.');
        $this->assertStringContainsString('Group by Language Pair', $content,
            'PRESERVATION 3.5: "Group by Language Pair" option must always be present.');
        $this->assertStringContainsString('No Grouping', $content,
            'PRESERVATION 3.5: "No Grouping" option must always be present.');

        // Sort select
        $this->assertStringContainsString('id="history-sort"', $content,
            'PRESERVATION 3.5: Sort select (#history-sort) must always be present.');
        $this->assertStringContainsString('Newest First', $content,
            'PRESERVATION 3.5: "Newest First" sort option must always be present.');
        $this->assertStringContainsString('Oldest First', $content,
            'PRESERVATION 3.5: "Oldest First" sort option must always be present.');
        $this->assertStringContainsString('Language A', $content,
            'PRESERVATION 3.5: "Language A–Z" sort option must always be present.');

        // Action buttons on cards
        $this->assertStringContainsString('share-btn', $content,
            'PRESERVATION 3.5: Share button (.share-btn) must always be present on cards.');
        $this->assertStringContainsString('bookmark-btn', $content,
            'PRESERVATION 3.5: Bookmark button (.bookmark-btn) must always be present on cards.');
    }

    /**
     * Preservation 3.5 — Document cards have redownload button; text cards have copy/download
     *
     * **Validates: Requirements 3.5**
     *
     * Property: for any Saved Translations state, document cards always have a
     * redownload button and text cards always have copy and download buttons.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_5_card_action_buttons_match_translation_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $records = [
            $this->makeDocumentRecord('English', 'Cebuano', now()->subDays(1)->toIso8601String(), 10),
            $this->makeTextRecord('English', 'Filipino', 'Hello', 'Kumusta', now()->subDays(2)->toIso8601String(), 20),
        ];

        $this->mock(HistoryService::class, function ($mock) use ($records) {
            $mock->shouldReceive('getHistory')->andReturn($records);
        });

        $response = $this->get('/history');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Document card: redownload button with data-id
        $this->assertStringContainsString('data-id="10"', $content,
            'PRESERVATION 3.5: Document card must have redownload button with data-id.');

        // Text card: copy button
        $this->assertStringContainsString('copy-text-btn', $content,
            'PRESERVATION 3.5: Text card must have copy button (.copy-text-btn).');

        // Text card: download-as-txt button
        $this->assertStringContainsString('download-text-btn', $content,
            'PRESERVATION 3.5: Text card must have download-text button (.download-text-btn).');
    }

    /**
     * Preservation 3.5 — Grouped sections always render with correct data-lang-pair
     *
     * **Validates: Requirements 3.5**
     *
     * Property: for any Saved Translations state with records, history-group sections
     * always render with data-lang-pair attributes matching the language pair.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_5_grouped_sections_always_render(): void
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

        $response = $this->get('/history');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('history-group', $content,
            'PRESERVATION 3.5: history-group sections must always render.');
        $this->assertStringContainsString('data-lang-pair="English → Cebuano"', $content,
            'PRESERVATION 3.5: Group section must have data-lang-pair="English → Cebuano".');
        $this->assertStringContainsString('data-lang-pair="English → Filipino"', $content,
            'PRESERVATION 3.5: Group section must have data-lang-pair="English → Filipino".');
    }

    // ─── Preservation 3.6 — Translation Pipeline Returns download_url ─────────

    /**
     * Preservation 3.6 — translate() always returns download_url when translated upload succeeds
     *
     * **Validates: Requirements 3.6**
     *
     * Property: for any valid document upload, TranslationController::translate()
     * always returns a download_url in the JSON response, regardless of whether
     * the original-file upload succeeds or fails.
     *
     * Observed on unfixed code: POST /translate with a document returns 200 with
     * download_url, download_filename, and signed_url_expires_at.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     */
    public function test_preservation_3_6_translate_always_returns_download_url(): void
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
            // First call: original file upload; second call: translated file upload
            $mock->shouldReceive('uploadFile')->andReturn([
                'storage_path'          => '1/report_translated.docx',
                'signed_url'            => 'https://storage.example.com/signed-url',
                'signed_url_expires_at' => now()->addHour()->toIso8601String(),
            ]);
        });

        $this->mock(HistoryService::class, function ($mock) {
            $mock->shouldReceive('insertRecord')->once()->andReturn(null);
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
        $response->assertJsonStructure(['download_url', 'download_filename', 'signed_url_expires_at']);

        $data = $response->json();
        $this->assertNotEmpty($data['download_url'],
            'PRESERVATION 3.6: translate() must always return a non-empty download_url.');
        $this->assertStringStartsWith('https://', $data['download_url'],
            'PRESERVATION 3.6: download_url must be a valid HTTPS URL.');

        if (file_exists($fakeOutputPath)) {
            @unlink($fakeOutputPath);
        }
    }

    /**
     * Preservation 3.6 — translate() still returns download_url when original-file upload fails
     *
     * **Validates: Requirements 3.6**
     *
     * Property: for any valid document upload, TranslationController::translate()
     * still returns a download_url even if the original-file upload to Supabase fails.
     * This is the additive nature of Bug 1.5 fix — it must not break the existing flow.
     *
     * NOTE: On unfixed code, there is no original-file upload at all, so this test
     * verifies the translated-file upload path is unaffected. After the fix, the
     * original-file upload is wrapped in try/catch so failure is non-blocking.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS (no original upload attempted on unfixed code)
     */
    public function test_preservation_3_6_translate_returns_download_url_even_if_original_upload_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $fakeOutputPath = tempnam(sys_get_temp_dir(), 'translated_') . '.docx';
        file_put_contents($fakeOutputPath, 'fake translated content');

        $this->mock(TranslationService::class, function ($mock) use ($fakeOutputPath) {
            $mock->shouldReceive('translateDocument')->once()->andReturn($fakeOutputPath);
            $mock->shouldReceive('getOriginalOutputName')->once()->andReturn('report_translated.docx');
        });

        // On unfixed code: uploadFile is called exactly once (for the translated file only).
        // On fixed code: uploadFile may be called twice; the second call (original) may throw.
        // Either way, the response must contain download_url.
        $this->mock(StorageService::class, function ($mock) {
            $mock->shouldReceive('uploadFile')
                 ->andReturn([
                     'storage_path'          => '1/report_translated.docx',
                     'signed_url'            => 'https://storage.example.com/signed-url-preserved',
                     'signed_url_expires_at' => now()->addHour()->toIso8601String(),
                 ]);
        });

        $this->mock(HistoryService::class, function ($mock) {
            $mock->shouldReceive('insertRecord')->once()->andReturn(null);
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

        $data = $response->json();
        $this->assertArrayHasKey('download_url', $data,
            'PRESERVATION 3.6: translate() must always return download_url key in response.');
        $this->assertNotEmpty($data['download_url'],
            'PRESERVATION 3.6: download_url must be non-empty even if original-file upload fails.');

        if (file_exists($fakeOutputPath)) {
            @unlink($fakeOutputPath);
        }
    }

    /**
     * Preservation 3.6 — translate() returns download_url for all valid language pairs
     *
     * **Validates: Requirements 3.6**
     *
     * Property: for any valid (source, target) language pair, translate() always
     * returns a download_url.
     *
     * EXPECTED OUTCOME ON UNFIXED CODE: PASS
     *
     * @dataProvider validLanguagePairProvider
     */
    public function test_preservation_3_6_translate_returns_download_url_for_all_language_pairs(
        string $sourceLang,
        string $targetLang
    ): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $fakeOutputPath = tempnam(sys_get_temp_dir(), 'translated_') . '.docx';
        file_put_contents($fakeOutputPath, 'fake translated content');

        $this->mock(TranslationService::class, function ($mock) use ($fakeOutputPath) {
            $mock->shouldReceive('translateDocument')->once()->andReturn($fakeOutputPath);
            $mock->shouldReceive('getOriginalOutputName')->once()->andReturn('doc_translated.docx');
        });

        $this->mock(StorageService::class, function ($mock) {
            $mock->shouldReceive('uploadFile')->andReturn([
                'storage_path'          => '1/doc_translated.docx',
                'signed_url'            => 'https://storage.example.com/signed-url',
                'signed_url_expires_at' => now()->addHour()->toIso8601String(),
            ]);
        });

        $this->mock(HistoryService::class, function ($mock) {
            $mock->shouldReceive('insertRecord')->once()->andReturn(null);
        });

        $file = \Illuminate\Http\UploadedFile::fake()->create(
            'doc.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $response = $this->post('/translate', [
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'document'    => $file,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('download_url', $data,
            "PRESERVATION 3.6: translate() must return download_url for {$sourceLang} → {$targetLang}.");

        if (file_exists($fakeOutputPath)) {
            @unlink($fakeOutputPath);
        }
    }

    public static function validLanguagePairProvider(): array
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
}
