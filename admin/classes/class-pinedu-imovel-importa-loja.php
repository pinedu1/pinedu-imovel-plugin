<?php
require_once plugin_dir_path(__FILE__) . 'class-pinedu-foto-util.php';
class Pinedu_Imovel_Importa_Loja extends Pinedu_Foto_Util {
	const TERMOS = array(
		'enderecos', 'emails', 'telefones'
	);
	const PROPRIEDADES_ENDERECO = array( 'uf', 'cidade', 'bairro', 'tipo', 'numero', 'complemento', 'cep', 'logradouro' );
	const PROPRIEDADES = array(
		'id' => 'id'
		, 'codigo' => 'codigo'
		, 'nome' => 'nome'
		, 'carteira_id' => 'carteira_id'
		, 'carteiraNome' => 'carteiraNome'
		, 'ativo' => 'ativo'
		, 'creci' => 'creci'
		, 'sistema' => 'sistema'
		, 'atuacao' => 'atuacao'
		, 'latitude' => 'latitude'
		, 'longitude' => 'longitude'
		, 'observacoes' => 'observacoes'
		, 'dateCreated' => 'dateCreated'
		, 'lastUpdated' => 'lastUpdated'
		, 'nomeUsuCriador' => 'nomeUsuCriador'
		, 'foto' => 'foto'
		, 'fotoPath' => 'fotoPath'
		, 'fotoIdMask' => 'fotoIdMask'
		, 'fotoNome' => 'fotoNome'
		, 'p_id' => 'pessoa_id'
		, 'p_codigo' => 'pessoa_codigo'
		, 'p_nome' => 'pessoa_nome'
		, 'p_codNome' => 'codNome'
		, 'p_sexo' => 'sexo'
		, 'p_tipoPessoa' => 'tipoPessoa'
		, 'p_estadoCivil' => 'estadoCivil'
		, 'p_ativo' => 'ativo'
		, 'p_sistema' => 'sistema'
		, 'p_nascimento' => 'nascimento'
		, 'p_cpf' => 'cpf'
		, 'p_rg' => 'rg'
		, 'p_rgOrgaoEmissor' => 'rgOrgaoEmissor'
		, 'p_rgDataExpedicao' => 'rgDataExpedicao'
		, 'p_profissao' => 'profissao'
		, 'p_nacionalidade' => 'nacionalidade'
		, 'p_inscricao' => 'inscricao'
		, 'p_bloquearEnvioEmail' => 'bloquearEnvioEmail'
		, 'p_nomeConjuge' => 'nomeConjuge'
		, 'p_cpfConjuge' => 'cpfConjuge'
		, 'p_rgConjuge' => 'rgConjuge'
		, 'p_nascimentoConjuge' => 'nascimentoConjuge'
		, 'p_observacoes' => 'observacoes'
		, 'p_foto' => 'foto'
		, 'p_emailPadrao_id' => 'emailPadrao_id'
		, 'p_telefonePadrao_id' => 'telefonePadrao_id'
		, 'p_enderecoPadrao_id' => 'enderecoPadrao_id'
		, 'p_dateCreated' => 'dateCreated'
		, 'p_lastUpdated' => 'lastUpdated'
		, 'p_nomeUsuCriador' => 'nomeUsuCriador'
		, 'p_site' => 'site'
		, 'p_faceBook' => 'faceBook'
		, 'p_twitter' => 'twitter'
		, 'p_googlePlus' => 'googlePlus'
		, 'p_skype' => 'skype'
		, 'p_instagram' => 'instagram'
		, 'logo' => 'logo'
	);
	private $post_id;
	public function __construct(  ) {
	}
	public function importar( $lojas ) {
		if ( ! post_type_exists( 'loja' ) ) {
			wp_send_json_error( ['message' => 'Post Type Loja não existe!.'] );
			return;
		}
		foreach( $lojas as $loja ) {
			$this->importa( $loja );
		}
	}
	private function importa( $loja ) {
		$args = array(
			'meta_key' => 'codigo'
			, 'meta_value' => $loja['codigo']
			, 'post_type' => 'loja'
			, 'post_status' => 'any'
			, 'numberposts' => 1
		);
		$post = get_posts( $args );
		if ( empty( $post ) ) {
			$this->post_id = $this->salvar( $loja );
		} else {
			$post = $post[0];
			$this->post_id = $post->ID;
			$this->post_id = $this->atualizar( $this->post_id, $loja );
		}
		wp_reset_postdata( );
	}
	public function salvar( $loja ) {
		$obs = $loja['observacoes']??$loja['nome'];
		$post_data = array(
			'post_title' => sanitize_title( $loja['nome'] )
			, 'post_content' => $obs??''
			, 'post_status' => 'publish'
			, 'post_type' => 'loja'
			, 'post_date' => current_time( 'mysql' )
		);
		$this->post_id = wp_insert_post( $post_data);
		if ( is_wp_error( $this->post_id ) ) {
			wp_die( $this->post_id->get_error_messages( ) );
			return false;
		}
		$this->salvar_metadados( $loja );

		$importa_fotos = new Pinedu_Imovel_Importa_Foto_Loja( $this->post_id, $loja );
		$importa_fotos->salva_imagem_destaque();
	}
	public function atualizar( $post_id, $loja ) {
		$obs = $loja['observacoes']??$loja['nome'];
		$post_data = array(
			'post_title' => sanitize_title( $loja['nome'] )
			, 'post_content' => $obs??''
			, 'post_status' => 'publish'
			, 'post_type' => 'loja'
			, 'ID' => $post_id
		);
		$post_id = wp_update_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			error_log( 'Erro ao atualizar loja: ' . $post_id->get_error_message( ) );
			return false;
		}
		$this->apagar_metadados( $post_id );
		$this->salvar_metadados( $loja );
	}
	private function apagar_metadados( $post_id ) {
		global $wpdb;
		if ( !get_post( $post_id ) ) {
			return 0;
		}
		$wpdb->delete(
			$wpdb->postmeta,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);
	}
	private function recolhe_propriedades( $loja ) {
		$properties = array( );
		$set_propriedades = self::PROPRIEDADES;
		foreach ( $set_propriedades as $key => $value ) {
			if ( isset( $loja[$key] ) ) {
				$properties[ $value ] = $loja[ $key ];
			}
		}
		return $properties;
	}
	private function salvar_metadados( $loja ) {
		$properties = $this->recolhe_propriedades( $loja );

		foreach( $properties as $key => $value ) {
			if (is_string($value) && trim($value) === '') {
				continue;
			}
			if (is_numeric($value) && $value <= 0) {
				continue;
			}
			if ((is_array($value) || is_object($value)) && empty($value)) {
				continue;
			}
			add_post_meta( $this->post_id, $key, $value,false );
		}

		$endereco_padrao = $loja['p_enderecopadrao'];
		//print_r( $endereco_padrao );
		$this->salva_dados_endereco( $endereco_padrao );

		$emails = $this->recolhe_emails( $loja['emails'] );
		foreach( $emails as $key => $value ) {
			$endereco = $value['endereco'];
			add_post_meta( $this->post_id, 'email', $endereco,true );
		}
		$enderecos = $this->recolhe_enderecos( $loja['enderecos'] );
		foreach( $enderecos as $key => $value ) {
			$enderecoRenderizado = $value['enderecoRenderizado'];
			add_post_meta( $this->post_id, 'endereco', $enderecoRenderizado,false );
		}
		$telefones = $this->recolhe_telefones( $loja['telefones'] );
		foreach( $telefones as $key => $value ) {
			$numero = $value['numero'];
			$tipo = strtolower( $value['tipo'] );
			if ( $tipo == 'whatsapp' ) {
				add_post_meta( $this->post_id, 'whatsapp', $numero,false );
			} else {
				add_post_meta( $this->post_id, 'telefone', $numero,false );
			}
		}
	}
	private function salva_dados_endereco( $endereco ) {
		$propriedades = self::PROPRIEDADES_ENDERECO;
		foreach ( $propriedades as $meta ) {
			$prop = $endereco[ $meta ];
			//print_r( $prop );
			if ( !empty( $prop) ) {
				$pm = add_post_meta( $this->post_id, $meta, $prop, true );
			}
		}
	}
	private function recolhe_telefones( $telefones ) {
		$tels = [];
		foreach ( $telefones as $tel ) {
			$tipoendereco = $tel['tipoendereco'];
			$tels[] = array(
				'numero' => $tel['telefone']
				, 'tipo' => $tipoendereco['nome']
			);
		}
		return $tels;
	}
	private function recolhe_emails( $emails ) {
		$ems = [];
		foreach ( $emails as $em ) {
			$ems[] = array(
				'endereco' => $em['endereco']
				, 'nome' => esc_html( $em['nome'] )
				, 'tipo' => $em['tipoendereco']['nome']
			);
		}
		return $ems;
	}
	private function recolhe_enderecos( $enderecos ) {
		$end = [];
		foreach ( $enderecos as $endereco ) {
			$end[] = array(
				'bairro' => $endereco[ 'bairro' ]
				, 'cep' => $endereco[ 'cep' ]
				, 'cidade' => $endereco[ 'cidade' ]
				, 'complemento' => $endereco[ 'complemento' ]
				, 'enderecoRenderizado' => $endereco[ 'enderecoRenderizado' ]
				, 'logradouro' => $endereco[ 'logradouro' ]
				, 'numero' => $endereco[ 'numero' ]
				, 'tipo' => $endereco[ 'tipo' ]
				, 'uf' => $endereco[ 'uf' ]
				, 'tipo' => $endereco['tipoendereco']['nome']
			);
		}
		return $end;
	}
}
