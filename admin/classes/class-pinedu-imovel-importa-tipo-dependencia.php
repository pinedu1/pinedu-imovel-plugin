<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';
/**
 * Classe responsável pela importação, listagem e cacheamento em transiente da taxonomia 'tipo-dependencia'.
 *
 * @author Eduardo Pinheiro da Silva
 */
class Pinedu_Imovel_Importa_Tipo_Dependencia extends Pinedu_Importa_Taxonomia_Base {
	/**
	 * Construtor padrão da classe de importação de tipos de dependência.
	 */
	public function __construct( ) {
		//$this->limpa('faixa_valor');
	}
	/**
	 * Importa os tipos de dependência em transação única no banco de dados com suspensão temporária de recálculo de cache.
	 *
	 * @param array $tipo_dependencia Lista de tipos de dependência oriunda do payload JSON do ERP.
	 * @return bool Retorna true ao concluir a persistência ou false se a taxonomia não existir.
	 * @throws \Throwable Repassa exceções ocorridas durante a transação após executar rollback.
	 */
	public function importa( $tipo_dependencia ) {
		if ( ! taxonomy_exists( 'tipo-dependencia' ) ) {
			wp_send_json_error( [ 'message' => 'Taxonomia Tipo de Dependencia não existe!.' ] );
			return false;
		}
		if ( empty( $tipo_dependencia ) || ! is_array( $tipo_dependencia ) ) {
			return false;
		}
		global $wpdb;
		$ids_afetados = [ ];
		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );
		$wpdb->query( 'START TRANSACTION' );
		try {
			foreach ( $tipo_dependencia as $tipo ) {
				$sigla = $tipo['sigla'];
				$nome = sanitize_text_field( $tipo['nome'] );
				$descricao = $tipo['descricao'];
				$relativo = $tipo['relativo'];
				$ordem = $tipo['ordem'];
				$tipo_campo = $tipo['tipo'];
				$pai = strtolower( trim( $tipo['pai'] ) );
				$term = $this->salva( $sigla, $nome, 'tipo-dependencia' );
				$id_term = isset( $term['term_id'] ) ? ( int ) $term['term_id'] : ( int ) $term;
				if ( $id_term > 0 ) {
					$ids_afetados[] = $id_term;
					update_term_meta( $id_term, 'nome', $nome );
					update_term_meta( $id_term, 'sigla', $sigla );
					update_term_meta( $id_term, 'relativo', $relativo );
					update_term_meta( $id_term, 'descricao', $descricao );
					update_term_meta( $id_term, 'ordem', $ordem );
					update_term_meta( $id_term, 'tipo', $tipo_campo );
					add_term_meta( $id_term, 'tipo-imovel-pai', $pai, true );
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
			clean_term_cache( $ids_afetados, 'tipo-dependencia' );
		}
		// LIMPA O CACHE FINAL após a importação para garantir que os novos dados apareçam
		delete_transient( 'pnd_dep_grouped_v1' );
		return true;
	}
	/**
	 * Trazemos a busca simples para cá. Sem cache neste nível,
	 * pois ele não grava os metadados na serialização.
	 *
	 * @param string $relativo Filtro opcional pelo agrupador relativo da dependência.
	 * @return array|\WP_Error|false Lista de termos encontrados ou false se a taxonomia não existir.
	 */
	public static function list( $relativo = '' ) {
		if ( ! taxonomy_exists( 'tipo-dependencia' ) ) {
			return false;
		}
		$args = [
			'taxonomy' => 'tipo-dependencia'
			, 'hide_empty' => false
			, 'orderby' => 'meta_value'
			, 'meta_key' => 'ordem'
			, 'order' => 'ASC'
			// Deixa o WP fazer a query monstro ( 1 hit nativo )
			, 'update_term_meta_cache' => true
		];
		if ( ! empty( $relativo ) ) {
			$args['meta_query'] = [ [ 'key' => 'relativo', 'value' => $relativo, 'compare' => '=' ] ];
		}
		return get_terms( $args );
	}
	/**
	 * Recolhe a Taxonomia tipo-dependencia, e devolve separada por RELATIVO
	 * O CACHE REAL E DEFINITIVO FICA AQUI.
	 *
	 * @return array Mapa de dependências agrupado pelas chaves CARACTERISTICAS, CONDOMINIO, EDIFICIO e INFRAEXTRUTURA.
	 */
	public static function get_tipo_dependencias( ) {
		$transient_key = 'pnd_dep_grouped_v1';
		// 1. Tenta puxar o array final 100% montado
		$dados_finais = get_transient( $transient_key );
		// Se tem cache, retorna imediatamente. ZERO QUERIES. N(0) ATINGIDO!
		if ( false !== $dados_finais ) {
			return $dados_finais;
		}
		// 2. Não tem cache? Então roda o processamento pesado UMA única vez a cada 50min.
		$tipo_dependencias = self::list( );
		$edificio = [ ];
		$condominio = [ ];
		$caracteristicas = [ ];
		$infraextrutura = [ ];
		if ( ! empty( $tipo_dependencias ) && ! is_wp_error( $tipo_dependencias ) ) {
			// O WP já fez o Eager Load lá no self::list(),
			foreach ( $tipo_dependencias as $tipo_dependencia ) {
				$meta = get_term_meta( $tipo_dependencia->term_id );
				$relativo = $meta['relativo'][0] ?? '';
				$td = [
					'sigla' => $meta['sigla'][0] ?? ''
					, 'nome' => $meta['nome'][0] ?? ''
					, 'descricao' => $meta['descricao'][0] ?? ''
					, 'relativo' => $relativo
					, 'tipo' => $meta['tipo'][0] ?? ''
				];
				switch ( strtoupper( $relativo ) ) {
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
				}
			}
		}
		$dados_finais = [ 'CARACTERISTICAS' => $caracteristicas, 'CONDOMINIO' => $condominio, 'EDIFICIO' => $edificio, 'INFRAEXTRUTURA' => $infraextrutura ];
		// 3. Salva apenas as strings limpas organizadas no Transient
		set_transient( $transient_key, $dados_finais, 60 * MINUTE_IN_SECONDS );
		return $dados_finais;
	}
}