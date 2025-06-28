<?php

namespace ibrahim masha\autotranslator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use ibrahimmasha\autotranslator\Translators\DeepLDriver;
use ibrahimmasha\autotranslator\Translators\MyMemoryDriver;

class ScanAndTranslate extends Command
{
    protected $signature = 'translate:scan {--lang= : Target language (e.g., fr, es, de)} {--source= : Source language (e.g., en, fr, es)} {--driver= : Translation driver (mymemory, deepl)} {--silent : Suppress detailed translation output}';
    protected $description = 'Scan all files for translation keys and translate missing ones to the target language using the specified driver.';

    protected $validLanguageCodes = ['en', 'ar', 'fr', 'es', 'de', 'it', 'zh', 'ja', 'ko', 'ru', 'pt', 'nl', 'sv', 'tr', 'pl', 'hi'];

    public function handle()
    {
        $sourceLang = $this->option('source') ?? config('autotranslator.source', 'en');
        $targetLang = $this->option('lang') ?? config('autotranslator.target', 'ar');
        $driverName = $this->option('driver') ?? config('autotranslator.driver', 'mymemory');
        $outputFormat = config('autotranslator.output_format', 'json');
        $fallbackDriver = config('autotranslator.fallback_driver', 'deepl');

  
        if ($driverName === 'mymemory') {
            $this->line("MyMemory is currently set as the primary driver with an anonymous quota (~15,000 chars/day). For higher limits or better quality, consider switching to DeepL or adding a DeepL API key for fallback by running: php artisan translate:setup");
        } else {
            $this->line("DeepL is set as the primary driver. Ensure a valid DeepL API key is configured. To change drivers or add a fallback, run: php artisan translate:setup");
        }
        $this->newLine();

        if (!$this->isValidLanguageCode($sourceLang)) {
            $this->error("Invalid source language code: {$sourceLang}. Supported codes: " . implode(', ', $this->validLanguageCodes));
            return 1;
        }
        if (!$this->isValidLanguageCode($targetLang)) {
            $this->error("Invalid target language code: {$targetLang}. Supported codes: " . implode(', ', $this->validLanguageCodes));
            return 1;
        }
        if ($sourceLang === $targetLang) {
            $this->error('Source and target languages cannot be the same.');
            return 1;
        }

        try {
            $driver = $this->getDriver($driverName);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->info('Run `php artisan translate:setup` to set up the translator.');
            return 1;
        }

        $source = $this->loadTranslations($sourceLang, 'source', $outputFormat);
        if ($source === null) {
            $this->error("Failed to load source translations for {$sourceLang}");
            return 1;
        }

        $target = $this->loadTranslations($targetLang, 'target', $outputFormat);
        if ($target === null) {
            $this->warn("Target translations not found or invalid for {$targetLang}. Creating empty translations.");
            $target = [];
        }

        $directories = config('autotranslator.directories', [resource_path('views'), app_path(), base_path('Modules')]);
        $patterns = config('autotranslator.patterns', []);
        $foundKeys = [];

        $this->info('Scanning directories for translation keys...');
        $files = collect();
        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php'])) {
                    $files->push($file);
                }
            }
        }

        $this->withProgressBar($files, function ($file) use ($patterns, &$foundKeys) {
            $contents = @file_get_contents($file->getPathname());
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $contents, $matches)) {
                    foreach ($matches[1] as $match) {
                        $foundKeys[$match] = $match;
                    }
                }
            }
        });

        foreach ($foundKeys as $key => $value) {
            if (!array_key_exists($key, $source)) {
                $source[$key] = $value;
            }
        }

        $missing = array_diff_key($source, $target);

        if (empty($missing)) {
            $this->info('No missing translations.');
            return 0;
        }

        $texts = array_values($missing);
        $charCount = array_sum(array_map('strlen', $texts));
        if ($charCount > 15000 && $driverName === 'mymemory') {
            $this->warn("High character count ($charCount). MyMemory anonymous quota is ~15,000 characters/day. Consider using DeepL fallback.");
        }

        try {
            $results = $this->translateWithFallback($driver, $texts, $sourceLang, $targetLang);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'MyMemory daily translation quota reached')) {
                $this->error($e->getMessage());
                $this->line('You can:');
                $this->line('1. Wait until the quota resets (see reset time above).');
                $this->line('2. Use DeepL as a fallback (500,000 characters/month free).');
                $this->info('Run `php artisan translate:setup` to set up or update API keys.');
                return 1;
            }
            if (str_contains($e->getMessage(), 'DeepL')) {
                $this->error($e->getMessage());
                $this->line('Generate a DeepL API key at https://www.deepl.com/pro-api and update DEEPL_API_KEY in .env.');
                $this->info('Run `php artisan translate:setup` to set up or update an API key.');
                return 1;
            }
            $this->error("Translation failed: {$e->getMessage()}");
            return 1;
        }

        $translations = [];
        $failedTranslations = 0;
        $this->info('Translating missing keys...');
        $missingKeys = array_keys($missing);
        $totalKeys = count($missingKeys);
        $currentKey = 0;

        $this->withProgressBar($missingKeys, function ($key) use ($results, $source, &$translations, $missingKeys, $totalKeys, &$currentKey, &$failedTranslations) {
            $currentKey++;
            $index = array_search($key, $missingKeys);
            $translation = $results[$index] ?? $source[$key];
            if ($translation === $source[$key] || $translation === null) {
                $this->warn("Translation failed for: {$key}. Using original text.");
                $translation = $source[$key];
                $failedTranslations++;
            }
            $translations[$key] = $translation;
            if (!$this->option('silent')) {
                $this->line("Translated {$currentKey}/{$totalKeys}: {$key}: {$source[$key]} -> {$translation}");
            }
        }, $totalKeys);

        $target = array_merge($target, $translations);

        try {
            $this->saveTranslations($targetLang, $target, $outputFormat);
            $this->saveTranslations($sourceLang, $source, $outputFormat);
        } catch (\Exception $e) {
            $this->error("Failed to save translation files: {$e->getMessage()}");
            return 1;
        }

        $this->info("Translation complete. Processed $totalKeys missing keys.");
        if ($failedTranslations > 0) {
            $this->warn("Failed translations: $failedTranslations. Check logs for details.");
        }
        $this->info("Characters processed this run: $charCount");

        return 0;
    }

    protected function translateWithFallback($driver, array $texts, string $sourceLang, string $targetLang): array
    {
        try {
            return $driver->batchTranslate($texts, $sourceLang, $targetLang);
        } catch (\Exception $e) {
            if ($e->getCode() === 429 && get_class($driver) === MyMemoryDriver::class) {
                $this->error($e->getMessage());
                if ($this->confirm('MyMemory quota reached. Use DeepL fallback (500,000 characters/month free)?')) {
                    if (!config('autotranslator.deepl.api_key')) {
                        $deeplKey = $this->ask('Enter your DeepL API key (get one at https://www.deepl.com/pro-api)');
                        if ($deeplKey) {
                            $this->updateEnv('DEEPL_API_KEY', $deeplKey);
                            $this->call('config:clear');
                        } else {
                            throw new \Exception('DeepL API key not provided.');
                        }
                    }
                    try {
                        $fallbackDriver = new DeepLDriver();
                        return $fallbackDriver->batchTranslate($texts, $sourceLang, $targetLang);
                    } catch (\Exception $fallbackE) {
                        throw new \Exception("DeepL fallback failed: {$fallbackE->getMessage()}");
                    }
                } else {
                    throw $e;
                }
            }
            throw $e;
        }
    }

    protected function updateEnv($key, $value)
    {
        $path = base_path('.env');
        if (File::exists($path)) {
            $content = File::get($path);
            $pattern = "/^{$key}=.*$/m";
            $replacement = "{$key}={$value}";
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                $content .= PHP_EOL . $replacement;
            }
            File::put($path, $content);
        } else {
            $this->error('.env file not found. Please create it and add the key manually.');
            throw new \Exception('.env file not found.');
        }
    }

    protected function getDriver(string $driverName)
    {
        $driverName = strtolower($driverName);
        if ($driverName === 'mymemory') {
            return new MyMemoryDriver();
        } elseif ($driverName === 'deepl') {
            return new DeepLDriver();
        }
        throw new \Exception("Invalid driver: {$driverName}. Supported drivers: mymemory, deepl");
    }

    protected function loadTranslations(string $lang, string $type, string $format): ?array
    {
        if ($format === 'json') {
            return $this->loadJsonFile(lang_path("{$lang}.json"), $type);
        }

        $translations = [];
        $langDirectories = config('autotranslator.lang_directories', [lang_path()]);
        foreach ($langDirectories as $baseDir) {
            $langDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $lang;
            if (is_dir($langDir)) {
                $files = glob("{$langDir}/*.php");
                foreach ($files as $file) {
                    $group = pathinfo($file, PATHINFO_FILENAME);
                    $data = include $file;
                    if (is_array($data)) {
                        foreach ($data as $key => $value) {
                            $translations["{$group}.{$key}"] = $value;
                        }
                    }
                }
            }
        }
        return $translations;
    }

    protected function saveTranslations(string $lang, array $translations, string $format)
    {
        if ($format === 'json') {
            $filePath = lang_path("{$lang}.json");
            file_put_contents($filePath, json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return;
        }

        $grouped = [];
        foreach ($translations as $key => $value) {
            $parts = explode('.', $key, 2);
            $group = $parts[0];
            $subKey = $parts[1] ?? $key;
            $grouped[$group][$subKey] = $value;
        }

        $langDirectories = config('autotranslator.lang_directories', [lang_path()]);
        $primaryLangDir = rtrim($langDirectories[0], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $lang;
        if (!is_dir($primaryLangDir)) {
            mkdir($primaryLangDir, 0755, true);
        }

        foreach ($grouped as $group => $keys) {
            $filePath = "{$primaryLangDir}/{$group}.php";
            $content = "<?php\n\nreturn " . var_export($keys, true) . ";\n";
            file_put_contents($filePath, $content);
        }
    }

    protected function loadJsonFile(string $filePath, string $type): ?array
    {
        if (!file_exists($filePath)) {
            Log::warning("{$type} file does not exist: {$filePath}");
            return [];
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            Log::error("Unable to read {$type} file: {$filePath}");
            return null;
        }

        if (empty(trim($content))) {
            Log::warning("{$type} file is empty: {$filePath}");
            return [];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Invalid JSON in {$type} file: {$filePath}. Error: " . json_last_error_msg());
            return null;
        }

        return is_array($data) ? $data : [];
    }

    protected function isValidLanguageCode(string $code): bool
    {
        return in_array(strtolower($code), $this->validLanguageCodes);
    }
}