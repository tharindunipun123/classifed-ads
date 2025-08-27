<?php
require_once 'db.php';

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
if ($_POST && isset($_POST['confirm_payment'])) {
    $payment_method = sanitize_input($_POST['payment_method']);
    $transaction_id = sanitize_input($_POST['transaction_id']);
    
    function sanitize_input($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
    
    // Handle receipt upload
    $receipt_image = '';
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['receipt']['type'], $allowed_types)) {
            $directory = 'uploads/receipts/';
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
            
            $extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $filename = 'receipt_' . $ad_id . '_' . uniqid() . '.' . $extension;
            $filepath = $directory . $filename;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filepath)) {
                $receipt_image = $filepath;
            }
        }
    }
    
    if (empty($payment_method)) {
        $error = 'Please select a payment method';
    } elseif (empty($transaction_id)) {
        $error = 'Transaction ID is required';
    } else {
        try {
            // Insert payment record
            $stmt = $pdo->prepare("INSERT INTO ad_payments (ad_id, amount, payment_method, transaction_id, receipt_image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ad_id, $amount, $payment_method, $transaction_id, $receipt_image]);
            
            $success = 'Payment details submitted successfully! Your ad will be reviewed and approved within 24 hours.';
            
        } catch (Exception $e) {
            $error = 'Failed to process payment. Please try again.';
        }
    }
}

include 'header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
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
                            <br>
                            <a href="dashboard.php" class="btn btn-sm btn-success mt-2">Go to Dashboard</a>
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
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="ez_cash">eZ Cash</option>
                                <option value="mcash">mCash</option>
                                <option value="payhere">PayHere</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="transaction_id" class="form-label">Transaction ID / Reference Number *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="transaction_id" 
                                   name="transaction_id" 
                                   placeholder="Enter transaction ID or reference number"
                                   required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="receipt" class="form-label">Upload Receipt (Optional)</label>
                            <input type="file" 
                                   class="form-control" 
                                   id="receipt" 
                                   name="receipt" 
                                   accept="image/*">
                            <div class="form-text">Upload payment receipt for faster verification</div>
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
document.getElementById('paymentForm')?.addEventListener('submit', function() {
    const button = document.getElementById('paymentBtn');
    if (button) {
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        button.disabled = true;
    }
});
</script>

<?php include 'footer.php'; ?>