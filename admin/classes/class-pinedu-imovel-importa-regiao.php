<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';
/**
 * Classe responsável pela importação, listagem e cacheamento em transiente da taxonomia 'regiao' e cascata de bairros.
 *
 * @author Eduardo Pinheiro da Silva
 */
class Pinedu_Imovel_Importa_Regiao extends Pinedu_Importa_Taxonomia_Base {
	private $cls_bairros;
	private $ids_afetados = [ ];
	/**
	 * Instancia a dependência de importação de bairros.
	 */
	public function __construct( ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-bairro.php';
		$this->cls_bairros = new Pinedu_Imovel_Importa_Bairro( );
		//$this->limpa('regiao');
	}
	/**
	 * Importa as regiões de uma cidade e delega a importação dos respectivos bairros.
	 *
	 * @param array $regioes Lista de regiões pertencentes à cidade.
	 * @param string $cidade_pai Identificador da cidade pai.
	 * @return bool Retorna true ao concluir a importação das regiões e bairros ou false se a taxonomia não existir.
	 */
	public function importa_regioes( $regioes, $cidade_pai ) {
		if ( ! taxonomy_exists( 'regiao' ) ) {
			wp_send_json_error( [ 'message' => 'Taxonomia Regiao não existe!.' ] );
			return false;
		}
		if ( empty( $regioes ) || ! is_array( $regioes ) ) {
			return false;
		}
		foreach ( $regioes as $regiao ) {
			$key = ( string ) $regiao['id'];
			$nome = sanitize_text_field( $regiao['nome'] );
			$term = $this->salva( $key, $nome, 'regiao' );
			if ( ! is_wp_error( $term ) ) {
				$id_term = isset( $term['term_id'] ) ? ( int ) $term['term_id'] : ( int ) $term;
				if ( $id_term > 0 ) {
					$this->ids_afetados[] = $id_term;
					update_term_meta( $id_term, 'parent_id', $cidade_pai );
				}
			}
			$bairros = $regiao['bairros'] ?? [ ];
			if ( ! empty( $bairros ) ) {
				$this->cls_bairros->importa_bairros( $bairros, $key );
			}
		}
		$cidade_id = str_pad( ( string ) $cidade_pai, 4, '0', STR_PAD_LEFT );
		delete_transient( 'pnd_list_regiao_' . $cidade_id );
		return true;
	}
	/**
	 * Executa a limpeza consolidada do cache de termos das taxonomias 'regiao' e 'bairro'.
	 *
	 * @return void
	 */
	public function limpar_cache_termos( ) {
		if ( ! empty( $this->ids_afetados ) ) {
			clean_term_cache( array_unique( $this->ids_afetados ), 'regiao' );
			$this->ids_afetados = [ ];
		}
		$this->cls_bairros->limpar_cache_termos( );
	}
	/**
	 * Lista as regiões vinculadas a uma cidade específica utilizando cache em transiente de 40 minutos.
	 *
	 * @param string|int $cidade Identificador da cidade pai.
	 * @return array|\WP_Error|false Lista de termos da taxonomia 'regiao' ou false em caso de erro.
	 */
	public static function list( $cidade ) {
		if ( ! taxonomy_exists( 'regiao' ) ) {
			wp_send_json_error( [ 'message' => 'Taxonomia Regiao não existe!.' ] );
			return false;
		}
		// 1. Gera uma chave única para esta cidade específica
		$cidade_id = str_pad( ( string ) $cidade, 4, '0', STR_PAD_LEFT );
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
			, 'meta_query' => [ [ 'key' => 'parent_id', 'value' => $cidade_id, 'compare' => '=' ] ]
		);
		$terms = get_terms( $args );
		// 4. Salva o resultado no cache por 1 hora
		set_transient( $transient_key, $terms, 40 * MINUTE_IN_SECONDS );
		return $terms;
	}
}