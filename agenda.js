/* mssg-agenda v4 */

    /* Definisce MSSGAG inline come fallback se wp_localize_script non l'ha fatto
       (es. plugin di cache/minify che rimuovono lo script inline di localizzazione).
       IMPORTANTE: non leggere MSSGAG.* qui dentro — in questo ramo MSSGAG non esiste
       ancora, quindi leggerne le proprietà lancerebbe un ReferenceError che blocca
       l'intero file (e con esso il calendario, che resta bloccato su "Caricamento…"). */
    if (typeof MSSGAG === 'undefined') {
        var MSSGAG = {
            ajax_url: (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php',
            nonce:    ''
        };
    }

    /* ── Helper AJAX condiviso per tutto il modulo agenda ──────────────
       Uniforma gestione errori/connessione: nasconde sempre lo stato di
       caricamento e mostra un feedback (toast se disponibile, altrimenti
       console) invece di lasciare l'interfaccia bloccata senza spiegazione. */
    window.mssgagNotifyError = function(msg) {
        if (typeof window.mssgToast === 'function') window.mssgToast(msg, 'error');
        else if (window.console) console.error('[mssg-agenda]', msg);
    };

    window.mssgagAjax = function(data, onSuccess, onError) {
        return jQuery.post(MSSGAG.ajax_url, data)
            .done(function(r) {
                if (r && r.success) {
                    if (onSuccess) onSuccess(r.data);
                } else {
                    var msg = (r && r.data && r.data.msg) ? r.data.msg : 'Si è verificato un errore.';
                    if (onError) onError(msg); else window.mssgagNotifyError(msg);
                }
            })
            .fail(function(xhr) {
                var msg = 'Errore di connessione' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '. Riprova.';
                if (onError) onError(msg); else window.mssgagNotifyError(msg);
            });
    };

    (function($){
        var currentWeekStart = null;

        function getMonday(d) {
            d = new Date(d); var day = d.getDay(), diff = d.getDate()-day+(day===0?-6:1);
            d.setDate(diff); return d.toISOString().split('T')[0];
        }
        function formatWeekLabel(ws) {
            var d = new Date(ws); var de = new Date(ws); de.setDate(de.getDate()+6);
            var mesi=['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
            return d.getDate()+' '+mesi[d.getMonth()]+' \u2013 '+de.getDate()+' '+mesi[de.getMonth()]+' '+de.getFullYear();
        }

        function loadWeek(weekStart) {
            window.currentWeekStart = weekStart;
            currentWeekStart = weekStart;
            $('#mssgag-week-label').text(formatWeekLabel(weekStart));
            $('#mssgag-calendar-loading').show();
            $('#mssgag-calendar').hide();
            window.mssgagAjax(
                {action:'mssgag_get_week',nonce:MSSGAG.nonce,week_start:weekStart},
                function(data) {
                    renderCalendar(data.giorni, data.for_admin, data.no_orari_propri);
                    $('#mssgag-calendar-loading').hide();
                    $('#mssgag-calendar').show();
                },
                function(msg) {
                    $('#mssgag-calendar-loading').hide();
                    $('#mssgag-calendar').show().html(
                        '<div class="mssgag-error" style="padding:20px;text-align:center;color:#ef4444;font-size:13px">'
                        + '⚠️ ' + $('<div>').text(msg).html() + ' '
                        + '<button onclick="mssgagLoadWeek(window.currentWeekStart)" style="background:none;border:none;color:#ef4444;text-decoration:underline;cursor:pointer;font-size:13px">Riprova</button>'
                        + '</div>'
                    );
                    window.mssgagNotifyError(msg);
                }
            );
        }

        window.mssgagLoadWeek = loadWeek;

        function renderCalendar(giorni, forAdmin, noOrariPropri) {
            /* Banner orari non configurati */
            $('#mssgag-no-orari-banner').remove();
            if (noOrariPropri && forAdmin) {
                var $b = $('<div id="mssgag-no-orari-banner"></div>');
                $b.css({padding:'10px 14px',background:'rgba(245,158,11,.1)',border:'1px solid rgba(245,158,11,.25)',
                    borderRadius:'8px',marginBottom:'12px',fontSize:'12px',color:'#f59e0b',display:'flex',alignItems:'center',gap:'8px'});
                $b.text('Nessun orario configurato \u2014 vengono mostrati quelli predefiniti. ');
                var $a = $('<a href="#"></a>').css({color:'#f59e0b',fontWeight:'600'})
                    .addClass('mssgag-goto-orari').text('Configura i tuoi orari \u2192');
                $b.append($a);
                $b.insertBefore('#mssgag-calendar-wrap');
            }
                        /* Raccoglie tutti gli orari unici */
            var times = [];
            giorni.forEach(function(g) { g.slots.forEach(function(s) { if (times.indexOf(s.time)===-1) times.push(s.time); }); });
            times.sort();

            var html = '<table class="mssgag-cal-table"><thead><tr><th>Ora</th>';
            giorni.forEach(function(g) {
                var cls = g.oggi ? ' class="oggi"' : '';
                html += '<th'+cls+'>'+g.label+(g.oggi?' <span style="color:var(--msslu-accent,#e91e8c);font-size:9px">\u25CF</span>':'')+'</th>';
            });
            html += '</tr></thead><tbody>';

            times.forEach(function(time) {
                html += '<tr><td class="time-col">'+time+'</td>';
                giorni.forEach(function(g) {
                    if (!g.lavorativo) { html += '<td></td>'; return; }
                    var slot = null;
                    g.slots.forEach(function(s) { if (s.time === time) slot = s; });
                    if (!slot) { html += '<td></td>'; return; }

                    var cls = 'mssgag-slot ';
                    var label = '';
                    var attrs = 'data-datetime="'+slot.datetime+'" data-date="'+g.date+'"';

                    function bloccoColor(id) {
                        var h = (id * 137 + 43) % 360;
                        return 'hsl('+h+',60%,52%)';
                    }
                    function renderBlocchi(blocchi, isPassato) {
                        var alpha = isPassato ? '.5' : '1';
                        var bHtml = '';
                        blocchi.forEach(function(b){
                            var tl = b.titolo || '\uD83D\uDCC5';
                            var col = bloccoColor(b.blocco_id);
                            bHtml += '<div class="mssgag-mini-block"'
                                +' onclick="event.stopPropagation();mssgagClickBloccoId('+b.blocco_id+')"'
                                +' style="font-size:9px;padding:2px 5px;border-radius:3px;cursor:pointer;margin-bottom:2px;'
                                +'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;'
                                +'background:'+col+';color:#fff;opacity:'+alpha+'">'+tl+'</div>';
                        });
                        return bHtml;
                    }
                    if (slot.passato) {
                        var haBlocchiPast = forAdmin && slot.blocchi && slot.blocchi.length > 0;
                        if (haBlocchiPast) {
                            cls += 'mssgag-slot-past-busy';
                            label = renderBlocchi(slot.blocchi, true);
                            attrs += ' onclick="mssgagClickSlot(this)"'
                                + ' data-ts="'+(slot.ts||0)+'"'
                                + ' data-ts-fine="'+(slot.ts_fine||0)+'"';
                        } else {
                            cls += 'mssgag-slot-past';
                            label = '\u2014';
                        }
                    } else if (slot.free) {
                        var haBlocchi = forAdmin && slot.blocchi && slot.blocchi.length > 0;
                        if (haBlocchi) {
                            cls += 'mssgag-slot-multi';
                            label = renderBlocchi(slot.blocchi, false)
                                + '<div style="font-size:9px;color:rgba(34,197,94,.5);text-align:center">+ libero</div>';
                        } else {
                            cls += 'mssgag-slot-free';
                            label = forAdmin ? '<span style="font-size:10px;opacity:.6">libero</span>' : '';
                        }
                        attrs += ' onclick="mssgagClickSlot(this)"'
                            + ' data-ts="'+(slot.ts||0)+'"'
                            + ' data-ts-fine="'+(slot.ts_fine||0)+'"';
                    } else {
                        var tipo = slot.tipo_blocco || 'interno';
                        cls += 'mssgag-slot-busy-'+tipo;
                        if (forAdmin) {
                            var labelMap={'richiesta':'\uD83D\uDCE8 Richiesta','confermato':'\u2705 Confermato','interno':'\uD83D\uDD12 Blocco','admin_fissato':'\uD83D\uDCC5 Fissato'};
                            label = slot.titolo || labelMap[tipo] || '\uD83D\uDCC5';
                        } else { label = '\u25CF'; }
                        if (forAdmin && slot.blocco_id) {
                            attrs += ' data-blocco-id="'+slot.blocco_id+'"'
                                + ' data-tipo="'+(slot.tipo_blocco||'interno')+'"';
                        }
                    }
                    html += '<td><div class="'+cls+'" '+attrs+'>'+label+'</div></td>';
                });
                html += '</tr>';
            });

            if (times.length === 0) {
                html += '<tr><td colspan="'+(giorni.length+1)+'" style="text-align:center;padding:30px;color:var(--msslu-text-muted);font-size:13px">Nessun orario lavorativo configurato per questa settimana.</td></tr>';
            }

            html += '</tbody></table>';
            $('#mssgag-calendar').html(html);
        }

        /* \u2500\u2500 Multi-slot selection \u2500\u2500 */
        var selectedSlots = []; /* [{datetime, label}] */
        var usersData    = window._mssgagUsers || [];
        var cantieriData = window._mssgagCantieri || [];
        var destData     = {}; /* {uid: {id,nome,ruolo}} */

        window.mssgagClickSlot = function(el) {
            var dt   = $(el).data('datetime');
            var d    = new Date(dt.replace(' ','T'));
            var pad  = function(n){return String(n).padStart(2,'0');};
            var lbl  = pad(d.getDate())+'/'+pad(d.getMonth()+1)+' '+pad(d.getHours())+':'+pad(d.getMinutes());
            var idx  = selectedSlots.findIndex(function(s){return s.datetime===dt;});
            if (idx !== -1) {
                /* Deseleziona */
                selectedSlots.splice(idx, 1);
                $(el).removeClass('mssgag-slot-selected');
            } else {
                /* Seleziona */
                var tsStart  = parseInt($(el).data('ts'))      || 0;
                var tsFine   = parseInt($(el).data('ts-fine'))  || 0;
                selectedSlots.push({datetime:dt, label:lbl, ts:tsStart, ts_fine:tsFine});
                $(el).addClass('mssgag-slot-selected');
            }
            updateSlotBar();
        };

        function updateSlotBar() {
            var n = selectedSlots.length;
            $('#mssgag-slot-count').text(n ? '('+n+' slot selezionati)' : '(seleziona slot dal calendario)');
        }

        /* Click su blocco esistente */
        window.mssgagClickBlocco = function(el) {
            var id = $(el).data('blocco-id');
            if (!id) return;
            if (!confirm('Eliminare questo blocco?')) return;
            window.mssgagAjax(
                {action:'mssgag_delete_blocco',nonce:MSSGAG.nonce,blocco_id:id},
                function() { loadWeek(currentWeekStart); }
            );
        };

        /* Click su un mini-blocco dentro uno slot con più appuntamenti:
           apre la card di dettaglio dell'appuntamento cliccato (fetch on-demand). */
        window.mssgagClickBloccoId = function(id) {
            id = parseInt(id) || 0;
            if (!id) return;
            window.mssgagAjax(
                {action:'mssgag_get_blocco_detail',nonce:MSSGAG.nonce,blocco_id:id},
                function(d) {
                    if (typeof openSlotCard === 'function') {
                        openSlotCard(id, d.titolo, d.tipo, d.luogo, d.nota, d.partecipante_nome, d.cliente_id, d.cantiere_nome, d.cantiere_id, d.data_inizio, d.data_fine);
                    }
                }
            );
        };

        /* Tabs */
        $(document).on('click','.mssgag-tab',function(){
            var tab=$(this).data('tab');
            $('.mssgag-tab').removeClass('active'); $(this).addClass('active');
            $('.mssgag-panel').removeClass('active'); $('.mssgag-panel[data-tab="'+tab+'"]').addClass('active');
        });

        /* Nav settimana
           NB: binding DELEGATO su document invece che diretto su $('#id').
           Questo file (agenda.js) viene caricato una sola volta al caricamento
           della pagina (wp_enqueue_script), ma il markup della sezione Agenda
           viene inserito DOPO, via AJAX, quando l'utente clicca sulla voce di
           menu "Agenda" (architettura SPA a sezioni). Un binding diretto su
           $('#mssgag-prev-week') eseguito qui troverebbe zero elementi (non
           ancora nel DOM) e non si aggancerebbe mai agli elementi reali
           inseriti in seguito: risultato, calendario bloccato su "Caricamento…"
           e tutti i pulsanti del modulo Agenda non rispondenti. La delega
           risolve il problema perché il listener resta su document e
           intercetta i click anche su elementi che non esistono ancora al
           momento del binding. */
        $(document).on('click','#mssgag-prev-week',function(){
            var d=new Date(currentWeekStart+' 12:00'); d.setDate(d.getDate()-7);
            loadWeek(d.toISOString().split('T')[0]);
        });
        $(document).on('click','#mssgag-next-week',function(){
            var d=new Date(currentWeekStart+' 12:00'); d.setDate(d.getDate()+7);
            loadWeek(d.toISOString().split('T')[0]);
        });
        $(document).on('click','#mssgag-today',function(){ loadWeek(getMonday(new Date())); });
        $(document).on('click','#mssgag-add-blocco-btn',function(){ $('#mssgag-form-blocco').toggle(); });
        $(document).on('click','#mssgag-cancel-blocco',function(){ $('#mssgag-form-blocco').hide(); });

        /* Salva blocco */
        $(document).on('click','#mssgag-save-blocco',function(){
            var inizio=$('#mssgag-b-inizio').val(); var fine=$('#mssgag-b-fine').val();
            var $n=$('#mssgag-blocco-notice');
            if(!inizio||!fine){$n.show().css({background:'rgba(239,68,68,.1)',color:'#ef4444'}).text('Inserisci data inizio e fine.');return;}
            var $btn=$(this); $btn.prop('disabled',true);
            window.mssgagAjax({
                action:'mssgag_add_blocco',nonce:MSSGAG.nonce,
                data_inizio:inizio.replace('T',' '),
                data_fine:fine.replace('T',' '),
                titolo:$('#mssgag-b-titolo').val(),
                tipo:$('#mssgag-b-tipo').val()
            },function(){
                $n.show().css({background:'rgba(74,222,128,.1)',color:'#22c55e'}).text('Salvato!');
                $('#mssgag-b-inizio,#mssgag-b-fine,#mssgag-b-titolo').val('');
                setTimeout(function(){$('#mssgag-form-blocco').hide(); loadWeek(currentWeekStart);},800);
                $btn.prop('disabled',false);
            },function(msg){
                $n.show().css({background:'rgba(239,68,68,.1)',color:'#ef4444'}).text(msg);
                $btn.prop('disabled',false);
            });
        });

        /* Salva orari */
        $(document).on('click','#mssgag-save-orari',function(){
            /* Propaga slot_min globale */
            var slotMin=$('#mssgag-slot-min-global').val();
            $('.mssgag-slot-min-input').val(slotMin);

            var data={action:'mssgag_save_orari',nonce:MSSGAG.nonce,orari:{}};
            for(var g=1;g<=7;g++){
                var $row=$('#mssgag-gg-'+g);
                var attivo=$row.find('input[type=checkbox]').is(':checked')?1:0;
                var oi=$row.find('input[type=time]').eq(0).val();
                var of=$row.find('input[type=time]').eq(1).val();
                data.orari[g]={attivo:attivo,ora_inizio:oi,ora_fine:of,slot_min:slotMin};
            }
            var $btn=$(this); $btn.prop('disabled',true).text('Salvataggio\u2026');
            var $n=$('#mssgag-orari-notice');
            window.mssgagAjax(data,function(){
                $n.show().css({background:'rgba(74,222,128,.1)',color:'#22c55e'}).text('\u2713 Orari salvati!');
                setTimeout(function(){ loadWeek(currentWeekStart); }, 400);
                $btn.prop('disabled',false).text('\uD83D\uDCBE Salva orari');
                setTimeout(function(){$n.fadeOut();},3000);
            },function(msg){
                $n.show().css({background:'rgba(239,68,68,.1)',color:'#ef4444'}).text(msg);
                $btn.prop('disabled',false).text('\uD83D\uDCBE Salva orari');
                setTimeout(function(){$n.fadeOut();},3000);
            });
        });

        /* Toggle giorno on/off */
        window.mssgagToggleGiorno = function(g, on) {
            var $orari=$('#mssgag-orari-'+g);
            $orari.css({opacity:on?1:.3,'pointer-events':on?'auto':'none'});
            $('#mssgag-gg-'+g).toggleClass('mssgag-giorno-off',!on);
        };

        /* Richieste: conferma */
        $(document).on('click','.mssgag-conferma-btn',function(){
            var id=$(this).data('id'); var $card=$('#mssgag-req-'+id);
            var $confBtn=$(this).prop('disabled',true).text('\u2026');
            window.mssgagAjax(
                {action:'mssgag_rispondi_richiesta',nonce:MSSGAG.nonce,blocco_id:id,azione:'conferma',risposta:''},
                function() { $card.animate({opacity:0},300,function(){$(this).html('<div style="padding:12px;color:#22c55e;font-size:13px">\u2705 Confermato \u2014 email inviata al cliente.</div>');$(this).animate({opacity:1},200);}); },
                function(msg) { window.mssgagNotifyError(msg); $confBtn.prop('disabled',false).text('\u2705 Conferma'); }
            );
        });

        /* Richieste: mostra form rifiuto */
        $(document).on('click','.mssgag-rifiuta-toggle',function(){
            var id=$(this).data('id');
            $('.mssgag-rifiuta-form[data-id="'+id+'"]').slideToggle(200);
        });
        $(document).on('click','.mssgag-rifiuta-btn',function(){
            var id=$(this).data('id'); var $card=$('#mssgag-req-'+id);
            var nota=$('.mssgag-rifiuta-form[data-id="'+id+'"] .mssgag-rifiuto-nota').val();
            var $rifBtn=$(this).prop('disabled',true).text('\u2026');
            window.mssgagAjax(
                {action:'mssgag_rispondi_richiesta',nonce:MSSGAG.nonce,blocco_id:id,azione:'rifiuta',risposta:nota},
                function() { $card.animate({opacity:0},300,function(){$(this).html('<div style="padding:12px;color:rgba(239,68,68,.8);font-size:13px">\u2717 Rifiutato \u2014 email inviata al cliente.</div>');$(this).animate({opacity:1},200);}); },
                function(msg) { window.mssgagNotifyError(msg); $rifBtn.prop('disabled',false).text('Conferma rifiuto'); }
            );
        });

        /* Init */
        /* Click sul link configura orari nel banner */
        $(document).on('click', '.mssgag-goto-orari', function(e) {
            e.preventDefault();
            $('.mssgag-tab[data-tab="orari"]').trigger('click');
        });

        /* ── Caricamento calendario: al primo parse E ad ogni ricaricamento
           della sezione Agenda via AJAX (vedi nota sopra sul binding delegato).
           Senza questo hook, loadWeek() veniva chiamata una sola volta, subito
           al caricamento della pagina — spesso prima ancora che la sezione
           Agenda fosse presente nel DOM — lasciando il calendario bloccato
           su "Caricamento…" per sempre una volta aperta la sezione. ── */
        function mssgagInitIfPresent() {
            if ($('#mssgag-admin-wrap').length) loadWeek(getMonday(new Date()));
        }
        mssgagInitIfPresent();
        $(document).on('ajaxComplete', function(e, xhr, settings) {
            if (!settings.data || settings.data.indexOf('msslu_load_section') === -1) return;
            try { var res = JSON.parse(xhr.responseText); if (!res.success) return; } catch(err) { return; }
            setTimeout(mssgagInitIfPresent, 50);
        });

        /* \u2500\u2500 Form appuntamento: modo destinatario \u2500\u2500 */
        $(document).on('click','.mssgag-dest-mode',function(){
            var mode=$(this).data('mode');
            $('.mssgag-dest-mode').css({background:'transparent',border:'1px solid var(--msslu-box-border)',color:'var(--msslu-text)'});
            $(this).css({background:'rgba(233,30,140,.1)',border:'1.5px solid var(--msslu-accent,#e91e8c)',color:'var(--msslu-accent,#e91e8c)'});
            $('#mssgag-dest-ricerca').toggle(mode==='ricerca');
            $('#mssgag-dest-cantiere').toggle(mode==='cantiere');
        });

        /* Ricerca live utenti.
           NB: legge window._mssgagUsers al momento dell'uso (non la cache
           usersData impostata una sola volta al parse del file) perché quei
           dati vengono scritti dallo script inline della sezione Agenda, che
           viene rieseguito ad ogni ricaricamento AJAX della sezione — mentre
           questo file viene eseguito una sola volta al caricamento pagina,
           tipicamente PRIMA che la sezione esista, quindi la cache iniziale
           risultava sempre vuota. */
        $(document).on('input','#mssgag-bk-search',function(){
            var q=$(this).val().toLowerCase().trim();
            $('#mssgag-bk-drop').hide().html('');
            if(q.length<2) return;
            var liveUsers=window._mssgagUsers||usersData||[];
            var res=liveUsers.filter(function(u){return u.nome.toLowerCase().indexOf(q)!==-1||u.email.toLowerCase().indexOf(q)!==-1;}).slice(0,8);
            if(!res.length) return;
            var h='';
            res.forEach(function(u){
                var inDest=!!destData[u.id];
                h+='<div class="mssgag-bk-user-row" data-id="'+u.id+'" data-nome="'+$('<div>').text(u.nome).html()+'" data-ruolo="utente"'
                    +' style="padding:8px 12px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px;'+(inDest?'opacity:.5':'')+';">'
                    +'<span>'+$('<div>').text(u.nome).html()+'</span>'
                    +'<span style="font-size:11px;color:var(--msslu-text-muted)">'+u.email+'</span>'
                    +(inDest?'<span style="margin-left:auto;color:#22c55e;font-size:11px">\u2713</span>':'')
                    +'</div>';
            });
            $('#mssgag-bk-drop').html(h).show();
        });
        $(document).on('click','.mssgag-bk-user-row',function(){
            addDest($(this).data('id'),$(this).data('nome'),'utente');
            $('#mssgag-bk-search').val(''); $('#mssgag-bk-drop').hide();
        });
        $(document).on('click',function(e){if(!$(e.target).closest('#mssgag-bk-search,#mssgag-bk-drop').length)$('#mssgag-bk-drop').hide();});

        /* Cantiere \u2192 team (stesso motivo del blocco ricerca sopra: legge
           window._mssgagCantieri fresco invece della cache impostata una
           sola volta al parse del file) */
        $(document).on('change','#mssgag-bk-cant',function(){
            var cid=parseInt($(this).val());
            var luogo=$(this).find('option:selected').data('luogo')||'';
            if(luogo) $('#mssgag-bk-luogo').val(luogo);
            var liveCantieri=window._mssgagCantieri||cantieriData||[];
            var cant=liveCantieri.find(function(c){return c.id===cid;});
            var $tl=$('#mssgag-bk-team').html('');
            if(!cant||!cant.team||!cant.team.length){$tl.html('<span style="font-size:12px;color:var(--msslu-text-muted)">Nessun membro assegnato.</span>');return;}
            cant.team.forEach(function(m){
                var inDest=!!destData[m.id];
                $tl.append('<label style="display:flex;align-items:center;gap:8px;padding:6px 8px;background:var(--msslu-input-bg);border:0.5px solid var(--msslu-box-border);border-radius:7px;cursor:pointer;font-size:12px">'
                    +'<input type="checkbox" class="mssgag-team-chk" data-id="'+m.id+'" data-nome="'+$('<div>').text(m.nome).html()+'" data-ruolo="'+m.ruolo+'" '+(inDest?'checked':'')+' style="accent-color:var(--msslu-accent)">'
                    +'<span>'+$('<div>').text(m.nome).html()+'</span>'
                    +'<span style="font-size:10px;color:var(--msslu-text-muted);margin-left:auto">'+m.ruolo+'</span>'
                    +'</label>');
            });
        });
        $(document).on('change','.mssgag-team-chk',function(){
            var id=$(this).data('id'),nome=$(this).data('nome'),ruolo=$(this).data('ruolo');
            $(this).is(':checked')?addDest(id,nome,ruolo):removeDest(id);
        });

        /* Gestione destinatari */
        function addDest(id,nome,ruolo){ id=parseInt(id); if(destData[id])return; destData[id]={id:id,nome:nome,ruolo:ruolo}; renderDest(); }
        function removeDest(id){ delete destData[id]; renderDest(); }
        function renderDest(){
            var $b=$('#mssgag-bk-dest-badges').html('');
            Object.values(destData).forEach(function(d){
                $b.append('<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;background:var(--msslu-input-bg);border:1px solid var(--msslu-box-border);border-radius:999px;font-size:12px">'
                    +$('<div>').text(d.nome).html()
                    +' <button class="mssgag-rem-dest" data-id="'+d.id+'" style="background:none;border:none;color:rgba(239,68,68,.6);cursor:pointer;font-size:11px">\u2715</button>'
                    +'</span>');
            });
        }
        $(document).on('click','.mssgag-rem-dest',function(e){e.stopPropagation();removeDest($(this).data('id'));});

        /* Salva appuntamento (uno per ogni slot selezionato \u00D7 ogni destinatario) */
        $(document).on('click','#mssgag-bk-save',function(){
            var titolo=$('#mssgag-bk-titolo').val().trim();
            var $n=$('#mssgag-booking-notice');
            if(!titolo){$n.show().css({background:'rgba(239,68,68,.1)',color:'#ef4444'}).text('Inserisci un titolo.');return;}
            if(!selectedSlots.length){$n.show().css({background:'rgba(239,68,68,.1)',color:'#ef4444'}).text('Seleziona almeno uno slot dal calendario.');return;}
            var dests=Object.values(destData);
            if(!dests.length) dests=[{id:0}]; /* nessun destinatario = promemoria */
            var $btn=$(this); $btn.prop('disabled',true).text('Salvataggio...'); $n.hide();

            /* \u2500\u2500 Raggruppa slot sequenziali in un unico appuntamento \u2500\u2500 */
            var sorted=[].concat(selectedSlots).sort(function(a,b){return a.ts-b.ts;});
            var groups=[];
            var cur=null;
            sorted.forEach(function(s){
                if(!cur){
                    cur={dt:s.datetime, ts_start:s.ts, ts_end:s.ts_fine};
                } else if(s.ts>0 && s.ts===cur.ts_end){
                    /* Slot adiacente: estendi il gruppo */
                    cur.ts_end=s.ts_fine;
                } else {
                    groups.push(cur);
                    cur={dt:s.datetime, ts_start:s.ts, ts_end:s.ts_fine};
                }
            });
            if(cur) groups.push(cur);
            /* Ogni gruppo diventa un appuntamento, durata = differenza in minuti */
            var jobs=[];
            groups.forEach(function(g){
                var durata=g.ts_start>0?Math.round((g.ts_end-g.ts_start)/60):60;
                dests.forEach(function(d){ jobs.push({dt:g.dt, durata:durata, uid:d.id}); });
            });
            var done=0, errors=[];
            jobs.forEach(function(job){
                $.post(MSSGAG.ajax_url,{
                    action:'mssgag_admin_save_appuntamento',nonce:MSSGAG.nonce,
                    titolo:titolo, data_ora:job.dt,
                    durata:job.durata||60,
                    luogo:$('#mssgag-bk-luogo').val(),
                    note:$('#mssgag-bk-note').val(),
                    user_id:job.uid,
                    cantiere_id:$('#mssgag-bk-cant').val()||0,
                    notifica:$('#mssgag-bk-email').is(':checked')?1:0,
                    notifica_email:$('#mssgag-notifica-email').is(':checked')?1:0,
                    notifica_minuti:parseInt($('#mssgag-notifica-minuti').val()||60)
                },function(r){
                    done++;
                    if(!r.success) errors.push(r.data&&r.data.msg?r.data.msg:'Errore');
                    if(done===jobs.length){
                        if(!errors.length){
                            $n.show().css({background:'rgba(74,222,128,.1)',color:'#22c55e'})
                              .text('\u2713 '+(jobs.length>1?jobs.length+' appuntamenti':'Appuntamento')+' fissati!');
                            /* Reset */
                            selectedSlots=[]; destData={};
                            $('.mssgag-slot-selected').removeClass('mssgag-slot-selected');
                            updateSlotBar(); renderDest();
                            $('#mssgag-bk-titolo,#mssgag-bk-luogo,#mssgag-bk-note,#mssgag-bk-search').val('');
                            $('#mssgag-bk-cant').val('0'); $('#mssgag-bk-team').html('');
                            $('#mssgag-slot-count').text('(seleziona slot dal calendario)');
                            loadWeek(currentWeekStart);
                            setTimeout(function(){$n.fadeOut(300);},4000);
                        } else {
                            $n.show().css({background:'rgba(239,68,68,.1)',color:'#ef4444'}).text(errors.join(' '));
                        }
                        $btn.prop('disabled',false).text('\uD83D\uDCC5 Fissa appuntamento');
                    }
                }).fail(function(){
                    done++; errors.push('Errore connessione');
                    if(done===jobs.length){$n.show().css('color','#ef4444').text(errors.join(' '));$btn.prop('disabled',false).text('\uD83D\uDCC5 Fissa appuntamento');}
                });
            });
        });

        /* Aggiungi blocco interno (vecchio form) */

    
        /* \u2500\u2500 Promemoria: pannello collassabile (click sull'header) \u2500\u2500 */
        $(document).on('click','#mssgag-prom-header',function(e){
            if ($(e.target).closest('#mssgag-btn-nuovo-prom').length) return;
            var $body=$('#mssgag-prom-body'), $chev=$('#mssgag-prom-chevron');
            $body.slideToggle(200);
            $chev.css('transform', $body.is(':visible') ? 'rotate(0deg)' : 'rotate(-90deg)');
        });
        /* \u2500\u2500 Form promemoria nell'agenda \u2500\u2500 */
        $(document).on('click','#mssgag-btn-nuovo-prom',function(e){
            e.stopPropagation();
            var $form=$('#mssgag-form-promemoria');
            var $body=$('#mssgag-prom-body');
            if ($body.is(':hidden')) {
                $body.slideDown(200);
                $('#mssgag-prom-chevron').css('transform','rotate(0deg)');
            }
            if ($form.is(':visible')) {
                /* gi\u00e0 aperto: un secondo click sul bottone lo richiude */
                $form.slideUp(200);
                return;
            }
            $('#mssgag-prom-id').val('');
            $('#mssgag-prom-titolo').val('');
            $('#mssgag-prom-durata').val('');
            $('#mssgag-prom-notifica').prop('checked',false);
            var d=new Date(); d.setHours(d.getHours()+1,0,0,0);
            var pad=function(n){return n<10?'0'+n:n;};
            $('#mssgag-prom-data').val(d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':00');
            $form.slideDown(200);
            $('#mssgag-prom-titolo').focus();
        });
        $(document).on('click','#mssgag-prom-annulla',function(){ $('#mssgag-form-promemoria').slideUp(200); });
        $(document).on('click','#mssgag-prom-salva',function(){
            var titolo=$('#mssgag-prom-titolo').val().trim();
            var dataRaw=$('#mssgag-prom-data').val();
            if(!titolo||!dataRaw){window.mssgagNotifyError('Titolo e data obbligatori.');return;}
            var data=dataRaw.replace('T',' ')+':00';
            var $btn=$(this); $btn.prop('disabled',true).text('\u2026');
            var $n=$('#mssgag-prom-notice');
            window.mssgagAjax({action:'mssgag_salva_promemoria',nonce:MSSGAG.nonce,
                id:$('#mssgag-prom-id').val(),titolo:titolo,data_ora:data,
                durata_min:$('#mssgag-prom-durata').val()||0,
                notifica_email:$('#mssgag-prom-notifica').is(':checked')?1:0,
                notifica_minuti:parseInt($('#mssgag-prom-minuti').val()||60)
            },function(){
                $n.show().css({background:'rgba(168,85,247,.15)',color:'rgba(168,85,247,.9)'}).text('Salvato!');
                $('#mssgag-form-promemoria').slideUp(200);
                $('#mssgag-prom-id').val('');
                loadWeek(currentWeekStart);
                setTimeout(function(){location.reload();},800);
                $btn.prop('disabled',false).text('\uD83D\uDCBE Salva');
            },function(msg){
                $n.show().css({background:'rgba(239,68,68,.1)',color:'#ef4444'}).text(msg);
                $btn.prop('disabled',false).text('\uD83D\uDCBE Salva');
            });
        });
        /* Elimina promemoria dalla lista + ricarica calendario */
        $(document).on('click','.mssgag-elimina-prom',function(e){
            e.stopPropagation();
            if(!confirm('Eliminare questo promemoria?'))return;
            var id=$(this).data('id');
            var $row=$(this).closest('[style*="rgba(168,85,247"]');
            window.mssgagAjax(
                {action:'mssgag_elimina_promemoria',nonce:MSSGAG.nonce,id:id},
                function() { $row.fadeOut(300,function(){$(this).remove();}); loadWeek(currentWeekStart); }
            );
        });

    })(jQuery);

    /* \u2500\u2500 Click su slot occupato: card modale con dettagli \u2500\u2500 */
    var spostaModeData = null; /* {blocco_id, durata_min} se in modalit\u00E0 sposta */

    function openSlotCard(bid, titolo, tipo, luogo, nota, pNome, pId, cNome, cId, dtStart, dtFine) {
        jQuery('#mssgag-slot-card').remove();
        var labelT={richiesta:'\u23F3 Richiesta',confermato:'\u2705 Confermato',admin_fissato:'\uD83D\uDCC5 Fissato',interno:'\uD83D\uDD12 Blocco',promemoria:'\uD83D\uDCCC Promemoria'};
        var canEdit=(tipo==='admin_fissato'||tipo==='interno'||tipo==='confermato');
        var isPromemoria=(tipo==='promemoria');

        /* Formatta orari */
        var fmtDt=function(s){if(!s)return'';var d=new Date(s.replace(' ','T'));var pad=function(n){return String(n).padStart(2,'0');};return pad(d.getDate())+'/'+pad(d.getMonth()+1)+'/'+d.getFullYear()+' '+pad(d.getHours())+':'+pad(d.getMinutes());};
        var fmtOra=function(s){if(!s)return'';var d=new Date(s.replace(' ','T'));var pad=function(n){return String(n).padStart(2,'0');};return pad(d.getHours())+':'+pad(d.getMinutes());};

        var html='<div id="mssgag-slot-card" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:99999;'
            +'background:var(--msslu-box-bg,#1e1e2e);border:1px solid var(--msslu-box-border);border-radius:14px;'
            +'padding:20px;min-width:300px;max-width:380px;width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.6)">'
            /* Overlay */
            +'<div id="mssgag-slot-card-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:-1"></div>'
            /* Header */
            +'<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">'
            +'<div>'
            +'<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--msslu-text-muted);margin-bottom:3px">'+(labelT[tipo]||tipo)+'</div>'
            +(titolo?'<div style="font-size:15px;font-weight:700;color:var(--msslu-text)">'+jQuery('<div>').text(titolo).html()+'</div>':'')
            +'</div>'
            +'<button id="mssgag-card-close" style="background:none;border:none;color:var(--msslu-text-muted);cursor:pointer;font-size:18px;padding:0;line-height:1;margin-left:12px">\u2715</button>'
            +'</div>'
            /* Orario */
            +'<div style="display:flex;align-items:center;gap:8px;padding:10px;background:rgba(255,255,255,.04);border-radius:8px;margin-bottom:10px">'
            +'<span style="font-size:20px">\uD83D\uDCC5</span>'
            +'<div><div style="font-size:13px;font-weight:600;color:var(--msslu-text)">'+fmtDt(dtStart)+'</div>'
            +(dtFine?'<div style="font-size:12px;color:var(--msslu-text-muted)">fino alle '+fmtOra(dtFine)+'</div>':'')
            +'</div></div>'
            /* Partecipante */
            +(pNome?'<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px">'
            +'<span>\uD83D\uDC64</span>'
            +(pId?'<a class="mssgag-nav-cliente" data-uid="'+pId+'" href="#" style="color:var(--msslu-accent,#e91e8c);text-decoration:none;font-weight:500">'+jQuery('<div>').text(pNome).html()+' \u2197</a>':'<span>'+jQuery('<div>').text(pNome).html()+'</span>')
            +'</div>':'')
            /* Cantiere */
            +(cNome?'<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px">'
            +'<span>\uD83C\uDFD7</span>'
            +(cId?'<a class="mssgag-nav-cantiere" data-cid="'+cId+'" href="#" style="color:var(--msslu-accent,#e91e8c);text-decoration:none;font-weight:500">'+jQuery('<div>').text(cNome).html()+' \u2197</a>':'<span>'+jQuery('<div>').text(cNome).html()+'</span>')
            +'</div>':'')
            /* Luogo */
            +(luogo?'<div style="font-size:12px;color:var(--msslu-text-muted);margin-bottom:8px">\uD83D\uDCCD '+jQuery('<div>').text(luogo).html()+'</div>':'')
            /* Note */
            +(nota?'<div style="font-size:12px;color:var(--msslu-text-muted);margin-bottom:8px;font-style:italic">\uD83D\uDCAC '+jQuery('<div>').text(nota).html()+'</div>':'')
            /* Bottoni */
            +(canEdit||isPromemoria?'<div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">'            +(!isPromemoria?'<button class="mssgag-card-sposta" data-bid="'+bid+'" data-start="'+dtStart+'" data-fine="'+dtFine+'"'            +' style="flex:1;padding:8px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);border-radius:8px;color:#818cf8;font-size:12px;font-weight:600;cursor:pointer">\uD83D\uDCC5 Sposta</button>':'')            +(isPromemoria?'<button class="mssgag-prom-card-modifica" data-id="'+bid+'" data-titolo="'+jQuery('<div>').text(titolo).html()+'" data-start="'+dtStart+'" data-fine="'+dtFine+'"'            +' style="flex:1;padding:8px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);border-radius:8px;color:#818cf8;font-size:12px;font-weight:600;cursor:pointer">\u270F\uFE0F Modifica</button>':'')            +'<button class="'+(isPromemoria?'mssgag-prom-card-elimina':'mssgag-card-elimina')+'" data-bid="'+bid+'" data-id="'+bid+'"'            +' style="flex:1;padding:8px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#ef4444;font-size:12px;font-weight:600;cursor:pointer">\uD83D\uDDD1 Elimina</button>'            +'</div>':'')
            +'</div>';
        jQuery('body').append(html);
    }

    jQuery(document).off('click.ag-slot-card').on('click.ag-slot-card',
        '.mssgag-slot-busy-richiesta,.mssgag-slot-busy-confermato,.mssgag-slot-busy-admin_fissato,.mssgag-slot-busy-interno,.mssgag-slot-busy,.mssgag-slot-busy-promemoria',
        function(e){
            e.stopPropagation();
            var $s=jQuery(this);
            var bid=parseInt($s.data('blocco-id')||0);
            if(!bid) return;
            /* Fetch dettagli on-demand */
            window.mssgagAjax(
                {action:'mssgag_get_blocco_detail',nonce:MSSGAG.nonce,blocco_id:bid},
                function(d) { openSlotCard(bid,d.titolo,d.tipo,d.luogo,d.nota,d.partecipante_nome,d.cliente_id,d.cantiere_nome,d.cantiere_id,d.data_inizio,d.data_fine); }
            );
        }
    );

    /* Chiudi card */
    jQuery(document).on('click','#mssgag-card-close,#mssgag-slot-card-overlay',function(){
        jQuery('#mssgag-slot-card').remove();
        if(spostaModeData){ spostaModeData=null; jQuery('#mssgag-sposta-banner').remove(); }
    });

    /* Naviga a scheda cliente */
    jQuery(document).on('click','.mssgag-nav-cliente',function(e){
        e.preventDefault();
        var uid=jQuery(this).data('uid');
        jQuery('#mssgag-slot-card').remove();
        /* Naviga alla sezione clienti */
        var $nav=jQuery('[data-section="mssg_clienti"],.msslu-nav-link[href*="clienti"]').first();
        if($nav.length) $nav.trigger('click');
    });

    /* Naviga a scheda cantiere */
    jQuery(document).on('click','.mssgag-nav-cantiere',function(e){
        e.preventDefault();
        var cid=jQuery(this).data('cid');
        jQuery('#mssgag-slot-card').remove();
        var $nav=jQuery('[data-section="mssg_cantieri"],.msslu-nav-link[href*="cantieri"]').first();
        if($nav.length) $nav.trigger('click');
        /* Apri scheda cantiere dopo navigazione */
        setTimeout(function(){
            jQuery('.mssgc-open-cantiere[data-id="'+cid+'"]').first().trigger('click');
        },500);
    });

    /* Elimina */
    jQuery(document).on('click','.mssgag-card-elimina',function(){
        if(!confirm('Eliminare questo appuntamento?'))return;
        var bid=jQuery(this).data('bid');
        jQuery('#mssgag-slot-card').remove();
        /* NB: siamo fuori dalla IIFE principale — loadWeek/currentWeekStart locali
           non sono visibili qui, va usato l'alias globale window.mssgagLoadWeek. */
        window.mssgagAjax(
            {action:'mssgag_admin_delete_appuntamento',nonce:MSSGAG.nonce,app_id:bid},
            function() { window.mssgagLoadWeek(window.currentWeekStart); }
        );
    });

    /* Sposta: entra in modalit\u00E0 selezione slot */
    jQuery(document).on('click','.mssgag-card-sposta',function(){
        var bid=jQuery(this).data('bid');
        var dtStart=jQuery(this).data('start');
        var dtFine=jQuery(this).data('fine');
        /* Calcola durata in minuti */
        var durMin=60;
        if(dtStart&&dtFine){
            var ms=new Date(dtFine.replace(' ','T'))-new Date(dtStart.replace(' ','T'));
            durMin=Math.round(ms/60000)||60;
        }
        spostaModeData={blocco_id:bid, durata_min:durMin};
        jQuery('#mssgag-slot-card').remove();
        /* Banner di istruzione */
        jQuery('#mssgag-sposta-banner').remove();
        jQuery('#mssgag-calendar-wrap').before(
            '<div id="mssgag-sposta-banner" style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.35);border-radius:9px;margin-bottom:10px;font-size:13px">'
            +'<span style="color:#818cf8;font-weight:600">\uD83D\uDCC5 Seleziona il nuovo slot per spostare l\'appuntamento</span>'
            +'<button id="mssgag-sposta-annulla" style="margin-left:auto;background:none;border:none;color:rgba(239,68,68,.7);cursor:pointer;font-size:12px">\u2715 Annulla</button>'
            +'</div>'
        );
    });
    jQuery(document).on('click','#mssgag-sposta-annulla',function(){
        spostaModeData=null; jQuery('#mssgag-sposta-banner').remove();
    });

    /* Elimina promemoria dalla card */
    jQuery(document).on('click','.mssgag-prom-card-elimina',function(){
        if(!confirm('Eliminare questo promemoria?'))return;
        var id=parseInt(jQuery(this).data('id')||jQuery(this).data('bid')||0);
        jQuery('#mssgag-slot-card,#mssgag-slot-card-overlay').remove();
        window.mssgagAjax(
            {action:'mssgag_elimina_promemoria',nonce:MSSGAG.nonce,id:id},
            function() { window.mssgagLoadWeek(window.currentWeekStart); }
        );
    });

    /* Modifica promemoria dalla card */
    jQuery(document).on('click','.mssgag-prom-card-modifica',function(){
        var id=jQuery(this).data('id');
        var titolo=jQuery(this).attr('data-titolo');
        var dtStart=jQuery(this).data('start');
        jQuery('#mssgag-slot-card,#mssgag-slot-card-overlay').remove();
        jQuery('#mssgag-prom-id').val(id);
        jQuery('#mssgag-prom-titolo').val(titolo||'');
        if(dtStart){
            var d=new Date((dtStart+'').replace(' ','T'));
            var pad=function(n){return n<10?'0'+n:''+n;};
            jQuery('#mssgag-prom-data').val(d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes()));
        }
        jQuery('#mssgag-form-promemoria').slideDown(200);
        jQuery('#mssgag-prom-titolo').focus();
        jQuery('html,body').animate({scrollTop:jQuery('#mssgag-form-promemoria').offset().top-80},300);
    });

    /* Click su slot libero in modalit\u00E0 Sposta */
    var origClickSlot = window.mssgagClickSlot;
    window.mssgagClickSlot = function(el) {
        if(spostaModeData) {
            /* Modalit\u00E0 sposta: muovi l'appuntamento
               (fuori dalla IIFE principale \u2014 usa l'alias globale window.mssgagLoadWeek) */
            var dt=jQuery(el).data('datetime');
            window.mssgagAjax({
                action:'mssgag_sposta_appuntamento',nonce:MSSGAG.nonce,
                blocco_id:spostaModeData.blocco_id,
                new_start:dt,
                durata_min:spostaModeData.durata_min
            },function(){
                spostaModeData=null;
                jQuery('#mssgag-sposta-banner').remove();
                window.mssgagLoadWeek(window.currentWeekStart);
            });
        } else {
            origClickSlot(el);
        }
    };;
    jQuery(document).on('click','#mssgag-detail-close',function(){jQuery('#mssgag-slot-detail').remove();});
    /* Elimina dall'agenda (solo slot admin) */
    jQuery(document).on('click','.mssgag-slot-delete-btn',function(){
        if(!confirm('Eliminare questo appuntamento?'))return;
        var bid=jQuery(this).data('bid');
        jQuery('#mssgag-slot-detail').remove();
        window.mssgagAjax({
            action:'mssgag_admin_delete_appuntamento',
            nonce:typeof MSSGAG!=='undefined'?MSSGAG.nonce:(typeof MSSG!=='undefined'?MSSG.nonce:''),
            app_id:bid
        },function(){
            /* Ricarica il calendario */
            if(typeof window.mssgagLoadWeek==='function') window.mssgagLoadWeek(window.currentWeekStart);
            else location.reload();
        });
    });

    /* Mobile: scroll orizzontale invece di 3 giorni \u2014 gestito da CSS */
    /* (rimossa la logica di column-hiding: l'utente scorre la tabella orizzontalmente) */
