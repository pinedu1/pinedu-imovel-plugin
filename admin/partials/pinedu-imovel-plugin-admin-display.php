<?php

	/**
	 * Provide a admin area view for the plugin
	 *
	 * This file is used to markup the admin-facing aspects of the plugin.
	 *
	 * @link       http://example.com
	 * @since      1.0.0
	 *
	 * @package    Pinedu_Imovel
	 * @subpackage Pinedu_Imovel/admin/partials
	 */
?>
<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div id="pinedu-imovel-config" class="wrap">
    <h1>Configurações Pinedu Imóveis</h1>
    <form method="post" action="options.php">
		<?php
			settings_fields('pinedu-imovel-group');
			do_settings_sections('pinedu-imovel');
			submit_button('Salvar Configurações');
		?>
    </form>
    <form method="post" action="">
		<?php wp_nonce_field('pinedu_imovel_actions', 'pinedu_imovel_nonce'); ?>
        <div class="form-table">
            <ul>
                <li>
                    <button id="testar-servidor-btn" class="button secondary" onclick="return testarServidor(event);">Testar Servidor</button>
                </li>
                <li>
                    <button id="importar-btn" class="button secondary" onclick="return importarImoveis(event);">Importar imóveis agora</button>
                </li>
                <li>
                    <button id="importar-forcado-btn" class="button secondary" onclick="return forcarImportarImoveis(event);">Forçar Importação</button>
                </li>
            </ul>
        </div>
    </form>
</div>
