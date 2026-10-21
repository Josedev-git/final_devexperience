<section class="panel prose">
    <p class="eyebrow">CONFIGURAÇÃO INICIAL · SOMENTE NESTE COMPUTADOR</p>
    <h2>Criar o único administrador</h2>
    <p>Esta etapa aparece apenas enquanto não houver administrador no banco. Escolha os dados de acesso do responsável
        pelo sistema. Depois disso, novas contas serão criadas pelo cadastro normal.</p>
    <form class="form-grid" method="post" action="<?= e(url('setup')) ?>"><?= csrf() ?><label>Nome<input name="name"
                required maxlength="120" autocomplete="name"></label><label>E-mail<input name="email" type="email"
                required maxlength="190" autocomplete="email"></label><label>Senha · mínimo 12 caracteres<input
                name="password" type="password" required minlength="12" maxlength="72"
                autocomplete="new-password"></label><label>Confirmar senha<input name="password_confirmation"
                type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label
            class="checkbox"><input name="privacy" type="checkbox" value="1" required><span>Li o <a
                    href="<?= e(url('privacy')) ?>" target="_blank" rel="noopener">aviso de
                    privacidade</a>.</span></label><button>Configurar administrador</button></form>
    <p><a href="<?= e(url('login')) ?>">Voltar ao login</a></p>
</section>