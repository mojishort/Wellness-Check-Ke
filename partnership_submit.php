<?php
// partnership_submit.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php'; // Composer autoload

// ===== 1. DB CONNECTION (same as booking_submit.php) =====
$dsn = "mysql:host=127.0.0.1;port=3307;dbname=wellness_db;charset=utf8mb4";
$user = "root";
$pass = "";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    die("❌ DB Connection failed: " . $e->getMessage());
}

// ===== 2. HANDLE FORM =====
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data safely
    $orgName   = $_POST['org-name'] ?? '';
    $orgType   = $_POST['org-type'] ?? '';
    $website   = $_POST['website'] ?? '';
    $orgLoc    = $_POST['org-location'] ?? '';
    $contact   = $_POST['contact-full-name'] ?? '';
    $position  = $_POST['contact-position'] ?? '';
    $phone     = $_POST['contact-phone'] ?? '';
    $email     = $_POST['contact-email'] ?? '';

    // Handle checkbox interests
    $interests = isset($_POST['interest']) ? implode(", ", $_POST['interest']) : '';
    if (!empty($_POST['other-interest-text'])) {
        $interests .= ($interests ? ", " : "") . "Other: " . $_POST['other-interest-text'];
    }

    $outreach  = $_POST['outreach-model'] ?? '';
    $timeline  = $_POST['timeline'] ?? '';
    $desc      = $_POST['partnership-description'] ?? '';
    $consent   = isset($_POST['consent']) ? 1 : 0;

    try {
        // ===== 3. INSERT INTO partnerships TABLE =====
        $stmt = $pdo->prepare("
            INSERT INTO partnerships 
                (org_name, org_type, website, org_location, contact_name, contact_position, contact_phone, contact_email, interests, outreach_model, timeline, description, consent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orgName, $orgType, $website, $orgLoc,
            $contact, $position, $phone, $email,
            $interests, $outreach, $timeline, $desc, $consent
        ]);

        // ===== 4. SETUP MAILER =====
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'wellnesscheckkenya@gmail.com'; // your Gmail
        $mail->Password   = 'xyom bftr moyz snji'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->isHTML(true);

// ---------- CLIENT EMAIL ----------
        $mail->clearAddresses();
        $mail->setFrom('wellnesscheckkenya@gmail.com', 'Wellness Check Ke');
        $mail->addAddress($email, $contact);

        $mail->Subject = "Thank You for Your Community Health Partnership Request";
        $mail->Body    = "
            Hi <b>$contact</b>,<br><br>
            Thank you for reaching out to Wellness Check Ke. We’ve received your request for a 
            Community & Rural Health Outreach partnership, and we’re excited about the opportunity 
            to collaborate with <b>$orgName</b> in advancing community health.<br><br>

            <b>Details Submitted:</b><br>
            • Organization Type: $orgType<br>
            • Office Location: $orgLoc<br>
            • Contact Person: $contact ($position, $phone, $email)<br>
            • Partnership Interest: $interests<br>
            • Website/Social Media Link: $website<br><br>

            Our team will contact you within <b>2 to 3 working days</b> to schedule a meeting where we can:<br>
            • Understand your organization’s goals for the outreach.<br>
            • Identify suitable locations and target beneficiaries.<br>
            • Discuss the scope of services required.<br>
            • Develop a tailored plan, including logistics and budget.<br><br>

            We believe that together, we can make a meaningful impact on community wellness.<br><br>

            Warm regards,<br>
            <b>The Wellness Check Ke Team</b>
        ";

        $mail->send();

        // ---------- ADMIN EMAIL ----------
        $mail->clearAddresses();
        $mail->addAddress('wellnesscheckkenya@gmail.com', 'Wellness Admin');

        $mail->Subject = "📩 New Partnership Request from $orgName";
        $mail->Body    = "
            <h2>New Partnership Submission</h2>
            <table border='1' cellpadding='6' cellspacing='0' width='100%'>
                <tr><td><b>Organization</b></td><td>$orgName</td></tr>
                <tr><td><b>Type</b></td><td>$orgType</td></tr>
                <tr><td><b>Website</b></td><td>$website</td></tr>
                <tr><td><b>Location</b></td><td>$orgLoc</td></tr>
                <tr><td><b>Contact Person</b></td><td>$contact ($position)</td></tr>
                <tr><td><b>Phone</b></td><td>$phone</td></tr>
                <tr><td><b>Email</b></td><td>$email</td></tr>
                <tr><td><b>Interests</b></td><td>$interests</td></tr>
                <tr><td><b>Outreach Model</b></td><td>$outreach</td></tr>
                <tr><td><b>Timeline</b></td><td>$timeline</td></tr>
                <tr><td><b>Description</b></td><td>$desc</td></tr>
                <tr><td><b>Consent</b></td><td>" . ($consent ? "Yes" : "No") . "</td></tr>
            </table>
            <br>
            <em>This is an automatic notification from the website.</em>
        ";
        $mail->send();

        // ===== 5. SUCCESS FEEDBACK =====
        echo "<script>
                alert('✅ Thank you! Your partnership request has been received.');
                window.location.href = 'community.html';
              </script>";
        exit;

    } catch (Exception $e) {
        echo "❌ Mailer Error: " . $mail->ErrorInfo;
    } catch (\Throwable $t) {
        echo "❌ General Error: " . $t->getMessage();
    }
} else {
    echo "❌ Invalid request method.";
}
?>
