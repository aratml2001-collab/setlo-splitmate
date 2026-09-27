<?php
// Scan → Review → Assign progress indicator. Set $step to 1, 2 or 3.
$names = [1 => 'Scan', 2 => 'Review', 3 => 'Assign'];
?>
<div class="steps">
<?php foreach ($names as $n => $label): $cls = $n < $step ? 'done' : ($n === $step ? 'current' : ''); ?>
  <?php if ($n > 1): ?><div class="step-line<?= $n <= $step ? ' done' : '' ?>"></div><?php endif; ?>
  <div class="step <?= $cls ?>"><span class="dot"><?= $n < $step ? '✓' : $n ?></span><?= $label ?></div>
<?php endforeach; ?>
</div>
