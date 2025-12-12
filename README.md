# School of Mary Faculty and Research Portal

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Bootstrap](https://img.shields.io/badge/Bootstrap-Icons-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white)

The **School of Mary Faculty and Research Portal** is a web-based information system designed to centralize, organize, and present data on the faculty members of the School of Mary and their academic research contributions. It also records the funding provided by various agencies for each research project.

> *(Note that the School of Mary is a dummy institution created solely for demonstrative purposes.)*

---

## 📖 Table of Contents
- [Project Overview](#project-overview)
- [Scope of Features](#scope-of-features)
- [Database Schema](#database-schema)
- [Installation](#installation)
- [Usage](#usage)
- [Future Improvements](#future-improvements)
- [Team Members and Contributions](#team-members-and-contributions)
- [References](#references-and-contributions)

---

## Project Overview

Without a centralized system, university administrators have difficulty in knowing which professors are collaborating in research projects and in managing the funding information related to these projects. They manually track the assignments, funding, and project details and as the amount of data grows their work becomes less and less efficient and more prone to errors. They also have to spend a lot of time on this task.

Besides that, public information should also be clearly distinguished from the confidential financial records so that only the authorized staff can access the sensitive funding data. Hence, the system guarantees that only authorized persons can carry out such operations as creating, updating, and deleting records in order to keep the data integrity and confidentiality.

The system helps to overcome these difficulties by offering organized, role-based access and a simplified manner of handling large, complex datasets that are interrelated. A portal acts as a medium through which this data is presented via two primary gateways: a publicly accessible site that enables users to locate faculty and research information through a live search; an authenticated administrator interface which allows authorized staff to make changes to the underlying records. Server-side live search, filtering, pagination, and structured CRUD (Create, Read, Update, Delete) workflows are carried out by the native PHP code which makes the architecture light in weight and secure.

---

## Scope of Features

### Public-Facing Modules
* **Public Faculty Directory:** A public page that lists faculty members with options to search by name or email and to filter by academic rank and department. Data are read from a faculty profile view that joins `FACULTY`, `RANK`, and `DEPARTMENT`, and a detailed view through "Read More" allows users to see projects associated with each faculty member.
* **Public Research Directory:** A public page that lists research projects with filters for research status, start from date, and end by date. Data are read from a research details view that joins `RESEARCH`, `FACULTY`, `ASSIGNMENT`, `FUNDING`, and `AGENCY`, and a detailed view through "Read More" allows users to see faculty assigned to each research project. However, the public cannot view corresponding funding information.

### Admin Modules
* **Admin Authentication:** A login page that authenticates administrators against the `ADMIN_USER` table. Only authenticated users can access the dashboard and CRUD modules.
* **Admin Dashboard:** An admin-only page that summarizes key indicators such as total numbers of faculty, research projects, assignments, agencies, funding records, and audit log, computed using aggregate SQL queries over the core tables.
* **Admin CRUD Modules:** Separate admin pages for managing Faculty records, Research projects, Faculty–research assignments, Funding records, and Agencies. These modules rely on standard `INSERT`, `UPDATE`, `DELETE`, and `SELECT` statements and enforce referential integrity through foreign keys.
* **Search, Filtering, and Pagination:** Public and admin modules support server-side search and filtering, using indexed columns and `FULLTEXT` indices to improve performance on larger datasets.
* **Data Export:** Admin users can export or download table data into CSV format with injection hardening.
* **Audit Logging and Reporting:** An automated audit trail populated by MySQL triggers. The system includes a dedicated print view (`audit_print.php`) styled via `print.css` for generating hard-copy reports of system activity.

---

## Database Schema

The system follows a server-rendered architecture where the core domain is modeled through normalized relational tables.

**Key Entities:**
* **DEPARTMENT:** Stores department information (Classification, specialization).
* **RANK:** Represents academic ranks of faculty members.
* **FACULTY:** Represents individual faculty members (First name, middle initial, last name, email).
* **RESEARCH:** Represents research projects (Title, start date, end date, status).
* **ROLE:** Encodes roles within research projects.
* **AGENCY:** Represents funding or partner agencies (Name, type, contact information).
* **ADMIN\_USER:** Stores the admin login credentials.
* **AUDIT\_LOG:** Records administrative activities (create, update, delete) via database triggers.

**Junction Tables:**
* **ASSIGNMENT:** Link faculty and research projects and capturing their role and assignment date.
* **FUNDING:** Links research projects to funding agencies, allowing multiple funding records per project.
* **RESEARCH\_KEYWORD:** Implements a many-to-many relationship between research projects and keyword tags.

---

## Installation

1.  **Clone the repository** into your web server root (for example, `htdocs` for XAMPP or the configured DocumentRoot for Apache).
2.  **Setup Database:** Create a MySQL database named `mary127` on `127.0.0.1` and import the provided SQL schema.
3.  **Configure Credentials:** Update `config/db.php` with your local database credentials (host, database name, username, password, and charset).
4.  **Verify Environment:** Ensure Apache and MySQL are running (tested on both Windows and macOS) and that PHP has PDO MySQL enabled.
5.  **Access:** Access the public portal via your browser and the admin interface via the Admin Login link.

---

## Usage

### Public-Facing Features
* **Home Page:** Displays a hero section with background images and highlights key capabilities.
* **Faculty Directory:** Lists faculty members with live search by name or email, live filters by academic rank and department, and a detailed view for associated research projects.
* **Research Directory:** Lists research projects with keyword-based live search, filters by status and duration, and detailed views showing assigned faculty (excluding funding information).

### Admin Dashboard and Management
* **Dashboard:** Displays KPIs (total number of faculty, research, agencies, funding, assignments), a real-time calendar, and lists of "Top Faculty by Assignments" and "Top Research by Funding".
* **Faculty Management:** Create, edit, and delete faculty records; assign ranks and departments; live search, filter, and export to CSV.
* **Research Management:** Create, edit, and delete research projects; assign status; enforce server-side validation for dates; export to CSV.
* **Assignment Management:** Link faculty members to research projects, select roles, and set dates.
* **Agency & Funding Management:** Manage agencies and funding records (restricted to authorized users).
* **Audit Logs:** Track real-time system changes (create, update, delete) with a printable log for institutional documentation.

---

## Future Improvements

Although the current system satisfies the basic requirements, several enhancements can be considered:

* **Role-Based Access Control:** Introduce multiple admin roles, for example, super admin, department admin, and possibly self-service faculty accounts.
* **File Management:** Support uploading and managing research documents (abstracts, full reports) with appropriate storage strategies.
* **Integration with External Profiles:** Connect with ORCID, Google Scholar, or institutional repositories.
* **Advanced Analytics:** Provide charts and dashboards that visualize research output by year, funding trends, and agency contributions.
* **Enhanced Search:** Implement full-text search for research abstracts and keywords.
* **Notification Features:** Send notifications to administrators or faculty when new projects or funding records are added.
* **Multi-Institution Support:** Generalize the system to support multiple schools or campuses.

---

## Team Members and Contributions

| Member | Contributions |
| :--- | :--- |
| **Lopez, Rhona Shayne** | Navigation bar position and Active Page Highlighting.<br>Date and other inputs validation.<br>Calendar and Time for Admins.<br>Improved UI/UX (Home Page, Footer, Print Styles, Error Pop-ups). |
| **Miguel, Angeline Joy** | **Whole of Phase 1:** Public pages, Faculty/Research directories, Admin Dashboard, KPI stats, CRUD and Audit Log functionalities, and Export feature.<br>Handled Errors from Phase 2 (UI polishing). |
| **Tulic, Janine Irish** | Admin Login Username/Password Validation.<br>Improved Pagination Handling and UI.<br>'Create' Buttons logic.<br>Live Search, Filter and Sort functionalities.<br>Sidebar Toggle and Table Alignments. |

---

## References and Inspirations

This project’s design, interface, and implementation were informed by the following publicly available resources, which are used strictly as learning references and remain under their respective licenses:

* **Harvard Business School**, “Faculty & Research,” [https://www.hbs.edu/faculty/Pages/default.aspx](https://www.hbs.edu/faculty/Pages/default.aspx).
* **CodeAstro**, “Employee Task Management System in PHP with Source Code,” YouTube video (2021-08-27), [https://youtu.be/t4ZFF5z-T1U](https://youtu.be/t4ZFF5z-T1U).
* **Coding with Elias**, “Building a Task Management System using PHP and MySQL,” YouTube playlist (2024-08-30), [https://youtube.com/playlist?list=PL2WFgdVk-usHC-HHC0SkpsmHquwHB0Aiy](https://youtube.com/playlist?list=PL2WFgdVk-usHC-HHC0SkpsmHquwHB0Aiy).
* **Skillthrive**, “HTML and CSS Project Tutorial: Pure CSS Image Slider,” YouTube video (2022-07-20), [https://youtu.be/McPdzhLRzCg](https://youtu.be/McPdzhLRzCg).
* **Database Star**, “The Best Way To Add Audit Tables to Your Database,” YouTube video (2023-10-10), [https://youtu.be/jvjhDBXSAZU](https://youtu.be/jvjhDBXSAZU).
* **QuickAdminPanel**, “Audit Logs: Observe Model Updates for Individual Fields,” YouTube video (2021-10-10), [https://youtu.be/8L2Jzo0tVpE](https://youtu.be/8L2Jzo0tVpE).
* **Stack Overflow question**, “How to do audit log for php webpage and mysql database?” (2018-10), [https://stackoverflow.com/questions/52587401/how-to-do-audit-log-for-php-webpage-and-mysql-database](https://stackoverflow.com/questions/52587401/how-to-do-audit-log-for-php-webpage-and-mysql-database).
* **SetBased**, “php-audit,” GitHub repository, [https://github.com/SetBased/php-audit](https://github.com/SetBased/php-audit).
* **CodingWithElias**, “Complete Employee Task Management System using PHP and MySQL,” YouTube video (2024-09-12), [https://youtu.be/HMuThowRpeQ](https://youtu.be/HMuThowRpeQ).
* **CodingWithElias**, “Employee Task Management System using PHP and MySQL,” GitHub repository, [https://github.com/codingWithElias/Employee-Task-Management-System-using-PHP-and-MySQL](https://github.com/codingWithElias/Employee-Task-Management-System-using-PHP-and-MySQL).
* **Smart UI Studio**, “Collapsible Sidebar Menu: Create sidebar menu using HTML CSS JS,” article (2025-01-24), [https://www.smartinfogl.com/2025/01/collapsible-sidebar-menu-using-css.html](https://www.smartinfogl.com/2025/01/collapsible-sidebar-menu-using-css.html).
* **Webslesson**, “Instant Search with Pagination in PHP Mysql jQuery and Ajax,” tutorial, [https://www.webslesson.info/2020/02/instant-search-with-pagination-in-php-mysql-jquery-and-ajax.html](https://www.webslesson.info/2020/02/instant-search-with-pagination-in-php-mysql-jquery-and-ajax.html).
* **Funda Of Web IT**, “PHP Website with Admin Panel in php,” YouTube playlist (2023-08-26), [https://www.youtube.com/playlist?list=PLRheCL1cXHrvOzRYgiV8qOz8j_dCzffvK](https://www.youtube.com/playlist?list=PLRheCL1cXHrvOzRYgiV8qOz8j_dCzffvK).
* **How to Make Tut’s**, “Multi search and multi filter application using PHP MySQL AJAX,” YouTube playlist (2022-08-19), [https://www.youtube.com/playlist?list=PLZwQ-rUbiP02ODR8QDPjaIiNqcAfk92OK](https://www.youtube.com/playlist?list=PLZwQ-rUbiP02ODR8QDPjaIiNqcAfk92OK).
* **Programming Guru**, “PHP MySQL - PHP MySQL OrderBy Query - Sort Records [A-z] [z-A] {PHP MySQL Tutorial},” YouTube video (2022-12-07), [https://www.youtube.com/watch?v=x9q2qbVOYmw](https://www.youtube.com/watch?v=x9q2qbVOYmw).
