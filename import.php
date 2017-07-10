<?php

date_default_timezone_set("Asia/Hong_Kong");

require_once("./include/issue.php");
require_once("./include/mail.php");
require_once("./include/db.php");

// If we're in the browser make sure output is formatted
if (PHP_SAPI !== "cli") {
    echo "<pre>\n";
}

importMessages();

function importMessages () {
    global $db, $mail, $issue;

    $inserted = 0;

    $inbox = $mail->connect();

    // $mailboxes = imap_list($inbox, '{imap.gmail.com:993/imap/ssl}', "*");

    // print_r($mailboxes);

    $users = $db->getUsers();

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
            $raw_header = imap_fetchheader($inbox,$email_number);
            $headers = imap_rfc822_parse_headers($raw_header);

            // var_dump($overview);
            // var_dump($raw_header);
            // var_dump($headers);
            // continue;

            if (strpos($raw_header, "X-Autoreply") !== false || strpos($raw_header, "Auto-Submitted") !== false) {
                echo "Auto-Reply found\n";
                // DO NOT get into infinite loop!!
            }
            else {
                $from_addresses = $headers->from;

                if ($from_addresses && count($from_addresses) >= 1) {

                    $from_email = $from_addresses[0]->mailbox . "@" . $from_addresses[0]->host;
                    $from_name = $from_addresses[0]->personal;

                    if (isSelfEmail($from_email)) {
                        echo "Self-email detected\n";
                        continue;
                    }

                    if (is_user($users, $from_email)) {

                        $raw_title = imap_utf8($overview[0]->subject);

                        $issue_id = false;

                        // Parse tags in subject [Tag][Multiple, Tags]
                        $tags = parseTags($raw_title);
                        $title = stripTags($raw_title);

                        $body = getPlainText($inbox, $email_number);

                        $notify_list = array($from_email);

                        $reply_to = array($headers->fromaddress);
                        $reply_cc = array();

                        foreach($headers->to as $addr) {
                            $email = $addr->mailbox . "@" . $addr->host;

                            if (isSelfEmail($email)) {
                                if (preg_match("/\+issue(\d+)/", $addr->mailbox, $matches)) {
                                    // Reply to previous issue

                                    $issue_id = $matches[1];
                                }
                            } else {
                                $notify_list[] = $email;
                                $reply_to[] = formatAddr($addr);
                            }
                        }

                        if(isset($headers->cc)) {
                            foreach($headers->cc as $addr) {
                                $email = $addr->mailbox . "@" . $addr->host;

                                if (isSelfEmail($email)) {
                                    if (preg_match("/\+issue(\d+)/", $addr->mailbox, $matches)) {
                                        // Reply to previous issue

                                        $issue_id = $matches[1];
                                    }
                                } else {
                                    $notify_list[] = $email;
                                    $reply_cc[] = formatAddr($addr);
                                }
                            }
                        }

                        if (preg_match("/Issue ID: (\d+)/", $body, $matches)) {
                            // Reply to previous issue

                            $issue_id = $matches[1];
                        }

                        if ($issue_id) {

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

                            $db->insertIssueHistory($from_email, $issue_id, "COMMENT", $message);

                            // There may have been new people included in the reply who should be notified
                            $issue->addNotify($issue_id, $notify_list);
                        }
                        else {
                            // Import new issue

                            $fields = array(
                                "title" => $title,
                                "description" => $body,
                                "creator" => $from_email,
                                "notify" => $notify_list,
                                "tags" => $tags,
                            );

                            $id = $issue->addIssue($from_email, $fields);

                            $inserted++;

                            $issue->notifyNewIssue($id, array("In-Reply-To" => $overview[0]->message_id));
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

                        $mail->sendMail($reply_to, $reply_subject, $reply_body, $reply_headers);
                    }
                    else {
                        echo "Spam Email found.\n";
                    }
                }
                else {
                    echo "No emails found\n";
                }
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

function isSelfEmail($email) {
    return strpos($email, "bugtracker") !== false;
}

function formatAddr($addr) {
    $email = $addr->mailbox . "@" . $addr->host;
    $name = $addr->personal;

    if($name) {
        return $name . " <" . $email . ">";
    }
    else {
        return $email;
    }
}

function getPlainText($imap_stream, $msg_number) {
    $struct = imap_fetchstructure($imap_stream, $msg_number);

    // type 0: TEXT
    // type 1: MULTIPART
    if($struct->type == 1) {
        $part_index = 1;

        foreach($struct->parts as $part) {
            if($part->type == 0 && $part->subtype == "PLAIN") {
                $body = imap_fetchbody($imap_stream, $msg_number, $part_index);

                // encoding 0: 7bit
                // encoding 1: 8bit
                // encoding 2: binary
                // encoding 3: base64
                // encoding 4: quoted printable
                if($part->encoding == 3) {
                    $body = base64_decode($body);
                } else if ($part->encoding == 4) {
                    $body = quoted_printable_decode($body);
                }

                return $body;
            }
            $part_index++;
        }
    }
}

// Parse tags in the form of [Tag][Multiple, Tags]
function parseTags ($string) {
    preg_match_all("/\[([^]]*)\]/", $string, $matches);

    $out = array();
    foreach($matches[1] as $match) {
        $subtags = explode(",", $match);
        foreach($subtags as $tag) {
            $out[] = trim($tag);
        }
    }

    return $out;
}

// Strip tags in the form of [Tag][Multiple, Tags]
function stripTags($string) {
    return preg_replace("/\[[^]]*\]/", "", $string);
}
