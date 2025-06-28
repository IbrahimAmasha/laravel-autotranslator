# 🌍 Laravel AutoTranslator

**Laravel AutoTranslator** is a developer-friendly Laravel package that automates the process of translating your language files — especially useful for bilingual (Arabic/English) projects.  
It detects all translation keys in your Blade templates and controllers, fills in missing entries in `lang/en.json`, and generates accurate translations in `lang/ar.json` using **MyMemory** or **DeepL**.

---

## ✨ Features

- 🔍 Scans your code for `__()` and `@lang()` keys  
- 🧠 Auto-generates `lang/en.json` and `lang/ar.json`  
- ⚡ Translates missing strings using **MyMemory** _(free, no API key required)_  
- 🔁 Fallback to **DeepL** _(requires API key — 500,000 free characters/month)_  
- 🛠️ Simple 2-command usage  
- 🆓 No API key needed for MyMemory

---

## 📦 Installation

Install the package using Composer:

```bash
composer require ibrahim/autotranslator
```

---

## ⚙️ Configuration (Optional)

To customize source/target languages or configure drivers:

```bash
php artisan vendor:publish --tag=config
```

This creates `config/autotranslator.php`:

```php
return [
    'source' => 'en',
    'target' => 'ar',

    'driver' => 'mymemory', // or 'deepl'

    'mymemory' => [
        'email' => env('MYMEMORY_EMAIL'), // optional
        'key' => env('MYMEMORY_KEY'),     // optional
    ],

    'deepl' => [
        'key' => env('DEEPL_KEY'),        // required if using DeepL
    ],
];
```

> 🔁 If `mymemory` fails or rate-limits, you can switch to `deepl` for better accuracy and reliability.  
> DeepL offers a free tier with up to **500,000 characters/month**. [Get an API key here](https://www.deepl.com/pro-api).

---

## 🧪 Commands

### 🔧 Step 1 — Setup translation files

This command creates default `lang/en.json` and `lang/ar.json` if they don’t exist.

```bash
php artisan translate:setup
```

---

### 🌐 Step 2 — Scan and translate

Scans your app for translation keys, adds them to `en.json`, and auto-translates them into Arabic.

```bash
php artisan translate:scan
```

---

## 📁 Example

If you use this in a controller or Blade view:

```php
__('Dashboard')
@lang('No messages yet')
```

The package will generate:

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

## 💡 Tips

- Change source and target languages in `config/autotranslator.php`  
- Use `php artisan translate:scan` anytime to detect & update new strings  
- Add your API key if you need more reliability or switch to DeepL  

---

## 👤 Author

**Ibrahim Amasha**  
Laravel Developer & Open Source Contributor  
🔗 [GitHub](https://github.com/your-github-username)

---

## 🪪 License

This package is open-sourced under the [MIT license](LICENSE).
