<?php
/**
 * Copy this file to `config.php` and fill in your own credentials.
 * `config.php` is git-ignored, so your keys are never committed.
 */

return [
    // PrintFul API — https://developers.printful.com/
    'printful_token'    => 'YOUR_PRINTFUL_API_TOKEN',
    'printful_store_id' => 'YOUR_PRINTFUL_STORE_ID',

    // WooCommerce REST API — WooCommerce → Settings → Advanced → REST API
    'woo_store_url'     => 'https://your-store.example.com',
    'woo_key'           => 'ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'woo_secret'        => 'cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',

    // Default WooCommerce category id for new products
    'woo_category_id'   => 16,
];
