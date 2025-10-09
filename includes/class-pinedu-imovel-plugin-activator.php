<?php
require_once plugin_dir_path( __FILE__ ) . 'classes/PaginasIniciais.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/MailConfig.php';

/**
 * Fired during plugin activation
 *
 * @link       https://www.pinedu.com.br
 * @since      1.0.0
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 * @author     Eduardo Pinheiro da Silva <eduardopinhe@gmail.com>
 */
class Pinedu_Imovel_Plugin_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		PaginasIniciaisFactory::criar('PaginasIniciais')->registrar();
		if ( defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development' ) {
			MailConfig::config_options_mail();
		}
	}
}
