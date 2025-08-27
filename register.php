<?php
session_start();
require_once 'db.php'; // Your database connection file

// If user is already in the verification process, send them to the correct page
if (isset($_SESSION['registration_phone'])) {
    header('Location: verify.php');
    exit;
}

$page_title = 'Register';
$error = '';

// --- Helper Functions (Copy these from the previous answer) ---
function sanitize_input($data) { return htmlspecialchars(strip_tags(trim($data))); }
function generate_otp() { return mt_rand(100000, 999999); }
function send_otp_sms($phone, $otp) {
    // ⚠️ IMPORTANT: Replace with your actual credentials
    $api_key = "RH9L1weIpJJODyQkFfSe";
    $user_id = "29316";
    $sender_id = "NotifyDEMO"; 

    $message = "Your verification code is: $otp";
    $to_number = '94' . $phone;

    $url = "https://app.notify.lk/api/v1/send?" . http_build_query([
        'user_id' => $user_id, 'api_key' => $api_key, 'sender_id' => $sender_id,
        'to' => $to_number, 'message' => $message
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http_code == 200;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitize_input($_POST['phone']);

    if (empty($phone) || !preg_match('/^[0-9]{9}$/', $phone)) {
        $error = 'Please enter a valid 9-digit phone number.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $error = 'This number is already registered. <a href="login.php">Please login</a>.';
        } else {
            $otp = generate_otp();
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stmt = $pdo->prepare("INSERT INTO otp_logs (phone, otp_code, purpose, expires_at) VALUES (?, ?, 'registration', ?)");
            $stmt->execute([$phone, $otp, $expires_at]);

            if (send_otp_sms($phone, $otp)) {
                // SUCCESS: Store phone in session and REDIRECT to the verify page
                $_SESSION['registration_phone'] = $phone;
                header('Location: verify.php');
                exit;
            } else {
                $error = "Failed to send OTP. Please check the number and try again.";
            }
        }
    }
}

include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class='bx bx-mobile-alt text-primary' style="font-size: 3rem;"></i>
                        <h3 class="mt-3">Create Account</h3>
                        <p class="text-muted">Enter your mobile number to begin.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="phone" class="form-label">**Mobile Number**</label>
                            <div class="input-group">
                                <span class="input-group-text">+94</span>
                                <input type="tel" class="form-control" name="phone" id="phone" placeholder="712345678" required pattern="[0-9]{9}">
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                Send Verification Code
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="text-muted small">
                            Already have an account? <a href="login.php">Login here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>