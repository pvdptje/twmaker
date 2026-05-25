<?php

namespace App\Services\Llm;

class ImageAttachments
{
    public const MAX_IMAGES = 3;

    public const MAX_IMAGE_BYTES = 5_500_000;

    public const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * @param  array<int, array{base64?: string, mime_type?: string}>|null  $attachments
     * @return array<int, array{base64: string, mime_type: string}>
     */
    public function normalize(?array $attachments): array
    {
        if (! is_array($attachments) || $attachments === []) {
            return [];
        }

        $normalized = [];

        foreach (array_slice($attachments, 0, self::MAX_IMAGES) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $base64 = trim((string) ($entry['base64'] ?? ''));
            $mime = strtolower(trim((string) ($entry['mime_type'] ?? '')));

            if ($base64 === '' || ! in_array($mime, self::ALLOWED_MIMES, true)) {
                continue;
            }

            if (! preg_match('/^[A-Za-z0-9+\/=\s]+$/', $base64)) {
                continue;
            }

            $decodedLength = (int) (strlen($base64) * 3 / 4);
            if ($decodedLength > self::MAX_IMAGE_BYTES) {
                continue;
            }

            $normalized[] = [
                'base64' => preg_replace('/\s+/', '', $base64) ?? '',
                'mime_type' => $mime,
            ];
        }

        return $normalized;
    }
}
