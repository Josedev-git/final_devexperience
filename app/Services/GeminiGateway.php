<?php
declare(strict_types=1);
namespace Services;
final class GeminiGateway {
    public function __construct(private mixed $transport=null) {}
    public function reply(string $instructions,string $input):array {
        if(env('AI_ENABLED','1')!=='1') return ['text'=>'','source'=>'local','reason'=>'disabled'];
        $key=env('GEMINI_API_KEY');
        if($key==='') return ['text'=>'','source'=>'local','reason'=>'missing_key'];
        $model=env('GEMINI_MODEL','gemini-3.5-flash-lite');
        if(!preg_match('/^[a-zA-Z0-9._-]{1,100}$/D',$model)) return ['text'=>'','source'=>'local','reason'=>'invalid_model'];
        $url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent';
        $body=['systemInstruction'=>['parts'=>[['text'=>$instructions]]],'contents'=>[['role'=>'user','parts'=>[['text'=>mb_substr($input,0,16000)]]]],'generationConfig'=>['temperature'=>0.2,'maxOutputTokens'=>900]];
        try {
            if($this->transport) [$status,$raw]=($this->transport)($url,$body,['x-goog-api-key: '.$key,'Content-Type: application/json']);
            else {
                if(!function_exists('curl_init')) return ['text'=>'','source'=>'local','reason'=>'curl_missing'];
                $ch=curl_init($url);
                curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>18,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['x-goog-api-key: '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR)]);
                $ca=env('GEMINI_CA_FILE'); if($ca!=='' && is_file($ca)) curl_setopt($ch,CURLOPT_CAINFO,$ca);
                $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
            }
            if($status===429) return ['text'=>'','source'=>'local','reason'=>'quota'];
            if($status<200 || $status>=300 || !is_string($raw)) return ['text'=>'','source'=>'local','reason'=>'unavailable'];
            $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);$answer='';
            foreach($data['candidates'][0]['content']['parts']??[] as $part) if(empty($part['thought']) && isset($part['text'])) $answer.=$part['text'];
            return trim($answer)!==''?['text'=>mb_substr(trim($answer),0,5000),'source'=>'Gemini / '.$model,'reason'=>null]:['text'=>'','source'=>'local','reason'=>'empty_or_blocked'];
        } catch(\Throwable $error) { return ['text'=>'','source'=>'local','reason'=>'request_failed']; }
    }
}
