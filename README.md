# 🌍 Laravel AutoTranslator

**Laravel AutoTranslator** is a developer-friendly Laravel package that automates the process of translating your language files — especially useful for bilingual (Arabic/English) projects.  
It detects all translation keys in your Blade templates and controllers, fills in missing entries in `lang/en.json`, and generates accurate translations in `lang/ar.json` using the **MyMemory API** — all with **no API key required**.

---

## ✨ Features

- 🔍 Scans your code for `__()` and `@lang()` keys
- 🧠 Auto-generates `lang/en.json` and `lang/ar.json`
- ⚡ Translates missing strings using **MyMemory**
- 🆓 No API key required (optional API key support for higher limits)
- 🛠️ Simple 2-command usage

---

## 📦 Installation

Install the package using Composer:

```bash
composer require ibrahimamasha/autotranslator
