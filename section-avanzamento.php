<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function mssgc_render_avanzamento_tab( $cantiere_id, $user_id ) {
    $can_manage  = mssg_user_can($user_id,'manage_avanzamenti');
    $avanzamenti = mssgc_get_avanzamenti($cantiere_id);
    $tipi        = array('aggiornamento'=>'Aggiornamento','avviso'=>'Avviso','completamento'=>'Completamento','problema'=>'Problema');
    $tipi_icon   = array('aggiornamento'=>'🔄','avviso'=>'⚠️','completamento'=>'✅','problema'=>'🔴');
    $tipi_color  = array('aggiornamento'=>'mssg-status--completato','avviso'=>'mssg-status--sospeso','completamento'=>'mssg-status--attivo','problema'=>'mssg-status--chiuso');

    /* Recupera percentuale avanzamento lavori */
    global $wpdb;
    $avanz_pct = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT avanzamento_pct FROM `".mssgc_table('cantieri')."` WHERE id=%d", $cantiere_id
    ));
    $avanz_pct = min(100, max(0, $avanz_pct));

    ob_start(); ?>

    <!-- ── Barra avanzamento lavori ── -->
    <div style="background:var(--msslu-box-bg);border:1px solid var(--msslu-box-border);border-radius:10px;padding:16px;margin-bottom:20px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;gap:8px;flex-wrap:wrap">
            <div style="font-size:13px;font-weight:500;color:var(--msslu-text)">🏗 Avanzamento lavori</div>
            <div id="mssgc-avanz-pct-display" style="font-size:22px;font-weight:700;color:<?php echo $avanz_pct>=100?'#22c55e':($avanz_pct>=50?'var(--msslu-accent)':'#f59e0b');?>">
                <?php echo $avanz_pct;?>%
            </div>
        </div>
        <div style="height:10px;background:var(--msslu-box-border);border-radius:5px;overflow:hidden;margin-bottom:<?php echo $can_manage?'14':'0';?>px">
            <div id="mssgc-avanz-barra-fill" style="height:100%;width:<?php echo $avanz_pct;?>%;background:<?php echo $avanz_pct>=100?'#22c55e':($avanz_pct>=50?'var(--msslu-accent)':'#f59e0b');?>;border-radius:5px;transition:width .3s ease"></div>
        </div>
        <?php if($can_manage):?>
        <div style="display:flex;align-items:center;gap:10px">
            <input type="range" id="mssgc-avanz-pct-slider" min="0" max="100" value="<?php echo $avanz_pct;?>"
                   style="flex:1;accent-color:var(--msslu-accent)"
                   data-cantiere-id="<?php echo(int)$cantiere_id;?>">
            <span id="mssgc-avanz-pct-val" style="font-size:12px;color:var(--msslu-accent);font-weight:700;min-width:36px;text-align:right"><?php echo $avanz_pct;?>%</span>
            <button id="mssgc-avanz-pct-save" class="mssg-btn mssg-btn--primary" style="font-size:11px;padding:5px 12px"
                    data-cantiere-id="<?php echo(int)$cantiere_id;?>">Salva</button>
        </div>
        <div style="font-size:10px;color:var(--msslu-text-muted);margin-top:6px">
            Trascina per aggiornare — max 100%
        </div>
        <?php endif;?>
    </div>

    <?php if ($can_manage) : ?>
    <!-- Form nuovo aggiornamento -->
    <div style="background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:10px;padding:16px;margin-bottom:20px">
        <div style="font-size:12px;font-weight:500;margin-bottom:12px;color:var(--msslu-text)">Pubblica aggiornamento</div>
        <div class="mssg-form-grid">
            <div class="mssg-field">
                <label class="mssg-field-label">Tipo</label>
                <select name="avanz_tipo" id="mssgc-avanz-tipo">
                    <?php foreach ($tipi as $v=>$l) : ?>
                    <option value="<?php echo $v; ?>"><?php echo $tipi_icon[$v]; ?> <?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mssg-field">
                <label class="mssg-field-label">Titolo *</label>
                <input type="text" name="avanz_titolo" id="mssgc-avanz-titolo" placeholder="Es. Completata demolizione, Inizio posa piastrelle…">
            </div>
            <div class="mssg-field" style="grid-column:1/-1">
                <label class="mssg-field-label">Descrizione (opzionale)</label>
                <textarea name="avanz_testo" id="mssgc-avanz-testo" rows="2" placeholder="Dettagli aggiuntivi…"></textarea>
            </div>
            <div class="mssg-field" style="grid-column:1/-1">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                    <input type="checkbox" name="avanz_visibile_cliente" id="mssgc-avanz-cliente" value="1" checked style="accent-color:var(--msslu-accent)">
                    Visibile al cliente nella sua area personale
                </label>
                <div style="font-size:11px;color:var(--msslu-text-muted);margin-top:3px;margin-left:24px">
                    Se spuntato, il cliente riceve anche una notifica email.
                </div>
            </div>
        </div>
        <button class="mssg-btn mssg-btn--primary" id="mssgc-pubblica-avanzamento"
                data-cantiere-id="<?php echo (int)$cantiere_id; ?>" style="margin-top:12px">
            Pubblica aggiornamento
        </button>
    </div>
    <?php endif; ?>

    <!-- Lista aggiornamenti -->
    <?php if (empty($avanzamenti)) : ?>
    <div class="mssg-empty-state"><p>Nessun aggiornamento pubblicato.</p></div>
    <?php else : ?>
    <div class="mssgc-avanzamenti-list" id="mssgc-avanzamenti-list">
        <?php foreach ($avanzamenti as $a) : ?>
        <div class="mssgc-avanz-item" data-id="<?php echo (int)$a->id; ?>">
            <div class="mssgc-avanz-view">
                <div class="mssgc-avanz-header">
                    <span class="mssg-status <?php echo $tipi_color[$a->tipo]??'mssg-status--bozza'; ?>" style="font-size:11px">
                        <?php echo ($tipi_icon[$a->tipo]??'').' '.($tipi[$a->tipo]??$a->tipo); ?>
                    </span>
                    <?php if ($a->visibile_cliente) : ?>
                    <span style="font-size:11px;color:var(--msslu-text-muted)">👁 Visibile al cliente</span>
                    <?php endif; ?>
                    <span style="font-size:11px;color:var(--msslu-text-muted);margin-left:auto">
                        <?php echo date_i18n('d/m/Y H:i',strtotime($a->created_at)); ?>
                        · <?php echo esc_html($a->autore); ?>
                    </span>
                    <?php if ($can_manage) : ?>
                    <button class="mssgc-avanz-edit mssg-btn mssg-btn--ghost"
                            data-id="<?php echo (int)$a->id; ?>"
                            style="padding:2px 8px;font-size:11px;margin-left:6px">✎</button>
                    <button class="mssgc-avanz-delete mssg-btn mssg-btn--ghost"
                            data-id="<?php echo (int)$a->id; ?>"
                            style="padding:2px 8px;font-size:11px">✕</button>
                    <?php endif; ?>
                </div>
                <div style="font-size:14px;font-weight:500;margin-top:8px"><?php echo esc_html($a->titolo); ?></div>
                <?php if ($a->testo) : ?>
                <div style="font-size:13px;color:var(--msslu-text-muted);margin-top:4px;line-height:1.5"><?php echo nl2br(esc_html($a->testo)); ?></div>
                <?php endif; ?>
            </div>
            <?php if ($can_manage) : ?>
            <!-- Form di modifica inline, nascosto finché non si clicca ✎ -->
            <div class="mssgc-avanz-edit-form" style="display:none;margin-top:8px">
                <div class="mssg-form-grid">
                    <div class="mssg-field">
                        <label class="mssg-field-label">Tipo</label>
                        <select class="mssgc-avanz-edit-tipo">
                            <?php foreach ($tipi as $v=>$l) : ?>
                            <option value="<?php echo $v; ?>" <?php selected($a->tipo,$v); ?>><?php echo $tipi_icon[$v]; ?> <?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mssg-field">
                        <label class="mssg-field-label">Titolo *</label>
                        <input type="text" class="mssgc-avanz-edit-titolo" value="<?php echo esc_attr($a->titolo); ?>">
                    </div>
                    <div class="mssg-field" style="grid-column:1/-1">
                        <label class="mssg-field-label">Descrizione</label>
                        <textarea class="mssgc-avanz-edit-testo" rows="2"><?php echo esc_textarea($a->testo); ?></textarea>
                    </div>
                    <div class="mssg-field" style="grid-column:1/-1">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                            <input type="checkbox" class="mssgc-avanz-edit-cliente" <?php checked($a->visibile_cliente,1); ?> style="accent-color:var(--msslu-accent)">
                            Visibile al cliente
                        </label>
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:10px">
                    <button class="mssgc-avanz-save-edit mssg-btn mssg-btn--primary" data-id="<?php echo (int)$a->id; ?>" style="font-size:12px">Salva modifiche</button>
                    <button class="mssgc-avanz-cancel-edit mssg-btn mssg-btn--ghost" style="font-size:12px">Annulla</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
