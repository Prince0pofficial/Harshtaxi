<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_SESSION["csrf"]) ||
    !hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
    http_response_code(403);
    exit("Invalid logout request.");
}
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}
session_destroy();
header("Location: admin.php");
exit;
