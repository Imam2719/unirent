<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Location: admin-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | UniRent</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>


    /* Modern Admin Navigation Styles */
    :root {
        --primary: #3498db;
        --primary-dark: #2980b9;
        --secondary: #2ecc71;
        --dark: #2c3e50;
        --light: #ecf0f1;
        --sidebar-width: 250px;
    }

    html, body {
    margin: 0;
    padding: 0;
    height: 100%;
}


.admin-nav {
    background: linear-gradient(135deg, var(--dark) 0%, #1a2530 100%);
    color: white;
    width: var(--sidebar-width);
    height: 100vh; /* ADD this line */
    position: fixed;
    top: 0;
    left: 0;
    box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    overflow-y: auto; /* already added */
}


.admin-nav::-webkit-scrollbar {
    width: 6px;
}

.admin-nav::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}


    .brand {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .brand h2 {
        margin: 0;
        font-weight: 600;
        color: white;
        font-size: 1.5rem;
    }

    .brand .logo {
        width: 40px;
        height: 40px;
        margin-bottom: 10px;
    }


    .nav-section-header {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 15px 20px 10px;
        margin-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .nav-item {
        position: relative;
        margin: 5px 15px;
    }

    .nav-link {
        color: rgba(255, 255, 255, 0.8);
        padding: 12px 20px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        transform: translateX(5px);
    }

    .nav-link.active {
        background: var(--primary);
        color: white;
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }

    .nav-link i {
        margin-right: 12px;
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }

    .notification-bubble {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: var(--secondary);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: bold;
    }

    .main-content {
        margin-left: var(--sidebar-width);
        padding: 20px;
        transition: all 0.3s ease;
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        .admin-nav {
            width: 70px;
            overflow: hidden;
        }

        .brand h2,
        .nav-link span,
        .nav-section-header {
            display: none;
        }

        .nav-link {
            justify-content: center;
            padding: 15px 0;
        }

        .nav-link i {
            margin-right: 0;
            font-size: 1.3rem;
        }

        .main-content {
            margin-left: 70px;
        }
    }

    @media (max-width: 576px) {
        .admin-nav {
            width: 0;
        }

        .admin-nav.active {
            width: 250px;
            z-index: 1001;
        }

        .main-content {
            margin-left: 0;
        }
    }
    </style>
</head>

<body>
    <div class="admin-nav">
        <div class="brand">
            <div class="logo">
                <i class="fas fa-camera-retro fa-2x" style="color: var(--primary)"></i>
            </div>
            <h2>UniRent Admin</h2>
            <small>Provenance-Enabled</small>
        </div>

        <div class="nav-menu">
            <!-- MAIN ADMIN FUNCTIONS -->
            <div class="nav-item">
                <a href="dashboard.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="manage-equipment.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage-equipment.php' ? 'active' : '' ?>">
                    <i class="fas fa-camera"></i>
                    <span>Equipment</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="manage-users.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                 
                </a>
            </div>

            <div class="nav-item">
                <a href="manage-rentals.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage-rentals.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Rentals</span>
                </a>
            </div>

            <!-- PROVENANCE SECTION -->
            <div class="nav-section-header">
                PROVENANCE TRACKING
            </div>

            <div class="nav-item">
                <a href="provenance-dashboard.php" 
                   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'provenance-dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-project-diagram"></i>
                    <span>Provenance Dashboard</span>
                </a>
            </div>

            <!-- WHY PROVENANCE QUERIES -->
            <div class="nav-item">
                <a href="query-why-equipment-prices.php" 
                   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'query-why-equipment-prices.php' ? 'active' : '' ?>">
                    <i class="fas fa-question-circle" style="color: #f39c12;"></i>
                    <span>WHY: Price Changes</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="query-why-user-permissions.php" 
                   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'query-why-user-permissions.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-shield" style="color: #f39c12;"></i>
                    <span>WHY: User Permissions</span>
                </a>
            </div>

            <!-- HOW PROVENANCE QUERIES -->
            <div class="nav-item">
                <a href="query-how-rental-lifecycle.php" 
                   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'query-how-rental-lifecycle.php' ? 'active' : '' ?>">
                    <i class="fas fa-route" style="color: #3498db;"></i>
                    <span>HOW: Rental Lifecycle</span>
                </a>
            </div>

            <!-- WHERE PROVENANCE QUERIES -->
            <div class="nav-item">
                <a href="query-where-data-lineage.php" 
                   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'query-where-data-lineage.php' ? 'active' : '' ?>">
                    <i class="fas fa-sitemap" style="color: #2ecc71;"></i>
                    <span>WHERE: Data Lineage</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="query-where-rental-sources.php" 
                   class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'query-where-rental-sources.php' ? 'active' : '' ?>">
                    <i class="fas fa-database" style="color: #2ecc71;"></i>
                    <span>WHERE: Rental Sources</span>
                </a>
            </div>

            <!-- SYSTEM MONITORING -->
            <div class="nav-section-header">
                SYSTEM MONITORING
            </div>

        

            <div class="nav-item">
                <a href="provenance-activity.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'provenance-activity.php' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>User Activity</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="provenance-queries.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'provenance-queries.php' ? 'active' : '' ?>">
                    <i class="fas fa-database"></i>
                    <span>Query Logs</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="reports.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
        </div>
    </div>

    <main class="main-content">
        <div class="container-fluid">