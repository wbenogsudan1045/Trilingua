<?php

namespace App\Services;

use App\Exceptions\TranslationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TranslationService
{
    /**
     * Base URL of the Python translation microservice.
     * Run `python Model/server.py` before using the app.
     */
    private const SERVICE_URL = 'http://127.0.0.1:5000';

    private const LANGUAGE_MAP = [
        'English'  => 'eng_Latn',
        'Cebuano'  => 'ceb_Latn',
        'Filipino' => 'tgl_Latn',
    ];

    private const EXTENSION_MAP = [
        '.docx' => '.docx',
        '.pdf'  => '.pdf',
        '.txt'  => '.txt',
        '.md'   => '.md',
        '.csv'  => '.csv',
        '.rtf'  => '.docx',
        '.odt'  => '.docx',
    ];

    private const TIMEOUT_SECONDS = 120;

    // -------------------------------------------------------------------------
    // Text translation
    // -------------------------------------------------------------------------

    /**
     * Translate a plain-text string via the microservice.
     *
     * @throws TranslationException
     */
    public function translateText(string $text, string $sourceLang, string $targetLang): string
    {
        $payload = json_encode([
            'text'        => $text,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
        ]);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $payload,
                'timeout'       => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);

        $url      = self::SERVICE_URL . '/translate/text';
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new TranslationException(
                'Could not connect to the translation service. ' .
                'Make sure it is running: python Model/server.py'
            );
        }

        $data       = json_decode($response, true);
        $statusLine = $http_response_header[0] ?? '';
        $statusCode = (int) (explode(' ', $statusLine)[1] ?? 500);

        if ($statusCode !== 200) {
            $message = $data['detail'] ?? $data['error'] ?? 'Translation failed.';
            $code    = $statusCode === 504 ? 504 : 500;
            throw new TranslationException($message, $code);
        }

        return (string) ($data['translated'] ?? '');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a user-friendly output filename from the original uploaded filename.
     */
    public function getOriginalOutputName(string $originalName, string $ext): string
    {
        $outExt = self::EXTENSION_MAP[$ext] ?? $ext;
        $stem   = pathinfo($originalName, PATHINFO_FILENAME);
        return $stem . '_translated' . $outExt;
    }

    // -------------------------------------------------------------------------
    // Document translation
    // -------------------------------------------------------------------------

    /**
     * Translate an uploaded document file via the microservice.
     * Returns the absolute path to the translated output file.
     *
     * @throws TranslationException
     */
    public function translateDocument(UploadedFile $file, string $sourceLang, string $targetLang, string $pdfColumnMode = 'auto'): string
    {
        $ext    = strtolower('.' . $file->getClientOriginalExtension());
        $outExt = self::EXTENSION_MAP[$ext] ?? $ext;

        // Build a multipart/form-data body manually using cURL
        $outputPath = storage_path('app/temp/' . Str::uuid() . '_translated' . $outExt);

        $ch = curl_init(self::SERVICE_URL . '/translate/document');

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_POSTFIELDS     => [
                'file'            => new \CURLFile(
                    $file->getRealPath(),
                    $file->getMimeType() ?: 'application/octet-stream',
                    $file->getClientOriginalName()
                ),
                'source_lang'     => $sourceLang,
                'target_lang'     => $targetLang,
                'pdf_column_mode' => $pdfColumnMode,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlErr) {
            throw new TranslationException(
                'Could not connect to the translation service: ' . $curlErr .
                '. Make sure it is running: python Model/server.py'
            );
        }

        if ($httpCode !== 200) {
            $data    = json_decode($response, true);
            $message = $data['detail'] ?? $data['error'] ?? 'Document translation failed.';
            throw new TranslationException($message, $httpCode >= 500 ? 500 : $httpCode);
        }

        // The response body is the translated file — save it to temp storage
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        file_put_contents($outputPath, $response);

        // Clean up after the response is sent
        register_shutdown_function(function () use ($outputPath): void {
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
        });

        return $outputPath;
    }
}
