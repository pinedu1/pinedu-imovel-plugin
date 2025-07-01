<?php
require_once plugin_dir_path( __FILE__ ) . 'classes/Posttypes.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/Taxonomias.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/PaginasIniciais.php';

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.pinedu.com.br
 * @since      1.0.0
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 * @author     Eduardo Pinheiro da Silva <eduardopinhe@gmail.com>
 */
class Pinedu_Imovel_Plugin_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		PosttypeFactory::criar('Empresa')->desregistrar();
		PosttypeFactory::criar('Loja')->desregistrar();
		PosttypeFactory::criar('Corretor')->desregistrar();
		PosttypeFactory::criar('Imovel')->desregistrar();
		//
		TaxonomiaFactory::criar('Contrato')->desregistrar();
		TaxonomiaFactory::criar('TipoImovel')->desregistrar();
		TaxonomiaFactory::criar('Cidade')->desregistrar();
		TaxonomiaFactory::criar('Regiao')->desregistrar();
		TaxonomiaFactory::criar('Bairro')->desregistrar();
		//
		PaginasIniciaisFactory::criar('PaginasIniciais')->desregistrar();
	}
}
