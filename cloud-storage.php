<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════
   CLOUD STORAGE — MSS Gestionale
   Supporta: Google Drive (Service Account), Cloudflare R2, Amazon S3
══════════════════════════════════════════════════════════ */

/* ── Legge le impostazioni storage ── */
function mssgcs_get_settings() {
    return array(
        'mode'     => get_option('mssg_storage_mode',     'local'),   // local|cloud|both
        'provider' => get_option('mssg_cloud_provider',   ''),        // gdrive|r2|s3
        'quota_gb' => (float) get_option('mssg_hosting_quota_gb', 5), // quota hosting in GB
        'gdrive'   => array(
            'service_account_json' => get_option('mssg_gdrive_sa_json', ''),
            'folder_id'            => get_option('mssg_gdrive_folder_id', ''),
        ),
        's3' => array(
            'endpoint'   => get_option('mssg_s3_endpoint',   ''),
            'access_key' => get_option('mssg_s3_access_key', ''),
            'secret_key' => get_option('mssg_s3_secret_key', ''),
            'bucket'     => get_option('mssg_s3_bucket',     ''),
            'region'     => get_option('mssg_s3_region',     'auto'),
            'public_url' => get_option('mssg_s3_public_url', ''),
        ),
    );
}

/* ── Calcola spazio usato dalla cartella /mssg/ ── */
function mssgcs_spazio_usato() {
    $upload_dir = wp_upload_dir();
    $dir        = $upload_dir['basedir'] . '/mssg';
    if ( ! is_dir($dir) ) return array('usato_bytes' => 0, 'usato_gb' => 0, 'file_count' => 0);
    $bytes = 0; $count = 0;
    foreach ( new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    ) as $f ) {
        if ( $f->isFile() ) { $bytes += $f->getSize(); $count++; }
    }
    return array(
        'usato_bytes' => $bytes,
        'usato_gb'    => round($bytes / 1073741824, 2),
        'file_count'  => $count,
    );
}

/* ══════════════════════════════════════════════════════════
   UPLOAD SUL CLOUD — dispatcher per provider
══════════════════════════════════════════════════════════ */
function mssgcs_upload_cloud( $local_path, $file_name, $cantiere_id ) {
    $cfg = mssgcs_get_settings();
    if ( empty($cfg['provider']) ) return new WP_Error('no_provider', 'Nessun provider configurato.');

    /* Struttura cartella: /mssg/cantieri/{id}/ */
    $cloud_path = 'mssg/cantieri/' . (int)$cantiere_id . '/' . $file_name;

    switch ( $cfg['provider'] ) {
        case 'gdrive': return mssgcs_gdrive_upload($local_path, $cloud_path, $file_name, $cfg['gdrive']);
        case 'r2':
        case 's3':     return mssgcs_s3_upload($local_path, $cloud_path, $cfg['s3']);
    }
    return new WP_Error('unknown_provider', 'Provider non riconosciuto: ' . $cfg['provider']);
}

/* ══════════════════════════════════════════════════════════
   GOOGLE DRIVE — Service Account (JWT, nessun SDK)
══════════════════════════════════════════════════════════ */
function mssgcs_gdrive_get_token( $sa_json ) {
    $sa   = json_decode($sa_json, true);
    if ( empty($sa['private_key']) || empty($sa['client_email']) ) return new WP_Error('gdrive_cfg','Service Account JSON non valido.');
    $now  = time();
    $claim = array(
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    );
    $header  = base64_encode(json_encode(array('alg'=>'RS256','typ'=>'JWT')));
    $payload = base64_encode(json_encode($claim));
    $jwt_in  = $header . '.' . $payload;
    $sig     = '';
    if ( ! openssl_sign($jwt_in, $sig, $sa['private_key'], 'SHA256') ) {
        return new WP_Error('gdrive_jwt','Impossibile firmare il JWT (openssl).');
    }
    $jwt = $jwt_in . '.' . base64_encode($sig);
    $res = wp_remote_post('https://oauth2.googleapis.com/token', array(
        'body'    => http_build_query(array('grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt)),
        'headers' => array('Content-Type'=>'application/x-www-form-urlencoded'),
        'timeout' => 20,
    ));
    if ( is_wp_error($res) ) return $res;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['access_token'] ?? new WP_Error('gdrive_token','Token non ottenuto: ' . wp_remote_retrieve_body($res));
}

function mssgcs_gdrive_get_or_create_folder( $token, $name, $parent_id ) {
    /* Cerca cartella esistente */
    $q   = urlencode("name='{$name}' and mimeType='application/vnd.google-apps.folder' and '{$parent_id}' in parents and trashed=false");
    $res = wp_remote_get("https://www.googleapis.com/drive/v3/files?q={$q}&fields=files(id,name)", array(
        'headers' => array('Authorization'=>'Bearer '.$token), 'timeout'=>15));
    if ( !is_wp_error($res) ) {
        $data = json_decode(wp_remote_retrieve_body($res),true);
        if (!empty($data['files'][0]['id'])) return $data['files'][0]['id'];
    }
    /* Crea cartella */
    $res2 = wp_remote_post('https://www.googleapis.com/drive/v3/files', array(
        'headers' => array('Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'),
        'body'    => json_encode(array('name'=>$name,'mimeType'=>'application/vnd.google-apps.folder','parents'=>array($parent_id))),
        'timeout' => 15,
    ));
    $d2 = json_decode(wp_remote_retrieve_body($res2),true);
    return $d2['id'] ?? new WP_Error('gdrive_folder','Impossibile creare cartella: '.$name);
}

function mssgcs_gdrive_upload( $local_path, $cloud_path, $file_name, $cfg ) {
    if ( empty($cfg['service_account_json']) ) return new WP_Error('gdrive_no_cfg','Service Account JSON mancante.');
    $token = mssgcs_gdrive_get_token($cfg['service_account_json']);
    if ( is_wp_error($token) ) return $token;

    $root = $cfg['folder_id'] ?: 'root';
    /* Crea struttura /mssg/cantieri/{id}/ */
    $parts = explode('/', trim(dirname($cloud_path),'/'));
    $parent = $root;
    foreach ($parts as $part) {
        if (!$part) continue;
        $parent = mssgcs_gdrive_get_or_create_folder($token, $part, $parent);
        if (is_wp_error($parent)) return $parent;
    }

    /* Controlla se il file esiste già */
    $q = urlencode("name='{$file_name}' and '{$parent}' in parents and trashed=false");
    $existing = wp_remote_get("https://www.googleapis.com/drive/v3/files?q={$q}&fields=files(id,webContentLink)", array(
        'headers'=>array('Authorization'=>'Bearer '.$token),'timeout'=>15));
    if (!is_wp_error($existing)) {
        $ed = json_decode(wp_remote_retrieve_body($existing),true);
        if (!empty($ed['files'][0]['id'])) {
            /* File già presente — restituisce URL esistente */
            $fid = $ed['files'][0]['id'];
            return 'https://drive.google.com/uc?export=download&id='.$fid;
        }
    }

    /* Upload multipart */
    $mime     = mime_content_type($local_path) ?: 'application/octet-stream';
    $content  = file_get_contents($local_path);
    $boundary = '-------' . md5(time());
    $meta     = json_encode(array('name'=>$file_name,'parents'=>array($parent)));
    $body     = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$meta}\r\n"
              . "--{$boundary}\r\nContent-Type: {$mime}\r\n\r\n{$content}\r\n--{$boundary}--";

    $res = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id', array(
        'headers' => array('Authorization'=>'Bearer '.$token,'Content-Type'=>"multipart/related; boundary={$boundary}"),
        'body'    => $body,
        'timeout' => 60,
    ));
    if ( is_wp_error($res) ) return $res;
    $data = json_decode(wp_remote_retrieve_body($res),true);
    if ( empty($data['id']) ) return new WP_Error('gdrive_upload','Upload fallito: '.wp_remote_retrieve_body($res));

    /* Rendi il file pubblicamente leggibile */
    wp_remote_post('https://www.googleapis.com/drive/v3/files/'.$data['id'].'/permissions', array(
        'headers' => array('Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'),
        'body'    => json_encode(array('role'=>'reader','type'=>'anyone')),
        'timeout' => 15,
    ));
    return 'https://drive.google.com/uc?export=download&id='.$data['id'];
}

/* ══════════════════════════════════════════════════════════
   S3 / CLOUDFLARE R2 — Signature V4 (nessun SDK)
══════════════════════════════════════════════════════════ */
function mssgcs_s3_upload( $local_path, $cloud_path, $cfg ) {
    if ( empty($cfg['access_key']) || empty($cfg['secret_key']) || empty($cfg['bucket']) ) {
        return new WP_Error('s3_cfg','Credenziali S3/R2 incomplete.');
    }
    $content  = file_get_contents($local_path);
    $mime     = mime_content_type($local_path) ?: 'application/octet-stream';
    $bucket   = $cfg['bucket'];
    $region   = $cfg['region']  ?: 'auto';
    $endpoint = rtrim($cfg['endpoint'] ?: "https://s3.{$region}.amazonaws.com", '/');
    $url      = "{$endpoint}/{$bucket}/{$cloud_path}";
    $host     = parse_url($url, PHP_URL_HOST);
    $date     = gmdate('Ymd');
    $datetime = gmdate('Ymd\THis\Z');
    $hash     = hash('sha256', $content);

    $headers_to_sign = "content-type:{$mime}\nhost:{$host}\nx-amz-content-sha256:{$hash}\nx-amz-date:{$datetime}\n";
    $signed_headers  = 'content-type;host;x-amz-content-sha256;x-amz-date';
    $canonical = "PUT\n/{$bucket}/{$cloud_path}\n\n{$headers_to_sign}\n{$signed_headers}\n{$hash}";
    $scope     = "{$date}/{$region}/s3/aws4_request";
    $str2sign  = "AWS4-HMAC-SHA256\n{$datetime}\n{$scope}\n" . hash('sha256',$canonical);
    $sigkey    = hash_hmac('sha256','aws4_request',
                   hash_hmac('sha256','s3',
                     hash_hmac('sha256',$region,
                       hash_hmac('sha256',$date,'AWS4'.$cfg['secret_key'],true),true),true),true);
    $signature = hash_hmac('sha256',$str2sign,$sigkey);
    $auth      = "AWS4-HMAC-SHA256 Credential={$cfg['access_key']}/{$scope},SignedHeaders={$signed_headers},Signature={$signature}";

    $res = wp_remote_request($url, array(
        'method'  => 'PUT',
        'headers' => array(
            'Authorization'       => $auth,
            'Content-Type'        => $mime,
            'Host'                => $host,
            'x-amz-content-sha256'=> $hash,
            'x-amz-date'          => $datetime,
        ),
        'body'    => $content,
        'timeout' => 60,
    ));
    if ( is_wp_error($res) ) return $res;
    $code = wp_remote_retrieve_response_code($res);
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error('s3_upload',"Upload S3 fallito (HTTP {$code}): ".wp_remote_retrieve_body($res));
    }
    $pub = rtrim($cfg['public_url'] ?: $endpoint, '/');
    return "{$pub}/{$bucket}/{$cloud_path}";
}

/* ══════════════════════════════════════════════════════════
   AJAX HANDLERS
══════════════════════════════════════════════════════════ */

/* ── Salva impostazioni storage ── */
add_action('wp_ajax_mssgcs_save_settings', function(){
    mssg_ajax_check('manage_cantieri');
    $fields = array(
        'mssg_storage_mode','mssg_cloud_provider','mssg_hosting_quota_gb',
        'mssg_gdrive_sa_json','mssg_gdrive_folder_id',
        'mssg_s3_endpoint','mssg_s3_access_key','mssg_s3_secret_key',
        'mssg_s3_bucket','mssg_s3_region','mssg_s3_public_url',
    );
    foreach ($fields as $f) {
        if (isset($_POST[$f])) update_option($f, sanitize_textarea_field($_POST[$f]));
    }
    wp_send_json_success(array('msg'=>'Impostazioni salvate.'));
});

/* ── Info spazio hosting ── */
add_action('wp_ajax_mssgcs_spazio', function(){
    mssg_ajax_check('manage_cantieri');
    $cfg  = mssgcs_get_settings();
    $info = mssgcs_spazio_usato();
    $quota_bytes = $cfg['quota_gb'] * 1073741824;
    $pct  = $quota_bytes > 0 ? min(100, round($info['usato_bytes'] / $quota_bytes * 100)) : 0;
    wp_send_json_success(array(
        'usato_gb'   => $info['usato_gb'],
        'quota_gb'   => $cfg['quota_gb'],
        'file_count' => $info['file_count'],
        'pct'        => $pct,
        'warning'    => $pct >= 90,
    ));
});

/* ── Test connessione cloud ── */
add_action('wp_ajax_mssgcs_test_cloud', function(){
    mssg_ajax_check('manage_cantieri');
    $cfg = mssgcs_get_settings();
    if (empty($cfg['provider'])) wp_send_json_error(array('msg'=>'Nessun provider selezionato.'));
    switch ($cfg['provider']) {
        case 'gdrive':
            $token = mssgcs_gdrive_get_token($cfg['gdrive']['service_account_json']);
            if (is_wp_error($token)) wp_send_json_error(array('msg'=>'Google Drive: '.$token->get_error_message()));
            wp_send_json_success(array('msg'=>'✅ Google Drive connesso correttamente.'));
        case 'r2':
        case 's3':
            $s = $cfg['s3'];
            if (empty($s['access_key'])||empty($s['secret_key'])||empty($s['bucket']))
                wp_send_json_error(array('msg'=>'Credenziali S3/R2 incomplete.'));
            wp_send_json_success(array('msg'=>'✅ Credenziali S3/R2 impostate. Verifica effettiva al primo upload.'));
    }
    wp_send_json_error(array('msg'=>'Provider non riconosciuto.'));
});

/* ── Sposta file su cloud (batch) ── */
add_action('wp_ajax_mssgcs_sposta_su_cloud', function(){
    mssg_ajax_check('manage_cantieri');
    $cfg = mssgcs_get_settings();
    if (empty($cfg['provider'])) wp_send_json_error(array('msg'=>'Nessun provider cloud configurato.'));
    $limite = max(1, (int)($_POST['limite'] ?? 10));  /* lavorazione a batch */
    global $wpdb; $t = mssgc_table('media');
    /* Prende file con file_path locale ma senza cloud_url */
    $files = $wpdb->get_results($wpdb->prepare(
        "SELECT id, file_path, file_url, nome, cantiere_id FROM `{$t}`
         WHERE file_path != '' AND (cloud_url = '' OR cloud_url IS NULL) AND deleted_at IS NULL
         LIMIT %d", $limite));
    if (empty($files)) wp_send_json_success(array('msg'=>'Tutti i file sono già sul cloud.','restanti'=>0));
    $ok = 0; $err = array();
    foreach ($files as $f) {
        if (!file_exists($f->file_path)) { $err[] = "File fisico non trovato: {$f->nome}"; continue; }
        $cloud_url = mssgcs_upload_cloud($f->file_path, basename($f->file_path), $f->cantiere_id);
        if (is_wp_error($cloud_url)) { $err[] = $f->nome.': '.$cloud_url->get_error_message(); continue; }
        /* Aggiorna DB */
        $wpdb->update($t, array(
            'cloud_url'   => $cloud_url,
            'file_url'    => $cloud_url,  /* usa cloud come URL primario */
            'storage_loc' => 'cloud',
        ), array('id'=>(int)$f->id));
        /* Elimina file locale */
        @unlink($f->file_path);
        /* Elimina thumbnail locale se esiste */
        $thumb = preg_replace('/(\.[^.]+)$/','-thumb.jpg', $f->file_path);
        if (file_exists($thumb)) @unlink($thumb);
        $ok++;
    }
    /* Conta restanti */
    $restanti = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$t}` WHERE file_path != '' AND (cloud_url='' OR cloud_url IS NULL) AND deleted_at IS NULL");
    wp_send_json_success(array(
        'spostati' => $ok,
        'errori'   => $err,
        'restanti' => $restanti,
        'msg'      => "Spostati {$ok} file. Restano {$restanti} da spostare.".($err?" Errori: ".implode('; ',array_slice($err,0,3)):''),
    ));
});
