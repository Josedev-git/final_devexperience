<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli')
    exit;
require __DIR__ . '/../config/bootstrap.php';
$lock = fopen(ROOT . '/storage/monitor.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB))
    exit("Monitor já em execução.\n");
$service = new Services\Alerts;
$n = $service->scan();
$service->sendEmails();
echo "$n alertas abertos.\n";
flock($lock, LOCK_UN);
fclose($lock);
