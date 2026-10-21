<?php $currentUser = Core\Auth::user();
$notice = flash(); ?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> · SENN AI-X</title>
    <meta name="csrf-token" content="<?= e($_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(32)))) ?>">
    <meta name="base-path" content="<?= e(env('APP_BASE_PATH')) ?>">
    <meta name="maps-enabled" content="<?= e(env('MAPS_ENABLED', '1')) ?>">
    <link rel="stylesheet" href="<?= e(asset('app.css') . '?v=20261021-local-1') ?>">
    <?php if (($page ?? ($_GET['page'] ?? '')) === 'tracking' && env('MAPS_ENABLED', '1') === '1'): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>
    <script defer src="<?= e(asset('app.js') . '?v=20261021-local-1') ?>"></script>
</head>

<body class="<?= $currentUser ? '' : 'guest' ?>"><a class="skip" href="#content">Pular para conteúdo</a>
    <?php if ($currentUser): ?>
        <header class="sidebar">
            <div class="masthead">
                <a class="brand" href="<?= e(url()) ?>" aria-label="SENN AI-X, início"><span class="brand-mark" aria-hidden="true"></span>SENN <span class="brand-suffix">AI-X</span></a>
                <span class="masthead-caption">GESTÃO DE ÚLTIMA MILHA</span>
                <div class="sidebar-bottom">
                    <div class="account-label"><strong><?= e($currentUser['name']) ?></strong><small><?= e(['admin'=>'Administrador','gestor'=>'Gestor de Frota','despachante'=>'Despachante','motorista'=>'Motorista'][$currentUser['role']] ?? $currentUser['role']) ?></small></div>
                    <form action="<?= e(url('logout')) ?>" method="post"><?= csrf() ?><button class="ghost">Sair da conta</button></form>
                </div>
            </div>
            <nav aria-label="Principal">
                <?php $links = ['dashboard' => ['Visão geral', ['admin', 'gestor', 'despachante', 'motorista']], 'deliveries' => ['Pedidos', ['admin', 'gestor', 'despachante']], 'routes' => ['Rotas', ['admin', 'gestor', 'despachante']], 'tracking' => ['Rastreamento', ['admin', 'gestor', 'despachante', 'motorista']], 'intelligence' => ['Inteligência', ['admin', 'gestor', 'despachante']], 'vehicles' => ['Frota', ['admin', 'gestor']], 'drivers' => ['Motoristas', ['admin', 'gestor']], 'alerts' => ['Alertas', ['admin', 'gestor', 'despachante']], 'users' => ['Usuários', ['admin']], 'driver' => ['Minhas entregas', ['motorista']]];
                if ($currentUser['role']==='admin' && Services\Accounts::isLocal()) $links['demo']=['Demonstração',['admin']];
                foreach ($links as $key => [$label, $roles]):
                    if (!in_array($currentUser['role'], $roles, true))
                        continue; ?>
                    <a href="<?= e(url($key)) ?>" <?= ($_GET['page'] ?? 'dashboard') === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
        </header><?php endif; ?>
    <main id="content">
        <header class="page-heading">
            <div>
                <p class="eyebrow"><?= $currentUser ? 'CENTRAL DE OPERAÇÕES / SENN AI-X' : 'SENN AI-X / GESTÃO DE ENTREGAS' ?></p>
                <h1><?= e($title) ?></h1>
            </div><a class="privacy-link" href="<?= e(url('privacy')) ?>">Privacidade</a>
        </header>
        <?php if ($notice): ?>
            <div role="status" class="notice"><?= e($notice) ?></div><?php endif; ?>