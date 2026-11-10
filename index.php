<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrintFul → WooCommerce — Create Product</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; margin:0; display:flex; justify-content:center; align-items:center; min-height:100vh; }
        .upload-form { background:#fff; border:1px solid #ccc; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.1); width:400px; text-align:center; }
        .upload-form h2 { margin:0 0 20px; font-size:1.5rem; }
        .upload-form label { font-weight:bold; display:block; margin-bottom:5px; }
        .upload-form input[type="text"] { width:93%; padding:10px; border:1px solid #ccc; border-radius:6px; margin-bottom:15px; }
        .upload-form input[type="text"]:focus { border-color:#007bff; }
        .upload-form h3 { font-size:1.2rem; margin-bottom:10px; }
        .checkbox-group { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin-bottom:15px; }
        .checkbox-item { display:flex; align-items:center; }
        .checkbox-group input[type="checkbox"] { display:none; }
        label.checkbox-label { display:inline-block; padding:6px 12px; border:1px solid #ccc; border-radius:4px; cursor:pointer; }
        input[type="checkbox"]:checked + label.checkbox-label { background:#007bff; color:#fff; border-color:#007bff; }
        button[type="submit"] { background:#007bff; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; }
        button[type="submit"]:hover { background:#0056b3; }
    </style>
</head>
<body>
    <div class="upload-form">
        <h2>Create Product</h2>
        <label for="url">Artwork image URL:</label>
        <input type="text" id="url" name="url" placeholder="https://example.com/image.jpg" required>

        <h3>Select Size:</h3>
        <div class="size-options checkbox-group">
            <?php foreach (["XS","S","M","L","XL","2XL","3XL","4XL","5XL"] as $size): ?>
                <div class="checkbox-item">
                    <input type="checkbox" id="size-<?= $size ?>" name="size[]" value="<?= $size ?>">
                    <label class="checkbox-label" for="size-<?= $size ?>"><?= $size ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <h3>Select Color:</h3>
        <div class="color-options checkbox-group">
            <?php foreach (["Black","Navy","Brown","Red","Yellow","Army","Pink","Light Blue","Silver","White","Berry","Gold","Mustard"] as $color): ?>
                <div class="checkbox-item">
                    <input type="checkbox" id="color-<?= $color ?>" name="color[]" value="<?= $color ?>">
                    <label class="checkbox-label" for="color-<?= $color ?>"><?= $color ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" id="urlBtn" name="submit">Create</button>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.27/dist/sweetalert2.all.min.js"></script>
    <script>
    $(function () {
        $("#urlBtn").click(function () {
            var URL = $("#url").val();
            if (!URL) return;

            var sizes  = $(".size-options input:checked").map(function () { return $(this).val(); }).get();
            var colors = $(".color-options input:checked").map(function () { return $(this).val(); }).get();

            if (sizes.length === 0 || colors.length === 0) {
                Swal.fire({ title: 'Please select at least one size and one color.', icon: 'warning' });
                return;
            }

            Swal.fire({ title: 'Creating in PrintFul…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            // Step 1 — create the product in PrintFul
            $.post("functions.php", { action: "CreatePrintProd", imgUrl: URL, sizes: sizes, colors: colors }, function (response) {
                var r = JSON.parse(response);
                Swal.fire({
                    title: 'Created in PrintFul',
                    text: 'Product ' + r.id + ' is ready. Create it in WooCommerce?',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Create in WooCommerce',
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'Creating in WooCommerce…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                    // Step 2 — create the matching product in WooCommerce
                    $.post("functions.php", {
                        action: "CreateWooProd",
                        printfulId: r.id, name: r.name, externalID: r.externalID,
                        previewImage: r.previewImage, varcolors: colors, varsizes: sizes
                    }, function (link) {
                        Swal.close();
                        if (link) window.location.href = link;
                    });
                });
            });
        });
    });
    </script>
</body>
</html>
