<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo isset($page_title) 
            ? htmlspecialchars($page_title) . ' - ClassiFind' 
            : 'ClassiFind - Lanka Classified Ads'; ?>
    </title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Boxicons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        /* Gradient Navbar + Buttons */
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
        }

        /* Post & Story Cards */
        .post-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-radius: 10px;
            overflow: hidden;
        }
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .story-card {
            transition: transform 0.2s;
        }
        .story-card:hover {
            transform: scale(1.05);
        }

        /* Animation */
        .animate-pop {
            animation: pop 2s infinite;
        }
        @keyframes pop {
            0%, 50%, 100% { transform: scale(1); }
            25% { transform: scale(1.05); }
        }

        /* Text Line Clamp */
        .line-clamp-1 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 1;
        }
        .line-clamp-2 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        /* Ad Styles */
        .vip-ad { border-left: 4px solid #ffd700; }
        .super-ad { border-left: 4px solid #ff6b35; }
        .normal-ad { border-left: 4px solid #28a745; }

        /* Navbar */
        .navbar-toggler {
            border: none;
        }
        .navbar-toggler:focus {
            box-shadow: none;
        }
    </style>
</head>
<body class="bg-light">

<!-- Navigation -->
<nav class="navbar navbar-expand-lg bg-gradient-primary navbar-dark sticky-top shadow">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="index.php">
            SL HOT GIRL
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Search Form (Desktop) -->
            <div class="mx-auto d-none d-lg-block" style="max-width: 400px;">
                <form class="d-flex" action="search.php" method="GET">
                    <input class="form-control" type="search" name="q" placeholder="Search ads..."
                           value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button class="btn btn-light ms-2" type="submit">
                        <i class="bx bx-search"></i>
                    </button>
                </form>
            </div>
            
            <!-- Navigation Links -->
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="post-ad.php">
                            <i class="bx bx-plus-circle me-1"></i>Post Ad
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bx bx-user me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bx bx-log-out me-1"></i>Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bx bx-log-in me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="bx bx-user-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Mobile Search -->
<div class="d-lg-none bg-white border-bottom py-2">
    <div class="container">
        <form class="d-flex" action="search.php" method="GET">
            <input class="form-control" type="search" name="q" placeholder="Search ads..."
                   value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            <button class="btn btn-outline-primary ms-2" type="submit">
                <i class="bx bx-search"></i>
            </button>
        </form>
    </div>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['logout']) && $_GET['logout'] == '1'): ?>
<div class="container mt-3">
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bx bx-check-circle me-2"></i>You have been logged out successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['login_required']) && $_GET['login_required'] == '1'): ?>
<div class="container mt-3">
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bx bx-info-circle me-2"></i>Please login to continue.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>
