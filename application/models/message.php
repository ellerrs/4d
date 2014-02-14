<?php
require 'PHPMailerAutoload.php';

class Message_Model extends ORM
{
	protected $belongs_to = array('user');
	
	private $domain = 'http://chess.teknine.com';
	private $sender = 'chessmaster@ches';

	public function push($view=null)
	{
		$to_user = ORM::factory('user',$this->to_user_id);
		if($to_user->notifications)
		{
			// if magic quotes is on, strip slashes from message
			$message = $this->text;
			if (get_magic_quotes_gpc() == 1) {
				$message = stripslashes($message);
			}
			$subject = preg_replace("|<a *href=\"(.*)\">(.*)</a>|","\\2",$this->subject);
			if(isset($view)) $message .= ' [domain]/'.$view;
			
			$subject = str_replace("[domain]",$this->domain,$subject);
			$message = str_replace("[domain]",$this->domain,$message);
		//Create a new PHPMailer instance
$mail = new PHPMailer();
//Tell PHPMailer to use SMTP
$mail->isSMTP();
//Enable SMTP debugging
// 0 = off (for production use)
// 1 = client messages
// 2 = client and server messages
$mail->SMTPDebug = 2;
//Ask for HTML-friendly debug output
$mail->Debugoutput = 'html';
//Set the hostname of the mail server
//Set the SMTP port number - likely to be 25, 465 or 587
$mail->Port = 25;
//Whether to use SMTP authentication
$mail->SMTPAuth = true;
//Username to use for SMTP authentication
//Password to use for SMTP authentication
//Set who the message is to be sent from
$mail->setFrom('yourmove@teknine.com', 'Its Your Move');
//Set who the message is to be sent to
$mail->addAddress($to_user->email);
//Set the subject line	
$mail->Subject = $subject;
$mail->AltBody = $message;
$mail->msgHTML($message);
//$mail->send();
			// send email
//	$mail_headers = 'From: '.$this->sender."\r\n".'X-Mailer: PHP/' . phpversion();
//	$mail_status = mail(
//			$to_user->email,
//				$subject,
//				$message,
//				$mail_headers 
//			);
//			if ($mail_status === false) 
//			{
//				//todo Send a message to the original user so they know the message didn't get sent?
//			}
		}
	}

}

?>
