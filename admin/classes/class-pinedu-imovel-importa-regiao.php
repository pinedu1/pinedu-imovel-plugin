<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';

class Pinedu_Imovel_Importa_Regiao extends Pinedu_Importa_Taxonomia_Base {
	private $cls_bairros;
	public function __construct( ) {
		require_once plugin_dir_path(__FILE__) . 'class-pinedu-imovel-importa-bairro.php';
		$this->cls_bairros = new Pinedu_Imovel_Importa_Bairro();
		//$this->limpa('regiao');
	}
	public function importa_regioes( $regioes, $cidade_pai ) {
		if ( !taxonomy_exists( 'regiao' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Regiao não existe!.'] );
			return;
		}

		foreach ( $regioes as $regiao ) {
			$key = ( string )$regiao['id'];
			$nome = sanitize_text_field( $regiao['nome'] );
			$term = $this->salva( $key, $nome, 'regiao');
			if ( !is_wp_error( $term ) ) {
				if ( metadata_exists( 'term', $term['term_id'], 'parent_id' ) ) {
					$result = update_term_meta( $term['term_id'], 'parent_id', $cidade_pai );
				} else {
					$result = add_term_meta( $term['term_id'], 'parent_id', $cidade_pai );
				}
			}

			$bairros = $regiao['bairros'];
			if ( !empty( $bairros ) ) {
				$this->cls_bairros->importa_bairros( $bairros, $key );
			}
		}
	}
    public static function list( $cidade ) {
        if ( !taxonomy_exists( 'regiao' ) ) {
           wp_send_json_error( ['message' => 'Taxonomia Regiao não existe!.'] );
           return false;
        }

        // 1. Gera uma chave única para esta cidade específica
        $cidade_id = str_pad($cidade, 4, '0', STR_PAD_LEFT);
        $transient_key = 'pnd_list_regiao_' . $cidade_id;

        // 2. Tenta buscar do cache
        $terms = get_transient( $transient_key );

        if ( false !== $terms ) {
            return $terms;
        }

        // 3. Se não houver cache, realiza a busca
        $args = array(
           'taxonomy' => 'regiao'
           , 'hide_empty' => false
           , 'orderby' => 'slug'
           , 'order' => 'ASC'
           , 'meta_query' => [
                 [
                    'key' => 'parent_id'
                    , 'value' => $cidade_id
                    , 'compare' => '='
                 ]
              ]
        );

        $terms = get_terms($args);

        // 4. Salva o resultado no cache por 1 hora
        set_transient( $transient_key, $terms, 40 * MINUTE_IN_SECONDS );

        return $terms;
    }
}
