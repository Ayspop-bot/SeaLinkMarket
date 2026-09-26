<?php
/**
 * SeaLink Web Application
 * File: /config/db.php
 * Purpose: Creates the DB connection ($conn) and ensures schema integrity for Info Hub, Categories, and Forum.
 * Connected To: Any page/action that needs DB access
 * Uses: MySQL database sealink_database
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'sealink_database';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

// Ensure info_hub_tbl exists if not created
$check_info_hub = mysqli_query($conn, "SHOW TABLES LIKE 'info_hub_tbl'");
if (!$check_info_hub || mysqli_num_rows($check_info_hub) === 0) {
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS info_hub_tbl (
            info_hub_id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NULL,
            title VARCHAR(255) NOT NULL,
            type ENUM('Article','Research','Tutorial','Recipes','Forum') DEFAULT 'Article',
            category VARCHAR(100) DEFAULT 'Article',
            content TEXT NOT NULL,
            image_url VARCHAR(255) NULL,
            status ENUM('Draft','Published','Unpublished') DEFAULT 'Published',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    // Schema migration: Add missing columns or modify existing ones if table already exists
    $check_cat_col = mysqli_query($conn, "SHOW COLUMNS FROM info_hub_tbl LIKE 'category'");
    if ($check_cat_col && mysqli_num_rows($check_cat_col) === 0) {
        mysqli_query($conn, "ALTER TABLE info_hub_tbl ADD COLUMN category VARCHAR(100) DEFAULT 'Article' AFTER type");
    }
    
    // Ensure admin_id can be NULL as per new schema
    mysqli_query($conn, "ALTER TABLE info_hub_tbl MODIFY COLUMN admin_id INT NULL");
    
    // Ensure type ENUM matches new requirements
    mysqli_query($conn, "ALTER TABLE info_hub_tbl MODIFY COLUMN type ENUM('Article','Study','Tutorial','Recipe','Dish Guide','Research','Recipes','Forum') DEFAULT 'Article'");
}

// Categories setup as requested
$expected_categories = [
    'Fish (Bangus, Tilapia, Tulingan)',
    'Shrimps / Crabs (Hipon, Alimango)',
    'Shellfish (Tahong, Talaba)',
    'Squid (Pusit)',
    'Octopus (Pugita)',
    'Seaweeds (Lato, Guso)',
];

$cat_check = mysqli_query($conn, "SHOW TABLES LIKE 'category_tbl'");
if ($cat_check && mysqli_num_rows($cat_check) > 0) {
    foreach ($expected_categories as $cname) {
        $stmt = mysqli_prepare($conn, "SELECT category_id FROM category_tbl WHERE category_name = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $cname);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($res) === 0) {
                $ins = mysqli_prepare($conn, "INSERT INTO category_tbl (category_name, description) VALUES (?, '')");
                if ($ins) {
                    mysqli_stmt_bind_param($ins, "s", $cname);
                    mysqli_stmt_execute($ins);
                    mysqli_stmt_close($ins);
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Schema migration: Discounted price for Today's Deals
$dp_check = mysqli_query($conn, "SHOW COLUMNS FROM product_tbl LIKE 'discounted_price'");
if ($dp_check && mysqli_num_rows($dp_check) === 0) {
    @mysqli_query($conn, "ALTER TABLE product_tbl ADD COLUMN discounted_price DECIMAL(10,2) NULL DEFAULT NULL AFTER price");
}

// Schema integrity checks for forum tables
$check_forum = mysqli_query($conn, "SHOW COLUMNS FROM forum_post_tbl LIKE 'user_id'");
if (!$check_forum || mysqli_num_rows($check_forum) === 0) {
    // If user_id doesn't exist, drop the old tables and recreate them to match the new schema cleanly
    mysqli_query($conn, "DROP TABLE IF EXISTS forum_comment_tbl");
    mysqli_query($conn, "DROP TABLE IF EXISTS forum_post_tbl");
}

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS forum_post_tbl (
        post_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_role ENUM('farmer', 'buyer', 'Content Admin', 'User Admin') NOT NULL DEFAULT 'farmer',
        author_name VARCHAR(150) NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100) DEFAULT 'General Discussion',
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS forum_comment_tbl (
        comment_id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        user_role ENUM('farmer', 'buyer', 'Content Admin', 'User Admin') NOT NULL DEFAULT 'buyer',
        author_name VARCHAR(150) NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Support message follow-up reply table
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS admin_support_reply_tbl (
        reply_id INT AUTO_INCREMENT PRIMARY KEY,
        support_id INT NOT NULL,
        sender_type ENUM('user', 'admin') NOT NULL,
        sender_id INT NOT NULL,
        sender_role VARCHAR(50) NOT NULL,
        sender_name VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (support_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Auto-migrate legacy single admin_reply rows to admin_support_reply_tbl if not migrated yet
$check_mig = @mysqli_query($conn, "
    SELECT s.support_id, s.admin_id, s.admin_reply, s.updated_at, a.role, a.full_name
    FROM admin_support_tbl s
    JOIN admin_tbl a ON a.admin_id = s.admin_id
    WHERE s.admin_reply IS NOT NULL AND s.admin_reply != ''
      AND NOT EXISTS (SELECT 1 FROM admin_support_reply_tbl r WHERE r.support_id = s.support_id AND r.sender_type = 'admin')
");
if ($check_mig && mysqli_num_rows($check_mig) > 0) {
    while ($m_row = mysqli_fetch_assoc($check_mig)) {
        $ins = mysqli_prepare($conn, "INSERT INTO admin_support_reply_tbl (support_id, sender_type, sender_id, sender_role, sender_name, message, created_at) VALUES (?, 'admin', ?, ?, ?, ?, ?)");
        if ($ins) {
            mysqli_stmt_bind_param($ins, "iissss", $m_row['support_id'], $m_row['admin_id'], $m_row['role'], $m_row['full_name'], $m_row['admin_reply'], $m_row['updated_at']);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
    }
}

// Update order_tbl order_status enum to ensure 'Ready for fulfillment' is supported
try {
    @mysqli_query($conn, "ALTER TABLE order_tbl MODIFY COLUMN order_status ENUM('Order Placed','Confirmed','Ready for fulfillment','Ready for Pickup','Ready for Delivery','Completed','Cancelled') NOT NULL DEFAULT 'Order Placed'");
} catch (Throwable $e) {}

// Update order_tbl payment_status enum to include 'Cancelled'
try {
    @mysqli_query($conn, "ALTER TABLE order_tbl MODIFY COLUMN payment_status ENUM('Pending','Paid','Failed','Cancelled') NOT NULL DEFAULT 'Pending'");
    @mysqli_query($conn, "UPDATE order_tbl SET payment_status = 'Cancelled' WHERE order_status = 'Cancelled' AND payment_status != 'Cancelled'");
} catch (Throwable $e) {}

// Schema migration: Order cancellation tracking & 2% platform monetization
$order_cols = [
    'cancellation_reason' => "ALTER TABLE order_tbl ADD COLUMN cancellation_reason TEXT NULL AFTER order_status",
    'cancelled_by' => "ALTER TABLE order_tbl ADD COLUMN cancelled_by ENUM('Buyer', 'Farmer', 'Admin') NULL AFTER cancellation_reason",
    'cancelled_at' => "ALTER TABLE order_tbl ADD COLUMN cancelled_at DATETIME NULL AFTER cancelled_by",
    'subtotal_amount' => "ALTER TABLE order_tbl ADD COLUMN subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER order_date",
    'platform_fee' => "ALTER TABLE order_tbl ADD COLUMN platform_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal_amount",
    'farmer_payout' => "ALTER TABLE order_tbl ADD COLUMN farmer_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER platform_fee",
];
foreach ($order_cols as $col_name => $alter_sql) {
    try {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM order_tbl LIKE '$col_name'");
        if ($check && mysqli_num_rows($check) === 0) {
            mysqli_query($conn, $alter_sql);
        }
    } catch (Throwable $e) {}
}

// Schema migration: Product shelf-life & TikTok-style boost flags
$product_cols = [
    'harvested_at' => "ALTER TABLE product_tbl ADD COLUMN harvested_at DATETIME NULL AFTER description",
    'shelf_life_hours' => "ALTER TABLE product_tbl ADD COLUMN shelf_life_hours INT(11) NOT NULL DEFAULT 24 AFTER harvested_at",
    'selling_deadline' => "ALTER TABLE product_tbl ADD COLUMN selling_deadline DATETIME NULL AFTER shelf_life_hours",
    'is_boosted' => "ALTER TABLE product_tbl ADD COLUMN is_boosted TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
    'boost_tier' => "ALTER TABLE product_tbl ADD COLUMN boost_tier VARCHAR(50) NULL AFTER is_boosted",
    'boost_expires_at' => "ALTER TABLE product_tbl ADD COLUMN boost_expires_at DATETIME NULL AFTER boost_tier",
];
foreach ($product_cols as $col_name => $alter_sql) {
    try {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM product_tbl LIKE '$col_name'");
        if ($check && mysqli_num_rows($check) === 0) {
            mysqli_query($conn, $alter_sql);
        }
    } catch (Throwable $e) {}
}

// Table: product_boost_tbl (Paid promotions / advertising)
try {
    @mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS product_boost_tbl (
            boost_id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            farmer_id INT NOT NULL,
            boost_tier ENUM('Flash Banner', 'Top Search', 'Featured Badge') NOT NULL,
            amount_paid DECIMAL(10,2) NOT NULL,
            start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            end_date DATETIME NOT NULL,
            status ENUM('Pending', 'Active', 'Expired') NOT NULL DEFAULT 'Active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (product_id),
            INDEX (farmer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $e) {}

// Table: guest_alert_tbl (Guest email catch alerts & newsletter)
try {
    @mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS guest_alert_tbl (
            alert_id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            category_interest VARCHAR(100) DEFAULT 'All',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $e) {}



// Seed starter info hub articles if empty
$art_count = mysqli_query($conn, "SELECT COUNT(*) as c FROM info_hub_tbl");
if ($art_count) {
    $row = mysqli_fetch_assoc($art_count);
    if ((int)($row['c'] ?? 0) === 0) {
        $starter_articles = [
            [
                'title' => 'Sustainable Bangus & Tilapia Pond Management in Coastal Romblon',
                'category' => 'Farming Tips',
                'content' => "Proper water aeration and monitoring of salinity levels are essential for healthy bangus (milkfish) growth in island fish pens. Maintaining a steady water exchange cycle during high tides reduces ammonia buildup and promotes disease resistance.\n\nKey Recommendations:\n1. Check water pH level twice weekly (optimal range: 7.5 - 8.5).\n2. Feed formulated pellets in regulated portions to prevent pond bottom sludge accumulation.\n3. Implement rotational harvesting to allow pond soil revitalization."
            ],
            [
                'title' => 'Best Practices for Seaweed (Eucheuma & Kappaphycus) Cultivation',
                'category' => 'Seaweed Farming',
                'content' => "Seaweed farming in Santa Fe coastal areas requires careful site selection where moderate water currents provide essential nutrients while preventing excessive sediment deposition.\n\nKey Steps:\n- Ensure planting lines are submerged at least 0.5m below low tide level.\n- Regular weeding of epiphytes and removal of grazers ensures maximum carrageenan yield.\n- Sun-dry harvests on elevated drying platforms rather than directly on the ground to preserve export quality."
            ],
            [
                'title' => 'Post-Harvest Handling & Cold Chain Storage for Local Fisherfolk',
                'category' => 'Product Care & Preservation',
                'content' => "Maintaining product freshness from boat to market significantly increases selling prices for aquatic producers. Applying clean crushed ice at a 1:1 ratio immediately upon catch slows bacterial degradation and ensures firm texture.\n\nHandling Protocol:\n- Wash fish with clean seawater before chilling.\n- Use insulated iceboxes with drain plugs to prevent fish from soaking in melted dirty ice water.\n- Separate high-value crustaceans (crabs/shrimps) to prevent limb breakage during transport."
            ],
            [
                'title' => 'Understanding BFAR Quality and Sizing Standards for Trade',
                'category' => 'Market Standards',
                'content' => "Adhering to municipal fisheries standards protects marine breeding cycles and guarantees fair pricing for consumers and commercial buyers.\n\nStandard Categories:\n- Milkfish (Bangus): Medium (3-4 pcs/kg), Large (1-2 pcs/kg).\n- Crabs: Minimum carapace width of 10cm for commercial sale.\n- Dried Aquatic Products: Moisture content below 15% to prevent mold growth during packaging."
            ]
        ];

        foreach ($starter_articles as $art) {
            $stmt = mysqli_prepare($conn, "INSERT INTO info_hub_tbl (title, category, content, status) VALUES (?, ?, ?, 'Published')");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sss", $art['title'], $art['category'], $art['content']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
}