<section class="login-grid">
    <div class="login-story">
        <div class="story-top"><span class="story-wordmark">SENN <span>AI-X</span></span><span class="story-edition">CADASTRO DE USUÁRIO</span></div><p class="eyebrow">UMA CONTA PARA SUA FUNÇÃO</p>
        <h2>Sua equipe.<br>No mesmo caminho.</h2>
        <p>Crie sua conta e acesse a plataforma. Escolha sua função: Gestor de Frota, Despachante ou Motorista. A conta
            fica ativa imediatamente com as permissões dessa função.</p>
        <p>A conta de Administrador é única e não pode ser criada por este formulário.</p><a
            href="<?= e(url('login')) ?>">Já tenho conta → Entrar</a>
    </div>
    <form class="panel login-form" method="post" action="<?= e(url('register')) ?>"><?= csrf() ?>
        <p class="eyebrow">NOVA CONTA</p>
        <h2>Criar sua conta</h2><label>Nome completo<input name="name" required maxlength="120"
                autocomplete="name"></label><label>E-mail<input type="email" name="email" required maxlength="190"
                autocomplete="email"></label><label>Seu perfil<select name="role" required>
                <option value="">Selecione sua função</option>
                <option value="gestor">Gestor de Frota</option>
                <option value="despachante">Despachante</option>
                <option value="motorista">Motorista</option>
            </select></label><label>Senha · mínimo 12 caracteres<input type="password" name="password" required
                minlength="12" maxlength="72" autocomplete="new-password"></label><label>Confirmar senha<input
                type="password" name="password_confirmation" required minlength="12" maxlength="72"
                autocomplete="new-password"></label><label class="checkbox"><input type="checkbox" name="privacy"
                value="1" required><span>Li o <a href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener">aviso de
                    privacidade</a>.</span></label><button>Criar conta →</button><small>Depois do cadastro, entre com
            seu e-mail, senha e o perfil escolhido.</small>
    </form>
</section>