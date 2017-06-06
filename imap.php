<?php
/* connect to gmail */
$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$username = 'bugtracker.ilearner@gmail.com';
$password = 'il3ii388i5';

/* try to connect */
$inbox = imap_open($hostname,$username ,$password) or die('Cannot connect to Gmail: ' . imap_last_error());

/* grab emails */
$emails = imap_search($inbox,'ALL');

/* if emails are returned, cycle through each... */
if($emails) {

    /* begin output var */
    $output = '';

    /* put the newest emails on top */
    rsort($emails);

    /* for every email... */
    foreach($emails as $email_number) {

        /* get information specific to this email */
        $overview = imap_fetch_overview($inbox,$email_number,0);


        $output.= 'Name:  '.htmlspecialchars($overview[0]->from).'</br>';
        $output.= 'Subject:  '.$overview[0]->subject.'</br>';
        // print_r($overview[0]);
        $output.= 'ID:  '.htmlspecialchars($overview[0]->message_id).'</br>';

        // print_r(imap_fetchstructure($inbox, $email_number));
        $output .= 'Body:  '.nl2br(htmlspecialchars(imap_fetchbody($inbox, $email_number, 1))).'</br>';
        // $output .= 'Body:  '.nl2br(htmlspecialchars(base64_decode(imap_fetchbody($inbox, $email_number, 1)))).'</br>';
        // $output .= 'Body:  '.htmlspecialchars(imap_qprint(imap_fetchbody($inbox, $email_number, 2))).'</br>';
        // $output .= 'Body:  '.nl2br(htmlspecialchars(strip_tags(br2nl(imap_qprint(imap_fetchbody($inbox, $email_number, 2)))))).'</br>';


        $output .= '<hr>';
    }

    echo $output;
}

/* close the connection */
imap_close($inbox);

function br2nl($text){
    return str_replace("</div>", "</div>\n", $text);
    return str_replace("<br>", "\n", str_replace("</div>", "</div>\n", $text));
}
?>
