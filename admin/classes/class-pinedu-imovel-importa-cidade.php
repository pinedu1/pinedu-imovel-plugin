<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';
/**
 * Classe responsável pela importação transacional da árvore de cidades, regiões e bairros e listagem cacheada.
 *
 * @author Eduardo Pinheiro da Silva
 */
class Pinedu_Imovel_Importa_Cidade extends Pinedu_Importa_Taxonomia_Base {
	private $cls_regioes;
	/**
	 * Instancia a dependência de importação de regiões.
	 */
	public function __construct( ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-regiao.php';
		$this->cls_regioes = new Pinedu_Imovel_Importa_Regiao( );
		//$this->limpa('cidade');
	}
	/**
	 * Importa toda a hierarquia geográfica (Cidades > Regiões > Bairros) em transação única e com suspensão de cache.
	 *
	 * @param array $cidades Árvore de cidades oriunda do payload JSON do ERP.
	 * @return bool Retorna true ao concluir a importação ou false se a taxonomia não existir.
	 * @throws \Throwable Repassa exceções ocorridas durante a transação após executar rollback.
	 */
	public function importa_cidades( $cidades ) {
		if ( ! taxonomy_exists( 'cidade' ) ) {
			wp_send_json_error( [ 'message' => 'Taxonomia Cidade não existe!.' ] );
			return false;
		}
		if ( empty( $cidades ) || ! is_array( $cidades ) ) {
			return false;
		}
		global $wpdb;
		$ids_afetados = [ ];
		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );
		$wpdb->query( 'START TRANSACTION' );
		try {
			foreach ( $cidades as $cidade ) {
				$key = ( string ) $cidade['id'];
				$nome = sanitize_text_field( $cidade['nome'] );
				$term = $this->salva( $key, $nome, 'cidade' );
				if ( ! is_wp_error( $term ) ) {
					$id_term = isset( $term['term_id'] ) ? ( int ) $term['term_id'] : ( int ) $term;
					if ( $id_term > 0 ) {
						$ids_afetados[] = $id_term;
					}
				}
				$regioes = $cidade['regioes'] ?? [ ];
				if ( ! empty( $regioes ) ) {
					$this->cls_regioes->importa_regioes( $regioes, $key );
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
			clean_term_cache( array_unique( $ids_afetados ), 'cidade' );
		}
		$this->cls_regioes->limpar_cache_termos( );
		delete_transient( 'pnd_list_cidade' );
		return true;
	}
	/**
	 * Lista os termos ativos da taxonomia 'cidade' utilizando cache em transiente de 35 minutos.
	 *
	 * @return array|\WP_Error|false Lista de termos da taxonomia 'cidade' ou false se a taxonomia não existir.
	 */
	public static function list( ) {
		if ( ! taxonomy_exists( 'cidade' ) ) {
			wp_send_json_error( [ 'message' => 'Taxonomia Cidade não existe!.' ] );
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
		$args = array( 'taxonomy' => 'cidade', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' );
		$terms = get_terms( $args );
		// 4. Salva o resultado no cache por 1 hora
		set_transient( $transient_key, $terms, 35 * MINUTE_IN_SECONDS );
		return $terms;
	}
}