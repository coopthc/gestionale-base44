<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function mssgcs_render_storage_section( $user ) {
    if ( ! function_exists('mssgcs_get_settings') ) {
        return '<p style="color:#ef4444;padding:16px">Errore: cloud-storage.php non caricato.</p>';
    }
    $cfg   = mssgcs_get_settings();
    $nonce = wp_create_nonce('mssg_nonce');
    $ajax  = admin_url('admin-ajax.php');
    $mode  = esc_attr( $cfg['mode'] );
    $prov  = esc_attr( $cfg['provider'] );
    $quota = esc_attr( $cfg['quota_gb'] );
    $gd_json   = esc_textarea( $cfg['gdrive']['service_account_json'] );
    $gd_folder = esc_attr( $cfg['gdrive']['folder_id'] );
    $s3 = $cfg['s3'];

    ob_start();
?>
<div class="mssg-section" style="max-width:820px">
    <h2 class="msslu-section-title">Storage &amp; Cloud</h2>

    <!-- Barra spazio hosting -->
    <div id="mssgcs-spazio-wrap" style="padding:16px 18px;background:var(--msslu-box-bg);border:1px solid var(--msslu-box-border);border-radius:12px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="font-size:13px;font-weight:600;color:var(--msslu-text)">Spazio hosting (cartella gestionale)</span>
            <button onclick="mssgcsLoadSpazio()" style="background:none;border:none;color:var(--msslu-text-muted);cursor:pointer;font-size:12px">Aggiorna</button>
        </div>
        <div style="height:10px;background:var(--msslu-box-border);border-radius:5px;overflow:hidden;margin-bottom:6px">
            <div id="mssgcs-barra" style="height:100%;width:0%;background:#22c55e;border-radius:5px;transition:width .5s ease"></div>
        </div>
        <div id="mssgcs-spazio-label" style="font-size:12px;color:var(--msslu-text-muted)">Caricamento...</div>
        <div id="mssgcs-spazio-warning" style="display:none;margin-top:8px;padding:8px 12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:7px;font-size:12px;color:#ef4444">
            Spazio oltre il 90% &mdash; considera di spostare i file sul cloud.
        </div>
    </div>

    <!-- Modalita storage -->
    <div style="padding:16px 18px;background:var(--msslu-box-bg);border:1px solid var(--msslu-box-border);border-radius:12px;margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:var(--msslu-text);margin-bottom:14px">Dove salvare i nuovi file</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
            <?php
            $modes = array('local' => 'Solo hosting', 'cloud' => 'Solo cloud', 'both' => 'Entrambi');
            foreach ($modes as $val => $lbl) :
                $checked = $mode === $val ? 'checked' : '';
                $sel = $mode === $val ? 'background:rgba(233,30,140,.15);border-color:#e91e8c;color:#e91e8c' : '';
            ?>
            <label style="display:flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid var(--msslu-box-border);border-radius:8px;cursor:pointer;font-size:12px;<?php echo $sel; ?>">
                <input type="radio" name="mssgcs_mode" value="<?php echo esc_attr($val); ?>" <?php echo $checked; ?> style="accent-color:#e91e8c">
                <?php echo esc_html($lbl); ?>
            </label>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--msslu-text-muted)">
            <label>Quota hosting stimata (GB):</label>
            <input type="number" id="mssgcs-quota" value="<?php echo $quota; ?>" min="1" step="0.5"
                style="width:80px;padding:4px 8px;font-size:12px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:5px;color:var(--msslu-text)">
        </div>
    </div>

    <!-- Provider cloud -->
    <div style="padding:16px 18px;background:var(--msslu-box-bg);border:1px solid var(--msslu-box-border);border-radius:12px;margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:var(--msslu-text);margin-bottom:14px">Provider cloud</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
            <?php
            $provs = array('' => 'Nessuno', 'gdrive' => 'Google Drive', 'r2' => 'Cloudflare R2', 's3' => 'Amazon S3');
            foreach ($provs as $val => $lbl) :
                $checked = $prov === $val ? 'checked' : '';
                $sel = $prov === $val ? 'background:rgba(233,30,140,.12);border-color:#e91e8c;color:#e91e8c' : '';
            ?>
            <label style="display:flex;align-items:center;gap:6px;padding:7px 12px;border:1px solid var(--msslu-box-border);border-radius:7px;cursor:pointer;font-size:12px;<?php echo $sel; ?>">
                <input type="radio" name="mssgcs_provider" value="<?php echo esc_attr($val); ?>" <?php echo $checked; ?> style="accent-color:#e91e8c">
                <?php echo esc_html($lbl); ?>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- Sezione Google Drive -->
        <div id="mssgcs-cfg-gdrive" <?php echo $prov !== 'gdrive' ? 'style="display:none"' : ''; ?>>
            <details style="margin-bottom:12px">
                <summary style="cursor:pointer;padding:8px 12px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);border-radius:7px;font-size:12px;color:rgba(147,197,253,.9)">
                    Come configurare Google Drive &mdash; guida passo per passo
                </summary>
                <div style="padding:12px;background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.15);border-radius:0 0 8px 8px;font-size:11px;color:var(--msslu-text-muted);line-height:1.8">
                    <b>1.</b> Vai su <a href="https://console.cloud.google.com" target="_blank" style="color:rgba(147,197,253,.9)">console.cloud.google.com</a> &rarr; crea un nuovo progetto<br>
                    <b>2.</b> Menu &rarr; <b>API e servizi &rarr; Libreria</b> &rarr; cerca "Google Drive API" &rarr; <b>Abilita</b><br>
                    <b>3.</b> Menu &rarr; <b>API e servizi &rarr; Credenziali</b> &rarr; "Crea credenziali" &rarr; <b>Account di servizio</b> &rarr; dai un nome &rarr; Crea<br>
                    <b>4.</b> Clicca sull'account &rarr; tab <b>Chiavi</b> &rarr; "Aggiungi chiave" &rarr; "Crea nuova chiave" &rarr; <b>JSON</b> &rarr; Scarica<br>
                    <b>5.</b> Apri il JSON con un editor &rarr; <b>copia tutto il contenuto</b> e incollalo nel campo qui sotto<br>
                    <b>6.</b> Su <a href="https://drive.google.com" target="_blank" style="color:rgba(147,197,253,.9)">Google Drive</a> crea una cartella &rarr; tasto destro &rarr; <b>Condividi</b> &rarr; incolla l'email del campo "client_email" del JSON &rarr; ruolo <b>Editor</b><br>
                    <b>7.</b> Apri la cartella &rarr; dall'URL copia l'ID: drive.google.com/drive/folders/<b>QUESTO_ID</b><br>
                    <br><b>Costo:</b> gratis fino a 15 GB, poi 2.99 euro/mese per 100 GB
                </div>
            </details>
            <div style="margin-bottom:10px">
                <label style="font-size:11px;color:var(--msslu-text-muted);display:block;margin-bottom:4px">Service Account JSON</label>
                <textarea id="mssgcs-gdrive-json" rows="4" placeholder='{"type":"service_account","client_email":"...","private_key":"..."}'
                    style="width:100%;font-size:11px;padding:8px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:6px;color:var(--msslu-text);resize:vertical;box-sizing:border-box;font-family:monospace"><?php echo $gd_json; ?></textarea>
            </div>
            <div>
                <label style="font-size:11px;color:var(--msslu-text-muted);display:block;margin-bottom:4px">ID cartella Drive</label>
                <input type="text" id="mssgcs-gdrive-folder" value="<?php echo $gd_folder; ?>" placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs"
                    style="width:100%;font-size:12px;padding:7px 10px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:6px;color:var(--msslu-text);box-sizing:border-box">
            </div>
        </div>

        <!-- Sezione S3 / R2 -->
        <div id="mssgcs-cfg-s3" <?php echo !in_array($prov, array('r2','s3')) ? 'style="display:none"' : ''; ?>>
            <details id="guida-r2-det" style="margin-bottom:8px;<?php echo $prov === 's3' ? 'display:none' : ''; ?>">
                <summary style="cursor:pointer;padding:8px 12px;background:rgba(251,146,60,.1);border:1px solid rgba(251,146,60,.25);border-radius:7px;font-size:12px;color:rgba(253,186,116,.9)">
                    Come configurare Cloudflare R2 &mdash; guida passo per passo
                </summary>
                <div style="padding:12px;background:rgba(251,146,60,.05);border:1px solid rgba(251,146,60,.15);border-radius:0 0 8px 8px;font-size:11px;color:var(--msslu-text-muted);line-height:1.8">
                    <b>1.</b> Crea account gratis su <a href="https://cloudflare.com" target="_blank" style="color:rgba(253,186,116,.9)">cloudflare.com</a><br>
                    <b>2.</b> Dashboard &rarr; <b>R2 Object Storage</b> &rarr; <b>Crea bucket</b> &rarr; nome (es. mssg-media) &rarr; Automatic &rarr; Crea<br>
                    <b>3.</b> Torna a R2 &rarr; <b>Gestisci token R2 API</b> &rarr; "Crea token API"<br>
                    <b>4.</b> Permessi: <b>Oggetto: lettura e scrittura</b> &rarr; seleziona il bucket &rarr; Crea token<br>
                    <b>5.</b> Copia subito <b>Access Key ID</b> e <b>Secret Access Key</b> &mdash; non le rivedrai<br>
                    <b>6.</b> Endpoint: nel bucket &rarr; "Impostazioni" &rarr; copia URL S3<br>
                    <b>7.</b> URL pubblico: nel bucket &rarr; "Dominio R2.dev pubblico" &rarr; Abilita &rarr; copia URL<br>
                    <b>8.</b> Regione: lascia <b>auto</b><br>
                    <br><b>Costo:</b> gratis fino a 10 GB/mese, poi $0.015/GB. Zero costi di scaricamento.
                </div>
            </details>
            <details id="guida-s3-det" style="margin-bottom:8px;<?php echo $prov === 'r2' ? 'display:none' : ''; ?>">
                <summary style="cursor:pointer;padding:8px 12px;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.25);border-radius:7px;font-size:12px;color:rgba(253,224,71,.9)">
                    Come configurare Amazon S3 &mdash; guida passo per passo
                </summary>
                <div style="padding:12px;background:rgba(251,191,36,.05);border:1px solid rgba(251,191,36,.15);border-radius:0 0 8px 8px;font-size:11px;color:var(--msslu-text-muted);line-height:1.8">
                    <b>1.</b> Crea account su <a href="https://aws.amazon.com" target="_blank" style="color:rgba(253,224,71,.9)">aws.amazon.com</a><br>
                    <b>2.</b> Console AWS &rarr; <b>S3</b> &rarr; "Crea bucket" &rarr; nome univoco &rarr; regione eu-central-1 &rarr; Crea<br>
                    <b>3.</b> Console AWS &rarr; <b>IAM</b> &rarr; "Utenti" &rarr; "Crea utente" &rarr; policy: AmazonS3FullAccess &rarr; Crea<br>
                    <b>4.</b> Clicca sull'utente &rarr; tab <b>Credenziali di sicurezza</b> &rarr; "Crea chiave di accesso" &rarr; Crea<br>
                    <b>5.</b> Copia subito <b>Access Key ID</b> e <b>Secret Access Key</b><br>
                    <b>6.</b> Endpoint: https://s3.eu-central-1.amazonaws.com<br>
                    <br><b>Costo:</b> ~$0.023/GB/mese + $0.09/GB di scaricamento.
                </div>
            </details>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <?php
                $s3fields = array(
                    'mssgcs-s3-endpoint'   => array('Endpoint URL',       'https://xxx.r2.cloudflarestorage.com', $s3['endpoint'],    false),
                    'mssgcs-s3-access-key' => array('Access Key ID',      'AKIAIOSFODNN7EXAMPLE',                 $s3['access_key'],  false),
                    'mssgcs-s3-secret-key' => array('Secret Access Key',  '(nascosto)',                           $s3['secret_key'],  true),
                    'mssgcs-s3-bucket'     => array('Nome bucket',        'mssg-media',                           $s3['bucket'],      false),
                    'mssgcs-s3-region'     => array('Regione',            'auto',                                 $s3['region'],      false),
                    'mssgcs-s3-public-url' => array('URL pubblico base',  'https://pub-xxx.r2.dev',               $s3['public_url'],  false),
                );
                foreach ($s3fields as $fid => $f) :
                    $ftype = $f[3] ? 'password' : 'text';
                ?>
                <div>
                    <label style="font-size:11px;color:var(--msslu-text-muted);display:block;margin-bottom:3px"><?php echo esc_html($f[0]); ?></label>
                    <input type="<?php echo $ftype; ?>" id="<?php echo esc_attr($fid); ?>"
                        value="<?php echo esc_attr($f[2]); ?>"
                        placeholder="<?php echo esc_attr($f[1]); ?>"
                        style="width:100%;font-size:11px;padding:6px 8px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:5px;color:var(--msslu-text);box-sizing:border-box;font-family:monospace">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Bottoni -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
        <button id="mssgcs-salva" class="mssg-btn mssg-btn--primary" style="font-size:12px">Salva impostazioni</button>
        <button id="mssgcs-test" class="mssg-btn mssg-btn--ghost" style="font-size:12px">Testa connessione cloud</button>
        <div id="mssgcs-notice" style="display:none;align-self:center;font-size:12px;padding:5px 10px;border-radius:6px"></div>
    </div>

    <!-- Sposta su cloud -->
    <div style="padding:16px 18px;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.2);border-radius:12px">
        <div style="font-size:13px;font-weight:700;color:#818cf8;margin-bottom:8px">Sposta file su cloud</div>
        <div style="font-size:12px;color:var(--msslu-text-muted);margin-bottom:12px">
            Carica i file presenti sull'hosting sul cloud e libera spazio.<br>
            <strong style="color:#818cf8">I file vengono eliminati dall'hosting dopo il caricamento.</strong>
        </div>
        <div id="mssgcs-sposta-result" style="display:none;margin-bottom:10px;font-size:12px;padding:8px 12px;border-radius:7px"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button id="mssgcs-sposta-10" class="mssg-btn mssg-btn--ghost" style="font-size:12px;border-color:#818cf8;color:#818cf8">Sposta i prossimi 10</button>
            <button id="mssgcs-sposta-tutti" class="mssg-btn" style="font-size:12px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.4);color:#818cf8">Sposta TUTTI</button>
            <div id="mssgcs-sposta-progress" style="display:none;font-size:12px;color:var(--msslu-text-muted);align-self:center"></div>
        </div>
    </div>
</div>

<script>
var _mssgcsAjax  = '<?php echo esc_js($ajax); ?>';
var _mssgcsNonce = '<?php echo esc_js($nonce); ?>';

function mssgcsLoadSpazio() {
    jQuery.post(_mssgcsAjax, {action:'mssgcs_spazio', nonce:_mssgcsNonce}, function(r) {
        if (!r || !r.success) return;
        var d = r.data, pct = d.pct;
        var col = pct >= 90 ? '#ef4444' : pct >= 70 ? '#f59e0b' : '#22c55e';
        jQuery('#mssgcs-barra').css({width: pct + '%', background: col});
        jQuery('#mssgcs-spazio-label').text(d.usato_gb + ' GB usati di ' + d.quota_gb + ' GB (' + pct + '%) - ' + d.file_count + ' file');
        if (d.warning) jQuery('#mssgcs-spazio-warning').show(); else jQuery('#mssgcs-spazio-warning').hide();
    }).fail(function() {
        jQuery('#mssgcs-spazio-label').text('Errore nel caricamento dello spazio utilizzato.');
    });
}
mssgcsLoadSpazio();

jQuery(function($) {
    /* Switch provider */
    $('input[name="mssgcs_provider"]').on('change', function() {
        $('#mssgcs-cfg-gdrive, #mssgcs-cfg-s3').hide();
        var v = $(this).val();
        if (v === 'gdrive') $('#mssgcs-cfg-gdrive').show();
        if (v === 'r2' || v === 's3') {
            $('#mssgcs-cfg-s3').show();
            if (v === 'r2') { $('#guida-r2-det').show(); $('#guida-s3-det').hide(); }
            else            { $('#guida-r2-det').hide(); $('#guida-s3-det').show(); }
        }
        $('input[name="mssgcs_provider"]').each(function() {
            var sel = $(this).is(':checked');
            $(this).closest('label').css(sel ? {background:'rgba(233,30,140,.12)',borderColor:'#e91e8c',color:'#e91e8c'} : {background:'',borderColor:'',color:''});
        });
    });
    $('input[name="mssgcs_mode"]').on('change', function() {
        $('input[name="mssgcs_mode"]').each(function() {
            var sel = $(this).is(':checked');
            $(this).closest('label').css(sel ? {background:'rgba(233,30,140,.15)',borderColor:'#e91e8c',color:'#e91e8c'} : {background:'',borderColor:'',color:''});
        });
    });

    function showNotice(msg, ok) {
        $('#mssgcs-notice').show().css({
            background: ok ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)',
            border: '1px solid ' + (ok ? 'rgba(34,197,94,.3)' : 'rgba(239,68,68,.3)'),
            color: ok ? '#22c55e' : '#ef4444'
        }).text(msg);
    }

    $('#mssgcs-salva').on('click', function() {
        var b = $(this); b.prop('disabled', true).text('...');
        $.post(_mssgcsAjax, {
            action: 'mssgcs_save_settings', nonce: _mssgcsNonce,
            mssg_storage_mode:     $('input[name="mssgcs_mode"]:checked').val(),
            mssg_cloud_provider:   $('input[name="mssgcs_provider"]:checked').val(),
            mssg_hosting_quota_gb: $('#mssgcs-quota').val(),
            mssg_gdrive_sa_json:   $('#mssgcs-gdrive-json').val(),
            mssg_gdrive_folder_id: $('#mssgcs-gdrive-folder').val(),
            mssg_s3_endpoint:      $('#mssgcs-s3-endpoint').val(),
            mssg_s3_access_key:    $('#mssgcs-s3-access-key').val(),
            mssg_s3_secret_key:    $('#mssgcs-s3-secret-key').val(),
            mssg_s3_bucket:        $('#mssgcs-s3-bucket').val(),
            mssg_s3_region:        $('#mssgcs-s3-region').val(),
            mssg_s3_public_url:    $('#mssgcs-s3-public-url').val()
        }, function(r) {
            b.prop('disabled', false).text('Salva impostazioni');
            showNotice(r && r.data && r.data.msg ? r.data.msg : (r && r.success ? 'Salvato.' : 'Errore.'), r && r.success);
        }).fail(function(xhr) {
            b.prop('disabled', false).text('Salva impostazioni');
            showNotice('Errore di connessione' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '. Riprova.', false);
        });
    });

    $('#mssgcs-test').on('click', function() {
        var b = $(this); b.prop('disabled', true).text('...');
        $.post(_mssgcsAjax, {action: 'mssgcs_test_cloud', nonce: _mssgcsNonce}, function(r) {
            b.prop('disabled', false).text('Testa connessione cloud');
            showNotice(r && r.data && r.data.msg ? r.data.msg : (r && r.success ? 'OK.' : 'Errore.'), r && r.success);
        }).fail(function(xhr) {
            b.prop('disabled', false).text('Testa connessione cloud');
            showNotice('Errore di connessione' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '. Riprova.', false);
        });
    });

    function spostaCloud(limite, btn, loop) {
        btn.prop('disabled', true);
        $('#mssgcs-sposta-progress').show().text('Spostamento in corso...');
        $.post(_mssgcsAjax, {action: 'mssgcs_sposta_su_cloud', nonce: _mssgcsNonce, limite: limite}, function(r) {
            if (!r || !r.success) {
                btn.prop('disabled', false);
                $('#mssgcs-sposta-progress').hide();
                $('#mssgcs-sposta-result').show().css({background:'rgba(239,68,68,.1)',border:'1px solid rgba(239,68,68,.3)',color:'#ef4444'}).text(r && r.data && r.data.msg ? r.data.msg : 'Errore.');
                return;
            }
            var d = r.data;
            $('#mssgcs-sposta-result').show().css({background:'rgba(34,197,94,.1)',border:'1px solid rgba(34,197,94,.3)',color:'#22c55e'}).text(d.msg);
            mssgcsLoadSpazio();
            if (loop && d.restanti > 0) {
                $('#mssgcs-sposta-progress').text('Restano ' + d.restanti + ' file...');
                setTimeout(function() { spostaCloud(limite, btn, true); }, 500);
            } else {
                btn.prop('disabled', false);
                $('#mssgcs-sposta-progress').hide();
            }
        }).fail(function(xhr) {
            btn.prop('disabled', false);
            $('#mssgcs-sposta-progress').hide();
            $('#mssgcs-sposta-result').show().css({background:'rgba(239,68,68,.1)',border:'1px solid rgba(239,68,68,.3)',color:'#ef4444'})
                .text('Errore di connessione' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '. Riprova.');
        });
    }

    $('#mssgcs-sposta-10').on('click', function() { spostaCloud(10, $(this), false); });
    $('#mssgcs-sposta-tutti').on('click', function() {
        if (!confirm('Spostare TUTTI i file sul cloud?\nVerranno eliminati dall\'hosting dopo il caricamento.')) return;
        spostaCloud(20, $(this), true);
    });
});
</script>
<?php
    echo ob_get_clean();
}
