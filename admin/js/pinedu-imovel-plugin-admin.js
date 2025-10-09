( function ( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $( function( ) {
	 *
	 * } );
	 *
	 * When the window is loaded:
	 *
	 * $ ( window ).load( function( ) {
	 *
	 * } );
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
} ) ( jQuery );
$ = jQuery;
function importarImoveisNormal( e ) {
	e.preventDefault( );
	importarImoveis( false );
}
function importarImoveisForcado( e ) {
	e.preventDefault( );
	importarImoveis( true );
}
function importarImoveis( forcarImportarImoveis ) {
    const info = $( 'div.informacao' ); // Seleciona TODAS as divs com classe info
	const urlServidor = $( '#url_servidor' ).val( );
	info.removeClass( 'error' ).removeClass( 'success' ).addClass( 'info' ).text( 'Processando importação, esta operação pode demorar algumas horas. Por favor, aguarde...' ).fadeIn( );;
	const forcar = (forcarImportarImoveis == true);
    $("button#testar-servidor-btn, button#importar-btn, button#importar-forcado-btn").prop('disabled', true);
	$.ajax({
		url: ajaxurl,
		type: 'POST',
		data: {
			action: 'pinedu_importar',
			url_servidor: urlServidor,
			forcar: forcar
		},
		timeout: 7200000, // 2 hora em milissegundos
		beforeSend: function() {
			info.removeClass('error success').addClass('info').text('Processando importação, esta operação pode demorar algumas horas. Por favor, aguarde...').fadeIn();
		}
	}).done(function(response) {
		if (response.success) {
			var dados = response.data;
			$('#token').val(dados.token);
			info.removeClass('error info').addClass('success')
				.text(dados.message).fadeIn();
			const dateString = dados.ultima_atualizacao.date.replace(' ', 'T') + 'Z';
			const dateUTC = new Date(dateString);

			const dt = new Intl.DateTimeFormat('pt-BR', {
				timeZone: 'America/Sao_Paulo',
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			}).format(dateUTC);

			var prox = dados.proxima_atualizacao > 0 ?
				new Intl.DateTimeFormat('pt-BR', {
					timeZone: 'America/Sao_Paulo',
					day: '2-digit',
					month: '2-digit',
					year: 'numeric',
					hour: '2-digit',
					minute: '2-digit',
					second: '2-digit',
					hour12: false
				}).format(new Date(dados.proxima_atualizacao * 1000)) :
				null;
			$("#status-importacao").empty().append(
				$('<ul>').append(
					$('<li>').append($('<div>', { id: 'ultima_atualizacao' }).append(
						$('<p>').append(
							$('<strong>').text('Última atualização: '), dt
						)
					)),
					$('<li>').append($('<div>', { id: 'imoveis_importados' }).append(
						$('<p>').append(
							$('<strong>').text('Imóveis importados: '),
							dados.imoveis_importados
						)
					)),
					$('<li>').append($('<div>', { id: 'tempo_utilizado' }).append(
						$('<p>').append(
							$('<strong>').text('Tempo utilizado: '),
							dados.tempo_utilizado
						)
					)),
					$('<li>').append($('<div>', { id: 'proxima_atualizacao' }).append(
						$('<p>').append(
							$('<strong>').text('Próxima atualização: '),
							prox || ''
						)
					))
				)
			);
		} else {
			info.removeClass('success info').addClass('error').text(response.data.message).fadeIn();
		}
        $("button#testar-servidor-btn, button#importar-btn, button#importar-forcado-btn").prop('disabled', false);
		info.delay(5000).fadeOut();
	})
	.fail(function(jqXHR, textStatus, errorThrown) {
		var errorMessage = 'Timeout no servidor. Volte novamente daqui 1 hora e verifique o resultado da importação.';

		if (textStatus === 'timeout') {
			errorMessage = 'A operação excedeu o tempo limite de 1 hora.';
		} else if (jqXHR.status === 504) {
			errorMessage = 'Timeout no servidor (Gateway Timeout).';
		} else if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
			errorMessage = jqXHR.responseJSON.data.message;
		}
		info.removeClass('success info').addClass('error')
			.text(errorMessage).fadeIn()
			.delay(5000).fadeOut();
        $("button#testar-servidor-btn, button#importar-btn, button#importar-forcado-btn").prop('disabled', false);
	});
}
function testarServidor( e ) {
    e.preventDefault();

    const urlServidor = $( '#url_servidor' ).val();
    const info = $( 'div.informacao' ); // Seleciona TODAS as divs com classe info

    // Esconde e limpa todas as divs de info
    info.removeClass( 'error' ).removeClass( 'success' ).removeClass( 'info' )
        .addClass( 'info' ).text( 'Testando Conexão com o servidor remoto. Por favor, aguarde.' )
        .stop( true, true ).fadeIn();
    $("button#testar-servidor-btn, button#importar-btn, button#importar-forcado-btn").prop('disabled', true);
    $.post( ajaxurl, {
        action: 'pinedu_testar_servidor',
        url_servidor: urlServidor
    }, function( response ) {
        if ( response.success ) {
            info.removeClass( 'error' ).removeClass( 'info' )
                .addClass( 'success' ).text( response.data.message )
                .stop( true, true ).fadeIn();
            $( '#url_servidor' ).val( response.data.url_servidor );
        } else {
            info.removeClass( 'success' ).removeClass( 'info' )
                .addClass( 'error' ).text( response.data.message )
                .stop( true, true ).fadeIn();
        }
        // Esconde todas após delay
        info.delay( 5000 ).fadeOut();
        $("button#testar-servidor-btn, button#importar-btn, button#importar-forcado-btn").prop('disabled', false);
    } ).fail( function( ) {
        info.removeClass( 'success' ).removeClass( 'info' )
            .addClass( 'error' ).text( 'Houve um erro desconhecido!' )
            .stop( true, true ).fadeIn();
        info.delay( 5000 ).fadeOut();
        $("button#testar-servidor-btn, button#importar-btn, button#importar-forcado-btn").prop('disabled', false);
    } );
}