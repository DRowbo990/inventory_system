<?php
session_start();

// Set your PIN here
$correct_pin = "your_secure_pin";

// If PIN is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["pin"])) {
    if ($_POST["pin"] === $correct_pin) {
        $_SESSION["authenticated"] = true;
        // Redirect back to original page
        if (isset($_GET["redirect"])) {
            header("Location: " . $_GET["redirect"]);
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "Invalid PIN. Try again.";
    }
}

// If not authenticated, show PIN form
if (!isset($_SESSION["authenticated"])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Enter PIN</title>
    </head>
    <body>
        <h2>Authentication Required</h2>
        <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="post">
            <input type="password" name="pin" placeholder="Enter PIN" required>
            <button type="submit">Submit</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}
?>

