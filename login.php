<?php
session_start();
require_once 'db.php';

$page_title = 'Login';
$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generate_otp() {
    return sprintf("%06d", mt_rand(100000, 999999));
}

// Send OTP using Notify.lk
function send_otp_sms($phone, $otp) {
    $api_key = "RH9L1weIpJJODyQkFfSe";   // change
    $user_id = "29316";   // change
    $sender_id = "NotifyDEMO";            // or your approved sender ID

    $url = "https://app.notify.lk/api/v1/send?user_id=$user_id&api_key=$api_key&sender_id=$sender_id&to=94$phone&message=Your OTP is $otp";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        return true; // you can decode JSON and check status if needed
    }
    return false;
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['send_otp'])) {
        // Step 1: Send OTP
        $phone = sanitize_input($_POST['phone']);
        
        if (empty($phone)) {
            $error = 'Phone number is required';
        } elseif (!preg_match('/^[0-9]{9}$/', $phone)) {
            $error = 'Please enter a valid 9-digit phone number';
        } else {
            // Check if phone exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            
            if (!$stmt->fetch()) {
                $error = 'Phone number not registered. Please register first.';
            } else {
                $otp = generate_otp();
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $stmt = $pdo->prepare("INSERT INTO otp_logs (phone, otp_code, purpose, expires_at) VALUES (?, ?, 'login', ?)");
                
                if ($stmt->execute([$phone, $otp, $expires_at])) {
                    if (send_otp_sms($phone, $otp)) {
                        $_SESSION['login_phone'] = $phone;
                        $success = 'OTP sent to +94' . $phone . '. Please enter the code below.';
                    } else {
                        $error = 'Failed to send OTP. Please try again.';
                    }
                } else {
                    $error = 'Database error. Please try again.';
                }
            }
        }
    } elseif (isset($_POST['verify_otp'])) {
        // Step 2: Verify OTP
        $phone = $_SESSION['login_phone'] ?? '';
        $entered_otp = sanitize_input($_POST['otp']);
        
        if (empty($phone)) {
            $error = 'Session expired. Please start again.';
        } elseif (empty($entered_otp)) {
            $error = 'Please enter the OTP code';
        } elseif (!preg_match('/^[0-9]{6}$/', $entered_otp)) {
            $error = 'OTP must be 6 digits';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM otp_logs 
                WHERE phone = ? AND otp_code = ? AND purpose = 'login' 
                AND status = 'sent' AND expires_at > NOW() 
                ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$phone, $entered_otp]);
            $otp_record = $stmt->fetch();
            
            if ($otp_record) {
                // Login user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
                $stmt->execute([$phone]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Mark OTP as verified
                    $stmt = $pdo->prepare("UPDATE otp_logs SET status = 'verified' WHERE id = ?");
                    $stmt->execute([$otp_record['id']]);
                    
                    $_SESSION['user_id'] = $user['id'];
                    unset($_SESSION['login_phone']);
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'User not found. Please register first.';
                }
            } else {
                $error = 'Invalid or expired OTP. Please try again.';
            }
        }
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class='bx bx-log-in text-primary' style="font-size: 3rem;"></i>
                        <h3 class="mt-3">Welcome Back</h3>
                        <p class="text-muted">Login with your mobile number</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!isset($_SESSION['login_phone'])): ?>
                        <!-- Phone Form -->
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-group-text">+94</span>
                                    <input type="text" name="phone" class="form-control" placeholder="771234567" maxlength="9" required>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="send_otp" class="btn btn-primary">Send OTP</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- OTP Form -->
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Enter OTP</label>
                                <input type="text" name="otp" class="form-control text-center" maxlength="6" required>
                                <div class="form-text">OTP sent to +94<?php echo $_SESSION['login_phone']; ?></div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="verify_otp" class="btn btn-success">Verify & Login</button>
                                <button type="submit" name="send_otp" class="btn btn-outline-secondary btn-sm">Resend OTP</button>
                                <input type="hidden" name="phone" value="<?php echo $_SESSION['login_phone']; ?>">
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted">Don't have an account? 
                            <a href="register.php">Register here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
