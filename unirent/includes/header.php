<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - UniRent' : 'UniRent - University Equipment Rental'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (isset($page_styles)): ?>
    <?php echo $page_styles; ?>
    <?php endif; ?>
    <style>
        /* Header Styles */
        header {
            background-color: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 0.8rem 0;
            transition: all 0.3s ease;
        }
        
        header.scrolled {
            padding: 0.5rem 0;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.8rem;
            font-weight: 800;
            color: #2b2d42;
            text-decoration: none;
        }
        
        .logo span:first-child {
            color: #4361ee;
        }
        
        .logo span:last-child {
            color: #3f37c9;
        }
        
        .logo:hover {
            opacity: 0.9;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
            flex: 1;
            justify-content: center;
        }
        
        .nav-links a {
            color: #4a4e69;
            font-weight: 600;
            text-decoration: none;
            padding: 0.5rem 0;
            position: relative;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .nav-links a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, #4361ee, #3f37c9);
            transition: width 0.3s ease;
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            color: #4361ee;
        }
        
        .nav-links a:hover:after,
        .nav-links a.active:after {
            width: 100%;
        }
        
        .auth-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #4361ee;
            color: #4361ee;
        }
        
        .btn-outline:hover {
            background: rgba(67, 97, 238, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(90deg, #4361ee, #3f37c9);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.3);
        }
        
        @media (max-width: 992px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .auth-buttons {
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header id="main-header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <span>Uni</span><span>Rent</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="browse.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'browse.php' ? 'active' : ''; ?>">Browse</a></li>
                    <li><a href="how-it-works.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'how-it-works.php' ? 'active' : ''; ?>">How It Works</a></li>
                    <li><a href="contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">Contact</a></li>
                </ul>
                
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                        <a href="logout.php" class="btn btn-primary"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('main-header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    </script>