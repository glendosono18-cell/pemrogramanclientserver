const loginModal = document.querySelector('#loginModal');
const roleSelection = document.querySelector('#roleSelection');
const roleInput = document.querySelector('#role');
const loginTitle = document.querySelector('#login-title');
const usernameLabel = document.querySelector('#usernameLabel');
const usernameInput = document.querySelector('#username');
const passwordInput = document.querySelector('#password');
const passwordToggle = document.querySelector('#passwordToggle');
const loginMessage = document.querySelector('#loginMessage');
const loginForm = document.querySelector('#loginForm');
const submitLogin = document.querySelector('#submitLogin');
const registrationDivider = document.querySelector('#registrationDivider');
const registrationLink = document.querySelector('#registrationLink');
let lastSelectedRole = null;

const allowedRedirects = {
  mahasiswa: 'Beranda/dashboard_mahasiswa.html',
  dosen: 'Beranda/dashboard_dosen.html',
  admin: 'Beranda/dashboard_admin.html',
};

document.querySelector('#loginTrigger').addEventListener('click', () => {
  roleSelection.hidden = false;
  document.body.classList.add('modal-open');
  document.querySelector('#closeRoles').focus();
});

function closeRoleSelection() {
  roleSelection.hidden = true;
  document.body.classList.remove('modal-open');
  document.querySelector('#loginTrigger').focus();
}

function openLogin(role, card) {
  lastSelectedRole = card;
  const names = { mahasiswa: 'Mahasiswa', dosen: 'Dosen', admin: 'Admin' };
  const registrationPages = {
    mahasiswa: 'daftar.html',
    dosen: 'daftar_dosen.html',
  };
  const labels = {
    mahasiswa: 'NIM atau username',
    dosen: 'NIDN atau username',
    admin: 'Username',
  };
  roleInput.value = role;
  loginTitle.textContent = `Login ${names[role]}`;
  usernameLabel.textContent = labels[role];
  usernameInput.placeholder = `Masukkan ${labels[role].toLowerCase()}`;
  const registrationPage = registrationPages[role];
  registrationDivider.hidden = !registrationPage;
  registrationLink.hidden = !registrationPage;
  if (registrationPage) {
    registrationLink.href = registrationPage;
    registrationLink.textContent = `Daftar Akun ${names[role]}`;
  }
  loginMessage.textContent = '';
  passwordInput.value = '';
  roleSelection.hidden = true;
  loginModal.hidden = false;
  document.body.classList.add('modal-open');
  usernameInput.focus();
}

const registrationBanner = document.querySelector('#registrationBanner');
const registrationStatus = new URLSearchParams(window.location.search).get('daftar');
const registrationMessages = {
  berhasil: {
    message: 'Akun berhasil dibuat, QR Anda sudah tersedia. Silakan masuk.',
    className: 'registration-banner-success',
  },
  menunggu: {
    message: 'Pendaftaran berhasil. Akun dosen Anda menunggu persetujuan admin sebelum bisa masuk.',
    className: 'registration-banner-pending',
  },
};
if (registrationMessages[registrationStatus]) {
  const banner = registrationMessages[registrationStatus];
  registrationBanner.textContent = banner.message;
  registrationBanner.classList.add(banner.className);
  registrationBanner.hidden = false;
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.delete('daftar');
  history.replaceState(null, '', `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`);
}

document.querySelectorAll('.role-card').forEach((card) => {
  card.addEventListener('click', () => openLogin(card.dataset.role, card));
});

document.querySelector('#closeRoles').addEventListener('click', closeRoleSelection);
roleSelection.addEventListener('click', (event) => {
  if (event.target === roleSelection) closeRoleSelection();
});

function closeLogin() {
  loginModal.hidden = true;
  roleSelection.hidden = false;
  document.body.classList.add('modal-open');
  if (lastSelectedRole) lastSelectedRole.focus();
}

document.querySelector('#closeLogin').addEventListener('click', closeLogin);
loginModal.addEventListener('click', (event) => {
  if (event.target === loginModal) closeLogin();
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && !loginModal.hidden) closeLogin();
  else if (event.key === 'Escape' && !roleSelection.hidden) closeRoleSelection();
});

passwordToggle.addEventListener('click', () => {
  const visible = passwordInput.type === 'password';
  passwordInput.type = visible ? 'text' : 'password';
  passwordToggle.setAttribute('aria-pressed', String(visible));
  passwordToggle.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
});

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  loginMessage.textContent = '';
  submitLogin.disabled = true;

  try {
    const response = await fetch('api/login.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({
        username: usernameInput.value,
        password: passwordInput.value,
        role: roleInput.value,
      }),
    });
    const result = await response.json();

    if (response.ok && result.status === 'success'
      && allowedRedirects[result.user.role] === result.redirect) {
      window.location.assign(result.redirect);
      return;
    }

    loginMessage.textContent = result.message || 'Username, kata sandi, atau peran salah';
  } catch (error) {
    loginMessage.textContent = 'Tidak dapat terhubung ke server. Coba lagi.';
  } finally {
    submitLogin.disabled = false;
  }
});
