<?php
////////////////////////////////////////////////////
// PHPMailer - PHP email class
//
// Class for sending email using either
// sendmail, PHP mail(), or SMTP.  Methods are
// based upon the standard AspEmail(tm) classes.
//
// Copyright (C) 2001 - 2003  Brent R. Matzelle
//
// License: LGPL, see LICENSE
////////////////////////////////////////////////////

/**
 * PHPMailer - PHP email transport class
 * @package PHPMailer
 * @author Brent R. Matzelle
 * @copyright 2001 - 2003 Brent R. Matzelle
 */
class phpmailer
{
    /////////////////////////////////////////////////
    // PUBLIC VARIABLES
    /////////////////////////////////////////////////

    /**
 Email priority (1 = High, 3 = Normal, 5 = low).
 @var int
     */
    public $Priority          = 3;

    /**
 Sets the CharSet of the message.
 @var string
     */
    public $CharSet           = "iso-8859-1";

    /**
 Sets the Content-type of the message.
 @var string
     */
    public $ContentType        = "text/plain";

    /**
 Sets the Encoding of the message. Options for this are "8bit",
 "7bit", "binary", "base64", and "quoted-printable".
 @var string
     */
    public $Encoding          = "8bit";

    /**
 Holds the most recent mailer error message.
 @var string
     */
    public $ErrorInfo         = "";

    /**
 Sets the From email address for the message.
 @var string
     */
    public $From               = "root@localhost";

    /**
 Sets the From name of the message.
 @var string
     */
    public $FromName           = "Root User";

    /**
 Sets the Sender email (Return-Path) of the message.  If not empty,
 will be sent via -f to sendmail or as 'MAIL FROM' in smtp mode.
 @var string
     */
    public $Sender            = "";

    /**
 Sets the Subject of the message.
 @var string
     */
    public $Subject           = "";

    /**
 Sets the Body of the message.  This can be either an HTML or text body.
 If HTML then run IsHTML(true).
 @var string
     */
    public $Body               = "";

    /**
 Sets the text-only body of the message.  This automatically sets the
 email to multipart/alternative.  This body can be read by mail
 clients that do not have HTML email capability such as mutt. Clients
 that can read HTML will view the normal Body.
 @var string
     */
    public $AltBody           = "";

    /**
 Sets word wrapping on the body of the message to a given number of
 characters.
 @var int
     */
    public $WordWrap          = 0;

    /**
 Method to send mail: ("mail", "sendmail", or "smtp").
 @var string
     */
    public $Mailer            = "mail";

    /**
 Sets the path of the sendmail program.
 @var string
     */
    public $Sendmail          = "/usr/sbin/sendmail";

    /**
 Path to PHPMailer plugins.  This is now only useful if the SMTP class
 is in a different directory than the PHP include path.
 @var string
     */
    public $PluginDir         = "";

    /**
  Holds PHPMailer version.
  @var string
     */
    public $Version           = "1.73";

    /**
 Sets the email address that a reading confirmation will be sent.
 @var string
     */
    public $ConfirmReadingTo  = "";

    /**
  Sets the hostname to use in Message-Id and Received headers
  and as default HELO string. If empty, the value returned
  by SERVER_NAME is used or 'localhost.localdomain'.
  @var string
     */
    public $Hostname          = "";

    /////////////////////////////////////////////////
    // SMTP VARIABLES
    /////////////////////////////////////////////////

    /**
  Sets the SMTP hosts.  All hosts must be separated by a
  semicolon.  You can also specify a different port
  for each host by using this format: [hostname:port]
  (e.g. "smtp1.example.com:25;smtp2.example.com").
  Hosts will be tried in order.
  @var string
     */
    public $Host        = "localhost";

    /**
  Sets the default SMTP server port.
  @var int
     */
    public $Port        = 25;

    /**
  Sets the SMTP HELO of the message (Default is $Hostname).
  @var string
     */
    public $Helo        = "";

    /**
  Sets SMTP authentication. Utilizes the Username and Password variables.
  @var bool
     */
    public $SMTPAuth     = false;

    /**
  Sets SMTP username.
  @var string
     */
    public $Username     = "";

    /**
  Sets SMTP password.
  @var string
     */
    public $Password     = "";

    /**
  Sets the SMTP server timeout in seconds. This function will not
  work with the win32 version.
  @var int
     */
    public $Timeout      = 10;

    /**
  Sets SMTP class debugging on or off.
  @var bool
     */
    public $SMTPDebug    = false;

    /**
 Prevents the SMTP connection from being closed after each mail
 sending.  If this is set to true then to close the connection
 requires an explicit call to SmtpClose().
 @var bool
     */
    public $SMTPKeepAlive = false;

    /**#@+
 @access private
     */
    public $smtp            = NULL;
    public $to              = array();
    public $cc              = array();
    public $bcc             = array();
    public $ReplyTo         = array();
    public $attachment      = array();
    public $CustomHeader    = array();
    public $message_type    = "";
    public $boundary        = array();
    public $language        = array();
    public $error_count     = 0;
    public $LE              = "\n";
    /**#@-*/

    /////////////////////////////////////////////////
    // VARIABLE METHODS
    /////////////////////////////////////////////////

    /**
 Sets message type to HTML.
 @param bool $bool
 @return void
     */
    public function IsHTML($bool)
    {
        if($bool == true)
            $this->ContentType = "text/html";
        else
            $this->ContentType = "text/plain";
    }

    /**
 Sets Mailer to send message using SMTP.
 @return void
     */
    public function IsSMTP()
    {
        $this->Mailer = "smtp";
    }

    /**
 Sets Mailer to send message using PHP mail() function.
 @return void
     */
    public function IsMail()
    {
        $this->Mailer = "mail";
    }

    /**
 Sets Mailer to send message using the $Sendmail program.
 @return void
     */
    public function IsSendmail()
    {
        $this->Mailer = "sendmail";
    }

    /**
 Sets Mailer to send message using the qmail MTA.
 @return void
     */
    public function IsQmail()
    {
        $this->Sendmail = "/var/qmail/bin/sendmail";
        $this->Mailer = "sendmail";
    }

    /////////////////////////////////////////////////
    // RECIPIENT METHODS
    /////////////////////////////////////////////////

    /**
 Adds a "To" address.
 @param string $address
 @param string $name
 @return void
     */
    public function AddAddress($address, $name = "")
    {
        $cur = count($this->to);
        $this->to[$cur][0] = trim($address);
        $this->to[$cur][1] = $name;
    }

    /**
 Adds a "Cc" address. Note: this function works
 with the SMTP mailer on win32, not with the "mail"
 mailer.
 @param string $address
 @param string $name
 @return void
    */
    public function AddCC($address, $name = "")
    {
        $cur = count($this->cc);
        $this->cc[$cur][0] = trim($address);
        $this->cc[$cur][1] = $name;
    }

    /**
 Adds a "Bcc" address. Note: this function works
 with the SMTP mailer on win32, not with the "mail"
 mailer.
 @param string $address
 @param string $name
 @return void
     */
    public function AddBCC($address, $name = "")
    {
        $cur = count($this->bcc);
        $this->bcc[$cur][0] = trim($address);
        $this->bcc[$cur][1] = $name;
    }

    /**
 Adds a "Reply-to" address.
 @param string $address
 @param string $name
 @return void
     */
    public function AddReplyTo($address, $name = "")
    {
        $cur = count($this->ReplyTo);
        $this->ReplyTo[$cur][0] = trim($address);
        $this->ReplyTo[$cur][1] = $name;
    }

    /////////////////////////////////////////////////
    // MAIL SENDING METHODS
    /////////////////////////////////////////////////

    /**
 Creates message and assigns Mailer. If the message is
 not sent successfully then it returns false.  Use the ErrorInfo
 variable to view description of the error.
 @return bool
     */
    public function Send()
    {
        $header = "";
        $body = "";
        $result = true;

        if ((count($this->to) + count($this->cc) + count($this->bcc)) < 1) {
            $this->SetError($this->Lang("provide_address"));

            return false;
        }

        // Set whether the message is multipart/alternative
        if(!empty($this->AltBody))
            $this->ContentType = "multipart/alternative";

        $this->error_count = 0; // reset errors
        $this->SetMessageType();
        $header .= $this->CreateHeader();
        $body = $this->CreateBody();

        if ($body == "") { return false; }

        // Choose the mailer
        switch ($this->Mailer) {
            case "sendmail":
                $result = $this->SendmailSend($header, $body);
                break;
            case "mail":
                $result = $this->MailSend($header, $body);
                break;
            case "smtp":
                $result = $this->SmtpSend($header, $body);
                break;
            default:
            $this->SetError($this->Mailer . $this->Lang("mailer_not_supported"));
                $result = false;
                break;
        }

        return $result;
    }

    /**
 Sends mail using the $Sendmail program.
 @access private
 @return bool
     */
    public function SendmailSend($header, $body)
    {
        if ($this->Sender != "")
            $sendmail = sprintf("%s -oi -f %s -t", $this->Sendmail, $this->Sender);
        else
            $sendmail = sprintf("%s -oi -t", $this->Sendmail);

        if (!@$mail = popen($sendmail, "w")) {
            $this->SetError($this->Lang("execute") . $this->Sendmail);

            return false;
        }

        fputs($mail, $header);
        fputs($mail, $body);

        $result = pclose($mail) >> 8 & 0xFF;
        if ($result != 0) {
            $this->SetError($this->Lang("execute") . $this->Sendmail);

            return false;
        }

        return true;
    }

    /**
 Sends mail using the PHP mail() function.
 @access private
 @return bool
     */
    public function MailSend($header, $body)
    {
        $to = "";
        for ($i = 0; $i < count($this->to); $i++) {
            if ($i != 0) { $to .= ", "; }
            $to .= $this->to[$i][0];
        }

        if ($this->Sender != "" && strlen(ini_get("safe_mode"))< 1) {
            $old_from = ini_get("sendmail_from");
            ini_set("sendmail_from", $this->Sender);
            $params = sprintf("-oi -f %s", $this->Sender);
            $rt = @mail($to, $this->EncodeHeader($this->Subject), $body,
                        $header, $params);
        } else
            $rt = @mail($to, $this->EncodeHeader($this->Subject), $body, $header);

        if (isset($old_from))
            ini_set("sendmail_from", $old_from);

        if (!$rt) {
            $this->SetError($this->Lang("instantiate"));

            return false;
        }

        return true;
    }

    /**
 Sends mail via SMTP using PhpSMTP (Author:
 Chris Ryan).  Returns bool.  Returns false if there is a
 bad MAIL FROM, RCPT, or DATA input.
 @access private
 @return bool
     */
    public function SmtpSend($header, $body)
    {
        include_once($this->PluginDir . "class.smtp.php");
        $error = "";
        $bad_rcpt = array();

        if(!$this->SmtpConnect())

            return false;

        $smtp_from = ($this->Sender == "") ? $this->From : $this->Sender;
        if (!$this->smtp->Mail($smtp_from)) {
            $error = $this->Lang("from_failed") . $smtp_from;
            $this->SetError($error);
            $this->smtp->Reset();

            return false;
        }

        // Attempt to send attach all recipients
        for ($i = 0; $i < count($this->to); $i++) {
            if(!$this->smtp->Recipient($this->to[$i][0]))
                $bad_rcpt[] = $this->to[$i][0];
        }
        for ($i = 0; $i < count($this->cc); $i++) {
            if(!$this->smtp->Recipient($this->cc[$i][0]))
                $bad_rcpt[] = $this->cc[$i][0];
        }
        for ($i = 0; $i < count($this->bcc); $i++) {
            if(!$this->smtp->Recipient($this->bcc[$i][0]))
                $bad_rcpt[] = $this->bcc[$i][0];
        }

        if (count($bad_rcpt) > 0) { // Create error message
            for ($i = 0; $i < count($bad_rcpt); $i++) {
                if ($i != 0) { $error .= ", "; }
                $error .= $bad_rcpt[$i];
            }
            $error = $this->Lang("recipients_failed") . $error;
            $this->SetError($error);
            $this->smtp->Reset();

            return false;
        }

        if (!$this->smtp->Data($header . $body)) {
            $this->SetError($this->Lang("data_not_accepted"));
            $this->smtp->Reset();

            return false;
        }
        if($this->SMTPKeepAlive == true)
            $this->smtp->Reset();
        else
            $this->SmtpClose();

        return true;
    }

    /**
 Initiates a connection to an SMTP server.  Returns false if the
 operation failed.
 @access private
 @return bool
     */
    public function SmtpConnect()
    {
        if ($this->smtp == NULL) { $this->smtp = new SMTP(); }

        $this->smtp->do_debug = $this->SMTPDebug;
        $hosts = explode(";", $this->Host);
        $index = 0;
        $connection = ($this->smtp->Connected());

        // Retry while there is no connection
        while ($index < count($hosts) && $connection == false) {
            if(strstr($hosts[$index], ":"))
                list($host, $port) = explode(":", $hosts[$index]);
            else {
                $host = $hosts[$index];
                $port = $this->Port;
            }

            if ($this->smtp->Connect($host, $port, $this->Timeout)) {
                if ($this->Helo != '')
                    $this->smtp->Hello($this->Helo);
                else
                    $this->smtp->Hello($this->ServerHostname());

                if ($this->SMTPAuth) {
                    if(!$this->smtp->Authenticate($this->Username,
                                                  $this->Password))
                    {
                        $this->SetError($this->Lang("authenticate"));
                        $this->smtp->Reset();
                        $connection = false;
                    }
                }
                $connection = true;
            }
            $index++;
        }
        if(!$connection)
            $this->SetError($this->Lang("connect_host"));

        return $connection;
    }

    /**
 Closes the active SMTP session if one exists.
 @return void
     */
    public function SmtpClose()
    {
        if ($this->smtp != NULL) {
            if ($this->smtp->Connected()) {
                $this->smtp->Quit();
                $this->smtp->Close();
            }
        }
    }

    /**
 Sets the language for all class error messages.  Returns false
 if it cannot load the language file.  The default language type
 is English.
 @param string $lang_type Type of language (e.g. Portuguese: "br")
 @param string $lang_path Path to the language file directory
 @access public
 @return bool
     */
    public function SetLanguage($lang_type, $lang_path = "language/")
    {
        if(file_exists($lang_path.'phpmailer.lang-'.$lang_type.'.php'))
            include($lang_path.'phpmailer.lang-'.$lang_type.'.php');
        else if(file_exists($lang_path.'phpmailer.lang-en.php'))
            include($lang_path.'phpmailer.lang-en.php');
        else {
            $this->SetError("Could not load language file");

            return false;
        }
        $this->language = $PHPMAILER_LANG;

        return true;
    }

    /////////////////////////////////////////////////
    // MESSAGE CREATION METHODS
    /////////////////////////////////////////////////

    /**
 Creates recipient headers.
 @access private
 @return string
     */
    public function AddrAppend($type, $addr)
    {
        $addr_str = $type . ": ";
        $addr_str .= $this->AddrFormat($addr[0]);
        if (count($addr) > 1) {
            for($i = 1; $i < count($addr); $i++)
                $addr_str .= ", " . $this->AddrFormat($addr[$i]);
        }
        $addr_str .= $this->LE;

        return $addr_str;
    }

    /**
 Formats an address correctly.
 @access private
 @return string
     */
    public function AddrFormat($addr)
    {
        if(empty($addr[1]))
            $formatted = $addr[0];
        else {
            $formatted = $this->EncodeHeader($addr[1], 'phrase') . " <" .
                         $addr[0] . ">";
        }

        return $formatted;
    }

    /**
 Wraps message for use with mailers that do not
 automatically perform wrapping and for quoted-printable.
 Original written by philippe.
 @access private
 @return string
     */
    public function WrapText($message, $length, $qp_mode = false)
    {
        $soft_break = ($qp_mode) ? sprintf(" =%s", $this->LE) : $this->LE;

        $message = $this->FixEOL($message);
        if (substr($message, -1) == $this->LE)
            $message = substr($message, 0, -1);

        $line = explode($this->LE, $message);
        $message = "";
        for ($i=0 ;$i < count($line); $i++) {
          $line_part = explode(" ", $line[$i]);
          $buf = "";
          for ($e = 0; $e<count($line_part); $e++) {
              $word = $line_part[$e];
              if ($qp_mode and (strlen($word) > $length)) {
                $space_left = $length - strlen($buf) - 1;
                if ($e != 0) {
                    if ($space_left > 20) {
                        $len = $space_left;
                        if (substr($word, $len - 1, 1) == "=")
                          $len--;
                        elseif (substr($word, $len - 2, 1) == "=")
                          $len -= 2;
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);
                        $buf .= " " . $part;
                        $message .= $buf . sprintf("=%s", $this->LE);
                    } else {
                        $message .= $buf . $soft_break;
                    }
                    $buf = "";
                }
                while (strlen($word) > 0) {
                    $len = $length;
                    if (substr($word, $len - 1, 1) == "=")
                        $len--;
                    elseif (substr($word, $len - 2, 1) == "=")
                        $len -= 2;
                    $part = substr($word, 0, $len);
                    $word = substr($word, $len);

                    if (strlen($word) > 0)
                        $message .= $part . sprintf("=%s", $this->LE);
                    else
                        $buf = $part;
                }
              } else {
                $buf_o = $buf;
                $buf .= ($e == 0) ? $word : (" " . $word);

                if (strlen($buf) > $length and $buf_o != "") {
                    $message .= $buf_o . $soft_break;
                    $buf = $word;
                }
              }
          }
          $message .= $buf . $this->LE;
        }

        return $message;
    }

    /**
 Set the body wrapping.
 @access private
 @return void
     */
    public function SetWordWrap()
    {
        if($this->WordWrap < 1)

            return;

        switch ($this->message_type) {
           case "alt":
              // fall through
           case "alt_attachments":
              $this->AltBody = $this->WrapText($this->AltBody, $this->WordWrap);
              break;
           default:
              $this->Body = $this->WrapText($this->Body, $this->WordWrap);
              break;
        }
    }

    /**
 Assembles message header.
 @access private
 @return string
     */
    public function CreateHeader()
    {
        $result = "";

        // Set the boundaries
        $uniq_id = md5(uniqid(time()));
        $this->boundary[1] = "b1_" . $uniq_id;
        $this->boundary[2] = "b2_" . $uniq_id;

        $result .= $this->HeaderLine("Date", $this->RFCDate());
        if($this->Sender == "")
            $result .= $this->HeaderLine("Return-Path", trim($this->From));
        else
            $result .= $this->HeaderLine("Return-Path", trim($this->Sender));

        // To be created automatically by mail()
        if ($this->Mailer != "mail") {
            if(count($this->to) > 0)
                $result .= $this->AddrAppend("To", $this->to);
            else if (count($this->cc) == 0)
                $result .= $this->HeaderLine("To", "undisclosed-recipients:;");
            if(count($this->cc) > 0)
                $result .= $this->AddrAppend("Cc", $this->cc);
        }

        $from = array();
        $from[0][0] = trim($this->From);
        $from[0][1] = $this->FromName;
        $result .= $this->AddrAppend("From", $from);

        // sendmail and mail() extract Bcc from the header before sending
        if((($this->Mailer == "sendmail") || ($this->Mailer == "mail")) && (count($this->bcc) > 0))
            $result .= $this->AddrAppend("Bcc", $this->bcc);

        if(count($this->ReplyTo) > 0)
            $result .= $this->AddrAppend("Reply-to", $this->ReplyTo);

        // mail() sets the subject itself
        if($this->Mailer != "mail")
            $result .= $this->HeaderLine("Subject", $this->EncodeHeader(trim($this->Subject)));

        $result .= sprintf("Message-ID: <%s@%s>%s", $uniq_id, $this->ServerHostname(), $this->LE);
        $result .= $this->HeaderLine("X-Priority", $this->Priority);
        $result .= $this->HeaderLine("X-Mailer", "PHPMailer [version " . $this->Version . "]");

        if ($this->ConfirmReadingTo != "") {
            $result .= $this->HeaderLine("Disposition-Notification-To",
                       "<" . trim($this->ConfirmReadingTo) . ">");
        }

        // Add custom headers
        for ($index = 0; $index < count($this->CustomHeader); $index++) {
            $result .= $this->HeaderLine(trim($this->CustomHeader[$index][0]),
                       $this->EncodeHeader(trim($this->CustomHeader[$index][1])));
        }
        $result .= $this->HeaderLine("MIME-Version", "1.0");

        switch ($this->message_type) {
            case "plain":
                $result .= $this->HeaderLine("Content-Transfer-Encoding", $this->Encoding);
                $result .= sprintf("Content-Type: %s; charset=\"%s\"",
                                    $this->ContentType, $this->CharSet);
                break;
            case "attachments":
                // fall through
            case "alt_attachments":
                if ($this->InlineImageExists()) {
                    $result .= sprintf("Content-Type: %s;%s\ttype=\"text/html\";%s\tboundary=\"%s\"%s",
                                    "multipart/related", $this->LE, $this->LE,
                                    $this->boundary[1], $this->LE);
                } else {
                    $result .= $this->HeaderLine("Content-Type", "multipart/mixed;");
                    $result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
                }
                break;
            case "alt":
                $result .= $this->HeaderLine("Content-Type", "multipart/alternative;");
                $result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
        }

        if($this->Mailer != "mail")
            $result .= $this->LE.$this->LE;

        return $result;
    }

    /**
 Assembles the message body.  Returns an empty string on failure.
 @access private
 @return string
     */
    public function CreateBody()
    {
        $result = "";

        $this->SetWordWrap();

        switch ($this->message_type) {
            case "alt":
                $result .= $this->GetBoundary($this->boundary[1], "",
                                              "text/plain", "");
                $result .= $this->EncodeString($this->AltBody, $this->Encoding);
                $result .= $this->LE.$this->LE;
                $result .= $this->GetBoundary($this->boundary[1], "",
                                              "text/html", "");

                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE.$this->LE;

                $result .= $this->EndBoundary($this->boundary[1]);
                break;
            case "plain":
                $result .= $this->EncodeString($this->Body, $this->Encoding);
                break;
            case "attachments":
                $result .= $this->GetBoundary($this->boundary[1], "", "", "");
                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE;

                $result .= $this->AttachAll();
                break;
            case "alt_attachments":
                $result .= sprintf("--%s%s", $this->boundary[1], $this->LE);
                $result .= sprintf("Content-Type: %s;%s" .
                                   "\tboundary=\"%s\"%s",
                                   "multipart/alternative", $this->LE,
                                   $this->boundary[2], $this->LE.$this->LE);

                // Create text body
                $result .= $this->GetBoundary($this->boundary[2], "",
                                              "text/plain", "") . $this->LE;

                $result .= $this->EncodeString($this->AltBody, $this->Encoding);
                $result .= $this->LE.$this->LE;

                // Create the HTML body
                $result .= $this->GetBoundary($this->boundary[2], "",
                                              "text/html", "") . $this->LE;

                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE.$this->LE;

                $result .= $this->EndBoundary($this->boundary[2]);

                $result .= $this->AttachAll();
                break;
        }
        if($this->IsError())
            $result = "";

        return $result;
    }

    /**
 Returns the start of a message boundary.
 @access private
     */
    public function GetBoundary($boundary, $charSet, $contentType, $encoding)
    {
        $result = "";
        if ($charSet == "") { $charSet = $this->CharSet; }
        if ($contentType == "") { $contentType = $this->ContentType; }
        if ($encoding == "") { $encoding = $this->Encoding; }

        $result .= $this->TextLine("--" . $boundary);
        $result .= sprintf("Content-Type: %s; charset = \"%s\"",
                            $contentType, $charSet);
        $result .= $this->LE;
        $result .= $this->HeaderLine("Content-Transfer-Encoding", $encoding);
        $result .= $this->LE;

        return $result;
    }

    /**
 Returns the end of a message boundary.
 @access private
     */
    public function EndBoundary($boundary)
    {
        return $this->LE . "--" . $boundary . "--" . $this->LE;
    }

    /**
 Sets the message type.
 @access private
 @return void
     */
    public function SetMessageType()
    {
        if(count($this->attachment) < 1 && strlen($this->AltBody) < 1)
            $this->message_type = "plain";
        else {
            if(count($this->attachment) > 0)
                $this->message_type = "attachments";
            if(strlen($this->AltBody) > 0 && count($this->attachment) < 1)
                $this->message_type = "alt";
            if(strlen($this->AltBody) > 0 && count($this->attachment) > 0)
                $this->message_type = "alt_attachments";
        }
    }

    /**
 Returns a formatted header line.
 @access private
 @return string
     */
    public function HeaderLine($name, $value)
    {
        return $name . ": " . $value . $this->LE;
    }

    /**
 Returns a formatted mail line.
 @access private
 @return string
     */
    public function TextLine($value)
    {
        return $value . $this->LE;
    }

    /////////////////////////////////////////////////
    // ATTACHMENT METHODS
    /////////////////////////////////////////////////

    /**
 Adds an attachment from a path on the filesystem.
 Returns false if the file could not be found
 or accessed.
 @param string $path Path to the attachment.
 @param string $name Overrides the attachment name.
 @param string $encoding File encoding (see $Encoding).
 @param string $type File extension (MIME) type.
 @return bool
     */
    public function AddAttachment($path, $name = "", $encoding = "base64",
                           $type = "application/octet-stream") {
        if (!@is_file($path)) {
            $this->SetError($this->Lang("file_access") . $path);

            return false;
        }

        $filename = basename($path);
        if($name == "")
            $name = $filename;

        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $path;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $name;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = false; // isStringAttachment
        $this->attachment[$cur][6] = "attachment";
        $this->attachment[$cur][7] = 0;

        return true;
    }

    /**
 Attaches all fs, string, and binary attachments to the message.
 Returns an empty string on failure.
 @access private
 @return string
     */
    public function AttachAll()
    {
        // Return text of body
        $mime = array();

        // Add all attachments
        for ($i = 0; $i < count($this->attachment); $i++) {
            // Check for string attachment
            $bString = $this->attachment[$i][5];
            if ($bString)
                $string = $this->attachment[$i][0];
            else
                $path = $this->attachment[$i][0];

            $filename    = $this->attachment[$i][1];
            $name        = $this->attachment[$i][2];
            $encoding    = $this->attachment[$i][3];
            $type        = $this->attachment[$i][4];
            $disposition = $this->attachment[$i][6];
            $cid         = $this->attachment[$i][7];

            $mime[] = sprintf("--%s%s", $this->boundary[1], $this->LE);
            $mime[] = sprintf("Content-Type: %s; name=\"%s\"%s", $type, $name, $this->LE);
            $mime[] = sprintf("Content-Transfer-Encoding: %s%s", $encoding, $this->LE);

            if($disposition == "inline")
                $mime[] = sprintf("Content-ID: <%s>%s", $cid, $this->LE);

            $mime[] = sprintf("Content-Disposition: %s; filename=\"%s\"%s",
                              $disposition, $name, $this->LE.$this->LE);

            // Encode as string attachment
            if ($bString) {
                $mime[] = $this->EncodeString($string, $encoding);
                if ($this->IsError()) { return ""; }
                $mime[] = $this->LE.$this->LE;
            } else {
                $mime[] = $this->EncodeFile($path, $encoding);
                if ($this->IsError()) { return ""; }
                $mime[] = $this->LE.$this->LE;
            }
        }

        $mime[] = sprintf("--%s--%s", $this->boundary[1], $this->LE);

        return join("", $mime);
    }

    /**
 Encodes attachment in requested format.  Returns an
 empty string on failure.
 @access private
 @return string
     */
    public function EncodeFile ($path, $encoding = "base64")
    {
        if (!@$fd = fopen($path, "rb")) {
            $this->SetError($this->Lang("file_open") . $path);

            return "";
        }
        $magic_quotes = get_magic_quotes_runtime();
        set_magic_quotes_runtime(0);
        $file_buffer = fread($fd, filesize($path));
        $file_buffer = $this->EncodeString($file_buffer, $encoding);
        fclose($fd);
        set_magic_quotes_runtime($magic_quotes);

        return $file_buffer;
    }

    /**
 Encodes string to requested format. Returns an
 empty string on failure.
 @access private
 @return string
     */
    public function EncodeString ($str, $encoding = "base64")
    {
        $encoded = "";
        switch (strtolower($encoding)) {
          case "base64":
              // chunk_split is found in PHP >= 3.0.6
              $encoded = chunk_split(base64_encode($str), 76, $this->LE);
              break;
          case "7bit":
          case "8bit":
              $encoded = $this->FixEOL($str);
              if (substr($encoded, -(strlen($this->LE))) != $this->LE)
                $encoded .= $this->LE;
              break;
          case "binary":
              $encoded = $str;
              break;
          case "quoted-printable":
              $encoded = $this->EncodeQP($str);
              break;
          default:
              $this->SetError($this->Lang("encoding") . $encoding);
              break;
        }

        return $encoded;
    }

    /**
 Encode a header string to best of Q, B, quoted or none.
 @access private
 @return string
     */
    public function EncodeHeader ($str, $position = 'text')
    {
      $x = 0;

      switch (strtolower($position)) {
        case 'phrase':
          if (!preg_match('/[\200-\377]/', $str)) {
            // Can't use addslashes as we don't know what value has magic_quotes_sybase.
            $encoded = addcslashes($str, "\0..\37\177\\\"");

            if (($str == $encoded) && !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str))

              return ($encoded);
            else
              return ("\"$encoded\"");
          }
          $x = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
          break;
        case 'comment':
          $x = preg_match_all('/[()"]/', $str, $matches);
          // Fall-through
        case 'text':
        default:
          $x += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
          break;
      }

      if ($x == 0)
        return ($str);

      $maxlen = 75 - 7 - strlen($this->CharSet);
      // Try to select the encoding which should produce the shortest output
      if (strlen($str)/3 < $x) {
        $encoding = 'B';
        $encoded = base64_encode($str);
        $maxlen -= $maxlen % 4;
        $encoded = trim(chunk_split($encoded, $maxlen, "\n"));
      } else {
        $encoding = 'Q';
        $encoded = $this->EncodeQ($str, $position);
        $encoded = $this->WrapText($encoded, $maxlen, true);
        $encoded = str_replace("=".$this->LE, "\n", trim($encoded));
      }

      $encoded = preg_replace('/^(.*)$/m', " =?".$this->CharSet."?$encoding?\\1?=", $encoded);
      $encoded = trim(str_replace("\n", $this->LE, $encoded));

      return $encoded;
    }

    /**
 Encode string to quoted-printable.
 @access private
 @return string
     */
    public function EncodeQP ($str)
    {
        $encoded = $this->FixEOL($str);
        if (substr($encoded, -(strlen($this->LE))) != $this->LE)
            $encoded .= $this->LE;

        // Replace every high ascii, control and = characters
        $encoded = preg_replace('/([\000-\010\013\014\016-\037\075\177-\377])/e',
                  "'='.sprintf('%02X', ord('\\1'))", $encoded);
        // Replace every spaces and tabs when it's the last character on a line
        $encoded = preg_replace("/([\011\040])".$this->LE."/e",
                  "'='.sprintf('%02X', ord('\\1')).'".$this->LE."'", $encoded);

        // Maximum line length of 76 characters before CRLF (74 + space + '=')
        $encoded = $this->WrapText($encoded, 74, true);

        return $encoded;
    }

    /**
 Encode string to q encoding.
 @access private
 @return string
     */
    public function EncodeQ ($str, $position = "text")
    {
        // There should not be any EOL in the string
        $encoded = preg_replace("[\r\n]", "", $str);

        switch (strtolower($position)) {
          case "phrase":
            $encoded = preg_replace("/([^A-Za-z0-9!*+\/ -])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
            break;
          case "comment":
            $encoded = preg_replace("/([\(\)\"])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
          case "text":
          default:
            // Replace every high ascii, control =, ? and _ characters
            $encoded = preg_replace('/([\000-\011\013\014\016-\037\075\077\137\177-\377])/e',
                  "'='.sprintf('%02X', ord('\\1'))", $encoded);
            break;
        }

        // Replace every spaces to _ (more readable than =20)
        $encoded = str_replace(" ", "_", $encoded);

        return $encoded;
    }

    /**
 Adds a string or binary attachment (non-filesystem) to the list.
 This method can be used to attach ascii or binary data,
 such as a BLOB record from a database.
 @param string $string String attachment data.
 @param string $filename Name of the attachment.
 @param string $encoding File encoding (see $Encoding).
 @param string $type File extension (MIME) type.
 @return void
     */
    public function AddStringAttachment($string, $filename, $encoding = "base64",
                                 $type = "application/octet-stream") {
        // Append to $attachment array
        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $string;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $filename;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = true; // isString
        $this->attachment[$cur][6] = "attachment";
        $this->attachment[$cur][7] = 0;
    }

    /**
 Adds an embedded attachment.  This can include images, sounds, and
 just about any other document.  Make sure to set the $type to an
 image type.  For JPEG images use "image/jpeg" and for GIF images
 use "image/gif".
 @param string $path Path to the attachment.
 @param string $cid Content ID of the attachment.  Use this to identify
        the Id for accessing the image in an HTML form.
 @param string $name Overrides the attachment name.
 @param string $encoding File encoding (see $Encoding).
 @param string $type File extension (MIME) type.
 @return bool
     */
    public function AddEmbeddedImage($path, $cid, $name = "", $encoding = "base64",
                              $type = "application/octet-stream") {

        if (!@is_file($path)) {
            $this->SetError($this->Lang("file_access") . $path);

            return false;
        }

        $filename = basename($path);
        if($name == "")
            $name = $filename;

        // Append to $attachment array
        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $path;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $name;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = false; // isStringAttachment
        $this->attachment[$cur][6] = "inline";
        $this->attachment[$cur][7] = $cid;

        return true;
    }

    /**
 Returns true if an inline attachment is present.
 @access private
 @return bool
     */
    public function InlineImageExists()
    {
        $result = false;
        for ($i = 0; $i < count($this->attachment); $i++) {
            if ($this->attachment[$i][6] == "inline") {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /////////////////////////////////////////////////
    // MESSAGE RESET METHODS
    /////////////////////////////////////////////////

    /**
 Clears all recipients assigned in the TO array.  Returns void.
 @return void
     */
    public function ClearAddresses()
    {
        $this->to = array();
    }

    /**
 Clears all recipients assigned in the CC array.  Returns void.
 @return void
     */
    public function ClearCCs()
    {
        $this->cc = array();
    }

    /**
 Clears all recipients assigned in the BCC array.  Returns void.
 @return void
     */
    public function ClearBCCs()
    {
        $this->bcc = array();
    }

    /**
 Clears all recipients assigned in the ReplyTo array.  Returns void.
 @return void
     */
    public function ClearReplyTos()
    {
        $this->ReplyTo = array();
    }

    /**
 Clears all recipients assigned in the TO, CC and BCC
 array.  Returns void.
 @return void
     */
    public function ClearAllRecipients()
    {
        $this->to = array();
        $this->cc = array();
        $this->bcc = array();
    }

    /**
 Clears all previously set filesystem, string, and binary
 attachments.  Returns void.
 @return void
     */
    public function ClearAttachments()
    {
        $this->attachment = array();
    }

    /**
 Clears all custom headers.  Returns void.
 @return void
     */
    public function ClearCustomHeaders()
    {
        $this->CustomHeader = array();
    }


    /////////////////////////////////////////////////
    // MISCELLANEOUS METHODS
    /////////////////////////////////////////////////

    /**
 Adds the error message to the error container.
 Returns void.
 @access private
 @return void
     */
    public function SetError($msg)
    {
        $this->error_count++;
        $this->ErrorInfo = $msg;
    }

    /**
 Returns the proper RFC 822 formatted date.
 @access private
 @return string
     */
    public function RFCDate()
    {
        $tz = date("Z");
        $tzs = ($tz < 0) ? "-" : "+";
        $tz = abs($tz);
        $tz = ($tz/3600)*100 + ($tz%3600)/60;
        $result = sprintf("%s %s%04d", date("D, j M Y H:i:s"), $tzs, $tz);

        return $result;
    }

    /**
 Returns the appropriate server variable.  Should work with both
 PHP 4.1.0+ as well as older versions.  Returns an empty string
 if nothing is found.
 @access private
 @return mixed
     */
    public function ServerVar($varName)
    {
        global $HTTP_SERVER_VARS;
        global $HTTP_ENV_VARS;

        if (!isset($_SERVER)) {
            $_SERVER = $HTTP_SERVER_VARS;
            if(!isset($_SERVER["REMOTE_ADDR"]))
                $_SERVER = $HTTP_ENV_VARS; // must be Apache
        }

        if(isset($_SERVER[$varName]))

            return $_SERVER[$varName];
        else
            return "";
    }

    /**
 Returns the server hostname or 'localhost.localdomain' if unknown.
 @access private
 @return string
     */
    public function ServerHostname()
    {
        if ($this->Hostname != "")
            $result = $this->Hostname;
        elseif ($this->ServerVar('SERVER_NAME') != "")
            $result = $this->ServerVar('SERVER_NAME');
        else
            $result = "localhost.localdomain";

        return $result;
    }

    /**
 Returns a message in the appropriate language.
 @access private
 @return string
     */
    public function Lang($key)
    {
        if(count($this->language) < 1)
            $this->SetLanguage("en"); // set the default language

        if(isset($this->language[$key]))

            return $this->language[$key];
        else
            return "Language string failed to load: " . $key;
    }

    /**
 Returns true if an error occurred.
 @return bool
     */
    public function IsError()
    {
        return ($this->error_count > 0);
    }

    /**
 Changes every end of line from CR or LF to CRLF.
 @access private
 @return string
     */
    public function FixEOL($str)
    {
        $str = str_replace("\r\n", "\n", $str);
        $str = str_replace("\r", "\n", $str);
        $str = str_replace("\n", $this->LE, $str);

        return $str;
    }

    /**
 Adds a custom header.
 @return void
     */
    public function AddCustomHeader($custom_header)
    {
        $this->CustomHeader[] = explode(":", $custom_header, 2);
    }
}
