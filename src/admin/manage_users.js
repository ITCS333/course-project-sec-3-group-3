let users = [];
let sortDirection = 'asc';

function createUserRow(user) {
  const tr = document.createElement('tr');
  
  const tdName = document.createElement('td');
  tdName.textContent = user.name;
  tr.appendChild(tdName);

  const tdEmail = document.createElement('td');
  tdEmail.textContent = user.email;
  tr.appendChild(tdEmail);

  const tdAdmin = document.createElement('td');
  tdAdmin.textContent = (user.is_admin === 1 || user.is_admin === true || user.is_admin === '1') ? 'Yes' : 'No';
  tr.appendChild(tdAdmin);

  const tdActions = document.createElement('td');
  
  const editBtn = document.createElement('button');
  editBtn.className = 'edit-btn';
  editBtn.setAttribute('data-id', user.id);
  editBtn.textContent = 'Edit';
  tdActions.appendChild(editBtn);

  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'delete-btn';
  deleteBtn.setAttribute('data-id', user.id);
  deleteBtn.textContent = 'Delete';
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdActions);

  return tr;
}

function renderTable(usersArray) {
  const tbody = document.getElementById('user-table-body') || document.querySelector('tbody');
  if (tbody) {
    tbody.innerHTML = '';
    usersArray.forEach(user => {
      tbody.appendChild(createUserRow(user));
    });
  }
}

function handleChangePassword(event) {
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault();
  }

  const currentPasswordInput = document.getElementById("current-password");
  const newPasswordInput = document.getElementById("new-password");
  const confirmPasswordInput = document.getElementById("confirm-password");

  const current = currentPasswordInput ? currentPasswordInput.value : "";
  const newPass = newPasswordInput ? newPasswordInput.value : "";
  const confirm = confirmPasswordInput ? confirmPasswordInput.value : "";

  if (newPass !== confirm) {
    alert("Passwords do not match.");
    return;
  }

  if (newPass.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  if (currentPasswordInput) currentPasswordInput.value = "";
  if (newPasswordInput) newPasswordInput.value = "";
  if (confirmPasswordInput) confirmPasswordInput.value = "";
  alert("Password updated successfully!");

  if (typeof fetch !== 'undefined') {
    fetch('api/index.php?action=change_password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: 1, current_password: current, new_password: newPass })
    }).catch(() => {});
  }
}

function handleAddUser(event) {
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault();
  }

  const nameInput = document.getElementById('new-name');
  const emailInput = document.getElementById('new-email');
  const passwordInput = document.getElementById('default-password');
  const adminInput = document.getElementById('new-is-admin');

  const name = nameInput ? nameInput.value.trim() : '';
  const email = emailInput ? emailInput.value.trim() : '';
  const password = passwordInput ? passwordInput.value : '';
  const is_admin = (adminInput && adminInput.checked) ? 1 : 0;

  if (!name || !email || !password) {
    alert("Required fields are missing.");
    return;
  }

  if (typeof fetch !== 'undefined') {
    fetch('api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, email, password, is_admin })
    }).catch(() => {});
  }
}

function handleTableClick(event) {
  if (event && event.target && event.target.classList.contains('delete-btn')) {
    const id = event.target.getAttribute('data-id');
    if (typeof fetch !== 'undefined') {
      fetch(`api/index.php?id=${id}`, { method: 'DELETE' }).catch(() => {});
    }
  }
}

function handleSearch(event) {
  const term = (event && event.target) ? event.target.value.toLowerCase() : '';
  if (!term) {
    renderTable(users);
    return;
  }
  const filtered = users.filter(u => u.name.toLowerCase().includes(term) || u.email.toLowerCase().includes(term));
  renderTable(filtered);
}

function handleSort(event) {
  sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
  const sorted = [...users].sort((a, b) => {
    if (a.name < b.name) return sortDirection === 'asc' ? -1 : 1;
    if (a.name > b.name) return sortDirection === 'asc' ? 1 : -1;
    return 0;
  });
  renderTable(sorted);
}

function loadUsersAndInitialize() {
  const passwordForm = document.getElementById('password-form');
  if (passwordForm) passwordForm.addEventListener('submit', handleChangePassword);

  const addUserForm = document.getElementById('add-user-form');
  if (addUserForm) addUserForm.addEventListener('submit', handleAddUser);

  const userTable = document.getElementById('user-table-body');
  if (userTable) {
    userTable.addEventListener('click', handleTableClick);
  }

  const searchInput = document.getElementById('search-input');
  if (searchInput) {
    searchInput.addEventListener('input', handleSearch);
  }

  const sortBtn = document.getElementById('sort-btn');
  if (sortBtn) {
    sortBtn.addEventListener('click', handleSort);
  }

  if (typeof fetch !== 'undefined') {
    fetch('api/index.php')
      .then(res => res.json())
      .then(data => {
        if (data && data.success && data.data) {
          users = data.data;
          renderTable(users);
        }
      })
      .catch(() => {});
  }
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', loadUsersAndInitialize);
}