<?php
session_start();
require_once __DIR__ . '/../backend/config.php';

function tableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $check = $conn->query("SHOW TABLES LIKE '$safe'");
    return $check && $check->num_rows > 0;
}

$galleryItems = [];

if (tableExists($conn, 'gallery_items')) {
    $query = "SELECT g.*, e.title AS event_title FROM gallery_items g LEFT JOIN events e ON g.event_id = e.id WHERE g.status = 'published' ORDER BY g.created_at DESC";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $galleryItems[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - Event Ease</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout.css?v=2">
    <style>
        :root {
            --primary: #5a2ca0;
            --primary-dark: #431f75;
            --bg: #f5f3ff;  
        }
        * {
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            margin: 0;
            background: var(--bg);
            padding-top: 90px;
        }
        .gallery-hero {
            text-align: center;
            padding: 80px 20px 40px;
        }
        .gallery-hero h1 {
            font-size: 42px;
            color: var(--primary-dark);
            margin-bottom: 10px;
        }
        .gallery-grid {
            padding: 0 20px 60px;
        }
        .gallery-grid .row {
            display: flex;
            flex-wrap: wrap;
        }
        .gallery-grid .row > [class*='col-'] {
            display: flex;
            flex-direction: column;
        }
        .gallery-card {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(90,44,160,0.12);
            margin-bottom: 30px;
            background: #fff;
            border: 1px solid rgba(90,44,160,0.08);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .gallery-card img {
            width: 100%;
            height: 240px;
            object-fit: cover;
            object-position: center;
            cursor: pointer;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        .gallery-card img:hover {
            transform: scale(1.02);
        }
        .gallery-card .content {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            min-height: 140px;
        }
        .gallery-card h5 {
            margin-bottom: 8px;
            color: var(--primary-dark);
            font-size: 18px;
            line-height: 1.3;
        }
        .gallery-card .description-container {
            flex-grow: 1;
            position: relative;
            margin-bottom: 12px;
        }
        .gallery-card .description-text {
            color: #606060;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gallery-card .description-text.expanded {
            display: block;
            -webkit-line-clamp: unset;
        }
        .gallery-card .see-more-btn {
            color: var(--primary);
            background: none;
            border: none;
            padding: 0;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            text-decoration: none;
            display: inline-block;
        }
        .gallery-card .see-more-btn:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        .gallery-card small {
            color: #a05fff;
            font-weight: 600;
            font-size: 12px;
            margin-bottom: 8px;
            display: block;
        }
        
        /* Lightbox Modal */
        .lightbox-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            padding-top: 60px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
        }
        .lightbox-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 1000px;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 4px;
            animation: zoom 0.3s;
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 2001;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
        @keyframes zoom {
            from {transform:scale(0.1)}
            to {transform:scale(1)}
        }
        @media only screen and (max-width: 700px){
            .lightbox-content {
                width: 95%;
            }
        }
        
        @media (max-width: 768px) {
            .gallery-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="gallery-hero">
    <small class="text-uppercase text-muted">Visual Stories</small>
    <h1>Moments crafted by Event Ease</h1>
    <p class="text-muted">Explore décor inspiration, behind-the-scenes team action and guest experiences captured at our signature events.</p>
</section>

<section class="gallery-grid container">
    <div class="row">
        <?php foreach ($galleryItems as $item): ?>
            <?php
                $image = !empty($item['image_path']) && !preg_match('/^https?:/i', $item['image_path'])
                    ? "../" . ltrim($item['image_path'], './')
                    : ($item['image_path'] ?? '');
                if (!$image) {
                    $image = "https://images.unsplash.com/photo-1472653431158-6364773b2a56?auto=format&fit=crop&w=900&q=60";
                }
            ?>
            <div class="col-lg-4 col-md-6">
                <article class="gallery-card">
                    <img src="<?= htmlspecialchars($image); ?>" alt="<?= htmlspecialchars($item['title'] ?? 'Gallery image'); ?>" onclick="openModal(this.src)">
                    <div class="content">
                        <small><?= htmlspecialchars($item['event_title'] ?? 'Event Highlight'); ?></small>
                        <h5><?= htmlspecialchars($item['title'] ?? 'Showcase'); ?></h5>
                        <div class="description-container">
                            <?php 
                                $description = htmlspecialchars($item['description'] ?? 'Captured moment from one of our curated celebrations.');
                                $descriptionId = 'desc-' . $item['id'];
                                $hasLongDescription = strlen($description) > 120;
                            ?>
                            <p class="description-text" id="<?= $descriptionId; ?>"><?= $description; ?></p>
                            <?php if ($hasLongDescription): ?>
                                <button class="see-more-btn" onclick="toggleDescription('<?= $descriptionId; ?>', this)">
                                    <span class="see-more-text">See More</span>
                                    <span class="see-less-text" style="display: none;">See Less</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Lightbox Modal -->
<div id="imageModal" class="lightbox-modal">
  <span class="close-btn" onclick="closeModal()">&times;</span>
  <img class="lightbox-content" id="img01">
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/layout.js?v=2"></script>
<script>
    function openModal(src) {
        var modal = document.getElementById("imageModal");
        var modalImg = document.getElementById("img01");
        modal.style.display = "block";
        modalImg.src = src;
        document.body.style.overflow = 'hidden'; // Disable scroll
    }
    
    function closeModal() {
        var modal = document.getElementById("imageModal");
        modal.style.display = "none";
        document.body.style.overflow = 'auto'; // Enable scroll
    }

    // Close on click outside
    window.onclick = function(event) {
        var modal = document.getElementById("imageModal");
        if (event.target == modal) {
            closeModal();
        }
    }
    
    // Close on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });
    
    // Toggle description expand/collapse
    function toggleDescription(descId, btn) {
        const descElement = document.getElementById(descId);
        const seeMoreText = btn.querySelector('.see-more-text');
        const seeLessText = btn.querySelector('.see-less-text');
        
        if (descElement.classList.contains('expanded')) {
            descElement.classList.remove('expanded');
            seeMoreText.style.display = 'inline';
            seeLessText.style.display = 'none';
        } else {
            descElement.classList.add('expanded');
            seeMoreText.style.display = 'none';
            seeLessText.style.display = 'inline';
        }
    }
</script>
</body>
</html>

