(function () {
  // stagger animasi elemen form
  document.querySelectorAll('.reveal').forEach((el, i) => el.style.setProperty('--i', i));

  // show/hide password (semua ikon .toggle-password)
  document.querySelectorAll('.toggle-password').forEach(icon => {
    icon.addEventListener('click', () => {
      const input = icon.parentElement.querySelector('input');
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    });
  });

  // loading spinner saat submit
  document.querySelectorAll('.auth-form').forEach(form => {
    form.addEventListener('submit', () => form.querySelector('.submit-btn').classList.add('loading'));
  });
  window.addEventListener('pageshow', () =>
    document.querySelectorAll('.submit-btn.loading').forEach(b => b.classList.remove('loading')));

  // carousel
  const slides = [...document.querySelectorAll('.slide')];
  if (!slides.length) return;
  const dotsBox = document.getElementById('dots');
  const cap = document.getElementById('cap');
  const capTitle = document.getElementById('capTitle');
  const capSub = document.getElementById('capSub');
  let cur = 0, timer;

  slides.forEach((_, i) => {
    const d = document.createElement('i');
    d.addEventListener('click', () => { go(i); restart(); });
    dotsBox.appendChild(d);
  });

  function go(n) {
    cur = (n + slides.length) % slides.length;
    slides.forEach((s, i) => s.classList.toggle('active', i === cur));
    [...dotsBox.children].forEach((d, i) => d.classList.toggle('on', i === cur));
    cap.classList.add('swap');
    setTimeout(() => {
      capTitle.textContent = slides[cur].dataset.title;
      capSub.textContent = slides[cur].dataset.sub;
      cap.classList.remove('swap');
    }, 300);
  }
  function restart() { clearInterval(timer); timer = setInterval(() => go(cur + 1), 5000); }

  document.getElementById('prev').addEventListener('click', () => { go(cur - 1); restart(); });
  document.getElementById('next').addEventListener('click', () => { go(cur + 1); restart(); });
  go(0); restart();
})();