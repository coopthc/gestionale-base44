/* MSS Gestionale — Cantieri JS v3.1.0 — fix completo */
(function($){
'use strict';

$(document).on('ajaxComplete',function(e,xhr,s){
    if(!s.data||s.data.indexOf('msslu_load_section')===-1)return;
    try{var r=JSON.parse(xhr.responseText);if(!r.success)return;}catch(e){return;}
    setTimeout(function(){
        var $m=$('.msslu-account-main,[id*="section-main"]').first();
        if($m.find('.mssgc-section').length)MSSGCV3.init($m);
    },50);
});

$(function(){
    var $m=$('.msslu-account-main,[id*="section-main"]').first();
    if($m.find('.mssgc-section').length)MSSGCV3.init($m);
});

window.MSSGCV3={
    _chatTimer:null,
    _chatLastId:0,

    init:function($w){
        $w.off('.mssgcv3');
        $w.on('click.mssgcv3','#mssgc-btn-nuovo,#mssgc-btn-nuovo-empty',function(){MSSGCV3.openForm(0,$w);});
        $w.on('click.mssgcv3','.mssgc-open-cantiere',function(){MSSGCV3.openForm(parseInt($(this).data('id')),$w);});

        /* Ricerca live */
        $w.on('input.mssgcv3','#mssgc-search',function(){
            var q=$(this).val().toLowerCase().trim();
            $w.find('#mssgc-table tbody tr').each(function(){
                $(this).toggle(!q||(($(this).data('search')||'').indexOf(q)!==-1));
            });
        });

        /* Filtri stato */
        $w.on('click.mssgcv3','.mssgc-filtro-btn',function(){
            $w.find('.mssgc-filtro-btn').removeClass('active');$(this).addClass('active');
            MSSGCV3.reloadLista($(this).data('stato'),$w);
        });

        /* Pin */
        $w.on('click.mssgcv3','.mssgc-pin-btn',function(e){
            e.stopPropagation();
            var $btn=$(this);
            MSSGCV3._ajax('cantieri_toggle_pin',{cantiere_id:$btn.data('id')},{
                success:function(r){
                    $btn.data('pinned',r.pinned).toggleClass('pinned',!!r.pinned);
                    $btn.closest('tr').toggleClass('mssgc-row-pinned',!!r.pinned);
                    MSSGCV3._notice($w,r.msg,'success');
                }
            });
        });

        /* Menu azioni ⋯ — usa un div fuori dalla tabella */
        $w.on('click.mssgcv3','.mssgc-action-btn',function(e){
            e.stopPropagation();
            var $btn=$(this);
            var $menu=$('#mssgc-action-menu-global');
            if(!$menu.length){
                $menu=$('<div id="mssgc-action-menu-global">'+
                    '<button class="mssgc-menu-item" data-action="archivia">📦 Archivia cantiere</button>'+
                    '<button class="mssgc-menu-item" data-action="riabilita" style="display:none">🔄 Riabilita cantiere</button>'+
                    '<button class="mssgc-menu-item" data-action="elimina">🗑 Elimina cantiere</button>'+
                '</div>').css({
                    display:'none',position:'fixed',zIndex:99999,
                    background:'var(--msslu-box-bg,#1a1a2e)',
                    border:'1px solid var(--msslu-box-border)',
                    borderRadius:'8px',padding:'6px 0',minWidth:'180px',
                    boxShadow:'0 4px 20px rgba(0,0,0,.5)'
                }).appendTo('body');
                $menu.find('.mssgc-menu-item').css({
                    display:'flex',alignItems:'center',gap:'8px',
                    padding:'9px 16px',background:'none',border:'none',
                    width:'100%',textAlign:'left',cursor:'pointer',
                    fontSize:'13px',color:'var(--msslu-text)',whiteSpace:'nowrap'
                });
                $menu.find('[data-action="elimina"]').css('color','#ef4444');
            }
            var pos=$btn[0].getBoundingClientRect();
            var stato=$btn.data('stato')||'';
            $menu.data('cantiere-id',$btn.data('id')).data('nome',$btn.data('nome')).data('stato',stato);
            /* Mostra Riabilita o Archivia in base allo stato */
            $menu.find('[data-action="archivia"]').css('display', stato==='archiviato' ? 'none' : 'flex');
            $menu.find('[data-action="riabilita"]').css('display', stato==='archiviato' ? 'flex' : 'none');
            $menu.css({top:pos.bottom+4+'px',left:Math.min(pos.left,window.innerWidth-200)+'px'}).show();
        });

        $(document).off('click.mssgcv3-menu').on('click.mssgcv3-menu',function(e){
            if(!$(e.target).closest('#mssgc-action-menu-global').length)
                $('#mssgc-action-menu-global').hide();
        });

        $(document).off('click.mssgcv3-menuitem').on('click.mssgcv3-menuitem','.mssgc-menu-item',function(){
            var $menu=$('#mssgc-action-menu-global');
            var cid=$menu.data('cantiere-id'),nome=$menu.data('nome');
            $menu.hide();
            if($(this).data('action')==='archivia') MSSGCV3.archiviaDialog(cid,nome,$w);
            if($(this).data('action')==='riabilita') MSSGCV3.riabilitaDialog(cid,nome,$w);
            else if($(this).data('action')==='elimina') MSSGCV3.eliminaDialog(cid,nome,$w);
        });

        /* Export CSV */
        $w.on('click.mssgcv3','#mssgc-export-csv',function(){
            var form=$('<form method="POST" action="'+MSSG.ajax_url+'" target="_blank" style="display:none">');
            form.append($('<input>').attr({name:'action',value:'mssg_cantieri_export_csv'}));
            form.append($('<input>').attr({name:'nonce',value:MSSG.nonce}));
            $('body').append(form);form[0].submit();setTimeout(function(){form.remove();},1000);
        });
    },

    riabilitaDialog:function(cid,nome,$w){
        if(!confirm('Riabilitare il cantiere "'+nome+'"?'))return;
        MSSGCV3._ajax('cantieri_riabilita',{cantiere_id:cid},{
            success:function(r){
                MSSGCV3._notice($w,r.msg,'success');
                MSSGCV3.reloadLista('tutti',$w);
            },
            error:function(m){MSSGCV3._notice($w,m,'error');}
        });
    },

    archiviaDialog:function(cid,nome,$w){
        if(!confirm('Archiviare "'+nome+'"?\nNon apparirà nella lista principale. Visibile nel filtro Archivio.'))return;
        MSSGCV3._ajax('cantieri_archivia',{cantiere_id:cid},{
            success:function(r){MSSGCV3._notice($w,r.msg,'success');setTimeout(function(){MSSGCV3.reloadLista('tutti',$w);},700);}
        });
    },

    eliminaDialog:function(cid,nome,$w){
        var inp=prompt('Per eliminare "'+nome+'"\nDigita esattamente il nome del cantiere per confermare:');
        if(inp===null)return;
        MSSGCV3._ajax('cantieri_delete',{cantiere_id:cid,confirm_nome:inp},{
            success:function(r){MSSGCV3._notice($w,r.msg,'success');setTimeout(function(){MSSGCV3.reloadLista('tutti',$w);},700);},
            error:function(m){MSSGCV3._notice($w,m,'error');}
        });
    },

    openForm:function(id,$w,targetTab){
        this._stopChatPoll();
        var $p=$w.find('#mssgc-panel'),$l=$w.find('.mssgc-list-area');
        $p.html('<div style="padding:30px;text-align:center"><div class="mssg-spinner"></div></div>').show();
        $l.hide();
        MSSGCV3._ajax('cantieri_form',{cantiere_id:id},{
            success:function(d){
                $p.html(d.html);
                /* Legge il cantiere_id direttamente dal DOM — fonte di verità */
                var cid=parseInt($p.find('.mssgc-form-wrap').data('cantiere-id'))||id||0;
                MSSGCV3.initForm($p,$w,cid);
                if(targetTab){
                    /* CORREZIONE: un singolo setTimeout(50ms) a volte scattava prima
                       che il tab richiesto fosse davvero pronto/cliccabile (es. se il
                       browser era ancora impegnato a renderizzare/caricare immagini
                       della sezione appena inserita), col risultato che il click
                       "andava a vuoto" e restava visibile la tab di default (Dati).
                       Riprova più volte finché il tab non risulta effettivamente attivo. */
                    (function tryClickTab(attempt){
                        var $btn=$p.find('.mssgc-tab-btn[data-tab="'+targetTab+'"]');
                        if($btn.length){
                            $btn.trigger('click');
                            setTimeout(function(){
                                if(!$btn.hasClass('active') && attempt<8) tryClickTab(attempt+1);
                            },80);
                        } else if(attempt<8){
                            setTimeout(function(){tryClickTab(attempt+1);},80);
                        }
                    })(0);
                }
            },
            error:function(m){$p.html('<p style="color:#ef4444;padding:16px">'+m+'</p>');}
        });
    },

    initForm:function($p,$w,cantiere_id){
        var cid=cantiere_id||0;

        /* Rimuovi handler precedenti per evitare doppi confirm */
        $p.off('.mssgcv3');

        /* Tab */
        $p.on('click.mssgcv3','.mssgc-tab-btn',function(){
            $p.find('.mssgc-tab-btn').removeClass('active');$(this).addClass('active');
            var tab=$(this).data('tab');
            $p.find('.mssgc-tab-content').removeClass('active');
            $p.find('.mssgc-tab-content[data-tab="'+tab+'"]').addClass('active');
            if(tab==='media'&&cid){MSSGCV3.initChat($p,cid);MSSGCV3.rigeneraThumb($p,cid);}
        });

        /* Torna */
        $p.on('click.mssgcv3','.mssgc-btn-back',function(){
            MSSGCV3._stopChatPoll();
            $p.hide().html('');$w.find('.mssgc-list-area').show();
        });

        /* ── TAB DATI: Salva ── */
        $p.on('click.mssgcv3','#mssgc-form-save',function(){
            var $btn=$(this);
            var data={cantiere_id:cid};
            $p.find('[data-tab="dati"] input[name],[data-tab="dati"] select[name],[data-tab="dati"] textarea[name]').each(function(){
                data[$(this).attr('name')]=$(this).val();
            });
            if(!data.nome||!data.nome.trim()){MSSGCV3._notice($p,'Il nome è obbligatorio.','error');return;}
            MSSGCV3._btnLoad($btn,true);
            MSSGCV3._ajax('cantieri_save',data,{
                success:function(res){
                    if(res.is_new&&res.cantiere_id){
                        cid=res.cantiere_id;
                        MSSGCV3._notice($p,res.msg,'success');
                        /* Ricarica con il nuovo ID così si sbloccano gli altri tab */
                        setTimeout(function(){MSSGCV3.openForm(cid,$w);},800);
                    } else {
                        MSSGCV3._notice($p,res.msg,'success');
                        MSSGCV3._btnLoad($btn,false);
                    }
                },
                error:function(m){MSSGCV3._notice($p,m,'error');MSSGCV3._btnLoad($btn,false);}
            });
        });

        /* Elimina */
        $p.on('click.mssgcv3','#mssgc-form-delete',function(){
            var nome=$p.find('input[name="nome"]').val()||'questo cantiere';
            MSSGCV3.eliminaDialog(cid,nome,$w);
        });

        /* Export PDF */
        $p.on('click.mssgcv3','#mssgc-export-pdf',function(){
            if(!cid)return;
            MSSGCV3._ajax('cantieri_export_pdf',{cantiere_id:cid},{
                success:function(r){var win=window.open('','_blank');win.document.write(r.html);win.document.close();}
            });
        });

        /* ── TAB TEAM ── */
        $p.on('click.mssgcv3','#mssgc-save-cliente',function(){
            var $btn=$(this);MSSGCV3._btnLoad($btn,true);
            MSSGCV3._ajax('cantieri_update_cliente',{cantiere_id:cid,cliente_id:$p.find('#mssgc-select-cliente').val()},{
                success:function(r){MSSGCV3._notice($p,r.msg,'success');MSSGCV3._btnLoad($btn,false);},
                error:function(m){MSSGCV3._notice($p,m,'error');MSSGCV3._btnLoad($btn,false);}
            });
        });
        $p.on('click.mssgcv3','#mssgc-save-responsabile',function(){
            MSSGCV3._ajax('cantieri_update_responsabile',{cantiere_id:cid,responsabile_id:$p.find('#mssgc-select-responsabile').val()},{
                success:function(r){MSSGCV3._notice($p,r.msg,'success');}
            });
        });
        $p.on('click.mssgcv3','#mssgc-aggiungi-col',function(){
            var uid=$p.find('#mssgc-add-collaboratore').val();
            var ruolo=$p.find('#mssgc-add-ruolo').val();
            if(!uid||uid==='0'){MSSGCV3._notice($p,'Seleziona un collaboratore.','error');return;}
            MSSGCV3._ajax('cantieri_aggiungi_col',{cantiere_id:cid,user_id:uid,ruolo:ruolo},{
                success:function(r){
                    MSSGCV3._notice($p,r.msg,'success');
                    var $list=$p.find('#mssgc-team-list');
                    $list.find('p').remove();
                    $list.append(r.html);
                    $p.find('#mssgc-add-collaboratore option[value="'+r.user_id+'"]').remove();
                }
            });
        });
        $p.on('click.mssgcv3','.mssgc-rimuovi-col',function(){
            if(!confirm('Rimuovere dal cantiere?'))return;
            var uid=$(this).data('user-id'),$row=$(this).closest('.mssgc-team-row');
            MSSGCV3._ajax('cantieri_rimuovi_col',{cantiere_id:cid,user_id:uid},{success:function(){$row.remove();}});
        });
        $p.on('change.mssgcv3','.mssgc-ruolo-select',function(){
            MSSGCV3._ajax('cantieri_update_ruolo',{cantiere_id:cid,user_id:$(this).data('user-id'),ruolo:$(this).val()},{});
        });

        /* ── TAB MEDIA: Selezione file → apre pannello pre-upload ── */
        $p.on('change.mssgcv3','.mssgc-file-input',function(){
            if(!this.files.length)return;
            if(!cid){MSSGCV3._notice($p,'Salva prima i dati del cantiere.','error');return;}
            /* IMPORTANTE: convertire in Array PRIMA del reset — this.value='' invalida il FileList */
            var files=Array.from(this.files);
            this.value='';
            var $panel=$p.find('#mssgc-preupload-panel');
            var $prev=$p.find('#mssgc-preupload-previews');

            /* Anteprime con dimensione */
            $prev.empty();
            Array.from(files).forEach(function(f){
                var isImg=f.type.startsWith('image/');
                var sizeMB=(f.size/1024/1024).toFixed(1);
                var sizeStr=f.size>1024*1024?sizeMB+' MB':(f.size/1024).toFixed(0)+' KB';
                var $wrap=$('<div>').css({position:'relative',flexShrink:0,textAlign:'center'});
                var $thumb=$('<div>').css({width:'72px',height:'72px',borderRadius:'6px',overflow:'hidden',background:'var(--msslu-input-bg)',display:'flex',alignItems:'center',justifyContent:'center',marginBottom:'4px'});
                if(isImg){
                    var url=URL.createObjectURL(f);
                    $thumb.html('<img src="'+url+'" style="width:100%;height:100%;object-fit:cover">');
                } else {
                    var ico=f.type==='application/pdf'?'📄':f.type.startsWith('video/')?'🎥':'📎';
                    $thumb.html('<div style="text-align:center;font-size:10px;padding:4px;color:var(--msslu-text-muted)"><div style="font-size:22px">'+ico+'</div>'+f.name.substring(0,10)+'</div>');
                }
                $wrap.append($thumb);
                $wrap.append($('<div>').css({fontSize:'10px',color:'var(--msslu-accent)',fontWeight:'600'}).text(sizeStr));
                $prev.append($wrap);
            });

            /* Aggiorna label slider */
            MSSGCV3._aggiornaSlider($p);
            $panel.data('files-pending', files);
            $panel.slideDown(200);
        });

        /* ── Slider qualità: aggiorna label in tempo reale ── */
        $p.on('input.mssgcv3','#mssgc-upload-qualita',function(){
            MSSGCV3._aggiornaSlider($p);
        });

        /* ── Conferma upload ── */
        $p.on('click.mssgcv3','#mssgc-preupload-confirm',function(){
            var $panel=$p.find('#mssgc-preupload-panel');
            var files=$panel.data('files-pending');
            if(!files||!files.length)return;
            var cat=$p.find('#mssgc-upload-categoria').val()||'cantiere';
            var vis=$p.find('#mssgc-upload-visibile-cliente').is(':checked')?1:0;
            var did=$p.find('#mssgc-upload-didascalia').val().trim();
            var q=parseInt($p.find('#mssgc-upload-qualita').val()||82)/100;
            $p.find('#mssgc-preupload-confirm,#mssgc-preupload-cancel').prop('disabled',true);
            MSSGCV3.uploadFiles(files,cid,cat,vis,did,q,$p,$w);
        });

        /* ── Annulla ── */
        $p.on('click.mssgcv3','#mssgc-preupload-cancel',function(){
            var $panel=$p.find('#mssgc-preupload-panel');
            $panel.slideUp(200,function(){
                $panel.find('#mssgc-preupload-previews').empty();
                $p.find('#mssgc-upload-didascalia').val('');
                $p.find('#mssgc-upload-visibile-cliente').prop('checked',false);
                $panel.data('files-pending',null);
            });
        });

        /* ── Mostra tutte le foto (lazy load dei data-src) ── */
        $p.on('click.mssgcv3','#mssgc-mostra-tutte-foto',function(){
            var $btn=$(this);
            $p.find('.mssgc-foto-extra').each(function(){
                var $el=$(this);
                var $img=$el.find('img[data-src]');
                if($img.length) $img.attr('src',$img.data('src')).removeAttr('data-src');
                $el.show();
            });
            $btn.remove();
        });

        /* ── Toggle visibile al cliente ──
           NB: bottone type="button" esplicito (evita comportamenti submit di
           default) + stopPropagation/preventDefault, per evitare che il tap
           venga interpretato come click sul link "apri file" adiacente
           (segnalato specialmente su mobile, dove la tabella documenti senza
           min-width si schiacciava rendendo i tap target ambigui — vedi fix
           min-width nella tabella in section-media.php). */
        $p.on('click.mssgcv3','.mssgc-toggle-visibile',function(e){
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            var $btn=$(this),id=$btn.data('id'),stato=parseInt($btn.data('stato'))||0;
            var nuovoStato=stato?0:1;
            $btn.prop('disabled',true);
            MSSGCV3._ajax('cantieri_toggle_visibilita',{media_id:id,visibile:nuovoStato},{
                success:function(){
                    $btn.data('stato',nuovoStato);
                    if($btn.closest('.mssgc-media-thumb').length){
                        $btn.html(nuovoStato?'👁':'🚫')
                            .css('background',nuovoStato?'rgba(34,197,94,.85)':'rgba(40,40,60,.7)')
                            .attr('title',nuovoStato?'Visibile al cliente — tocca per nascondere':'Nascosto al cliente — tocca per rendere visibile');
                    } else {
                        $btn.html(nuovoStato?'<span style="color:#22c55e">✓ Visibile</span>':'<span style="color:var(--msslu-text-muted)">— Nascosto</span>')
                            .css({background:nuovoStato?'rgba(34,197,94,.12)':'rgba(255,255,255,.06)',
                                  borderColor:nuovoStato?'rgba(34,197,94,.35)':'var(--msslu-box-border)'});
                    }
                    $btn.prop('disabled',false);
                },
                error:function(m){if(window.mssgToast)mssgToast(m,'error');else alert(m);$btn.prop('disabled',false);}
            });
        });

        /* ── Svuota chat ── */
        $p.on('click.mssgcv3','#mssgc-chat-clear',function(){
            if(!confirm('Svuotare tutta la chat di questo cantiere?\nI messaggi verranno eliminati definitivamente.'))return;
            var cid_chat=$(this).data('cantiere-id')||cid;
            MSSGCV3._ajax('chat_clear',{cantiere_id:cid_chat},{
                success:function(){
                    $p.find('#mssgc-chat-messages').html('<div class="mssgc-chat-empty">Nessun messaggio. Inizia la conversazione!</div>');
                    MSSGCV3._notice($p,'Chat svuotata.','success');
                },
                error:function(m){if(window.mssgToast)mssgToast(m,'error');else alert(m);}
            });
        });

        /* Lightbox */
        $p.on('click.mssgcv3','.mssgc-media-thumb',function(e){
            if($(e.target).hasClass('mssgc-media-delete')||$(e.target).hasClass('mssgc-toggle-visibile'))return;
            MSSGCV3.openLightbox($(this).data('url'),'foto',$(this).data('nome'),$p,$(this));
        });
        $p.on('click.mssgcv3','.mssgc-media-video-item',function(e){
            if($(e.target).hasClass('mssgc-media-delete'))return;
            MSSGCV3.openLightbox($(this).data('url'),'video',$(this).data('nome'),$p,$(this));
        });
        $p.on('click.mssgcv3','.mssgc-doc-open',function(){
            var mime=$(this).data('tipo');
            if(mime==='application/pdf') MSSGCV3.openLightbox($(this).data('url'),'pdf',$(this).data('nome'),$p,$(this));
            else window.open($(this).data('url'),'_blank');
        });
        $p.on('click.mssgcv3','.mssgc-media-delete',function(e){
            e.stopPropagation();
            if(!confirm('Eliminare questo file?'))return;
            var id=$(this).data('id'),$item=$(this).closest('.mssgc-media-thumb,.mssgc-media-video-item,tr');
            MSSGCV3._ajax('cantieri_delete_media',{media_id:id},{
                success:function(){$item.fadeOut(300,function(){$(this).remove();});}
            });
        });
        $p.on('click.mssgcv3','#mssgc-lightbox,#mssgc-lightbox-close',function(e){
            if($(e.target).is('#mssgc-lightbox,#mssgc-lightbox-close'))
                $p.find('#mssgc-lightbox').hide().find('#mssgc-lightbox-content').html('');
        });

        /* ── TAB AVANZAMENTO ── */
        /* ── Slider avanzamento lavori ── */
        $p.on('input.mssgcv3 change.mssgcv3','#mssgc-avanz-pct-slider',function(){
            var v=$(this).val();
            $p.find('#mssgc-avanz-pct-val').text(v+'%');
            /* Aggiorna anche la barra in tempo reale */
            var color=v>=100?'#22c55e':v>=50?'var(--msslu-accent)':'#f59e0b';
            $p.find('#mssgc-avanz-barra-fill').css({width:v+'%',background:color});
            $p.find('#mssgc-avanz-pct-display').text(v+'%').css('color',color);
        });
        $p.on('click.mssgcv3','#mssgc-avanz-pct-save',function(){
            var $btn=$(this);
            /* Legge cid dal data attribute del pulsante — più affidabile della closure */
            var cidSave=parseInt($btn.data('cantiere-id')||cid||0);
            var pct=parseInt($p.find('#mssgc-avanz-pct-slider').val()||0);
            pct=Math.min(100,Math.max(0,pct));
            if(!cidSave){MSSGCV3._notice($p,'Cantiere non identificato.','error');return;}
            $btn.prop('disabled',true).text('...');
            MSSGCV3._ajax('cantieri_salva_avanzamento_pct',{cantiere_id:cidSave,pct:pct},{
                success:function(d){
                    /* Usa il valore confermato dal server (riletto dopo la scrittura),
                       non quello ottimisticamente assunto lato client. */
                    var confermato=(d&&typeof d.pct!=='undefined')?parseInt(d.pct):pct;
                    $p.find('#mssgc-avanz-pct-slider').val(confermato);
                    $p.find('#mssgc-avanz-pct-val').text(confermato+'%');
                    var color=confermato>=100?'#22c55e':confermato>=50?'var(--msslu-accent)':'#f59e0b';
                    $p.find('#mssgc-avanz-barra-fill').css({width:confermato+'%',background:color});
                    $p.find('#mssgc-avanz-pct-display').text(confermato+'%').css('color',color);
                    $btn.text('✓ Salvato!');
                    setTimeout(function(){$btn.prop('disabled',false).text('Salva');},1800);
                },
                error:function(m){var em='Errore salvataggio: '+m;if(window.mssgToast)mssgToast(em,'error');else alert(em);$btn.prop('disabled',false).text('Salva');}
            });
        });

        $p.on('click.mssgcv3','#mssgc-pubblica-avanzamento',function(){
            var $btn=$(this);
            if(!cid){MSSGCV3._notice($p,'Salva prima i dati del cantiere.','error');return;}
            var titolo=$p.find('#mssgc-avanz-titolo').val().trim();
            if(!titolo){MSSGCV3._notice($p,'Inserisci un titolo.','error');return;}
            var data={
                cantiere_id:cid,
                titolo:titolo,
                testo:$p.find('#mssgc-avanz-testo').val(),
                tipo:$p.find('#mssgc-avanz-tipo').val(),
                visibile_cliente:$p.find('#mssgc-avanz-cliente').is(':checked')?1:0
            };
            MSSGCV3._btnLoad($btn,true);
            MSSGCV3._ajax('cantieri_pubblica_avanzamento',data,{
                success:function(r){
                    MSSGCV3._notice($p,r.msg,'success');
                    $p.find('#mssgc-avanz-titolo').val('');
                    $p.find('#mssgc-avanz-testo').val('');
                    /* Ricarica SOLO il tab avanzamento via AJAX */
                    MSSGCV3._ajax('cantieri_avanzamento_tab',{cantiere_id:cid},{
                        success:function(html){
                            $p.find('.mssgc-tab-content[data-tab="avanzamento"]').html(html);
                            MSSGCV3._btnLoad($btn,false);
                        },
                        error:function(){MSSGCV3._btnLoad($btn,false);}
                    });
                },
                error:function(m){MSSGCV3._notice($p,m,'error');MSSGCV3._btnLoad($btn,false);}
            });
        });
        $p.on('click.mssgcv3','.mssgc-avanz-delete',function(){
            if(!confirm('Eliminare questo aggiornamento?'))return;
            var id=$(this).data('id'),$item=$(this).closest('.mssgc-avanz-item');
            MSSGCV3._ajax('cantieri_delete_avanzamento',{avanz_id:id},{
                success:function(){$item.fadeOut(300,function(){$(this).remove();});}
            });
        });

        /* ── Modifica aggiornamento avanzamento (inline) ── */
        $p.on('click.mssgcv3','.mssgc-avanz-edit',function(){
            var $item=$(this).closest('.mssgc-avanz-item');
            $item.find('.mssgc-avanz-view').hide();
            $item.find('.mssgc-avanz-edit-form').show();
        });
        $p.on('click.mssgcv3','.mssgc-avanz-cancel-edit',function(){
            var $item=$(this).closest('.mssgc-avanz-item');
            $item.find('.mssgc-avanz-edit-form').hide();
            $item.find('.mssgc-avanz-view').show();
        });
        $p.on('click.mssgcv3','.mssgc-avanz-save-edit',function(){
            var $btn=$(this),id=$btn.data('id'),$item=$btn.closest('.mssgc-avanz-item'),$form=$btn.closest('.mssgc-avanz-edit-form');
            var titolo=$form.find('.mssgc-avanz-edit-titolo').val().trim();
            if(!titolo){if(window.mssgToast)mssgToast('Inserisci un titolo.','error');else alert('Inserisci un titolo.');return;}
            var data={
                avanz_id:id,
                titolo:titolo,
                testo:$form.find('.mssgc-avanz-edit-testo').val(),
                tipo:$form.find('.mssgc-avanz-edit-tipo').val(),
                visibile_cliente:$form.find('.mssgc-avanz-edit-cliente').is(':checked')?1:0
            };
            $btn.prop('disabled',true).text('...');
            MSSGCV3._ajax('cantieri_modifica_avanzamento',data,{
                success:function(){
                    /* Ricarica l'intero tab per riflettere la modifica con la
                       formattazione corretta (icone tipo, badge visibilità, ecc.) */
                    var cidReload=parseInt($item.closest('.mssgc-tab-content').find('[data-cantiere-id]').first().data('cantiere-id'))||cid;
                    MSSGCV3._ajax('cantieri_avanzamento_tab',{cantiere_id:cidReload},{
                        success:function(html){ $p.find('.mssgc-tab-content[data-tab="avanzamento"]').html(html); }
                    });
                },
                error:function(m){if(window.mssgToast)mssgToast(m,'error');else alert(m);$btn.prop('disabled',false).text('Salva modifiche');}
            });
        });

        /* Note tab rimosso - appuntamenti in Proponi Appuntamento */

    /* ── TAB PAGAMENTI ── */
        /* ── Conversione bidirezionale %↔€ tra i due campi della nuova milestone ──
           In precedenza il campo "Importo (€)" esisteva solo come placeholder
           "Auto da %" ma non era collegato a nulla: non aggiornava la % né
           veniva mai inviato al salvataggio. Ora i due campi si aggiornano a
           vicenda in base al totale preventivo del cantiere, e l'ultimo campo
           modificato dall'utente è quello che viene inviato come sorgente. */
        (function(){
            var $percField=$p.find('#mssgc-pag-perc'),$impField=$p.find('#mssgc-pag-importo');
            var totale=parseFloat($percField.data('importo-totale')||$impField.data('importo-totale')||0);
            if(!totale||!$impField.length)return;
            $p.on('input.mssgcv3','#mssgc-pag-perc',function(){
                var perc=parseFloat($(this).val())||0;
                $impField.val((totale*perc/100).toFixed(2));
            });
            $p.on('input.mssgcv3','#mssgc-pag-importo',function(){
                var imp=parseFloat($(this).val())||0;
                var perc=totale>0?(imp/totale*100):0;
                $percField.val(Math.round(perc*100)/100);
            });
            /* Precompila subito l'importo in base alla % di default */
            $impField.val((totale*(parseFloat($percField.val())||0)/100).toFixed(2));
        })();
        $p.on('click.mssgcv3','#mssgc-pag-aggiungi',function(){
            var $btn=$(this);
            var cid_p=parseInt($btn.data('cantiere-id'))||cid;
            if(!cid_p){MSSGCV3._notice($p,'Salva prima il cantiere.','error');return;}
            /* Calcola somma percentuali esistenti */
            var totale_esistente=0;
            $p.find('.mssgc-pag-row').each(function(){
                /* Legge data-perc se presente, altrimenti la percentuale dal testo */
                var perc=parseFloat($(this).data('perc')||0);
                if(!perc) {
                    var txt=$(this).find('span:contains("%")').first().text();
                    perc=parseFloat(txt)||0;
                }
                totale_esistente+=perc;
            });
            var nuova_perc=parseFloat($p.find('#mssgc-pag-perc').val())||0;
            if(totale_esistente+nuova_perc>100){
                MSSGCV3._notice($p,'Somma milestone supera il 100% (attuale: '+totale_esistente+'%). Riduci la percentuale.','error');
                return;
            }
            var data={cantiere_id:cid_p,tipo:$p.find('#mssgc-pag-tipo').val()||'avanzamento',
                label:$p.find('#mssgc-pag-label').val(),percentuale:nuova_perc,
                importo:parseFloat($p.find('#mssgc-pag-importo').val())||0,
                note:$p.find('#mssgc-pag-note').val()};
            MSSGCV3._btnLoad($btn,true);
            MSSGCV3._ajax('cantieri_pag_aggiungi',data,{
                success:function(r){
                    $p.find('.mssgc-tab-content[data-tab="pagamenti"]').html(r.html);
                    MSSGCV3._notice($p,r.msg,'success');
                    MSSGCV3._btnLoad($btn,false);
                },
                error:function(m){MSSGCV3._notice($p,m,'error');MSSGCV3._btnLoad($btn,false);}
            });
        });
        /* Toggle pagato via checkbox */
        $p.on('change.mssgcv3','.mssgc-pag-check',function(){
            var pid=$(this).data('id');
            var pagato=$(this).is(':checked')?1:0;
            MSSGCV3._ajax('cantieri_pag_toggle',{milestone_id:pid,pagato:pagato},{
                success:function(r){
                    $p.find('.mssgc-tab-content[data-tab="pagamenti"]').html(r.html);
                    MSSGCV3._notice($p,r.msg||'Stato aggiornato.','success');
                }
            });
        });
        /* Salva data pagamento */
        $p.on('change.mssgcv3','.mssgc-pag-data',function(){
            var pid=$(this).data('id'), data_pag=$(this).val();
            MSSGCV3._ajax('cantieri_pag_toggle',{milestone_id:pid,data_pagamento:data_pag},{
                success:function(r){ if(r.html) $p.find('.mssgc-tab-content[data-tab="pagamenti"]').html(r.html); }
            });
        });
        $p.on('click.mssgcv3','.mssgc-pag-delete',function(){
            if(!confirm('Eliminare questa milestone?'))return;
            var pid=$(this).data('id');
            MSSGCV3._ajax('cantieri_pag_delete',{milestone_id:pid},{
                success:function(r){$p.find('.mssgc-tab-content[data-tab="pagamenti"]').html(r.html);}
            });
        });
    },

    /* ── Chat ── */
    initChat:function($p,cid){
        if(!cid)return;
        var self=this;
        var $msgs=$p.find('#mssgc-chat-messages');
        if(!$msgs.length)return;
        $msgs.scrollTop($msgs[0].scrollHeight);
        var last=$msgs.find('.mssgc-msg').last();
        self._chatLastId=last.length?parseInt(last.data('id'))||0:0;

        /* Auto-resize textarea */
        $p.find('#mssgc-chat-input').on('input',function(){
            this.style.height='auto';
            this.style.height=Math.min(this.scrollHeight,100)+'px';
        });

        /* Invia con Enter (no shift) */
        $p.on('keydown.mssgcv3-chat','#mssgc-chat-input',function(e){
            if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();$p.find('#mssgc-chat-send').trigger('click');}
        });

        $p.on('click.mssgcv3','#mssgc-chat-send',function(){
            var testo=$p.find('#mssgc-chat-input').val().trim();
            if(!testo&&!$p.find('#mssgc-chat-file-pending').length)return;
            var fd=new FormData();
            fd.append('action','mssg_chat_invia');
            fd.append('nonce',MSSG.nonce);
            fd.append('cantiere_id',cid);
            fd.append('testo',testo);
            $(this).prop('disabled',true);
            $.ajax({url:MSSG.ajax_url,type:'POST',data:fd,processData:false,contentType:false,
                success:function(r){
                    if(r&&r.success){
                        $msgs.find('.mssgc-chat-empty').remove();
                        $msgs.append(r.data.html);
                        $msgs.scrollTop($msgs[0].scrollHeight);
                        $p.find('#mssgc-chat-input').val('').css('height','36px');
                        self._chatLastId=r.data.msg_id;
                    } else if(window.mssgToast){
                        mssgToast(r&&r.data&&r.data.msg?r.data.msg:'Errore invio messaggio.','error');
                    }
                    $p.find('#mssgc-chat-send').prop('disabled',false);
                },
                error:function(){
                    if(window.mssgToast)mssgToast('Errore di connessione. Il messaggio non è stato inviato.','error');
                    $p.find('#mssgc-chat-send').prop('disabled',false);
                }
            });
        });

        /* Allegato chat */
        $p.on('change.mssgcv3','#mssgc-chat-file',function(){
            if(!this.files.length)return;
            var fd=new FormData();
            fd.append('action','mssg_chat_invia');fd.append('nonce',MSSG.nonce);
            fd.append('cantiere_id',cid);fd.append('testo','');fd.append('allegato',this.files[0]);
            $.ajax({url:MSSG.ajax_url,type:'POST',data:fd,processData:false,contentType:false,
                success:function(r){
                    if(r&&r.success){
                        $msgs.find('.mssgc-chat-empty').remove();
                        $msgs.append(r.data.html);
                        $msgs.scrollTop($msgs[0].scrollHeight);
                        self._chatLastId=r.data.msg_id;
                    } else if(window.mssgToast){
                        mssgToast(r&&r.data&&r.data.msg?r.data.msg:'Errore invio allegato.','error');
                    }
                },
                error:function(){ if(window.mssgToast)mssgToast('Errore di connessione. Allegato non inviato.','error'); }
            });
            this.value='';
        });

        /* Poll ogni 8 secondi — errori di rete ignorati silenziosamente (retry al giro successivo) */
        self._stopChatPoll();
        self._chatTimer=setInterval(function(){
            if(!$p.find('#mssgc-chat-messages').length){self._stopChatPoll();return;}
            $.post(MSSG.ajax_url,{action:'mssg_chat_poll',nonce:MSSG.nonce,cantiere_id:cid,last_id:self._chatLastId})
                .done(function(r){
                    if(r&&r.success&&r.data.messaggi&&r.data.messaggi.length){
                        r.data.messaggi.forEach(function(m){
                            if(!$msgs.find('[data-id="'+m.id+'"]').length){
                                $msgs.find('.mssgc-chat-empty').remove();
                                $msgs.append(m.html);
                                self._chatLastId=Math.max(self._chatLastId,m.id);
                            }
                        });
                        $msgs.scrollTop($msgs[0].scrollHeight);
                    }
                })
                .fail(function(){ /* poll silenzioso: niente toast per non disturbare durante la digitazione */ });
        },8000);
    },

    _stopChatPoll:function(){
        if(this._chatTimer){clearInterval(this._chatTimer);this._chatTimer=null;}
    },

    /* ── Label slider qualità ── */
    /* ── Rigenera thumbnail foto esistenti in background ── */
    rigeneraThumb:function($p,cid){
        /* Chiama il backend una foto alla volta finché tutte hanno il thumbnail */
        function step(){
            MSSGCV3._ajax('cantieri_rigenera_thumb',{cantiere_id:cid},{
                success:function(d){
                    if(d.done)return; /* tutte pronte */
                    /* Aggiorna l'immagine nella griglia se è già visibile */
                    if(d.id&&d.thumb){
                        $p.find('.mssgc-media-thumb[data-id="'+d.id+'"] img').each(function(){
                            if(!$(this).attr('src')&&$(this).data('src')===undefined)return;
                            $(this).attr('src',d.thumb);
                        });
                    }
                    setTimeout(step,300); /* aspetta un po' prima del prossimo */
                }
                /* Se errore, stop silenzioso */
            });
        }
        /* Avvia solo se ci sono immagini nella griglia senza src valido */
        if($p.find('.mssgc-media-thumb img[src=""],.mssgc-media-thumb img:not([src])').length||
           $p.find('.mssgc-media-thumb img').filter(function(){return !$(this).data('thumb-checked');}).length){
            step();
        }
    },

    _aggiornaSlider:function($p){
        var v=parseInt($p.find('#mssgc-upload-qualita').val()||82);
        var lbl=v>=95?'Originale ('+v+'%)':v>=75?'Stampa/Alta ('+v+'%)':v>=55?'Web ('+v+'%)':'Compresso ('+v+'%)';
        $p.find('#mssgc-qualita-label').text(lbl);
    },

    /* ── Comprimi immagine prima dell'upload ── */
    compressImage:function(file,maxPx,quality){
        return new Promise(function(resolve){
            if(!file.type.startsWith('image/')||file.type==='image/svg+xml'){resolve(file);return;}
            if(quality>=1){resolve(file);return;}
            var reader=new FileReader();
            reader.onload=function(e){
                var img=new Image();
                img.onload=function(){
                    var w=img.width,h=img.height;
                    /* Ridimensiona se necessario */
                    if(w>maxPx||h>maxPx){
                        if(w>h){h=Math.round(h*maxPx/w);w=maxPx;}
                        else{w=Math.round(w*maxPx/h);h=maxPx;}
                    }
                    var canvas=document.createElement('canvas');
                    canvas.width=w;canvas.height=h;
                    canvas.getContext('2d').drawImage(img,0,0,w,h);
                    canvas.toBlob(function(blob){
                        if(!blob||blob.size>file.size){resolve(file);return;} /* non conviene */
                        resolve(new File([blob],file.name.replace(/\.[^.]+$/,'.jpg'),{type:'image/jpeg',lastModified:Date.now()}));
                    },'image/jpeg',quality||0.82);
                };
                img.src=e.target.result;
            };
            reader.readAsDataURL(file);
        });
    },

    /* ── Upload media ── */
    uploadFiles:function(files,cid,cat,vis,did,quality,$p,$w){
        var total=files.length,done=0;
        var $prog=$p.find('#mssgc-upload-progress');
        var $bar=$p.find('#mssgc-upload-bar');
        var $lbl=$p.find('#mssgc-upload-label');
        $prog.show();

        var maxPx=quality>=0.95?99999:quality>=0.75?3000:1600;
        var nonce=(typeof MSSGC_DATA!=='undefined'&&MSSGC_DATA.upload_nonce)?MSSGC_DATA.upload_nonce:MSSG.nonce;

        Array.from(files).forEach(function(file){
            MSSGCV3.compressImage(file,maxPx,quality).then(function(fileToUpload){
                var fd=new FormData();
                fd.append('action','mssg_cantieri_upload_media');
                fd.append('nonce',nonce);
                fd.append('cantiere_id',cid);
                fd.append('categoria',cat);
                fd.append('visibile_cliente',vis);
                fd.append('nome',file.name);
                fd.append('didascalia',did||'');
                fd.append('file',fileToUpload);
                $.ajax({
                    url:MSSG.ajax_url,type:'POST',data:fd,
                    processData:false,contentType:false,
                    xhr:function(){
                        var x=new XMLHttpRequest();
                        x.upload.addEventListener('progress',function(e){
                            if(e.lengthComputable){
                                var pct=Math.round((done/total+(e.loaded/e.total/total))*100);
                                $bar.css('width',pct+'%');
                                $lbl.text('Caricamento '+(done+1)+'/'+total+'…');
                            }
                        });
                        return x;
                    }
                }).done(function(r){
                    done++;
                    $bar.css('width',(done/total*100)+'%');
                    if(r.success){$lbl.text(done===total?'Completato!':'Caricamento…');}
                    else{$lbl.text('Errore: '+(r.data&&r.data.msg?r.data.msg:'upload fallito'));}
                    if(done===total){
                        setTimeout(function(){
                            $prog.hide();$bar.css('width','0%');
                            $p.find('#mssgc-preupload-panel').slideUp(150);
                            $p.find('#mssgc-upload-didascalia').val('');
                            $p.find('#mssgc-upload-visibile-cliente').prop('checked',false);
                            $p.find('#mssgc-preupload-confirm,#mssgc-preupload-cancel').prop('disabled',false);
                            MSSGCV3.openForm(cid,$w,'media');
                        },600);
                    }
                }).fail(function(xhr){
                    done++;
                    $lbl.text('Errore HTTP '+xhr.status+' su '+file.name);
                    $p.find('#mssgc-preupload-confirm,#mssgc-preupload-cancel').prop('disabled',false);
                });
            });
        });

    },

    /* ── Lightbox ── */
    openLightbox:function(url,tipo,nome,$p,$el){
        var $lb=$p.find('#mssgc-lightbox');
        var $cnt=$lb.find('#mssgc-lightbox-content');
        $cnt.html('');
        var cat=($el&&$el.data('categoria'))||'';
        var data=($el&&$el.data('data'))||'';
        var autore=($el&&$el.data('autore'))||'';
        var did=($el&&$el.data('didascalia'))||'';
        var info=[];
        if(cat)info.push('📁 '+cat.charAt(0).toUpperCase()+cat.slice(1));
        if(data)info.push('📅 '+data);
        if(autore)info.push('👤 '+autore);
        var caption=(did?'"'+did+'" — ':'')+(nome||'')+(info.length?' · '+info.join(' · '):'');
        $lb.find('#mssgc-lightbox-caption').text(caption);
        if(tipo==='foto') $cnt.html('<img src="'+url+'" alt="'+nome+'" style="max-width:100%;max-height:calc(100% - 60px);object-fit:contain;border-radius:4px">');
        else if(tipo==='video') $cnt.html('<video src="'+url+'" controls autoplay style="max-width:100%;max-height:calc(100% - 60px)"></video>');
        else if(tipo==='pdf') $cnt.html('<iframe src="'+url+'#toolbar=0" style="width:100%;height:calc(100% - 60px);border:none;border-radius:4px"></iframe>');
        $lb.show();
    },



    reloadLista:function(stato,$w){
        $w.css({opacity:.5,pointerEvents:'none'});
        MSSGCV3._ajax('cantieri_list',{stato:stato||'tutti'},{
            success:function(r){
                $w.css({opacity:1,pointerEvents:''}).html(r.html);
                MSSGCV3.init($w);
            }
        });
    },

    _ajax:function(a,d,cb){
        if(typeof MSSG==='undefined'){if(window.mssgToast)mssgToast('Errore di configurazione. Ricarica la pagina.','error');return;}
        cb=cb||{};
        return $.post(MSSG.ajax_url,$.extend({action:'mssg_'+a,nonce:MSSG.nonce},d))
            .done(function(r){
                if(r&&r.success){if(cb.success)cb.success(r.data);}
                else{var m=r&&r.data&&r.data.msg?r.data.msg:'Errore.';if(cb.error)cb.error(m);else if(window.mssgToast)mssgToast(m,'error');}
            })
            .fail(function(xhr){
                var m='Errore di connessione'+(xhr&&xhr.status?' (HTTP '+xhr.status+')':'')+'. Riprova.';
                if(cb.error)cb.error(m);else if(window.mssgToast)mssgToast(m,'error');
            })
            .always(function(){if(cb.always)cb.always();});
    },

    _notice:function($c,msg,type){
        var ok=type==='success';
        var $n=$('<div class="mssg-notice">').css({
            padding:'10px 14px',borderRadius:'7px',fontSize:'13px',marginBottom:'12px',
            background:ok?'rgba(34,197,94,.12)':'rgba(239,68,68,.12)',
            border:'1px solid '+(ok?'rgba(34,197,94,.3)':'rgba(239,68,68,.3)'),
            color:ok?'#22c55e':'#ef4444'
        }).text(msg);
        $c.find('.mssg-notice').remove();
        $c.prepend($n);
        if(ok)setTimeout(function(){$n.fadeOut(300,function(){$(this).remove();});},3500);
    },

    _btnLoad:function($b,on){
        if(on) $b.data('orig',$b.html()).prop('disabled',true).html('<span class="mssg-spinner" style="width:13px;height:13px;border-width:1.5px;vertical-align:middle"></span>');
        else   $b.prop('disabled',false).html($b.data('orig')||$b.text());
    }
};

})(jQuery);
