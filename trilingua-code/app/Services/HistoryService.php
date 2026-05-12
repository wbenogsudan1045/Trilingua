<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use RuntimeException;

class HistoryService
{
    public function __construct(private Client $guzzle) {}

    /**
     * Insert a new translation job record into translation_history.
     *
     * For document translations, pass:
     *   user_id, translation_type='document', original_filename, translated_filename,
     *   source_language, target_language, created_at, storage_path, signed_url_expires_at
     *
     * For text translations, pass:
     *   user_id, translation_type='text', source_text, translated_text,
     *   source_language, target_language, created_at
     *
     * @throws RuntimeException on DB error or connection failure.
     */
    public function insertRecord(array $data): void
    {
        $url     = config('services.supabase.url');
        $anonKey = config('services.supabase.anon_key');

        // Build payload — only include keys that are present
        $payload = array_filter([
            'user_id'               => $data['user_id'] ?? null,
            'translation_type'      => $data['translation_type'] ?? 'document',
            'original_filename'     => $data['original_filename'] ?? null,
            'translated_filename'   => $data['translated_filename'] ?? null,
            'source_language'       => $data['source_language'] ?? null,
            'target_language'       => $data['target_language'] ?? null,
            'created_at'            => $data['created_at'] ?? null,
            'storage_path'          => $data['storage_path'] ?? null,
            'signed_url_expires_at' => $data['signed_url_expires_at'] ?? null,
            'source_text'           => $data['source_text'] ?? null,
            'translated_text'       => $data['translated_text'] ?? null,
        ], fn($v) => $v !== null);

        try {
            $response = $this->guzzle->post("{$url}/rest/v1/translation_history", [
                'headers' => [
                    'Authorization' => "Bearer {$anonKey}",
                    'apikey'        => $anonKey,
                    'Content-Type'  => 'application/json',
                    'Prefer'        => 'return=minimal',
                ],
                'json' => $payload,
            ]);
        } catch (ConnectException $e) {
            throw new RuntimeException(
                'Supabase DB insert failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = (string) $response->getBody();
            throw new RuntimeException("Supabase DB insert failed: {$body}");
        }
    }

    /**
     * Fetch history records for a user, newest first, capped at 200.
     *
     * @param  int  $userId  The authenticated user's ID.
     * @return array<int, array>  Each element is a translation_history row.
     * @throws RuntimeException on DB error or connection failure.
     */
    public function getHistory(int $userId): array
    {
        $url     = config('services.supabase.url');
        $anonKey = config('services.supabase.anon_key');

        try {
            $response = $this->guzzle->get("{$url}/rest/v1/translation_history", [
                'headers' => [
                    'Authorization' => "Bearer {$anonKey}",
                    'apikey'        => $anonKey,
                    'Content-Type'  => 'application/json',
                ],
                'query' => [
                    'user_id' => "eq.{$userId}",
                    'order'   => 'created_at.desc',
                    'limit'   => '200',
                    'select'  => 'id,user_id,translation_type,original_filename,translated_filename,source_language,target_language,created_at,storage_path,signed_url_expires_at,source_text,translated_text',
                ],
            ]);
        } catch (ConnectException $e) {
            throw new RuntimeException(
                'Supabase DB query failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = (string) $response->getBody();
            throw new RuntimeException("Supabase DB query failed: {$body}");
        }

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * Fetch a single record by ID.
     *
     * @return array|null  Null if not found.
     * @throws RuntimeException on DB error or connection failure.
     */
    public function getRecord(int $id): ?array
    {
        $url     = config('services.supabase.url');
        $anonKey = config('services.supabase.anon_key');

        try {
            $response = $this->guzzle->get("{$url}/rest/v1/translation_history", [
                'headers' => [
                    'Authorization' => "Bearer {$anonKey}",
                    'apikey'        => $anonKey,
                    'Content-Type'  => 'application/json',
                ],
                'query' => [
                    'id'    => "eq.{$id}",
                    'limit' => '1',
                ],
            ]);
        } catch (ConnectException $e) {
            throw new RuntimeException(
                'Supabase DB query failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = (string) $response->getBody();
            throw new RuntimeException("Supabase DB query failed: {$body}");
        }

        $rows = json_decode((string) $response->getBody(), true) ?? [];

        return $rows[0] ?? null;
    }

    /**
     * Update the signed_url_expires_at column for a record.
     *
     * @throws RuntimeException on DB error or connection failure.
     */
    public function updateExpiry(int $id, string $newExpiry): void
    {
        $url     = config('services.supabase.url');
        $anonKey = config('services.supabase.anon_key');

        try {
            $response = $this->guzzle->patch("{$url}/rest/v1/translation_history", [
                'headers' => [
                    'Authorization' => "Bearer {$anonKey}",
                    'apikey'        => $anonKey,
                    'Content-Type'  => 'application/json',
                    'Prefer'        => 'return=minimal',
                ],
                'query' => [
                    'id' => "eq.{$id}",
                ],
                'json' => [
                    'signed_url_expires_at' => $newExpiry,
                ],
            ]);
        } catch (ConnectException $e) {
            throw new RuntimeException(
                'Supabase DB update failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = (string) $response->getBody();
            throw new RuntimeException("Supabase DB update failed: {$body}");
        }
    }
}
