<?php
require_once plugin_dir_path( __FILE__ ) . 'classes/class-pinedu-imovel-testar-server.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-pinedu-imovel-importar.php';

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
	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pinedu-imovel-plugin-admin.css', array( ), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pinedu-imovel-plugin-admin.js', array( 'jquery' ), $this->version, false );

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
		add_settings_field( 'chave_google_api', 'Chave Google API', [$this, 'exibir_chave_google_api'], 'pinedu-imovel', 'secao_integracao' );
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
					<li><div id="tempo_utilizado"><p><strong>Tempo utilizado:  </strong><?php echo esc_html( $tempo_utilizado ); ?></p></div></li>
					<li><div id="proxima_atualizacao"><p><strong>Próxima atualização: </strong><?php echo esc_html( $proxima_atualizacao->format( 'd/m/Y, H:i:s' ) ); ?></p></div></li>
				</ul>
			<?php endif; ?>
		</div>
		<div id="info"></div>
		<?php
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
		echo '<textarea placeholder="Token" id="token" name="pinedu_imovel_options[token]" rows="6" cols="50">'.esc_textarea( $options['token']??'' ).'</textarea>';
	}
	public function exibir_token_username( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="text" placeholder="Usuário" name="pinedu_imovel_options[token_username]" value="'.esc_attr( $options['token_username']??'' ).'" required>';
	}
	public function exibir_token_password( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="password" placeholder="Senha" name="pinedu_imovel_options[token_password]" value="'.esc_attr( $options['token_password']??'' ).'" required>';
	}


	public function exibir_chave_google_api( ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		echo '<input type="text" placeholder="Chave do Google Api" name="pinedu_imovel_options[chave_google_api]" value="'.esc_attr( $options['chave_google_api']??'' ).'">';
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
