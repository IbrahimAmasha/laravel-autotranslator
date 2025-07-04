<?php

namespace ibrahimmasha\autotranslator\Translators;

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
    }

    public function translate(string $text, string $from = 'en', string $to = 'ar'): ?string
    {
        if (!$this->apiKey) {
            throw new \Exception('DeepL API key not configured.');
        }
        if (empty(trim($text))) {
            return null;
        }

        try {
            $form = [
                'auth_key' => $this->apiKey,
                'text' => $text,
                'source_lang' => strtoupper($from),
                'target_lang' => strtoupper($to),
            ];


            $response = Http::asForm()->timeout(10)->post($this->endpoint, $form);

            $responseData = [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ];

            if ($response->successful()) {
                $data = $response->json();
                $translation = $data['translations'][0]['text'] ?? null;
                if ($translation && $this->isValidTranslation($translation, $text)) {
                    return $translation;
                }
                return null;
            }

            $errorMessage = $response->body();
            if ($response->status() === 429 || str_contains($errorMessage, 'Quota')) {
                throw new \Exception('DeepL quota reached.');
            }
            if ($response->status() === 403) {
                throw new \Exception('Invalid DeepL API key.');
            }

            return null;
        } catch (\Exception $e) {
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