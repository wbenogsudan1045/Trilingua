<?php

namespace App\Http\Controllers;

use App\Services\HistoryService;
use App\Services\StorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HistoryController extends Controller
{
    public function __construct(
        private HistoryService $history,
        private StorageService $storage,
    ) {}

    /**
     * GET /history — render the translation history page.
     *
     * Fetches all history records for the current session and renders the
     * history view. On database error, logs the exception and renders the
     * error view with an empty records array.
     */
    public function index(Request $request): View
    {
        try {
            $records = $this->history->getHistory(Auth::id());

            return view('history', [
                'records' => $records,
                'error'   => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('HistoryController::index failed to load history', [
                'user_id'   => Auth::id(),
                'exception'  => $e->getMessage(),
            ]);

            return view('history', [
                'records' => [],
                'error'   => true,
            ]);
        }
    }

    /**
     * POST /history/redownload/{id} — generate a new signed URL for a past translation.
     *
     * Validates session ownership, generates a fresh signed URL via StorageService,
     * updates the expiry in the database, and returns the new download URL as JSON.
     */
    public function redownload(Request $request, int $id): JsonResponse
    {
        // 1. Fetch the record; return 404 if not found
        try {
            $record = $this->history->getRecord($id);
        } catch (\Throwable $e) {
            Log::error('HistoryController::redownload failed to fetch record', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json(
                ['error' => 'Unable to generate download link. Please try again later.'],
                500
            );
        }

        if ($record === null) {
            return response()->json(
                ['error' => 'This file is no longer available.'],
                404
            );
        }

        // 2. Enforce user ownership — return 403 without revealing record existence
        if ((int) $record['user_id'] !== Auth::id()) {
            return response()->json(
                ['error' => 'Forbidden.'],
                403
            );
        }

        // 3. Generate a new signed URL via StorageService
        try {
            $storageResult = $this->storage->generateSignedUrl($record['storage_path']);
        } catch (\Throwable $e) {
            // 4. Map "not found" exceptions to 404
            if (stripos($e->getMessage(), 'not found') !== false) {
                return response()->json(
                    ['error' => 'This file is no longer available.'],
                    404
                );
            }

            // 5. All other storage errors map to 500
            Log::error('HistoryController::redownload failed to generate signed URL', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json(
                ['error' => 'Unable to generate download link. Please try again later.'],
                500
            );
        }

        // 6. Update the expiry timestamp in the database
        try {
            $this->history->updateExpiry($id, $storageResult['signed_url_expires_at']);
        } catch (\Throwable $e) {
            // Log but do not block the response — the user still gets their download URL
            Log::error('HistoryController::redownload failed to update expiry', [
                'id'        => $id,
                'exception' => $e->getMessage(),
            ]);
        }

        // 7. Return the new download URL
        return response()->json([
            'download_url' => $storageResult['signed_url'],
        ], 200);
    }
}
