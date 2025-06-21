<?php

namespace ibrahimmasha\AutoTranslator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslateSetup extends Command
{
    protected $signature = 'translate:setup';
    protected $description = 'Set up the auto-translator package configuration and API keys.';

    protected $availableDrivers = ['mymemory', 'deepl'];

    public function handle()
    {
        $this->info('Setting up AutoTranslator package...');

        // Display introductory message
        $this->info('AutoTranslator Summary: MyMemory (default) is free, no API key, quota ≈15,000 chars/day. DeepL is more accurate (up to 500,000/mo) but requires API key & credit card info .');

        $this->line('To change or unlock higher limits at any time, run: php artisan translate:setup');
        
        $this->newLine();

        // Publish configuration file if not exists
        if (!File::exists(config_path('autotranslator.php'))) {
            $this->call('vendor:publish', [
                '--provider' => 'Ibrahim\autotranslator.AutoTranslatorServiceProvider',
                '--tag' => 'config',
            ]);
        }

        // Explain pros and cons for primary driver selection
 
        // Prompt for primary driver
        $primaryDriver = $this->choice(
            'Select the primary translation driver',
            $this->availableDrivers,
            0 // Default to 'mymemory'
        );
        $this->updateEnv('AUTOTRANSLATOR_DRIVER', $primaryDriver);

        // Prompt for DeepL API key if selected as primary driver
        if ($primaryDriver === 'deepl' && $this->confirm('Do you want to configure a DeepL API key? (required for DeepL)?')) {
            $deeplApiKey = $this->ask('Enter your DeepL API key (get one at https://www.deepl.com/pro-api)');
            if ($deeplApiKey) {
                $this->updateEnv('DEEPL_API_KEY', $deeplApiKey);
            }
        }

        // Prompt for fallback driver
        $this->line('Choose a fallback driver (used when primary driver fails, e.g., MyMemory quota reached):');
        $fallbackDriver = $this->choice(
            'Select the fallback translation driver',
            $this->availableDrivers,
            $primaryDriver === 'mymemory' ? 1 : 0 // Default to opposite of primary
        );
        $this->updateEnv('AUTOTRANSLATOR_FALLBACK_DRIVER', $fallbackDriver);

        // Prompt for DeepL API key if selected as fallback driver
        if ($fallbackDriver === 'deepl' && $this->confirm('Do you want to configure a DeepL API key for fallback (500,000 chars/month free)?')) {
            $deeplApiKey = $this->ask('Enter your DeepL API key (get one at https://www.deepl.com/pro-api)');
            if ($deeplApiKey) {
                $this->updateEnv('DEEPL_API_KEY', $deeplApiKey);
            }
        }

        // Clear cache to apply changes
        $this->call('config:clear');
        $this->call('cache:clear');

        $this->info('AutoTranslator setup complete. You can now use `php artisan translate:scan` to start translating your application.');
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
        }
    }
}
