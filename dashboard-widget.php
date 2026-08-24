<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function mssgc_dashboard_widget( $user ) {
    $user_id = $user->ID;
    if ( ! mssg_user_can( $user_id, 'view_cantieri' ) ) return;

    /* SICUREZZA/CORRETTEZZA (Task #23 — isolamento dati cliente): questo widget
       KPI compare sulla Dashboard di OGNI utente gestionale, cliente incluso
       (view_cantieri include mssg_cliente). Le due query sottostanti erano
       GLOBALI — "richieste in attesa" e "messaggi cliente→admin non letti" su
       TUTTI i cantieri/clienti, non solo i propri — quindi ogni cliente vedeva
       nella propria dashboard un numero riferito all'intera attività
       dell'azienda (dati di altri clienti), oltre a un badge che non
       corrispondeva a nulla di suo cliccandolo. Ora questi due KPI "stile
       admin" si calcolano solo per lo staff; il cliente ha già i badge
       corretti (scoped ai suoi soli dati) nelle tab Appuntamenti/Comunicazioni
       della propria Area cliente. */
    $is_cliente = mssg_get_primary_role( $user_id ) === 'mssg_cliente';

    $tutti  = mssgc_get_cantieri( $user_id );
    $attivi = array_filter( $tutti, function($c){ return $c->stato === 'attivo'; });

    /* Appuntamenti in attesa (richieste non ancora gestite) — solo staff */
    global $wpdb;
    $tab_b = $wpdb->prefix . 'mssg_agenda_blocchi';
    $n_app     = 0;
    $n_app_tot = 0;
    $now       = current_time('mysql');
    if ( ! $is_cliente && $wpdb->get_var( "SHOW TABLES LIKE '{$tab_b}'" ) === $tab_b ) {
        /* Badge: solo richieste in attesa di conferma */
        $n_app = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$tab_b}` WHERE tipo='richiesta'"
        );
        /* KPI numero: tutti gli appuntamenti futuri (interno + confermato + richiesta) */
        $n_app_tot = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$tab_b}` WHERE tipo != 'rifiutato' AND data_ora_inizio > %s",
            $now
        ));
    }

    /* Comunicazioni non lette (dal cliente verso admin) — solo staff */
    $n_com = 0;
    $tab_c = $wpdb->prefix . 'mssg_comunicazioni';
    if ( ! $is_cliente && $wpdb->get_var( "SHOW TABLES LIKE '{$tab_c}'" ) === $tab_c ) {
        $n_com = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$tab_c}` WHERE direzione='cliente_to_admin' AND letta=0"
        );
    }
    ?>
    <!-- Toggle KPI -->
    <div style="display:flex;justify-content:flex-end;margin-bottom:4px">
        <button id="mssg-kpi-toggle"
                onclick="(function(){var $g=jQuery('#mssg-kpi-grid');var open=localStorage.getItem('mssg_kpi_open')!=='0';$g.slideToggle(200);var newState=!open;localStorage.setItem('mssg_kpi_open',newState?'1':'0');jQuery('#mssg-kpi-arrow').css('transform',newState?'rotate(0deg)':'rotate(180deg)');})()"
                style="background:none;border:none;cursor:pointer;padding:4px 8px;color:var(--msslu-text-muted,rgba(255,255,255,.4));font-size:11px;display:flex;align-items:center;gap:4px;border-radius:6px">
            <span style="font-size:10px">Riepilogo</span>
            <span id="mssg-kpi-arrow" style="display:inline-block;transition:transform .2s;font-size:12px">&#8963;</span>
        </button>
    </div>
    <div id="mssg-kpi-grid" class="mssg-widgets-grid" style="margin-bottom:6px">
        <button class="mssg-widget-kpi" data-section="mssg_cantieri">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mssg-kpi-icon"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 22V12h6v10M9 7h1m4 0h1"/></svg>
            <div class="mssg-kpi-value"><?php echo count($tutti); ?></div>
            <div class="mssg-kpi-label">Cantieri totali</div>
        </button>
        <button class="mssg-widget-kpi" data-section="mssg_cantieri" style="<?php echo count($attivi)>0?'border-color:rgba(34,197,94,.3)':''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mssg-kpi-icon"><polyline points="20 6 9 17 4 12"/></svg>
            <div class="mssg-kpi-value" style="color:var(--msslu-accent)"><?php echo count($attivi); ?></div>
            <div class="mssg-kpi-label">Attivi ora</div>
        </button>
        <?php if ( ! $is_cliente ): ?>
        <button class="mssg-widget-kpi"
                onclick="(function(){var $n=jQuery('[data-section=mssg_area_cliente]').first();if($n.length){$n.trigger('click');setTimeout(function(){jQuery('.mssgcl-tab[data-tab=appuntamenti]').trigger('click');},400);}})();return false;"
                style="<?php echo $n_app>0?'border-color:rgba(245,158,11,.35)':''; ?>;position:relative;cursor:pointer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mssg-kpi-icon"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <div class="mssg-kpi-value" style="color:<?php echo $n_app>0?'#f59e0b':'var(--msslu-accent)'; ?>">
                <?php echo $n_app_tot; ?>
            </div>
            <div class="mssg-kpi-label">Appuntamenti</div>
            <?php if ( $n_app > 0 ): ?>
            <span style="position:absolute;top:8px;right:8px;background:#f59e0b;color:#000;font-size:10px;font-weight:800;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center">
                <?php echo $n_app; ?>
            </span>
            <?php endif; ?>
        </button>
        <button class="mssg-widget-kpi"
                onclick="(function(){var $n=jQuery('[data-section=mssg_area_cliente]').first();if($n.length){$n.trigger('click');setTimeout(function(){jQuery('.mssgcl-tab[data-tab=comunicazioni]').trigger('click');},400);}})();return false;"
                style="<?php echo $n_com>0?'border-color:rgba(233,30,140,.35)':''; ?>;position:relative;cursor:pointer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mssg-kpi-icon"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <div class="mssg-kpi-value" style="color:<?php echo $n_com>0?'var(--msslu-accent)':'inherit'; ?>">
                <?php echo $n_com; ?>
            </div>
            <div class="mssg-kpi-label">Messaggi non letti</div>
            <?php if ( $n_com > 0 ): ?>
            <span style="position:absolute;top:8px;right:8px;background:var(--msslu-accent,#e91e8c);color:#fff;font-size:10px;font-weight:800;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center">
                <?php echo $n_com; ?>
            </span>
            <?php endif; ?>
        </button>
        <?php endif; ?>
    </div><!-- #mssg-kpi-grid -->

    <script>
    (function(){
        var saved=localStorage.getItem('mssg_kpi_open');
        if(saved==='0'){
            jQuery('#mssg-kpi-grid').hide();
            jQuery('#mssg-kpi-arrow').css('transform','rotate(180deg)');
        }
    })();
    </script>
    <?php
}
