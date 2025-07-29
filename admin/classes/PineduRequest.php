<?php

class PineduRequest{
	private const ENDPOINT = '/pndPortal/wordpress/index';
	private const TIMEOUT = 600;
	public function __construct( ) {
	}
	public static function get( $url, $argumentos = [], $isHook = false ) {
		//error_log( "PineduRequest: " . $url );

		$options = get_option('pinedu_imovel_options', []);
		$token = $options['token'] ?? '';
		$headers = [
			'timeout' => self::TIMEOUT
			, 'headers' => [ 'Content-Type' => 'application/json' , 'Authorization' => 'Bearer ' . sanitize_text_field( $token ) ]
			, 'sslverify' => true
		];
		$credenciais = [ 'username' => $options['token_username'], 'password' => $options['token_password'] ];
		$args = array_merge( $credenciais, $argumentos );
		$my_url = self::monta_get_url( $url, $args );
		$response = wp_remote_get( $my_url, $headers );
		if ( is_wp_error( $response ) ) {
			if ( $isHook ) wp_send_json_error( ['message' => 'Erro de conexão com o servidor'] );
		}
		$body = wp_remote_retrieve_body( $response );

		return json_decode( $body, true );
	}
	public static function getFile( $url, $file_name, $argumentos = [] ) {
		$options = get_option('pinedu_imovel_options', []);
		$token = $options['token'] ?? '';
		$headers = [
			'timeout' => self::TIMEOUT
			, 'headers' => [ 'Content-Type' => 'application/json' , 'Authorization' => 'Bearer ' . sanitize_text_field( $token ) ]
			, 'sslverify' => true
			, 'stream' => true
			, 'filename' => $file_name
		];
		$credenciais = [ 'username' => $options['token_username'], 'password' => $options['token_password'] ];
		$args = array_merge( $credenciais, $argumentos );
		$my_url = self::monta_get_url( $url, $args );
		$response = wp_remote_get( $my_url, $headers );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( ['message' => 'Erro de conexão com o servidor'] );
			return null;
		}

		return $response;
	}
	public static function post( $url, $argumentos = [], $isHook = false ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		$credenciais = [ 'username' => $options['token_username'], 'password' => $options['token_password'] ];
		if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( ['message' => 'URL inválida.'] );
			return false;
		}
		$token = $options['token'] ?? '';
		$payload = [
			'timeout' => self::TIMEOUT
			, 'headers' => [ 'Content-Type' => 'application/json' , 'Authorization' => 'Bearer ' . sanitize_text_field( $token ) ]
			, 'sslverify' => true
		];
		if ( $argumentos && is_array( $argumentos ) ) {
			$a = array_merge( $credenciais, $argumentos );
			$payload['body'] = wp_json_encode( $a );
		} else {
			$payload[ 'body' ] = wp_json_encode( $credenciais );
		}

		$response = wp_remote_post( $url, $payload );
		if ( is_wp_error( $response ) ) {
			error_log( "Erro: " . json_encode( $response ) );
			if ( $isHook ) wp_send_json_error( ['message' => 'Erro de conexão com o servidor'] );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		return $data;
	}
	protected static function monta_get_url( $url, $args) {
		return add_query_arg( $args, $url );
	}
	public static function put( $url, $data ) {}
	public static function delete( $url ) {}
}