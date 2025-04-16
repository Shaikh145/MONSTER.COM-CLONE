<?php
// Database connection parameters
$host = "localhost";
$database = "dbca5l2mdjg2yo";
$user = "uklz9ew3hrop3";
$password = "zyrbspyjlzjb";

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");

// Function to sanitize input data
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to execute queries
function executeQuery($sql) {
    global $conn;
    $result = $conn->query($sql);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    return $result;
}

// Function to get a single row
function getRow($sql) {
    $result = executeQuery($sql);
    return $result->fetch_assoc();
}

// Function to get multiple rows
function getRows($sql) {
    $result = executeQuery($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

// Function to insert data and return the inserted ID
function insertData($sql) {
    global $conn;
    if ($conn->query($sql) === TRUE) {
        return $conn->insert_id;
    } else {
        die("Error: " . $sql . "<br>" . $conn->error);
    }
}
?>
