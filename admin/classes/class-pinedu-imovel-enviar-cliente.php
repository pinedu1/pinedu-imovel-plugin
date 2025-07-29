<?php

class Pinedu_Imovel_Enviar_Cliente {
	const ENDPOINT = '/pndPortal/wordpress/contatoCliente';
	private $nome;
	private $telefone;
	private $email;
	private $cookie;
	private $mensagem;
	private $referencia;
	public function setMensagem( $mensagem ): void {
		$this->mensagem = $mensagem;
	}
	public function __construct( $nome, $telefone, $email, $mensagem, $cookie, $referencia = null ) {
		$this->setNome( $nome );
		$this->setTelefone( $telefone );
		$this->setEmail( $email );
		$this->setCookie( $cookie );
		$this->setMensagem( $mensagem );
		if ( $referencia ) {
			$this->referencia = $referencia;
		}
	}
	public function contato_cliente( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		$url = $options[ 'url_servidor' ];
		if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( ['message' => 'URL inválida.'] );
			return false;
		}
		$fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINT, '/' );
		$args = [
			'body' => json_encode(
				[
					'nome' => sanitize_text_field($this->nome)
					, 'telefone' => sanitize_text_field($this->telefone)
					, 'email' => sanitize_text_field($this->email)
					, 'cookie' => sanitize_text_field($this->cookie)
					, 'mensagem' => sanitize_text_field($this->mensagem)
					, 'referencia' => sanitize_text_field($this->referencia)
					, 'username' => $options['token_username']
					, 'password' => $options['token_password']
				]
			)
			, 'headers' => [
				'Content-Type' => 'application/json'
				, 'Authorization' => 'Bearer ' . sanitize_text_field( $options['token'] )
			]
		];
		$response = wp_remote_post( $fullUrl, $args );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data;
	}
	public function invoca_server( ) {
		$data = self::contato_cliente( );

		if ( isset( $data['success'] ) && $data['success'] === true ) {
			wp_send_json_success( [
				'message' => $data['message'] ?? 'Servidor OK'
				, 'url_servidor' => $data['url_servidor']
			] );
			return $data;
		} else {
			wp_send_json_error( [
				'message' => $data['message'] ?? 'Servidor respondeu com erro'
			] );
		}
		return false;
	}
	/**
	 * @return mixed
	 */
	public function getNome( ) {
		return $this->nome;
	}
	/**
	 * @param mixed $nome
	 */
	public function setNome( $nome ): void {
		$this->nome = $nome;
	}
	/**
	 * @return mixed
	 */
	public function getTelefone( ) {
		return $this->telefone;
	}
	/**
	 * @param mixed $telefone
	 */
	public function setTelefone( $telefone ): void {
		$this->telefone = $telefone;
	}
	/**
	 * @return mixed
	 */
	public function getEmail( ) {
		return $this->email;
	}
	/**
	 * @param mixed $email
	 */
	public function setEmail( $email ): void {
		$this->email = $email;
	}
	/**
	 * @return mixed
	 */
	public function getCookie( ) {
		return $this->cookie;
	}
	/**
	 * @param mixed $cookie
	 */
	public function setCookie( $cookie ): void {
		$this->cookie = $cookie;
	}
	/**
	 * @return mixed
	 */
	public function getMensagem() {
		return $this->mensagem;
	}

	/**
	 * @param mixed $mensagem
	 */
}
