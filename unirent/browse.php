<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get filter parameters
$category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$owner_type = isset($_GET['owner_type']) ? $_GET['owner_type'] : null;
$max_price = isset($_GET['price']) ? intval($_GET['price']) : null;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Get all categories for filter
$categories = getCategories($conn);

// Get equipment based on filters
$equipment = searchEquipment($conn, $keyword, $category_id, null, $owner_type, $max_price, $start_date, $end_date, $sort);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Equipment - UniRent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Improved Browse Page Styles */
        .browse-header {
            background: linear-gradient(135deg, #6e48aa 0%, #9d50bb 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .browse-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .search-form {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-input {
            display: flex;
            background: white;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .search-input input {
            flex: 1;
            padding: 12px 20px;
            border: none;
            font-size: 1rem;
        }
        
        .search-input button {
            background: #6e48aa;
            color: white;
            border: none;
            padding: 0 20px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .search-input button:hover {
            background: #9d50bb;
        }
        
        .browse-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }
        
        .filters {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .filter-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .filter-group h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .filter-group ul {
            list-style: none;
            padding: 0;
        }
        
        .filter-group ul li a {
            display: block;
            padding: 8px 0;
            color: #555;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .filter-group ul li a:hover, 
        .filter-group ul li a.active {
            color: #6e48aa;
            font-weight: 500;
        }
        
        .checkbox {
            margin-bottom: 8px;
        }
        
        .price-range {
            padding: 0 10px;
        }
        
        .price-range input {
            width: 100%;
        }
        
        .price-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Improved Availability Section */
        .availability-section {
            margin-top: 1.5rem;
        }
        
        .date-range-fields {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .date-field {
            display: flex;
            flex-direction: column;
        }
        
        .date-field label {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: #555;
        }
        
        .date-field input {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .filter-apply {
            margin-top: 1rem;
            width: 100%;
            padding: 10px;
            font-weight: 500;
            background: #6e48aa;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .filter-apply:hover {
            background: #5d3da9;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .results-sort select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem 0;
            color: #666;
        }
        
        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <section class="browse-header">
            <div class="container">
                <h1>Browse Equipment</h1>
                <p>Find the perfect equipment for your next project or assignment</p>
                
                <form action="browse.php" method="get" class="search-form">
                    <div class="search-input">
                        <input type="text" name="keyword" placeholder="Search cameras, projectors, audio equipment..." value="<?php echo htmlspecialchars($keyword); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
        </section>
        
        <section class="browse-content">
            <div class="container">
                <div class="browse-layout">
                    <aside class="filters">
                        <form id="filter-form">
                            <div class="filter-group">
                                <h3>Categories</h3>
                                <ul>
                                    <li>
                                        <a href="browse.php" class="<?php echo !$category_id ? 'active' : ''; ?>">
                                            All Categories
                                        </a>
                                    </li>
                                    <?php foreach($categories as $category): ?>
                                        <li>
                                            <a href="browse.php?category=<?php echo $category['id']; ?>" class="<?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                                                <?php echo $category['name']; ?> (<?php echo $category['item_count']; ?>)
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <div class="filter-group">
                                <h3>Owner Type</h3>
                                <div class="checkbox">
                                    <input type="checkbox" id="university" name="owner_type" value="university" <?php echo $owner_type == 'university' ? 'checked' : ''; ?>>
                                    <label for="university">University</label>
                                </div>
                                <div class="checkbox">
                                    <input type="checkbox" id="student" name="owner_type" value="student" <?php echo $owner_type == 'student' ? 'checked' : ''; ?>>
                                    <label for="student">Student</label>
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <h3>Price Range</h3>
                                <div class="price-range">
                                    <input type="range" id="price" name="price" min="0" max="100" value="<?php echo $max_price ? $max_price : 100; ?>">
                                    <div class="price-labels">
                                        <span>$0</span>
                                        <span>$<?php echo $max_price ? $max_price : '100+'; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Improved Availability Section -->
                            <div class="filter-group availability-section">
                                <h3>Availability</h3>
                                <div class="date-range-fields">
                                    <div class="date-field">
                                        <label for="start_date">From</label>
                                        <input type="date" id="start_date" name="start_date" 
                                               value="<?php echo htmlspecialchars($start_date); ?>"
                                               min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="date-field">
                                        <label for="end_date">To</label>
                                        <input type="date" id="end_date" name="end_date" 
                                               value="<?php echo htmlspecialchars($end_date); ?>"
                                               min="<?php echo $start_date ? $start_date : date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>">
                            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                            <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                            
                            <button type="submit" class="btn btn-primary btn-block filter-apply">Apply Filters</button>
                            <?php if($keyword || $category_id || $owner_type || $max_price || $start_date || $end_date): ?>
                                <a href="browse.php" class="btn btn-outline btn-block" style="margin-top: 10px;">Clear All</a>
                            <?php endif; ?>
                        </form>
                    </aside>
                    
                    <div class="results">
                        <div class="results-header">
                            <div class="results-count">
                                <p><?php echo count($equipment); ?> <?php echo count($equipment) == 1 ? 'item' : 'items'; ?> found</p>
                            </div>
                            
                            <div class="results-sort">
                                <label for="sort">Sort by:</label>
                                <select id="sort" name="sort">
                                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                </select>
                            </div>
                        </div>
                        
                        <?php if (count($equipment) > 0): ?>
                            <div class="equipment-grid">
                                <?php foreach($equipment as $item): ?>
                                    <div class="equipment-card">
                                        <div class="equipment-image">
                                            <img src="<?php echo $item['image'] ?: 'assets/images/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        </div>
                                        <div class="equipment-info">
                                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                            <p class="category"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                            <p class="price">$<?php echo number_format($item['daily_rate'], 2); ?>/day</p>
                                            <p class="owner">Owner: <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></p>
                                        </div>
                                        <div class="equipment-actions">
                                            <a href="equipment-details.php?id=<?php echo $item['id']; ?>" class="btn btn-primary">View Details</a>
                                            <a href="rent.php?id=<?php echo $item['id']; ?>" class="btn btn-outline">Rent Now</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-results">
                                <i class="fas fa-search"></i>
                                <h2>No equipment found</h2>
                                <p>Try adjusting your search or filter criteria.</p>
                                <a href="browse.php" class="btn btn-primary">Clear Filters</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update price label when slider changes
            const priceRange = document.getElementById('price');
            if (priceRange) {
                priceRange.addEventListener('input', function() {
                    const priceLabels = this.parentElement.querySelector('.price-labels');
                    if (priceLabels) {
                        priceLabels.querySelector('span:last-child').textContent = 
                            this.value == 100 ? '$100+' : '$' + this.value;
                    }
                });
            }
            
            // Handle filter form submission
            const filterForm = document.getElementById('filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const params = new URLSearchParams();
                    
                    // Add all form data to URL params
                    for (const [key, value] of formData.entries()) {
                        if (value) params.append(key, value);
                    }
                    
                    // Handle checkboxes
                    const universityChecked = document.getElementById('university').checked;
                    const studentChecked = document.getElementById('student').checked;
                    
                    if (universityChecked && !studentChecked) {
                        params.set('owner_type', 'university');
                    } else if (!universityChecked && studentChecked) {
                        params.set('owner_type', 'student');
                    } else {
                        params.delete('owner_type');
                    }
                    
                    // Preserve existing category if set
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('category')) {
                        params.set('category', urlParams.get('category'));
                    }
                    
                    // Redirect with new params
                    window.location.href = 'browse.php?' + params.toString();
                });
            }
            
            // Handle sort dropdown change
            const sortSelect = document.getElementById('sort');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('sort', this.value);
                    window.location.href = url.toString();
                });
            }
            
            // Preserve category in filter form when clicking category links
            document.querySelectorAll('.filter-group ul li a').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.getAttribute('href') !== 'browse.php') {
                        e.preventDefault();
                        const url = new URL(this.href);
                        const category = url.searchParams.get('category');
                        
                        const filterForm = document.getElementById('filter-form');
                        if (filterForm) {
                            filterForm.querySelector('input[name="category"]').value = category;
                            filterForm.submit();
                        }
                    }
                });
            });
            
            // Date range validation
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            if (startDateInput && endDateInput) {
                // Set minimum dates
                const today = new Date().toISOString().split('T')[0];
                startDateInput.min = today;
                
                if (startDateInput.value) {
                    endDateInput.min = startDateInput.value;
                } else {
                    endDateInput.min = today;
                }
                
                // Update end date min when start date changes
                startDateInput.addEventListener('change', function() {
                    endDateInput.min = this.value;
                    if (endDateInput.value && endDateInput.value < this.value) {
                        endDateInput.value = this.value;
                    }
                });
                
                // Ensure start date is before end date
                endDateInput.addEventListener('change', function() {
                    if (startDateInput.value && this.value < startDateInput.value) {
                        this.value = startDateInput.value;
                    }
                });
            }
        });
    </script>
</body>
</html>