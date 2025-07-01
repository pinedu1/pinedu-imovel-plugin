<?php

class CookieUtil {
	const NOME_COOKIE = 'PND_VISITANTE';
	const DIAS_VALIDADE_COOKIE = 90;

	/**
	 * Obtém o cookie do visitante
	 * @return string|null Valor do cookie ou null se não existir
	 */
	public static function getCookie() {
		if (isset($_COOKIE[self::NOME_COOKIE]) && $_COOKIE[self::NOME_COOKIE] !== 'true') {
			return sanitize_text_field($_COOKIE[self::NOME_COOKIE]);
		}
		return null;
	}
	public static function getCookieId() {
		if ( isset( $_COOKIE[ self::NOME_COOKIE ] ) ) {
			$valor_cookie = stripslashes( $_COOKIE[ self::NOME_COOKIE ] );
			$dados = json_decode( $valor_cookie, true );
			if ( is_array( $dados ) && isset( $dados['id'] ) ) {
				return $dados['id'];
			}
		}
		return false;
	}

	/**
	 * @param $nome
	 * @param $email
	 * @param $telefone
	 * @return: string Id do Cookie
	 */
	public static function update_cookie( $nome, $telefone, $email ) {
		if ( isset( $_COOKIE[ self::NOME_COOKIE ] ) ) {
			$dados = json_decode( $_COOKIE[ self::NOME_COOKIE ], true );
			if ( ! is_array( $dados ) ) {
				$dados = [ 'id' => str_replace('-', '', wp_generate_uuid4( ) ) ];
			}
		} else {
			$dados = [ 'id' => str_replace('-', '', wp_generate_uuid4( ) ) ];
		}
		$dados['nome'] = sanitize_text_field( $nome );
		$dados['email'] = sanitize_email( $email );
		$dados['telefone'] = sanitize_text_field( $telefone );

		$expira = time() + (24 * 60 * 60 * self::DIAS_VALIDADE_COOKIE);
		setcookie(
			self::NOME_COOKIE,
			json_encode( $dados ),
			[
				'expires' => $expira,
				'path' => COOKIEPATH,
				'domain' => COOKIE_DOMAIN,
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax'
			]
		);
		return $dados['id'];
	}
	/**
	 * Cria um novo cookie para o visitante
	 * @return string Valor do cookie criado
	 */
	public static function criaCookie() {
		$cookie_value = [
			'id' => str_replace('-', '', wp_generate_uuid4())
			, 'nome' => ''
			, 'email' => ''
			, 'telefone' => ''
		];
		$expira = time() + (24 * 60 * 60 * self::DIAS_VALIDADE_COOKIE);
		setcookie(
			self::NOME_COOKIE
			, json_encode( $cookie_value )
			, [
				'expires' => $expira
				, 'path' => COOKIEPATH
				, 'domain' => COOKIE_DOMAIN
				, 'secure' => is_ssl()
				, 'httponly' => true
				, 'samesite' => 'Lax'
			]
		);
		// Atualiza também a variável global para acesso imediato
		//$_COOKIE[self::NOME_COOKIE] = $cookie_value;
		return $cookie_value;
	}
}