<?php
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request");
}

$name  = trim($_POST["customer_name"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$pickup = trim($_POST["pickup_location"] ?? "");
$drop = trim($_POST["drop_location"] ?? "");
$date = $_POST["journey_date"] ?? "";
$time = $_POST["journey_time"] ?? "";
$trip = $_POST["trip_type"] ?? "";

if (
    !$name || !$pickup || !$drop || !$date || !$time ||
    !preg_match("/^[0-9]{10}$/", $phone) ||
    !in_array($trip, ["Local", "Outstation", "Sightseeing"], true)
) {
    exit("Please enter valid booking details.");
}

$dateCheck = DateTime::createFromFormat("Y-m-d", $date);

if (!$dateCheck || $dateCheck->format("Y-m-d") !== $date ||
    $date < date("Y-m-d")) {
    exit("Invalid journey date.");
}

$sql = "INSERT INTO bookings
(customer_name, phone, pickup_location, drop_location,
journey_date, journey_time, trip_type)
VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit("Booking error. Please contact us.");
}

$stmt->bind_param(
    "sssssss",
    $name, $phone, $pickup, $drop, $date, $time, $trip
);

$success = $stmt->execute();

$stmt->close();
$conn->close();

if (!$success) {
    exit("Booking could not be saved. Please try again.");
}

$businessNumber = "919759772092";

$message = "NEW TAXI BOOKING - HARSH TAXI\n\n"
    . "Customer Name: $name\n"
    . "Customer Phone: $phone\n"
    . "Pickup: $pickup\n"
    . "Destination: $drop\n"
    . "Journey Date: $date\n"
    . "Journey Time: $time\n"
    . "Trip Type: $trip\n\n"
    . "Please confirm this booking.";

$whatsappURL = "https://wa.me/" . $businessNumber
    . "?text=" . rawurlencode($message);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Received | Harsh Taxi</title>
<style>
* { box-sizing: border-box; }
body {
    background: #171819;
    color: white;
    font-family: Arial, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    padding: 20px;
}
.box {
    background: #252729;
    border-top: 5px solid #ffbf00;
    border-radius: 12px;
    padding: 30px 20px;
    text-align: center;
    max-width: 430px;
    width: 100%;
}
h2 { color: #ffbf00; }
p { color: #ddd; line-height: 1.6; }
a {
    display: inline-block;
    text-decoration: none;
    padding: 14px 18px;
    margin: 7px 3px;
    font-weight: bold;
    border-radius: 4px;
}
.whatsapp { background: #20c76a; color: white; }
.home { background: #ffbf00; color: #171819; }
</style>
</head>
<body>

<div class="box">
    <h2>Booking Received!</h2>
    <p>Thank you, <?php echo htmlspecialchars($name); ?>.</p>

    <p>Your booking has been saved successfully.</p>

    <p>Please send your booking details to Harsh Taxi on WhatsApp.</p>

    <a class="whatsapp" href="<?php echo htmlspecialchars($whatsappURL, ENT_QUOTES); ?>">
        SEND BOOKING ON WHATSAPP
    </a>

    <br>

    <a class="home" href="index.html">BACK TO HOME</a>
</div>

<script>
window.location.href = <?php echo json_encode($whatsappURL); ?>;
</script>

</body>
</html>
