<?php
// Tab bar: bottom bar on phones, left sidebar on laptops (see .nav2 in src/input.css).
// Set $nav to 'dashboard' | 'bills' | 'scan' | 'settle' (or 'stats' / 'profile') before including.
$tabs = [
    'dashboard' => ['dashboard.php', 'Dashboard', '<path stroke-linecap="round" stroke-linejoin="round" d="M3 11l9-7 9 7M5 9.5V20h5v-5h4v5h5V9.5"/>'],
    'bills'     => ['my-bills.php', 'Bills', '<path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3zM9 8h6M9 12h6"/>'],
    'scan'      => ['scan-receipt.php', 'Scan', '<path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 012-2h2M16 4h2a2 2 0 012 2v2M20 16v2a2 2 0 01-2 2h-2M8 20H6a2 2 0 01-2-2v-2M8 12h8"/>'],
    'settle'    => ['my-settlements.php', 'Settle', '<rect x="3" y="6" width="18" height="14" rx="2.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 13h2M3 10h14a2 2 0 012 2"/>'],
];
// More links, listed right under the tabs in the desktop sidebar (on phones they live in the dashboard menu).
$more = [
    'stats'   => ['stats.php', 'Spending', '<path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
    'history' => ['bill-history.php', 'History', '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/>'],
    'profile' => ['profile.php', 'Profile & payments', '<circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5"/>'],
];
$icon = fn (string $paths) => '<svg class="h-[22px] w-[22px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">' . $paths . '</svg>';
?>
<nav class="nav2">
  <a href="dashboard.php" class="!hidden lg:!flex !mb-4 !text-lg !font-extrabold !text-ink hover:!bg-transparent" aria-label="Setlo home">
    <img src="<?= h(url('assets/icons/icon-192.png')) ?>" alt="" class="h-8 w-8 rounded-lg" /> setlo
  </a>
<?php foreach ($tabs as $key => [$href, $label, $paths]): ?>
  <a href="<?= $href ?>"<?= ($nav ?? '') === $key ? ' class="active" aria-current="page"' : '' ?>><?= $icon($paths) ?><?= $label ?></a>
<?php endforeach; ?>
<?php foreach ($more as $key => [$href, $label, $paths]): ?>
  <a href="<?= $href ?>" class="!hidden lg:!flex<?= ($nav ?? '') === $key ? ' active' : '' ?>"><?= $icon($paths) ?><?= $label ?></a>
<?php endforeach; ?>
  <form method="post" action="logout.php" class="hidden lg:mt-3 lg:block lg:border-t lg:border-[#e8eeee] lg:pt-3">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>" />
    <button class="!text-rose-600"><?= $icon('<path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>') ?>Sign out</button>
  </form>
</nav>
