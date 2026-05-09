// --- Global Data Store ---
let currentWeekId  = null;  
let currentComments = [];

// --- Element Selections ---
// TODO: Select each element by its id:
const weekTitle        = document.getElementById('week-title');
const weekStartDate    = document.getElementById('week-start-date');
const weekDescription  = document.getElementById('week-description');
const weekLinksList    = document.getElementById('week-links-list');
const commentList      = document.getElementById('comment-list');
const commentForm      = document.getElementById('comment-form');
const newCommentInput  = document.getElementById('new-comment');

// --- Functions ---

/**
 * TODO: Implement getWeekIdFromURL.
 */
function getWeekIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

/**
 * TODO: Implement renderWeekDetails.
 */
function renderWeekDetails(week) {
  weekTitle.textContent = week.title;
  weekStartDate.textContent = "Starts on: " + week.start_date;
  weekDescription.textContent = week.description;

  // Clear list and add links
  weekLinksList.innerHTML = "";
  week.links.forEach(url => {
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = url;
    a.textContent = url;
    a.target = "_blank"; // يفتح الرابط في صفحة جديدة
    li.appendChild(a);
    weekLinksList.appendChild(li);
  });
}

/**
 * TODO: Implement createCommentArticle.
 */
function createCommentArticle(comment) {
  const article = document.createElement('article');
  article.innerHTML = `
    <p>${comment.text}</p>
    <footer>Posted by: ${comment.author}</footer>
  `;
  return article;
}

/**
 * TODO: Implement renderComments.
 */
function renderComments() {
  commentList.innerHTML = "";
  currentComments.forEach(comment => {
    const commentArticle = createCommentArticle(comment);
    commentList.appendChild(commentArticle);
  });
}

/**
 * TODO: Implement handleAddComment (async).
 */
async function handleAddComment(event) {
  event.preventDefault();
  const commentText = newCommentInput.value.trim();

  if (!commentText) return;

  const response = await fetch('./api/index.php?action=comment', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      week_id: parseInt(currentWeekId),
      author:  "Student", 
      text:    commentText
    })
  });

  const result = await response.json();

  if (result.success) {
    currentComments.push(result.data);
    renderComments();
    newCommentInput.value = "";
  }
}

/**
 * TODO: Implement initializePage (async).
 */
async function initializePage() {
  currentWeekId = getWeekIdFromURL();

  if (!currentWeekId) {
    weekTitle.textContent = "Week not found.";
    return;
  }

  try {
    // Fetch week details and comments in parallel
    const [weekRes, commentsRes] = await Promise.all([
      fetch(`./api/index.php?id=${currentWeekId}`),
      fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`)
    ]);

    const weekData = await weekRes.json();
    const commentsData = await commentsRes.json();

    if (weekData.success) {
      currentComments = commentsData.success ? commentsData.data : [];
      
      renderWeekDetails(weekData.data);
      renderComments();
      
      // Attach listener for the form
      commentForm.addEventListener('submit', handleAddComment);
    } else {
      weekTitle.textContent = "Week not found.";
    }
  } catch (error) {
    console.error("Error initializing page:", error);
    weekTitle.textContent = "Error loading data.";
  }
}

// --- Initial Page Load ---
initializePage();
