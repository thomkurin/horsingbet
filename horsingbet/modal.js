// Obter os elementos do DOM
const registerBtn = document.getElementById('register-btn');
const loginBtn = document.getElementById('login-btn');
const registerModal = document.getElementById('register-modal');
const loginModal = document.getElementById('login-modal');
const registerCloseBtn = registerModal.getElementsByClassName('close')[0];
const loginCloseBtn = loginModal.getElementsByClassName('close')[0];

// Abrir modal de registro ao clicar em "Registre-se"
registerBtn.addEventListener('click', function() {
  registerModal.style.display = 'block';
});

// Abrir modal de login ao clicar em "Login"
loginBtn.addEventListener('click', function() {
  loginModal.style.display = 'block';
});

// Fechar o modal de registro ao clicar no botão de fechar
registerCloseBtn.addEventListener('click', function() {
  registerModal.style.display = 'none';
});

// Fechar o modal de login ao clicar no botão de fechar
loginCloseBtn.addEventListener('click', function() {
  loginModal.style.display = 'none';
});

// Fechar o modal de registro ou login ao clicar fora dele
window.addEventListener('click', function(event) {
  if (event.target == registerModal) {
    registerModal.style.display = 'none';
  } else if (event.target == loginModal) {
    loginModal.style.display = 'none';
  }
});
