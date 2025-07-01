<?php

	class MailConfig {
		public static function config_options_mail( ) {
			$options = get_option( 'pinedu_imovel_options' );

			$options['nome_remetente'] = 'Pinedu Software';
			$options['email_remetente'] = 'eduardo@pinedu.com.br';
			$options['usuario'] = 'no-reply@pinedu.com.br';
			$options['password'] = '3hAv8nOX@R&m#72Y';
			$options['servidor_smtp'] = 'email-ssl.com.br';
			$options['porta_smtp'] = 465;
			$options['tipo_seguranca'] = 'ssl';
			$options['requer_autenticacao'] = 'on';
			$options['email_contato'] = 'eduardo@pinedu.com.br';

			$options['chave_google_api'] = 'AIzaSyBe89OvSNnCtC3RrHKES0PrgGtbQ4lZAu0';

			update_option( 'pinedu_imovel_options', $options );
		}
		public static function config_wp_mail( $phpmailer ) {
			$options = get_option( 'pinedu_imovel_options' );
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