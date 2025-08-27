<?php
// Start session at the very top
session_start();
require_once 'db.php';

// Helper function should be at the top
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

$page_title = 'Payment';
$error = '';
$success = '';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get ad details
$ad_id = (int)($_GET['ad_id'] ?? 0);
if (!$ad_id) {
    header('Location: dashboard.php');
    exit;
}

// Fetch ad details
$stmt = $pdo->prepare("SELECT a.*, c.name as category_name, d.name as district_name 
                       FROM ads a 
                       LEFT JOIN categories c ON a.category_id = c.id 
                       LEFT JOIN districts d ON a.district_id = d.id 
                       WHERE a.id = ? AND a.user_id = ?");
$stmt->execute([$ad_id, $_SESSION['user_id']]);
$ad = $stmt->fetch();

if (!$ad) {
    header('Location: dashboard.php');
    exit;
}

// Ad pricing
$ad_prices = [
    'normal' => 700,
    'super' => 1500,
    'vip' => 10000
];

$amount = $ad_prices[$ad['ad_type']] ?? 0;

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $payment_method = sanitize_input($_POST['payment_method'] ?? '');
    $transaction_id = sanitize_input($_POST['transaction_id'] ?? '');
    
    // Debug output
    echo "<!-- DEBUG: Payment Method: $payment_method -->";
    echo "<!-- DEBUG: Transaction ID: $transaction_id -->";
    
    // Handle receipt upload
    $receipt_image = '';
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        echo "<!-- DEBUG: File upload detected -->";
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['receipt']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $directory = 'uploads/receipts/';
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
            
            $extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $filename = 'receipt_' . $ad_id . '_' . uniqid() . '.' . $extension;
            $filepath = $directory . $filename;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filepath)) {
                $receipt_image = $filepath;
                echo "<!-- DEBUG: File uploaded successfully: $receipt_image -->";
            } else {
                echo "<!-- DEBUG: File upload failed -->";
            }
        } else {
            echo "<!-- DEBUG: Invalid file type: $file_type -->";
        }
    }
    
    // Validation
    if (empty($payment_method)) {
        $error = 'Please select a payment method';
        echo "<!-- DEBUG: Validation failed - payment method empty -->";
    } elseif (empty($transaction_id)) {
        $error = 'Mobile number is required';
        echo "<!-- DEBUG: Validation failed - transaction ID empty -->";
    } else {
        try {
            // Check if ad_payments table exists
            $table_check = $pdo->query("SHOW TABLES LIKE 'ad_payments'")->fetch();
            if (!$table_check) {
                // Create the table if it doesn't exist
                $create_table = "CREATE TABLE ad_payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ad_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    payment_method VARCHAR(50) NOT NULL,
                    transaction_id VARCHAR(100) NOT NULL,
                    receipt_image VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE
                )";
                $pdo->exec($create_table);
                echo "<!-- DEBUG: Created ad_payments table -->";
            }
            
            // Insert payment record
            $stmt = $pdo->prepare("INSERT INTO ad_payments (ad_id, amount, payment_method, transaction_id, receipt_image) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$ad_id, $amount, $payment_method, $transaction_id, $receipt_image]);
            
            if ($result) {
                $success = 'Payment details submitted successfully! Your ad will be reviewed and approved within 24 hours.';
                
                // Update ad status to pending payment verification
                $update_stmt = $pdo->prepare("UPDATE ads SET payment_status = 'pending_verification' WHERE id = ?");
                $update_result = $update_stmt->execute([$ad_id]);
                
                echo "<!-- DEBUG: Payment inserted successfully, ad update result: " . ($update_result ? 'success' : 'failed') . " -->";
                
                // Redirect after success
                header("Refresh: 3; URL=dashboard.php");
            } else {
                $error = 'Failed to process payment. Please try again.';
                echo "<!-- DEBUG: Payment insertion failed -->";
                $errorInfo = $stmt->errorInfo();
                echo "<!-- DEBUG: PDO Error: " . implode(" | ", $errorInfo) . " -->";
            }
            
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
            error_log("Payment Error: " . $e->getMessage());
            echo "<!-- DEBUG: Exception: " . $e->getMessage() . " -->";
        }
    }
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
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .alert-auto-hide {
            display: block;
        }
        .text-purple {
            color: #6f42c1;
        }
        .debug-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Debug Information -->
            <div class="debug-info">
                <strong>Debug Information:</strong><br>
                Ad ID: <?php echo $ad_id; ?><br>
                User ID: <?php echo $_SESSION['user_id']; ?><br>
                Amount: Rs. <?php echo number_format($amount); ?><br>
                POST Method: <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST') ? 'Yes' : 'No'; ?><br>
                Form Submitted: <?php echo isset($_POST['confirm_payment']) ? 'Yes' : 'No'; ?>
            </div>
            
            <div class="card">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class='bx bx-credit-card me-2'></i>
                        Complete Payment
                    </h5>
                </div>
                <div class="card-body">
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
                            <p class="mt-2">You will be redirected to your dashboard in 3 seconds...</p>
                            <a href="dashboard.php" class="btn btn-sm btn-success mt-2">Go to Dashboard Now</a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Ad Summary -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h6 class="card-title">Ad Summary</h6>
                            <div class="row">
                                <div class="col-md-8">
                                    <p class="mb-1"><strong>Title:</strong> <?php echo htmlspecialchars($ad['title']); ?></p>
                                    <p class="mb-1"><strong>Category:</strong> <?php echo htmlspecialchars($ad['category_name']); ?></p>
                                    <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($ad['district_name']) . ', ' . htmlspecialchars($ad['location']); ?></p>
                                    <p class="mb-0"><strong>Ad Type:</strong> <?php echo ucfirst($ad['ad_type']); ?> Ad</p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <h4 class="text-primary">Rs. <?php echo number_format($amount); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Instructions -->
                    <div class="alert alert-warning">
                        <h6><i class='bx bx-info-circle me-2'></i>Payment Instructions:</h6>
                        <p class="mb-2">ඔබගේ දැන්වීම සජීවී කර ගැනීමට කරුණාකර අදාළ මුදල ගෙවා WhatsApp හරහා රිසිට්පත හා දැන්වීම් දුරකථන අංකය එවන්න.</p>
                        <p class="mb-0">To activate your ad, please make the payment and send the receipt along with your ad phone number via WhatsApp.</p>
                    </div>
                    
                    <!-- Payment Methods -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Bank Transfer</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-1"><strong>Bank:</strong> Commercial Bank</p>
                                    <p class="mb-1"><strong>Account:</strong> 1234567890</p>
                                    <p class="mb-0"><strong>Name:</strong> ClassiFind (Pvt) Ltd</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Mobile Payment</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-1"><strong>eZ Cash:</strong> 0771234567</p>
                                    <p class="mb-1"><strong>mCash:</strong> 0771234567</p>
                                    <p class="mb-0"><strong>PayHere:</strong> Available</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$success): ?>
                    <!-- Payment Form -->
                    <form method="POST" enctype="multipart/form-data" id="paymentForm">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <option value="bank_transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="ez_cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'ez_cash') ? 'selected' : ''; ?>>eZ Cash</option>
                                <option value="mcash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'mcash') ? 'selected' : ''; ?>>mCash</option>
                                <option value="payhere" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'payhere') ? 'selected' : ''; ?>>PayHere</option>
                                <option value="other" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="transaction_id" class="form-label">Enter Your Mobile Number *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="transaction_id" 
                                   name="transaction_id" 
                                   placeholder="Enter 10-digit mobile number (e.g., 771234567)"
                                   value="<?php echo isset($_POST['transaction_id']) ? htmlspecialchars($_POST['transaction_id']) : ''; ?>"
                                   pattern="[0-9]{9,10}"
                                   required>
                            <div class="form-text">Enter your 9 or 10 digit mobile number without country code</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="receipt" class="form-label">Upload Receipt (Optional)</label>
                            <input type="file" 
                                   class="form-control" 
                                   id="receipt" 
                                   name="receipt" 
                                   accept="image/*">
                            <div class="form-text">Upload payment receipt for faster verification (JPEG, PNG, GIF)</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="confirm_payment" class="btn btn-success btn-lg" id="paymentBtn">
                                <i class='bx bx-check-circle me-2'></i>
                                Confirm Payment
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Contact Info -->
            <div class="card mt-4">
                <div class="card-body text-center">
                    <h6>Need Help?</h6>
                    <p class="text-muted mb-2">Contact us for payment assistance</p>
                    <a href="https://wa.me/94771234567" class="btn btn-outline-success btn-sm me-2" target="_blank">
                        <i class='bx bxl-whatsapp me-1'></i>
                        WhatsApp
                    </a>
                    <a href="tel:+94771234567" class="btn btn-outline-primary btn-sm">
                        <i class='bx bx-phone me-1'></i>
                        Call Us
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
    // Basic validation
    const paymentMethod = document.getElementById('payment_method');
    const transactionId = document.getElementById('transaction_id');
    
    if (!paymentMethod.value) {
        e.preventDefault();
        alert('Please select a payment method');
        paymentMethod.focus();
        return;
    }
    
    if (!transactionId.value.trim()) {
        e.preventDefault();
        alert('Please enter your mobile number');
        transactionId.focus();
        return;
    }
    
    // Validate mobile number format (9-10 digits)
    const mobileRegex = /^[0-9]{9,10}$/;
    if (!mobileRegex.test(transactionId.value.trim())) {
        e.preventDefault();
        alert('Please enter a valid 9 or 10-digit mobile number (numbers only)');
        transactionId.focus();
        return;
    }
    
    // Show loading state
    const button = document.getElementById('paymentBtn');
    if (button) {
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        button.disabled = true;
    }
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>