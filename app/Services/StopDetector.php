<?php
declare(strict_types=1);
namespace Services;
final class StopDetector {
    /** Pontos do mais recente ao mais antigo. Só mede o trecho estacionário atual. */
    public static function stopped(array $points,int $now,int $threshold=900,float $radiusKm=0.15):bool {
        if(!$points || $now-strtotime($points[0]['recorded_at'])>120) return false;
        $last=$points[0];$anchor=strtotime($last['recorded_at']);$previous=$anchor;
        foreach($points as $point) {
            $timestamp=strtotime($point['recorded_at']);
            if($previous-$timestamp>180) break; // perda de GPS não comprova uma parada
            if(RoutePlanner::distance((float)$last['lat'],(float)$last['lng'],(float)$point['lat'],(float)$point['lng'])>$radiusKm) break;
            $anchor=$timestamp;$previous=$timestamp;
        }
        return strtotime($last['recorded_at'])-$anchor>=$threshold;
    }
}
