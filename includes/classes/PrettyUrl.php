<?php

class PrettyUrl {
	const CACHE_URL_AMIGAVEL = 'CACHE_PRETTY_URL';
	const INDEX_CACHE = 'index.php?post_type=imovel&tipo_pesquisa_submit=imovel';
	private $delete_cache = false;
	public function __construct( $delete_cache = false ) {
		if ( wp_get_environment_type() === 'development' ) {
			$delete_cache = true;
		}
		$this->delete_cache = $delete_cache;
		if ( $delete_cache === true )	{
			$this->clear();
		}
	}
	private function url_one( $taxonomy_mapping ) {
		$rule = [];
		foreach ($taxonomy_mapping as $slug => $data) {
			$rule[] = [
				'regex' => ( '^' . $slug . '/page/([0-9]{1,3})/?$' )
				, 'rule' => (
					self::INDEX_CACHE
					. '&' . $this->tupla( $data['tax'], $data['slug'] ) . '&' . $this->tupla( 'paged', '$matches[1]' )
				)
				, 'hierarchical' => 'top'
			];
			$rule[] = [
				'regex' => ( '^' . $slug . '/?$' )
				, 'rule' => (
					self::INDEX_CACHE
					. '&' . $this->tupla( $data['tax'], $data['slug'] )
				)
				, 'hierarchical' => 'top'
			];
		}
		return $rule;
	}
	private function url_two( $taxonomy_one, $taxonomy_two ) {
		$rule = [];
		foreach ($taxonomy_one as $slug1 => $data1) {
			foreach ($taxonomy_two as $slug2 => $data2) {
				if ($data1['tax'] != $data2['tax']) {
					$rule[] = [
						'regex' => ( '^' . $slug1 . '/' . $slug2 . '/page/([0-9]{1,3})/?$' )
						, 'rule' => (
							self::INDEX_CACHE
							. '&'. $this->tupla( $data1['tax'], $data1['slug'] )
							. '&' . $this->tupla( $data2['tax'], $data2['slug'] )
							. '&' . $this->tupla( 'paged', '$matches[1]' )
						)
						, 'hierarchical' => 'top'
					];
					$rule[] = [
						'regex' => ( '^' . $slug1 . '/' . $slug2 . '/?$' )
						, 'rule' => (
							self::INDEX_CACHE
							. '&'. $this->tupla( $data1['tax'], $data1['slug'] )
							. '&' . $this->tupla( $data2['tax'], $data2['slug'] )
						)
						, 'hierarchical' => 'top'
					];
				}
			}
		}
		return $rule;
	}
	private function tupla( $parameter, $value ) {
		return ( $parameter . '=' . $value );
	}
	private function url_tree( $taxonomy_one, $taxonomy_two, $taxonomy_tree ) {
		$rule = [];
		foreach ($taxonomy_one as $slug1 => $data1) {
			foreach ($taxonomy_two as $slug2 => $data2) {
				foreach ($taxonomy_tree as $slug3 => $data3) {
					if ($data1['tax'] != $data2['tax'] &&
						$data1['tax'] != $data3['tax'] &&
						$data2['tax'] != $data3['tax']) {
						$rule[] = [
							'regex' => ( '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/page/([0-9]{1,3})/?$' )
							, 'rule' => (
								self::INDEX_CACHE
								. '&' . $this->tupla( $data1['tax'], $data1['slug'] )
								. '&' . $this->tupla( $data2['tax'], $data2['slug'] )
								. '&' . $this->tupla( $data3['tax'], $data3['slug'] )
								. '&' . $this->tupla( 'paged', '$matches[1]' )
							)
							, 'hierarchical' => 'top'
						];
						$rule[] = [
							'regex' => ( '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/?$' )
							, 'rule' => (
								self::INDEX_CACHE
								. '&' . $this->tupla( $data1['tax'], $data1['slug'] )
								. '&' . $this->tupla( $data2['tax'], $data2['slug'] )
								. '&' . $this->tupla( $data3['tax'], $data3['slug'] )
							)
							, 'hierarchical' => 'top'
						];
					}
				}
			}
		}
		return $rule;
	}
	private function url_four( $taxonomy_one, $taxonomy_two, $taxonomy_tree, $taxonomy_four ) {
		$rule = [];

		foreach ( $taxonomy_one as $slug1 => $data1 ) {
			foreach ( $taxonomy_two as $slug2 => $data2 ) {
				foreach ( $taxonomy_tree as $slug3 => $data3 ) {
					foreach ( $taxonomy_four as $slug4 => $data4 ) {
						if (
							$data1['tax'] !== $data2['tax'] &&
							$data1['tax'] !== $data3['tax'] &&
							$data1['tax'] !== $data4['tax'] &&
							$data2['tax'] !== $data3['tax'] &&
							$data2['tax'] !== $data4['tax'] &&
							$data3['tax'] !== $data4['tax']
						) {
							$rule[] = [
								'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/page/([0-9]{1,3})/?$'
								, 'rule' => self::INDEX_CACHE
									. $data1['tax'] . '=' . $data1['slug']
									. '&' . $data2['tax'] . '=' . $data2['slug']
									. '&' . $data3['tax'] . '=' . $data3['slug']
									. '&' . $data4['tax'] . '=' . $data4['slug']
									. '&' . $this->tupla( 'paged', '$matches[1]' )
								, 'hierarchical' => 'top'
							];
							$rule[] = [
								'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/?$',
								'rule'  => self::INDEX_CACHE
									. $data1['tax'] . '=' . $data1['slug']
									. '&' . $data2['tax'] . '=' . $data2['slug']
									. '&' . $data3['tax'] . '=' . $data3['slug']
									. '&' . $data4['tax'] . '=' . $data4['slug'],
								'hierarchical' => 'top'
							];
						}
					}
				}
			}
		}

		return $rule;
	}
	private function register( $rules ) {
		foreach ($rules as $rule) {
			add_rewrite_rule( $rule[ 'regex' ], $rule[ 'rule' ], $rule[ 'hierarchical' ] );
		}
		if ( $this->delete_cache === true ) {
			flush_rewrite_rules( );
		}
	}
	private function cria( $tax_contrato, $tax_tipo_imovel, $tax_cidade ) {
		$rules = array_merge(
			$this->url_one( $tax_contrato )
			, $this->url_one( $tax_tipo_imovel )
			, $this->url_one( $tax_cidade )
			//
			, $this->url_two( $tax_contrato, $tax_tipo_imovel )
			, $this->url_two( $tax_contrato, $tax_cidade )
			, $this->url_two( $tax_tipo_imovel, $tax_contrato )
			, $this->url_two( $tax_tipo_imovel, $tax_cidade )
			, $this->url_two( $tax_cidade, $tax_contrato )
			, $this->url_two( $tax_cidade, $tax_tipo_imovel )
			//
			, $this->url_tree( $tax_contrato, $tax_tipo_imovel, $tax_cidade )
			, $this->url_tree( $tax_contrato, $tax_cidade, $tax_tipo_imovel )
			, $this->url_tree( $tax_tipo_imovel, $tax_contrato, $tax_cidade )
			, $this->url_tree( $tax_tipo_imovel, $tax_cidade, $tax_contrato )
			, $this->url_tree( $tax_cidade, $tax_contrato, $tax_tipo_imovel )
			, $this->url_tree( $tax_cidade, $tax_tipo_imovel, $tax_contrato )
		);
		return $rules;
	}
	public function do() {
		$rules = get_transient( self::CACHE_URL_AMIGAVEL );

		if ( $rules === false ) {
			$tax_contrato = [];
			foreach (lista_contratos() as $contrato) {
				$tax_contrato[ sanitize_title( $contrato->name ) ] = ['tax' => 'contrato', 'slug' => $contrato->slug];
			}
			$tax_tipo_imovel = [];
			foreach (lista_tipo_imovel() as $tipo_imovel) {
				$tax_tipo_imovel[ sanitize_title( $tipo_imovel->name ) ] = ['tax' => 'tipo-imovel', 'slug' => $tipo_imovel->slug];
			}
			$tax_cidade = [];
			foreach (lista_cidade() as $cidade) {
				$tax_cidade[ sanitize_title( $cidade->name ) ] = ['tax' => 'cidade', 'slug' => $cidade->slug];
			}
			$rules = $this->cria( $tax_contrato, $tax_tipo_imovel, $tax_cidade );
			set_transient( self::CACHE_URL_AMIGAVEL, $rules, WEEK_IN_SECONDS );
		}

		$this->register( $rules );
	}
	public function clear() {
		delete_transient( self::CACHE_URL_AMIGAVEL );
	}
}