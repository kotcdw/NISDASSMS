<?php
/** Renders the plain-text notice: "## " headings, "- " bullets, other lines as paragraphs (all escaped). Expects $text. */
$inList = false;
foreach (preg_split('/\R/', $text) as $line) {
    $line = trim($line);
    if (str_starts_with($line, '- ')) {
        if (!$inList) { echo '<ul>'; $inList = true; }
        echo '<li>' . e(substr($line, 2)) . '</li>';
        continue;
    }
    if ($inList) { echo '</ul>'; $inList = false; }
    if ($line === '') {
        continue;
    }
    echo str_starts_with($line, '## ') ? '<h2>' . e(substr($line, 3)) . '</h2>' : '<p>' . e($line) . '</p>';
}
if ($inList) { echo '</ul>'; }
