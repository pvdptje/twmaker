<?php

namespace App\Services\Llm;

class PromptLog
{
    /**
     * @return array{stage: string, provider: string, model: string, system_prompt: string, user_prompt: string, context: array, image_count: int}
     */
    public static function fromTextRequest(TextRequest $request): array
    {
        return [
            'stage' => $request->stage,
            'provider' => $request->provider,
            'model' => $request->model,
            'system_prompt' => $request->systemPrompt,
            'user_prompt' => $request->userPrompt,
            'context' => self::scrubArray($request->context),
            'image_count' => count($request->images),
        ];
    }

    private static function scrubArray(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && str_contains(strtolower($key), 'key')) {
                $value[$key] = '[redacted]';

                continue;
            }

            $value[$key] = match (true) {
                is_string($item) => mb_check_encoding($item, 'UTF-8') ? $item : mb_scrub($item, 'UTF-8'),
                is_array($item) => self::scrubArray($item),
                default => $item,
            };
        }

        return $value;
    }
}
