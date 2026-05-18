const loginForm = document.getElementById("login-form");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const messageContainer = document.getElementById("message-container");

function displayMessage(message, type) {
  if (messageContainer) {
    messageContainer.textContent = message;
    messageContainer.className = type;
  }
}

function isValidEmail(email) {
  const regex = /\S+@\S+\.\S+/;
  return regex.test(email);
}

function isValidPassword(password) {
  return password && password.length >= 8;
}

function handleLogin(event) {
  if (event) event.preventDefault();

  const email = emailInput ? emailInput.value.trim() : "";
  const password = passwordInput ? passwordInput.value : "";

  if (!isValidEmail(email)) {
    displayMessage("Invalid email format.", "error");
    return;
  }

  if (!isValidPassword(password)) {
    displayMessage("Password must be at least 8 characters.", "error");
    return;
  }

  displayMessage("Login successful!", "success");

  if (emailInput) emailInput.value = "";
  if (passwordInput) passwordInput.value = "";

  if (typeof fetch !== 'undefined') {
    fetch("api/index.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (data.user && data.user.is_admin) {
          window.location.href = "../admin/manage_users.html";
        } else {
          window.location.href = "../../index.html";
        }
      }
    })
    .catch(() => {});
  }
}

function setupLoginForm() {
  if (loginForm) {
    loginForm.addEventListener("submit", handleLogin);
  }
}

setupLoginForm();

if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
  module.exports = {
    displayMessage,
    isValidEmail,
    isValidPassword,
    handleLogin,
    setupLoginForm
  };
}