<footer>
    <div class="container">
        <div class="footer-content">
            <!-- Logo and Description -->
            <div class="footer-section">
                <div class="footer-logo">
                    <span>Uni</span><span>Rent</span>
                </div>
                <p class="footer-description">
                    The ultimate equipment rental platform for university students.
                </p>
                <div class="social-links">
                    <a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="browse.php"><i class="fas fa-chevron-right"></i> Browse Equipment</a></li>
                    <li><a href="how-it-works.php"><i class="fas fa-chevron-right"></i> How It Works</a></li>
                    <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                </ul>
            </div>

            <!-- Categories -->
            <div class="footer-section">
                <h3>Categories</h3>
                <ul class="footer-links">
                    <li><a href="browse.php?category=1"><i class="fas fa-chevron-right"></i> Audio Equipment</a></li>
                    <li><a href="browse.php?category=2"><i class="fas fa-chevron-right"></i> Cameras</a></li>
                    <li><a href="browse.php?category=3"><i class="fas fa-chevron-right"></i> Laptops</a></li>
                    <li><a href="browse.php?category=4"><i class="fas fa-chevron-right"></i> Projectors</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div class="footer-section">
                <h3>Support</h3>
                <ul class="footer-links">
                    <li><a href="faq.php"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                    <li><a href="terms.php"><i class="fas fa-chevron-right"></i> Terms of Service</a></li>
                    <li><a href="privacy.php"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                </ul>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> UniRent. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="assets/js/main.js"></script>
<?php if (!empty($page_scripts)): ?>
    <?php echo $page_scripts; ?>
<?php endif; ?>

<style>
    /* Footer Styles */
    footer {
        background-color: #1a1a2e;
        color: white;
        padding: 4rem 0 0;
        margin-top: 4rem;
    }
    
    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 3rem;
        margin-bottom: 3rem;
    }
    
    .footer-section {
        margin-bottom: 2rem;
    }
    
    .footer-logo {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 1rem;
        display: flex;
    }
    
    .footer-logo span:first-child {
        color: #4361ee;
    }
    
    .footer-logo span:last-child {
        color: #3f37c9;
    }
    
    .footer-description {
        color: #8d99ae;
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }
    
    .social-links {
        display: flex;
        gap: 1rem;
    }
    
    .social-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
        transition: all 0.3s ease;
    }
    
    .social-link:hover {
        background: linear-gradient(90deg, #4361ee, #3f37c9);
        transform: translateY(-3px);
    }
    
    .footer-section h3 {
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        position: relative;
        padding-bottom: 0.75rem;
    }
    
    .footer-section h3:after {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 50px;
        height: 2px;
        background: linear-gradient(90deg, #4361ee, #3f37c9);
    }
    
    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-links li {
        margin-bottom: 0.8rem;
    }
    
    .footer-links a {
        color: #8d99ae;
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .footer-links a:hover {
        color: white;
        padding-left: 5px;
    }
    
    .footer-links i {
        font-size: 0.8rem;
        color: #4361ee;
    }
    
    .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding: 1.5rem 0;
        text-align: center;
        color: #8d99ae;
        font-size: 0.9rem;
    }
    
    @media (max-width: 768px) {
        .footer-content {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
    }
</style>
</body>
</html>