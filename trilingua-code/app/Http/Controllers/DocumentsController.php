<?php

namespace App\Http\Controllers;

use App\Services\HistoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DocumentsController extends Controller
{
    public function __construct(private HistoryService $history) {}

    /**
     * GET /documents — render the My Documents page.
     *
     * Shows only document-type translation records (originals and their
     * translated counterparts) for the current session.
     */
    public function index(Request $request): View
    {
        try {
            $all = $this->history->getHistory(Auth::id());

            // Keep only document records
            $documents = array_values(array_filter(
                $all,
                fn($r) => ($r['translation_type'] ?? 'document') === 'document'
            ));

            return view('my-documents', [
                'documents' => $documents,
                'error'     => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('DocumentsController::index failed', [
                'user_id'   => Auth::id(),
                'exception'  => $e->getMessage(),
            ]);

            return view('my-documents', [
                'documents' => [],
                'error'     => true,
            ]);
        }
    }
}
