<?php
declare(strict_types=1);
namespace Services;
/** Regressão logística local, sem dependências externas. Treina com entregas encerradas. */
final class RiskModel {
    private array $weights=[];
    public function fit(array $rows):void {
        $this->weights=array_fill(0,8,0.0);if(!$rows) return;
        for($epoch=0;$epoch<220;$epoch++) {
            $gradient=array_fill(0,8,0.0);
            foreach($rows as $row) {
                $x=self::features($row);$error=$this->probability($row)-(float)$row['late'];
                foreach($x as $j=>$value) $gradient[$j]+=$error*$value;
            }
            foreach($this->weights as $j=>$value) $this->weights[$j]-=0.18*($gradient[$j]/count($rows)+($j?0.008*$value:0));
        }
    }
    public function probability(array $row):float {
        $z=0;foreach(self::features($row) as $j=>$v) $z+=($this->weights[$j]??0)*$v;
        return 1/(1+exp(-max(-35,min(35,$z))));
    }
    public static function features(array $row):array {
        $hours=max(0.25,(strtotime($row['window_end'])-strtotime($row['window_start']))/3600);
        return [1,min(2,(float)$row['weight_kg']/100),min(2,(float)$row['volume_m3']/2),min(2,8/$hours),($row['priority']??'normal')==='urgente'?1:0,min(2,(float)($row['estimated_km']??0)/40),($row['region']??'')==='Centro'?1:0,($row['region']??'')==='Independência'?1:0];
    }
    public static function evaluate(array $history):array {
        if(count($history)<30) return ['samples'=>count($history),'brier'=>null,'accuracy'=>null];
        $cut=(int)floor(count($history)*0.8);$m=new self;$m->fit(array_slice($history,0,$cut));$error=0;$correct=0;$test=array_slice($history,$cut);
        foreach($test as $row) { $p=$m->probability($row);$error+=($p-(float)$row['late'])**2;$correct+=(int)(($p>=0.5)===(bool)$row['late']); }
        return ['samples'=>count($history),'test_samples'=>count($test),'brier'=>round($error/count($test),3),'accuracy'=>round(100*$correct/count($test),1)];
    }
}
