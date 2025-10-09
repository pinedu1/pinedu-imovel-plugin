<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';

class Pinedu_Imovel_Importa_Contrato extends Pinedu_Importa_Taxonomia_Base {
	public function __construct() {
		//$this->limpa('contrato');
	}

	public function importar( $contratos ) {
		if ( !taxonomy_exists( 'contrato' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Contrato não existe!.'] );
			return;
		}
		foreach ( $contratos as $contrato ) {
			$key = ( int )$contrato['id'];
			$nome = sanitize_text_field( $contrato['nome'] );
			$this->salva( $key, $nome, 'contrato' );
		}
	}
	public static function list( ) {
		if ( !taxonomy_exists( 'contrato' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Contrato não existe!.'] );
			return false;
		}
		$args = array(
			'taxonomy'   => 'contrato'
			, 'hide_empty' => false
			, 'orderby'    => 'slug'
			, 'order'      => 'ASC'
		);
		return get_terms($args);
	}
}
