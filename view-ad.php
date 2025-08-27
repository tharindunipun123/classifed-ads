<?php
require_once 'db.php';

// Get ad ID
$ad_id = (int)($_GET['id'] ?? 0);
if (!$ad_id) {
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

// Fetch ad details with category and district
$stmt = $pdo->prepare("SELECT a.*, c.name as category_name, d.name as district_name, u.phone as user_phone 
                       FROM ads a 
                       LEFT JOIN categories c ON a.category_id = c.id 
                       LEFT JOIN districts d ON a.district_id = d.id 
                       LEFT JOIN users u ON a.user_id = u.id
                       WHERE a.id = ? AND a.status = 'approved'");
$stmt->execute([$ad_id]);
$ad = $stmt->fetch();

if (!$ad) {
    header('Location: index.php');
    exit;
}

// Update view count
$stmt = $pdo->prepare("UPDATE ads SET views = views + 1 WHERE id = ?");
$stmt->execute([$ad_id]);

// Get similar ads from same category
$stmt = $pdo->prepare("SELECT a.*, c.name as category_name, d.name as district_name 
                       FROM ads a 
                       LEFT JOIN categories c ON a.category_id = c.id 
                       LEFT JOIN districts d ON a.district_id = d.id 
                       WHERE a.category_id = ? AND a.id != ? AND a.status = 'approved' 
                       ORDER BY a.created_at DESC 
                       LIMIT 6");
$stmt->execute([$ad['category_id'], $ad_id]);
$similar_ads = $stmt->fetchAll();

$page_title = htmlspecialchars($ad['title']);
include 'header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Main Ad Content -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <!-- Ad Type Badge -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge fs-6 
                            <?php echo $ad['ad_type'] == 'vip' ? 'bg-warning text-dark' : 
                                      ($ad['ad_type'] == 'super' ? 'bg-danger' : 'bg-success'); ?>">
                            <?php echo ucfirst($ad['ad_type']); ?> Ad
                        </span>
                        <div class="text-muted">
                            <i class='bx bx-show me-1'></i>
                            <?php echo number_format($ad['views']); ?> views
                        </div>
                    </div>
                    
                    <!-- Title -->
                    <h1 class="h3 mb-3"><?php echo htmlspecialchars($ad['title']); ?></h1>
                    
                    <!-- Ad Image -->
                    <?php if ($ad['image_url']): ?>
                        <div class="mb-4">
                            <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" 
                                 class="img-fluid rounded" 
                                 style="max-height: 400px; width: 100%; object-fit: cover;">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Ad Details -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <i class='bx bx-category me-2 text-primary'></i>
                                <strong>Category:</strong> <?php echo htmlspecialchars($ad['category_name']); ?>
                            </p>
                            <p class="mb-2">
                                <i class='bx bx-map me-2 text-primary'></i>
                                <strong>Location:</strong> <?php echo htmlspecialchars($ad['district_name']) . ', ' . htmlspecialchars($ad['location']); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($ad['price'] > 0): ?>
                                <p class="mb-2">
                                    <i class='bx bx-money me-2 text-success'></i>
                                    <strong>Price:</strong> Rs. <?php echo number_format($ad['price']); ?>
                                </p>
                            <?php endif; ?>
                            <p class="mb-2">
                                <i class='bx bx-time me-2 text-muted'></i>
                                <strong>Posted:</strong> <?php echo format_time_ago($ad['created_at']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <?php if ($ad['description']): ?>
                        <div class="mb-4">
                            <h5>Description</h5>
                            <p class="text-muted" style="white-space: pre-line;"><?php echo htmlspecialchars($ad['description']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class='bx bx-phone me-2'></i>Contact Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h4 class="text-primary">+94<?php echo htmlspecialchars($ad['phone']); ?></h4>
                    </div>
                    
                    <!-- Call Button -->
                    <div class="d-grid gap-2">
                        <a href="tel:+94<?php echo $ad['phone']; ?>" class="btn btn-primary">
                            <i class='bx bx-phone me-2'></i>Call Now
                        </a>
                        
                        <!-- Messaging Apps -->
                        <?php if ($ad['whatsapp']): ?>
                            <a href="https://wa.me/94<?php echo $ad['phone']; ?>" 
                               class="btn btn-success" target="_blank">
                                <i class='bx bxl-whatsapp me-2'></i>WhatsApp
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($ad['telegram']): ?>
                            <a href="https://t.me/+94<?php echo $ad['phone']; ?>" 
                               class="btn btn-primary" target="_blank">
                                <i class='bx bxl-telegram me-2'></i>Telegram
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($ad['viber']): ?>
                            <a href="viber://chat?number=+94<?php echo $ad['phone']; ?>" 
                               class="btn btn-secondary">
                                <i class='bx bx-phone me-2'></i>Viber
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($ad['imo']): ?>
                            <button class="btn btn-info">
                                <i class='bx bx-video me-2'></i>IMO Available
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <!-- Safety Notice -->
                    <div class="alert alert-warning alert-sm">
                        <small>
                            <i class='bx bx-shield me-1'></i>
                            Be cautious when meeting strangers. Meet in public places and inform someone about your plans.
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Share -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class='bx bx-share me-2'></i>Share This Ad
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="shareAd()">
                            <i class='bx bx-share'></i>
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="copyLink()">
                            <i class='bx bx-copy'></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Similar Ads -->
    <?php if (!empty($similar_ads)): ?>
    <div class="mt-5">
        <h5 class="mb-4">Similar Ads in <?php echo htmlspecialchars($ad['category_name']); ?></h5>
        <div class="row">
            <?php foreach ($similar_ads as $similar_ad): ?>
                <div class="col-md-4 col-lg-2 mb-3">
                    <div class="card h-100">
                        <a href="view-ad.php?id=<?php echo $similar_ad['id']; ?>" class="text-decoration-none">
                            <?php if ($similar_ad['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($similar_ad['image_url']); ?>" 
                                     class="card-img-top" 
                                     style="height: 120px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 120px;">
                                    <i class='bx bx-image text-muted'></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body p-2">
                                <h6 class="card-title small line-clamp-1 text-dark">
                                    <?php echo htmlspecialchars($similar_ad['title']); ?>
                                </h6>
                                <p class="text-muted small mb-0">
                                    <?php echo htmlspecialchars($similar_ad['district_name']); ?>
                                </p>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function shareAd() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo htmlspecialchars($ad['title']); ?>',
            text: 'Check out this ad on ClassiFind',
            url: window.location.href
        });
    } else {
        copyLink();
    }
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(function() {
        alert('Link copied to clipboard!');
    });
}
</script>

<?php include 'footer.php'; ?>