<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════
   TAB PAGAMENTI CANTIERE
   Milestone di pagamento con percentuale e stato
   Barra progressiva calcolata sui pagamenti ricevuti
══════════════════════════════════════════════════════ */

function mssgc_render_pagamenti_tab( $cantiere_id, $user_id ) {
    global $wpdb;
    $tp = mssgc_table('pagamenti');
    $can_edit = mssg_user_can($user_id,'edit_cantieri');

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$tp}` WHERE cantiere_id=%d ORDER BY ordine ASC, id ASC",
        $cantiere_id
    ));

    /* Calcola totale preventivo dal cantiere */
    $cantiere = mssgc_get_cantiere($cantiere_id, $user_id);
    $importo_totale = (float)($cantiere->importo_prev ?? 0);

    /* % completamento pagamenti */
    $perc_pagata = 0;
    foreach ($rows as $r) {
        if ($r->pagato) $perc_pagata += (float)$r->percentuale;
    }
    $perc_pagata = min(100, $perc_pagata);

    ob_start(); ?>


    <div id="mssgc-pagamenti-wrap">

        <!-- Riepilogo barra -->
        <div style="background:var(--wn-bg-2, rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:16px;margin-bottom:20px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
                <div>
                    <div style="font-size:13px;font-weight:700;color:#f0f0f0">Avanzamento pagamenti</div>
                    <?php if ($importo_totale > 0): ?>
                    <div style="font-size:12px;color:rgba(255,255,255,.5)">
                        Totale preventivo: <strong style="color:#f0f0f0">€ <?php echo number_format($importo_totale, 2, ',', '.'); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mssgc-pag-perc-label" style="font-size:28px;font-weight:800;color:<?php echo $perc_pagata >= 100 ? '#22c55e' : 'var(--msslu-accent, var(--wn-accent-txt, #e91e8c))'; ?>">
                    <?php echo $perc_pagata; ?>%
                </div>
            </div>
            <div class="mssgc-pag-bar-track">
                <?php if ($perc_pagata > 0): ?>
                <div class="mssgc-pag-bar-fill<?php echo $perc_pagata >= 100 ? ' mssgc-pag-bar-fill--done' : ''; ?>"
                     style="width:<?php echo $perc_pagata; ?>%"></div>
                <?php endif; ?>
            </div>
            <!-- Labels milestone sotto la barra -->
            <?php if (!empty($rows)): ?>
            <div style="display:flex;margin-top:8px;position:relative">
                <?php foreach ($rows as $r): ?>
                <div style="flex:<?php echo max(1,(int)$r->percentuale); ?>;text-align:center;font-size:9px;color:rgba(255,255,255,.5)">
                    <?php echo $r->percentuale; ?>%
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lista milestone -->
        <div id="mssgc-pag-lista" style="display:flex;flex-direction:column;gap:10px">
            <?php if (empty($rows)): ?>
            <div style="text-align:center;color:rgba(255,255,255,.5);font-size:13px;padding:20px 0">
                Nessuna milestone definita. Aggiungi acconto, avanzamenti e saldo.
            </div>
            <?php else: foreach ($rows as $r): ?>
            <?php mssgc_render_pagamento_row($r, $importo_totale, $can_edit); ?>
            <?php endforeach; endif; ?>
        </div>

        <!-- Form aggiunta milestone -->
        <?php if ($can_edit): ?>
        <div style="margin-top:20px;padding:16px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:12px">
            <div style="font-size:12px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px">+ Aggiungi milestone di pagamento</div>
            <div class="mssg-form-grid" style="grid-template-columns:1fr 1fr;gap:10px">
                <div class="mssg-field">
                    <label class="mssg-field-label">Tipo</label>
                    <select id="mssgc-pag-tipo">
                        <option value="acconto">💶 Acconto</option>
                        <option value="avanzamento">📊 Avanzamento SAL</option>
                        <option value="saldo">✅ Saldo finale</option>
                    </select>
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">Etichetta (opz.)</label>
                    <input type="text" id="mssgc-pag-label" placeholder="Es. Acconto 30%, SAL 1, Saldo…">
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">% sul totale</label>
                    <input type="number" id="mssgc-pag-perc" min="1" max="100" value="30" style="width:100%" data-importo-totale="<?php echo esc_attr($importo_totale); ?>">
                </div>
                <?php if ($importo_totale > 0): ?>
                <div class="mssg-field">
                    <label class="mssg-field-label">Importo (€) <span style="font-weight:400;text-transform:none">— alternativa alla %</span></label>
                    <input type="number" id="mssgc-pag-importo" min="0" step="0.01" placeholder="Es. 3.000,00" data-importo-totale="<?php echo esc_attr($importo_totale); ?>">
                </div>
                <div style="grid-column:1/-1;font-size:10px;color:rgba(255,255,255,.4);margin-top:-6px">
                    Compila uno dei due campi: l'altro si aggiorna automaticamente in base al totale preventivo (€ <?php echo number_format($importo_totale,2,',','.'); ?>).
                </div>
                <?php endif; ?>
                <div class="mssg-field" style="grid-column:1/-1">
                    <label class="mssg-field-label">Note interne</label>
                    <input type="text" id="mssgc-pag-note" placeholder="Note per questo pagamento...">
                </div>
            </div>
            <button class="mssg-btn mssg-btn--primary" id="mssgc-pag-aggiungi" data-cantiere-id="<?php echo (int)$cantiere_id; ?>" style="margin-top:10px">
                + Aggiungi
            </button>
        </div>
        <?php endif; ?>

    </div>

    <?php
    return ob_get_clean();
}

function mssgc_render_pagamento_row($r, $importo_totale, $can_edit) {
    $tipo_icon = array('acconto'=>'💶','avanzamento'=>'📊','saldo'=>'✅');
    $icon = $tipo_icon[$r->tipo] ?? '💶';
    $importo_calc = $importo_totale > 0 ? ($importo_totale * $r->percentuale / 100) : (float)$r->importo;
    $label = $r->label ?: ucfirst($r->tipo);
    ?>
    <div class="mssgc-pag-row" data-id="<?php echo (int)$r->id; ?>"
         style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--wn-bg-2, rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.1);border-radius:10px;<?php echo $r->pagato?'border-left:3px solid #22c55e':''; ?>">

        <!-- Checkbox pagato -->
        <?php if ($can_edit): ?>
        <label style="flex-shrink:0;cursor:pointer" title="Segna come pagato">
            <input type="checkbox" class="mssgc-pag-check" data-id="<?php echo (int)$r->id; ?>"
                   <?php checked($r->pagato,1); ?>
                   style="width:18px;height:18px;accent-color:#22c55e;cursor:pointer">
        </label>
        <?php else: ?>
        <span style="font-size:18px;flex-shrink:0"><?php echo $r->pagato ? '✅' : '⬜'; ?></span>
        <?php endif; ?>

        <!-- Icona tipo -->
        <span style="font-size:20px;flex-shrink:0"><?php echo $icon; ?></span>

        <!-- Info -->
        <div style="flex:1;min-width:0">
            <div style="font-size:14px;font-weight:700;color:#f0f0f0"><?php echo esc_html($label); ?></div>
            <div style="font-size:12px;color:rgba(255,255,255,.5);display:flex;flex-wrap:wrap;gap:10px;margin-top:3px">
                <span><?php echo $r->percentuale; ?>% del totale</span>
                <?php if ($importo_calc > 0): ?>
                <span style="color:var(--msslu-accent, var(--wn-accent-txt, #e91e8c));font-weight:700">€ <?php echo number_format($importo_calc,2,',','.'); ?></span>
                <?php endif; ?>
                <?php if ($r->pagato && $r->data_pagamento): ?>
                <span style="color:#22c55e">✓ Pagato il <?php echo date_i18n('d/m/Y',strtotime($r->data_pagamento)); ?></span>
                <?php endif; ?>
                <?php if ($r->note): ?>
                <span style="font-style:italic"><?php echo esc_html($r->note); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Data pagamento (editabile) -->
        <?php if ($can_edit && $r->pagato): ?>
        <div style="flex-shrink:0">
            <input type="date" class="mssgc-pag-data" data-id="<?php echo (int)$r->id; ?>"
                   value="<?php echo esc_attr($r->data_pagamento ?: date('Y-m-d')); ?>"
                   style="font-size:12px;padding:4px 8px;background:var(--wn-bg-2, rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#f0f0f0">
        </div>
        <?php endif; ?>

        <!-- Elimina -->
        <?php if ($can_edit): ?>
        <button class="mssgc-pag-delete mssg-btn mssg-btn--ghost" data-id="<?php echo (int)$r->id; ?>"
                style="flex-shrink:0;padding:4px 8px;font-size:12px;color:#ef4444;border-color:#ef4444"
                title="Elimina milestone">✕</button>
        <?php endif; ?>
    </div>
    <?php
}
