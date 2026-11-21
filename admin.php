<?php
session_start();
require_once "db.php";

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}

if (!isset($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

$hasTable = $conn->query("SHOW TABLES LIKE 'admin_users'");
$setupNeeded = !$hasTable || $hasTable->num_rows === 0;

if (!$setupNeeded) {
    $checkResult = $conn->query("SELECT COUNT(*) AS total FROM admin_users");
    $setupNeeded = (int)$checkResult->fetch_assoc()["total"] === 0;
}

$error = "";
$notice = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        $error = "Security token expired. Refresh the page.";

    } elseif (($_POST["action"] ?? "") === "login" && !$setupNeeded) {

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        $loginStmt = $conn->prepare(
            "SELECT id, username, password_hash
             FROM admin_users WHERE username=? LIMIT 1"
        );

        if ($loginStmt) {
            $loginStmt->bind_param("s", $username);
            $loginStmt->execute();
            $loginStmt->bind_result($adminId, $adminUsername, $passwordHash);

            if ($loginStmt->fetch() && password_verify($password, $passwordHash)) {
                $loginStmt->close();
                session_regenerate_id(true);
                $_SESSION["admin_id"] = $adminId;
                $_SESSION["admin_user"] = $adminUsername;
                header("Location: admin.php");
                exit;
            }

            $loginStmt->close();
        }

        $error = "Incorrect username or password.";

    } elseif (
        isset($_SESSION["admin_id"]) &&
        in_array($_POST["action"] ?? "", ["status", "delete"], true)
    ) {

        $bookingId = filter_var(
            $_POST["booking_id"] ?? 0,
            FILTER_VALIDATE_INT
        );

        if (!$bookingId || $bookingId < 1) {
            $error = "Invalid booking.";

        } elseif ($_POST["action"] === "status") {

            $newStatus = $_POST["status"] ?? "";
            $allowed = ["Pending", "Confirmed", "Completed", "Cancelled"];

            if (!in_array($newStatus, $allowed, true)) {
                $error = "Invalid status.";
            } else {
                $actionStmt = $conn->prepare(
                    "UPDATE bookings SET status=? WHERE id=?"
                );

                if ($actionStmt) {
                    $actionStmt->bind_param("si", $newStatus, $bookingId);

                    if ($actionStmt->execute()) {
                        $notice = "Booking status updated successfully.";
                    } else {
                        $error = "Could not update booking status.";
                    }

                    $actionStmt->close();
                } else {
                    $error = "Database error. Please try again.";
                }
            }

        } else {

            $actionStmt = $conn->prepare(
                "DELETE FROM bookings WHERE id=?"
            );

            if ($actionStmt) {
                $actionStmt->bind_param("i", $bookingId);

                if ($actionStmt->execute()) {
                    $notice = "Booking deleted successfully.";
                } else {
                    $error = "Could not delete booking.";
                }

                $actionStmt->close();
            } else {
                $error = "Database error. Please try again.";
            }
        }
    }
}

$logged = isset($_SESSION["admin_id"]);

$stats = [
    "total" => 0,
    "pending" => 0,
    "confirmed" => 0,
    "completed" => 0
];

$rows = [];
$customers = 0;

if ($logged) {

    $statsResult = $conn->query(
        "SELECT COUNT(*) AS total,
        COALESCE(SUM(status='Pending'),0) AS pending,
        COALESCE(SUM(status='Confirmed'),0) AS confirmed,
        COALESCE(SUM(status='Completed'),0) AS completed
        FROM bookings"
    );

    if ($statsResult) {
        $stats = array_map("intval", $statsResult->fetch_assoc());
    }

    $customerResult = $conn->query(
        "SELECT COUNT(DISTINCT phone) AS c FROM bookings"
    );

    if ($customerResult) {
        $customers = (int)$customerResult->fetch_assoc()["c"];
    }

    $search = trim($_GET["q"] ?? "");
    $filter = $_GET["status"] ?? "";

    $allowedStatuses = [
        "Pending", "Confirmed", "Completed", "Cancelled"
    ];

    $sql = "SELECT id, customer_name, phone, pickup_location,
            drop_location, journey_date, journey_time,
            trip_type, status, created_at FROM bookings";

    $where = [];
    $params = [];
    $types = "";

    if ($search !== "") {
        $where[] = "(customer_name LIKE ? OR phone LIKE ?
                    OR pickup_location LIKE ? OR drop_location LIKE ?)";

        $like = "%" . $search . "%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;

        $types .= "ssss";
    }

    if (in_array($filter, $allowedStatuses, true)) {
        $where[] = "status=?";
        $params[] = $filter;
        $types .= "s";
    }

    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY id DESC LIMIT 200";

    if ($params) {
        $listStmt = $conn->prepare($sql);

        if ($listStmt) {
            $listStmt->bind_param($types, ...$params);
            $listStmt->execute();
            $listResult = $listStmt->get_result();
        } else {
            $listResult = false;
        }
    } else {
        $listStmt = null;
        $listResult = $conn->query($sql);
    }

    if ($listResult) {
        while ($row = $listResult->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    if ($listStmt instanceof mysqli_stmt) {
        $listStmt->close();
    }
}

function badge($status) {
    $classes = [
        "Pending" => "pending",
        "Confirmed" => "confirmed",
        "Completed" => "completed",
        "Cancelled" => "cancelled"
    ];

    return $classes[$status] ?? "";
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Harsh Taxi Admin</title>
<link rel="stylesheet" href="admin.css">
</head>

<body>

<?php if (!$logged): ?>

<div class="auth">
<div class="authbox">
<div class="mark">H</div>
<p class="gold">HARSH TAXI · AGRA</p>
<h1>Admin Login</h1>
<p>Sign in to manage taxi bookings.</p>

<?php if ($setupNeeded): ?>

<div class="alert">First create your admin account.</div>
<a class="btn" href="admin-setup.php">CREATE ADMIN ACCOUNT</a>

<?php else: ?>

<?php if ($error): ?>
<div class="alert"><?=e($error)?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="csrf" value="<?=e($_SESSION["csrf"])?>">
<input type="hidden" name="action" value="login">

<label>Username</label>
<input name="username" required autocomplete="username">

<label>Password</label>
<input type="password" name="password" required autocomplete="current-password">

<button class="btn">LOGIN</button>
</form>

<?php endif; ?>

<a class="back" href="index.html">Back to website</a>
</div>
</div>

<?php else: ?>

<header>
<a class="brand" href="admin.php">
<span class="mark mini">H</span>
HARSH<span class="gold">TAXI</span>
<small>ADMIN PANEL</small>
</a>

<span>Hi, <?=e($_SESSION["admin_user"] ?? "Admin")?></span>

<form action="admin-logout.php" method="post">
<input type="hidden" name="csrf" value="<?=e($_SESSION["csrf"])?>">
<button class="outline">LOGOUT</button>
</form>
</header>

<main class="wrap">

<div class="heading">
<div>
<p class="gold">OVERVIEW</p>
<h1>Dashboard</h1>
<p class="muted">Manage your taxi booking requests.</p>
</div>
<a class="btn" href="admin.php">REFRESH</a>
</div>

<?php if ($notice): ?>
<div class="success"><?=e($notice)?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert"><?=e($error)?></div>
<?php endif; ?>

<section class="stats">

<div class="stat">
<span>Total Bookings</span>
<b><?=$stats["total"]?></b>
</div>

<div class="stat">
<span>Pending</span>
<b><?=$stats["pending"]?></b>
</div>

<div class="stat">
<span>Confirmed</span>
<b><?=$stats["confirmed"]?></b>
</div>

<div class="stat">
<span>Completed</span>
<b><?=$stats["completed"]?></b>
</div>

<div class="stat">
<span>Customers</span>
<b><?=$customers?></b>
</div>

</section>

<section class="panel">

<div class="heading">
<div>
<p class="gold">BOOKING MANAGEMENT</p>
<h2>All Bookings</h2>
</div>
<span class="muted">Latest 200 requests</span>
</div>

<form class="filters" method="get">

<input name="q"
value="<?=e($_GET["q"] ?? "")?>"
placeholder="Search customer, phone or location">

<select name="status">
<option value="">All statuses</option>

<?php foreach (["Pending","Confirmed","Completed","Cancelled"] as $x): ?>
<option value="<?=e($x)?>"
<?=($_GET["status"] ?? "") === $x ? "selected" : ""?>>
<?=e($x)?>
</option>
<?php endforeach; ?>

</select>

<button class="btn">SEARCH</button>
<a class="outline" href="admin.php">CLEAR</a>

</form>

<?php if (!$rows): ?>

<p class="muted empty">No bookings found.</p>

<?php else: ?>

<div class="scroll">
<table>

<thead>
<tr>
<th>Booking / Customer</th>
<th>Phone</th>
<th>Journey</th>
<th>Date / Time</th>
<th>Trip</th>
<th>Status</th>
<th>Manage</th>
</tr>
</thead>

<tbody>

<?php foreach ($rows as $b): ?>

<tr>

<td>
<b>#<?=(int)$b["id"]?> · <?=e($b["customer_name"])?></b>
<small><?=e($b["created_at"])?></small>
</td>

<td>
<a href="tel:<?=e($b["phone"])?>"><?=e($b["phone"])?></a>
<small>
<a target="_blank" rel="noopener"
href="https://wa.me/91<?=preg_replace('/\D+/', '', $b["phone"])?>">
WhatsApp customer
</a>
</small>
</td>

<td>
<?=e($b["pickup_location"])?>
<small>to <?=e($b["drop_location"])?></small>
</td>

<td>
<?=e($b["journey_date"])?>
<small><?=e($b["journey_time"])?></small>
</td>

<td>
<?=e($b["trip_type"])?>
<small>Swift Dzire</small>
</td>

<td>
<span class="badge <?=badge($b["status"])?>">
<?=e($b["status"])?>
</span>
</td>

<td>

<details>
<summary>View details</summary>
<p><b>Customer:</b> <?=e($b["customer_name"])?></p>
<p><b>Phone:</b> <?=e($b["phone"])?></p>
<p><b>Pickup:</b> <?=e($b["pickup_location"])?></p>
<p><b>Destination:</b> <?=e($b["drop_location"])?></p>
<p><b>Date:</b> <?=e($b["journey_date"])?></p>
<p><b>Time:</b> <?=e($b["journey_time"])?></p>
<p><b>Trip:</b> <?=e($b["trip_type"])?></p>
<p><b>Vehicle:</b> Swift Dzire</p>
</details>

<form method="post" class="manage">
<input type="hidden" name="csrf" value="<?=e($_SESSION["csrf"])?>">
<input type="hidden" name="action" value="status">
<input type="hidden" name="booking_id" value="<?=(int)$b["id"]?>">

<select name="status">
<?php foreach (["Pending","Confirmed","Completed","Cancelled"] as $x): ?>
<option value="<?=e($x)?>" <?=$b["status"] === $x ? "selected" : ""?>>
<?=e($x)?>
</option>
<?php endforeach; ?>
</select>

<button class="btn tiny">SAVE</button>
</form>

<form method="post"
onsubmit="return confirm('Delete this booking permanently?')"
class="delete">

<input type="hidden" name="csrf" value="<?=e($_SESSION["csrf"])?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="booking_id" value="<?=(int)$b["id"]?>">

<button class="danger tiny">DELETE</button>
</form>

</td>
</tr>

<?php endforeach; ?>

</tbody>
</table>
</div>

<?php endif; ?>

</section>

<p class="foot">Harsh Taxi · Admin Dashboard</p>

</main>

<?php endif; ?>

</body>
</html>
