<?php


if (!defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 * Shipment plugin for weight_countries shipments, like regular postal services
 *
 * @version $Id: weight_countries.php 3220 2011-05-12 20:09:14Z Milbo $
 * @package VirtueMart
 * @subpackage Plugins - shipment
 * @copyright Copyright (C) 2004-2011 VirtueMart Team - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 * @author Valerie Isaksen
 *
 */
 
 /**
	Altera��o pluging para VirtueMart vers�o 2.0
	Data: 19/06/2012
	Autor: Bruno Pinho
	bpinhosilva@gmail.com
	Insercao do BT no metodo checkConditions() para permitir que o endereco de fatura 
	seja tambem usado para calculo do frete.
*/
 
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmShipmentCorreios_PAC extends vmPSPlugin {

    // instance of class
    public static $_this = false;

    function __construct(& $subject, $config) {
        //if (self::$_this)
        //   return self::$_this;
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = array(
            'shipment_logos' => array('', 'char'),
            'ValorDeclara_SN' => array(1, 'int'),
            'MaoPropria_SN' => array(0, 'int'),
            'AvisoReceb_SN' => array(0, 'int'),
            'Formatos_SN' => array(0, 'int'),
            'Comprimento_SN' => array(0, 'int'),
            'Larg_Diam_SN' => array(0, 'int'),
            'Altura_SN' => array(0, 'int'),
            'UsoFaixa_SN' => array(0, 'int'),
            'package_fee' => array(0, 'int'),
            'CepStart_SN' => array(0, 'int'),
            'CepEnd_SN' => array(0, 'int'),
            'ContFalha_SN' => array(0, 'int'),
            'UsaSC_SN' => array(0, 'int'),
            'Msgfalha_SN' => array('', 'varchar(32)'),
            'Handling_Fee_SN' => array(0, 'double'),
            'SEDEX_TAX_CLASS' => array(0, 'int')
        );
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

// 		self::$_this
        //$this->createPluginTable($this->_tablename);
        //self::$_this = $this;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * @author ValÃ©rie Isaksen
     */
    protected function getVmPluginCreateTableSQL() {

        return $this->createTableSQL('Shipment Correios Pac Table');
    }

    function getTableSQLFields() {
        $SQLfields = array(
            'id' => ' tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_shipmentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'shipment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'order_weight' => 'decimal(10,4) DEFAULT NULL',
            'shipment_weight_unit' => 'char(3) DEFAULT \'KG\' ',
            'shipment_cost' => 'decimal(10,2) DEFAULT NULL',
            'shipment_package_fee' => 'decimal(10,2) DEFAULT NULL',
            'tax_id' => 'smallint(1) DEFAULT NULL'
        );
        return $SQLfields;
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the shipment-specific data.
     *
     * @param integer $order_number The order Number
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     * @author ValÃ©rie Isaksen
     * @author Max Milbers
     */
    public function plgVmOnShowOrderFEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
    }

    /**
     * This event is fired after the order has been stored; it gets the shipment method-
     * specific data.
     *
     * @param int $order_id The order_id being processed
     * @param object $cart  the cart
     * @param array $priceData Price information for this order
     * @return mixed Null when this method was not selected, otherwise true
     * @author Valerie Isaksen
     */
    function plgVmConfirmedOrder(VirtueMartCart $cart, $order) {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_shipmentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->shipment_element)) {
            return false;
        }

        $values['order_number'] = $order['details']['BT']->order_number;
        $values['shipment_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
        $values['shipment_name'] = $this->renderPluginName($method);
        $values['order_weight'] = $this->getOrderWeight($cart, $method->weight_unit);
        $values['shipment_weight_unit'] = $method->weight_unit;
        $values['shipment_cost'] = $method->cost;
        $values['shipment_package_fee'] = $method->package_fee;
        $values['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($values);

        return true;
    }

    /**
     * This method is fired when showing the order details in the backend.
     * It displays the shipment-specific data.
     * NOTE, this plugin should NOT be used to display form fields, since it's called outside
     * a form! Use plgVmOnUpdateOrderBE() instead!
     *
     * @param integer $virtuemart_order_id The order ID
     * @param integer $vendorId Vendor ID
     * @param object $_shipInfo Object with the properties 'shipment' and 'name'
     * @return mixed Null for shipments that aren't active, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderBEShipment($virtuemart_order_id, $virtuemart_shipmentmethod_id) {
        if (!($this->selectedThisByMethodId($virtuemart_shipmentmethod_id))) {
            return null;
        }
        $html = $this->getOrderShipmentHtml($virtuemart_order_id);
        return $html;
    }

    function getOrderShipmentHtml($virtuemart_order_id) {

        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
                . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);
        if (!($shipinfo = $db->loadObject())) {
            vmWarn(500, $q . " " . $db->getErrorMsg());
            return '';
        }

        if (!class_exists('CurrencyDisplay'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');

        $currency = CurrencyDisplay::getInstance();
        $tax = ShopFunctions::getTaxByID($shipinfo->tax_id);
        $taxDisplay = is_array($tax) ? $tax['calc_value'] . ' ' . $tax['calc_value_mathop'] : $shipinfo->tax_id;
        $taxDisplay = ($taxDisplay == -1 ) ? JText::_('COM_VIRTUEMART_PRODUCT_TAX_NONE') : $taxDisplay;

        $html = '<table class="adminlist">' . "\n";
        $html .=$this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_SHIPPING_NAME', $shipinfo->shipment_name);
        $html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_WEIGHT', $shipinfo->order_weight . ' ' . ShopFunctions::renderWeightUnit($shipinfo->shipment_weight_unit));
        $html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_COST', $currency->priceDisplay($shipinfo->shipment_cost, '', false));
        $html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_PACKAGE_FEE', $currency->priceDisplay($shipinfo->shipment_package_fee, '', false));
        $html .= $this->getHtmlRowBE('WEIGHT_COUNTRIES_TAX', $taxDisplay);
        $html .= '</table>' . "\n";

        return $html;
    }

    function vendedor($campo, $id=1) {
        $db = JFactory::getDBO();
        $sql = "SELECT * FROM #__virtuemart_vendors v " .
                "JOIN #__virtuemart_vmuser_shoppergroups g on (g.id = v.virtuemart_vendor_id)" .
                "JOIN #__virtuemart_userinfos i using (virtuemart_user_id)" .
                "WHERE virtuemart_vendor_id = '$id'";
        $db->setQuery($sql);
        $result = $db->loadAssoc();
        return $result[$campo];
    }

    function peso_total($c) {
        $peso = 0;
        foreach ($c->products as $p) {
            $peso += ( ShopFunctions::convertWeigthUnit($p->product_weight, $p->product_weight_uom, "KG") * $p->quantity);
        }
        return $peso;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        return $this->total;
    }

    function busca_preco_site_correios($cart, $method, $cart_prices) {





        //Define medidas e formato da embalagem
        //Usei os valores mínimos (16x11x2 Cm) para todas as medidas a seguir:
        //Comprimento médio dos pacotes utilizados para envio pelos Correios(Cm)
        $this->Order_Length = $method->Comprimento_SN;
        if ($this->Order_Length < 16) {
            $this->Order_Length = "16";
        }

        //Largura/Diâmetro médio dos pacotes utilizados para envio pelos Correios(Cm)
        $this->Order_Width = $method->Larg_Diam_SN;
        if ($this->Order_Width < 11) {
            $this->Order_Width = "11";
        }

        //Altura média dos pacotes utilizados para envio pelos Correios(Cm)
        $this->Order_Height = $method->Altura_SN;
        if ($this->Order_Height < 2) {
            $this->Order_Height = "2";
        }

        //Tipo de embrulho dos Correios
        $this->Order_Formatos = $method->Formatos_SN;

        // Define se o formato é rolo ou pacote
        if ($this->Order_Formatos == 1) {
            $this->Opt1 = "&nVlLargura=";
            $this->Opt2 = "&nVlAltura=" . $this->Order_Height;
        } else {
            $this->Opt1 = "&nVlDiametro=";
            $this->Opt2 = "";
        }


        //Taxa de empacotamento e manuseio, e será acrescida aos custos de envio retornados pelos Correios
        $this->Order_Handling_Fee = $method->Handling_Fee_SN;
        $this->Order_Handling_Fee = floatval(str_replace(",", ".", $this->Order_Handling_Fee));

        //Serviço Mão Própria dos Correios
        $this->Order_MaoPropria = $method->MaoPropria_SN;

        //Aviso de Recebimento dos Correios
        $this->Order_Aviso = $method->AvisoReceb_SN;

        $workstring = 'nCdEmpresa=';
        $workstring.='&sDsSenha=';
        $workstring.='&nCdServico=41106';
        $workstring.='&sCepOrigem=' . $this->cepOrigem;
        $workstring.='&sCepDestino=' . $this->cepDestino;
        $workstring.='&nVlPeso=' . $this->Order_WeightKG;
        $workstring.='&nCdFormato=' . $this->Order_Formatos;
        $workstring.='&nVlComprimento=' . $this->Order_Length . $this->Opt1 . $this->Order_Width . $this->Opt2;
        $workstring.='&MaoPropria=' . $this->Order_MaoPropria;
        if ($method->ValorDeclara_SN == "0") {
            $workstring.='&nVlValorDeclarado=0';
        } else {
            $workstring.='&nVlValorDeclarado=' . number_format($cart_prices->billTotal, 2, ',', '.');
        }
        $workstring.='&sCdAvisoRecebimento=' . $this->Order_Aviso;
        $workstring.='&StrRetorno=xml';
        $url_busca = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx";
        $url_busca .= "?" . $workstring;



        $this->retorno = $this->xml_correios($url_busca);

        $this->total = str_replace(",", ".", $this->retorno["valor"]) + $this->Order_Handling_Fee;
        return;
    }

    function xml_correios($url) {
        $xml = new DOMDocument();
        if (@$xml->load($url)) {
            $elementoMae = $xml->getElementsByTagName('cServico')->item(0);
            $valorFrete = $elementoMae->childNodes->item(1)->nodeValue;
            $prazoEntrega = $elementoMae->childNodes->item(2)->nodeValue;
            $msgerro = $elementoMae->getElementsByTagName("MsgErro")->item(0)->nodeValue;
            $erro = $elementoMae->getElementsByTagName("Erro")->item(0)->nodeValue;
        } else {
            $this->erro_site_correios = "Erro PAC: N&atilde;o foi poss&iacute;vel conectar ao site dos correios, tente novamente mais tarde.";
            return false;
        }
        if ($erro) {
            $this->erro_site_correios = "Aconteceu um erro no m&oacute;dulo PAC: " . $msgerro;
        }


        return array(
            "valor" => $valorFrete,
            "prazo" => $prazoEntrega,
            "erro" => $erro,
            "msgErro" => $msgerro);
    }

    function redireciona($url, $msg = null) {        
        if ($msg)
            setcookie("Mensagem", utf8_encode("$msg"), time() + 3, "/");
        header("Location:" . JRoute::_($url));
        
    }

    protected function checkConditions($cart, $method, $cart_prices) {

        //verificar se o usuário está logado        

        //$user = & JFactory::getUser();
        //tirei a checagem de usuario logado e o endereço pode ser tanto BT quanto ST
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
        
        $this->logado = false;
        $mainframe = JFactory::getApplication();
        $cepOrigem = $this->cepOrigem = ereg_replace('[^0-9]', '', $this->vendedor("zip", $method->virtuemart_vendor_id));
        // inseri o cep destino logo aqui
        $cepDestino = $this->cepDestino = ereg_replace('[^0-9]', '', $address["zip"]);
        /*$cepDestino = $this->cepDestino = ereg_replace('[^0-9]', '', $cart->ST["zip"] ?
                        $cart->ST["zip"] :
                        $cart->cep_simulacao
                        
        );
        */


	// lembrar de repor } na linha 396
	// tirei necessidade de logado para verificacoes.
        // if ($user->id != 0) { // se estiver logado faz as verificações
            //$this->logado = true;
            //verificar se o usuário preencheu o cep no cadastro do endereço de entrega
            if (!is_array($address)) {
                $this->redireciona('index.php?option=com_virtuemart&view=user&task=editaddresscheckout&addrtype=BT', "Escolha o local de entrega ou cadastre um novo endereço");
                return false;
            }

            //verificar se o zip está correto

            if (empty($address)) {
                $this->redireciona('index.php?option=com_virtuemart&view=user&task=editaddresscheckout&addrtype=BT', "Preencha corretamente o endere&ccedil;o de entrega");
                return false;
            }
            //verificar se está no Brasil
            if ($address["virtuemart_country_id"] != 30) {
                $mainframe->enqueueMessage('PAC erro: Somente para entregas no Brasil');
                return false;
            }
            //verificar o valor máximo
            if ($cart_prices->billTotal > 10000) {
                $mainframe->enqueueMessage("PAC erro: Excede o valor m&aacute;ximo para uso do servi&ccedil;o. (R$ 10.000,00)");
                return false;
            }



            if (strlen($cepOrigem) < 8 || strlen($cepOrigem) > 8) {
                $mainframe->enqueueMessage("PAC erro: CEP da loja &eacute; inv&aacute;lido - CEP deve ter 8 d&iacute;gitos num&eacute;ricos - " . $cepOrigem);
                return false;
            }

            if (strlen($cepDestino) < 8 || strlen($cepDestino) > 8) {
                $mainframe->enqueueMessage("PAC erro: CEP do destinat&aacute;rio &eacute; inv&aacute;lido - CEP deve ter 8 d&iacute;gitos num&eacute;ricos - " . $cepDestino);
                return false;
            }


            /* =================================
              Aplica a faixa de CEPs
              ================================== */

            //Pega os CEPs da faixa de CEPs e remove símbolos indesejados (ex. 96840150)
            $CepStart = ereg_replace('[^0-9]', '', $method->CepStart_SN);
            $CepEnd = ereg_replace('[^0-9]', '', $method->CepEnd_SN);

            if ($method->UsoFaixa_SN != "2") {
                if ($method->UsoFaixa_SN == "0") {
                    if ($cepDestino < $CepStart || $cepDestino > $CepEnd) {
                        $mainframe->enqueueMessage("A faixa de cep de entrega da loja n&atilde;o permite enviar para este endere&ccedil;o");
                        return false;
                    }
                } else {
                    if ($cepDestino >= $CepStart && $cepDestino <= $CepEnd) {
                        $mainframe->enqueueMessage("A faixa de cep de entrega da loja n&atilde;o permite enviar para este endere&ccedil;o");
                        return false;
                    }
                }
            }

            //verifica se o carrinho está vazio (se a compra for efetuado o carrinho estará vazio)
            //deve fazer essa verificação para não enviar msg de aviso na tela de confirmação do pedido
            if (!count($cart->products))
                return false;
         // }

        // Verifica se o peso está dentro dos limites
        //não precisa estar logado
        $this->Order_WeightKG = $this->peso_total($cart);

        if ($this->Order_WeightKG > 30) {
            $mainframe->enqueueMessage("PAC erro: o peso de " . $this->Order_WeightKG . " Kg excede o peso m&aacute;ximo (30 Kg).");
            return false;
        } elseif ($this->Order_WeightKG == 0) {
            $mainframe->enqueueMessage("PAC: o peso de " . $this->Order_WeightKG . " Kg sugere produto(s) para DownLoad.");
            return false;
        } elseif ($this->Order_WeightKG < 0.01) {
            $this->Order_WeightKG = 0.01;
        }

         if(empty($cepDestino))
          return false;
        


        // buscar info do site dos correios 

        $this->busca_preco_site_correios($cart, $method, $cart_prices);


        if ($this->erro_site_correios) {
            $mainframe->enqueueMessage($this->erro_site_correios);
            return false;
        }

        $orderWeight = $this->getOrderWeight($cart, $method->weight_unit);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $nbShipment = 0;
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['zip'] = 0;
            $address['virtuemart_country_id'] = 0;
        }
        $weight_cond = $this->_weightCond($orderWeight, $method);

        if (isset($address['zip'])) {
            $zip_cond = $this->_zipCond($address['zip'], $method);
        } else {
            //no zip in address data normally occurs only, when it is removed from the form by the shopowner
            //Todo for  valerie, you may take a look, maybe should be false, or configurable.
            $zip_cond = true;
        }

        if (!isset($address['virtuemart_country_id']))
            $address['virtuemart_country_id'] = 0;
        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($weight_cond AND $zip_cond) {
                return true;
            }
        }

        return false;
    }

    private function _weightCond($orderWeight, $method) {
        if ($orderWeight) {

            $weight_cond = ($orderWeight >= $method->weight_start AND $orderWeight <= $method->weight_stop
                    OR
                    ($method->weight_start <= $orderWeight AND ($method->weight_stop == 0) ));
        } else
            $weight_cond = true;
        return $weight_cond;
    }

    /**
     * Check the conditions on Zip code
     * @param int $zip : zip code
     * @param $params paremters for this specific shiper
     * @author ValÃ©rie Isaksen
     * @return string if Zip condition is ok or not
     */
    private function _zipCond($zip, $method) {
        if (!empty($zip)) {
            $zip_cond = (( $zip >= $method->zip_start AND $zip <= $method->zip_stop )
                    OR
                    ($method->zip_start <= $zip AND ($method->zip_stop == 0) ));
        } else {
            $zip_cond = true;
        }
        return $zip_cond;
    }

    /*
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author ValÃ©rie Isaksen
     *
     */
    function plgVmOnStoreInstallShipmentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the shipment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author ValÃ©rie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheck($psType, VirtueMartCart $cart) {
        return $this->OnSelectCheck($psType, $cart);
    }

    /**
     * plgVmDisplayListFE
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEShipment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
     * plgVmonSelectedCalculatePrice
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    public function plgVmonSelectedCalculatePriceShipment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelected
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedShipment(VirtueMartCart $cart, array $cart_prices = array()) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers

      public function plgVmOnCheckoutCheckData($psType, VirtueMartCart $cart) {
      return null;
      }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrint($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk

      public function plgVmOnUpdateOrder($psType, $_formData) {
      return null;
      }
     */
    /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk

      public function plgVmOnUpdateOrderLine($psType, $_formData) {
      return null;
      }
     */
    /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

      public function plgVmOnEditOrderLineBE($psType, $_orderId, $_lineId) {
      return null;
      }
     */
    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

      public function plgVmOnShowOrderLineFE($psType, $_orderId, $_lineId) {
      return null;
      }
     */

    /**
     * plgVmOnResponseReceived
     * This event is fired when the  method returns to the shop after the transaction
     *
     *  the method itself should send in the URL the parameters needed
     * NOTE for Plugin developers:
     *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
     *
     * @param int $virtuemart_order_id : should return the virtuemart_order_id
     * @param text $html: the html to display
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     * @author Valerie Isaksen
     *

      function plgVmOnResponseReceived($psType, &$virtuemart_order_id, &$html) {
      return null;
      }
     */
    function plgVmDeclarePluginParamsShipment($name, $id, &$data) {

        return $this->declarePluginParams('shipment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsShipment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

}

// No closing tag
