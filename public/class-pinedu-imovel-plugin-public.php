<?php
require_once plugin_dir_path( __FILE__ ) . '../rest/PineduReceiverRest.php';

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
			$meta_query = $query->get('meta_query');
			switch ( $sort ) {
				case 'dataPreco':
					switch ( $contrato ) {
						case '1':
							$meta_query[] = [ 'venda_clause' => [ 'key' => 'vendaDataAtualizacao', 'type' => 'DATETIME', 'compare' => 'EXISTS' ] ];
							$query->set('orderby', [ 'venda_clause' => $direction ]);
							break;
						case '2':
							$meta_query[] = [ 'locacao_clause' => [ 'key' => 'locacaoDataAtualizacao', 'type' => 'DATETIME', 'compare' => 'EXISTS' ] ];
							$query->set('orderby', [ 'locacao_clause' => $direction ]);
							break;
						case '3':
							$meta_query[] = [ 'lancamento_clause' => [ 'key' => 'lancamentoDataAtualizacao', 'type' => 'DATETIME', 'compare' => 'EXISTS' ] ];
							$query->set('orderby', [ 'lancamento_clause' => $direction ]);
							break;
						default:
							$query->set('meta_key', 'data');
							$query->set('orderby', 'meta_value');
							$query->set('order', $direction);
							break;
					}
					break;
				case 'valor':
					switch ( $contrato ) {
						case '1':
							$query->set('meta_key', 'vendaValor');
							$query->set('orderby', 'meta_value_num');
							$query->set('order', $direction);
							break;
						case '2':
							$query->set('meta_key', 'locacaoValor');
							$query->set('orderby', 'meta_value_num');
							$query->set('order', $direction);
							break;
						case '3':
							$query->set('meta_key', 'lancamentoValor');
							$query->set('orderby', 'meta_value_num');
							$query->set('order', $direction);
							break;
						default:
							$query->set('meta_key', 'valor');
							$query->set('orderby', 'meta_value_num');
							$query->set('order', $direction);
							break;
					}
					break;
				case 'referencia':
					$query->set('meta_key', 'referencia');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', $direction);
					break;
				case 'cidade':
					$query->set('meta_key', 'cidade');
					$query->set('orderby', 'meta_value');
					$query->set('order', $direction);
					break;
				case 'tipoimovel':
					$query->set('meta_key', 'tipoImovelNome');
					$query->set('orderby', 'meta_value');
					$query->set('order', $direction);
					break;
				case 'regiao':
					$query->set('meta_key', 'regiao');
					$query->set('orderby', 'meta_value');
					$query->set('order', $direction);
					break;
				case 'bairro':
					$query->set('meta_key', 'bairro');
					$query->set('orderby', 'meta_value');
					$query->set('order', $direction);
					break;
				case 'finalidade':
					$query->set('meta_key', 'finalidadeNome');
					$query->set('orderby', 'meta_value');
					$query->set('order', $direction);
					break;
				case 'dormitorio':
					$query->set('meta_key', 'DOR');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', $direction);
					break;
				case 'suite':
					$query->set('meta_key', 'SUI');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', $direction);
					break;
				case 'garagem':
					$query->set('meta_key', 'GAR');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', $direction);
					break;
				case 'iptu':
					$query->set('meta_key', 'valorIptu');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', $direction);
					break;
				case 'condominio':
					$query->set('meta_key', 'valorCondominio');
					$query->set('orderby', 'meta_value_num');
					$query->set('order', $direction);
					break;
			}
			$query->set('meta_query', $meta_query );
		}
		public function register_search_posttype_imovel( $query ) {
			if ( $query->is_main_query( ) ) {
				if ( !empty( $this->get_request_param( 'tipo_pesquisa_submit' ) ) ) {
					if ( $this->get_request_param( 'tipo_pesquisa_submit' ) == 'imovel' ) {
						$sort = $this->get_request_param( 'sort' ) ?? 'dataPreco';
						$direction = $this->get_request_param( 'ordem' ) ?? 'DESC';
						$query->set( 'post_type', 'imovel' );
						$query->set( 'posts_per_page', 12 );
						$max = (int)$this->get_request_param( 'max' );
						if ( $max > 0 ) {
							$query->set( 'posts_per_page', $max );
						}
						$meta_query = [ 'key' => 'statusImovel', 'value' => 'D', 'compare' => '=' ];
						$tax_query = array( 'relation' => 'AND', );
						$contrato = $this->get_request_param( 'contrato' );
						if ( !empty( $contrato ) && ( (int)$contrato ) > 0 ) {
							$t = array( 'taxonomy' => 'contrato', 'field' => 'slug', 'terms' => array( (string)$contrato ) );
							$tax_query[] = $t;
						}
						$tipo_imovel = $this->get_request_param( 'tipo-imovel' );
						if ( !empty( $tipo_imovel ) && ( (int)$tipo_imovel ) > 0 ) {
							$t = array( 'taxonomy' => 'tipo-imovel', 'field' => 'slug', 'terms' => array( (string)$tipo_imovel ) );
							$tax_query[] = $t;
						}
						$cidade = $this->get_request_param( 'cidade' );
						if ( !empty( $cidade ) && ( (int)$cidade ) > 0 ) {
							$t = array( 'taxonomy' => 'cidade', 'field' => 'slug', 'terms' => array( (string)$cidade ) );
							$tax_query[] = $t;
						}
						$regiao = $this->get_request_param( 'regiao' );
						if ( !empty( $regiao ) && ( (int)$regiao ) > 0 ) {
							$t = array( 'taxonomy' => 'regiao', 'field' => 'slug', 'terms' => array( (string)$regiao ) );
							$tax_query[] = $t;
						}
						$valor_minimo = (float)$this->get_request_param( 'valor-inicial' );
						$valor_maximo = (float)$this->get_request_param( 'valor-final' );
						if ($valor_minimo && $valor_maximo) {
							switch ( $contrato ) {
								case '1':
									$meta_query_args = [
										'relation' => 'AND',
										[
											'key'     => 'vendaValor',
											'value'   => [$valor_minimo, $valor_maximo],
											'type'    => 'NUMERIC',
											'compare' => 'BETWEEN',
										]
									];
									break;
								case '2':
									$meta_query_args = [
										'relation' => 'AND',
										[
											'key'     => 'locacaoValor',
											'value'   => [$valor_minimo, $valor_maximo],
											'type'    => 'NUMERIC',
											'compare' => 'BETWEEN',
										]
									];
									break;
								case '3':
									$meta_query_args = [
										'relation' => 'AND',
										[
											'key'     => 'lancamentoValor',
											'value'   => [$valor_minimo, $valor_maximo],
											'type'    => 'NUMERIC',
											'compare' => 'BETWEEN',
										]
									];
									break;
								default:
									$meta_query_args = [
										'relation' => 'OR',
										[
											'key'     => 'vendaValor',
											'value'   => [$valor_minimo, $valor_maximo],
											'type'    => 'NUMERIC',
											'compare' => 'BETWEEN',
										],
										[
											'key'     => 'locacaoValor',
											'value'   => [$valor_minimo, $valor_maximo],
											'type'    => 'NUMERIC',
											'compare' => 'BETWEEN',
										],
										[
											'key'     => 'lancamentoValor',
											'value'   => [$valor_minimo, $valor_maximo],
											'type'    => 'NUMERIC',
											'compare' => 'BETWEEN',
										],
									];
									break;
							}
							$meta_query[] = $meta_query_args;
						}
						$query->set( 'tax_query', $tax_query );
						$query->set( 'meta_query', $meta_query );
						$this->ordenar_pesquisa( $query, $contrato, $sort, $direction );
					} else if ( $this->get_request_param( 'tipo_pesquisa_submit' ) == 'consulta' ) {
						$referencia = $this->get_request_param( 'referencia' );
						$query->set( 'post_type', 'imovel' );
						$query->set( 'posts_per_page', 1 );
						$meta_query = array( 'relation' => 'AND', array( 'key' => 'statusImovel', 'value' => 'D', 'compare' => '=' ), array( 'key' => 'referencia', 'value' => $referencia, 'compare' => '=' ) );
						$query->set( 'meta_query', $meta_query );
					}
				} else {
					/**
					 * Não pode por nada aqui
					 */
				}
			}
		}
		public function force_single_imovel_template( $template ) {
            global $wp_query;

            // 1. Primeiro verifica as novas URLs de referência
            $this->handle_referencia_redirects();

            // 2. Mantém sua lógica original para consultas de pesquisa
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
        /**
         * Encontra imóvel pela referência (similar ao WP_Query)
         */
        private function find_imovel_by_referencia($referencia) {
            $args = array(
                'post_type' => 'imovel',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    array(
                        'key' => 'referencia',
                        'value' => $referencia,
                        'compare' => '='
                    )
                )
            );

            $posts = get_posts($args);
            return !empty($posts) ? $posts[0] : false;
        }        /**
         * Redireciona para o imóvel baseado na referência
         */
        private function redirect_to_imovel_by_referencia($referencia) {
            $imovel_id = $this->find_imovel_by_referencia($referencia);

            if ($imovel_id) {
                wp_redirect(get_permalink($imovel_id), 301);
                exit;
            } else {
                // Se não encontrar o imóvel, redireciona para o archive
                wp_redirect(get_post_type_archive_link('imovel'), 302);
                exit;
            }
        }
        /**
         * Manipula os redirects para URLs com referência
         */
        private function handle_referencia_redirects() {
            // Verifica se já estamos em um redirect para evitar loop
            if (did_action('template_redirect') > 1) {
                return;
            }

            $referencia = $this->get_referencia_from_request();

            if ($referencia) {
                $this->redirect_to_imovel_by_referencia($referencia);
            }
        }
        /**
         * Extrai a referência das diferentes URL patterns
         */
        private function get_referencia_from_request() {
            global $wp;
            $path = trim($wp->request, '/');
            // Pattern 1: /imoveis/15227
            if (preg_match('#^imoveis/(\d+)/?$#', $path, $matches)) {
                return $matches[1];
            }
            // Pattern 4: Query string ?referencia=15227
            if (isset($_GET['referencia']) && is_numeric($_GET['referencia'])) {
                return $_GET['referencia'];
            }
            // Pattern 5: Query string ?ref=15227
            if (isset($_GET['ref']) && is_numeric($_GET['ref'])) {
                return $_GET['ref'];
            }
            return null;
        }

		public function config_wp_mail( $phpmailer ) {
			require_once plugin_dir_path( __FILE__ ) . '../includes/classes/MailConfig.php';
			MailConfig::config_wp_mail( $phpmailer );
		}
        public function register_rest_endpoint() {
            PineduReceiverRest::instala_rest_end_point();
        }
	}
