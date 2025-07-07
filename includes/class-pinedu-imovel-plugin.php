<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.pinedu.com.br
 * @since      1.0.0
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 * @author     Eduardo Pinheiro da Silva <eduardopinhe@gmail.com>
 */
class Pinedu_Imovel_Plugin {
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Pinedu_Imovel_Plugin_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PINEDU_IMOVEL_PLUGIN_VERSION' ) ) {
			$this->version = PINEDU_IMOVEL_PLUGIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'pinedu-imovel-plugin';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Pinedu_Imovel_Plugin_Loader. Orchestrates the hooks of the plugin.
	 * - Pinedu_Imovel_Plugin_i18n. Defines internationalization functionality.
	 * - Pinedu_Imovel_Plugin_Admin. Defines all hooks for the admin area.
	 * - Pinedu_Imovel_Plugin_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( __FILE__ ) . 'classes/Posttypes.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/Taxonomias.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/PaginasIniciais.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/MailConfig.php';
		require_once plugin_dir_path( __FILE__ ) . 'classes/PrettyUrl.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pinedu-imovel-plugin-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pinedu-imovel-plugin-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pinedu-imovel-plugin-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-pinedu-imovel-plugin-public.php';

		$this->loader = new Pinedu_Imovel_Plugin_Loader();

	}
	public function config_wp_mail( $phpmailer ) {
		require_once plugin_dir_path( __FILE__ ) . 'classes/MailConfig.php';
		MailConfig::config_wp_mail( $phpmailer );
	}
	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Pinedu_Imovel_Plugin_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Pinedu_Imovel_Plugin_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Pinedu_Imovel_Plugin_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Pinedu_Imovel_Plugin_Public( $this->get_plugin_name(), $this->get_version() );
		$pretty_url = new PrettyUrl( true );

		$this->loader->add_action( 'phpmailer_init', $plugin_public, 'config_wp_mail', 0 );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles', 5 );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts', 10 );

		$this->loader->add_action( 'init', $plugin_public, 'register_posttypes', 6 );
		$this->loader->add_action( 'init', $plugin_public, 'register_taxonomies', 5 );
		$this->loader->add_action( 'init', $pretty_url, 'do', 10 );

		$this->loader->add_action( 'pre_get_posts', $plugin_public, 'register_search_posttype_imovel' );

		$this->loader->add_filter('template_include', $plugin_public, 'force_single_imovel_template');
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Pinedu_Imovel_Plugin_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
