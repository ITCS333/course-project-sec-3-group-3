/*
  Requirement: Populate the resource detail page and discussion forum.

  Instructions:
  1. Link this file to `details.html` using:
     <script src="details.js" defer></script>

  2. In `details.html`, add the following IDs:
     - To the <h1>:                           id="resource-title"
     - To the description <p>:                id="resource-description"
     - To the "Access Resource Material" <a>: id="resource-link"
     - To the <div> for comments:             id="comment-list"
     - To the comment <form>:                 id="comment-form"
     - To the <textarea>:                     id="new-comment"

  3. Implement the TODOs below.
*/

let currentResourceId = null;
let currentComments = [];

const resourceTitle = document.getElementById("resource-title");
const resourceDescription = document.getElementById("resource-description");
const resourceLink = document.getElementById("resource-link");
const commentList = document.getElementById("comment-list");
const commentForm = document.getElementById("comment-form");
const newComment = document.getElementById("new-comment");

function getResourceIdFromURL() {
  const queryString = window.location.search;
  const params = new URLSearchParams(queryString);
  return params.get("id");
}

function renderResourceDetails(resource) {
  resourceTitle.textContent = resource.title;
  resourceDescription.textContent = resource.description;
  resourceLink.href = resource.link;
}

function createCommentArticle(comment) {
  const article = document.createElement("article");

  const text = document.createElement("p");
  text.textContent = comment.text;

  const footer = document.createElement("footer");
  footer.textContent = "Posted by: " + comment.author;

  article.appendChild(text);
  article.appendChild(footer);

  return article;
}

function renderComments() {
  commentList.textContent = "";

  for (let i = 0; i < currentComments.length; i++) {
    const article = createCommentArticle(currentComments[i]);
    commentList.appendChild(article);
  }
}

async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newComment.value;

  if (commentText === "") {
    return;
  }

  const response = await fetch("./api/index.php?action=comment", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      resource_id: currentResourceId,
      author: "Student",
      text: commentText
    })
  });

  const result = await response.json();

  if (result.success) {
    currentComments.push({
      id: result.id,
      resource_id: currentResourceId,
      author: "Student",
      text: commentText
    });

    renderComments();
    newComment.value = "";
  }
}

async function initializePage() {
  currentResourceId = getResourceIdFromURL();

  if (!currentResourceId) {
    resourceTitle.textContent = "Resource not found.";
    return;
  }

  const responses = await Promise.all([
    fetch("./api/index.php?id=" + currentResourceId),
    fetch("./api/index.php?resource_id=" + currentResourceId + "&action=comments")
  ]);

  const resourceResult = await responses[0].json();
  const commentsResult = await responses[1].json();

  if (resourceResult.success) {
    renderResourceDetails(resourceResult.data);

    if (commentsResult.success) {
      currentComments = commentsResult.data;
    } else {
      currentComments = [];
    }

    renderComments();
    commentForm.addEventListener("submit", handleAddComment);
  } else {
    resourceTitle.textContent = "Resource not found.";
  }
}


// --- Initial Page Load ---
initializePage();
