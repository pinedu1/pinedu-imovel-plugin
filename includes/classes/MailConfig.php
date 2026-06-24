<?php

	class MailConfig {
		public static function config_options_mail( ) {
			$options = get_option( 'pinedu_imovel_options', []);
            $options['url_servidor'] = 'https://haddad.intranet.pndimo.com.br/sisprof';
            $options['nome_remetente'] = 'Pinedu Software';
			$options['email_remetente'] = 'eduardo@pinedu.com.br';
			$options['usuario'] = 'no-reply@pinedu.com.br';
			$options['password'] = '3hAv8nOX@R&m#72Y';
			$options['servidor_smtp'] = 'email-ssl.com.br';
			$options['porta_smtp'] = 465;
			$options['tipo_seguranca'] = 'ssl';
			$options['requer_autenticacao'] = 'on';
			$options['email_contato'] = 'eduardo@pinedu.com.br';
			$options['token'] = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ3b3JkcHJlc3MiLCJwYXNzd29yZCI6IndvcmRwcmVzczEyMyIsIlpFTSI6MSwiaWF0IjoxNzYzNDkyMTYzLCJleHAiOjE3NjM1Nzg1NjN9.P4UQy7QRcjsHAIGc5jh-1WCLFoKyzeERr-Pp97pfVdA';
			$options['token_username'] = 'wordpress';
			$options['token_password'] = 'wordpress123';
            $options['token_expiration_date'] = '1980-01-01T00:00:00-300';
			$options['chave_google_api'] = 'AIzaSyB5Jxg-gp9IQ5cXT_fBce1DLMpW8cLVjE0';
            $options['chave_publica_recaptcha'] = '6LcSxystAAAAAHn_1Tu-FSJ9Fro6nlGh3h1NA71i';
            $options['chave_secreta_recaptcha'] = '6LcSxystAAAAAC3x2o6yw4nUJTdj2P7QrO5IasJd';
            $options['fotos_demanda'] = 'off';
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