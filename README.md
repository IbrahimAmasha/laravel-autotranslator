# Laravel AutoTranslator

**Laravel AutoTranslator** is a simple Laravel package to automate translating your language files — ideal for bilingual projects like Arabic/English.

It scans your code for translation keys, fills in missing entries in `lang/en.json`, and auto-translates them into Arabic (`lang/ar.json`) using **MyMemory** or **DeepL**.

---

## Features

- Scans Blade and PHP files for `__()` and `@lang()` keys  
- Auto-generates `lang/en.json` and `lang/ar.json`  
- Uses **MyMemory** by default (free, no API key)  
- Fallback support for **DeepL** (requires API key, 500k characters/month free)  
- Works with just two Artisan commands  

---

## Installation

```bash
composer require ibrahim/autotranslator
```

---

## Configuration (Optional)

Publish the config file:

```bash
php artisan vendor:publish --tag=config
```

Example `config/autotranslator.php`:

```php
return [
    'source' => 'en',
    'target' => 'ar',
    'driver' => 'mymemory', // default: free, no key needed

    'mymemory' => [
        'email' => env('MYMEMORY_EMAIL'),
        'key' => env('MYMEMORY_KEY'),
    ],

    'deepl' => [
        'key' => env('DEEPL_KEY'), // required if using DeepL
    ],
];
```

> MyMemory is the default driver and works without an API key.  
> DeepL offers more accurate results, but requires an API key (500,000 free characters/month).

---

## Usage

### 1. Create translation files

```bash
php artisan translate:setup
```

### 2. Scan and translate

```bash
php artisan translate:scan
```

---

## Example

For this code:

```php
__('Dashboard')
@lang('No messages yet')
```

The package generates:

```json
// lang/en.json
{
  "Dashboard": "Dashboard",
  "No messages yet": "No messages yet"
}

// lang/ar.json
{
  "Dashboard": "لوحة التحكم",
  "No messages yet": "لا توجد رسائل بعد"
}
```

---

## Author

**Ibrahim Amasha**  
[LinkedIn](https://www.linkedin.com/in/ibrahim-amasha-24199a230)

---

## License

MIT License
