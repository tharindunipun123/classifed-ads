<?php
require_once 'db.php';

$page_title = 'Home';

// Helper functions for this page
function format_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . 'mo ago';
    
    return floor($time/31536000) . 'y ago';
}

function format_views($views) {
    if ($views >= 1000000) {
        return number_format($views/1000000, 1) . 'M';
    } elseif ($views >= 1000) {
        return number_format($views/1000, 1) . 'K';
    }
    return number_format($views);
}

// Fetch featured ads (VIP and Super ads)
$featured_ads_query = "SELECT a.*, c.name as category_name, d.name as district_name 
                      FROM ads a 
                      LEFT JOIN categories c ON a.category_id = c.id 
                      LEFT JOIN districts d ON a.district_id = d.id 
                      WHERE a.status = 'approved' AND a.ad_type IN ('vip', 'super')
                      ORDER BY 
                      CASE WHEN a.ad_type = 'vip' THEN 1 
                           WHEN a.ad_type = 'super' THEN 2 
                           ELSE 3 END,
                      a.created_at DESC 
                      LIMIT 5";

$featured_ads = $pdo->query($featured_ads_query)->fetchAll();

// Fetch regular ads with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$ads_query = "SELECT a.*, c.name as category_name, d.name as district_name 
             FROM ads a 
             LEFT JOIN categories c ON a.category_id = c.id 
             LEFT JOIN districts d ON a.district_id = d.id 
             WHERE a.status = 'approved' 
             ORDER BY 
             CASE WHEN a.ad_type = 'vip' THEN 1 
                  WHEN a.ad_type = 'super' THEN 2 
                  ELSE 3 END,
             a.created_at DESC 
             LIMIT $per_page OFFSET $offset";

$ads = $pdo->query($ads_query)->fetchAll();

// Get total count for pagination
$total_query = "SELECT COUNT(*) as total FROM ads WHERE status = 'approved'";
$total_result = $pdo->query($total_query)->fetch();
$total_ads = $total_result['total'];
$total_pages = ceil($total_ads / $per_page);

// Fetch categories for sidebar
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-3 d-none d-lg-block">
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class='bx bx-category me-2'></i>Categories
                    </h6>
                    <ul class="list-unstyled">
                        <?php foreach ($categories as $category): ?>
                            <li class="mb-2">
                                <a href="category.php?id=<?php echo $category['id']; ?>" 
                                   class="text-decoration-none d-flex align-items-center">
                                    
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class='bx bx-plus-circle me-2'></i>Quick Actions
                    </h6>
                    <div class="d-grid gap-2">
                        <a href="post-ad.php" class="btn btn-gradient text-white">
                            <i class='bx bx-plus me-2'></i>Post Free Ad
                        </a>
                        <a href="search.php" class="btn btn-outline-primary">
                            <i class='bx bx-search me-2'></i>Advanced Search
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <div class="text-center mb-4 p-4 bg-gradient-primary text-white rounded">
                <h1 class="display-6 fw-bold mb-2">SL Hot Girl</h1>
                <p class="lead mb-3">The #1 Lanka Ads Platform for Personal Ads in Sri Lanka</p>
                <a href="post-ad.php" class="btn btn-light btn-lg animate-pop">
                    <i class='bx bx-plus-circle me-2'></i>Post Your Ad Now
                </a>
            </div>
            
            <!-- <?php if (!empty($featured_ads)): ?>
                <div class="mb-4">
                    <h5 class="mb-3">
                        <i class='bx bx-star me-2 text-warning'></i>Featured Ads
                    </h5>
                    <div class="d-flex flex-row flex-nowrap overflow-auto pb-3">
                        <?php foreach ($featured_ads as $ad): ?>
                            <div class="col-4 col-sm-3 col-md-2 me-3">
                                <a href="view-ad.php?id=<?php echo $ad['id']; ?>" class="card story-card text-center h-100 text-decoration-none">
                                    <div class="story-card-image mb-2">
                                        <?php if ($ad['image_url']): ?>
                                            <img loading="lazy" src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="img-fluid rounded-circle p-2">
                                        <?php else: ?>
                                            <div class="d-flex align-items-center justify-content-center bg-light rounded-circle border" style="height: 80px; width: 80px; margin: 0 auto; padding: 10px;">
                                                <i class='bx bx-image text-muted' style="font-size: 2rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body p-0">
                                        <h6 class="card-title line-clamp-1 small mb-0">
                                            <?php echo htmlspecialchars($ad['title']); ?>
                                        </h6>
                                        <span class="badge <?php echo $ad['ad_type'] == 'vip' ? 'bg-warning text-dark' : 'bg-danger'; ?> mt-1">
                                            <?php echo ucfirst($ad['ad_type']); ?>
                                        </span>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?> -->
            
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class='bx bx-grid me-2'></i>Latest Ads
                    </h5>
                    <span class="text-muted">
                        Showing <?php echo min($per_page, count($ads)); ?> of <?php echo number_format($total_ads); ?> ads
                    </span>
                </div>

                <div class="row row-cols-1 row-cols-lg-2 g-4">
                    <?php foreach ($ads as $ad): ?>
                        <div class="col">
                            <a href="view-ad.php?id=<?php echo $ad['id']; ?>" class="card post-card h-100 text-decoration-none text-dark">
                                <div class="row g-0">
                                    <div class="col-md-5">
                                        <?php if ($ad['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" class="img-fluid rounded-start h-100 object-fit-cover" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                        <?php else: ?>
                                            <div class="d-flex align-items-center justify-content-center bg-light h-100 rounded-start">
                                                <i class='bx bx-image text-muted' style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="card-body d-flex flex-column h-100">
                                            <div class="d-flex flex-wrap gap-1 mb-2">
                                                <span class="badge <?php echo $ad['ad_type'] == 'vip' ? 'bg-warning text-dark' : ($ad['ad_type'] == 'super' ? 'bg-danger' : 'bg-success'); ?>">
                                                    <?php echo ucfirst($ad['ad_type']); ?>
                                                </span>
                                            </div>
                                            <h6 class="card-title line-clamp-1">
                                                <?php echo htmlspecialchars($ad['title']); ?>
                                            </h6>
                                            <p class="card-text text-muted line-clamp-2 small">
                                                <?php echo htmlspecialchars(substr($ad['description'], 0, 100)); ?>
                                                <?php echo strlen($ad['description']) > 100 ? '...' : ''; ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                                <div class="text-muted small d-flex flex-column">
                                                    <span><i class='bx bx-show me-1'></i> <?php echo format_views($ad['views']); ?> views</span>
                                                    <span><i class='bx bx-map me-1'></i> <?php echo htmlspecialchars($ad['district_name']); ?></span>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo format_time_ago($ad['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);

                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>