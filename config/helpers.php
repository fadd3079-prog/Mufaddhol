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

function render_markdown_inline(string $text): string
{
    $codes = [];
    $escaped = e($text);
    $escaped = preg_replace_callback('/`([^`\n]+)`/', static function (array $m) use (&$codes): string {
        $key = "\x01CODE" . count($codes) . "\x01";
        $codes[$key] = '<code>' . $m[1] . '</code>';
        return $key;
    }, $escaped);

    $escaped = preg_replace_callback(
        '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/i',
        static fn(array $m): string => '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>',
        $escaped
    );
    $escaped = preg_replace('/\*\*\*([^\s*](?:[^*]*?[^\s*])?)\*\*\*|(?<!\w)___([^\s_](?:[^_]*?[^\s_])?)___(?!\w)/', '<strong><em>$1$2</em></strong>', $escaped);
    $escaped = preg_replace('/\*\*([^\s*](?:[^*]*?[^\s*])?)\*\*|(?<!\w)__([^\s_](?:[^_]*?[^\s_])?)__(?!\w)/', '<strong>$1$2</strong>', $escaped);
    $escaped = preg_replace('/~~([^\s~](?:[^~]*?[^\s~])?)~~/', '<del>$1</del>', $escaped);
    $escaped = preg_replace('/(?<!\*)\*([^\s*](?:[^*]*?[^\s*])?)\*(?!\*)|(?<!\w)_([^\s_](?:[^_]*?[^\s_])?)_(?!\w)/', '<em>$1$2</em>', $escaped);

    return $codes ? strtr($escaped, $codes) : $escaped;
}

/**
 * Lightweight, XSS-safe native Markdown renderer for portfolio descriptions.
 */
function render_markdown(?string $raw): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $raw));
    if ($text === '') {
        return '';
    }

    $lines = explode("\n", $text);
    $html = [];
    $paragraph = [];
    $listType = null;
    $listItems = [];
    $quoteLines = [];
    $inCodeBlock = false;
    $codeLines = [];

    $flushParagraph = static function () use (&$paragraph, &$html): void {
        if (!$paragraph) {
            return;
        }
        $rendered = array_map(static fn(string $line): string => render_markdown_inline($line), $paragraph);
        $html[] = '<p>' . implode("<br>\n", $rendered) . '</p>';
        $paragraph = [];
    };

    $flushList = static function () use (&$listType, &$listItems, &$html): void {
        if (!$listItems || $listType === null) {
            return;
        }
        $items = array_map(static fn(string $item): string => '<li>' . render_markdown_inline($item) . '</li>', $listItems);
        $html[] = '<' . $listType . '>' . implode('', $items) . '</' . $listType . '>';
        $listType = null;
        $listItems = [];
    };

    $flushQuote = static function () use (&$quoteLines, &$html): void {
        if (!$quoteLines) {
            return;
        }
        $rendered = array_map(static fn(string $line): string => render_markdown_inline($line), $quoteLines);
        $html[] = '<blockquote><p>' . implode("<br>\n", $rendered) . '</p></blockquote>';
        $quoteLines = [];
    };

    $flushCode = static function () use (&$inCodeBlock, &$codeLines, &$html): void {
        if (!$inCodeBlock) {
            return;
        }
        $html[] = '<pre><code>' . e(implode("\n", $codeLines)) . '</code></pre>';
        $inCodeBlock = false;
        $codeLines = [];
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (preg_match('/^```/', $trimmed)) {
            if ($inCodeBlock) {
                $flushCode();
            } else {
                $flushParagraph();
                $flushList();
                $flushQuote();
                $inCodeBlock = true;
                $codeLines = [];
            }
            continue;
        }

        if ($inCodeBlock) {
            $codeLines[] = $line;
            continue;
        }

        if ($trimmed === '') {
            $flushParagraph();
            $flushList();
            $flushQuote();
            continue;
        }

        if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $html[] = '<hr>';
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $tag = strlen($m[1]) === 1 ? 'h3' : 'h4';
            $html[] = '<' . $tag . '>' . render_markdown_inline($m[2]) . '</' . $tag . '>';
            continue;
        }

        if (preg_match('/^>\s?(.*)$/', $trimmed, $m)) {
            $flushParagraph();
            $flushList();
            $quoteLines[] = $m[1];
            continue;
        }

        if (preg_match('/^[-*+]\s+(.+)$/', $trimmed, $m)) {
            $flushParagraph();
            $flushQuote();
            if ($listType !== 'ul') {
                $flushList();
                $listType = 'ul';
            }
            $listItems[] = $m[1];
            continue;
        }

        if (preg_match('/^\d+[.)]\s+(.+)$/', $trimmed, $m)) {
            $flushParagraph();
            $flushQuote();
            if ($listType !== 'ol') {
                $flushList();
                $listType = 'ol';
            }
            $listItems[] = $m[1];
            continue;
        }

        if ($listType !== null && preg_match('/^\s{2,}\S/', $line) && $listItems) {
            $lastIdx = count($listItems) - 1;
            $listItems[$lastIdx] .= ' ' . $trimmed;
            continue;
        }

        $flushList();
        $flushQuote();
        $paragraph[] = $trimmed;
    }

    $flushCode();
    $flushParagraph();
    $flushList();
    $flushQuote();

    return implode("\n", $html);
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

/**
 * Parses a public video URL (YouTube, Vimeo, Google Drive, TikTok, Instagram)
 * into an iframe embed URL when supported, or returns null embed_url for fallback links.
 *
 * @return array{provider:string,embed_url:?string,aspect:string,portrait:bool,url:string}
 */
function parse_external_video(string $url): array
{
    $url = trim($url);
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $host = preg_replace('/^www\./', '', $host);
    $path = (string) ($parts['path'] ?? '');
    parse_str((string) ($parts['query'] ?? ''), $query);

    if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
        $id = null;
        $isShort = false;
        if ($host === 'youtu.be') {
            $id = explode('/', trim($path, '/'))[0] ?? '';
        } elseif (preg_match('#^/(shorts|embed|live)/([A-Za-z0-9_-]{6,20})#', $path, $m)) {
            $id = $m[2];
            $isShort = $m[1] === 'shorts';
        } elseif (!empty($query['v']) && is_string($query['v'])) {
            $id = $query['v'];
        }
        if ($id !== null && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
            return [
                'provider' => 'YouTube',
                'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0',
                'aspect' => $isShort ? '9 / 16' : '16 / 9',
                'portrait' => $isShort,
                'url' => $url,
            ];
        }
    }

    if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
        if (preg_match('#/(?:video/)?(\d{5,15})(?:$|[/?])#', $path, $m)) {
            return [
                'provider' => 'Vimeo',
                'embed_url' => 'https://player.vimeo.com/video/' . $m[1],
                'aspect' => '16 / 9',
                'portrait' => false,
                'url' => $url,
            ];
        }
    }

    if ($host === 'drive.google.com') {
        $id = null;
        if (preg_match('#^/file/d/([A-Za-z0-9_-]{10,80})#', $path, $m)) {
            $id = $m[1];
        } elseif (!empty($query['id']) && is_string($query['id']) && preg_match('/^[A-Za-z0-9_-]{10,80}$/', $query['id'])) {
            $id = $query['id'];
        }
        if ($id !== null) {
            return [
                'provider' => 'Google Drive',
                'embed_url' => 'https://drive.google.com/file/d/' . $id . '/preview',
                'aspect' => '16 / 9',
                'portrait' => false,
                'url' => $url,
            ];
        }
    }

    if (in_array($host, ['tiktok.com', 'm.tiktok.com'], true)) {
        if (preg_match('#/video/(\d{8,25})#', $path, $m)) {
            return [
                'provider' => 'TikTok',
                'embed_url' => 'https://www.tiktok.com/embed/v2/' . $m[1],
                'aspect' => '9 / 16',
                'portrait' => true,
                'url' => $url,
            ];
        }
    }

    if ($host === 'instagram.com') {
        if (preg_match('#^/(reel|reels|p|tv)/([A-Za-z0-9_-]{5,30})#', $path, $m)) {
            $type = $m[1] === 'reels' ? 'reel' : $m[1];
            return [
                'provider' => 'Instagram',
                'embed_url' => 'https://www.instagram.com/' . $type . '/' . $m[2] . '/embed',
                'aspect' => '9 / 16',
                'portrait' => true,
                'url' => $url,
            ];
        }
    }

    return [
        'provider' => 'External Video',
        'embed_url' => null,
        'aspect' => '16 / 9',
        'portrait' => false,
        'url' => $url,
    ];
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
        } elseif ($mode !== 'image_only' && !empty($row['video_url'])) {
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
