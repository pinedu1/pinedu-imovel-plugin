<?php

interface PosttypeInterface {
	public function registrar();
	public function desregistrar();
}

class PosttypeFactory {
	public static function criar( $classe ) {
		if ( class_exists( $classe ) ) {
			return new $classe();
		}
		throw new Exception( "Classe de Posttype não encontrada: $classe" );
	}
}
class Imovel implements PosttypeInterface {
	public function registrar( ) {
		$labels = array(
			'name' => 'Imóveis'
			, 'singular_name' => 'Imóvel'
			, 'search_items' => 'Pesquisar Imóveis'
			, 'popular_items' => 'Imóveis mais populares'
			, 'all_items' => 'Todos os Imóveis'
			, 'parent_item' => 'Imóvel Pai'
			, 'parent_item_colon' => 'Imóvel Pai Colon'
			, 'edit_item' => 'Editar imóvel'
			, 'update_item' => 'Atualizar imóvel'
			, 'add_new_item' => 'Adicionar imóvel'
			, 'new_item_name' => 'Novo imóvel'
			, 'add_or_remove_items' => 'Adicionar Remover Imóveis'
			, 'choose_from_most_used' => 'Escolher entre os Imóveis mais usados'
			, 'menu_name' => 'Imóvel'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'exclude_from_search' => false
			, 'description' => 'Imóvel de Corretagem'
			, 'capability_type' => 'post'
			, 'hierarchical' => true
			, 'query_var' => true
			, 'has_archive' => false
			, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'revisions', 'post-formats' )
			, 'show_ui' => true
			, 'show_tagcloud' => true
			, 'show_in_nav_menus' => true
			, 'map_meta_cap' => true
			, 'menu_position' => 20
			, 'publicly_queryable' => true
			, 'rewrite' => array('slug' => 'imoveis')
			, 'menu_icon' => 'dashicons-store'
		);
		register_post_type( 'imovel', $args );
	}
	public function desregistrar() {
		unregister_post_type( 'imovel' );
	}
}
class Empresa implements PosttypeInterface {
	public function registrar( ) {
		$labels = array(
			'name' => 'Empresas'
			, 'singular_name' => 'Empresa'
			, 'search_items' => 'Pesquisar Empresas'
			, 'popular_items' => 'Empresas mais populares'
			, 'all_items' => 'Todos as Empresas'
			, 'parent_item' => 'Empresa Pai'
			, 'parent_item_colon' => 'Empresa Pai Colon'
			, 'edit_item' => 'Editar Empresa'
			, 'update_item' => 'Atualizar Empresa'
			, 'add_new_item' => 'Adicionar Empresa'
			, 'new_item_name' => 'Nova Empresa'
			, 'add_or_remove_items' => 'Adicionar Remover Empresas'
			, 'choose_from_most_used' => 'Escolher entre os Empresas mais usadas'
			, 'menu_name' => 'Empresa'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'exclude_from_search' => false
			, 'description' => 'Imobiliária'
			, 'capability_type' => 'post'
			, 'hierarchical' => true
			, 'query_var' => true
			, 'has_archive' => false
			, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats' )
			, 'show_ui' => true
			, 'show_tagcloud' => true
			, 'show_admin_ui' => true
			, 'show_admin_column' => true
			, 'show_in_nav_menus' => true
			, 'map_meta_cap' => true
			, 'menu_position' => 20
			, 'delete_with_user' => false
			, 'publicly_queryable' => true
			, 'rewrite' => array('slug' => 'empresas')
			, 'menu_icon' => 'dashicons-awards'
		);
		register_post_type( 'empresa', $args );
	}
	public function desregistrar() {
		unregister_post_type( 'empresa' );
	}
}
class Loja implements PosttypeInterface {
	public function registrar( ) {
		$labels = array(
			'name' => 'Lojas'
			, 'singular_name' => 'Loja'
			, 'search_items' => 'Pesquisar Lojas'
			, 'popular_items' => 'Lojas mais populares'
			, 'all_items' => 'Todas as Lojas'
			, 'parent_item' => 'Loja Pai'
			, 'parent_item_colon' => 'Loja Pai Colon'
			, 'edit_item' => 'Editar Loja'
			, 'update_item' => 'Atualizar Loja'
			, 'add_new_item' => 'Adicionar Loja'
			, 'new_item_name' => 'Nova Loja'
			, 'add_or_remove_items' => 'Adicionar/Remover Lojas'
			, 'choose_from_most_used' => 'Escolher entre os Lojas mais usadas'
			, 'menu_name' => 'Lojas'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'exclude_from_search' => false
			, 'description' => 'Filiais da Imobiliária'
			, 'capability_type' => 'post'
			, 'hierarchical' => true
			, 'query_var' => true
			, 'has_archive' => false
			, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats' )
			, 'show_ui' => true
			, 'show_tagcloud' => true
			, 'show_admin_ui' => true
			, 'show_admin_column' => true
			, 'show_in_nav_menus' => true
			, 'map_meta_cap' => true
			, 'menu_position' => 20
			, 'delete_with_user' => false
			, 'publicly_queryable' => true
			, 'rewrite' => array('slug' => 'lojas')
			, 'menu_icon' => 'dashicons-location'
		);
		register_post_type( 'loja', $args );
	}
	public function desregistrar() {
		unregister_post_type( 'loja' );
	}
}
class Corretor implements PosttypeInterface {
	public function registrar( ) {
		$labels = array(
			'name' => 'Corretores'
			, 'singular_name' => 'Corretor'
			, 'search_items' => 'Pesquisar Corretores'
			, 'popular_items' => 'Corretores mais populares'
			, 'all_items' => 'Todos os Corretores'
			, 'parent_item' => 'Corretor Pai'
			, 'parent_item_colon' => 'Corretor Pai Colon'
			, 'edit_item' => 'Editar imóvel'
			, 'update_item' => 'Atualizar imóvel'
			, 'add_new_item' => 'Adicionar imóvel'
			, 'new_item_name' => 'Novo imóvel'
			, 'add_or_remove_items' => 'Adicionar Remover Corretores'
			, 'choose_from_most_used' => 'Escolher entre os Corretores mais usados'
			, 'menu_name' => 'Corretor'
		);

		$args = array(
			'labels' => $labels
			, 'public' => true
			, 'exclude_from_search' => false
			, 'description' => 'Filiais da Imobiliária'
			, 'capability_type' => 'post'
			, 'hierarchical' => true
			, 'query_var' => true
			, 'has_archive' => false
			, 'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats' )
			, 'show_ui' => true
			, 'show_tagcloud' => true
			, 'show_admin_ui' => true
			, 'show_admin_column' => true
			, 'show_in_nav_menus' => true
			, 'map_meta_cap' => true
			, 'menu_position' => 20
			, 'delete_with_user' => false
			, 'publicly_queryable' => true
			, 'rewrite' => array('slug' => 'corretores')
			, 'menu_icon' => 'dashicons-businessman'
		);
		register_post_type( 'corretor', $args );
	}
	public function desregistrar() {
		unregister_post_type( 'corretor' );
	}
}
