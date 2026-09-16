const loginTrigger = document.querySelector('#loginTrigger');
const loginModal = document.querySelector('#loginModal');
const closeLogin = document.querySelector('#closeLogin');
const loginForm = document.querySelector('#loginForm');
const passwordInput = document.querySelector('#password');
const passwordToggle = document.querySelector('#passwordToggle');
const loginMessage = document.querySelector('#loginMessage');
let passwordValue = '';
let passwordVisible = false;

function openLoginModal() {
	loginModal.hidden = false;
	document.body.classList.add('modal-open');
	document.querySelector('#username').focus();
}

function closeLoginModal() {
	loginModal.hidden = true;
	document.body.classList.remove('modal-open');
}

loginTrigger.addEventListener('click', openLoginModal);
closeLogin.addEventListener('click', closeLoginModal);

loginModal.addEventListener('click', (event) => {
	if (event.target === loginModal) closeLoginModal();
});

document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && !loginModal.hidden) closeLoginModal();
});

function renderPassword() {
 passwordInput.value = passwordVisible ? passwordValue : '*'.repeat(passwordValue.length);
}

passwordInput.addEventListener('input', () => {
 const maskedValue = passwordInput.value;
 const previousMaskedValue = '*'.repeat(passwordValue.length);

 if (passwordVisible) {
  passwordValue = maskedValue;
 } else if (maskedValue.length < previousMaskedValue.length) {
  passwordValue = passwordValue.slice(0, maskedValue.length);
 } else if (maskedValue.startsWith(previousMaskedValue)) {
  passwordValue += maskedValue.slice(previousMaskedValue.length);
 } else {
  passwordValue = maskedValue.replaceAll('*', '');
 }

 renderPassword();
});

passwordToggle.addEventListener('click', () => {
 passwordVisible = !passwordVisible;
 passwordToggle.classList.toggle('is-open', passwordVisible);
 passwordToggle.setAttribute('aria-pressed', String(passwordVisible));
 passwordToggle.setAttribute('aria-label', passwordVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
 renderPassword();
});

loginForm.addEventListener('submit', (event) => {
	event.preventDefault();
	loginMessage.textContent = 'Data login siap diproses oleh sistem.';
});
