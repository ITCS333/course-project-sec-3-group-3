// --- Element Selections ---
// TODO: Select the section for the week list using its id 'week-list-section'.
const weekListSection = document.getElementById('week-list-section');

// --- Functions ---

/**
 * TODO: Implement createWeekArticle.
 * 
 * Returns:
 * An <article> element matching the structure shown in list.html
 */
function createWeekArticle(week) {
    // Create the article element
    const article = document.createElement('article');

    // Set the inner HTML based on the structure in the instructions
    article.innerHTML = `
        <h2>${week.title}</h2>
        <p>Starts on: ${week.start_date}</p>
        <p>${week.description}</p>
        <a href="details.html?id=${week.id}">View Details & Discussion</a>
    `;

    return article;
}

/**
 * TODO: Implement loadWeeks (async).
 */
async function loadWeeks() {
    // 1. Use fetch() to GET data from './api/index.php'
    const response = await fetch('./api/index.php');
    
    // 2. Parse the JSON response
    const result = await response.json();

    if (result.success) {
        // 3. Clear any existing content from the list section
        weekListSection.innerHTML = "";

        // 4. Loop through the data array
        result.data.forEach(week => {
            // Call createWeekArticle(week)
            const weekArticle = createWeekArticle(week);
            
            // Append the returned <article> to the list section
            weekListSection.appendChild(weekArticle);
        });
    }
}

// --- Initial Page Load ---
loadWeeks();
