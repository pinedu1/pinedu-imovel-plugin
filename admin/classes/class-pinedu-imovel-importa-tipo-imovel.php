<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';
/**
 * Classe responsável pela importação, listagem e cacheamento em transiente da taxonomia 'tipo-imovel'.
 *
 * @author Eduardo Pinheiro da Silva
 */
class Pinedu_Imovel_Importa_Tipo_Imovel extends Pinedu_Importa_Taxonomia_Base {
	/**
	 * Construtor padrão da classe de importação de tipos de imóvel.
	 */
	public function __construct( ) {
		//$this->limpa('tipo_imovel');
	}
	/**
	 * Importa os tipos de imóvel em transação única no banco de dados com suspensão temporária de recálculo de cache.
	 *
	 * @param array $tipo_imoveis Lista de tipos de imóvel oriunda do payload JSON do ERP.
	 * @return bool Retorna true ao concluir a persistência ou false se a taxonomia não existir.
	 * @throws \Throwable Repassa exceções ocorridas durante a transação após executar rollback.
	 */
	public function importa_tipo_imoveis( $tipo_imoveis ) {
		if ( ! taxonomy_exists( 'tipo-imovel' ) ) {
			wp_send_json_error( [ 'message' => 'Taxonomia Tipo Imóvel não existe!.' ] );
			return false;
		}
		if ( empty( $tipo_imoveis ) || ! is_array( $tipo_imoveis ) ) {
			return false;
		}
		global $wpdb;
		$ids_afetados = [ ];
		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );
		$wpdb->query( 'START TRANSACTION' );
		try {
			foreach ( $tipo_imoveis as $tipo_imovel ) {
				$key = ( string ) $tipo_imovel['id'];
				$nome = sanitize_text_field( $tipo_imovel['nome'] );
				$term = $this->salva( $key, $nome, 'tipo-imovel' );
				$id_term = isset( $term['term_id'] ) ? ( int ) $term['term_id'] : ( int ) $term;
				if ( $id_term > 0 ) {
					$ids_afetados[] = $id_term;
				}
			}
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		} finally {
			wp_suspend_cache_invalidation( false );
			wp_defer_term_counting( false );
		}
		if ( ! empty( $ids_afetados ) ) {
			clean_term_cache( $ids_afetados, 'tipo-imovel' );
		}
		// LIMPA O CACHE FINAL após a importação para garantir que os novos dados apareçam
		delete_transient( 'pnd_list_tipo_imovel' );
		return true;
	}
	/**
	 * Lista os termos ativos da taxonomia 'tipo-imovel' utilizando cache em transiente de 30 minutos.
	 *
	 * @return array|\WP_Error|false Lista de objetos WP_Term ou false se a taxonomia não existir.
	 */
	public static function list( ) {
		if ( ! taxonomy_exists( 'tipo-imovel' ) ) {
			wp_send_json_error( [ 'message' => 'Taxonomia Tipo de Imóvel não existe!.' ] );
			return false;
		}
		// 1. Chave única para o cache
		$transient_key = 'pnd_list_tipo_imovel';
		$terms = get_transient( $transient_key );
		// 2. Se o cache existir, retorna imediatamente (Zero Queries)
		if ( false !== $terms ) {
			return $terms;
		}
		// 3. Se não existir, faz a busca
		$args = array( 'taxonomy' => 'tipo-imovel', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' );
		$terms = get_terms( $args );
		// 4. Salva o resultado no cache por 1 hora (1 * HOUR_IN_SECONDS)
		set_transient( $transient_key, $terms, 30 * MINUTE_IN_SECONDS );
		return $terms;
	}
}