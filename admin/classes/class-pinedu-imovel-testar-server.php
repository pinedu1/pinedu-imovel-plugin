<?php

class Pinedu_Imovel_Testar_Server {
	const ENDPOINT = '/pndPortal/wordpress/index';
	public function __construct() {
		add_action('wp_ajax_pinedu_testar_servidor', [$this, 'invoca_server']);
	}

	/**
	 * @param $url: Url do servidor de Importação
	 * @param $isHook: Indica que a chamada é via Hook | TRUE, para dar sendError | FALSE chamada como função, teste interno
	 * @return false|mixed
	 */
	public static function testar_server( $url, $isHook = false ) {
		$options = get_option('pinedu_imovel_options', []);
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			wp_send_json_error(['message' => 'URL inválida.']);
			return false;
		}
		$fullUrl = trailingslashit($url) . ltrim(self::ENDPOINT, '/');
		$token = $options['token'] ?? '';
		$response = wp_remote_post($fullUrl, [
			'timeout' => 10
			, 'headers' => [
				'Content-Type' => 'application/json'
				, 'Authorization' => 'Bearer ' . sanitize_text_field( $token )
			]
			, 'body' => wp_json_encode( [ 'username' => $options['token_username'], 'password' => $options['token_password'] ] )
			, 'sslverify' => true
		]);

		if (is_wp_error($response)) {
			if ($isHook) wp_send_json_error(['message' => 'Erro de conexão com o servidor']);
			return false;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		return $data;
	}
	public function invoca_server( ) {
		$url = $_POST['url_servidor'] ?? '';

		$data = self::testar_server( $url, true );

		if (isset($data['success']) && $data['success'] === true) {
			$options = get_option('pinedu_imovel_options', []);
			$options['url_servidor'] = $data['url_servidor'] ?? '';
			$ultima_carga = $data['ultimaCarga'];
			if (!empty($ultima_carga)) {
				$options['ultima_atualizacao'] = $ultima_carga;
			}
			$options['url_servidor'] = $data['url_servidor'] ?? '';
			update_option('pinedu_imovel_options', $options);

			wp_send_json_success([
				'message' => $data['message'] ?? 'Servidor OK'
				, 'url_servidor' => $data['url_servidor']
			]);
		} else {
			wp_send_json_error([
				'message' => $data['message'] ?? 'Servidor respondeu com erro'
			]);
		}
	}
}
