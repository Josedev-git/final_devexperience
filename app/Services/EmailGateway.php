<?php
declare(strict_types=1);
namespace Services;
/** SMTP opcional; segredos são lidos apenas no servidor. true significa aceite, não entrega. */
final class EmailGateway {
    public function send(string $to,string $subject,string $message):bool {
        $from=env('MAIL_FROM');
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$subject.$to.$from))return false;
        $host=env('SMTP_HOST');
        if($host==='') return function_exists('mail') && @mail($to,$subject,$message,'From: '.$from);
        if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$host))return false;
        $port=(int)env('SMTP_PORT','587');$security=env('SMTP_SECURITY','tls');
        if($port<1||$port>65535||!in_array($security,['tls','ssl','none'],true))return false;
        // Usuário/senha nunca são transmitidos sem TLS.
        if($security==='none' && env('SMTP_USER')!=='')return false;
        $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host]]);
        $ca=env('SMTP_CA_FILE');if($ca!=='' && is_file($ca))stream_context_set_option($context,'ssl','cafile',$ca);
        $socket=null;
        try {
            $socket=@stream_socket_client(($security==='ssl'?'tls':'tcp').'://'.$host.':'.$port,$errno,$error,8,STREAM_CLIENT_CONNECT,$context);
            if(!is_resource($socket))return false;
            stream_set_timeout($socket,8);
            $read=function(array $expected)use($socket):void {
                for($i=0;$i<100;$i++) {
                    $line=fgets($socket,2048);
                    if($line===false)throw new \RuntimeException('SMTP sem resposta.');
                    if(preg_match('/^(\d{3}) /',$line,$match)) {
                        if(!in_array((int)$match[1],$expected,true))throw new \RuntimeException('SMTP recusou operação.');
                        return;
                    }
                }
                throw new \RuntimeException('Resposta SMTP inválida.');
            };
            $write=function(string $line)use($socket):void {
                $data=$line."\r\n";
                while($data!=='') { $written=fwrite($socket,$data);if($written===false||$written===0)throw new \RuntimeException('Falha de escrita SMTP.');$data=substr($data,$written); }
            };
            $read([220]);$write('EHLO localhost');$read([250]);
            if($security==='tls') {
                $write('STARTTLS');$read([220]);
                if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new \RuntimeException('TLS não iniciado.');
                $write('EHLO localhost');$read([250]);
            }
            if(env('SMTP_USER')!=='') {
                $write('AUTH LOGIN');$read([334]);$write(base64_encode(env('SMTP_USER')));$read([334]);$write(base64_encode(env('SMTP_PASS')));$read([235]);
            }
            $write('MAIL FROM:<'.$from.'>');$read([250]);$write('RCPT TO:<'.$to.'>');$read([250,251]);$write('DATA');$read([354]);
            $headers=['From: '.$from,'To: '.$to,'Subject: =?UTF-8?B?'.base64_encode($subject).'?=','Date: '.date(DATE_RFC2822),'Message-ID: <'.bin2hex(random_bytes(16)).'@localhost>','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64'];
            $write(implode("\r\n",$headers)."\r\n\r\n".rtrim(chunk_split(base64_encode($message),76,"\r\n"))."\r\n.");$read([250]);
            // A mensagem já foi aceita. Falha no QUIT não deve causar reenvio duplicado.
            @fwrite($socket,"QUIT\r\n");return true;
        } catch(\Throwable $error) { return false; }
        finally { if(is_resource($socket))fclose($socket); }
    }
}
