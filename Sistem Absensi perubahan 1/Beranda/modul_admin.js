async function loadAdminDashboard() {
  const result = await requestJson('../api/admin.php?bagian=ringkasan');
  const cards = document.querySelectorAll('[data-view="dashboard"] .card');
  if (cards[0]) cards[0].querySelector('strong').textContent = result.statistik.mahasiswa;
  if (cards[1]) cards[1].querySelector('strong').textContent = result.statistik.dosen;
  if (cards[2]) cards[2].querySelector('strong').textContent = result.statistik.kelas;
  const pendingCount = document.querySelector('#dosen-menunggu-count');
  if (pendingCount) pendingCount.textContent = result.statistik.dosen_menunggu;
  renderAdminDonut('admin-mahasiswa-donut', 'admin-mahasiswa-legend', result.distribusi_mahasiswa, result.statistik.mahasiswa, 'Jumlah mahasiswa');
  renderAdminDonut('admin-dosen-donut', 'admin-dosen-legend', result.distribusi_dosen, result.statistik.dosen, 'Jumlah dosen');
}

function renderAdminDonut(chartId, legendId, items, total, label) {
  const chart = document.getElementById(chartId);
  const legend = document.getElementById(legendId);
  const colors = ['#1b4f9c', '#1d9b6c', '#f2b134', '#c62828', '#7452a8', '#e8793a'];
  const circumference = 2 * Math.PI * 40;
  let offset = 0;
  const segments = items.filter((item) => Number(item.jumlah) > 0).map((item, index) => {
    const length = circumference * Number(item.jumlah) / Math.max(1, Number(total));
    const color = colors[index % colors.length];
    const markup = `<circle class="ak-admin-donut-segment" cx="50" cy="50" r="40" stroke="${color}" stroke-dasharray="${length.toFixed(3)} ${(circumference - length).toFixed(3)}" stroke-dashoffset="${(-offset).toFixed(3)}"><title>${escapeHtml(item.kategori)}: ${item.jumlah}</title></circle>`;
    offset += length;
    return { markup, color, item };
  });
  chart.innerHTML = `<svg class="ak-admin-donut" viewBox="0 0 100 100" role="img" aria-label="${escapeHtml(label)} ${Number(total)}">
    <circle class="ak-admin-donut-track" cx="50" cy="50" r="40"></circle>
    ${segments.map((segment) => segment.markup).join('')}
    <text class="ak-admin-donut-total" x="50" y="50">${Number(total)}</text>
  </svg>`;
  legend.innerHTML = items.map((item) => {
    const index = segments.findIndex((segment) => segment.item === item);
    const color = index >= 0 ? segments[index].color : '#aeb8c6';
    return `<div><span class="ak-admin-donut-key" style="--ak-admin-donut-color:${color}"></span><span>${escapeHtml(item.kategori)}</span><strong>${Number(item.jumlah)}</strong></div>`;
  }).join('') || '<p class="ak-state">Belum ada data.</p>';
}

async function adminPost(payload) {
  return requestJson('../api/admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json; charset=utf-8' },
    body: JSON.stringify(payload),
  });
}

let activeAdminChatTarget = null;
let adminChatPollingTimer = null;

const adminChatFileInput = document.querySelector('#admin-chat-file');
const adminChatFilePreview = document.querySelector('#admin-chat-file-preview');
const adminChatFileName = document.querySelector('#admin-chat-file-name');
function clearAdminChatFile() {
  if (adminChatFileInput) adminChatFileInput.value = '';
  if (adminChatFilePreview) adminChatFilePreview.hidden = true;
  if (adminChatFileName) adminChatFileName.textContent = '';
}
adminChatFileInput?.addEventListener('change', () => {
  const file = adminChatFileInput.files?.[0];
  if (file) {
    adminChatFileName.textContent = file.name;
    adminChatFilePreview.hidden = false;
  } else {
    clearAdminChatFile();
  }
});
document.querySelector('#admin-chat-file-clear')?.addEventListener('click', clearAdminChatFile);

async function loadAdminConversations() {
  const result = await requestJson('../api/chat.php?bagian=daftar');
  document.querySelector('#admin-chat-list-state').textContent = '';
  document.querySelector('#admin-chat-conversations').innerHTML = result.percakapan.length
    ? result.percakapan.map((item) => {
      const tipe = item.tipe || 'mahasiswa';
      const id = item.id_pengguna || item.id_mahasiswa;
      const isActive = activeAdminChatTarget
        && activeAdminChatTarget.tipe === tipe
        && activeAdminChatTarget.id === id;
      const roleLabel = tipe === 'dosen' ? 'Dosen' : 'Mahasiswa';
      return `
      <button class="ak-admin-conversation ${isActive ? 'is-active' : ''}" type="button" data-admin-chat-type="${escapeHtml(tipe)}" data-admin-chat-id="${id}">
        <span><strong>${escapeHtml(item.nama_lengkap)} <span class="ak-badge ${tipe === 'dosen' ? 'ak-badge-info' : ''}">${roleLabel}</span></strong>
        <small>${escapeHtml(item.nomor_induk || item.nim || '')} · ${escapeHtml(item.username)}</small>
        <small>${escapeHtml(item.pesan_terakhir || '')}</small></span>
        ${item.belum_dibaca ? `<span class="ak-badge ak-chat-unread">${item.belum_dibaca}</span>` : ''}
      </button>`;
    }).join('')
    : '<p class="ak-state">Belum ada pesan dari mahasiswa atau dosen.</p>';
  const unreadTotal = result.percakapan.reduce((total, item) => total + item.belum_dibaca, 0);
  const badge = document.querySelector('#admin-chat-unread');
  badge.textContent = unreadTotal > 99 ? '99+' : String(unreadTotal);
  badge.hidden = unreadTotal < 1;
}

async function loadAdminChatThread(target) {
  if (!target) return;
  const query = new URLSearchParams({
    bagian: 'percakapan',
    tipe: target.tipe,
    id_pengguna: String(target.id),
  });
  const result = await requestJson(`../api/chat.php?${query.toString()}`);
  const conversation = result.pesan;
  const title = document.querySelector('#admin-chat-title');
  const btn = document.querySelector(`[data-admin-chat-type="${target.tipe}"][data-admin-chat-id="${target.id}"]`);
  title.textContent = btn
    ? btn.querySelector('strong').textContent
    : (target.tipe === 'dosen' ? 'Percakapan dosen' : 'Percakapan mahasiswa');
  document.querySelector('#admin-chat-messages').innerHTML = conversation.map((message) => {
    const attachment = message.lampiran_path
      ? ((message.lampiran_tipe || '').startsWith('image/')
        ? `<a class="ak-chat-attachment-image" href="../${escapeHtml(message.lampiran_path)}" target="_blank" rel="noopener"><img src="../${escapeHtml(message.lampiran_path)}" alt="${escapeHtml(message.lampiran_nama || 'Lampiran')}" /></a>`
        : `<a class="ak-chat-attachment-file" href="../${escapeHtml(message.lampiran_path)}" target="_blank" rel="noopener" download="${escapeHtml(message.lampiran_nama || 'lampiran')}">📎 ${escapeHtml(message.lampiran_nama || 'Unduh lampiran')}</a>`)
      : '';
    return `
    <article class="ak-chat-message ${message.pengirim === 'admin' ? 'is-mine' : 'is-admin'}">
      ${message.isi ? `<p>${escapeHtml(message.isi)}</p>` : ''}${attachment}<time>${escapeHtml(message.dibuat_pada)}</time>
    </article>`;
  }).join('') || '<p class="ak-chat-empty">Belum ada pesan.</p>';
  const messages = document.querySelector('#admin-chat-messages');
  messages.scrollTop = messages.scrollHeight;
  document.querySelector('#admin-chat-input').disabled = false;
  if (adminChatFileInput) adminChatFileInput.disabled = false;
  document.querySelector('#admin-chat-form button[type="submit"]').disabled = false;
}

document.querySelector('#admin-chat-conversations')?.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-admin-chat-id]');
  if (!button) return;
  activeAdminChatTarget = {
    tipe: button.dataset.adminChatType || 'mahasiswa',
    id: Number(button.dataset.adminChatId),
  };
  document.querySelector('#admin-chat-state').textContent = '';
  try {
    await loadAdminConversations();
    await loadAdminChatThread(activeAdminChatTarget);
  } catch (error) {
    document.querySelector('#admin-chat-state').textContent = error.message;
  }
});

document.querySelector('#admin-chat-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!activeAdminChatTarget) return;
  const input = document.querySelector('#admin-chat-input');
  const button = event.currentTarget.querySelector('button[type="submit"]');
  const file = adminChatFileInput?.files?.[0] || null;
  if (!input.value.trim() && !file) {
    document.querySelector('#admin-chat-state').textContent = 'Tulis balasan atau pilih file lampiran terlebih dahulu.';
    return;
  }
  button.disabled = true;
  try {
    const formData = new FormData();
    formData.append('aksi', 'kirim');
    formData.append('tipe', activeAdminChatTarget.tipe);
    formData.append('id_pengguna', String(activeAdminChatTarget.id));
    formData.append('isi', input.value);
    if (file) formData.append('lampiran', file);
    const result = await requestJson('../api/chat.php', {
      method: 'POST',
      body: formData,
    });
    input.value = '';
    clearAdminChatFile();
    document.querySelector('#admin-chat-state').textContent = result.message;
    await loadAdminChatThread(activeAdminChatTarget);
    await loadAdminConversations();
  } catch (error) {
    document.querySelector('#admin-chat-state').textContent = error.message;
  } finally {
    button.disabled = false;
  }
});

async function loadMahasiswa() {
  const query = encodeURIComponent(document.querySelector('#mahasiswa-cari')?.value.trim() || '');
  const result = await requestJson(`../api/admin.php?bagian=mahasiswa&q=${query}`);
  document.querySelector('#mahasiswa-list').innerHTML = result.mahasiswa.map((student) => `
    <tr><td>${escapeHtml(student.nim)}</td><td>${escapeHtml(student.nama_lengkap)}</td>
    <td>${escapeHtml(student.prodi)}</td><td>${student.semester}</td><td>${escapeHtml(student.username)}</td>
    <td>${student.jumlah_wajah}/3</td><td>
      <button class="ak-secondary-button" type="button" data-mahasiswa-edit="${student.id_mahasiswa}" data-nim="${escapeHtml(student.nim)}" data-nama="${escapeHtml(student.nama_lengkap)}" data-prodi="${escapeHtml(student.prodi)}" data-semester="${student.semester}" data-username="${escapeHtml(student.username)}" data-active="${student.aktif}">Ubah</button>
      <button class="ak-secondary-button" type="button" data-mahasiswa-reset-qr="${student.id_mahasiswa}">Reset QR</button>
      <button class="ak-secondary-button" type="button" data-mahasiswa-reset-face="${student.id_mahasiswa}">Reset wajah</button>
      <button class="ak-secondary-button" type="button" data-mahasiswa-delete="${student.id_mahasiswa}">Hapus</button>
    </td></tr>`).join('');
}

const adminMahasiswaForm = document.querySelector('#mahasiswa-form');
const adminMahasiswaWilayah = adminMahasiswaForm && window.initWilayahSelector
  ? window.initWilayahSelector(adminMahasiswaForm)
  : null;
adminMahasiswaForm?.addEventListener('reset', () => {
  window.setTimeout(() => adminMahasiswaWilayah?.reset(), 0);
});
adminMahasiswaForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.aksi = 'mahasiswa_simpan';
  payload.semester = Number(payload.semester);
  try {
    const result = await adminPost(payload);
    document.querySelector('#mahasiswa-state').textContent = result.message;
    form.reset();
    adminMahasiswaWilayah?.reset();
    await loadMahasiswa();
    academicData = null;
  } catch (error) {
    document.querySelector('#mahasiswa-state').textContent = error.message;
  }
});

document.querySelector('#mahasiswa-cari-button')?.addEventListener('click', () => loadMahasiswa().catch((error) => {
  document.querySelector('#mahasiswa-state').textContent = error.message;
}));

document.querySelector('#mahasiswa-list')?.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  const state = document.querySelector('#mahasiswa-state');
  try {
    if (button.dataset.mahasiswaEdit) {
      const nim = window.prompt('NIM', button.dataset.nim);
      const nama = window.prompt('Nama lengkap', button.dataset.nama);
      const prodi = window.prompt('Program studi', button.dataset.prodi);
      const semester = Number(window.prompt('Semester', button.dataset.semester));
      const username = window.prompt('Username', button.dataset.username);
      if (!nim || !nama || !prodi || !username || !Number.isInteger(semester)) return;
      await adminPost({ aksi: 'mahasiswa_update', id_mahasiswa: Number(button.dataset.mahasiswaEdit), nim, nama_lengkap: nama, prodi, semester, username, aktif: button.dataset.active === '1' });
    } else if (button.dataset.mahasiswaResetQr) {
      await adminPost({ aksi: 'mahasiswa_reset_qr', id_mahasiswa: Number(button.dataset.mahasiswaResetQr) });
    } else if (button.dataset.mahasiswaResetFace) {
      if (!window.confirm('Reset semua data wajah mahasiswa ini?')) return;
      await adminPost({ aksi: 'mahasiswa_reset_wajah', id_mahasiswa: Number(button.dataset.mahasiswaResetFace) });
    } else if (button.dataset.mahasiswaDelete) {
      if (!window.confirm('Hapus akun mahasiswa ini beserta data terkait?')) return;
      await adminPost({ aksi: 'mahasiswa_hapus', id_mahasiswa: Number(button.dataset.mahasiswaDelete) });
    }
    state.textContent = 'Perubahan berhasil disimpan.';
    await loadMahasiswa();
  } catch (error) {
    state.textContent = error.message;
  }
});

async function loadDosen() {
  const status = encodeURIComponent(document.querySelector('#dosen-status-filter')?.value || '');
  const query = encodeURIComponent(document.querySelector('#dosen-cari')?.value.trim() || '');
  const result = await requestJson(`../api/admin.php?bagian=dosen&status=${status}&q=${query}`);
  const statusStyles = {
    Menunggu: 'ak-badge-waiting',
    Aktif: 'ak-badge-success',
    Ditolak: 'ak-badge-rejected',
  };
  document.querySelector('#dosen-list').innerHTML = result.dosen.map((lecturer) => `
    <tr><td>${escapeHtml(lecturer.nidn)}</td><td>${escapeHtml(lecturer.nama_lengkap)}</td>
    <td>${escapeHtml(lecturer.prodi)}</td><td>${escapeHtml(lecturer.username)}</td>
    <td><span class="ak-badge ${statusStyles[lecturer.status_akun] || ''}">${escapeHtml(lecturer.status_akun)}</span></td><td>
      ${lecturer.status_akun === 'Menunggu' ? `<button class="ak-secondary-button" type="button" data-dosen-keputusan="setujui" data-dosen-id="${lecturer.id_dosen}">Setujui</button>
      <button class="ak-secondary-button" type="button" data-dosen-keputusan="tolak" data-dosen-id="${lecturer.id_dosen}">Tolak</button>` : ''}
      <button class="ak-secondary-button" type="button" data-dosen-edit="${lecturer.id_dosen}" data-nidn="${escapeHtml(lecturer.nidn)}" data-nama="${escapeHtml(lecturer.nama_lengkap)}" data-prodi="${escapeHtml(lecturer.prodi)}" data-username="${escapeHtml(lecturer.username)}" data-provinsi="${escapeHtml(lecturer.provinsi || '')}" data-kota="${escapeHtml(lecturer.kota || '')}" data-kecamatan="${escapeHtml(lecturer.kecamatan || '')}" data-kelurahan="${escapeHtml(lecturer.kelurahan || '')}">Ubah</button>
      <button class="ak-secondary-button" type="button" data-dosen-delete="${lecturer.id_dosen}">Hapus</button>
    </td></tr>`).join('');
}

let dosenSearchTimer = null;
document.querySelector('#dosen-cari')?.addEventListener('input', () => {
  window.clearTimeout(dosenSearchTimer);
  dosenSearchTimer = window.setTimeout(() => loadDosen().catch((error) => {
    document.querySelector('#dosen-state').textContent = error.message;
  }), 300);
});

document.querySelector('#dosen-status-filter')?.addEventListener('change', () => loadDosen().catch((error) => {
  document.querySelector('#dosen-state').textContent = error.message;
}));

const adminDosenForm = document.querySelector('#dosen-form');
const adminDosenWilayah = adminDosenForm && window.initWilayahSelector
  ? window.initWilayahSelector(adminDosenForm)
  : null;
function setDosenWilayahRequired(required) {
  if (!adminDosenForm) return;
  ['provinsi', 'kota', 'kecamatan', 'kelurahan'].forEach((name) => {
    if (adminDosenForm.elements[name]) adminDosenForm.elements[name].required = required;
  });
}
adminDosenForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.aksi = 'dosen_simpan';
  payload.id_dosen = payload.id_dosen ? Number(payload.id_dosen) : null;
  try {
    const result = await adminPost(payload);
    document.querySelector('#dosen-state').textContent = result.message;
    form.reset();
    form.elements.id_dosen.value = '';
    setDosenWilayahRequired(true);
    adminDosenWilayah?.reset();
    await loadDosen();
  } catch (error) {
    document.querySelector('#dosen-state').textContent = error.message;
  }
});

document.querySelector('#dosen-reset')?.addEventListener('click', () => {
  if (!adminDosenForm) return;
  adminDosenForm.reset();
  adminDosenForm.elements.id_dosen.value = '';
  setDosenWilayahRequired(true);
  adminDosenWilayah?.reset();
});
document.querySelector('#dosen-list')?.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  if (button.dataset.dosenKeputusan) {
    try {
      const decision = button.dataset.dosenKeputusan;
      const result = await requestJson('../api/dosen.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
        body: JSON.stringify({
          aksi: 'persetujuan',
          id_dosen: Number(button.dataset.dosenId),
          keputusan: decision,
        }),
      });
      document.querySelector('#dosen-state').textContent = result.message;
      await loadDosen();
      await loadAdminDashboard();
    } catch (error) {
      document.querySelector('#dosen-state').textContent = error.message;
    }
  } else if (button.dataset.dosenEdit) {
    const form = document.querySelector('#dosen-form');
    form.elements.id_dosen.value = button.dataset.dosenEdit;
    form.elements.nidn.value = button.dataset.nidn;
    form.elements.nama_lengkap.value = button.dataset.nama;
    form.elements.prodi.value = button.dataset.prodi;
    form.elements.username.value = button.dataset.username;
    setDosenWilayahRequired(false);
    adminDosenWilayah?.setValues({
      provinsi: button.dataset.provinsi || '',
      kota: button.dataset.kota || '',
      kecamatan: button.dataset.kecamatan || '',
      kelurahan: button.dataset.kelurahan || '',
    });
  } else if (button.dataset.dosenDelete) {
    if (!window.confirm('Hapus akun dosen ini?')) return;
    try {
      await adminPost({ aksi: 'dosen_hapus', id_dosen: Number(button.dataset.dosenDelete) });
      document.querySelector('#dosen-state').textContent = 'Dosen berhasil dihapus.';
      await loadDosen();
    } catch (error) {
      document.querySelector('#dosen-state').textContent = error.message;
    }
  }
});

let academicData = null;
async function loadAcademic() {
  academicData = await requestJson('../api/admin.php?bagian=akademik');
  document.querySelector('#tahun-list').innerHTML = academicData.tahun.map((item) =>
    `<tr><td>${escapeHtml(item.tahun)}</td><td>${escapeHtml(item.semester)}</td><td><span class="ak-badge ${item.aktif ? 'ak-badge-success' : 'ak-badge-info'}">${item.aktif ? 'Aktif' : 'Tidak aktif'}</span></td><td>${item.aktif ? '' : `<button class="ak-secondary-button" type="button" data-tahun-activate="${item.id_tahun_akademik}">Aktifkan</button>`} <button class="ak-secondary-button" type="button" data-tahun-edit="${item.id_tahun_akademik}" data-tahun="${escapeHtml(item.tahun)}" data-semester="${escapeHtml(item.semester)}" data-aktif="${item.aktif}">Ubah</button></td></tr>`
  ).join('');
  document.querySelector('#mata-list').innerHTML = academicData.mata_kuliah.map((item) =>
    `<tr><td>${escapeHtml(item.kode)}</td><td>${escapeHtml(item.nama)}</td><td>${item.sks}</td><td>${item.semester}</td><td><button class="ak-secondary-button" type="button" data-mata-edit="${item.id_mata_kuliah}" data-kode="${escapeHtml(item.kode)}" data-nama="${escapeHtml(item.nama)}" data-sks="${item.sks}" data-semester="${item.semester}">Ubah</button></td></tr>`
  ).join('');
  const courseSelect = document.querySelector('#kelas-form [name="id_mata_kuliah"]');
  const yearSelect = document.querySelector('#kelas-form [name="id_tahun_akademik"]');
  const firstLecturerSelect = document.querySelector('#kelas-form [name="id_dosen_1"]');
  const secondLecturerSelect = document.querySelector('#kelas-form [name="id_dosen_2"]');
  courseSelect.innerHTML = academicData.mata_kuliah.map((item) => `<option value="${item.id_mata_kuliah}">${escapeHtml(item.kode)} - ${escapeHtml(item.nama)}</option>`).join('');
  yearSelect.innerHTML = academicData.tahun.map((item) => `<option value="${item.id_tahun_akademik}">${escapeHtml(item.tahun)} ${escapeHtml(item.semester)}</option>`).join('');
  const activeYear = academicData.tahun.find((item) => item.aktif === 1);
  if (activeYear) yearSelect.value = String(activeYear.id_tahun_akademik);
  const lecturerOptions = academicData.dosen.map((item) =>
    `<option value="${item.id_dosen}">${escapeHtml(item.nama_lengkap)} (${escapeHtml(item.nidn)})</option>`
  ).join('');
  firstLecturerSelect.innerHTML = lecturerOptions;
  secondLecturerSelect.innerHTML = '<option value="">Tanpa dosen kedua</option>' + lecturerOptions;
  document.querySelector('#kelas-list').innerHTML = academicData.kelas.map((item) =>
    `<tr><td>${escapeHtml(item.kode)} - ${escapeHtml(item.nama_mata_kuliah)}</td><td>${item.semester_mata_kuliah}</td><td>${escapeHtml(item.nama_kelas)}</td><td>${escapeHtml(item.hari)} ${escapeHtml(item.jam_mulai).slice(0, 5)}-${escapeHtml(item.jam_selesai).slice(0, 5)}</td><td>${item.tim_pengajar.map((name) => escapeHtml(name)).join('<br>')}</td><td>${item.jumlah_peserta}</td><td><button class="ak-secondary-button" type="button" data-kelas-edit="${item.id_kelas}" data-mk="${item.id_mata_kuliah}" data-year="${item.id_tahun_akademik}" data-kelas-nama="${escapeHtml(item.nama_kelas)}" data-hari="${escapeHtml(item.hari)}" data-mulai="${escapeHtml(item.jam_mulai).slice(0, 5)}" data-selesai="${escapeHtml(item.jam_selesai).slice(0, 5)}" data-ruangan="${escapeHtml(item.ruangan)}" data-dosen-1="${item.id_dosen_1 || ''}" data-dosen-2="${item.id_dosen_2 || ''}">Ubah</button></td></tr>`
  ).join('');
  document.querySelector('#peserta-kelas').innerHTML =
    '<option value="">Pilih kelas</option>' + academicData.kelas.map((item) =>
      `<option value="${item.id_kelas}">${escapeHtml(item.kode)} - ${escapeHtml(item.nama_kelas)}</option>`
    ).join('');
}

document.querySelector('#tahun-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.aksi = 'tahun_simpan';
  payload.id_tahun_akademik = payload.id_tahun_akademik ? Number(payload.id_tahun_akademik) : null;
  payload.aktif = form.elements.aktif.checked;
  try {
    const result = await adminPost(payload);
    document.querySelector('#tahun-state').textContent = result.message;
    form.reset();
    await Promise.all([loadAcademic(), loadAdminDashboard()]);
  } catch (error) {
    document.querySelector('#tahun-state').textContent = error.message;
  }
});

document.querySelector('#tahun-list')?.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  const state = document.querySelector('#tahun-state');
  try {
    if (button.dataset.tahunActivate) {
      const result = await adminPost({
        aksi: 'tahun_aktifkan',
        id_tahun_akademik: Number(button.dataset.tahunActivate),
      });
      state.textContent = result.message;
      await Promise.all([loadAcademic(), loadAdminDashboard()]);
      return;
    }
    if (button.dataset.tahunEdit) {
      const form = document.querySelector('#tahun-form');
      form.elements.id_tahun_akademik.value = button.dataset.tahunEdit;
      form.elements.tahun.value = button.dataset.tahun;
      form.elements.semester.value = button.dataset.semester;
      form.elements.aktif.checked = button.dataset.aktif === '1';
      state.textContent = '';
    }
  } catch (error) {
    state.textContent = error.message;
  }
});

document.querySelector('#mata-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = Object.fromEntries(new FormData(event.currentTarget).entries());
  payload.aksi = 'mata_simpan';
  payload.id_mata_kuliah = payload.id_mata_kuliah ? Number(payload.id_mata_kuliah) : null;
  payload.sks = Number(payload.sks);
  try {
    const result = await adminPost(payload);
    document.querySelector('#mata-state').textContent = result.message;
    event.currentTarget.reset();
    await loadAcademic();
  } catch (error) {
    document.querySelector('#mata-state').textContent = error.message;
  }
});

document.querySelector('#mata-reset')?.addEventListener('click', () => document.querySelector('#mata-form').reset());
document.querySelector('#mata-list')?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-mata-edit]');
  if (!button) return;
  const form = document.querySelector('#mata-form');
  form.elements.id_mata_kuliah.value = button.dataset.mataEdit;
  form.elements.kode.value = button.dataset.kode;
  form.elements.nama.value = button.dataset.nama;
  form.elements.sks.value = button.dataset.sks;
  form.elements.semester.value = button.dataset.semester;
});

document.querySelector('#kelas-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = Object.fromEntries(new FormData(event.currentTarget).entries());
  payload.aksi = 'kelas_simpan';
  payload.id_kelas = payload.id_kelas ? Number(payload.id_kelas) : null;
  ['id_mata_kuliah', 'id_tahun_akademik', 'id_dosen_1'].forEach((key) => payload[key] = Number(payload[key]));
  payload.id_dosen_2 = payload.id_dosen_2 ? Number(payload.id_dosen_2) : null;
  try {
    const result = await adminPost(payload);
    document.querySelector('#kelas-state').textContent = result.message;
    await loadAcademic();
  } catch (error) {
    document.querySelector('#kelas-state').textContent = error.message;
  }
});

document.querySelector('#kelas-list')?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-kelas-edit]');
  if (!button) return;
  const form = document.querySelector('#kelas-form');
  form.elements.id_kelas.value = button.dataset.kelasEdit;
  form.elements.id_mata_kuliah.value = button.dataset.mk;
  form.elements.id_tahun_akademik.value = button.dataset.year;
  form.elements.id_dosen_1.value = button.dataset.dosen1;
  form.elements.id_dosen_2.value = button.dataset.dosen2;
  form.elements.nama_kelas.value = button.dataset.kelasNama;
  form.elements.hari.value = button.dataset.hari;
  form.elements.jam_mulai.value = button.dataset.mulai;
  form.elements.jam_selesai.value = button.dataset.selesai;
  form.elements.ruangan.value = button.dataset.ruangan;
});

document.querySelector('#kelas-reset')?.addEventListener('click', () => document.querySelector('#kelas-form').reset());

document.querySelector('#peserta-kelas')?.addEventListener('change', async (event) => {
  const classId = event.target.value;
  const options = document.querySelector('#peserta-options');
  const save = document.querySelector('#peserta-simpan');
  save.disabled = !classId;
  if (!classId) {
    options.innerHTML = '';
    return;
  }
  const result = await requestJson(`../api/admin.php?bagian=peserta&id_kelas=${encodeURIComponent(classId)}`);
  const selected = new Set(result.peserta.map((item) => item.id_mahasiswa));
  options.innerHTML = academicData.mahasiswa.map((item) =>
    `<label class="remember-row"><input type="checkbox" value="${item.id_mahasiswa}" ${selected.has(item.id_mahasiswa) ? 'checked' : ''}><span>${escapeHtml(item.nim)} - ${escapeHtml(item.nama_lengkap)}</span></label>`
  ).join('');
});

document.querySelector('#peserta-simpan')?.addEventListener('click', async () => {
  const classId = Number(document.querySelector('#peserta-kelas').value);
  const studentIds = Array.from(document.querySelectorAll('#peserta-options input:checked')).map((input) => Number(input.value));
  try {
    const result = await adminPost({ aksi: 'peserta_simpan', id_kelas: classId, id_mahasiswa: studentIds });
    document.querySelector('#kelas-state').textContent = result.message;
    await loadAcademic();
  } catch (error) {
    document.querySelector('#kelas-state').textContent = error.message;
  }
});

async function loadPengumuman() {
  const result = await requestJson('../api/admin.php?bagian=pengumuman');
  document.querySelector('#pengumuman-list').innerHTML = result.pengumuman.map((item) =>
    `<article class="info-card"><h3>${escapeHtml(item.judul)}</h3><p>${escapeHtml(item.isi)}</p><small>${escapeHtml(item.dibuat_pada)}</small><div><button class="ak-secondary-button" type="button" data-pengumuman-edit="${item.id_pengumuman}" data-judul="${escapeHtml(item.judul)}" data-isi="${escapeHtml(item.isi)}">Ubah</button><button class="ak-secondary-button" type="button" data-pengumuman-delete="${item.id_pengumuman}">Hapus</button></div></article>`
  ).join('');
}

document.querySelector('#pengumuman-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = Object.fromEntries(new FormData(event.currentTarget).entries());
  payload.aksi = 'pengumuman_simpan';
  payload.id_pengumuman = payload.id_pengumuman ? Number(payload.id_pengumuman) : null;
  try {
    const result = await adminPost(payload);
    document.querySelector('#pengumuman-state').textContent = result.message;
    event.currentTarget.reset();
    await loadPengumuman();
  } catch (error) {
    document.querySelector('#pengumuman-state').textContent = error.message;
  }
});
document.querySelector('#pengumuman-reset')?.addEventListener('click', () => document.querySelector('#pengumuman-form').reset());
document.querySelector('#pengumuman-list')?.addEventListener('click', async (event) => {
  const edit = event.target.closest('[data-pengumuman-edit]');
  if (edit) {
    const form = document.querySelector('#pengumuman-form');
    form.elements.id_pengumuman.value = edit.dataset.pengumumanEdit;
    form.elements.judul.value = edit.dataset.judul;
    form.elements.isi.value = edit.dataset.isi;
    return;
  }
  const remove = event.target.closest('[data-pengumuman-delete]');
  if (remove && window.confirm('Hapus pengumuman ini?')) {
    try {
      await adminPost({ aksi: 'pengumuman_hapus', id_pengumuman: Number(remove.dataset.pengumumanDelete) });
      await loadPengumuman();
    } catch (error) {
      document.querySelector('#pengumuman-state').textContent = error.message;
    }
  }
});

async function loadPengaturan() {
  const result = await requestJson('../api/admin.php?bagian=pengaturan');
  document.querySelector('#pengaturan-form').elements.ambang_wajah.value = result.ambang_wajah;
  document.querySelector('#pengaturan-form').elements.batas_tepat_menit.value = result.batas_tepat_menit;
}
document.querySelector('#pengaturan-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = Object.fromEntries(new FormData(event.currentTarget).entries());
  payload.aksi = 'pengaturan_simpan';
  payload.ambang_wajah = Number(payload.ambang_wajah);
  payload.batas_tepat_menit = Number(payload.batas_tepat_menit);
  try {
    const result = await adminPost(payload);
    document.querySelector('#pengaturan-state').textContent = result.message;
  } catch (error) {
    document.querySelector('#pengaturan-state').textContent = error.message;
  }
});

function renderAdminAttendanceTrend(trend) {
  const container = document.querySelector('#admin-rekap-trend');
  if (!trend.length) {
    container.innerHTML = '<h3>Tren Kehadiran</h3><p class="ak-state">Belum ada data pertemuan untuk kelas ini.</p>';
    return;
  }
  const chartWidth = 720;
  const chartHeight = 300;
  const left = 58;
  const right = 700;
  const top = 22;
  const bottom = 236;
  const points = trend.map((item, index) => {
    const percent = Math.max(0, Math.min(100, Number(item.persentase_hadir) || 0));
    const x = trend.length === 1 ? (left + right) / 2 : left + index * (right - left) / (trend.length - 1);
    const y = bottom - (percent / 100) * (bottom - top);
    return { x, y, percent, item };
  });
  const linePath = points.map((point, index) => `${index ? 'L' : 'M'} ${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' ');
  const areaPath = `${linePath} L ${points[points.length - 1].x.toFixed(1)} ${bottom} L ${points[0].x.toFixed(1)} ${bottom} Z`;
  const grid = [0, 25, 50, 75, 100].map((value) => {
    const y = bottom - (value / 100) * (bottom - top);
    return `<g><line class="ak-admin-trend-grid" x1="${left}" y1="${y}" x2="${right}" y2="${y}"></line><text class="ak-admin-trend-axis-label" x="${left - 10}" y="${y + 4}" text-anchor="end">${value}%</text></g>`;
  }).join('');
  const pointMarkup = points.map(({ x, y, percent, item }) => {
    const number = Number(item.pertemuan_ke) || 0;
    const attended = (Number(item.hadir) || 0) + (Number(item.terlambat) || 0);
    const total = Number(item.total_peserta) || 0;
    const title = `Pertemuan ${number}: ${percent.toFixed(1)}% (${attended}/${total} mahasiswa hadir atau terlambat)`;
    return `<g><circle class="ak-admin-trend-point" cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="5"><title>${escapeHtml(title)}</title></circle><text class="ak-admin-trend-axis-label" x="${x.toFixed(1)}" y="${bottom + 24}" text-anchor="middle">${number}</text></g>`;
  }).join('');
  container.innerHTML = `
    <h3>Tren Kehadiran</h3>
    <div class="ak-admin-trend-legend"><span></span>Persentase hadir (Hadir + Terlambat)</div>
    <div class="ak-admin-trend-scroll">
      <svg class="ak-admin-trend-chart" viewBox="0 0 ${chartWidth} ${chartHeight}" role="img" aria-label="Persentase kehadiran per pertemuan">
        <defs><linearGradient id="admin-trend-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1b4f9c" stop-opacity="0.28"></stop><stop offset="100%" stop-color="#1b4f9c" stop-opacity="0.02"></stop></linearGradient></defs>
        ${grid}<line class="ak-admin-trend-axis" x1="${left}" y1="${top}" x2="${left}" y2="${bottom}"></line><line class="ak-admin-trend-axis" x1="${left}" y1="${bottom}" x2="${right}" y2="${bottom}"></line>
        <path class="ak-admin-trend-area" d="${areaPath}"></path><path class="ak-admin-trend-line" d="${linePath}"></path>${pointMarkup}
        <text class="ak-admin-trend-axis-title" x="${(left + right) / 2}" y="${chartHeight - 8}" text-anchor="middle">Pertemuan ke-</text>
      </svg>
    </div>`;
}

async function loadAdminRekap() {
  const result = await requestJson('../api/admin.php?bagian=rekap');
  document.querySelector('#admin-rekap-mata-kuliah').innerHTML =
    '<option value="">Pilih mata kuliah</option>' + result.mata_kuliah.map((item) =>
      `<option value="${item.id_mata_kuliah}">${escapeHtml(item.kode)} - ${escapeHtml(item.nama)}</option>`
    ).join('');
  document.querySelector('#admin-rekap-kelas').innerHTML = '<option value="">Pilih kelas</option>';
  document.querySelector('#admin-rekap-pertemuan').innerHTML = '<option value="">Pilih pertemuan</option>';
  document.querySelector('#admin-rekap-kelas').disabled = true;
  document.querySelector('#admin-rekap-pertemuan').disabled = true;
  document.querySelector('#admin-rekap-export').disabled = true;
  document.querySelector('#admin-rekap-list').replaceChildren();
  document.querySelector('#admin-rekap-state').textContent = '';
  renderAdminAttendanceTrend([]);
}
document.querySelector('#admin-rekap-mata-kuliah')?.addEventListener('change', async (event) => {
  const courseId = event.target.value;
  const classSelect = document.querySelector('#admin-rekap-kelas');
  const meetingSelect = document.querySelector('#admin-rekap-pertemuan');
  classSelect.innerHTML = '<option value="">Pilih kelas</option>';
  meetingSelect.innerHTML = '<option value="">Pilih pertemuan</option>';
  classSelect.disabled = !courseId;
  meetingSelect.disabled = true;
  document.querySelector('#admin-rekap-export').disabled = true;
  document.querySelector('#admin-rekap-list').replaceChildren();
  document.querySelector('#admin-rekap-state').textContent = '';
  renderAdminAttendanceTrend([]);
  if (!courseId) return;
  try {
    const result = await requestJson(`../api/admin.php?bagian=rekap&id_mata_kuliah=${encodeURIComponent(courseId)}`);
    if (classSelect.closest('.dashboard-view').hidden || event.target.value !== courseId) return;
    classSelect.innerHTML = '<option value="">Pilih kelas</option>' + result.kelas.map((item) =>
      `<option value="${item.id_kelas}">${escapeHtml(item.kode)} - ${escapeHtml(item.nama)} (${escapeHtml(item.nama_kelas)})</option>`
    ).join('');
  } catch (error) {
    document.querySelector('#admin-rekap-state').textContent = error.message;
  }
});
document.querySelector('#admin-rekap-kelas')?.addEventListener('change', async (event) => {
  const courseId = document.querySelector('#admin-rekap-mata-kuliah').value;
  const classId = event.target.value;
  const meetingSelect = document.querySelector('#admin-rekap-pertemuan');
  meetingSelect.innerHTML = '<option value="">Pilih pertemuan</option>';
  meetingSelect.disabled = !classId;
  document.querySelector('#admin-rekap-export').disabled = true;
  document.querySelector('#admin-rekap-list').replaceChildren();
  renderAdminAttendanceTrend([]);
  if (!courseId || !classId) return;
  try {
    const result = await requestJson(`../api/admin.php?bagian=rekap&id_mata_kuliah=${encodeURIComponent(courseId)}&id_kelas=${encodeURIComponent(classId)}`);
    if (event.target.value !== classId || document.querySelector('#admin-rekap-mata-kuliah').value !== courseId) return;
    meetingSelect.innerHTML = '<option value="">Pilih pertemuan</option>' + result.pertemuan.map((item) =>
      `<option value="${item.id_pertemuan}">Pertemuan ${item.pertemuan_ke} - ${escapeHtml(item.waktu_buka)}</option>`
    ).join('');
    renderAdminAttendanceTrend(result.tren);
    document.querySelector('#admin-rekap-state').textContent = result.pertemuan.length
      ? 'Pilih pertemuan untuk melihat tabel presensi dan mengekspor CSV.'
      : 'Belum ada pertemuan pada kelas ini.';
  } catch (error) {
    document.querySelector('#admin-rekap-state').textContent = error.message;
  }
});
document.querySelector('#admin-rekap-pertemuan')?.addEventListener('change', async (event) => {
  const meetingId = event.target.value;
  const courseId = document.querySelector('#admin-rekap-mata-kuliah').value;
  const classId = document.querySelector('#admin-rekap-kelas').value;
  document.querySelector('#admin-rekap-export').disabled = !meetingId;
  if (!meetingId) {
    document.querySelector('#admin-rekap-list').replaceChildren();
    document.querySelector('#admin-rekap-state').textContent = classId
      ? 'Pilih pertemuan untuk melihat data presensi.'
      : 'Pilih mata kuliah dan kelas.';
    return;
  }
  try {
    const query = `id_mata_kuliah=${encodeURIComponent(courseId)}&id_kelas=${encodeURIComponent(classId)}&id_pertemuan=${encodeURIComponent(meetingId)}`;
    const result = await requestJson(`../api/admin.php?bagian=rekap&${query}`);
    if (event.target.value !== meetingId || document.querySelector('#admin-rekap-kelas').value !== classId) return;
    document.querySelector('#admin-rekap-list').innerHTML = result.peserta.map((item) =>
      `<tr><td>${escapeHtml(item.nim)}</td><td>${escapeHtml(item.nama_lengkap)}</td><td>${escapeHtml(item.status)}</td><td>${escapeHtml(item.metode || '-')}</td><td>${escapeHtml(item.waktu_scan || '-')}</td></tr>`
    ).join('');
    renderAdminAttendanceTrend(result.tren);
    document.querySelector('#admin-rekap-state').textContent = result.peserta.length
      ? `Rekap pertemuan ${result.pertemuan.find((item) => String(item.id_pertemuan) === meetingId)?.pertemuan_ke ?? ''} berhasil dimuat.`
      : 'Belum ada data presensi pada pertemuan ini.';
  } catch (error) {
    document.querySelector('#admin-rekap-state').textContent = error.message;
  }
});
document.querySelector('#admin-rekap-export')?.addEventListener('click', () => {
  const meetingId = document.querySelector('#admin-rekap-pertemuan').value;
  if (meetingId) window.location.assign(`../api/admin.php?bagian=rekap&export=csv&id_pertemuan=${encodeURIComponent(meetingId)}`);
});

async function initializeAdminView() {
  const name = window.location.hash.slice(1) || 'dashboard';
  window.clearInterval(adminChatPollingTimer);
  adminChatPollingTimer = null;
  try {
    if (name === 'dashboard') await loadAdminDashboard();
    if (name === 'data-mahasiswa') await loadMahasiswa();
    if (name === 'data-dosen') await loadDosen();
    if (['mata-kuliah', 'tahun-akademik'].includes(name)) await loadAcademic();
    if (name === 'informasi') await loadPengumuman();
    if (name === 'pengaturan') await loadPengaturan();
    if (name === 'rekap-absensi') await loadAdminRekap();
    if (name === 'pesan-bantuan') {
      await loadAdminConversations();
      if (activeAdminChatTarget) await loadAdminChatThread(activeAdminChatTarget);
      adminChatPollingTimer = window.setInterval(async () => {
        try {
          await loadAdminConversations();
          if (activeAdminChatTarget) await loadAdminChatThread(activeAdminChatTarget);
        } catch (error) {
          document.querySelector('#admin-chat-list-state').textContent = error.message;
        }
      }, 10000);
    }
  } catch (error) {
    document.querySelectorAll('.dashboard-view:not([hidden]) .ak-state').forEach((element) => element.textContent = error.message);
  }
}

window.addEventListener('hashchange', initializeAdminView);
initializeAdminView();
