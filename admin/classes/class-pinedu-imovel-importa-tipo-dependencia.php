<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';

class Pinedu_Imovel_Importa_Tipo_Dependencia extends Pinedu_Importa_Taxonomia_Base {
	public function __construct() {
		//$this->limpa('faixa_valor');
	}
	public function importa( $tipo_dependencia ) {
		if ( !taxonomy_exists( 'tipo-dependencia' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Tipo de Dependencia não existe!.'] );
			return;
		}
		foreach ( $tipo_dependencia as $tipo ) {
			$sigla = $tipo['sigla'];
			$nome = $tipo['nome'];
			$descricao = $tipo['descricao'];
			$relativo = $tipo['relativo'];
			$ordem = $tipo['ordem'];
			$tipo_campo = $tipo['tipo'];

			$key = $sigla;
			$nome = sanitize_text_field( $nome );
			$term = $this->salva( $key, $nome, 'tipo-dependencia' );
			if ( isset( $term['term_id'] ) ) {
				$id_term = $term[ 'term_id' ];
			} else {
				$id_term = $term;
			}

			if (metadata_exists('term', $id_term, 'nome')) {
				$result = update_term_meta( $id_term, 'nome', $nome );
			} else {
				$result = add_term_meta( $id_term, 'nome', $nome );
			}
			if (metadata_exists('term', $id_term, 'sigla')) {
				$result = update_term_meta( $id_term, 'sigla', $sigla );
			} else {
				$result = add_term_meta( $id_term, 'sigla', $sigla );
			}
			if (metadata_exists('term', $id_term, 'relativo')) {
				$result = update_term_meta( $id_term, 'relativo', $relativo );
			} else {
				$result = add_term_meta( $id_term, 'relativo', $relativo );
			}
			if (metadata_exists('term', $id_term, 'descricao')) {
				$result = update_term_meta( $id_term, 'descricao', $descricao );
			} else {
				$result = add_term_meta( $id_term, 'descricao', $descricao );
			}
			if (metadata_exists('term', $id_term, 'ordem')) {
				$result = update_term_meta( $id_term, 'ordem', $ordem );
			} else {
				$result = add_term_meta( $id_term, 'ordem', $ordem );
			}
			if (metadata_exists('term', $id_term, 'tipo')) {
				$result = update_term_meta( $id_term, 'tipo', $tipo_campo );
			} else {
				$result = add_term_meta( $id_term, 'tipo', $tipo_campo );
			}
		}
	}
	public static function list( $relativo = '' ) {
		if ( !taxonomy_exists( 'tipo-dependencia' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Tipo de dependencia não existe!.'] );
			return false;
		}
		$args = [
			'taxonomy' => 'tipo-dependencia'
			, 'hide_empty' => false
			, 'orderby' => 'meta_value'
			, 'meta_key' => 'ordem'
			, 'order' => 'ASC'
		];
		if ( !empty( $relativo ) ) {
			$args[ 'meta_query' ] = [ [ 'key' => 'relativo' , 'value' => $relativo , 'compare' => '=' ] ];
		}
		return get_terms($args);
	}

	/**
	 * Recolhe a Taxonomia tipo-dependencia, e devolve separada por RELATIVO
	 * @return array( 'CARACTERISTICAS' => $caracteristicas, 'CONDOMINIO' => $condominio, 'EDIFICIO' => $edificio, 'INFRAEXTRUTURA' => $infraextrutura );
	 */
	public static function get_tipo_dependencias() {
		$tipo_dependencias = self::list();
		$edificio = [];
		$condominio = [];
		$caracteristicas = [];
		$infraextrutura = [];
		foreach ( $tipo_dependencias as $tipo_dependencia ) {
			$meta = get_term_meta( $tipo_dependencia->term_id );
			$relativo = $meta['relativo'][0];
			$td = [ 'sigla' => $meta['sigla'][0], 'nome' => $meta['nome'][0], 'descricao' => $meta['descricao'][0], 'relativo' => $relativo, 'tipo' => $meta['tipo'][0] ];
			switch ( $relativo ) {
				case 'CARACTERISTICAS':
					$caracteristicas[] = $td;
					break;
				case 'CONDOMINIO':
					$condominio[] = $td;
					break;
				case 'EDIFICIO':
					$edificio[] = $td;
					break;
				case 'INFRAEXTRUTURA':
					$infraextrutura[] = $td;
					break;
				default:
					break;
			}
		}
		return array( 'CARACTERISTICAS' => $caracteristicas, 'CONDOMINIO' => $condominio, 'EDIFICIO' => $edificio, 'INFRAEXTRUTURA' => $infraextrutura );
	}
}
