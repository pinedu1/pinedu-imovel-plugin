<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';
class Pinedu_Imovel_Importa_Tipo_Imovel extends Pinedu_Importa_Taxonomia_Base {
	public function __construct() {
		//$this->limpa('tipo_imovel');
	}
	public function importa_tipo_imoveis( $tipo_imoveis ) {
		if ( !taxonomy_exists( 'tipo-imovel' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Tipo Imóvel não existe!.'] );
			return;
		}

		foreach ( $tipo_imoveis as $tipo_imovel ) {
			$key = ( string )$tipo_imovel['id'];
			$nome = sanitize_text_field( $tipo_imovel['nome'] );
			$this->salva($key, $nome, 'tipo-imovel');
		}
	}
	public static function list( ) {
		if ( !taxonomy_exists( 'tipo-imovel' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Tipo de Imóvel não existe!.'] );
			return false;
		}
		$args = array(
			'taxonomy'   => 'tipo-imovel'
			, 'hide_empty' => true
			, 'orderby'    => 'name'
			, 'order'      => 'ASC'
		);
		return get_terms($args);
	}

}
