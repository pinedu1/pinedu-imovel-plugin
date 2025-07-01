<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.pinedu.com.br
 * @since      1.0.0
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/public
 * @author     Eduardo Pinheiro da Silva <eduardopinhe@gmail.com>
 */
class Pinedu_Imovel_Plugin_Public {
	const PAGINAS_FILTRO_PESQUISA = array( 'front-page.php', 'pesquisa', 'imovel' );
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles( ) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run( ) function
		 * defined in Pinedu_Imovel_Plugin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Pinedu_Imovel_Plugin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pinedu-imovel-plugin-public.css', array( ), $this->version, 'all' );

	}
	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts( ) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run( ) function
		 * defined in Pinedu_Imovel_Plugin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Pinedu_Imovel_Plugin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pinedu-imovel-plugin-public.js', array( 'jquery' ), $this->version, false );

	}
	public function register_taxonomies( ) {
		TaxonomiaFactory::criar( 'Contrato' )->registrar( );
		TaxonomiaFactory::criar( 'TipoImovel' )->registrar( );
		TaxonomiaFactory::criar( 'Cidade' )->registrar( );
		TaxonomiaFactory::criar( 'Regiao' )->registrar( );
		TaxonomiaFactory::criar( 'Bairro' )->registrar( );
		TaxonomiaFactory::criar( 'Captador' )->registrar( );
		TaxonomiaFactory::criar( 'FaixaValor' )->registrar( );
		TaxonomiaFactory::criar( 'TipoDependencia' )->registrar( );
	}
	public function register_posttypes( ) {
		PosttypeFactory::criar( 'Empresa' )->registrar( );
		PosttypeFactory::criar( 'Loja' )->registrar( );
		PosttypeFactory::criar( 'Corretor' )->registrar( );
		PosttypeFactory::criar( 'Imovel' )->registrar( );
	}
	// Função segura para obter parâmetros de qualquer método
	function get_request_param($key, $default = '') {
		return isset($_REQUEST[$key]) ? sanitize_text_field($_REQUEST[$key]) : $default;
	}
	private function ordenar_pesquisa( $query, $contrato, $sort, $direction ) {
		$campoSort = 'vendaValor';
		$direction = $direction ?? 'DESC';
		$type = 'meta_value';
		switch ( $sort ) {
			case 'dataPreco':
				$campoSort = 'vendaDataAtualizacao';
				$type = 'meta_value_date';
				switch ( $contrato ) {
					case 1:
						$campoSort = 'vendaDataAtualizacao';
						break;
					case 2:
						$campoSort = 'locacaoDataAtualizacao';
						break;
					case 3:
						$campoSort = 'lancamentoDataAtualizacao';
						break;
					default:
						$campoSort = 'vendaDataAtualizacao';
						break;
				}
				break;
			case 'valor':
				$type = 'meta_value_num';
				switch ( $contrato ) {
					case 1:
						$campoSort = 'vendaValor';
						break;
					case 2:
						$campoSort = 'locacaoValor';
						break;
					case 3:
						$campoSort = 'lancamentoValor';
						break;
					default:
						$campoSort = 'vendaValor';
						break;
				}
				break;
			case 'referencia':
				$type = 'meta_value_num';
				$campoSort = 'referencia';
				break;
			case 'cidade':
				$type = 'meta_value';
				$campoSort = 'cidade';
				break;
			case 'tipoimovel':
				$type = 'meta_value';
				$campoSort = 'tipoImovelNome';
				break;
			case 'regiao':
				$type = 'meta_value';
				$campoSort = 'tipoImovelNome';
				break;
			case 'bairro':
				$type = 'meta_value';
				$campoSort = 'regiao';
				break;
			case 'finalidade':
				$type = 'meta_value';
				$campoSort = 'finalidadeNome';
				break;
			case 'dormitorio':
				$type = 'meta_value_num';
				$campoSort = 'DOR';
				break;
			case 'suite':
				$type = 'meta_value_num';
				$campoSort = 'SUI';
				break;
			case 'garagem':
				$type = 'meta_value_num';
				$campoSort = 'GAR';
				break;
			case 'iptu':
				$type = 'meta_value_num';
				$campoSort = 'valorIptu';
				break;
			case 'condominio':
				$type = 'meta_value_num';
				$campoSort = 'valorCondominio';
				break;
		}
		$query->set( 'meta_key', $campoSort );
		$query->set( 'orderby', $type );
		$query->set( 'order', $direction );

		return array( $campoSort => $direction, 'type' => $type );
	}
	public function register_search_posttype_imovel( $query ) {
		if ( $query->is_main_query( ) && ( isset( get_queried_object()->post_name ) && ( get_queried_object()->post_name == 'pesquisa' ) ) ) {
			$sort = $_REQUEST[ 'sort' ]?? 'dataPreco';
			$direction = $_REQUEST[ 'ordem' ]?? 'DESC';
			$query->set('post_type', 'imovel');
			$query->set('posts_per_page', intval( $_REQUEST[ 'max' ] ?? 12 ) );
			$meta_query = [ 'key' => 'statusImovel', 'value' => 'D', 'compare' => '=' ];
			$query->set('meta_query', $meta_query);
			$contrato = $this->get_request_param( 'contrato' );
			if ( empty( $contrato ) || ((int)$contrato) <= 0 ) {
				$options = get_option('pinedu_imovel_options');
				$contrato = $options['contrato'];
			}
			if ( !empty( $contrato ) && ((int)$contrato) > 0 ) {
				$t = array(
					'taxonomy' => 'contrato'
					, 'field' => 'slug'
					, 'terms'	=> array((string)$contrato)
				);
				$tax_query[] = $t;
			}
			$this->ordenar_pesquisa( $query, $contrato, $sort, $direction );
			//$query->set( 'orderby', $this->ordenar_pesquisa( $query, $contrato, $sort, $direction ) );
		}
		if ( $query->is_main_query( ) && !empty( $this->get_request_param( 'tipo_pesquisa_submit' ) ) ) {
			if ( $this->get_request_param( 'tipo_pesquisa_submit' ) == 'imovel' ) {
				$sort = $this->get_request_param( 'sort' )?? 'dataPreco';
				$direction = $this->get_request_param( 'ordem' )?? 'DESC';
				$query->set('post_type', 'imovel');
				$query->set('posts_per_page', intval( $this->get_request_param( 'max' ) ?? 12 ) );
				$meta_query = [ 'key' => 'statusImovel', 'value' => 'D', 'compare' => '=' ];
				$query->set('meta_query', $meta_query);
				$tax_query = array(
					'relation' => 'AND',
				);
				$contrato = $this->get_request_param( 'contrato' );
				if ( !empty( $contrato ) && ((int)$contrato) > 0 ) {
					$t = array(
						'taxonomy' => 'contrato'
						, 'field' => 'slug'
						, 'terms'	=> array((string)$contrato)
					);
					$tax_query[] = $t;
				}
				$tipo_imovel = $this->get_request_param( 'tipo-imovel' );
				if ( !empty( $tipo_imovel ) && ((int)$tipo_imovel) > 0 ) {
					$t = array(
						'taxonomy' => 'tipo-imovel'
						, 'field' => 'slug'
						, 'terms'	=> array((string)$tipo_imovel)
					);
					$tax_query[] = $t;
				}
				$cidade = $this->get_request_param( 'cidade' );
				if ( !empty( $cidade ) && ((int)$cidade) > 0 ) {
					$t = array(
						'taxonomy' => 'cidade'
						, 'field' => 'slug'
						, 'terms'	=> array((string)$cidade)
					);
					$tax_query[] = $t;
				}
				$regiao = $this->get_request_param( 'regiao' );
				if ( !empty( $regiao ) && ((int)$regiao) > 0 ) {
					$t = array(
						'taxonomy' => 'regiao'
						, 'field' => 'slug'
						, 'terms'	=> array((string)$regiao)
					);
					$tax_query[] = $t;
				}
				$query->set('tax_query', $tax_query);

				$valor_inicial = $this->get_request_param( 'valor-inicial' );
				if ( ( empty( $valor_inicial ) || ((int)$valor_inicial) <= 0 ) ) {
					$valor_inicial = 0;
				}
				$valor_final = $this->get_request_param( 'valor-final' );
				if (!empty($contrato) && ((int)$contrato) > 0) {
					switch ( $contrato ) {
						case 1:
							$propValor = 'vendaValor';
							break;
						case 2:
							$propValor = 'locacaoValor';
							break;
						case 3:
							$propValor = 'lancamentoValor';
							break;
					}
					if ( ( !empty( $valor_final ) && ((int)$valor_final) > 0 ) ) {
						$meta_query = array(
							'relation' => 'AND'
							, array(
								'key' => $propValor && ((int)$contrato) > 0
								, 'value' => array((float)$valor_inicial, (float)$valor_final)
								, 'type' => 'numeric'
								, 'compare' => 'BETWEEN'
							)
						);
						$query->set('meta_query', $meta_query);
					}
				}
				$ordenacao = $this->ordenar_pesquisa( $query, $contrato, $sort, $direction );
				//$query->set( 'orderby', $ordenacao );
			}
			if ( $this->get_request_param( 'tipo_pesquisa_submit' ) == 'consulta' ) {
				$referencia = $this->get_request_param( 'referencia' );
				$query->set('post_type', 'imovel');
				$query->set('posts_per_page', 1);
				$meta_query = array(
					'relation' => 'AND'
					, array('key' => 'statusImovel', 'value' => 'D', 'compare' => '=')
					, array('key' => 'referencia', 'value' => $referencia, 'compare' => '=')
				);
				$query->set('meta_query', $meta_query);
			}
		}
	}
	public function force_single_imovel_template( $template ) {
		global $wp_query;
		if ( !empty($this->get_request_param('tipo_pesquisa_submit')) && $this->get_request_param('tipo_pesquisa_submit') === 'consulta' ) {
			if ( ($wp_query->get('post_type') === 'imovel') && ($wp_query->post_count === 1) ) {
				$new_template = locate_template(array('single-imovel.php', 'single.php') );
				if ($new_template) {
					return $new_template;
				}
			}
		}
		return $template;
	}
	public function config_wp_mail( $phpmailer ) {
		require_once plugin_dir_path( __FILE__ ) . '../includes/classes/MailConfig.php';
		MailConfig::config_wp_mail( $phpmailer );
	}
}
