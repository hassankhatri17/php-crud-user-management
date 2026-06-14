# Professional User Management System (PHP CRUD)

A simple, full-featured **CRUD (Create, Read, Update, Delete)** web application built with **PHP** and **MySQL**. It manages a list of users with server-side validation, duplicate-email checking, search, and pagination.

## Features

- Add, edit, and delete users
- Server-side validation (name format, email format, required fields)
- Duplicate email detection (on add and update)
- Live search by name or email
- Pagination (5 records per page)
- Prepared statements throughout (SQL injection safe)
- Sanitized output (XSS safe)
- Clean, responsive UI

## Tech Stack

- PHP (procedural, mysqli with prepared statements)
- MySQL
- HTML / CSS (no external frameworks)

## Setup (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Clone or copy this project into `C:\xampp\htdocs\<project-folder>`.
3. Open phpMyAdmin (`http://localhost/phpmyadmin`) and create a database named `login`.
4. Create a `users` table:
   ```sql
   CREATE TABLE users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       name VARCHAR(100) NOT NULL,
       email VARCHAR(100) NOT NULL UNIQUE
   );
   ```
5. Visit `http://localhost/<project-folder>/index.php` in your browser.

## Database Configuration

By default the app connects with:
```php
$conn = mysqli_connect("localhost", "root", "", "login");
```
Update these credentials in `index.php` if your local MySQL setup differs.

## Project Flow

See `Project_Flow_Report.docx` for a diagram and explanation of the application's request flow (Add / Update / Delete / Edit / Search & Pagination).

## Author

Muhammad Hassan — BSCS, SZABIST Gharo Campus
