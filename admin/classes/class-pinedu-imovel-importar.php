<?php

class Pinedu_Imovel_Importar {
    const HOOK_IMPORTACAO = 'PINEDU_EXECUTAR_IMPORTACAO';
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
        $options = get_option('pinedu_imovel_options', []);
        $options['importacao_andamento'] = true;
        update_option('pinedu_imovel_options', $options);
		$this->time_inicio_importacao = new DateTime();
		$this->exclui_agendamento_completo();
	}
	public function post_import( $imoveis_importados ) {
        error_log( '!!! Terminou importação !!!' );
        $this->agendar_importacao( $this->time_inicio_importacao->getTimestamp(), $this->getTempoAtualizacao() );
        error_log( '!!! Agendar prox importacao !!!' );
        $this->atualizar_metadados_importacao();
        error_log( '!!! Enviar metadados da importacao !!!' );
        $this->envia_metadados_importacao();
        /*
         * Vai Importar apenas as imagens de destaque
         * As fotografias serão importadas via HOOK THE_POST
         */
        $importar_fotos = new Pinedu_Imovel_Importa_Foto_Batch( );
        if ( verificar_fotos_demanda() !== true ) {
/*            error_log( '!!! Importar Destaques !!!' );
            $importar_fotos->salva_imagens_destaque();
            error_log( '!!! Importar Fotos !!!' );
            $importar_fotos->salva_imagens_fotos();
*/
        }
        error_log( '!!! Terminou importação !!!' );
	}
    private function envia_metadados_importacao():void {
        $options = get_option('pinedu_imovel_options', []);
        $url_servidor = $options['url_servidor'];
        $ultima_atualizacao = $options['ultima_atualizacao'];
        $tempo_atualizacao = $options['tempo_atualizacao'];
        $imoveis_importados = $options['imoveis_importados'];
        $tempo_utilizado = $options['tempo_utilizado'];
        $token = $options[ 'token' ];
        $proxima_atualizacao = $options['proxima_atualizacao'];


        $endpoint_path = '/wordpress/postImport';
        $fullUrl = trailingslashit( $url_servidor ) . ltrim( $endpoint_path, '/' );
        $args = [
            'dataAtualizacao' => formataData_iso8601( $ultima_atualizacao )
            , 'tempoAtualizacao' =>$tempo_atualizacao
            , 'imoveisImportados' => $imoveis_importados
            , 'tempoUtilizado' => $tempo_utilizado
            , 'token' => $token
            , 'proximaAtualizacao' => formataData_iso8601( $proxima_atualizacao )
            , 'success' => true
        ];
        error_log( $fullUrl );
        error_log( 'Argumentos: ' . print_r( $args, true ) );
        $data = PineduRequest::get( $fullUrl, $args);
        error_log( 'Envia Metadados: ' . print_r( $data, true ) );
    }
    private function atualizar_metadados_importacao():void {
        $options = get_option('pinedu_imovel_options', []);
        $this->time_final_importacao = new DateTime();
        $this->ultima_atualizacao = $this->time_inicio_importacao;
        $this->imoveis_importados = $this->getImoveisImportados();
        $this->tempo_utilizado = $this->getTempoUtilizado();
        $options['tempo_atualizacao'] = $this->getTempoAtualizacao();
        $options['imoveis_importados'] = $this->imovel->getImoveisImportados();
        $options['ultima_atualizacao'] = $this->ultima_atualizacao;
        $options['tempo_utilizado'] = $this->tempo_utilizado;
        $options[ 'token' ] = $this->imovel->getToken();
        $options['proxima_atualizacao'] = $this->parse_timestamp_scheduler( $this->consulta_agendamento() );
        $options['importacao_andamento'] = false;
        update_option('pinedu_imovel_options', $options);
    }
	public function invoca_importacao( ) {
        $url_servidor = $_POST['url_servidor'];
        $forcar = $_POST['forcar'];
		do_action('pinedu_pre_import');
        $data = $this->testar_server();
        if (is_wp_error( $data ) ) {
            error_log('$this->testar_server(): ' . print_r($data, true));
        }
		if ( $data === true ) {
			$this->basicos->invoca_server( $url_servidor, $forcar );
			$this->imovel->invoca_server( $url_servidor, $forcar );;
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
    public function parse_timestamp_scheduler($timestamp_utc, $target_timezone = 'America/Sao_Paulo') {
        if (false === $timestamp_utc || !is_numeric($timestamp_utc)) {
            return false;
        }
        try {
            $tz = new DateTimeZone($target_timezone);
        } catch (Exception $e) {
            $tz = new DateTimeZone('UTC');
        }
        $datetime_obj = new DateTime("@$timestamp_utc", new DateTimeZone('UTC'));
        $datetime_obj->setTimezone($tz);
        return $datetime_obj;
    }
	public function get_agendamento_info() {
		$timestamp = $this->consulta_agendamento();
		if ($timestamp === false) {
			return "Nenhum agendamento ativo";
		}
		return sprintf(
			"Próxima execução em: %s (%s)",
            parse_timestamp_scheduler($timestamp)->format('d/m/Y H:i:s'),
			$this->hook
		);
	}
	private function testar_server() {
		$options = get_option('pinedu_imovel_options', []);
		$url = $options['url_servidor'] ?? '';
		if ( empty( $url ) ) return false;
        error_log('Pinedu_Imovel_Testar_Server::url: ' . print_r( $url, true ) );
		$data = Pinedu_Imovel_Testar_Server::testar_server( $url, false );
        error_log('Pinedu_Imovel_Testar_Server::testar_server: ' . print_r( $data, true ) );
		if ( isset( $data['success'] ) ) {
			return $data[ 'success' ];
		}
		return false;
	}
}
