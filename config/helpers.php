<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const CATEGORIES = [
    'Graphic Design',
    'Video Editing',
    'Presentation Design',
    'Software Development',
];

const MEDIA_MODES = [
    'image_only' => 'Image',
    'hover_video' => 'Hover Video',
    'autoplay_video' => 'Auto-play Video',
];

const IMAGE_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

const VIDEO_MIMES = [
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
];

const UPLOAD_ROOT = __DIR__ . '/../assets/uploads';

/* Primary contact email; shown as the mailto CTA in the contact band. */
const CONTACT_EMAIL = 'mufaddhol02@gmail.com';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function asset_url(string $path, string $prefix = ''): string
{
    $full = __DIR__ . '/../' . ltrim($path, '/');
    $ver = is_file($full) ? (string) filemtime($full) : '1';
    return $prefix . ltrim($path, '/') . '?v=' . $ver;
}

/* Auth, CSRF, and flash messages */

function require_admin(): void
{
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* Tags and aspect ratios */

function normalizeTechTags(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $parts = array_map('trim', explode(',', $raw));
    $parts = array_filter($parts, fn($tag) => $tag !== '');
    $unique = [];
    $seen = [];
    foreach ($parts as $tag) {
        $key = mb_strtolower($tag);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $tag;
        }
    }
    return empty($unique) ? null : implode(', ', $unique);
}

function splitTags(?string $tags): array
{
    if ($tags === null || trim($tags) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $tags)), fn($t) => $t !== ''));
}

function computeAspectRatio(string $tmpPath): float
{
    $size = @getimagesize($tmpPath);
    if ($size === false) {
        return 1.0;
    }
    [$width, $height] = $size;
    $ratio = ($width > 0) ? round($height / $width, 4) : 1.0;
    return max(0.45, min(2.2, $ratio));
}

/* URL and input validation */

function clean_url(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }
    if (mb_strlen($url) > 255 || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('URL must start with http:// or https:// and be at most 255 characters.');
    }
    return $url;
}

/* Uploads */

function ini_bytes(string $key): int
{
    $value = trim((string) ini_get($key));
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (int) $value;
    return match ($unit) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => $number,
    };
}

function post_exceeds_limit(): bool
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $max = ini_bytes('post_max_size');
    return $_SERVER['REQUEST_METHOD'] === 'POST' && $max > 0 && $length > $max;
}

function upload_error_message(int $code): ?string
{
    return match ($code) {
        UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE => null,
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A file exceeds the upload size limit (' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_PARTIAL => 'A file was only partially uploaded.',
        default => 'A file could not be uploaded.',
    };
}

function normalize_files(?array $files): array
{
    if ($files === null || !isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return [$files];
    }
    $out = [];
    foreach ($files['name'] as $i => $name) {
        $out[] = [
            'name' => $name,
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }
    return $out;
}

function has_upload(?array $file): bool
{
    return $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Validates one uploaded file by real MIME type and moves it into
 * assets/uploads/{subdir}. Returns the stored relative path and media type.
 *
 * @param array<string,string> $allowed MIME => extension
 */
function store_upload(array $file, string $subdir, array $allowed): array
{
    $error = upload_error_message((int) $file['error']);
    if ($error !== null) {
        throw new RuntimeException($error);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported file type: ' . $mime . '.');
    }

    $dir = UPLOAD_ROOT . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Upload directory is not writable.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return [
        'path' => 'assets/uploads/' . $subdir . '/' . $name,
        'media_type' => isset(VIDEO_MIMES[$mime]) ? 'video' : 'image',
    ];
}

function delete_stored_file(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '' || !str_starts_with($relativePath, 'assets/uploads/')) {
        return;
    }
    $full = realpath(__DIR__ . '/../' . $relativePath);
    $root = realpath(UPLOAD_ROOT);
    if ($full !== false && $root !== false && str_starts_with($full, $root . DIRECTORY_SEPARATOR) && is_file($full)) {
        @unlink($full);
    }
}

function delete_stored_files(array $paths): void
{
    foreach ($paths as $path) {
        delete_stored_file($path);
    }
}

/* Card markup shared by index.php and portfolio.php */

function render_card(array $row, string $prefix = ''): string
{
    $id = (int) $row['id'];
    $mode = $row['media_mode'];
    $category = e($row['category']);
    $tags = mb_strtolower(implode(',', splitTags($row['tech_tags'])));
    $title = e($row['title']);
    $image = e($prefix . $row['main_image']);
    $video = $row['main_video'] ? e($prefix . $row['main_video']) : '';
    $ratioFloat = (float) $row['aspect_ratio'];
    if ($ratioFloat <= 0) {
        $ratioFloat = 1.0;
    }
    $ratio = number_format($ratioFloat, 4, '.', '');
    $intrinsicWidth = 640;
    $intrinsicHeight = max(288, (int) round($intrinsicWidth * $ratioFloat));

    $mediaBadge = '';
    if ($mode === 'autoplay_video' && $video !== '') {
        $media = '<video poster="' . $image . '" data-src="' . $video . '" width="' . $intrinsicWidth . '" height="' . $intrinsicHeight . '" preload="none" muted loop playsinline disablePictureInPicture controlsList="nodownload"></video>';
        $mediaBadge = '<span class="porto-card_badge" aria-hidden="true"><svg viewBox="0 0 16 16" width="12" height="12"><path d="M5 3.5v9l7-4.5-7-4.5Z" fill="currentColor"/></svg></span>';
    } else {
        $media = '<img src="' . $image . '" alt="' . $title . '" width="' . $intrinsicWidth . '" height="' . $intrinsicHeight . '" loading="lazy" decoding="async" draggable="false">';
        if ($mode === 'hover_video' && $video !== '') {
            $media .= '<video data-src="' . $video . '" width="' . $intrinsicWidth . '" height="' . $intrinsicHeight . '" preload="none" muted loop playsinline disablePictureInPicture controlsList="nodownload" aria-hidden="true"></video>';
            $mediaBadge = '<span class="porto-card_badge" aria-hidden="true"><svg viewBox="0 0 16 16" width="12" height="12"><path d="M5 3.5v9l7-4.5-7-4.5Z" fill="currentColor"/></svg></span>';
        }
    }

    return '<article class="porto-card" data-id="' . $id . '" data-mode="' . e($mode) . '"'
        . ' data-ratio="' . $ratio . '" style="--ratio:' . $ratio . '" data-category="' . $category . '"'
        . ' data-tags="' . e($tags) . '" data-title="' . e(mb_strtolower($row['title'])) . '">'
        . '<div class="porto-card_media">' . $media . $mediaBadge . '</div>'
        . '<div class="porto-card_shade">'
        . '<span class="porto-card_meta">' . $category . '</span>'
        . '<h3 class="porto-card_title">' . $title . '</h3>'
        . '</div>'
        . '<a class="porto-card_hit" href="detail.php?id=' . $id . '" aria-label="Open ' . $title . ' (' . $category . ')"></a>'
        . '</article>';
}
