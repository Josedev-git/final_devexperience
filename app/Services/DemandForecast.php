<?php
declare(strict_types=1);
namespace Services;
use Core\DB;
final class DemandForecast {
    public function forecast():array {
        $start=date('Y-m-d',strtotime('-56 days'));$today=date('Y-m-d');
        $rows=DB::all('SELECT region,DATE(created_at) day,COUNT(*) n FROM deliveries WHERE created_at>=? AND created_at<? GROUP BY region,DATE(created_at)',[$start,$today]);
        if(!$rows) return ['source'=>'seasonal-trend-local','history_days'=>56,'regions'=>[],'mae'=>null,'warning'=>'Sem histórico anterior a hoje. Carregue a demonstração ou registre entregas.'];
        $regions=[];foreach($rows as $row) $regions[$row['region']][$row['day']]=(int)$row['n'];
        $out=[];$errors=[];
        foreach($regions as $region=>$counts) {
            $series=[];for($i=56;$i>=1;$i--) { $day=date('Y-m-d',strtotime("-$i days"));$series[$day]=$counts[$day]??0; }
            // Backtest temporal: treina em 42 dias e avalia os 14 dias seguintes.
            $train=array_slice($series,0,42,true);$test=array_slice($series,42,null,true);$regionalErrors=[];
            foreach($test as $day=>$actual) { $pred=self::predict($train,$day);$regionalErrors[]=abs($pred-$actual);$errors[]=abs($pred-$actual); }
            $daily=[];for($i=1;$i<=30;$i++) { $day=date('Y-m-d',strtotime("+$i days"));$daily[]=['day'=>$day,'value'=>round(self::predict($series,$day),2)]; }
            $out[]=['region'=>$region,'next7'=>round(array_sum(array_column(array_slice($daily,0,7),'value')),1),'next30'=>round(array_sum(array_column($daily,'value')),1),'daily'=>$daily,'mae'=>round(array_sum($regionalErrors)/count($regionalErrors),2)];
        }
        return ['source'=>'seasonal-trend-local','history_days'=>56,'regions'=>$out,'mae'=>round(array_sum($errors)/max(1,count($errors)),2),'warning'=>'Modelo local de sazonalidade semanal e tendência recente. MAE mede erro absoluto médio no backtest de 14 dias. Dados sintéticos não validam desempenho em uma operação real.'];
    }
    public static function predict(array $series,string $day):float {
        $sum=0;$n=0;$weekday=date('w',strtotime($day));
        foreach($series as $date=>$value) if(date('w',strtotime($date))===$weekday) { $sum+=$value;$n++; }
        $values=array_values($series);$recent=array_sum(array_slice($values,-14));$previous=array_sum(array_slice($values,-28,14));
        $trend=$previous>0?max(0.5,min(1.5,$recent/$previous)):1.0;
        return $n?max(0,$sum/$n*$trend):0.0;
    }
}
