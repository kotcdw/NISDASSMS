<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('quizzes/' . $q['id'] . '/results')) ?>">Results</a></p><h1><?= e($a['first_name'] . ' ' . $a['last_name']) ?></h1>
<p class="muted"><?= e($q['title']) ?> · attempt <?= (int) $a['attempt_no'] ?> · <?= e(Quiz::fmt($a['score'])) ?>/<?= e(Quiz::fmt($a['max_score'])) ?> · <?= e(date('d M H:i', strtotime((string) $a['submitted_at']))) ?></p></div></div>
<?php $reveal = true; require dirname(__DIR__) . '/learn/_review.php'; ?>
