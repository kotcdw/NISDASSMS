<?php /** Rows / columns / method fields. Expects $rows, $cols, $method (optional), $exam (bool). */ ?>
<div class="grid-3">
    <label>Rows (front to back)<input type="number" name="seat_rows" min="1" max="20" value="<?= (int) $rows ?>"></label>
    <label>Seats per row<input type="number" name="seat_cols" min="1" max="16" value="<?= (int) $cols ?>"></label>
    <label>How to fill the seats<select name="method">
        <?php foreach (Seating::METHODS as $k => $l): if (!empty($exam) && $k === 'mixed') continue; ?><option value="<?= $k ?>"<?= selected($k, $method ?? 'alpha') ?>><?= e(!empty($exam) && $k === 'alpha' ? 'Classes alternating, alphabetical' : (!empty($exam) && $k === 'random' ? 'Classes alternating, random order' : $l)) ?></option><?php endforeach; ?>
    </select></label>
</div>
