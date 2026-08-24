(function($){
    'use strict';

    /* ══════════════════════════════════════════════════
       POPUP / MODAL
    ══════════════════════════════════════════════════ */
    function openModal(type) {
        var $overlay = $('#msslu-modal-overlay');
        if (!$overlay.length) return;
        $overlay.find('[data-msslu-panel]').hide();
        $overlay.find('[data-msslu-panel="' + type + '"]').show();
        $overlay.addClass('open');
        $('body').css('overflow','hidden');
        setTimeout(function(){ $overlay.find('[data-msslu-panel="'+type+'"] input:first').focus(); }, 250);
    }

    function closeModal() {
        $('#msslu-modal-overlay').removeClass('open');
        $('body').css('overflow','');
    }

    $(document).on('click','[data-msslu-open]', function(e){ e.preventDefault(); openModal($(this).data('msslu-open')); });
    $(document).on('click','#msslu-modal-overlay', function(e){ if($(e.target).is('#msslu-modal-overlay')) closeModal(); });
    $(document).on('click','.msslu-modal-close', closeModal);
    $(document).on('keydown', function(e){ if(e.key==='Escape') closeModal(); });

    /* ══════════════════════════════════════════════════
       ACCOUNT NAV — AJAX senza scroll
    ══════════════════════════════════════════════════ */
    // Helper per caricare sezione con parametri extra
    function mssluLoadSection(section, params, $layout) {
        params = params || {};
        var $main = $layout.find('.msslu-account-main, [id*="section-main"]').first();
        if (!$main.length) return;

        $main.css({opacity:.4,'pointer-events':'none'});

        $.post(MSSLU.ajax_url, {
            action:'msslu_load_section', nonce:MSSLU.nonce,
            section:section, section_params:params
        }, function(res){
            $main.css({opacity:1,'pointer-events':''});
            if (res && res.success) {
                $main.html(res.data.html);
                if (res.data.nickname) $layout.find('.msslu-account-nickname').text(res.data.nickname);
                if (res.data.avatar) $layout.find('.msslu-avatar-img').attr('src', res.data.avatar);
                var top = $layout.offset().top - 80;
                if ($(window).scrollTop() > top) $('html,body').animate({scrollTop:top}, 300);
            } else {
                showNotice(res && res.data && res.data.msg ? res.data.msg : 'Errore nel caricamento della sezione.', 'error', $main);
            }
        }).fail(function(){
            $main.css({opacity:1,'pointer-events':''});
            showNotice('Errore di connessione. Ricarica la pagina.', 'error', $main);
        });
    }

    $(document).on('click', '[data-section]', function(e){
        e.preventDefault();
        var section = $(this).data('section');
        if (!section) return;

        // Aggiorna nav active
        $(this).closest('nav, .msslu-account-nav').find('.msslu-nav-item').removeClass('active');
        $(this).addClass('active');

        var $layout = $(this).closest('.msslu-account-layout');
        var $main   = $layout.find('.msslu-account-main, [id*="section-main"]').first();
        if (!$main.length) return;

        $main.css({opacity:.4, 'pointer-events':'none'});

        $.post(MSSLU.ajax_url, {
            action:'msslu_load_section', nonce:MSSLU.nonce, section:section
        }, function(res){
            $main.css({opacity:1,'pointer-events':''});
            if (res && res.success) {
                $main.html(res.data.html);

                if (res.data.nickname) {
                    $layout.find('.msslu-account-nickname').text(res.data.nickname);
                }
                if (res.data.avatar) {
                    $layout.find('.msslu-avatar-img').attr('src', res.data.avatar);
                }

                var top = $layout.offset().top - 80;
                if ($(window).scrollTop() > top) {
                    $('html,body').animate({scrollTop: top}, 300);
                }
            } else {
                showNotice(res && res.data && res.data.msg ? res.data.msg : 'Errore nel caricamento della sezione.', 'error', $main);
            }
        }).fail(function(){
            $main.css({opacity:1,'pointer-events':''});
            showNotice('Errore di connessione. Ricarica la pagina.', 'error', $main);
        });

        if (!$(this).closest('#msslu-modal-overlay').length) {
            if (window.history && window.history.pushState) {
                var url = new URL(window.location.href);
                url.searchParams.set('section', section);
                window.history.pushState({section:section},'',url.toString());
            }
        }
    });

    // Bottone chat influencer nel pannello admin frontend
    $(document).on('click', '[data-inftr-chat]', function(e){
        e.preventDefault();
        var infId   = $(this).data('inftr-chat');
        var $layout = $(this).closest('.msslu-account-layout');
        // Aggiorna nav
        $layout.find('.msslu-nav-item').removeClass('active');
        $layout.find('[data-section="inf_admin"]').addClass('active');
        mssluLoadSection('inf_admin', {inf_chat: infId}, $layout);
    });

    // Bottone "← Lista" nel pannello chat admin
    $(document).on('click', '[data-inftr-back]', function(e){
        e.preventDefault();
        var $layout = $(this).closest('.msslu-account-layout');
        mssluLoadSection('inf_admin', {}, $layout);
    });

    /* ══════════════════════════════════════════════════
       FORM SUBMIT AJAX
    ══════════════════════════════════════════════════ */
    $(document).on('submit', '.msslu-account-main form:not(.inftr-external-form)', function(e){
        e.preventDefault();
        var $form   = $(this);
        var $btn    = $form.find('.msslu-btn').first();
        var origTxt = $btn.text();
        $btn.css('opacity',.6).prop('disabled',true).text('Salvataggio...');

        // Trova il main container del form
        var $main = $form.closest('.msslu-account-main, [id*="section-main"]');

        $.post(MSSLU.ajax_url,
            $form.serialize() + '&action=msslu_account_action&nonce=' + MSSLU.nonce,
            function(res){
                $btn.css('opacity',1).prop('disabled',false).text(origTxt);
                showNotice(
                    res && res.success ? (res.data.msg||'Salvato.') : (res && res.data && res.data.msg || 'Errore.'),
                    res && res.success ? 'success' : 'error',
                    $main
                );
            }
        ).fail(function(){
            $btn.css('opacity',1).prop('disabled',false).text(origTxt);
            showNotice('Errore di connessione. Riprova.', 'error', $main);
        });
    });

    /* ══════════════════════════════════════════════════
       AVATAR UPLOAD — triggers from details section
    ══════════════════════════════════════════════════ */
    // Click su immagine, badge o pulsante "Scegli foto"
    $(document).on('click', '#msslu-avatar-trigger, #msslu-avatar-trigger-btn, .msslu-avatar-large, .msslu-avatar-upload-badge', function(e){
        e.preventDefault();
        $('#msslu-avatar-input').trigger('click');
    });

    $(document).on('change', '#msslu-avatar-input', function(){
        var file = this.files[0];
        if (!file) return;

        // Validazione client-side
        var allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (allowed.indexOf(file.type) === -1) {
            showNotice('Formato non supportato. Usa JPG, PNG, GIF o WebP.', 'error');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showNotice('Il file è troppo grande. Max 2MB.', 'error');
            return;
        }

        // Preview immediato
        var reader = new FileReader();
        reader.onload = function(ev){ $('.msslu-avatar-img').attr('src', ev.target.result); };
        reader.readAsDataURL(file);

        // Upload
        var fd = new FormData();
        fd.append('action','msslu_upload_avatar');
        fd.append('nonce', MSSLU.nonce);
        fd.append('msslu_avatar_upload', file);

        var $progress = $('#msslu-avatar-progress');
        var $fill     = $('#msslu-upload-fill');
        var $label    = $('#msslu-upload-label');
        $progress.show();

        $.ajax({
            url:MSSLU.ajax_url, type:'POST', data:fd,
            processData:false, contentType:false,
            xhr: function(){
                var x = new XMLHttpRequest();
                x.upload.addEventListener('progress', function(e){
                    if (e.lengthComputable) {
                        var pct = Math.round(e.loaded/e.total*90);
                        $fill.css('width', pct+'%');
                        $label.text('Caricamento ' + pct + '%...');
                    }
                });
                return x;
            },
            success:function(res){
                $fill.css('width','100%');
                setTimeout(function(){ $progress.hide(); $fill.css('width','0'); $label.text(''); }, 600);
                if (res.success) {
                    // Aggiorna TUTTE le immagini avatar nella pagina (sidebar + preview dettagli)
                    $('img.msslu-avatar-img, img.msslu-avatar-large, #msslu-avatar-preview, #msslu-modal-avatar').attr('src', res.data.url);
                    showNotice(res.data.msg, 'success');
                } else {
                    showNotice(res.data.msg || MSSLU.strings.upload_error, 'error');
                }
            },
            error:function(){ $progress.hide(); showNotice(MSSLU.strings.upload_error,'error'); }
        });
    });

    // Salva src originale per rollback
    $(document).on('mouseenter', '.msslu-avatar-wrap', function(){
        var $img = $(this).find('.msslu-avatar-img');
        if (!$img.data('original')) $img.data('original', $img.attr('src'));
    });

    /* ══════════════════════════════════════════════════
       GOOGLE — scollega account
    ══════════════════════════════════════════════════ */
    $(document).on('click', '#msslu-unlink-google-btn', function(){
        if (!confirm('Scollegare il tuo account Google? Potrai comunque accedere con email e password.')) return;
        var $btn = $(this).prop('disabled',true).text('Scollegamento...');
        $.post(MSSLU.ajax_url, {action:'msslu_unlink_google', nonce:MSSLU.nonce}, function(res){
            if (res && res.success) {
                showNotice(res.data.msg || 'Scollegato.', 'success');
                setTimeout(function(){ location.reload(); }, 600);
            } else {
                $btn.prop('disabled',false).text('Scollega Google');
                showNotice(res && res.data && res.data.msg || 'Errore.', 'error');
            }
        }).fail(function(){
            $btn.prop('disabled',false).text('Scollega Google');
            showNotice('Errore di connessione. Riprova.', 'error');
        });
    });

    /* ══════════════════════════════════════════════════
       ACCOUNT DELETE / DISABLE
    ══════════════════════════════════════════════════ */
    $(document).on('click', '#msslu-disable-account-btn', function(){
        if (!confirm(MSSLU.strings.disable_confirm)) return;
        var $btn = $(this).prop('disabled',true).text('Disabilitazione...');
        $.post(MSSLU.ajax_url, {action:'msslu_disable_account', nonce:MSSLU.nonce}, function(res){
            if (res && res.success) {
                alert(res.data.msg);
                window.location.href = MSSLU.home_url;
            } else {
                $btn.prop('disabled',false).text('Disabilita il mio account');
                showNotice(res && res.data && res.data.msg || 'Errore.','error');
            }
        }).fail(function(){
            $btn.prop('disabled',false).text('Disabilita il mio account');
            showNotice('Errore di connessione. Riprova.','error');
        });
    });

    $(document).on('click', '#msslu-delete-account-btn', function(){
        alert(MSSLU.strings.delete_warning);
        $('#msslu-delete-confirm').slideDown(200);
        $(this).hide();
    });

    $(document).on('click', '#msslu-delete-account-cancel', function(){
        $('#msslu-delete-confirm').slideUp(200);
        $('#msslu-delete-account-btn').show();
        $('#msslu-delete-password').val('');
    });

    $(document).on('click', '#msslu-delete-account-confirm', function(){
        var pwd = $('#msslu-delete-password').val();
        if (!pwd) { showNotice('Inserisci la password per confermare.','error'); return; }
        var $btn = $(this).prop('disabled',true).text('Eliminazione in corso...');
        $.post(MSSLU.ajax_url, {action:'msslu_delete_account', nonce:MSSLU.nonce, confirm_password:pwd}, function(res){
            if (res && res.success) {
                alert(res.data.msg);
                window.location.href = res.data.redirect || MSSLU.home_url;
            } else {
                $btn.prop('disabled',false).text('Sì, elimina definitivamente il mio account');
                showNotice(res && res.data && res.data.msg || 'Errore.','error');
            }
        }).fail(function(){
            $btn.prop('disabled',false).text('Sì, elimina definitivamente il mio account');
            showNotice('Errore di connessione. Riprova.','error');
        });
    });

    /* ══════════════════════════════════════════════════
       PASSWORD STRENGTH — uno solo, non si accumula
    ══════════════════════════════════════════════════ */
    $(document).on('input', 'input[name="new_password"], input[name="user_password"]', function(){
        // Rimuovi TUTTI gli indicatori precedenti nel form
        $(this).closest('form').find('.msslu-strength').remove();

        var val = $(this).val();
        if (!val.length) return;

        var s = 0;
        if (val.length >= 6)          s++;
        if (val.length >= 10)         s++;
        if (/[A-Z]/.test(val))        s++;
        if (/[0-9]/.test(val))        s++;
        if (/[^A-Za-z0-9]/.test(val)) s++;

        var labels = ['Molto debole','Debole','Discreta','Forte','Molto forte'];
        var colors = ['#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
        var idx    = Math.min(s, 4);

        $(this).closest('.msslu-field').after(
            '<div class="msslu-strength" style="color:'+colors[idx]+';font-size:11px;font-weight:600;padding:2px 4px;">'
            + labels[idx] + '</div>'
        );
    });

    /* ══════════════════════════════════════════════════
       BANNER AUTOPLAY
    ══════════════════════════════════════════════════ */
    function initBanners() {
        $('.msslu-banner-wrap').each(function(){
            var $wrap    = $(this);
            var $slides  = $wrap.find('.msslu-banner-slide');
            var $dots    = $wrap.find('.msslu-banner-dot');
            var interval = parseInt($wrap.data('interval')) || 3000;
            var current  = 0;
            var total    = $slides.length;
            if (total <= 1) return;

            function goTo(n) {
                $slides.eq(current).css({position:'absolute',opacity:0,zIndex:0});
                $dots.eq(current).removeClass('active');
                current = (n + total) % total;
                $slides.eq(current).css({position:'relative',opacity:1,zIndex:1});
                $dots.eq(current).addClass('active');
            }

            $dots.on('click', function(){ goTo($(this).data('idx')); });

            // Avvia timer
            var timer = setInterval(function(){ goTo(current + 1); }, interval);
            $wrap.on('mouseenter', function(){ clearInterval(timer); });
            $wrap.on('mouseleave', function(){ timer = setInterval(function(){ goTo(current+1); }, interval); });
        });
    }
    initBanners();

    /* ══════════════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════════════ */
    function showNotice(msg, type, $container) {
        var $main = $container || $('#msslu-section-main, .msslu-account-main').first();
        if (!$main.length) $main = $('body');
        $main.find('.msslu-notice:not(.msslu-notice--static)').remove();
        var $n = $('<div class="msslu-notice msslu-notice--'+type+'">'+esc(msg)+'</div>');
        $main.prepend($n);
        setTimeout(function(){ $n.fadeOut(400, function(){ $(this).remove(); }); }, 4000);
    }

    function esc(s) { return $('<div>').text(String(s)).html(); }

    // Auto-dismiss static notices on page load
    setTimeout(function(){
        $('.msslu-notice').not('.msslu-notice--static').fadeOut(600);
    }, 4000);

    /* ── Modal dettaglio ordine ─────────────────────── */
    $(document).on('click', '.msslu-order-detail-btn', function() {
        var orderId  = $(this).data('order-id');
        var $overlay = $('#msslu-order-modal-overlay');
        var $body    = $('#msslu-order-modal-body');
        var $title   = $('#msslu-order-modal-title');
        var $pdfBtn  = $('#msslu-order-pdf-btn');
        var $payBtn  = $('#msslu-order-pay-btn');

        $body.html('<div class="msslu-order-modal-loading">Caricamento...</div>');
        $title.text('Dettagli ordine');
        $payBtn.hide();
        $overlay.css('display','flex');
        $('body').css('overflow','hidden');

        $.post(MSSLU.ajax_url, {
            action:   'msslu_order_detail',
            nonce:    MSSLU.nonce,
            order_id: orderId,
        }, function(res) {
            if (res.success) {
                $title.text(res.data.title);
                $body.html(res.data.html);
                // Mostra PDF solo per ordini con totale > 0 e non annullati
                if (res.data.pdf_url && res.data.can_pdf) {
                    $pdfBtn.attr('href', res.data.pdf_url).show();
                } else {
                    $pdfBtn.hide();
                }
                if (res.data.can_pay && res.data.pay_url) {
                    $payBtn.attr('href', res.data.pay_url).show();
                }
            } else {
                $body.html('<p style="color:#ef4444;text-align:center;padding:20px;">Errore nel caricamento.</p>');
            }
        }).fail(function() {
            $body.html('<p style="color:#ef4444;text-align:center;padding:20px;">Errore di connessione.</p>');
        });
    });

    function mssluCloseOrderModal() {
        $('#msslu-order-modal-overlay').css('display','none');
        $('body').css('overflow','');
    }
    $(document).on('click','#msslu-order-modal-close,#msslu-order-modal-close-btn', mssluCloseOrderModal);
    $(document).on('click','#msslu-order-modal-overlay', function(e){
        if($(e.target).is('#msslu-order-modal-overlay')) mssluCloseOrderModal();
    });
    $(document).on('keydown', function(e){ if(e.key==='Escape') mssluCloseOrderModal(); });

    /* ── Annulla ordine ─────────────────────────────── */
    $(document).on('click', '.msslu-order-cancel-btn', function() {
        var $btn    = $(this);
        var orderId = $btn.data('order-id');
        var nonce   = $btn.data('nonce');
        if (!confirm('Sei sicuro di voler annullare questo ordine? L\'operazione non può essere annullata.')) return;

        $btn.prop('disabled',true).text('Annullamento...');

        $.post(MSSLU.ajax_url, {
            action:   'msslu_cancel_order',
            nonce:    nonce,
            order_id: orderId,
        }, function(res) {
            var $notice = $('#msslu-order-notice');
            if (res && res.success) {
                var $row = $('#msslu-order-row-' + orderId);
                $row.find('.msslu-order-status')
                    .text('Annullato')
                    .attr('class','msslu-order-status msslu-status--cancelled');
                $row.find('.msslu-btn-pay, .msslu-order-cancel-btn').remove();
                $notice.css({'background':'#e0fce7','color':'#166534'}).text(res.data.msg).fadeIn(200);
                setTimeout(function(){ $notice.fadeOut(400); }, 4000);
            } else {
                $notice.css({'background':'#fee2e2','color':'#991b1b'}).text((res && res.data) || 'Impossibile annullare l\'ordine.').fadeIn(200);
                setTimeout(function(){ $notice.fadeOut(400); }, 4000);
                $btn.prop('disabled',false).text('✕ Annulla');
            }
        }).fail(function(){
            var $notice = $('#msslu-order-notice');
            $notice.css({'background':'#fee2e2','color':'#991b1b'}).text('Errore di connessione. Riprova.').fadeIn(200);
            setTimeout(function(){ $notice.fadeOut(400); }, 4000);
            $btn.prop('disabled',false).text('✕ Annulla');
        });
    });

})(jQuery);
