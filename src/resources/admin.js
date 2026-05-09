/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// This will hold the resources loaded from the API.
let resources = [];
let editingId = null;

const resourceForm = document.querySelector("#resource-form");
const resourcesTbody = document.querySelector("#resources-tbody");

function createResourceRow(resource) {
  const tr = document.createElement("tr");

  const titleTd = document.createElement("td");
  titleTd.textContent = resource.title;

  const descriptionTd = document.createElement("td");
  descriptionTd.textContent = resource.description;

  const linkTd = document.createElement("td");
  linkTd.textContent = resource.link;

  const actionsTd = document.createElement("td");

  const editButton = document.createElement("button");
  editButton.textContent = "Edit";
  editButton.className = "edit-btn";
  editButton.setAttribute("data-id", resource.id);

  const deleteButton = document.createElement("button");
  deleteButton.textContent = "Delete";
  deleteButton.className = "delete-btn";
  deleteButton.setAttribute("data-id", resource.id);

  actionsTd.appendChild(editButton);
  actionsTd.appendChild(deleteButton);

  tr.appendChild(titleTd);
  tr.appendChild(descriptionTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}

function renderTable() {
  resourcesTbody.textContent = "";

  for (let i = 0; i < resources.length; i++) {
    const row = createResourceRow(resources[i]);
    resourcesTbody.appendChild(row);
  }
}

async function handleAddResource(event) {
  event.preventDefault();

  const title = document.getElementById("resource-title").value;
  const description = document.getElementById("resource-description").value;
  const link = document.getElementById("resource-link").value;

  if (editingId === null) {
    const response = await fetch("./api/index.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ title, description, link })
    });

    const result = await response.json();

    if (result.success) {
      resources.push({
        id: result.id,
        title: title,
        description: description,
        link: link
      });

      renderTable();
      resourceForm.reset();
    }
  } else {
    const response = await fetch("./api/index.php", {
      method: "PUT",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        id: editingId,
        title: title,
        description: description,
        link: link
      })
    });

    const result = await response.json();

    if (result.success) {
      for (let i = 0; i < resources.length; i++) {
        if (resources[i].id == editingId) {
          resources[i].title = title;
          resources[i].description = description;
          resources[i].link = link;
        }
      }

      editingId = null;
      document.getElementById("add-resource").textContent = "Add Resource";
      renderTable();
      resourceForm.reset();
    }
  }
}

async function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.getAttribute("data-id");

    const response = await fetch("./api/index.php?id=" + id, {
      method: "DELETE"
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.filter(function (resource) {
        return resource.id != id;
      });

      renderTable();
    }
  }

  if (event.target.classList.contains("edit-btn")) {
    const id = event.target.getAttribute("data-id");

    const resource = resources.find(function (resource) {
      return resource.id == id;
    });

    if (resource) {
      document.getElementById("resource-title").value = resource.title;
      document.getElementById("resource-description").value = resource.description;
      document.getElementById("resource-link").value = resource.link;

      editingId = id;
      document.getElementById("add-resource").textContent = "Update Resource";
    }
  }
}

async function loadAndInitialize() {
  const response = await fetch("./api/index.php");
  const result = await response.json();

  if (result.success) {
    resources = result.data;
  }

  renderTable();

  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
