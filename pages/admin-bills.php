<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$title = 'Bills & Settlements';
$subtitle = 'Read-only monitoring across all bills';
$adminNav = 'bills';
$loginPage = 'admin-login';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/admin-top.php';
?>
<div id="app" class="space-y-4 p-5 md:p-8" v-cloak>
  <div class="seg seg-light max-w-md">
    <button v-for="f in filters" :key="f.key" :class="{ on: status === f.key }" @click="status = f.key; load()">{{ f.label }}</button>
  </div>

  <div class="card overflow-x-auto">
    <spinner v-if="loading"></spinner>
    <table v-else class="w-full min-w-[760px] text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="th">Bill</th><th class="th">Creator</th><th class="th">Members</th><th class="th">Total</th><th class="th">Created</th><th class="th">Progress</th><th class="th">Status</th><th class="th"></th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-for="b in bills" :key="b.id" class="cursor-pointer hover:bg-slate-50" @click="open(b)">
          <td class="td font-medium">{{ b.name }}</td>
          <td class="td text-slate-500">{{ b.creator }}</td>
          <td class="td text-slate-500">{{ b.members }}</td>
          <td class="td font-medium">{{ peso(b.total) }}</td>
          <td class="td text-slate-500">{{ fmtDate(b.created_at, true) }}</td>
          <td class="td"><div class="progress w-24"><span :style="{ width: b.progress + '%' }"></span></div></td>
          <td class="td">
            <div class="flex flex-wrap gap-1">
              <status-pill :status="b.status"></status-pill>
              <status-pill v-if="b.disputed" status="disputed" :label="b.disputed + ' disputed'"></status-pill>
              <status-pill v-if="b.awaiting" status="awaiting" :label="b.awaiting + ' awaiting'"></status-pill>
            </div>
          </td>
          <td class="td text-right text-xs font-semibold text-brand-600">View →</td>
        </tr>
        <tr v-if="!bills.length"><td colspan="8" class="td py-8 text-center text-slate-400">No bills.</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Bill detail drawer -->
  <div v-if="detail" class="fixed inset-0 z-50 flex justify-end bg-ink/40" @click.self="detail = null">
    <div class="h-full w-full max-w-lg overflow-y-auto bg-white p-6 shadow-2xl">
      <div class="mb-4 flex items-start justify-between">
        <div>
          <h2 class="text-lg font-bold text-ink">{{ detail.bill.name }}</h2>
          <p class="text-xs text-slate-400">Created by {{ detail.bill.creator }} · {{ fmtDate(detail.bill.created_at, true) }} · {{ peso(detail.bill.total) }}</p>
        </div>
        <button @click="detail = null" class="text-slate-400 hover:text-slate-700" aria-label="Close">✕</button>
      </div>
      <p class="section-label">MEMBERS</p>
      <div class="mb-5 flex flex-wrap gap-2">
        <span v-for="m in detail.members" :key="m.id" class="chip gap-1.5 !bg-slate-100 !text-slate-700"><avatar :user="m" :size="18"></avatar>{{ m.name }}</span>
      </div>
      <p class="section-label">ITEMS</p>
      <div class="card mb-5 divide-y divide-slate-100">
        <div v-for="it in detail.items" :key="it.id" class="flex justify-between gap-3 px-4 py-2.5 text-sm">
          <span>{{ it.name }} ×{{ it.qty }} <span v-if="it.was_corrected" class="pill pill-settling ml-1">OCR corrected</span></span>
          <span class="font-medium">{{ peso(it.line_total) }}</span>
        </div>
      </div>
      <p class="section-label">SETTLEMENTS</p>
      <div class="card divide-y divide-slate-100">
        <div v-for="s in detail.settlements" :key="s.id" class="flex items-center justify-between px-4 py-2.5 text-sm">
          <span>{{ s.from.name }} → {{ s.to.name }} · <b>{{ peso(s.amount) }}</b></span>
          <status-pill :status="s.status"></status-pill>
        </div>
        <p v-if="!detail.settlements.length" class="px-4 py-3 text-sm text-slate-400">Not generated yet.</p>
      </div>
    </div>
  </div>
</div>

<script>
Setlo.mount({
  data: () => ({
    loading: true, bills: [], status: '', detail: null,
    filters: [{ key: '', label: 'All' }, { key: 'active', label: 'Active' }, { key: 'settling', label: 'Settling' }, { key: 'closed', label: 'Closed' }],
  }),
  async mounted() { await this.load(); },
  methods: {
    async load() { await Setlo.load(this, 'admin.php', { view: 'bills', status: this.status }, (r) => { this.bills = r.bills; }); },
    async open(b) {
      const r = await Setlo.run(this, () => api.get('admin.php', { view: 'bill', id: b.id }), 'busy');
      if (r) this.detail = r;
    },
  },
});
</script>
<?php require __DIR__ . '/../partials/admin-bottom.php'; ?>
<?php require __DIR__ . '/../partials/foot.php'; ?>
