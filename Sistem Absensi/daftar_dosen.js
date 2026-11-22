const form = document.querySelector('#lecturerRegistrationForm');
if (window.initWilayahSelector) window.initWilayahSelector(form);
const message = document.querySelector('#lecturerRegistrationMessage');
const submitButton = document.querySelector('#submitLecturerRegistration');
const passwordInput = document.querySelector('#password');
const passwordToggle = document.querySelector('#lecturerPasswordToggle');

passwordToggle.addEventListener('click', () => {
  const visible = passwordInput.type === 'password';
  passwordInput.type = visible ? 'text' : 'password';
  passwordToggle.setAttribute('aria-pressed', String(visible));
  passwordToggle.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  if (!form.reportValidity()) return;

  submitButton.disabled = true;
  try {
    const response = await fetch('api/pendaftaran_dosen.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({
        nidn: form.elements.nidn.value,
        nama_lengkap: form.elements.nama_lengkap.value,
        prodi: form.elements.prodi.value,
        provinsi: form.elements.provinsi.value,
        kota: form.elements.kota.value,
        kecamatan: form.elements.kecamatan.value,
        kelurahan: form.elements.kelurahan.value,
        username: form.elements.username.value,
        password: passwordInput.value,
      }),
    });
    const result = await response.json();

    if (!response.ok) {
      message.textContent = result.message || 'Pendaftaran dosen gagal.';
      return;
    }

    window.location.assign('index.html?daftar=menunggu');
  } catch (error) {
    message.textContent = 'Tidak dapat terhubung ke server. Coba lagi.';
  } finally {
    submitButton.disabled = false;
  }
});
