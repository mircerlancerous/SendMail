# SendMail

Originally written by Vasyl Rusanovskyy but since then significantly modified. Enhancements include support for multiple recipients on one connection, pdf attachments and inline base64 images for HTML emails. Could use some updating but is a great, lightweight, reliable method for getting versatile email functionality going with little effort.

 
Usage:

 

include_once("SendMail.class.php");
$mail = new Mail();
//default email server details can be set in the file or set/adjusted at this point

//for a single send
$mail->Send($mail_to, $name_to, $subject, $message);

//for multiple recipients with one smtp connection
$recipientArr = array(
		array("address"=>"recip1@email.com","name"=>"Recipient One"),
		array("address"=>"recip2@email.com","name"=>"Recipient Two"),
		array("address"=>"recip3@email.com","name"=>"Recipient Three")
	};
$mail->multiSendSMTP($recipientArr, $subject, $message);

//for multiple recipients with different messages and one smtp connection
$subjectArr = array(
		"Msg 1 Sub",
		"Msg 2 Sub",
		"Msg 3 Sub"
	);
$messageArr = array(
		"Message 1 Body",
		"Message 2 Body",
		"Message 3 Body"
	);
$mail->multiSendSMTP($recipientArr, $subjectArr, $messageArr);
