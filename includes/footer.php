</div>
<!-- End .container -->

<footer class="footer bg-dark text-light mt-5 pt-5 pb-3" style="background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%) !important;">
  <div class="container">
    <div class="row g-4">

      <!-- About -->
      <div class="col-md-4">
        <div class="d-flex align-items-center mb-3">
          <img src="/ncl-league-platform/assets/images/nukta-logo.png" alt="Nukta" style="height: 40px; margin-right: 15px; background: white; padding: 5px; border-radius: 8px;">
          <h4 class="fw-bold text-white mb-0">Nukta League Management</h4>
        </div>
        <p class="small mb-3 text-light" style="opacity: 0.9;">
          Professional sports league management powered by Nukta Sports Solutions. Part of the Nukta ecosystem transforming African sports.
        </p>
        <div class="d-flex gap-3 fs-5">
          <a href="#" class="text-light"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="text-light"><i class="fab fa-x-twitter"></i></a>
          <a href="https://www.instagram.com/nairobicountyleague/?__pwa=1#" target="_blank" class="text-light"><i class="fab fa-instagram"></i></a>
          <a href="#" class="text-light"><i class="fab fa-youtube"></i></a>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="col-md-4">
        <h5 class="fw-semibold text-white mb-3">Contact Information</h5>
        <div class="small text-light" style="opacity: 0.9;">
          <div class="mb-3">
            <i class="fas fa-map-marker-alt text-primary me-2"></i>
            <strong>Location</strong><br>
            <span class="ms-3">Fortis Suites, 7th Floor, Rm 708</span>
          </div>
          <div class="mb-3">
            <i class="fas fa-envelope text-primary me-2"></i>
            <strong>Email</strong><br>
            <a href="mailto:admin@nukta.pro" class="text-primary ms-3">admin@nukta.pro</a>
          </div>
          <div class="mb-3">
            <i class="fas fa-phone text-primary me-2"></i>
            <strong>Phone</strong><br>
            <a href="tel:+254113056293" class="text-primary ms-3">+254 113 056 293</a>
          </div>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="col-md-4">
        <h5 class="fw-semibold text-white mb-3">Quick Links</h5>
        <ul class="list-unstyled small text-light" style="opacity: 0.9;">
          <li class="mb-2"><a class="text-light text-decoration-none" href="/ncl-league-platform/leagues/fixtures.php"><i class="fas fa-calendar-alt me-2"></i>Fixtures</a></li>
          <li class="mb-2"><a class="text-light text-decoration-none" href="/ncl-league-platform/leagues/standings.php"><i class="fas fa-trophy me-2"></i>Standings</a></li>
          <li class="mb-2"><a class="text-light text-decoration-none" href="/ncl-league-platform/leagues/teams.php"><i class="fas fa-users me-2"></i>Teams</a></li>
          <li class="mb-2"><a class="text-primary text-decoration-none" href="https://nukta.pro" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Visit Nukta.pro</a></li>
        </ul>
      </div>
    </div>

    <hr class="my-4" style="border-color: rgba(255, 255, 255, 0.2);">
    
    <div class="text-center small text-light" style="opacity: 0.8;">
      © <?= date('Y') ?> Nukta League Management System. All rights reserved.<br>
      A product of <a href="https://nukta.pro" target="_blank" class="text-primary">Nukta Sports Solutions</a> | Powered by <strong>Shujero Sports Group</strong>
    </div>
  </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS -->
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
  AOS.init({
    duration: 800,
    once: true
  });
</script>
</body>
</html>
