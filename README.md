# Pag Seguro
Biblioteca alternativa para pagamento padrão do PagSeguro. Esta biblioteca utiliza a versão 2.* da API do PagSeguro.

## Instalação

Voce deve instalar a biblioteca usando o [Composer](https://packagist.org/packages/elieldepaula/pagseguro)

Adicione no seu arquivo composer.json:

``` json
"require": {
    ...
    "elieldepaula/pagseguro":"dev-master"
}
```

## Exemplos de utilização

### Gerar botão de pagamento
```php

// Considerei que você já tem um autoload configurado.

use elieldepaula\pagseguro\PagSeguro;

try {
    
    // Referência da sua venda.
    $referencia = (int) 2017;
    
    $ps = new PagSeguro();    
    $ps->setCredentials(['email'=>'mail@dominio.com', 'token'=>'N0N0N0']);
    $ps->setReference($referencia);
    $ps->setCustomer(
        [
            'nome' => 'Comprador de Teste',
            'email' => 'email@sandbox.pagseguro.com.br',
            'shippingType' => 3
        ]
    );
    $ps->setProducts(
        [
            'id' => 1,
            'descricao' => 'Produto de exemplo',
            'valor' => 1.99,
            'quantidade' => 2,
            'peso' => 0
        ],
        ... (mais produtos)
    );
    
    $botao = $ps->getButton();
	
    echo $botao;
    
} catch (Exception $error) {
    echo $error->getMessage();
}

```

### Fazer uma consulta por código de transação

```php

// Considerei que você já tem um autoload configurado.

use elieldepaula\pagseguro\PagSeguro;

try {

    $ps = new PagSeguro();
    $ps->setCredentials(['email'=>'mail@dominio.com', 'token'=>'N0N0N0']);
    $resultado = $ps->findByCode($_POST['transactionCode']);
    
    var_dump($resultado);
    
} catch (Exception $error) {
    echo $error->getMessage();
}

```

### Fazer uma consulta por código de notificação

```php

// Considerei que você já tem um autoload configurado.

use elieldepaula\pagseguro\PagSeguro;

try {

    $ps = new PagSeguro();
    $ps->setCredentials(['email'=>'mail@dominio.com', 'token'=>'N0N0N0']);
    $resultado = $ps->findByNotification($_POST['notificationCode']);
    
    var_dump($resultado);
    
} catch (Exception $error) {
    echo $error->getMessage();
}

```

### Exemplo de retorno de notificação automática do Pag Seguro

Este tipo de retorno ocorre toda vez que o status de uma transação é alterado pelo sistema do Pag Seguro, como por exemplo quando uma transação é alterada de "Aguardando Pagamento" para "Paga". 

O Pag Seguro envia um POST com o Código de notificação para a URL indicada nas suas configurações da sua conta no Pag Seguro. 

Em seguida, usamos o código de notificação para buscar os dados completos da Transação, onde você pode pegar o campo "Reference" que você criou na hora de gerar o botão de pagamento. 

Assim você pode identificar sua venda no banco de dados e atualizar o status ou disparar qualquer outro tipo de ação no seu sistema.

```php

// Considerei que você já tem um autoload configurado.

use elieldepaula\pagseguro\PagSeguro;

if (count($_POST) > 0) {

    try {

        $ps = new PagSeguro();
        $ps->setCredentials(['email'=>'mail@dominio.com', 'token'=>'N0N0N0']);

        $notificationType = (isset($_POST['notificationType']) && $_POST['notificationType'] != '') ? $_POST['notificationType'] : FALSE;
        $notificationCode = (isset($_POST['notificationCode']) && $_POST['notificationCode'] != '') ? $_POST['notificationCode'] : FALSE;

        $resultado = $ps->findByNotification($_POST['notificationCode']);

        var_dump($resultado);

        // Exemplo: $resultado->reference;

    } catch (Exception $error) {
        echo $error->getMessage();
    }

} else {
    echo "Nenhum POST foi recebido.";
}
    
```