<?php

namespace ibrahimmasha\autotranslator\Translators;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MyMemoryDriver
{
    protected $endpoint;
    protected $apiKey;

    public function __construct()
    {
        $this->endpoint = config('autotranslator.mymemory.endpoint', 'https://api.mymemory.translated.net/get');
        $this->apiKey = config('autotranslator.api_key', null);
    }

    public function translate(string $text, string $from = 'en', string $to = 'ar'): ?string
    {
        try {
            $query = [
                'q' => $text,
                'langpair' => "$from|$to",
            ];
            if ($this->apiKey) {
                $query['key'] = $this->apiKey;
            }


            $response = Http::timeout(10)->get($this->endpoint, $query);

            $responseData = [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ];

            if ($response->successful()) {
                $data = $response->json();
                $translation = $data['responseData']['translatedText'] ?? null;
                if ($translation && $this->isValidTranslation($translation, $text)) {
                    return $translation;
                }
                return null;
            }

            $errorMessage = $response->body();
            $data = $response->json();
            $responseDetails = $data['responseDetails'] ?? $errorMessage;

            if ($response->status() === 429 || str_contains($errorMessage, 'Quota') || str_contains($errorMessage, 'limit reached')) {
                $resetInfo = $this->extractResetInfo($responseDetails);
                $errorMsg = "MyMemory daily translation quota reached.{$resetInfo}";
                if ($this->apiKey && str_contains($responseDetails, 'FREE TRANSLATIONS')) {
                    $errorMsg .= ' API key seems invalid or not applied; free quota used instead.';
                }
                throw new \Exception($errorMsg, 429);
            }
            if ($response->status() === 401 || str_contains($errorMessage, 'Invalid API key')) {
                throw new \Exception('Invalid MyMemory API key provided.');
            }

            return null;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function batchTranslate(array $texts, string $from = 'en', string $to = 'ar'): array
    {
        $translations = [];
        $maxQueryLength = 500; // MyMemory API query length limit
        $chunks = [];
        $currentChunk = [];
        $currentLength = 0;
    
        // Dynamically create chunks based on character length
        foreach ($texts as $text) {
            $textLength = strlen($text) + 1; // Include newline character
            if ($currentLength + $textLength > $maxQueryLength && !empty($currentChunk)) {
                // Start a new chunk
                $chunks[] = $currentChunk;
                $currentChunk = [$text];
                $currentLength = $textLength;
            } else {
                $currentChunk[] = $text;
                $currentLength += $textLength;
            }
        }
        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }
    
        foreach ($chunks as $chunk) {
            try {
                $query = [
                    'q' => implode("\n", $chunk),
                    'langpair' => "$from|$to",
                ];
                if ($this->apiKey) {
                    $query['key'] = $this->apiKey;
                }
    
    
                $response = Http::timeout(15)->get($this->endpoint, $query);
    
                $responseData = [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                ];
    
                if ($response->successful()) {
                    $data = $response->json();
                    $translated = $data['responseData']['translatedText'] ?? null;
                    if ($translated) {
                        $translatedArray = explode("\n", $translated);
                        foreach ($chunk as $index => $text) {
                            $translation = $translatedArray[$index] ?? null;
                            $translations[] = $translation && $this->isValidTranslation($translation, $text) ? $translation : null;
                        }
                    } else {
                        $translations = array_merge($translations, array_fill(0, count($chunk), null));
                    }
                } else {
                    $errorMessage = $response->body();
                    $data = $response->json();
                    $responseDetails = $data['responseDetails'] ?? $errorMessage;
    
                    if ($response->status() === 429 || str_contains($errorMessage, 'Quota') || str_contains($errorMessage, 'limit reached')) {
                        $resetInfo = $this->extractResetInfo($responseDetails);
                        $errorMsg = "MyMemory daily translation quota reached.{$resetInfo}";
                        if ($this->apiKey && str_contains($responseDetails, 'FREE TRANSLATIONS')) {
                            $errorMsg .= ' API key seems invalid or not applied; free quota used instead.';
                        }
                        throw new \Exception($errorMsg, 429);
                    }
                    if ($response->status() === 401 || str_contains($errorMessage, 'Invalid API key')) {
                        throw new \Exception('Invalid MyMemory API key provided.');
                    }
                    $translations = array_merge($translations, array_fill(0, count($chunk), null));
                }
            } catch (\Exception $e) {
                throw $e;
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

    protected function extractResetInfo(string $responseDetails): string
    {
        if (preg_match('/NEXT AVAILABLE IN\s+(\d+\s+HOURS\s+\d+\s+MINUTES\s+\d+\s+SECONDS)/i', $responseDetails, $matches)) {
            return " Next available in {$matches[1]}.";
        }
        return '';
    }
}