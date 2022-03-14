<?php

use Support\Pages;



//===============================BENCHMARKS============================================
define('MEMORY', memory_get_usage());
define('PHP_EXEC_START', microtime(true));


define('PLACEHOLDER_IMAGE', 'https://cwflow.apiloc.ru/placeholder.jpg');


define('ELEMENT_COUNT', 50);

define("DB_ANALYSIS_NOTES", 'analysis_notes');
define("DB_GENERAL_INFORMATION", 'app_general_information');
define("DB_SYSTEM_UPDATES", 'app_system_updates');
define("DB_MONEY_CURRENCIES", 'app_money_currencies');

//==================ПРОДУКТЫ,ТОЧКИ,ПАРТНЕРЫ==============================================
define("DB_ARCHIVE", 'app_archive');
define("DB_COLORS", 'app_colors');
define("DB_ITEMS", 'app_items');
define("DB_ITEMS_CATEGORY", 'app_items_category');
define("DB_PARTNER", 'app_partner');
define("DB_PARTNERS_TOKEN", 'app_partners_token');
define("DB_PARTNER_POINTS", 'app_partner_points');
define("DB_PARTNER_TRANSACTIONS", 'app_partner_transactions');
define("DB_POINT_ITEMS", 'app_point_items');
define("DB_POINT_ITEMS_TMP", 'app_point_items_tmp');
define("DB_PRODUCTS", 'app_products');
define("DB_POINT_CATEGORIES", 'app_point_categories');
define("DB_PRODUCT_CATEGORIES", 'app_product_categories');
define("DB_PRODUCT_COMPOSITION", 'app_product_composition');
define("DB_PRODUCT_PRICES", 'app_product_prices');
define("DB_TECHNICAL_CARD", 'app_technical_card');
define("DB_POINTS_TOKEN", 'app_points_token');
define("DB_MENU_PRODUCTS", 'app_menu_products');
define("DB_MENU_CATEGORIES", 'app_menu_categories');
define("DB_CITIES", 'app_cities');
define("DB_CHECKLIST", 'app_checklist');
define("DB_PARTNER_ORDERS_INCOME", 'app_partner_orders_income');
define("DB_PARTNER_ORDERS_SPENDING", 'app_partner_orders_spending');
define("DB_PARTNER_ORDERS_REPORT", 'app_partner_orders_report');
define("DB_SERVICES", 'app_services');
define("DB_AUTO_PRICE_DOCUMENT", 'app_auto_price_document');
define("DB_AUTO_PRICE_POSITION", 'app_auto_price_position');
//=======================================================================================

//==================СКЛАД================================================================
define("DB_SUPPLIERS", 'app_suppliers');
define("DB_SUPPLIES", 'app_supplies');
define("DB_SUPPLY_ITEMS", 'app_supply_items');
define("DB_REMOVALS", 'app_removals');
define("DB_REMOVAL_CAUSES", 'app_removal_causes');
define("DB_REMOVAL_ITEMS", 'app_removal_items');
define("DB_INVENTORY", 'app_inventory');
define("DB_INVENTORY_ITEMS", 'app_inventory_items');
define("DB_INVENTORY_ITEMS_TMP", 'app_inventory_items_tmp');
//=======================================================================================

//==================ПРОИЗВОДСТВО=========================================================
define("DB_PRODUCTIONS", 'app_productions');
define("DB_PRODUCTION_ITEMS", 'app_production_items');
define("DB_PRODUCTION_PRODUCTS", 'app_production_products');
define("DB_PRODUCTIONS_COMPOSITION", 'app_productions_composition');
define("DB_PRODUCTION_ITEMS_MOVING", 'app_production_items_moving');
//=======================================================================================

//==================ДОСТУПЫ==============================================================
define("DB_EMPLOYEES", 'app_employees');
define("DB_EMPLOYEE_SHIFTS", 'app_employee_shifts');
define("DB_POSITIONS", 'app_positions');
define("DB_POINTS_PLAN", 'app_points_plan');
//=======================================================================================

//=================ТРАНЗАКЦИИ============================================================
define("DB_TRANSACTIONS", 'app_transactions');
define("DB_TRANSACTION_ITEMS", 'app_transaction_items');
define("DB_REFUND_REQUESTS", 'app_refund_requests');
//=======================================================================================

//==================ФИНАНСЫ==============================================================
define("DB_FINANCES_CATEGORIES", 'app_finances_categories');
define("DB_FINANCES_TRANSACTIONS", 'app_finances_transactions');
//=======================================================================================

//===================КЛИЕНТЫ,БОНУСЫ,АКЦИИ================================================
define("DB_CLIENTS", 'app_clients');
define("DB_CLIENTS_GROUP", 'app_clients_group');
define("DB_PROMOTIONS", 'app_promotions');
define("DB_PROMOTION_TECHNICAL_CARDS", 'app_promotion_technical_cards');
define("DB_TECHNICAL_CARD_DISCOUNT", 'app_technical_card_discount');
define("DB_PROMOTIONAL_CODES", 'app_promotional_codes');
define("DB_PROMOTION_GIFTS", 'app_promotion_gifts');
define("DB_CLIENT_PROMOTION_GIFTS", 'app_client_promotion_gifts');
define("DB_PROMOTION_GIFT_REQUESTS", 'app_promotion_gift_requests');
define("DB_LOYALCLIENT_REQUEST_QUEUE", 'app_loyalclient_request_queue');
//=======================================================================================

//====================АРХИВ==============================================================
define("DB_ARCHIVE_PRODUCT_CATEGORIES", 'app_archive_product_categories');
//=======================================================================================


define('LC_API_KEY', "418c250fab99a175f93ebc5e4c6dd9ac4d763af529d56d965446137b1891a225");



?>