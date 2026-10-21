<section class="login-grid">
    <div class="login-story">
        <div class="story-top"><span class="story-wordmark">SENN <span>AI-X</span></span><span class="story-edition">LOGÍSTICA / ÚLTIMA MILHA</span></div>
        <p class="eyebrow">DO PLANEJAMENTO À COMPROVAÇÃO</p>
        <h2>Cada entrega<br>tem um caminho.</h2>
        <p class="story-description">Organize as saídas. Acompanhe a frota.<br>Saiba o que chegou ao destino.</p>
        <svg class="dispatch-art" viewBox="0 0 560 220" fill="none" aria-hidden="true">
            <path d="M-20 172H128C162 172 170 80 211 80H350C400 80 402 164 446 164H580" stroke="currentColor" stroke-width="30"/>
            <path d="M-20 172H128C162 172 170 80 211 80H350C400 80 402 164 446 164H580" stroke="#eeb795" stroke-width="2" stroke-dasharray="8 10"/>
            <circle cx="63" cy="172" r="8" fill="#f8efe4"/><circle cx="487" cy="164" r="8" fill="#f8efe4"/>
            <path d="M242 40H309V94H231V55L242 40Z" fill="#f7eee3" stroke="#262721" stroke-width="2"/>
            <path d="M309 57H332L348 75V94H309V57Z" fill="#f7eee3" stroke="#262721" stroke-width="2"/>
            <path d="M316 63H329L339 76H316V63Z" fill="#d2d4c6" stroke="#262721" stroke-width="2"/>
            <circle cx="249" cy="95" r="10" fill="#262721"/><circle cx="249" cy="95" r="3" fill="#f7eee3"/>
            <circle cx="329" cy="95" r="10" fill="#262721"/><circle cx="329" cy="95" r="3" fill="#f7eee3"/>
            <path d="M246 61H291M246 68H277" stroke="#ae4223" stroke-width="3"/>
            <path d="M62 134V151M487 126V143" stroke="#262721" stroke-width="2"/>
            <rect x="52" y="119" width="20" height="15" fill="#f7eee3" stroke="#262721" stroke-width="2"/>
            <path d="M478 120L487 112L496 120V131H478V120Z" fill="#f7eee3" stroke="#262721" stroke-width="2"/>
        </svg>
        <div class="story-bottom"><span>01 / Planejar</span><span>02 / Acompanhar</span><span>03 / Entregar</span></div>
    </div>
    <form class="panel login-form" action="<?= e(url('login')) ?>" method="post"><?= csrf() ?>
        <p class="eyebrow">ÁREA DE ACESSO</p>
        <h2>Bom ter você aqui.</h2>
        <p>Informe seus dados para acessar a operação.</p><label>E-mail<input type="email" name="email" required
                maxlength="190" autocomplete="username"></label><label>Senha<input type="password" name="password"
                required maxlength="200" autocomplete="current-password"></label><label>Perfil de acesso<select
                name="role" required>
                <option value="">Selecione seu perfil</option>
                <option value="admin">Administrador</option>
                <option value="gestor">Gestor de Frota</option>
                <option value="despachante">Despachante</option>
                <option value="motorista">Motorista</option>
            </select></label><button>Entrar na plataforma <span aria-hidden="true">↗</span></button>
        <p class="auth-switch">Ainda não tem conta? <a href="<?= e(url('register')) ?>">Criar conta</a></p>
        <?php if ($canSetup ?? false): ?>
            <p><a href="<?= e(url('setup')) ?>">Configurar administrador inicial</a></p><?php endif; ?>
    </form>
</section>