<?php
session_start();

// Check if we have both booking and payment data
if (!isset($_SESSION['pending_booking']) || !isset($_SESSION['payment_data'])) {
    header('Location: index.html');
    exit;
}

$booking_data = $_SESSION['pending_booking'];
$payment_data = $_SESSION['payment_data'];

$host = 'localhost';
$db = 'hotel_db';
$user = 'root';
$pass = '';

// Connect directly to the database
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("<h2 style='color:red;'>Connection failed: " . htmlspecialchars($conn->connect_error) . "</h2>");
}

// Check if bookings table exists, create if it doesn't
$table_check = $conn->query("SHOW TABLES LIKE 'bookings'");
if ($table_check->num_rows == 0) {
    $create_table_sql = "CREATE TABLE bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guest_name VARCHAR(255) NOT NULL,
        room_type VARCHAR(100) NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        total_amount DECIMAL(10,2) DEFAULT 0,
        payment_status VARCHAR(50) DEFAULT 'pending',
        transaction_id VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($create_table_sql)) {
        die("<h2 style='color:red;'>Error creating bookings table: " . htmlspecialchars($conn->error) . "</h2>");
    }
} else {
    // Check if payment columns exist, add if they don't
    $columns_to_add = [
        'total_amount' => "ALTER TABLE bookings ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0",
        'payment_status' => "ALTER TABLE bookings ADD COLUMN payment_status VARCHAR(50) DEFAULT 'pending'",
        'transaction_id' => "ALTER TABLE bookings ADD COLUMN transaction_id VARCHAR(100)",
        'created_at' => "ALTER TABLE bookings ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];
    
    foreach ($columns_to_add as $column => $sql) {
        $column_check = $conn->query("SHOW COLUMNS FROM bookings LIKE '$column'");
        if ($column_check->num_rows == 0) {
            $conn->query($sql);
        }
    }
}

// Prepare booking insertion
$stmt = $conn->prepare("INSERT INTO bookings (guest_name, room_type, check_in, check_out, total_amount, payment_status, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    die("<h2 style='color:red;'>Error preparing statement: " . htmlspecialchars($conn->error) . "</h2>");
}

// Bind parameters
$payment_status = 'paid';
$stmt->bind_param("ssssdss", 
    $booking_data['guest_name'],
    $booking_data['room_type'],
    $booking_data['check_in'],
    $booking_data['check_out'],
    $payment_data['amount'],
    $payment_status,
    $payment_data['transaction_id']
);

// Execute the statement
if ($stmt->execute()) {
    $booking_id = $conn->insert_id;
    
    // Clear session data
    unset($_SESSION['pending_booking']);
    unset($_SESSION['payment_data']);
    
    // Show success page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Booking Confirmed - Hotel Management</title>
      <link rel="stylesheet" href="style.css">
      <style>
        body {
          background: #181c24 !important;
          color: #f4f4f4;
        }
        .container {
          background: #232a36 !important;
          color: #f4f4f4;
          max-width: 600px;
          text-align: center;
        }
        .success-card {
          background: #1e5f46;
          color: #d1fae5;
          padding: 30px;
          border-radius: 12px;
          margin: 20px 0;
          border-left: 5px solid #10b981;
        }
        .success-icon {
          font-size: 4rem;
          margin-bottom: 20px;
        }
        .booking-details {
          background: #2d3748;
          padding: 20px;
          border-radius: 8px;
          margin: 20px 0;
          text-align: left;
        }
        .detail-row {
          display: flex;
          justify-content: space-between;
          margin: 10px 0;
          padding: 8px 0;
          border-bottom: 1px solid #4a5568;
        }
        .detail-row:last-child {
          border-bottom: none;
          font-weight: bold;
          color: #10b981;
        }
        .action-buttons {
          margin: 30px 0;
          display: flex;
          gap: 15px;
          justify-content: center;
          flex-wrap: wrap;
        }
        .btn {
          background: #007bff;
          color: #fff;
          padding: 12px 24px;
          text-decoration: none;
          border-radius: 6px;
          font-weight: bold;
          transition: background 0.2s;
          display: inline-block;
        }
        .btn:hover {
          background: #0056b3;
          text-decoration: none;
          color: #fff;
        }
        .btn-success {
          background: #28a745;
        }
        .btn-success:hover {
          background: #218838;
        }
        .btn-info {
          background: #17a2b8;
        }
        .btn-info:hover {
          background: #138496;
        }
        @media (max-width: 600px) {
          .action-buttons {
            flex-direction: column;
            align-items: center;
          }
          .btn {
            width: 200px;
            text-align: center;
          }
        }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="success-card">
          <div class="success-icon">✅</div>
          <h1>Booking Confirmed!</h1>
          <p>Thank you for your reservation. Your booking has been successfully confirmed and payment has been processed.</p>
        </div>
        
        <div class="booking-details">
          <h3>Booking Summary</h3>
          <div class="detail-row">
            <span>Booking ID:</span>
            <span>#<?= htmlspecialchars($booking_id) ?></span>
          </div>
          <div class="detail-row">
            <span>Guest Name:</span>
            <span><?= htmlspecialchars($booking_data['guest_name']) ?></span>
          </div>
          <div class="detail-row">
            <span>Room Type:</span>
            <span><?= htmlspecialchars($booking_data['room_type']) ?></span>
          </div>
          <div class="detail-row">
            <span>Check-in Date:</span>
            <span><?= date('M d, Y', strtotime($booking_data['check_in'])) ?></span>
          </div>
          <div class="detail-row">
            <span>Check-out Date:</span>
            <span><?= date('M d, Y', strtotime($booking_data['check_out'])) ?></span>
          </div>
          <div class="detail-row">
            <span>Transaction ID:</span>
            <span><?= htmlspecialchars($payment_data['transaction_id']) ?></span>
          </div>
          <div class="detail-row">
            <span>Total Paid:</span>
            <span>RM<?= number_format($payment_data['amount'], 2) ?></span>
          </div>
        </div>
        
        <div class="action-buttons">
          <a href="receipt.php?id=<?= $booking_id ?>" class="btn btn-success">📄 View Receipt</a>
          <a href="user_bookings.php" class="btn btn-info">📋 My Bookings</a>
          <a href="index.html" class="btn">🏨 Book Another Room</a>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #1e3a5f; border-radius: 8px; color: #e3f2fd;">
          <h4>Important Information:</h4>
          <ul style="text-align: left; line-height: 1.6;">
            <li>Please save your booking confirmation number: <strong>#<?= htmlspecialchars($booking_id) ?></strong></li>
            <li>Check-in time: 3:00 PM | Check-out time: 11:00 AM</li>
            <li>Please bring a valid ID for check-in</li>
            <li>For any changes or cancellations, contact us at (555) 123-4567</li>
          </ul>
        </div>
      </div>
    </body>
    </html>
    <?php
} else {
    // Error occurred
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Booking Error</title>
      <link rel="stylesheet" href="style.css">
      <style>
        body { background: #181c24; color: #f4f4f4; }
        .container { background: #232a36; color: #f4f4f4; max-width: 500px; text-align: center; }
        .error-card { background: #7f1d1d; color: #fecaca; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .back-link { color: #007bff; text-decoration: none; padding: 10px 20px; background: #1e3a5f; border-radius: 4px; display: inline-block; margin: 10px 5px; }
        .back-link:hover { background: #007bff; color: #fff; text-decoration: none; }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="error-card">
          <h2>❌ Booking Error</h2>
          <p>Sorry, there was an error processing your booking: <?= htmlspecialchars($stmt->error) ?></p>
          <p>Your payment was not processed. Please try again.</p>
        </div>
        <div>
          <a href="index.html" class="back-link">Back to Booking Form</a>
          <a href="payment.php" class="back-link">Try Payment Again</a>
        </div>
      </div>
    </body>
    </html>
    <?php
}

$stmt->close();
$conn->close();
?>