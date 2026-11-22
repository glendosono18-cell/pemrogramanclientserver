const form = document.querySelector('#registrationForm');
if (window.initWilayahSelector) window.initWilayahSelector(form);
const identityStep = document.querySelector('#identityStep');
const faceStep = document.querySelector('#faceStep');
const video = document.querySelector('#registrationVideo');
const message = document.querySelector('#registrationMessage');
const instruction = document.querySelector('#faceInstruction');
const captureButton = document.querySelector('#captureFace');
const poses = [
  { name: 'depan', label: 'Hadap depan' },
  { name: 'kiri', label: 'Sedikit kiri' },
  { name: 'kanan', label: 'Sedikit kanan' },
];
let stream = null;
let descriptors = {};
let captureIndex = 0;

async function loadModels() {
  await faceapi.nets.tinyFaceDetector.loadFromUri('assets/models');
  await faceapi.nets.faceLandmark68Net.loadFromUri('assets/models');
  await faceapi.nets.faceRecognitionNet.loadFromUri('assets/models');
}

async function startCamera() {
  stream = await navigator.mediaDevices.getUserMedia({
    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
    audio: false,
  });
  video.srcObject = stream;
  await video.play();
}

function stopCamera() {
  if (stream) stream.getTracks().forEach((track) => track.stop());
  stream = null;
}

document.querySelector('#continueFace').addEventListener('click', async () => {
  message.textContent = '';
  if (!form.reportValidity()) return;

  const password = form.elements.password.value;
  if (password.length < 8) {
    message.textContent = 'Kata sandi minimal 8 karakter.';
    return;
  }

  identityStep.hidden = true;
  faceStep.hidden = false;
  captureIndex = 0;
  descriptors = {};

  try {
    await loadModels();
    await startCamera();
    instruction.textContent = 'Atur wajah lalu tekan rekam untuk pose: Hadap depan.';
    captureButton.disabled = false;
  } catch (error) {
    instruction.textContent = 'Kamera atau model wajah tidak dapat dimuat.';
  }
});

document.querySelector('#backIdentity').addEventListener('click', () => {
  stopCamera();
  faceStep.hidden = true;
  identityStep.hidden = false;
});

captureButton.addEventListener('click', async () => {
  const pose = poses[captureIndex];
  instruction.textContent = `Memproses pose ${pose.label.toLowerCase()}...`;
  captureButton.disabled = true;

  try {
    const detection = await faceapi
      .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!detection) {
      instruction.textContent = `Wajah tidak terdeteksi. Ulangi pose ${pose.label.toLowerCase()}.`;
      captureButton.disabled = false;
      return;
    }

    descriptors[pose.name] = Array.from(detection.descriptor);
    document.querySelector(`#pose-${pose.name}`).classList.add('ak-badge-success');
    captureIndex += 1;

    if (captureIndex < poses.length) {
      instruction.textContent = `Pose ${pose.label} berhasil. Rekam pose berikutnya: ${poses[captureIndex].label}.`;
      captureButton.disabled = false;
      return;
    }

    await submitRegistration();
  } catch (error) {
    instruction.textContent = 'Pengambilan data wajah gagal. Coba lagi.';
    captureButton.disabled = false;
  }
});

async function submitRegistration() {
  instruction.textContent = 'Menyimpan akun dan data wajah...';
  const payload = {
    nim: form.elements.nim.value,
    nama_lengkap: form.elements.nama_lengkap.value,
    prodi: form.elements.prodi.value,
    semester: Number(form.elements.semester.value),
    provinsi: form.elements.provinsi.value,
    kota: form.elements.kota.value,
    kecamatan: form.elements.kecamatan.value,
    kelurahan: form.elements.kelurahan.value,
    username: form.elements.username.value,
    password: form.elements.password.value,
    consent: form.elements.consent.checked,
    descriptors,
  };

  try {
    const response = await fetch('api/pendaftaran.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify(payload),
    });
    const result = await response.json();

    if (!response.ok) {
      instruction.textContent = result.message || 'Pendaftaran gagal.';
      captureButton.disabled = false;
      return;
    }

    stopCamera();
    instruction.textContent = 'Pendaftaran berhasil. Anda akan dialihkan ke login.';
    setTimeout(() => window.location.assign('index.html?daftar=berhasil'), 1200);
  } catch (error) {
    instruction.textContent = 'Tidak dapat terhubung ke server.';
    captureButton.disabled = false;
  }
}
