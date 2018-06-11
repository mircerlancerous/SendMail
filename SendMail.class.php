<?php
##============================================================================##
##                       -=* Vasyl Rusanovskyy *=-        02.03.2011          ##
##                         rusanovskyy@gmail.com                              ##
##                            Capital-Media                                   ##
##                          www.k-media.com.ua                                ##
##                        office@k-media.com.ua                               ##
##============================================================================##

/*******************************************************************************

Modified By Dave Robinson
June 2011 - xx
www.offthebricks.com
https://github.com/mircerlancerous/SendMail/

*******************************************************************************/

Class SendMail{

	public $smtp_host = "mail.yourdomain.com";
	public $smtp_port = 25;
	public $smtp_user = "youruser@yourdomain.com";
	public $smtp_password = "YourPass";
	public $from_name = "YourName";
	public $SendFromMail = "no-reply@yourmail.com";
	public $ReplyToMail = "no-reply@yourmail.com";
	public $mail_to;
	public $subject;
	public $message;
	public $altmessage;
	public $headers = '';
	public $ContentType = 'html';	//html or plain
	public $charset = 'ISO-8859-1';//'windows-1251';
	public $smtp_debug = true;
	public $socket;
	public $error = "";
	public $errorCount = 0;
	public $SendMailVia  = 'smtp';
	public $priority  = '3';
	private $any_attachments = FALSE;
	private $attachments = array();
	private $attachment_types = array(
			'pdf' => array('ctype' => 'application/pdf', 'encoding' => 'base64'),
			'ics' => array('ctype' => 'text/calendar', 'encoding' => '8bit')
		);
	
  public function __construct()
	{
			if($this->SendFromMail == ''){
			   $this->SendFromMail = $this->smtp_user;
			}
	}
	
	private function SMTPconnect(){
		$socket = fsockopen($this->smtp_host, $this->smtp_port, $errno, $errstr, 30);
	    if(!$socket){
        	if($this->smtp_debug) $this->error .= $errno." - ".$errstr;
        	return false;
        }
				
		if (!$this->server_parse($socket, "220", __LINE__)){ return false; }
				
     //   fputs($socket, "HELO ".$this->smtp_host. "\r\n");
        fputs($socket, "EHLO ".$this->smtp_host. "\r\n");
        if(!$this->server_parse($socket, "250", __LINE__)){
        	if($this->smtp_debug){
				$this->error .= '<p>Can not send HELO!</p>';
			}
        	fclose($socket);
        	return false;
		}
		if($this->smtp_user && $this->smtp_password){
            fputs($socket, "AUTH LOGIN\r\n");
            if (!$this->server_parse($socket, "334", __LINE__)) {
               if ($this->smtp_debug) $this->error .= '<p>Can not find an answer to a request authorization.</p>';
               fclose($socket);
               return false;
            }
            fputs($socket, base64_encode($this->smtp_user) . "\r\n");
            if (!$this->server_parse($socket, "334", __LINE__)) {
               if ($this->smtp_debug) $this->error .= '<p>Login authorization was not accepted by server!</p>';
               fclose($socket);
               return false;
            }
            fputs($socket, base64_encode($this->smtp_password) . "\r\n");
            if (!$this->server_parse($socket, "235", __LINE__)) {
               if ($this->smtp_debug) $this->error .= '<p>No password was not accepted as a true server! Authorization Error!</p>';
               fclose($socket);
               return false;
            }
		}
		
		return $socket;
	}
	
	private function SMTPclose($socket){
        fputs($socket, "QUIT\r\n");
        fclose($socket);
	}
	
	//send several messages using the same socket connection instead of reconnecting for each one
	public function multiSendSMTP($addresses, $subject = NULL, $message = NULL){
		if(!is_array($addresses)){
			$this->error .= "'mail_to' must be an array";
			return FALSE;
		}
		
		$socket = $this->SMTPconnect();
		if(!$socket){
			$this->error .= "SMTP connection error";
			return FALSE;
		}
		
		if($subject && !is_array($subject)){
			$this->subject = stripslashes($subject);
		}
		if($message && !is_array($message)){
			$this->message = $message;
		}
		
		//mail_to contains array elements 'address', and 'name'
		foreach($addresses as $i => $mail_to){
			if($mail_to['address']){
				stripslashes($mail_to['address']);
			}
			if($subject && is_array($subject)){
				$this->subject = stripslashes($subject[$i]);
			}
			if($message && is_array($message)){
				$this->message = $message[$i];
			}
			if($mail_to['address']){
				//address validation will be done on email entry and editing to improve efficiency
				if(!$this->SMTPsend($mail_to['address'], $mail_to['name'], $socket)){
					$this->errorCount++;
					$this->error .= "\r\nError sending to ".$mail_to['name']." at ".$mail_to['address'];
				}
			}
			else{
				$this->errorCount++;
				$this->error .= "\r\nBlank email on recipient ".($i + 1);
			}
		}
		
		$this->SMTPclose($socket);

		if($this->errorCount > 0){
			return FALSE;
		}
		return TRUE;
	}
	
	public function Send($mail_to = '', $name_to = '', $subject = '', $message = '', $altmessage = '')
	{
	    $mail_to = trim($mail_to);
	    $name_to = trim($name_to);
	    $subject = trim($subject);
	    
	    if($mail_to!=''){$this->mail_to = stripslashes($mail_to);}
		if($subject!=''){$this->subject = stripslashes($subject);}
		if($message!=''){$this->message = $message;}
		if($altmessage!=''){$this->altmessage = $altmessage;}

		$validEmail = $this->validEmail($mail_to);
		if($validEmail){
			if($this->SendMailVia=='smtp'){
		  		$socket = $this->SMTPconnect();
				if(!$socket){
					return FALSE;
				}
				if(!$this->SMTPsend($mail_to, $name_to, $socket)){
					$this->SMTPclose($socket);
					return FALSE;
				}
				$this->SMTPclose($socket);
				return TRUE;
			}
			return $this->MAILsend($mail_to, $name_to);	
		}
		$this->error .= "invalid email address";
		return FALSE;
	}
	
	private function MAILsend($mail_to, $name_to)
	{
		if(!$this->ReplyToMail){
			$this->ReplyToMail = $this->SendFromMail;
		}
    	$header="Return-Path: ".trim($this->smtp_user)."\r\n".
  			"Reply-To: ".trim($this->ReplyToMail)."\r\n".
  			"From: ".trim($this->from_name)." <".trim($this->SendFromMail).">\r\n".
  			"Subject: ".$this->subject."\r\n";
  		
    	$header .= "Content-Type: text/".$this->ContentType."; charset=".$this->charset."\r\n";
	  	
	  	if(mail("$name_to <$mail_to>",$this->subject,$this->message,$header)){
			return true;
		}else{
			return false;
		}
  	}
	
	private function SMTPsend($mail_to, $name_to, $socket){
		//build smtp message
		$SEND .=   "Date: ".gmdate("D, d M Y H:i:s") . "\r\n";
		$SEND .=   'Subject: =?'.$this->charset.'?B?'.base64_encode($this->subject)."=?=\r\n";
		if ($this->headers!=''){
			$SEND .= $this->headers."\r\n\r\n";
		}
      	else{
			if(!$this->ReplyToMail){
				$this->ReplyToMail = $this->SendFromMail;
			}
		    $SEND .= "Reply-To: ".trim($this->ReplyToMail)."\r\n";
		    $SEND .= "X-Accept-Language: en\r\n";
		    $SEND .= "MIME-Version: 1.0\r\n";
			
		    $SEND .= "From: ".trim($this->from_name)." <".trim($this->SendFromMail).">\r\n";
		    $SEND .= "To: $name_to <$mail_to>\r\n";
		    $SEND .= "X-Priority: ".$this->priority."\r\n";
		    
			if ($this->any_attachments) {
				$SEND .= "Content-Type: multipart/mixed; boundary=a95ed0b485e4a9b0fd4ff93f50ad06cb \r\n\r\n";
				$SEND .= "--a95ed0b485e4a9b0fd4ff93f50ad06cb\r\n";
			}
			
			if($this->altmessage){
				$semi_rand = md5(time());
				$SEND .= "Content-Type: multipart/alternative; boundary=$semi_rand \r\n\r\n";
				$SEND .= "--$semi_rand\r\n";
				$SEND .= "Content-Type: text/plain; charset=".$this->charset."\r\n";
				$SEND .= "Content-Transfer-Encoding: 8bit\r\n";
				$SEND .="\r\n";
				$SEND .= $this->altmessage;
				$SEND .= "\r\n\r\n--$semi_rand\r\n";
			}
			
			$SEND .= "Content-Type: text/".$this->ContentType."; charset=".$this->charset."\r\n";
			$SEND .= "Content-Transfer-Encoding: 8bit\r\n";
		
			$SEND .="\r\n";
		}
		
		//check for base64 images embedded in the message
	    $imgcount = 0;
		$pos = strpos($this->message,"<img");
		//if no images found
		if($pos === FALSE){
	    	$SEND .=  $this->message."\r\n";
	    }
	    //check if any images are base64
	    else{
	    	$msgbuf = "";
			while($pos !== FALSE){
				//find the image src
				$pos = strpos($this->message,"src=",$pos);
				$quote = substr($this->message,$pos+4,1);
				$pos += 5;
				if(substr($this->message,$pos,5) != 'data:'){
					$pos = strpos($this->message,"<img",$pos);
					continue;
				}
				//this is a base64 image so process accordingly
				$imgcount++;
				$inpos = $pos-1;
				$pos += 5;
				$pos2 = strpos($this->message,";",$pos);
				$type = substr($this->message,$pos,$pos2-$pos);
				$pos = 1 + strpos($this->message,"/",$pos);
				$ext = substr($this->message,$pos,$pos2-$pos);
				$msgbuf .= "\r\n\r\n--a95ed0b485e4a9b0fd4ff93f50ad06cb\r\n";
				$msgbuf .= 'Content-Type: ' . $type . '; name="embimg' . $imgcount . '.' . $ext . '"'."\r\n";
				$msgbuf .= 'Content-Transfer-Encoding: base64'."\r\n";
				$msgbuf .= 'Content-ID: <embimg'.$imgcount.'.01060107.06090408>'."\r\n";
				$msgbuf .= 'Content-Disposition: attachment; filename="embimg' . $imgcount . '.' . $ext . '"'."\r\n\r\n";
				
				$pos = 1 + strpos($this->message,",",$pos);
				$pos2 = strpos($this->message,$quote,$pos);
				$msgbuf .= substr($this->message,$pos,$pos2-$pos)."\r\n";
				$this->message = substr($this->message,0,$inpos).'"'."cid:embimg".$imgcount.".01060107.06090408".'"'.substr($this->message,$pos2+1);
				
				$pos = strpos($this->message,"<img",$inpos);
			}
			$SEND .= $this->message."\r\n".$msgbuf;
		}
		
		if ($this->any_attachments) {
			foreach($this->attachments as $att) {
				$type = $this->attachment_types[$att['type']];
				$SEND .= "\r\n\r\n--a95ed0b485e4a9b0fd4ff93f50ad06cb\r\n";
				$SEND .= 'Content-Type: ' . $type['ctype'] . '; name="' . $att['name'] . '"'."\r\n";
				$SEND .= 'Content-Transfer-Encoding: ' . $type['encoding'] . "\r\n";
				$SEND .= 'Content-Disposition: attachment; filename="' . $att['name'] . '"'."\r\n\r\n";
				
				$SEND .= $att['content'] . "\r\n";
			}
		}
		
		if($imgcount > 0 || $this->any_attachments){
			$SEND .= "\r\n--a95ed0b485e4a9b0fd4ff93f50ad06cb--\r\n";
		}
 
/**
socket connect stuff placed in separate function to allow for multi message send on one connection
**/
            fputs($socket, "MAIL FROM: <".trim($this->ReplyToMail).">\r\n");
            if (!$this->server_parse($socket, "250", __LINE__)) {
               if ($this->smtp_debug) $this->error .= '<p>Unable to send command MAIL FROM: </p>';
      //         fclose($socket);
               return false;
            }
            fputs($socket, "RCPT TO: <" . $mail_to . ">\r\n");
            if (!$this->server_parse($socket, "250", __LINE__)) {
               if ($this->smtp_debug) $this->error .= '<p>Unable to send command RCPT TO: </p>';
      //         fclose($socket);
               return false;
            }
            fputs($socket, "DATA\r\n");
            if (!$this->server_parse($socket, "354", __LINE__)) {
               if ($this->smtp_debug) $this->error .= '<p>Unable to send command DATA</p>';
      //         fclose($socket);
               return false;
            }
            fputs($socket, $SEND."\r\n.\r\n");
            if (!$this->server_parse($socket, "250", __LINE__)) {
               if ($this->smtp_debug) $this->error .= '<p>Unable to send the message body. The letter was sent!</p>';
      //         fclose($socket);
               return false;
            }
			
/**
socket connect stuff placed in separate function to allow for multi message send on one connection
**/
            return TRUE;
	}
	
	
	
	private function GetMailAndNameArr(){
	    $mailingArr = array();
			$tos = preg_split("/;|,/",$this->mail_to);
			$pregcode = '/(.*?)<(.*?)>/i';
			foreach($tos as $to)
			{
			  if(preg_match('/(.*?)<(.*?)>/i',$to,$matches))
				{
				  unset($matches[0]);	
				  $matches[1] = trim(str_replace('"','',$matches[1]));
				  $matches[2] = trim($matches[2]);
				  $mailingArr[] =$matches; 
				}
				elseif(preg_match('/\b([A-Z0-9._%-]+)@([A-Z0-9.-]+\.[A-Z]{2,4})\b/i',$to,$matches2))
				{
					 unset($matches[0]);	
					 $matches[1] = trim(str_replace('"','',$matches2[1]));
					 $matches[2] = trim($matches2[0]);
					 $mailingArr[] =$matches;
				}
			}
			return $mailingArr;
	}
	
	private function server_parse($socket, $response, $line = __LINE__) {
		$server_response = NULL;
	    while (substr($server_response, 3, 1) != ' ') {
	          if (!($server_response = fgets($socket, 256))) {
	               if ($this->smtp_debug) $this->error .= "<p>$line Problems sending mail! $response</p>";
	               return false;
	          }//echo "<p>$server_response</p>";
	    }
	    if (!(substr($server_response, 0, 3) == $response)) {
           if ($this->smtp_debug) $this->error .= "<p>$line Problems sending mail! $response</p>";
           return false;
        }
	    return true;
  }

	//todo - add checks for extensions and double quotes
  public function validEmail($email)
  {
  	if(!$email){
		return FALSE;
	}
  
    $isValid = true;
    $atIndex = strrpos($email, "@");
	  $msg = '';
    if (is_bool($atIndex) && !$atIndex)
    {
      $isValid = false;
    }
    else
    {
      $domain = substr($email, $atIndex+1);
      $local = substr($email, 0, $atIndex);
      $localLen = strlen($local);
      $domainLen = strlen($domain);
      if ($localLen < 1 || $localLen > 64){
				 $msg = 'local part length exceeded';
         $isValid = false;
      }
      else if ($domainLen < 1 || $domainLen > 255){
				 $msg = ' domain part length exceeded ';
         $isValid = false;
      }
      else if ($local[0] == '.' || $local[$localLen-1] == '.'){
				 $msg = ' local part starts or ends with .';
         $isValid = false;
      }
      else if (preg_match('/\\.\\./', $local)){
				 $msg = 'local part has two consecutive dots';
         $isValid = false;
      }
      else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)){
				 $msg = 'character not valid in domain part';
         $isValid = false;
      }
      else if (preg_match('/\\.\\./', $domain)){
				 $msg = '  domain part has two consecutive dots';
         $isValid = false;
      }
      else if(!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))){
				 $msg = '  character not valid in local part unless local part is quoted';
         if (!preg_match('/^"(\\\\"|[^"])+"$/',str_replace("\\\\","",$local))){
            $isValid = false;
         }
      }
	  //
	/*  if(function_exists("checkdnsrr")){
	      if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))){
					 $msg = '  domain <b>'.$domain.'</b> not found in DNS';
	         $isValid = false;
	      }
	  }
	  else{
	  	//todo - put some other kind of simple syntax check here
	  }*/
    }
	  //$this->error .= $msg;
	if($msg){
	//  	echo $msg." -- ";
		$this->error .= $msg;
	}
    return $isValid;
  }
  
  public function Attach($file_name, $content) {
  	preg_match('/.*\.(.*)$/', $file_name, $type);
	$type = strtolower($type[1]);
	$this->any_attachments = TRUE;
	if ($this->attachment_types[$type]['encoding'] == 'base64') {
		$content = base64_encode($content);
	}
	$this->attachments[] = array('name' => $file_name, 'type' => $type, 'content' => $content);
  }
  
  public function clearAttachments(){
  	$this->attachments = array();
  	$this->any_attachments = FALSE;
  }

}
?>
