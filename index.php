<?php
session_start();
if (!isset($_SESSION["authenticated"])) {
    header("Location: auth.php?redirect=index.php");
    exit;
}
$servername   = "localhost";
$username     = "inventory_user";
$password     = "strong_password";  // your DB password
$dbname       = "inventory_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --------------------
// Handle Add
// --------------------
if (isset($_POST['add'])) {
    $barcode  = $_POST['barcode'];
    $name     = $_POST['name'];
    $quantity = intval($_POST['quantity']);
    $serial   = !empty($_POST['serial_number']) ? $_POST['serial_number'] : NULL;
    $category = !empty($_POST['new_category']) ? $_POST['new_category'] : $_POST['category'];
    $stmt = $conn->prepare("INSERT INTO inventory (barcode, product_name, quantity, serial_number, category) VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE product_name=VALUES(product_name), serial_number=VALUES(serial_number), category=VALUES(category), quantity=quantity+VALUES(quantity)");
    $stmt->bind_param("ssiss", $barcode, $name, $quantity, $serial, $category);
    $stmt->execute();
    $stmt->close();
}

// --------------------
// Handle Update
// --------------------
if (isset($_POST['update'])) {
    $barcode  = $_POST['barcode'];
    $name     = $_POST['name'];
    $quantity = intval($_POST['quantity']);
    $serial   = !empty($_POST['serial_number']) ? $_POST['serial_number'] : NULL;
    $category = !empty($_POST['new_category']) ? $_POST['new_category'] : $_POST['category'];
    $stmt = $conn->prepare("UPDATE inventory SET product_name=?, quantity=?, serial_number=?, category=? WHERE barcode=?");
    $stmt->bind_param("sisss", $name, $quantity, $serial, $category, $barcode);
    $stmt->execute();
    $stmt->close();
}

// --------------------
// Handle Delete (set quantity = 0, keep in DB)
// --------------------
if (isset($_POST['delete'])) {
    $barcode = $_POST['barcode'];
    $stmt = $conn->prepare("UPDATE inventory SET quantity=0 WHERE barcode=?");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $stmt->close();
}

// --------------------
// Sorting
// --------------------
$sort  = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = "product_name ASC";
if ($sort === "quantity_desc") $order = "quantity DESC";
elseif ($sort === "quantity_asc") $order = "quantity ASC";
elseif ($sort === "name_desc") $order = "product_name DESC";

// --------------------
// Category filter
// --------------------
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'All';
$where = ($categoryFilter !== "All") ? "WHERE category='" . $conn->real_escape_string($categoryFilter) . "'" : "";

// --------------------
// Search filter
// --------------------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $safeSearch = "%" . $conn->real_escape_string($search) . "%";
    $where .= ($where ? " AND" : "WHERE") . " (product_name LIKE '$safeSearch' OR barcode LIKE '$safeSearch')";
}

// --------------------
// Query categories dynamically
// --------------------
$categoriesResult = $conn->query("SELECT DISTINCT category FROM inventory ORDER BY category ASC");
$categories = [];
while ($row = $categoriesResult->fetch_assoc()) {
    if (!empty($row['category'])) $categories[] = $row['category'];
}

// Only show items with quantity > 0
$sql = "SELECT barcode, product_name, quantity, serial_number, category FROM inventory $where" . ($where ? " AND" : " WHERE") . " quantity > 0 ORDER BY $order";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 20px; }
        h1, h2 { color: #333; }
        a.link-btn { display:inline-block; padding:8px 12px; margin:5px 0; background:#3498db; color:white; border-radius:5px; text-decoration:none; }
        a.link-btn:hover { background:#2980b9; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        th, td { border-right: 1px solid #eee; padding: 12px; text-align: center; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background: #fafafa; }
        tr:hover { background: #f1f7ff; }
        td:last-child, th:last-child { border-right: none; }
        form { margin-top: 20px; }
        input, select, button { padding: 6px 8px; margin: 2px; border-radius: 4px; border: 1px solid #ccc; }
        button { background: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
        .delete-btn { background: #e74c3c; }
        .delete-btn:hover { background: #c0392b; }
        .search-box { margin: 10px 0; }
        .name-input { width: 100%; box-sizing: border-box; }
    </style>
</head>
<body>

<h1>Inventory</h1>

<!-- Link to scan.php -->
<a href="scan.php" class="link-btn">Go to Scan Page</a>

<!-- Search, Sorting and Filtering -->
<form method="get" style="margin-bottom: 10px;">
    <input type="text" name="search" placeholder="Search by name or barcode..." value="<?=htmlspecialchars($search)?>" class="search-box">

    <label>Sort by:</label>
    <select name="sort" onchange="this.form.submit()">
        <option value="name" <?=($sort=="name"?"selected":"")?>>Name (A-Z)</option>
        <option value="name_desc" <?=($sort=="name_desc"?"selected":"")?>>Name (Z-A)</option>
        <option value="quantity_desc" <?=($sort=="quantity_desc"?"selected":"")?>>Most in Stock</option>
        <option value="quantity_asc" <?=($sort=="quantity_asc"?"selected":"")?>>Least in Stock</option>
    </select>

    <label>Category:</label>
    <select name="category" onchange="this.form.submit()">
        <option <?=($categoryFilter=="All"?"selected":"")?>>All</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?=$cat?>" <?=($categoryFilter==$cat?"selected":"")?>><?=$cat?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Search</button>
</form>

<!-- Inventory Table -->
<table>
<tr>
    <th>Barcode</th>
    <th>Name</th>
    <th>Quantity</th>
    <th>Serial Number</th>
    <th>Category</th>
    <th>Actions</th>
</tr>

<?php
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "<tr>
            <form method='post'>
                <td>".$row["barcode"]."<input type='hidden' name='barcode' value='".$row["barcode"]."'></td>
                <td><input type='text' class='name-input' name='name' value='".htmlspecialchars($row["product_name"])."'></td>
                <td><input type='number' name='quantity' value='".$row["quantity"]."' min='0'></td>
                <td><input type='text' name='serial_number' value='".htmlspecialchars($row["serial_number"] ?? "")."'></td>
                <td>
                    <select name='category'>
                        <option value='".$row["category"]."' selected>".$row["category"]."</option>";
                        foreach ($categories as $cat) {
                            if ($cat != $row["category"]) {
                                echo "<option value='$cat'>$cat</option>";
                            }
                        }
        echo "      </select>
                    or New: <input type='text' name='new_category' placeholder='New category'>
                </td>
                <td>
                    <button type='submit' name='update'>Update</button>
                    <button type='submit' name='delete' class='delete-btn' onclick='return confirm(\"Delete this item?\")'>Delete</button>
                </td>
            </form>
        </tr>";
    }
} else {
    echo "<tr><td colspan='6'>No results</td></tr>";
}
?>
</table>

<!-- Add new item -->
<h2>Add Item</h2>
<form method="post">
    Barcode: <input type="text" name="barcode" required>
    Name: <input type="text" name="name" required>
    Quantity: <input type="number" name="quantity" value="0" min="0">
    Serial Number: <input type="text" name="serial_number">
    Category:
    <select name="category">
        <?php foreach ($categories as $cat): ?>
            <option><?=$cat?></option>
        <?php endforeach; ?>
    </select>
    or New: <input type="text" name="new_category" placeholder="New category">
    <button type="submit" name="add">Add Item</button>
</form>

</body>
</html>

<?php $conn->close(); ?>

