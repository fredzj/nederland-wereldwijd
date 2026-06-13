<?php

declare(strict_types=1);

/**
 * Toont alle nood-informatie ("hulp bij nood") uit de tabel `emergency_infos`.
 */

require __DIR__ . '/_bootstrap.php';

$rows = db()
    ->query('SELECT id, title, introduction, content, parent, order_no, data_url, canonical FROM emergency_infos ORDER BY parent, order_no + 0, title')
    ->fetchAll();

// Verzamel alle bekende id's, zodat we "wezen" (een parent die niet bestaat)
// als hoofditem kunnen tonen in plaats van ze te verbergen.
$ids = [];
foreach ($rows as $row) {
    $ids[$row['id']] = true;
}

/**
 * Bepaalt of een rij een hoofditem is: geen parent, of een parent die niet
 * als eigen rij voorkomt.
 *
 * @param array<string, mixed> $row
 */
$isRoot = static function (array $row) use ($ids): bool {
    $parent = $row['parent'] ?? null;
    return $parent === null || $parent === '' || !isset($ids[$parent]);
};

// Groepeer onderliggende items per bovenliggend item (parent).
$children = [];
foreach ($rows as $row) {
    if (!$isRoot($row)) {
        $children[$row['parent']][] = $row;
    }
}

page_header('Nood-informatie', 'emergency.php');
?>

<header class="mb-4">
    <h1 class="h3">Nood-informatie</h1>
    <p class="text-muted mb-0">Wat te doen bij nood in het buitenland.</p>
</header>

<?php if ($rows === []): ?>
    <div class="alert alert-info" role="alert">Er is nog geen nood-informatie beschikbaar.</div>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <?php
        // Toon onderliggende items binnen hun bovenliggende kaart.
        if (!$isRoot($row)) {
            continue;
        }
        $content = render_content(decode_json($row['content']));
        $kids = $children[$row['id']] ?? [];
        ?>
        <article class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h5 card-title"><?= e($row['title'] ?? 'Zonder titel') ?></h2>
                <?php if (!empty($row['introduction'])): ?>
                    <div class="text-muted content-blocks"><?= safe_html($row['introduction']) ?></div>
                <?php endif; ?>
                <?php if ($content !== ''): ?>
                    <div class="content-blocks"><?= $content ?></div>
                <?php endif; ?>

                <?php $source = $row['data_url'] ?? $row['canonical'] ?? null; ?>
                <?php if (!empty($source)): ?>
                    <p class="mt-3 mb-0 small">
                        <a href="<?= e($source) ?>" rel="noopener noreferrer" target="_blank">Bron</a>
                    </p>
                <?php endif; ?>

                <?php if ($kids !== []): ?>
                    <div class="accordion mt-3" id="acc-<?= e($row['id']) ?>">
                        <?php foreach ($kids as $i => $kid): ?>
                            <?php
                            $kidContent = render_content(decode_json($kid['content']));
                            $collapseId = 'c-' . e($kid['id']);
                            ?>
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#<?= $collapseId ?>" aria-expanded="false"
                                            aria-controls="<?= $collapseId ?>">
                                        <?= e($kid['title'] ?? 'Zonder titel') ?>
                                    </button>
                                </h3>
                                <div id="<?= $collapseId ?>" class="accordion-collapse collapse"
                                     data-bs-parent="#acc-<?= e($row['id']) ?>">
                                    <div class="accordion-body content-blocks">
                                        <?php if (!empty($kid['introduction'])): ?>
                                            <div class="text-muted content-blocks"><?= safe_html($kid['introduction']) ?></div>
                                        <?php endif; ?>
                                        <?= $kidContent !== '' ? $kidContent : '<p class="text-muted mb-0">Geen verdere inhoud.</p>' ?>
                                        <?php $kidSource = $kid['data_url'] ?? $kid['canonical'] ?? null; ?>
                                        <?php if (!empty($kidSource)): ?>
                                            <p class="mt-3 mb-0 small">
                                                <a href="<?= e($kidSource) ?>" rel="noopener noreferrer" target="_blank">Bron</a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php page_footer(); ?>
