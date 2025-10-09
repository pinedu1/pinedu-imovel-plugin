<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.pinedu.com.br
 * @since      1.0.0
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 * @author     Eduardo Pinheiro da Silva <eduardopinhe@gmail.com>
 */
class Pinedu_Imovel_Plugin_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'pinedu-imovel-plugin',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
