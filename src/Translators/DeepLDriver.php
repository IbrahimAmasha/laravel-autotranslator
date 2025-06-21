<?php

namespace IbrahimAmasha\AutoTranslator\Translators;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepLDriver
{
    protected $endpoint;
    protected $apiKey;

    public function __construct()
    {
        $this->endpoint = config('autotranslator.deepl.endpoint', 'https://api-free.deepl.com/v2/translate');
        $this->apiKey = config('autotranslator.deepl.api_key', null);
        Log::debug('DeepLDriver initialized', ['api_key' => $this->apiKey ? '[redacted]' : 'none']);
    }

    public function translate(string $text, string $from = 'en', string $to = 'ar'): ?string
    {
        if (!$this->apiKey) {
            throw new \Exception('DeepL API key not configured.');
        }
        if (empty(trim($text))) {
            Log::warning('Empty text provided for DeepL translation.');
            return null;
        }

        try {
            $form = [
                'auth_key' => $this->apiKey,
                'text' => $text,
                'source_lang' => strtoupper($from),
                'target_lang' => strtoupper($to),
            ];

            Log::debug('DeepL API request', [
                'endpoint' => $this->endpoint,
                'form' => array_merge($form, ['auth_key' => '[redacted]']),
                'raw_text' => $text,
            ]);

            $response = Http::asForm()->timeout(10)->post($this->endpoint, $form);

            $responseData = [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ];
            Log::debug('DeepL API response', $responseData);

            if ($response->successful()) {
                $data = $response->json();
                $translation = $data['translations'][0]['text'] ?? null;
                if ($translation && $this->isValidTranslation($translation, $text)) {
                    return $translation;
                }
                Log::warning('Invalid translation received for: ' . $text);
                return null;
            }

            $errorMessage = $response->body();
            if ($response->status() === 429 || str_contains($errorMessage, 'Quota')) {
                throw new \Exception('DeepL quota reached.');
            }
            if ($response->status() === 403) {
                throw new \Exception('Invalid DeepL API key.');
            }

            Log::error('DeepL API error: ' . $errorMessage);
            return null;
        } catch (\Exception $e) {
            Log::error('DeepL translation error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function batchTranslate(array $texts, string $from = 'en', string $to = 'ar'): array
    {
        $translations = [];
        foreach ($texts as $text) {
            try {
                $translations[] = $this->translate($text, $from, $to);
            } catch (\Exception $e) {
                Log::warning('DeepL batch translation failed for: ' . $text . '. Error: ' . $e->getMessage());
                $translations[] = null;
            }
        }
        return $translations;
    }

    protected function isValidTranslation(?string $translation, string $original): bool
    {
        if (!$translation || $translation === $original || stripos($translation, 'INVALID') !== false) {
            return false;
        }
        return true;
    }
}