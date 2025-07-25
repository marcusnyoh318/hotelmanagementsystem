<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  header('Location: admin_login.php');
  exit;
}

$host = 'localhost';
$db = 'hotel_db';
$user = 'root';
$pass = '';

// Try connecting to MySQL server first
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Check if database exists
$db_selected = $conn->select_db($db);
if (!$db_selected) {
  echo "<h2 style='color:red;'>Database '$db' does not exist. Please run create_db.php first.</h2>";
  echo "<div style='text-align:center; margin-top:20px;'>";
  echo "<a href='create_db.php'>Create Database</a> | ";
  echo "<a href='index.html'>Back to Booking Page</a>";
  echo "</div>";
  exit;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $delete_id = intval($_POST['delete_id']);
  if ($delete_id > 0) {
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    if ($stmt) {
      $stmt->bind_param("i", $delete_id);
      if ($stmt->execute()) {
        $success_message = "Booking deleted successfully.";
      } else {
        $error_message = "Error deleting booking: " . $stmt->error;
      }
      $stmt->close();
    } else {
      $error_message = "Error preparing delete statement: " . $conn->error;
    }
  }
}

// Fetch bookings with error handling
$result = null;
$bookings_error = '';
try {
  $result = $conn->query("SELECT * FROM bookings ORDER BY created_at DESC");
  if (!$result) {
    $bookings_error = "Error fetching bookings: " . $conn->error;
  }
} catch (Exception $e) {
  $bookings_error = "Database error: " . $e->getMessage();
}

// Load users from users.txt
$usersFile = __DIR__ . '/users.txt';
$users = [];
$users_error = '';
if (file_exists($usersFile)) {
  $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines === false) {
    $users_error = "Error reading users file.";
  } else {
    foreach ($lines as $line) {
      $parts = explode(':', $line, 2);
      if (count($parts) === 2) {
        list($uname, $phash) = $parts;
        $users[] = ['username' => trim($uname), 'password' => trim($phash)];
      }
    }
  }
} else {
  $users_error = "Users file not found.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - View Bookings</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .delete-btn {
      background: #e74c3c;
      color: #fff;
      border: none;
      padding: 6px 14px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.2s;
    }
    .delete-btn:hover {
      background: #c0392b;
    }
    .delete-btn:disabled {
      background: #bdc3c7;
      cursor: not-allowed;
    }
    form.delete-form {
      display: inline;
    }
    .user-table {
      margin-top: 40px;
      margin-bottom: 20px;
    }
    .user-table th, .user-table td {
      font-size: 14px;
      word-break: break-all;
    }
    .success-message {
      background: #d4edda;
      color: #155724;
      padding: 12px;
      border: 1px solid #c3e6cb;
      border-radius: 6px;
      margin: 20px 0;
      text-align: center;
    }
    .error-message {
      background: #f8d7da;
      color: #721c24;
      padding: 12px;
      border: 1px solid #f5c6cb;
      border-radius: 6px;
      margin: 20px 0;
      text-align: center;
    }
    .logout-btn {
      position: absolute;
      top: 20px;
      right: 20px;
      background: #dc3545;
      color: #fff;
      padding: 8px 16px;
      text-decoration: none;
      border-radius: 4px;
      font-size: 14px;
      transition: background 0.2s;
    }
    .logout-btn:hover {
      background: #c82333;
      text-decoration: none;
      color: #fff;
    }
    .stats {
      display: flex;
      gap: 20px;
      margin: 20px 0;
      justify-content: center;
      flex-wrap: wrap;
    }
    .stat-card {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      text-align: center;
      min-width: 150px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stat-number {
      font-size: 2rem;
      font-weight: bold;
      color: #007bff;
    }
    .stat-label {
      color: #666;
      font-size: 0.9rem;
      margin-top: 5px;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="admin_logout.php" class="logout-btn">Logout</a>
    <h1>Admin Panel</h1>
    
    <?php if (isset($success_message)): ?>
      <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
      <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats">
      <div class="stat-card">
        <div class="stat-number"><?= $result ? $result->num_rows : 0 ?></div>
        <div class="stat-label">Total Bookings</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= count($users) ?></div>
        <div class="stat-label">Registered Users</div>
      </div>
    </div>

    <h2>All Bookings</h2>
    
    <?php if ($bookings_error): ?>
      <div class="error-message"><?= htmlspecialchars($bookings_error) ?></div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Guest Name</th>
            <th>Room Type</th>
            <th>Check-In</th>
            <th>Check-Out</th>
            <th>Booked At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['guest_name']) ?></td>
                <td><?= htmlspecialchars($row['room_type']) ?></td>
                <td><?= htmlspecialchars($row['check_in']) ?></td>
                <td><?= htmlspecialchars($row['check_out']) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td>
                  <form method="POST" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this booking for <?= htmlspecialchars($row['guest_name']) ?>?');">
                    <input type="hidden" name="delete_id" value="<?= htmlspecialchars($row['id']) ?>">
                    <button type="submit" class="delete-btn">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7">No bookings found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h2 style="margin-top:40px;">Registered Users</h2>
    
    <?php if ($users_error): ?>
      <div class="error-message"><?= htmlspecialchars($users_error) ?></div>
    <?php else: ?>
      <table class="user-table">
        <thead>
          <tr>
            <th>Username</th>
            <th>Password Hash</th>
            <th>Registration Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars(substr($u['password'], 0, 30) . '...') ?></td>
                <td>
                  <span style="color: #28a745; font-weight: bold;">✓ Active</span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="3">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div style="text-align:center; margin-top:30px;">
      <a href="index.html" style="margin-right: 15px;">Back to Booking Page</a>
    </div>
  </div>
</body>
</html>

<?php 
if ($conn) {
  $conn->close(); 
}
?>