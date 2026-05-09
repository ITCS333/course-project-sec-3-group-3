// --- Global Data Store ---
let weeks = [];

// --- Element Selections ---
const weekForm = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');
const addWeekBtn = document.getElementById('add-week');

// --- Functions ---

/**
 * Creates a <tr> element for a week object.
 */
function createWeekRow(week) {
    const tr = document.createElement('tr');
    
    tr.innerHTML = `
        <td>${week.title}</td>
        <td>${week.start_date}</td>
        <td>${week.description}</td>
        <td>
            <button class="edit-btn" data-id="${week.id}">Edit</button>
            <button class="delete-btn" data-id="${week.id}">Delete</button>
        </td>
    `;
    return tr;
}

/**
 * Renders the weeks array into the table body.
 */
function renderTable() {
    weeksTbody.innerHTML = "";
    weeks.forEach(week => {
        const row = createWeekRow(week);
        weeksTbody.appendChild(row);
    });
}

/**
 * Handles the form submission for adding a new week.
 */
async function handleAddWeek(event) {
    event.preventDefault();

    const title = document.getElementById('week-title').value;
    const startDate = document.getElementById('week-start-date').value;
    const description = document.getElementById('week-description').value;
    const linksRaw = document.getElementById('week-links').value;

    // Split by newlines and filter out empty strings
    const links = linksRaw.split('\n').map(l => l.trim()).filter(l => l !== "");

    const weekData = { 
        title: title, 
        start_date: startDate, 
        description: description, 
        links: links 
    };

    const editId = addWeekBtn.getAttribute('data-edit-id');

    if (editId) {
        // If we are in edit mode
        await handleUpdateWeek(editId, weekData);
    } else {
        // Normal add mode
        const response = await fetch('./api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(weekData)
        });

        const result = await response.json();

        if (result.success) {
            weekData.id = result.id;
            weeks.push(weekData);
            renderTable();
            weekForm.reset();
        }
    }
}

/**
 * Handles updating an existing week.
 */
async function handleUpdateWeek(id, fields) {
    const response = await fetch('./api/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, ...fields })
    });

    const result = await response.json();

    if (result.success) {
        // Update local array
        const index = weeks.findIndex(w => w.id == id);
        if (index !== -1) {
            weeks[index] = { id: id, ...fields };
        }
        
        renderTable();
        weekForm.reset();
        
        // Reset button state
        addWeekBtn.textContent = "Add Week";
        addWeekBtn.removeAttribute('data-edit-id');
    }
}

/**
 * Handles clicks on the table body (Delete and Edit).
 */
async function handleTableClick(event) {
    const id = event.target.getAttribute('data-id');

    if (event.target.classList.contains('delete-btn')) {
        if (confirm("Are you sure you want to delete this week?")) {
            const response = await fetch(`./api/index.php?id=${id}`, {
                method: 'DELETE'
            });
            const result = await response.json();
            if (result.success) {
                weeks = weeks.filter(w => w.id != id);
                renderTable();
            }
        }
    }

    if (event.target.classList.contains('edit-btn')) {
        const week = weeks.find(w => w.id == id);
        if (week) {
            // Populate form
            document.getElementById('week-title').value = week.title;
            document.getElementById('week-start-date').value = week.start_date;
            document.getElementById('week-description').value = week.description;
            document.getElementById('week-links').value = week.links.join('\n');

            // Change button to update mode
            addWeekBtn.textContent = "Update Week";
            addWeekBtn.setAttribute('data-edit-id', id);
        }
    }
}

/**
 * Initial load of data and event listeners.
 */
async function loadAndInitialize() {
    const response = await fetch('./api/index.php');
    const result = await response.json();

    if (result.success) {
        weeks = result.data;
        renderTable();
    }

    weekForm.addEventListener('submit', handleAddWeek);
    weeksTbody.addEventListener('click', handleTableClick);
}

// Initial Page Load
loadAndInitialize();
