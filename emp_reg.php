<?php include "header.php"; ?>
<?php include "navbar.php"; ?>

<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "pharmacy");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$success = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $emp_id     = $conn->real_escape_string($_POST['emp_id']);
    $emp_name   = $conn->real_escape_string($_POST['emp_name']);
    $emp_email  = $conn->real_escape_string($_POST['emp_email']);
    $emp_phone  = $conn->real_escape_string($_POST['emp_phone']);
    $emp_position = $conn->real_escape_string($_POST['emp_position']);
    $emp_address = $conn->real_escape_string($_POST['emp_address']);

    // Check if Employee ID already exists
    $check = $conn->query("SELECT emp_id FROM employee WHERE emp_id='$emp_id'");
    if ($check->num_rows > 0) {
        $success = "<span style='color:red;'>Error: Employee ID already exists!</span>";
    } else {
        $sql = "INSERT INTO employee (emp_id, emp_name, emp_email, emp_phone, emp_position, emp_address) 
                VALUES ('$emp_id', '$emp_name', '$emp_email', '$emp_phone', '$emp_position', '$emp_address')";
        if ($conn->query($sql) === TRUE) {
            $success = "<span style='color:green;'>Employee registered successfully!</span>";
        } else {
            $success = "<span style='color:red;'>Error: " . $conn->error . "</span>";
        }
    }
}
?>

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f8f9fa;
}
.form-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 70vh;
    padding: 20px;
}
.form-box {
    background: white;
    padding: 30px;
    border-radius: 8px;
    width: 400px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.form-box h2 {
    text-align: center;
    color: #009688;
    margin-bottom: 20px;
}
.form-box input, .form-box textarea, .form-box button, .form-box select {
    width: 100%;
    padding: 10px;
    margin: 8px 0;
    border-radius: 4px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}
.form-box button {
    background: #009688;
    color: white;
    border: none;
    cursor: pointer;
    font-size: 16px;
}
.form-box button:hover {
    background: #00796b;
}
.success-msg {
    text-align: center;
    margin-bottom: 15px;
}
</style>

<div class="form-container">
    <div class="form-box">
        <h2>Employee Registration</h2>
        <?php if ($success) echo "<div class='success-msg'>$success</div>"; ?>
        <form method="POST">
            <label>Employee ID:</label>
            <input type="text" name="emp_id" required placeholder="Enter unique Employee ID">

            <label>Name:</label>
            <input type="text" name="emp_name" required>

            <label>Email:</label>
            <input type="email" name="emp_email" required>

            <label>Phone:</label>
            <input type="text" name="emp_phone" required>

            <label>Position:</label>
            <select name="emp_position" required>
                <option value="">-- Select Position --</option>
                <option value="Manager">Manager</option>
                <option value="Pharmacist">Pharmacist</option>
                <option value="Salesman">Salesman</option>
                <option value="Delivery">Delivery</option>
            </select>

            <label>Address:</label>
            <textarea name="emp_address" required></textarea>

            <button type="submit">Register</button>
        </form>
    </div>
</div>

<?php include "footer.php"; ?>
