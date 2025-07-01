<?php

class Pinedu_Imovel_Importar {
	const HOOK_IMPORTACAO = 'pinedu_executar_importacao';
	const HOOK_PREIMPORT = 'pinedu_pre_import';
	const HOOK_POSTIMPORT = 'pinedu_post_import';
	//
	private $time_inicio_importacao;
	private $time_final_importacao;
	private $ultima_atualizacao;
	private $tempo_utilizado;
	private $imoveis_importados;
	private $imovel;
	private $basicos;
	public function __construct( ) {
		require_once plugin_dir_path(__FILE__) . 'class-pinedu-imovel-testar-server.php';
		require_once plugin_dir_path(__FILE__) . 'class-pinedu-imovel-importar-basicos.php';
		require_once plugin_dir_path(__FILE__) . 'class-pinedu-imovel-importar-imoveis.php';
		add_action( 'wp_ajax_pinedu_importar', [$this, 'invoca_importacao'] );
		add_action(self::HOOK_PREIMPORT, [$this, 'pre_import']);
		add_action(self::HOOK_POSTIMPORT, [$this, 'post_import'], 10, 1);
		add_action(self::HOOK_IMPORTACAO, [$this, 'invoca_importacao']);

		$this->basicos = new Pinedu_Imovel_Importar_Basicos();
		$this->imovel = new Pinedu_Imovel_Importar_Imoveis();
	}
	private function getUltimaAtualizacao() {
		return $this->ultima_atualizacao;
	}
	private function getTempoUtilizado() {
		$inicio = $this->time_inicio_importacao->getTimestamp();
		$fim = $this->time_final_importacao->getTimestamp();
		$diferenca = $fim - $inicio;

		return gmdate('H:i:s', $diferenca); // Formato horas:minutos:segundos
	}
	private function getImoveisImportados() {
		return $this->imovel->getImoveisImportados();
	}
	private function getTempoAtualizacao() {
		$options = get_option('pinedu_imovel_options', []);
		$tempo_atualizacao = null;
		if ( isset( $options['tempo_atualizacao'] ) ) {
			$tempo_atualizacao = ( int )$options['tempo_atualizacao'];
		}
		if ( !$tempo_atualizacao ) {
			$tempo_atualizacao = 1;
		}
		return $tempo_atualizacao;
	}
	public function pre_import( ) {
		$this->time_inicio_importacao = new DateTime();
		$this->exclui_agendamento_completo();
	}
	public function post_import( $imoveis_importados ) {
		$this->time_final_importacao = new DateTime();
		$this->ultima_atualizacao = $this->time_inicio_importacao;
		$this->imoveis_importados = $this->getImoveisImportados();
		$this->tempo_utilizado = $this->getTempoUtilizado();
		$options = get_option('pinedu_imovel_options', []);

		$options['tempo_atualizacao'] = $this->getTempoAtualizacao();
		$options['imoveis_importados'] = $this->imovel->getImoveisImportados();
		$options['ultima_atualizacao'] = $this->ultima_atualizacao;
		$options['tempo_utilizado'] = $this->tempo_utilizado;
		$options[ 'token' ] = $this->imovel->getToken();

		$data_hora = $this->time_inicio_importacao->getTimestamp();
		$this->agendar_importacao( $data_hora, $this->getTempoAtualizacao() );
		$options['proxima_atualizacao'] = $this->consulta_agendamento();
		update_option('pinedu_imovel_options', $options);
	}
	public function invoca_importacao( ) {
		do_action('pinedu_pre_import');
		if ( $this->testar_server() === true ) {
			$this->basicos->invoca_server();
			$this->imovel->invoca_server();
			do_action('pinedu_post_import', $this->imovel->getImoveisImportados());
			wp_send_json_success([
				'message' => $data['message'] ?? 'Importação dos Imóveis Realizada com sucesso!'
				, 'ultima_atualizacao' => $this->ultima_atualizacao
				, 'tempo_utilizado' => $this->tempo_utilizado
				, 'imoveis_importados' => $this->imoveis_importados
				, 'token' => $this->imovel->getToken()
				, 'proxima_atualizacao' => ( time() + ( $this->getTempoAtualizacao() * 3600 ) )
			]);
		} else {
			wp_send_json_error([
				'message' => $data['message'] ?? 'Servidor remoto respondeu com erro. Verifique novamente o URL para conexão, ou se o servidor está ativo.'
			]);
		}
	}
	/*
	 * AGENDAMENTO / WP-Cron
	 */
	public function agendar_importacao( $data_hora, $horas ) {
		if (!wp_next_scheduled( self::HOOK_IMPORTACAO )) {
			wp_schedule_event( ( ( $data_hora?? time( ) ) + ( 3600 * ( $horas?? 1 ) ) ), 'hourly', self::HOOK_IMPORTACAO );
		}
		add_action( self::HOOK_IMPORTACAO, [$this, 'invoca_importacao']);
	}
	public function exclui_agendamento_completo() {
		wp_clear_scheduled_hook( self::HOOK_IMPORTACAO );
		remove_action(self::HOOK_IMPORTACAO, [$this, 'invoca_importacao']);
		return $this->consulta_agendamento() === false;
	}
	public function consulta_agendamento() {
		return wp_next_scheduled( self::HOOK_IMPORTACAO );
	}
	public function get_agendamento_info() {
		$timestamp = $this->consulta_agendamento();
		if ($timestamp === false) {
			return "Nenhum agendamento ativo";
		}
		return sprintf(
			"Próxima execução em: %s (%s)",
			date_i18n('d/m/Y H:i:s', $timestamp),
			$this->hook
		);
	}
	private function testar_server() {
		$options = get_option('pinedu_imovel_options', []);
		$url = $options[ 'url_servidor' ];
		$data = Pinedu_Imovel_Testar_Server::testar_server( $url, false );
		if ( $data['success'] !== true ) return false;
		return $data['success'];
	}
}
