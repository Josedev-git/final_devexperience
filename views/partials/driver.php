<p class="notice">GPS funciona em HTTPS ou localhost, com permissão. O navegador precisa permanecer aberto; este
    protótipo não rastreia em segundo plano.</p>
<p id="gps-status" role="status" aria-live="polite"></p>
<?php foreach ($routes as $r): ?>
    <section class="panel">
        <div class="panel-title">
            <h2>Rota #<?= e($r['id']) ?> · <?= e($r['plate']) ?></h2><span class="pill"><?= e($r['status']) ?></span>
        </div>
        <div class="toolbar">
            <form method="post"><?= csrf() ?><input type="hidden" name="route_id" value="<?= e($r['id']) ?>"><input
                    type="hidden" name="action"
                    value="<?= $r['status'] === 'planejada' ? 'start' : 'finish' ?>"><button><?= $r['status'] === 'planejada' ? 'Iniciar rota' : 'Concluir rota' ?></button>
            </form><?php if ($r['status'] === 'em_andamento'): ?><button type="button" class="secondary gps-start"
                    data-route="<?= e($r['id']) ?>">Ativar localização</button><button type="button" class="ghost"
                    id="gps-stop">Parar localização</button><?php endif; ?>
        </div>
        <?php foreach ($r['stops'] as $d): ?>
            <article class="stop">
                <h3><?= e($d['sequence_no'] . '. ' . $d['reference']) ?> <span class="pill"><?= e($d['status']) ?></span></h3>
                <p><?= e($d['address']) ?></p>
                <p>Janela: <?= e($d['window_start'] . ' → ' . $d['window_end']) ?> · ETA: <?= e($d['eta']) ?></p>
                <?php if ($d['status'] === 'a_caminho'): ?>
                    <form method="post" class="pod-form toolbar"><?= csrf() ?><input type="hidden" name="action" value="pod"><input
                            type="hidden" name="route_id" value="<?= e($r['id']) ?>"><input type="hidden" name="delivery_id"
                            value="<?= e($d['id']) ?>"><input type="hidden" name="lat"><input type="hidden" name="lng"><label>Código
                            do destinatário<input name="confirmation_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                required autocomplete="off"></label><button>Capturar GPS e confirmar POD</button></form>
                    <form method="post" class="failure-form"><?= csrf() ?><input type="hidden" name="action" value="fail"><input
                            type="hidden" name="route_id" value="<?= e($r['id']) ?>"><input type="hidden" name="delivery_id"
                            value="<?= e($d['id']) ?>"><button class="danger">Registrar falha de entrega</button></form>
                <?php endif; ?>
            </article><?php endforeach; ?>
    </section><?php endforeach;
if (!$routes): ?>
    <section class="panel">
        <p>Você ainda não possui rotas abertas. Sua conta já está ativa. O gestor vincula seu cadastro de motorista a um
            veículo antes da atribuição de entregas.</p>
    </section><?php endif; ?>