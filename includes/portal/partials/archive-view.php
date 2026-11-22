<?php
/**
 * includes/portal/partials/archive-view.php
 * Archive page body, shared by admin (Review) and super admin (View, read-only).
 * Expects $archive = ['base', 'can_manage', 'filters', 'counts', 'list', 'selected', 'loadError'].
 */
/** @var array $archive Provided by admin/archive/index.php or super_admin/archive.php */
$f = $archive['filters'];
$counts = $archive['counts'];
$list = $archive['list'];
$sel = $archive['selected'];
$canManage = $archive['can_manage'];
$base = $archive['base'];
$actionLabel = $canManage ? 'Review' : 'View';
$baseParams = ['tab' => $f['tab'], 'q' => $f['q']];
$tabs = ['all' => 'All records', 'inquiries' => 'Inquiries', 'donations' => 'Donations', 'activities' => 'Reading activities', 'campaigns' => 'Campaigns', 'content' => 'Website content'];
$cards = [
    ['Inquiries', $counts['inquiries'], ['archive_inquiries_light.png', 'archive_inquiries_dark.png']],
    ['Donations', $counts['donations'], ['manage_donations.png', 'manage_donations_dark.png']],
    ['Reading activities', $counts['activities'], '▤'],
    ['Campaigns', $counts['campaigns'], ['manage_campaigns.png', 'manage_campaigns_dark.png']],
    ['Website content', $counts['content'], ['manage-content-light.png', 'manage-content-dark.png']],
];
?>
<header class="page-header">
    <h1 class="page-title">Archive</h1>
    <p class="page-subtitle">Completed work, kept for reference.</p>
</header>

<?php if ($archive['loadError']): ?>
    <div class="flash flash-error" role="alert"><span><?= e($archive['loadError']) ?></span></div>
<?php endif; ?>

<section class="stat-grid archive-stats" aria-label="Archive summary">
    <?php foreach ($cards as [$label, $n, $icon]): ?>
        <article class="stat-card archive-stat">
            <span class="archive-stat-icon" aria-hidden="true">
                <?php if (is_array($icon)): ?>
                    <img class="archive-theme-icon archive-icon-light" src="<?= e(asset('images/icons/' . $icon[0])) ?>" alt="" width="24" height="24">
                    <img class="archive-theme-icon archive-icon-dark" src="<?= e(asset('images/icons/' . $icon[1])) ?>" alt="" width="24" height="24">
                <?php else: ?>
                    <?= e($icon) ?>
                <?php endif; ?>
            </span>
            <div>
                <div class="stat-label"><?= e($label) ?></div>
                <div class="stat-value"><?= (int) $n ?></div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<div class="archive-toolbar">
    <nav class="archive-tabs" aria-label="Record type">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="archive-tab<?= $f['tab'] === $key ? ' is-active' : '' ?>"
               href="<?= e(archive_url($base, ['tab' => $key, 'q' => $f['q']])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="archive-search-form" method="get" action="<?= e(url($base)) ?>">
        <?php if ($f['tab'] !== 'all'): ?><input type="hidden" name="tab" value="<?= e($f['tab']) ?>"><?php endif; ?>
        <input class="input archive-search" type="search" name="q" value="<?= e($f['q']) ?>"
               placeholder="Search archived records" maxlength="100" aria-label="Search archived records">
    </form>
</div>

<section class="panel" aria-label="Archived records">
    <div class="panel-header"><h2 class="panel-title">Archived records</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Type</th><th>Record</th><th>Status</th><th>Archived</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if (!$list['rows']): ?>
                <tr><td colspan="5" class="table-empty">
                    <?= ($f['q'] !== '' || $f['tab'] !== 'all') ? 'No archived records match your search.' : 'Nothing has been archived yet.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($list['rows'] as $row):
                $isSel = $sel && $sel['type'] === $row['type'] && (int) ($_GET['id'] ?? 0) === (int) $row['id'];
                $openUrl = archive_url($base, $baseParams + ['page' => $list['page'] > 1 ? $list['page'] : '', 'type' => $row['type'], 'id' => (int) $row['id']]);
            ?>
                <tr<?= $isSel ? ' class="is-selected"' : '' ?>>
                    <td><span class="archive-type"><?= e($row['type_label']) ?></span></td>
                    <td class="archive-record"><?= e($row['record']) ?></td>
                    <td><?= archive_badge($row['status_key'], $row['status_label']) ?></td>
                    <td><?= e(format_date($row['archived_at'])) ?></td>
                    <td><a class="archive-action" href="<?= e($openUrl) ?>"><?= e($actionLabel) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($list['pages'] > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($list['page'] > 1): ?>
            <a href="<?= e(archive_url($base, $baseParams + ['page' => $list['page'] - 1 > 1 ? $list['page'] - 1 : ''])) ?>">Previous</a>
        <?php endif; ?>
        <?php for ($p = max(1, $list['page'] - 2); $p <= min($list['pages'], $list['page'] + 2); $p++): ?>
            <?php if ($p === $list['page']): ?>
                <span class="is-current" aria-current="page"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= e(archive_url($base, $baseParams + ['page' => $p > 1 ? $p : ''])) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($list['page'] < $list['pages']): ?>
            <a href="<?= e(archive_url($base, $baseParams + ['page' => $list['page'] + 1])) ?>">Next</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <p class="archive-note">
        <?= $canManage
            ? 'Archived records stay available to admins and are not deleted.'
            : 'Archived records are read-only for super admins. Only administrators can review them.' ?>
    </p>
</section>

<?php if ($sel): ?>
<dialog class="dialog dialog-review archive-dialog" id="review-dialog" aria-labelledby="archive-title" data-autoopen>
    <div class="review-form">
        <header class="dialog-head">
            <div>
                <p class="dialog-kicker"><?= e($sel['type_label']) ?> · Archived</p>
                <h2 class="dialog-title" id="archive-title"><?= e($sel['title']) ?></h2>
            </div>
            <div class="dialog-head-side">
                <?= archive_badge($sel['status_key'], $sel['status_label']) ?>
                <button type="button" class="dialog-x" data-dialog-close aria-label="Close">&times;</button>
            </div>
        </header>

        <div class="dialog-body">
            <section>
                <h3 class="review-heading">Details</h3>
                <dl class="detail-grid">
                    <?php foreach ($sel['fields'] as $field): ?>
                        <?= detail_item($field[0], e($field[1]), !empty($field[2])) ?>
                    <?php endforeach; ?>
                </dl>
            </section>

            <?php if ($sel['image']): ?>
                <section><img class="archive-image" src="<?= e($sel['image']) ?>" alt="" loading="lazy"></section>
            <?php endif; ?>

            <?php if ($sel['body_label'] !== '' && trim($sel['body']) !== ''): ?>
            <section>
                <h3 class="review-heading"><?= e($sel['body_label']) ?></h3>
                <div class="inquiry-message archive-text"><?= nl2br(e($sel['body'])) ?></div>
            </section>
            <?php endif; ?>

            <?php if ($sel['type'] === 'inquiry'): ?>
            <section>
                <h3 class="review-heading">Saved responses</h3>
                <?php if ($sel['responses']): ?>
                    <ul class="response-list">
                    <?php foreach ($sel['responses'] as $r): ?>
                        <li class="response-item">
                            <div class="response-body"><?= nl2br(e($r['body'])) ?></div>
                            <div class="response-meta">
                                <?= e(format_datetime($r['created_at'])) ?><?= $r['admin_name'] ? ' · ' . e($r['admin_name']) : '' ?>
                                · <?= e(inquiry_email_label($r['email_status'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No response was saved for this inquiry.</p>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>

        <footer class="dialog-foot">
            <span class="spacer"></span>
            <button type="button" class="btn btn-light" data-dialog-close>Close</button>
            <?php if ($canManage && $sel['manage_url']): ?>
                <a class="btn btn-dark" href="<?= e($sel['manage_url']) ?>">Open in manage page</a>
            <?php endif; ?>
        </footer>
    </div>
</dialog>
<?php endif; ?>
