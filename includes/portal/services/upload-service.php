<?php
/**
 * includes/services/upload-service.php
 * Safe image uploads for gallery, story covers and announcement images.
 *
 * What is checked (the file name and the browser-supplied type are NOT trusted):
 *   - the upload really came through HTTP (is_uploaded_file)
 *   - size is between 1 byte and 5 MB
 *   - the REAL content type (read from the file's bytes) is JPEG, PNG or WebP
 *   - getimagesize() can read it as an image of the same type
 *   - width and height are 1..8000 pixels
 * The validated image is uploaded to Cloudinary; its HTTPS URL is stored in
 * the database so the asset survives Render restarts and redeploys.
 */

const UPLOAD_MAX_BYTES     = 5 * 1024 * 1024;
const UPLOAD_MAX_DIMENSION = 8000;
const UPLOAD_FOLDERS       = ['gallery', 'stories', 'announcements', 'campaigns', 'challenges'];
const UPLOAD_MIME_EXT      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** Did the user pick a file in this form field? */
function upload_provided($file): bool
{
    return is_array($file) && isset($file['error']) && !is_array($file['error'])
        && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
}

/**
 * Check the CONTENT of a file on disk. Returns ['ok'=>true,'mime'=>..,'ext'=>..]
 * or ['ok'=>false,'message'=>..]. Separate from saving so it can be tested alone.
 */
function validate_image_file(string $path, int $size): array
{
    $fail = fn(string $m) => ['ok' => false, 'message' => $m];

    if ($size < 1) {
        return $fail('The uploaded file is empty.');
    }
    if ($size > UPLOAD_MAX_BYTES) {
        return $fail('The image is too large. The maximum size is 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!isset(UPLOAD_MIME_EXT[$mime])) {
        return $fail('Only JPG, PNG or WebP images are allowed.');
    }
    $info = @getimagesize($path);
    if (!$info || ($info['mime'] ?? '') !== $mime) {
        return $fail('The file is not a valid image.');
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > UPLOAD_MAX_DIMENSION || $info[1] > UPLOAD_MAX_DIMENSION) {
        return $fail('The image dimensions are not supported (maximum ' . UPLOAD_MAX_DIMENSION . ' x ' . UPLOAD_MAX_DIMENSION . ' pixels).');
    }
    return ['ok' => true, 'mime' => $mime, 'ext' => UPLOAD_MIME_EXT[$mime]];
}

/**
 * Validate and store one uploaded image ($_FILES['field']) in Cloudinary.
 * Returns ['ok'=>true,'path'=>'https://res.cloudinary.com/...','original_name','mime','size']
 * or ['ok'=>false,'message'=>..].
 */
function save_uploaded_image($file, string $folder): array
{
    $fail = fn(string $m) => ['ok' => false, 'message' => $m];

    if (!in_array($folder, UPLOAD_FOLDERS, true)) {
        return $fail('Invalid upload folder.');
    }
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size']) || is_array($file['error'])) {
        return $fail('No file was uploaded.');
    }

    switch ((int) $file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $fail('The image is too large. The maximum size is 5 MB.');
        case UPLOAD_ERR_PARTIAL:
            return $fail('The upload was interrupted. Please try again.');
        default:
            return $fail('The image could not be uploaded. Please try again.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('The image could not be uploaded. Please try again.');
    }

    $check = validate_image_file($file['tmp_name'], (int) $file['size']);
    if (!$check['ok']) {
        return $check;
    }

    try {
        $uploaded = cloudinary_client()->uploadApi()->upload($file['tmp_name'], [
            'folder' => 'rc2000/' . $folder,
            'resource_type' => 'image',
            'use_filename' => false,
            'unique_filename' => true,
            'overwrite' => false,
        ]);
        $imageUrl = (string) ($uploaded['secure_url'] ?? '');
        if (!preg_match('#^https://res\.cloudinary\.com/[^/]+/image/upload/.+#i', $imageUrl)) {
            return $fail('The image storage service did not return a valid image URL. Please try again.');
        }
    } catch (Throwable $e) {
        error_log('Cloudinary image upload failed (' . get_class($e) . ').');
        return $fail('The image could not be saved to image storage. Please try again.');
    }

    $original = preg_replace('/[\x00-\x1F\x7F]/u', '', basename((string) ($file['name'] ?? '')));
    return [
        'ok'            => true,
        'path'          => $imageUrl,
        'original_name' => mb_substr($original, 0, 255),
        'mime'          => $check['mime'],
        'size'          => (int) $file['size'],
    ];
}

/** Get a configured Cloudinary SDK instance, or fail without exposing credentials. */
function cloudinary_client(): \Cloudinary\Cloudinary
{
    static $client = null;
    if ($client instanceof \Cloudinary\Cloudinary) {
        return $client;
    }

    $config = $GLOBALS['APP_CONFIG'] ?? [];
    $cloudinaryUrl = trim((string) ($config['cloudinary_url'] ?? getenv('CLOUDINARY_URL') ?: ''));
    if ($cloudinaryUrl === '') {
        throw new RuntimeException('Cloudinary is not configured.');
    }

    $autoload = ROOT_PATH . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Cloudinary dependencies are not installed.');
    }
    require_once $autoload;

    $client = new \Cloudinary\Cloudinary($cloudinaryUrl);
    return $client;
}

/** Extract a Cloudinary public ID only from this application's Cloudinary URL. */
function cloudinary_public_id_from_url(string $url): ?string
{
    $urlParts = parse_url($url);
    $config = $GLOBALS['APP_CONFIG'] ?? [];
    $cloudinaryUrl = trim((string) ($config['cloudinary_url'] ?? getenv('CLOUDINARY_URL') ?: ''));
    $cloudName = $cloudinaryUrl !== '' ? parse_url($cloudinaryUrl, PHP_URL_HOST) : null;
    if (!is_array($urlParts) || strtolower((string) ($urlParts['host'] ?? '')) !== 'res.cloudinary.com' || !$cloudName) {
        return null;
    }

    $segments = explode('/', trim((string) ($urlParts['path'] ?? ''), '/'));
    if (count($segments) < 5 || $segments[0] !== $cloudName || $segments[1] !== 'image' || $segments[2] !== 'upload') {
        return null;
    }
    $assetSegments = array_slice($segments, 3);
    if (isset($assetSegments[0]) && preg_match('/^v\d+$/', $assetSegments[0])) {
        array_shift($assetSegments);
    }
    $publicId = rawurldecode(implode('/', $assetSegments));
    $publicId = preg_replace('/\.[A-Za-z0-9]+$/', '', $publicId);
    return $publicId !== '' ? $publicId : null;
}

/** Delete a stored upload locally or remotely after the database update succeeds. */
function delete_uploaded_file(?string $relPath): void
{
    $relPath = trim((string) $relPath);
    if ($relPath === '') {
        return;
    }

    if (preg_match('#^uploads/(gallery|stories|announcements|campaigns|challenges)/[A-Za-z0-9_-][A-Za-z0-9._-]*$#', $relPath)) {
        $full = ROOT_PATH . '/public/' . $relPath;
        if (is_file($full)) {
            @unlink($full);
        }
        return;
    }

    $publicId = cloudinary_public_id_from_url($relPath);
    if ($publicId === null) {
        return;
    }

    try {
        cloudinary_client()->uploadApi()->destroy($publicId, [
            'resource_type' => 'image',
            'type' => 'upload',
            'invalidate' => true,
        ]);
    } catch (Throwable $e) {
        error_log('Cloudinary image deletion failed (' . get_class($e) . ').');
    }
}
