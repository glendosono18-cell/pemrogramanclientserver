async function loadLecturerSchedule(yearId = '') {
  const container = document.querySelector('#jadwal-dosen-list');
  const yearSelect = document.querySelector('#lecturer-academic-year');
  const weekNav = document.querySelector('#lecturer-week-days');
  if (!container || !yearSelect) return;
  try {
    const query = yearId ? `?id_tahun_akademik=${encodeURIComponent(yearId)}` : '';
    const result = await requestJson(`../api/dosen.php${query}`);
    yearSelect.innerHTML = result.tahun_akademik.map((year) =>
      `<option value="${year.id_tahun_akademik}">${escapeHtml(year.tahun)} ${escapeHtml(year.semester)}</option>`
    ).join('');
    yearSelect.value = String(result.id_tahun_akademik);

    if (!result.jadwal.length) {
      container.innerHTML = '<p class="ak-state">Belum ada jadwal mengajar pada tahun akademik ini.</p>';
    }

    const groups = new Map();
    result.jadwal.forEach((item) => {
      if (!groups.has(item.hari)) groups.set(item.hari, []);
      groups.get(item.hari).push(item);
    });
    const week = result.pekan || [];
    if (weekNav) {
      weekNav.innerHTML = week.map((day) => `
        <button type="button" class="ak-week-tab ${day.hari_ini ? 'is-today' : ''}"
          data-week-date="${escapeHtml(day.tanggal)}" aria-pressed="${day.hari_ini ? 'true' : 'false'}">
          <span>${escapeHtml(day.hari)}</span><small>${escapeHtml(day.label_tanggal)}</small>
          ${day.hari_ini ? '<b>Hari ini</b>' : ''}
        </button>
      `).join('');
    }
    container.innerHTML = week.map((day) => {
      const entries = groups.get(day.hari) || [];
      return `
        <section id="lecturer-day-${escapeHtml(day.tanggal)}" class="ak-schedule-day ${day.hari_ini ? 'is-today' : ''}">
          <h2 class="ak-schedule-day-title">${escapeHtml(day.hari)} <time>${escapeHtml(day.label_tanggal)}</time>
            ${day.hari_ini ? '<span>Hari ini</span>' : ''}
          </h2>
          ${entries.length ? `
            <div class="ak-table-wrap"><table class="ak-table ak-schedule-table">
              <thead><tr><th>NO</th><th>KODE</th><th>MATA KULIAH</th><th>SKS</th><th>SMT</th><th>KELAS</th><th>MULAI</th><th>SELESAI</th><th>RUANGAN</th><th>PESERTA</th><th>TIM PENGAJAR</th></tr></thead>
              <tbody>${entries.map((item, index) => `
                <tr class="${item.sedang_berlangsung ? 'ak-schedule-active' : ''}">
                  <td>${index + 1}</td><td>${escapeHtml(item.kode)}</td><td>${escapeHtml(item.nama_mata_kuliah)}</td>
                  <td>${item.sks}</td><td>${item.semester}</td><td>${escapeHtml(item.nama_kelas)}</td>
                  <td>${escapeHtml(item.jam_mulai)}</td><td>${escapeHtml(item.jam_selesai)}</td><td>${escapeHtml(item.ruangan)}</td>
                  <td>${item.jumlah_peserta}</td><td>${item.tim_pengajar.map((name) => escapeHtml(name)).join('<br>')}</td>
                </tr>`).join('')}</tbody>
            </table></div>
          ` : '<p class="ak-state">Tidak ada jadwal mengajar pada hari ini.</p>'}
        </section>
      `;
    }).join('');
  } catch (error) {
    container.innerHTML = `<p class="ak-state" role="alert">${escapeHtml(error.message)}</p>`;
  }
}

window.addEventListener('hashchange', () => {
  if (window.location.hash === '#jadwal-mengajar') loadLecturerSchedule();
});
if (window.location.hash === '#jadwal-mengajar') loadLecturerSchedule();
document.querySelector('#lecturer-academic-year')?.addEventListener('change', (event) => {
  loadLecturerSchedule(event.target.value);
});

document.querySelector('#lecturer-week-days')?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-week-date]');
  if (!button) return;
  document.querySelectorAll('#lecturer-week-days [data-week-date]').forEach((tab) => {
    tab.setAttribute('aria-pressed', String(tab === button));
  });
  document.querySelector(`#lecturer-day-${button.dataset.weekDate}`)?.scrollIntoView({
    behavior: 'smooth',
    block: 'start',
  });
});

async function loadRecentLecturerSessions() {
  const state = document.querySelector('#lecturer-recent-state');
  const list = document.querySelector('#lecturer-recent-list');
  if (!state || !list) return;
  try {
    const result = await requestJson('../api/absensi_dosen.php?bagian=sesi_terbaru');
    const sessions = result.sesi || [];
    const openSessions = sessions.filter((session) => session.status === 'Dibuka').length;
    const cards = document.querySelectorAll('[data-view="dashboard"] .card');
    if (cards[1]) cards[1].querySelector('strong').textContent = String(openSessions);
    if (!sessions.length) {
      state.textContent = 'Tidak ada sesi aktif atau sesi dalam 4 hari terakhir.';
      list.innerHTML = '<tr><td colspan="7">Belum ada sesi absensi.</td></tr>';
      return;
    }
    state.textContent = `${sessions.length} sesi dapat dibuka atau diedit.`;
    list.innerHTML = sessions.map((session) => `
      <tr>
        <td>${escapeHtml(session.kode)} · ${escapeHtml(session.nama_mata_kuliah)}</td>
        <td>${escapeHtml(session.nama_kelas)}</td>
        <td>${session.pertemuan_ke}</td>
        <td>${escapeHtml(session.tanggal_jam)}</td>
        <td><span class="ak-badge ${session.status === 'Dibuka' ? 'ak-badge-warning' : 'ak-badge-success'}">${escapeHtml(session.status)}</span></td>
        <td>${session.jumlah_hadir}/${session.total_peserta}</td>
        <td><button class="ak-secondary-button" type="button" data-lecturer-session="${session.id_pertemuan}"
          data-action="${session.status === 'Dibuka' ? 'absensi' : 'rekap'}"
          data-class="${session.id_kelas}" data-year="${session.id_tahun_akademik}"
          data-course="${session.id_mata_kuliah}" data-date="${escapeHtml(session.tanggal_pertemuan)}"
          data-status="${escapeHtml(session.status)}">Buka/Edit</button></td>
      </tr>
    `).join('');
  } catch (error) {
    state.textContent = error.message || 'Sesi absensi tidak dapat dimuat.';
    list.innerHTML = '<tr><td colspan="7">Sesi absensi tidak dapat dimuat.</td></tr>';
  }
}

function openLecturerSession(target) {
  if (target.action === 'absensi') {
    sessionStorage.setItem('dosenAttendanceTarget', JSON.stringify(target));
    if (window.location.hash === '#absensi') loadAttendanceOptions();
    else window.location.hash = '#absensi';
    return;
  }
  sessionStorage.setItem('dosenRekapTarget', JSON.stringify(target));
  if (window.location.hash === '#rekap') loadRekapClasses();
  else window.location.hash = '#rekap';
}

document.querySelector('#lecturer-recent-list')?.addEventListener('click', (event) => {
  const button = event.target.closest('[data-lecturer-session]');
  if (!button) return;
  openLecturerSession({
    action: button.dataset.action,
    id_pertemuan: Number(button.dataset.lecturerSession),
    id_kelas: Number(button.dataset.class),
    id_tahun_akademik: Number(button.dataset.year),
    id_mata_kuliah: Number(button.dataset.course),
    tanggal_pertemuan: button.dataset.date,
    status: button.dataset.status,
  });
});

loadRecentLecturerSessions();

let activeMeetingId = null;
let activeStudentId = null;
let activeQrToken = null;
let qrScanner = null;
let faceStream = null;
let pollingTimer = null;
let attendanceClasses = [];
let attendanceDates = [];
let selectedMeetingDate = '';

function populateAttendanceClasses(courseId = '', classId = '') {
  const courseSelect = document.querySelector('#dosen-attendance-course');
  const classSelect = document.querySelector('#dosen-attendance-class');
  const dateSelect = document.querySelector('#dosen-attendance-date');
  const activationButton = document.querySelector('#activate-attendance');
  activeMeetingId = null;
  selectedMeetingDate = '';
  setAttendancePanelHidden(true);
  const courseMap = new Map();
  attendanceClasses.forEach((item) => {
    if (!courseMap.has(item.id_mata_kuliah)) courseMap.set(item.id_mata_kuliah, item);
  });
  courseSelect.innerHTML = '<option value="">Pilih mata kuliah</option>' + Array.from(courseMap.values()).map((item) =>
    `<option value="${item.id_mata_kuliah}">${escapeHtml(item.kode)} - ${escapeHtml(item.nama_mata_kuliah)}</option>`
  ).join('');
  courseSelect.disabled = courseMap.size === 0;
  courseSelect.value = courseId ? String(courseId) : '';
  classSelect.innerHTML = '<option value="">Pilih kelas</option>';
  classSelect.disabled = true;
  dateSelect.innerHTML = '<option value="">Pilih jadwal/tanggal</option>';
  dateSelect.disabled = true;
  activationButton.disabled = true;

  if (courseSelect.value) {
    const classes = attendanceClasses.filter((item) => item.id_mata_kuliah === Number(courseSelect.value));
    classSelect.innerHTML = '<option value="">Pilih kelas</option>' + classes.map((item) =>
      `<option value="${item.id_kelas}">${escapeHtml(item.nama_kelas)} · ${escapeHtml(item.hari)} ${escapeHtml(item.jam_mulai)}</option>`
    ).join('');
    classSelect.disabled = classes.length === 0;
    if (classId && classes.some((item) => item.id_kelas === Number(classId))) {
      classSelect.value = String(classId);
    }
  }
}

async function loadAttendanceDates(preselectDate = '') {
  const classSelect = document.querySelector('#dosen-attendance-class');
  const dateSelect = document.querySelector('#dosen-attendance-date');
  const state = document.querySelector('#dosen-schedule-state');
  const activationButton = document.querySelector('#activate-attendance');
  const selectedClassId = Number(classSelect.value);
  attendanceDates = [];
  selectedMeetingDate = '';
  activeMeetingId = null;
  activationButton.disabled = true;
  dateSelect.innerHTML = '<option value="">Pilih jadwal/tanggal</option>';
  dateSelect.disabled = !selectedClassId;
  await setAttendancePanelHidden(true);
  if (!selectedClassId) return;

  try {
    const result = await requestJson(`../api/absensi_dosen.php?bagian=tanggal&id_kelas=${encodeURIComponent(selectedClassId)}`);
    attendanceDates = result.tanggal_pertemuan || [];
    dateSelect.innerHTML = '<option value="">Pilih jadwal/tanggal</option>' + attendanceDates.map((item) => {
      const meeting = item.pertemuan;
      const suffix = meeting
        ? ` · Pertemuan ${meeting.pertemuan_ke} (${meeting.status})`
        : ' · Belum dibuka';
      return `<option value="${escapeHtml(item.tanggal)}">${escapeHtml(item.label + suffix)}</option>`;
    }).join('');
    dateSelect.disabled = attendanceDates.length === 0;
    if (!attendanceDates.length) {
      state.textContent = 'Tidak ada tanggal yang sesuai dengan jadwal kelas dalam 4 hari terakhir.';
      return;
    }
    state.textContent = 'Pilih jadwal/tanggal pertemuan untuk melanjutkan.';
    if (preselectDate && attendanceDates.some((item) => item.tanggal === preselectDate)) {
      dateSelect.value = preselectDate;
      await selectAttendanceDate();
    }
  } catch (error) {
    state.textContent = error.message || 'Jadwal dosen tidak dapat dimuat.';
  }
}

async function loadAttendanceOptions(requestedYearId = '', target = null) {
  const yearSelect = document.querySelector('#dosen-attendance-year');
  const courseSelect = document.querySelector('#dosen-attendance-course');
  const classSelect = document.querySelector('#dosen-attendance-class');
  const state = document.querySelector('#dosen-schedule-state');
  const storedTarget = target || (() => {
    const raw = sessionStorage.getItem('dosenAttendanceTarget');
    sessionStorage.removeItem('dosenAttendanceTarget');
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (error) { return null; }
  })();
  const yearId = storedTarget?.id_tahun_akademik || requestedYearId || yearSelect.value;
  state.textContent = 'Memuat pilihan jadwal...';
  try {
    const query = yearId ? `?id_tahun_akademik=${encodeURIComponent(yearId)}` : '';
    const result = await requestJson(`../api/dosen.php${query}`);
    yearSelect.innerHTML = result.tahun_akademik.map((year) =>
      `<option value="${year.id_tahun_akademik}">${escapeHtml(year.tahun)} ${escapeHtml(year.semester)}</option>`
    ).join('');
    yearSelect.value = String(result.id_tahun_akademik);
    attendanceClasses = result.jadwal || [];
    populateAttendanceClasses(storedTarget?.id_mata_kuliah, storedTarget?.id_kelas);
    if (storedTarget?.id_kelas) {
      await loadAttendanceDates(storedTarget.tanggal_pertemuan || '');
      return;
    }
    if (!attendanceClasses.length) {
      state.textContent = 'Tidak ada kelas mengajar pada semester ini.';
      return;
    }
    state.textContent = 'Pilih mata kuliah, kelas, dan tanggal pertemuan.';
  } catch (error) {
    courseSelect.innerHTML = '<option value="">Pilih mata kuliah</option>';
    classSelect.innerHTML = '<option value="">Pilih kelas</option>';
    courseSelect.disabled = true;
    classSelect.disabled = true;
    state.textContent = error.message || 'Pilihan jadwal tidak dapat dimuat.';
  }
}

async function selectAttendanceDate() {
  const dateSelect = document.querySelector('#dosen-attendance-date');
  const activationButton = document.querySelector('#activate-attendance');
  const state = document.querySelector('#dosen-schedule-state');
  selectedMeetingDate = dateSelect.value;
  const selected = attendanceDates.find((item) => item.tanggal === selectedMeetingDate);
  activeMeetingId = selected?.pertemuan?.id_pertemuan || null;
  await setAttendancePanelHidden(true);
  if (!selected) {
    activationButton.disabled = true;
    return;
  }
  if (!selected.pertemuan) {
    activationButton.disabled = false;
    activationButton.textContent = 'Aktifkan Absensi';
    state.textContent = `${selected.label}: belum ada sesi absensi.`;
    return;
  }

  activationButton.disabled = true;
  activationButton.textContent = selected.pertemuan.status === 'Dibuka'
    ? 'Sesi sedang dibuka'
    : 'Pertemuan sudah ditutup';
  state.textContent = `${selected.label}: pertemuan ${selected.pertemuan.pertemuan_ke}, status ${selected.pertemuan.status}.`;
  document.querySelector('#attendance-panel').hidden = false;
  await loadAttendance();
  if (selected.pertemuan.status === 'Dibuka') {
    document.querySelector('#scan-panel').hidden = false;
    await startQrScanner();
    startPolling();
  }
}

async function activateAttendance() {
  const idKelas = Number(document.querySelector('#dosen-attendance-class').value);
  const button = document.querySelector('#activate-attendance');
  if (!idKelas || !selectedMeetingDate) {
    document.querySelector('#dosen-schedule-state').textContent =
      'Pilih kelas dan tanggal pertemuan terlebih dahulu.';
    return;
  }
  const mode = window.prompt('Mode verifikasi (ketik QR atau QR + Wajah)', 'QR + Wajah');
  if (!mode || !['QR', 'QR + Wajah'].includes(mode)) return;
  const limitText = window.prompt('Batas tepat waktu dalam menit', '15');
  const limit = Number(limitText);
  if (!Number.isInteger(limit) || limit < 1 || limit > 180) return;

  button.disabled = true;
  try {
    const result = await requestJson('../api/absensi_dosen.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({
        aksi: 'aktifkan',
        id_kelas: idKelas,
        tanggal_pertemuan: selectedMeetingDate,
        mode_verifikasi: mode,
        batas_tepat_menit: limit,
      }),
    });
    button.textContent = 'Sesi sedang dibuka';
    document.querySelector('#dosen-schedule-state').textContent =
      `Sesi pertemuan ${result.pertemuan_ke} pada ${result.tanggal_pertemuan} dibuka.`;
    await loadAttendanceDates(selectedMeetingDate);
    await loadRecentLecturerSessions();
  } catch (error) {
    document.querySelector('#dosen-schedule-state').textContent = error.message;
    button.disabled = false;
  }
}

document.querySelector('#dosen-attendance-year')?.addEventListener('change', (event) => {
  loadAttendanceOptions(event.target.value);
});
document.querySelector('#dosen-attendance-course')?.addEventListener('change', (event) => {
  populateAttendanceClasses(event.target.value);
  document.querySelector('#dosen-schedule-state').textContent = 'Pilih kelas untuk menampilkan tanggal pertemuan.';
});
document.querySelector('#dosen-attendance-class')?.addEventListener('change', () => loadAttendanceDates());
document.querySelector('#dosen-attendance-date')?.addEventListener('change', selectAttendanceDate);
document.querySelector('#activate-attendance')?.addEventListener('click', activateAttendance);

async function setAttendancePanelHidden(hidden) {
  document.querySelector('#scan-panel').hidden = hidden;
  document.querySelector('#attendance-panel').hidden = hidden;
  if (hidden) {
    clearInterval(pollingTimer);
    pollingTimer = null;
    if (qrScanner) {
      const scanner = qrScanner;
      qrScanner = null;
      try { await scanner.stop(); } catch (error) {}
    }
    if (faceStream) {
      faceStream.getTracks().forEach((track) => track.stop());
      faceStream = null;
    }
    document.querySelector('#qr-reader').hidden = false;
    document.querySelector('#face-verification').hidden = true;
    document.querySelector('#scan-face-button').hidden = true;
  }
}

async function startQrScanner() {
  if (!window.Html5Qrcode) return;
  qrScanner = new Html5Qrcode('qr-reader');
  try {
    await qrScanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 240, height: 240 } },
      onQrDecoded,
      () => {}
    );
  } catch (error) {
    document.querySelector('#scan-state').textContent = 'Kamera QR tidak dapat dinyalakan.';
  }
}

async function onQrDecoded(decodedText) {
  if (!decodedText.startsWith('ABSENSI:')) {
    document.querySelector('#scan-state').textContent = 'Format QR tidak dikenali.';
    return;
  }
  if (activeMeetingId === null) return;
  activeQrToken = decodedText.slice('ABSENSI:'.length);

  try {
    const result = await requestJson('../api/scan_absensi.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({ id_pertemuan: activeMeetingId, qr_token: activeQrToken }),
    });
    if (result.memerlukan_wajah) {
      activeStudentId = result.mahasiswa.id_mahasiswa;
      await startFaceVerification(result.mahasiswa);
      return;
    }
    document.querySelector('#scan-state').textContent =
      `${result.mahasiswa.nama_lengkap} (${result.mahasiswa.nim}) — ${result.status_kehadiran}`;
  } catch (error) {
    document.querySelector('#scan-state').textContent = error.message;
  }
}

async function startFaceVerification(student) {
  document.querySelector('#scan-state').textContent =
    `Verifikasi wajah ${student.nama_lengkap} (${student.nim}).`;
  document.querySelector('#qr-reader').hidden = true;
  const facePanel = document.querySelector('#face-verification');
  facePanel.hidden = false;
  document.querySelector('#scan-face-button').hidden = false;

  if (qrScanner) {
    try { await qrScanner.stop(); } catch (error) {}
  }

  try {
    await faceapi.nets.tinyFaceDetector.loadFromUri('../assets/models');
    await faceapi.nets.faceLandmark68Net.loadFromUri('../assets/models');
    await faceapi.nets.faceRecognitionNet.loadFromUri('../assets/models');
    faceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
    const video = document.querySelector('#face-video');
    video.srcObject = faceStream;
    await video.play();
  } catch (error) {
    document.querySelector('#scan-state').textContent = 'Kamera wajah atau model tidak dapat dimuat.';
  }
}

document.querySelector('#scan-face-button')?.addEventListener('click', async () => {
  const video = document.querySelector('#face-video');
  const button = document.querySelector('#scan-face-button');
  button.disabled = true;
  try {
    const detection = await faceapi
      .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
      .withFaceLandmarks()
      .withFaceDescriptor();
    if (!detection) {
      document.querySelector('#scan-state').textContent = 'Wajah tidak terdeteksi. Coba lagi.';
      button.disabled = false;
      return;
    }

    const result = await requestJson('../api/scan_absensi.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({
        id_pertemuan: activeMeetingId,
        qr_token: activeQrToken,
        descriptor: Array.from(detection.descriptor),
      }),
    });
    document.querySelector('#scan-state').textContent =
      `${result.mahasiswa.nama_lengkap} (${result.mahasiswa.nim}) — ${result.status_kehadiran}`;
  } catch (error) {
    document.querySelector('#scan-state').textContent = error.message;
  } finally {
    button.disabled = false;
  }
});

async function loadAttendance() {
  if (activeMeetingId === null) return;
  try {
    const result = await requestJson(`../api/scan_absensi.php?id_pertemuan=${activeMeetingId}`);
    document.querySelector('#attendance-count').textContent =
      `${result.jumlah_hadir} hadir / ${result.total_peserta} peserta`;
    document.querySelector('#attendance-list').innerHTML = result.peserta
      .filter((student) => ['Hadir', 'Terlambat'].includes(student.status))
      .map((student) => `
        <tr><td>${escapeHtml(student.nim)}</td><td>${escapeHtml(student.nama_lengkap)}</td>
        <td>${escapeHtml(student.status)}</td><td>${escapeHtml(student.waktu_scan || '-')}</td></tr>`)
      .join('');
  } catch (error) {}
}

function startPolling() {
  clearInterval(pollingTimer);
  loadAttendance();
  pollingTimer = setInterval(loadAttendance, 3000);
}

document.querySelector('#close-attendance')?.addEventListener('click', async () => {
  if (activeMeetingId === null) return;
  const closedDate = selectedMeetingDate;
  try {
    const result = await requestJson('../api/absensi_dosen.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({ aksi: 'tutup', id_pertemuan: activeMeetingId }),
    });
    document.querySelector('#scan-state').textContent = result.message;
    await loadAttendanceDates(closedDate);
    await loadRecentLecturerSessions();
  } catch (error) {
    document.querySelector('#scan-state').textContent = error.message;
  }
});

const absensiView = document.querySelector('[data-view="absensi"]');
if (absensiView) {
  const observer = new MutationObserver(() => {
    if (!absensiView.hidden) loadAttendanceOptions();
  });
  observer.observe(absensiView, { attributes: true, attributeFilter: ['hidden'] });
  if (!absensiView.hidden) loadAttendanceOptions();
}

async function loadRekapClasses() {
  const state = document.querySelector('#rekap-state');
  const rawTarget = sessionStorage.getItem('dosenRekapTarget');
  sessionStorage.removeItem('dosenRekapTarget');
  let target = null;
  if (rawTarget) {
    try { target = JSON.parse(rawTarget); } catch (error) { target = null; }
  }
  try {
    const result = await requestJson('../api/rekap_dosen.php');
    const select = document.querySelector('#rekap-kelas');
    select.innerHTML = '<option value="">Pilih kelas</option>' + result.kelas.map((item) =>
      `<option value="${item.id_kelas}">${escapeHtml(item.kode)} - ${escapeHtml(item.nama_mata_kuliah)} (${escapeHtml(item.nama_kelas)})</option>`
    ).join('');
    if (target?.id_kelas && result.kelas.some((item) => item.id_kelas === target.id_kelas)) {
      select.value = String(target.id_kelas);
      await loadRekapMeetings(target.id_pertemuan);
    }
  } catch (error) {
    state.textContent = error.message || 'Daftar kelas tidak dapat dimuat.';
  }
}

async function loadRekapMeetings(preselectMeetingId = '') {
  const classId = document.querySelector('#rekap-kelas').value;
  const meetingSelect = document.querySelector('#rekap-pertemuan');
  const state = document.querySelector('#rekap-state');
  meetingSelect.innerHTML = '<option value="">Pilih pertemuan</option>';
  document.querySelector('#rekap-export').disabled = true;
  document.querySelector('#rekap-list').innerHTML =
    '<tr><td colspan="6" class="ak-state-cell">Pilih pertemuan.</td></tr>';
  if (!classId) return;

  try {
    const result = await requestJson(`../api/rekap_dosen.php?id_kelas=${encodeURIComponent(classId)}`);
    meetingSelect.innerHTML = '<option value="">Pilih pertemuan</option>' + result.pertemuan.map((item) =>
      `<option value="${item.id_pertemuan}">Pertemuan ${item.pertemuan_ke} - ${escapeHtml(item.tanggal_pertemuan)} ${escapeHtml(item.jam_buka)} (${escapeHtml(item.status)})</option>`
    ).join('');
    if (preselectMeetingId
      && result.pertemuan.some((item) => item.id_pertemuan === Number(preselectMeetingId))) {
      meetingSelect.value = String(preselectMeetingId);
      await loadRekapRows();
    } else {
      state.textContent = result.pertemuan.length
        ? 'Pilih pertemuan untuk memuat rekap.'
        : 'Kelas ini belum memiliki pertemuan.';
    }
  } catch (error) {
    state.textContent = error.message || 'Daftar pertemuan tidak dapat dimuat.';
  }
}

async function loadRekapRows() {
  const meetingId = document.querySelector('#rekap-pertemuan').value;
  const state = document.querySelector('#rekap-state');
  const table = document.querySelector('#rekap-list');
  const exportButton = document.querySelector('#rekap-export');
  exportButton.disabled = !meetingId;
  if (!meetingId) return;

  try {
    const result = await requestJson(`../api/rekap_dosen.php?id_pertemuan=${encodeURIComponent(meetingId)}`);
    state.textContent =
      `${result.detail.kode} - ${result.detail.nama_mata_kuliah}, kelas ${result.detail.nama_kelas}, pertemuan ${result.detail.pertemuan_ke} (${escapeHtml(result.detail.tanggal_pertemuan)}).`
      + (result.dapat_dikoreksi ? '' : ' Batas koreksi 4 hari telah lewat; rekap hanya dapat dilihat.');
    table.innerHTML = result.peserta.map((student) => `
      <tr>
        <td>${escapeHtml(student.nim)}</td>
        <td>${escapeHtml(student.nama_lengkap)}</td>
        <td>${escapeHtml(student.status)}</td>
        <td>${escapeHtml(student.metode || '-')}</td>
        <td>${escapeHtml(student.waktu_scan || '-')}</td>
        <td>
          <select class="rekap-status" data-rekap-student="${student.id_mahasiswa}" aria-label="Status ${escapeHtml(student.nama_lengkap)}" ${result.dapat_dikoreksi ? '' : 'disabled'}>
            ${['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa'].map((status) =>
              `<option value="${status}" ${status === student.status ? 'selected' : ''}>${status}</option>`
            ).join('')}
          </select>
        </td>
      </tr>`).join('');
  } catch (error) {
    state.textContent = error.message || 'Rekap presensi tidak dapat dimuat.';
  }
}

document.querySelector('#rekap-kelas')?.addEventListener('change', () => loadRekapMeetings());
document.querySelector('#rekap-pertemuan')?.addEventListener('change', () => loadRekapRows());

document.querySelector('#rekap-list')?.addEventListener('change', async (event) => {
  const select = event.target.closest('[data-rekap-student]');
  if (!select) return;
  const meetingId = Number(document.querySelector('#rekap-pertemuan').value);
  const state = document.querySelector('#rekap-state');
  try {
    const result = await requestJson('../api/rekap_dosen.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify({
        id_pertemuan: meetingId,
        id_mahasiswa: Number(select.dataset.rekapStudent),
        status: select.value,
      }),
    });
    state.textContent = result.message;
    await loadRekapRows();
  } catch (error) {
    state.textContent = error.message || 'Koreksi presensi gagal.';
  }
});

document.querySelector('#rekap-export')?.addEventListener('click', () => {
  const meetingId = document.querySelector('#rekap-pertemuan').value;
  if (meetingId) {
    window.location.assign(`../api/rekap_dosen.php?export=csv&id_pertemuan=${encodeURIComponent(meetingId)}`);
  }
});

const rekapView = document.querySelector('[data-view="rekap"]');
if (rekapView) {
  const observer = new MutationObserver(() => {
    if (!rekapView.hidden) loadRekapClasses();
  });
  observer.observe(rekapView, { attributes: true, attributeFilter: ['hidden'] });
  if (!rekapView.hidden) loadRekapClasses();
}

async function loadLecturerProfile() {
  const list = document.querySelector('#profil-dosen');
  const avatar = document.querySelector('#dosen-profil-avatar');
  const state = document.querySelector('#dosen-profil-state');
  if (!list) return;
  try {
    const result = await requestJson('../api/profil.php');
    const profile = result.profile || {};
    if (avatar && profile.nama_lengkap) {
      avatar.textContent = profile.nama_lengkap.slice(0, 1).toLocaleUpperCase('id-ID');
    }
    list.innerHTML = [
      ['NIDN', profile.nidn || '-'],
      ['Nama lengkap', profile.nama_lengkap || '-'],
      ['Program studi', profile.prodi || '-'],
      ['Username', profile.username || '-'],
      ['Status akun', profile.status_akun || '-'],
      ['Provinsi asal', profile.provinsi || '-'],
      ['Kota / Kabupaten asal', profile.kota || '-'],
      ['Kecamatan asal', profile.kecamatan || '-'],
      ['Kelurahan / Desa asal', profile.kelurahan || '-'],
      ['Terdaftar sejak', profile.dibuat_pada || '-'],
    ].map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('');
    if (state) state.textContent = '';
  } catch (error) {
    if (state) state.textContent = error.message || 'Profil dosen tidak dapat dimuat.';
  }
}

const lecturerProfileView = document.querySelector('[data-view="profil"]');
if (lecturerProfileView) {
  loadLecturerProfile();
  const observer = new MutationObserver(() => {
    if (!lecturerProfileView.hidden) loadLecturerProfile();
  });
  observer.observe(lecturerProfileView, { attributes: true, attributeFilter: ['hidden'] });
}

const dosenChatPanel = document.querySelector('#dosen-chat-panel');
if (dosenChatPanel) {
  let dosenChatTimer = null;
  let dosenChatLastId = 0;
  let dosenChatLoaded = false;
  const chatToggle = document.querySelector('#dosen-chat-toggle');
  const chatState = document.querySelector('#dosen-chat-state');
  const chatFileInput = document.querySelector('#dosen-chat-file');
  const chatFilePreview = document.querySelector('#dosen-chat-file-preview');
  const chatFileName = document.querySelector('#dosen-chat-file-name');

  function clearDosenChatFile() {
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
      clearDosenChatFile();
    }
  });
  document.querySelector('#dosen-chat-file-clear')?.addEventListener('click', clearDosenChatFile);

  async function loadDosenChat() {
    try {
      const query = new URLSearchParams({ bagian: 'percakapan' });
      if (dosenChatLoaded) query.set('since_id', String(dosenChatLastId));
      const result = await requestJson(`../api/chat.php?${query.toString()}`);
      const messages = document.querySelector('#dosen-chat-messages');
      if (!dosenChatLoaded && !result.pesan.length) {
        messages.innerHTML = '<p class="ak-chat-empty">Belum ada pesan. Silakan kirim pertanyaan kepada admin.</p>';
      } else if (result.pesan.length) {
        if (!dosenChatLoaded) messages.replaceChildren();
        messages.insertAdjacentHTML('beforeend', result.pesan.map((message) => {
          const attachment = message.lampiran_path
            ? ((message.lampiran_tipe || '').startsWith('image/')
              ? `<a class="ak-chat-attachment-image" href="../${escapeHtml(message.lampiran_path)}" target="_blank" rel="noopener"><img src="../${escapeHtml(message.lampiran_path)}" alt="${escapeHtml(message.lampiran_nama || 'Lampiran')}" /></a>`
              : `<a class="ak-chat-attachment-file" href="../${escapeHtml(message.lampiran_path)}" target="_blank" rel="noopener" download="${escapeHtml(message.lampiran_nama || 'lampiran')}">📎 ${escapeHtml(message.lampiran_nama || 'Unduh lampiran')}</a>`)
            : '';
          return `
        <article class="ak-chat-message ${message.pengirim === 'dosen' ? 'is-mine' : 'is-admin'}">
          ${message.isi ? `<p>${escapeHtml(message.isi)}</p>` : ''}${attachment}<time>${escapeHtml(message.dibuat_pada)}</time>
        </article>`;
        }).join(''));
        dosenChatLastId = result.pesan[result.pesan.length - 1].id_pesan;
        messages.scrollTop = messages.scrollHeight;
      }
      dosenChatLoaded = true;
      chatState.textContent = '';
    } catch (error) {
      chatState.textContent = error.message;
    }
  }

  async function loadDosenChatBadge() {
    try {
      const result = await requestJson('../api/chat.php?bagian=badge');
      const badge = document.querySelector('#dosen-chat-badge');
      badge.textContent = result.belum_dibaca > 99 ? '99+' : String(result.belum_dibaca);
      badge.hidden = result.belum_dibaca < 1;
    } catch (error) {
      if (!dosenChatPanel.hidden) chatState.textContent = error.message;
    }
  }

  function closeDosenChat() {
    dosenChatPanel.hidden = true;
    chatToggle.setAttribute('aria-expanded', 'false');
    window.clearInterval(dosenChatTimer);
    dosenChatTimer = null;
  }

  chatToggle.addEventListener('click', () => {
    const opening = dosenChatPanel.hidden;
    dosenChatPanel.hidden = !opening;
    chatToggle.setAttribute('aria-expanded', String(opening));
    if (opening) {
      loadDosenChat();
      window.clearInterval(dosenChatTimer);
      dosenChatTimer = window.setInterval(loadDosenChat, 5000);
      document.querySelector('#dosen-chat-input').focus();
    } else {
      closeDosenChat();
    }
  });
  document.querySelector('#dosen-chat-close').addEventListener('click', closeDosenChat);
  document.querySelector('#dosen-chat-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = document.querySelector('#dosen-chat-input');
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
      clearDosenChatFile();
      chatState.textContent = result.message;
      await loadDosenChat();
    } catch (error) {
      chatState.textContent = error.message;
    } finally {
      button.disabled = false;
    }
  });
  loadDosenChatBadge();
  window.setInterval(loadDosenChatBadge, 15000);
}
