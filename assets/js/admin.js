
jQuery( document ).ready( function( $ ) {

    var msg_ara = $("#fd_form_msg");
    msg_ara.hide();
    var form = $( '#deploy-form');

    function send_deploy( zip_file ){
        $.ajax({
            type: 'POST',
            data: {
                action: 'fd_ajax_deploy',
                zip_file: zip_file,
                _nonce: FD_Config.nonce
            },
            url: ajaxurl,
            cache: false,
            error: function ( xhr, ajaxOptions, thrownError ) {
                $( 'input[type="file"]', form).removeAttr( 'disabled');
                form.removeClass( 'loading' );
                msg_ara.hide();
            },
            beforeSend: function () {
                form.addClass( 'loading' );
                msg_ara.show();
                // msg_ara.html( res.data );
                msg_ara.html( FD_Config.deploying );
            },
            complete: function () {
                $( 'input[type="file"]', form ).removeAttr( 'disabled');
                form.removeClass( 'loading' );
            },
            success: function ( res ) {
                form.removeClass( 'loading' );
                if ( res.success ) {
                    msg_ara.show();
                    msg_ara.html( res.data );
                } else {
                    msg_ara.show();
                    $( 'input[type="file"]', form ).removeAttr( 'disabled');
                    msg_ara.html( res.data );
                }
            }
        });

    }

    form.submit( function( e ){
        e.preventDefault();
        if ( form.hasClass( 'loading' ) ) {
            return true;
        }
        var formData = new FormData(form[0]);

        $.ajax({
            type: 'POST',
            data: formData,
            async: false,
            url: ajaxurl,
            cache: false,
            contentType: false,
            processData: false,
            error: function ( xhr, ajaxOptions, thrownError ) {
                $( '.reset_button', form ).trigger( 'click' );
                form.removeClass( 'loading' );
                msg_ara.hide();
            },
            beforeSend: function () {
                form.addClass( 'loading' );
                $( 'input[type="file"]', form ).attr( 'disabled', 'disabled' );
            },
            complete: function () {
                $( 'input[type="file"]', form ).removeAttr( 'disabled');
                form.removeClass( 'loading' );
            },
            success: function ( res ) {
                form.removeClass( 'loading' );
                $( '.reset_button', form ).trigger( 'click' );
                if ( res.success ) {
                    send_deploy( res.data  );
                } else {
                    msg_ara.show();
                    msg_ara.html( res.data );
                    $( 'input[type="file"]', form ).removeAttr( 'disabled');
                }
            }
        });

        return false;
    } );


    //--------------------------------------------------------

    // Upload handle
    window._fd_media_current = {};

    if ( ! window._upload_fame ) {
        window._upload_fame = wp.media({
            title: wp.media.view.l10n.addMedia,
            multiple: false,
            //frame: 'post',
            //library: {type: 'all' },
            //button : { text : 'Insert' }
        });
    }

    window._upload_fame.on('close', function () {
        // get selections and save to hidden input plus other AJAX stuff etc.
       // var selection = window._upload_fame.state().get('selection');
       // console.log(selection);
    });

    window._upload_fame.on('select', function () {
        // Grab our attachment selection and construct a JSON representation of the model.
        var attachment = window._upload_fame.state().get('selection').first().toJSON();
        //$('.image_id', window.media_current).val(media_attachment.id);
        if ( 'application/zip' !== attachment.mime ) {
            return false;
        }
        if ( window._fd_media_current ) {
            window._fd_media_current.metabox.fdDeploy.updateRowData( window._fd_media_current.key, {
                type: 'upload',
                attachment_id: attachment.id
            } );
        }

    });


    $.ajax({
        type: 'GET',
        data: {
            action: 'fd_ajax_github',
            doing: 'get_repos',
            _nonce: FD_Config.nonce
        },
        url: ajaxurl,
        //cache: false,
        error: function (xhr, ajaxOptions, thrownError) {

        },
        complete: function () {

        },
        success: function (res) {
			console.log( 'Github loaded', res );
            $( 'select.github_repo').html(res.data);
            $( 'select.github_repo').trigger( 'option_added' );
        }
    });

    $( '.fd-meta-box').each( function(){
        var metabox = $( this );
        metabox.fdDeploy = {};
        metabox.fdDeploy.input = $( '.fd_versions', metabox );
        var value = metabox.fdDeploy.input.val() || '{}';
        try {
            metabox.fdDeploy.versions = JSON.parse(value);
        } catch ( e ){ }

        if( typeof metabox.fdDeploy.versions !== 'object' ) {
            metabox.fdDeploy.versions = {};
        }

        //console.log( 'metabox.fdDeploy.versions', metabox.fdDeploy.versions );
        metabox.fdDeploy.versionTable = $( this).parent().find( 'table.fd-versions-table' );

        metabox.fdDeploy.updateVersionData = function( save ){

            // Delete all duplicate versions
            var versions = {};
           $.each( metabox.fdDeploy.versions, function( index, value ) {
                if ( typeof versions[ value.version ] === "undefined" ) {
                    versions[ value.version ] = true;
                } else {
                    var rows =   metabox.fdDeploy.versionTable.find( 'tr[data-ver="'+value.version+'"]' );
                    if ( rows.length > 1 ) {
                        $.each( rows, function(_index){
                            if ( _index > 0 ) {
                                $( this ).remove();
                            }
                        } );
                    }

                    delete  metabox.fdDeploy.versions[ index ];
                }
           } );


            metabox.fdDeploy.input.val( JSON.stringify( metabox.fdDeploy.versions ) );
            if ( typeof save === "undefined" || save === true ) {
                metabox.fdDeploy.save();
            }

        };

        metabox.fdDeploy.save = function(){
            $.ajax({
                type: 'POST',
                data: {
                    action: 'fd_ajax_deployment',
                    post_id: $( '#post_ID').val(),
                    _nonce: FD_Config.nonce,
                    versions: metabox.fdDeploy.versions
                },
                url: ajaxurl,
                dataType: 'json',
                error: function ( xhr, ajaxOptions, thrownError) {

                },
                complete: function () {

                },
                success: function ( res ) {
                	console.log( 'save_versions success', res );
                }
            });
        };

        metabox.fdDeploy.ajaxDeploy = function( type, data, rowKey ){
            type = type || 'free';
            var row = metabox.fdDeploy.versionTable.find( 'tbody tr[row-id="'+rowKey+'"]');
			$( '.fd-update-button', row ).addClass( 'spin' );
			console.log( 'ajaxDeploy data ' + type + ': ', data );
            $.ajax({
                type: 'POST',
                data: {
                    action: 'fd_ajax_deploy',
                    data: data,
                    type: type,
                    _nonce: FD_Config.nonce
                },
                url: ajaxurl,
                dataType: 'json',
                error: function ( xhr, ajaxOptions, thrownError) {

                },
                complete: function () {

                },
                success: function ( res ) {
					console.log( 'ajaxDeploy respond', res );
                    if ( res.success ) {
                        metabox.fdDeploy.updateRowData( rowKey, res.data );
                        if ( type != 'premium') {
                            metabox.fdDeploy.ajaxDeploy( 'premium', data, rowKey );
                        } else {
                            $( '.fd-update-button', row ).removeClass( 'spin' );
                            metabox.fdDeploy.updateVersionData( true );
                        }
                    }
                }
            });
        };

        metabox.fdDeploy.updateRowData = function( key, data, save ){

            var currentData = metabox.fdDeploy.versions[ key ] || {};
            var args = _.defaults( data || {}, currentData );
            args = _.defaults( args, {
                free_name: '',
                free_url: '',
                premium_name: '',
                premium_url: '',
                version: '',
                current: '',
                type: '', // upload or github
                attachment_id: '', // Attachment Post ID
                repo_name: '', // Github repo full name
                repo_version: '', // Github version tag
            });
            var row;
            row = metabox.fdDeploy.versionTable.find( 'tbody tr[row-id="'+key+'"]');
            if ( ! row.length ) {
                row = metabox.fdDeploy.versionTable.find( 'tbody tr[data-ver="'+args.version+'"]');
            }
            if ( row.length ) {

                if ( args.free_name && args.free_url ) {
                    $( '.free-version', row ).html( '<a target="_blank" href="' + FD_Config.deploy_url + args.free_url+ '">'+ args.free_name + '</a>' );
                } else if ( args.free_name ) {
                    $( '.free-version', row ).html( args.free_name );
                }

                if ( args.premium_name && args.premium_url ) {
                    $( '.premium-version', row ).html( '<a target="_blank" href="' + FD_Config.deploy_url + args.premium_url+'">'+ args.premium_name + '</a>' );
                } else if ( args.premium_name ) {
                    $( '.premium-version', row ).html( args.premium_name );
                }

                row.attr( 'row-id', key );

                if ( args.current ) {
                    row.addClass( 'current' );
                } else {
                    row.removeClass( 'current' );
                }

                if ( args.version ) {
                    $( '.version-number', row ).html( args.version );
                    row.attr( 'data-ver', args.version );
                }

                if (args.type == 'github' ) {
                    $( '.file-select-button', row ).hide();
                    $( '.fd-github-icon', row ).show();
                } else {
                    $( '.file-select-button', row ).show();
                    $( '.fd-github-icon', row ).hide();
                }

                if ( args.attachment_id > 0  || args.repo_name != '' ) {
                    $( '.fd-update-button', row ).show();
                } else {
                    $( '.fd-update-button', row ).hide();
                }
            }
            metabox.fdDeploy.versions[ key ] = args;
            metabox.fdDeploy.updateVersionData( save );

         };

        metabox.fdDeploy.addRowToTable = function( data, key, save ){
			console.log( 'Add row to table: ', data );
            var tplR = metabox.fdDeploy.versionTable.find( 'tbody tr.tpl').eq( 0 );
            var row_c = tplR.clone();
            row_c.removeClass( 'tpl' );
            metabox.fdDeploy.versionTable.find( 'tbody').prepend( row_c );
            if ( typeof key === "undefined" || ! key ) {
                key = new Date().getTime();
                key = 'k'+key;
            }

            row_c.data( 'key', key );
            row_c.attr( 'row-id', key );

            if ( data.version ) {
                row_c.attr( 'data-ver', data.version.replace( 'v', '' ) );
            }
            metabox.fdDeploy.updateRowData( key, data, save );
            return key;
        };

        // Add exists item to table
        $.each( metabox.fdDeploy.versions, function( key, data ){
            metabox.fdDeploy.addRowToTable( data, key, false );
        } );

        $( '.github-repos', metabox ).each( function(){
            var item = $( this );
            var repoSelect = $( 'select.github_repo', item);
            var versionSelect = $( 'select.github_version', item);
            var button = $( '.fetch-repo', item );

            repoSelect.on( 'change', function( e ){
                var repoName = $( this).val();
                if ( repoName ) {
                    $.ajax({
                        type: 'GET',
                        data: {
                            action: 'fd_ajax_github',
                            repo: repoName,
                            doing: 'get_tags',
                            _nonce: FD_Config.nonce
                        },
                        url: ajaxurl,
                        //cache: false,
                        error: function (xhr, ajaxOptions, thrownError) {

                        },
                        complete: function () {

                        },
                        success: function (res) {
                            versionSelect.html(res.data);
                        }
                    });
                } else {
                    versionSelect.html( '<option value=""> --- </option>' );
                }
            } );

            repoSelect.on( 'option_added', function(){
				var current = repoSelect.attr( 'data-value' ) || '';
				console.log( 'Github option_added', current );
                if ( current ) {
                    $( 'option[value="'+current+'"]').attr( 'selected', 'selected' );
                    repoSelect.trigger( 'change' );
                }
            } );
            repoSelect.trigger( 'change' );

            //repoSelect.trigger( 'change' );
            button.on( 'click', function( e ) {
                e.preventDefault();
                var repoName = repoSelect.val();
                var version = versionSelect.val();
                var post_id = $( '#post_ID').val() || '';

                if ( repoName && version ) {

                    var key = metabox.fdDeploy.addRowToTable( {
                        free_name: FD_Config.deploying,
                        premium_name: FD_Config.deploying,
                        version: version,
                        type: 'github',
                        repo_name: repoName,
                        repo_version: version,
                    } );

                    button.addClass( 'updating-message' );
                    $.ajax({
                        type: 'GET',
                        data: {
                            action: 'fd_ajax_github',
                            repo: repoName,
                            version: version,
                            doing: 'fetch',
                            post_id: post_id,
                            _nonce: FD_Config.nonce
                        },
                        url: ajaxurl,
                        //cache: false,
                        error: function (xhr, ajaxOptions, thrownError) {

                        },
                        complete: function () {
                            button.removeClass( 'updating-message' );
                        },
                        success: function (res) {
							console.log( 'fd_ajax_github ' + repoName + ': ', res );
                            metabox.fdDeploy.ajaxDeploy( 'free', metabox.fdDeploy.versions[ key ] || {}, key );
                        }
                    });
                }

            } );

        } );
        //--------------------------------------------------------

        // Add new upload row
        $( '.fd-new-upload-item', metabox ).on( 'click', function( e ){
            e.preventDefault();
            metabox.fdDeploy.addRowToTable();
        } );


        // When remove a version
        metabox.on( 'click', '.fd-versions-table tr td.remove a', function( e ){
            e.preventDefault();

            var tr =  $( this ).closest( 'tr' );
            var key = tr.data( 'key' );
            var ver = tr.find( '.version-number').text();
            var c;
            if ( metabox.fdDeploy.versions[ key].version == '' ) {
                c = true;
            } else {
                c = confirm( 'Confirm remove version '+ver+' ?' );
            }
            if ( c ) {
                delete metabox.fdDeploy.versions[ key ];
                metabox.fdDeploy.updateVersionData( true );
                $( this ).closest( 'tr' ).remove();
            }

        } );

        // Upload media
        metabox.on( 'click', 'tr .file-select-button', function( e ) {
            e.preventDefault();
            window._upload_fame.open();
            var tr = $( this).closest( 'tr' );
            var key = tr.data( 'key' );

            window._fd_media_current = {
                row: tr,
                button: $( this ),
                metabox: metabox,
                data: metabox.fdDeploy.versions[ key ] || {},
                key: key
            }
        } );

        // Upload button
        metabox.on( 'click', 'tr .fd-update-button', function( e ){
            e.preventDefault();
            var tr = $( this).closest( 'tr' );
            $( '.free-version, .premium-version', tr ).html( FD_Config.deploying );
            $( '.fd-update-button', tr ).addClass( 'spin' );
            var key = tr.data( 'key' );
            metabox.fdDeploy.ajaxDeploy( 'free', metabox.fdDeploy.versions[ key ] || {}, key );
        } );

    } );


} );