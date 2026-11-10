<?php
/**
 * PrintFul → WooCommerce product sync (backend).
 *
 * Flow:
 *   1) CreatePrintProd — reads a base PrintFul product, matches the chosen
 *      colour/size variants, creates a new PrintFul sync product with the
 *      uploaded artwork, and returns its id + preview image.
 *   2) CreateWooProd  — creates the matching WooCommerce variable product
 *      with Size/Colour attributes and one variation per combination.
 *
 * All credentials are loaded from config.php (see config.example.php) — nothing
 * sensitive is hard-coded.
 */

$config = file_exists(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

// ── helpers ────────────────────────────────────────────────────────────────
function printful_headers(array $cfg): array {
    return [
        'X-PF-Store-Id: ' . $cfg['printful_store_id'],
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['printful_token'],
    ];
}

function woo_headers(array $cfg): array {
    return [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($cfg['woo_key'] . ':' . $cfg['woo_secret']),
    ];
}

function http_request(string $url, string $method, array $headers, string $body = ''): array {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true) ?? [];
}

// ── routing ─────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
if ($action === 'CreatePrintProd') {
    CreatePrintProd($config);
} elseif ($action === 'CreateWooProd') {
    CreateWooProd($config);
}

// ── 1) create the product in PrintFul ───────────────────────────────────────
function CreatePrintProd(array $cfg): void {
    $image  = $_POST['imgUrl'] ?? '';
    $sizes  = $_POST['sizes']  ?? [];
    $colors = $_POST['colors'] ?? [];

    $base = 'https://api.printful.com';

    // Read a base product to map the selected colour/size → PrintFul variant ids.
    $data = http_request("$base/store/products/31803", 'GET', printful_headers($cfg));
    $variants = $data['result']['sync_variants'] ?? [];

    $catalog = [];
    foreach ($variants as $v) {
        $catalog[] = ['name' => $v['name'], 'variant' => $v['variant_id']];
    }

    $wanted = [];
    foreach ($sizes as $size) {
        foreach ($colors as $color) {
            $wanted[] = "$color / $size";
        }
    }

    $variantIds = [];
    foreach ($catalog as $item) {
        foreach ($wanted as $pattern) {
            if (strpos($item['name'], $pattern) !== false) {
                $variantIds[] = $item['variant'];
                break;
            }
        }
    }

    // Build the new PrintFul sync product with the uploaded artwork.
    $sync_variants = [];
    foreach ($variantIds as $variant) {
        $sync_variants[] = [
            'variant_id' => $variant,
            'files'      => [['image' => $image, 'url' => $image]],
        ];
    }

    $payload = json_encode([
        'sync_product'  => [
            'name'         => 'New Product',
            'image'        => $image,
            'thumbnail'    => $image,
            'retail_price' => '21.00',
            'currency'     => 'USD',
            'files'        => [['image' => $image, 'url' => $image]],
        ],
        'sync_variants' => $sync_variants,
    ]);

    $created = http_request("$base/store/products", 'POST', printful_headers($cfg), $payload);
    $id = $created['result']['id'] ?? null;

    // PrintFul needs a moment to render the mockups.
    sleep(15);

    $product = http_request("$base/store/products/$id", 'GET', printful_headers($cfg));
    $id          = $product['result']['sync_product']['id'] ?? $id;
    $external_id = $product['result']['sync_product']['external_id'] ?? '';
    $name        = $product['result']['sync_product']['name'] ?? '';

    $mockURLs = [];
    foreach (($product['result']['sync_variants'] ?? []) as $variant) {
        foreach ($variant['files'] as $file) {
            if (($file['type'] ?? '') === 'preview') {
                $mockURLs[] = $file['preview_url'];
            }
        }
    }
    $preview_url = $mockURLs[0] ?? '';

    // Use the first mockup as the product thumbnail.
    http_request("$base/store/products/$id", 'PUT', printful_headers($cfg),
        json_encode(['sync_product' => ['thumbnail' => $preview_url]]));

    echo json_encode([
        'id'           => $id,
        'name'         => $name,
        'externalID'   => $external_id,
        'previewImage' => $preview_url,
        'varcolors'    => $colors,
        'varsizes'     => $sizes,
    ]);
}

// ── 2) create the matching variable product in WooCommerce ───────────────────
function CreateWooProd(array $cfg): void {
    $printId     = $_POST['printfulId'] ?? '';
    $name        = $_POST['name'] ?? '';
    $externalID  = $_POST['externalID'] ?? '';
    $preview_url = $_POST['previewImage'] ?? '';
    $varcolors   = $_POST['varcolors'] ?? [];
    $varsizes    = $_POST['varsizes'] ?? [];

    $store = rtrim($cfg['woo_store_url'], '/');

    $product_data = [
        'name'           => $name,
        'type'           => 'variable',
        'regular_price'  => '19.99',
        'sku'            => $printId,
        'purchasable'    => true,
        'meta_data'      => [['key' => 'printfulID', 'value' => $externalID]],
        'stock_quantity' => 2200,
        'images'         => [['src' => $preview_url]],
        'attributes'     => [
            ['name' => 'Size',  'position' => 0, 'visible' => true, 'variation' => true, 'options' => $varsizes],
            ['name' => 'Color', 'position' => 1, 'visible' => true, 'variation' => true, 'options' => $varcolors],
        ],
        'categories'     => [['id' => $cfg['woo_category_id']]],
        'variations'     => [],
    ];

    $created = http_request("$store/wp-json/wc/v3/products", 'POST', woo_headers($cfg), json_encode($product_data));
    $productId = $created['id'] ?? null;

    // One variation per colour × size combination.
    $permalink = [];
    foreach ($varsizes as $size) {
        foreach ($varcolors as $color) {
            $variation = [
                'attributes'    => [
                    ['name' => 'Size',  'option' => $size],
                    ['name' => 'Color', 'option' => $color],
                ],
                'regular_price' => '20.00',
            ];
            $res = http_request("$store/wp-json/wc/v3/products/$productId/variations", 'POST', woo_headers($cfg), json_encode($variation));
            if (!empty($res['permalink'])) $permalink[] = $res['permalink'];
        }
    }

    echo $permalink[0] ?? '';
}
