<?php

declare(strict_types=1);

/**
 * Eenvoudige startpagina met links naar de twee weergaven.
 */

require __DIR__ . '/_bootstrap.php';

page_header('Home', 'index.php');
?>

<header class="mb-4">
    <h1 class="h3">Nederland Wereldwijd</h1>
    <p class="text-muted mb-0">Bekijk nood-informatie, landen en reisadviezen uit de open data.</p>
</header>

<div class="row g-3">
    <div class="col-12 col-md-6">
        <a href="emergency.php" class="card shadow-sm h-100 text-decoration-none text-reset">
            <div class="card-body">
                <h2 class="h5 card-title">Nood-informatie</h2>
                <p class="card-text text-muted mb-0">Wat te doen bij nood in het buitenland.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6">
        <a href="country.php" class="card shadow-sm h-100 text-decoration-none text-reset">
            <div class="card-body">
                <h2 class="h5 card-title">Landen &amp; reisadvies</h2>
                <p class="card-text text-muted mb-0">Kies een land en bekijk het bijbehorende reisadvies.</p>
            </div>
        </a>
    </div>
</div>

<?php page_footer(); ?>
