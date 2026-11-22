async function loadStudentData(bagian) {
  return requestJson(`../api/mahasiswa.php?bagian=${encodeURIComponent(bagian)}`);
}

function donutMarkup(percent, size, label) {
  const value = Math.max(0, Math.min(100, Number(percent) || 0));
  const color = value >= 85 ? 'green' : value >= 75 ? 'yellow' : 'red';
  const circumference = 251.2;
  const offset = circumference * (1 - value / 100);
  return `<svg class="ak-donut ak-donut-${color}" viewBox="0 0 100 100" width="${size}" height="${size}" role="img" aria-label="${escapeHtml(label)} ${value.toFixed(1)} persen">
    <defs><linearGradient id="donut-gradient-${size}-${color}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="var(--ak-donut-start)"/><stop offset="100%" stop-color="var(--ak-donut-end)"/>
    </linearGradient></defs>
    <circle class="ak-donut-track" cx="50" cy="50" r="40"/>
    <circle class="ak-donut-progress" cx="50" cy="50" r="40" stroke="url(#donut-gradient-${size}-${color})" stroke-dasharray="${circumference}" style="--ak-donut-offset:${offset}"/>
    <text class="ak-donut-value" x="50" y="53">${value.toFixed(1)}%</text>
  </svg>`;
}

async function loadStudentStats(yearId = '') {
  const yearSelect = document.querySelector('#attendance-year');
  const state = document.querySelector('#attendance-state');
  const container = document.querySelector('#attendance-courses');
  try {
    const query = yearId
      ? `?bagian=statistik&id_tahun_akademik=${encodeURIComponent(yearId)}`
      : '?bagian=statistik';
    const result = await requestJson(`../api/mahasiswa.php${query}`);
    yearSelect.innerHTML = result.tahun_akademik.map((year) =>
      `<option value="${year.id_tahun_akademik}">${escapeHtml(year.tahun)} ${escapeHtml(year.semester)}</option>`
    ).join('');
    yearSelect.value = String(result.id_tahun_akademik);
    const percent = Number(result.statistik.persen) || 0;
    document.querySelector('#ringkasan-kehadiran').innerHTML = donutMarkup(percent, 150, 'Kehadiran keseluruhan');
    state.textContent = result.statistik.total_pertemuan
      ? `${result.statistik.hadir + result.statistik.terlambat} dari ${result.statistik.total_pertemuan} pertemuan ditutup`
      : 'Belum ada pertemuan yang ditutup.';
    container.innerHTML = result.mata_kuliah.length ? result.mata_kuliah.map((course) => `
      <article class="ak-attendance-course">
        ${donutMarkup(course.persen, 116, `${course.kode} ${course.nama}`)}
        <h3>${escapeHtml(course.nama)}</h3>
        <small>${course.hadir + course.terlambat} dari ${course.total_pertemuan} pertemuan</small>
        ${course.total_pertemuan ? '' : '<small>Belum ada pertemuan</small>'}
      </article>
    `).join('') : '<p class="ak-state">Belum ada mata kuliah untuk semester Anda pada tahun akademik ini.</p>';
  } catch (error) {
    state.textContent = error.message;
    container.replaceChildren();
  }
}

document.querySelector('#attendance-year')?.addEventListener('change', (event) => {
  loadStudentStats(event.target.value);
});

async function loadStudentClasses(yearId = '') {
  const container = document.querySelector('#kelas-saya-list');
  const yearSelect = document.querySelector('#student-academic-year');
  try {
    const query = yearId ? `&id_tahun_akademik=${encodeURIComponent(yearId)}` : '';
    const result = await requestJson(`../api/mahasiswa.php?bagian=kelas${query}`);
    window.studentClasses = result.kelas;
    yearSelect.innerHTML = result.tahun_akademik.map((year) =>
      `<option value="${year.id_tahun_akademik}">${escapeHtml(year.tahun)} ${escapeHtml(year.semester)}</option>`
    ).join('');
    yearSelect.value = String(result.id_tahun_akademik);

    if (!result.kelas.length) {
      container.innerHTML = '<p class="ak-state">Belum ada jadwal kuliah pada tahun akademik ini.</p>';
      return;
    }

    const groups = new Map();
    result.kelas.forEach((item) => {
      if (!groups.has(item.hari)) groups.set(item.hari, []);
      groups.get(item.hari).push(item);
    });
    const dayOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    container.innerHTML = dayOrder.filter((day) => groups.has(day)).map((day) => `
      <section class="ak-schedule-day">
        <h2 class="ak-schedule-day-title">${escapeHtml(day)}</h2>
        <div class="ak-table-wrap"><table class="ak-table ak-schedule-table">
          <thead><tr><th>NO</th><th>KODE</th><th>MATA KULIAH</th><th>SKS</th><th>SMT</th><th>KELAS</th><th>MULAI</th><th>SELESAI</th><th>RUANGAN</th><th>KETIDAKHADIRAN</th><th>TIM PENGAJAR</th></tr></thead>
          <tbody>${groups.get(day).map((item, index) => `
            <tr class="${item.sedang_berlangsung ? 'ak-schedule-active' : ''}">
              <td>${index + 1}</td><td>${escapeHtml(item.kode)}</td><td>${escapeHtml(item.nama_mata_kuliah)}</td>
              <td>${item.sks}</td><td>${item.semester}</td><td>${escapeHtml(item.nama_kelas)}</td>
              <td>${escapeHtml(item.jam_mulai)}</td><td>${escapeHtml(item.jam_selesai)}</td><td>${escapeHtml(item.ruangan)}</td>
              <td><button class="ak-absence-button" type="button" data-absence-id="${item.id_kelas}" aria-label="Lihat ${item.ketidakhadiran} ketidakhadiran">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                <span>${item.ketidakhadiran}</span>
              </button></td>
              <td>${item.tim_pengajar.map((name) => escapeHtml(name)).join('<br>')}</td>
            </tr>`).join('')}</tbody>
        </table></div>
      </section>
    `).join('');
  } catch (error) {
    container.innerHTML = `<p class="ak-state" role="alert">${escapeHtml(error.message)}</p>`;
  }
}

document.querySelector('#kelas-saya-list')?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-absence-id]');
  if (!button) return;
  const item = window.studentClasses?.find((row) => row.id_kelas === Number(button.dataset.absenceId));
  const dialog = document.querySelector('#absence-dialog');
  const list = document.querySelector('#absence-dialog-list');
  list.innerHTML = item && item.pertemuan.length ? item.pertemuan.map((meeting) => `
    <tr><td>${escapeHtml(meeting.waktu_buka)}</td><td>${escapeHtml(meeting.status)}</td><td>${escapeHtml(meeting.metode || '-')}</td></tr>
  `).join('') : '<tr><td colspan="3">Belum ada pertemuan.</td></tr>';
  if (!dialog.open) dialog.showModal();
});

document.querySelector('#absence-dialog-close')?.addEventListener('click', () => {
  document.querySelector('#absence-dialog').close();
});

document.querySelector('#student-academic-year')?.addEventListener('change', (event) => {
  loadStudentClasses(event.target.value);
});

async function loadStudentHistory(yearId = '', classId = '') {
  const table = document.querySelector('#riwayat-list');
  const yearSelect = document.querySelector('#history-year');
  const classSelect = document.querySelector('#history-course');
  const state = document.querySelector('#history-state');
  const summary = document.querySelector('#history-summary');
  try {
    const query = new URLSearchParams({ bagian: 'riwayat' });
    if (yearId) query.set('id_tahun_akademik', yearId);
    if (classId) query.set('id_kelas', classId);
    const result = await requestJson(`../api/mahasiswa.php?${query.toString()}`);
    yearSelect.innerHTML = '<option value="">Pilih semester</option>' + result.tahun_akademik.map((year) =>
      `<option value="${year.id_tahun_akademik}">${escapeHtml(year.tahun)} ${escapeHtml(year.semester)}</option>`
    ).join('');
    yearSelect.value = String(result.id_tahun_akademik);
    classSelect.innerHTML = '<option value="">Pilih mata kuliah</option>' + result.kelas.map((course) =>
      `<option value="${course.id_kelas}">${escapeHtml(course.kode)} - ${escapeHtml(course.nama)} (${escapeHtml(course.nama_kelas)})</option>`
    ).join('');
    classSelect.disabled = result.kelas.length === 0;
    if (classId) classSelect.value = String(classId);
    if (!classId) {
      summary.hidden = true;
      state.textContent = result.kelas.length ? 'Pilih mata kuliah untuk melihat riwayat.' : 'Tidak ada kelas untuk semester ini.';
      table.innerHTML = '<tr><td colspan="5">Pilih semester dan mata kuliah.</td></tr>';
      return;
    }

    const statusStyles = {
      Hadir: 'ak-badge-success', Terlambat: 'ak-badge-warning',
      Izin: 'ak-badge-info', Sakit: 'ak-badge-info', Alpa: 'ak-badge-danger',
    };
    if (result.ringkasan) {
      const entries = [
        ['Hadir', result.ringkasan.hadir], ['Terlambat', result.ringkasan.terlambat],
        ['Izin', result.ringkasan.izin], ['Sakit', result.ringkasan.sakit], ['Alpa', result.ringkasan.alpa],
        ['Persentase', `${result.ringkasan.persen}%`],
      ];
      summary.innerHTML = entries.map(([label, value]) =>
        `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`
      ).join('');
      summary.hidden = false;
    }
    state.textContent = result.riwayat.length ? '' : 'Belum ada pertemuan yang ditutup untuk mata kuliah ini.';
    table.innerHTML = result.riwayat.length ? result.riwayat.map((item) => `
      <tr><td>Pertemuan ${item.pertemuan_ke}</td><td>${escapeHtml(item.tanggal_jam)}</td>
      <td><span class="ak-badge ${statusStyles[item.status] || ''}">${escapeHtml(item.status)}</span></td>
      <td>${escapeHtml(item.metode || '-')}</td><td>${escapeHtml(item.waktu_scan || '-')}</td></tr>
    `).join('') : '<tr><td colspan="5">Belum ada riwayat presensi.</td></tr>';
  } catch (error) {
    state.textContent = error.message;
    table.innerHTML = '<tr><td colspan="5">Riwayat tidak dapat dimuat.</td></tr>';
  }
}

document.querySelector('#history-year')?.addEventListener('change', (event) => {
  loadStudentHistory(event.target.value);
});
document.querySelector('#history-course')?.addEventListener('change', (event) => {
  loadStudentHistory(document.querySelector('#history-year').value, event.target.value);
});

async function loadStudentFace() {
  const state = document.querySelector('#data-wajah-state');
  const container = document.querySelector('#data-wajah-list');
  try {
    const result = await loadStudentData('wajah');
    state.textContent = `${result.jumlah_pose} dari 3 pose wajah telah direkam.`;
    const poseNames = { depan: 'Menghadap depan', kiri: 'Menoleh ke kiri', kanan: 'Menoleh ke kanan' };
    container.innerHTML = result.poses.length ? result.poses.map((pose) => `
      <article class="ak-face-pose">
        <div><h3>${escapeHtml(poseNames[pose.pose] || pose.pose)}</h3><span class="ak-badge ak-badge-success">${escapeHtml(pose.status)}</span></div>
        <p>Direkam ${escapeHtml(pose.dibuat_pada)}</p>
        <div class="ak-descriptor-bars" role="img" aria-label="Visualisasi descriptor ${escapeHtml(poseNames[pose.pose] || pose.pose)}">
          ${pose.visualisasi.map((height) => `<span style="--ak-bar-height:${Math.max(3, Math.round(height * 100))}%"></span>`).join('')}
        </div>
      </article>
    `).join('') : '<p class="ak-state">Belum ada descriptor wajah yang direkam.</p>';
  } catch (error) {
    state.textContent = error.message;
    container.replaceChildren();
  }
}

async function loadStudentDashboard() {
  try {
    const result = await loadStudentData('ringkasan');
    document.querySelector('#ringkasan-kelas').textContent = result.ringkasan.jumlah_kelas;
    document.querySelector('#ringkasan-wajah').textContent =
      `${result.ringkasan.jumlah_wajah}/3 pose`;
    document.querySelector('#biodata-ringkas').innerHTML =
      `<p><b>NIM:</b> ${escapeHtml(result.profil.nim)}</p>
       <p><b>Nama:</b> ${escapeHtml(result.profil.nama_lengkap)}</p>
       <p><b>Program studi:</b> ${escapeHtml(result.profil.prodi)}</p>
       <p><b>Semester:</b> ${result.profil.semester}</p>`;
    const biodata = document.querySelector('#biodata-mahasiswa');
    if (biodata) {
      const session = await requestJson('../api/session.php');
      const avatarEl = document.querySelector('#biodata-avatar');
      if (result.profil.foto_profil) {
        avatarEl.innerHTML = `<img src="../${escapeHtml(result.profil.foto_profil)}" alt="Foto profil ${escapeHtml(result.profil.nama_lengkap)}" />`;
      } else {
        avatarEl.textContent = result.profil.nama_lengkap.slice(0, 1).toLocaleUpperCase('id-ID');
      }
      biodata.innerHTML = [
        ['NIM', result.profil.nim], ['Nama lengkap', result.profil.nama_lengkap],
        ['Program studi', result.profil.prodi], ['Semester', result.profil.semester],
        ['Provinsi asal', result.profil.provinsi || '-'],
        ['Kota / Kabupaten asal', result.profil.kota || '-'],
        ['Kecamatan asal', result.profil.kecamatan || '-'],
        ['Kelurahan / Desa asal', result.profil.kelurahan || '-'],
        ['Username', session.username],
      ].map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('');
    }
  } catch (error) {
    const status = document.querySelector('#profile-status');
    if (status) status.textContent = error.message;
  }
}

const studentView = document.querySelector('[data-view]');
if (studentView) {
  loadStudentDashboard();
  loadStudentStats();
  window.addEventListener('hashchange', () => {
    const name = window.location.hash.slice(1);
    if (name === 'kelas-saya') loadStudentClasses();
    if (name === 'riwayat') loadStudentHistory();
    if (name === 'data-wajah') loadStudentFace();
  });
  const current = window.location.hash.slice(1);
  if (current === 'kelas-saya') loadStudentClasses();
  if (current === 'riwayat') loadStudentHistory();
  if (current === 'data-wajah') loadStudentFace();
}

let qrLoaded = false;
const qrView = document.querySelector('[data-view="qr-saya"]');
if (qrView) {
  async function loadStudentQr() {
    if (qrLoaded) return;
    qrLoaded = true;
    const qrContainer = document.querySelector('#qr-code');
    const state = document.querySelector('#qr-state');
    const downloadButton = document.querySelector('#qr-download');
    try {
      const result = await requestJson('../api/qr_mahasiswa.php');
      document.querySelector('#qr-nama').textContent = result.mahasiswa.nama_lengkap;
      document.querySelector('#qr-nim').textContent =
        `NIM ${result.mahasiswa.nim} · ${result.mahasiswa.prodi} · Semester ${result.mahasiswa.semester}`;
      qrContainer.replaceChildren();
      new QRCode(qrContainer, {
        text: result.qr_content,
        width: 260,
        height: 260,
        colorDark: '#12284a',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H,
      });
      state.textContent = 'QR siap digunakan.';
      downloadButton.disabled = false;
      downloadButton.onclick = () => {
        const canvas = qrContainer.querySelector('canvas');
        const image = qrContainer.querySelector('img');
        const source = canvas || image;
        if (!source) return;
        const link = document.createElement('a');
        link.download = `QR-${result.mahasiswa.nim}.png`;
        link.href = canvas ? canvas.toDataURL('image/png') : source.src;
        link.click();
      };
    } catch (error) {
      qrLoaded = false;
      state.textContent = error.message;
    }
  }
  const observer = new MutationObserver(() => {
    if (!qrView.hidden) loadStudentQr();
  });
  observer.observe(qrView, { attributes: true, attributeFilter: ['hidden'] });
  if (!qrView.hidden) loadStudentQr();
}

document.querySelector('#faq-search')?.addEventListener('input', (event) => {
  const query = event.target.value.trim().toLocaleLowerCase('id-ID');
  document.querySelectorAll('#faq-list .ak-faq').forEach((item) => {
    item.hidden = !item.textContent.toLocaleLowerCase('id-ID').includes(query);
  });
});

const studentChatPanel = document.querySelector('#student-chat-panel');
if (studentChatPanel) {
  let studentChatTimer = null;
  let studentChatLastId = 0;
  let studentChatLoaded = false;
  const chatToggle = document.querySelector('#student-chat-toggle');
  const chatState = document.querySelector('#student-chat-state');

  async function loadStudentChat() {
    try {
      const query = new URLSearchParams({ bagian: 'percakapan' });
      if (studentChatLoaded) query.set('since_id', String(studentChatLastId));
      const result = await requestJson(`../api/chat.php?${query.toString()}`);
      const messages = document.querySelector('#student-chat-messages');
      if (!studentChatLoaded && !result.pesan.length) {
        messages.innerHTML = '<p class="ak-chat-empty">Belum ada pesan. Silakan kirim pertanyaan kepada admin.</p>';
      } else if (result.pesan.length) {
        if (!studentChatLoaded) messages.replaceChildren();
        messages.insertAdjacentHTML('beforeend', result.pesan.map((message) => {
          const attachment = message.lampiran_path
            ? ((message.lampiran_tipe || '').startsWith('image/')
              ? `<a class="ak-chat-attachment-image" href="../${escapeHtml(message.lampiran_path)}" target="_blank" rel="noopener"><img src="../${escapeHtml(message.lampiran_path)}" alt="${escapeHtml(message.lampiran_nama || 'Lampiran')}" /></a>`
              : `<a class="ak-chat-attachment-file" href="../${escapeHtml(message.lampiran_path)}" target="_blank" rel="noopener" download="${escapeHtml(message.lampiran_nama || 'lampiran')}">📎 ${escapeHtml(message.lampiran_nama || 'Unduh lampiran')}</a>`)
            : '';
          return `
        <article class="ak-chat-message ${message.pengirim === 'mahasiswa' ? 'is-mine' : 'is-admin'}">
          ${message.isi ? `<p>${escapeHtml(message.isi)}</p>` : ''}${attachment}<time>${escapeHtml(message.dibuat_pada)}</time>
        </article>`;
        }).join(''));
        studentChatLastId = result.pesan[result.pesan.length - 1].id_pesan;
        messages.scrollTop = messages.scrollHeight;
      }
      studentChatLoaded = true;
      chatState.textContent = '';
    } catch (error) {
      chatState.textContent = error.message;
    }
  }

  async function loadStudentChatBadge() {
    try {
      const result = await requestJson('../api/chat.php?bagian=badge');
      const badge = document.querySelector('#student-chat-badge');
      badge.textContent = result.belum_dibaca > 99 ? '99+' : String(result.belum_dibaca);
      badge.hidden = result.belum_dibaca < 1;
    } catch (error) {
      if (!studentChatPanel.hidden) chatState.textContent = error.message;
    }
  }

  function closeStudentChat() {
    studentChatPanel.hidden = true;
    chatToggle.setAttribute('aria-expanded', 'false');
    window.clearInterval(studentChatTimer);
    studentChatTimer = null;
  }

  chatToggle.addEventListener('click', () => {
    const opening = studentChatPanel.hidden;
    studentChatPanel.hidden = !opening;
    chatToggle.setAttribute('aria-expanded', String(opening));
    if (opening) {
      loadStudentChat();
      window.clearInterval(studentChatTimer);
      studentChatTimer = window.setInterval(loadStudentChat, 5000);
      document.querySelector('#student-chat-input').focus();
    } else {
      closeStudentChat();
    }
  });
  document.querySelector('#student-chat-close').addEventListener('click', closeStudentChat);
  const chatFileInput = document.querySelector('#student-chat-file');
  const chatFilePreview = document.querySelector('#student-chat-file-preview');
  const chatFileName = document.querySelector('#student-chat-file-name');
  function clearStudentChatFile() {
    if (chatFileInput) chatFileInput.value = '';
    if (chatFilePreview) chatFilePreview.hidden = true;
    if (chatFileName) chatFileName.textContent = '';
  }
  chatFileInput?.addEventListener('change', () => {
    const file = chatFileInput.files?.[0];
    if (file) {
      chatFileName.textContent = file.name;
      chatFilePreview.hidden = false;
    } else {
      clearStudentChatFile();
    }
  });
  document.querySelector('#student-chat-file-clear')?.addEventListener('click', clearStudentChatFile);
  document.querySelector('#student-chat-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = document.querySelector('#student-chat-input');
    const button = event.currentTarget.querySelector('button[type="submit"]');
    const file = chatFileInput?.files?.[0] || null;
    if (!input.value.trim() && !file) {
      chatState.textContent = 'Tulis pesan atau pilih file lampiran terlebih dahulu.';
      return;
    }
    button.disabled = true;
    try {
      const formData = new FormData();
      formData.append('aksi', 'kirim');
      formData.append('isi', input.value);
      if (file) formData.append('lampiran', file);
      const result = await requestJson('../api/chat.php', {
        method: 'POST',
        body: formData,
      });
      input.value = '';
      clearStudentChatFile();
      chatState.textContent = result.message;
      await loadStudentChat();
    } catch (error) {
      chatState.textContent = error.message;
    } finally {
      button.disabled = false;
    }
  });
  loadStudentChatBadge();
  window.setInterval(loadStudentChatBadge, 15000);
}

const photoInput = document.querySelector('#biodata-photo-input');
const photoForm = document.querySelector('#biodata-photo-form');
const photoSubmit = document.querySelector('#biodata-photo-submit');
const photoState = document.querySelector('#biodata-photo-state');
if (photoInput && photoForm) {
  photoInput.addEventListener('change', () => {
    const file = photoInput.files?.[0];
    if (!file) {
      photoSubmit.hidden = true;
      return;
    }
    photoSubmit.hidden = false;
    photoState.textContent = `File terpilih: ${file.name}`;
  });
  photoForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const file = photoInput.files?.[0];
    if (!file) {
      photoState.textContent = 'Pilih file foto terlebih dahulu.';
      return;
    }
    photoSubmit.disabled = true;
    photoState.textContent = 'Mengunggah foto profil...';
    try {
      const formData = new FormData();
      formData.append('foto_profil', file);
      const result = await requestJson('../api/mahasiswa.php', {
        method: 'POST',
        body: formData,
      });
      photoInput.value = '';
      photoSubmit.hidden = true;
      photoState.textContent = result.message;
      await loadStudentDashboard();
    } catch (error) {
      photoState.textContent = error.message;
    } finally {
      photoSubmit.disabled = false;
    }
  });
}
