[![Review Assignment Due Date](https://classroom.github.com/assets/deadline-readme-button-22041afd0340ce965d47ae6ef1cefeee28c7c493a6346c4f15d667ab976d596c.svg)](https://classroom.github.com/a/rvj7Fl7r)
# ITCS333 Internet Software Development — Course Website Project

## Overview

This is a group project for **ITCS333 Internet Software Development** at the
University of Bahrain. The goal is to build a fully functional course website
using HTML, CSS, JavaScript (vanilla), and PHP with a MySQL database.

The website covers five functional areas, each assigned to one team member.

---

## Live Demo

> **Replit Deployment:** Update this link with your Replit deployment URL once the project is live.

---

## Team

| Task | Description | Student ID | GitHub Username |
|------|-------------|------------|-----------------|
| 1 | Home Page, Authentication & User Management | | |
| 2 | Course Resources | | |
| 3 | Weekly Breakdown | | |
| 4 | Assignments | | |
| 5 | Discussion Board | | |

---

## Task Descriptions

| Task | Pages & Features |
|------|-----------------|
| **Task 1 — Home & Auth** | `index.html` landing page, login/logout (`src/auth/`), admin user management (`src/admin/`) |
| **Task 2 — Resources** | List, detail, and admin CRUD for course resources (`src/resources/`) |
| **Task 3 — Weekly Breakdown** | List, detail, and admin CRUD for weekly content (`src/weekly/`) |
| **Task 4 — Assignments** | List, detail, and admin CRUD for assignments (`src/assignments/`) |
| **Task 5 — Discussion Board** | Topic board and single-topic replies (`src/discussion/`) |

---
## Database

The full schema is in **`schema.sql`**. It contains all table definitions and
seed data required by the autograder.

### Tables

| Table | Description |
|-------|-------------|
| `users` | Registered users with an `is_admin` flag |
| `resources` | Course resource links and descriptions |
| `comments_resource` | Comments on resources |
| `weeks` | Weekly content entries with resource links (JSON) |
| `comments_week` | Comments on weekly content |
| `assignments` | Course assignments with file links (JSON) |
| `comments_assignment` | Comments on assignments |
| `topics` | Discussion board topics |
| `replies` | Replies to discussion topics |

### Default Credentials

All seed users share the password **`password`**.

| Role | Email |
|------|-------|
| Admin | `admin@uob.edu.bh` |
| Student | `202101234@stu.uob.edu.bh` |

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Front-end | HTML5, CSS3, Vanilla JavaScript (ES6+) |
| Back-end | PHP 8+ |
| Database | MySQL 8 |

---

## Grading

This project is graded automatically. The autograding system will:

- Check HTML structure and required element IDs.
- Test JavaScript functions against a mocked DOM and API.
- Start a real PHP server and send HTTP requests to each API endpoint, verifying status codes and JSON responses.

> Do not rename, move, or delete any files or folders.
> The autograder expects files to be at the exact paths shown in the project structure above.

