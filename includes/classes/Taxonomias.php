<?php
//namespace PineduImovel\Init\Taxonomias;

interface TaxonomiaInterface {
	public function registrar();
	public function desregistrar();
}

class TaxonomiaFactory {
	public static function criar( $classe ) {
		if ( class_exists( $classe ) ) {
			return new $classe();
		}
		throw new Exception( "Classe de taxonomia não encontrada: $classe" );
	}
}

class Contrato implements TaxonomiaInterface {
	public function registrar() {

		$labels = array(
			'name' => 'Contratos'
			, 'singular_name' => 'Contrato'
			, 'search_items' => 'Pesquisar Contratos'
			, 'popular_items' => 'Contratos Populares'
			, 'all_items' => 'Todos os Contratos'
			, 'parent_item' => 'Contrato Pai'
			, 'parent_item_colon' => 'Contrato Pai:'
			, 'edit_item' => 'Editar Contrato'
			, 'update_item' => 'Atualizar Contrato'
			, 'add_new_item' => 'Adicionar Novo Contrato'
			, 'new_item_name' => 'Novo Nome de Contrato'
			, 'add_or_remove_items' => 'Adicionar ou remover Contratos'
			, 'choose_from_most_used' => 'Escolher entre os contratos mais usados'
			, 'menu_name' => 'Contratos'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'show_ui' => true
			, 'show_in_nav_menus' => false
			, 'show_admin_column' => true
			, 'show_in_tag_cloud' => true
			, 'hierarchical' => true
			, 'show_tagcloud' => false
			, 'query_var' => true
			, 'pll_translatable' => true
			, 'show_in_rest' => true
			, 'rewrite' => array( 'slug' => 'contrato', 'hierarchical' => true, 'with_front' => false )
			, 'menu_icon' => 'dashicons-sticky'
		);
		register_taxonomy( 'contrato', array( 'imovel' ), $args );
	}
	public function desregistrar() {
		unregister_taxonomy_for_object_type( 'contrato', 'imovel' );
	}
}

class TipoImovel implements TaxonomiaInterface {
	public function registrar() {
		$labels = array(
			'name' => 'Tipos de Imóveis'
			, 'singular_name' => 'Tipo de Imóvel'
			, 'search_items' => 'Buscar Tipos de Imóveis'
			, 'popular_items' => 'Tipos de Imóveis Populares'
			, 'all_items' => 'Todos os Tipos de Imóveis'
			, 'parent_item' => 'Tipo de Imóvel Pai'
			, 'parent_item_colon' => 'Tipo de Imóvel Pai:'
			, 'edit_item' => 'Editar Tipo de Imóvel'
			, 'update_item' => 'Atualizar Tipo de Imóvel'
			, 'add_new_item' => 'Adicionar Novo Tipo de Imóvel'
			, 'new_item_name' => 'Novo Nome de Tipo de Imóvel'
			, 'add_or_remove_items' => 'Adicionar ou remover Tipos'
			, 'choose_from_most_used' => 'Tipos mais utilizados'
			, 'menu_name' => 'Tipos de Imóveis'
			, 'not_found' => 'Nenhum tipo encontrado'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'show_in_nav_menus' => false
			, 'show_admin_column' => true
			, 'hierarchical' => true
			, 'show_tagcloud' => true
			, 'query_var' => true
			, 'pll_translatable' => true
			, 'show_ui' => true
			, 'rewrite' => array( 'slug' => 'tipo-imovel', 'hierarchical' => true, 'with_front' => false )
		);
		register_taxonomy( 'tipo-imovel', array( 'imovel' ), $args );
	}
	public function desregistrar() {
		unregister_taxonomy_for_object_type( 'tipo-imovel', 'imovel' );
	}
}

class Cidade implements TaxonomiaInterface {
	public function registrar() {
		$labels = array(
			'name' => 'Cidades'
			, 'singular_name' => 'Cidade'
			, 'search_items' => 'Pesquisar Cidades'
			, 'popular_items' => 'Cidades Populares'
			, 'all_items' => 'Todas as Cidades'
			, 'parent_item' => 'Cidade Pai'
			, 'parent_item_colon' => 'Cidade Pai:'
			, 'edit_item' => 'Editar Cidade'
			, 'update_item' => 'Atualizar Cidade'
			, 'add_new_item' => 'Adicionar Nova Cidade'
			, 'new_item_name' => 'Novo Nome de Cidade'
			, 'add_or_remove_items' => 'Adicionar ou remover Cidades'
			, 'choose_from_most_used' => 'Cidades mais utilizadas'
			, 'menu_name' => 'Cidades'
			, 'not_found' => 'Nenhuma cidade encontrada'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'show_ui' => true
			, 'show_in_nav_menus' => false
			, 'show_admin_column' => true
			, 'hierarchical' => true
			, 'show_tagcloud' => true
			, 'show_in_tag_cloud' => true
			, 'query_var' => true
			, 'pll_translatable' => true
			, 'rewrite' => array( 'slug' => 'cidade', 'hierarchical' => true, 'with_front' => false )
		);
		register_taxonomy( 'cidade', array( 'imovel' ), $args );
	}
	public function desregistrar() {
		unregister_taxonomy_for_object_type( 'cidade', 'imovel' );
	}
}

class Regiao implements TaxonomiaInterface {
	public function registrar() {
		$labels = array(
			'name' => 'Regiões'
			, 'singular_name' => 'Região'
			, 'search_items' => 'Pesquisar Regiões'
			, 'popular_items' => 'Regiões Populares'
			, 'all_items' => 'Todas as Regiões'
			, 'parent_item' => 'Região Pai'
			, 'parent_item_colon' => 'Região Pai:'
			, 'edit_item' => 'Editar Região'
			, 'update_item' => 'Atualizar Região'
			, 'add_new_item' => 'Adicionar Nova Região'
			, 'new_item_name' => 'Novo Nome de Região'
			, 'add_or_remove_items' => 'Adicionar ou remover Regiões'
			, 'choose_from_most_used' => 'Regiões mais utilizadas'
			, 'menu_name' => 'Regiões'
			, 'not_found' => 'Nenhuma regiao encontrada'
		);

		$args = [
			'labels' => $labels
			, 'public' => true
			, 'show_ui' => true
			, 'show_in_nav_menus' => false
			, 'show_admin_column' => true
			, 'hierarchical' => true
			, 'show_tagcloud' => true
			, 'show_in_tag_cloud' => true
			, 'query_var' => true
			, 'pll_translatable' => true
			, 'rewrite' => array( 'slug' => 'regiao', 'hierarchical' => true, 'with_front' => false )
		];
		register_taxonomy( 'regiao', array( 'imovel' ), $args );
	}
	public function desregistrar() {
		unregister_taxonomy_for_object_type( 'regiao', 'imovel' );
	}
}

class Bairro implements TaxonomiaInterface {
	public function registrar() {
		$labels = array(
			'name' => 'Bairros'
			, 'singular_name' => 'Bairro'
			, 'search_items' => 'Pesquisar Bairros'
			, 'popular_items' => 'Bairros Populares'
			, 'all_items' => 'Todas as Bairros'
			, 'parent_item' => 'Bairro Pai'
			, 'parent_item_colon' => 'Bairro Pai:'
			, 'edit_item' => 'Editar Bairro'
			, 'update_item' => 'Atualizar Bairro'
			, 'add_new_item' => 'Adicionar Nova Bairro'
			, 'new_item_name' => 'Novo Nome de Bairro'
			, 'add_or_remove_items' => 'Adicionar ou remover Bairros'
			, 'choose_from_most_used' => 'Bairros mais utilizadas'
			, 'menu_name' => 'Bairros'
			, 'not_found' => 'Nenhuma bairro encontrada'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'show_ui' => true
			, 'show_in_nav_menus' => false
			, 'show_admin_column' => true
			, 'hierarchical' => true
			, 'show_tagcloud' => true
			, 'show_in_tag_cloud' => true
			, 'query_var' => true
			, 'pll_translatable' => true
			, 'rewrite' => array( 'slug' => 'bairro' )
		);
		register_taxonomy( 'bairro', array( 'imovel' ), $args );
	}
	public function desregistrar() {
		unregister_taxonomy_for_object_type( 'bairro', 'imovel' );
	}
}
class Captador implements TaxonomiaInterface {
	public function registrar() {
		$labels = array(
			'name' => 'Captadores'
			, 'singular_name' => 'Captador'
			, 'search_items' => 'Pesquisar Captadores'
			, 'popular_items' => 'Captadores Populares'
			, 'all_items' => 'Todas as Captadores'
			, 'parent_item' => 'Captador Pai'
			, 'parent_item_colon' => 'Captador Pai:'
			, 'edit_item' => 'Editar Captador'
			, 'update_item' => 'Atualizar Captador'
			, 'add_new_item' => 'Adicionar Nova Captador'
			, 'new_item_name' => 'Novo Nome de Captador'
			, 'add_or_remove_items' => 'Adicionar ou remover Captadores'
			, 'choose_from_most_used' => 'Captadores mais utilizadas'
			, 'menu_name' => 'Captadores'
			, 'not_found' => 'Nenhuma captador encontrada'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'show_ui' => true
			, 'show_in_nav_menus' => false
			, 'show_admin_column' => true
			, 'hierarchical' => true
			, 'show_tagcloud' => true
			, 'show_in_tag_cloud' => true
			, 'query_var' => true
			, 'pll_translatable' => true
			, 'rewrite' => array( 'slug' => 'captador' )
		);
		register_taxonomy( 'captador', array( 'imovel' ), $args );
	}
	public function desregistrar() {
		unregister_taxonomy_for_object_type( 'captador', 'imovel' );
	}
}
class FaixaValor implements TaxonomiaInterface {
	public function registrar() {
		$labels = array(
			'name' => 'Faixas de Valores'
			, 'singular_name' => 'Faixa de Valor'
			, 'search_items' => 'Pesquisar Faixas de Valores'
			, 'popular_items' => 'Faixas de Valores Populares'
			, 'all_items' => 'Todas as Faixas de Valores'
			, 'parent_item' => 'Faixa de Valor Pai'
			, 'parent_item_colon' => 'Faixa de Valor Pai:'
			, 'edit_item' => 'Editar Faixa de Valor'
			, 'update_item' => 'Atualizar Faixa de Valor'
			, 'add_new_item' => 'Adicionar Nova Faixa de Valor'
			, 'new_item_name' => 'Nova Faixa de Valor'
			, 'add_or_remove_items' => 'Adicionar ou remover Faixas de Valores'
			, 'choose_from_most_used' => 'Faixas de Valores mais utilizadas'
			, 'menu_name' => 'Faixas de Valores'
			, 'not_found' => 'Nenhuma faixa de valor encontrada'
		);
		$args = array(
			'labels' => $labels
			, 'public' => false
			, 'show_ui' => true
			, 'show_in_nav_menus' => false
			, 'show_admin_column' => true
			, 'hierarchical' => true
			, 'show_tagcloud' => true
			, 'show_in_tag_cloud' => true
			, 'query_var' => true
			, 'pll_translatable' => true
			, 'rewrite' => array( 'slug' => 'faixa-valor' )
		);
		register_taxonomy( 'faixa-valor', array( ), $args );
	}
	public function desregistrar() {
		unregister_taxonomy_for_object_type( 'faixa-valor' );
	}
}
class TipoDependencia implements TaxonomiaInterface {
	public function registrar() {
		$labels = array(
			'name' => 'Tipo de Dependencias'
			, 'singular_name' => 'Tipo de Dependencia'
			, 'search_items' => 'Pesquisar Tipo de Dependencias'
			, 'popular_items' => 'Tipo de Dependencias Populares'
			, 'all_items' => 'Todas as Tipo de Dependencias'
			, 'parent_item' => 'Tipo de Dependencia Pai'
			, 'parent_item_colon' => 'Tipo de Dependencia Pai:'
			, 'edit_item' => 'Editar Tipo de Dependencia'
			, 'update_item' => 'Atualizar Tipo de Dependencia'
			, 'add_new_item' => 'Adicionar Novo Tipo de Dependencia'
			, 'new_item_name' => 'Novo Tipo de Dependencia'
			, 'add_or_remove_items' => 'Adicionar ou remover Tipo de Dependencias'
			, 'choose_from_most_used' => 'Tipo de Dependencias mais utilizadas'
			, 'menu_name' => 'Tipo de Dependencias'
			, 'not_found' => 'Nenhum Tipo de Dependencia encontrado'
		);
		$args = array(
			'labels' => $labels
			, 'public' => false
			, 'show_ui' => true
			, 'show_in_nav_menus' => false
			, 'show_admin_column' => true
			, 'hierarchical' => true
			, 'show_tagcloud' => true
			, 'show_in_tag_cloud' => true
			, 'query_var' => true
			, 'pll_translatable' => true
			, 'rewrite' => array( 'slug' => 'Tipo de Dependencia' )
		);
		register_taxonomy( 'tipo-dependencia', array( ), $args );
	}
	public function desregistrar() {
		unregister_taxonomy_for_object_type( 'tipo-dependencia' );
	}
}
