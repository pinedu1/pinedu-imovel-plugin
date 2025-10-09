<?php
interface PaginasIniciaisInterface {
	public function registrar();
	public function desregistrar();
}
class PaginasIniciaisFactory {
	public static function criar( $classe ) {
		if ( class_exists( $classe ) ) {
			return new $classe();
		}
		throw new Exception( "Classe de PaginasIniciais não encontrada: $classe" );
	}
}
class PaginasIniciais implements PaginasIniciaisInterface {
	const PAGINAS = array(
		[
			'post_title' => 'Home',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => 'ALTERE ME!',
			'front_page' => true,
			'template' => 'front-page.php' // Especifica template para home
		], [
			'post_title' => 'Sobre-nós',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => 'ALTERE ME!',
			'template' => 'default' // Força modelo padrão
		], [
			'post_title' => 'Contato',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => 'ALTERE ME!',
			'template' => 'default'
		], [
			'post_title' => 'Pesquisa',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => 'ALTERE ME!',
			'posts_page' => true,
			'template' => 'default'
		], [
			'post_title' => 'Deixe seu Imóvel',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => 'ALTERE ME!',
			'template' => 'default'
		], [
			'post_title' => 'Login',
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => 'ALTERE ME!',
			'template' => 'default'
		]
	);

	public function desregistrar() {
		// Não faz nada
	}

	public function registrar() {
		$this->apagar_pagina_exemplo();

		foreach (self::PAGINAS as $pagina) {
			$front_page = $pagina['front_page'] ?? false;
			$posts_page = $pagina['posts_page'] ?? false;
			$template = $pagina['template'] ?? 'default';

			// Remove propriedades extras
			unset($pagina['front_page'], $pagina['posts_page'], $pagina['template']);

			$name = sanitize_title($pagina['post_title']);
			$page = get_page_by_path($name, OBJECT, $pagina['post_type']);

			if ($page) {
				$page_id = $page->ID;
			} else {
				$pagina['post_name'] = $name;
				$page_id = wp_insert_post($pagina);
			}

			// Configurações específicas
			if ($front_page) {
				update_option('page_on_front', $page_id);
				update_option('show_on_front', 'page');
				update_post_meta($page_id, '_wp_page_template', $template);
			} elseif ($posts_page) {
				update_option('page_for_posts', $page_id);
				update_post_meta($page_id, '_wp_page_template', $template);
			} else {
				update_post_meta($page_id, '_wp_page_template', $template);
			}
		}
	}

	private function apagar_pagina_exemplo() {
		$page = get_page_by_path('pagina-exemplo', OBJECT, 'page');
		if ($page) {
			wp_delete_post($page->ID, true);
		}
	}
}