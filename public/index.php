<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$statement = db()->query(
    'SELECT s.name, s.description, s.duration, s.price, c.name AS category_name
     FROM services s
     INNER JOIN service_categories c ON c.id = s.category_id
     WHERE s.active = 1
     ORDER BY c.name, s.name
     LIMIT 6'
);
$services = $statement->fetchAll();
$pageTitle = 'Nowoczesna stomatologia bez pośpiechu';
require dirname(__DIR__) . '/app/views/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> Twoje miejsce na zdrowy uśmiech</span>
            <h1>Uśmiech, o który <em>dbamy razem.</em></h1>
            <p class="hero-description">Nowoczesna stomatologia, uważni specjaliści i spokojna atmosfera. Wybierz opiekę dopasowaną do siebie.</p>
            <div class="hero-actions">
                <a class="button" href="<?= e(app_url('register.php')) ?>">Utwórz konto <span aria-hidden="true">↗</span></a>
                <a class="quiet-link" href="#oferta">Poznaj naszą ofertę <span aria-hidden="true">↓</span></a>
            </div>
            <div class="hero-proof">
                <div class="avatar-stack" aria-hidden="true"><span>A</span><span>P</span><span>M</span></div>
                <p><strong>Z troską o każdy uśmiech</strong><small>Dołącz do pacjentów Lume Dental</small></p>
            </div>
        </div>
        <div class="hero-art" aria-label="Przyjazna, nowoczesna opieka stomatologiczna">
            <div class="art-orbit orbit-one"></div>
            <div class="art-orbit orbit-two"></div>
            <div class="art-sun"></div>
            <div class="art-tooth" aria-hidden="true">✦</div>
            <div class="art-note note-top"><span class="note-icon">✳</span><span><strong>Delikatna opieka</strong><small>komfort na każdym etapie</small></span></div>
            <div class="art-note note-bottom"><span class="note-check">✓</span><span><strong>Twój czas, Twoje tempo</strong><small>rezerwacje online już wkrótce</small></span></div>
            <span class="art-caption">MAŁE KROKI. ZDROWY UŚMIECH.</span>
        </div>
    </div>
</section>
<section class="trust-strip" aria-label="Atuty gabinetu">
    <div class="container trust-grid">
        <div><span>01</span><strong>Uważni specjaliści</strong></div>
        <div><span>02</span><strong>Nowoczesne metody</strong></div>
        <div><span>03</span><strong>Przyjazna atmosfera</strong></div>
        <div><span>04</span><strong>Wygodne konto online</strong></div>
    </div>
</section>
<section class="section services-section" id="oferta">
    <div class="container">
        <div class="section-heading">
            <div><span class="eyebrow">NASZA OFERTA</span><h2>Opieka, której <em>potrzebujesz.</em></h2></div>
            <p>Od profilaktyki po specjalistyczne leczenie — zaplanujemy je wspólnie, w Twoim tempie.</p>
        </div>
        <?php if ($services === []): ?>
            <div class="notice notice-info">Oferta gabinetu jest w przygotowaniu. Wróć do nas wkrótce.</div>
        <?php else: ?>
            <div class="service-grid">
                <?php foreach ($services as $index => $service): ?>
                    <article class="service-card">
                        <span class="service-number"><?= sprintf('%02d', $index + 1) ?></span>
                        <span class="service-category"><?= e($service['category_name']) ?></span>
                        <h3><?= e($service['name']) ?></h3>
                        <p><?= e($service['description']) ?></p>
                        <div class="service-meta">
                            <span><?= (int) $service['duration'] ?> min</span>
                            <strong><?= e(number_format((float) $service['price'], 2, ',', ' ')) ?> zł</strong>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="section-cta"><span>Masz pytania? Z chęcią pomożemy.</span><a class="quiet-link" href="<?= e(app_url('register.php')) ?>">Załóż konto i poznaj nas bliżej <span aria-hidden="true">↗</span></a></div>
    </div>
</section>
<section class="about-section" id="o-nas">
    <div class="container about-grid">
        <div class="about-illustration"><span class="about-circle"></span><span class="about-spark">✳</span><span class="about-letter">l</span><span class="about-label">LUME DENTAL STUDIO · OD 2018</span></div>
        <div class="about-copy"><span class="eyebrow">DOBRZE, ŻE JESTEŚ</span><h2>Wizyta u dentysty może być <em>spokojna.</em></h2><p>W Lume łączymy nowoczesną wiedzę z życzliwym podejściem. Tłumaczymy każdy etap leczenia i wspólnie wybieramy rozwiązania, które są dla Ciebie najlepsze.</p><a class="quiet-link" href="<?= e(app_url('register.php')) ?>">Poznaj Lume <span aria-hidden="true">↗</span></a></div>
    </div>
</section>
<?php require dirname(__DIR__) . '/app/views/footer.php'; ?>
