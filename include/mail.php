<?php
/* connect to gmail */
$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$username = 'bugtracker.ilearner@gmail.com';
$password = 'il3ii388i5';

require_once("class.phpmailer.php");
require_once("class.smtp.php");

class Mail {

    var $inbox;
    var $mail;

    function __construct() {
        $this->connect();
    }

    function __destruct () {
        imap_close($this->inbox);
    }

    function connect() {
        global $hostname, $username, $password;

        if (!$this->inbox) {
            /* try to connect */
            $this->inbox = imap_open($hostname, $username, $password) or die('Cannot connect to Gmail: ' . imap_last_error());
        }
        else if (!imap_ping($this->inbox)) {
            $this->inbox = imap_open($hostname, $username, $password);
        }

        return $this->inbox;
    }

    function sendMail ($to, $subject, $body, $headers) {
        global $username, $password;

        $this->mail = new PHPMailer();

        $this->mail->IsSMTP();

        $this->mail->SMTPAuth   = true;                  // enable SMTP authentication
        $this->mail->SMTPSecure = "ssl";                 // sets the prefix to the servier
        $this->mail->Host       = "smtp.gmail.com";      // sets GMAIL as the SMTP server
        $this->mail->Port       = 465;                   // set the SMTP port for the GMAIL server
        $this->mail->Username   = $username;             // GMAIL username
        $this->mail->Password   = $password;            // GMAIL password

        $this->mail->SetFrom($username, "i-Learner Bugtracker");

        $this->mail->Subject    = $subject;

        $this->mail->MsgHTML($body);

        $addresses = imap_rfc822_parse_adrlist($to, "");

        if (is_array($addresses)) {
            foreach ($addresses as $address) {
                $this->mail->AddAddress($address->mailbox . "@" . $address->host, $address->personal);
            }
        } else {
            echo "No 'To' addresses parsed";
            return;
        }

        if ($headers) {
            foreach ($headers as $name => $value) {
                if (strtolower($name) == "cc") {
                     $addresses = imap_rfc822_parse_adrlist($value, "");

                    if (is_array($addresses)) {
                        foreach ($addresses as $address) {
                            $this->mail->AddCC($address->mailbox . "@" . $address->host, $address->personal);
                        }
                    }
                } else {
                    $this->mail->addCustomHeader($name, $value);
                }
            }
        }

        if(!$this->mail->send()) {
            echo "Message could not be sent.\n";
            echo 'Mailer Error: ' . $this->mail->ErrorInfo."\n";
        }
        // else {
        //     echo 'Message has been sent';
        // }
    }


    function getLastMessageID () {
        if ($this->mail) {
            return $this->mail->getLastMessageID();
        }
    }
}

// Singleton
$mail = new Mail;
