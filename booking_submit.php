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
$company     = $_POST['company-name'] ?? '';
$contact     = $_POST['contact-person'] ?? $_POST['full-name'] ?? '';
$email       = $_POST['email'] ?? '';
$phone       = $_POST['phone'] ?? '';
$location    = $_POST['location'] ?? $_POST['address'] ?? '';
$formType    = $_POST['form_type'] ?? 'corporate'; // default corporate
$package     = $_POST['package'] ?? '';
$date        = $_POST['date'] ?? '';
$time        = $_POST['time'] ?? '';

// Unify participants into $employees (DB column)
if ($formType === 'corporate') {
    $employees = $_POST['employees'] ?? 0;
} elseif ($formType === 'homebased') {
    $employees = $_POST['participants'] ?? 1; // at least 1
} elseif ($formType === 'institution') {
    $employees = $_POST['participants'] ?? ''; // ranges
} elseif ($formType === 'men' || $formType === 'women') {
    $employees = 1; // individual checkups
} elseif ($formType === 'family') {
    $employees = $_POST['family-members'] ?? 1;
}
else {
    $employees = 0;
}

// ===== 3. VALIDATION =====
if (!$contact || !$email || !$phone || !$location || !$date || !$time || !$package) {
    die("❌ Missing required fields");
}
if ($formType === 'corporate' && (!$company || !$employees)) {
    die("❌ Missing required corporate fields");
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
if ($formType === 'homebased') {
    switch ($package) {
        case "Essential Screening":
            $package_full = "Homebased-Essential Wellness";
            break;
        case "Comprehensive Screening":
            $package_full = "Homebased-Comprehensive Screening";
            break;
        case "Family Wellness Package":
            $package_full = "Homebased-Family Wellness";
            break;
        default:
            $package_full = $package;
    }
} elseif ($formType === 'institution') {
    $package_full = $package; // already matches DB
} elseif ($formType === 'men') {
    switch ($package) {
        case "Essential Men’s Package":
            $package_full = "Annual-Essential Men’s Package";
            break;
        case "Comprehensive Men’s Package":
            $package_full = "Annual-Comprehensive Men’s Package";
            break;
        default:
            $package_full = $package;
    }
} elseif ($formType === 'women') {
    switch ($package) {
        case "Essential Women’s Package":
            $package_full = "Annual-Essential Women’s Package";
            break;
        case "Comprehensive Women’s Package":
            $package_full = "Annual-Comprehensive Women’s Package";
            break;
        default:
            $package_full = $package;
    }
} elseif ($formType === 'family') {
    switch ($package) {
        case "Essential Family Package":
            $package_full = "Annual-Essential Family Package";
            break;
        case "Comprehensive Family Package":
            $package_full = "Annual-Comprehensive Family Package";
            break;
        default:
            $package_full = $package;
    }
}
 else {
    $package_full = "Corporate-" . $package;
}

$stmt = $pdo->prepare("SELECT id, package_name, price FROM packages WHERE package_name = ?");
$stmt->execute([$package_full]);
$pkg = $stmt->fetch();

if (!$pkg) {
    die("❌ Invalid package selected: " . htmlspecialchars($package_full));
}
$package_id    = $pkg['id'];
$package_name  = $pkg['package_name'];
$package_price = $pkg['price'];

// ===== 5b. Ensure default participants for homebased =====
if ($formType === 'homebased') {
    if (empty($employees) || $employees <= 0) {
        if (in_array($package_full, ["Homebased-Essential Wellness", "Homebased-Comprehensive Screening"])) {
            $employees = 1;
        }
    }
}

// ===== 6. INSERT BOOKING =====
$stmt = $pdo->prepare("INSERT INTO bookings (client_id, package_id, location, employees, preferred_date, preferred_time) 
                       VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$client_id, $package_id, $location, $employees, $date, $time]);
$booking_id = $pdo->lastInsertId();

// Custom formatted booking ID
$formatted_booking_id = "Wck" . $booking_id;

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
<p><strong>Booking ID:</strong> $formatted_booking_id</p>
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
";

if ($package_name === "Corporate-Comprehensive Wellness") {
    $invoiceHtml .= "
    <h3>Comprehensive Wellness Pricing</h3>
    <table border='1' cellpadding='6' cellspacing='0' width='100%'>
      <tr style='background:#d9edf7;'>
        <th>Staff Number</th>
        <th>Standard Price</th>
        <th>Discount</th>
        <th>New Price (Per Staff)</th>
        <th>Savings Per Staff</th>
        <th>Total Savings</th>
      </tr>
      <tr>
        <td>1-50 Staff</td>
        <td>KES 2,000</td>
        <td>-</td>
        <td>KES 2,000</td>
        <td>-</td>
        <td>-</td>
      </tr>
      <tr>
        <td>51-200 Staff</td>
        <td>KES 2,000</td>
        <td>5%</td>
        <td>KES 1,900</td>
        <td>KES 100</td>
        <td>Up to KES 20,000</td>
      </tr>
      <tr>
        <td>201-500 Staff</td>
        <td>KES 2,000</td>
        <td>10%</td>
        <td>KES 1,800</td>
        <td>KES 200</td>
        <td>Up to KES 100,000</td>
      </tr>
      <tr>
        <td>500+ Staff</td>
        <td>KES 2,000</td>
        <td>Custom Pricing</td>
        <td>Negotiated Rate</td>
        <td>Bigger Savings</td>
        <td>Flexible</td>
      </tr>
    </table>
    ";
} else {
    $invoiceHtml .= "
    <h3>Invoice</h3>
    <table border='1' cellpadding='6' cellspacing='0' width='100%'>
      <tr style='background:#d9edf7;'>
        <th>Description</th>
        <th>Package</th>
        <th>Amount (KES)</th>
      </tr>
      <tr>
        <td>$formType</td>
        <td>$package_name</td>
        <td>$package_price</td>
      </tr>
      <tr>
        <td colspan='2'><strong>Total</strong></td>
        <td><strong>$package_price</strong></td>
      </tr>
    </table>
    ";
}

$invoiceHtml .= "
<h3>Payment Instructions</h3>
<p>
Bank: {$pay['bank_name']}<br>
Account Name: {$pay['account_name']}<br>
Account Number: {$pay['account_number']}<br>
M-Pesa Paybill: {$pay['mpesa_paybill']}<br>
Till Number: {$pay['mpesa_till_number']}<br>
Reference: $contact / Booking $formatted_booking_id
</p>
";

$dompdf = new Dompdf();
$dompdf->loadHtml($invoiceHtml);
$dompdf->render();

if (!is_dir(__DIR__ . "/invoices")) {
    mkdir(__DIR__ . "/invoices", 0777, true);
}
$invoicePath = __DIR__ . "/invoices/invoice_$formatted_booking_id.pdf";
file_put_contents($invoicePath, $dompdf->output());

// ===== 9. SEND EMAIL =====
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "wellnesscheckkenya@gmail.com";
    $mail->Password   = "xyom bftr moyz snji"; // Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPDebug  = 0;

    $mail->setFrom("wellnesscheckkenya@gmail.com", "Wellness Check Ke");
    $mail->addAddress($email, $contact);

    if (file_exists($invoicePath)) {
        $mail->addAttachment($invoicePath);
    }

    $mail->isHTML(true);
    $mail->Subject = "Your Wellness Check Ke Booking ($formatted_booking_id) & Invoice";
    $mail->Body = "
    Hi $contact,<br><br>
    Thank you for booking with <strong>Wellness Check Ke</strong>!<br><br>
    <strong>Booking Details:</strong><br>
    • Booking ID: $formatted_booking_id <br>
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
    Reference: $contact or Booking $formatted_booking_id<br><br>

    We look forward to serving you!<br>
    <em>The Wellness Check Ke Team</em>
    ";

    $mail->send();
    echo "✅ Mail sent successfully!";
} catch (Exception $e) {
    echo "❌ Mailer Error: {$mail->ErrorInfo}";
}

// ===== 10. SUCCESS ALERT + REDIRECT =====
if ($formType === 'homebased') {
    $redirectPage = 'homebased.html';
} elseif ($formType === 'institution') {
    $redirectPage = 'institution.html';
} elseif ($formType === 'men') {
    $redirectPage = 'menannual.html';
} elseif ($formType === 'women') {
    $redirectPage = 'womenannual.html';
} elseif ($formType === 'family') {
    $redirectPage = 'familyannual.html';
} else {
    $redirectPage = 'corporate-screenings.html';
}

echo "<script>
    alert('✅ Thank you for booking the $package_name package! Your Booking ID is $formatted_booking_id. An invoice has been emailed to you.');
    window.location.href = '$redirectPage';
</script>";
exit();
?>
