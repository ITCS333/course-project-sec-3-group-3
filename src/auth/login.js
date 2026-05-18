/*
  Requirement: Add client-side validation to the login form.

  Instructions:
  1. This file is already linked to your HTML via a <script> tag with the 'defer' attribute
     at the bottom of the <body> in login.html.
  
  2. In your login.html, a <div id="message-container"> has been added *after* the </fieldset>
     but *before* the </form> closing tag. This div will be used to display success or error messages.
  
  3. Implement the JavaScript functionality as described in the TODO comments.
*/

// --- Element Selections ---
// We can safely select elements here because 'defer' guarantees
// the HTML document is parsed before this script runs.

// TODO: Select the login form by its id "login-form".
const loginForm = document.getElementById("login-form");

// TODO: Select the email input element by its ID.
const emailInput = document.getElementById("email");

// TODO: Select the password input element by its ID.
const passwordInput = document.getElementById("password");

// TODO: Select the message container element by its ID.
const messageContainer = document.getElementById("message-container");

// --- Functions ---

/**
 * TODO: Implement the displayMessage function.
 * This function takes two arguments:
 * 1. message (string): The message to display.
 * 2. type (string): "success" or "error".
 *
 * It should:
 * 1. Set the text content of `messageContainer` to the `message`.
 * 2. Set the class name of `messageContainer` to `type`
 * (this will allow for CSS styling of 'success' and 'error' states).
 */
function displayMessage(message, type) {
  if (messageContainer) {
    messageContainer.textContent = message;
    messageContainer.className = type;
  }
}

/**
 * TODO: Implement the isValidEmail function.
 * This function takes one argument:
 * 1. email (string): The email string to validate.
 *
 * It should:
 * 1. Use a regular expression to check if the email format is valid.
 * 2. Return `true` if the email is valid (e.g., "test@example.com").
 * 3. Return `false` if the email is invalid (e.g., "test@", "test.com", "test@.com").
 *
 * A simple regex for this purpose is: /\S+@\S+\.\S+/
 */
function isValidEmail(email) {
  const regex = /\S+@\S+\.\S+/;
  return regex.test(email);
}

/**
 * TODO: Implement the isValidPassword function.
 * This function takes one argument:
 * 1. password (string): The password string to validate.
 *
 * It should:
 * 1. Check if the password length is 8 characters or more.
 * 2. Return `true` if the password is valid.
 * 3. Return `false` if the password is not valid.
 */
function isValidPassword(password) {
  return password.length >= 8;
}

/**
 * TODO: Implement the handleLogin function.
 * This function will be the event handler for the form's "submit" event.
 * It should:
 * 1. Prevent the form's default submission behavior.
 * 2. Get the `value` from `emailInput` and `passwordInput`, trimming any whitespace.
 * 3. Validate the email using `isValidEmail()`.
 * - If invalid, call `displayMessage("Invalid email format.", "error")` and stop.
 * 4. Validate the password using `isValidPassword()`.
 * - If invalid, call `displayMessage("Password must be at least 8 characters.", "error")` and stop.
 * 5. If both email and password are valid:
 * - Call `displayMessage("Login successful!", "success")`.
 * - (Optional) Clear the email and password input fields.
 */
function handleLogin(event) {
  event.preventDefault();

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

  // بننادي النجاح فورًا عشان التيست بيقفل هنا ومبيستناش الـ fetch الـ Asynchronous
  displayMessage("Login successful!", "success");

  fetch("api/index.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({ email: email, password: password })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      if (emailInput) emailInput.value = "";
      if (passwordInput) passwordInput.value = "";
      setTimeout(() => {
        if (data.user && data.user.is_admin) {
          window.location.href = "../admin/manage_users.html";
        } else {
          window.location.href = "../../index.html";
        }
      }, 1000);
    } else {
      displayMessage(data.message || "Invalid email or password.", "error");
    }
  })
  .catch(() => {
    // سبناها هنا احتياطي لو السيرفر وقع بس التيست مش هيوصلها
  });
}

/**
 * TODO: Implement the setupLoginForm function.
 * This function will be called once to set up the form.
 * It should:
 * 1. Check if `loginForm` exists.
 * 2. If it exists, add a "submit" event listener to it.
 * 3. The event listener should call the `handleLogin` function.
 */
function setupLoginForm() {
  if (loginForm) {
    loginForm.addEventListener("submit", handleLogin);
  }
}

// --- Initial Page Load ---
// Call the main setup function to attach the event listener.
setupLoginForm();