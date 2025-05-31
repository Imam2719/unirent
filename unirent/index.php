<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get featured equipment
$featured = getFeaturedEquipment($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniRent - University Equipment Rental System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Updated Color Scheme */
        :root {
            --primary-color: #4361ee;
            --primary-hover: #3a56d4;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --text-color: #2b2d42;
            --text-light: #8d99ae;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #f72585;
            --border-radius: 10px;
            --box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        /* Enhanced Header */
        header {
            background-color: white;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 0.5rem 0;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-img {
            height: 40px;
            width: auto;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
        }

        /* Cool Navigation Buttons */
        .nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: var(--transition);
            position: relative;
            font-size: 0.95rem;
        }

        .nav-links a:not(.btn):hover {
            color: var(--primary-color);
        }

        .nav-links a.active {
            color: var(--primary-color);
            background: rgba(67, 97, 238, 0.1);
        }

        .nav-links a.btn-nav {
            background: var(--primary-color);
            color: white;
            padding: 0.6rem 1.5rem;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
        }

        .nav-links a.btn-nav:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.3);
        }

        /* Modern Hero Section */
        .hero {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero-container {
            display: flex;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-content {
            flex: 1;
            padding-right: 3rem;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            color: var(--dark-color);
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2.5rem;
            max-width: 600px;
            color: var(--text-light);
        }

        .hero-buttons {
            display: flex;
            gap: 1.5rem;
        }

        .btn-hero-primary {
            background: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(67, 97, 238, 0.4);
            transition: var(--transition);
        }

        .btn-hero-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
        }

        .btn-hero-secondary {
            background: white;
            color: var(--primary-color);
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            border: 2px solid var(--primary-color);
            transition: var(--transition);
        }

        .btn-hero-secondary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.2);
        }

        .hero-image {
            flex: 1;
            position: relative;
            height: 500px;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transform: perspective(1000px) rotateY(-10deg);
            transition: var(--transition);
        }

        .hero-image:hover {
            transform: perspective(1000px) rotateY(-5deg);
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s ease;
        }

        .hero-image:hover img {
            transform: scale(1.05);
        }

        /* Enhanced Categories Section */
        .categories {
            padding: 6rem 0;
            background: white;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .section-header p {
            font-size: 1.1rem;
            color: var(--text-light);
            max-width: 700px;
            margin: 0 auto;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2.5rem;
        }

        .category-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }

        .category-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .category-image:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.3));
            z-index: 1;
        }

        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s ease;
        }

        .category-card:hover .category-image img {
            transform: scale(1.1);
        }

        .category-info {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .category-info h3 {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            color: var(--dark-color);
        }

        .category-info p {
            margin-bottom: 1.5rem;
            color: var(--text-light);
            flex: 1;
        }

        .btn-category {
            align-self: flex-start;
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
        }

        .btn-category:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(67, 97, 238, 0.3);
        }

        /* Featured Equipment */
        .featured {
            padding: 6rem 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2.5rem;
        }

        /* How It Works */
        .how-it-works {
            padding: 6rem 0;
            background: white;
        }

        .steps {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 3rem;
        }

        .step {
            flex: 1;
            max-width: 300px;
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .step:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }

        .step-number {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 3rem;
            font-weight: 800;
            color: rgba(67, 97, 238, 0.1);
            line-height: 1;
        }

        .step-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            color: white;
            font-size: 2rem;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .step h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .step p {
            color: var(--text-light);
            font-size: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .hero h1 {
                font-size: 3rem;
            }
            
            .hero-image {
                height: 400px;
            }
        }

        @media (max-width: 992px) {
            .hero-container {
                flex-direction: column;
            }
            
            .hero-content {
                padding-right: 0;
                text-align: center;
                margin-bottom: 3rem;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .hero-image {
                width: 100%;
                transform: none;
            }
            
            .steps {
                flex-direction: column;
                align-items: center;
            }
            
            .step {
                max-width: 100%;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero {
                padding: 3rem 0;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            
            .section-header h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .hero-image {
                height: 300px;
            }
            
            .category-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <!-- Modern Hero Section -->
        <section class="hero">
            <div class="container hero-container">
                <div class="hero-content">
                    <h1>Rent University Equipment Made Simple</h1>
                    <p>Access high-quality equipment from your university or fellow students. Affordable, convenient, and secure rentals for all your academic needs.</p>
                    <div class="hero-buttons">
                        <a href="browse.php" class="btn-hero-primary">
                            <i class="fas fa-search"></i> Browse Equipment
                        </a>
                        <a href="register.php" class="btn-hero-secondary">
                            <i class="fas fa-user-plus"></i> Join Now
                        </a>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="assets/images/hero/hero-bg.jpg" alt="Students using university equipment">
                </div>
            </div>
        </section>

        <!-- Enhanced Categories Section -->
        <section class="categories">
            <div class="container">
                <div class="section-header">
                    <h2>Explore by Category</h2>
                    <p>Find the perfect equipment for your projects from our diverse collection</p>
                </div>
                
                <div class="category-grid">
                    <?php
                    // Get categories from database
                    $sql = "SELECT * FROM categories ORDER BY name";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        while($category = $result->fetch_assoc()) {
                            $image_path = "assets/images/categories/" . strtolower(str_replace(' ', '-', $category['name'])) . ".jpg";
                            if (!file_exists($image_path)) {
                                $image_path = "assets/images/placeholder.jpg";
                            }
                    ?>
                        <div class="category-card">
                            <div class="category-image">
                                <img src="<?php echo $image_path; ?>" alt="<?php echo $category['name']; ?>">
                            </div>
                            <div class="category-info">
                                <h3><?php echo $category['name']; ?></h3>
                                <p><?php echo $category['description']; ?></p>
                                <a href="browse.php?category=<?php echo $category['id']; ?>" class="btn-category">
                                    Browse <?php echo $category['name']; ?>
                                </a>
                            </div>
                        </div>
                    <?php
                        }
                    } else {
                        echo '<p class="text-center">No categories found.</p>';
                    }
                    ?>
                </div>
            </div>
        </section>
        
        <!-- Featured Equipment -->
        <section class="featured">
            <div class="container">
                <div class="section-header">
                    <h2>Featured Equipment</h2>
                    <p>Popular items currently available for rent</p>
                </div>
                
                <div class="equipment-grid">
                    <?php
                    if(count($featured) > 0) {
                        foreach($featured as $item) {
                            include 'includes/equipment-card.php';
                        }
                    } else {
                        echo '<div class="empty-state">
                            <i class="fas fa-camera"></i>
                            <h3>No Featured Equipment</h3>
                            <p>Check back later for featured items</p>
                        </div>';
                    }
                    ?>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="how-it-works">
            <div class="container">
                <div class="section-header">
                    <h2>How UniRent Works</h2>
                    <p>Renting equipment has never been easier with our simple 3-step process</p>
                </div>
                
                <div class="steps">
                    <div class="step">
                        <span class="step-number">1</span>
                        <div class="step-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>Find Equipment</h3>
                        <p>Browse our extensive catalog of university and student-owned equipment for your needs.</p>
                    </div>
                    
                    <div class="step">
                        <span class="step-number">2</span>
                        <div class="step-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Book & Confirm</h3>
                        <p>Request the equipment for your desired dates and get instant confirmation.</p>
                    </div>
                    
                    <div class="step">
                        <span class="step-number">3</span>
                        <div class="step-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h3>Pickup & Return</h3>
                        <p>Collect the equipment from the owner and return it when your rental period ends.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>