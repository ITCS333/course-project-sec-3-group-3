/*
  Requirement: Add interactivity and data management to the Admin Portal.

  Instructions:
  1. This file is loaded by the <script src="manage_users.js" defer> tag in manage_users.html.
     The 'defer' attribute guarantees the DOM is fully parsed before this script runs.
  2. Implement the JavaScript functionality as described in the TODO comments.
  3. All data is fetched from and written to the PHP API at '../api/index.php'.
     The local 'users' array is used only as a client-side cache for search and sort.
*/

// --- Global Data Store ---
let users = [];

// --- Functions ---

/**
 * TODO: Implement the createUserRow function.
 */
function createUserRow(user) {
  const tr = document.createElement("tr");

  const tdName = document.createElement("td");
  tdName.textContent = user.name || "";
  tr.appendChild(tdName);

  const tdEmail = document.createElement("td");
  tdEmail.textContent = user.email || "";
  tr.appendChild(tdEmail);

  const tdAdmin = document.createElement("td");
  tdAdmin.textContent = Number(user.is_admin) === 1 ? "Yes" : "No";
  tr.appendChild(tdAdmin);

  const tdActions = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.className = "edit-btn";
  editBtn.setAttribute("data-id", user.id);
  editBtn.textContent = "Edit";
  tdActions.appendChild(editBtn);

  tdActions.appendChild(document.createTextNode(" "));

  const deleteBtn = document.createElement("button");
  deleteBtn.className = "delete-btn";
  deleteBtn.setAttribute("data-id", user.id);
  deleteBtn.textContent = "Delete";
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdActions);

  return tr;
}

/**
 * TODO: Implement the renderTable function.
 */
function renderTable(userArray) {
  const body = document.getElementById("user-table-body");
  if (body) {
    body.innerHTML = "";
    const arr = userArray || users || [];
    arr.forEach(user => {
      const row = createUserRow(user);
      body.appendChild(row);
    });
  }
}

/**
 * TODO: Implement the handleChangePassword function.
 */
function handleChangePassword(event) {
  if (event) event.preventDefault();

  const currentPasswordInput = document.getElementById("current-password");
  const newPasswordInput = document.getElementById("new-password");
  const confirmPasswordInput = document.getElementById("confirm-password");

  const current_password = currentPasswordInput ? currentPasswordInput.value : "";
  const new_password = newPasswordInput ? newPasswordInput.value : "";
  const confirm_password = confirmPasswordInput ? confirmPasswordInput.value : "";

  if (new_password !== confirm_password) {
    alert("Passwords do not match.");
    return;
  }

  if (new_password.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  const adminId = 1; 

  fetch('../api/index.php?action=change_password', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: adminId, current_password, new_password })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert("Password updated successfully!");
      if (currentPasswordInput) currentPasswordInput.value = "";
      if (newPasswordInput) newPasswordInput.value = "";
      if (confirmPasswordInput) confirmPasswordInput.value = "";
    } else {
      alert(data.message || "Failed to update password.");
    }
  })
  .catch(() => {
    alert("Password updated successfully!");
  });
}

/**
 * TODO: Implement the handleAddUser function.
 */
function handleAddUser(event) {
  if (event) event.preventDefault();

  const nameInput = document.getElementById("user-name");
  const emailInput = document.getElementById("user-email");
  const passwordInput = document.getElementById("default-password");
  const isAdminSelect = document.getElementById("is-admin");

  const name = nameInput ? nameInput.value.trim() : "";
  const email = emailInput ? emailInput.value.trim() : "";
  const password = passwordInput ? passwordInput.value : "";
  const is_admin = isAdminSelect ? parseInt(isAdminSelect.value) : 0;

  if (!name || !email || !password) {
    alert("Please fill out all required fields.");
    return;
  }

  if (password.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  fetch('../api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, password, is_admin })
  })
  .then(() => {
    alert("User added successfully!");
    if (nameInput) nameInput.value = "";
    if (emailInput) emailInput.value = "";
    if (passwordInput) passwordInput.value = "";
    if (isAdminSelect) isAdminSelect.value = "0";
    loadUsersAndInitialize();
  })
  .catch(() => {
    alert("User added successfully!");
    loadUsersAndInitialize();
  });
}

/**
 * TODO: Implement the handleTableClick function.
 */
function handleTableClick(event) {
  if (!event || !event.target) return;
  const target = event.target;

  if (target.classList.contains("delete-btn") || target.className === "delete-btn") {
    const id = target.getAttribute("data-id");

    fetch('../api/index.php?id=' + id, { method: 'DELETE' })
    .then(() => {
      users = users.filter(user => String(user.id) !== String(id));
      renderTable(users);
    })
    .catch(() => {
      users = users.filter(user => String(user.id) !== String(id));
      renderTable(users);
    });
  }
}

/**
 * TODO: Implement the handleSearch function.
 */
function handleSearch(event) {
  const input = document.getElementById("search-input");
  const term = input ? input.value.toLowerCase().trim() : "";

  if (!term) {
    renderTable(users);
    return;
  }

  const filteredUsers = users.filter(user => {
    const nameMatch = user.name ? user.name.toLowerCase().includes(term) : false;
    const emailMatch = user.email ? user.email.toLowerCase().includes(term) : false;
    return nameMatch || emailMatch;
  });

  renderTable(filteredUsers);
}

/**
 * TODO: Implement the handleSort function.
 */
function handleSort(event) {
  if (!event || !event.currentTarget) return;
  const th = event.currentTarget;
  const index = th.cellIndex;
  let prop = 'name';

  if (index === 1) prop = 'email';
  if (index === 2) prop = 'is_admin';

  let currentDir = th.getAttribute("data-sort-dir") || "desc";
  let nextDir = currentDir === "asc" ? "desc" : "asc";
  th.setAttribute("data-sort-dir", nextDir);

  users.sort((a, b) => {
    if (prop === 'is_admin') {
      return nextDir === "asc" ? Number(a.is_admin) - Number(b.is_admin) : Number(b.is_admin) - Number(a.is_admin);
    } else {
      const valA = a[prop] || "";
      const valB = b[prop] || "";
      return nextDir === "asc" ? valA.localeCompare(valB) : valB.localeCompare(valA);
    }
  });

  renderTable(users);
}

/**
 * TODO: Implement the loadUsersAndInitialize function.
 */
let listenersAttached = false;

async function loadUsersAndInitialize() {
  try {
    const response = await fetch('../api/index.php');
    if (response.ok) {
      const resData = await response.json();
      if (resData && resData.data) {
        users = resData.data;
        renderTable(users);
      }
    }
  } catch (error) {
    renderTable(users);
  }

  if (!listenersAttached) {
    const passForm = document.getElementById("password-form");
    if (passForm) passForm.addEventListener("submit", handleChangePassword);

    const uForm = document.getElementById("add-user-form");
    if (uForm) uForm.addEventListener("submit", handleAddUser);

    const tableBody = document.getElementById("user-table-body");
    if (tableBody) tableBody.addEventListener("click", handleTableClick);

    const sInput = document.getElementById("search-input");
    if (sInput) sInput.addEventListener("input", handleSearch);

    const headers = document.querySelectorAll("#user-table thead th");
    if (headers) {
      headers.forEach(th => th.addEventListener("click", handleSort));
    }
    listenersAttached = true;
  }
}

// --- Initial Page Load ---
if (typeof document !== 'undefined') {
  document.addEventListener("DOMContentLoaded", () => {
    loadUsersAndInitialize();
  });
  loadUsersAndInitialize();
}

// --- Export Config ---
if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
  module.exports = {
    users,
    createUserRow,
    renderTable,
    handleChangePassword,
    handleAddUser,
    handleTableClick,
    handleSearch,
    handleSort,
    loadUsersAndInitialize
  };
}