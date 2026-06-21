<?php
class Pinedu_Curriculo_Enviar {
    const ENDPOINT = '/wordpress/recebeCurriculo'; // Endpoint específico para currículos

    private $dados;

    public function __construct( $dados ) {
        $this->dados = $dados;
    }

    public function enviar() {
        $options = get_option( 'pinedu_imovel_options', [] );
        $url = $options['url_servidor'] ?? '';

        if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return ['success' => false, 'message' => 'URL do servidor não configurada.'];
        }

        $fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINT, '/' );

        $args = [
            'body' => json_encode([
                'nome'     => sanitize_text_field( $this->dados['nome'] ),
                'email'    => sanitize_email( $this->dados['email'] ),
                'telefone' => sanitize_text_field( $this->dados['celular'] ),
                'cidade'   => sanitize_text_field( $this->dados['cidade'] ),
                'cargo'    => sanitize_text_field( $this->dados['cargo'] ),
                'creci'    => sanitize_text_field( $this->dados['creci'] ),
                'mensagem' => sanitize_textarea_field( $this->dados['mensagem'] ),
                'username' => $options['token_username'],
                'password' => $options['token_password']
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . sanitize_text_field( $options['token'] )
            ]
        ];

        $response = wp_remote_post( $fullUrl, $args );
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}