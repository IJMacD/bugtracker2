<?php
/* connect to gmail */
$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$username = 'bugtracker.ilearner@gmail.com';
$password = 'il3ii388i5';

require_once("class.phpmailer.php");
require_once("class.smtp.php");

function mailConnect() {
    global $hostname, $username, $password;

    /* try to connect */
    $inbox = imap_open($hostname, $username, $password) or die('Cannot connect to Gmail: ' . imap_last_error());

    return $inbox;
}


function send_email ($to, $subject, $body, $headers) {
    global $username, $password;

    $mail = new PHPMailer();

    $mail->IsSMTP();

    $mail->SMTPAuth   = true;                  // enable SMTP authentication
    $mail->SMTPSecure = "ssl";                 // sets the prefix to the servier
    $mail->Host       = "smtp.gmail.com";      // sets GMAIL as the SMTP server
    $mail->Port       = 465;                   // set the SMTP port for the GMAIL server
    $mail->Username   = $username;             // GMAIL username
    $mail->Password   = $password;            // GMAIL password

    $mail->SetFrom($username, "i-Learner Bugtracker");

    $mail->Subject    = $subject;

    $mail->MsgHTML($body);

    $addresses = imap_rfc822_parse_adrlist($to, "");

    if ($addresses && count($addresses) >= 1) {
        foreach ($addresses as $address) {
            $mail->AddAddress($address->mailbox . "@" . $address->host, $address->personal);
        }
    } else {
        echo 'No \'To\' addresses parsed';
        return;
    }

    if ($headers) {
        foreach ($headers as $name => $value) {
            $mail->addCustomHeader($name, $value);
        }
    }

    if(!$mail->send()) {
        echo 'Message could not be sent.<br>';
        echo 'Mailer Error: ' . $mail->ErrorInfo."<br>";
    }
    // else {
    //     echo 'Message has been sent';
    // }
}
?>
