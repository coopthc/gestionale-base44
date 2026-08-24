<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════
   I MIEI LAVORI — Agenda personale
   Per: administrator, mssg_admin, mssg_capo, mssg_operaio
   Mostra:
   - Cantieri assegnati (con stato + avanzamento)
   - Appuntamenti futuri a cui si partecipa
   - Appuntamenti passati recenti
══════════════════════════════════════════════════════ */

function mssgc_render_agenda( $user ) {
    $user_id = $user->ID;
    global $wpdb;

    $tc   = mssgc_table('cantieri');
    $tcu  = mssgc_table('cantieri_users');
    $ta   = mssgc_table('appuntamenti');
    $tav  = mssgc_table('avanzamenti');

    /* ── Cantieri dell'utente ── */
    $cantieri = mssgc_get_cantieri( $user_id );
    $cids     = array_column( $cantieri, 'id' );

    /* ── Appuntamenti: tutti i cantieri dell'utente ── */
    $appuntamenti = array();
    if ( ! empty($cids) && $wpdb->get_var("SHOW TABLES LIKE '{$ta}'") === $ta ) {
        $placeholders = implode( ',', array_fill(0, count($cids), '%d') );
        /* Admin/capo: vede TUTTI gli appuntamenti sui suoi cantieri
           Operaio: vede quelli dove è partecipante O creati su suoi cantieri */
        $is_admin_capo = mssg_user_can($user_id,'edit_cantieri');
        if ( $is_admin_capo ) {
            /* Tutti gli appuntamenti sui cantieri, senza filtro user_id */
            $appuntamenti = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT a.*, c.nome AS cantiere_nome, c.indirizzo AS cantiere_indirizzo,
                            u.display_name AS partecipante,
                            cb.display_name AS creato_da
                     FROM `{$ta}` a
                     LEFT JOIN `{$tc}` c ON c.id = a.cantiere_id
                     LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                     LEFT JOIN {$wpdb->users} cb ON cb.ID = a.created_by
                     WHERE a.cantiere_id IN ({$placeholders})
                     ORDER BY a.data_ora ASC",
                    $cids
                )
            );
        } else {
            /* Operaio: solo quelli dove è partecipante */
            $appuntamenti = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT a.*, c.nome AS cantiere_nome, c.indirizzo AS cantiere_indirizzo,
                            u.display_name AS partecipante
                     FROM `{$ta}` a
                     LEFT JOIN `{$tc}` c ON c.id = a.cantiere_id
                     LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                     WHERE a.cantiere_id IN ({$placeholders}) AND a.user_id = %d
                     ORDER BY a.data_ora ASC",
                    array_merge($cids, array($user_id))
                )
            );
        }
        usort( $appuntamenti, fn($a,$b) => strcmp($a->data_ora, $b->data_ora) );
    }

    $now     = current_time('mysql');
    $futuri  = array_filter( $appuntamenti, fn($a) => $a->data_ora >= $now );
    $passati = array_filter( $appuntamenti, fn($a) => $a->data_ora < $now );
    $passati = array_slice( array_reverse(array_values($passati)), 0, 5 );

    $stati_label = array(
        'bozza'=>'In attesa','attivo'=>'In corso','sospeso'=>'Sospeso',
        'completato'=>'Completato','chiuso'=>'Chiuso','archiviato'=>'Archiviato'
    );
    $stati_color = array(
        'attivo'=>'#22c55e','completato'=>'#4f46e5','sospeso'=>'#f59e0b',
        'chiuso'=>'#6b7280','bozza'=>'#8b5cf6','archiviato'=>'#374151'
    );
    ?>

    <div class="mssg-section mssgc-agenda" id="mssgc-agenda-main">

        <!-- ══ HEADER ══ -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
            <div>
                <h2 class="msslu-section-title" style="margin:0">I miei lavori</h2>
                <div style="font-size:13px;color:var(--msslu-text-muted);margin-top:3px">
                    <?php echo count($cantieri); ?> cantieri · <?php echo count($futuri); ?> appuntamenti in programma
                </div>
            </div>
            <button class="mssg-btn mssg-btn--ghost" data-section="mssg_cantieri" style="font-size:12px">
                → Vai ai cantieri
            </button>
        </div>

        <?php if ( empty($cantieri) && empty($appuntamenti) ) : ?>
        <div class="mssg-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px;height:40px;opacity:.3;display:block;margin:0 auto 12px"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 22V12h6v10M9 7h1m4 0h1"/></svg>
            <p>Nessun cantiere o appuntamento assegnato.</p>
        </div>
        <?php else : ?>

        <!-- ══ APPUNTAMENTI FUTURI ══ -->
        <?php if ( ! empty($futuri) ) : ?>
        <div style="margin-bottom:28px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--msslu-text-muted);margin-bottom:12px">
                📅 Prossimi appuntamenti (<?php echo count($futuri); ?>)
            </div>
            <?php foreach ( $futuri as $app ) :
                $data_fmt  = date_i18n('D d M Y', strtotime($app->data_ora));
                $ora_fmt   = date_i18n('H:i', strtotime($app->data_ora));
                $is_oggi   = date('Y-m-d', strtotime($app->data_ora)) === date('Y-m-d', strtotime($now));
                $is_domani = date('Y-m-d', strtotime($app->data_ora)) === date('Y-m-d', strtotime('+1 day', strtotime($now)));
                $label_giorno = $is_oggi ? '🔴 OGGI' : ($is_domani ? '🟡 DOMANI' : '');
            ?>
            <div style="display:flex;gap:14px;padding:12px 16px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:10px;margin-bottom:8px;<?php echo $is_oggi?'border-left:3px solid var(--msslu-accent)':($is_domani?'border-left:3px solid #f59e0b':''); ?>">
                <!-- Data block -->
                <div style="flex-shrink:0;text-align:center;min-width:52px;padding:6px 10px;background:var(--msslu-box-bg);border-radius:8px;border:1px solid var(--msslu-box-border)">
                    <div style="font-size:20px;font-weight:800;color:var(--msslu-text);line-height:1"><?php echo date_i18n('d', strtotime($app->data_ora)); ?></div>
                    <div style="font-size:10px;text-transform:uppercase;color:var(--msslu-text-muted);letter-spacing:.05em"><?php echo date_i18n('M', strtotime($app->data_ora)); ?></div>
                    <div style="font-size:12px;font-weight:700;color:var(--msslu-accent);margin-top:2px"><?php echo $ora_fmt; ?></div>
                </div>
                <!-- Info -->
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                        <?php if ($label_giorno) : ?>
                        <span style="font-size:11px;font-weight:800;color:<?php echo $is_oggi?'var(--msslu-accent)':'#f59e0b'; ?>"><?php echo $label_giorno; ?></span>
                        <?php endif; ?>
                        <span style="font-size:14px;font-weight:700;color:var(--msslu-text)"><?php echo esc_html($app->titolo); ?></span>
                    </div>
                    <div style="font-size:12px;color:var(--msslu-text-muted);display:flex;flex-wrap:wrap;gap:10px">
                        <?php if ($app->cantiere_nome) : ?>
                        <span>🏗 <?php echo esc_html($app->cantiere_nome); ?></span>
                        <?php endif; ?>
                        <?php if ($app->luogo) : ?>
                        <span>📍 <?php echo esc_html($app->luogo); ?></span>
                        <?php endif; ?>
                        <?php if ($app->partecipante) : ?>
                        <span>👤 <?php echo esc_html($app->partecipante); ?></span>
                        <?php endif; ?>
                        <span>⏱ <?php echo (int)$app->durata_min; ?> min</span>
                    </div>
                    <?php if ($app->note) : ?>
                    <div style="font-size:12px;color:var(--msslu-text-muted);margin-top:4px;font-style:italic"><?php echo esc_html($app->note); ?></div>
                    <?php endif; ?>
                </div>
                <!-- Azione -->
                <div style="flex-shrink:0">
                    <button class="mssg-btn mssg-btn--ghost mssgc-agenda-apri" data-cantiere-id="<?php echo (int)$app->cantiere_id; ?>" style="font-size:11px;padding:5px 10px">Apri</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ══ CANTIERI ASSEGNATI ══ -->
        <?php if ( ! empty($cantieri) ) : ?>
        <div style="margin-bottom:28px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--msslu-text-muted);margin-bottom:12px">
                🏗 I miei cantieri (<?php echo count($cantieri); ?>)
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px">
                <?php foreach ( $cantieri as $c ) :
                    $avanz = 0;
                    if ( function_exists('mssgcl_calcola_avanzamento') ) {
                        $avanz = mssgcl_calcola_avanzamento($c->id);
                    } else {
                        /* Fallback: conta avanzamenti completamento */
                        $tot = (int)$wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM `{$tav}` WHERE cantiere_id=%d AND deleted_at IS NULL", $c->id));
                        $ok  = (int)$wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM `{$tav}` WHERE cantiere_id=%d AND tipo='completamento' AND deleted_at IS NULL", $c->id));
                        $avanz = $tot > 0 ? round($ok/$tot*100) : 0;
                    }
                    $color = $stati_color[$c->stato] ?? '#6b7280';
                    /* Prossimo appuntamento per questo cantiere */
                    $prossimo = null;
                    if ( $wpdb->get_var("SHOW TABLES LIKE '{$ta}'") === $ta ) {
                        $prossimo = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM `{$ta}` WHERE cantiere_id=%d AND data_ora>=%s ORDER BY data_ora ASC LIMIT 1",
                            $c->id, $now));
                    }
                ?>
                <div style="background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:12px;padding:14px;transition:border-color .2s;cursor:pointer"
                     class="mssgc-agenda-card"
                     data-cantiere-id="<?php echo (int)$c->id; ?>"
                     onmouseover="this.style.borderColor='rgba(255,255,255,.2)'"
                     onmouseout="this.style.borderColor='var(--msslu-box-border)'">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
                        <div style="font-size:14px;font-weight:700;color:var(--msslu-text);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html($c->nome); ?></div>
                        <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:999px;background:<?php echo $color; ?>22;color:<?php echo $color; ?>;flex-shrink:0;margin-left:8px">
                            <?php echo $stati_label[$c->stato] ?? $c->stato; ?>
                        </span>
                    </div>
                    <?php if ($c->citta || $c->indirizzo) : ?>
                    <div style="font-size:11px;color:var(--msslu-text-muted);margin-bottom:8px">
                        📍 <?php echo esc_html($c->citta ?: $c->indirizzo); ?>
                    </div>
                    <?php endif; ?>
                    <!-- Avanzamento -->
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        <div style="flex:1;height:4px;background:var(--msslu-box-border);border-radius:2px;overflow:hidden">
                            <div style="height:100%;width:<?php echo $avanz; ?>%;background:<?php echo $color; ?>;border-radius:2px;transition:width .5s"></div>
                        </div>
                        <span style="font-size:10px;color:var(--msslu-text-muted);flex-shrink:0"><?php echo $avanz; ?>%</span>
                    </div>
                    <?php if ($prossimo) : ?>
                    <div style="font-size:11px;color:var(--msslu-text-muted);border-top:1px solid var(--msslu-box-border);padding-top:6px;margin-top:2px">
                        📅 <?php echo date_i18n('d/m H:i', strtotime($prossimo->data_ora)); ?> — <?php echo esc_html($prossimo->titolo); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ APPUNTAMENTI PASSATI ══ -->
        <?php if ( ! empty($passati) ) : ?>
        <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--msslu-text-muted);margin-bottom:12px">
                🕐 Ultimi appuntamenti
            </div>
            <?php foreach ( $passati as $app ) : ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:8px;margin-bottom:6px;opacity:.7">
                <div style="flex-shrink:0;font-size:12px;color:var(--msslu-text-muted);min-width:100px">
                    <?php echo date_i18n('d/m/Y H:i', strtotime($app->data_ora)); ?>
                </div>
                <div style="flex:1;min-width:0">
                    <span style="font-size:13px;font-weight:600;color:var(--msslu-text)"><?php echo esc_html($app->titolo); ?></span>
                    <?php if ($app->cantiere_nome) : ?>
                    <span style="font-size:11px;color:var(--msslu-text-muted);margin-left:8px">🏗 <?php echo esc_html($app->cantiere_nome); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; /* end !empty */ ?>
    </div>

    <script>
    jQuery(function($){
        /* Click su card cantiere → apri il cantiere */
        $('.mssgc-agenda-card,.mssgc-agenda-apri').on('click', function(e){
            e.stopPropagation();
            var cid = $(this).data('cantiere-id');
            if (!cid) return;
            /* Vai alla sezione cantieri e apri il form */
            var $nav = $('[data-section="mssg_cantieri"]');
            if ($nav.length) {
                $nav.trigger('click');
                setTimeout(function(){
                    if (typeof MSSGCV3 !== 'undefined') {
                        var $w = $('#mssgc-main');
                        if ($w.length) MSSGCV3.openForm(cid, $w);
                    }
                }, 400);
            }
        });
        /* Click pulsante "Vai ai cantieri" */
        $('[data-section="mssg_cantieri"]').on('click', function(){
            $(document).trigger('msslu:load-section', ['mssg_cantieri']);
        });
    });
    </script>
    <?php
}
