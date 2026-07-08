<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';
class Pinedu_Imovel_Importa_Cidade extends Pinedu_Importa_Taxonomia_Base {
	private $cls_regioes;
	public function __construct( ) {
		require_once plugin_dir_path(__FILE__) . 'class-pinedu-imovel-importa-regiao.php';
		$this->cls_regioes = new Pinedu_Imovel_Importa_Regiao();
		//$this->limpa('cidade');
	}
	public function importa_cidades( $cidades ) {
		if ( !taxonomy_exists( 'cidade' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Cidade não existe!.'] );
			return;
		}

		foreach ( $cidades as $cidade ) {
			$key = ( string )$cidade['id'];
			$nome = sanitize_text_field( $cidade['nome'] );
			$this->salva($key, $nome, 'cidade');
			$regioes = $cidade['regioes'];
			if ( !empty( $regioes ) ) {
				$this->cls_regioes->importa_regioes( $regioes, $key );
			}
		}
	}
    public static function list( ) {
        if ( !taxonomy_exists( 'cidade' ) ) {
           wp_send_json_error( ['message' => 'Taxonomia Cidade não existe!.'] );
           return false;
        }

        // 1. Chave única para o cache
        $transient_key = 'pnd_list_cidade';
        $terms = get_transient( $transient_key );

        // 2. Se o cache existir, retorna imediatamente (Zero Queries)
        if ( false !== $terms ) {
            return $terms;
        }

        // 3. Se não existir, faz a busca
        $args = array(
           'taxonomy'   => 'cidade'
           , 'hide_empty' => true
           , 'orderby'    => 'name'
           , 'order'      => 'ASC'
        );
        $terms = get_terms($args);

        // 4. Salva o resultado no cache por 1 hora
        set_transient( $transient_key, $terms, 1 * HOUR_IN_SECONDS );

        return $terms;
    }
}
