/*
  Requirement: Populate the "Course Resources" list page.

  Instructions:
  1. Link this file to `list.html` using:
     <script src="list.js" defer></script>

  2. In `list.html`, add id="resource-list-section" to the
     <section> element that will contain the resource articles.

  3. Implement the TODOs below.
*/

const resourceListSection = document.querySelector("#resource-list-section");

function createResourceArticle(resource) {
  const article = document.createElement("article");

  const title = document.createElement("h2");
  title.textContent = resource.title;

  const description = document.createElement("p");
  description.textContent = resource.description;

  const link = document.createElement("a");
  link.href = "details.html?id=" + resource.id;
  link.textContent = "View Resource & Discussion";

  article.appendChild(title);
  article.appendChild(description);
  article.appendChild(link);

  return article;
}

async function loadResources() {
  const response = await fetch("./api/index.php");
  const result = await response.json();

  resourceListSection.textContent = "";

  if (result.success) {
    for (let i = 0; i < result.data.length; i++) {
      const article = createResourceArticle(result.data[i]);
      resourceListSection.appendChild(article);
    }
  }
}

loadResources();
