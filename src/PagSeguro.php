<?php
namespace elieldepaula\pagseguro;

/**
 * Esta biblioteca fornece os meios de forma resumida para integrar
 * o pagamento padrão do PagSeguro em seu site.
 *
 * Usa a versão 2.* da API do pagseguro.
 *
 * @author Eliel de Paula <dev@elieldepaula.com.br>
 */
class PagSeguro
{

    /**
     * Email da conta do PagSeguro.
     * @var string
     */
    private $ps_email = '';

    /**
     * Token de integração do PagSeguro.
     * @var string
     */
    private $ps_token = '';

    /**
     * Url do PagSeguro.
     * @var string
     */
    private $ps_url = 'sandbox.pagseguro.uol.com.br';

    /**
     * Link da imagem do botão de pagamento.
     * @var string
     */
    private $ps_imgbotao = 'https://p.simg.uol.com.br/out/pagseguro/i/botoes/pagamentos/164x37-pagar-assina.gif';

    /**
     * Array de produtos.
     * @var array
     */
    private $ps_products = array();

    /**
     * Array com os dados do cliente.
     * @var array
     */
    private $ps_customer = array(
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

    /**
     * Array com os status disponíveis para as transações.
     * @var array
     */
    private $ps_status = array(
        0 => 'desconhecido',
        1 => 'Aguardando pagamento',
        2 => 'Em análise',
        3 => 'Paga',
        4 => 'Disponível',
        5 => 'Em disputa',
        6 => 'Devolvida',
        7 => 'Cancelada'
    );

    /**
     * Código de referência da transaçao.
     * @var string
     */
    private $ps_reference = '';

    /**
     * Seta os dados da credencial da conta do PagSeguro. Ppor padrão as credenciais
     * são criadas usando o ambiente de desenvolvimento (sandbox) para evitar
     * confusão, bastando informar 'false' no terçeiro parâmetro.
     * 
     * Ex: $ps->setCredentials(['email'=>'email@dominio.com', 'token'=>'DADADADADADADA'], true);
     * 
     * @param array $dados Array com o email e o token de integração.
     * @param bool $sandbox Define se usa ou não o sandbox.
     * @throws Exception Caso os dados informados estejam errados.
     */
    public function setCredentials($dados = array(), $sandbox = true)
    {
        if (!is_array($dados))
            throw new Exception('As credenciais devem ser informadas como array.');

        if ((count($dados) <= 0) or ( $dados['email'] === '') or ( $dados['token'] === ''))
            throw new Exception('As credenciais não podemm ficar em branco ou estãao incorretas.');

        if ($sandbox == true)
            $this->ps_url = 'sandbox.pagseguro.uol.com.br';
        else
            $this->ps_url = 'pagseguro.uol.com.br';
        $this->ps_email = $dados['email'];
        $this->ps_token = $dados['token'];
    }

    /**
     * Informa o código de referência da venda.
     *
     * @param int $reference Código de referência da venda.
     * @throws Exception Caso a referência fique em branco.
     */
    public function setReference($reference = null)
    {
        if ($reference == null)
            throw new Exception('A referência não pode ficar em branco.');
        $this->ps_reference = $reference;
    }

    /**
     * Informa uma imagem alternativa para o botão de pagamento.
     *
     * @param string $imageUrl Link completo da imagem.
     */
    public function setImageButon($imageUrl)
    {
        $this->ps_imgbotao = $imageUrl;
    }

    /**
     * Recebe e prapara dados do cliente (customer ou sender). Informar o cliente
     * é opcional mas enviar os dados agiliza o checkout no site do PagSeguro.
     * 
     * @param array $data Array com os dados do cliente.
     * @return mixed
     */
    public function setCustomer($data = array())
    {
        $data = $this->parserCustomer($data);
        foreach ($this->ps_customer as $key => $val) {
            if (isset($data[$key]))
                $this->ps_customer[$key] = $data[$key];
        }
    }

    /**
     * Recebe o array com um produto, ou array multi com vários campos:
     * 
     * - id
     * - descricao
     * - valor
     * - quantidade
     * - peso
     * 
     * @param array $data Array com os produtos da transação.
     */
    public function setProducts($data)
    {
        if (!is_array($data))
            throw new Exception('Nenhum produto foi informado.');
        if (isset($data[0]) && is_array($data[0]))
            $this->ps_products = $data;
        else
            $this->ps_products = array($data);
    }

    /**
     * Retorna dados do cliente guardados na variável.
     * 
     * @return array Array com os dados do cliente.
     */
    public function getCustomer()
    {
        return $this->ps_customer;
    }

    /**
     * Busca uma transação pelo código de notificação.
     * 
     * @param string $notificationCode Código de notificação enviado pelo PagSeguro.
     * @return mixed
     * @throws Exception
     */
    public function findByNotification($notificationCode = null)
    {
        $url = 'https://ws.' . $this->ps_url . '/v2/transactions/notifications/' . $notificationCode . '?email=' . $this->ps_email . '&token=' . $this->ps_token;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $transaction = curl_exec($curl);
        curl_close($curl);
        if ($transaction == 'Unauthorized') {
            throw new Exception('Código de notificação não autorizado.');
            exit;
        }
        return simplexml_load_string($transaction);
    }

    /**
     * Busca uma transação por um código de transação.
     * 
     * @param string $transactionCode Código de transação.
     * @return mixed
     * @throws Exception
     */
    public function findByCode($transactionCode = null)
    {
        if ($transactionCode === null)
            $transactionCode = $_POST['notificationCode'];
        $url = 'https://ws.' . $this->ps_url . '/v2/transactions/' . $transactionCode . '?email=' . $this->ps_email . '&token=' . $this->ps_token;
        $options = Array(
            CURLOPT_HTTPHEADER => Array(
                "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                null
            ),
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_HTTPGET => true
        );
        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $transaction = curl_exec($curl);
        $error = curl_errno($curl);
        $errorMessage = curl_error($curl);
        curl_close($curl);
        if ($error)
            throw new Exception('CURL não pode conectar: ' . $errorMessage);

        if ($transaction == 'Unauthorized')
            throw new Exception('Notificação PagSeguro com problemas.');
        return simplexml_load_string($transaction);
    }

    /**
     * Retorna o nome dos status da transação do PagSeguro.
     * 
     * @param int $status Código do status da transação.
     * @return string
     */
    public function getStatus($status = 0)
    {
        return $this->ps_status[$status];
    }

    /**
     * Gera o botão de pagamento com as configurações enviadas.
     * 
     * @return string
     * @throws Exception
     */
    public function getButton()
    {
        if ($this->ps_reference === false && !is_numeric($this->ps_reference))
            throw new Exception('Erro ao gerar o botão: Linha 315');
        $button = $this->getFormOpen();
        $button .= $this->getCustomerInputs();
        if ($this->getProductsInputs() === false)
            throw new Exception('Erro ao gerar os inputs do botão.');
        $button .= $this->getProductsInputs();
        $button .= $this->getFormClose();
        return $button;
    }

    /**
     * Prepara dados do cliente para a transação.
     * 
     * @param array $data Array com os dados do cliente.
     * @return mixed
     */
    private function parserCustomer($data)
    {
        if (!is_array($data))
            return false;
        $result = array();
        foreach ($data as $key => $value) {
            if ($key == 'cep')
                $value = str_replace(array(',', '.', ' '), '', $value);
            if ($key == 'tel1') {
                $result['ddd'] = substr($value, 0, 2);
                $result['telefone'] = substr(str_replace('-', '', $value), -8);
            }
            if ($key == 'tel2' && strlen($result['ddd']) != 2) {
                $result['ddd'] = substr($value, 0, 2);
                $result['telefone'] = substr(str_replace('-', '', $value), -8);
            }
            if ($key == 'num')
                $result['numero'] = $value;
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Retorna os inputs com os dados do cliente.
     * 
     * @return string
     */
    private function getCustomerInputs()
    {
        $out = array();
        if ($this->ps_customer['nome'])
            $out[] = '<input type="hidden" name="senderName" value="' . $this->ps_customer['nome'] . '">';
        if ($this->ps_customer['ddd'])
            $out[] = '<input type="hidden" name="senderAreaCode" value="' . $this->ps_customer['ddd'] . '">';
        if ($this->ps_customer['telefone'])
            $out[] = '<input type="hidden" name="senderPhone" value="' . $this->ps_customer['telefone'] . '">';
        if ($this->ps_customer['email'])
            $out[] = '<input type="hidden" name="senderEmail" value="' . $this->ps_customer['email'] . '">';
        if ($this->ps_customer['shippingType'])
            $out[] = '<input type="hidden" name="shippingType" value="' . $this->ps_customer['shippingType'] . '">';
        if ($this->ps_customer['cep'])
            $out[] = '<input type="hidden" name="shippingAddressPostalCode" value="' . $this->ps_customer['cep'] . '">';
        if ($this->ps_customer['logradouro'])
            $out[] = '<input type="hidden" name="shippingAddressStreet" value="' . $this->ps_customer['logradouro'] . '">';
        if ($this->ps_customer['numero'])
            $out[] = '<input type="hidden" name="shippingAddressNumber" value="' . $this->ps_customer['numero'] . '">';
        if ($this->ps_customer['compl'])
            $out[] = '<input type="hidden" name="shippingAddressComplement" value="' . $this->ps_customer['compl'] . '">';
        if ($this->ps_customer['bairro'])
            $out[] = '<input type="hidden" name="shippingAddressDistrict" value="' . $this->ps_customer['bairro'] . '">';
        if ($this->ps_customer['cidade'])
            $out[] = '<input type="hidden" name="shippingAddressCity" value="' . $this->ps_customer['cidade'] . '">';
        if ($this->ps_customer['uf'])
            $out[] = '<input type="hidden" name="shippingAddressState" value="' . $this->ps_customer['uf'] . '">';
        if ($this->ps_customer['pais'])
            $out[] = '<input type="hidden" name="shippingAddressCountry" value="' . $this->ps_customer['pais'] . '">';
        return implode("\n", $out);
    }

    /**
     * Retorna os inputs com os dados dos produtos.
     * 
     * @return mixed
     */
    private function getProductsInputs()
    {
        if ($this->ps_products === false)
            return false;
        $ttl = count($this->ps_products);
        $out = array();
        for ($x = 0; $x < $ttl; $x++)
        {
            $id = $x + 1;
            $itemId = $this->ps_products[$x]['id'];
            $itemDescription = $this->ps_products[$x]['descricao'];
            $itemAmount = $this->ps_products[$x]['valor'];
            $itemQuantity = $this->ps_products[$x]['quantidade'];
            $itemWeight = $this->ps_products[$x]['peso'];
            $out[] = '<input type="hidden" name="itemId' . $id . '" value="' . $itemId . '">';
            $out[] = '<input type="hidden" name="itemDescription' . $id . '" value="' . $itemDescription . '">';
            $out[] = '<input type="hidden" name="itemAmount' . $id . '" value="' . $itemAmount . '">';
            $out[] = '<input type="hidden" name="itemQuantity' . $id . '" value="' . $itemQuantity . '">';
            $out[] = '<input type="hidden" name="itemWeight' . $id . '" value="' . $itemWeight . '">';
        }
        return implode("\n", $out);
    }

    /**
     * Gera a código inicial do formlário do botão de pagamento.
     * 
     * @return string
     */
    private function getFormOpen()
    {
        $out = array();
        $out[] = '<form target="pagseguro" method="post" action="https://' . $this->ps_url . '/v2/checkout/payment.html">';
        $out[] = '<input type="hidden" name="receiverEmail" value="' . $this->ps_email . '">';
        $out[] = '<input type="hidden" name="currency" value="BRL">';
        $out[] = '<input type="hidden" name="encoding" value="UTF-8">';
        $out[] = '<input type="hidden" name="reference" value="' . $this->ps_reference . '">';
        return implode("\n", $out);
    }

    /**
     * Retorna o codigo de fechamento do form com o botão do PagSeguro.
     * 
     * @return string
     */
    private function getFormClose()
    {
        $out = array();
        $out[] = '<input type="image" name="submit" src="' . $this->ps_imgbotao . '" alt="Pague com PagSeguro">';
        $out[] = '</form>';
        return implode("\n", $out);
    }

}
