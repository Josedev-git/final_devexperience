<section class="panel">
    <h2>Central de alertas</h2>
    <p>Atualizada pelo agendador <code>php bin/monitor.php</code>. Resolver significa reconhecer e tratar o evento; não
        altera o pedido.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Mensagem</th>
                    <th>Data</th>
                    <th>Situação</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody><?php foreach ($rows as $a): ?>
                    <tr>
                        <td><?= e($a['kind']) ?></td>
                        <td><?= e($a['message']) ?></td>
                        <td><?= e($a['created_at']) ?></td>
                        <td><?= $a['resolved_at'] ? 'Resolvido' : 'Aberto' ?></td>
                        <td><?php if (!$a['resolved_at']): ?>
                                <form method="post" action="<?= e(url('resolve')) ?>"><?= csrf() ?><input type="hidden"
                                        name="id" value="<?= e($a['id']) ?>"><button class="secondary">Resolver</button></form>
                            <?php endif; ?>
                        </td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>