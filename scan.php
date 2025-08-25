<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION["authenticated"])) {
    header("Location: auth.php?redirect=scan.php");
    exit;
}

// --- database connection ---
$servername = "localhost";
$username   = "inventory_user";
$password   = "strong_password";
$dbname     = "inventory_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// Default mode = add
if (!isset($_SESSION['mode'])) {
    $_SESSION['mode'] = "add";
}

// Handle toggle request
if (isset($_POST['toggle_mode'])) {
    $_SESSION['mode'] = ($_SESSION['mode'] === "add") ? "remove" : "add";
}

$statusMessage = "";
$statusClass   = "";
$prefillName   = "";
$lastBarcode   = "";
$needNameFocus = false;

// --- Handle new category creation ---
if (isset($_POST['new_category']) && trim($_POST['new_category']) !== '') {
    $newCat = trim($_POST['new_category']);
    $_SESSION['selected_category'] = $newCat;
}

// --- Fetch categories dynamically ---
$categories = [];
$catResult = $conn->query("SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
if ($catResult && $catResult->num_rows > 0) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Include new session category if not in DB yet
if (!empty($_SESSION['selected_category']) && !in_array($_SESSION['selected_category'], $categories)) {
    $categories[] = $_SESSION['selected_category'];
    sort($categories);
}

// Handle category selection
if (isset($_POST['selected_category'])) {
    $_SESSION['selected_category'] = $_POST['selected_category'];
}
$currentCategory = $_SESSION['selected_category'] ?? "";

/**
 * Try UPCitemdb API lookup
 */
function lookupProductName($barcode) {
    $url = "https://api.upcitemdb.com/prod/trial/lookup?upc=" . urlencode($barcode);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http === 200 && $resp) {
        $data = json_decode($resp, true);
        if (isset($data["total"]) && $data["total"] > 0 && !empty($data["items"])) {
            return $data["items"][0]["title"] ?? null;
        }
    }
    return null;
}

// Handle scan submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['scan'])) {
    $mode    = $_SESSION['mode'];
    $barcode = trim($_POST['barcode'] ?? '');
    $name    = trim($_POST['name']    ?? '');
    $lastBarcode = $barcode;

    if ($barcode !== '') {
        $stmt = $conn->prepare("SELECT product_name, quantity FROM inventory WHERE barcode=?");
        $stmt->bind_param("s", $barcode);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $productName = $row['product_name'] ?: "Unnamed Product";

            if ($mode === "remove") {
                $upd = $conn->prepare("UPDATE inventory SET quantity = IF(quantity > 0, quantity - 1, 0) WHERE barcode=?");
                $upd->bind_param("s", $barcode);
                $upd->execute();
                $upd->close();
                $newQty = max(0, $row['quantity'] - 1);
                $statusMessage = "Removed 1 → $productName (Now: $newQty)";
                $statusClass = "success";
            } else {
                $upd = $conn->prepare("UPDATE inventory SET quantity = quantity + 1 WHERE barcode=?");
                $upd->bind_param("s", $barcode);
                $upd->execute();
                $upd->close();
                $newQty = $row['quantity'] + 1;
                $statusMessage = "Added 1 → $productName (Now: $newQty)";
                $statusClass = "success";
            }

        } else {
            // Not found → check API first
            $itemName = lookupProductName($barcode);

            if (!$itemName) {
                if ($name === '') {
                    $statusMessage = "No product found. Please enter a name.";
                    $statusClass   = "error";
                    $prefillName   = "";
                    $needNameFocus = true;
                } else {
                    $itemName = $name;
                }
            } else {
                $prefillName = $itemName;
                if ($name !== '') {
                    $itemName = $name; // user override
                }
            }

            if ($itemName) {
                $initialQty      = ($mode === "remove") ? 0 : 1;
                $serial          = "";
                $categoryToUse   = $currentCategory ?: "Other";

                $ins = $conn->prepare("INSERT INTO inventory (barcode, product_name, quantity, serial_number, category) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("ssiss", $barcode, $itemName, $initialQty, $serial, $categoryToUse);
                $ins->execute();
                $ins->close();

                if ($mode === "remove") {
                    $statusMessage = "Created with 0 → $itemName";
                } else {
                    $statusMessage = "Added 1 → $itemName (Now: 1)";
                }
                $statusClass = "success";
            }
        }

        $stmt->close();
    } else {
        $statusMessage = "No barcode provided.";
        $statusClass   = "error";
    }
}

$conn->close();
$currentMode = $_SESSION['mode'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scan Items</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f4f6f9;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        padding: 1rem;
    }
    .container {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 400px;
        text-align: center;
        box-sizing: border-box;
    }
    h2 { margin-bottom: 1rem; color: #333; }
    a.link-btn {
        display: inline-block;
        margin-bottom: 1rem;
        padding: 8px 12px;
        background: #3498db;
        color: white;
        border-radius: 5px;
        text-decoration: none;
    }
    a.link-btn:hover { background: #2980b9; }
    input[type="text"], select {
        width: 100%;
        padding: 0.6rem;
        margin: 0.5rem 0;
        border: 1px solid #ccc;
        border-radius: 8px;
        box-sizing: border-box;
    }
    button {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        margin-top: 0.5rem;
        width: 100%;
        box-sizing: border-box;
    }
    .scan-btn { background: #4CAF50; }
    .scan-btn:hover { background: #45a049; }
    .toggle-btn { background: #2196F3; margin-bottom: 1rem; }
    .toggle-btn:hover { background: #1976d2; }
    .status { margin-top: 1rem; padding: 0.8rem; border-radius: 8px; }
    .success { background: #e0f7e9; color: #2e7d32; }
    .error   { background: #fdecea; color: #c62828; }
    .mode-indicator { margin-bottom: 1rem; font-weight: bold; font-size: 1.1rem; }
    .mode-add { color: #2e7d32; }
    .mode-remove { color: #c62828; }
</style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="link-btn">Back to Inventory</a>

        <h2>Scan Barcode</h2>
        <div class="mode-indicator <?= $currentMode === 'add' ? 'mode-add' : 'mode-remove' ?>">
            Current Mode: <?= strtoupper(htmlspecialchars($currentMode)) ?>
        </div>

        <!-- Toggle mode -->
        <form method="post">
            <button type="submit" name="toggle_mode" value="1" class="toggle-btn">Toggle Mode</button>
        </form>

        <!-- Category selector -->
        <form method="post">
            <label for="selected_category">Select Category:</label>
            <select name="selected_category" id="selected_category" onchange="this.form.submit()">
                <option value="" <?= $currentCategory==="" ? "selected" : "" ?>>-- None --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $currentCategory===$cat ? "selected" : "" ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- Add new category -->
        <form method="post">
            <input type="text" name="new_category" placeholder="Add new category">
            <button type="submit">Add Category</button>
        </form>

        <!-- Scan form -->
        <form method="post" id="scanForm">
            <input type="text" id="barcodeField" name="barcode" value="<?= htmlspecialchars($lastBarcode) ?>" placeholder="Scan barcode here..." autofocus required>
            <input type="text" id="nameField" name="name" value="<?= htmlspecialchars($prefillName) ?>" placeholder="Enter item name if new">
            <button type="submit" name="scan" class="scan-btn">Submit</button>
        </form>

        <?php if ($statusMessage): ?>
            <div class="status <?= $statusClass ?>">
                <?= htmlspecialchars($statusMessage) ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const barcodeField = document.getElementById("barcodeField");
        const nameField    = document.getElementById("nameField");
        const scanForm     = document.getElementById("scanForm");
        const scanButton   = scanForm.querySelector("button[name='scan']");

        <?php if ($needNameFocus): ?>
        nameField.focus();
        <?php else: ?>
        barcodeField.value = "";
        nameField.value = "";
        barcodeField.focus();
        <?php endif; ?>

        nameField.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                scanButton.click();
            }
        });
    </script>
</body>
</html>

