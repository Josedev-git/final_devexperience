<section class="panel">
    <h2>Nova rota</h2>
    <p>Aproximação por distância, capacidade e janelas. Confirme o trajeto viário antes de iniciar. Base configurada no
        .env.</p>
    <form method="post"><?= csrf() ?>
        <div class="form-grid"><label>Veículo<select name="vehicle_id" required>
                    <option value="">Selecione</option><?php foreach ($vehicles as $v): ?>
                        <option value="<?= e($v['id']) ?>">
                            <?= e($v['plate'] . ' · ' . $v['capacity_kg'] . ' kg / ' . $v['capacity_m3'] . ' m³') ?></option>
                    <?php endforeach; ?>
                </select></label><label>Motorista vinculado<select name="driver_id" required>
                    <option value="">Selecione</option><?php foreach ($drivers as $d): ?>
                        <option value="<?= e($d['id']) ?>"><?= e($d['name'] . ' · veículo #' . $d['vehicle_id']) ?></option>
                    <?php endforeach; ?>
                </select></label><label>Saída<input type="datetime-local" name="departure_at" required
                    value="<?= e(date('Y-m-d\TH:i', time() + 300)) ?>"></label></div>
        <fieldset>
            <legend>Pedidos pendentes · até 100 por rota</legend>
            <div class="selection-grid"><?php foreach ($jobs as $j): ?><label class="checkbox"><input type="checkbox"
                            name="delivery_ids[]"
                            value="<?= e($j['id']) ?>"><span><strong><?= e($j['reference']) ?></strong><small><?= e($j['region'] . ' · até ' . $j['window_end'] . ' · ' . $j['weight_kg'] . ' kg') ?></small></span></label><?php endforeach;
            if (!$jobs): ?>
                    <p>Nenhuma entrega pendente.</p><?php endif; ?>
            </div>
        </fieldset><button>Calcular e liberar rota →</button>
    </form>
</section>
<section class="panel">
    <h2>Rotas liberadas</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Rota</th>
                    <th>Veículo / motorista</th>
                    <th>Saída</th>
                    <th>Status</th>
                    <th>Km estimado / GPS</th>
                    <th>Custo estimado</th>
                </tr>
            </thead>
            <tbody><?php foreach ($routes as $r): ?>
                    <tr>
                        <td>#<?= e($r['id']) ?></td>
                        <td><?= e($r['plate'] . ' / ' . $r['name']) ?></td>
                        <td><?= e($r['departure_at']) ?></td>
                        <td><?= e($r['status']) ?></td>
                        <td><?= e($r['estimated_km'] . ' / ' . $r['actual_km']) ?></td>
                        <td><?= e(money((float) $r['estimated_cost'])) ?></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>