<?php

	class MailConfig {
		public static function config_options_mail( ) {
			$options = get_option( 'pinedu_imovel_options', []);
            $options['url_servidor'] = 'https://haddad.intranet.pndimo.com.br/sisprof';
            //$options['url_servidor'] = 'https://haddad.haddadimoveis.com.br/sisprof';
            $options['nome_remetente'] = 'Pinedu Software';
			$options['email_remetente'] = 'eduardo@pinedu.com.br';
			$options['usuario'] = 'no-reply@pinedu.com.br';
			$options['password'] = '3hAv8nOX@R&m#72Y';
			$options['servidor_smtp'] = 'email-ssl.com.br';
			$options['porta_smtp'] = 465;
			$options['tipo_seguranca'] = 'ssl';
			$options['requer_autenticacao'] = 'on';
			$options['email_contato'] = 'eduardo@pinedu.com.br';
			$options['token'] = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ3b3JkcHJlc3MiLCJST0xFIjoiUk9MRV9XT1JEUFJFU1MiLCJwYXNzd29yZCI6IntiY3J5cHR9JDJhJDEwJFhIVGhpak5xcy54cFhQZGJPY05XMWVKaEhmdkpyQWNuMnRVcld5N2M4M29qSGN1Y1NHeEdXIiwiaWF0IjoxNzUzNDU3OTM5LCJleHAiOjE3NTYwNDk5Mzl9.muCTcCFJnagRtXv_XeQHeBIRBylKM7onzH2mRRjtU1s';
			$options['token_username'] = 'wordpress';
			$options['token_password'] = 'wordpress123';
            $options['token_expiration_date'] = '1980-01-01T00:00:00-300';
			$options['chave_google_api'] = 'AIzaSyBe89OvSNnCtC3RrHKES0PrgGtbQ4lZAu0';
            $options['fotos_demanda'] = 'on';
			update_option( 'pinedu_imovel_options', $options );
		}
		public static function config_wp_mail( $phpmailer ) {
			$options = get_option( 'pinedu_imovel_options', [] );
			$phpmailer->IsSMTP( );
			$phpmailer->Host = $options[ 'servidor_smtp' ];
			$phpmailer->Port = $options[ 'porta_smtp' ];
			$phpmailer->Username = $options[ 'usuario' ];
			$phpmailer->Password = $options[ 'password' ];
			$phpmailer->SMTPAuth = ( $options[ 'requer_autenticacao' ] == true ) || ( $options[ 'requer_autenticacao' ] == 'on' );
			$phpmailer->SMTPSecure = $options[ 'tipo_seguranca' ];
			$phpmailer->From = $options[ 'email_remetente' ];
			$phpmailer->FromName = $options[ 'nome_remetente' ];
		}
		public static function mostrar_erro_envio_email( $wp_error ) {
			// Verifica se há erros
			if( is_wp_error( $wp_error ) ) {
				// Obtém mensagens de erro
				$erro_mensagem = $wp_error->get_error_message( );

				// Exibe as mensagens de erro na tela
				echo '<div class="erro-envio-email">';
				echo '<p>Ocorreu um erro ao enviar o e-mail:</p>';
				echo '<p>' . esc_html( $erro_mensagem ) . '</p>';
				echo '</div>';
			}
		}
	}