# Admin-V2 - New Administration Interface

## Features

- **Dashboard**: Overview with statistics and recent applications
- **Applications Management**: Full CRUD with filtering by status and search
- **Properties Management**: Manage available properties
- **Contracts Management**: Generate and track lease contracts
- **Move-in/Move-out**: Track inspections and damages
- **Secure Authentication**: Session-based admin login with auto-logout

## Default Admin Account

For initial setup, create an admin account using this SQL:

```sql
INSERT INTO administrateurs (username, password_hash, nom, prenom, email, actif) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'System', 'admin@myinvest-immobilier.com', 1);
```

**Default credentials:**
- Username: `admin`
- Password: `password`

**Important:** Change this password immediately after first login!

## Access

Login at: `/admin-v2/login.php`

## Security

- Passwords are hashed using bcrypt
- Session timeout after 2 hours of inactivity
- All admin pages protected by authentication check
- CSRF protection on forms (to be implemented)
