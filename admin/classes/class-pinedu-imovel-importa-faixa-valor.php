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
			$args[ 'meta_query' ] = [ [ 'key' => 'tipo-contrato', 'value' => $contrato, 'compare' => '=' ] ];
		}
		return get_terms($args);
	}
    public static function lista( $contrato ) {
        if ( !taxonomy_exists( 'faixa-valor' ) ) {
            wp_send_json_error( ['message' => 'Taxonomia Faixa de Valor não existe!.'] );
            return false;
        }

        // 1. Gera uma chave única baseada no contrato (ou 'all' se estiver vazio)
        $contrato_key = !empty($contrato) ? sanitize_title($contrato) : 'all';
        $transient_key = 'pnd_lista_faixa_valor_' . $contrato_key;

        // 2. Tenta buscar o array já formatado no cache
        $cached_fx = get_transient( $transient_key );

        if ( false !== $cached_fx ) {
            return $cached_fx;
        }

        // 3. Se não houver cache, processa a lógica
        $args = [
            'taxonomy'   => 'faixa-valor'
            , 'hide_empty' => false
            , 'orderby' => 'meta_value_num'
            , 'meta_key' => 'valor-inicial'
            , 'order'   => 'ASC'
        ];

        if ( !empty( $contrato ) ) {
            $args[ 'meta_query' ] = [ [ 'key' => 'tipo-contrato', 'value' => $contrato, 'compare' => '=' ] ];
        }

        $terms = get_terms($args);
        $fx = [];

        foreach ($terms as $term) {
            $id_term = $term->term_id;
            $fx[] = [
                'id'            => $id_term
                , 'nome'        => (string)$term->name
                , 'valor-inicial'=> get_term_meta( $id_term, 'valor-inicial')
                , 'valor-final'  => get_term_meta( $id_term, 'valor-final')
            ];
        }

        // 4. Salva o array resultante no cache por 1 hora
        set_transient( $transient_key, $fx, 1 * HOUR_IN_SECONDS );

        return $fx;
    }
}
