<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../config/bootstrap.php';
try { echo json_encode(Services\DemoSeeder::load(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL; }
catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
