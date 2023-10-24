#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\Streamhandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Logger;
use GuzzleHttp\Client;

$getOpt = new \GetOpt\GetOpt([

    \GetOpt\Option::create('d', 'debug', \GetOpt\GetOpt::NO_ARGUMENT)
        ->setDescription('Enable debug logging'),

    \GetOpt\Option::create('b', 'browse', \GetOpt\GetOpt::REQUIRED_ARGUMENT)
        ->setDescription('Browse data'),

    \GetOpt\Option::create('c', 'customers', \GetOpt\GetOpt::NO_ARGUMENT)
        ->setDescription('Show list of customers'),

    \GetOpt\Option::create('o', 'offices', \GetOpt\GetOpt::NO_ARGUMENT)
        ->setDescription('Show list of offices'),

    \GetOpt\Option::create('v', 'vat', \GetOpt\GetOpt::NO_ARGUMENT)
        ->setDescription('Show list of vat codes'),
]);
try {
    try {
        $getOpt->process();
    } catch (\GetOpt\Missing $exception) {
        // catch missing exceptions if help is requested
        if (!$getOpt->getOption('help')) {
            throw $exception;
        }
    }
} catch (\GetOpt\ArgumentException $exception) {
    file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
    echo PHP_EOL . $getOpt->getHelpText();
    exit(1);
}

$level = Level::Info;
$last_argc = 0;
if ($getOpt->getOption('debug')) {
  $level = Level::Debug;
}

$log = new Monolog\Logger('sync');
$handler = new StreamHandler('php://stderr', $level);
$handler->setFormatter(new LineFormatter(null, null, true, true));
$log->pushHandler($handler);

$config_file = $_SERVER['HOME'] . '/.breww_twinfield_php.toml';
try {
  $config = \Yosymfony\Toml\Toml::ParseFile($config_file);
} 
catch (Exception $e) {
  if (!file_exists($config_file)) {
    $log->error("There is no config file $config_file");
  }
  else {
    $log->error($e->getMessage());
  }
  $log->info('The configuration file should contain the following:');
  $log->info('');
  $log->info('[settings]');
  $log->info('  url          = "<where-this-is-running>"');
  $log->info('');
  $log->info('[twinfield]');
  $log->info('  clientId     = "<application_id>"');
  $log->info('  clientSecret = "<application_secret>"');
  $log->info('  redirectUri  = "<redirect_uri>"');
  $log->info('  refreshToken = "<token>"');
  $log->info('  officeCode   = "<twinfield_office_code>"');
  $log->info('  salesCode    = "VRK"');
  $log->info('  # Map BREWW tax_rate_decimal to Twinfield taxrate codes. No provision for EU/NonEU rates yet');
  $log->info('  vatCodes     = { "0.00" = "VN", "0.09" = "VL", "0.21" = "VH" }');
  $log->info('');
  $log->info('[breww]');
  $log->info('  token           = "<breww_application_token>"');
  $log->info('  dimension_regex = "GBTF-(\d{4})(-|$)"');
  $log->info('  dimension_group = "1"');
  exit(1);
}

$BREWW_CLIENT = new Client([
  'base_uri' => 'https://breww.com/api/',
  'timeout'  => 30.0,
]);

$URL_CLIENT = new Client([
  'base_uri' => $config['settings']['url'],
  'timeout'  => 30.0,
]);

function breww_get($endpoint)
{
  global $BREWW_CLIENT, $breww_token;

  $response = $BREWW_CLIENT->get($endpoint, [
    'headers' => [ 'Authorization' => "Bearer $breww_token" ]
  ]);

  if ($response->getStatusCode() != 200) {
    $log->error('[' . $endpoint . ']: ' . $response->getStatusCode());
    throw new Exception('Brew responded with error ' .  $response->getStatusCode());
  }
  $data = json_decode($response->getBody());
  return $data;
}

function EUR($value) : \Money\Money
{
  return \Money\Money::EUR(intval($value * 100));
}

function get_short_url($url)
{
  global $log;
  global $URL_CLIENT;

  $response = $URL_CLIENT->get('url?new=' . $url);

  if ($response->getStatusCode() != 200) {
    $log->error($response->getBody());
    throw new Exception('Our URL shortener responded with error ' .  $response->getStatusCode());
  }
  $short = $response->getBody();
  $log->debug("URL $url shortened to $short");
  return $short;
}

/**
 * Get customer info by customer name.
 */
function browse_data($connection, $code) : array
{
  global $log;

  $connector = new \PhpTwinfield\ApiConnectors\BrowseDataApiConnector($connection);
  $browseDefinition = $connector->getBrowseDefinition($code);
  $browseFields = $connector->getBrowseFields();

  $columns = array();
  foreach ($browseDefinition->getColumns() as $column) {
    $log->info($column->getField() . '/' . $column->getLabel());
    if ($column->getLabel() !== null) {
      $column = (new \PhpTwinfield\BrowseColumn())
        ->setField($column->getField())
        ->setLabel($column->getLabel())
        ->setVisible(true);
      $columns[] = $column;
    }
  }
  $browseData = $connector->getBrowseData($code, $columns, array());
  print_r($browseData);
}

/**
 * Check if the existing transaction_number can be found in Twinfield.
 * If so, return a list with the Twinfield transaction number and the current total value.
 * If not, return a list with null values.
 */
function get_existing_transaction($connection, $invoice_id) : array
{
  global $log;
  global $twinfield_config;

  $connector = new \PhpTwinfield\ApiConnectors\BrowseDataApiConnector($connection);

  $browseDefinition = $connector->getBrowseDefinition('100');

  $columns = array();
  $columns[] = (new \PhpTwinfield\BrowseColumn())
                ->setField('fin.trs.head.code')
                ->setLabel('salesCode')
                ->setOperator(\PhpTwinfield\Enums\BrowseColumnOperator::EQUAL())
                ->setFrom($twinfield_config['salesCode']);
  $columns[] = (new \PhpTwinfield\BrowseColumn())
                ->setField('fin.trs.line.invnumber')
                ->setLabel('invoice number')
                ->setOperator(\PhpTwinfield\Enums\BrowseColumnOperator::EQUAL())
                ->setFrom($invoice_id);
  $columns[] = (new \PhpTwinfield\BrowseColumn())
                ->setField('fin.trs.head.number')
                ->setLabel('tx')
                ->setVisible(true); // Visible means: return in result set
  $columns[] = (new \PhpTwinfield\BrowseColumn())
                ->setField('fin.trs.line.valuesigned')
                ->setLabel('value')
                ->setVisible(true);
  // $sortFields[] = new \PhpTwinfield\BrowseSortField('fin.trs.head.code');
  $browseData = $connector->getBrowseData('100', $columns, array());

  if ($browseData->getTotal() >= 1) {
    $rows = $browseData->getRows();
    $cells = $rows[0]->getCells();
    $tx = intval($cells[0]->getValue());
    $value = $cells[1]->getValue();
    $log->debug("Invoice $invoice_id already exists as TX $tx with total value $value");
    return array($tx, $value);
  }
  return array(null, null);
}

/**
 * Get customer info by customer name.
 */
function get_customer_data($connection, $name) : array
{
  global $log;
  global $twinfield_config;

  $connector = new \PhpTwinfield\ApiConnectors\BrowseDataApiConnector($connection);

  $browseDefinition = $connector->getBrowseDefinition('100');

  $columns = array();
  $columns[] = (new \PhpTwinfield\BrowseColumn())
                ->setField('fin.trs.head.code')
                ->setLabel('salesCode')
                ->setOperator(\PhpTwinfield\Enums\BrowseColumnOperator::EQUAL())
                ->setFrom($twinfield_config['salesCode']);
  $columns[] = (new \PhpTwinfield\BrowseColumn())
                ->setField('fin.trs.line.invnumber')
                ->setLabel('invoice number')
                ->setOperator(\PhpTwinfield\Enums\BrowseColumnOperator::EQUAL())
                ->setFrom($invoice_id);
  $columns[] = (new \PhpTwinfield\BrowseColumn())
                ->setField('fin.trs.head.number')
                ->setLabel('tx')
                ->setVisible(true); // Visible means: return in result set
  $columns[] = (new \PhpTwinfield\BrowseColumn())
                ->setField('fin.trs.line.valuesigned')
                ->setLabel('value')
                ->setVisible(true);
  // $sortFields[] = new \PhpTwinfield\BrowseSortField('fin.trs.head.code');
  $browseData = $connector->getBrowseData('100', $columns, array());
}

/**
 * Create a new customer.
 */
function create_customer($connection, $order)
{
  global $log;
  global $twinfield_config;
  global $customer_lookup;

  $customer = breww_get('customers-suppliers/' . $order->customer->id . '/');

  $log->info('Creating customer ' . $order->customer->name);
  $log->debug(print_r($customer, true));

  $customer_lookup[$order->customer->name] = 0;
  # exit(1);
}

/*
 * Get a list of all customers in Twinfield.
 */
function create_customer_lookup($customerFactory) : array 
{
  global $log;
  global $office;

  $customers = $customerFactory->listAll($office);
  $customer_lookup = array();
  foreach($customers as $code => $customer) {
    $name = $customer['name'];
    if (array_key_exists($name, $customer_lookup) || true) {
      // Fill the non-unique customer array value with a further lookup by billing address
      $val = array(); // $val = $customer_lookup[$name]; 
      if (!is_array($val))
      {
        $val[$val] = null;
      }
      $val[$code] = null;
      $customer_lookup[$name] = $val;
    }
    else
    {
      $customer_lookup[$name] = $code;
    }
  }
  $log->info('There are ' . count($customer_lookup) . ' customers in office ' . $office->getCode());
  return $customer_lookup;
}

function get_customer_code($customerFactory, &$customer_lookup, $order)
{
  global $log;
  global $office;

  $name = $order->customer->name;

  if (!array_key_exists($name, $customer_lookup)) {
    // $log->notice('New customer ' . $name);
    // create_customer($connection, $order);
    throw new Exception("Customer '$name' does not exist in TwinField");
  }

  $val = $customer_lookup[$name];

  if (is_array($val)) {
    $desired_postal_code = $order->billing_address->postal_code;
    $log->debug("Locate customer $name with multiple addresses, match " . $desired_postal_code);
    foreach ($val as $code => $customer) {
      if (!isset($customer)) {
        $customer = $customerFactory->get($code, $office);
        $log->debug("Retrieved customer $code");
        $customer_lookup[$name][$code] = $customer;
      }
      foreach ($customer->getAddresses() as $address) {
        if ($address->getType() == 'invoice' && $address->getPostCode() == $desired_postal_code) {
          $log->debug("Match customer $name with duplicate names on postal_code " . $desired_postal_code . " => " . $code);
          $val = $code;
          break 2;
        }
      }
    }
    if (is_array($val)) {
      $log->error("Customer $name cannot be found with postal_code " . $desired_postal_code);
      exit(1);
    }
  }

  if (!is_numeric($val)) {
    $log->error("Unexpected customer code: " . print_r($val, true));
    exit(1);
  }

  return $val;
}

$breww_product_dimensions = array();

/**
 * Retrieve the Twinfield dimension that needs to be set
 * on the sales transaction. 
 *
 * We do this by retrieving the tags on the Breww product sold,
 * then filtering by regex and extracting one group from the
 * regex. If this results in an empty string, refuse it.
 */
function get_dimension($connection, $item)
{
  global $log;
  global $breww_product_dimensions;
  global $config;


  if (array_key_exists($item->product, $breww_product_dimensions)) {
    return $breww_product_dimensions[$item->product];
  }

  $regex = $config['breww']['dimension_regex'];
  $group = $config['breww']['dimension_group'];

  if (empty($regex)) {
    $log->error("Please set 'dimension_regex' in config file under 'breww'.");
    exit(1);
  }
  if (empty($group)) {
    $log->error("Please set 'dimension_group' in config file under 'breww'.");
    exit(1);
  }

  $product = breww_get('products/' . $item->product . '/');
  $tags = '';
  foreach ($product->tags as $tag) {
    $output_array = array();
    if (preg_match('/' . $regex . '/', $tag->name, $output_array)) {
      if (!empty($output_array[$group])) {
        $log->info("Dimension for product $item->product '$item->product_name' is $output_array[$group]");
        $breww_product_dimensions[$item->product] = $output_array[$group];
        return $output_array[$group];
      }
    }
    $tags = $tags . ', ' . $tag->name;
  }

  $tags = substr($tags, 2);
  throw new Exception("Product '$item->product_name' No tag matching regex $regex found in tags: $tags");
}

$twinfield_config = $config['twinfield'];
$breww_token = $config['breww']['token'];

$provider    = new \PhpTwinfield\Secure\Provider\OAuthProvider([
    'clientId'     => $twinfield_config['clientId'],
    'clientSecret' => $twinfield_config['clientSecret'],
    'redirectUri'  => $twinfield_config['redirectUri']
]);
$refreshToken    = $twinfield_config['refreshToken'];
$office          = \PhpTwinfield\Office::fromCode($twinfield_config['officeCode']);
$connection      = new \PhpTwinfield\Secure\OpenIdConnectAuthentication($provider, $refreshToken, $office);
$customerFactory = new \PhpTwinfield\ApiConnectors\CustomerApiConnector($connection);

if ($getOpt->getOption('browse')) {
  browse_data($connection, $getOpt->getOption('browse'));
  exit(0);
}

if ($getOpt->getOption('offices')) {
  $officeConnector = new \PhpTwinfield\ApiConnectors\OfficeApiConnector($connection);
  $offices = $officeConnector->listAll();
  print_r($offices);
  exit(0);
}

if ($getOpt->getOption('vat')) {
  print_r($vatCodes);
  foreach ($vatCodes as $vatCode) {
    $vatDetail = $vatCodeFactory->get($vatCode->getCode(), $office);
    print_r($vatDetail);
  }
  exit(0);
}

if ($getOpt->getOption('customers')) {
  $customers = $customerFactory->listAll($office);
  print_r($customers);
  exit(0);
}

$log->info('Starting sync');

$transactionFactory = new \PhpTwinfield\ApiConnectors\TransactionApiConnector($connection);

$vatCodeFactory = new \PhpTwinfield\ApiConnectors\VatCodeApiConnector($connection);
$vatCodes = $vatCodeFactory->listAll();
$vatCodeLookup = $twinfield_config['vatCodes'];
$customer_lookup = create_customer_lookup($customerFactory);

$orders = breww_get('orders/');
$log->info(('There are ' . strval($orders->count) . ' confirmed orders in Breww'));
while (true) {
  foreach ($orders->results as $order) {
    if ($order->order_status == 'Invoiced') {
      continue;
    }

    $log->debug(print_r($order, true));
    $order_nr = 'BREWW_KEES1_' . strval($order->number);

    try {

      // Check if this is an update of an existing sales transaction.
      list ($tx_id, $old_value) = get_existing_transaction($connection, $order_nr);
      if ($old_value === $order->total)
      {
        $log->info("Skip existing transaction " . $order_nr . " -> $tx_id @ $old_value");
        continue;
      }

      $tf_sale = new \PhpTwinfield\SalesTransaction();
      if ($tx_id !== null) {
        $log->info("Updating transaction " . $order_nr . "-> $tx_id from EUR $old_value to " . $order->total);
        $tf_sale->setNumber($tx_id);
      }
      else {
        $log->info('Processing transaction ' . $order_nr . ' with status ' . $order->order_status . ' due date ' . $order->due_date);
      }

      $tf_customer = new \PhpTwinfield\Customer();
      $customer_code = get_customer_code($customerFactory, $customer_lookup, $order);
      $tf_customer->setCode($customer_code);
      $tf_customer->setOffice($office);

      $tf_sale->setInvoiceNumber($order_nr);
      $tf_sale->setOffice($office);
      $tf_sale->setCode($twinfield_config['salesCode']);
      $tf_sale->setDestiny(\PhpTwinfield\Enums\Destiny::TEMPORARY());
      $tf_sale->setRaiseWarning(false);
      $tf_sale->setDueDate(date_create_immutable_from_format('Y-m-d', $order->due_date));
      $tf_sale->setDate(date_create_immutable_from_format('Y-m-d', $order->issue_date));
      $tf_sale->setAutoBalanceVat(true);
      $tf_sale->setFreeText3(get_short_url($order->pdf_url));

      $id = 1;

      // Twinfield TOTAL line
      $tf_sales_line = new \PhpTwinfield\SalesTransactionLine();
      $tf_sales_line->setLineType(\PhpTwinfield\Enums\LineType::TOTAL());
      $tf_sales_line->setId($id);
      $id = $id + 1;
      $tf_sales_line->setDim1('1300'); // TODO ' accounts receivable balance account.' 
      $tf_sales_line->setDim2($customer_code);
      $tf_sales_line->setValue(EUR($order->total));
      $tf_sale->addLine($tf_sales_line);
      /*
                        [quantity] => 3
                        [requires_delivery] => 1
                        [shown_on_invoice] => 1
                        [tax_rate_decimal] => 0.21
                        [total_amount] => 111.16
                        [total_discount_percentage] => 0
                        [total_discount_value] => 0
                        [value] => 91.87
                        [vat] => 19.29
                    )
       */

      // Twinfield DETAIL lines
      foreach ($order->order_lines as $item) {
        $log->debug($item->product_name . ' @ ' . $item->quantity . ' * ' . $item->product_original_unit_value);

        $tf_sales_line = new \PhpTwinfield\SalesTransactionLine();
        $tf_sales_line->setLineType(\PhpTwinfield\Enums\LineType::DETAIL());
        $tf_sales_line->setId($id);
        $id = $id + 1;
        $tf_sales_line->setDim1(get_dimension($connection, $item));
        $tf_sales_line->setValue(EUR($item->value));

        if (!array_key_exists(strval($item->tax_rate_decimal), $vatCodeLookup))
        {
          $log->error("Do not know how to handle VAT rate $item->tax_rate_decimal");
          exit(2);
        }
        $vatCode = $vatCodeLookup[strval($item->tax_rate_decimal)];
        $tf_sales_line->setVatCode($vatCode);

        $tf_sale->addLine($tf_sales_line);
      }

      $result = $transactionFactory->send($tf_sale);
      $log->debug(print_r($result, true));
    } catch (Exception $e) {
      $log->error($e->getMessage());
      $log->error("Skipping transaction $order_nr because of above error");
    }
  }

  if (empty($orders->next)) {
    break;
  }
  $orders = breww_get($orders->next);
  $log->info(('There are ' . strval($orders->count) . ' more confirmed orders in Breww'));
}

$log->info('Done');


?>
