<?php

namespace ibrahimmasha\AutoTranslator;

use Illuminate\Support\ServiceProvider;
use ibrahimmasha\AutoTranslator\Commands\TranslateSetup;
use ibrahimmasha\AutoTranslator\Commands\ScanAndTranslate;

class AutoTranslatorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/autotranslator.php', 'autotranslator');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/autotranslator.php' => config_path('autotranslator.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanAndTranslate::class,
                TranslateSetup::class,
            ]);
        }
    }
}
