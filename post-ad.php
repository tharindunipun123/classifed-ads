<?php
require_once 'db.php';

$page_title = 'Post New Ad';
$error = '';
$success = '';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=post-ad.php');
    exit;
}

// Helper functions for this page
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function upload_image($file, $directory = 'uploads/ads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return false;
    }
    
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $directory . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filepath;
    }
    
    return false;
}

// Ad types and prices
$ad_types = [
    'normal' => ['name' => 'Normal Ad', 'price' => 700, 'duration' => '30 days'],
    'super' => ['name' => 'Super Ad', 'price' => 1500, 'duration' => '45 days', 'featured' => true],
    'vip' => ['name' => 'VIP Ad', 'price' => 10000, 'duration' => '60 days', 'featured' => true, 'priority' => true]
];

// Handle form submission
if ($_POST && isset($_POST['submit_ad'])) {
    $title = sanitize_input($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $district_id = (int)$_POST['district_id'];
    $location = sanitize_input($_POST['location']);
    $price = (float)$_POST['price'];
    $description = sanitize_input($_POST['description']);
    $phone = sanitize_input($_POST['phone']);
    $ad_type = sanitize_input($_POST['ad_type']);
    
    // Optional messaging apps
    $whatsapp = isset($_POST['whatsapp']) ? 1 : 0;
    $telegram = isset($_POST['telegram']) ? 1 : 0;
    $imo = isset($_POST['imo']) ? 1 : 0;
    $viber = isset($_POST['viber']) ? 1 : 0;
    
    // Validation
    if (empty($title)) {
        $error = 'Title is required';
    } elseif (empty($category_id)) {
        $error = 'Please select a category';
    } elseif (empty($district_id)) {
        $error = 'Please select a district';
    } elseif (empty($location)) {
        $error = 'Location is required';
    } elseif (empty($phone) || !preg_match('/^[0-9]{9}$/', $phone)) {
        $error = 'Valid phone number is required';
    } elseif (!array_key_exists($ad_type, $ad_types)) {
        $error = 'Invalid ad type selected';
    } else {
        // Handle image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_image = upload_image($_FILES['image']);
            if ($uploaded_image) {
                $image_url = $uploaded_image;
            } else {
                $error = 'Image upload failed. Please try again.';
            }
        }
        
        if (!$error) {
            try {
                // Calculate expiry date
                $duration_days = $ad_type == 'vip' ? 60 : ($ad_type == 'super' ? 45 : 30);
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
                
                // Insert ad
                $stmt = $pdo->prepare("INSERT INTO ads (user_id, category_id, district_id, title, description, price, location, phone, whatsapp, telegram, imo, viber, image_url, ad_type, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $category_id,
                    $district_id,
                    $title,
                    $description,
                    $price,
                    $location,
                    $phone,
                    $whatsapp,
                    $telegram,
                    $imo,
                    $viber,
                    $image_url,
                    $ad_type,
                    $expires_at
                ]);
                
                $ad_id = $pdo->lastInsertId();
                
                // Redirect to payment page
                header("Location: payment.php?ad_id=$ad_id");
                exit;
                
            } catch (Exception $e) {
                $error = 'Failed to create ad. Please try again.';
            }
        }
    }
}

// Fetch categories and districts
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$districts = $pdo->query("SELECT * FROM districts ORDER BY name")->fetchAll();

include 'header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class='bx bx-plus-circle me-2'></i>
                        Post New Advertisement
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Notice -->
                    <div class="alert alert-info">
                        <strong>Notice:</strong> Ads cannot be edited after approval. Please fill everything correctly and submit.
                        <br>
                        <strong>දැන්වීම:</strong> දැන්වීම approve වීමෙන් පසුව නැවත edit කල නොහැක. සියලු දේ නිවැරදිව සම්පූර්ණ කර submit කරන්න.
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-auto-hide">
                            <i class='bx bx-error me-2'></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="adForm">
                        <!-- Image Upload -->
                        <div class="mb-4">
                            <label for="image" class="form-label">Image *</label>
                            <input type="file" 
                                   class="form-control" 
                                   id="image" 
                                   name="image" 
                                   accept="image/*"
                                   onchange="previewImage(this)"
                                   required>
                            <div class="form-text">Upload a clear image of your ad (Max: 5MB)</div>
                            <img id="image-preview" class="mt-2 rounded" style="display:none; max-width: 200px; max-height: 200px;">
                        </div>
                        
                        <!-- Ad Type -->
                        <div class="mb-4">
                            <label for="ad_type" class="form-label">Ad Type *</label>
                            <select class="form-select" id="ad_type" name="ad_type" required>
                                <option value="">Select Ad Type</option>
                                <?php foreach ($ad_types as $type => $info): ?>
                                    <option value="<?php echo $type; ?>">
                                        <?php echo $info['name']; ?> - Rs. <?php echo number_format($info['price']); ?> 
                                        (<?php echo $info['duration']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <!-- Title -->
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="title" 
                                       name="title" 
                                       maxlength="200"
                                       required>
                            </div>
                            
                            <!-- Category -->
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- District -->
                            <div class="col-md-6 mb-3">
                                <label for="district_id" class="form-label">District *</label>
                                <select class="form-select" id="district_id" name="district_id" required>
                                    <option value="">Select District</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option value="<?php echo $district['id']; ?>">
                                            <?php echo htmlspecialchars($district['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Location -->
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="location" 
                                       name="location" 
                                       placeholder="e.g., Colombo 03"
                                       required>
                            </div>
                        </div>
                        
                        <!-- Price -->
                        <div class="mb-3">
                            <label for="price" class="form-label">Price (LKR)</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="price" 
                                   name="price" 
                                   min="0" 
                                   step="0.01"
                                   placeholder="Enter price (optional)">
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="6" 
                                      placeholder="Describe your ad in detail..."></textarea>
                        </div>
                        
                        <!-- Phone -->
                        <div class="mb-4">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <div class="input-group">
                                <span class="input-group-text">+94</span>
                                <input type="text" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       placeholder="771234567" 
                                       maxlength="9" 
                                       pattern="[0-9]{9}"
                                       onkeyup="formatPhone(this)"
                                       required>
                            </div>
                            <div class="form-text">Enter your 9-digit mobile number</div>
                        </div>
                        
                        <!-- Messaging Apps -->
                        <div class="mb-4">
                            <label class="form-label">Available on:</label>
                            <div class="row">
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="whatsapp" name="whatsapp" value="1">
                                        <label class="form-check-label" for="whatsapp">
                                            <i class='bx bxl-whatsapp text-success'></i> WhatsApp
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="telegram" name="telegram" value="1">
                                        <label class="form-check-label" for="telegram">
                                            <i class='bx bxl-telegram text-primary'></i> Telegram
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="imo" name="imo" value="1">
                                        <label class="form-check-label" for="imo">
                                            <i class='bx bx-video text-info'></i> IMO
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="viber" name="viber" value="1">
                                        <label class="form-check-label" for="viber">
                                            <i class='bx bx-phone text-purple'></i> Viber
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="submit_ad" class="btn btn-gradient text-white btn-lg" id="submitBtn">
                                <i class='bx bx-paper-plane me-2'></i>
                                Create Advertisement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Ad Types Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class='bx bx-info-circle me-2'></i>
                        Ad Types & Pricing
                    </h6>
                </div>
                <div class="card-body">
                    <?php foreach ($ad_types as $type => $info): ?>
                        <div class="border rounded p-3 mb-3 <?php echo $type; ?>-ad">
                            <h6 class="fw-bold text-capitalize"><?php echo $info['name']; ?></h6>
                            <div class="text-success fw-bold fs-5">Rs. <?php echo number_format($info['price']); ?></div>
                            <div class="small text-muted">Valid for <?php echo $info['duration']; ?></div>
                            <?php if (isset($info['featured'])): ?>
                                <div class="small text-primary">
                                    <i class='bx bx-star me-1'></i>Featured listing
                                </div>
                            <?php endif; ?>
                            <?php if (isset($info['priority'])): ?>
                                <div class="small text-warning">
                                    <i class='bx bx-crown me-1'></i>Priority placement
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class='bx bx-help-circle me-2'></i>
                        How to Post
                    </h6>
                </div>
                <div class="card-body">
                    <ol class="list-unstyled">
                        <li class="mb-2">
                            <i class='bx bx-check-circle text-success me-2'></i>
                            Fill all required fields
                        </li>
                        <li class="mb-2">
                            <i class='bx bx-check-circle text-success me-2'></i>
                            Upload clear image
                        </li>
                        <li class="mb-2">
                            <i class='bx bx-check-circle text-success me-2'></i>
                            Select ad type
                        </li>
                        <li class="mb-2">
                            <i class='bx bx-check-circle text-success me-2'></i>
                            Complete payment
                        </li>
                        <li>
                            <i class='bx bx-check-circle text-success me-2'></i>
                            Ad goes live after approval
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form submission
document.getElementById('adForm').addEventListener('submit', function() {
    showLoading('submitBtn');
});

function showLoading(buttonId) {
    const button = document.getElementById(buttonId);
    if (button) {
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating Ad...';
        button.disabled = true;
    }
}

// Phone formatting
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

// Image preview
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('image-preview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include 'footer.php'; ?>