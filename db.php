<?php

$host = "127.0.0.1";
$username = "root";
$password = "root";
$database = "harsh_taxi";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    3306
);

if ($conn->connect_error) {
    die("Database connection failed. Please check db.php settings.");
}

$conn->set_charset("utf8mb4");

?>
