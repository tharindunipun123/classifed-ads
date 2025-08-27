<?php
require_once 'db.php';

// Get category ID
$category_id = (int)($_GET['id'] ?? 0);
if (!$category_id) {
    header('Location: index.php');
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

function format_views($views) {
    if ($views >= 1000000) {
        return number_format($views/1000000, 1) . 'M';
    } elseif ($views >= 1000) {
        return number_format($views/1000, 1) . 'K';
    }
    return number_format($views);
}

// Fetch category details
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND status = 'active'");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Sorting options
$sort = $_GET['sort'] ?? 'latest';
$order_by = match($sort) {
    'oldest' => 'a.created_at ASC',
    'price_low' => 'a.price ASC',
    'price_high' => 'a.price DESC',
    'popular' => 'a.views DESC',
    default => 'a.created_at DESC'
};

// Fetch ads in this category
$ads_query = "SELECT a.*, c.name as category_name, d.name as district_name 
              FROM ads a 
              LEFT JOIN categories c ON a.category_id = c.id 
              LEFT JOIN districts d ON a.district_id = d.id 
              WHERE a.category_id = ? AND a.status = 'approved' 
              ORDER BY 
              CASE WHEN a.ad_type = 'vip' THEN 1 
                   WHEN a.ad_type = 'super' THEN 2 
                   ELSE 3 END,
              $order_by
              LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($ads_query);
$stmt->execute([$category_id]);
$ads = $stmt->fetchAll();

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM ads WHERE category_id = ? AND status = 'approved'";
$stmt = $pdo->prepare($count_query);
$stmt->execute([$category_id]);
$total_result = $stmt->fetch();
$total_ads = $total_result['total'];
$total_pages = ceil($total_ads / $per_page);

// Fetch all categories for sidebar
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

$page_title = htmlspecialchars($category['name']) . ' Ads';
include 'header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bx bx-category me-2"></i>All Categories
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($categories as $cat): ?>
                            <a href="category.php?id=<?php echo $cat['id']; ?>" 
                               class="list-group-item list-group-item-action d-flex align-items-center <?php echo $cat['id'] == $category_id ? 'active' : ''; ?>">
                                
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-body text-center">
                    <h6>Post Your Ad</h6>
                    <p class="text-muted small">Reach thousands of potential buyers</p>
                    <a href="post-ad.php" class="btn btn-gradient text-white btn-sm">
                        <i class="bx bx-plus me-1"></i>Post Ad Now
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Category Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="<?php echo $category['icon']; ?> display-6 text-primary me-3"></i>
                            <div>
                                <h2 class="mb-1"><?php echo htmlspecialchars($category['name']); ?></h2>
                                <p class="text-muted mb-0">
                                    <?php echo number_format($total_ads); ?> ads available
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sort and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <label class="form-label me-2 mb-0">Sort by:</label>
                                <select class="form-select form-select-sm" style="width: auto;" onchange="changeSorting(this.value)">
                                    <option value="latest" <?php echo $sort == 'latest' ? 'selected' : ''; ?>>Latest</option>
                                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                                    <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end mt-2 mt-md-0">
                            <span class="text-muted">
                                Showing <?php echo min($per_page, count($ads)); ?> of <?php echo number_format($total_ads); ?> ads
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ads Grid -->
            <?php if (empty($ads)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bx bx-folder-open text-muted" style="font-size: 4rem;"></i>
                        <h5 class="text-muted mt-3">No ads found in this category</h5>
                        <p class="text-muted">Be the first to post an ad in <?php echo htmlspecialchars($category['name']); ?></p>
                        <a href="post-ad.php" class="btn btn-gradient text-white">
                            <i class="bx bx-plus me-2"></i>Post First Ad
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($ads as $ad): ?>
                        <div class="col-md-6 col-xl-4 mb-4">
                            <div class="card post-card h-100 <?php echo $ad['ad_type']; ?>-ad">
                                <div class="position-relative">
                                    <?php if ($ad['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" 
                                             class="card-img-top" 
                                             style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" 
                                             style="height: 200px;">
                                            <i class="bx bx-image text-muted" style="font-size: 3rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <span class="badge 
                                               <?php echo $ad['ad_type'] == 'vip' ? 'bg-warning text-dark' : 
                                                         ($ad['ad_type'] == 'super' ? 'bg-danger text-white' : 'bg-success text-white'); ?>">
                                            <?php echo ucfirst($ad['ad_type']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <h6 class="card-title line-clamp-1">
                                        <a href="view-ad.php?id=<?php echo $ad['id']; ?>" 
                                           class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($ad['title']); ?>
                                        </a>
                                    </h6>
                                    
                                    <p class="card-text text-muted line-clamp-2 small">
                                        <?php echo htmlspecialchars(substr($ad['description'], 0, 100)); ?>
                                        <?php echo strlen($ad['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                    
                                    <?php if ($ad['price'] > 0): ?>
                                        <div class="fw-bold text-success mb-2">
                                            Rs. <?php echo number_format($ad['price']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-muted small">
                                            <i class="bx bx-map me-1"></i>
                                            <?php echo htmlspecialchars($ad['district_name']); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="bx bx-show me-1"></i>
                                            <?php echo format_views($ad['views']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-muted small mt-1">
                                        <?php echo format_time_ago($ad['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>">
                                        <i class="bx bx-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>">
                                        <i class="bx bx-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function changeSorting(sortValue) {
    const url = new URL(window.location);
    url.searchParams.set('sort', sortValue);
    url.searchParams.set('page', '1'); // Reset to first page
    window.location.href = url.toString();
}
</script>

<?php include 'footer.php'; ?>