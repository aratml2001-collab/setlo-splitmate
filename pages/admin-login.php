<?php
require __DIR__ . '/../includes/bootstrap.php';
if ((current_user()['role'] ?? '') === 'admin') {
    redirect('pages/admin-dashboard');
}
$title = 'Admin Log In';
$loginPage = 'admin-login';
$bodyClass = 'min-h-screen flex items-center justify-center !bg-gradient-to-br from-brand-700 to-ink';
require __DIR__ . '/../partials/head.php';
?>
<div id="app" class="mx-4 w-full max-w-sm" v-cloak>
  <form class="card p-8" @submit.prevent="submit" novalidate>
    <div class="mb-6 flex flex-col items-center">
      <img src="<?= h(url('assets/setlo_logo.png')) ?>" alt="" class="mb-3 h-16 w-16 rounded-2xl object-cover" />
      <h1 class="text-xl font-bold text-ink">Setlo Admin Panel</h1>
      <p class="mt-1 text-xs text-slate-500">App manager access only</p>
    </div>
    <div class="space-y-4">
      <div>
        <label class="text-xs font-semibold text-slate-600" for="email">Admin Email</label>
        <input id="email" data-field="email" v-model.trim="email" @input="touch('email')" @blur="touch('email')" :class="{ 'is-invalid': err('email') }" maxlength="190" type="email" autocomplete="username" class="input mt-1" placeholder="admin@setlo.app" autofocus />
        <p v-if="err('email')" class="field-error">{{ err('email') }}</p>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600" for="pw">Password</label>
        <input id="pw" data-field="password" v-model="password" @input="touch('password')" :class="{ 'is-invalid': err('password') }" maxlength="72" type="password" autocomplete="current-password" class="input mt-1" />
        <p v-if="err('password')" class="field-error">{{ err('password') }}</p>
      </div>
      <p v-if="error" class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600">{{ error }}</p>
      <button class="btn btn-primary w-full" :disabled="busy">{{ busy ? 'Signing in…' : 'Log In to Admin Panel' }}</button>
    </div>
    <p class="mt-6 text-center text-xs text-slate-400">Not an admin? <a href="login" class="font-semibold text-brand-600">Go to user sign in</a></p>
  </form>
  <p class="mt-4 text-center text-xs text-brand-100/70">Setlo · Internal tool for platform oversight, not end users</p>
</div>

<script>
Setlo.mount({
  mixins: [Setlo.validation],
  data: () => ({ email: '', password: '', busy: false, error: '' }),
  methods: {
    validators() {
      return { email: V.email(this.email), password: V.required(this.password, 'Password') };
    },
    async submit() {
      this.error = '';
      if (!this.validateAll()) return;
      this.busy = true;
      try {
        const r = await api.post('auth.php', { action: 'login', admin: true, email: this.email, password: this.password });
        location.href = r.redirect;
      } catch (e) {
        this.error = e.message;
        if (e.status === 429) Setlo.alert('Too many attempts', e.message, 'warning');
        this.busy = false;
      }
    },
  },
});
</script>
<?php require __DIR__ . '/../partials/foot.php'; ?>
