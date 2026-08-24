<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════
   SHORTCODE ACCESSO COLLABORATORI
   Uso: [mssg_login_collaboratore]
   Mostra login form, dopo login ridirige all'area gestionale.
   Nasconde il form se l'utente è già loggato.
══════════════════════════════════════════════════════ */
add_shortcode('mssg_login_collaboratore','mssgc_shortcode_login_collaboratore');

function mssgc_shortcode_login_collaboratore($atts){
    $atts=shortcode_atts(array('redirect'=>'','titolo'=>'Accedi al gestionale','show_logo'=>'1'),$atts);

    if(is_user_logged_in()){
        $user_id=get_current_user_id();
        if(mssg_is_gestionale_user($user_id)){
            $url=mssg_get_private_area_url();
            return '<p style="font-size:14px">Sei già connesso. <a href="'.esc_url($url).'" style="color:var(--msslu-accent)">Vai al gestionale →</a></p>';
        }
    }

    $redirect=$atts['redirect']?:mssg_get_private_area_url();
    ob_start();?>
    <div class="mssgc-login-wrap" style="max-width:380px;margin:0 auto">

        <?php if($atts['show_logo']==='1'):
            $logo=mssg_get_option('company_logo');
            $nome=mssg_get_option('company_name',get_bloginfo('name'));
        ?>
        <div style="text-align:center;margin-bottom:20px">
            <?php if($logo):?><img src="<?php echo esc_url($logo);?>" alt="<?php echo esc_attr($nome);?>" style="max-height:60px;margin-bottom:10px;display:block;margin-inline:auto"><?php endif;?>
            <h2 style="font-size:18px;margin:0"><?php echo esc_html($nome);?></h2>
        </div>
        <?php endif;?>

        <div style="background:var(--msslu-box-bg,#1a1a2e);border:1px solid var(--msslu-box-border);border-radius:14px;padding:28px">
            <h3 style="font-size:15px;margin:0 0 20px;text-align:center"><?php echo esc_html($atts['titolo']);?></h3>
            <div id="mssgc-login-notice" style="margin-bottom:12px"></div>

            <div style="margin-bottom:14px">
                <label style="font-size:12px;color:var(--msslu-text-muted);display:block;margin-bottom:5px">Username o Email</label>
                <input type="text" id="mssgc-login-user" placeholder="mario.rossi"
                       style="width:100%;padding:9px 12px;background:var(--msslu-input-bg);border:1px solid var(--msslu-input-border);border-radius:8px;color:var(--msslu-text);font-size:13px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:20px">
                <label style="font-size:12px;color:var(--msslu-text-muted);display:block;margin-bottom:5px">Password</label>
                <input type="password" id="mssgc-login-pass" placeholder="••••••••"
                       style="width:100%;padding:9px 12px;background:var(--msslu-input-bg);border:1px solid var(--msslu-input-border);border-radius:8px;color:var(--msslu-text);font-size:13px;box-sizing:border-box">
            </div>
            <button id="mssgc-login-submit" data-redirect="<?php echo esc_url($redirect);?>"
                    style="width:100%;padding:11px;background:var(--msslu-accent,#e53e3e);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer">
                Accedi
            </button>
            <p style="text-align:center;margin-top:12px;font-size:12px;color:var(--msslu-text-muted)">
                <a href="<?php echo esc_url(wp_lostpassword_url());?>" style="color:var(--msslu-accent)">Password dimenticata?</a>
            </p>
        </div>
    </div>
    <script>
    (function($){
        $('#mssgc-login-submit').on('click',function(){
            var $btn=$(this);var user=$('#mssgc-login-user').val().trim();var pass=$('#mssgc-login-pass').val();
            if(!user||!pass){$('#mssgc-login-notice').html('<p style="color:#ef4444;font-size:12px">Inserisci username e password.</p>');return;}
            $btn.prop('disabled',true).text('Accesso in corso…');
            $.post('<?php echo admin_url('admin-ajax.php');?>',{
                action:'mssg_login_ajax',nonce:'<?php echo wp_create_nonce('mssg_login_nonce');?>',
                username:user,password:pass,redirect:$btn.data('redirect')
            },function(r){
                if(r&&r.success){window.location.href=r.data.redirect;}
                else{$('#mssgc-login-notice').html('<p style="color:#ef4444;font-size:12px">'+(r&&r.data&&r.data.msg?r.data.msg:'Credenziali non valide.')+'</p>');$btn.prop('disabled',false).text('Accedi');}
            }).fail(function(xhr){
                var msg='Errore di connessione'+(xhr&&xhr.status?' (HTTP '+xhr.status+')':'')+'. Riprova.';
                $('#mssgc-login-notice').html('<p style="color:#ef4444;font-size:12px">'+msg+'</p>');
                $btn.prop('disabled',false).text('Accedi');
            });
        });
        $('#mssgc-login-pass').on('keypress',function(e){if(e.which===13)$('#mssgc-login-submit').trigger('click');});
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

/* ══════════════════════════════════════════════════════
   SHORTCODE ACCESSO CLIENTI
   Uso: [mssg_login_cliente]
   Mostra login + link auto-registrazione.
══════════════════════════════════════════════════════ */
add_shortcode('mssg_login_cliente','mssgc_shortcode_login_cliente');

function mssgc_shortcode_login_cliente($atts){
    $atts=shortcode_atts(array('redirect'=>'','registrazione'=>'1','titolo'=>'Area clienti'),$atts);

    if(is_user_logged_in()&&mssg_user_has_role(get_current_user_id(),'mssg_cliente')){
        $url=$atts['redirect']?:mssg_get_private_area_url();
        return '<p>Sei già nella tua area. <a href="'.esc_url($url).'">Accedi →</a></p>';
    }

    $redirect=$atts['redirect']?:mssg_get_private_area_url();
    ob_start();?>
    <div class="mssgc-login-wrap" style="max-width:380px;margin:0 auto">
        <?php $logo=mssg_get_option('company_logo');$nome=mssg_get_option('company_name',get_bloginfo('name'));?>
        <div style="text-align:center;margin-bottom:20px">
            <?php if($logo):?><img src="<?php echo esc_url($logo);?>" alt="" style="max-height:60px;display:block;margin:0 auto 10px"><?php endif;?>
            <h2 style="font-size:18px;margin:0"><?php echo esc_html($nome);?></h2>
        </div>

        <div style="background:var(--msslu-box-bg,#1a1a2e);border:1px solid var(--msslu-box-border);border-radius:14px;padding:28px">
            <h3 style="font-size:15px;margin:0 0 20px;text-align:center"><?php echo esc_html($atts['titolo']);?></h3>
            <div id="mssgc-login-notice2"></div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;color:var(--msslu-text-muted);display:block;margin-bottom:5px">Email o Username</label>
                <input type="text" id="mssgcl-login-user" placeholder="mario@email.it"
                       style="width:100%;padding:9px 12px;background:var(--msslu-input-bg);border:1px solid var(--msslu-input-border);border-radius:8px;color:var(--msslu-text);font-size:13px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:20px">
                <label style="font-size:12px;color:var(--msslu-text-muted);display:block;margin-bottom:5px">Password</label>
                <input type="password" id="mssgcl-login-pass" placeholder="••••••••"
                       style="width:100%;padding:9px 12px;background:var(--msslu-input-bg);border:1px solid var(--msslu-input-border);border-radius:8px;color:var(--msslu-text);font-size:13px;box-sizing:border-box">
            </div>
            <button id="mssgcl-login-submit" data-redirect="<?php echo esc_url($redirect);?>"
                    style="width:100%;padding:11px;background:var(--msslu-accent,#e53e3e);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer">
                Accedi all'area personale
            </button>
            <p style="text-align:center;margin-top:12px;font-size:12px;color:var(--msslu-text-muted)">
                <a href="<?php echo esc_url(wp_lostpassword_url());?>" style="color:var(--msslu-accent)">Password dimenticata?</a>
            </p>
            <?php if($atts['registrazione']==='1'):?>
            <div style="border-top:1px solid var(--msslu-box-border);margin-top:16px;padding-top:16px;text-align:center;font-size:13px;color:var(--msslu-text-muted)">
                Prima volta? <a href="<?php echo esc_url(home_url('/registrati'));?>" style="color:var(--msslu-accent)">Registrati qui →</a>
            </div>
            <?php endif;?>
        </div>
    </div>
    <script>
    (function($){
        $('#mssgcl-login-submit').on('click',function(){
            var $btn=$(this);
            $btn.prop('disabled',true).text('Accesso…');
            $.post('<?php echo admin_url('admin-ajax.php');?>',{
                action:'mssg_login_ajax',nonce:'<?php echo wp_create_nonce('mssg_login_nonce');?>',
                username:$('#mssgcl-login-user').val().trim(),
                password:$('#mssgcl-login-pass').val(),
                redirect:$btn.data('redirect')
            },function(r){
                if(r&&r.success){window.location.href=r.data.redirect;}
                else{$('#mssgc-login-notice2').html('<p style="color:#ef4444;font-size:12px">'+(r&&r.data&&r.data.msg?r.data.msg:'Credenziali non valide.')+'</p>');$btn.prop('disabled',false).text('Accedi all\'area personale');}
            }).fail(function(xhr){
                var msg='Errore di connessione'+(xhr&&xhr.status?' (HTTP '+xhr.status+')':'')+'. Riprova.';
                $('#mssgc-login-notice2').html('<p style="color:#ef4444;font-size:12px">'+msg+'</p>');
                $btn.prop('disabled',false).text('Accedi all\'area personale');
            });
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

/* ══════════════════════════════════════════════════════
   AJAX LOGIN (usato da entrambi gli shortcode)
══════════════════════════════════════════════════════ */
add_action('wp_ajax_nopriv_mssg_login_ajax','mssgc_ajax_login');
add_action('wp_ajax_mssg_login_ajax','mssgc_ajax_login');

function mssgc_ajax_login(){
    check_ajax_referer('mssg_login_nonce','nonce');
    $username=sanitize_user($_POST['username']??'');
    $password=$_POST['password']??'';
    $redirect=esc_url_raw($_POST['redirect']??'');

    if(!$username||!$password)wp_send_json_error(array('msg'=>'Inserisci username e password.'));

    $user=wp_authenticate($username,$password);
    if(is_wp_error($user)){
        // Prova anche con email
        if(is_email($username)){
            $by_email=get_user_by('email',$username);
            if($by_email)$user=wp_authenticate($by_email->user_login,$password);
        }
        if(is_wp_error($user))wp_send_json_error(array('msg'=>'Credenziali non valide.'));
    }

    if(!mssg_is_gestionale_user($user->ID))
        wp_send_json_error(array('msg'=>'Account non autorizzato ad accedere al gestionale.'));

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID,false);

    $go=$redirect?:mssg_get_private_area_url();
    wp_send_json_success(array('redirect'=>$go,'msg'=>'Accesso effettuato.'));
}

/* ══════════════════════════════════════════════════════
   SHORTCODE AREA PRIVATA (pagina gestionale)
   Uso: [mssg_area_privata]
   Mostra l'interfaccia account di login-ui.
   Se non loggato, ridirige al login.
══════════════════════════════════════════════════════ */
add_shortcode('mssg_area_privata','mssgc_shortcode_area_privata');

function mssgc_shortcode_area_privata($atts){
    if(!is_user_logged_in()){
        $login_url=get_option('mssg_login_page_url',home_url('/accedi'));
        wp_redirect($login_url);exit;
    }
    if(!mssg_is_gestionale_user(get_current_user_id())){
        return '<p>Non sei autorizzato ad accedere a questa area.</p>';
    }
    // Usa il shortcode di login-ui per mostrare l'area account
    if(shortcode_exists('msslu_account')){
        return do_shortcode('[msslu_account]');
    }
    return '<p>Plugin MSS Login UI non attivo.</p>';
}
