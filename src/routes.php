<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/', function($request, $response) {
	return $response->withRedirect($this->router->pathFor('suppliers'));
});

$app->group('/projects', function() {
	$this->get('', 'ProjectsController');
	$this->post('/{action}', 'ProjectsController:action');
})->add($hostAuth);

$app->group('/login', function() use ($sessionAuth, $companyAuth) {
	$this->get('', 'LoginController')->setName('login');
	$this->post('', 'LoginController:login');
	$this->post('/logout', 'LoginController:logout');
	
	// password recovery
	$this->get('/recovery', 'LoginController:passwordRecovery')->setName('password_recovery');
	$this->post('/recovery', 'LoginController:sendTemporaryPassword');
	
	// companies options
	$this->group('/companies', function() {
		$this->get('/selection', 'LoginController:companiesSelection')->setName('companies_selection');
		$this->post('/selected/{company_id}', 'LoginController:companySelected');
	})->add($sessionAuth);
	
	// projects options
	$this->group('/projects', function() {
		$this->get('/selection', 'LoginController:projectsSelection')->setName('projects_selection');
		$this->post('/selected/{project_id}', 'LoginController:projectSelected');
	})->add($companyAuth)->add($sessionAuth);
})->add($hostAuth);

// home
$app->group('/home', function() {
	$this->get('', 'HomeController')->setName('home');
})->add($appAuth)->add($sessionAuth)->add($hostAuth);

// currencies
$app->group('/currencies', function() {
	$this->get('', 'CurrenciesController');
	$this->post('/{action}[/{exchange}]', 'CurrenciesController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// exchange
$app->group('/exchange', function() {
	$this->get('', 'ExchangeController');
	$this->post('/{action}', 'ExchangeController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// ************* //
// **  SALES  ** //
// ************* //

// customers
$app->group('/customers', function() {
	$this->get('', 'CustomersController')->setName('customers');
	$this->post('/{action}[/{customer_id}]', 'CustomersController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// sales
$app->group('/sales', function() {
	// reports
	$this->get('/report', 'SalesReportsController');
	$this->post('/report', 'SalesReportsController:report');
	
	// pivot
	$this->get('/pivot', 'SalesReportsController:pivot');
	$this->post('/pivot', 'SalesReportsController:pivotData');
	
	// query
	$this->get('/query', 'SalesController:query');
	
	// general
	$this->get('[/{headerId}]', 'SalesController');
	$this->post('/{action}[/{headerId}]', 'SalesController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// collections
$app->group('/collections', function(){
	// query
	$this->get('/query', 'CollectionsController:query');
	
	// general
	$this->get('[/{headerId}]', 'CollectionsController');
	$this->post('/{action}[/{headerId}]', 'CollectionsController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// ***************** //
// **  PURCHASES  ** //
// ***************** //

// suppliers
$app->group('/suppliers', function() {
	$this->get('', 'SuppliersController')->setName('suppliers');
	$this->post('/{action}[/{supplier_id}]', 'SuppliersController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// purchases
$app->group('/purchases', function() {
	// reports
	$this->get('/report', 'PurchasesReportsController');
	$this->post('/report', 'PurchasesReportsController:report');

	// delivery
	$this->get('/delivery', 'PurchasesDeliveryController');
	$this->post('/delivery/{action}[/{detailId}]', 'PurchasesDeliveryController:action');
	
	// query
	$this->get('/query', 'PurchasesController:query');
	
	// general
	$this->get('[/{headerId}]', 'PurchasesController');
	$this->post('/{action}[/{headerId}]', 'PurchasesController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// payments
$app->group('/payments', function() {
	// query
	$this->get('/query', 'PaymentsController:query');
	
	// general
	$this->get('[/{headerId}]', 'PaymentsController');
	$this->post('/{action}[/{headerId}]', 'PaymentsController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// ************ //
// **  CASH  ** //
// ************ //

// cash
$app->group('/cash', function() {
	// reports
	$this->get('/report', 'CashReportsController');
	$this->post('/report', 'CashReportsController:report');
	
	// pivot
	$this->get('/pivot', 'CashReportsController:pivot');
	$this->post('/pivot', 'CashReportsController:pivotData');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// banks
$app->group('/banks', function() {
	$this->get('', 'BanksController');
	$this->post('/{action}', 'BanksController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// banks accounts
$app->group('/banks_accounts', function() {
	$this->get('', 'BanksAccountsController');
	$this->post('/{action}', 'BanksAccountsController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// products
$app->group('/products', function() {
	$this->get('', 'ProductsController');
	$this->post('/{action}', 'ProductsController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// products state
$app->group('/products_state', function() {
	$this->get('', 'ProductsStateController');
	$this->post('/{action}', 'ProductsStateController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// products units
$app->group('/products_units', function() {
	$this->get('', 'ProductsUnitsController');
	$this->post('/{action}', 'ProductsUnitsController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);

// ******************* //
// **  INVESTMENTS  ** //
// ******************* //

// investors
$app->group('/investors', function() {
	$this->get('', 'InvestorsController')->setName('investors');
	$this->post('/{action}[/{investor_id}]', 'InvestorsController:action');
})->add($profileAdminAuth)->add($appAuth)->add($sessionAuth)->add($hostAuth);