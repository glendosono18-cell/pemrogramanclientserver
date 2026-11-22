const requiredRole = document.body.dataset.role;

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
  });
  let result = {};
  try {
    result = await response.json();
  } catch (error) {
    throw new Error('Respons server tidak valid');
  }
  if (!response.ok) {
    throw new Error(result.message || 'Permintaan tidak dapat diproses');
  }
  return result;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[character]);
}

async function loadDashboardShell() {
  try {
    const session = await requestJson('../api/session.php');
    if (session.role !== requiredRole) {
      window.location.replace('../index.html');
      return;
    }

    const result = await requestJson('../api/profil.php');
    const profile = result.profile || {};
    const name = profile.nama_lengkap || profile.nama || session.username;
    document.querySelectorAll('#profile-name').forEach((element) => {
      element.textContent = name;
    });

    const profileInfo = document.querySelector('#profile-info');
    if (requiredRole === 'mahasiswa') {
      profileInfo.textContent = `NIM ${profile.nim} · ${profile.prodi} · Semester ${profile.semester}`;
    } else if (requiredRole === 'dosen') {
      profileInfo.textContent = `NIDN ${profile.nidn} · ${profile.prodi}`;
    } else {
      profileInfo.textContent = 'Pengelola seluruh sistem absensi';
    }

    const status = document.querySelector('#profile-status');
    if (status) status.textContent = 'Profil berhasil dimuat.';
  } catch (error) {
    if (error.message === 'Silakan masuk terlebih dahulu'
      || error.message === 'Akun dosen Anda menunggu persetujuan admin'
      || error.message === 'Pendaftaran akun dosen Anda ditolak. Hubungi admin.') {
      window.location.replace('../index.html');
      return;
    }
    const status = document.querySelector('#profile-status');
    if (status) status.textContent = 'Data profil belum dapat dimuat.';
  }
}

const logoutButton = document.querySelector('[data-logout]');
if (logoutButton) {
  logoutButton.addEventListener('click', async () => {
    try {
      await requestJson('../api/logout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
        body: JSON.stringify({}),
      });
    } finally {
      window.location.replace('../index.html');
    }
  });
}

loadDashboardShell();
