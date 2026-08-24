<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════
   FORM CANTIERE — TAB MULTI-STEP
   Tab 1: Dati generali
   Tab 2: Collaboratori + Cliente
   Tab 3: Media (foto/video/docs)
   Tab 4: Avanzamento lavori
   Tab 5: Note + Appuntamento
══════════════════════════════════════════════════════════ */

function mssgc_render_form( $cantiere_id = 0, $user_id = null ) {
    $user_id  = $user_id ?: get_current_user_id();
    $cantiere = $cantiere_id ? mssgc_get_cantiere($cantiere_id,$user_id) : null;
    $is_edit  = !empty($cantiere);
    $can_edit = mssg_user_can($user_id,'edit_cantieri',$cantiere_id);

    $capicantiere = get_users(array('role__in'=>array('mssg_capo','mssg_admin','administrator'),'orderby'=>'display_name'));
    $clienti      = get_users(array('role'=>'mssg_cliente','orderby'=>'display_name'));
    $disponibili  = mssgc_get_collaboratori_disponibili();
    $assegnati    = $is_edit ? mssgc_get_collaboratori_cantiere($cantiere_id) : array();
    $assegnati_ids= array_column($assegnati,'user_id');
    $avanzamenti  = $is_edit ? mssgc_get_avanzamenti($cantiere_id) : array();
    $appuntamenti = $is_edit ? mssgc_get_appuntamenti($cantiere_id) : array();

    ob_start(); ?>
    <div class="mssgc-form-wrap" data-cantiere-id="<?php echo (int)$cantiere_id; ?>">

        <!-- Header -->
        <div class="mssgc-form-header">
            <h3><?php echo $is_edit ? esc_html($cantiere->nome) : 'Nuovo cantiere'; ?></h3>
            <button class="mssg-btn mssg-btn--ghost mssgc-btn-back" style="padding:5px 12px;font-size:12px">← Lista</button>
        </div>

        <!-- Tab nav -->
        <div class="mssgc-tabs-nav">
            <button class="mssgc-tab-btn active" data-tab="dati">📋 Dati</button>
            <button class="mssgc-tab-btn" data-tab="team">👥 Team</button>
            <button class="mssgc-tab-btn" data-tab="media">📷 Media</button>
            <button class="mssgc-tab-btn" data-tab="avanzamento">📊 Avanzamento</button>
            <button class="mssgc-tab-btn" data-tab="pagamenti">💶 Pagamenti</button>
<!-- Note/Appuntamenti tab rimosso - gestito da Proponi Appuntamento -->
        </div>

        <!-- TAB 1: DATI GENERALI ─────────────────────── -->
        <div class="mssgc-tab-content active" data-tab="dati">
            <div class="mssg-form-grid">
                <div class="mssg-field">
                    <label class="mssg-field-label">Codice cantiere</label>
                    <input type="text" name="codice" placeholder="ES-2024-001" value="<?php echo esc_attr($cantiere->codice??''); ?>">
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">Nome cantiere *</label>
                    <input type="text" name="nome" placeholder="Via Roma 14 — Ristrutturazione" value="<?php echo esc_attr($cantiere->nome??''); ?>">
                </div>
                <div class="mssg-field" style="grid-column:1/-1">
                    <label class="mssg-field-label">Indirizzo</label>
                    <input type="text" name="indirizzo" placeholder="Via Roma, 14" value="<?php echo esc_attr($cantiere->indirizzo??''); ?>">
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">Città</label>
                    <input type="text" name="citta" placeholder="Milano" value="<?php echo esc_attr($cantiere->citta??''); ?>">
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">CAP</label>
                    <input type="text" name="cap" maxlength="5" placeholder="20100" value="<?php echo esc_attr($cantiere->cap??''); ?>">
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">Data inizio</label>
                    <input type="date" name="data_inizio" value="<?php echo esc_attr($cantiere->data_inizio??''); ?>">
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">Fine prevista</label>
                    <input type="date" name="data_fine_prev" value="<?php echo esc_attr($cantiere->data_fine_prev??''); ?>">
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">Importo preventivato (€)</label>
                    <input type="number" name="importo_prev" step="0.01" min="0" placeholder="0.00" value="<?php echo esc_attr($cantiere->importo_prev??''); ?>">
                </div>
                <div class="mssg-field">
                    <label class="mssg-field-label">Stato</label>
                    <select name="stato">
                        <?php foreach (array('bozza'=>'Bozza','attivo'=>'Attivo','sospeso'=>'Sospeso','completato'=>'Completato','chiuso'=>'Chiuso') as $v=>$l) : ?>
                        <option value="<?php echo $v; ?>" <?php selected($cantiere->stato??'bozza',$v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mssg-field" style="grid-column:1/-1">
                    <label class="mssg-field-label">Descrizione lavori</label>
                    <textarea name="descrizione" rows="3" placeholder="Descrizione intervento…"><?php echo esc_textarea($cantiere->descrizione??''); ?></textarea>
                </div>
                <div class="mssg-field" style="grid-column:1/-1">
                    <label class="mssg-field-label">Note interne</label>
                    <textarea name="note_interne" rows="2" placeholder="Note visibili solo agli amministratori…"><?php echo esc_textarea($cantiere->note_interne??''); ?></textarea>
                </div>
            </div>
            <div class="mssgc-tab-actions">
                <button class="mssg-btn mssg-btn--primary" id="mssgc-form-save" data-id="<?php echo (int)$cantiere_id; ?>">
                    <?php echo $is_edit ? 'Salva dati' : 'Crea cantiere'; ?>
                </button>
                <?php if ($is_edit && mssg_user_can($user_id,'delete_cantieri')) : ?>
                <button class="mssg-btn mssg-btn--danger" id="mssgc-form-delete" data-id="<?php echo (int)$cantiere_id; ?>" style="margin-left:auto">Elimina</button>
                <?php endif; ?>
            </div>
        </div>

        </div><!-- fine tab dati -->

        <!-- TAB 2: TEAM ─────────────────────────────── -->
        <div class="mssgc-tab-content" data-tab="team">
            <?php if (!$is_edit) : ?>
            <div style="padding:30px;text-align:center;color:var(--msslu-text-muted);font-size:13px">
                Salva prima i dati del cantiere per abbinare il team.
            </div>
            <?php else : ?>

            <!-- Cliente -->
            <div style="margin-bottom:20px">
                <div class="mssgp-form-section-label" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted);margin-bottom:10px">Cliente abbinato</div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <select id="mssgc-select-cliente" style="flex:1;min-width:200px;padding:8px 10px;background:var(--msslu-input-bg);border:1px solid var(--msslu-input-border);border-radius:7px;color:var(--msslu-text);font-size:13px">
                        <option value="0">— Nessun cliente —</option>
                        <?php foreach ($clienti as $u) : ?>
                        <option value="<?php echo $u->ID; ?>" <?php selected((int)($cantiere->cliente_id??0),$u->ID); ?>><?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_email); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button class="mssg-btn mssg-btn--primary" id="mssgc-save-cliente">Aggiorna cliente</button>
                </div>
                <?php if (empty($clienti)) : ?>
                <p style="font-size:12px;color:var(--msslu-text-muted);margin-top:8px">Nessun cliente registrato. <a href="#" id="mssgc-crea-cliente-link" style="color:var(--msslu-accent)">Crea un nuovo cliente →</a></p>
                <?php endif; ?>
            </div>

            <!-- Responsabile -->
            <div style="margin-bottom:20px">
                <div class="mssgp-form-section-label" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted);margin-bottom:10px">Responsabile / Capo cantiere</div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <select id="mssgc-select-responsabile" style="flex:1;min-width:200px;padding:8px 10px;background:var(--msslu-input-bg);border:1px solid var(--msslu-input-border);border-radius:7px;color:var(--msslu-text);font-size:13px">
                        <option value="0">— Seleziona responsabile —</option>
                        <?php foreach ($capicantiere as $u) : ?>
                        <option value="<?php echo $u->ID; ?>" <?php selected((int)($cantiere->responsabile_id??0),$u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="mssg-btn mssg-btn--primary" id="mssgc-save-responsabile">Aggiorna</button>
                </div>
            </div>

            <!-- Collaboratori -->
            <div class="mssgp-form-section-label" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted);margin-bottom:10px">Collaboratori assegnati</div>

            <div class="mssgc-team-list" id="mssgc-team-list">
                <?php if (empty($assegnati)) : ?>
                <p style="font-size:13px;color:var(--msslu-text-muted)">Nessun collaboratore assegnato.</p>
                <?php else : ?>
                <?php foreach ($assegnati as $col) :
                    $ruoli_label = array('capo'=>'Capo','operaio'=>'Operaio','subappaltatore'=>'Sub.','supervisore'=>'Supervisore');
                ?>
                <div class="mssgc-team-row" data-user-id="<?php echo (int)$col->user_id; ?>">
                    <div class="mssgc-team-info">
                        <img src="<?php echo esc_url(get_avatar_url($col->user_id,array('size'=>32))); ?>" width="32" height="32" style="border-radius:50%;flex-shrink:0">
                        <div>
                            <div style="font-size:13px;font-weight:500"><?php echo esc_html($col->display_name); ?></div>
                            <div style="font-size:11px;color:var(--msslu-text-muted)"><?php echo esc_html($col->qualifica?:$col->user_email); ?></div>
                        </div>
                    </div>
                    <select class="mssgc-ruolo-select" data-user-id="<?php echo (int)$col->user_id; ?>" style="font-size:12px;padding:4px 8px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:6px;color:var(--msslu-text)">
                        <?php foreach (array('operaio'=>'Operaio','capo'=>'Capo cantiere','subappaltatore'=>'Subappaltatore','supervisore'=>'Supervisore') as $v=>$l) : ?>
                        <option value="<?php echo $v; ?>" <?php selected($col->ruolo_cantiere??'operaio',$v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="mssg-btn mssg-btn--danger mssgc-rimuovi-col" data-user-id="<?php echo (int)$col->user_id; ?>" style="padding:4px 8px;font-size:12px">✕</button>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Aggiungi collaboratore -->
            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <select id="mssgc-add-collaboratore" style="flex:1;min-width:180px;padding:7px 10px;background:var(--msslu-input-bg);border:1px solid var(--msslu-input-border);border-radius:7px;color:var(--msslu-text);font-size:13px">
                    <option value="0">+ Aggiungi collaboratore</option>
                    <?php foreach ($disponibili as $u) :
                        if (in_array($u->ID,$assegnati_ids)) continue; ?>
                    <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="mssgc-add-ruolo" style="padding:7px 10px;background:var(--msslu-input-bg);border:1px solid var(--msslu-input-border);border-radius:7px;color:var(--msslu-text);font-size:13px">
                    <option value="operaio">Operaio</option>
                    <option value="capo">Capo cantiere</option>
                    <option value="subappaltatore">Subappaltatore</option>
                    <option value="supervisore">Supervisore</option>
                </select>
                <button class="mssg-btn mssg-btn--primary" id="mssgc-aggiungi-col">+ Aggiungi</button>
            </div>

            <?php endif; // is_edit ?>
        </div>

        <!-- TAB 3: MEDIA ─────────────────────────────── -->
        <div class="mssgc-tab-content" data-tab="media">
            <?php if (!$is_edit) : ?>
            <div style="padding:30px;text-align:center;color:var(--msslu-text-muted);font-size:13px">Salva prima i dati del cantiere per caricare i media.</div>
            <?php else : echo mssgc_render_media_tab($cantiere_id, $user_id); endif; ?>
        </div>

        <!-- TAB 4: AVANZAMENTO ───────────────────────── -->
        <div class="mssgc-tab-content" data-tab="avanzamento">
            <?php if (!$is_edit) : ?>
            <div style="padding:30px;text-align:center;color:var(--msslu-text-muted);font-size:13px">Salva prima i dati del cantiere.</div>
            <?php else : echo mssgc_render_avanzamento_tab($cantiere_id, $user_id); endif; ?>
        </div>

        <!-- TAB 5: PAGAMENTI ─────────────────────────── -->
        <div class="mssgc-tab-content" data-tab="pagamenti">
            <?php if (!$is_edit) : ?>
            <div style="padding:30px;text-align:center;color:var(--msslu-text-muted);font-size:13px">Salva prima i dati del cantiere.</div>
            <?php else : echo mssgc_render_pagamenti_tab($cantiere_id, $user_id); endif; ?>
        </div>

        <!-- TAB 6: NOTE + APPUNTAMENTO ───────────────── -->
        

    </div>
    <?php
    return ob_get_clean();
}


/* ── Standalone note tab render — per reload AJAX parziale ── */
function mssgc_render_note_tab($cantiere_id, $user_id) {
    $can_edit     = mssg_user_can($user_id, 'edit_cantieri');
    $appuntamenti = mssgc_get_appuntamenti($cantiere_id);
    $cantiere     = mssgc_get_cantiere($cantiere_id, $user_id);
    /* Usa gli assegnati al cantiere + cliente, come nel form originale */
    $assegnati    = mssgc_get_collaboratori_cantiere($cantiere_id);
    ob_start(); ?>
    <div class="mssgc-app-lista" style="margin-bottom:16px">
    <?php if(empty($appuntamenti)):?>
    <div class="mssgc-note-empty" style="color:var(--msslu-text-muted);font-size:13px;padding:12px 0">Nessun appuntamento.</div>
    <?php else: foreach($appuntamenti as $app):?>
    <div class="mssgc-app-row" style="padding:10px 14px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:8px;margin-bottom:8px;font-size:13px;display:flex;justify-content:space-between;align-items:center">
        <div>
            <div style="font-weight:500"><?php echo esc_html($app->titolo);?></div>
            <div style="color:var(--msslu-text-muted);font-size:11px">
                📅 <?php echo esc_html(date_i18n('d/m/Y H:i',strtotime($app->data_ora)));?>
                <?php if($app->luogo):?> · 📍 <?php echo esc_html($app->luogo);?><?php endif;?>
                <?php if($app->partecipante):?> · 👤 <?php echo esc_html($app->partecipante);?><?php endif;?>
            </div>
            <?php if($app->note):?><div style="color:var(--msslu-text-muted);font-size:11px;margin-top:4px"><?php echo esc_html($app->note);?></div><?php endif;?>
        </div>
        <?php if($can_edit):?>
        <button class="mssg-btn mssg-btn--danger mssgc-app-delete" data-id="<?php echo(int)$app->id;?>" style="padding:3px 8px;font-size:11px">✕</button>
        <?php endif;?>
    </div>
    <?php endforeach; endif;?>
    </div>

    <?php if($can_edit):?>
    <div class="mssgp-form-section-label" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted);margin-bottom:10px">Nuovo appuntamento</div>
    <div class="mssg-form-grid" style="margin-bottom:14px">
        <div class="mssg-field" style="grid-column:1/-1">
            <label class="mssg-field-label">Oggetto appuntamento</label>
            <input type="text" name="app_titolo" placeholder="Presentazione preventivo, Sopralluogo, Consegna lavori…">
        </div>
        <div class="mssg-field">
            <label class="mssg-field-label">Data e ora</label>
            <input type="datetime-local" name="app_data_ora">
        </div>
        <div class="mssg-field">
            <label class="mssg-field-label">Durata (minuti)</label>
            <input type="number" name="app_durata" value="60" min="15" step="15">
        </div>
        <div class="mssg-field">
            <label class="mssg-field-label">Partecipante (cliente / collaboratore)</label>
            <select name="app_user_id">
                <option value="0">— Seleziona —</option>
                <?php
                /* Prima il cliente del cantiere */
                if (!empty($cantiere->cliente_id)) {
                    $cl = get_userdata($cantiere->cliente_id);
                    if ($cl) echo '<option value="'.$cl->ID.'">★ '.esc_html($cl->display_name).' (cliente)</option>';
                }
                foreach ($assegnati as $col):
                    if ((int)$col->user_id === (int)($cantiere->cliente_id??0)) continue; ?>
                <option value="<?php echo (int)$col->user_id;?>"><?php echo esc_html($col->display_name);?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mssg-field">
            <label class="mssg-field-label">Luogo</label>
            <input type="text" name="app_luogo" value="<?php echo esc_attr(mssgc_get_cantiere($cantiere_id)->indirizzo??'');?>">
        </div>
        <div class="mssg-field" style="grid-column:1/-1">
            <label class="mssg-field-label">Note per il partecipante</label>
            <textarea name="app_note" rows="2" placeholder="Portare documenti, Vedere preventivo n.…"></textarea>
        </div>
    </div>
    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--msslu-text-muted);margin-bottom:12px">
        <input type="checkbox" name="app_notifica"> Invia email di conferma appuntamento al partecipante
    </label>
    <button class="mssg-btn mssg-btn--primary" id="mssgc-salva-appuntamento">📅 Fissa appuntamento</button>
    <?php endif;?>
    <?php echo ob_get_clean();
}
