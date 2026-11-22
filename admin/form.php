<?php

declare(strict_types=1);

require_once __DIR__ . '/_layout.php';
require_admin();

const ROOT_DIR = __DIR__ . '/..';

$isViewer = is_viewer();
$pdo = null;
$item = null;
$details = [];
$errors = [];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($isViewer && $id) {
    flash_set('error', 'Demo viewer can only view the portfolio list and the add portfolio form.');
    header('Location: index.php');
    exit;
}

try {
    $pdo = db();
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM portfolios WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch() ?: null;
        if (!$item) {
            flash_set('error', 'Portfolio not found.');
            header('Location: index.php');
            exit;
        }
        $stmt = $pdo->prepare('SELECT * FROM portfolio_details WHERE portfolio_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$id]);
        $details = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    flash_set('error', 'Database unavailable.');
    header('Location: index.php');
    exit;
}

$draftKey = $item ? 'edit-' . (int) $item['id'] : 'new';

$vals = [
    'title' => $item['title'] ?? '',
    'category' => $item['category'] ?? CATEGORIES[0],
    'media_mode' => $item['media_mode'] ?? 'image_only',
    'video_url' => $item['video_url'] ?? '',
    'description' => $item['description'] ?? '',
    'tech_tags' => $item['tech_tags'] ?? '',
    'demo_url' => $item['demo_url'] ?? '',
    'github_url' => $item['github_url'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isViewer) {
        $errors[] = 'Demo viewer is read-only. Changes cannot be saved.';
    } elseif (post_exceeds_limit()) {
        $errors[] = 'The upload is larger than the server limit (post_max_size ' . ini_get('post_max_size') . '). Use smaller files.';
    } elseif (!csrf_valid()) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $vals = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'category' => (string) ($_POST['category'] ?? ''),
            'media_mode' => (string) ($_POST['media_mode'] ?? 'image_only'),
            'video_url' => trim((string) ($_POST['video_url'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'tech_tags' => (string) ($_POST['tech_tags'] ?? ''),
            'demo_url' => trim((string) ($_POST['demo_url'] ?? '')),
            'github_url' => trim((string) ($_POST['github_url'] ?? '')),
        ];

        if ($vals['title'] === '') {
            $errors[] = 'Title is required.';
        } elseif (mb_strlen($vals['title']) > 150) {
            $errors[] = 'Title must be at most 150 characters.';
        }
        if (!in_array($vals['category'], CATEGORIES, true)) {
            $errors[] = 'Choose a category.';
        }
        if (!array_key_exists($vals['media_mode'], MEDIA_MODES)) {
            $errors[] = 'Choose a media mode.';
        }

        $tags = $vals['category'] === 'Software Development' ? normalizeTechTags($vals['tech_tags']) : null;
        if ($tags !== null && mb_strlen($tags) > 255) {
            $errors[] = 'Tech tags must be at most 255 characters.';
        }

        $demoUrl = $githubUrl = null;
        try {
            $demoUrl = clean_url($vals['demo_url']);
        } catch (InvalidArgumentException $e) {
            $errors[] = 'Live Demo: ' . $e->getMessage();
        }
        try {
            $githubUrl = clean_url($vals['github_url']);
        } catch (InvalidArgumentException $e) {
            $errors[] = 'GitHub: ' . $e->getMessage();
        }

        $imageFile = $_FILES['main_image'] ?? null;
        $videoFile = $_FILES['main_video'] ?? null;
        $wantsVideo = $vals['media_mode'] !== 'image_only';
        $videoUrl = null;

        if ($wantsVideo && $vals['video_url'] !== '') {
            try {
                $videoUrl = clean_url($vals['video_url']);
            } catch (InvalidArgumentException $e) {
                $errors[] = 'Video Link: ' . $e->getMessage();
            }
        }

        if (!$item && !has_upload($imageFile)) {
            $errors[] = 'Main image is required.';
        }
        if ($wantsVideo && !has_upload($videoFile) && empty($item['main_video']) && $videoUrl === null) {
            $errors[] = 'Provide a Main Video file or a Video Link for this media mode.';
        }
        foreach ([$imageFile, $videoFile] as $file) {
            if ($file !== null && ($message = upload_error_message((int) $file['error'])) !== null) {
                $errors[] = $message;
            }
        }

        if (!$errors) {
            $newFiles = [];
            $oldFiles = [];
            try {
                $pdo->beginTransaction();

                $mainImage = $item['main_image'] ?? null;
                $mainVideo = $item['main_video'] ?? null;
                $ratio = (float) ($item['aspect_ratio'] ?? 1.0);

                if (has_upload($imageFile)) {
                    $stored = store_upload($imageFile, 'covers', IMAGE_MIMES);
                    $newFiles[] = $stored['path'];
                    $ratio = computeAspectRatio(ROOT_DIR . '/' . $stored['path']);
                    if ($item) {
                        $oldFiles[] = $item['main_image'];
                    }
                    $mainImage = $stored['path'];
                }

                if ($wantsVideo) {
                    if (has_upload($videoFile)) {
                        $stored = store_upload($videoFile, 'videos', VIDEO_MIMES);
                        $newFiles[] = $stored['path'];
                        if ($mainVideo) {
                            $oldFiles[] = $mainVideo;
                        }
                        $mainVideo = $stored['path'];
                    }
                } else {
                    if ($mainVideo) {
                        $oldFiles[] = $mainVideo;
                    }
                    $mainVideo = null;
                    $videoUrl = null;
                }

                $fields = [
                    $vals['category'],
                    $vals['title'],
                    $vals['media_mode'],
                    $mainImage,
                    $mainVideo,
                    $videoUrl,
                    $ratio,
                    $vals['description'],
                    $tags,
                    $demoUrl,
                    $githubUrl,
                ];
                if ($item) {
                    $pdo->prepare(
                        'UPDATE portfolios SET category = ?, title = ?, media_mode = ?, main_image = ?, main_video = ?,
                         video_url = ?, aspect_ratio = ?, description = ?, tech_tags = ?, demo_url = ?, github_url = ?
                         WHERE id = ?'
                    )->execute([...$fields, $item['id']]);
                    $portfolioId = (int) $item['id'];
                } else {
                    $pdo->prepare(
                        'INSERT INTO portfolios (category, title, media_mode, main_image, main_video, video_url, aspect_ratio,
                         description, tech_tags, demo_url, github_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute($fields);
                    $portfolioId = (int) $pdo->lastInsertId();
                }

                $deleteIds = array_map('intval', (array) ($_POST['delete_details'] ?? []));
                $captions = (array) ($_POST['existing_captions'] ?? []);
                $nextSort = 0;
                foreach ($details as $detail) {
                    $nextSort = max($nextSort, (int) $detail['sort_order'] + 1);
                    $detailId = (int) $detail['id'];
                    if (in_array($detailId, $deleteIds, true)) {
                        $pdo->prepare('DELETE FROM portfolio_details WHERE id = ? AND portfolio_id = ?')
                            ->execute([$detailId, $portfolioId]);
                        $oldFiles[] = $detail['file_path'];
                    } elseif (isset($captions[$detailId])) {
                        $caption = mb_substr(trim((string) $captions[$detailId]), 0, 150);
                        $pdo->prepare('UPDATE portfolio_details SET caption = ? WHERE id = ? AND portfolio_id = ?')
                            ->execute([$caption === '' ? null : $caption, $detailId, $portfolioId]);
                    }
                }

                $newCaptions = (array) ($_POST['detail_captions'] ?? []);
                foreach (normalize_files($_FILES['details'] ?? null) as $i => $file) {
                    if (!has_upload($file)) {
                        continue;
                    }
                    $stored = store_upload($file, 'details', IMAGE_MIMES + VIDEO_MIMES);
                    $newFiles[] = $stored['path'];
                    $detailRatio = $stored['media_type'] === 'image'
                        ? computeAspectRatio(ROOT_DIR . '/' . $stored['path'])
                        : 0.5625;
                    $caption = mb_substr(trim((string) ($newCaptions[$i] ?? '')), 0, 150);
                    $pdo->prepare(
                        'INSERT INTO portfolio_details (portfolio_id, file_path, aspect_ratio, media_type, caption, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$portfolioId, $stored['path'], $detailRatio, $stored['media_type'], $caption === '' ? null : $caption, $nextSort++]);
                }

                $pdo->commit();
                delete_stored_files($oldFiles);
                $_SESSION['clear_draft_key'] = $draftKey;
                flash_set('success', $item ? 'Portfolio updated.' : 'Portfolio created.');
                header('Location: index.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                delete_stored_files($newFiles);
                $errors[] = ($e instanceof RuntimeException || $e instanceof InvalidArgumentException)
                    ? $e->getMessage()
                    : 'Save failed. Nothing was changed.';
            }
        }
    }
}

$isSoftware = $vals['category'] === 'Software Development';
$wantsVideoView = $vals['media_mode'] !== 'image_only';
$hasExistingVideo = !empty($item['main_video']);

admin_start($item ? 'Edit Portfolio' : 'Add Portfolio', true, 'admin--form');
?>
<div class="admin-head">
    <h1><?= $item ? 'Edit Portfolio' : 'Add Portfolio' ?></h1>
    <a class="btn btn--outline" href="index.php">Back to list</a>
</div>

<?php if ($errors): ?>
    <div class="flash flash--error" role="alert">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form" id="portfolio-form" data-has-video="<?= $hasExistingVideo ? '1' : '0' ?>" data-editing="<?= $item ? '1' : '0' ?>" data-readonly="<?= $isViewer ? '1' : '0' ?>" data-draft-key="<?= e($draftKey) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="10485760">

    <div class="form_section">
        <div class="form_row">
            <div class="field">
                <label for="title">Title</label>
                <input class="input" type="text" id="title" name="title" maxlength="150" value="<?= e($vals['title']) ?>" placeholder="Project or work title" required>
            </div>
            <div class="field">
                <label for="category">Category</label>
                <select class="input" id="category" name="category" required>
                    <?php foreach (CATEGORIES as $category): ?>
                        <option value="<?= e($category) ?>" <?= $category === $vals['category'] ? ' selected' : '' ?>><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="form_section">
        <fieldset class="field">
            <legend>Media Mode</legend>
            <div class="mode-cards">
                <?php foreach (MEDIA_MODES as $mode => $label): ?>
                    <label class="mode-card">
                        <input type="radio" name="media_mode" value="<?= e($mode) ?>" <?= $mode === $vals['media_mode'] ? ' checked' : '' ?>>
                        <span class="mode-card_name"><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="form_media-grid">
            <div class="field">
                <label for="main_image">Main Image<?= $item ? '' : ' *' ?></label>
                <div class="dropzone" data-dropzone="image">
                    <input class="dropzone_input" type="file" id="main_image" name="main_image" accept="image/jpeg,image/png,image/webp" <?= $item ? '' : ' required' ?>>
                    <div class="dropzone_prompt">
                        <span class="dropzone_icon">
                            <img src="../assets/icons/file.svg" alt="" width="20" height="20" aria-hidden="true">
                        </span>
                        <span class="dropzone_text"><strong>Drop cover image</strong> or click</span>
                    </div>
                    <div class="dropzone_preview" id="cover-preview" <?= $item ? '' : ' hidden' ?>>
                        <?php if ($item): ?>
                            <img class="dropzone_thumb" src="../<?= e($item['main_image']) ?>" alt="Current cover of <?= e($item['title']) ?>" width="80" height="80">
                            <span class="dropzone_file-info">
                                <span class="dropzone_file-name">Current cover</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="field" id="field-video" <?= $wantsVideoView ? '' : ' hidden' ?>>
                <label for="main_video">Main Video</label>
                <div class="dropzone" data-dropzone="video">
                    <input class="dropzone_input" type="file" id="main_video" name="main_video" accept="video/mp4,video/webm">
                    <div class="dropzone_prompt">
                        <span class="dropzone_icon">
                            <img src="../assets/icons/file.svg" alt="" width="20" height="20" aria-hidden="true">
                        </span>
                        <span class="dropzone_text"><strong>Drop video</strong> or click</span>
                    </div>
                    <div class="dropzone_preview" id="video-preview" <?= $hasExistingVideo ? '' : ' hidden' ?>>
                        <?php if ($hasExistingVideo): ?>
                            <video class="dropzone_thumb" src="../<?= e($item['main_video']) ?>" preload="metadata" muted playsinline width="80" height="80"></video>
                            <span class="dropzone_file-info">
                                <span class="dropzone_file-name">Attached video</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="field form_video-url" id="field-video-url" <?= $wantsVideoView ? '' : ' hidden' ?>>
                <label for="video_url">Video Link</label>
                <input class="input" type="url" id="video_url" name="video_url" maxlength="255" value="<?= e($vals['video_url']) ?>" placeholder="https://">
            </div>
        </div>
    </div>

    <div class="form_section">
        <div class="field">
            <label for="description">Description</label>
            <textarea class="input" id="description" name="description" rows="4"><?= e($vals['description']) ?></textarea>
        </div>

        <div class="field" id="field-tags" <?= $isSoftware ? '' : ' hidden' ?>>
            <label for="tech_tags">Tech Tags</label>
            <input class="input" type="text" id="tech_tags" name="tech_tags" maxlength="255" value="<?= e($vals['tech_tags']) ?>" placeholder="React, PostgreSQL, Next.js">
            <ul class="modal_stack" id="tag-preview" aria-label="Tag preview"></ul>
        </div>

        <details class="group" id="links-group" <?= ($isSoftware || $vals['demo_url'] !== '' || $vals['github_url'] !== '') ? ' open' : '' ?>>
            <summary>Project Links</summary>
            <div class="form_row">
                <div class="field">
                    <label for="demo_url">Live Demo URL</label>
                    <input class="input" type="url" id="demo_url" name="demo_url" maxlength="255" value="<?= e($vals['demo_url']) ?>" placeholder="https://">
                </div>
                <div class="field">
                    <label for="github_url">GitHub URL</label>
                    <input class="input" type="url" id="github_url" name="github_url" maxlength="255" value="<?= e($vals['github_url']) ?>" placeholder="https://github.com/">
                </div>
            </div>
        </details>
    </div>

    <div class="form_section">
        <?php if ($item && count($details) > 0): ?>
            <fieldset class="field">
                <legend>Existing Details</legend>
                <div class="detail-grid">
                    <?php foreach ($details as $detail): ?>
                        <div class="detail-card">
                            <?php if ($detail['media_type'] === 'video'): ?>
                                <video src="../<?= e($detail['file_path']) ?>" preload="metadata" muted playsinline width="160" height="120"></video>
                            <?php else: ?>
                                <img src="../<?= e($detail['file_path']) ?>" alt="<?= e($detail['caption'] ?? 'Detail image') ?>" width="160" height="120" loading="lazy">
                            <?php endif; ?>
                            <label class="visually-hidden" for="existing-caption-<?= (int) $detail['id'] ?>">Caption for detail <?= (int) $detail['id'] ?></label>
                            <input class="input" type="text" id="existing-caption-<?= (int) $detail['id'] ?>" name="existing_captions[<?= (int) $detail['id'] ?>]" maxlength="150" value="<?= e($detail['caption'] ?? '') ?>" placeholder="Caption">
                            <label class="check">
                                <input type="checkbox" name="delete_details[]" value="<?= (int) $detail['id'] ?>">
                                <span>Delete</span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <div class="field">
            <label for="details"><?= $item ? 'Add Detail Assets' : 'Detail Assets' ?></label>
            <div class="dropzone dropzone--multi" data-dropzone="details">
                <input class="dropzone_input" type="file" id="details" name="details[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
                <div class="dropzone_prompt">
                    <span class="dropzone_icon">
                        <img src="../assets/icons/file.svg" alt="" width="20" height="20" aria-hidden="true">
                    </span>
                    <span class="dropzone_text"><strong>Drop images or videos</strong> or click</span>
                </div>
            </div>
            <div class="detail-grid" id="detail-preview"></div>
        </div>
    </div>

    <div class="form_actions">
        <?php if ($isViewer): ?>
            <button type="submit" class="btn" disabled>Create Portfolio (Read-only)</button>
        <?php else: ?>
            <button type="submit" class="btn"><?= $item ? 'Save Changes' : 'Create Portfolio' ?></button>
        <?php endif; ?>
        <a class="btn btn--outline" href="index.php" data-draft-clear>Cancel</a>
    </div>
</form>
<?php admin_end();
