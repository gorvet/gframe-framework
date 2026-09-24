<?php
// Core mail service.




use PHPMailer\PHPMailer\PHPMailer;

class Email {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(TRUE);
    //  $this->mailer->SMTPDebug = 2;
        $this->mailer->CharSet = 'UTF-8'; 
        $this->mailer->Encoding = 'base64';
        $this->mailer->isSMTP();
        $this->mailer->SMTPAuth = true;

    // Datos del servidor y usuario
        $this->mailer->Host = M_Host;
        $this->mailer->Port = M_Port;
        $this->mailer->Username = M_Username;
        $this->mailer->Password = M_Password;
        $this->mailer->SMTPSecure = M_Secure;
        $this->mailer->Timeout = 60; 

    // Remitente
        $this->mailer->setFrom(M_From, M_Name);
        $this->mailer->isHTML(true);
    //  $this->mailer->AltBody = 'El texto como elemento de texto simple';
    }

    public function sendMail($address, $subject,$body,$email=M_From ) {
        $adressName = $this->buildDisplayNameFromEmail((string)$address);
       try {
        $this->mailer->addReplyTo($email, M_Name );
        $this->mailer->addAddress($address, $adressName);
        $this->mailer->Subject = $subject;
        $this->mailer->Body = $body;
        $this->mailer->send(); 
 
        return 'okMailSend';
    } catch (Exception $e) {
        if (DebugMode) {
 
            return  $e->getMessage(); //'noMailSend';
        }
        else { 
             return  'noMailSend';
        }
        //echo '2Error al enviar el correo: ' . $e->getMessage();
    }

}

private function buildDisplayNameFromEmail(string $email, string $fallback = 'Usuario'): string {
    $email = trim($email);
    if ($email === '' || strpos($email, '@') === false) {
        return $fallback;
    }

    $local = trim((string)strtok($email, '@'));
    if ($local === '') {
        return $fallback;
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        $first = (string)mb_substr($local, 0, 1, 'UTF-8');
        $rest = (string)mb_substr($local, 1, null, 'UTF-8');
        if ($first !== '') {
            return mb_strtoupper($first, 'UTF-8') . $rest;
        }
    }

    return ucfirst($local);
}


public function sendAsyncMail($params){
    try {
        (new Async())->create(function () use ($params) {
            try {
                $result = (new Email())->sendTemplateMail($params);
            } catch (\Throwable $e) {
                $result = $e->getMessage();
            }

            if ($result !== 'okMailSend') {
                LogHelper::write([
                    'result' => $result,
                    'subject' => $params['subject'] ?? '',
                    'email' => $params['email'] ?? '',
                ], 'mail_async_errors');
            }
        });

        return 'okMailSend';
    } catch (\Throwable $e) {
        LogHelper::write($e->getMessage(), 'mail_async_errors');

        if (DebugMode) {
            return $e->getMessage();
        }

        return 'noMailSend';
    }
}

public function sendTemplateMail($params){

$htmlContent = file_get_contents(realpath( ABSPATH . $params['htmlContent']));

if (isset($params['toMe'])&&$params['toMe']==true) {
    $sendName=M_Name;
    $sendMail=M_From;
    $replyName=isset($params['name'])?$params['name']:'';
    $replyMail=isset($params['email'])?$params['email']:$sendMail;
}
else{
    $replyName=M_Name;
    $replyMail=M_From;
    $sendName=isset($params['name'])?$params['name']:'';
    $sendMail=isset($params['email'])?$params['email']:$replyMail;
}


 

$subject = $params['subject']??'';

$title = $params['title']??'';
$body = str_replace('{{title}}', $title, $htmlContent);
 


foreach ($params as $key => $value) {
        if ($key === 'message') {
          $lines = preg_split("/\r\n/", $value);
          $value = "<p>" . implode("</p><p>", $lines) . "</p>";
        }
       
        $body = str_replace('{{' . $key . '}}', $value, $body);
}

 
        
       try {
        $this->mailer->addReplyTo($replyMail, $replyName);
        $this->mailer->addAddress($sendMail, $sendName);
        $this->mailer->Subject = $subject;
        $this->mailer->Body = $body;
        $this->mailer->send(); 
 
        return 'okMailSend';
    } catch (Exception $e) {
        if (DebugMode) {
 
            return  $e->getMessage(); //'noMailSend';
        }
        else { 
             return  'noMailSend';
        }
        //echo '2Error al enviar el correo: ' . $e->getMessage();
    }

}


}

