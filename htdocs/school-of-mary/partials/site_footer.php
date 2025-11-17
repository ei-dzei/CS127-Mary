</main>

<footer class="footer">
    <div class="container footer__grid">
        
        <div class="footer__col footer__brand">
            <a href="<?= BASE_URL ?>/public/">
                <img src="<?= BASE_URL ?>/public/logo.png" alt="School of Mary Logo" class="footer__logo">
            </a>
            <p>School of Mary</p>
            <p class="footer__tagline">Excellence in Research and Innovation</p>
            <p class="footer__legal">
                Copyright &copy; <?= date('Y'); ?> School of Mary. All Rights Reserved.
            </p>
        </div>

        <div class="footer__col">
            <h4 class="footer__heading">Quick Links</h4>
            <ul class="footer__list">
                <li><a href="<?= BASE_URL ?>/public/">Home</a></li>
                <li><a href="<?= BASE_URL ?>/public/faculty.php">Faculty</a></li>
                <li><a href="<?= BASE_URL ?>/public/research.php">Research</a></li>
                <li><a href="<?= BASE_URL ?>/admin/login.php">Admin Login</a></li>
            </ul>
        </div>

        <div class="footer__col">
            <h4 class="footer__heading">Contact Us</h4>
            <ul class="footer__list footer__contact">
                <li><i class="icon">📞</i> (02) 8555-1234</li>
                <li><i class="icon">📧</i> info@somary.edu.ph</li>
                <li><i class="icon">📍</i> 123 Research Lane, City, 1000</li>
                <li><i class="icon">🕒</i> Mon - Fri: 8:00 AM - 5:00 PM</li>
            </ul>
        </div>

        <div class="footer__col">
            <h4 class="footer__heading">Connect</h4>
            <div class="footer__social">
                <a href="#" aria-label="Facebook"><i class="icon">f</i></a>
                <a href="#" aria-label="Twitter"><i class="icon">t</i></a>
                <a href="#" aria-label="LinkedIn"><i class="icon">in</i></a>
            </div>
            <p class="footer__note">
                Dedicated to advancing knowledge through scholarly pursuit.
            </p>
        </div>
    </div>
</footer>