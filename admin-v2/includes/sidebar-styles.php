<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f5f5f5;
    }
    .sidebar {
        background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
        min-height: 100vh;
        padding: 0;
        position: fixed;
        left: 0;
        top: 0;
        width: 250px;
        color: white;
    }
    .sidebar .logo {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar .logo h4 {
        margin: 10px 0 5px 0;
        font-size: 18px;
        font-weight: 600;
    }
    .sidebar .logo small {
        color: #bdc3c7;
    }
    .sidebar .nav-link {
        color: rgba(255,255,255,0.8);
        padding: 12px 20px;
        transition: all 0.3s;
        border-left: 3px solid transparent;
    }
    .sidebar .nav-link:hover {
        background-color: rgba(255,255,255,0.1);
        color: white;
        border-left-color: #3498db;
    }
    .sidebar .nav-link.active {
        background-color: rgba(52, 152, 219, 0.2);
        color: white;
        border-left-color: #3498db;
    }
    .sidebar .nav-link i {
        margin-right: 10px;
        width: 20px;
    }
    .main-content {
        margin-left: 250px;
        padding: 30px;
    }
    .logout-btn {
        position: absolute;
        bottom: 20px;
        left: 20px;
        right: 20px;
    }
</style>
