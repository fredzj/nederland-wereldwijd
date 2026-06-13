<?php

declare(strict_types=1);

/**
 * Laat een land kiezen en toont vervolgens de landgegevens en het bijbehorende
 * reisadvies uit de tabellen `countries` en `travel_advices`.
 */

require __DIR__ . '/_bootstrap.php';

$pdo = db();

$countries = $pdo
    ->query('SELECT iso_code, location FROM countries ORDER BY location')
    ->fetchAll();

$selectedIso = isset($_GET['iso']) ? strtoupper(trim((string) $_GET['iso'])) : '';

$country = null;
$advices = [];

if ($selectedIso !== '') {
    $stmt = $pdo->prepare('SELECT * FROM countries WHERE iso_code = :iso');
    $stmt->execute(['iso' => $selectedIso]);
    $country = $stmt->fetch() ?: null;

    if ($country !== null) {
        $stmt = $pdo->prepare(
            'SELECT id, title, introduction, classification, content, files, location, last_modified_at
             FROM travel_advices
             WHERE country_iso_code = :iso
             ORDER BY title'
        );
        $stmt->execute(['iso' => $selectedIso]);
        $advices = $stmt->fetchAll();
    }
}

page_header('Landen & reisadvies', 'country.php');
?>

<header class="mb-4">
    <h1 class="h3">Landen &amp; reisadvies</h1>
    <p class="text-muted mb-0">Kies een land om de gegevens en het reisadvies te bekijken.</p>
</header>

<form method="get" class="row g-2 align-items-end mb-4">
    <div class="col-12 col-md-6 col-lg-5">
        <label for="iso" class="form-label">Land</label>
        <select class="form-select" id="iso" name="iso" onchange="this.form.submit()">
            <option value="">— Kies een land —</option>
            <?php foreach ($countries as $c): ?>
                <option value="<?= e($c['iso_code']) ?>"<?= $c['iso_code'] === $selectedIso ? ' selected' : '' ?>>
                    <?= e($c['location']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Tonen</button>
    </div>
</form>

<?php if ($selectedIso !== '' && $country === null): ?>
    <div class="alert alert-warning" role="alert">Geen land gevonden voor code "<?= e($selectedIso) ?>".</div>
<?php elseif ($country !== null): ?>
    <section class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <h2 class="h4 card-title mb-0"><?= e($country['location']) ?></h2>
                <span class="badge text-bg-secondary align-self-center"><?= e($country['iso_code']) ?></span>
            </div>
            <dl class="row mt-3 mb-0 small">
                <?php if (!empty($country['type'])): ?>
                    <dt class="col-sm-3">Type</dt>
                    <dd class="col-sm-9"><?= e($country['type']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($country['travel_advice_url'])): ?>
                    <dt class="col-sm-3">Reisadvies</dt>
                    <dd class="col-sm-9">
                        <a href="<?= e($country['travel_advice_url']) ?>" rel="noopener" target="_blank">
                            <?= e($country['travel_advice_url']) ?>
                        </a>
                    </dd>
                <?php endif; ?>
                <?php if (!empty($country['nl_representation_url'])): ?>
                    <dt class="col-sm-3">NL-vertegenwoordiging</dt>
                    <dd class="col-sm-9">
                        <a href="<?= e($country['nl_representation_url']) ?>" rel="noopener" target="_blank">
                            <?= e($country['nl_representation_url']) ?>
                        </a>
                    </dd>
                <?php endif; ?>
            </dl>
        </div>
    </section>

    <h2 class="h5 mb-3">Reisadvies</h2>

    <?php if ($advices === []): ?>
        <div class="alert alert-info" role="alert">Voor dit land is geen reisadvies beschikbaar.</div>
    <?php else: ?>
        <?php foreach ($advices as $advice): ?>
            <?php
            $content = render_content(decode_json($advice['content']));
            $files = render_files(decode_json($advice['files']));
            ?>
            <article class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <h3 class="h6 card-title mb-0"><?= e($advice['title'] ?? 'Reisadvies') ?></h3>
                    </div>
                    <?php if (!empty($advice['introduction'])): ?>
                        <div class="text-muted content-blocks mt-2"><?= safe_html($advice['introduction']) ?></div>
                    <?php endif; ?>
                    <?php if ($files !== ''): ?>
                        <div class="content-blocks mt-2"><?= $files ?></div>
                    <?php endif; ?>
                    <?php if ($content !== ''): ?>
                        <div class="content-blocks mt-2"><?= $content ?></div>
                    <?php endif; ?>
                    <?php if (!empty($advice['last_modified_at'])): ?>
                        <p class="text-muted small mb-0 mt-2">Laatst gewijzigd: <?= e($advice['last_modified_at']) ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php page_footer(); ?>
