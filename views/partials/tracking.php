<form id="tracking-filters" class="toolbar" method="get"><input type="hidden" name="page" value="tracking"><label>Rota
        (ID)<input name="route" type="number" min="1" value="<?= e($_GET['route'] ?? '') ?>"></label><label>Motorista
        (ID)<input name="driver" type="number" min="1" value="<?= e($_GET['driver'] ?? '') ?>"></label><label>Região<input
            name="region" maxlength="80" value="<?= e($_GET['region'] ?? '') ?>"></label><button>Filtrar</button></form>
<section class="panel">
    <div class="panel-title">
        <h2>Operação em campo</h2><span class="pill">Polling · 10 s</span>
    </div>
    <p id="tracking-status" role="status">Posições recebidas do motorista; rotas de demonstração podem receber GPS fictício pela tela do administrador.</p>
    <div id="map" aria-label="Mapa de entregas" role="region"></div>
    <p class="subtle">Linhas ligam paradas na ordem planejada; não representam ruas. Posições com mais de 2 min aparecem
        como antigas. Use a tabela se o mapa estiver indisponível.</p>
</section>
<section class="panel">
    <h2>Entregas e posições · fallback sempre disponível</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Rota</th>
                    <th>Veículo</th>
                    <th>Pedido</th>
                    <th>Região</th>
                    <th>Status</th>
                    <th>Coordenadas da entrega</th>
                </tr>
            </thead>
            <tbody id="tracking-body"><?php foreach ($data['deliveries'] as $r): ?>
                    <tr>
                        <td><?= e($r['route_id']) ?></td>
                        <td><?= e($r['plate']) ?><?= str_starts_with($r['algorithm'],'demo-')?' · SIMULAÇÃO':'' ?></td>
                        <td><?= e($r['reference']) ?></td>
                        <td><?= e($r['region']) ?></td>
                        <td><?= e($r['status']) ?></td>
                        <td><?= e($r['lat'] . ', ' . $r['lng']) ?></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div id="position-list"><?php foreach ($data['positions'] as $p): ?>
            <p>Rota <?= e($p['route_id']) ?>: <?= e((str_starts_with($p['algorithm'],'demo-')?'SIMULAÇÃO · ':'') . $p['lat'] . ', ' . $p['lng'] . ' · ' . $p['recorded_at']) ?></p>
        <?php endforeach; ?>
    </div>
    <p>Máximo de 500 paradas por consulta. Refine os filtros.</p>
</section>