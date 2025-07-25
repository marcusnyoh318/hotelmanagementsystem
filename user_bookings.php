<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['username'])) {
    // If not logged in, redirect to user login with a message
    header('Location: user_login.php?message=Please log in to view your bookings');
    exit;
}

$guestName = $_SESSION['username'];

$host = 'localhost';
$db = 'hotel_db';
$user = 'root';
$pass = '';

$error_message = '';
$result = null;
$bookings = [];

// Try to connect to database
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    $error_message = "Database connection failed: " . $conn->connect_error;
} else {
    // Check if bookings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'bookings'");
    if ($table_check->num_rows == 0) {
        $error_message = "Bookings table doesn't exist. Please contact administrator to set up the database.";
    } else {
        // First check table structure and add created_at column if missing
        $structure_check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'created_at'");
        if ($structure_check->num_rows == 0) {
            // Add created_at column if it doesn't exist
            $conn->query("ALTER TABLE bookings ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        
        // Use prepared statement to get user's bookings
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE guest_name = ? ORDER BY id DESC");
        if ($stmt) {
            $stmt->bind_param("s", $guestName);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $bookings[] = $row;
                    }
                }
            } else {
                $error_message = "Error retrieving bookings: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing database query: " . $conn->error;
        }
    }
}

// Calculate statistics
$total_bookings = count($bookings);
$upcoming_bookings = 0;
$past_bookings = 0;
$ongoing_bookings = 0;
$current_date = date('Y-m-d');

foreach ($bookings as $booking) {
    $check_in = $booking['check_in'];
    $check_out = $booking['check_out'];
    
    if ($current_date < $check_in) {
        $upcoming_bookings++;
    } elseif ($current_date >= $check_in && $current_date <= $check_out) {
        $ongoing_bookings++;
    } else {
        $past_bookings++;
    }
}

// Function to get booking status
function getBookingStatus($check_in, $check_out) {
    $current_date = date('Y-m-d');
    
    if ($current_date < $check_in) {
        return ['status' => 'upcoming', 'text' => 'Upcoming'];
    } elseif ($current_date >= $check_in && $current_date <= $check_out) {
        return ['status' => 'ongoing', 'text' => 'Ongoing'];
    } else {
        return ['status' => 'past', 'text' => 'Completed'];
    }
}

// Function to format date safely
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return 'N/A';
    
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'Invalid Date';
    
    return date($format, $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Bookings - <?= htmlspecialchars($guestName) ?></title>
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      background: #181c24 !important;
      color: #f4f4f4;
      margin: 0;
      padding: 0;
    }
    
    .container {
      background: #232a36 !important;
      color: #f4f4f4;
      position: relative;
      min-height: 100vh;
      padding: 20px;
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
      border: none;
      cursor: pointer;
    }
    .logout-btn:hover {
      background: #c82333;
      text-decoration: none;
      color: #fff;
    }
    
    h1 {
      color: #fff;
      text-align: center;
      margin: 60px 0 20px 0;
    }
    
    .welcome-message {
      text-align: center;
      background: #1e3a5f;
      padding: 15px;
      border-radius: 8px;
      margin: 20px 0;
      border-left: 4px solid #2196f3;
      color: #e3f2fd;
    }
    
    .stats {
      display: flex;
      gap: 20px;
      margin: 20px 0;
      justify-content: center;
      flex-wrap: wrap;
    }
    .stat-card {
      background: #2d3748;
      padding: 20px;
      border-radius: 8px;
      text-align: center;
      min-width: 120px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.3);
      color: #f4f4f4;
    }
    .stat-number {
      font-size: 1.8rem;
      font-weight: bold;
      color: #007bff;
    }
    .stat-label {
      color: #cbd5e0;
      font-size: 0.9rem;
      margin-top: 5px;
    }
    
    table {
      width: 100%;
      background: #2d3748;
      border-radius: 8px;
      overflow: hidden;
      margin: 20px 0;
      border-collapse: collapse;
    }
    
    th, td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #4a5568;
      color: #000000ff;
    }
    
    th {
      background: #1a202c;
      font-weight: bold;
      color: #fff;
    }
    
    tr:hover {
      background: #374151;
    }
    
    .booking-status {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
    }
    .status-upcoming {
      background: #065f46;
      color: #d1fae5;
    }
    .status-ongoing {
      background: #92400e;
      color: #fef3c7;
    }
    .status-past {
      background: #7f1d1d;
      color: #fecaca;
    }
    
    .error-message {
      background: #7f1d1d;
      color: #fecaca;
      padding: 12px;
      border: 1px solid #991b1b;
      border-radius: 6px;
      margin: 20px 0;
      text-align: center;
    }
    
    .no-bookings {
      text-align: center;
      padding: 40px;
      color: #cbd5e0;
      background: #2d3748;
      border-radius: 8px;
      margin: 20px 0;
    }
    .no-bookings h3 {
      color: #007bff;
      margin-bottom: 15px;
    }
    
    .book-now-btn, .action-btn {
      background: #28a745;
      color: #fff;
      padding: 12px 24px;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
      transition: background 0.2s;
      display: inline-block;
      margin: 5px;
      border: none;
      cursor: pointer;
    }
    .book-now-btn:hover, .action-btn:hover {
      background: #218838;
      text-decoration: none;
      color: #fff;
    }
    
    .action-links {
      text-align: center;
      margin-top: 30px;
    }
    
    .action-links a {
      color: #007bff;
      text-decoration: none;
      margin: 0 15px;
      padding: 8px 16px;
      border: 1px solid #007bff;
      border-radius: 4px;
      transition: all 0.2s;
    }
    
    .action-links a:hover {
      background: #007bff;
      color: #fff;
      text-decoration: none;
    }
    
    .debug-info {
      background: rgba(0,0,0,0.8);
      color: white;
      padding: 10px;
      border-radius: 4px;
      font-size: 12px;
      margin-top: 20px;
      max-width: 100%;
    }
    
    @media (max-width: 768px) {
      .container {
        padding: 10px;
      }
      
      .logout-btn {
        position: static;
        display: block;
        width: fit-content;
        margin: 10px auto 20px auto;
      }
      
      h1 {
        margin: 20px 0;
      }
      
      .stats {
        flex-direction: column;
        align-items: center;
      }
      .stat-card {
        width: 100%;
        max-width: 200px;
      }
      
      table {
        font-size: 14px;
      }
      
      th, td {
        padding: 8px 4px;
      }
      
      .action-links {
        flex-direction: column;
      }
      
      .action-links a {
        display: block;
        margin: 5px 0;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="user_logout.php" class="logout-btn">Logout</a>
    
    <h1>My Bookings</h1>
    
    <div class="welcome-message">
      <strong>Welcome back, <?= htmlspecialchars($guestName) ?>!</strong><br>
      Here are all your hotel bookings.
    </div>

    <?php if ($error_message): ?>
      <div class="error-message">
        <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
        <br><br>
        <a href="create_db.php" class="action-btn">Set up database</a>
        <a href="user_login.php" class="action-btn">Login Again</a>
        <a href="index.html" class="action-btn">Back to Home</a>
      </div>
    <?php else: ?>
      <!-- Statistics -->
      <div class="stats">
        <div class="stat-card">
          <div class="stat-number"><?= $total_bookings ?></div>
          <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= $upcoming_bookings ?></div>
          <div class="stat-label">Upcoming</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= $ongoing_bookings ?></div>
          <div class="stat-label">Ongoing</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= $past_bookings ?></div>
          <div class="stat-label">Past Bookings</div>
        </div>
      </div>

      <?php if ($total_bookings > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Room Type</th>
              <th>Check-In</th>
              <th>Check-Out</th>
              <th>Booked At</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($bookings as $booking): ?>
              <?php 
                $status_info = getBookingStatus($booking['check_in'], $booking['check_out']);
                $created_at = isset($booking['created_at']) ? $booking['created_at'] : '';
              ?>
              <tr>
                <td>#<?= htmlspecialchars($booking['id']) ?></td>
                <td><?= htmlspecialchars($booking['room_type']) ?></td>
                <td><?= formatDate($booking['check_in']) ?></td>
                <td><?= formatDate($booking['check_out']) ?></td>
                <td><?= formatDate($created_at, 'M d, Y g:i A') ?></td>
                <td>
                  <span class="booking-status status-<?= $status_info['status'] ?>">
                    <?= $status_info['text'] ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="no-bookings">
          <h3>No Bookings Yet</h3>
          <p>You haven't made any bookings yet. Start by making your first reservation!</p>
          <a href="index.html" class="book-now-btn">Book Now</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="action-links">
      <a href="index.html">Make New Booking</a>
      <a href="user_login.php">Switch Account</a>
    </div>

    <!-- Debug Info (remove in production) -->
    <div class="debug-info">
      <strong>Debug Info:</strong><br>
      Session Status: <?= isset($_SESSION['user_logged_in']) ? 'Active' : 'None' ?><br>
      Username: <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Not set' ?><br>
      Guest Name: <?= htmlspecialchars($guestName ?? 'Not set') ?><br>
      Total Bookings Found: <?= $total_bookings ?><br>
      Database Connection: <?= $error_message ? 'Failed' : 'Success' ?>
    </div>
  </div>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>