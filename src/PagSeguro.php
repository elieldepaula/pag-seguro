<?php

namespace elieldepaula\pagseguro;

/**
 * Description of PagSeguro
 *
 * @author elieldepaula
 */
class PagSeguro
{

    private $ps_email = '';
    private $ps_token = '';
    private $ps_sandbox = TRUE;
    private $ps_url = 'sandbox.pagseguro.uol.com.br';
    private $ps_imgbotao = 'https://p.simg.uol.com.br/out/pagseguro/i/botoes/pagamentos/164x37-pagar-assina.gif';
    private $ps_cart = false;
    private $ps_timeout = 30;
    private $this_user = array(
        'id' => false,
        'nome' => false,
        'ddd' => false, // só números
        'telefone' => false, // só números
        'email' => false,
        'shippingType' => 3, //1=Encomenda normal (PAC), 2=SEDEX, 3=Tipo de frete não especificado.
        'cep' => false, // só números
        'logradouro' => '',
        'numero' => '',
        'compl' => '',
        'bairro' => '',
        'cidade' => '',
        'uf' => '',
        'pais' => 'BRA'
    );
    private $status = array(
        0 => 'desconhecido',
        1 => 'Aguardando pagamento',
        2 => 'Em análise',
        3 => 'Paga',
        4 => 'Disponível',
        5 => 'Em disputa',
        6 => 'Devolvida',
        7 => 'Cancelada'
    );
    private $config_botao = array();
    private $ps_reference = '';

    /**
     * Seta os dados da credencial da conta do PagSeguro.
     * @param type $dados
     * @param bool $sandbox Define se usa ou não o sandbox.
     * @throws Exception
     */
    public function setCredentials($dados = array(), $sandbox = TRUE)
    {

        if (!is_array($dados)) {
            throw new Exception('As credenciais devem ser informadas como array.');
        }

        if ((count($dados) <= 0) or ( $dados['email'] === '') or ( $dados['token'] === '')) {
            throw new Exception('As credenciais não podemm ficar em branco..');
        }

        if ($sandbox == TRUE) {
            $this->ps_url = 'sandbox.pagseguro.uol.com.br';
        } else {
            $this->ps_url = 'pagseguro.uol.com.br';
        }

        $this->ps_email = $dados['email'];
        $this->ps_token = $dados['token'];
    }

    /**
     * Recebe e prapara dados do usuário... opcional
     * @param array $data
     * @return boolean|string
     */
    public function setCustomer($data = array())
    {

        $data = $this->customerParser($data);

        foreach ($this->this_user as $key => $val)
        {

            if (isset($data[$key])) {
                $this->this_user[$key] = $data[$key];
            }
        }
    }

    /**
     * Retorna dados do usuário na memória.
     * @return type
     */
    public function getCustomer()
    {
        return $this->this_user;
    }

    /**
     * Métdo que se comunica com o PagSeguro, recebe o POST e retorna dados
     * da loja validando a requisição do usuário.
     * O retorno pode ser: VERIFICADO, FALSO, ...
     * @return string
     */
    public function notificationPost()
    {

        $postdata = 'Comando=validar&Token=' . $this->ps_token;
        foreach ($_POST as $key => $value)
        {
            $valued = $this->clearStr($value);
            $postdata .= "&$key=$valued";
        }
        return $this->verify($postdata);
    }

    /**
     * Limpa string para enviar ao PagSeguro
     * @param string $str
     * @return string
     */
    private function clearStr($str)
    {
        if (!get_magic_quotes_gpc()) {
            $str = addslashes($str);
        }
        return $str;
    }

    /**
     * Faz conexão com os servidores do PagSeguro e recebe a string de retorno
     * @param string $data
     * @return string
     */
    private function verify($data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://" . $this->ps_url . "/pagseguro-ws/checkout/NPI.jhtml");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-Type: application/xml; charset=ISO-8859-1"))
        curl_setopt($curl, CURLOPT_pagseguro_timeout, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $result = trim(curl_exec($curl));
        curl_close($curl);
        return $result;
    }

    /**
     * Retorna array com o número do pedido (extrato) e o status.
     * Fonte: PagSeguro.
     * Retorno:
     *  array
     *      'code' => 3
     *      'status' => 'Paga'
     *      'reference' => 107
     * @param string $code
     * @return array
     */
    public function getNotification($code = NULL)
    {

        // se não for passado um código, usará o $_POST que o PS envia
        if ($code === NULL) {
            $code = $_POST['notificationCode'];
        }

        $url = "https://ws." . $this->ps_url . "/v2/transactions/notifications/";
        $url .= $code . "?email=" . $this->ps_email . "&token=" . $this->ps_token;

        // faz conexão
        $transaction = $this->curlConnection($url);

        // algo deu errado na autenticação
        if ($transaction == 'Unauthorized') {
            log_message('erro', 'Notificação PagSeguro com problemas.');
            return FALSE;
        }

        // converte para objeto
        $xml = simplexml_load_string($transaction);

        // retorna
        return array(
            'code' => (int) $xml->status,
            'status' => $this->getStatus((int) $xml->status),
            'reference' => (int) $xml->reference
        );
    }

    /**
     * Nomenclatura de notificações do PagSeguro.
     * @param int $indice
     * @return string
     */
    public function getStatus($indice = 0)
    {
        return $this->ps_status[$indice];
    }

    /**
     * Método do PagSeguro para conexão via cRUL.
     * @param type $url
     * @param string $method GET com padrão
     * @param array $data
     * @param type $pagseguro_timeout 30
     * @param type $charset ISO
     * @return array
     */
    private function curlConnection($url, $method = 'GET', Array $data = null, $pagseguro_timeout = 30, $charset = 'UTF-8')
    {

        if (strtoupper($method) === 'POST') {
            $postFields = ($data ? http_build_query($data, '', '&') : "");
            $contentLength = "Content-length: " . strlen($postFields);
            $methodOptions = Array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
            );
        } else {
            $contentLength = null;
            $methodOptions = Array(
                CURLOPT_HTTPGET => true
            );
        }

        $options = Array(
            CURLOPT_HTTPHEADER => Array(
                "Content-Type: application/x-www-form-urlencoded; charset=" . $charset,
                $contentLength
            ),
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTpagseguro_timeout => $pagseguro_timeout,
            //CURLOPT_pagseguro_timeout => $pagseguro_timeout
        );
        $options = ($options + $methodOptions);

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $resp = curl_exec($curl);
        $info = curl_getinfo($curl); // para debug
        $error = curl_errno($curl);
        $errorMessage = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new Exception("CURL não pode conectar: $errorMessage");
        } else {
            return $resp;
        }
    }

    /**
     * Estabelece quais config_botaourações foram enviadas.
     * Obrigatórias:
     *  array(
     *      'reference' => int
     * )
     * @param type $data
     */
    private function configButton($data = array())
    {

        if (!is_array($data)) {
            $data = array($data);
        }

        foreach ($this->config_botao as $chv => $vlr)
        {

            if (isset($data[$chv])) {
                $this->$chv = $data[$chv];
            } else {
                $this->$chv = $vlr;
            }
        }
    }

    /**
     * Recebe as config_botaourações e gera botão.
     * @param type $config_botao
     * @return type
     */
    public function getButton($config_botao = NULL)
    {

        // primeira coisa, parsear as config_botaourações
        $this->configButton($config_botao);

        if ($this->ps_reference === FALSE && !is_numeric($this->ps_reference)) {
            return 'Erro ao gerar o botão: Linha 299.';
        }

        $button = $this->getFormOpen();

        // opcional
        if ($this->this_user['nome']) {
            $button .= $this->getUserInputs();
        }

        if ($this->getProductsInputs() === FALSE) {
            return 'Erro ao gerar o botão: Linha 310';
        }

        $button .= $this->getProductsInputs();
        $button .= $this->getFormClose();

        return $button;
    }

    /**
     * Prepara dados do usuário para o PagSeguro
     * @param type $user_array
     * @return boolean
     */
    private function customerParser($user_array)
    {

        if (!is_array($user_array)) {
            return FALSE;
        }

        $return = array();

        foreach ($user_array as $key => $value)
        {

            // cep
            if ($key == 'cep') {
                $value = str_replace(array(',', '.', ' '), '', $value);
            }

            // telefone
            if ($key == 'tel1') {
                $return['ddd'] = substr($value, 0, 2);
                $return['telefone'] = substr(str_replace('-', '', $value), -8);
            }
            // tel2
            if ($key == 'tel2' && strlen($return['ddd']) != 2) {
                $return['ddd'] = substr($value, 0, 2);
                $return['telefone'] = substr(str_replace('-', '', $value), -8);
            }

            // número
            if ($key == 'num') {
                $return['numero'] = $value;
            }

            $return[$key] = $value;
        }

        return $return;
    }

    /**
     * baseado nas configurações, monta o formulário
     * @param array $user_array
     * @return string
     */
    public function getUserInputs()
    {
        $f = array();
        // '<!-- Dados do comprador (opcionais) -->  
        $f[] = '<input type="hidden" name="senderName" value="' . $this->this_user['nome'] . '">';
        $f[] = '<input type="hidden" name="senderAreaCode" value="' . $this->this_user['ddd'] . '">';
        $f[] = '<input type="hidden" name="senderPhone" value="' . $this->this_user['telefone'] . '">';
        $f[] = '<input type="hidden" name="senderEmail" value="' . $this->this_user['email'] . '">';

        // <!-- Informações de frete (opcionais) -->  
        $f[] = '<input type="hidden" name="shippingType" value="' . $this->this_user['shippingType'] . '">';
        $f[] = '<input type="hidden" name="shippingAddressPostalCode" value="' . $this->this_user['cep'] . '">';
        $f[] = '<input type="hidden" name="shippingAddressStreet" value="' . $this->this_user['logradouro'] . '">';
        $f[] = '<input type="hidden" name="shippingAddressNumber" value="' . $this->this_user['numero'] . '">';
        $f[] = '<input type="hidden" name="shippingAddressComplement" value="' . $this->this_user['compl'] . '">';
        $f[] = '<input type="hidden" name="shippingAddressDistrict" value="' . $this->this_user['bairro'] . '">';
        $f[] = '<input type="hidden" name="shippingAddressCity" value="' . $this->this_user['cidade'] . '">';
        $f[] = '<input type="hidden" name="shippingAddressState" value="' . $this->this_user['uf'] . '">';
        $f[] = '<input type="hidden" name="shippingAddressCountry" value="' . $this->this_user['pais'] . '">';

        return implode("\n", $f);
    }

    /**
     * Recebe o array com um produto, ou array multi com vários
     * Campos:
     *      id
     *      descricao
     *      valor
     *      quantidade
     *      peso
     * @param array $product_array
     */
    public function setProducts($product_array)
    {

        if (!is_array($product_array)) {
            log_message('error', 'set_products() Não existem produtos para PagSeguro');
            return FALSE;
        }

        // verifica se é um único produto, ou multi array
        if (isset($product_array[0]) && is_array($product_array[0])) {
            // já é multi array... vários produtos
            $this->ps_cart = $product_array;
        } else {
            // um único produto
            $this->ps_cart = array($product_array);
        }
    }

    /**
     * baseado nas configurações, monta o formulário
     */
    private function getProductsInputs()
    {

        if ($this->ps_cart === FALSE) {
            return FALSE;
        }

        $ttl = count($this->ps_cart);

        $f = array();
        //<!-- Itens do pagamento (ao menos um item é obrigatório) -->        
        // percorre os produtos
        for ($x = 0; $x < $ttl; $x++)
        {
            $id = $x + 1;

            $itemId = $this->ps_cart[$x]['id'];
            $itemDescription = $this->ps_cart[$x]['descricao'];
            $itemAmount = $this->ps_cart[$x]['valor'];
            $itemQuantity = $this->ps_cart[$x]['quantidade'];
            $itemWeight = $this->ps_cart[$x]['peso'];

            $f[] = '<input type="hidden" name="itemId' . $id . '" value="' . $itemId . '">';
            $f[] = '<input type="hidden" name="itemDescription' . $id . '" value="' . $itemDescription . '">';
            $f[] = '<input type="hidden" name="itemAmount' . $id . '" value="' . $itemAmount . '">';
            $f[] = '<input type="hidden" name="itemQuantity' . $id . '" value="' . $itemQuantity . '">';
            $f[] = '<input type="hidden" name="itemWeight' . $id . '" value="' . $itemWeight . '">';
        }

        return implode("\n", $f);
    }

    /**
     * Gera a parte inicial do form
     * @return string
     */
    private function getFormOpen()
    {
        $f = array();
        $f[] = '<form target="pagseguro" method="post" action="https://' . $this->ps_url . '/v2/checkout/payment.html">';
        // '<!-- Campos obrigatórios -->';
        $f[] = '<input type="hidden" name="receiverEmail" value="' . $this->ps_email . '">';
        $f[] = '<input type="hidden" name="currency" value="BRL">';
        $f[] = '<input type="hidden" name="encoding" value="UTF-8">';
        //<!-- Código de referência do pagamento no sistema (opcional) -->  
        $f[] = '<input type="hidden" name="reference" value="' . $this->ps_reference . '">';

        return implode("\n", $f);
    }

    /**
     * Gera a parte final do form
     * @return string
     */
    private function getFormClose()
    {
        $f = array();
        //<!-- submit do form (obrigatório) -->  
        $f[] = '<input type="image" class="btn-pagseuro" name="submit" src="' . $this->ps_imgbotao . '" alt="Pague com PagSeguro">';
        $f[] = '</form>';
        return implode("\n", $f);
    }

}
