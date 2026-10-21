<?php if ($codes): ?>
    <section class="panel">
        <h2>Códigos de confirmação · exibição única</h2>
        <p>Repasse de forma privada ao respectivo destinatário. Não compartilhe a lista com o motorista.</p>
        <?php foreach ($codes as $c): ?>
            <p><strong><?= e($c['reference']) ?></strong>: <?= e($c['code']) ?></p><?php endforeach; ?>
    </section><?php endif; ?>
<details class="panel" open>
    <summary>Novo pedido</summary>
    <form method="post" class="form-grid"><?= csrf() ?>
        <label>Referência<input name="reference" required maxlength="50"
                placeholder="PED-1001"></label><label>Destinatário<input name="recipient_name" required
                maxlength="120"></label><label>Endereço<input name="address" required
                maxlength="255"></label><label>Região<input name="region" required
                maxlength="80"></label><label>Latitude<input type="number" name="lat" min="-90" max="90"
                step="0.0000001" required></label><label>Longitude<input type="number" name="lng" min="-180" max="180"
                step="0.0000001" required></label><label>Início da janela<input type="datetime-local"
                name="window_start" required></label><label>Fim da janela<input type="datetime-local" name="window_end"
                required></label><label>Peso (kg)<input type="number" name="weight_kg" min="0.01" step="0.01"
                required></label><label>Volume (m³)<input type="number" name="volume_m3" min="0.001" step="0.001"
                required></label><label>Prioridade<select name="priority">
                <option value="normal">Normal</option>
                <option value="alta">Alta</option>
                <option value="urgente">Urgente</option>
            </select></label><button>Cadastrar pedido</button>
    </form>
    <p class="subtle">Coordenadas explícitas permitem roteirização mesmo sem serviço externo de geocodificação.</p>
</details>
<section class="panel">
    <h2>Importar CSV</h2>
    <form method="post" enctype="multipart/form-data" class="toolbar"><?= csrf() ?><input type="hidden" name="action"
            value="import"><label>Arquivo CSV · até 200 pedidos<input type="file" name="csv" accept=".csv,text/csv"
                required></label><button>Validar e importar</button></form><small>Modelo em
        database/deliveries-example.csv. Datas: AAAA-MM-DDTHH:MM. Todas as linhas são validadas na mesma
        transação.</small>
</section>
<section class="panel">
    <h2>Pedidos recentes</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Região</th>
                    <th>Endereço</th>
                    <th>Fim da janela</th>
                    <th>Peso / volume</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody><?php foreach ($rows as $d): ?>
                    <tr>
                        <td><?= e($d['reference']) ?></td>
                        <td><?= e($d['region']) ?></td>
                        <td><?= e($d['address']) ?></td>
                        <td><?= e($d['window_end']) ?></td>
                        <td><?= e($d['weight_kg']) ?> kg / <?= e($d['volume_m3']) ?> m³</td>
                        <td><span class="pill"><?= e($d['status']) ?></span></td>
                    </tr><?php endforeach;
            if (!$rows): ?>
                    <tr>
                        <td colspan="6">Nenhum pedido.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="toolbar"><?php if ($page > 1): ?><a
                href="<?= e(url('deliveries') . '&p=' . ($page - 1)) ?>">Anterior</a><?php endif; ?><span>Página
            <?= e($page) ?></span><?php if (count($rows) === 50): ?><a
                href="<?= e(url('deliveries') . '&p=' . ($page + 1)) ?>">Próxima</a><?php endif; ?></div>
</section>