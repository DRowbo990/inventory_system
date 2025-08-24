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
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enter PIN</title>
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
                box-shadow: 0 6px 16px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 350px;
                text-align: center;
            }
            h2 {
                margin-bottom: 1rem;
                color: #333;
                font-size: 1.5rem;
            }
            input[type="password"] {
                width: 100%;
                padding: 0.8rem;
                margin-bottom: 1rem;
                border: 1px solid #ccc;
                border-radius: 8px;
                font-size: 1rem;
                box-sizing: border-box;
            }
            button {
                width: 100%;
                padding: 0.8rem;
                background: #007BFF;
                border: none;
                border-radius: 8px;
                color: white;
                font-size: 1rem;
                cursor: pointer;
                transition: background 0.3s;
            }
            button:hover {
                background: #0056b3;
            }
            .error {
                color: red;
                margin-bottom: 1rem;
                font-size: 0.9rem;
            }

            /* Responsive adjustments */
            @media (max-width: 480px) {
                .container {
                    padding: 1.5rem;
                }
                h2 {
                    font-size: 1.3rem;
                }
                input[type="password"], button {
                    font-size: 0.95rem;
                    padding: 0.7rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Authentication Required</h2>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="post">
                <input type="password" name="pin" placeholder="Enter PIN" required>
                <button type="submit">Submit</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
