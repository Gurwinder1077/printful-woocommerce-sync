# PrintFul → WooCommerce Product Sync

A small PHP tool that creates a print-on-demand product once and publishes it to **both PrintFul and WooCommerce** — with all the colour/size variants generated automatically.

You paste an artwork image URL, tick the sizes and colours you want, and the tool:

1. **PrintFul** — matches your chosen colour/size combinations to PrintFul variants, creates a new sync product with your artwork, waits for the mockups to render, and sets the first mockup as the thumbnail.
2. **WooCommerce** — creates a matching **variable product** with `Size` and `Color` attributes and one variation per combination, via the WooCommerce REST API.

Built to remove a tedious manual step for a print-on-demand store (creating the same product twice, by hand, in two systems).

## How it works

```
index.php (form)
    │  artwork URL + selected sizes/colours
    ▼
functions.php
    ├─ CreatePrintProd → PrintFul API  (create product, read mockups, set thumbnail)
    └─ CreateWooProd   → WooCommerce REST API  (variable product + variations)
```

## Tech

PHP · cURL · PrintFul API · WooCommerce REST API · jQuery · SweetAlert2

## Setup

1. Copy `config.example.php` to `config.php`.
2. Fill in your PrintFul API token + store id, and your WooCommerce store URL + REST API key/secret.
   *(`config.php` is git-ignored, so your keys are never committed.)*
3. Serve the folder with PHP and open `index.php`.

## Notes

This is a focused utility, not a full app. Credentials live only in `config.php`; nothing sensitive is hard-coded.
