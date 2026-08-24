<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function mssgc_render_media_tab($cantiere_id,$user_id){
    $can_upload = mssg_user_can($user_id,'upload_media');
    $can_manage = mssg_user_can($user_id,'manage_cantieri');
    $media_list = mssgc_get_media($cantiere_id);
    $foto  = array_filter($media_list, function($m){ return $m->tipo === 'foto'; });
    $video = array_filter($media_list, function($m){ return $m->tipo === 'video'; });
    $docs  = array_filter($media_list, function($m){ return $m->tipo === 'documento' || $m->tipo === 'altro'; });
    ob_start();?>

    <?php if($can_upload):?>
    <div class="mssgc-upload-zone" id="mssgc-upload-zone" data-cantiere-id="<?php echo(int)$cantiere_id;?>">

        <!-- Bottoni di selezione file -->
        <div class="mssgc-upload-buttons">
            <label class="mssg-btn mssg-btn--primary mssgc-upload-label" style="cursor:pointer">
                📷 Scatta foto
                <input type="file" accept="image/*" capture="environment" class="mssgc-file-input" data-tipo="foto" style="display:none">
            </label>
            <label class="mssg-btn mssg-btn--primary mssgc-upload-label" style="cursor:pointer">
                🎥 Registra video
                <input type="file" accept="video/*" capture="environment" class="mssgc-file-input" data-tipo="video" style="display:none">
            </label>
            <label class="mssg-btn mssg-btn--ghost mssgc-upload-label" style="cursor:pointer">
                📎 Carica file / Galleria
                <input type="file" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" class="mssgc-file-input" data-tipo="auto" multiple style="display:none">
            </label>
        </div>

        <!-- Pannello pre-upload (appare dopo selezione file) -->
        <div id="mssgc-preupload-panel" style="display:none;margin-top:14px;background:var(--msslu-box-bg);border:1px solid var(--msslu-box-border);border-radius:10px;padding:14px;animation:mssgFadeIn .2s ease">

            <!-- Anteprime file selezionati -->
            <div id="mssgc-preupload-previews" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px"></div>

            <!-- Controlli -->
            <div style="display:flex;flex-direction:column;gap:10px">

                <!-- Categoria -->
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span style="font-size:12px;color:var(--msslu-text-muted);width:80px;flex-shrink:0">Categoria</span>
                    <select id="mssgc-upload-categoria" style="flex:1;font-size:12px;padding:5px 8px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:6px;color:var(--msslu-text)">
                        <option value="cantiere">Cantiere (generico)</option>
                        <option value="contratto">Contratto</option>
                        <option value="planimetria">Planimetria</option>
                        <option value="permesso">Permesso/Autorizzazione</option>
                        <option value="sicurezza">Sicurezza</option>
                        <option value="altro">Altro</option>
                    </select>
                </div>

                <!-- Didascalia -->
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span style="font-size:12px;color:var(--msslu-text-muted);width:80px;flex-shrink:0">Didascalia</span>
                    <input type="text" id="mssgc-upload-didascalia" placeholder="Descrizione opzionale…"
                        style="flex:1;font-size:12px;padding:5px 10px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:6px;color:var(--msslu-text)">
                </div>

                <!-- Visibile al cliente -->
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-size:12px;color:var(--msslu-text-muted);width:80px;flex-shrink:0">Cliente</span>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--msslu-text);cursor:pointer">
                        <input type="checkbox" id="mssgc-upload-visibile-cliente" style="accent-color:var(--msslu-accent);width:15px;height:15px">
                        Visibile al cliente
                    </label>
                </div>

                <!-- Qualità (slider) — sempre visibile, ha effetto solo su immagini -->
                <div id="mssgc-qualita-row" style="display:flex;align-items:center;gap:8px">
                    <span style="font-size:12px;color:var(--msslu-text-muted);width:80px;flex-shrink:0">Qualità</span>
                    <input type="range" id="mssgc-upload-qualita" min="30" max="100" value="82"
                        style="flex:1;accent-color:var(--msslu-accent)">
                    <span id="mssgc-qualita-label" style="font-size:11px;color:var(--msslu-accent);min-width:90px;text-align:right;font-weight:600">Web (82%)</span>
                </div>
                <div style="font-size:10px;color:var(--msslu-text-muted);margin-left:88px;margin-top:-4px;opacity:.7">Solo immagini — video e documenti non vengono modificati</div>
            </div>

            <!-- Barra progresso -->
            <div id="mssgc-upload-progress" style="display:none;margin-top:12px">
                <div style="height:4px;background:var(--msslu-box-border);border-radius:2px;overflow:hidden">
                    <div id="mssgc-upload-bar" style="height:100%;background:var(--msslu-accent);width:0%;transition:width .3s"></div>
                </div>
                <div id="mssgc-upload-label" style="font-size:12px;color:var(--msslu-text-muted);margin-top:4px">Caricamento…</div>
            </div>

            <!-- Bottoni conferma/annulla -->
            <div style="display:flex;gap:8px;margin-top:14px">
                <button id="mssgc-preupload-confirm" class="mssg-btn mssg-btn--primary" style="flex:1">
                    ⬆ Carica
                </button>
                <button id="mssgc-preupload-cancel" class="mssg-btn mssg-btn--ghost" style="flex:0 0 auto">
                    Annulla
                </button>
            </div>
        </div>
    </div>
    <?php endif;?>

    <!-- CHAT DEL CANTIERE -->
    <?php echo mssgc_render_chat_tab($cantiere_id,$user_id);?>

    <!-- Lightbox overlay -->
    <div id="mssgc-lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:99999;padding:20px;box-sizing:border-box">
        <button id="mssgc-lightbox-close" style="position:absolute;top:14px;right:14px;background:none;border:none;color:#fff;font-size:24px;cursor:pointer">✕</button>
        <div id="mssgc-lightbox-content" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center"></div>
        <div id="mssgc-lightbox-caption" style="position:absolute;bottom:14px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.7);font-size:12px;max-width:80%;text-align:center"></div>
    </div>

    <?php if(!empty($foto)):
        $foto = array_values($foto);
        $tot_foto = count($foto);
        $foto_visibili = 8;
    ?>
    <div style="margin-top:20px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:6px">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted)">
                Foto (<?php echo $tot_foto;?>)
            </div>
            <?php if($can_upload):?>
            <div style="font-size:11px;color:var(--msslu-text-muted);display:flex;align-items:center;gap:10px">
                <span style="display:inline-flex;align-items:center;gap:3px">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:rgba(34,197,94,.8);border-radius:50%;font-size:11px">👁</span>
                    <span>visibile al cliente</span>
                </span>
                <span style="display:inline-flex;align-items:center;gap:3px">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:rgba(100,100,120,.5);border-radius:50%;font-size:11px">🚫</span>
                    <span>nascosto — tocca per cambiare</span>
                </span>
            </div>
            <?php endif;?>
        </div>
        <div class="mssgc-foto-grid">
            <?php foreach($foto as $i => $m):
                $did = trim($m->note ?? '');
                $is_lazy = $i >= $foto_visibili;
                $thumb = !empty($m->thumb_url) ? $m->thumb_url : $m->file_url;
            ?>
            <div class="mssgc-media-thumb<?php echo $is_lazy?' mssgc-foto-extra':''?>"
                 data-id="<?php echo(int)$m->id;?>"
                 data-url="<?php echo esc_url($m->file_url);?>"
                 data-tipo="foto"
                 data-nome="<?php echo esc_attr($m->nome);?>"
                 data-categoria="<?php echo esc_attr($m->categoria);?>"
                 data-data="<?php echo esc_attr(date_i18n('d/m/Y H:i',strtotime($m->created_at)));?>"
                 data-autore="<?php echo esc_attr($m->autore??'');?>"
                 data-didascalia="<?php echo esc_attr($did);?>"
                 <?php echo $is_lazy?'style="display:none"':''; ?>>
                <?php if($is_lazy):?>
                <img data-src="<?php echo esc_url($thumb);?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block">
                <?php else:?>
                <img src="<?php echo esc_url($thumb);?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
                <?php endif;?>

                <?php if($can_upload):?>
                <button type="button" class="mssgc-toggle-visibile" data-id="<?php echo(int)$m->id;?>" data-stato="<?php echo(int)$m->visibile_cliente;?>"
                        title="<?php echo $m->visibile_cliente?'Visibile al cliente — tocca per nascondere':'Nascosto al cliente — tocca per rendere visibile';?>"
                        style="position:absolute;top:5px;left:5px;background:<?php echo $m->visibile_cliente?'rgba(34,197,94,.85)':'rgba(40,40,60,.7)';?>;border:none;border-radius:50%;width:30px;height:30px;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:5;box-shadow:0 1px 4px rgba(0,0,0,.4);transition:background .2s">
                    <?php echo $m->visibile_cliente?'👁':'🚫';?>
                </button>
                <?php else: ?>
                <?php if($m->visibile_cliente):?>
                <span style="position:absolute;top:5px;left:5px;background:rgba(34,197,94,.85);border-radius:50%;width:22px;height:22px;font-size:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,.4)" title="Visibile al cliente">👁</span>
                <?php endif;?>
                <?php endif;?>

                <div class="mssgc-thumb-overlay">
                    <span class="mssgc-thumb-cat"><?php echo esc_html(ucfirst($m->categoria));?></span>
                </div>
                <?php if($did):?>
                <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,.75));padding:4px 6px;font-size:10px;color:#fff;line-height:1.2;pointer-events:none"><?php echo esc_html($did);?></div>
                <?php endif;?>
                <?php if($can_upload):?><button type="button" class="mssgc-media-delete" data-id="<?php echo(int)$m->id;?>">✕</button><?php endif;?>
            </div>
            <?php endforeach;?>
        </div>
        <?php if($tot_foto > $foto_visibili):?>
        <button id="mssgc-mostra-tutte-foto" style="margin-top:10px;background:none;border:1px solid var(--msslu-box-border);color:var(--msslu-text-muted);font-size:12px;padding:6px 14px;border-radius:6px;cursor:pointer;width:100%">
            Mostra tutte le foto (<?php echo $tot_foto - $foto_visibili;?> nascoste)
        </button>
        <?php endif;?>
    </div>
    <?php endif;?>

    <?php if(!empty($video)):?>
    <div style="margin-top:20px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted);margin-bottom:10px">Video (<?php echo count($video);?>)</div>
        <div class="mssgc-video-grid">
            <?php foreach($video as $m):?>
            <div class="mssgc-media-video-item" data-id="<?php echo(int)$m->id;?>" data-url="<?php echo esc_url($m->file_url);?>" data-tipo="video" data-nome="<?php echo esc_attr($m->nome);?>" data-categoria="<?php echo esc_attr($m->categoria);?>" data-data="<?php echo esc_attr(date_i18n('d/m/Y H:i',strtotime($m->created_at)));?>" data-autore="<?php echo esc_attr($m->autore??'');?>">
                <div class="mssgc-video-placeholder">
                    <span style="font-size:28px">▶</span>
                    <div style="font-size:11px;margin-top:4px;opacity:.7"><?php echo esc_html($m->nome);?></div>
                    <div style="font-size:10px;margin-top:2px;opacity:.5"><?php echo esc_html(ucfirst($m->categoria));?></div>
                </div>
                <?php if($can_upload):?><button type="button" class="mssgc-media-delete" data-id="<?php echo(int)$m->id;?>">✕</button><?php endif;?>
            </div>
            <?php endforeach;?>
        </div>
    </div>
    <?php endif;?>

    <?php if(!empty($docs)):?>
    <div style="margin-top:20px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--msslu-text-muted);margin-bottom:10px">Documenti (<?php echo count($docs);?>)</div>
        <!-- min-width forza lo scroll orizzontale su mobile invece di far
             schiacciare le colonne: senza, su schermi stretti il bottone
             "cambia visibilità" (icona piccola, nessun padding) e il link
             "apri file" finivano visivamente troppo vicini/sovrapposti,
             facendo capitare tap sul file invece che sul toggle. -->
        <div class="mssg-table-wrap">
            <table class="mssg-table" style="font-size:12px;min-width:520px">
                <thead><tr><th>Nome</th><th>Categoria</th><th>Didascalia</th><th>Dim.</th><th>Cliente</th><th></th></tr></thead>
                <tbody>
                <?php foreach($docs as $m):?>
                <tr>
                    <td><span class="mssgc-doc-open" data-url="<?php echo esc_url($m->file_url);?>" data-tipo="<?php echo esc_attr($m->mime_type);?>" data-nome="<?php echo esc_attr($m->nome);?>" style="cursor:pointer;color:var(--msslu-accent)"><?php echo $m->mime_type==='application/pdf'?'📄':'📎';?> <?php echo esc_html($m->nome);?></span></td>
                    <td><?php echo esc_html(ucfirst($m->categoria));?></td>
                    <td style="color:var(--msslu-text-muted);font-style:italic"><?php echo esc_html($m->note??'—');?></td>
                    <td><?php echo round($m->dimensione/1024,0);?> KB</td>
                    <td>
                        <?php if($can_upload):?>
                        <button type="button" class="mssgc-toggle-visibile" data-id="<?php echo(int)$m->id;?>" data-stato="<?php echo(int)$m->visibile_cliente;?>"
                                style="background:<?php echo $m->visibile_cliente?'rgba(34,197,94,.12)':'rgba(255,255,255,.06)';?>;border:1px solid <?php echo $m->visibile_cliente?'rgba(34,197,94,.35)':'var(--msslu-box-border)';?>;border-radius:6px;cursor:pointer;font-size:12px;padding:5px 10px;min-width:70px;white-space:nowrap" title="Tocca per cambiare la visibilità al cliente">
                            <?php echo $m->visibile_cliente?'<span style="color:#22c55e">✓ Visibile</span>':'<span style="color:var(--msslu-text-muted)">— Nascosto</span>';?>
                        </button>
                        <?php else:?>
                            <?php echo $m->visibile_cliente?'<span style="color:#22c55e">✓</span>':'—';?>
                        <?php endif;?>
                    </td>
                    <td><?php if($can_upload):?><button type="button" class="mssgc-media-delete mssg-btn mssg-btn--ghost" data-id="<?php echo(int)$m->id;?>" style="padding:2px 8px;font-size:11px">✕</button><?php endif;?></td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif;?>

    <?php if(empty($foto)&&empty($video)&&empty($docs)):?>
    <div class="mssg-empty-state" style="margin-top:16px"><p>Nessun media caricato.</p></div>
    <?php endif;?>

    <?php return ob_get_clean();
}
