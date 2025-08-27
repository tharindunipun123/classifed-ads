<?php
session_start();
require_once 'db.php'; 

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// ========== API ENDPOINTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Generate OTP
    function generate_otp() {
        return sprintf("%06d", mt_rand(100000, 999999));
    }

    // Send OTP via Notify.lk
    function send_otp_sms($phone, $otp) {
        $api_key   = "RH9L1weIpJJODyQkFfSe";  // Your API key
        $user_id   = "29316";                 // Your user ID
        $sender_id = "NotifyDEMO";            // Your approved sender ID

        $message   = "Your OTP code is $otp. Valid for 10 minutes.";
        $to_number = "94" . $phone;

        $url = "https://app.notify.lk/api/v1/send?" . http_build_query([
            'user_id'   => $user_id,
            'api_key'   => $api_key,
            'sender_id' => $sender_id,
            'to'        => $to_number,
            'message'   => $message
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res  = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("Notify.lk CURL Error: $err");
            return false;
        }

        if ($http === 200) {
            $data = json_decode($res, true);
            return isset($data['status']) && $data['status'] === 'success';
        }

        return false;
    }

    $action = $_GET['action'];

    // ----- SEND OTP -----
    if ($action === 'send_otp') {
        $data = json_decode(file_get_contents("php://input"), true);
        $phone = preg_replace('/\D/', '', $data['phone'] ?? '');

        if (!$phone || !preg_match('/^[0-9]{9}$/', $phone)) {
            echo json_encode(["status" => "error", "msg" => "Enter valid 9-digit number"]);
            exit;
        }

        // Check user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND phone_verified = 1");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(["status" => "error", "msg" => "Number not registered"]);
            exit;
        }

        $otp = generate_otp();
        $_SESSION['login_phone'] = $phone;
        $_SESSION['login_otp']   = $otp;
        $_SESSION['otp_expiry']  = time() + 600; // 10 min

        if (send_otp_sms($phone, $otp)) {
            echo json_encode(["status" => "ok", "msg" => "OTP sent to +94$phone"]);
        } else {
            echo json_encode(["status" => "error", "msg" => "Failed to send OTP"]);
        }
        exit;
    }

    // ----- VERIFY OTP -----
    if ($action === 'verify_otp') {
        $data = json_decode(file_get_contents("php://input"), true);
        $entered = $data['otp'] ?? '';

        if (!isset($_SESSION['login_phone'], $_SESSION['login_otp'], $_SESSION['otp_expiry'])) {
            echo json_encode(["status" => "error", "msg" => "Session expired"]);
            exit;
        }

        if (time() > $_SESSION['otp_expiry']) {
            echo json_encode(["status" => "error", "msg" => "OTP expired"]);
            exit;
        }

        if ($entered != $_SESSION['login_otp']) {
            echo json_encode(["status" => "error", "msg" => "Invalid OTP"]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND phone_verified = 1");
        $stmt->execute([$_SESSION['login_phone']]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(["status" => "error", "msg" => "User not found"]);
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        unset($_SESSION['login_otp'], $_SESSION['otp_expiry']);

        echo json_encode(["status" => "ok", "msg" => "Login successful"]);
        exit;
    }

    echo json_encode(["status" => "error", "msg" => "Invalid request"]);
    exit;
}

include 'header.php';
?>


<div class="d-flex align-items-center justify-content-center vh-100 bg-light">
  <div class="card shadow p-4" style="max-width:400px;width:100%;">
    <div class="text-center mb-3">
      <i class="bx bx-log-in text-primary" style="font-size:2.5rem;"></i>
      <h4 class="mt-2">Login</h4>
      <p class="text-muted small">Login using your mobile number</p>
    </div>

    <!-- Step 1 -->
    <div id="step1">
      <label class="form-label">Mobile Number (+94)</label>
      <input type="text" id="phone" class="form-control" placeholder="771234567">
      <button class="btn btn-primary w-100 mt-3" onclick="sendOTP()" id="sendBtn">
        <i class="bx bx-send me-1"></i> Send OTP
      </button>
    </div>

    <!-- Step 2 -->
    <div id="step2" style="display:none;">
      <div class="alert alert-info py-2 small mb-3">
        <i class="bx bx-info-circle me-1"></i> OTP sent. Don’t refresh browser.
      </div>
      <input type="text" id="otp" maxlength="6" class="form-control text-center fs-4" placeholder="000000">
      <button class="btn btn-success w-100 mt-3" onclick="verifyOTP()" id="verifyBtn">
        <i class="bx bx-check-circle me-1"></i> Verify & Login
      </button>
    </div>

    <div id="msg" class="mt-3 text-center small"></div>

    <div class="text-center mt-3">
      <p class="text-muted small">Don’t have an account? 
        <a href="register.php">Register here</a>
      </p>
    </div>
  </div>
</div>

<script>
async function sendOTP() {
  let phone = document.getElementById("phone").value.trim();
  let btn = document.getElementById("sendBtn");
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
  btn.disabled = true;

  let res = await fetch("login.php?action=send_otp", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({phone})
  });
  let data = await res.json();
  document.getElementById("msg").innerHTML = data.msg;
  btn.innerHTML = '<i class="bx bx-send me-1"></i> Send OTP';
  btn.disabled = false;

  if (data.status === "ok") {
    document.getElementById("step1").style.display = "none";
    document.getElementById("step2").style.display = "block";
  }
}

async function verifyOTP() {
  let otp = document.getElementById("otp").value.trim();
  let btn = document.getElementById("verifyBtn");
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
  btn.disabled = true;

  let res = await fetch("login.php?action=verify_otp", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({otp})
  });
  let data = await res.json();
  document.getElementById("msg").innerHTML = data.msg;

  if (data.status === "ok") {
    window.location.href = "dashboard.php";
  } else {
    btn.innerHTML = '<i class="bx bx-check-circle me-1"></i> Verify & Login';
    btn.disabled = false;
  }
}
</script>

<?php include 'footer.php'; ?>

