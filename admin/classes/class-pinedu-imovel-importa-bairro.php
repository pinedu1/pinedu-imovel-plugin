<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';

class Pinedu_Imovel_Importa_Bairro extends Pinedu_Importa_Taxonomia_Base {
	public function __construct() {
		//$this->limpa('bairro');
	}
	public function importa_bairros( $bairros, $regiao_pai ) {
		if ( !taxonomy_exists( 'bairro' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Bairro não existe!.'] );
			return;
		}
		foreach ( $bairros as $bairro ) {
			$key = ( int )$bairro['id'];
			$nome = sanitize_text_field( $bairro['nome'] );
			$term = $this->salva( $key, $nome, 'bairro');
			if ( !is_wp_error( $term ) ) {
				if ( metadata_exists( 'term', $term['term_id'], 'parent_id' ) ) {
					$result = update_term_meta( $term['term_id'], 'parent_id', $regiao_pai );
				} else {
					$result = add_term_meta( $term['term_id'], 'parent_id', $regiao_pai );
				}
			}
		}
	}
}
