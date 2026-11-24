<?php if ($items): ?>
    <div class="card-grid three">
        <?php foreach ($items as $index => $item): ?>
            <?php
            $title = $item['title'] ?? 'Untitled ' . ucfirst($contentType);
            $body = $item['body'] ?? '';
            $cover = $item['cover_image_path'] ?? '';
            $url = app_url('public/content.php') . '?id=' . (int) $item['content_id'];
            ?>
            <article class="story-card">
                <?php if ($cover !== '' && media_file_exists($cover)): ?>
                    <img src="<?= e(media_url($cover)) ?>" alt="<?= e($title) ?>" loading="lazy">
                <?php else: ?>
                    <div class="card-art art-<?= ($index % 4) + 1 ?>" aria-hidden="true"></div>
                <?php endif; ?>
                <div class="story-card-content">
                    <h3><?= e($title) ?></h3>
                    <?php if (trim($body) !== ''): ?>
                        <p><?= e(excerpt($body, 180)) ?></p>
                    <?php endif; ?>
                    <a class="text-link" href="<?= e($url) ?>">Read <?= e($contentType) ?> →</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>No published <?= $contentType === 'article' ? 'articles' : 'stories' ?> are available yet.</p>
<?php endif; ?>
