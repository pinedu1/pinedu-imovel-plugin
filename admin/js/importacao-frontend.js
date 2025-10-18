function doPost(action, argumentos, callbackSuccess, callbackError, callbackPre, callbackPost) {
    const data = {action, ...(argumentos || {})}; // <== Este troço mescla a string com o JSON
    if (typeof callbackPre === 'function') {
        callbackPre();
    }
    fetch(PineduAjax.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams(data)
    })
        .then(response => {
            // 1. PRIMEIRO THEN: Verifica o status HTTP
            if (!response.ok) {
                // Se status 4xx ou 5xx, lança erro para ir ao .catch()
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // Retorna a Promise para ler o corpo como JSON
            return response.json();
        })
        .then(data => {
            if (typeof callbackSuccess === 'function') {
                // Agora, callbackSuccess recebe o objeto 'data' final e pronto para uso
                if ( parseInt( PineduAjax.atrasarRequisicao ) > 0 ) {
                    setTimeout(() => {
                        callbackSuccess(data);
                    }, ( parseInt( PineduAjax.atrasarRequisicao ) * 1000) );
                } else {
                    callbackSuccess(data);
                }
            }
            // Você não precisa de um 'return' aqui a menos que queira encadear mais Promises
        })
        .catch(err => {
            // O .catch() lida com erros de rede OU o Error lançado no primeiro .then()
            console.error('❌ Erro na requisição:', err);
            // MELHOR LUGAR PARA O CALLBACK DE ERRO:
            if (typeof callbackError === 'function') {
                // Passe o objeto de erro para o callback de erro
                callbackError(err);
            }
        })
        .finally(() => {
            // NOVO: EXECUÇÃO DE posPost (SEMPRE, APÓS SUCESSO OU ERRO)
            // Este é o equivalente moderno e limpo do 'complete' do jQuery.
            if (typeof callbackPost === 'function') {
                callbackPost();
            }
            // Ideal para esconder o spinner, reabilitar o botão, etc.
        });
}

function errorDoPost(data) {
    alteraInfo('Erro!');
    alteraMessage(data.message);
    alteraProgresso(0);
}

function escondeFechar() {
    const buttonElement = document.getElementById('importacao-fechar');
    if (buttonElement) {
        buttonElement.style.display = 'none';
    }
}

function exibeFechar() {
    const buttonElement = document.getElementById('importacao-fechar');
    if (buttonElement) {
        buttonElement.style.display = 'block';
    }
}

function finalizaOverlay() {
    const overlay = document.getElementById('importacao-log');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function inicializaOverlay() {
    const overlay = document.getElementById('importacao-log');
    if (overlay) {
        overlay.style.display = 'block';
    }
}

function alteraInfo(info) {
    const infoElement = document.getElementById('importacao-info');
    if (infoElement) {
        infoElement.textContent = info;
    }
}

function alteraMessage(message) {
    const messageElement = document.getElementById('importacao-message');
    if (messageElement) {
        messageElement.textContent = message;
    }
}

function alteraProgresso(progresso, text) {
    const progressoElement = document.getElementById('importacao-progress');
    const progressoTextElement = document.getElementById('importacao-progress-text');
    if (progressoElement) {
        progressoElement.style.backgroundColor = '#28a745';
        progressoElement.style.width = progresso + '%';
    }
    if (progressoTextElement) {
        progressoTextElement.textContent = text ? text : '';
    }
}

function inicializar() {
    console.log('inicializar');
    var before = function () {
        escondeFechar();
        inicializaOverlay();
        alteraInfo('Inicializando...');
        alteraMessage('Testando conexão com o servidor Remoto. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const progresso = 100;
        const message = 'Conexão com o Servidor remoto realizada com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            prepararCadastrosBasicos();
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;

    const args = {url_servidor: document.getElementById('url_servidor').value};
    doPost('IMPORTA_FRONTEND_INICIALIZAR', args, success, error, before, null);
}

function prepararCadastrosBasicos() {
    console.log('importarCadastrosBasicos');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando dados básicos. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const progresso = 100;
        const message = 'Dados básicos processados com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarEmpresa(data.empresa, data, ((100 / 8) * 1));
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {
        url_servidor: document.getElementById('url_servidor').value,
        forcar: ((document?.forcar === true) ? true : false)
    };
    doPost('IMPORTA_FRONTEND_PREPARAR_BASICOS', args, success, error, before, null);
}

function importarEmpresa(empresa, dadosBasicos, progresso) {
    console.log('importarEmpresa');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando Empresa. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Empresa processada com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarLoja(dadosBasicos.lojas, dadosBasicos, ((100 / 8) * 2));
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {empresa: JSON.stringify(empresa)};
    doPost('IMPORTA_FRONTEND_IMPORTAR_EMPRESA', args, success, error, before, null);
}

function importarLoja(lojas, dadosBasicos, progresso) {
    console.log('importarLoja');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando Lojas. Aguarde!');
        alteraProgresso(progresso);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Lojas processadas com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarCidades(dadosBasicos.cidades, dadosBasicos, ((100 / 8) * 3));
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {lojas: JSON.stringify(lojas)};
    doPost('IMPORTA_FRONTEND_IMPORTAR_LOJA', args, success, error, before, null);
}

function importarCidades(cidades, dadosBasicos, progresso) {
    console.log('importarCidades');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando Cidades. Aguarde!');
        alteraProgresso(progresso);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Cidades processadas com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarCorretores(dadosBasicos.corretores, dadosBasicos, ((100 / 8) * 4));
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {cidades: JSON.stringify(cidades)};
    doPost('IMPORTA_FRONTEND_IMPORTAR_CIDADE', args, success, error, before, null);
}

function importarCorretores(corretores, dadosBasicos, progresso) {
    console.log('importarCorretores');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando Corretores. Aguarde!');
        alteraProgresso(progresso);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Corretores processadas com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarTipoImoveis(dadosBasicos.tipoImoveis, dadosBasicos, ((100 / 8) * 5));
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {corretores: JSON.stringify(corretores)};
    doPost('IMPORTA_FRONTEND_IMPORTAR_CORRETOR', args, success, error, before, null);
}

function importarTipoImoveis(tipoImoveis, dadosBasicos, progresso) {
    console.log('importarTipoImoveis');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando Tipo de Imoveis. Aguarde!');
        alteraProgresso(progresso);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Tipo de Imoveis processadas com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarFaixaValor(dadosBasicos.faixaValores, dadosBasicos, ((100 / 8) * 6));
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {tipo_imoveis: JSON.stringify(tipoImoveis)};
    doPost('IMPORTA_FRONTEND_IMPORTAR_TIPO_IMOVEL', args, success, error, before, null);
}

function importarFaixaValor(faixaValores, dadosBasicos, progresso) {
    console.log('importarFaixaValor');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando Faixa de Valor. Aguarde!');
        alteraProgresso(progresso);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Faixa de Valor processadas com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarTipoDependencias(dadosBasicos.tipoDependencias, dadosBasicos, ((100 / 8) * 7));
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {faixa_valores: JSON.stringify(faixaValores)};
    doPost('IMPORTA_FRONTEND_IMPORTAR_FAIXA_VALOR', args, success, error, before, null);
}

function importarTipoDependencias(tipoDependencias, dadosBasicos, progresso) {
    console.log('importarTipoDependencias');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando Tipo de Dependências. Aguarde!');
        alteraProgresso(progresso);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Tipo de Dependências processados com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarTipoContrato(dadosBasicos.tipoContratos, dadosBasicos, ((100 / 8) * 8));
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {tipo_dependencias: JSON.stringify(tipoDependencias)};
    doPost('IMPORTA_FRONTEND_IMPORTAR_TIPO_DEPENDENCIA', args, success, error, before, null);
}

function importarTipoContrato(tipoContratos, dadosBasicos, progresso) {
    console.log('importarTipoContrato');
    var before = function () {
        alteraInfo('Cadastros Básicos...');
        alteraMessage('Importando Tipo de Contrato. Aguarde!');
        alteraProgresso(progresso);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Tipo de Contrato processados com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            prepararExcluirImoveis();
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message ?? 'Não foi possível conectar ao servidor remoto. Volte novamente mais tarde.');
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {tipo_contratos: JSON.stringify(tipoContratos)};
    doPost('IMPORTA_FRONTEND_IMPORTAR_CONTRATO', args, success, error, before, null);
}

function prepararImportarImoveis() {
    console.log('prepararImportarImoveis');
    var before = function () {
        alteraInfo('Imóveis...');
        alteraMessage('Preparando Importação de Imóveis. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const progresso = 100;
        const message = 'Preparação importação de Imóveis realizada com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            importarImoveisFrontEnd(PineduAjax.max, 0, data.total, 0, 0);
        } else {
            prepararImportarImagemDestaque(0);
        }
    }
    , error = errorDoPost;
    const args = {
        url_servidor: document.getElementById('url_servidor').value,
        forcar: ((document?.forcar === true) ? true : false)
    };
    doPost('IMPORTA_FRONTEND_PREPARAR_IMOVEIS', args, success, error, before, null);
}

function prepararExcluirImoveis() {
    console.log('excluirImoveis');
    var before = function () {
        alteraInfo('Imóveis...');
        alteraMessage('Preparando Exclusão de Imóveis fora do contexto. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const progresso = 100;
        const message = 'Exclusão de Imóveis fora do contexto realizada com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            excluirImoveis( data.ids, data.total );
        } else {
            prepararImportarImoveis();
        }
    }
    , error = errorDoPost;
    const args = {url_servidor: document.getElementById('url_servidor').value, forcar: ((document?.forcar === true) ? true : false)};
    doPost('PREPARAR_EXCLUIR_IMOVEIS', args, success, error, before, null);
}

function excluirImoveis(imoveisExcluidos, total) {
    console.log('excluirImoveis');
    var before = function () {
        alteraInfo('Imóveis...');
        alteraMessage('Preparando Exclusão de Imóveis fora do contexto. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const progresso = 100;
        const message = 'Exclusão de Imóveis fora do contexto realizada com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            prepararImportarImoveis();
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message);
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {excluidos: JSON.stringify(imoveisExcluidos)};
    doPost('IMPORTA_FRONTEND_EXCLUIR_IMOVEIS', args, success, error, before, null);
}

function importarImoveisFrontEnd(max, offset, total, progresso, retornados) {
    console.log('importarImoveis');

    var before = function () {
        alteraInfo('Imóveis...');
        alteraMessage('Importando de Imóveis. Aguarde!');
        alteraProgresso(progresso, offset + ' / ' + total);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Importação de Imóveis realizada com sucesso!';
        if (data.success === true) {
            retornados += data.returned;
            progresso = Math.round((retornados / total) * 100);
            offset = parseInt(offset) + parseInt(max);
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso, parseInt(offset) + '/' + parseInt(total));
            if (retornados < total) {
                importarImoveisFrontEnd(max, parseInt(offset), parseInt(total), progresso, retornados);
            } else {
                prepararImportarImagemDestaque(total);
            }
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message);
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {
        url_servidor: document.getElementById('url_servidor').value
        , ultima_atualizacao: PineduAjax?.ultimaAtualizacao
        , forcar: ((document?.forcar === true) ? true : false)
        , max: max
        , offset: offset
        , total: total
    };
    doPost('IMPORTA_FRONTEND_IMPORTAR_IMOVEIS', args, success, error, before, null);
}

function prepararImportarImagemDestaque(totalImoveis) {
    console.log('prepararImportarImagemDestaque');
    var before = function () {
        alteraInfo('Imagem de Destaque...');
        alteraMessage('Preparando Importação de Imagem Destaque. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const progresso = 100;
        const message = 'Preparação importação de Imagem Destaque realizada com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso);
            if (data.hasOwnProperty('total') && (data.total > 0)) {
                importarImagemDestaque(totalImoveis, 0, data.total, 0, 0);
            } else {
                finalizarImportacaoFrontEnd(totalImoveis);
            }
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message);
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {};
    doPost('PREPARA_IMAGEM_DESTAQUE', args, success, error, before, null);

}

function importarImagemDestaque(totalImoveis, offset, totalDestaques, progresso, retornados) {
    console.log('importarImagemDestaque');
    var before = function () {
        const info = 'Sucesso!';
        const message = 'Importando Imagens Destaque. Aguarde!';
        alteraInfo(info);
        alteraMessage(message);
        alteraProgresso(progresso, parseInt(retornados) + '/' + parseInt(totalDestaques));
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Importando Imagens Destaque. Aguarde!';

        if (data.success === true) {
            retornados += parseInt(data.returned);
            progresso = Math.round((retornados / totalDestaques) * 100);
            offset = parseInt(offset) + parseInt(PineduAjax.maxDestaques);
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(progresso, parseInt(retornados) + '/' + parseInt(totalDestaques));
            if ((data.returned > 0) && (retornados < totalDestaques)) {
                importarImagemDestaque(totalImoveis, parseInt(offset), parseInt(totalDestaques), progresso, retornados);
            } else {
                finalizarImportacaoDestaques(totalImoveis);
            }
        } else {
            finalizarImportacaoDestaques(totalImoveis);
        }
    }
    , error = errorDoPost;
    const args = {
        offset: offset
        , max: PineduAjax.maxDestaques
    };
    doPost('IMPORTA_IMAGEM_DESTAQUE', args, success, error, before, null);
}

function finalizarImportacaoDestaques(totalImoveis) {
    console.log('finalizarImportacaoDestaques');

    var before = function () {
        alteraInfo('Concluído!');
        alteraMessage('Finalizando Importação de Destaques. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Importação de Destaques realizada com sucesso!';
        alteraInfo(info);
        alteraMessage(message);
        alteraProgresso(100);
        finalizarImportacaoFrontEnd(totalImoveis)
    }
    , error = errorDoPost;
    doPost('FINALIZA_IMAGEM_DESTAQUE', {}, success, error, before, null);
}

function finalizarImportacaoFrontEnd(totalImoveis) {
    console.log('finalizarImportacaoFrontEnd');

    var before = function () {
        alteraInfo('Concluído!');
        alteraMessage('Finalizando Importação. Aguarde!');
        alteraProgresso(0);
    }
    , success = function (data) {
        const info = 'Sucesso!';
        const message = 'Importação de Dados e Imóveis realizada com sucesso!';
        if (data.success === true) {
            alteraInfo(info);
            alteraMessage(message);
            alteraProgresso(100);
            exibeFechar();
        } else {
            alteraInfo('Erro!');
            alteraMessage(data.message);
            alteraProgresso(0);
        }
    }
    , error = errorDoPost;
    const args = {
        imoveis_importados: totalImoveis
    };
    doPost('FINALIZA_IMPORTACAO', args, success, error, before, null);
}
/**
 * Escuta o clique no botão
 */
document.addEventListener('DOMContentLoaded', function () {
    const btnImportarForcado = document.getElementById('btnImportarForcadoFrontEnd');
    const btnImportar = document.getElementById('btnImportarFrontEnd');
    const btnFechar = document.getElementById('btnFechar');
    if (btnImportar) {
        document.forcar = false;
        btnImportar.addEventListener('click', function (e) {
            e.preventDefault();
            inicializar();
        });
    }
    if (btnImportarForcado) {
        document.forcar = true;
        btnImportarForcado.addEventListener('click', function (e) {
            e.preventDefault();
            inicializar();
        });
    }
    if (btnFechar) {
        btnFechar.addEventListener('click', function (e) {
            e.preventDefault();
            finalizaOverlay();
        });
    }
});
