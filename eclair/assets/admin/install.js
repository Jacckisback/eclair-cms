/* ⚡ Éclair — assistant d'installation */
(() => {
  const form = document.getElementById('wizard');
  if (!form) return;
  const data = JSON.parse(document.getElementById('install-data').textContent);
  const steps = [...form.querySelectorAll('[data-step]')];
  const bars = [...form.querySelectorAll('.steps i')];
  let current = 0;

  const show = (index) => {
    steps.forEach((s, i) => { s.hidden = i !== index; });
    bars.forEach((b, i) => b.classList.toggle('on', i <= index));
    current = index;
    if (index === 4) recap();
    const first = steps[index].querySelector('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), select');
    if (first && index > 0) first.focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const error = (input, message) => {
    const field = input.closest('.field');
    let el = field && field.querySelector('.error-text');
    if (!message) {
      input.removeAttribute('aria-invalid');
      if (el) el.remove();
      return;
    }
    input.setAttribute('aria-invalid', 'true');
    if (field && !el) {
      el = document.createElement('span');
      el.className = 'error-text';
      field.appendChild(el);
    }
    if (el) el.textContent = message;
  };

  const validate = (index) => {
    let ok = true;
    steps[index].querySelectorAll('input[required]').forEach((input) => {
      let message = '';
      const value = input.value.trim();
      if (!value) message = data.errors.required;
      else if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) message = data.errors.email;
      else if (input.name === 'user_pass' && value.length < 10) message = data.errors.short;
      else if (input.pattern && !new RegExp('^(?:' + input.pattern + ')$').test(value)) message = input.closest('.field').querySelector('.hint')?.textContent || data.errors.required;
      error(input, message);
      if (message && ok) { input.focus(); ok = false; }
    });
    return ok;
  };

  form.addEventListener('click', (event) => {
    if (event.target.closest('[data-next]') && validate(current)) show(current + 1);
    if (event.target.closest('[data-prev]')) show(current - 1);
  });
  form.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && event.target.tagName === 'INPUT' && current < 4) {
      event.preventDefault();
      if (validate(current)) show(current + 1);
    }
  });

  // Afficher / masquer le mot de passe et jauge de robustesse.
  form.querySelectorAll('[data-reveal]').forEach((btn) => btn.addEventListener('click', () => {
    const input = btn.previousElementSibling;
    input.type = input.type === 'password' ? 'text' : 'password';
  }));
  form.querySelectorAll('[data-strength]').forEach((input) => input.addEventListener('input', () => {
    const v = input.value;
    let score = 0;
    if (v.length >= 10) score++;
    if (v.length >= 14) score++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
    if (/\d/.test(v) || /[^\w]/.test(v)) score++;
    if (v.length >= 20) score = 4;
    if (new Set(v).size < 5) score = Math.min(score, 1);
    input.closest('.field').querySelector('.strength').dataset.level = v ? Math.max(1, score) : 0;
  }));

  // Adresse d'administration secrète.
  document.getElementById('secret-path')?.addEventListener('click', () => {
    const words = ['atelier', 'cockpit', 'coulisses', 'studio', 'tableau', 'bureau', 'cabine', 'console'];
    const rand = Math.random().toString(36).slice(2, 7);
    form.elements.admin_path.value = words[Math.floor(Math.random() * words.length)] + '-' + rand;
  });

  // Test de réécriture d'URL (le navigateur appelle une adresse « propre »).
  const rewrite = document.getElementById('rewrite');
  if (rewrite) {
    fetch(rewrite.dataset.url, { cache: 'no-store' })
      .then((r) => r.text())
      .then((text) => text.trim() === 'ok')
      .catch(() => false)
      .then((ok) => {
        rewrite.className = ok ? 'ok' : 'warn';
        rewrite.querySelector('.ico').textContent = ok ? '✓' : '!';
        const small = rewrite.querySelector('small');
        small.textContent = ok ? small.dataset.ok : small.dataset.ko;
      });
  }

  const recap = () => {
    const f = form.elements;
    const type = form.querySelector('input[name=site_type]:checked');
    const ai = form.querySelector('input[name=ai]:checked');
    const rows = [
      [data.recap.site, f.site_name.value + (f.site_tagline.value ? ' — ' + f.site_tagline.value : '')],
      [data.recap.address, data.url + '/'],
      [data.recap.type, type ? type.closest('.choice').querySelector('b').textContent : ''],
      [data.recap.account, f.user_name.value + ' · ' + f.user_email.value],
      [data.recap.visibility, (f.index.checked ? data.recap.indexed : data.recap.hidden) + ' · ' + data.recap.ai + ' : ' + (ai ? ai.closest('.choice').querySelector('b').textContent : '')],
      [data.recap.admin, data.url + '/' + f.admin_path.value],
    ];
    const list = document.getElementById('recap');
    list.textContent = '';
    rows.forEach(([label, value]) => {
      const li = document.createElement('li');
      li.className = 'ok';
      li.innerHTML = '<span class="ico">✓</span><span><b></b><small></small></span>';
      li.querySelector('b').textContent = label;
      li.querySelector('small').textContent = value;
      list.appendChild(li);
    });
  };

  const confetti = () => {
    const box = document.createElement('div');
    box.className = 'confetti';
    const colors = ['#5b4cf0', '#9b5cff', '#ffc928', '#ff6b8b', '#3ccf8a', '#38bdf8'];
    for (let i = 0; i < 140; i++) {
      const piece = document.createElement('i');
      piece.style.left = Math.random() * 100 + 'vw';
      piece.style.background = colors[i % colors.length];
      piece.style.animationDuration = 2.2 + Math.random() * 2.2 + 's';
      piece.style.animationDelay = Math.random() * 0.6 + 's';
      piece.style.setProperty('--dx', (Math.random() * 40 - 20) + 'vw');
      piece.style.setProperty('--r', Math.random() * 900 + 'deg');
      box.appendChild(piece);
    }
    document.body.appendChild(box);
    setTimeout(() => box.remove(), 5500);
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = document.getElementById('install-btn');
    const box = document.getElementById('install-error');
    button.disabled = true;
    box.hidden = true;
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!result.ok) {
        const errors = result.errors || {};
        const field = Object.keys(errors).find((name) => form.elements[name]);
        if (field) {
          const input = form.elements[field];
          show(steps.findIndex((s) => s.contains(input)));
          error(input, errors[field]);
        } else {
          box.querySelector('p').textContent = errors._ || data.errors.network;
          box.hidden = false;
        }
        button.disabled = false;
        return;
      }
      steps.forEach((s) => { s.hidden = true; });
      bars.forEach((b) => b.classList.add('on'));
      document.getElementById('success').hidden = false;
      document.getElementById('go-site').href = result.site;
      document.getElementById('go-admin').href = result.admin;
      document.getElementById('admin-address').textContent = data.adminAddress.replace('{url}', location.origin + result.admin);
      confetti();
    } catch (e) {
      box.querySelector('p').textContent = data.errors.network;
      box.hidden = false;
      button.disabled = false;
    }
  });
})();
