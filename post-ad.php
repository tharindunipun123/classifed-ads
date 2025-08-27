<?php
session_start();
require_once 'db.php';

$page_title = 'Post New Ad (DEBUG MODE)';
$error = '';
$success = '';

// // DEBUG: show all PHP errors
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// // DEBUG: dump session
// echo "<h3>DEBUG: Session Data</h3><pre>";
// print_r($_SESSION);
// echo "</pre>";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
      // Wait a second so you can see the debug
    header("Refresh:0; url=login.php?redirect=post-ad.php");
    exit;
}

// Helper
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function upload_image($file, $directory = 'uploads/ads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        echo "<div style='color:red'>DEBUG: No image uploaded or upload error.</div>";
        return false;
    }

    $allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];
    $file_info = getimagesize($file['tmp_name']);
    if (!$file_info || !in_array($file_info['mime'], $allowed_types)) {
        echo "<div style='color:red'>DEBUG: Invalid image type.</div>";
        return false;
    }

    if ($file['size'] > 5*1024*1024) {
        echo "<div style='color:red'>DEBUG: Image too large.</div>";
        return false;
    }

    if (!file_exists($directory)) mkdir($directory, 0777, true);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'ad_' . uniqid() . '.' . $ext;
    $path = $directory . $filename;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        echo "<div style='color:green'>DEBUG: Image uploaded to $path</div>";
        return $path;
    } else {
        echo "<div style='color:red'>DEBUG: move_uploaded_file failed</div>";
        return false;
    }
}

// Ad types
$ad_types = [
    'normal' => ['name'=>'Normal Ad','price'=>700,'duration'=>30],
    'super'  => ['name'=>'Super Ad','price'=>1500,'duration'=>45,'featured'=>true],
    'vip'    => ['name'=>'VIP Ad','price'=>10000,'duration'=>60,'featured'=>true,'priority'=>true]
];

// Fetch categories and districts for the form
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$districts = $pdo->query("SELECT * FROM districts ORDER BY name")->fetchAll();

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>DEBUG: Raw POST Data</h3><pre>";
    print_r($_POST);
    echo "</pre>";

    echo "<h3>DEBUG: Raw FILES Data</h3><pre>";
    print_r($_FILES);
    echo "</pre>";

    $title = sanitize_input($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $district_id = (int)($_POST['district_id'] ?? 0);
    $location = sanitize_input($_POST['location'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $description = sanitize_input($_POST['description'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $ad_type = sanitize_input($_POST['ad_type'] ?? '');

    $whatsapp = isset($_POST['whatsapp']) ? 1 : 0;
    $telegram = isset($_POST['telegram']) ? 1 : 0;
    $imo = isset($_POST['imo']) ? 1 : 0;
    $viber = isset($_POST['viber']) ? 1 : 0;

    if (strlen($title) < 10) {
        $error = "Title must be at least 10 characters.";
    } elseif (!$category_id) {
        $error = "Select a category.";
    } elseif (!$district_id) {
        $error = "Select a district.";
    } elseif (!$location) {
        $error = "Location is required.";
    } elseif (!preg_match('/^[0-9]{9}$/', $phone)) {
        $error = "Enter valid 9-digit phone.";
    } elseif (!array_key_exists($ad_type,$ad_types)) {
        $error = "Invalid ad type.";
    } elseif (empty($description)) {
        $error = "Description is required.";
    } else {
        try {
            // Validate category
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id=? AND status='active'");
            $stmt->execute([$category_id]);
            if (!$stmt->fetch()) $error="Invalid category.";

            // Validate district
            if (!$error) {
                $stmt = $pdo->prepare("SELECT id FROM districts WHERE id=?");
                $stmt->execute([$district_id]);
                if (!$stmt->fetch()) $error="Invalid district.";
            }

            if (!$error) {
                // Upload image
                $image_url = '';
                if (!empty($_FILES['image']['name'])) {
                    $image_url = upload_image($_FILES['image']);
                    if (!$image_url) $error="Invalid image. Use JPEG, PNG, GIF, WebP under 5MB.";
                } else {
                    $error="Please upload an image.";
                }
            }
            
            if (!$error) {
                $days = $ad_types[$ad_type]['duration'];
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$days} days"));

                $stmt = $pdo->prepare("INSERT INTO ads 
                (user_id, category_id, district_id, title, description, price, location, phone, whatsapp, telegram, imo, viber, image_url, ad_type, expires_at, status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved ','pending')");

                $ok = $stmt->execute([
                    $_SESSION['user_id'],
                    $category_id,
                    $district_id,
                    $title,
                    $description,
                    $price ?: null,
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

                if ($ok) {
                    $ad_id = $pdo->lastInsertId();
                    // echo "<div style='color:green'>DEBUG: Ad inserted successfully with ID $ad_id</div>";
                    //header("Location: payment.php?ad_id=$ad_id&success=1");
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error="Failed to create ad. DB error: " . implode(" | ", $stmt->errorInfo());
                }
            }
        } catch (Exception $e) {
            $error="Database error: " . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

if ($error) {
    echo "<div style='color:red'><b>DEBUG ERROR:</b> $error</div>";
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="path/to/your/custom.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

</head>
<body>

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
                        <i class='bx bx-info-circle me-2'></i>
                        <strong>Notice:</strong> Ads cannot be edited after approval. Please fill everything correctly and submit.
                        <br>
                        <strong>දැනුම්:</strong> දැන්වීම approve වීමෙන් පසුව නවත edit කළ නොහැක. සියල්ල දේ නිවැරදිව සම්පූර්ණ කර submit කරන්න.
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-auto-hide">
                            <i class='bx bx-error me-2'></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-auto-hide">
                            <i class='bx bx-check me-2'></i>
                            <?php echo $success; ?>
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
                            <div class="form-text">Upload a clear image of your ad (Max: 5MB, formats: JPEG, PNG, GIF, WebP)</div>
                            <img id="image-preview" class="mt-2 rounded" style="display:none; max-width: 300px; max-height: 200px;">
                        </div>
                        
                        <!-- Ad Type -->
                        <div class="mb-4">
                            <label for="ad_type" class="form-label">Ad Type *</label>
                            <select class="form-select" id="ad_type" name="ad_type" required>
                                <option value="">Select Ad Type</option>
                                <?php foreach ($ad_types as $type => $info): ?>
                                    <option value="<?php echo $type; ?>" 
                                            <?php echo (isset($_POST['ad_type']) && $_POST['ad_type'] == $type) ? 'selected' : ''; ?>>
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
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                       placeholder="Enter a catchy title (min 10 characters)"
                                       required>
                                <div class="form-text">Minimum 10 characters</div>
                            </div>
                            
                            <!-- Category -->
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo $district['id']; ?>"
                                                <?php echo (isset($_POST['district_id']) && $_POST['district_id'] == $district['id']) ? 'selected' : ''; ?>>
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
                                       placeholder="e.g., Colombo 03, Kandy City"
                                       value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
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
                                   value="<?php echo isset($_POST['price']) ? $_POST['price'] : ''; ?>"
                                   placeholder="Enter price (optional)">
                            <div class="form-text">Leave empty if price is negotiable</div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="6" 
                                      placeholder="Describe your ad in detail..."
                                      required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <div class="form-text">Provide detailed information about your ad</div>
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
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                       onkeyup="formatPhone(this)"
                                       required>
                            </div>
                            <div class="form-text">Enter your 9-digit mobile number (without +94 or 0)</div>
                        </div>
                        
                        <!-- Messaging Apps -->
                        <div class="mb-4">
                            <label class="form-label">Available on:</label>
                            <div class="row">
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="whatsapp" name="whatsapp" value="1"
                                               <?php echo (isset($_POST['whatsapp']) && $_POST['whatsapp']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="whatsapp">
                                            <i class='bx bxl-whatsapp text-success'></i> WhatsApp
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="telegram" name="telegram" value="1"
                                               <?php echo (isset($_POST['telegram']) && $_POST['telegram']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="telegram">
                                            <i class='bx bxl-telegram text-primary'></i> Telegram
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="imo" name="imo" value="1"
                                               <?php echo (isset($_POST['imo']) && $_POST['imo']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="imo">
                                            <i class='bx bx-video text-info'></i> IMO
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="viber" name="viber" value="1"
                                               <?php echo (isset($_POST['viber']) && $_POST['viber']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="viber">
                                            <i class='bx bx-phone text-purple'></i> Viber
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms Agreement -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    I agree to the <a href="#" target="_blank">Terms of Service</a> and confirm that all information provided is accurate.
                                </label>
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
                    
                    <hr>
                    
                    <div class="alert alert-warning alert-sm">
                        <small>
                            <i class='bx bx-shield me-1'></i>
                            <strong>Safety:</strong> Never share personal details like NIC, bank details in ads.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation and submission
document.getElementById('adForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const image = document.getElementById('image').files[0];
    const agreeTerms = document.getElementById('agree_terms').checked;
    
    // Client-side validation
    if (title.length < 10) {
        e.preventDefault();
        alert('Title must be at least 10 characters long');
        return;
    }
    
    if (description.length < 20) {
        e.preventDefault();
        alert('Description must be at least 20 characters long');
        return;
    }
    
    if (!phone.match(/^[0-9]{9}$/)) {
        e.preventDefault();
        alert('Please enter a valid 9-digit phone number');
        return;
    }
    
    if (!image) {
        e.preventDefault();
        alert('Please upload an image for your ad');
        return;
    }
    
    if (!agreeTerms) {
        e.preventDefault();
        alert('Please agree to the terms and conditions');
        return;
    }
    
    // Show loading state
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
        const file = input.files[0];
        
        // File size check (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Image size must be less than 5MB');
            input.value = '';
            return;
        }
        
        // File type check
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please upload a valid image file (JPEG, PNG, GIF, WebP)');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('image-preview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}

// Character counters
document.getElementById('title').addEventListener('input', function() {
    const len = this.value.length;
    const color = len < 10 ? 'text-danger' : 'text-success';
    // You can add character counter display here
});

document.getElementById('description').addEventListener('input', function() {
    const len = this.value.length;
    const color = len < 20 ? 'text-danger' : 'text-success';
    // You can add character counter display here
});
</script>

</body>
</html>
<!-- The rest of your HTML form stays the same as before -->
<?php include 'footer.php'; ?>
