<?php
session_start();
require_once "db.php";
$conn->query("CREATE TABLE IF NOT EXISTS admin_users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(50) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$n = (int)$conn->query("SELECT COUNT(*) total FROM admin_users")->fetch_assoc()["total"];
if (!isset($_SESSION["csrf"])) $_SESSION["csrf"] = bin2hex(random_bytes(32));
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && $n === 0) {
 if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) $error = "Refresh and try again.";
 else {
  $u = trim($_POST["username"] ?? ""); $p = $_POST["password"] ?? ""; $p2 = $_POST["confirm"] ?? "";
  if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $u)) $error = "Username: 3-50 letters, numbers or underscore.";
  elseif (strlen($p) < 12) $error = "Password must be at least 12 characters.";
  elseif ($p !== $p2) $error = "Passwords do not match.";
  else {
   $h = password_hash($p, PASSWORD_DEFAULT);
   $s = $conn->prepare("INSERT INTO admin_users(username,password_hash) VALUES(?,?)");
   $s->bind_param("ss", $u, $h);
   if ($s->execute()) { header("Location: admin.php"); exit; }
   $error = "Could not create account. Try another username.";
  }
 }
}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,"UTF-8");}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Setup</title><link rel="stylesheet" href="admin.css"></head><body class="auth">
<div class="authbox"><div class="mark">H</div><p class="gold">HARSH TAXI · ADMIN</p>
<?php if($n>0): ?><h1>Admin already created</h1><p>Setup is locked. Please use the login page.</p><a class="btn" href="admin.php">Go to Login</a>
<?php else: ?><h1>Create Admin Account</h1><p>Choose a private username and strong password.</p>
<?php if($error): ?><div class="alert"><?=e($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION["csrf"])?>">
<label>Username</label><input name="username" required minlength="3" maxlength="50">
<label>Password (12+ characters)</label><input type="password" name="password" required minlength="12">
<label>Confirm password</label><input type="password" name="confirm" required minlength="12">
<button class="btn" type="submit">CREATE ADMIN</button></form><?php endif;?></div></body></html>
