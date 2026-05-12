<?php

namespace App\Http\Controllers;

use App\Services\HistoryService;
use Illuminate\Support\Facades\Auth;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(private HistoryService $history) {}

    /**
     * Show the dashboard with real stats and recent records for the current session.
     */
    public function index()
    {
        $error         = false;
        $stats         = ['totalDocs' => 0, 'translationsThisMonth' => 0, 'wordsTranslated' => 0];
        $recentRecords = [];

        try {
            $records       = $this->history->getHistory(Auth::id());
            $stats         = $this->computeStats($records);
            // Records are already ordered newest-first by HistoryService; take the first 5.
            $recentRecords = array_slice($records, 0, 5);
        } catch (Throwable) {
            $error = true;
        }

        return view('dashboard', compact('stats', 'recentRecords', 'error'));
    }

    /**
     * Compute dashboard stat values from an array of translation_history records.
     *
     * @param  array<int, array>  $records  Rows returned by HistoryService::getHistory().
     * @return array{totalDocs: int, translationsThisMonth: int, wordsTranslated: int}
     */
    public function computeStats(array $records): array
    {
        $currentMonthPrefix = date('Y-m');

        $totalDocs             = 0;
        $translationsThisMonth = 0;
        $wordsTranslated       = 0;

        foreach ($records as $r) {
            $isDocument = ($r['translation_type'] ?? '') === 'document';

            if ($isDocument) {
                $totalDocs++;
                // Document records have no stored word count; use a fixed estimate of
                // 250 words per document as a reasonable default.
                $wordsTranslated += 250;
            } else {
                // Text record — count actual words in the source text.
                $wordsTranslated += str_word_count($r['source_text'] ?? '');
            }

            // Count records created in the current calendar month.
            if (str_starts_with($r['created_at'] ?? '', $currentMonthPrefix)) {
                $translationsThisMonth++;
            }
        }

        return [
            'totalDocs'             => $totalDocs,
            'translationsThisMonth' => $translationsThisMonth,
            'wordsTranslated'       => $wordsTranslated,
        ];
    }
}
