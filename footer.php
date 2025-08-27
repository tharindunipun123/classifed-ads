<!-- Footer -->
<footer class="bg-dark text-white pt-5 pb-4">
  <div class="container">
    <div class="row g-4">
      
      <!-- Brand / About -->
      <div class="col-lg-4 col-md-6">
        <h4 class="fw-bold mb-3">
          <i class="bx bx-store-alt me-2"></i> ClassiFind
        </h4>
        <p>
          Sri Lanka's leading classified ads platform. Post your ads, find what you need, and connect with buyers and sellers across the island.
        </p>
        <div class="d-flex gap-3 mt-3">
          <a href="#" class="footer-icon"><i class="bx bxl-facebook-circle"></i></a>
          <a href="#" class="footer-icon"><i class="bx bxl-twitter"></i></a>
          <a href="#" class="footer-icon"><i class="bx bxl-instagram"></i></a>
          <a href="#" class="footer-icon"><i class="bx bxl-whatsapp"></i></a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="col-lg-2 col-md-6">
        <h6 class="text-uppercase fw-semibold mb-3">Quick Links</h6>
        <ul class="list-unstyled">
          <li><a href="index.php" class="footer-link">Home</a></li>
          <li><a href="categories.php" class="footer-link">Categories</a></li>
          <li><a href="post-ad.php" class="footer-link">Post Ad</a></li>
          <li><a href="search.php" class="footer-link">Search</a></li>
        </ul>
      </div>

      <!-- Categories -->
      <div class="col-lg-3 col-md-6">
        <h6 class="text-uppercase fw-semibold mb-3">Popular Categories</h6>
        <ul class="list-unstyled">
          <li><a href="#" class="footer-link">Personal Ads</a></li>
          <li><a href="#" class="footer-link">Jobs</a></li>
          <li><a href="#" class="footer-link">Sale / Rent</a></li>
          <li><a href="#" class="footer-link">Services</a></li>
        </ul>
      </div>

      <!-- Contact Info -->
      <div class="col-lg-3 col-md-6">
        <h6 class="text-uppercase fw-semibold mb-3">Contact</h6>
        <ul class="list-unstyled">
          <li class="mb-2"><i class="bx bx-envelope me-2"></i> info@classifind.lk</li>
          <li class="mb-2"><i class="bx bx-phone me-2"></i> +94 11 123 4567</li>
          <li><i class="bx bx-map me-2"></i> Colombo, Sri Lanka</li>
        </ul>
      </div>

    </div>

    <hr class="border-light my-4">

    <!-- Bottom Footer -->
    <div class="row align-items-center">
      <div class="col-md-6">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> ClassiFind. All Rights Reserved.</p>
      </div>
      <div class="col-md-6 text-md-end">
        <a href="privacy.php" class="footer-link me-3">Privacy Policy</a>
        <a href="terms.php" class="footer-link">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>

<!-- Custom Footer CSS -->
<style>
  .footer-link {
    color: #ffffff;
    text-decoration: none;
    transition: color 0.2s ease-in-out;
  }
  .footer-link:hover {
    color: #0d6efd; /* Bootstrap primary blue on hover */
  }
  .footer-icon {
    color: #ffffff;
    font-size: 1.5rem;
    transition: color 0.2s ease-in-out;
  }
  .footer-icon:hover {
    color: #0d6efd;
  }
</style>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
        
        // Image preview for file uploads
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('image-preview');
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Phone number formatting
        function formatPhone(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.startsWith('94')) {
                value = value.substring(2);
            }
            if (value.startsWith('0')) {
                value = value.substring(1);
            }
            input.value = value;
        }
        
        // Form validation
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;
            
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }
        
        // Loading state for forms
        function showLoading(buttonId) {
            const button = document.getElementById(buttonId);
            if (button) {
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                button.disabled = true;
            }
        }
        
        // Format numbers
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
    </script>
    
</body>
</html>