// Setlo prototype — shared UI helpers (mockup only, no real logic)

function toggleDropdown(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.toggle('hidden');
}

document.addEventListener('click', function (e) {
  document.querySelectorAll('[data-dropdown]').forEach(function (dd) {
    if (!dd.contains(e.target) && !e.target.closest('[data-dropdown-trigger="' + dd.id + '"]')) {
      dd.classList.add('hidden');
    }
  });
});

function toggleModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.toggle('hidden');
}

function toggleSwitch(el) {
  el.classList.toggle('on');
  return el.classList.contains('on');
}

function setRole(role) {
  document.querySelectorAll('[data-role-view]').forEach(function (node) {
    node.classList.toggle('hidden', node.getAttribute('data-role-view') !== role);
  });
  document.querySelectorAll('[data-role-btn]').forEach(function (btn) {
    const active = btn.getAttribute('data-role-btn') === role;
    btn.classList.toggle('bg-white', active);
    btn.classList.toggle('shadow-sm', active);
    btn.classList.toggle('text-teal-700', active);
    btn.classList.toggle('text-slate-500', !active);
  });
}
