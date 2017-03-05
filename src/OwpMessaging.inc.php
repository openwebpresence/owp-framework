<?php
/**
 * OpenWebPresence Support Library - openwebpresence.com
 *
 * @copyright 2001 - 2017, Brian Tafoya.
 * @package   OwpMessaging
 * @author    Brian Tafoya <btafoya@briantafoya.com>
 * @version   1.0
 * @license   MIT
 * @license   https://opensource.org/licenses/MIT The MIT License
 * @category  OpenWebPresence_Support_Library
 * @link      http://openwebpresence.com OpenWebPresence
 *
 * Copyright (c) 2017, Brian Tafoya
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * This class provides messaging for the owpFramework library utilizing PHPMailer.
 */
class OwpMessaging
{

    /**
     * @var array $errors Error array
     */
    public $errors = array();

    /**
     * @var string $root_path The root file path.
     */
    private $root_path = null;

    /**
     * Constructor.
     *
     * @param string $root_path The root file path.
     */
    function __construct($root_path)
    {
        $this->root_path = $root_path;
    }

    /**
     * sendEmailDirect
     *
     * @method boolean sendEmailDirect($data_array) Send email directly to the recipient's mail server using the PHPMailer library.
     * @access public
     * @return boolean
     * @param  array $data_array Mailer data array.
     *
     * @throws InvalidArgumentException Missing required argument
     * @throws Exception Meage send failure.
     *
     * @author  Brian Tafoya
     * @version 1.0
     */
    public function sendEmailDirect($data_array)
    {

        if($this->validateData($data_array)) {
            throw new InvalidArgumentException($this->errors);
        }

        try {
            $email_to_clean = filter_var($data_array["email_to"], FILTER_SANITIZE_EMAIL);
            list($to_addy_info) = imap_rfc822_parse_adrlist($email_to_clean, "");
            $dns_get_mx = dns_get_record($to_addy_info->host, DNS_MX);
            $ip = gethostbyname($dns_get_mx[0]["target"]);
            $message_sent = $this->sendCore($data_array, (string)$ip);
        } catch (Exception $e) {
            throw new Exception("'Caught exception: ". $e->getMessage());
        }

        if(!$message_sent) {
            throw new InvalidArgumentException($this->errors);
        }

        return $message_sent;
    }


    /**
     * sendEmailViaSMTP()
     *
     * @method boolean sendEmailViaSMTP($data_array) Send email via mail server using the PHPMailer library.
     * @access public
     * @return boolean
     * @param  array $data_array Mailer data array.
     *
     * @throws InvalidArgumentException Missing required argument
     * @throws Exception Meage send failure.
     *
     * @author  Brian Tafoya
     * @version 1.0
     */
    public function sendEmailViaSMTP($data_array)
    {
        if($this->validateData($data_array)) {
            throw new InvalidArgumentException($this->errors);
        }

        try {
            $message_sent = $this->sendCore($data_array, (string)getenv("smtp_hostname"));
        } catch (Exception $e) {
            throw new Exception("'Caught exception: ". $e->getMessage());
        }

        if(!$message_sent) {
            throw new InvalidArgumentException($this->errors);
        }

        return $message_sent;
    }

    /**
     * sendCore()
     *
     * @method boolean sendCore($data_array, $smtp_hostname) Core PHPMailer library.
     * @access private
     * @return boolean
     * @param  array  $data_array    Mailer data array.
     * @param  String $smtp_hostname hostname to send to.
     * @throws Exception Mail end failure.
     *
     * @author  Brian Tafoya
     * @version 1.0
     */
    private function sendCore($data_array, $smtp_hostname)
    {
        $email_to_clean = filter_var($data_array["email_to"], FILTER_SANITIZE_EMAIL);

        $mail = new PHPMailer;

        $mail->isSMTP();
        $mail->Host = $smtp_hostname;
        $mail->SMTPAuth = (bool)getenv("smtp_hostname");
        if((bool)getenv("smtp_hostname")) {
            $mail->Username = (string)getenv("smtp_username");
            $mail->Password = (string)getenv("smtp_password");
        }
        if((string)getenv("smtp_secure") != "none") {
            $mail->SMTPSecure = (string)getenv("smtp_secure");
        }
        $mail->Port = (string)getenv("smtp_port");

        $mail->XMailer = "OpenWebPresence-1.0";
        $mail->Helo = $data_array["sender_domain"];
        $mail->Hostname = $data_array["sender_domain"];

        $mail->From = $data_array["email_from"];
        $mail->FromName = $data_array["email_from_name"];
        $mail->AddAddress($email_to_clean, $data_array["email_to_name"]);
        $mail->AddReplyTo(filter_var($data_array["reply_to"], FILTER_SANITIZE_EMAIL));

        $mail->DKIM_domain = getenv("DKIM_domain");
        $mail->DKIM_private = $this->root_path . 'PHPMailer_DKIM/' . getenv("DKIM_private") . '.htkeyprivate';
        $mail->DKIM_selector = getenv("DKIM_selector");
        $mail->DKIM_passphrase = getenv("DKIM_passphrase");
        $mail->DKIM_identity = getenv("DKIM_identity");

        $mail->WordWrap = 50;
        $mail->IsHTML(true);

        $mail->Subject = $data_array["subject"];
        $mail->Body    = $data_array["message_body"];

        $mail->addCustomHeader("X-AntiAbuse", "This is a solicited email for " . $data_array["sender_domain"]. ".");
        $mail->addCustomHeader("X-AntiAbuse", $data_array["email_from"]);

        $message_sent = (boolean)($mail->Send()?true:false);

        if(!$message_sent) {
            throw new Exception($mail->ErrorInfo);
        }

        return true;
    }

    /**
     * validateData
     *
     * @method validateData() Validate the arguments used to send a message.
     * @access private
     * @param  array $data_array Mail data to be validated.
     * @return boolean
     *
     * @author  Brian Tafoya
     * @version 1.0
     */
    private function validateData($data_array)
    {
        $required_variables = array("sender_domain", "subject", "message_body", "email_to", "email_to_name", "email_from", "email_from_name", "reply_to");

        $missing_columns = array_diff($required_variables, array_keys($data_array));

        if($missing_columns) {
            throw new InvalidArgumentException("The following columns are missing: " . implode(", ", $missing_columns));
        }

        return true;
    }
}

