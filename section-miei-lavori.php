<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════════
   I MIEI LAVORI — sezione personalizzata per ruolo
   ──────────────────────────────────────────────────────────────
   administrator / mssg_admin  → tutti i cantieri attivi + KPI globali
   mssg_capo                   → cantieri dove è capo/supervisore
   mssg_operaio                → cantieri dove è assegnato
   mssg_cliente                → rimandato a sezione area-cliente
══════════════════════════════════════════════════════════════ */

function mssgc_render_miei_lavori( $user ) {
    $user_id = $user->ID;
    $role    = mssg_get_primary_role( $user_id );
    global $wpdb;

    if ( $role === 'mssg_cliente' ) {
        echo '<div class="mssg-empty-state"><p>Usa la sezione <strong>Area personale</strong> per vedere i tuoi lavori.</p></div>';
        return;
    }

    $tc   = mssgc_table( 'cantieri' );
    $tcu  = mssgc_table( 'cantieri_users' );
    $ta   = mssgc_table( 'appuntamenti' );
    $tl   = mssgc_table( 'lavorazioni' );
    $tpag = mssgc_table( 'pagamenti' );
    $tp   = mssgc_table( 'presenze' );

    $is_admin = in_array( $role, array( 'administrator', 'mssg_admin' ) );
    $is_capo  = $role === 'mssg_capo';

    /* ── Cantieri in base al ruolo ── */
    if ( $is_admin ) {
        $cantieri = $wpdb->get_results(
            "SELECT c.*, u.display_name AS cliente_nome
             FROM `{$tc}` c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.cliente_id
             WHERE c.deleted_at IS NULL AND c.stato NOT IN ('archiviato','chiuso')
             ORDER BY c.pinned DESC, c.stato = 'attivo' DESC, c.data_inizio DESC"
        );
    } else {
        $ruoli_ok = $is_capo
            ? array( 'capo', 'supervisore' )
            : array( 'capo', 'operaio', 'subappaltatore', 'supervisore' );
        $ph_r = implode( ',', array_fill( 0, count( $ruoli_ok ), '%s' ) );
        $args = array_merge( array( $user_id ), $ruoli_ok );
        $cantieri = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, cu.ruolo AS ruolo_cantiere, u.display_name AS cliente_nome
             FROM `{$tc}` c
             INNER JOIN `{$tcu}` cu ON cu.cantiere_id = c.id AND cu.user_id = %d AND cu.ruolo IN ({$ph_r})
             LEFT JOIN {$wpdb->users} u ON u.ID = c.cliente_id
             WHERE c.deleted_at IS NULL AND c.stato NOT IN ('archiviato','chiuso')
             ORDER BY c.stato = 'attivo' DESC, c.data_inizio DESC",
            ...$args
        ));
    }

    $cids = array_map( 'intval', array_column( $cantieri, 'id' ) );

    /* ── Appuntamenti prossimi 7 giorni — unica sorgente: mssg_agenda_blocchi ── */
    $now     = current_time( 'mysql' );
    $in7     = date( 'Y-m-d H:i:s', strtotime( '+7 days' ) );
    $appuntamenti_prox = array();
    $tab_b   = $wpdb->prefix . 'mssg_agenda_blocchi';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tab_b}'" ) === $tab_b ) {
        if ( $is_admin ) {
            $appuntamenti_prox = $wpdb->get_results( $wpdb->prepare(
                "SELECT b.id AS blocco_id, b.data_ora_inizio AS data_ora, b.data_ora_fine,
                        COALESCE( NULLIF(b.titolo_interno,''), b.nota_cliente, b.tipo ) AS titolo,
                        b.tipo AS fonte, b.cliente_id, b.cantiere_id,
                        COALESCE(c.nome,'') AS cantiere_nome,
                        u.display_name AS partecipante_nome,
                        COALESCE(b.luogo,'') AS luogo
                 FROM `{$tab_b}` b
                 LEFT JOIN `{$tc}` c ON c.id=b.cantiere_id
                 LEFT JOIN {$wpdb->users} u ON u.ID=b.cliente_id
                 WHERE b.tipo != 'rifiutato'
                   AND b.data_ora_fine >= %s
                   AND b.data_ora_inizio <= %s
                 ORDER BY b.data_ora_inizio ASC",
                $now, $in7
            ));
        } else {
            $appuntamenti_prox = $wpdb->get_results( $wpdb->prepare(
                "SELECT b.id AS blocco_id, b.data_ora_inizio AS data_ora, b.data_ora_fine,
                        COALESCE( NULLIF(b.titolo_interno,''), b.nota_cliente, b.tipo ) AS titolo,
                        b.tipo AS fonte, b.cliente_id, b.cantiere_id,
                        COALESCE(c.nome,'') AS cantiere_nome,
                        u.display_name AS partecipante_nome,
                        COALESCE(b.luogo,'') AS luogo
                 FROM `{$tab_b}` b
                 LEFT JOIN `{$tc}` c ON c.id=b.cantiere_id
                 LEFT JOIN {$wpdb->users} u ON u.ID=b.cliente_id
                 WHERE b.tipo != 'rifiutato'
                   AND ( b.cliente_id=%d OR b.created_by=%d )
                   AND b.data_ora_fine >= %s
                   AND b.data_ora_inizio <= %s
                 ORDER BY b.data_ora_inizio ASC",
                $user_id, $user_id, $now, $in7
            ));
        }
    }

    /* ── Promemoria personali (da mssg_agenda_blocchi tipo=promemoria) ── */
    $promemoria_list = array();
    if ( isset($tab_b) && $wpdb->get_var( "SHOW TABLES LIKE '{$tab_b}'" ) === $tab_b ) {
        $promemoria_list = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, titolo_interno AS titolo, nota_cliente AS note,
                    data_ora_inizio AS data_ora, data_ora_fine
             FROM `{$tab_b}`
             WHERE tipo='promemoria' AND admin_id=%d AND data_ora_fine >= %s
             ORDER BY data_ora_inizio ASC LIMIT 20",
            $user_id, $now
        ));
    }

    /* ── Storico appuntamenti passati (ultimi 30 giorni) ── */
    $storico = array();
    $trenta_giorni_fa = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
    if ( isset($tab_b) && $wpdb->get_var( "SHOW TABLES LIKE '{$tab_b}'" ) === $tab_b ) {
        if ( $is_admin ) {
            $storico = $wpdb->get_results( $wpdb->prepare(
                "SELECT b.id AS blocco_id, b.data_ora_inizio AS data_ora, b.data_ora_fine,
                        COALESCE( NULLIF(b.titolo_interno,''), b.nota_cliente, b.tipo ) AS titolo,
                        b.tipo AS fonte, b.cliente_id, b.cantiere_id,
                        COALESCE(c.nome,'') AS cantiere_nome,
                        u.display_name AS partecipante_nome,
                        COALESCE(b.luogo,'') AS luogo
                 FROM `{$tab_b}` b
                 LEFT JOIN `{$tc}` c ON c.id=b.cantiere_id
                 LEFT JOIN {$wpdb->users} u ON u.ID=b.cliente_id
                 WHERE b.tipo != 'rifiutato'
                   AND b.data_ora_fine < %s
                   AND b.data_ora_inizio >= %s
                 ORDER BY b.data_ora_inizio DESC",
                $now, $trenta_giorni_fa
            ));
        } else {
            $storico = $wpdb->get_results( $wpdb->prepare(
                "SELECT b.id AS blocco_id, b.data_ora_inizio AS data_ora, b.data_ora_fine,
                        COALESCE( NULLIF(b.titolo_interno,''), b.nota_cliente, b.tipo ) AS titolo,
                        b.tipo AS fonte, b.cliente_id, b.cantiere_id,
                        COALESCE(c.nome,'') AS cantiere_nome,
                        u.display_name AS partecipante_nome,
                        COALESCE(b.luogo,'') AS luogo
                 FROM `{$tab_b}` b
                 LEFT JOIN `{$tc}` c ON c.id=b.cantiere_id
                 LEFT JOIN {$wpdb->users} u ON u.ID=b.cliente_id
                 WHERE b.tipo != 'rifiutato'
                   AND ( b.cliente_id=%d OR b.created_by=%d )
                   AND b.data_ora_fine < %s
                   AND b.data_ora_inizio >= %s
                 ORDER BY b.data_ora_inizio DESC",
                $user_id, $user_id, $now, $trenta_giorni_fa
            ));
        }
    }

    /* ── Presenze oggi (solo per operai/capo) ── */
    $presenti_oggi = array();
    if ( ! $is_admin && ! empty( $cids ) && $wpdb->get_var( "SHOW TABLES LIKE '{$tp}'" ) === $tp ) {
        $ph = implode( ',', array_fill( 0, count( $cids ), '%d' ) );
        $args_p = array_merge( array( $user_id, date('Y-m-d') ), $cids );
        $presenti_oggi = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, c.nome AS cantiere_nome FROM `{$tp}` p
             LEFT JOIN `{$tc}` c ON c.id = p.cantiere_id
             INNER JOIN {$wpdb->prefix}mssg_personale pers ON pers.id = p.personale_id AND pers.user_id = %d
             WHERE p.data = %s AND p.cantiere_id IN ({$ph})",
            ...$args_p
        ));
    }

    /* ── KPI globali (solo admin) ── */
    $kpi = array();
    if ( $is_admin ) {
        $kpi['attivi']     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tc}` WHERE stato='attivo'   AND deleted_at IS NULL" );
        $kpi['sospesi']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tc}` WHERE stato='sospeso'  AND deleted_at IS NULL" );
        $kpi['completati'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tc}` WHERE stato='completato' AND deleted_at IS NULL" );
        $kpi['bozze']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tc}` WHERE stato='bozza'    AND deleted_at IS NULL" );
    }

    /* ── Mappa cantiere → prossimo appuntamento ── */
    $app_per_cantiere = array();
    if ( ! empty( $cids ) && $wpdb->get_var( "SHOW TABLES LIKE '{$ta}'" ) === $ta ) {
        $ph = implode( ',', array_fill( 0, count( $cids ), '%d' ) );
        $args_ac = array_merge( $cids, array( $now ) );
        $rows_ac = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.cantiere_id, a.data_ora, a.titolo,
                    ROW_NUMBER() OVER (PARTITION BY a.cantiere_id ORDER BY a.data_ora ASC) AS rn
             FROM `{$ta}` a
             WHERE a.cantiere_id IN ({$ph}) AND a.data_ora > %s",
            ...$args_ac
        ));
        foreach ( $rows_ac as $r ) {
            if ( (int) $r->rn === 1 ) {
                $app_per_cantiere[ (int) $r->cantiere_id ] = $r;
            }
        }
    }

    $stati_label = array(
        'attivo'     => 'In corso',
        'sospeso'    => 'Sospeso',
        'completato' => 'Completato',
        'bozza'      => 'Bozza',
        'chiuso'     => 'Chiuso',
    );
    $stati_color = array(
        'attivo'     => '#22c55e',
        'sospeso'    => '#f59e0b',
        'completato' => '#6366f1',
        'bozza'      => '#8b5cf6',
        'chiuso'     => '#6b7280',
    );
    $ruolo_badge = array(
        'capo'           => '🪖 Capo',
        'operaio'        => '🔨 Operaio',
        'supervisore'    => '👁 Supervisore',
        'subappaltatore' => '🤝 Subappaltatore',
    );

    $n_attivi  = count( array_filter( $cantieri, fn($c) => $c->stato === 'attivo' ) );
    $n_sospesi = count( array_filter( $cantieri, fn($c) => $c->stato === 'sospeso' ) );
    $n_app     = count( $appuntamenti_prox );
    ?>

    <div class="mssgml-wrap" id="mssgml-main">
    <div id="mssgc-panel" style="display:none"></div>
    <div class="mssgc-list-area">

    <!-- ── KPI STRIP ── -->
    <div class="mssgml-kpi-row">
        <div class="mssgml-kpi">
            <div class="mssgml-kpi-num"><?php echo $is_admin ? $kpi['attivi'] : $n_attivi; ?></div>
            <div class="mssgml-kpi-label">In corso</div>
        </div>
        <div class="mssgml-kpi">
            <div class="mssgml-kpi-num mssgml-warn"><?php echo $is_admin ? $kpi['sospesi'] : $n_sospesi; ?></div>
            <div class="mssgml-kpi-label">Sospesi</div>
        </div>
        <?php if ( $is_admin ): ?>
        <div class="mssgml-kpi">
            <div class="mssgml-kpi-num mssgml-purple"><?php echo $kpi['completati']; ?></div>
            <div class="mssgml-kpi-label">Completati</div>
        </div>
        <div class="mssgml-kpi">
            <div class="mssgml-kpi-num mssgml-muted"><?php echo $kpi['bozze']; ?></div>
            <div class="mssgml-kpi-label">Bozze</div>
        </div>
        <?php endif; ?>
        <div class="mssgml-kpi mssgml-kpi-accent">
            <div class="mssgml-kpi-num mssgml-accent"><?php echo $n_app; ?></div>
            <div class="mssgml-kpi-label">Appuntamenti 7gg</div>
        </div>
        <?php if ( ! $is_admin && ! empty( $presenti_oggi ) ): ?>
        <div class="mssgml-kpi mssgml-kpi-ok">
            <div class="mssgml-kpi-num mssgml-ok"><?php echo count( $presenti_oggi ); ?></div>
            <div class="mssgml-kpi-label">Presenze oggi</div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( ! empty( $appuntamenti_prox ) ): ?>
    <!-- ── APPUNTAMENTI PROSSIMI 7gg ── -->
    <div class="mssgml-block-header">📅 Prossimi 7 giorni <span class="mssgml-counter"><?php echo $n_app; ?></span></div>
    <div class="mssgml-app-list">
    <?php foreach ( $appuntamenti_prox as $a ):
        $is_oggi = date( 'Y-m-d', strtotime( $a->data_ora ) ) === date( 'Y-m-d' );
        $is_dom  = date( 'Y-m-d', strtotime( $a->data_ora ) ) === date( 'Y-m-d', strtotime( '+1 day' ) );
        $border  = $is_oggi ? 'var(--msslu-accent,#e91e8c)' : ( $is_dom ? '#f59e0b' : 'transparent' );
    ?>
    <?php
        $tipo_color = array('richiesta'=>'#f59e0b','confermato'=>'#818cf8','admin_fissato'=>'#f59e0b','interno'=>'#888');
        $tipo_label = array('richiesta'=>'⏳ In attesa','confermato'=>'✅ Confermato','interno'=>'🔒 Interno');
        $t_color = $tipo_color[$a->fonte] ?? '#888';
        $t_label = $tipo_label[$a->fonte] ?? '';
    ?>
    <?php
        $a_ts  = strtotime( $a->data_ora );
        $a_f_ts = isset($a->data_ora_fine) && $a->data_ora_fine ? strtotime($a->data_ora_fine) : 0;
        $now_ts = strtotime( current_time('mysql') );
        $in_corso_now = $a_ts <= $now_ts && ($a_f_ts > $now_ts || (!$a_f_ts && $a_ts >= $now_ts - 3600));
        $ora_fine_fmt = $a_f_ts ? date_i18n('H:i', $a_f_ts) : '';
    ?>
    <div class="mssgml-app-item" style="border-left:3px solid <?php echo $in_corso_now ? 'var(--msslu-accent,#e91e8c)' : $border; ?><?php echo $in_corso_now ? ';background:rgba(233,30,140,.04)' : ''; ?>">
        <!-- Data box: giorno/mese/ora inizio - fine -->
        <div class="mssgml-app-cal">
            <span class="mssgml-app-dd"><?php echo date_i18n( 'd', $a_ts ); ?></span>
            <span class="mssgml-app-mm"><?php echo strtoupper(date_i18n( 'M', $a_ts )); ?></span>
            <span class="mssgml-app-hh"><?php echo date_i18n( 'H:i', $a_ts ); ?></span>
            <?php if ($ora_fine_fmt) : ?>
            <span style="font-size:8px;color:var(--msslu-text-muted);margin-top:1px"><?php echo $ora_fine_fmt; ?></span>
            <?php endif; ?>
        </div>
        <div class="mssgml-app-body">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;flex-wrap:wrap">
                <?php if ( $in_corso_now ) : ?><span class="mssgml-badge" style="background:rgba(233,30,140,.15);color:var(--msslu-accent,#e91e8c)">🔴 IN CORSO</span><?php endif; ?>
                <?php if ( $is_oggi && !$in_corso_now ) : ?><span class="mssgml-badge mssgml-badge-oggi">OGGI</span><?php endif; ?>
                <?php if ( $is_dom )  : ?><span class="mssgml-badge mssgml-badge-dom">DOMANI</span><?php endif; ?>
                <?php if ( $t_label ) : ?><span style="font-size:10px;color:<?php echo $t_color;?>;background:<?php echo $t_color;?>18;padding:2px 7px;border-radius:999px"><?php echo $t_label;?></span><?php endif; ?>
            </div>
            <div class="mssgml-app-titolo"><?php echo esc_html( $a->titolo ); ?></div>
            <!-- Partecipante e cantiere cliccabili fuori dal box data -->
            <div style="display:flex;gap:12px;margin-top:4px;flex-wrap:wrap">
                <?php if ( ! empty( $a->partecipante_nome ) ) : ?>
                <a href="#" onclick="mssgmlOpenCliente(<?php echo (int)$a->cliente_id; ?>);return false;"
                   style="font-size:11px;color:var(--msslu-accent,#e91e8c);text-decoration:none">
                    👤 <?php echo esc_html( $a->partecipante_nome ); ?>
                </a>
                <?php endif; ?>
                <?php if ( ! empty( $a->cantiere_nome ) && (int)$a->cantiere_id > 0 ) : ?>
                <a href="#" onclick="mssgmlOpenCantiere(<?php echo (int)$a->cantiere_id; ?>);return false;"
                   style="font-size:11px;color:rgba(255,255,255,.5);text-decoration:none">
                    🏗 <?php echo esc_html( $a->cantiere_nome ); ?>
                </a>
                <?php endif; ?>
                <?php if ( $a->luogo ) : ?>
                <span style="font-size:11px;color:var(--msslu-text-muted)">📍 <?php echo esc_html($a->luogo);?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ( (int)$a->blocco_id > 0 ) : ?>
        <button class="mssg-btn mssg-btn--ghost"
                onclick="mssgmlVediApp(<?php echo (int)$a->blocco_id;?>)"
                style="font-size:11px;padding:5px 12px;align-self:center;flex-shrink:0">
            Vedi ›
        </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── PROMEMORIA ──
         Se non ce n'è nessuno, il pannello parte già chiuso (solo intestazione +
         pulsante "Nuovo") invece di occupare spazio mostrando un messaggio
         "Nessun promemoria attivo": si apre da solo quando c'è qualcosa da
         vedere, altrimenti resta compatto. ── -->
    <?php $prom_vuoto = empty( $promemoria_list ); ?>
    <div style="margin:20px 0 0;padding:16px 18px;background:rgba(168,85,247,.08);border:2px solid rgba(168,85,247,.35);border-radius:14px">
        <div id="mssgml-prom-header" style="display:flex;align-items:center;justify-content:space-between;<?php echo $prom_vuoto?'':'margin-bottom:12px;';?>cursor:pointer">
            <div style="font-size:14px;font-weight:700;color:rgba(168,85,247,.95)">
                <span id="mssgml-prom-chevron" style="display:inline-block;transition:transform .2s;margin-right:4px;<?php echo $prom_vuoto?'transform:rotate(-90deg)':'';?>">▾</span>
                📌 Promemoria<?php if(!empty($promemoria_list)):?> <span style="font-size:11px;background:rgba(168,85,247,.2);color:rgba(168,85,247,.9);padding:2px 8px;border-radius:999px;margin-left:6px"><?php echo count($promemoria_list);?></span><?php endif;?>
            </div>
            <button id="mssgml-btn-nuovo-promemoria" class="mssg-btn" style="font-size:12px;background:rgba(168,85,247,.15);border:1px solid rgba(168,85,247,.4);color:rgba(168,85,247,.95);padding:5px 12px;border-radius:6px">+ Nuovo</button>
        </div>
        <div id="mssgml-prom-body" style="<?php echo $prom_vuoto?'display:none':'';?>">
        <div id="mssgml-form-promemoria" style="display:none;padding:14px;background:rgba(168,85,247,.05);border:1px solid rgba(168,85,247,.2);border-radius:10px;margin-bottom:12px">
            <input type="hidden" id="mssgml-prom-id" value="">
            <input type="text" id="mssgml-prom-titolo" placeholder="es. Chiamare avvocato, Pagare affitto…"
                style="width:100%;font-size:13px;padding:8px 10px;background:var(--msslu-input-bg);border:1px solid rgba(168,85,247,.3);border-radius:7px;color:var(--msslu-text);box-sizing:border-box;margin-bottom:8px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                <input type="datetime-local" id="mssgml-prom-data"
                    style="font-size:12px;padding:7px 10px;background:var(--msslu-input-bg);border:1px solid rgba(168,85,247,.3);border-radius:7px;color:var(--msslu-text);width:100%;box-sizing:border-box">
                <input type="number" id="mssgml-prom-durata" min="0" placeholder="Durata min (0=puntuale)"
                    style="font-size:12px;padding:7px 10px;background:var(--msslu-input-bg);border:1px solid rgba(168,85,247,.3);border-radius:7px;color:var(--msslu-text);width:100%;box-sizing:border-box">
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer">
                    <input type="checkbox" id="mssgml-prom-notifica" style="accent-color:#22c55e;width:14px;height:14px">
                    <span style="color:#22c55e">⏰ Email a me stesso</span>
                </label>
                <select id="mssgml-prom-minuti" style="font-size:11px;padding:3px 8px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:5px;color:var(--msslu-text)">
                    <option value="15">15 min prima</option>
                    <option value="30">30 min prima</option>
                    <option value="60" selected>1 ora prima</option>
                    <option value="120">2 ore prima</option>
                    <option value="1440">1 giorno prima</option>
                </select>
            </div>
            <div style="display:flex;gap:8px">
                <button id="mssgml-prom-salva" class="mssg-btn mssg-btn--primary" style="font-size:12px">💾 Salva</button>
                <button id="mssgml-prom-annulla" class="mssg-btn mssg-btn--ghost" style="font-size:12px">Annulla</button>
            </div>
        </div>
        <?php if ( ! empty( $promemoria_list ) ) : ?>
        <?php foreach ( $promemoria_list as $p ) :
            $p_ts = strtotime($p->data_ora);
            $p_oggi = date('Y-m-d', $p_ts) === date('Y-m-d');
            $p_in_corso = $p_ts <= strtotime(current_time('mysql')) && strtotime($p->data_ora_fine) > strtotime(current_time('mysql'));
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:rgba(168,85,247,.07);border:1px solid rgba(168,85,247,.2);border-radius:8px;margin-bottom:6px<?php echo $p_oggi?';border-left:3px solid rgba(168,85,247,.7)':'';?>">
            <span style="font-size:18px;flex-shrink:0">📌</span>
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600;color:var(--msslu-text)">
                    <?php if($p_in_corso):?><span style="font-size:10px;color:rgba(168,85,247,.9);margin-right:5px">🔴 ORA</span><?php elseif($p_oggi):?><span style="font-size:10px;color:rgba(168,85,247,.7);margin-right:5px">OGGI</span><?php endif;?>
                    <?php echo esc_html($p->titolo); ?>
                </div>
                <div style="font-size:11px;color:var(--msslu-text-muted)"><?php echo date_i18n('d/m H:i', $p_ts); ?><?php if($p->data_ora_fine && strtotime($p->data_ora_fine) > $p_ts+60): echo ' → '.date_i18n('H:i', strtotime($p->data_ora_fine)); endif; ?></div>
            </div>
            <button class="mssgml-elimina-promemoria" data-id="<?php echo (int)$p->id; ?>" style="background:none;border:none;color:rgba(239,68,68,.4);cursor:pointer;font-size:13px">🗑</button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        </div><!-- #mssgml-prom-body -->
    </div><!-- fine sezione promemoria -->

    <!-- ── LISTA CANTIERI ── -->
    <div class="mssgml-block-header" style="margin-top:22px">
        🏗 <?php echo $is_admin ? 'Tutti i cantieri' : 'I miei cantieri'; ?>
        <span class="mssgml-counter"><?php echo count( $cantieri ); ?></span>
        <?php if ( $is_admin ) : ?>
        <div style="margin-left:auto;display:flex;gap:6px">
            <button class="mssgml-filter-btn active" data-filter="tutti">Tutti</button>
            <button class="mssgml-filter-btn" data-filter="attivo">In corso</button>
            <button class="mssgml-filter-btn" data-filter="sospeso">Sospesi</button>
            <button class="mssgml-filter-btn" data-filter="completato">Completati</button>
            <button class="mssgml-filter-btn" data-filter="bozza">Bozze</button>
        </div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:12px">
        <input type="text" id="mssgml-search" placeholder="Cerca cantiere…"
               oninput="mssgmlFilter(this.value)"
               style="width:100%;max-width:280px;padding:7px 14px;font-size:12px;border-radius:20px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);color:var(--msslu-text)">
    </div>

    <?php if ( empty( $cantieri ) ) : ?>
    <div class="mssg-empty-state" style="padding:40px 0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px;opacity:.3;display:block;margin:0 auto 12px"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 22V12h6v10M9 7h1m4 0h1"/></svg>
        <p>Nessun cantiere assegnato.</p>
    </div>
    <?php else : ?>
    <div class="mssgml-grid" id="mssgml-grid">

    <?php foreach ( $cantieri as $c ) :
        /* Calcola avanzamento % */
        $perc = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tpag}'" ) === $tpag ) {
            $pags = $wpdb->get_results( $wpdb->prepare(
                "SELECT percentuale, pagato FROM `{$tpag}` WHERE cantiere_id=%d ORDER BY ordine ASC", $c->id
            ));
            if ( ! empty( $pags ) ) {
                foreach ( $pags as $p ) { if ( $p->pagato ) $perc += (float) $p->percentuale; }
                $perc = min( 100, (int) $perc );
            }
        }
        if ( ! $perc && $wpdb->get_var( "SHOW TABLES LIKE '{$tl}'" ) === $tl ) {
            $tot = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$tl}` WHERE cantiere_id=%d AND deleted_at IS NULL AND stato!='annullata'", $c->id
            ));
            if ( $tot ) {
                $ok  = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$tl}` WHERE cantiere_id=%d AND stato='completata' AND deleted_at IS NULL", $c->id
                ));
                $perc = round( ( $ok / $tot ) * 100 );
            }
        }

        $stato_color = $stati_color[ $c->stato ] ?? '#6b7280';
        $stato_label = $stati_label[ $c->stato ] ?? $c->stato;
        $prossimo    = $app_per_cantiere[ (int) $c->id ] ?? null;

        /* Lavorazioni in corso per questo cantiere */
        $lav_in_corso = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tl}'" ) === $tl ) {
            $lav_in_corso = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$tl}` WHERE cantiere_id=%d AND stato='in_corso' AND deleted_at IS NULL", $c->id
            ));
        }
    ?>
    <div class="mssgml-card" data-nome="<?php echo esc_attr( strtolower( $c->nome ) ); ?>"
         data-stato="<?php echo esc_attr( $c->stato ); ?>">

        <?php if ( $c->pinned ) : ?>
        <div class="mssgml-pin-badge">📌 Pinnato</div>
        <?php endif; ?>

        <div class="mssgml-card-top">
            <div class="mssgml-card-title-wrap">
                <div class="mssgml-card-nome"><?php echo esc_html( $c->nome ); ?></div>
                <?php if ( $c->codice ) : ?>
                <div class="mssgml-card-codice">#<?php echo esc_html( $c->codice ); ?></div>
                <?php endif; ?>
            </div>
            <span class="mssg-status mssg-status--<?php echo esc_attr( $c->stato ); ?>"><?php echo $stato_label; ?></span>
        </div>

        <?php if ( $c->cliente_nome ) : ?>
        <div class="mssgml-card-meta">👤 <?php echo esc_html( $c->cliente_nome ); ?></div>
        <?php endif; ?>
        <?php if ( $c->citta ) : ?>
        <div class="mssgml-card-meta">📍 <?php echo esc_html( $c->citta ); ?><?php echo $c->cap ? ' ' . esc_html( $c->cap ) : ''; ?></div>
        <?php endif; ?>
        <?php if ( $c->data_inizio || $c->data_fine_prev ) : ?>
        <div class="mssgml-card-meta" style="display:flex;gap:14px;flex-wrap:wrap">
            <?php if ( $c->data_inizio )   : ?>
            <span style="display:flex;flex-direction:column;gap:1px">
                <span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted);opacity:.7">Inizio</span>
                <span>📅 <?php echo date_i18n( 'd/m/Y', strtotime( $c->data_inizio ) ); ?></span>
            </span>
            <?php endif; ?>
            <?php if ( $c->data_fine_prev ): ?>
            <span style="display:flex;flex-direction:column;gap:1px">
                <span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted);opacity:.7">Fine prevista</span>
                <span>🏁 <?php echo date_i18n( 'd/m/Y', strtotime( $c->data_fine_prev ) ); ?></span>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! $is_admin && ! empty( $c->ruolo_cantiere ) ) : ?>
        <div class="mssgml-ruolo-badge"><?php echo $ruolo_badge[ $c->ruolo_cantiere ] ?? esc_html( $c->ruolo_cantiere ); ?></div>
        <?php endif; ?>

        <!-- Doppia barra: pagamenti + avanzamento lavori -->
        <div class="mssgml-progress-wrap" style="flex-direction:column;align-items:stretch;gap:5px">
            <!-- Pagamenti -->
            <div style="display:flex;align-items:center;gap:6px">
                <span style="font-size:9px;color:var(--msslu-text-muted);width:56px;flex-shrink:0;text-transform:uppercase;letter-spacing:.03em">Pagati</span>
                <div class="mssgml-progress-track" style="flex:1">
                    <div class="mssgml-progress-fill" style="width:<?php echo $perc; ?>%;background:<?php echo $stato_color; ?>"></div>
                </div>
                <span style="font-size:10px;font-weight:700;color:<?php echo $stato_color;?>;min-width:30px;text-align:right"><?php echo $perc;?>%</span>
            </div>
            <!-- Avanzamento lavori -->
            <?php $avanz_pct = min(100, max(0, (int)($c->avanzamento_pct ?? 0)));
                  $av_color  = $avanz_pct>=100?'#22c55e':($avanz_pct>=50?'#8b5cf6':'#f59e0b'); ?>
            <div style="display:flex;align-items:center;gap:6px">
                <span style="font-size:9px;color:var(--msslu-text-muted);width:56px;flex-shrink:0;text-transform:uppercase;letter-spacing:.03em">Lavori</span>
                <div class="mssgml-progress-track" style="flex:1">
                    <div class="mssgml-progress-fill" style="width:<?php echo $avanz_pct; ?>%;background:<?php echo $av_color; ?>"></div>
                </div>
                <span style="font-size:10px;font-weight:700;color:<?php echo $av_color;?>;min-width:30px;text-align:right"><?php echo $avanz_pct;?>%</span>
            </div>
        </div>

        <?php if ( $lav_in_corso > 0 ) : ?>
        <div class="mssgml-card-meta" style="margin-top:6px">
            🔨 <?php echo $lav_in_corso; ?> lavorazion<?php echo $lav_in_corso === 1 ? 'e' : 'i'; ?> in corso
        </div>
        <?php endif; ?>

        <?php if ( $prossimo ) : ?>
        <div class="mssgml-prossimo-app">
            📅 <?php echo date_i18n( 'd/m/Y H:i', strtotime( $prossimo->data_ora ) ); ?>
            &nbsp;—&nbsp; <?php echo esc_html( $prossimo->titolo ); ?>
        </div>
        <?php endif; ?>

        <div class="mssgml-card-actions">
            <button class="mssg-btn mssg-btn--primary mssgc-open-cantiere"
                    data-id="<?php echo (int) $c->id; ?>"
                    style="font-size:12px;padding:7px 16px;flex:1">
                📂 Apri cantiere
            </button>
            <?php if ( $c->cliente_id ) : ?>
            <button class="mssg-btn mssg-btn--ghost mssgml-btn-chat-cantiere"
                    data-cantiere-id="<?php echo (int) $c->id; ?>"
                    style="font-size:12px;padding:7px 12px"
                    title="Chat del cantiere">
                💬
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    </div><!-- #mssgml-grid -->
    <?php endif; ?>

    <?php if ( ! empty( $storico ) ) : ?>
    <div style="margin-top:28px">
        <div class="mssgml-block-header" style="margin-bottom:10px">
            🕐 Storico ultimi 30 giorni
            <span class="mssgml-counter"><?php echo count($storico); ?></span>
        </div>
        <div id="mssgml-storico-lista">
        <?php foreach ( $storico as $i => $app ) :
            $app_ts = strtotime( $app->data_ora );
            $extra  = $i >= 5 ? 'display:none' : '';
        ?>
        <div class="mssgml-storico-row" data-blocco-id="<?php echo (int)$app->blocco_id; ?>" style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:8px;margin-bottom:6px;opacity:.65;<?php echo $extra; ?>">
            <div style="flex-shrink:0;font-size:12px;color:var(--msslu-text-muted);min-width:105px"><?php echo date_i18n('d/m/Y H:i', $app_ts); ?></div>
            <div style="flex:1;min-width:0">
                <span style="font-size:13px;font-weight:600;color:var(--msslu-text)"><?php echo esc_html($app->titolo); ?></span>
                <?php if ( $app->cantiere_nome ) : ?>
                <span style="font-size:11px;color:var(--msslu-text-muted);margin-left:8px">🏗 <?php echo esc_html($app->cantiere_nome); ?></span>
                <?php endif; ?>
                <?php if ( $app->luogo ) : ?>
                <span style="font-size:11px;color:var(--msslu-text-muted);margin-left:8px">📍 <?php echo esc_html($app->luogo); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( (int)$app->blocco_id > 0 ) : ?>
            <button type="button" class="mssgml-storico-elimina" data-blocco-id="<?php echo (int)$app->blocco_id; ?>"
                    title="Rimuovi dallo storico"
                    style="flex-shrink:0;background:none;border:none;color:rgba(239,68,68,.45);cursor:pointer;font-size:13px;padding:2px 4px">🗑</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php if ( count($storico) > 5 ) : ?>
        <button onclick="document.querySelectorAll('.mssgml-storico-row').forEach(function(r){r.style.display='flex';});this.remove();"
                style="width:100%;margin-top:6px;background:none;border:1px solid var(--msslu-box-border);color:var(--msslu-text-muted);font-size:12px;padding:6px;border-radius:6px;cursor:pointer">
            Mostra tutto lo storico (<?php echo count($storico) - 5; ?> nascosti)
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    </div><!-- .mssgc-list-area -->
    </div><!-- .mssgml-wrap -->

    <style>
    .mssgml-wrap{max-width:960px}
    .mssgml-kpi-row{display:flex;gap:10px;margin-bottom:22px;flex-wrap:wrap}
    .mssgml-kpi{flex:1;min-width:72px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:10px;padding:12px 14px;text-align:center}
    .mssgml-kpi-accent{border-color:rgba(233,30,140,.3)}
    .mssgml-kpi-ok{border-color:rgba(34,197,94,.3)}
    .mssgml-kpi-num{font-size:26px;font-weight:800;color:var(--msslu-text);line-height:1}
    .mssgml-kpi-label{font-size:11px;color:var(--msslu-text-muted);margin-top:4px;line-height:1.3}
    .mssgml-warn{color:#f59e0b!important}
    .mssgml-purple{color:#6366f1!important}
    .mssgml-muted{color:var(--msslu-text-muted)!important}
    .mssgml-accent{color:var(--msslu-accent,#e91e8c)!important}
    .mssgml-ok{color:#22c55e!important}
    .mssgml-block-header{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--msslu-text-muted);margin-bottom:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .mssgml-counter{font-size:12px;font-weight:800;background:rgba(255,255,255,.06);padding:1px 7px;border-radius:10px;color:var(--msslu-text)}
    .mssgml-filter-btn{font-size:11px;padding:3px 10px;background:transparent;border:1px solid var(--msslu-box-border);border-radius:999px;color:var(--msslu-text-muted);cursor:pointer;transition:all .15s}
    .mssgml-filter-btn.active{background:var(--msslu-accent,#e91e8c);border-color:var(--msslu-accent,#e91e8c);color:#fff}
    .mssgml-app-list{display:flex;flex-direction:column;gap:8px;margin-bottom:6px}
    .mssgml-app-item{display:flex;gap:12px;align-items:stretch;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:10px;padding:12px 14px}
    .mssgml-app-cal{flex-shrink:0;text-align:center;min-width:46px;padding:6px 8px;background:var(--msslu-box-bg,rgba(0,0,0,.2));border:1px solid var(--msslu-box-border);border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center}
    .mssgml-app-dd{font-size:20px;font-weight:800;line-height:1}
    .mssgml-app-mm{font-size:9px;text-transform:uppercase;color:var(--msslu-text-muted);letter-spacing:.04em}
    .mssgml-app-hh{font-size:11px;font-weight:700;color:var(--msslu-accent,#e91e8c);margin-top:2px}
    .mssgml-app-body{flex:1;min-width:0}
    .mssgml-app-titolo{font-size:14px;font-weight:600;margin:4px 0}
    .mssgml-app-meta{font-size:12px;color:var(--msslu-text-muted)}
    .mssgml-badge{display:inline-block;font-size:9px;font-weight:800;padding:2px 7px;border-radius:999px;margin-right:4px;letter-spacing:.04em}
    .mssgml-badge-oggi{background:rgba(233,30,140,.12);color:var(--msslu-accent,#e91e8c)}
    .mssgml-badge-dom{background:rgba(245,158,11,.12);color:#f59e0b}
    .mssgml-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:12px}
    .mssgml-card{background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:12px;padding:16px;transition:border-color .2s;position:relative}
    .mssgml-card:hover{border-color:rgba(255,255,255,.2)}
    .mssgml-pin-badge{position:absolute;top:10px;right:10px;font-size:10px;opacity:.5}
    .mssgml-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px}
    .mssgml-card-nome{font-size:15px;font-weight:700;color:var(--msslu-text);margin-bottom:2px;line-height:1.3}
    .mssgml-card-codice{font-size:11px;color:var(--msslu-text-muted)}
    .mssgml-card-meta{font-size:12px;color:var(--msslu-text-muted);margin-top:3px}
    .mssgml-ruolo-badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 9px;border:1px solid rgba(255,255,255,.15);border-radius:999px;color:var(--msslu-text-muted);margin:6px 0}
    .mssgml-progress-wrap{display:flex;align-items:center;gap:8px;margin:10px 0 6px}
    .mssgml-progress-track{flex:1;height:5px;background:rgba(255,255,255,.07);border-radius:3px;overflow:hidden}
    .mssgml-progress-fill{height:100%;border-radius:3px;transition:width .6s ease}
    .mssgml-progress-pct{font-size:11px;font-weight:700;color:var(--msslu-text-muted);min-width:32px;text-align:right}
    .mssgml-prossimo-app{font-size:11px;color:var(--msslu-text-muted);background:rgba(255,255,255,.03);border:1px solid var(--msslu-box-border);border-radius:6px;padding:6px 9px;margin:8px 0}
    .mssgml-card-actions{display:flex;gap:8px;margin-top:12px}
    @media(max-width:600px){
        .mssgml-grid{grid-template-columns:1fr}
        .mssgml-kpi-row{gap:8px}
        .mssgml-kpi{min-width:65px;padding:10px 8px}
        .mssgml-kpi-num{font-size:22px}
    }
    </style>

    <script>
    (function(){
        /* ── Promemoria: pannello collassabile (click sull'header, esclusa la ── */
        /* ── freccia/testo, o sul bottone + Nuovo) ── */
        jQuery('#mssgml-prom-header').on('click', function(e){
            if (jQuery(e.target).closest('#mssgml-btn-nuovo-promemoria').length) return;
            var $body = jQuery('#mssgml-prom-body');
            var $chev = jQuery('#mssgml-prom-chevron');
            $body.slideToggle(200);
            $chev.css('transform', $body.is(':visible') ? 'rotate(0deg)' : 'rotate(-90deg)');
        });
        jQuery('#mssgml-btn-nuovo-promemoria').on('click', function(e){
            e.stopPropagation();
            var $form = jQuery('#mssgml-form-promemoria');
            var $body = jQuery('#mssgml-prom-body');
            if ( $body.is(':hidden') ) {
                $body.slideDown(200);
                jQuery('#mssgml-prom-chevron').css('transform','rotate(0deg)');
            }
            if ( $form.is(':visible') ) {
                /* Il form è già aperto: un secondo click lo richiude (toggle) */
                $form.slideUp(200);
                return;
            }
            jQuery('#mssgml-prom-id').val('');
            jQuery('#mssgml-prom-titolo').val('');
            jQuery('#mssgml-prom-durata').val('');
            var d = new Date(); d.setHours(d.getHours()+1,0,0,0);
            var pad = function(n){return n<10?'0'+n:n;};
            jQuery('#mssgml-prom-data').val(d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':00');
            $form.slideDown(200);
            jQuery('#mssgml-prom-titolo').focus();
        });
        jQuery('#mssgml-prom-annulla').on('click', function(){jQuery('#mssgml-form-promemoria').slideUp(200);});
        jQuery('#mssgml-prom-salva').on('click', function(){
            var titolo = jQuery('#mssgml-prom-titolo').val().trim();
            var dataRaw = jQuery('#mssgml-prom-data').val();
            if (!titolo || !dataRaw) { alert('Titolo e data obbligatori.'); return; }
            var data = dataRaw.replace('T',' ') + ':00';
            var $btn = jQuery(this); $btn.prop('disabled',true).text('…');
            var ajaxUrl = typeof MSSGAG !== 'undefined' ? MSSGAG.ajax_url : (typeof MSSG !== 'undefined' ? MSSG.ajax_url : ajaxurl);
            var nonce   = typeof MSSGAG !== 'undefined' ? MSSGAG.nonce   : (typeof MSSG !== 'undefined' ? MSSG.nonce   : '');
            jQuery.post(ajaxUrl, {
                action:'mssgag_salva_promemoria', nonce:nonce,
                id: jQuery('#mssgml-prom-id').val(),
                titolo: titolo, data_ora: data,
                durata_min: jQuery('#mssgml-prom-durata').val() || 0,
                notifica_email: jQuery('#mssgml-prom-notifica').is(':checked') ? 1 : 0,
                notifica_minuti: parseInt(jQuery('#mssgml-prom-minuti').val() || 60)
            }, function(r){
                if (r&&r.success) { jQuery('#mssgml-form-promemoria').slideUp(200); location.reload(); }
                else {
                    var msg=(r&&r.data&&r.data.msg)?r.data.msg:'Errore.';
                    if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
                    $btn.prop('disabled',false).text('💾 Salva');
                }
            }).fail(function(xhr){
                var msg='Errore di connessione'+(xhr&&xhr.status?' (HTTP '+xhr.status+')':'')+'. Riprova.';
                if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
                $btn.prop('disabled',false).text('💾 Salva');
            });
        });
        jQuery(document).on('click', '.mssgml-storico-elimina', function(){
            if (!confirm('Rimuovere questo appuntamento dallo storico? L\'azione è definitiva.')) return;
            var $btn = jQuery(this);
            var $row = $btn.closest('.mssgml-storico-row');
            var bid  = $btn.data('blocco-id');
            var ajaxUrl = typeof MSSG !== 'undefined' ? MSSG.ajax_url : ajaxurl;
            var nonce   = typeof MSSG !== 'undefined' ? MSSG.nonce   : '';
            $btn.prop('disabled', true);
            jQuery.post(ajaxUrl, {action:'mssgag_admin_delete_appuntamento', nonce:nonce, app_id:bid}, function(r){
                if (r && r.success) {
                    $row.fadeOut(200, function(){ jQuery(this).remove(); });
                } else {
                    var msg = (r && r.data && r.data.msg) ? r.data.msg : 'Errore.';
                    if (window.mssgToast) mssgToast(msg,'error'); else alert(msg);
                    $btn.prop('disabled', false);
                }
            }).fail(function(xhr){
                var msg = 'Errore di connessione' + (xhr && xhr.status ? ' (HTTP '+xhr.status+')' : '') + '. Riprova.';
                if (window.mssgToast) mssgToast(msg,'error'); else alert(msg);
                $btn.prop('disabled', false);
            });
        });

        jQuery(document).on('click', '.mssgml-elimina-promemoria', function(){
            if (!confirm('Eliminare questo promemoria?')) return;
            var id = jQuery(this).data('id');
            var $row = jQuery(this).closest('[style*="rgba(168,85,247"]');
            var ajaxUrl = typeof MSSGAG !== 'undefined' ? MSSGAG.ajax_url : (typeof MSSG !== 'undefined' ? MSSG.ajax_url : ajaxurl);
            var nonce   = typeof MSSGAG !== 'undefined' ? MSSGAG.nonce   : (typeof MSSG !== 'undefined' ? MSSG.nonce   : '');
            jQuery.post(ajaxUrl, {action:'mssgag_elimina_promemoria', nonce:nonce, id:id}, function(r){
                if (r&&r.success) $row.fadeOut(300, function(){jQuery(this).remove();});
                else {
                    var msg=(r&&r.data&&r.data.msg)?r.data.msg:'Errore.';
                    if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
                }
            }).fail(function(xhr){
                var msg='Errore di connessione'+(xhr&&xhr.status?' (HTTP '+xhr.status+')':'')+'. Riprova.';
                if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
            });
        });

    /* Filtro per nome */
        window.mssgmlFilter = function(q) {
            q = q.toLowerCase().trim();
            document.querySelectorAll('.mssgml-card').forEach(function(c) {
                c.style.display = (!q || c.dataset.nome.indexOf(q) !== -1) ? '' : 'none';
            });
        };

        /* Filtro per stato */
        document.querySelectorAll('.mssgml-filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.mssgml-filter-btn').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                var f = btn.dataset.filter;
                document.querySelectorAll('.mssgml-card').forEach(function(c) {
                    c.style.display = (f === 'tutti' || c.dataset.stato === f) ? '' : 'none';
                });
            });
        });
    })();

    /* ── Funzioni navigazione da miei-lavori ── */
    window.mssgmlOpenCliente = function(uid) {
        var $nav=jQuery('[data-section="mssg_clienti"]').first();
        if($nav.length) $nav.trigger('click');
    };
    window.mssgmlOpenCantiere = function(cid) {
        var $nav=jQuery('[data-section="mssg_cantieri"]').first();
        if($nav.length){ $nav.trigger('click'); }
        setTimeout(function(){
            jQuery('.mssgc-open-cantiere[data-id="'+cid+'"]').first().trigger('click');
        },600);
    };

    /* ── "Vedi" appuntamento: funzione globale chiamata via onclick ── */
    window.mssgmlVediApp = function(bid) {
        bid=parseInt(bid||0);
        if(!bid) return;
        var ajaxUrl=typeof MSSG!=='undefined'?MSSG.ajax_url:ajaxurl;
        var nonce=typeof MSSG!=='undefined'?MSSG.nonce:'';
        jQuery.post(ajaxUrl,{action:'mssgag_get_blocco_detail',nonce:nonce,blocco_id:bid},function(r){
            if(!r||!r.success){
                var msg=(r&&r.data&&r.data.msg)?r.data.msg:'Errore nel caricamento appuntamento.';
                if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
                return;
            }
            var d=r.data;
            var fmtDt=function(s){if(!s)return'';var dt=new Date(s.replace(' ','T'));var p=function(n){return String(n).padStart(2,'0');};return p(dt.getDate())+'/'+p(dt.getMonth()+1)+'/'+dt.getFullYear()+' '+p(dt.getHours())+':'+p(dt.getMinutes());};
            var fmtOra=function(s){if(!s)return'';var dt=new Date(s.replace(' ','T'));var p=function(n){return String(n).padStart(2,'0');};return p(dt.getHours())+':'+p(dt.getMinutes());};
            var labelT={richiesta:'⏳ In attesa di conferma',confermato:'✅ Confermato',interno:'🔒 Promemoria'};
            var canEdit=(d.tipo==='admin_fissato'||d.tipo==='confermato'||d.tipo==='interno');
            var html='<div id="mssgml-app-card" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:99999;background:var(--msslu-box-bg,#1e1e2e);border:1px solid var(--msslu-box-border);border-radius:14px;padding:20px;min-width:300px;max-width:380px;width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.6)">'
                +'<div id="mssgml-app-card-ov" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:-1"></div>'
                +'<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">'
                +'<div><div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--msslu-text-muted);margin-bottom:3px">'+(labelT[d.tipo]||d.tipo)+'</div>'
                +(d.titolo?'<div style="font-size:15px;font-weight:700;color:var(--msslu-text)">'+jQuery('<div>').text(d.titolo).html()+'</div>':'')
                +'</div><button id="mssgml-card-close" style="background:none;border:none;color:var(--msslu-text-muted);cursor:pointer;font-size:18px;padding:0;margin-left:12px">✕</button></div>'
                +'<div style="display:flex;gap:8px;padding:10px;background:rgba(255,255,255,.04);border-radius:8px;margin-bottom:10px">'
                +'<span style="font-size:20px">📅</span>'
                +'<div><div style="font-size:13px;font-weight:600">'+fmtDt(d.data_inizio)+'</div>'
                +(d.data_fine?'<div style="font-size:12px;color:var(--msslu-text-muted)">fino alle '+fmtOra(d.data_fine)+'</div>':'')
                +'</div></div>'
                +(d.partecipante_nome?'<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px"><span>👤</span>'
                +(d.cliente_id?'<a href="#" onclick="mssgmlOpenCliente('+d.cliente_id+');mssgmlCardClose();return false;" style="color:var(--msslu-accent,#e91e8c);text-decoration:none">'+jQuery('<div>').text(d.partecipante_nome).html()+' ↗</a>':'<span>'+jQuery('<div>').text(d.partecipante_nome).html()+'</span>')
                +'</div>':'')
                +(d.cantiere_nome?'<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px"><span>🏗</span>'
                +(d.cantiere_id?'<a href="#" onclick="mssgmlOpenCantiere('+d.cantiere_id+');mssgmlCardClose();return false;" style="color:var(--msslu-accent,#e91e8c);text-decoration:none">'+jQuery('<div>').text(d.cantiere_nome).html()+' ↗</a>':'<span>'+jQuery('<div>').text(d.cantiere_nome).html()+'</span>')
                +'</div>':'')
                +(d.luogo?'<div style="font-size:12px;color:var(--msslu-text-muted);margin-bottom:8px">📍 '+jQuery('<div>').text(d.luogo).html()+'</div>':'')
                +(d.nota?'<div style="font-size:12px;color:var(--msslu-text-muted);margin-bottom:8px;font-style:italic">💬 '+jQuery('<div>').text(d.nota).html()+'</div>':'')
                /* Sposta: datepicker inline */
                +'<div id="mssgml-sposta-wrap" style="display:none;margin-top:10px;padding:10px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.25);border-radius:8px">'
                +'<div style="font-size:12px;font-weight:600;color:#818cf8;margin-bottom:6px">📅 Nuovo orario</div>'
                +'<input type="datetime-local" id="mssgml-sposta-dt" style="width:100%;padding:7px 10px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:7px;color:var(--msslu-text);font-size:12px;box-sizing:border-box">'
                +'<button id="mssgml-sposta-ok" data-bid="'+bid+'" data-dur="'+Math.round((new Date((d.data_fine||d.data_inizio).replace(' ','T'))-new Date(d.data_inizio.replace(' ','T')))/60000)+'" style="margin-top:8px;width:100%;padding:7px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);border-radius:7px;color:#818cf8;cursor:pointer;font-size:12px">Conferma spostamento</button>'
                +'</div>'
                +(canEdit?'<div style="display:flex;gap:8px;margin-top:14px">'
                +'<button id="mssgml-card-sposta" data-bid="'+bid+'" style="flex:1;padding:8px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);border-radius:8px;color:#818cf8;font-size:12px;font-weight:600;cursor:pointer">📅 Sposta</button>'
                +'<button id="mssgml-card-elimina" data-bid="'+bid+'" style="flex:1;padding:8px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#ef4444;font-size:12px;font-weight:600;cursor:pointer">🗑 Annulla</button>'
                +'</div>':'')
                +'</div>';
            jQuery('#mssgml-app-card').remove();
            jQuery('body').append(html);
        }).fail(function(xhr){
            var msg='Errore di connessione'+(xhr&&xhr.status?' (HTTP '+xhr.status+')':'')+'. Riprova.';
            if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
        });
    };

    /* Card handlers */
    window.mssgmlCardClose = function(){jQuery('#mssgml-app-card').remove();};
    jQuery(document).on('click','#mssgml-card-close,#mssgml-app-card-ov',function(){mssgmlCardClose();});
    jQuery(document).on('click','#mssgml-card-sposta',function(){jQuery('#mssgml-sposta-wrap').slideToggle(200);});
    jQuery(document).on('click','#mssgml-sposta-ok',function(){
        var bid=jQuery(this).data('bid'), dur=jQuery(this).data('dur')||60;
        var dt=jQuery('#mssgml-sposta-dt').val();
        if(!dt){alert('Seleziona una data/ora.');return;}
        var ajaxUrl=typeof MSSG!=='undefined'?MSSG.ajax_url:ajaxurl;
        var nonce=typeof MSSG!=='undefined'?MSSG.nonce:'';
        jQuery.post(ajaxUrl,{action:'mssgag_sposta_appuntamento',nonce:nonce,blocco_id:bid,new_start:dt.replace('T',' ')+':00',durata_min:dur},function(r){
            if(r&&r.success){jQuery('#mssgml-app-card').remove();location.reload();}
            else{
                var msg=(r&&r.data&&r.data.msg)?r.data.msg:'Errore.';
                if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
            }
        }).fail(function(xhr){
            var msg='Errore di connessione'+(xhr&&xhr.status?' (HTTP '+xhr.status+')':'')+'. Riprova.';
            if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
        });
    });
    jQuery(document).on('click','#mssgml-card-elimina',function(){
        if(!confirm('Annullare questo appuntamento?'))return;
        var bid=jQuery(this).data('bid');
        var ajaxUrl=typeof MSSG!=='undefined'?MSSG.ajax_url:ajaxurl;
        var nonce=typeof MSSG!=='undefined'?MSSG.nonce:'';
        jQuery.post(ajaxUrl,{action:'mssgag_admin_delete_appuntamento',nonce:nonce,app_id:bid},function(r){
            if(r&&r.success){jQuery('#mssgml-app-card').remove();location.reload();}
            else{
                var msg=(r&&r.data&&r.data.msg)?r.data.msg:'Errore.';
                if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
            }
        }).fail(function(xhr){
            var msg='Errore di connessione'+(xhr&&xhr.status?' (HTTP '+xhr.status+')':'')+'. Riprova.';
            if(window.mssgToast)mssgToast(msg,'error');else alert(msg);
        });
    });

    /* Inizializza MSSGCV3 sul container giusto (serve #mssgc-panel e .mssgc-list-area) */
    (function tryInit(attempt){
        var $m = jQuery('#mssgml-main').length
            ? jQuery('#mssgml-main').closest('.msslu-account-main,[id*="section-main"]').first()
            : jQuery('.msslu-account-main,[id*="section-main"]').first();
        if (typeof MSSGCV3 !== 'undefined' && $m.length) {
            MSSGCV3.init($m);
        } else if (attempt < 10) {
            setTimeout(function(){ tryInit(attempt+1); }, 150);
        }
    })(0);

    /* Chat cantiere: apre il cantiere sulla tab Media (dove c'è la chat) */
    jQuery(document).off('click.mssgml-chat').on('click.mssgml-chat', '.mssgml-btn-chat-cantiere', function(e){
        e.stopPropagation();
        var cid = parseInt(jQuery(this).data('cantiere-id') || 0);
        if (!cid) return;
        var $w = jQuery('.msslu-account-main,[id*="section-main"]').first();
        if (typeof MSSGCV3 !== 'undefined' && MSSGCV3.openForm) {
            MSSGCV3.openForm(cid, $w, 'media');
        }
    });
    </script>
    <?php
}
