<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';

class Pinedu_Imovel_Importa_Faixa_Valor extends Pinedu_Importa_Taxonomia_Base {
	public function __construct() {
		//$this->limpa('faixa_valor');
	}
	public function importa( $faixas ) {
		if ( !taxonomy_exists( 'faixa-valor' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Faixa de Valor não existe!.'] );
			return;
		}
		foreach ( $faixas as $faixa ) {
			$valor_inicial = $faixa['valorInicial'];
			$valor_final = $faixa['valorFinal'];
			$tipo_contrato = $faixa['tipoContrato'];
			$key = $tipo_contrato . '-' . $valor_inicial . '-' . $valor_final;
			$nome = sanitize_text_field( $faixa['value'] );
			$term = $this->salva( $key, $nome, 'faixa-valor' );
			if ( isset( $term['term_id'] ) ) {
				$id_term = $term[ 'term_id' ];
			} else {
				$id_term = $term;
			}

			if (metadata_exists('term', $id_term, 'valor-inicial')) {
				$result = update_term_meta( $id_term, 'valor-inicial', (float)$valor_inicial );
			} else {
				$result = add_term_meta( $id_term, 'valor-inicial', (float)$valor_inicial );
			}
			if (metadata_exists('term', $id_term, 'valor-final')) {
				$result = update_term_meta( $id_term, 'valor-final', (float)$valor_final  );
			} else {
				$result = add_term_meta( $id_term, 'valor-final', (float)$valor_final );
			}
			if (metadata_exists('term', $id_term, 'tipo-contrato')) {
				$result = update_term_meta( $id_term, 'tipo-contrato', (int)$tipo_contrato );
			} else {
				$result = add_term_meta( $id_term, 'tipo-contrato', (int)$tipo_contrato );
			}
		}
	}
	public static function list( $contrato ) {
		if ( !taxonomy_exists( 'faixa-valor' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Faixa de Valor não existe!.'] );
			return false;
		}
		$args = [
			'taxonomy'   => 'faixa-valor'
			, 'hide_empty' => false
			, 'orderby' => 'meta_value_num'
			, 'meta_key' => 'valor-inicial'
			, 'order'   => 'ASC'
		];
		if ( !empty( $contrato ) ) {
			$args[ 'meta_query' ] = [ [ 'key'     => 'tipo-contrato' , 'value'   => $contrato , 'compare' => '=' ] ];
		}
		return get_terms($args);
	}
}
