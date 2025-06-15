<?php

namespace ibrahimamasha\AutoTranslator\Translators;

class TranslatorFactory
{
    public static function make()
    {
        return match (config('translation.driver')) {
            'deepl' => new DeepLDriver(),
            'mymemory' => new MyMemoryDriver(),
            default => new MyMemoryDriver(),
        };
    }
}
