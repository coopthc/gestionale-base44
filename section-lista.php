<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function mssgc_render_lista($user){
    $user_id=$user->ID;
    $can_new=mssg_user_can($user_id,'create_cantieri');
    /* Legge stato da POST (AJAX) o GET (caricamento diretto) */
    $filter=sanitize_key($_POST['stato']??$_GET['mssgc_stato']??'tutti');
    $stati=array('tutti'=>'Tutti','attivo'=>'Attivi','bozza'=>'Bozze','sospeso'=>'Sospesi','completato'=>'Completati','chiuso'=>'Chiusi','archiviato'=>'Archivio');
    $filters=$filter!=='tutti'?array('stato'=>$filter):array();
    $cantieri=mssgc_get_cantieri($user_id,$filters);
    $is_admin=mssg_user_can($user_id,'view_all_cantieri');
    ?>
    <div class="mssg-section mssgc-section" id="mssgc-main">
        <div id="mssgc-panel" style="display:none"></div>
        <div class="mssgc-list-area">

            <div class="mssg-toolbar" style="flex-wrap:wrap;gap:8px">
                <div class="mssg-toolbar-left" style="flex:1;min-width:0">
                    <h2 class="msslu-section-title" style="margin:0;white-space:nowrap">Cantieri</h2>
                    <span style="font-size:12px;color:var(--msslu-text-muted);margin-left:6px"><?php echo count($cantieri); ?></span>
                </div>
                <!-- Ricerca live -->
                <div class="mssg-toolbar-search" style="flex:1;max-width:240px">
                    <input type="text" id="mssgc-search" placeholder="Cerca cantiere…"
                           style="width:100%;padding:6px 10px 6px 28px;font-size:12px;border-radius:20px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);color:var(--msslu-text)">
                </div>
                <div class="mssg-toolbar-right" style="gap:6px">
                    <?php if($is_admin):?>
                    <button class="mssg-btn mssg-btn--ghost mssg-export-btn" id="mssgc-export-csv" title="Esporta CSV">📥 CSV</button>
                    <?php endif;?>
                    <?php if($can_new):?><button class="mssg-btn mssg-btn--primary" id="mssgc-btn-nuovo">+ Nuovo</button><?php endif;?>
                </div>
            </div>

            <!-- Filtri stato -->
            <div class="mssgc-filtri">
                <?php foreach($stati as $k=>$l):?>
                <button class="mssgc-filtro-btn <?php echo $filter===$k?'active':'';?>" data-stato="<?php echo esc_attr($k);?>"><?php echo esc_html($l);?></button>
                <?php endforeach;?>
            </div>

            <?php if(empty($cantieri)):?>
            <div class="mssg-empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px;height:40px;opacity:.3;display:block;margin:0 auto 12px"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 22V12h6v10M9 7h1m4 0h1"/></svg>
                <p>Nessun cantiere<?php echo $filter!=='tutti'?' — '.esc_html($stati[$filter]??$filter):'';?>.</p>
                <?php if($can_new):?><button class="mssg-btn mssg-btn--primary" id="mssgc-btn-nuovo-empty">+ Crea il primo</button><?php endif;?>
            </div>
            <?php else:?>
            <div class="mssg-table-wrap">
                <table class="mssg-table mssgc-table" id="mssgc-table">
                    <thead><tr>
                        <?php if($is_admin):?><th style="width:32px"></th><?php endif;?>
                        <th>Cantiere</th><th>Cliente</th><th>Stato</th><th>Inizio</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($cantieri as $c):?>
                    <tr class="mssgc-row <?php echo $c->pinned?'mssgc-row-pinned':'';?>"
                        data-id="<?php echo(int)$c->id;?>"
                        data-nome="<?php echo esc_attr($c->nome);?>"
                        data-search="<?php echo esc_attr(strtolower($c->nome.' '.$c->codice.' '.$c->indirizzo.' '.$c->citta));?>">
                        <?php if($is_admin):?>
                        <td>
                            <button class="mssgc-pin-btn <?php echo $c->pinned?'pinned':'';?>"
                                    data-id="<?php echo(int)$c->id;?>"
                                    data-pinned="<?php echo(int)$c->pinned;?>"
                                    title="<?php echo $c->pinned?'Rimuovi da in evidenza':'Metti in evidenza';?>">
                                📌
                            </button>
                        </td>
                        <?php endif;?>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <?php if($c->pinned):?><span style="font-size:10px;color:#f97316;font-weight:600">URGENTE</span><?php endif;?>
                                <div>
                                    <strong class="mssgc-open-cantiere" data-id="<?php echo(int)$c->id;?>" style="cursor:pointer">
                                        <?php echo esc_html($c->nome);?>
                                    </strong>
                                    <?php if($c->codice):?><span style="font-size:10px;color:var(--msslu-text-muted);margin-left:6px"><?php echo esc_html($c->codice);?></span><?php endif;?>
                                    <?php if($c->indirizzo):?><div style="font-size:11px;color:var(--msslu-text-muted)"><?php echo esc_html($c->citta?:$c->indirizzo);?></div><?php endif;?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo esc_html($c->cliente_nome?:'—');?></td>
                        <td>
                            <?php $stati_map=array('bozza'=>'Bozza','attivo'=>'Attivo','sospeso'=>'Sospeso','completato'=>'Completato','chiuso'=>'Chiuso','archiviato'=>'Archiviato');?>
                            <span class="mssg-status mssg-status--<?php echo esc_attr($c->stato);?>"><?php echo $stati_map[$c->stato]??$c->stato;?></span>
                        </td>
                        <td style="white-space:nowrap"><?php echo $c->data_inizio?date_i18n('d/m/Y',strtotime($c->data_inizio)):'—';?></td>
                        <td>
                            <div style="display:flex;gap:4px;align-items:center">
                                <?php if (!mssg_user_has_role($user_id,'mssg_cliente')): ?>
                                <button class="mssg-btn mssg-btn--ghost mssgc-open-cantiere" data-id="<?php echo(int)$c->id;?>" style="padding:4px 8px;font-size:12px">Apri</button>
                                <?php endif; ?>
                                <?php if($is_admin):?>
                                <button class="mssgc-action-btn" data-id="<?php echo(int)$c->id;?>" data-nome="<?php echo esc_attr($c->nome);?>" data-stato="<?php echo esc_attr($c->stato);?>" title="Azioni" style="background:none;border:1px solid var(--msslu-box-border);border-radius:6px;padding:4px 6px;cursor:pointer;color:var(--msslu-text-muted);font-size:14px">⋯</button>
                                <?php endif;?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>

            <!-- Menu azioni dropdown (appare vicino al bottone ⋯) -->
            <div id="mssgc-action-menu" style="display:none;position:absolute;background:var(--msslu-box-bg,#1a1a2e);border:1px solid var(--msslu-box-border);border-radius:8px;padding:6px 0;min-width:160px;z-index:100;box-shadow:0 4px 20px rgba(0,0,0,.3)">
                <button class="mssgc-menu-item" data-action="archivia" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:none;border:none;width:100%;text-align:left;cursor:pointer;font-size:13px;color:var(--msslu-text)">📦 Archivia cantiere</button>
                <button class="mssgc-menu-item" data-action="elimina" style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:none;border:none;width:100%;text-align:left;cursor:pointer;font-size:13px;color:#ef4444">🗑 Elimina cantiere</button>
            </div>

            <?php endif;?>
        </div>
    </div>
    <?php
}
