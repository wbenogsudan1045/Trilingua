<?php

namespace App\Http\Controllers;

use App\Exceptions\TranslationException;
use App\Services\HistoryService;
use App\Services\StorageService;
use App\Services\TranslationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
    public function __construct(
        private TranslationService $service,
        private StorageService $storage,
        private HistoryService $history,
    ) {}

    /**
     * GET /translate — render the translation page.
     */
    public function show(): View
    {
        return view('translation');
    }

    /**
     * POST /translate — handle text or document translation.
     */
    public function translate(Request $request): JsonResponse
    {
        $sourceLang = $request->input('source_lang');

        $request->validate([
            'source_lang' => ['required', Rule::in(['English', 'Cebuano', 'Filipino'])],
            'target_lang' => [
                'required',
                Rule::in(['English', 'Cebuano', 'Filipino']),
                Rule::notIn([$sourceLang]),
            ],
            'text'     => ['required_without:document', 'nullable', 'string', 'max:8000'],
            'document' => [
                'required_without:text',
                'nullable',
                'file',
                'mimes:docx,pdf,txt,md,rtf,odt,csv',
                'max:10240',
            ],
            'pdf_column_mode' => ['nullable', Rule::in(['auto', 'single', 'left', 'right'])],
        ], [
            'target_lang.not_in' => 'The source language and target language must be different.',
        ]);

        $targetLang = $request->input('target_lang');
        $pdfColumnMode = $request->input('pdf_column_mode', 'auto');

        try {
            // Document mode — upload to Supabase Storage, return a signed URL
            if ($request->hasFile('document')) {
                $outputPath = $this->service->translateDocument(
                    $request->file('document'),
                    $sourceLang,
                    $targetLang,
                    $pdfColumnMode
                );

                $storagePath = session()->getId() . '/' . basename($outputPath);

                try {
                    $storageResult = $this->storage->uploadFile($outputPath, $storagePath);
                } catch (\Throwable $e) {
                    Log::error('Supabase Storage upload failed', [
                        'exception' => $e->getMessage(),
                        'storage_path' => $storagePath,
                    ]);
                    @unlink($outputPath);
                    return response()->json([
                        'error' => 'Translation succeeded but file upload failed. Please try again.',
                    ], 500);
                }

                @unlink($outputPath);

                $downloadFilename = $this->service->getOriginalOutputName(
                    $request->file('document')->getClientOriginalName(),
                    strtolower('.' . $request->file('document')->getClientOriginalExtension())
                );

                try {
                    $this->history->insertRecord([
                        'session_id'            => session()->getId(),
                        'original_filename'     => $request->file('document')->getClientOriginalName(),
                        'translated_filename'   => $downloadFilename,
                        'source_language'       => $sourceLang,
                        'target_language'       => $targetLang,
                        'created_at'            => now()->toIso8601String(),
                        'storage_path'          => $storagePath,
                        'signed_url_expires_at' => $storageResult['signed_url_expires_at'],
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to insert translation history record', [
                        'exception'    => $e->getMessage(),
                        'session_id'   => session()->getId(),
                        'storage_path' => $storagePath,
                    ]);
                }

                return response()->json([
                    'download_url'          => $storageResult['signed_url'],
                    'download_filename'     => $downloadFilename,
                    'signed_url_expires_at' => $storageResult['signed_url_expires_at'],
                ]);
            }

            // Text mode
            $result = $this->service->translateText(
                $request->input('text'),
                $sourceLang,
                $targetLang
            );

            // Log text translation to history (non-blocking)
            try {
                $this->history->insertRecord([
                    'session_id'       => session()->getId(),
                    'translation_type' => 'text',
                    'source_text'      => $request->input('text'),
                    'translated_text'  => $result,
                    'source_language'  => $sourceLang,
                    'target_language'  => $targetLang,
                    'created_at'       => now()->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to insert text translation history record', [
                    'exception'  => $e->getMessage(),
                    'session_id' => session()->getId(),
                ]);
            }

            return response()->json(['translated' => $result]);

        } catch (TranslationException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'timed out')) {
                return response()->json(['error' => $message], 504);
            }

            return response()->json(['error' => $message], 500);
        }
    }

    /**
     * GET /translate/download/{token} — serve a previously translated document.
     */
    public function download(string $token): \Symfony\Component\HttpFoundation\Response
    {
        // Sanitise token — only allow UUID-style filenames to prevent path traversal
        if (!preg_match('/^[a-f0-9\-]+_translated\.[a-z]+$/i', $token)) {
            abort(404);
        }

        $outputPath = session()->pull('download_' . $token);

        if (!$outputPath || !file_exists($outputPath)) {
            abort(404, 'Download link has expired or the file was not found.');
        }

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }
}
