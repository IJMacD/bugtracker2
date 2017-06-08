<?php

require_once("mail.php");
require_once("db.php");

// If we're in the browser make sure output is formatted
if (PHP_SAPI !== "cli") {
    echo "<pre>\n";
}

importMessages();

function importMessages () {
    global $username; // Urgh

    $inserted = 0;

    $inbox = mailConnect();

    // $mailboxes = imap_list($inbox, '{imap.gmail.com:993/imap/ssl}', "*");

    // print_r($mailboxes);

    $db = dbConnect();

    $users = dbGetUsers($db);

    /* grab emails */
    $emails = imap_search($inbox,'ALL');

    /* if emails are returned, cycle through each... */
    if($emails) {

        echo "Emails found: ".count($emails)."\n";

        /* for every email... */
        foreach($emails as $email_number) {
            echo "Processing email $email_number\n";

            /* get information specific to this email */
            $overview = imap_fetch_overview($inbox,$email_number,0);

            $addresses = imap_rfc822_parse_adrlist($overview[0]->from, "");

            if ($addresses && count($addresses) >= 1) {

                $from_address = $addresses[0]->mailbox . "@" . $addresses[0]->host;
                $from_name = $addresses[0]->personal;

                if (!$from_name) {
                    $from_name = $from_address;
                }

                if ($from_address == $username) {
                    echo "Self-email detected\n";
                    continue;
                }

                if (is_user($users, $from_address)) {

                    $title = imap_utf8($overview[0]->subject);

                    // print_r(imap_fetchstructure($inbox, $email_number));
                    $body = quoted_printable_decode(imap_fetchbody($inbox, $email_number, 1)); // Plain Text

                    if (preg_match("/Issue ID: (\d+)/", $body, $matches)) {
                        // Reply to previous issue

                        $issue_id = $matches[1];

                        echo "Found reply to issue $issue_id\n";

                        $lines = explode("\n", $body);
                        $filtered = array();
                        foreach($lines as $line) {
                            if(strlen($line) && $line[0] != ">") {
                                $filtered[] = $line;
                            }
                        }

                        $truncated = array_slice($filtered, 0, -3);

                        $message = trim(implode("\n", $truncated));

                        dbInsertIssueHistory($db, $from_address, $issue_id, "COMMENT", $message);
                    }
                    else {
                        // Import new issue

                        $fields = array("title" => $title, "description" => $body, "creator" => $from_address);
                        // print_r($fields);
                        $id = dbInsertIssue($db, $from_address, $fields);

                        $inserted++;

                        //Send email saying successful
                        $reply_to = $overview[0]->from;
                        $reply_subject = "Re: " . $title;
                        // Including a copy in the reply causes jank when gmail tries to quote it.
                        $reply_body = "<p>Dear $from_name,</p>\n"
                            ."<p>Your issue has been added. You will be notified when there are any updates.</p>\n\n"
                            .'<hr style="border-top: 1px solid #999; margin-top: 50px;" />'
                            .'<p style="font-size: 0.8em; color: #666;">Issue ID: '.$id.'</p>';
                        $reply_headers = array(
                            "In-Reply-To" => $overview[0]->message_id
                        );

                        send_email($reply_to, $reply_subject, $reply_body, $reply_headers);
                    }

                }
                else if(!preg_match("/no-?reply/", $overview[0]->from)) {
                    // Not spam email

                    echo "Rejected Email found.\n";

                    // Send reply notififying they are not registered
                    $reply_to = $overview[0]->from;
                    $reply_subject = "Re: " . $overview[0]->subject;
                    $reply_body = "<p>Dear $from_name,</p>\n<p>Your issue has <b>not</b> been added. Your email address was not recognised. Please register your email address before submitting any issues.</p>";
                    $reply_headers = array(
                        "In-Reply-To" => $overview[0]->message_id
                    );

                    send_email($reply_to, $reply_subject, $reply_body, $reply_headers);
                }
                else {
                    echo "Spam Email found.\n";
                }
            }
            else {
                echo "No emails found\n";
            }

            // Archive email
            imap_delete($inbox, $email_number);
        }

        // Expunge
        imap_expunge($inbox);
    }

    /* close the connection */
    imap_close($inbox);

    echo "Inserted Issues: $inserted\n";

}

function is_user($users, $email) {
    foreach($users as $user) {
        if(strtolower($user['email']) == strtolower($email)){
            return true;
        }
    }
    return false;
}
