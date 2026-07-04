<?php

class Pinedu_Imovel_Enviar_Cliente {
    const ENDPOINT = '/wordpress/contatoCliente';
    const ENDPOINTVISITA     = '/wordpress/solicitarVisita';
	private $nome;
	private $telefone;
	private $email;
	private $cookie;
	private $mensagem;
	private $referencia;
    private $corretor;

    public function setMensagem( $mensagem ): void {
		$this->mensagem = $mensagem;
	}
	public function __construct( $nome, $telefone, $email, $mensagem, $cookie, $referencia = null, $corretor = null ) {
		$this->setNome( $nome );
		$this->setTelefone( $telefone );
		$this->setEmail( $email );
		$this->setCookie( $cookie );
		$this->setMensagem( $mensagem );
		if ( $referencia ) {
			$this->referencia = $referencia;
		}
        if ( $corretor ) {
            $this->corretor = $corretor;
        }
	}
    public function contato_cliente( ) {
        $options = get_option( 'pinedu_imovel_options', [] );
        $url = $options[ 'url_servidor' ] ?? '';

        if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_send_json_error( ['message' => 'URL inválida.'] );
            return false;
        }

        $fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINT, '/' );

        // 1. Prepara apenas os dados de negócio.
        // Não precisa injetar username/password aqui, a PineduRequest já faz isso.
        $argumentos = [
            'nome'       => sanitize_text_field( $this->nome ),
            'telefone'   => sanitize_text_field( $this->telefone ),
            'email'      => sanitize_text_field( $this->email ),
            'cookie'     => sanitize_text_field( $this->cookie ),
            'mensagem'   => sanitize_text_field( $this->mensagem ),
            'referencia' => sanitize_text_field( $this->referencia ),
            'corretor'   => sanitize_text_field( $this->corretor )
        ];

        // 2. Chama a fábrica de requisições enviando $isHook = true
        // Se o refresh_token falhar, a própria PineduRequest encerra o processo com wp_send_json_error.
        $data = PineduRequest::post( $fullUrl, $argumentos, true );

        return $data;
    }
    public function solicitar_visita( ) {
        $options = get_option( 'pinedu_imovel_options', [] );
        $url = $options[ 'url_servidor' ] ?? '';

        if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_send_json_error( ['message' => 'URL inválida.'] );
            return false;
        }

        $fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINTVISITA, '/' );

        // 1. Prepara apenas os dados de negócio.
        // Não precisa injetar username/password aqui, a PineduRequest já faz isso.
        $argumentos = [
            'nome'       => sanitize_text_field( $this->nome ),
            'telefone'   => sanitize_text_field( $this->telefone ),
            'email'      => sanitize_text_field( $this->email ),
            'cookie'     => sanitize_text_field( $this->cookie ),
            'mensagem'   => sanitize_text_field( $this->mensagem ),
            'referencia' => sanitize_text_field( $this->referencia ),
            'corretor'   => sanitize_text_field( $this->corretor )
        ];

        // 2. Chama a fábrica de requisições enviando $isHook = true
        // Se o refresh_token falhar, a própria PineduRequest encerra o processo com wp_send_json_error.
        $data = PineduRequest::post( $fullUrl, $argumentos, true );

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
	public function getMensagem( ) {
		return $this->mensagem;
	}

	/**
	 * @param mixed $mensagem
	 */
}
