<?php
// booking_submit.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php'; // Composer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;

// ===== 1. DB CONNECTION =====
$dsn = "mysql:host=127.0.0.1;port=3307;dbname=wellness_db;charset=utf8mb4";
$user = "root";
$pass = "";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// ===== 2. COLLECT POST DATA =====
$company   = $_POST['company-name'] ?? '';
$contact   = $_POST['contact-person'] ?? '';
$email     = $_POST['email'] ?? '';
$phone     = $_POST['phone'] ?? '';
$location  = $_POST['location'] ?? '';
$employees = $_POST['employees'] ?? 0;
$date      = $_POST['date'] ?? '';
$time      = $_POST['time'] ?? '';
$package   = $_POST['package'] ?? '';

// ===== 3. VALIDATION =====
if (!$company || !$contact || !$email || !$phone || !$location || !$employees || !$date || !$time || !$package) {
    die("❌ Missing required fields");
}

// ===== 4. CLIENT FETCH / INSERT =====
$stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
$stmt->execute([$email]);
$client = $stmt->fetch();

if ($client) {
    $client_id = $client['id'];
} else {
    $stmt = $pdo->prepare("INSERT INTO clients (company_name, contact_person, email, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute([$company, $contact, $email, $phone]);
    $client_id = $pdo->lastInsertId();
}

// ===== 5. PACKAGE LOOKUP =====
$package_full = "Corporate-" . $package;
$stmt = $pdo->prepare("SELECT id, package_name, price FROM packages WHERE package_name = ?");
$stmt->execute([$package_full]);
$pkg = $stmt->fetch();

if (!$pkg) {
    die("❌ Invalid package selected: " . htmlspecialchars($package_full));
}
$package_id    = $pkg['id'];
$package_name  = $pkg['package_name'];
$package_price = $pkg['price'];

// ===== 6. INSERT BOOKING =====
$stmt = $pdo->prepare("INSERT INTO bookings (client_id, package_id, location, employees, preferred_date, preferred_time) 
                       VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$client_id, $package_id, $location, $employees, $date, $time]);
$booking_id = $pdo->lastInsertId();

// ===== 7. PAYMENT METHOD FETCH =====
$pay = $pdo->query("SELECT * FROM payment_methods LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [
    'bank_name'        => '---',
    'account_name'     => '---',
    'account_number'   => '---',
    'mpesa_paybill'    => '---',
    'mpesa_till_number'=> '---',
];

// ===== 8. GENERATE PDF INVOICE =====
$invoiceHtml = "
<h2>Wellness Check Ke - Invoice</h2>
<p><strong>Booking ID:</strong> #$booking_id</p>
<p><strong>Client:</strong> $contact ($company)</p>
<p><strong>Email:</strong> $email</p>
<p><strong>Phone:</strong> $phone</p>

<h3>Booking Details</h3>
<ul>
  <li><strong>Package:</strong> $package_name</li>
  <li><strong>Date & Time:</strong> $date $time</li>
  <li><strong>Location:</strong> $location</li>
  <li><strong>Participants:</strong> $employees</li>
</ul>

<h3>Invoice</h3>
<table border='1' cellpadding='8' cellspacing='0'>
<tr><th>Description</th><th>Package</th><th>Amount (KES)</th></tr>
<tr><td>Wellness Screening</td><td>$package_name</td><td>$package_price</td></tr>
<tr><td colspan='2'><strong>Total</strong></td><td><strong>$package_price</strong></td></tr>
</table>

<h3>Payment Instructions</h3>
<p>
Bank: {$pay['bank_name']}<br>
Account Name: {$pay['account_name']}<br>
Account Number: {$pay['account_number']}<br>
M-Pesa Paybill: {$pay['mpesa_paybill']}<br>
Till Number: {$pay['mpesa_till_number']}<br>
Reference: $contact / Booking #$booking_id
</p>
";

$dompdf = new Dompdf();
$dompdf->loadHtml($invoiceHtml);
$dompdf->render();

if (!is_dir(__DIR__ . "/invoices")) {
    mkdir(__DIR__ . "/invoices", 0777, true);
}
$invoicePath = __DIR__ . "/invoices/invoice_$booking_id.pdf";
file_put_contents($invoicePath, $dompdf->output());

// ===== 9. SEND EMAIL =====
$mail = new PHPMailer(true);

try {
    // SMTP config
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "wellnesscheckkenya@gmail.com";     // 🔑 replace with Gmail
    $mail->Password   = "xyom bftr moyz snji";        // 🔑 use App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom("your_email@gmail.com", "Wellness Check Ke");
    $mail->addAddress($email, $contact);

    // Attach invoice
    $mail->addAttachment($invoicePath);

    $mail->isHTML(true);
    $mail->Subject = "Your Wellness Check Ke Booking & Invoice";
    $mail->Body = "
    Hi $contact,<br><br>
    Thank you for booking with <strong>Wellness Check Ke</strong>!<br><br>
    <strong>Booking Details:</strong><br>
    • Package: $package_name <br>
    • Date & Time: $date $time <br>
    • Location: $location <br>
    • Participants: $employees <br><br>

    <strong>Total Amount Payable:</strong> KES $package_price <br><br>

    <strong>Payment Instructions:</strong><br>
    Bank: {$pay['bank_name']}<br>
    Account Name: {$pay['account_name']}<br>
    Account Number: {$pay['account_number']}<br>
    M-Pesa Paybill: {$pay['mpesa_paybill']} / Till: {$pay['mpesa_till_number']}<br>
    Reference: $contact or Booking #$booking_id<br><br>

    We look forward to serving you!<br>
    <em>The Wellness Check Ke Team</em>
    ";

    $mail->send();

} catch (Exception $e) {
    error_log("Mailer Error: {$mail->ErrorInfo}");
}

// ===== 10. SUCCESS ALERT + REDIRECT =====
echo "<script>
    alert('✅ Thank you for booking the $package_name package! An invoice has been emailed to you.');
    window.location.href = 'corporate-screenings.html';
</script>";
exit();
