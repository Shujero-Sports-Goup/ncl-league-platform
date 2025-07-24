NCL League System
A full-stack web platform that empowers local basketball leagues to manage teams, fixtures, players, and live results—while delivering a sleek, responsive frontend experience for fans and stakeholders.

🚀 Features
🔐 Secure role-based login (Admin, Manager, Referee)

🏆 Multi-league management support

👥 Team and player registration workflows

📅 Fixture scheduling + referee score submission

📊 Real-time auto-generated standings

📱 Mobile-friendly public site: fixtures, standings, team profiles

🎨 Fully styled with Bootstrap 5 + modular custom CSS

📁 Folder Structure
/
├── admin/          → Admin dashboard & controls
├── manager/        → Team manager panel + roster tools
├── referee/        → Score submission interface
├── leagues/        → Public league portal (fixtures, teams, standings)
├── includes/       → auth.php, navbar.php, header/footer components
├── assets/         → CSS, JS, images (Bootstrap + custom styling)
├── api/            → JSON data endpoints (e.g. fetch_results.php)
├── db_connect.php
├── login.php / logout.php / index.php
├── schema.sql
└── README.md
🛠️ Setup Instructions
Create DB

Import schema.sql via phpMyAdmin or CLI

Includes seed leagues + an admin user

Update DB Credentials

db_connect.php:

php
$host = 'localhost';
$dbname = 'ncl_league_system';
$username = 'root';
$password = '';
Run Locally

Recommended: XAMPP / MAMP

Visit http://localhost/ncl/index.php

Default Login

Username: admin
Password: admin123
✨ Styling & Design
Bootstrap 5: Grid system, tables, modals, navbars

Custom CSS (assets/css/style.css): League branding + UI polish

Reusable layout via header.php and footer.php

Responsive navbar with session-based role display

📡 API Ready
JSON endpoint to fetch match results:

/api/fetch_results.php?league_id=1
Can be extended to power apps, dashboards, or stats visualizations.

💬 Roles & Capabilities
Role	Abilities
Admin	Add teams, fixtures, users, leagues
Manager	Manage team roster
Referee	Submit fixture results
Public	View standings, team info, fixtures
🧩 Tech Stack
PHP (MySQLi) – server-side logic

MySQL – relational database

HTML + Bootstrap 5 – responsive frontend

Alpine.js (optional) – light interactivity

Vanilla JS – page enhancements

📌 To-Do / Improvements
[ ] Player profile pages with stats

[ ] CSV export (standings, fixtures)

[ ] Admin graphs: match count per team, league heatmaps

[ ] Authentication tokens or API rate limiting

👑 Author
Built and architected by James – a visionary merging coaching, community, and code 👨🏽‍💻🏀