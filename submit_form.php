<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Get data from the form
$name = htmlspecialchars($_POST['name']);
$email = htmlspecialchars($_POST['email']);
$message = htmlspecialchars($_POST['message']);

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'zackarystaten101@gmail.com';         // <-- your Gmail address
    $mail->Password   = 'nufw halo cnja tzce';   // <-- the Gmail App Password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    //Recipients
    $mail->setFrom('zackarystaten101@gmail.com', 'Website Form');
    $mail->addAddress('zackarystaten101@gmail.com'); // You can change this to another email

    // Content
    $mail->isHTML(false);
    $mail->Subject = 'New Form Submission';
    $mail->Body    = "You have a new message:\n\nName: $name\nEmail: $email\nMessage:\n$message";

    $mail->send();
    echo '<h2>Thank you! Your message has been sent.</h2>';
} catch (Exception $e) {
    echo "<h2>Message could not be sent. Mailer Error: {$mail->ErrorInfo}</h2>";
}
?>

