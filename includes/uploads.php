<?php
// Image uploads (receipts, payment QR codes, proof-of-payment screenshots).
// Files live under uploads/<kind>/, which is not web-accessible; they are served through the API after an access check.

declare(strict_types=1);

const UPLOAD_KINDS = ['receipts', 'qr', 'proofs'];

function upload_dir(string $kind): string
{
    if (!in_array($kind, UPLOAD_KINDS, true)) {
        throw new InvalidArgumentException("Unknown upload kind: $kind");
    }
    $dir = config('uploads.dir') . '/' . $kind;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Validate and store an uploaded image from $_FILES[$field].
 * Returns the stored filename, or null when the field was left empty and $required is false.
 */
function save_uploaded_image(string $field, string $kind, string $prefix, bool $required = true): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            fail('Choose a photo to upload.', 422);
        }
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        fail('Upload failed. Try a smaller photo.', 422);
    }
    if ($f['size'] > config('uploads.max_bytes')) {
        fail('Photo is too large (max 8 MB).', 422);
    }
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) {
        fail('Please upload a JPG, PNG or WebP image.', 422);
    }
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], upload_dir($kind) . '/' . $name)) {
        fail('Could not save the photo.', 500);
    }
    return $name;
}

function delete_upload(string $kind, ?string $name): void
{
    if ($name) {
        @unlink(upload_dir($kind) . '/' . basename($name));
    }
}

/** Stream a stored image to the browser and stop. */
function serve_upload(string $kind, ?string $name): void
{
    $file = $name ? upload_dir($kind) . '/' . basename($name) : null;
    if (!$file || !is_file($file)) {
        fail('Image not found.', 404);
    }
    $info = getimagesize($file);
    header('Content-Type: ' . ($info['mime'] ?? 'application/octet-stream'));
    header_remove('Cache-Control');
    header('Cache-Control: private, max-age=3600');
    readfile($file);
    exit;
}
