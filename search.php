<?php
require_once 'db.php';

$page_title = 'Search Ads';

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

// Get search parameters
$query = trim($_GET['q'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$district_id = (int)($_GET['district'] ?? 0);
$min_price = (float)($_GET['min_price'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);
$ad_type = $_GET['ad_type'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build search query
$search_conditions = ["a.status = 'approved'"];
$params = [];

if (!empty($query)) {
    $search_conditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $search_param = '%' . $query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_id > 0) {
    $search_conditions[] = "a.category_id = ?";
    $params[] = $category_id;
}

if ($district_id > 0) {
    $search_conditions[] = "a.district_id = ?";
    $params[] = $district_id;
}

if ($min_price > 0) {
    $search_conditions[] = "a.price >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $search_conditions[] = "a.price <= ?";
    $params[] = $max_price;
}

if (!empty($ad_type) && in_array($ad_type, ['normal', 'super', 'vip'])) {
    $search_conditions[] = "a.ad_type = ?";
    $params[] = $ad_type;
}

$where_clause = implode(' AND ', $search_conditions);

// Search ads
$search_query = "SELECT a.*, c.name as category_name, d.name as district_name 
                 FROM ads a 
                 LEFT JOIN categories c ON a.category_id = c.id 
                 LEFT JOIN districts d ON a.district_id = d.id 
                 WHERE $where_clause
                 ORDER BY 
                 CASE WHEN a.ad_type = 'vip' THEN 1 
                      WHEN a.ad_type = 'super' THEN 2 
                      ELSE 3 END,
                 a.created_at DESC 
                 LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($search_query);
$stmt->execute($params);
$ads = $stmt->fetchAll();

// Get total count
$count_query = "SELECT COUNT(*) as total 
                FROM ads a 
                WHERE $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_result = $stmt->fetch();
$total_ads = $total_result['total'];
$total_pages = ceil($total_ads / $per_page);

// Fetch categories and districts for filters
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$districts = $pdo->query("SELECT * FROM districts ORDER BY name")->fetchAll();

include 'header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Search Filters Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bx bx-filter me-2"></i>Search Filters
                    </h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="search.php" id="searchForm">
                        <!-- Search Query -->
                        <div class="mb-3">
                            <label for="q" class="form-label">Keywords</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="q" 
                                   name="q" 
                                   placeholder="Enter keywords..." 
                                   value="<?php echo htmlspecialchars($query); ?>">
                        </div>
                        
                        <!-- Category Filter -->
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- District Filter -->
                        <div class="mb-3">
                            <label for="district" class="form-label">District</label>
                            <select class="form-select" id="district" name="district">
                                <option value="">All Districts</option>
                                <?php foreach ($districts as $dist): ?>
                                    <option value="<?php echo $dist['id']; ?>" 
                                            <?php echo $district_id == $dist['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dist['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Price Range -->
                        <div class="mb-3">
                            <label class="form-label">Price Range (LKR)</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="number" 
                                           class="form-control form-control-sm" 
                                           name="min_price" 
                                           placeholder="Min" 
                                           value="<?php echo $min_price > 0 ? $min_price : ''; ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" 
                                           class="form-control form-control-sm" 
                                           name="max_price" 
                                           placeholder="Max" 
                                           value="<?php echo $max_price > 0 ? $max_price : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ad Type Filter -->
                        <div class="mb-3">
                            <label for="ad_type" class="form-label">Ad Type</label>
                            <select class="form-select" id="ad_type" name="ad_type">
                                <option value="">All Types</option>
                                <option value="normal" <?php echo $ad_type == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="super" <?php echo $ad_type == 'super' ? 'selected' : ''; ?>>Super</option>
                                <option value="vip" <?php echo $ad_type == 'vip' ? 'selected' : ''; ?>>VIP</option>
                            </select>
                        </div>
                        
                        <!-- Search Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-gradient text-white">
                                <i class="bx bx-search me-2"></i>Search
                            </button>
                            <a href="search.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bx bx-refresh me-2"></i>Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Search Results -->
        <div class="col-lg-9">
            <!-- Search Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="mb-2">
                        <i class="bx bx-search me-2"></i>Search Results
                    </h4>
                    
                    <?php if (!empty($query) || $category_id || $district_id || $min_price || $max_price || !empty($ad_type)): ?>
                        <div class="mb-3">
                            <strong>Search criteria:</strong>
                            <div class="mt-2">
                                <?php if (!empty($query)): ?>
                                    <span class="badge bg-primary me-2">Keywords: "<?php echo htmlspecialchars($query); ?>"</span>
                                <?php endif; ?>
                                
                                <?php if ($category_id): ?>
                                    <?php
                                    $cat_name = '';
                                    foreach ($categories as $cat) {
                                        if ($cat['id'] == $category_id) {
                                            $cat_name = $cat['name'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="badge bg-info me-2">Category: <?php echo htmlspecialchars($cat_name); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($district_id): ?>
                                    <?php
                                    $dist_name = '';
                                    foreach ($districts as $dist) {
                                        if ($dist['id'] == $district_id) {
                                            $dist_name = $dist['name'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="badge bg-success me-2">District: <?php echo htmlspecialchars($dist_name); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($min_price > 0 || $max_price > 0): ?>
                                    <span class="badge bg-warning text-dark me-2">
                                        Price: Rs. <?php echo $min_price > 0 ? number_format($min_price) : '0'; ?> - 
                                        <?php echo $max_price > 0 ? number_format($max_price) : '∞'; ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($ad_type)): ?>
                                    <span class="badge bg-secondary me-2">Type: <?php echo ucfirst($ad_type); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-0">
                        Found <?php echo number_format($total_ads); ?> ads
                        <?php if ($page > 1): ?>
                            (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- Search Results -->
<?php if (empty($ads)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bx bx-search text-muted" style="font-size: 4rem;"></i>
            <h5 class="text-muted mt-3">No ads found</h5>
            <p class="text-muted">Try adjusting your search criteria or browse all categories</p>
            <a href="index.php" class="btn btn-gradient text-white">
                <i class="bx bx-home me-2"></i>Browse All Ads
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <?php foreach ($ads as $ad): ?>
            <div class="col">
                <a href="view-ad.php?id=<?php echo $ad['id']; ?>" class="card post-card h-100 text-decoration-none text-dark">
                    <div class="row g-0">
                        <div class="col-md-5">
                            <?php if ($ad['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" 
                                     class="img-fluid rounded-start h-100 object-fit-cover" 
                                     alt="<?php echo htmlspecialchars($ad['title']); ?>"
                                     style="height: 180px; object-fit: cover;">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center bg-light h-100 rounded-start" style="height: 180px;">
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
                                <?php if ($ad['price'] > 0): ?>
                                    <div class="fw-bold text-success mb-2">
                                        Rs. <?php echo number_format($ad['price']); ?>
                                    </div>
                                <?php endif; ?>
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
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);

                for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>