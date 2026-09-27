<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$title = 'My Settlements';
$nav = 'settle';
$back = 'dashboard.php';
require __DIR__ . '/../partials/head.php';
?>
<div id="app" class="device" v-cloak>

  <div class="app-hero px-5 pb-5 pt-8">
    <div class="flex items-center gap-3">
      <?php require __DIR__ . '/../partials/back.php'; ?>
      <h1 class="text-[21px] font-extrabold tracking-tight">My Settlements</h1>
    </div>
    <div class="seg mt-5">
      <button :class="{ on: tab === 'owe' }" @click="tab = 'owe'">You Owe <span v-if="openCount('owe')" class="ml-1 opacity-70">{{ openCount('owe') }}</span></button>
      <button :class="{ on: tab === 'owed' }" @click="tab = 'owed'">You're Owed <span v-if="openCount('owed')" class="ml-1 opacity-70">{{ openCount('owed') }}</span></button>
    </div>
  </div>

  <div class="flex-1 space-y-3 px-5 pb-6 pt-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-3 lg:space-y-0">
    <spinner v-if="loading" class="lg:col-span-2"></spinner>
    <div v-else-if="!list.length" class="tile p-6 text-center lg:col-span-2">
      <p class="text-sm font-bold text-ink">{{ tab === 'owe' ? "You don't owe anyone" : 'Nobody owes you' }}</p>
      <p class="mt-1 text-[12px] text-slate-400">Settlements appear once a bill's split is confirmed.</p>
    </div>

    <div v-for="s in list" :key="s.id" class="tile p-3.5" :class="{ '!border-red-200 ring-1 ring-red-100': s.status === 'disputed', 'opacity-70': s.status === 'settled' }">
      <div class="flex items-center gap-3">
        <avatar :user="tab === 'owe' ? s.to : s.from" :size="36"></avatar>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold">{{ tab === 'owe' ? (s.from.is_guest ? s.from.first + ' → ' + s.to.name : 'To ' + s.to.name) : 'From ' + s.from.name }}
            <span v-if="s.from.is_guest" class="pill pill-draft ml-0.5">{{ tab === 'owe' ? 'Guest · you pay for them' : 'Guest' }}</span>
          </p>
          <a :href="'bill-detail.php?bill=' + s.bill_id" class="block truncate text-xs text-slate-400">{{ s.bill_name }}</a>
        </div>
        <p class="text-sm font-bold">{{ peso(s.amount) }}</p>
      </div>

      <div v-if="s.status === 'disputed'" class="mt-2.5 rounded-lg bg-red-50 px-3 py-2">
        <p class="text-xs text-red-700"><b>{{ tab === 'owe' ? 'Disputed by ' + s.to.first : 'You disputed this' }}:</b> “{{ s.dispute_reason }}”</p>
      </div>
      <div v-if="tab === 'owe' && (s.status === 'pending' || s.status === 'disputed')" class="mt-2.5 flex items-center gap-3 rounded-lg bg-slate-50 px-3 py-2">
        <p class="min-w-0 flex-1 text-xs text-slate-600">
          <b class="text-slate-800">Pay via {{ s.to.payment_method }}</b>
          <span v-if="s.to.payment_account"> · {{ s.to.payment_account }}</span>
          <span v-else-if="s.to.payment_method !== 'Cash'" class="text-slate-400"> · no account details yet</span>
        </p>
        <button v-if="s.to.pay_code" @click="showQr(s.to)" class="shrink-0 text-xs font-bold text-brand-700">Show QR</button>
      </div>
      <div v-if="tab === 'owed' && s.status === 'awaiting'" class="mt-2.5 rounded-lg bg-blue-50 px-3 py-2">
        <div class="flex items-center gap-2">
          <svg class="h-4 w-4 shrink-0 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <p class="text-xs text-blue-700">{{ s.from.first }} marked this Paid on {{ fmtDateTime(s.paid_at) }} — confirm you received it.</p>
        </div>
        <div v-if="s.payment_ref || s.has_proof" class="mt-1.5 flex items-center justify-between gap-2 pl-6 text-xs">
          <span class="text-blue-800">{{ s.payment_ref ? 'Ref no. ' + s.payment_ref : '' }}</span>
          <button v-if="s.has_proof" @click="view('Proof of payment', proofSrc(s.id))" class="font-bold text-blue-700 underline">View proof</button>
        </div>
      </div>

      <div class="mt-3 flex items-center justify-between gap-2">
        <status-pill :status="s.status" :label="tab === 'owed' && s.status === 'awaiting' ? 'Awaiting Your Confirmation' : null"></status-pill>

        <!-- Sender actions -->
        <template v-if="tab === 'owe'">
          <button v-if="s.status === 'pending'" @click="openPay(s)" class="btn btn-primary btn-sm" :disabled="busy">Mark as Paid</button>
          <button v-else-if="s.status === 'disputed'" @click="act(s, 'resend')" class="btn btn-outline btn-sm" :disabled="busy">Review &amp; Resend</button>
          <div v-else-if="s.status === 'awaiting'" class="flex items-center gap-2">
            <span class="text-[11px] text-slate-400">Paid {{ timeAgo(s.paid_at) }}</span>
            <button v-if="canNudge(s)" @click="nudge(s)" class="btn btn-ghost btn-sm !px-2.5 !text-brand-700" :disabled="busy">Remind to confirm</button>
            <span v-else-if="s.my_last_nudge" class="text-[11px] font-semibold text-slate-400">Reminded {{ timeAgo(s.my_last_nudge) }}</span>
          </div>
          <a v-else :href="'settlement-audit.php?id=' + s.id" class="text-[11px] font-semibold text-brand-600">View timestamps →</a>
        </template>

        <!-- Receiver actions -->
        <template v-else>
          <div v-if="s.status === 'awaiting'" class="flex gap-2">
            <button @click="openReject(s)" class="btn btn-danger btn-sm" :disabled="busy">Reject</button>
            <button @click="act(s, 'confirm')" class="btn btn-accent btn-sm" :disabled="busy">Confirm</button>
          </div>
          <button v-else-if="s.status === 'pending' && s.from.is_guest && s.bill_creator_id === me" @click="act(s, 'record')" class="btn btn-accent btn-sm" :disabled="busy">Record cash received</button>
          <div v-else-if="s.status === 'pending'" class="flex items-center gap-2">
            <span class="text-[11px] text-slate-400">Not yet paid</span>
            <button v-if="canNudge(s)" @click="nudge(s)" class="btn btn-ghost btn-sm !px-2.5 !text-brand-700" :disabled="busy">Send reminder</button>
            <span v-else-if="s.my_last_nudge" class="text-[11px] font-semibold text-slate-400">Reminded {{ timeAgo(s.my_last_nudge) }}</span>
          </div>
          <a v-else :href="'settlement-audit.php?id=' + s.id" class="text-[11px] font-semibold text-brand-600">View timestamps →</a>
        </template>
      </div>
    </div>
  </div>

  <?php require __DIR__ . '/../partials/nav.php'; ?>

  <div v-if="paying" class="sheet-backdrop" @click.self="paying = null">
    <form class="sheet" @submit.prevent="submitPay">
      <div class="sheet-grip"></div>
      <h2 class="text-[17px] font-extrabold text-ink">Mark {{ peso(paying.amount) }} as paid?</h2>
      <p class="mb-4 mt-1 text-xs text-slate-500">{{ paying.to.first }} will be asked to confirm they received it. A reference number or screenshot helps them confirm faster.</p>
      <label class="field-label" for="ref">Reference no. <span class="font-medium text-slate-400">(optional)</span></label>
      <input id="ref" data-field="ref" v-model="payRef" @input="touch('ref')" :class="{ 'is-invalid': err('ref') }" class="input-soft" maxlength="60" placeholder="e.g. 1009 234 567 890" />
      <p v-if="err('ref')" class="field-error">{{ err('ref') }}</p>
      <p class="field-label mt-4">Screenshot <span class="font-medium text-slate-400">(optional)</span></p>
      <input ref="proof" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pickProof" aria-label="Proof screenshot" />
      <div class="flex items-center gap-3">
        <img v-if="proofPreview" :src="proofPreview" alt="Selected screenshot" class="h-16 w-16 rounded-xl object-cover" />
        <button type="button" @click="$refs.proof.click()" class="btn btn-outline btn-sm">{{ proofFile ? 'Change screenshot' : 'Attach screenshot' }}</button>
        <button v-if="proofFile" type="button" @click="clearProof" class="text-xs font-semibold text-slate-400">Remove</button>
      </div>
      <div class="mt-5 grid grid-cols-2 gap-2.5">
        <button type="button" @click="paying = null" class="btn-pill btn-pill-soft">Cancel</button>
        <button class="btn-pill btn-pill-primary" :disabled="busy">{{ busy ? 'Sending…' : 'Mark as Paid' }}</button>
      </div>
    </form>
  </div>

  <div v-if="viewer" class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-ink/80 p-6" @click="viewer = null">
    <p class="text-sm font-bold text-white">{{ viewer.title }}</p>
    <img :src="viewer.src" :alt="viewer.title" class="max-h-[75vh] max-w-full rounded-2xl bg-white shadow-2xl" />
    <p class="text-xs text-white/60">Tap anywhere to close</p>
  </div>

  <div v-if="rejecting" class="sheet-backdrop" @click.self="rejecting = null">
    <form class="sheet" @submit.prevent="submitReject">
      <div class="sheet-grip"></div>
      <h2 class="mb-1 text-[17px] font-extrabold text-ink">Reject this payment?</h2>
      <p class="mb-4 text-xs text-slate-500">This flags the settlement as Disputed and notifies {{ rejecting.from.first }}. Explain why.</p>
      <textarea data-field="reason" v-model="reason" @input="touch('reason')" :class="{ 'is-invalid': err('reason') }" class="input-soft h-24" maxlength="500" placeholder="e.g. I haven't received it — no GCash reference number yet." aria-label="Reason"></textarea>
      <div class="mt-1 flex justify-between text-[11px]"><span class="field-error !mt-0">{{ err('reason') }}</span><span class="text-slate-400">{{ reason.length }}/500</span></div>
      <div class="mt-4 grid grid-cols-2 gap-2.5">
        <button type="button" @click="rejecting = null" class="btn-pill btn-pill-soft">Cancel</button>
        <button class="btn-pill btn-pill-danger" :disabled="busy">Submit Dispute</button>
      </div>
    </form>
  </div>
</div>

<script>
Setlo.mount({
  mixins: [Setlo.validation],
  data: () => ({
    me: <?= (int) $user['id'] ?>, loading: true, busy: false, tab: new URLSearchParams(location.search).get('tab') === 'owed' ? 'owed' : 'owe',
    owe: [], owed: [], rejecting: null, reason: '',
    paying: null, payRef: '', proofFile: null, proofPreview: null, viewer: null,
  }),
  computed: { list() { return this[this.tab]; } },
  async mounted() { await Setlo.run(this, this.load, 'loading'); },
  methods: {
    async load() {
      const r = await api.get('settlements.php');
      this.owe = r.owe;
      this.owed = r.owed;
    },
    openCount(tab) {
      return this[tab].filter((s) => (tab === 'owe' ? ['pending', 'disputed'] : ['awaiting']).includes(s.status)).length;
    },
    async act(s, action, extra) {
      const r = await Setlo.run(this, () => api.post('settlements.php', { action, id: s.id, ...(extra || {}) }));
      if (r) {
        Object.assign(s, r.settlement);
        const msg = { mark_paid: 'Marked as paid — waiting for confirmation', confirm: 'Payment confirmed ✓', reject: 'Dispute submitted', resend: 'Payment reopened', record: 'Payment recorded ✓' }[action];
        Setlo.toast(msg, action === 'reject' ? 'warn' : 'ok');
      } else {
        await this.load();
      }
      return r;
    },
    proofSrc(id) { return '<?= h(url('api/settlements.php')) ?>?proof=' + id; },
    view(title, src) { this.viewer = { title, src }; },
    canNudge(s) {
      if (s.from.is_guest) return false; // guests have no account to remind
      return !s.my_last_nudge || Date.now() - new Date(s.my_last_nudge.replace(' ', 'T')).getTime() > 12 * 3600 * 1000;
    },
    async nudge(s) {
      const r = await Setlo.run(this, () => api.post('settlements.php', { action: 'nudge', id: s.id }));
      if (r) {
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        s.my_last_nudge = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        Setlo.toast('Reminder sent to ' + (this.tab === 'owe' ? s.to.first : s.from.first), 'ok');
      }
    },
    showQr(person) { Setlo.showPayQr(person); },
    openPay(s) { this.paying = s; this.payRef = ''; this.touched.ref = false; this.clearProof(); },
    pickProof(e) {
      const file = e.target.files[0];
      e.target.value = '';
      if (!file) return;
      if (file.size > 8 * 1024 * 1024) { Setlo.toast('Screenshot is too large (max 8 MB).'); return; }
      this.proofFile = file;
      this.proofPreview = URL.createObjectURL(file);
    },
    clearProof() { this.proofFile = null; this.proofPreview = null; },
    validators() {
      return { ref: V.refNo(this.payRef), reason: this.rejecting ? V.reason(this.reason, 5, 500) : '' };
    },
    async submitPay() {
      if (!this.validateAll(['ref'])) return;
      const fd = new FormData();
      fd.append('action', 'mark_paid');
      fd.append('id', this.paying.id);
      fd.append('payment_ref', this.payRef.trim());
      if (this.proofFile) fd.append('proof', this.proofFile);
      const r = await Setlo.run(this, () => api.post('settlements.php', fd));
      if (r) {
        Object.assign(this.paying, r.settlement);
        this.paying = null;
        Setlo.toast('Marked as paid — waiting for confirmation', 'ok');
      } else {
        await this.load();
      }
    },
    openReject(s) { this.rejecting = s; this.reason = ''; this.touched.reason = false; },
    async submitReject() {
      if (!this.validateAll(['reason'])) return;
      if (await this.act(this.rejecting, 'reject', { reason: this.reason.trim() })) this.rejecting = null;
    },
  },
});
</script>
<?php require __DIR__ . '/../partials/foot.php'; ?>
