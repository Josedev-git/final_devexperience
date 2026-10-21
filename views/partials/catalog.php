<section class="panel">
    <h2>Novo cadastro</h2>
    <form method="post" class="form-grid"><?= csrf() ?>
        <?php if ($kind === 'users'): ?>
            <label>Nome<input name="name" required maxlength="120"></label><label>E-mail<input name="email" type="email"
                    required maxlength="190"></label><label>Senha inicial<input name="password" type="password"
                    minlength="12" maxlength="72" required autocomplete="new-password"></label><label>Perfil<select
                    name="role">
                    <option value="motorista">Motorista</option>
                    <option value="despachante">Despachante</option>
                    <option value="gestor">Gestor</option>
                </select></label><label class="checkbox"><input type="checkbox" name="privacy" value="1" required> Confirmo
                que o titular recebeu o aviso de privacidade e está ciente do tratamento.</label>
        <?php elseif ($kind === 'vehicles'): ?>
            <label>Placa<input name="plate" maxlength="8" required placeholder="ABC1D23"></label><label>Tipo<input
                    name="type" maxlength="60" required placeholder="Van"></label><label>Capacidade (kg)<input
                    name="capacity_kg" type="number" min="0.01" step="0.01" required></label><label>Volume (m³)<input
                    name="capacity_m3" type="number" min="0.001" step="0.001" required></label><label>Autonomia (km)<input
                    name="autonomy_km" type="number" min="1" required></label><label>Custo por km (R$)<input
                    name="cost_per_km" type="number" min="0" step="0.01" value="2.50" required></label><label>Status<select
                    name="status">
                    <option value="disponivel">Disponível</option>
                    <option value="manutencao">Manutenção</option>
                </select></label><label>Próxima manutenção<input name="maintenance_date" type="date" required></label>
        <?php else: ?>
            <label>Usuário<select name="user_id" required>
                    <option value="">Selecione</option><?php foreach ($users as $u): ?>
                        <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
                </select></label><label>CNH<input name="cnh" pattern="[0-9]{11}" maxlength="11"
                    required></label><label>Categoria<select
                    name="cnh_category"><?php foreach (['A', 'B', 'AB', 'C', 'D', 'E', 'AC', 'AD', 'AE'] as $c): ?>
                        <option><?= e($c) ?></option><?php endforeach; ?>
                </select></label><label>Validade CNH<input name="cnh_expires" type="date"
                    required></label><label>Contato<input name="phone" type="tel" maxlength="24"
                    required></label><label>Veículo vinculado<select name="vehicle_id" required>
                    <option value="">Selecione</option><?php foreach ($vehicles as $v): ?>
                        <option value="<?= e($v['id']) ?>"><?= e($v['plate']) ?></option><?php endforeach; ?>
                </select></label>
        <?php endif; ?><button>Salvar cadastro</button>
    </form>
</section>
<?php if ($kind === 'vehicles'): ?>
    <details class="panel">
        <summary>Atualizar manutenção / disponibilidade</summary>
        <form method="post" class="toolbar"><?= csrf() ?><input type="hidden" name="action"
                value="maintenance"><label>Veículo<select name="id"><?php foreach ($rows as $v): ?>
                        <option value="<?= e($v['id']) ?>"><?= e($v['plate']) ?></option><?php endforeach; ?>
                </select></label><label>Status<select name="status">
                    <option value="disponivel">Disponível</option>
                    <option value="manutencao">Manutenção</option>
                </select></label><label>Próxima manutenção<input type="date" name="maintenance_date"
                    required></label><button>Atualizar</button></form>
    </details><?php endif; ?>
<section class="panel">
    <h2>Cadastros recentes</h2>
    <div class="table-wrap">
        <table>
            <caption>Até 100 registros mais recentes. CNH e senha não são exibidas.</caption><?php if ($rows): ?>
                <thead>
                    <tr><?php foreach (array_keys($rows[0]) as $key): ?>
                            <th scope="col"><?= e($key) ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody><?php foreach ($rows as $row): ?>
                        <tr><?php foreach ($row as $value): ?>
                                <td><?= e($value ?? '—') ?></td><?php endforeach; ?>
                        </tr><?php endforeach; ?>
                </tbody><?php else: ?>
                <tbody>
                    <tr>
                        <td>Nenhum cadastro.</td>
                    </tr>
                </tbody><?php endif; ?>
        </table>
    </div>
</section>

<?php if ($kind === 'users'): ?>
    <section class="panel">
        <h2>Função dos usuários cadastrados</h2>
        <p>Novas contas escolhem Motorista, Despachante ou Gestor no cadastro. Você pode ajustar essas funções; existe
            apenas um administrador.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($rows as $account): ?>
                        <tr>
                            <td><?= e($account['name']) ?></td>
                            <td><?= e($account['email']) ?></td>
                            <td><?= e($account['role']) ?></td>
                            <td><?php if ($account['role'] !== 'admin'): ?>
                                    <form class="toolbar" method="post"><?= csrf() ?><input type="hidden" name="action"
                                            value="role"><input type="hidden" name="id"
                                            value="<?= e($account['id']) ?>"><label>Função de <?= e($account['name']) ?><select
                                                name="role"><?php foreach (['motorista' => 'Motorista', 'despachante' => 'Despachante', 'gestor' => 'Gestor'] as $value => $label): ?>
                                                    <option value="<?= e($value) ?>" <?= $account['role'] === $value ? 'selected' : '' ?>>
                                                        <?= e($label) ?></option><?php endforeach; ?>
                                            </select></label><button>Salvar função</button></form><?php else: ?>Administrador
                                    único<?php endif; ?>
                            </td>
                        </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section><?php endif; ?>