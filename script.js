// ── Auth Form Switching ──
const loginForm    = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");

function showLogin() {
    if (loginForm) loginForm.classList.remove("hidden");
    if (registerForm) registerForm.classList.add("hidden");
}

function showRegister() {
    if (registerForm) registerForm.classList.remove("hidden");
    if (loginForm) loginForm.classList.add("hidden");
}

const showRegisterLink = document.getElementById("showRegisterForm");
const showLoginLink    = document.getElementById("showLoginForm");
if (showRegisterLink) showRegisterLink.addEventListener("click", (e) => { e.preventDefault(); showRegister(); });
if (showLoginLink)    showLoginLink.addEventListener("click", (e) => { e.preventDefault(); showLogin(); });

