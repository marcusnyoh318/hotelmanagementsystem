<?php
session_start();

// Check if booking data exists in session
if (!isset($_SESSION['pending_booking'])) {
    header('Location: index.html');
    exit;
}

$booking_data = $_SESSION['pending_booking'];
$payment_error = '';

// Calculate total cost based on room type and nights
$room_prices = [
    'Single' => 100,
    'Double' => 150,
    'Suite' => 250
];

$check_in = new DateTime($booking_data['check_in']);
$check_out = new DateTime($booking_data['check_out']);
$nights = $check_in->diff($check_out)->days;
$room_price = $room_prices[$booking_data['room_type']] ?? 100;
$subtotal = $room_price * $nights;
$tax = $subtotal * 0.10; // 10% tax
$total = $subtotal + $tax;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate payment form
    $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $expiry_date = $_POST['expiry_date'] ?? '';
    $card_name = trim($_POST['card_name'] ?? '');
    $cvv = $_POST['cvv'] ?? '';
    
    // Basic validation
    if (empty($card_number) || empty($expiry_date) || empty($card_name) || empty($cvv)) {
        $payment_error = 'All payment fields are required.';
    } elseif (!preg_match('/^\d{16}$/', $card_number)) {
        $payment_error = 'Card number must be 16 digits.';
    } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry_date)) {
        $payment_error = 'Expiry date must be in MM/YY format.';
    } elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
        $payment_error = 'CVV must be 3 or 4 digits.';
    } elseif (strlen($card_name) < 2) {
        $payment_error = 'Name on card is required.';
    } else {
        // Store payment info in session (in real app, this would go to payment processor)
        $_SESSION['payment_data'] = [
            'card_number' => '**** **** **** ' . substr($card_number, -4),
            'card_name' => $card_name,
            'amount' => $total,
            'transaction_id' => 'TXN' . time() . rand(100, 999)
        ];
        
        // Redirect to process booking
        header('Location: process_booking.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment - Hotel Management</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      background: #181c24 !important;
      color: #f4f4f4;
    }
    .container {
      background: #232a36 !important;
      color: #f4f4f4;
      max-width: 800px;
    }
    .payment-container {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      margin-top: 20px;
    }
    .booking-summary {
      background: #2d3748;
      padding: 25px;
      border-radius: 12px;
      border-left: 4px solid #007bff;
    }
    .booking-summary h3 {
      color: #007bff;
      margin-top: 0;
      margin-bottom: 20px;
    }
    .summary-row {
      display: flex;
      justify-content: space-between;
      margin: 10px 0;
      padding: 8px 0;
      border-bottom: 1px solid #4a5568;
    }
    .summary-row:last-child {
      border-bottom: none;
      font-weight: bold;
      font-size: 1.1em;
      color: #007bff;
      border-top: 2px solid #007bff;
      padding-top: 15px;
      margin-top: 15px;
    }
    .payment-form {
      background: #2d3748;
      padding: 25px;
      border-radius: 12px;
    }
    .payment-form h3 {
      color: #007bff;
      margin-top: 0;
      margin-bottom: 20px;
    }
    .form-row {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 15px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      color: #cbd5e0;
      margin-bottom: 5px;
      font-weight: bold;
    }
    .form-group input {
      width: 100%;
      padding: 12px;
      border: 1px solid #4a5568;
      border-radius: 6px;
      background: #1a202c;
      color: #f4f4f4;
      font-size: 16px;
    }
    .form-group input:focus {
      border-color: #007bff;
      background: #2d3748;
      outline: none;
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

    .submit-btn {
      background: linear-gradient(90deg, #28a745 0%, #20c997 100%) !important;
      color: #fff !important;
      padding: 15px 30px;
      font-size: 18px;
      font-weight: bold;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      width: 100%;
      margin-top: 20px;
      transition: all 0.2s;
    }
    .submit-btn:hover {
      background: linear-gradient(90deg, #218838 0%, #1e7e6b 100%) !important;
      transform: translateY(-2px);
    }
    .submit-btn:disabled {
      background: #6c757d !important;
      cursor: not-allowed;
      transform: none;
    }
    .back-link {
      text-align: center;
      margin-top: 20px;
    }
    .back-link a {
      color: #007bff;
      text-decoration: none;
      padding: 8px 16px;
      border: 1px solid #007bff;
      border-radius: 4px;
      transition: all 0.2s;
    }
    .back-link a:hover {
      background: #007bff;
      color: #fff;
      text-decoration: none;
    }
    .security-info {
      background: #1e3a5f;
      color: #e3f2fd;
      padding: 15px;
      border-radius: 8px;
      margin: 20px 0;
      font-size: 14px;
      text-align: center;
    }
    .security-info strong {
      color: #2196f3;
    }
    @media (max-width: 768px) {
      .payment-container {
        grid-template-columns: 1fr;
        gap: 20px;
      }
      .form-row {
        grid-template-columns: 1fr;
      }
      .container {
        padding: 15px;
      }
    }
  </style>
  <script>
    // Format card number input
    function formatCardNumber(input) {
      let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
      let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
      if (formattedValue.length > 19) formattedValue = formattedValue.substring(0, 19);
      input.value = formattedValue;
    }
    
    // Format expiry date
    function formatExpiry(input) {
      let value = input.value.replace(/\D/g, '');
      if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
      }
      input.value = value;
    }
    
    // Only allow numbers for CVV
    function formatCVV(input) {
      input.value = input.value.replace(/[^0-9]/g, '').substring(0, 4);
    }
  </script>
</head>
<body>
  <div class="container">
    <h1>Complete Your Payment</h1>
    
    <?php if ($payment_error): ?>
      <div class="error-message"><?= htmlspecialchars($payment_error) ?></div>
    <?php endif; ?>
    
    <div class="payment-container">
      <!-- Booking Summary -->
      <div class="booking-summary">
        <h3>📋 Booking Summary</h3>
        <div class="summary-row">
          <span>Guest Name:</span>
          <span><?= htmlspecialchars($booking_data['guest_name']) ?></span>
        </div>
        <div class="summary-row">
          <span>Room Type:</span>
          <span><?= htmlspecialchars($booking_data['room_type']) ?></span>
        </div>
        <div class="summary-row">
          <span>Check-in:</span>
          <span><?= date('M d, Y', strtotime($booking_data['check_in'])) ?></span>
        </div>
        <div class="summary-row">
          <span>Check-out:</span>
          <span><?= date('M d, Y', strtotime($booking_data['check_out'])) ?></span>
        </div>
        <div class="summary-row">
          <span>Number of Nights:</span>
          <span><?= $nights ?></span>
        </div>
        <div class="summary-row">
          <span>Room Rate (per night):</span>
          <span>RM<?= number_format($room_price, 2) ?></span>
        </div>
        <div class="summary-row">
          <span>Subtotal:</span>
          <span>RM<?= number_format($subtotal, 2) ?></span>
        </div>
        <div class="summary-row">
          <span>Tax (10%):</span>
          <span>RM<?= number_format($tax, 2) ?></span>
        </div>
        <div class="summary-row">
          <span>Total Amount:</span>
          <span>RM<?= number_format($total, 2) ?></span>
        </div>
      </div>
      
      <!-- Payment Form -->
      <div class="payment-form">
        <h3>💳 Payment Information</h3>
        
        <div class="security-info">
          <strong>🔒 Secure Payment</strong><br>
          Your payment information is encrypted and secure.
        </div>
        
        <form id="paymentForm" method="POST">
          <div class="form-group">
            <label for="card_number">Card Number</label>
            <input type="text" 
                   id="card_number" 
                   name="card_number" 
                   placeholder="1234 5678 9012 3456"
                   maxlength="19"
                   oninput="formatCardNumber(this)"
                   required>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="expiry_date">Expiry Date</label>
              <input type="text" 
                     id="expiry_date" 
                     name="expiry_date" 
                     placeholder="MM/YY"
                     maxlength="5"
                     oninput="formatExpiry(this)"
                     required>
            </div>
            <div class="form-group">
              <label for="cvv">CVV</label>
              <input type="text" 
                     id="cvv" 
                     name="cvv" 
                     placeholder="123"
                     maxlength="4"
                     oninput="formatCVV(this)"
                     required>
            </div>
          </div>
          
          <div class="form-group">
            <label for="card_name">Name on Card</label>
            <input type="text" 
                   id="card_name" 
                   name="card_name" 
                   placeholder="John Doe"
                   style="text-transform: uppercase;"
                   required>
          </div>
          
          <button type="submit" id="submitBtn" class="submit-btn">
            💳 Pay RM<?= number_format($total, 2) ?>
          </button>
        </form>
      </div>
    </div>
    
    <div class="back-link">
      <a href="index.html">← Back to Booking Form</a>
    </div>
  </div>
</body>
</html>