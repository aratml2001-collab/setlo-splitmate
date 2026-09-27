<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$title = 'User Management';
$subtitle = 'Search, suspend or reactivate end-user accounts';
$adminNav = 'users';
$loginPage = 'admin-login.php';
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/admin-top.php';
?>
<div id="app" class="space-y-4 p-5 md:p-8" v-cloak>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">{{ users.length }} {{ users.length === 1 ? 'user' : 'users' }}{{ q ? ' matching “' + q + '”' : '' }}</p>
    <input v-model="q" @input="queue" class="input !w-72" placeholder="Search by name or email" aria-label="Search users" />
  </div>

  <div class="card overflow-x-auto">
    <spinner v-if="loading"></spinner>
    <table v-else class="w-full min-w-[720px] text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="th">User</th><th class="th">Email</th><th class="th">Bills Joined</th><th class="th">Joined</th><th class="th">Status</th><th class="th text-right">Actions</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-for="u in users" :key="u.id" :class="{ 'bg-red-50/40': u.disputes }">
          <td class="td"><div class="flex items-center gap-2.5"><avatar :user="u" :size="28"></avatar><span class="font-medium">{{ u.name }}</span></div></td>
          <td class="td text-slate-500">{{ u.email }}</td>
          <td class="td text-slate-500">{{ u.bills }}</td>
          <td class="td text-slate-500">{{ fmtDate(u.created_at, true) }}</td>
          <td class="td">
            <status-pill v-if="u.status === 'suspended'" status="suspended"></status-pill>
            <status-pill v-else-if="u.disputes" status="disputed" :label="u.disputes + ' Open Dispute' + (u.disputes > 1 ? 's' : '')"></status-pill>
            <status-pill v-else status="active"></status-pill>
          </td>
          <td class="td text-right">
            <button v-if="u.status === 'active'" @click="setStatus(u, 'suspended')" class="text-xs font-semibold text-red-500" :disabled="busy">Suspend</button>
            <button v-else @click="setStatus(u, 'active')" class="text-xs font-semibold text-emerald-600" :disabled="busy">Reactivate</button>
            <button @click="resetPassword(u)" class="ml-3 text-xs font-semibold text-slate-500 hover:text-brand-700" :disabled="busy">Reset password</button>
          </td>
        </tr>
        <tr v-if="!users.length"><td colspan="6" class="td py-8 text-center text-slate-400">No users found.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
Setlo.mount({
  data: () => ({ loading: true, busy: false, users: [], q: '', timer: null }),
  async mounted() { await Setlo.run(this, this.load, 'loading'); },
  methods: {
    async load() { this.users = (await api.get('admin.php', { view: 'users', q: this.q })).users; },
    queue() { clearTimeout(this.timer); this.timer = setTimeout(() => Setlo.run(this, this.load), 300); },
    async resetPassword(u) {
      if (!(await Setlo.confirm({ title: 'Reset ' + u.name + '’s password?', text: 'Their current password will stop working right away.', confirmText: 'Reset password', danger: true }))) return;
      const r = await Setlo.run(this, () => api.post('admin.php', { action: 'reset_password', user_id: u.id }));
      if (r) Setlo.showCopy('Temporary password', r.temporary_password, 'Give this to ' + u.email + '. They can change it on their Profile after signing in.');
    },
    async setStatus(u, status) {
      if (status === 'suspended' && !(await Setlo.confirm({ title: 'Suspend ' + u.name + '?', text: 'They will be signed out and unable to log in until reactivated.', confirmText: 'Suspend', danger: true }))) return;
      const r = await Setlo.run(this, () => api.post('admin.php', { action: 'set_user_status', user_id: u.id, status }));
      if (r) { u.status = status; Setlo.toast(status === 'active' ? 'Account reactivated' : 'Account suspended', 'ok'); }
    },
  },
});
</script>
<?php require __DIR__ . '/../partials/admin-bottom.php'; ?>
<?php require __DIR__ . '/../partials/foot.php'; ?>
