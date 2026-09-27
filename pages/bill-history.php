<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$title = 'Bill History';
$nav = 'history';
$back = 'dashboard.php';
require __DIR__ . '/../partials/head.php';
?>
<div id="app" class="device device-narrow" v-cloak>

  <div class="app-hero px-5 pb-5 pt-8">
    <div class="flex items-center gap-3">
      <?php require __DIR__ . '/../partials/back.php'; ?>
      <div>
        <h1 class="text-[21px] font-extrabold tracking-tight">Bill History</h1>
        <p class="text-[12px] font-medium text-brand-50/90">{{ bills.length }} closed · {{ peso(total) }} split in total</p>
      </div>
    </div>
  </div>

  <div class="flex-1 space-y-3 px-5 pb-6 pt-4">
    <spinner v-if="loading"></spinner>
    <div v-else-if="!bills.length" class="tile p-6 text-center">
      <p class="text-sm font-bold text-ink">No closed bills yet</p>
      <p class="mt-1 text-[12px] text-slate-400">Bills move here once every payment is confirmed.</p>
    </div>
    <template v-for="g in groups" :key="g.month">
      <p class="pt-2 text-xs font-semibold text-slate-400">{{ g.month }}</p>
      <a v-for="b in g.bills" :key="b.id" :href="b.link" class="tile flex items-center gap-3 p-3.5">
        <div class="initials initials-muted">{{ b.initials }}</div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-slate-800">{{ b.name }}</p>
          <p class="text-xs text-slate-400">{{ b.members }} members · Closed {{ fmtDate(b.closed_at) }}</p>
        </div>
        <p class="text-sm font-bold text-slate-700">{{ peso(b.total) }}</p>
      </a>
    </template>
  </div>

  <?php require __DIR__ . '/../partials/nav.php'; ?>
</div>

<script>
Setlo.mount({
  data: () => ({ loading: true, bills: [] }),
  computed: {
    total() { return this.bills.reduce((s, b) => s + b.total, 0); },
    groups() {
      const out = [];
      for (const b of this.bills) {
        const month = this.fmtMonth(b.closed_at || b.created_at);
        let g = out.find((x) => x.month === month);
        if (!g) out.push((g = { month, bills: [] }));
        g.bills.push(b);
      }
      return out;
    },
  },
  async mounted() {
    await Setlo.load(this, 'bills.php', { scope: 'history' }, (r) => { this.bills = r.bills; });
  },
});
</script>
<?php require __DIR__ . '/../partials/foot.php'; ?>
