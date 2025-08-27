<?php
require_once 'db.php';

$page_title = 'Dashboard';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Helper functions
function format_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's ads
$stmt = $pdo->prepare("SELECT a.*, c.name as category_name, d.name as district_name 
                       FROM ads a 
                       LEFT JOIN categories c ON a.category_id = c.id 
                       LEFT JOIN districts d ON a.district_id = d.id 
                       WHERE a.user_id = ? 
                       ORDER BY a.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$user_ads = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_ads,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_ads,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_ads,
    SUM(views) as total_views,
    SUM(likes) as total_likes
    FROM ads WHERE user_id = ?";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

include 'header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class='bx bx-user-circle text-primary' style="font-size: 4rem;"></i>
                    <h5 class="mt-3"><?php echo 'User'; ?></h5>
                    <p class="text-muted">+94<?php echo htmlspecialchars($user['phone']); ?></p>
                    <div class="d-grid gap-2">
                        <a href="post-ad.php" class="btn btn-gradient text-white">
                            <i class='bx bx-plus-circle me-2'></i>Post New Ad
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm">
                            <i class='bx bx-log-out me-2'></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class='bx bx-collection display-6'></i>
                            <h4 class="mt-2"><?php echo $stats['total_ads']; ?></h4>
                            <p class="mb-0">Total Ads</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <i class='bx bx-time display-6'></i>
                            <h4 class="mt-2"><?php echo $stats['pending_ads']; ?></h4>
                            <p class="mb-0">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class='bx bx-check-circle display-6'></i>
                            <h4 class="mt-2"><?php echo $stats['approved_ads']; ?></h4>
                            <p class="mb-0">Approved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class='bx bx-show display-6'></i>
                            <h4 class="mt-2"><?php echo number_format($stats['total_views']); ?></h4>
                            <p class="mb-0">Total Views</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User's Ads -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class='bx bx-list-ul me-2'></i>My Advertisements
                    </h5>
                    <a href="post-ad.php" class="btn btn-primary btn-sm">
                        <i class='bx bx-plus me-1'></i>New Ad
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($user_ads)): ?>
                        <div class="text-center py-5">
                            <i class='bx bx-folder-open text-muted' style="font-size: 4rem;"></i>
                            <h5 class="text-muted mt-3">No ads posted yet</h5>
                            <p class="text-muted">Start by posting your first advertisement</p>
                            <a href="post-ad.php" class="btn btn-gradient text-white">
                                <i class='bx bx-plus me-2'></i>Post Your First Ad
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ad</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Views</th>
                                        <th>Posted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_ads as $ad): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($ad['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" 
                                                             class="rounded me-3" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class='bx bx-image text-muted'></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($ad['title']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($ad['district_name']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($ad['category_name']); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php echo $ad['ad_type'] == 'vip' ? 'bg-warning text-dark' : 
                                                              ($ad['ad_type'] == 'super' ? 'bg-danger' : 'bg-success'); ?>">
                                                    <?php echo ucfirst($ad['ad_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge 
                                                    <?php echo $ad['status'] == 'approved' ? 'bg-success' : 
                                                              ($ad['status'] == 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                    <?php echo ucfirst($ad['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($ad['views']); ?></td>
                                            <td><?php echo format_time_ago($ad['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($ad['status'] == 'approved'): ?>
                                                        <a href="view-ad.php?id=<?php echo $ad['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           target="_blank">
                                                            <i class='bx bx-show'></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($ad['payment_status'] == 'pending'): ?>
                                                        <a href="payment.php?ad_id=<?php echo $ad['id']; ?>" 
                                                           class="btn btn-outline-warning btn-sm">
                                                            <i class='bx bx-credit-card'></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>