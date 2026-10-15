<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.pinedu.com.br
 * @since      1.0.0
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 * @author     Eduardo Pinheiro da Silva
 */
class Pinedu_Imovel_Plugin {
	protected $loader;
	protected $plugin_name;
	protected $version;
	protected $pretty_url;
	/**
	 * Construtor da classe principal do plugin.
	 * Inicializa versao, dependencias, internacionalizacao e hooks administrativos e publicos.
	 * 
	 * @return void
	 */
	public function __construct( ) {
		if ( defined( 'PINEDU_IMOVEL_PLUGIN_VERSION' ) ) {
			$this->version = PINEDU_IMOVEL_PLUGIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'pinedu-imovel-plugin';
		$this->load_dependencies( );
		$this->set_locale( );
		$this->define_admin_hooks( );
		$this->define_public_hooks( );
	}
	/**
	 * Carrega as classes dependentes do plugin e inicializa o Loader de hooks.
	 * 
	 * @return void
	 */
	private function load_dependencies( ) {
		require_once plugin_dir_path( __FILE__ ) . 'classes/Posttypes.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/Taxonomias.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/PaginasIniciais.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/MailConfig.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/PrettyUrl.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pinedu-imovel-plugin-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pinedu-imovel-plugin-i18n.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pinedu-imovel-plugin-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-pinedu-imovel-plugin-public.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'rest/PineduReceiverRest.php';
		$this->loader = new Pinedu_Imovel_Plugin_Loader( );
	}
	/**
	 * Registra os hooks da area administrativa do WordPress.
	 * 
	 * @return void
	 */
	private function define_admin_hooks( ) {
		$plugin_admin = new Pinedu_Imovel_Plugin_Admin( $this->get_plugin_name( ), $this->get_version( ) );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
	}
	/**
	 * Registra todos os hooks publicos, incluindo os filtros e acoes de roteamento e SEO da classe PrettyUrl.
	 * 
	 * @return void
	 */
	private function define_public_hooks( ) {
		$plugin_public = new Pinedu_Imovel_Plugin_Public( $this->get_plugin_name( ), $this->get_version( ) );
		$this->pretty_url = new PrettyUrl( true );
		$this->loader->add_action( 'phpmailer_init', $this, 'config_wp_mail', 0 );
		$this->loader->add_action( 'pinedu_trigger_action', 'PineduReceiverRest', 'pinedu_trigger_action_callback', 10, 1 );
		$this->loader->add_filter( 'wp_mail', $this, 'aplicar_template_email', 10 );
		$this->loader->add_filter( 'pre_get_document_title', $this, 'controlar_titulo_seo', 99 );
		$this->loader->add_action( 'wp_head', $this, 'injetar_metas_seo', 1 );
		$this->loader->add_action( 'template_redirect', $this->pretty_url, 'redirecionar_acentos', 1 );
		$this->loader->add_filter( 'query_vars', $this->pretty_url, 'liberar_query_vars_consulta' );
		$this->loader->add_filter( 'term_link', $this->pretty_url, 'reescrever_term_link', 10, 3 );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles', 5 );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts', 10 );
		$this->loader->add_action( 'init', $plugin_public, 'register_posttypes', 6 );
		$this->loader->add_action( 'init', $plugin_public, 'register_taxonomies', 5 );
		$this->loader->add_action( 'init', $this->pretty_url, 'do', 10 );
		$this->loader->add_action( 'pre_get_posts', $plugin_public, 'register_search_posttype_imovel' );
		$this->loader->add_filter( 'template_include', $plugin_public, 'force_single_imovel_template' );
		$this->loader->add_action( 'rest_api_init', $plugin_public, 'register_rest_endpoint' );
		$this->loader->add_action( 'before_delete_post', $plugin_public, 'excluir_fotos_ao_apagar_post', 0 );
		$this->loader->add_action( 'template_redirect', $plugin_public, 'forcar_argumento_pesquisa_imovel', 10 );
		$this->loader->add_action( 'template_redirect', $plugin_public, 'forcar_404_se_vazio', 20 );
		$this->loader->add_action( 'template_redirect', $this, 'redirecionar_sitemap_nativo', 1 );
		$this->loader->add_action( 'add_meta_boxes', $this, 'adicionar_meta_box_financiamento' );
		$this->loader->add_action( 'save_post', $this, 'salvar_meta_box_financiamento' );
		$this->loader->add_filter( 'wpseo_metadesc', $this, 'override_yoast_seo' );
		$this->loader->add_filter( 'wpseo_opengraph', $this, 'override_yoast_seo' );
		$this->loader->add_filter( 'wpseo_twitter', $this, 'override_yoast_seo' );
		$this->loader->add_filter( 'rank_math/frontend/description', $this, 'override_rankmath_seo' );
		$this->loader->add_filter( 'rank_math/opengraph/facebook', $this, 'override_rankmath_seo' );
		$this->loader->add_filter( 'rank_math/opengraph/twitter', $this, 'override_rankmath_seo' );
		$this->loader->add_filter( 'aioseo_disable_title', $this, 'override_aioseo_seo' );
		$this->loader->add_filter( 'aioseo_disable_description', $this, 'override_aioseo_seo' );
		$this->loader->add_filter( 'robots_txt', $this, 'pinedu_adicionar_sitemap_robots_txt', 10, 2 );
		$this->loader->add_filter( 'wpseo_sitemap_index', $this, 'pinedu_adicionar_sitemap_yoast' );
		$this->loader->add_filter( 'rank_math/sitemap/index', $this, 'pinedu_adicionar_sitemap_rankmath' );
		$this->loader->add_filter( 'wp_sitemaps_enabled', $this, '__return_false' );
		$this->loader->add_filter( 'option_medium_size_w', $plugin_public, 'forcar_largura_medium' );
		$this->loader->add_filter( 'option_medium_size_h', $plugin_public, 'forcar_altura_medium' );
		$this->loader->add_filter( 'option_medium_crop', $plugin_public, 'forcar_crop_medium' );
		$this->loader->add_filter( 'option_large_size_w', $plugin_public, 'forcar_largura_large' );
		$this->loader->add_filter( 'option_large_size_h', $plugin_public, 'forcar_altura_large' );
		$this->loader->add_filter( 'option_large_crop', $plugin_public, 'forcar_crop_large' );
		$this->loader->add_filter( 'wp_editor_set_quality', $plugin_public, 'ajustar_qualidade_imagem' );
	}
	/**
	 * Executa o loader registrando todos os filtros e acoes no WordPress.
	 * 
	 * @return void
	 */
	public function run( ) {
		$this->loader->run( );
	}
	/**
	 * Retorna o identificador unico do plugin.
	 * 
	 * @return string Nome do plugin.
	 */
	public function get_plugin_name( ) {
		return $this->plugin_name;
	}
	/**
	 * Retorna a instancia do gerenciador de hooks do plugin.
	 * 
	 * @return Pinedu_Imovel_Plugin_Loader Instancia do loader.
	 */
	public function get_loader( ) {
		return $this->loader;
	}
	/**
	 * Retorna a versao atual do plugin.
	 * 
	 * @return string Versao do plugin.
	 */
	public function get_version( ) {
		return $this->version;
	}
	/**
	 * Bloqueia a injecao de metas do Yoast SEO quando configurado nas opcoes do plugin.
	 * 
	 * @param mixed $value Valor original do filtro do Yoast.
	 * @return mixed False quando desativado ou valor original.
	 */
	public function override_yoast_seo( $value ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		if ( isset( $options[ 'desativar_yoast' ] ) && $options[ 'desativar_yoast' ] === 'on' ) {
			return false;
		}
		return $value;
	}
	/**
	 * Bloqueia a injecao de metas do Rank Math quando configurado nas opcoes do plugin.
	 * 
	 * @param mixed $value Valor original do filtro do Rank Math.
	 * @return mixed False quando desativado ou valor original.
	 */
	public function override_rankmath_seo( $value ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		if ( isset( $options[ 'desativar_rankmath' ] ) && $options[ 'desativar_rankmath' ] === 'on' ) {
			return false;
		}
		return $value;
	}
	/**
	 * Afirma o desligamento do All in One SEO quando configurado nas opcoes do plugin.
	 * 
	 * @param mixed $value Valor original do filtro do AIOSEO.
	 * @return mixed True quando desativado ou valor original.
	 */
	public function override_aioseo_seo( $value ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		if ( isset( $options[ 'desativar_aioseo' ] ) && $options[ 'desativar_aioseo' ] === 'on' ) {
			return true;
		}
		return $value;
	}
	/**
	 * Configura a instancia do PHPMailer para envio em formato HTML.
	 * 
	 * @param object $phpmailer Instancia nativa do PHPMailer.
	 * @return void
	 */
	public function config_wp_mail( $phpmailer ) {
		$phpmailer->isHTML( true );
		require_once plugin_dir_path( __FILE__ ) . 'classes/MailConfig.php';
		MailConfig::config_wp_mail( $phpmailer );
	}
	/**
	 * Intercepta os disparos de wp_mail aplicando os templates HTML correspondentes ao assunto.
	 * 
	 * @param array $args Argumentos nativos da funcao wp_mail.
	 * @return array Argumentos com o corpo da mensagem formatado em HTML.
	 */
	public function aplicar_template_email( $args ) {
		if ( strpos( $args[ 'subject' ], 'Contato via Site' ) !== false ) {
			$mensagem_original = $args[ 'message' ];
			$nome = preg_match( '/Nome:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$email = preg_match( '/Email:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$telefone = preg_match( '/Telefone:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$texto_mensagem = $mensagem_original;
			if ( preg_match( '/Mensagem:\s*(.*)/is', $mensagem_original, $matches ) ) {
				$texto_mensagem = trim( $matches[ 1 ] );
			}
			$dados_template = [ 'nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'mensagem' => $texto_mensagem ];
			ob_start( );
			get_template_part( 'template-parts/empresa/template-email', 'email', $dados_template );
			$corpo_email_html = ob_get_clean( );
			if ( ! empty( $corpo_email_html ) ) {
				$args[ 'message' ] = $corpo_email_html;
			}
		} elseif ( strpos( $args[ 'subject' ], 'Trabalhe Conosco' ) !== false ) {
			$mensagem_original = $args[ 'message' ];
			$nome = preg_match( '/Nome:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$email = preg_match( '/Email:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$celular = preg_match( '/Telefone:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$cidade = preg_match( '/Cidade:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$cargo = preg_match( '/Cargo:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$creci = preg_match( '/Situação CRECI:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '';
			$texto_mensagem = '';
			if ( preg_match( '/Mensagem:\s*(.*)/is', $mensagem_original, $matches ) ) {
				$texto_mensagem = trim( $matches[ 1 ] );
			}
			$dados_template = [
				'nome' => $nome
				, 'email' => $email
				, 'celular' => $celular
				, 'cidade' => $cidade
				, 'cargo' => $cargo
				, 'creci' => $creci
				, 'mensagem' => $texto_mensagem
			];
			ob_start( );
			get_template_part( 'template-parts/empresa/template-email', 'trabalhe-conosco', $dados_template );
			$corpo_email_html = ob_get_clean( );
			if ( ! empty( $corpo_email_html ) ) {
				$args[ 'message' ] = $corpo_email_html;
			}
		} elseif ( ( strpos( $args[ 'subject' ], 'Solicitação de Visita no Imóvel Ref' ) !== false ) || ( strpos( $args[ 'subject' ], 'Opinião da Visita - Ref' ) !== false ) ) {
			$mensagem_original = $args[ 'message' ];
			$nome = preg_match( '/Nome:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$email = preg_match( '/Email:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$telefone = preg_match( '/Telefone:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$referencia = preg_match( '/Referência:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$corretor = preg_match( '/Corretor Associado:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$texto_mensagem = $mensagem_original;
			if ( preg_match( '/Mensagem:\s*(.*)/is', $mensagem_original, $matches ) ) {
				$texto_mensagem = trim( $matches[ 1 ] );
			}
			$dados_template = [
				'nome' => $nome
				, 'email' => $email
				, 'telefone' => $telefone
				, 'mensagem' => $texto_mensagem
				, 'referencia' => $referencia
				, 'corretor' => $corretor
			];
			ob_start( );
			get_template_part( 'template-parts/empresa/template-email', 'imovel', $dados_template );
			$corpo_email_html = ob_get_clean( );
			if ( ! empty( $corpo_email_html ) ) {
				$args[ 'message' ] = $corpo_email_html;
			}
		} elseif ( strpos( $args[ 'subject' ], 'Interesse no Imóvel Ref' ) !== false ) {
			$mensagem_original = $args[ 'message' ];
			$nome = preg_match( '/Nome:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$email = preg_match( '/Email:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$telefone = preg_match( '/Telefone:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$referencia = preg_match( '/Referência:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$corretor = preg_match( '/Corretor Associado:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[ 1 ] ) : '-';
			$texto_mensagem = $mensagem_original;
			if ( preg_match( '/Mensagem:\s*(.*)/is', $mensagem_original, $matches ) ) {
				$texto_mensagem = trim( $matches[ 1 ] );
			}
			$dados_template = [
				'nome' => $nome
				, 'email' => $email
				, 'telefone' => $telefone
				, 'mensagem' => $texto_mensagem
				, 'referencia' => $referencia
				, 'corretor' => $corretor
			];
			ob_start( );
			get_template_part( 'template-parts/empresa/template-email', 'email', $dados_template );
			$corpo_email_html = ob_get_clean( );
			if ( ! empty( $corpo_email_html ) ) {
				$args[ 'message' ] = $corpo_email_html;
			}
		}
		return $args;
	}
	/**
	 * Configura a internacionalizacao do plugin.
	 * 
	 * @return void
	 */
	private function set_locale( ) {
		$plugin_i18n = new Pinedu_Imovel_Plugin_i18n( );
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}
	/**
	 * Controla a geracao do titulo do documento ( tag title ) delegando as rotas imobiliarias para PrettyUrl.
	 * 
	 * @param string $title Titulo padrao enviado pelo filtro pre_get_document_title.
	 * @return string Titulo otimizado para SEO.
	 */
	public function controlar_titulo_seo( $title ) {
		$gerar_titulo_home = function( ) {
			$desc = get_bloginfo( 'description' );
			if ( empty( $desc ) ) {
				$options = get_option( 'pinedu_imovel_options', [] );
				$cidade_padrao = isset( $options[ 'cidade' ] ) ? $options[ 'cidade' ] : '';
				$cidade = 'Birigui';
				if ( ! empty( $cidade_padrao ) ) {
					$cid = get_term_by( 'slug', $cidade_padrao, 'cidade' );
					if ( $cid && ! is_wp_error( $cid ) ) {
						$cidade = mb_convert_case( $cid->name, MB_CASE_TITLE, 'UTF-8' );
					}
				}
				$desc = "Comprar e Alugar Casas, Apartamentos e Imóveis em {$cidade} e Região";
			}
			return get_bloginfo( 'name' ) . ' | ' . $desc;
		};
		$gerar_titulo_imovel = function( ) {
			global $post;
			$obter_finalidade = function( ) use ( $post ) {
				$finalidades = [];
				$venda = isset( $post->ativarVenda ) ? $post->ativarVenda : get_post_meta( $post->ID, 'ativarVenda', true );
				$locacao = isset( $post->ativarLocacao ) ? $post->ativarLocacao : get_post_meta( $post->ID, 'ativarLocacao', true );
				$lancamento = isset( $post->ativarLancamento ) ? $post->ativarLancamento : get_post_meta( $post->ID, 'ativarLancamento', true );
				if ( '1' == $venda ) $finalidades[] = 'à Venda';
				if ( '1' == $locacao ) $finalidades[] = 'para Alugar (Locação)';
				if ( '1' == $lancamento ) $finalidades[] = 'em Lançamento';
				if ( empty( $finalidades ) ) return '';
				if ( count( $finalidades ) > 1 ) {
					$ultimo = array_pop( $finalidades );
					return ' ' . implode( ', ', $finalidades ) . ' e ' . $ultimo;
				}
				return ' ' . $finalidades[ 0 ];
			};
			$referencia = get_post_meta( $post->ID, 'referencia', true );
			$tipo_bruto = get_post_meta( $post->ID, 'tipoImovelNome', true );
			$tipo = mb_convert_case( $tipo_bruto, MB_CASE_TITLE, 'UTF-8' );
			$bairro_bruto = get_post_meta( $post->ID, 'bairro', true );
			if ( empty( $bairro_bruto ) ) {
				$bairro_bruto = get_post_meta( $post->ID, 'regiao', true );
			}
			$bairro_limpo = PrettyUrl::limpar_prefixo_numerico( $bairro_bruto );
			$bairro = mb_convert_case( trim( $bairro_limpo ), MB_CASE_TITLE, 'UTF-8' );
			$cidade = mb_convert_case( get_post_meta( $post->ID, 'cidade', true ), MB_CASE_TITLE, 'UTF-8' );
			$finalidade_texto = $obter_finalidade( );
			$local_str = ! empty( $bairro ) ? "no {$bairro}, {$cidade}" : "em {$cidade}";
			return "{$tipo}{$finalidade_texto} {$local_str} - Ref: {$referencia} | " . get_bloginfo( 'name' );
		};
		$gerar_titulo_pesquisa = function( ) {
			$termo = get_search_query( );
			if ( empty( $termo ) ) {
				$filtros = [];
				$obter_finalidade = function( $contrato ) {
					if ( '1' == $contrato || 'venda' == $contrato ) return 'Imóveis à Venda (Comprar)';
					if ( '2' == $contrato || 'locacao' == $contrato ) return 'Imóveis para Alugar (Locação)';
					if ( '3' == $contrato || 'lancamento' == $contrato ) return 'Lançamentos de Imóveis';
					return '';
				};
				$obter_nome_termo = function( $slug, $taxonomia ) {
					$term = get_term_by( 'slug', sanitize_text_field( $slug ), $taxonomia );
					$nome = ( $term && ! is_wp_error( $term ) ) ? PrettyUrl::limpar_prefixo_numerico( $term->name ) : sanitize_text_field( $slug );
					return mb_convert_case( $nome, MB_CASE_TITLE, 'UTF-8' );
				};
				$contrato_param = $this->pretty_url ? $this->pretty_url->obter_parametro_consulta( 'contrato' ) : ( $_GET[ 'contrato' ] ?? '' );
				$tipo_param = $this->pretty_url ? $this->pretty_url->obter_parametro_consulta( 'tipo-imovel' ) : ( $_GET[ 'tipo-imovel' ] ?? '' );
				$cidade_param = $this->pretty_url ? $this->pretty_url->obter_parametro_consulta( 'cidade' ) : ( $_GET[ 'cidade' ] ?? '' );
				$regiao_param = $this->pretty_url ? $this->pretty_url->obter_parametro_consulta( 'regiao' ) : ( $_GET[ 'regiao' ] ?? '' );
				if ( ! empty( $contrato_param ) ) {
					$filtros[] = $obter_finalidade( $contrato_param );
				}
				if ( ! empty( $tipo_param ) ) {
					$filtros[] = $obter_nome_termo( $tipo_param, 'tipo-imovel' );
				}
				if ( ! empty( $cidade_param ) ) {
					$filtros[] = 'em ' . $obter_nome_termo( $cidade_param, 'cidade' );
				}
				if ( ! empty( $regiao_param ) ) {
					$filtros[] = '- ' . $obter_nome_termo( $regiao_param, 'regiao' );
				}
				$termo = ! empty( $filtros ) ? implode( ' ', $filtros ) : 'Imóveis em Birigui e Região';
			}
			return ucfirst( $termo ) . ' | ' . get_bloginfo( 'name' );
		};
		$gerar_titulo_post = function( ) {
			return get_the_title( ) . ' | ' . get_bloginfo( 'name' );
		};
		$gerar_titulo_pagina = function( ) {
			global $post;
			$slug = isset( $post->post_name ) ? $post->post_name : '';
			switch ( $slug ) {
				case 'deixe-seu-imovel':
					return 'Deixe seu Imóvel com a Haddad | Avaliação e Administração Segura';
				case 'trabalhe-conosco':
					return 'Trabalhe Conosco | Vagas na ' . get_bloginfo( 'name' );
				case 'contato':
					return 'Fale Conosco | Atendimento ' . get_bloginfo( 'name' );
				default:
					return get_the_title( ) . ' | ' . get_bloginfo( 'name' );
			}
		};
		if ( is_singular( 'imovel' ) ) return $gerar_titulo_imovel( );
		$is_busca_imovel = ( isset( $_GET[ 'tipo_pesquisa_submit' ] ) || ! empty( get_query_var( 'tipo_pesquisa_submit' ) ) || ! empty( get_query_var( 'referencia' ) ) );
		if ( ( is_front_page( ) || is_home( ) ) && ! $is_busca_imovel ) return $gerar_titulo_home( );
		if ( $this->pretty_url instanceof PrettyUrl ) {
			$seo_pretty = $this->pretty_url->obter_dados_seo_ativos( );
			if ( $seo_pretty && ! empty( $seo_pretty[ 'title' ] ) ) {
				return $seo_pretty[ 'title' ];
			}
		}
		$eh_pesquisa = is_search( ) || is_page( 'pesquisa' ) || $is_busca_imovel;
		if ( $eh_pesquisa ) return $gerar_titulo_pesquisa( );
		if ( is_singular( 'post' ) ) return $gerar_titulo_post( );
		if ( is_page( ) ) return $gerar_titulo_pagina( );
		return $title;
	}
	/**
	 * Injeta as tags meta de SEO, OpenGraph, WhatsApp, Twitter Cards e Schema.org JSON-LD no cabecalho ( wp_head ).
	 * 
	 * @return void
	 */
	public function injetar_metas_seo( ) {
		remove_action( 'wp_head', 'rel_canonical' );
		$print_image_tags = function( $img_url, $img_id = null ) {
			if ( empty( $img_url ) ) return;
			echo '<meta property="og:image" content="' . esc_url( $img_url ) . '" />' . "\n";
			echo '<meta property="og:image:secure_url" content="' . esc_url( str_replace( 'http://', 'https://', $img_url ) ) . '" />' . "\n";
			$filetype = wp_check_filetype( $img_url );
			$mime_type = ! empty( $filetype[ 'type' ] ) ? $filetype[ 'type' ] : 'image/jpeg';
			echo '<meta property="og:image:type" content="' . esc_attr( $mime_type ) . '" />' . "\n";
			if ( $img_id ) {
				$image_meta = wp_get_attachment_image_src( $img_id, 'large' );
				if ( $image_meta ) {
					echo '<meta property="og:image:width" content="' . esc_attr( $image_meta[ 1 ] ) . '" />' . "\n";
					echo '<meta property="og:image:height" content="' . esc_attr( $image_meta[ 2 ] ) . '" />' . "\n";
				}
			} else {
				echo '<meta property="og:image:width" content="1200" />' . "\n";
				echo '<meta property="og:image:height" content="630" />' . "\n";
			}
		};
		$renderizar_og_pretty_url = function( $seo_data ) use ( $print_image_tags ) {
			$titulo = $seo_data[ 'title' ];
			$descricao = $seo_data[ 'description' ];
			$canonical = ! empty( $seo_data[ 'canonical' ] ) ? $seo_data[ 'canonical' ] : home_url( strtok( $_SERVER[ 'REQUEST_URI' ], '?' ) );
			$site_name = get_bloginfo( 'name' );
			$imagem = get_template_directory_uri( ) . '/images/logofinal.png';
			$options = get_option( 'pinedu_imovel_options', [] );
			echo "\n";
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
			echo '<meta name="description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";
			echo '<meta property="og:locale" content="pt_BR" />' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $canonical ) . '" />' . "\n";
			echo '<meta property="og:type" content="website" />' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			$print_image_tags( $imagem, null );
			if ( isset( $options[ 'facebook_app_id' ] ) && ! empty( $options[ 'facebook_app_id' ] ) ) {
				echo '<meta property="fb:app_id" content="' . esc_attr( $options[ 'facebook_app_id' ] ) . '" />' . "\n";
			}
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
			echo '<meta name="twitter:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '" />' . "\n";
		};
		$renderizar_og_imovel = function( ) use ( $print_image_tags ) {
			global $post;
			$og_title = wp_get_document_title( );
			if ( empty( $og_title ) ) {
				$og_title = get_the_title( );
			}
			$tipo_imovel_fmt = isset( $post->tipoImovelNome ) ? mb_convert_case( $post->tipoImovelNome, MB_CASE_TITLE, 'UTF-8' ) : '';
			$cidade_fmt = ( isset( $post->cidade ) && ! empty( $post->cidade ) ) ? mb_convert_case( $post->cidade, MB_CASE_TITLE, 'UTF-8' ) : '';
			$regiao_fmt = '';
			if ( isset( $post->regiao ) && ! empty( $post->regiao ) ) {
				$regiao_limpa = PrettyUrl::limpar_prefixo_numerico( $post->regiao );
				$regiao_fmt = mb_convert_case( trim( $regiao_limpa ), MB_CASE_TITLE, 'UTF-8' );
				$excecoes = [ ' Ll ' => ' II ', ' Ii ' => ' II ', ' Sp ' => ' SP ' ];
				$regiao_fmt = trim( strtr( ' ' . $regiao_fmt . ' ', $excecoes ) );
			}
			$texto_base_manual = has_excerpt( ) ? wp_strip_all_tags( get_the_excerpt( ) ) : wp_trim_words( strip_tags( get_the_content( ) ), 40, '' );
			$gerador_descricao_inteligente = function( $texto_manual ) use ( $post, $tipo_imovel_fmt, $regiao_fmt, $cidade_fmt ) {
				$quartos = '';
				$vagas = '';
				$suites = '';
				if ( function_exists( 'get_meta_value' ) ) {
					$q = get_meta_value( $post, 'quartos' );
					if ( empty( $q[ 'valor' ] ) ) $q = get_meta_value( $post, 'dormitorio' );
					$quartos = ! empty( $q[ 'valor' ] ) ? $q[ 'valor' ] : '';
					$s = get_meta_value( $post, 'suites' );
					$suites = ! empty( $s[ 'valor' ] ) ? $s[ 'valor' ] : '';
					$v = get_meta_value( $post, 'garage' );
					$vagas = ! empty( $v[ 'valor' ] ) ? $v[ 'valor' ] : '';
				}
				$valor = 0;
				$prefixo_preco = 'à venda por';
				if ( isset( $post->ativarVenda ) && ( 1 == intval( $post->ativarVenda ) ) && ! empty( $post->vendaValor ) ) {
					$valor = floatval( $post->vendaValor );
				} elseif ( isset( $post->ativarLancamento ) && ( 1 == intval( $post->ativarLancamento ) ) && ! empty( $post->lancamentoValor ) ) {
					$valor = floatval( $post->lancamentoValor );
				} elseif ( isset( $post->ativarLocacao ) && ( 1 == intval( $post->ativarLocacao ) ) && ! empty( $post->locacaoValor ) ) {
					$valor = floatval( $post->locacaoValor );
					$prefixo_preco = 'para alugar (locação) por';
				}
				$preco_fmt = '';
				if ( $valor > 0 ) {
					$preco_fmt = $prefixo_preco . ' R$ ' . number_format( $valor, 2, ',', '.' );
					$preco_fmt = str_replace( ',00', '', $preco_fmt );
				}
				$mecanica = $tipo_imovel_fmt;
				if ( ! empty( $regiao_fmt ) ) $mecanica .= " em {$regiao_fmt}";
				if ( ! empty( $cidade_fmt ) ) $mecanica .= ", {$cidade_fmt}";
				$atributos = [];
				if ( ! empty( $quartos ) ) {
					$attr_q = "{$quartos} quarto(s)";
					if ( ! empty( $suites ) ) $attr_q .= " ({$suites} suíte)";
					$atributos[] = $attr_q;
				}
				if ( ! empty( $vagas ) ) {
					$atributos[] = "{$vagas} vaga(s)";
				}
				if ( ! empty( $atributos ) ) {
					$mecanica .= ' com ' . implode( ' e ', $atributos );
				}
				if ( ! empty( $preco_fmt ) ) {
					$mecanica .= ' ' . $preco_fmt;
				}
				$mecanica .= '.';
				$texto_limpo = trim( str_replace( [ '[&hellip;]', '[...]' ], '', $texto_manual ) );
				$is_contraprodutivo = false;
				if ( mb_strlen( $texto_limpo, 'UTF-8' ) < 30 ) $is_contraprodutivo = true;
				if ( preg_match( '/[A-Z]{15,}/', $texto_limpo ) ) $is_contraprodutivo = true;
				$options = get_option( 'pinedu_imovel_options', [] );
				$forcar_mecanica = ! isset( $options[ 'usar_descricao_do_imovel' ] ) || $is_contraprodutivo;
				if ( $forcar_mecanica ) {
					$descricao_final = $mecanica . ' Confira os detalhes completos e agende sua visita na ' . get_bloginfo( 'name' ) . '.';
				} else {
					$descricao_final = $mecanica . ' ' . $texto_limpo;
				}
				if ( mb_strlen( $descricao_final, 'UTF-8' ) > 155 ) {
					$descricao_final = mb_substr( $descricao_final, 0, 152, 'UTF-8' ) . '...';
				}
				return $descricao_final;
			};
			$og_description = $gerador_descricao_inteligente( $texto_base_manual );
			$og_url = get_permalink( );
			$og_type = 'product.item';
			$og_site_name = get_bloginfo( 'name' );
			$og_image = '';
			$image_id = null;
			if ( has_post_thumbnail( ) ) {
				$og_image = get_the_post_thumbnail_url( $post->ID, 'full' );
				$image_id = get_post_thumbnail_id( $post->ID );
			} else {
				$gallery = get_post_gallery( $post->ID, false );
				if ( ! empty( $gallery[ 'src' ] ) && is_array( $gallery[ 'src' ] ) ) {
					$og_image = $gallery[ 'src' ][ 0 ];
					if ( ! empty( $gallery[ 'ids' ] ) ) {
						$gallery_ids = explode( ',', $gallery[ 'ids' ] );
						$image_id = $gallery_ids[ 0 ];
					}
				}
			}
			echo "\n";
			echo '<link rel="canonical" href="' . esc_url( $og_url ) . '" />' . "\n";
			echo '<meta name="description" content="' . esc_attr( $og_description ) . '" />' . "\n";
			echo '<meta name="author" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
			echo '<meta property="og:locale" content="pt_BR" />' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
			echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
			$print_image_tags( $og_image, $image_id );
			$options = get_option( 'pinedu_imovel_options', [] );
			if ( isset( $options[ 'facebook_app_id' ] ) && ! empty( $options[ 'facebook_app_id' ] ) ) {
				echo '<meta property="fb:app_id" content="' . esc_attr( $options[ 'facebook_app_id' ] ) . '" />' . "\n";
			}
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
			echo '<meta name="twitter:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
			if ( ! empty( $og_image ) ) {
				echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '" />' . "\n";
			}
			echo '<meta property="product:retailer_item_id" content="' . esc_attr( $post->referencia ) . '" />' . "\n";
			if ( isset( $post->novo ) && ! empty( $post->novo ) && '1' == $post->novo ) {
				echo '<meta property="product:condition" content="new" />' . "\n";
			}
			if ( function_exists( 'getEmpresa' ) ) {
				$empresa = getEmpresa( 1 );
				if ( ! empty( $empresa ) ) echo '<meta property="product:brand" content="' . esc_attr( $empresa->codNome ) . '" />' . "\n";
			}
			$preco = '';
			if ( isset( $post->ativarVenda ) && ( 1 == intval( $post->ativarVenda ) ) && ! empty( $post->vendaValor ) ) {
				$preco = $post->vendaValor;
				echo '<meta property="og:availability" content="for_sale" />' . "\n";
			} elseif ( isset( $post->ativarLancamento ) && ( 1 == intval( $post->ativarLancamento ) ) && ! empty( $post->lancamentoValor ) ) {
				echo '<meta property="og:availability" content="for_sale" />' . "\n";
				$preco = $post->lancamentoValor;
			} elseif ( isset( $post->ativarLocacao ) && ( 1 == intval( $post->ativarLocacao ) ) && ! empty( $post->locacaoValor ) ) {
				echo '<meta property="og:availability" content="for_rent" />' . "\n";
				$preco = $post->locacaoValor;
			}
			echo '<meta property="og:property:status" content="Disponível" />' . "\n";
			echo '<meta property="product:availability" content="in stock" />' . "\n";
			$matriz = [
				[ 'tag' => 'og:property:bedrooms', 'label' => 'dormitorio' ]
				, [ 'tag' => 'og:property:bedrooms', 'label' => 'quartos' ]
				, [ 'tag' => 'og:property:bathrooms', 'label' => 'banheiros' ]
				, [ 'tag' => 'og:property:suites', 'label' => 'suites' ]
				, [ 'tag' => 'og:property:parking_spaces', 'label' => 'garage' ]
				, [ 'tag' => 'og:property:furnished', 'label' => 'mobiliado' ]
				, [ 'tag' => 'og:property:pets_allowed', 'label' => 'aceita pets' ]
				, [ 'tag' => 'og:property:floor', 'label' => 'numero do andar' ]
				, [ 'tag' => 'og:property:total_floors', 'label' => 'andares' ]
			];
			if ( function_exists( 'get_meta_value' ) ) {
				foreach ( $matriz as $m ) {
					$area_struct = get_meta_value( $post, $m[ 'label' ] );
					if ( $area_struct && isset( $area_struct[ 'valor' ] ) && ! empty( $area_struct[ 'valor' ] ) ) {
						echo '<meta property="' . $m[ 'tag' ] . '" content="' . esc_attr( $area_struct[ 'valor' ] ) . '" />' . "\n";
					}
				}
				$area_struct = get_meta_value( $post, 'area util' );
				if ( ! $area_struct || empty( $area_struct[ 'valor' ] ) ) {
					$area_struct = get_meta_value( $post, 'area total' );
				}
				if ( $area_struct && isset( $area_struct[ 'valor' ] ) && ! empty( $area_struct[ 'valor' ] ) && is_numeric( $area_struct[ 'valor' ] ) ) {
					echo '<meta property="product:size" content="' . esc_attr( $area_struct[ 'valor' ] ) . ' m²" />' . "\n";
					echo '<meta property="og:product:size" content="' . esc_attr( $area_struct[ 'valor' ] ) . '" />' . "\n";
					if ( floatval( $area_struct[ 'valor' ] ) > 0 && ! empty( $preco ) ) {
						echo '<meta property="og:property:price_per_sqm" content="' . intval( floatval( $preco ) / floatval( $area_struct[ 'valor' ] ) ) . '" />' . "\n";
					}
				}
			}
			if ( ! empty( $preco ) ) {
				echo '<meta property="og:price:amount" content="' . esc_attr( $preco ) . '" />' . "\n";
				echo '<meta property="og:price:currency" content="BRL" />' . "\n";
			}
			$ano = isset( $post->anoConstrucao ) ? $post->anoConstrucao : '';
			if ( ! empty( $ano ) ) echo '<meta property="og:property:year_built" content="' . esc_attr( $ano ) . '" />' . "\n";
			$condominio = isset( $post->valorCondominio ) ? $post->valorCondominio : '';
			if ( ! empty( $condominio ) ) echo '<meta property="og:property:condo_fee" content="' . esc_attr( $condominio ) . '" />' . "\n";
			$iptu = isset( $post->valorIptu ) ? $post->valorIptu : '';
			if ( ! empty( $iptu ) ) echo '<meta property="og:property:property_tax" content="' . esc_attr( $iptu ) . '" />' . "\n";
			if ( ! empty( $cidade_fmt ) ) echo '<meta property="og:locality" content="' . esc_attr( $cidade_fmt ) . '" />' . "\n";
			if ( ! empty( $post->estado ) ) echo '<meta property="og:region" content="' . esc_attr( $post->estado ) . '" />' . "\n";
			if ( ! empty( $post->cep ) && function_exists( 'formata_cep' ) ) echo '<meta property="og:postal-code" content="' . esc_attr( formata_cep( $post->cep ) ) . '" />' . "\n";
			echo '<meta property="og:country-name" content="Brasil" />' . "\n";
			if ( ! empty( $post->latitude ) && ! empty( $post->longitude ) && ( floatval( $post->latitude ) != 0 ) && ( floatval( $post->longitude ) != 0 ) ) {
				echo '<meta property="og:latitude" content="' . esc_attr( $post->latitude ) . '" />' . "\n";
				echo '<meta property="og:longitude" content="' . esc_attr( $post->longitude ) . '" />' . "\n";
			}
			if ( ! empty( $tipo_imovel_fmt ) ) echo '<meta property="product:category" content="' . esc_attr( $tipo_imovel_fmt ) . '" />' . "\n";
			if ( isset( $post->referencia ) ) echo '<meta property="product:item_group_id" content="' . esc_attr( $post->referencia ) . '" />' . "\n";
			if ( ! empty( $preco ) ) {
				$schema_json = [
					'@context' => 'https://schema.org/'
					, '@type' => 'Product'
					, 'name' => $og_title
					, 'image' => [ $og_image ]
					, 'description' => $og_description
					, 'sku' => isset( $post->referencia ) ? $post->referencia : ''
					, 'offers' => [
						'@type' => 'Offer'
						, 'url' => $og_url
						, 'priceCurrency' => 'BRL'
						, 'price' => number_format( floatval( $preco ), 2, '.', '' )
						, 'availability' => 'https://schema.org/InStock'
					]
				];
				if ( function_exists( 'getEmpresa' ) ) {
					$empresa = getEmpresa( 1 );
					if ( ! empty( $empresa ) && isset( $empresa->codNome ) ) {
						$schema_json[ 'brand' ] = [ '@type' => 'Brand', 'name' => $empresa->codNome ];
					}
				}
				echo '<script type="application/ld+json">' . "\n";
				echo wp_json_encode( $schema_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";
				echo '</script>' . "\n";
			}
		};
		$renderizar_og_post_padrao = function( ) use ( $print_image_tags ) {
			global $post;
			$og_title = wp_get_document_title( );
			$og_description = has_excerpt( ) ? wp_strip_all_tags( get_the_excerpt( ) ) : wp_trim_words( strip_tags( get_the_content( ) ), 30, '...' );
			$og_url = get_permalink( );
			$og_site_name = get_bloginfo( 'name' );
			$og_image = has_post_thumbnail( ) ? get_the_post_thumbnail_url( $post->ID, 'full' ) : '';
			$image_id = has_post_thumbnail( ) ? get_post_thumbnail_id( $post->ID ) : null;
			echo "\n";
			echo '<link rel="canonical" href="' . esc_url( $og_url ) . '" />' . "\n";
			echo '<meta name="description" content="' . esc_attr( $og_description ) . '" />' . "\n";
			echo '<meta name="author" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
			echo '<meta property="og:locale" content="pt_BR" />' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
			echo '<meta property="og:type" content="article" />' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
			$print_image_tags( $og_image, $image_id );
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
			echo '<meta name="twitter:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
			if ( ! empty( $og_image ) ) {
				echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '" />' . "\n";
			}
		};
		$renderizar_og_home = function( ) use ( $print_image_tags ) {
			$titulo = wp_get_document_title( );
			$options = get_option( 'pinedu_imovel_options', [] );
			$cidade_padrao = isset( $options[ 'cidade' ] ) ? $options[ 'cidade' ] : '';
			$cidade = 'Birigui';
			if ( ! empty( $cidade_padrao ) ) {
				$cid = get_term_by( 'slug', $cidade_padrao, 'cidade' );
				if ( $cid && ! is_wp_error( $cid ) ) {
					$cidade = mb_convert_case( $cid->name, MB_CASE_TITLE, 'UTF-8' );
				}
			}
			$descricao = "Imobiliária especializada em compra, venda, aluguel e locação de casas, apartamentos, terrenos e imóveis comerciais em {$cidade} e região.";
			$url = home_url( '/' );
			$site_name = get_bloginfo( 'name' );
			$imagem = get_template_directory_uri( ) . '/images/logofinal.png';
			echo "\n";
			echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
			echo '<meta name="description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";
			echo '<meta property="og:locale" content="pt_BR" />' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
			echo '<meta property="og:type" content="website" />' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			$print_image_tags( $imagem, null );
			if ( isset( $options[ 'facebook_app_id' ] ) && ! empty( $options[ 'facebook_app_id' ] ) ) {
				echo '<meta property="fb:app_id" content="' . esc_attr( $options[ 'facebook_app_id' ] ) . '" />' . "\n";
			}
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
			echo '<meta name="twitter:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '" />' . "\n";
		};
		$renderizar_og_pesquisa = function( ) use ( $print_image_tags ) {
			$titulo = wp_get_document_title( );
			$termo_pesquisa = trim( str_replace( ' | ' . get_bloginfo( 'name' ), '', $titulo ) );
			$descricao = "Confira as melhores opções para {$termo_pesquisa}. A " . get_bloginfo( 'name' ) . ' tem o imóvel perfeito para compra ou locação com segurança.';
			$url = home_url( $_SERVER[ 'REQUEST_URI' ] );
			$url_limpa_canonical = strtok( $url, '?' );
			$site_name = get_bloginfo( 'name' );
			$imagem = get_template_directory_uri( ) . '/images/logofinal.png';
			echo "\n";
			echo '<link rel="canonical" href="' . esc_url( $url_limpa_canonical ) . '" />' . "\n";
			echo '<meta name="description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";
			echo '<meta property="og:locale" content="pt_BR" />' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $url_limpa_canonical ) . '" />' . "\n";
			echo '<meta property="og:type" content="website" />' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			$print_image_tags( $imagem, null );
			$options = get_option( 'pinedu_imovel_options', [] );
			if ( isset( $options[ 'facebook_app_id' ] ) && ! empty( $options[ 'facebook_app_id' ] ) ) {
				echo '<meta property="fb:app_id" content="' . esc_attr( $options[ 'facebook_app_id' ] ) . '" />' . "\n";
			}
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
			echo '<meta name="twitter:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '" />' . "\n";
		};
		$renderizar_og_pagina = function( ) use ( $print_image_tags ) {
			global $post;
			$titulo = wp_get_document_title( );
			$site_name = get_bloginfo( 'name' );
			$url = get_permalink( );
			$imagem = get_template_directory_uri( ) . '/images/logofinal.png';
			$slug = isset( $post->post_name ) ? $post->post_name : '';
			if ( $slug === 'contato' ) {
				$descricao = "Fale com a equipe da {$site_name}. Tire suas dúvidas, solicite atendimento e encontre as melhores oportunidades no mercado imobiliário.";
			} elseif ( $slug === 'deixe-seu-imovel' ) {
				$descricao = "Quer vender ou alugar seu imóvel com segurança e rapidez? Anuncie com a {$site_name} e conte com nossa equipe de especialistas.";
			} elseif ( $slug === 'trabalhe-conosco' ) {
				$descricao = "Faça parte da equipe {$site_name}! Confira nossas oportunidades e venha construir uma carreira de sucesso no mercado imobiliário.";
			} else {
				$descricao = has_excerpt( ) ? wp_strip_all_tags( get_the_excerpt( ) ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '...' );
				if ( empty( $descricao ) ) {
					$descricao = "Saiba mais informações sobre a {$site_name}, sua parceira de confiança em negócios imobiliários.";
				}
			}
			echo "\n";
			echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
			echo '<meta name="description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";
			echo '<meta property="og:locale" content="pt_BR" />' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
			echo '<meta property="og:type" content="website" />' . "\n";
			echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			$print_image_tags( $imagem, null );
			$options = get_option( 'pinedu_imovel_options', [] );
			if ( isset( $options[ 'facebook_app_id' ] ) && ! empty( $options[ 'facebook_app_id' ] ) ) {
				echo '<meta property="fb:app_id" content="' . esc_attr( $options[ 'facebook_app_id' ] ) . '" />' . "\n";
			}
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
			echo '<meta name="twitter:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '" />' . "\n";
		};
		if ( is_singular( 'imovel' ) ) return $renderizar_og_imovel( );
		$is_busca_imovel = ( isset( $_GET[ 'tipo_pesquisa_submit' ] ) || ! empty( get_query_var( 'tipo_pesquisa_submit' ) ) || ! empty( get_query_var( 'referencia' ) ) );
		if ( ( is_front_page( ) || is_home( ) ) && ! $is_busca_imovel ) return $renderizar_og_home( );
		if ( $this->pretty_url instanceof PrettyUrl ) {
			$seo_pretty = $this->pretty_url->obter_dados_seo_ativos( );
			if ( ! empty( $seo_pretty ) ) {
				return $renderizar_og_pretty_url( $seo_pretty );
			}
		}
		$eh_pesquisa = is_search( ) || is_page( 'pesquisa' ) || $is_busca_imovel;
		if ( $eh_pesquisa ) return $renderizar_og_pesquisa( );
		if ( is_singular( 'post' ) ) return $renderizar_og_post_padrao( );
		if ( is_page( ) ) return $renderizar_og_pagina( );
	}
	/**
	 * Adiciona o sitemap customizado de imoveis ao indice do Yoast SEO.
	 * 
	 * @param string $sitemap_custom_items XML existente do Yoast.
	 * @return string XML acrescido do sitemap_imoveis.xml.
	 */
	public function pinedu_adicionar_sitemap_yoast( $sitemap_custom_items ) {
		$sitemap_url = home_url( '/sitemap_imoveis.xml' );
		$file_path = ABSPATH . 'sitemap_imoveis.xml';
		$lastmod = file_exists( $file_path ) ? date( 'c', filemtime( $file_path ) ) : date( 'c' );
		$sitemap_custom_items .= "<sitemap>\n";
		$sitemap_custom_items .= "\t<loc>" . esc_url( $sitemap_url ) . "</loc>\n";
		$sitemap_custom_items .= "\t<lastmod>" . $lastmod . "</lastmod>\n";
		$sitemap_custom_items .= "</sitemap>\n";
		return $sitemap_custom_items;
	}
	/**
	 * Adiciona o sitemap customizado de imoveis ao indice do Rank Math SEO.
	 * 
	 * @param string $xml XML existente do Rank Math.
	 * @return string XML acrescido do sitemap_imoveis.xml.
	 */
	public function pinedu_adicionar_sitemap_rankmath( $xml ) {
		$sitemap_url = home_url( '/sitemap_imoveis.xml' );
		$file_path = ABSPATH . 'sitemap_imoveis.xml';
		$lastmod = file_exists( $file_path ) ? date( 'c', filemtime( $file_path ) ) : date( 'c' );
		$xml .= "<sitemap>\n";
		$xml .= "\t<loc>" . esc_url( $sitemap_url ) . "</loc>\n";
		$xml .= "\t<lastmod>" . $lastmod . "</lastmod>\n";
		$xml .= "</sitemap>\n";
		return $xml;
	}
	/**
	 * Injeta a referencia ao sitemap_imoveis.xml no robots.txt virtual do WordPress.
	 * 
	 * @param string $output Conteudo original do robots.txt.
	 * @param string $public Flag de visibilidade publica do blog.
	 * @return string Conteudo do robots.txt atualizado.
	 */
	public function pinedu_adicionar_sitemap_robots_txt( $output, $public ) {
		if ( '0' != $public ) {
			$sitemap_url = home_url( '/sitemap_imoveis.xml' );
			$output .= "\nSitemap: " . esc_url( $sitemap_url ) . "\n";
		}
		return $output;
	}
	/**
	 * Redireciona requisicoes ao wp-sitemap nativo para o sitemap_imoveis.xml customizado.
	 * 
	 * @return void
	 */
	public function redirecionar_sitemap_nativo( ) {
		if ( strpos( $_SERVER[ 'REQUEST_URI' ], 'wp-sitemap' ) !== false ) {
			wp_redirect( home_url( '/sitemap_imoveis.xml' ), 301 );
			exit;
		}
	}
	/**
	 * Helper booleano para desativar recursos via filtro do Loader.
	 * 
	 * @return bool Retorna sempre false.
	 */
	public function __return_false( ) {
		return false;
	}
	/**
	 * Registra a meta box de links de financiamento no post type financeira.
	 * 
	 * @return void
	 */
	public function adicionar_meta_box_financiamento( ) {
		add_meta_box( 'meta_box_links_financeira', 'Links da Instituição', [ $this, 'renderizar_meta_box_financiamento' ], 'financeira', 'normal', 'default' );
	}
	/**
	 * Renderiza os inputs HTML da meta box de instituicao financeira no painel administrativo.
	 * 
	 * @param object $post Objeto WP_Post corrente.
	 * @return void
	 */
	public function renderizar_meta_box_financiamento( $post ) {
		$link = get_post_meta( $post->ID, 'link', true );
		$link_simular = get_post_meta( $post->ID, 'link_simular', true );
		if ( is_array( $link ) ) $link = '';
		if ( is_array( $link_simular ) ) $link_simular = '';
		wp_nonce_field( 'salvar_links_financeira_nonce', 'nonce_links_financeira' );
		echo '<p><strong>Link</strong></p>';
		echo '<input type="url" name="link" value="' . esc_url( (string) $link ) . '" style="width:100%; margin-bottom: 15px;" placeholder="https://exemplo.com" />';
		echo '<p><strong>Link para simular financiamento</strong></p>';
		echo '<input type="url" name="link_simular" value="' . esc_url( (string) $link_simular ) . '" style="width:100%;" placeholder="https://exemplo.com/simular" />';
	}
	/**
	 * Salva os metadados de links da instituicao financeira validando nonce e permissoes de autosave.
	 * 
	 * @param int $post_id ID do post salvo.
	 * @return void
	 */
	public function salvar_meta_box_financiamento( $post_id ) {
		if ( ! isset( $_POST[ 'nonce_links_financeira' ] ) || ! wp_verify_nonce( $_POST[ 'nonce_links_financeira' ], 'salvar_links_financeira_nonce' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( isset( $_POST[ 'post_type' ] ) && $_POST[ 'post_type' ] === 'financeira' ) {
			if ( isset( $_POST[ 'link' ] ) ) {
				update_post_meta( $post_id, 'link', esc_url_raw( $_POST[ 'link' ] ) );
			}
			if ( isset( $_POST[ 'link_simular' ] ) ) {
				update_post_meta( $post_id, 'link_simular', esc_url_raw( $_POST[ 'link_simular' ] ) );
			}
		}
	}
}