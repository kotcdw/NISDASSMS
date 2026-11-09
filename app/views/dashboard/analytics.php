<?php
/* Analytic Dashboard. Expects $cards (key => rendered HTML), $terms, $term, $asOf, $tab ('today'|'growth'), $growthOk. */
$growth = $tab === 'growth';
$kpis = array_intersect_key($cards, array_flip($growth ? Analytics::GROWTH_KPIS : Analytics::KPIS));
$panels = array_intersect_key($cards, array_flip($growth ? Analytics::GROWTH_PANELS : Analytics::PANELS));
$tq = $term ? ['term' => $term['id']] : [];
?>
<div class="an<?= $growth ? ' an-is-growth' : '' ?>" data-live="<?= e(url('dashboard/live', $tq + ($growth ? ['tab' => 'growth'] : []))) ?>" data-live-secs="60" data-tab="<?= e($tab) ?>"<?= $growthOk ? ' data-tabs' : '' ?>>
    <header class="an-head">
        <div class="an-title">
            <span class="an-eyebrow">School analytics</span>
            <h1><?= $growth ? 'School growth' : 'Analytics' ?></h1>
            <p><?= e(school_name()) ?><?= $term ? ' · ' . e($growth ? 'last five academic years to ' . $term['year_name'] : $term['year_name'] . ' · ' . $term['name']) : '' ?></p>
        </div>
        <?php if ($growthOk): ?>
        <nav class="an-tabs" aria-label="Dashboard view">
            <a href="<?= e(url('dashboard', $tq + ['tab' => 'today'])) ?>" data-tab-link="today"<?= !$growth ? ' class="active" aria-current="page"' : '' ?>>Today</a>
            <a href="<?= e(url('dashboard', $tq + ['tab' => 'growth'])) ?>" data-tab-link="growth"<?= $growth ? ' class="active" aria-current="page"' : '' ?>>School growth</a>
        </nav>
        <?php endif; ?>
        <div class="an-tools">
            <span class="an-live" data-live-at role="status" aria-live="polite"><i aria-hidden="true"></i>Live · updated <?= e($asOf) ?></span>
            <?php if ($terms): ?>
            <form method="get" action="<?= e(url('dashboard')) ?>" class="an-term">
                <?php if ($growth): ?><input type="hidden" name="tab" value="growth"><?php endif; ?>
                <label class="sr-only" for="an-term">Term</label>
                <select id="an-term" name="term" data-autosubmit>
                    <?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($term['id'] ?? 0, $t['id']) ?>><?= e($t['year_name'] . ' · ' . $t['name']) ?></option><?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-sm" type="submit">Show</button></noscript>
            </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm" data-print>Print</button>
        </div>
    </header>

    <?php if ($kpis): ?><div class="an-kpis<?= $growth ? ' an-kpis-5' : '' ?>"><?= implode('', $kpis) ?></div><?php endif; ?>
    <?php if (!$growth): ?><?= $cards['today'] ?? '' ?><?php endif; ?>
    <?php if ($panels): ?><div class="<?= $growth ? 'an-ggrid' : 'an-grid' ?>"><?= implode('', $panels) ?></div><?php endif; ?>
    <?php if (!$kpis && !$panels): ?><div class="card an-empty"><p>Your role has no school figures to show. Use the menu to open your pages.</p></div><?php endif; ?>
    <?php if (!$growth): ?>
    <?php $tabModule = ['attendance' => 'attendance', 'academic' => 'exams', 'fees' => 'fees', 'money' => 'finance'];
    $reports = $term && can('analytics.view') ? array_filter(AnalyticsController::TABS, fn($t, $k) => can($t[1]) && module_on($tabModule[$k] ?? 'core'), ARRAY_FILTER_USE_BOTH) : []; ?>
    <?php if ($reports): ?>
    <nav class="an-reports" aria-label="Printable school reports"><span>Printable school reports</span>
        <?php foreach ($reports as $k => [$label]): ?><a href="<?= e(url('analytics', ['tab' => $k, 'term' => $term['id']])) ?>" target="_blank"><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
    <p class="an-foot"><?= $growth ? 'Growth compares each academic year with the ones before it (up to five). This year\'s figures refresh every minute; earlier years every ten minutes.' : 'Figures are worked out from the school\'s records and refresh every minute while this page is open.' ?></p>
</div>
