<?php

namespace ibrahimmasha\autotranslator;

use Illuminate\Support\ServiceProvider;
use ibrahimmasha\autotranslator\Commands\TranslateSetup;
use ibrahimmasha\autotranslator\Commands\ScanAndTranslate;

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
