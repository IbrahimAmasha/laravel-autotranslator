<?php

namespace ibrahimamasha\AutoTranslator;

use Illuminate\Support\ServiceProvider;
use ibrahimamasha\AutoTranslator\Commands\TranslateSetup;
use ibrahimamasha\AutoTranslator\Commands\ScanAndTranslate;

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
