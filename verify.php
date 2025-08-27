<?php
session_start();
require_once 'db.php'; // Your database connection file

// If the user hasn't started the process, send them back to the beginning.
if (!isset($_SESSION['registration_phone'])) {
    header('Location: register.php');
    exit;
}

$page_title = 'Verify OTP';
$error = '';
$success = '';
$phone = $_SESSION['registration_phone'];

// --- Helper Functions (Copy these from the previous answer) ---
function sanitize_input($data) { return htmlspecialchars(strip_tags(trim($data))); }
function generate_otp() { return mt_rand(100000, 999999); }
function send_otp_sms($phone, $otp) {
    // ⚠️ IMPORTANT: Replace with your actual credentials
    $api_key = "RH9L1weIpJJODyQkFfSe";
    $user_id = "29316";
    $sender_id = "NotifyDEMO";

    $message = "Your new verification code is: $otp";
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

    // -------------------- RESEND OTP --------------------
    if (isset($_POST['resend_otp'])) {
        $otp = generate_otp();
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Insert new OTP
        $stmt = $pdo->prepare("
            INSERT INTO otp_logs (phone, otp_code, purpose, expires_at, status) 
            VALUES (?, ?, 'registration', ?, 'sent')
        ");
        $stmt->execute([$phone, $otp, $expires_at]);

        // Send OTP via Notify.lk
        if (send_otp_sms($phone, $otp)) {
            $success = "A new OTP has been sent to +94$phone.";
        } else {
            $error = "Failed to resend OTP. Please try again shortly.";
        }
    }

    // -------------------- VERIFY OTP --------------------
    elseif (isset($_POST['verify_otp'])) {
        $entered_otp = sanitize_input($_POST['otp']);

        // Validate input
        if (empty($entered_otp) || !preg_match('/^[0-9]{6}$/', $entered_otp)) {
            $error = 'Please enter a valid 6-digit OTP code.';
        } else {
            // Fetch the latest OTP for this phone
            $stmt = $pdo->prepare("
                SELECT id, expires_at 
                FROM otp_logs 
                WHERE phone = ? 
                AND otp_code = ? 
                AND status = 'sent' 
                AND purpose = 'registration'
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$phone, $entered_otp]);
            $otp_record = $stmt->fetch();

            if ($otp_record) {
                // Check if OTP has expired
                if (strtotime($otp_record['expires_at']) < time()) {
                    $error = 'OTP has expired. Please resend a new code.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        // Insert user account
                        $stmt_user = $pdo->prepare("
                            INSERT INTO users (phone, phone_verified, otp_code, otp_expires) 
                            VALUES (?, 1, NULL, NULL)
                        ");
                        $stmt_user->execute([$phone]);
                        $user_id = $pdo->lastInsertId();

                        // Mark OTP as verified
                        $stmt_otp = $pdo->prepare("UPDATE otp_logs SET status='verified' WHERE id=?");
                        $stmt_otp->execute([$otp_record['id']]);

                        $pdo->commit();

                        // Log the user in
                        $_SESSION['user_id'] = $user_id;
                        unset($_SESSION['registration_phone']);
                        header('Location: dashboard.php');
                        exit;

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Registration failed: ' . $e->getMessage();
                    }
                }
            } else {
                $error = 'Invalid OTP. Please try again or resend a new code.';
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
                        <i class='bx bx-message-square-check text-success' style="font-size: 3rem;"></i>
                        <h3 class="mt-3">Verify Your Number</h3>
                        <p class="text-muted">An OTP was sent to **+94<?php echo htmlspecialchars($phone); ?>**</p>
                    </div>

                    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="otp" class="form-label">**Verification Code**</label>
                            <input type="text" class="form-control text-center fs-5" id="otp" name="otp" required autofocus pattern="[0-9]{6}" maxlength="6">
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="verify_otp" class="btn btn-success">Verify & Create Account</button>
                            <button type="submit" name="resend_otp" class="btn btn-outline-secondary btn-sm" formnovalidate>Resend Code</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>