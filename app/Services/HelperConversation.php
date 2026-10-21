<?php
declare(strict_types=1);
namespace Services;
use Core\Auth;
final class HelperConversation {
    private static function ensure():void {
        $user=Auth::require();$owner=$user['id'].':'.$user['role'];
        if(($_SESSION['helper']['owner']??null)!==$owner) $_SESSION['helper']=['owner'=>$owner,'messages'=>[],'context'=>[]];
    }
    public static function messages():array {self::ensure();return $_SESSION['helper']['messages'];}
    public static function context():array {self::ensure();return $_SESSION['helper']['context'];}
    public static function remember(string $question,array $reply):void {
        self::ensure();
        $_SESSION['helper']['context']=$reply['context']??[];
        $_SESSION['helper']['messages'][]=['role'=>'user','text'=>mb_substr($question,0,500)];
        $_SESSION['helper']['messages'][]=['role'=>'assistant','text'=>mb_substr($reply['text'],0,6000),'source'=>$reply['source'],'actions'=>$reply['actions']??[]];
        $_SESSION['helper']['messages']=array_slice($_SESSION['helper']['messages'],-16);
    }
    public static function clear():void {self::ensure();$_SESSION['helper']['messages']=[];$_SESSION['helper']['context']=[];}
}
