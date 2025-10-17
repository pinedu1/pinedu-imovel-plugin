<?php
require_once plugin_dir_path( __FILE__ ) . './classes/class-pinedu-imovel-testar-server.php';
require_once plugin_dir_path( __FILE__ ) . './classes/class-pinedu-imovel-importar.php';
require_once plugin_dir_path( __FILE__ ) . './classes/PineduImportarFrontEnd.php';

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.pinedu.com.br
 * @since      1.0.0
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/admin
 * @author     Eduardo Pinheiro da Silva <eduardopinhe@gmail.com>
 */
class Pinedu_Imovel_Plugin_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	private $testar_server;
	private $importar;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action( 'admin_menu', [$this, 'add_admin_menu'] );
		add_action( 'admin_init', [$this, 'define_settings'] );

		$this->testar_server = new Pinedu_Imovel_Testar_Server( );
		$this->importar = new Pinedu_Imovel_Importar( );

        PineduImportarFrontEnd::init();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles( ) {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url(__FILE__) . 'css/plugin-admin.css', array( ), $this->version, 'all' );
        wp_enqueue_style( 'importacao-log', plugin_dir_url(__FILE__) . 'css/importacao.css', array( ), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts( ) {
        wp_enqueue_script( 'importacao-frontend', plugin_dir_url(__FILE__) . 'js/importacao-frontend.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name, plugin_dir_url(__FILE__) . 'js/plugin-admin.js', array( 'jquery' ), $this->version, false );
        $options = get_option( 'pinedu_imovel_options', [] );
        if (!isset($options['imoveis_importar_lote'])) {
            $imoveis_importar_lote = 10;
            $options['imoveis_importar_lote'] = $imoveis_importar_lote;
            update_option( 'pinedu_imovel_options', $options );
        }
        if (!isset($options['imagem_destaque_importar_lote'])) {
            $imoveis_importar_lote = 10;
            $options['imagem_destaque_importar_lote'] = $imoveis_importar_lote;
            update_option( 'pinedu_imovel_options', $options );
        }
        $imoveis_importar_lote = $options['imoveis_importar_lote'];
        $imagem_destaque_importar_lote = $options['imagem_destaque_importar_lote'];

        wp_localize_script('importacao-frontend', 'PineduAjax', [
            'url' => admin_url('admin-ajax.php'),
            'ultimaAtualizacao' => isset($options['ultima_atualizacao'])? formataData_iso8601( $options['ultima_atualizacao'] ): null,
            'max' => $imoveis_importar_lote,
            'maxDestaques' => $imagem_destaque_importar_lote,
        ]);
	}
	public function display_plugin_admin_page( ) {
		include_once plugin_dir_path( __FILE__ ) . 'partials/pinedu-imovel-plugin-admin-display.php';
	}
	public function define_settings( ) {
		// Registrar configurações do tema
		register_setting( 'pinedu-imovel-group', 'pinedu_imovel_options' );
		// Adicionar seções de configuração
		add_settings_section( 'secao_integracao', 'Configurações de Integração', [$this, 'exibir_secao_integracao'], 'pinedu-imovel' );
		add_settings_field( 'url_servidor', 'Url Servidor', [$this, 'exibir_url_servidor'], 'pinedu-imovel', 'secao_integracao' );
		add_settings_field( 'tempo_atualizacao', 'Atualização ( Horas )', [$this, 'exibir_tempo_atualizacao'], 'pinedu-imovel', 'secao_integracao' );
		add_settings_field( 'token_bearer', 'Token', [$this, 'exibir_token_bearer'], 'pinedu-imovel', 'secao_integracao' );
		add_settings_field( 'token_username', 'Usuário', [$this, 'exibir_token_username'], 'pinedu-imovel', 'secao_integracao' );
		add_settings_field( 'token_password', 'Senha', [$this, 'exibir_token_password'], 'pinedu-imovel', 'secao_integracao' );
        add_settings_field( 'token_bearer', 'Token', [$this, 'exibir_token_bearer'], 'pinedu-imovel', 'secao_integracao' );
        add_settings_field( 'token_expiration_date', 'Validade do Token', [$this, 'exibir_token_expiration_date'], 'pinedu-imovel', 'secao_integracao' );
        add_settings_field( 'importacao_andamento', 'Importacao em andamento', [$this, 'exibir_importacao_andamento'], 'pinedu-imovel', 'secao_integracao' );
        // Adicionar seções de comportamento
        add_settings_section( 'secao_comportamento', 'Comportamento', [$this, 'exibir_secao_comportamento'], 'pinedu-imovel' );
        add_settings_field( 'fotos_demanda', 'Carregar fotos sob Demanda', [$this, 'exibir_fotos_demanda'], 'pinedu-imovel', 'secao_comportamento' );
        add_settings_field( 'descricao_do_imovel', 'Usar Descrição do Imóvel', [$this, 'exibir_usar_descricao_do_imovel'], 'pinedu-imovel', 'secao_comportamento' );
        add_settings_field( 'imoveis_importar_lote', 'Lote de imóveis', [$this, 'exibir_imoveis_importar_lote'], 'pinedu-imovel', 'secao_comportamento' );
        add_settings_field( 'imagem_destaque_importar_lote', 'Lote de Destaques', [$this, 'exibir_imagem_destaque_importar_lote'], 'pinedu-imovel', 'secao_comportamento' );
        // Adicionar seções de certificados
        add_settings_section( 'secao_certificados', 'Certificados', [$this, 'exibir_secao_certificados'], 'pinedu-imovel' );
        add_settings_field( 'chave_google_api', 'Chave Google API', [$this, 'exibir_chave_google_api'], 'pinedu-imovel', 'secao_certificados' );
        add_settings_field( 'chave_facebook_id', 'Chave Facebook ID', [$this, 'exibir_chave_facebook_id'], 'pinedu-imovel', 'secao_certificados' );
		// Adicionar seções de configuração
		add_settings_section( 'secao_email', 'Configurações de Email', [$this, 'exibir_secao_email'], 'pinedu-imovel' );
		add_settings_field( 'nome_remetente', 'Nome Remetente', [$this, 'exibir_nome_remetente'], 'pinedu-imovel', 'secao_email' );
		add_settings_field( 'email_remetente', 'Email Remetente', [$this, 'exibir_email_remetente'], 'pinedu-imovel', 'secao_email' );
		add_settings_field( 'servidor_smtp', 'Servidor SMTP', [$this, 'exibir_servidor_smtp'], 'pinedu-imovel', 'secao_email' );
		add_settings_field( 'porta_smtp', 'Porta SMTP', [$this, 'exibir_porta_smtp'], 'pinedu-imovel', 'secao_email' );
		add_settings_field( 'tipo_seguranca', 'Tipo Segurança', [$this, 'exibir_tipo_seguranca'], 'pinedu-imovel', 'secao_email' );
		add_settings_field( 'requer_autenticacao', 'Requer Autenticação', [$this, 'exibir_requer_autenticacao'], 'pinedu-imovel', 'secao_email' );
		add_settings_field( 'usuario', 'Usuário', [$this, 'exibir_nome_usuario'], 'pinedu-imovel', 'secao_email' );
		add_settings_field( 'password', 'Senha', [$this, 'exibir_senha_usuario'], 'pinedu-imovel', 'secao_email' );
		add_settings_field( 'email_contato', 'Email Contato', [$this, 'exibir_email_contato'], 'pinedu-imovel', 'secao_email' );
	}
	public function exibir_secao_integracao( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		$ultima_atualizacao = !empty( $options['ultima_atualizacao'] ) ? $options['ultima_atualizacao']: null;
		$imoveis_importados = $options['imoveis_importados'] ?? 0;
		$tempo_utilizado = $options['tempo_utilizado'] ?? '';
		$proxima_atualizacao = $options['proxima_atualizacao'] ?? null;
		?>
		<div id="status-importacao">
			<?php if ( $ultima_atualizacao instanceof DateTime ):
				$ultima_atualizacao->setTimezone( new DateTimeZone( wp_timezone_string() ) );
				if ( $proxima_atualizacao && is_numeric($proxima_atualizacao) && $proxima_atualizacao > 0 && $proxima_atualizacao <= PHP_INT_MAX) {
					$data = new DateTime("@{$proxima_atualizacao}");
					$data->setTimezone( new DateTimeZone( wp_timezone_string() ) ) ;
					$proxima_atualizacao = $data;
				}
                ?>
				<ul>
					<li><div id="ultima_atualizacao"><p><strong>Última atualização: </strong><?php echo esc_html( $ultima_atualizacao->format( 'd/m/Y, H:i:s' ) ); ?></p></div></li>
					<li><div id="imoveis_importados"><p><strong>Imóveis importados: </strong><?php echo esc_html( $imoveis_importados ); ?></p></div></li>
					<?php if (isset($tempo_utilizado)): ?><li><div id="tempo_utilizado"><p><strong>Tempo utilizado:  </strong><?php echo esc_html( $tempo_utilizado ); ?></p></div></li><?php endif; ?>
                    <?php if (isset($proxima_atualizacao)): ?><li><div id="proxima_atualizacao"><p><strong>Próxima atualização: </strong><?php echo esc_html( $proxima_atualizacao->format( 'd/m/Y, H:i:s' ) ); ?></p></div></li><?php endif; ?>
				</ul>
			<?php endif; ?>
		</div>
		<div id="info" class="informacao"></div>
		<?php
	}
    public function exibir_secao_comportamento( ) {
        if (false && is_development_mode()) {
            $options = get_option( 'pinedu_imovel_options', [] );
            error_log( "Opções: " . print_r( $options, true ) );
        }
    }
    public function exibir_secao_certificados( ) {
    }

	public function exibir_url_servidor( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="text" placeholder="Url do Servidor de Imóveis" id="url_servidor" name="pinedu_imovel_options[url_servidor]" value="'.esc_attr( $options['url_servidor']??'' ).'" required>';
	}
	public function exibir_tempo_atualizacao( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="number" min="1" max="24" name="pinedu_imovel_options[tempo_atualizacao]" value="'.( $options['tempo_atualizacao']??1 ).'">';
	}
	public function exibir_token_bearer( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<textarea placeholder="Token" id="token" name="pinedu_imovel_options[token]" rows="4" cols="50">'.esc_textarea( $options['token']??'' ).'</textarea>';
	}
	public function exibir_token_username( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="text" placeholder="Usuário" name="pinedu_imovel_options[token_username]" value="'.esc_attr( $options['token_username']??'' ).'" required>';
	}
	public function exibir_token_password( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="password" placeholder="Senha" name="pinedu_imovel_options[token_password]" value="'.esc_attr( $options['token_password']??'' ).'" required>';
	}

    public function exibir_token_expiration_date( ) {
        $options = get_option( 'pinedu_imovel_options', [] );
        try {
            $token_date = $options['token_expiration_date'] ?? '1980-01-01T00:00:00-300';
            $date_time = new DateTime($token_date);
            $field_value = $date_time->format('Y-m-d\TH:i');
        } catch (Exception $e) {
            $field_value = '';
        }
        echo '<input type="datetime-local" name="pinedu_imovel_options[token_expiration_date]" value="'. esc_attr($field_value) . '" placeholder="Data de expiração do token">';
    }
    public function exibir_importacao_andamento( ) {
        $options = get_option( 'pinedu_imovel_options', [] );

        $importacao_andamento = false;
        if ( isset( $options['importacao_andamento'] ) ) {
            $importacao_andamento = $options['importacao_andamento'];
        }
        $checked = checked( $importacao_andamento, true, false );
        echo '<input type="checkbox" name="pinedu_imovel_options[importacao_andamento]" ' . $checked . ' />';
    }
    public function exibir_fotos_demanda( ) {
        $options = get_option( 'pinedu_imovel_options', [] );

        $fotos_demanda = false;
        if ( isset( $options['fotos_demanda'] ) ) {
            $fotos_demanda = $options['fotos_demanda'];
        }
        $checked = checked( $fotos_demanda, 'on', false );
        echo '<input type="checkbox" name="pinedu_imovel_options[fotos_demanda]" ' . $checked . ' />';
    }
    public function exibir_usar_descricao_do_imovel( ) {
        $options = get_option( 'pinedu_imovel_options', [] );

        $usar_descricao_do_imovel = false;
        if ( isset( $options['usar_descricao_do_imovel'] ) ) {
            $usar_descricao_do_imovel = $options['usar_descricao_do_imovel'];
        }
        $checked = checked( $usar_descricao_do_imovel, 'on', false );
        echo '<input type="checkbox" name="pinedu_imovel_options[usar_descricao_do_imovel]" ' . $checked . ' />';
        echo '<p class="description">Ou recriar a descrição com base nos dados so imóvel (IA - Somente nos metatags para AdSense)</p>';
    }
    public function exibir_imoveis_importar_lote( ) {
        $options = get_option( 'pinedu_imovel_options', [] );
        echo '<input type="number" min="1" max="50" name="pinedu_imovel_options[imoveis_importar_lote]" value="'.( $options['imoveis_importar_lote']??10 ).'">';
        echo '<p class="description">Tamanho por ROUND de importação (Hospedagem Compartilhada)</p>';
    }
    public function exibir_imagem_destaque_importar_lote( ) {
        $options = get_option( 'pinedu_imovel_options', [] );
        echo '<input type="number" min="1" max="50" name="pinedu_imovel_options[imagem_destaque_importar_lote]" value="'.( $options['imagem_destaque_importar_lote']??10 ).'">';
        echo '<p class="description">Tamanho por ROUND de importação (Hospedagem Compartilhada)</p>';
    }
	public function exibir_chave_google_api( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="text" placeholder="Chave do Google Api" name="pinedu_imovel_options[chave_google_api]" value="'.esc_attr( $options['chave_google_api']??'' ).'">';
	}
    public function exibir_chave_facebook_id( ) {
        $options = get_option( 'pinedu_imovel_options', [] );
        echo '<input type="text" placeholder="Chave Facebook ID" name="pinedu_imovel_options[facebook_app_id]" value="'.esc_attr( $options['facebook_app_id']??'' ).'">';
    }
	public function exibir_secao_email( ) {  }
	public function exibir_nome_remetente( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="text" placeholder="Nome Remetente" name="pinedu_imovel_options[nome_remetente]" value="'.esc_attr( $options['nome_remetente']??'' ).'" required>';
	}
	public function exibir_email_remetente( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="email" placeholder="Email Remetente" name="pinedu_imovel_options[email_remetente]" value="'.esc_attr( $options['email_remetente']??'' ).'" required>';
	}
	public function exibir_email_contato( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="email" placeholder="Email Contato" name="pinedu_imovel_options[email_contato]" value="'.esc_attr( $options['email_contato']??'' ).'" required>';
	}
	public function exibir_nome_usuario( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="text" placeholder="Usuário" name="pinedu_imovel_options[usuario]" value="'.esc_attr( $options['usuario']??'' ).'" required>';
	}
	public function exibir_senha_usuario( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="password" placeholder="Senha" name="pinedu_imovel_options[password]" value="'.esc_attr( $options['password']??'' ).'" required>';
	}
	public function exibir_servidor_smtp( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="text" placeholder="Servidor SMTP" name="pinedu_imovel_options[servidor_smtp]" value="'.esc_attr( $options['servidor_smtp']??'' ).'" required>';
	}
	public function exibir_porta_smtp( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="number" min="0" max="65535" name="pinedu_imovel_options[porta_smtp]" value="'.( $options['porta_smtp']??587 ).'" required>';
	}
	public function exibir_tipo_seguranca( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		$tipo_seguranca = $options['tipo_seguranca'] ?? ''; // estava pegando 'porta_smtp' por engano
		$opcoes = array( '' => 'Nenhum', 'ssl' => 'SSL', 'tls' => 'TLS' );
		echo '<select name="pinedu_imovel_options[tipo_seguranca]">';
		foreach ( $opcoes as $valor => $rotulo ) {
			$selecionado = selected( $tipo_seguranca, $valor, false );
			echo '<option value="' . esc_attr( $valor ) . '" ' . $selecionado . '>' . esc_html( $rotulo ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">Selecione o tipo de criptografia para conexão SMTP</p>';
	}
	public function exibir_requer_autenticacao( ) {
		$options = get_option( 'pinedu_imovel_options', [] );

		$requer_autenticacao = false;
        if ( isset( $options['requer_autenticacao'] ) ) {
			$requer_autenticacao = $options['requer_autenticacao'];
		}
		$checked = checked( $requer_autenticacao, 'on', false );
		echo '<input type="checkbox" name="pinedu_imovel_options[requer_autenticacao]" ' . $checked . ' />';
	}
	public function add_admin_menu( ) {
		// Adiciona o item de menu primeiro
		add_menu_page( 
			'Configurações Pinedu Imóveis',
			'Pinedu Imóveis',
			'manage_options',
			'pinedu-imoveis',
			[$this, 'display_plugin_admin_page']
		 );
	}
}
